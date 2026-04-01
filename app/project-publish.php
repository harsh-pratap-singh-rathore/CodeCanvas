<?php
/**
 * PROJECT PUBLISHER — Schema-Based Portfolio Generator
 * CodeCanvas | Loads template + schema, applies user data, outputs final HTML
 *
 * Flow:
 *   1. Load project + content_json from DB
 *   2. Load templates/developer/code.html
 *   3. Load templates/developer/schema.json
 *   4. Apply user data to template using schema selectors (PHP regex)
 *   5. Download final HTML
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

require_once APP_ROOT . '/app/publish_helpers.php';

$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    header("Location: " . BASE_URL . '/app/dashboard.php');
exit;
}

try {
    // ── 1. Fetch project & template info ──────────────────────────
    $stmt = $pdo->prepare(
        "SELECT p.*, t.name AS template_name, t.folder_path
         FROM projects p
         JOIN templates t ON p.template_id = t.id
         WHERE p.id = ? AND p.user_id = ?"
    );
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header("Location: " . BASE_URL . '/app/dashboard.php');
exit;
    }

    // ── 2. Load template HTML ─────────────────────────────────────
    $folderPath = rtrim($project['folder_path'] ?? 'templates/developer/', '/');
    $absFolder = __DIR__ . '/../' . $folderPath;
    $entryFile = null;

    // Check which HTML file exists (code.html, index.html, index.htm)
    $candidates = ['code.html', 'index.html', 'index.htm'];
    foreach ($candidates as $c) {
        if (file_exists($absFolder . '/' . $c)) {
            $entryFile = '/' . $c;
            break;
        }
    }
    
    // Check nested (1 level deep)
    if (!$entryFile) {
        $subDirs = glob($absFolder . '/*', GLOB_ONLYDIR);
        foreach ($subDirs as $dir) {
            foreach ($candidates as $c) {
                if (file_exists($dir . '/' . $c)) {
                    $entryFile = '/' . basename($dir) . '/' . $c;
                    break 2;
                }
            }
        }
    }
    
    // Fallback
    if (!$entryFile) $entryFile = '/index.html';
    $templatePath = $absFolder . $entryFile;

    if (!file_exists($templatePath)) {
        die('Template file not found: ' . htmlspecialchars($templatePath));
    }
    $html = file_get_contents($templatePath);

    // ── 3. Load schema ────────────────────────────────────────────
    $schemaPath = __DIR__ . '/../' . $folderPath . '/schema.json';
    if (!file_exists($schemaPath)) {
        die('Schema file not found: ' . htmlspecialchars($schemaPath));
    }
    $schema = json_decode(file_get_contents($schemaPath), true);
    if (!$schema || !isset($schema['fields'])) {
        die('Invalid schema.json');
    }

    // ── 4. Load user data ─────────────────────────────────────────
    $userData = [];
    if (!empty($project['content_json'])) {
        $decoded = json_decode($project['content_json'], true);
        if (is_array($decoded)) {
            $userData = $decoded;
        }
    }

    // ── 4b. Normalise full_name — fix duplicate-line bug ──────────────
    // The project editor sometimes saves the h1 textContent which includes
    // the <br>-generated newlines AND the original default text, resulting in
    // duplicates like "RATHORE\nHARSH\nRATHORE". We collapse that here.
    if (!empty($userData['full_name'])) {
        $rawName = str_replace("\r\n", "\n", $userData['full_name']); // normalise CRLF
        $lines   = array_map('trim', explode("\n", $rawName));
        $lines   = array_filter($lines);          // remove blank lines
        $lines   = array_values($lines);
        // Deduplicate consecutive identical lines (case-insensitive)
        $deduped = [];
        $seen    = [];
        foreach ($lines as $line) {
            $key = strtolower($line);
            if (!in_array($key, $seen, true)) {
                $deduped[] = $line;
                $seen[]    = $key;
            }
        }
        $userData['full_name'] = implode("\n", $deduped);
    }

    // ── 5. Apply user data to HTML ────────────────────────────────
    $html = applyUserDataToHTML($html, $schema['fields'], $userData);

    // ── 5.1 Inject Project ID & Contact Logic ──────────────────────
    $bridgeScript = "
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        window.CODECANVAS_PROJECT_ID = " . json_encode($projectId) . ";
        window.CODECANVAS_API_BASE = " . json_encode(rtrim(BASE_URL, '/') . '/../app/api') . ";

        
        document.addEventListener('DOMContentLoaded', () => {
            const contactForm = document.querySelector('#contact form');
            if (contactForm) {
                const submitBtn = contactForm.querySelector('button');
                const originalText = submitBtn ? submitBtn.innerText : 'SEND_MESSAGE';
                
                contactForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerText = 'SENDING...';
                    }
                    
                    const formData = {
                        project_id: window.CODECANVAS_PROJECT_ID,
                        name: contactForm.querySelector('#name')?.value,
                        email: contactForm.querySelector('#email')?.value,
                        subject: contactForm.querySelector('#subject')?.value,
                        message: contactForm.querySelector('#message')?.value
                    };
                    
                    try {
                        const res = await fetch(window.CODECANVAS_API_BASE + '/contact.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(formData)
                        });
                        const data = await res.json();
                        alert(data.message);
                        if (data.success) contactForm.reset();
                    } catch (err) {
                        console.error('Contact error:', err);
                        alert('Error sending message. Please try again.');
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerText = originalText;
                        }
                    }
                });

                // Also handle button click if it's type=\"button\"
                if (submitBtn && submitBtn.getAttribute('type') === 'button') {
                    submitBtn.addEventListener('click', () => {
                        contactForm.dispatchEvent(new Event('submit'));
                    });
                }
            }
        });
    </script>";
    
    $html = str_replace('</body>', $bridgeScript . "\n</body>", $html);


    // ── 5.5 Set Status to Publishing ─────────────────────────────
    $setStatus = $pdo->prepare("UPDATE projects SET publish_status = 'publishing' WHERE id = ?");
    $setStatus->execute([$projectId]);

    // ── 6. Handle Vercel Deployment ─────────────────────────────
    require_once APP_ROOT . '/app/services/VercelDeployService.php';

    // Build the static site
    require_once APP_ROOT . '/app/StaticBuilder.php';
    $builder   = new StaticBuilder($pdo, $projectId);
    
    // Determine slug
    $slug = $_POST['slug'] ?? $project['custom_slug'] ?? '';
    
    if (empty($slug)) {
        $slug = preg_replace('/[^a-z0-9]/', '-', strtolower($project['project_name'] ?? 'portfolio'));
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        if (empty($project['custom_slug'])) {
            $slug .= '-' . substr(md5(uniqid()), 0, 4);
        }
    }

    try {
        $buildDir  = $builder->build($slug);
        
        $vercelService = new VercelDeployService();
        $liveUrl = $vercelService->deploy($buildDir, $slug);

        // ── 7. Update Database ───────────────────────────────────────
        $upd = $pdo->prepare("UPDATE projects SET 
            status = 'published', 
            publish_status = 'published', 
            custom_slug = ?, 
            live_url = ?
            WHERE id = ?");
        $upd->execute([$slug, $liveUrl, $projectId]);

        // ── 7.5 Notify User via Email ──────────────────────────────
        try {
            // Resolve user email & name — session first, DB fallback
            $notifyEmail = $_SESSION['user_email'] ?? null;
            $notifyName  = $_SESSION['user_name']  ?? null;

            if (empty($notifyEmail)) {
                $uStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
                $uStmt->execute([$_SESSION['user_id']]);
                $uRow        = $uStmt->fetch(PDO::FETCH_ASSOC);
                $notifyEmail = $uRow['email'] ?? '';
                $notifyName  = $uRow['name']  ?? 'Creator';
            }

            if (!empty($notifyEmail)) {
                require_once APP_ROOT . '/app/events/PortfolioDeployedEvent.php';
                PortfolioDeployedEvent::dispatch(
                    $notifyEmail,
                    $notifyName  ?: 'Creator',
                    $project['project_name'],
                    $liveUrl
                );
            }
        } catch (\Throwable $mailErr) {
            // Email failure must NEVER break the publish flow — log silently
            error_log('[CodeCanvas] Publish notification email failed: ' . $mailErr->getMessage());
        }


        // ── 8. Success Redirect ──────────────────────────────────────
        header('Location: dashboard.php?published=' . $projectId . '&slug=' . urlencode($slug) . '&live_url=' . urlencode($liveUrl));
        exit;

    } catch (Exception $e) {
        // Reset status on error
        $resetStatus = $pdo->prepare("UPDATE projects SET publish_status = 'failed', build_log = ? WHERE id = ? AND publish_status = 'publishing'");
        $resetStatus->execute([$e->getMessage(), $projectId]);

        header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
        exit;
    }

} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}

// ══════════════════════════════════════════════════════════════════
// SCHEMA-BASED HTML REPLACEMENT ENGINE (PHP)
// Mirrors the JS applyUserDataToHTML() in project-editor.js
// ══════════════════════════════════════════════════════════════════

/**
 * Apply all user data fields to the raw HTML string using schema selectors.
 * TRULY GENERIC: Uses DOMDocument + XPath to find elements by selector.
 */
