document.addEventListener('DOMContentLoaded', () => {
    const promptInput = document.getElementById('prompt-input');
    const enhanceBtn = document.getElementById('enhance-btn');
    const generateBtn = document.getElementById('generate-btn');
    const loadingState = document.getElementById('loading-state');
    const loadingText = document.getElementById('loading-text');
    const loadingTime = document.getElementById('loading-time');
    const progressBar = document.getElementById('progress-bar');
    const previewIframe = document.getElementById('preview-iframe');
    const previewHeader = document.getElementById('preview-header');
    const emptyPreview = document.getElementById('empty-preview');
    const downloadLink = document.getElementById('download-link');
    const copyBtn = document.getElementById('copy-btn');

    const PLACEHOLDERS = [
        "minimal portfolio for UI designer",
        "modern portfolio for nodejs developer",
        "dark theme portfolio for freelancer",
        "creative portfolio for a digital artist",
        "clean white portfolio for a product manager"
    ];

    // --- Placeholder Rotation ---
    let pIndex = 0;
    setInterval(() => {
        pIndex = (pIndex + 1) % PLACEHOLDERS.length;
        promptInput.placeholder = PLACEHOLDERS[pIndex];
    }, 3000);

    // --- Progress & Loading State ---
    let progressInterval;
    let startTime;

    function resetProgress() {
        clearInterval(progressInterval);
        if (progressBar) progressBar.style.width = '0%';
        if (loadingTime) loadingTime.innerText = '~0s';
        document.querySelectorAll('.stage').forEach(s => {
            s.classList.remove('active', 'completed');
        });
        const stage1 = document.getElementById('stage-1');
        if (stage1) stage1.classList.add('active');
        
        const tokenDisplay = document.getElementById('token-display-final');
        if (tokenDisplay) {
            tokenDisplay.style.display = 'none';
        }
    }

    function startProgress(estimatedSeconds = 45) {
        resetProgress();
        startTime = Date.now();
        let currentProgress = 0;
        
        progressInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            if (loadingTime) loadingTime.innerText = `~${elapsed}s`;

            // Diminishing returns progress bar (never hits 100% until done)
            if (currentProgress < 90) {
                currentProgress += (90 - currentProgress) / (estimatedSeconds * 2);
                if (progressBar) progressBar.style.width = `${currentProgress}%`;
            }
        }, 100);
    }

    function setStage(stageNumber) {
        document.querySelectorAll('.stage').forEach((s, idx) => {
            if (idx + 1 < stageNumber) {
                s.classList.remove('active');
                s.classList.add('completed');
            } else if (idx + 1 === stageNumber) {
                s.classList.add('active');
                s.classList.remove('completed');
            } else {
                s.classList.remove('active', 'completed');
            }
        });

        if (stageNumber === 2) {
            loadingText.innerText = "Building portfolio...";
        } else if (stageNumber === 3) {
            loadingText.innerText = "Finalizing...";
            if (progressBar) progressBar.style.width = '100%';
        }
    }

    function toggleLoading(show) {
        const wrapper = document.querySelector('.ai-glow-box');
        if (wrapper) {
            if (show) {
                wrapper.classList.add('generating', 'generating-active');
            } else {
                wrapper.classList.remove('generating', 'generating-active');
            }
        }

        // Toggle AI Pulse blob
        const pulseBar = document.getElementById('ai-pulse-bar');
        if (pulseBar) {
            if (show) {
                pulseBar.classList.add('generating');
            } else {
                pulseBar.classList.remove('generating');
            }
        }

        loadingState.style.display = show ? 'block' : 'none';
        generateBtn.disabled = show;
        enhanceBtn.disabled = show;
        if (!show) clearInterval(progressInterval);
    }

    // --- Enhance Feature ---
    if (enhanceBtn) {
        enhanceBtn.addEventListener('click', async () => {
            const prompt = promptInput.value.trim();
            if (!prompt) return;

            const originalHTML = enhanceBtn.innerHTML;
            enhanceBtn.disabled = true;
            enhanceBtn.classList.add('enhancing');
            
            // Show temporary "Enhancing..." feedback below prompt
            const feedback = document.createElement('div');
            feedback.className = 'enhancement-feedback';
            feedback.innerText = '✨ Enhancing Prompt...';
            feedback.style.position = 'absolute';
            feedback.style.bottom = '-28px';
            feedback.style.left = '16px';
            feedback.style.fontSize = '12px';
            feedback.style.color = '#0a0a0a';
            feedback.style.fontWeight = '600';
            feedback.style.letterSpacing = '-0.01em';
            document.querySelector('.search-container').appendChild(feedback);
            
            try {
                const response = await fetch(BASE_URL + '/api/enhance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt })
                });

                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Enhance failed');
                }

                if (data.enhancedPrompt) {
                    // Replace text with exact V2 logic
                    promptInput.value = data.enhancedPrompt;
                    feedback.innerText = `✅ Enhanced! (${data.tokens} tokens)`;
                    setTimeout(() => feedback.remove(), 2000);
                }
            } catch (error) {
                console.error('Enhance failed:', error);
                feedback.innerText = '❌ ' + (error.message || 'Enhance failed');
                feedback.classList.add('error');
                setTimeout(() => feedback.remove(), 4000);
            } finally {
                enhanceBtn.disabled = false;
                enhanceBtn.innerHTML = originalHTML;
                enhanceBtn.classList.remove('enhancing');
            }
        });
    }

    // --- Generate Feature ---
    if (generateBtn) {
    generateBtn.addEventListener('click', async () => {
        const prompt = promptInput.value.trim() || promptInput.placeholder;
        
        toggleLoading(true);
        startProgress(50); // Estimated 50s for deepseek-v3.1:671b-cloud
        setStage(1);
        
        try {
            // Stage 1 is fast (Groq)
            setTimeout(() => setStage(2), 2000);

            const response = await fetch(BASE_URL + '/api/generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prompt })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Generation failed');
            }

            // Update Token Usage
            const tokenDisplay = document.getElementById('token-display-final');
            if (tokenDisplay && data.totalTokens) {
                tokenDisplay.innerText = `${data.totalTokens.toLocaleString()} Tokens Used`;
                tokenDisplay.style.display = 'inline-block';
            }

            let currentProjectId = data.id;

            setStage(3);
            setTimeout(() => {
                const grid = document.querySelector('.projects-grid');
                if (grid) {
                    const newCard = document.createElement('div');
                    newCard.className = 'project-card';
                    newCard.style.overflow = 'hidden';
                    newCard.style.borderRadius = '8px';
                    newCard.style.border = '1px solid #E5E5E5';
                    newCard.style.background = '#fff';
                    newCard.innerHTML = `
                        <a href="#" class="project-card-link" style="text-decoration: none; color: inherit;" onclick="event.preventDefault(); window.location.href=BASE_URL + '/public/editor.html?id=${currentProjectId}'">
                            <div class="project-card-visual" style="width: 100%; aspect-ratio: 16/10; background: #f9f9f9; border-bottom: 1px solid #E5E5E5; overflow: hidden; position: relative;">
                                <iframe src="${data.url}?t=${Date.now()}" style="width: 400%; height: 400%; transform: scale(0.25); transform-origin: top left; border: none; pointer-events: none; background: #fff;"></iframe>
                            </div>
                            <div class="project-card-header" style="padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;">
                                <span class="project-type" style="font-size: 10px; font-weight: bold; color: #666; text-transform: uppercase;">AI Generated</span>
                                <span class="project-status" style="font-size: 10px; font-weight: bold; background: #f0f0f0; padding: 4px 8px; border-radius: 12px; color: #333;">Draft</span>
                            </div>
                            <div class="project-card-body" style="padding: 0 16px 16px;">
                                <h3 class="project-card-title" style="margin: 0 0 4px; font-size: 15px; color: #111;">${prompt ? prompt.substring(0, 30) + (prompt.length > 30 ? '...' : '') : 'AI Portfolio'}</h3>
                                <p class="project-card-meta" style="margin: 0; font-size: 12px; color: #888;">Updated just now</p>
                            </div>
                        </a>
                        <div style="padding: 12px 16px; border-top: 1px solid #E5E5E5; display: flex; gap: 8px;">
                            <button onclick="window.location.href=BASE_URL + '/public/editor.html?id=${currentProjectId}'" style="flex: 1; padding: 8px; background: #000; color: #fff; border: none; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer;">Edit</button>
                            <button onclick="window.open('${data.url}', '_blank')" style="flex: 1; padding: 8px; background: #fff; color: #000; border: 1px solid #E5E5E5; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer;">View Live</button>
                        </div>
                    `;
                    const newProjectCard = grid.querySelector('.project-card-new');
                    if (newProjectCard) {
                        grid.insertBefore(newCard, newProjectCard.nextElementSibling);
                    } else {
                        grid.insertBefore(newCard, grid.firstChild);
                    }
                } else if (emptyPreview) {
                    emptyPreview.style.display = 'none';
                    previewIframe.style.display = 'block';
                    previewIframe.src = `${data.url}?t=${Date.now()}`;
                    if (downloadLink) downloadLink.href = data.url;
                    if (previewHeader) previewHeader.style.display = 'flex';
                    
                    const editBtnMain = document.getElementById('edit-btn-main');
                    if (editBtnMain) {
                        editBtnMain.style.display = 'inline-block';
                        editBtnMain.onclick = () => {
                            window.location.href = BASE_URL + `/public/editor.html?id=${currentProjectId}`;
                        };
                    }
                }

                toggleLoading(false);
            }, 1000);

        } catch (error) {
            console.error('Generation failed:', error);
            toggleLoading(false);
            alert(error.message || 'Generation failed. Please try again.');
        }
    });
    }

    // --- Copy Code ---
    if (copyBtn) {
        copyBtn.addEventListener('click', async () => {
            try {
                const iframeDoc = previewIframe.contentDocument || previewIframe.contentWindow.document;
                const html = iframeDoc.documentElement.outerHTML;
                await navigator.clipboard.writeText(html);
                const originalText = copyBtn.innerText;
                copyBtn.innerText = 'Copied! ✅';
                setTimeout(() => copyBtn.innerText = originalText, 2000);
            } catch (err) {
                console.error('Copy failed', err);
            }
        });
    }
});
