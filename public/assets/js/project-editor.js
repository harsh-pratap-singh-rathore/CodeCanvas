/**
 * project-editor.js — Schema-Based Portfolio Editor
 * CodeCanvas | Integrated from prototype
 * Handles: schema loading, dynamic form generation, live preview (postMessage), export, save
 */

'use strict';

// ── State ──────────────────────────────────────────────────
let schema = null;
let userData = {};
let previewReady = false;
let pendingUpdates = [];
let updateTimer = null;
let _autoSaveTimer = null;   // auto-save countdown
let _isDirty = false;        // true when userData has unsaved changes

// ── DOM References ─────────────────────────────────────────
const previewIframe = document.getElementById('preview-iframe');
const previewWrap = document.getElementById('preview-wrap');
const formSections = document.getElementById('form-sections');
const updateStatus = document.getElementById('update-status');
const previewStatus = document.getElementById('preview-status');
const saveDot = document.getElementById('save-dot');
const saveStatusEl = document.getElementById('save-status');

// Material Icons available for skills
const MATERIAL_ICONS = [
    'code', 'javascript', 'data_object', 'coffee', 'storage', 'html',
    'view_quilt', 'cloud_queue', 'terminal', 'design_services', 'api',
    'database', 'memory', 'developer_mode', 'build', 'settings',
    'web', 'phone_android', 'computer', 'devices', 'cloud',
    'security', 'analytics', 'psychology', 'auto_awesome', 'rocket_launch'
];

// ── Load Schema ────────────────────────────────────────────
async function loadSchema() {
    try {
        // 1. Check for Virtual Schema (Auto-parsed from {{ }} tags)
        if (typeof VIRTUAL_SCHEMA !== 'undefined' && VIRTUAL_SCHEMA && VIRTUAL_SCHEMA.fields) {
            schema = VIRTUAL_SCHEMA;
            console.log("Editor: Using Virtual Schema from {{placeholders}}");
        }
        // 2. Fallback to physical schema.json if URL is valid
        else if (typeof SCHEMA_URL !== 'undefined' && SCHEMA_URL && SCHEMA_URL !== 'null') {
            const res = await fetch(SCHEMA_URL);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            schema = await res.json();
            console.log("Editor: Using physical schema.json");
        }
        else {
            // No schema and no tags?
            schema = { fields: [] };
            console.warn("Editor: No editable fields or schema found for this template.");
        }

        // ── Normalize LLM section-based schema into ordered sections with human-readable labels
        if (schema && schema.sections && Array.isArray(schema.sections)) {
            // Helper: turn snake/kebab-case id into a Title Case label
            function sectionLabel(id) {
                return (id || 'general')
                    .replace(/[-_]/g, ' ')
                    .replace(/\b\w/g, c => c.toUpperCase());
            }

            let extractedFields = [];
            schema.sections.forEach(sec => {
                // Use explicit label if provided, otherwise humanize the id
                const label = sec.label && sec.label.trim() ? sec.label.trim() : sectionLabel(sec.id);
                if (sec.fields && Array.isArray(sec.fields)) {
                    sec.fields.forEach(f => {
                        f.section = label;
                        extractedFields.push(f);
                    });
                }
            });
            schema.fields = extractedFields;
        }

        // ── Always inject SEO Title (Global Settings) at the very top
        if (schema && schema.fields && Array.isArray(schema.fields)) {
            const hasSeoTitle = schema.fields.find(f => (f.id || f.key) === 'seo_title');
            if (!hasSeoTitle) {
                const projectNameEl = document.getElementById('display-project-name');
                const defaultTitle = projectNameEl ? projectNameEl.querySelector('span').textContent.trim() : 'My Portfolio';
                schema.fields.unshift({
                    id: 'seo_title',
                    label: 'Website Tab Title (SEO)',
                    type: 'text',
                    selector: 'title',
                    section: 'Global Settings',
                    hint: 'Shows up on browser tabs and Google Search',
                    defaultValue: defaultTitle
                });
            }
        }

        // Priority: server-saved data → localStorage draft → schema defaults
        const localKey = `cc_draft_${PROJECT_ID}`;
        const localSaved = localStorage.getItem(localKey);

        const allFields = schema.fields || [];
        if (SAVED_DATA && Object.keys(SAVED_DATA).length > 0) {
            userData = { ...buildDefaultData(allFields), ...SAVED_DATA };
        } else if (localSaved) {
            try {
                userData = { ...buildDefaultData(allFields), ...JSON.parse(localSaved) };
                showToast('Draft restored from local storage', 'info');
            } catch (e) {
                userData = buildDefaultData(allFields);
            }
        } else {
            userData = buildDefaultData(allFields);
        }

        renderForm(allFields);

    } catch (err) {
        console.error('Failed to load schema:', err);
        formSections.innerHTML = `
            <div style="padding:24px; text-align:center; color:#ef4444; font-size:12px;">
                Failed to load schema.json<br>${err.message}
            </div>`;
        showToast('Failed to load schema', 'error');
    }
}

// ── Normalize Field Object ───────────────────────────────
function normalizeField(f) {
    return {
        ...f,
        id: f.id || f.key || '',
        defaultValue: f.defaultValue !== undefined ? f.defaultValue : (f.default !== undefined ? f.default : '')
    };
}

// ── Build Default Data from Schema ────────────────────────
function buildDefaultData(fields) {
    const data = {};
    if (!fields) return data;
    fields.forEach(f => {
        const nf = normalizeField(f);
        if (nf.id) data[nf.id] = nf.defaultValue;
    });
    return data;
}

// ── Render Form ────────────────────────────────────────────
function renderForm(fields) {
    if (!formSections) return;
    formSections.innerHTML = '';

    if (!fields || fields.length === 0) {
        formSections.innerHTML = `
            <div style="padding:48px 24px; text-align:center; color:#6B7280; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px dashed rgba(255,255,255,0.05);">
                <span class="material-icons" style="font-size:32px; margin-bottom:12px; color: #4b5563;">info_outline</span><br>
                <p style="font-size:14px; font-weight: 500;">No editable fields found</p>
                <p style="font-size:12px; margin-top: 4px; opacity: 0.7;">This template might not have any {{placeholders}} or a schema.json config.</p>
            </div>`;
        return;
    }

    const normalizedFields = (fields || []).map(normalizeField);

    // Preserve section order using an insertion-ordered Map
    const sectionsMap = new Map();
    normalizedFields.forEach(f => {
        const sec = f.section || 'General';
        if (!sectionsMap.has(sec)) sectionsMap.set(sec, []);
        sectionsMap.get(sec).push(f);
    });

    // Ensure 'Global Settings' is always first if present
    const orderedEntries = [...sectionsMap.entries()];
    const globalIdx = orderedEntries.findIndex(([k]) => k === 'Global Settings');
    if (globalIdx > 0) {
        const [globalEntry] = orderedEntries.splice(globalIdx, 1);
        orderedEntries.unshift(globalEntry);
    }

    orderedEntries.forEach(([sectionName, sectionFields], idx) => {
        const group = document.createElement('div');
        // Only open the first section by default
        group.className = 'sec-group' + (idx === 0 ? ' open' : '');
        const safeId = sectionName.replace(/[^a-zA-Z0-9]/g, '_');
        group.innerHTML = `
            <div class="sec-group-head">
                <span class="sec-group-title">${sectionName}</span>
                <span class="material-icons sec-chevron">expand_more</span>
            </div>
            <div class="sec-group-body" id="sec-body-${safeId}"></div>
        `;

        group.querySelector('.sec-group-head').addEventListener('click', () => {
            group.classList.toggle('open');
        });

        const body = group.querySelector('.sec-group-body');
        sectionFields.forEach(field => {
            const el = renderField(field);
            if (el) body.appendChild(el);
        });

        formSections.appendChild(group);
    });
}