function applyUserDataToHTML(string $html, array $fields, array $userData): string
{
    if (empty($html)) return $html;

    $dom = new DOMDocument();
    // Use LIBXML constants to handle HTML5 better and avoid adding extra tags
    libxml_use_internal_errors(true);
    // Load with UTF-8 hint
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    foreach ($fields as $field) {
        $id       = $field['id']       ?? '';
        $type     = $field['type']     ?? 'text';
        $selector = $field['selector'] ?? '';
        $value    = $userData[$id]     ?? null;

        if ($value === null || $value === '' || !$selector) continue;

        // Convert simple CSS selectors to XPath
        $xPathQuery = cssToXPath($selector);
        $nodes = $xpath->query($xPathQuery);

        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $node) {
                switch ($type) {
                    case 'text':
                    case 'email':
                    case 'textarea':
                        // ── Safe DOM child removal (while loop avoids skipping nodes) ──
                        while ($node->firstChild) {
                            $node->removeChild($node->firstChild);
                        }
                        // ── Handle \n in values: insert <br> between lines ──
                        // This is important for full_name (HARSH\nRATHORE → two-line display)
                        $strVal = trim((string)$value);
                        $lines  = explode("\n", $strVal);
                        foreach ($lines as $i => $line) {
                            if ($i > 0) {
                                $node->appendChild($dom->createElement('br'));
                            }
                            $node->appendChild($dom->createTextNode(trim($line)));
                        }

                        // For email link: also update href if it's an <a> tag
                        if ($type === 'email' && $node->nodeName === 'a') {
                            $node->setAttribute('href', 'mailto:' . $strVal);
                        }
                        break;

                    case 'image':
                        $strVal = (string)$value;
                        if (str_starts_with($strVal, 'data:') || str_starts_with($strVal, 'http')) {
                            if ($node->nodeName === 'img') {
                                $node->setAttribute('src', $strVal);
                            } else {
                                // Background image
                                $style = $node->getAttribute('style');
                                $newStyle = preg_replace('/background-image:\s*url\([^)]*\)/i', 'background-image: url("' . $strVal . '")', $style);
                                if ($newStyle === $style) {
                                    $newStyle .= '; background-image: url("' . $strVal . '");';
                                }
                                $node->setAttribute('style', $newStyle);
                            }
                        }
                        break;

                    case 'file':
                        // Resume / PDF file — update the anchor's href and ensure download attr
                        $strVal = (string)$value;
                        if ($node->nodeName === 'a' && (str_starts_with($strVal, 'data:') || str_starts_with($strVal, 'http'))) {
                            $node->setAttribute('href', $strVal);
                            if (!$node->hasAttribute('download')) {
                                $node->setAttribute('download', 'Resume.pdf');
                            }
                        }
                        break;
                    case 'array':
                        if ($id === 'skills' && is_array($value) && count($value) > 0) {
                            // Specialized handler for developer template skills grid
                            $html = replaceSkillsGrid($html, $value);
                        }
                        if ($id === 'typing_words' && is_array($value) && count($value) > 0) {
                            $html = replaceTypingWords($html, $value);
                        }
                        break;

                    case 'group':
                        if ($id === 'projects' && is_array($value) && count($value) > 0) {
                            $html = replaceProjectsGrid($html, $value);
                        }
                        break;
                }
            }
        }
    }

    // Since skills/projects grid replacement might have updated the $html string directly (via regex), 
    // we need to be careful. But actually, it's better to just return the $html if we did regex, 
    // or the DOM output if we did DOM.
    // Mixing them is hard. 
    
    // Let's do this: if we handled a complex field, we update $html.
    // But DOM is already loaded.
    
    // Actually, I'll just put the regex helpers back and use them AFTER the DOM replacement.
    
    $output = $dom->saveHTML();
    $html = str_replace('<?xml encoding="UTF-8">', '', $output);

    // Final pass for developer-specific complex grids (if they exist in the HTML)
    foreach ($fields as $field) {
        $id = $field['id'];
        $type = $field['type'];
        $value = $userData[$id] ?? null;
        if (!$value) continue;

        if ($type === 'array' && $id === 'skills')   $html = replaceSkillsGrid($html, (array)$value);
        if ($type === 'array' && $id === 'typing_words') $html = replaceTypingWords($html, (array)$value);
        if ($type === 'group' && $id === 'projects') $html = replaceProjectsGrid($html, (array)$value);
    }

    return $html;
}

