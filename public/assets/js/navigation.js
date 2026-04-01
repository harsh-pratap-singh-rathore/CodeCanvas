/**
 * CodeCanvas — Navigation Manager (navigation.js)
 *
 * Responsibilities:
 *   • Store / read navigation state in sessionStorage
 *   • Persist key values in cookies for cross-session awareness
 *   • Guard editor and preview pages against missing state
 *   • Provide correct back-navigation helpers
 *
 * Usage: included via <script> on each page that needs it.
 * Exposes a single global: window.NavManager
 */

(function (global) {
    'use strict';

    /* ── Constants ────────────────────────────────────────────── */
    const PAGES = {
        CATEGORY: 'select-category.php',
        TEMPLATE: 'select-template.php',
        DASHBOARD: 'dashboard.php',
    };

    /* ── Cookie helpers ───────────────────────────────────────── */
    function setCookie(name, value, days) {
        days = days || 30;
        var expires = '';
        var d = new Date();
        d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
        expires = '; expires=' + d.toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/';
    }

    function getCookie(name) {
        var key = name + '=';
        var parts = document.cookie.split(';');
        for (var i = 0; i < parts.length; i++) {
            var c = parts[i].trim();
            if (c.indexOf(key) === 0) {
                return decodeURIComponent(c.substring(key.length));
            }
        }
        return null;
    }

    /* ── sessionStorage helpers ───────────────────────────────── */
    function ss(key, value) {
        try {
            if (value === undefined) {
                return sessionStorage.getItem(key);
            }
            if (value === null) {
                sessionStorage.removeItem(key);
            } else {
                sessionStorage.setItem(key, String(value));
            }
        } catch (e) {
            // sessionStorage may be unavailable (private mode, quota exceeded)
            console.warn('[NavManager] sessionStorage unavailable:', e);
        }
        return null;
    }

    /* ── Redirect helper ──────────────────────────────────────── */
    function redirectTo(page) {
        // Resolve relative to /app/ directory (where all PHP pages live)
        var base = window.location.pathname.replace(/\/[^/]*$/, '/');
        window.location.replace(base + page);
    }

    /* ── Public API ───────────────────────────────────────────── */
    var NavManager = {

        /* ── Setters ──────────────────────────────────────────── */

        /**
         * Called when user selects a category.
         * @param {string} category  e.g. "business"
         */
        setCategory: function (category) {
            ss('activeCategory', category);
            setCookie('lastVisitedPage', 'select-template');
        },

        /**
         * Called when user opens a template modal or starts editing.
         * @param {string} templateName  Human-readable name e.g. "Business Template"
         * @param {string} previewPath   Relative path to code.html
         */
        setTemplate: function (templateName, previewPath) {
            ss('activeTemplate', templateName);
            ss('activeEditorState', previewPath || '');
            setCookie('lastEditorTemplate', templateName);
        },

        /**
         * Called when preview is opened (standalone or from modal).
         * @param {string} previewPath  Relative path to code.html
         */
        setPreview: function (previewPath) {
            ss('activeTemplate', previewPath);
        },

        /**
         * Called when editor page loads with a valid project.
         * @param {string|number} projectId
         */
        setProject: function (projectId) {
            ss('activeProjectId', String(projectId));
            setCookie('lastProjectId', String(projectId));
            setCookie('lastVisitedPage', 'project-editor');
        },

        /* ── Getters ──────────────────────────────────────────── */

        getCategory: function () { return ss('activeCategory'); },
        getTemplate: function () { return ss('activeTemplate'); },
        getProject: function () { return ss('activeProjectId'); },
        getPreview: function () { return ss('activeTemplate'); },

        /* ── Cookie getters (persistent) ──────────────────────── */

        getLastPage: function () { return getCookie('lastVisitedPage'); },
        getLastTemplate: function () { return getCookie('lastEditorTemplate'); },
        getLastProject: function () { return getCookie('lastProjectId'); },

        /* ── Raw cookie access ────────────────────────────────── */
        setCookie: setCookie,
        getCookie: getCookie,

        /* ── Navigation Guards ────────────────────────────────── */

        /**
         * Guard for the editor page.
         * If PROJECT_ID global is missing or falsy, redirect to category selection.
         * Call this AFTER PHP has injected PROJECT_ID.
         */
        guardEditor: function () {
            var pid = (typeof PROJECT_ID !== 'undefined') ? PROJECT_ID : null;
            if (!pid) {
                redirectTo(PAGES.CATEGORY);
                return false;
            }
            return true;
        },

        /**
         * Guard for the standalone preview page.
         * Only applies when the preview is NOT inside an iframe.
         * If no ?template param and no sessionStorage activeTemplate → redirect.
         */
        guardPreview: function (templateParam) {
            // If inside an iframe (editor embeds preview), skip guard entirely
            if (window.parent !== window) return true;

            if (templateParam) return true; // URL param present — fine

            var stored = ss('activeTemplate');
            if (stored) return true;

            // Nothing to show — redirect to category selection
            redirectTo(PAGES.CATEGORY);
            return false;
        },

        /* ── Back Navigation Helpers ──────────────────────────── */

        /**
         * Navigate back from editor → template selection for the stored category.
         * Falls back to category selection if no category stored.
         */
        backFromEditor: function () {
            var cat = ss('activeCategory');
            if (cat) {
                redirectTo(PAGES.TEMPLATE + '?category=' + encodeURIComponent(cat));
            } else {
                redirectTo(PAGES.CATEGORY);
            }
        },

        /**
         * Navigate back from template selection → category selection.
         */
        backFromTemplate: function () {
            redirectTo(PAGES.CATEGORY);
        },

        /**
         * Navigate back from preview → template selection for the stored category.
         */
        backFromPreview: function () {
            var cat = ss('activeCategory');
            if (cat) {
                redirectTo(PAGES.TEMPLATE + '?category=' + encodeURIComponent(cat));
            } else {
                redirectTo(PAGES.CATEGORY);
            }
        },

        /* ── History state management ─────────────────────────── */

        /**
         * Push a named state into browser history so back-button
         * navigates to the correct page rather than a stale URL.
         * @param {string} pageName  e.g. "category", "template", "editor"
         * @param {object} extra     Additional state data
         */
        pushState: function (pageName, extra) {
            try {
                var state = Object.assign({ _nav: pageName }, extra || {});
                history.replaceState(state, '', window.location.href);
            } catch (e) {
                // history API unavailable — ignore
            }
        },

        /**
         * Install a popstate listener that enforces correct back navigation.
         * Call once on pages that need it (editor, preview).
         * @param {string} currentPage  "editor" | "preview" | "template"
         */
        installBackGuard: function (currentPage) {
            window.addEventListener('popstate', function (e) {
                var state = e.state;
                // If we popped back to a state we don't recognise, enforce redirect
                if (!state || !state._nav) {
                    if (currentPage === 'editor') {
                        NavManager.backFromEditor();
                    } else if (currentPage === 'preview') {
                        NavManager.backFromPreview();
                    } else if (currentPage === 'template') {
                        NavManager.backFromTemplate();
                    }
                }
            });
        }
    };

    /* ── Expose globally ──────────────────────────────────────── */
    global.NavManager = NavManager;

}(window));