// ── Render Individual Field ────────────────────────────────
function renderField(rawField) {
    const field = normalizeField(rawField);
    const wrap = document.createElement('div');
    wrap.className = 'f-group';
    wrap.dataset.fieldId = field.id;

    const labelHTML = `
        <label class="f-label" for="field-${field.id}">
            ${field.label}
            <span class="f-type-badge">${field.type}</span>
        </label>
    `;

    const hintHTML = field.hint
        ? `<span class="f-hint">${field.hint}</span>`
        : '';

    switch (field.type) {
        case 'text':
            const isLongText = field.id.includes('name') || field.id.includes('title') || field.id.includes('brand');
            const aiBtnText = !isLongText ? `<button class="ai-writer-btn" onclick="runAIWriter('${field.id}', '${field.label}')"><span class="material-icons">auto_awesome</span> AI+ Writer</button>` : '';

            wrap.innerHTML = `
                <div class="f-label-row">
                    <label class="f-label" for="field-${field.id}">
                        ${field.label}
                        <span class="f-type-badge">${field.type}</span>
                    </label>
                    ${aiBtnText}
                </div>
                <div class="f-input-wrapper">
                    <input type="text" id="field-${field.id}"
                        placeholder="${field.placeholder || ''}"
                        value="${escapeAttr(userData[field.id] || field.defaultValue || '')}" />
                    <button class="f-apply-btn" title="Apply Changes (Enter)">
                        <span class="material-icons">arrow_forward</span>
                    </button>
                </div>
                ${hintHTML}
            `;
            const input = wrap.querySelector('input');
            input.addEventListener('input', (e) => {
                userData[field.id] = e.target.value;
                scheduleUpdate(field, e.target.value);
                flashField(wrap);
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(updateTimer);
                    applyFieldUpdate(field, e.target.value);
                    showToast('Applying changes...', 'info');
                }
            });
            wrap.querySelector('.f-apply-btn').addEventListener('click', () => {
                clearTimeout(updateTimer);
                applyFieldUpdate(field, input.value);
                showToast('Applying changes...', 'info');
            });
            break;

        case 'email':
            wrap.innerHTML = labelHTML + `
                <div class="f-input-wrapper">
                    <input type="email" id="field-${field.id}"
                        placeholder="${field.placeholder || ''}"
                        value="${escapeAttr(userData[field.id] || field.defaultValue || '')}" />
                    <button class="f-apply-btn" title="Apply Changes (Enter)">
                        <span class="material-icons">arrow_forward</span>
                    </button>
                </div>
                ${hintHTML}
            `;
            const emailInput = wrap.querySelector('input');
            emailInput.addEventListener('input', (e) => {
                userData[field.id] = e.target.value;
                scheduleUpdate(field, e.target.value);
                flashField(wrap);
            });
            emailInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(updateTimer);
                    applyFieldUpdate(field, e.target.value);
                    showToast('Applying changes...', 'info');
                }
            });
            wrap.querySelector('.f-apply-btn').addEventListener('click', () => {
                clearTimeout(updateTimer);
                applyFieldUpdate(field, emailInput.value);
                showToast('Applying changes...', 'info');
            });
            break;

        case 'textarea':
            wrap.innerHTML = `
                <div class="f-label-row">
                    <label class="f-label" for="field-${field.id}">
                        ${field.label}
                        <span class="f-type-badge">${field.type}</span>
                    </label>
                    <button class="ai-writer-btn" onclick="runAIWriter('${field.id}', '${field.label}')">
                        <span class="material-icons">auto_awesome</span> AI+ Writer
                    </button>
                </div>
                <div class="f-input-wrapper">
                    <textarea id="field-${field.id}"
                        placeholder="${field.placeholder || ''}"
                        rows="4">${escapeHTML(userData[field.id] || field.defaultValue || '')}</textarea>
                    <button class="f-apply-btn" title="Apply Changes (Ctrl+Enter)" style="top: auto; bottom: 5px; transform: none;">
                        <span class="material-icons">arrow_forward</span>
                    </button>
                </div>
                ${hintHTML}
            `;
            const textarea = wrap.querySelector('textarea');
            textarea.addEventListener('input', (e) => {
                userData[field.id] = e.target.value;
                scheduleUpdate(field, e.target.value);
                flashField(wrap);
            });
            textarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    clearTimeout(updateTimer);
                    applyFieldUpdate(field, e.target.value);
                    showToast('Applying changes...', 'info');
                }
            });
            wrap.querySelector('.f-apply-btn').addEventListener('click', () => {
                clearTimeout(updateTimer);
                applyFieldUpdate(field, textarea.value);
                showToast('Applying changes...', 'info');
            });
            break;

        case 'image':
            wrap.innerHTML = labelHTML + `
                <div class="img-upload-wrap" id="img-wrap-${field.id}">
                    <input type="file" accept="image/*" id="field-${field.id}" />
                    <span class="material-icons img-upload-icon">add_photo_alternate</span>
                    <span class="img-upload-text">Click to upload image<br>PNG, JPG, GIF, WebP</span>
                </div>
                <img class="img-preview" id="img-preview-${field.id}" alt="Preview" />
                ${hintHTML}
            `;
            wrap.querySelector('input[type="file"]').addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const base64 = ev.target.result;
                    userData[field.id] = base64;
                    const preview = document.getElementById(`img-preview-${field.id}`);
                    preview.src = base64;
                    preview.style.display = 'block';
                    scheduleUpdate(field, base64);
                    flashField(wrap);
                };
                reader.readAsDataURL(file);
            });
            // Show existing image if any
            if (userData[field.id] && userData[field.id] !== field.defaultValue) {
                const preview = wrap.querySelector('.img-preview');
                preview.src = userData[field.id];
                preview.style.display = 'block';
            }
            break;

        case 'file':
            wrap.innerHTML = labelHTML + `
                <div class="img-upload-wrap" id="file-wrap-${field.id}">
                    <input type="file" accept=".pdf,application/pdf" id="field-${field.id}" />
                    <span class="material-icons img-upload-icon">upload_file</span>
                    <span class="img-upload-text">Click to upload PDF Resume<br><small style="opacity:0.6;">PDF only · Max 5 MB</small></span>
                </div>
                <div id="file-preview-${field.id}" style="display:none; margin-top:10px; padding:10px 12px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:8px; font-size:12px; color:#9ca3af; display:none; align-items:center; gap:8px;">
                    <span class="material-icons" style="font-size:16px; color:#60a5fa;">picture_as_pdf</span>
                    <a id="file-preview-link-${field.id}" href="#" target="_blank" style="color:#60a5fa; text-decoration:none; word-break:break-all;">Resume uploaded</a>
                    <span id="file-preview-name-${field.id}" style="margin-left:4px; opacity:0.6;"></span>
                </div>
                ${hintHTML}
            `;
            // Remove duplicate 'style="display:none"' conflict — fix via JS
            (() => {
                const previewDiv = wrap.querySelector(`#file-preview-${field.id}`);
                previewDiv.style.display = 'none';
            })();
            wrap.querySelector('input[type="file"]').addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                if (file.type !== 'application/pdf' && !file.name.endsWith('.pdf')) {
                    showToast('Please upload a PDF file', 'error');
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    showToast('PDF must be under 5 MB', 'error');
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const base64 = ev.target.result;
                    userData[field.id] = base64;
                    const previewDiv = document.getElementById(`file-preview-${field.id}`);
                    const previewLink = document.getElementById(`file-preview-link-${field.id}`);
                    const previewName = document.getElementById(`file-preview-name-${field.id}`);
                    previewLink.href = base64;
                    previewLink.textContent = 'Preview resume';
                    previewName.textContent = `(${file.name})`;
                    previewDiv.style.display = 'flex';
                    scheduleUpdate(field, base64);
                    flashField(wrap);
                    showToast('Resume uploaded!', 'success');
                };
                reader.readAsDataURL(file);
            });
            // Restore previously saved resume
            if (userData[field.id] && userData[field.id] !== '#' && userData[field.id].startsWith('data:')) {
                const previewDiv = wrap.querySelector(`#file-preview-${field.id}`);
                const previewLink = wrap.querySelector(`#file-preview-link-${field.id}`);
                previewLink.href = userData[field.id];
                previewLink.textContent = 'Preview resume';
                previewDiv.style.display = 'flex';
            }
            break;

        case 'url':
            wrap.innerHTML = labelHTML + `
                <div class="f-input-wrapper">
                    <input type="url" id="field-${field.id}"
                        placeholder="https://..."
                        value="${escapeAttr(userData[field.id] || field.defaultValue || '')}" />
                    <button class="f-apply-btn" title="Apply Changes (Enter)">
                        <span class="material-icons">arrow_forward</span>
                    </button>
                </div>
                ${hintHTML}
            `;
            const urlInput = wrap.querySelector('input');
            urlInput.addEventListener('input', (e) => {
                userData[field.id] = e.target.value;
                scheduleUpdate(field, e.target.value);
                flashField(wrap);
            });
            urlInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(updateTimer);
                    applyFieldUpdate(field, e.target.value);
                    showToast('Applying changes...', 'info');
                }
            });
            wrap.querySelector('.f-apply-btn').addEventListener('click', () => {
                clearTimeout(updateTimer);
                applyFieldUpdate(field, urlInput.value);
                showToast('Applying changes...', 'info');
            });
            break;

        case 'color':
            wrap.innerHTML = labelHTML + `
                <div style="display:flex; align-items:center; gap:12px;">
                    <input type="color" id="field-${field.id}"
                        style="width:44px; height:44px; padding:2px; border:1px solid #E5E5E5; border-radius:4px; cursor:pointer;"
                        value="${escapeAttr(userData[field.id] || field.defaultValue || '#000000')}" />
                    <code style="font-size:12px; color:#6B6B6B;">${userData[field.id] || field.defaultValue || '#000000'}</code>
                </div>
                ${hintHTML}
            `;
            const colorInput = wrap.querySelector('input');
            const colorCode = wrap.querySelector('code');
            colorInput.addEventListener('input', (e) => {
                const val = e.target.value;
                userData[field.id] = val;
                colorCode.textContent = val;
                scheduleUpdate(field, val);
                flashField(wrap);
            });
            break;

        case 'array':
            if (field.id === 'skills') {
                wrap.appendChild(createLabel(field));
                wrap.appendChild(renderSkillsArray(field));
            } else if (field.id === 'typing_words') {
                wrap.appendChild(createLabel(field));
                wrap.appendChild(renderSimpleArray(field));
            } else {
                wrap.appendChild(createLabel(field));
                wrap.appendChild(renderSimpleArray(field));
            }
            if (field.hint) {
                const hint = document.createElement('span');
                hint.className = 'f-hint';
                hint.textContent = field.hint;
                wrap.appendChild(hint);
            }
            break;

        case 'group':
            if (field.id === 'projects') {
                wrap.appendChild(createLabel(field));
                wrap.appendChild(renderProjectsGroup(field));
                if (field.hint) {
                    const hint = document.createElement('span');
                    hint.className = 'f-hint';
                    hint.textContent = field.hint;
                    wrap.appendChild(hint);
                }
            }
            break;

        default:
            return null;
    }

    if (field.selector && IS_SCHEMA_BASED) {
        wrap.addEventListener('focusin', () => {
            sendToPreview({ type: 'SCROLL_TO', selector: field.selector });
        });
    }

    return wrap;
}