/**
 * Very basic CSS selector to XPath converter for common cases in our schemas.
 * Supports: ".class", "#id", "tag", "parent child", "parent > child", "tag.class"
 */
function cssToXPath(string $selector): string
{
    // Handle multiple selectors? No, keep it simple for now.
    
    // 1. Convert "parent child" to "parent//child"
    $parts = preg_split('/\s+/', trim($selector));
    $xpathParts = [];

    foreach ($parts as $part) {
        if ($part === '>') {
            $xpathParts[] = '/';
            continue;
        }

        // Check for tag.class
        if (preg_match('/^([a-zA-Z0-9*]*)\.([a-zA-Z0-9_\-]+)$/', $part, $m)) {
            $tag = $m[1] ?: '*';
            $class = $m[2];
            $xpathParts[] = ".//{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
            continue;
        }

        // Check for .class
        if (str_starts_with($part, '.')) {
            $class = substr($part, 1);
            $xpathParts[] = ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
            continue;
        }

        // Check for #id
        if (str_starts_with($part, '#')) {
            $id = substr($part, 1);
            $xpathParts[] = ".//*[@id='{$id}']";
            continue;
        }

        // Tag only
        $xpathParts[] = ".//{$part}";
    }

    $final = implode('', $xpathParts);
    // Cleanup double slashes
    $final = str_replace(['.///', './/'], ['/', './/'], $final);
    if (!str_starts_with($final, '/')) $final = '/' . $final;

    return $final;
}

?>
