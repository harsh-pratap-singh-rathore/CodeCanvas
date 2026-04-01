/**
 * New Project Flow - Step Navigation (v2.1)
 * Handles multi-step form progression & Template Engine Integration
 */

(function () {
    'use strict';

    // State
    let currentStep = 1;
    const projectData = {
        type: null,
        template_id: null,
        project_name: '',
        brand_name: '',
        description: '',
        skills: '',
        contact: ''
    };

    // Initialize
    function init() {
        setupStep1();
        setupStep2();
        setupStep3();
        console.log('New Project flow initialized (v2.1)');
    }

    // Step 1: Project Type Selection
    function setupStep1() {
        const typeInputs = document.querySelectorAll('input[name="project_type"]');
        const continueBtn = document.getElementById('step1-continue');
        const devCard = document.getElementById('dev-portfolio-card');
        const devRadio = document.getElementById('dev-portfolio-radio');
        const devTemplateId = document.getElementById('dev-template-id');

        typeInputs.forEach(input => {
            input.addEventListener('change', () => {
                projectData.type = input.value;
                continueBtn.disabled = false;

                // If Developer Portfolio card selected: pre-set template and skip step 2
                if (input === devRadio) {
                    projectData.template_id = devTemplateId ? devTemplateId.value : '7';
                    continueBtn.textContent = 'Continue →';
                    continueBtn.dataset.skipTemplate = 'true';
                } else {
                    projectData.template_id = null;
                    continueBtn.textContent = 'Continue';
                    delete continueBtn.dataset.skipTemplate;
                    filterTemplates(projectData.type);
                }
            });
        });

        continueBtn.addEventListener('click', () => {
            if (!projectData.type) return;
            // Developer Portfolio: skip template step, go straight to details
            if (continueBtn.dataset.skipTemplate === 'true') {
                goToStep(3);
            } else {
                goToStep(2);
            }
        });
    }

    // Filter Templates Logic
    function filterTemplates(type) {
        const templateCards = document.querySelectorAll('.template-card');
        let hasVisible = false;

        // Reset previous selections
        document.querySelectorAll('input[name="template"]').forEach(inp => inp.checked = false);
        projectData.template_id = null;
        document.getElementById('step2-continue').disabled = true;

        templateCards.forEach(card => {
            if (card.dataset.type === type) {
                card.style.display = 'block';
                hasVisible = true;
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Step 2: Template Selection
    function setupStep2() {
        const backBtn = document.getElementById('step2-back');
        const continueBtn = document.getElementById('step2-continue');
        const templateList = document.getElementById('template-list');

        if (templateList) {
            templateList.addEventListener('change', (e) => {
                if (e.target.name === 'template') {
                    projectData.template_id = e.target.value;
                    continueBtn.disabled = false;
                }
            });
        }

        backBtn.addEventListener('click', () => goToStep(1));
        continueBtn.addEventListener('click', () => {
            if (projectData.template_id) goToStep(3);
        });
    }

    // Step 3: Basic Details
    function setupStep3() {
        const form = document.getElementById('project-form');
        const backBtn = document.getElementById('step3-back');
        const continueBtn = document.getElementById('step3-continue');
        const toggleBtn = document.getElementById('toggle-optional');
        const optionalFields = document.getElementById('optional-fields');

        if (toggleBtn && optionalFields) {
            toggleBtn.addEventListener('click', () => {
                optionalFields.classList.toggle('active');
            });
        }

        backBtn.addEventListener('click', () => goToStep(2));

        continueBtn.addEventListener('click', () => {
            if (form.checkValidity()) {
                projectData.project_name = document.getElementById('project_name').value;
                projectData.brand_name = document.getElementById('brand_name').value;
                projectData.description = document.getElementById('description').value;
                projectData.skills = document.getElementById('skills').value;
                projectData.contact = document.getElementById('contact').value;

                goToStep(4);
                startGeneration();
            } else {
                form.reportValidity();
            }
        });
    }

    // Step Navigation
    function goToStep(stepNumber) {
        document.querySelectorAll('.step-container').forEach(el => el.style.display = 'none');
        document.getElementById(`step-${stepNumber}`).style.display = 'block';

        // Update progress UI
        const steps = document.querySelectorAll('.progress-step');
        steps.forEach((step, index) => {
            step.classList.remove('active', 'completed');
            if (index < stepNumber - 1) step.classList.add('completed');
            if (index === stepNumber - 1) step.classList.add('active');
        });

        currentStep = stepNumber;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Generation Process
    function startGeneration() {
        const progressBar = document.getElementById('progress-bar');
        let progress = 0;

        const interval = setInterval(() => {
            progress += 2;
            if (progress > 90) clearInterval(interval); // Hold at 90 until done
            if (progressBar) progressBar.style.width = progress + '%';
        }, 50);

        // Start Submission process immediately
        submitProject();
    }

    // Submit to Backend & Initialize Template Engine
    async function submitProject() {
        try {
            const response = await fetch('new-project.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(projectData)
            });

            const result = await response.json();

            if (result.success) {
                console.log('Project created! Initializing Template Engine...');

                // 1. Initialize Engine with HTML Content
                if (window.templateEngine && result.templateHtml) {
                    // Update LocalStorage for the engine to pick up
                    localStorage.setItem('autofolio_template_html', result.templateHtml);

                    // Also parse fields immediately to set up initial data
                    const parser = new TemplateParser();
                    const fields = parser.parse(result.templateHtml);
                    localStorage.setItem('autofolio_template_fields', JSON.stringify(fields));

                    // Pre-fill data from project form inputs where possible
                    // We try to map our form fields to template placeholders
                    const initialData = {};
                    fields.forEach(field => {
                        const lower = field.toLowerCase();
                        if (lower.includes('name') || lower.includes('brand')) initialData[field] = projectData.brand_name;
                        else if (lower.includes('desc') || lower.includes('bio') || lower.includes('about')) initialData[field] = projectData.description;
                        else if (lower.includes('email') || lower.includes('contact')) initialData[field] = projectData.contact;
                        else if (lower.includes('skill')) initialData[field] = projectData.skills;
                        else initialData[field] = '';
                    });
                    localStorage.setItem('autofolio_template_data', JSON.stringify(initialData));

                    // Store Project ID for future saves
                    localStorage.setItem('autofolio_current_project_id', result.projectId);
                }

                // Complete Progress
                const progressBar = document.getElementById('progress-bar');
                if (progressBar) progressBar.style.width = '100%';

                // Redirect to Editor
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 500);

            } else {
                alert('Error: ' + (result.message || 'Unknown error'));
                goToStep(3);
            }

        } catch (error) {
            console.error('Submission error:', error);
            alert('Network error. Please try again.');
            goToStep(3);
        }
    }

    // Init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