// ── Create Label Element ───────────────────────────────────
function createLabel(field) {
    const label = document.createElement('label');
    label.className = 'f-label';
    label.innerHTML = `${field.label} <span class="f-type-badge">${field.type}</span>`;
    return label;
}

// ── Skills Array Field ─────────────────────────────────────
function renderSkillsArray(field) {
    const container = document.createElement('div');
    container.className = 'array-controls';

    const list = document.createElement('div');
    list.className = 'array-items-list';

    const skills = userData[field.id] || field.defaultValue || [];

    function renderSkillItem(skill, index) {
        const row = document.createElement('div');
        row.className = 'array-item-row';
        row.dataset.index = index;

        const iconPreview = document.createElement('span');
        iconPreview.className = 'material-icons icon-preview';
        iconPreview.textContent = skill.icon || 'code';

        const iconSelect = document.createElement('select');
        MATERIAL_ICONS.forEach(icon => {
            const opt = document.createElement('option');
            opt.value = icon;
            opt.textContent = icon;
            if (icon === skill.icon) opt.selected = true;
            iconSelect.appendChild(opt);
        });
        iconSelect.addEventListener('change', (e) => {
            skills[index].icon = e.target.value;
            iconPreview.textContent = e.target.value;
            userData[field.id] = skills;
            triggerSkillsUpdate();
        });

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.value = skill.name || '';
        nameInput.placeholder = 'Skill name';
        nameInput.addEventListener('input', (e) => {
            skills[index].name = e.target.value;
            userData[field.id] = skills;
            triggerSkillsUpdate();
        });

        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-btn';
        removeBtn.innerHTML = '<span class="material-icons">close</span>';
        removeBtn.title = 'Remove skill';
        removeBtn.addEventListener('click', () => {
            skills.splice(index, 1);
            userData[field.id] = skills;
            rebuildSkillsList();
            triggerSkillsUpdate();
        });

        row.appendChild(iconPreview);
        row.appendChild(iconSelect);
        row.appendChild(nameInput);
        row.appendChild(removeBtn);
        return row;
    }

    function rebuildSkillsList() {
        list.innerHTML = '';
        skills.forEach((skill, i) => list.appendChild(renderSkillItem(skill, i)));
    }

    rebuildSkillsList();

    const addBtn = document.createElement('button');
    addBtn.className = 'add-btn';
    addBtn.innerHTML = '<span class="material-icons">add</span> Add Skill';
    addBtn.addEventListener('click', () => {
        skills.push({ name: 'New Skill', icon: 'code' });
        userData[field.id] = skills;
        rebuildSkillsList();
        triggerSkillsUpdate();
        showToast('Skill added', 'success');
    });

    // Sync skill names → typing animation button
    const syncBtn = document.createElement('button');
    syncBtn.className = 'add-btn';
    syncBtn.innerHTML = '<span class="material-icons">sync</span> Sync Names → Typing Animation';
    syncBtn.title = 'Push skill names to the typing animation in the hero section';
    syncBtn.addEventListener('click', () => {
        const names = (userData[field.id] || []).map(s => s.name).filter(Boolean);
        if (names.length === 0) { showToast('No skills to sync', 'error'); return; }
        userData['typing_words'] = names;
        sendToPreview({ type: 'UPDATE_TYPING', words: names });
        showToast(`Synced ${names.length} skill(s) to typing animation`, 'success');
    });

    container.appendChild(list);
    container.appendChild(addBtn);
    container.appendChild(syncBtn);
    return container;
}

