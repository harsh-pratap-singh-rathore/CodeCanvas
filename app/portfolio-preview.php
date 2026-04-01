<?php
require_once __DIR__ . '/../config/bootstrap.php';
// Resolve which template to load in the inner iframe
// The parent (project-editor.php) passes ?template=BASE_URL/templates/developer/code.html
$rawTemplate = $_GET['template'] ?? (BASE_URL . '/templates/developer/code.html');

// Security: only allow paths within the project root (no directory traversal)
$templateSrc = htmlspecialchars($rawTemplate, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Portfolio Preview</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
      width: 100%;
      height: 100%;
      overflow: hidden;
      background: #060606;
    }
    #template-frame {
      width: 100%;
      height: 100%;
      border: none;
      display: block;
    }
    #loader {
      position: fixed;
      inset: 0;
      background: #060606;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px;
      z-index: 999;
      font-family: -apple-system, BlinkMacSystemFont, 'Inter', system-ui, sans-serif;
      color: rgba(255, 255, 255, 0.4);
      font-size: 11px;
      letter-spacing: 0.15em;
    }
    #loader .spinner {
      width: 28px;
      height: 28px;
      border: 2px solid rgba(255, 255, 255, 0.1);
      border-top-color: rgba(255, 255, 255, 0.6);
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div id="loader">
    <div class="spinner"></div>
    LOADING PREVIEW...
  </div>

  <iframe
    id="template-frame"
    src="<?= $templateSrc ?>"
    sandbox="allow-scripts allow-same-origin"
  ></iframe>

  <script src="<?= BASE_URL ?>/public/assets/js/navigation.js"></script>
  <script>
    const BASE_URL = <?= json_encode(BASE_URL) ?>;
    const frame = document.getElementById('template-frame');
    const loader = document.getElementById('loader');

    // ── UI: Hide loader once template loads (Priority 1)
    frame.addEventListener('load', () => {
      setTimeout(() => {
        loader.style.opacity = '0';
        loader.style.transition = 'opacity 0.4s';
        setTimeout(() => loader.style.display = 'none', 400);
      }, 300);
    });

    // ── Nav: guard (standalone only) + persist preview path
    try {
        (function () {
            var templateParam = <?= json_encode($rawTemplate !== (BASE_URL . '/templates/developer/code.html') ? $rawTemplate : null) ?>;
            if (typeof NavManager !== 'undefined') {
                // Run guard — redirects if no template available in standalone mode
                NavManager.guardPreview(templateParam);
                // Store the current template path in sessionStorage for refresh recovery
                NavManager.setPreview(<?= json_encode($rawTemplate) ?>);
                // Push history state + install back guard (standalone only)
                if (window.parent === window) {
                    NavManager.pushState('preview', { template: <?= json_encode($rawTemplate) ?> });
                    NavManager.installBackGuard('preview');
                }
            } else {
                console.warn('NavManager not loaded');
            }
        }());
    } catch (e) {
        console.error('NavManager error:', e);
    }

    // ── Core DOM access ─────────────────────────────────────
    function getDoc() {
      try { return frame.contentDocument || frame.contentWindow.document; }
      catch (e) { console.warn('Preview: cannot access frame document', e); return null; }
    }

    /**
     * Update a single field in the template DOM.
     */
    function updateField(selector, value, attr = 'innerText') {
      const doc = getDoc();
      if (!doc) return false;
      const el = doc.querySelector(selector);
      if (!el) return false;
      
      if (attr === 'innerText') {
        el.innerText = value;
      } else if (attr === 'innerHTML') {
        el.innerHTML = value;
      } else if (attr === 'color') {
        el.style.color = value;
      } else if (attr === 'bg-color') {
        el.style.backgroundColor = value;
      } else {
        el.setAttribute(attr, value);
      }
      return true;
    }

    /**
     * Update all elements matching selector.
     */
    function updateAllFields(selector, value, attr = 'innerText') {
      const doc = getDoc();
      if (!doc) return 0;
      const els = doc.querySelectorAll(selector);
      els.forEach(el => {
        if (attr === 'innerText') el.innerText = value;
        else if (attr === 'innerHTML') el.innerHTML = value;
        else if (attr === 'color') el.style.color = value;
        else if (attr === 'bg-color') el.style.backgroundColor = value;
        else el.setAttribute(attr, value);
      });
      return els.length;
    }

    function updateRepeaterGeneric(parentKey, items) {
      const doc = getDoc();
      if (!doc) return;
      const container = doc.querySelector(`[data-edit-repeat="${parentKey}"]`);
      if (!container) return;

      if (!container._template && container.children.length > 0) {
        container._template = container.children[0].cloneNode(true);
      }
      if (!container._template) return;

      container.innerHTML = '';
      items.forEach(item => {
        const clone = container._template.cloneNode(true);
        
        // ── Advanced Key Mapping ──
        Object.entries(item).forEach(([k, v]) => {
          // Find target using fuzzy matching: k, parent_k, parent-k
          const selectors = [
            `[data-edit="${k}"]`, `[data-edit="${parentKey}_${k}"]`, `[data-edit="${parentKey}-${k}"]`,
            `[data-edit-img="${k}"]`, `[data-edit-img="${parentKey}_${k}"]`, `[data-edit-img="${parentKey}-${k}"]`,
            `[data-edit-link="${k}"]`, `[data-edit-link="${parentKey}_${k}"]`, `[data-edit-link="${parentKey}-${k}"]`
          ];
          
          selectors.forEach(sel => {
            const targets = clone.querySelectorAll(sel);
            targets.forEach(el => {
              if (sel.includes('img')) {
                el.src = v || '';
              } else if (sel.includes('link')) {
                el.href = v || '#';
              } else {
                el.innerHTML = escSafe(v || '');
              }
            });
          });

          // Also do simple string replacement for {{k}} if they exist
          clone.innerHTML = clone.innerHTML.replace(new RegExp(`{{${k}}}`, 'g'), escSafe(v || ''));
        });

        // Special case for Material Icons
        clone.querySelectorAll('.material-icons').forEach(el => {
           if (item.icon) el.innerText = item.icon;
        });

        container.appendChild(clone);
      });
    }

    function updateSkills(skills) {
      updateRepeaterGeneric('skills', skills);
    }

    function updateProjects(projects) {
      updateRepeaterGeneric('projects', projects);
    }

    /**
     * Update typing animation words.
     */
    function updateTypingWords(words) {
      if (!Array.isArray(words) || words.length === 0) return;
      try {
        const win = frame.contentWindow;
        if (!win) return;
        if (typeof win.updateTypingWords === 'function') {
          win.updateTypingWords(words);
        } else if (win.words) {
          win.words.length = 0;
          words.forEach(w => win.words.push(w));
        }
      } catch (e) {
        console.warn('Preview: updateTypingWords failed', e);
      }
    }

    // ── Safe escape helpers ──────────────────────────────────
    function escSafe(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }
    function escAttr(str) {
      return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ── postMessage API ──────────────────────────────────────
    window.addEventListener('message', (event) => {
      const data = event.data;
      if (!data || !data.type) return;

      let result = { type: 'ack', id: data.id };

      switch (data.type) {
        case 'SCROLL_TO':
          const doc = getDoc();
          if (!doc) { result.ok = false; break; }
          const el = doc.querySelector(data.selector);
          if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Add a temporary highlight effect
            const oldOutline = el.style.outline;
            const oldTransition = el.style.transition;
            el.style.transition = 'outline 0.3s ease-in-out';
            el.style.outline = '3px solid #0ea5e9'; // Blue highlight
            setTimeout(() => {
                el.style.outline = '3px solid transparent';
                setTimeout(() => {
                    el.style.outline = oldOutline;
                    el.style.transition = oldTransition;
                }, 300);
            }, 1000);
            
            result.ok = true;
          } else {
            result.ok = false;
          }
          break;
        case 'UPDATE_FIELD':
          result.ok = updateField(data.selector, data.value, data.attr || 'innerText');
          break;
        case 'UPDATE_ALL':
          result.count = updateAllFields(data.selector, data.value, data.attr || 'innerText');
          result.ok = result.count > 0;
          break;
        case 'UPDATE_RESUME': {
          const doc = getDoc();
          const resumeLink = doc ? doc.querySelector(data.selector || '#resume-download-link') : null;
          if (resumeLink && data.value && data.value !== '#') {
            resumeLink.href = data.value;
            resumeLink.setAttribute('download', 'Resume.pdf');
            resumeLink.style.opacity = '1';
            result.ok = true;
          } else if (resumeLink) {
            resumeLink.href = '#';
            result.ok = true;
          } else {
            result.ok = false;
          }
          break;
        }
        case 'UPDATE_SKILLS':
          updateSkills(data.skills);
          result.ok = true;
          break;
        case 'UPDATE_PROJECTS':
          updateProjects(data.projects);
          result.ok = true;
          break;
        case 'UPDATE_TYPING':
          updateTypingWords(data.words);
          result.ok = true;
          break;
        case 'RELOAD':
          frame.src = frame.src;
          result.ok = true;
          break;
        case 'PING':
          result.pong = true;
          break;
      }

      if (event.source) {
        event.source.postMessage(result, '*');
      }
    });

    // Notify parent that preview is ready
    frame.addEventListener('load', () => {
      if (window.parent !== window) {
        window.parent.postMessage({ type: 'PREVIEW_READY' }, '*');
      }
    });
  </script>
</body>
</html>
