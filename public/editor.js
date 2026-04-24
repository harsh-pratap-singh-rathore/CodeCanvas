document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('id');
    const iframe = document.getElementById('preview-iframe');
    const saveBtn = document.getElementById('save-btn');
    const publishBtn = document.getElementById('publish-btn');
    const statusToast = document.getElementById('status-toast');

    if (!projectId) {
        alert("No project ID found in URL.");
        window.location.href = "/";
        return;
    }

    // Load the generated portfolio with cache buster
    iframe.src = `output/${projectId}/index.html?t=${Date.now()}`;

    iframe.onload = () => {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        const root = iframeDoc.getElementById('portfolio-root');

        if (!root) {
            console.error("Portfolio root not found.");
            return;
        }

        // Hide any internal edit buttons
        const innerEditBtn = iframeDoc.getElementById('edit-btn');
        if (innerEditBtn) innerEditBtn.style.display = 'none';

        // Scan for editable elements
        const editables = iframeDoc.querySelectorAll('[data-edit]');
        buildSectionedForm(editables, iframeDoc);
    };

    function buildSectionedForm(elements, iframeDoc) {
        const containers = {
            'hero': document.getElementById('form-hero'),
            'navigation': document.getElementById('form-navigation'),
            'about': document.getElementById('form-about'),
            'skills': document.getElementById('form-skills'),
            'projects': document.getElementById('form-projects'),
            'contact': document.getElementById('form-contact'),
            'footer': document.getElementById('form-footer'),
            'global': document.getElementById('form-global')
        };
        
        Object.values(containers).forEach(c => { if(c) c.innerHTML = ''; });

        elements.forEach((el) => {
            const key = el.getAttribute('data-edit');
            const content = el.innerText.trim();
            
            // Determine Section Mapping
            let section = 'global';
            const k = key.toLowerCase();
            if (k.startsWith('hero')) section = 'hero';
            else if (k.startsWith('nav') || k.includes('brand')) section = 'navigation';
            else if (k.startsWith('about')) section = 'about';
            else if (k.startsWith('skill')) section = 'skills';
            else if (k.startsWith('project')) section = 'projects';
            else if (k.startsWith('contact')) section = 'contact';
            else if (k.startsWith('footer')) section = 'footer';

            const container = containers[section] || containers['global'];
            
            const group = document.createElement('div');
            group.className = 'field-group';
            
            const label = document.createElement('label');
            label.innerText = key.split('.').pop().replace(/_/g, ' ');
            group.appendChild(label);

            let input;
            if (content.length > 50 || el.tagName.match(/P|DIV|SECTION/i) || k.includes('description')) {
                input = document.createElement('textarea');
            } else {
                input = document.createElement('input');
                input.type = 'text';
            }
            
            input.value = content;
            
            // Live Update & AutoSave
            input.oninput = (e) => {
                el.innerText = e.target.value;
                document.querySelector('.status-badge').innerHTML = '<span class="status-dot" style="background:#f59e0b"></span> Saving...';
                triggerAutoSave();
            };

            group.appendChild(input);
            container.appendChild(group);
        });
    }

    let autoSaveTimeout = null;
    let isSaving = false;
    
    window.addEventListener('beforeunload', (e) => {
        if (isSaving || autoSaveTimeout) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    function triggerAutoSave() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(executeSave, 1500); 
    }

    async function executeSave(isManual = false) {
        if (!projectId) return;
        
        const badge = document.querySelector('.status-badge');
        if (isManual && saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerText = 'Saving...';
        }

        isSaving = true;
        try {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            const htmlContent = iframeDoc.documentElement.outerHTML;
            
            const response = await fetch('../api/save-project.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: projectId,
                    html: '<!DOCTYPE html>\n' + htmlContent
                })
            });
            
            if (!response.ok) throw new Error('Failed to auto-save');
            
            badge.innerHTML = '<span class="status-dot"></span> All changes saved';
            autoSaveTimeout = null;
        } catch (err) {
            console.error(err);
            badge.innerHTML = '<span class="status-dot" style="background:#ef4444"></span> Save failed';
        } finally {
            isSaving = false;
            if (isManual && saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerText = 'Save Draft';
            }
        }
    }

    // --- Save Logic ---
    if (saveBtn) {
        saveBtn.addEventListener('click', () => executeSave(true));
    }

    // --- Publish/Republish Logic ---
    let _slugCheckTimeout = null;
    let currentProjectSlug = null;
    let isPublished = false;

    const publishModal = document.getElementById('publish-backdrop');
    const slugInput = document.getElementById('publish-slug');
    const slugFeedback = document.getElementById('slug-feedback');
    const publishConfirmBtn = document.getElementById('publish-confirm-btn');

    const topVisitBtn = document.getElementById('top-view-live-btn');

    window.openLiveSite = () => {
        if (currentProjectSlug) {
            window.open(`https://${currentProjectSlug}.vercel.app`, '_blank');
        }
    };

    // Fetch project status on load
    async function initPublishState() {
        try {
            const res = await fetch(`../api/get-project.php?id=${projectId}`);
            const data = await res.json();
            if (data.custom_slug) {
                currentProjectSlug = data.custom_slug;
                isPublished = (data.publish_status === 'deployed' || data.publish_status === 'published' || !!data.live_url);
                if (isPublished) {
                    publishBtn.innerHTML = '<i class="ph ph-rocket-launch"></i> Republish';
                    publishBtn.title = `Update site at ${data.custom_slug || 'your URL'}.vercel.app`;
                    if (topVisitBtn) topVisitBtn.style.display = 'flex';
                }
            }
        } catch (e) {
            console.error("Failed to load project state", e);
        }
    }
    initPublishState();

    if (publishBtn) {
        publishBtn.onclick = () => {
            if (isPublished && currentProjectSlug) {
                // Skip modal if already have a slug
                triggerPublish(currentProjectSlug);
            } else {
                publishModal.classList.add('open');
                slugInput.value = '';
                slugFeedback.innerHTML = '';
                publishConfirmBtn.disabled = true;
            }
        };
    }

    window.closePublishModal = () => {
        publishModal.classList.remove('open');
    };

    slugInput.oninput = (e) => {
        let slug = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        e.target.value = slug;
        
        if (slug.length < 3) {
            publishConfirmBtn.disabled = true;
            slugFeedback.innerHTML = '<span style="color:#888;">Min 3 characters</span>';
            return;
        }

        slugFeedback.innerHTML = '<span style="color:#888;">Checking availability...</span>';
        clearTimeout(_slugCheckTimeout);
        _slugCheckTimeout = setTimeout(() => validateSlug(slug), 500);
    };

    async function validateSlug(slug) {
        try {
            const fd = new FormData();
            fd.append('slug', slug);
            fd.append('project_id', projectId);
            const res = await fetch('../check-slug.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.available) {
                slugFeedback.innerHTML = '<span style="color:#22c55e;">✔ Available</span>';
                publishConfirmBtn.disabled = false;
            } else {
                slugFeedback.innerHTML = `<span style="color:#ef4444;">❌ Taken: ${data.error || 'choose another'}</span>`;
                publishConfirmBtn.disabled = true;
            }
        } catch (e) {
            slugFeedback.innerHTML = '<span style="color:#ef4444;">Error checking URL</span>';
        }
    }

    document.getElementById('publish-form').onsubmit = (e) => {
        e.preventDefault();
        publishModal.classList.remove('open');
        triggerPublish(slugInput.value);
    };

    async function triggerPublish(slug) {
        // Force a final save before publishing to ensure latest changes are included
        await executeSave();

        const overlay = document.getElementById('publish-progress-overlay');
        const fill = document.getElementById('pub-fill');
        const pctEl = document.getElementById('pub-pct');
        const stepEl = document.getElementById('pub-step');
        overlay.classList.add('visible');

        const stages = [
            { pct: 20, msg: 'Preparing project files...' },
            { pct: 40, msg: 'Bundling assets for Vercel...' },
            { pct: 70, msg: 'Uploading to production...' },
            { pct: 95, msg: 'Finalizing live URL...' }
        ];

        let stageIdx = 0;
        const progressTimer = setInterval(() => {
            if (stageIdx < stages.length) {
                fill.style.width = stages[stageIdx].pct + '%';
                pctEl.innerText = stages[stageIdx].pct + '%';
                stepEl.innerText = stages[stageIdx].msg;
                stageIdx++;
            }
        }, 1200);

        try {
            const fd = new FormData();
            fd.append('id', projectId);
            fd.append('slug', slug);
            const res = await fetch('../app/DeployController.php', { method: 'POST', body: fd });
            const data = await res.json();

            clearInterval(progressTimer);
            if (data.success) {
                fill.style.width = '100%';
                pctEl.innerText = '100%';
                stepEl.innerText = 'Site Updated!';
                
                // Update local state
                isPublished = true;
                currentProjectSlug = slug;
                publishBtn.innerHTML = '<i class="ph-bold ph-rocket-launch"></i> Republish';

                setTimeout(() => {
                    overlay.classList.remove('visible');
                    showSuccessModal(`https://${slug}.vercel.app`);
                }, 1000);
            } else {
                throw new Error(data.error || 'Deployment failed');
            }
        } catch (err) {
            clearInterval(progressTimer);
            overlay.classList.remove('visible');
            alert(err.message);
        }
    }

    function showSuccessModal(url) {
        document.getElementById('live-url-display').innerText = url;
        document.getElementById('view-live-btn').href = url;
        document.getElementById('publish-success-backdrop').classList.add('open');
    }

    window.closeSuccessModal = () => {
        document.getElementById('publish-success-backdrop').classList.remove('open');
    };

    // --- Device Toggles ---
    const desktopBtn = document.getElementById('view-desktop');
    const mobileBtn = document.getElementById('view-mobile');
    const liveBtn = document.getElementById('view-live');

    if (desktopBtn) {
        desktopBtn.onclick = () => {
            iframe.classList.remove('mobile-view');
            desktopBtn.classList.add('active');
            mobileBtn.classList.remove('active');
        };
    }

    if (mobileBtn) {
        mobileBtn.onclick = () => {
            iframe.classList.add('mobile-view');
            mobileBtn.classList.add('active');
            desktopBtn.classList.remove('active');
        };
    }

    if (liveBtn) {
        liveBtn.onclick = () => {
            if (isPublished && currentProjectSlug) {
                window.open(`https://${currentProjectSlug}.vercel.app`, '_blank');
            } else {
                window.open(iframe.src, '_blank');
            }
        };
    }

    function showToast(msg, isError = false) {
        statusToast.innerText = msg;
        statusToast.style.display = 'block';
        statusToast.className = isError ? 'error' : '';
        setTimeout(() => { statusToast.style.display = 'none'; }, 3000);
    }
});

// Helper for Downloading
function exportHTML() {
    const iframe = document.getElementById('preview-iframe');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    const html = iframeDoc.documentElement.outerHTML;
    
    const blob = new Blob(['<!DOCTYPE html>\n' + html], {type: 'text/html'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'portfolio.html';
    a.click();
}