// ── Simple Array Field (typing words, etc.) ────────────────
function renderSimpleArray(field) {
    const container = document.createElement('div');
    container.className = 'array-controls';

    const list = document.createElement('div');
    list.className = 'array-items-list';

    let items = [...(userData[field.id] || field.defaultValue || [])];

    function rebuildList() {
        list.innerHTML = '';
        items.forEach((item, i) => {
            const row = document.createElement('div');
            row.className = 'array-item-row';

            const input = document.createElement('input');
            input.type = 'text';
            input.value = item;
            input.placeholder = 'Value...';
            input.addEventListener('input', (e) => {
                items[i] = e.target.value;
                userData[field.id] = items;
                if (field.id === 'typing_words') {
                    sendToPreview({ type: 'UPDATE_TYPING', words: items });
                }
            });

            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-btn';
            removeBtn.innerHTML = '<span class="material-icons">close</span>';
            removeBtn.addEventListener('click', () => {
                items.splice(i, 1);
                userData[field.id] = items;
                rebuildList();
                if (field.id === 'typing_words') {
                    sendToPreview({ type: 'UPDATE_TYPING', words: items });
                }
            });

            row.appendChild(input);
            row.appendChild(removeBtn);
            list.appendChild(row);
        });
    }

    rebuildList();

    const addBtn = document.createElement('button');
    addBtn.className = 'add-btn';
    addBtn.innerHTML = '<span class="material-icons">add</span> Add Item';
    addBtn.addEventListener('click', () => {
        items.push('');
        userData[field.id] = items;
        rebuildList();
    });

    container.appendChild(list);
    container.appendChild(addBtn);
    return container;
}

