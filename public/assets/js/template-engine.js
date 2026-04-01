/**
 * TEMPLATE ENGINE
 * Manages state between Upload -> Parser -> Editor -> Preview -> Renderer
 */

const TE_HTML_KEY = 'autofolio_template_html';
const TE_FIELDS_KEY = 'autofolio_template_fields';
const TE_DATA_KEY = 'autofolio_template_data';

// Singleton instance to access from global scope
class TemplateEngine {
    constructor() {
        this.parser = new TemplateParser();
        this.renderer = new TemplateRenderer();
        this.currentHtml = localStorage.getItem(TE_HTML_KEY) || '';
        this.currentFields = JSON.parse(localStorage.getItem(TE_FIELDS_KEY)) || [];
        this.currentData = JSON.parse(localStorage.getItem(TE_DATA_KEY)) || {};
    }

    /**
     * UPLOAD: Saves HTML file content and detects fields.
     * @param {string} htmlContent - The raw HTML from uploaded file
     */
    handleUpload(htmlContent) {
        if (!htmlContent) return;

        // 1. Save HTML to local storage
        this.currentHtml = htmlContent;
        localStorage.setItem(TE_HTML_KEY, htmlContent);

        // 2. Parse placeholders
        this.currentFields = this.parser.parse(htmlContent);
        localStorage.setItem(TE_FIELDS_KEY, JSON.stringify(this.currentFields));

        console.log('Detected Fields:', this.currentFields);

        // 3. Initialize empty data object for fields
        this.currentData = {};
        this.currentFields.forEach(field => {
            this.currentData[field] = '';
        });
        localStorage.setItem(TE_DATA_KEY, JSON.stringify(this.currentData));

        // 4. Navigate
        window.location.href = 'template-editor.html';
    }

    /**
     * EDITOR: Saves user input to local storage immediately
     * @param {string} key - Field name (e.g. 'name')
     * @param {string} value - User input value
     */
    updateField(key, value) {
        this.currentData[key] = value;
        localStorage.setItem(TE_DATA_KEY, JSON.stringify(this.currentData));

        // If live preview is active, trigger update?
        // Usually handled by page event listener calling this method then updating iframe
    }

    /**
     * PREVIEW: Renders current template with current data
     * @returns {string} - Rendered HTML
     */
    getRenderedHtml() {
        return this.renderer.render(this.currentHtml, this.currentData);
    }

    /**
     * EXPORT: Trigger download of final file
     */
    exportPortfolio() {
        const finalHtml = this.getRenderedHtml();
        this.renderer.download(finalHtml, 'my-portfolio.html');
    }

    /**
     * RESET: Clears all stored template data
     */
    reset() {
        localStorage.removeItem(TE_HTML_KEY);
        localStorage.removeItem(TE_FIELDS_KEY);
        localStorage.removeItem(TE_DATA_KEY);
        this.currentHtml = '';
        this.currentFields = [];
        this.currentData = {};
    }
}

// Global instance
window.templateEngine = new TemplateEngine();