// ── Projects Group Field ───────────────────────────────────
function renderProjectsGroup(field) {
    const container = document.createElement('div');
    container.className = 'array-controls';

    const list = document.createElement('div');
    list.className = 'array-items-list';

    let projects = JSON.parse(JSON.stringify(userData[field.id] || field.defaultValue || []));

    function renderProjectItem(project, index) {
        const item = document.createElement('div');
        item.className = 'project-form-item';

        const header = document.createElement('div');
        header.className = 'project-form-header';
        header.innerHTML = `
            <span class="project-form-title">
                <span class="material-icons">folder</span>
                ${project.title || 'Project ' + (index + 1)}
            </span>
            <div style="display:flex; gap:4px;">
                <button class="remove-btn" title="Remove project" style="color:#ef4444;">
                    <span class="material-icons">delete</span>
                </button>
                <span class="material-icons" style="color:#6B6B6B; font-size:16px; cursor:pointer;">expand_more</span>
            </div>
        `;

        header.querySelector('.remove-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            projects.splice(index, 1);
            userData[field.id] = projects;
            rebuildProjectsList();
            triggerProjectsUpdate();
            showToast('Project removed', 'info');
        });

        header.addEventListener('click', (e) => {
            if (!e.target.closest('.remove-btn')) {
                item.classList.toggle('open');
                const chevron = header.querySelector('.material-icons:last-child');
                chevron.style.transform = item.classList.contains('open') ? 'rotate(180deg)' : '';
            }
        });

        const body = document.createElement('div');
        body.className = 'project-form-body';

        // Title
        body.appendChild(createProjectField('Title', 'text', project.title, (v) => {
            projects[index].title = v;
            header.querySelector('.project-form-title').innerHTML = `<span class="material-icons">folder</span> ${v || 'Project ' + (index + 1)}`;
            userData[field.id] = projects;
            triggerProjectsUpdate();
        }));

        // Category
        body.appendChild(createProjectField('Category (e.g. FINTECH • 2023)', 'text', project.category, (v) => {
            projects[index].category = v;
            userData[field.id] = projects;
            triggerProjectsUpdate();
        }));

        // Description
        body.appendChild(createProjectField('Description', 'textarea', project.description, (v) => {
            projects[index].description = v;
            userData[field.id] = projects;
            triggerProjectsUpdate();
        }));

        // Tags
        const tagsWrap = document.createElement('div');
        tagsWrap.className = 'f-group';
        tagsWrap.innerHTML = `<label class="f-label">Tags (comma-separated)</label>`;
        const tagsInput = document.createElement('input');
        tagsInput.type = 'text';
        tagsInput.value = (project.tags || []).join(', ');
        tagsInput.placeholder = 'React, Node.js, AWS';
        tagsInput.addEventListener('input', (e) => {
            projects[index].tags = e.target.value.split(',').map(t => t.trim()).filter(Boolean);
            userData[field.id] = projects;
            triggerProjectsUpdate();
        });
        tagsWrap.appendChild(tagsInput);
        body.appendChild(tagsWrap);

        // Link
        body.appendChild(createProjectField('Project Link', 'text', project.link, (v) => {
            projects[index].link = v;
            userData[field.id] = projects;
            triggerProjectsUpdate();
        }));

        // Image
        const imgWrap = document.createElement('div');
        imgWrap.className = 'f-group';
        imgWrap.innerHTML = `<label class="f-label">Project Image</label>`;

        const imgUrlInput = document.createElement('input');
        imgUrlInput.type = 'text';
        imgUrlInput.value = project.image || '';
        imgUrlInput.placeholder = 'Image URL or upload below';
        imgUrlInput.addEventListener('input', (e) => {
            projects[index].image = e.target.value;
            userData[field.id] = projects;
            if (e.target.value) {
                thumbPreview.src = e.target.value;
                thumbPreview.style.display = 'block';
            }
            triggerProjectsUpdate();
        });

        const uploadArea = document.createElement('div');
        uploadArea.className = 'img-upload-wrap';
        uploadArea.style.marginTop = '6px';
        uploadArea.innerHTML = `
            <input type="file" accept="image/*" />
            <span class="material-icons img-upload-icon" style="font-size:18px;">upload</span>
            <span class="img-upload-text">Upload image</span>
        `;
        uploadArea.querySelector('input').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => {
                projects[index].image = ev.target.result;
                imgUrlInput.value = '';
                thumbPreview.src = ev.target.result;
                thumbPreview.style.display = 'block';
                userData[field.id] = projects;
                triggerProjectsUpdate();
            };
            reader.readAsDataURL(file);
        });

        const thumbPreview = document.createElement('img');
        thumbPreview.className = 'project-thumb-preview';
        if (project.image) {
            thumbPreview.src = project.image;
            thumbPreview.style.display = 'block';
        }

        imgWrap.appendChild(imgUrlInput);
        imgWrap.appendChild(uploadArea);
        imgWrap.appendChild(thumbPreview);
        body.appendChild(imgWrap);

        item.appendChild(header);
        item.appendChild(body);
        return item;
    }

    function rebuildProjectsList() {
        list.innerHTML = '';
        projects.forEach((p, i) => list.appendChild(renderProjectItem(p, i)));
    }

    rebuildProjectsList();

    const addBtn = document.createElement('button');
    addBtn.className = 'add-btn';
    addBtn.innerHTML = '<span class="material-icons">add</span> Add Project';
    addBtn.addEventListener('click', () => {
        projects.push({
            title: 'New Project',
            category: 'WEB • 2024',
            description: 'Project description goes here.',
            tags: ['HTML', 'CSS', 'JS'],
            link: '#',
            image: ''
        });
        userData[field.id] = projects;
        rebuildProjectsList();
        triggerProjectsUpdate();
        showToast('Project added', 'success');
    });

    container.appendChild(list);
    container.appendChild(addBtn);
    return container;
}

// ── Helper: Create Project Sub-field ──────────────────────
function createProjectField(label, type, value, onChange) {
    const wrap = document.createElement('div');
    wrap.className = 'f-group';
    wrap.innerHTML = `<label class="f-label">${label}</label>`;

    const inputWrapper = document.createElement('div');
    inputWrapper.className = 'f-input-wrapper';

    let input;
    if (type === 'textarea') {
        input = document.createElement('textarea');
        input.rows = 2;
    } else {
        input = document.createElement('input');
        input.type = type;
    }
    input.value = value || '';

    const applyBtn = document.createElement('button');
    applyBtn.className = 'f-apply-btn';
    applyBtn.title = 'Apply Changes' + (type === 'textarea' ? ' (Ctrl+Enter)' : ' (Enter)');
    applyBtn.innerHTML = '<span class="material-icons">arrow_forward</span>';
    if (type === 'textarea') {
        applyBtn.style.top = 'auto';
        applyBtn.style.bottom = '5px';
        applyBtn.style.transform = 'none';
    }

    input.addEventListener('input', (e) => onChange(e.target.value));

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            if (type !== 'textarea' || (e.ctrlKey || e.metaKey)) {
                if (type === 'textarea') e.preventDefault();
                clearTimeout(updateTimer);
                // For projects, we call triggerProjectsUpdate() via the onChange callback usually
                // but since the callback only updates the data, we might need a way to force update.
                // However, triggerProjectsUpdate is what we usually want.
                onChange(e.target.value);
                triggerProjectsUpdate();
                showToast('Applying changes...', 'info');
            }
        }
    });

    applyBtn.addEventListener('click', () => {
        clearTimeout(updateTimer);
        onChange(input.value);
        triggerProjectsUpdate();
        showToast('Applying changes...', 'info');
    });

    inputWrapper.appendChild(input);
    inputWrapper.appendChild(applyBtn);
    wrap.appendChild(inputWrapper);
    return wrap;
}

// ── Preview Communication ──────────────────────────────────
function sendToPreview(message) {
    if (!previewReady) {
        pendingUpdates.push(message);
        return;
    }
    try {
        previewIframe.contentWindow.postMessage(message, '*');
    } catch (e) {
        console.warn('postMessage failed:', e);
    }
}

// ── Schedule Debounced Update ──────────────────────────────
// ── Schedule Debounced Update ────────────────────────────────────
function scheduleUpdate(field, value) {
    scheduleAutoSave();
    clearTimeout(updateTimer);
    setUpdateStatus('typing...');
    updateTimer = setTimeout(() => {
        applyFieldUpdate(field, value);
    }, 300);
}


// ── Apply Field Update to Preview ─────────────────────────
let cachedRawHtml = null;

async function applyFieldUpdate(field, value) {
    setUpdateStatus('updating...');

    if (IS_SCHEMA_BASED) {
        if (field.type === 'file') {
            sendToPreview({
                type: 'UPDATE_RESUME',
                selector: field.selector,
                value: value
            });
        } else if (field.type === 'url') {
            sendToPreview({
                type: 'UPDATE_FIELD',
                selector: field.selector,
                value: value,
                attr: 'href'
            });
        } else if (field.type === 'color') {
            sendToPreview({
                type: 'UPDATE_FIELD',
                selector: field.selector,
                value: value,
                attr: 'color' // The preview.php (portfolio-preview.php) should handle 'color' specially or it might just set attribute. 
                // Let's check portfolio-preview.php again to see how it handles generic attributes.
            });
        } else if (field.type === 'image') {
            sendToPreview({
                type: 'UPDATE_FIELD',
                selector: field.selector,
                value: value,
                attr: 'src'
            });
        } else if (field.id === 'email') {
            sendToPreview({
                type: 'UPDATE_FIELD',
                selector: field.selector,
                value: value,
                attr: 'innerText'
            });
            if (field.hrefSelector) {
                sendToPreview({
                    type: 'UPDATE_FIELD',
                    selector: field.hrefSelector,
                    value: (field.hrefPrefix || '') + value,
                    attr: 'href'
                });
            }
        } else {
            sendToPreview({
                type: 'UPDATE_FIELD',
                selector: field.selector,
                value: value,
                attr: field.attribute || 'innerText'
            });
        }
    } else {
        // Fallback for {{placeholder}} templates
        if (!cachedRawHtml) {
            try {
                const res = await fetch(TEMPLATE_URL);
                cachedRawHtml = await res.text();
            } catch (e) {
                console.error("Failed to fetch raw template for replacement");
            }
        }

        if (cachedRawHtml) {
            let rendered = cachedRawHtml;
            // Simple replace-all for placeholders
            Object.entries(userData).forEach(([key, val]) => {
                const regex = new RegExp(`{{${key}}}`, 'g');
                rendered = rendered.replace(regex, val || '');
            });
            previewIframe.srcdoc = rendered;
            previewStatus.textContent = 'Live (Preview Mode)';
        }
    }

    setTimeout(() => setUpdateStatus('Live'), 500);
}

// ── Skills Update Trigger ──────────────────────────────────
function triggerSkillsUpdate() {
    scheduleAutoSave();
    clearTimeout(updateTimer);
    setUpdateStatus('updating...');
    updateTimer = setTimeout(() => {
        const skills = userData['skills'] || [];
        sendToPreview({ type: 'UPDATE_SKILLS', skills });

        const skillNames = skills.map(s => s.name).filter(Boolean);
        if (skillNames.length > 0) {
            userData['typing_words'] = skillNames;
            sendToPreview({ type: 'UPDATE_TYPING', words: skillNames });
        }

        setTimeout(() => setUpdateStatus('Live'), 500);
    }, 400);
}


// ── Projects Update Trigger ────────────────────────────────
function triggerProjectsUpdate() {
    scheduleAutoSave();
    clearTimeout(updateTimer);
    setUpdateStatus('updating...');
    updateTimer = setTimeout(() => {
        sendToPreview({
            type: 'UPDATE_PROJECTS',
            projects: userData['projects'] || []
        });
        setTimeout(() => setUpdateStatus('Live'), 500);
    }, 400);
}



// ── Apply All Data to Preview ──────────────────────────────
async function applyAllToPreview() {
    if (!schema) return;

    if (!IS_SCHEMA_BASED) {
        // For placeholder templates, the first valid field update will trigger srcdoc rendering
        // We'll trigger a dummy update to force the first render
        await applyFieldUpdate({}, "");
        return;
    }

    schema.fields.forEach(field => {
        const value = userData[field.id];
        if (value === undefined || value === null) return;

        switch (field.type) {
            case 'text':
            case 'email':
            case 'textarea':
                applyFieldUpdate(field, value);
                break;
            case 'file':
                sendToPreview({
                    type: 'UPDATE_RESUME',
                    selector: field.selector,
                    value: value
                });
                break;
            case 'image':
                sendToPreview({
                    type: 'UPDATE_FIELD',
                    selector: field.selector,
                    value: value,
                    attr: 'src'
                });
                break;
            case 'array':
                if (field.id === 'skills') {
                    sendToPreview({ type: 'UPDATE_SKILLS', skills: value });
                } else if (field.id === 'typing_words') {
                    sendToPreview({ type: 'UPDATE_TYPING', words: value });
                }
                break;
            case 'group':
                if (field.id === 'projects') {
                    sendToPreview({ type: 'UPDATE_PROJECTS', projects: value });
                }
                break;
        }
    });
}

// ── Preview Ready Handler ──────────────────────────────────
window.addEventListener('message', (event) => {
    const data = event.data;
    if (!data) return;

    if (data.type === 'PREVIEW_READY') {
        previewReady = true;
        previewStatus.textContent = 'Live';

        // Flush pending updates
        if (pendingUpdates.length > 0) {
            pendingUpdates.forEach(msg => {
                try { previewIframe.contentWindow.postMessage(msg, '*'); } catch (e) { }
            });
            pendingUpdates = [];
        }

        // Apply all current data
        setTimeout(() => {
            applyAllToPreview();
        }, 200);
    }
});

// ── Viewport Controls ──────────────────────────────────────
document.querySelectorAll('.vp-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.vp-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const vp = btn.dataset.vp;
        previewWrap.className = `preview-wrap ${vp === 'desktop' ? '' : vp}`;
    });
});

// ── Refresh Preview ────────────────────────────────────────
document.getElementById('btn-refresh-preview').addEventListener('click', () => {
    previewReady = false;
    previewStatus.textContent = 'Reloading...';
    previewIframe.src = previewIframe.src;
    showToast('Preview refreshed', 'info');
});

// ── Fullscreen Preview ─────────────────────────────────────
document.getElementById('btn-fullscreen').addEventListener('click', () => {
    if (previewIframe.requestFullscreen) {
        previewIframe.requestFullscreen();
    }
});

// ── Save Draft (localStorage) ──────────────────────────────
function saveDraft() {
    try {
        const localKey = `cc_draft_${PROJECT_ID}`;
        localStorage.setItem(localKey, JSON.stringify(userData));
        showToast('Draft saved', 'success');
    } catch (e) {
        showToast('Failed to save draft: ' + e.message, 'error');
    }
}

document.getElementById('btn-save-draft').addEventListener('click', saveDraft);
document.getElementById('btn-save-draft-bottom').addEventListener('click', saveDraft);

// ── Save to Server ─────────────────────────────────────────
function saveToServer() {
    if (!saveDot || !saveStatusEl) return;
    saveDot.classList.add('saving');
    saveStatusEl.textContent = 'Saving...';

    fetch('project-save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            project_id: PROJECT_ID,
            data: userData
        })
    })
        .then(r => r.json())
        .then(result => {
            saveDot.classList.remove('saving');
            if (result.success) {
                saveStatusEl.textContent = 'All changes saved';
            } else {
                saveDot.classList.add('error');
                saveStatusEl.textContent = 'Error saving';
            }
        })
        .catch(() => {
            saveDot.classList.add('error');
            saveStatusEl.textContent = 'Error saving';
        });
}

// ── Auto-Save ──────────────────────────────────────────────
/**
 * Mark userData as dirty and schedule an auto-save in 30 seconds.
 * Each new edit resets the 30-second window (debounced).
 */
function scheduleAutoSave() {
    _isDirty = true;
    if (saveStatusEl) saveStatusEl.textContent = 'Unsaved changes';
    if (saveDot) { saveDot.classList.remove('saving', 'error'); }

    clearTimeout(_autoSaveTimer);
    _autoSaveTimer = setTimeout(() => {
        if (_isDirty) {
            _isDirty = false;
            // Silent save — no toast
            if (!saveDot || !saveStatusEl) return;
            saveDot.classList.add('saving');
            saveStatusEl.textContent = 'Auto-saving...';

            fetch('project-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: PROJECT_ID, data: userData })
            })
                .then(r => r.json())
                .then(result => {
                    saveDot.classList.remove('saving');
                    if (result.success) {
                        saveStatusEl.textContent = 'All changes saved';
                        // Also persist to localStorage as backup
                        try {
                            localStorage.setItem(`cc_draft_${PROJECT_ID}`, JSON.stringify(userData));
                        } catch (e) { /* ignore */ }
                    } else {
                        saveDot.classList.add('error');
                        saveStatusEl.textContent = 'Auto-save failed';
                    }
                })
                .catch(() => {
                    saveDot.classList.add('error');
                    saveStatusEl.textContent = 'Auto-save failed';
                });
        }
    }, 30000); // 30 seconds
}

// ── Reset ──────────────────────────────────────────────────
document.getElementById('btn-reset').addEventListener('click', () => {
    if (!confirm('Reset all fields to default values?')) return;
    const localKey = `cc_draft_${PROJECT_ID}`;
    localStorage.removeItem(localKey);
    userData = buildDefaultData(schema.fields);
    renderForm(schema.fields);
    previewReady = false;
    previewIframe.src = previewIframe.src;
    showToast('Reset to defaults', 'info');
});

// ── Publish ────────────────────────────────────────────────
// Publish modal + progress overlay are handled inline in project-editor.php
// (added after this script loads, so they can reference PUBLISH_STATUS / CUSTOM_SLUG)


// ── Generate / Download Portfolio ─────────────────────────
async function generatePortfolio() {
    showToast('Generating portfolio...', 'info');
    setUpdateStatus('generating...');

    try {
        const res = await fetch(TEMPLATE_URL);
        if (!res.ok) throw new Error('Failed to fetch template: HTTP ' + res.status);
        const rawHTML = await res.text();

        let finalHTML = applyUserDataToHTML(rawHTML);
        downloadHTML(finalHTML, 'my-portfolio.html');

        setUpdateStatus('Live');
        showToast('Portfolio downloaded! Open the HTML file in any browser.', 'success');

    } catch (err) {
        console.error('Generate error:', err);
        setUpdateStatus('Live');
        showToast('Generation failed: ' + err.message, 'error');
    }
}

document.getElementById('btn-generate').addEventListener('click', generatePortfolio);
document.getElementById('btn-generate-bottom').addEventListener('click', generatePortfolio);

// ── Apply all user data to raw HTML string ─────────────────
function applyUserDataToHTML(html) {
    if (!schema) return html;

    schema.fields.forEach(field => {
        const value = userData[field.id];
        if (value === undefined || value === null || value === '') return;

        switch (field.type) {
            case 'text':
            case 'email':
            case 'textarea': {
                html = replaceInnerText(html, field.selector, String(value));
                if (field.type === 'email' && field.hrefSelector) {
                    html = replaceAttribute(html, 'href', 'mailto:', String(value));
                }
                break;
            }
            case 'image': {
                if (String(value).startsWith('data:') || String(value).startsWith('http')) {
                    html = replaceImageSrc(html, field.selector, String(value));
                }
                break;
            }
            case 'url':
            case 'file': {
                html = replaceLinkHref(html, field.selector, String(value));
                break;
            }
            case 'array': {
                if (field.id === 'skills' && Array.isArray(value) && value.length > 0) {
                    html = replaceSkillsGrid(html, value);
                }
                if (field.id === 'typing_words' && Array.isArray(value) && value.length > 0) {
                    html = replaceTypingWords(html, value);
                }
                break;
            }
            case 'group': {
                if (field.id === 'projects' && Array.isArray(value) && value.length > 0) {
                    html = replaceProjectsGrid(html, value);
                }
                break;
            }
        }
    });


    return html;
}

// ── String-based replacement helpers ──────────────────────

function replaceInnerText(html, selector, newText) {
    const escaped = newText
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    switch (selector) {
        case '.hacker-name':
            return html.replace(
                /(class="hacker-name[^"]*">)([\s\S]*?)(<\/h1>)/,
                `$1${escaped}$3`
            );
        case 'nav a.font-hacker':
            return html.replace(
                /(<a\s[^>]*font-hacker[^>]*tracking-widest[^>]*>)([^<]*)(<\/a>)/,
                `$1${escaped}$3`
            );
        case '.flex.flex-col.md\\:flex-row span.text-gray-400':
            return html.replace(
                /(<span\s[^>]*text-lg[^>]*font-light[^>]*text-gray-400[^>]*>)([^<]*)(<\/span>)/,
                `$1${escaped}$3`
            );
        case '#about p.text-gray-400.leading-relaxed':
            return html.replace(
                /(<p\s[^>]*leading-relaxed[^>]*text-lg[^>]*font-light[^>]*>)([\s\S]*?)(<\/p>)/,
                `$1${escaped}$3`
            );
        case '#about .grid.grid-cols-2 div:first-child h4':
            return html.replace(
                /(<h4\s[^>]*text-3xl[^>]*>)([^<]*)(<\/h4>)/,
                `$1${escaped}$3`
            );
        case '#about .grid.grid-cols-2 div:last-child h4':
            return replaceNthMatch(
                html,
                /(<h4\s[^>]*text-3xl[^>]*>)([^<]*)(<\/h4>)/g,
                1,
                `$1${escaped}$3`
            );
        case '#contact a[href^=\'mailto\']':
            return html.replace(
                /(<a\s[^>]*href="mailto:[^"]*"[^>]*>)([^<]*)(<\/a>)/,
                `$1${escaped}$3`
            );
        case '#contact .space-y-8 div:nth-child(2) p.text-white':
            return html.replace(
                /(<p\s[^>]*class="text-white text-sm"[^>]*>)([^<]*)(<\/p>)/,
                `$1${escaped}$3`
            );
        case 'footer p:first-child':
            return html.replace(
                /(<p\s[^>]*text-\[10px\][^>]*text-gray-700[^>]*>)([^<]*)(<\/p>)/,
                `$1${escaped}$3`
            );
        default:
            // Generic fallback for [data-edit="..."]
            if (selector && selector.startsWith('[data-edit="')) {
                const key = selector.match(/"([^"]+)"/)[1];
                const regex = new RegExp(`(<[^>]*data-edit="${key}"[^>]*>)([\\s\\S]*?)(<\\/[^>]+>)`, 'g');
                return html.replace(regex, `$1${escaped}$3`);
            }
            return html;
    }
}

function replaceNthMatch(html, regex, n, replacement) {
    let count = 0;
    return html.replace(regex, (match, g1, g2, g3) => {
        if (count === n) {
            count++;
            return replacement
                .replace('$1', g1 || '')
                .replace('$2', g2 || '')
                .replace('$3', g3 || '');
        }
        count++;
        return match;
    });
}

function replaceAttribute(html, attr, prefix, newValue) {
    const escapedPrefix = prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const re = new RegExp(`(${attr}="${escapedPrefix})[^"]*(")`, 'g');
    return html.replace(re, `$1${newValue}$2`);
}

function replaceImageSrc(html, selector, newSrc) {
    if (selector && selector.startsWith('[data-edit-img="')) {
        const key = selector.match(/"([^"]+)"/)[1];
        const regex = new RegExp(`(<img[^>]*data-edit-img="${key}"[^>]*src=")([^"]*)(")`, 'g');
        return html.replace(regex, `$1${newSrc}$3`);
    }
    return html;
}

function replaceLinkHref(html, selector, newHref) {
    if (selector && selector.startsWith('[data-edit-link="')) {
        const key = selector.match(/"([^"]+)"/)[1];
        const regex = new RegExp(`(<a[^>]*data-edit-link="${key}"[^>]*href=")([^"]*)(")`, 'g');
        return html.replace(regex, `$1${newHref}$3`);
    }
    return html;
}

function replaceSkillsGrid(html, skills) {
    const skillsHTML = skills.map(skill => `
                <div class="group bg-black/40 border border-white/5 glow-border rounded-lg p-6 hover:bg-white/5 transition-all duration-300 flex flex-col items-center justify-center gap-3 cursor-default">
                    <span class="material-icons text-3xl text-gray-600 group-hover:text-white transition-colors">${escapeHTML(skill.icon || 'code')}</span>
                    <span class="font-hacker text-xs text-gray-400 group-hover:text-white tracking-wider transition-colors">${escapeHTML(skill.name || '')}</span>
                </div>`).join('\n');

    return html.replace(
        /(<div\s[^>]*grid-cols-2[^>]*md:grid-cols-4[^>]*lg:grid-cols-5[^>]*gap-4[^>]*>)([\s\S]*?)(<\/div>)/,
        `$1\n${skillsHTML}\n            $3`
    );
}

function replaceTypingWords(html, words) {
    const wordsJSON = JSON.stringify(words);
    return html.replace(
        /window\.words\s*=\s*\[[^\]]*\]/,
        `window.words = ${wordsJSON}`
    );
}

function replaceProjectsGrid(html, projects) {
    const projectsHTML = projects.map(p => {
        const tagsHTML = (p.tags || []).map(t =>
            `<span class="border border-white/10 px-2 py-0.5 rounded">${escapeHTML(t)}</span>`
        ).join('');

        return `
                <div class="group relative rounded-lg overflow-hidden bg-black/60 border border-white/5 glow-border transition-all duration-300">
                    <div class="aspect-[4/3] overflow-hidden">
                        <img alt="${escapeAttr(p.title || '')}" class="w-full h-full object-cover grayscale transition-all duration-700 group-hover:scale-110 group-hover:grayscale-0 opacity-70 group-hover:opacity-100" src="${escapeAttr(p.image || '')}" />
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                        <div class="translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                            <span class="text-[10px] font-hacker text-gray-400 mb-2 block tracking-widest">${escapeHTML(p.category || '')}</span>
                            <h4 class="text-lg font-bold text-white mb-1">${escapeHTML(p.title || '')}</h4>
                            <p class="text-xs text-gray-400 mb-3">${escapeHTML(p.description || '')}</p>
                            <div class="flex gap-2 text-[10px] font-hacker text-gray-500 mb-3">${tagsHTML}</div>
                            <a class="text-white text-xs font-hacker border-b border-white/20 pb-0.5 hover:border-white" href="${escapeAttr(p.link || '#')}">View_Project</a>
                        </div>
                    </div>
                </div>`;
    }).join('\n');

    return html.replace(
        /(<div\s[^>]*grid-cols-1[^>]*md:grid-cols-2[^>]*lg:grid-cols-3[^>]*gap-6[^>]*>)([\s\S]*?)(<\/div>)/,
        `$1\n${projectsHTML}\n            $3`
    );
}

function downloadHTML(html, filename) {
    const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }, 100);
}

// ── Flash Field on Update ──────────────────────────────────
function flashField(el) {
    el.classList.remove('field-updated');
    void el.offsetWidth;
    el.classList.add('field-updated');
}

// ── Update Status Text ─────────────────────────────────────
function setUpdateStatus(text) {
    if (updateStatus) updateStatus.textContent = text;
}

// ── Toast ──────────────────────────────────────────────────
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const icons = { success: 'check_circle', error: 'error', info: 'info' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span class="material-icons">${icons[type] || 'info'}</span> ${message}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ── Escape Helpers ─────────────────────────────────────────
function escapeHTML(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttr(str) {
    return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// ── Auto-save every 30s ────────────────────────────────────
setInterval(() => {
    if (Object.keys(userData).length > 0) {
        try {
            const localKey = `cc_draft_${PROJECT_ID}`;
            localStorage.setItem(localKey, JSON.stringify(userData));
        } catch (e) { }
        // Also save to server silently
        saveToServer();
    }
}, 30000);

// ── Init ───────────────────────────────────────────────────
loadSchema();

// ── AI Writer Logic ──────────────────────────────────────────

async function runAIWriter(fieldId, fieldLabel) {
    const inputEl = document.getElementById(`field-${fieldId}`);
    const btn = inputEl.closest('.f-group').querySelector('.ai-writer-btn');
    const currentValue = inputEl.value.trim();

    if (!currentValue) {
        showToast('Please enter some keywords or a rough draft first!', 'error');
        inputEl.focus();
        return;
    }

    if (btn.classList.contains('loading')) return;

    btn.classList.add('loading');
    btn.innerHTML = `<span class="material-icons">sync</span> Writing...`;

    try {
        const response = await fetch('ai-writer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                keywords: currentValue,
                context: fieldLabel + " section for a website template"
            })
        });

        const data = await response.json();

        if (data.success) {
            inputEl.value = data.content;
            userData[fieldId] = data.content;

            // Trigger update
            const field = { id: fieldId };
            scheduleUpdate(field, data.content);
            flashField(inputEl.closest('.f-group'));

            showToast('AI content generated!', 'success');
        } else {
            showToast(data.message || 'AI error', 'error');
        }
    } catch (err) {
        showToast('Connection error', 'error');
    } finally {
        btn.classList.remove('loading');
        btn.innerHTML = `<span class="material-icons">auto_awesome</span> AI+ Writer`;
    }
}
