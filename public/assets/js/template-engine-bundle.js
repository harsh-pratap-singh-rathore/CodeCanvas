var _templateEngine;
(function () {
    // Template Engine Logic
    class TemplateParser {
        constructor() {
            this.placeholders = [];
        }

        parse(htmlContent) {
            if (!htmlContent) return [];
            const regex = /{{(.*?)}}/g;
            let match;
            const found = new Set();
            while ((match = regex.exec(htmlContent)) !== null) {
                const key = match[1].trim();
                if (key) found.add(key);
            }
            this.placeholders = Array.from(found);
            return this.placeholders;
        }

        inferType(fieldName) {
            const lower = fieldName.toLowerCase();
            if (lower.includes('email')) return 'email';
            if (lower.includes('url') || lower.includes('link') || lower.includes('website')) return 'url';
            if (lower.includes('color') || lower.includes('bg') || lower.includes('theme')) return 'color';
            if (lower.includes('description') || lower.includes('about') || lower.includes('bio') || lower.includes('text')) return 'textarea';
            return 'text';
        }
    }

    class TemplateRenderer {
        constructor() { }

        render(htmlTemplate, data) {
            if (!htmlTemplate) return '';
            if (!data) return htmlTemplate;
            return htmlTemplate.replace(/{{(.*?)}}/g, (match, p1) => {
                const key = p1.trim();
                return Object.prototype.hasOwnProperty.call(data, key) && data[key] !== undefined && data[key] !== null ? data[key] : '';
            });
        }

        download(htmlContent, filename = 'portfolio.html') {
            const blob = new Blob([htmlContent], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    }

    const TE_HTML_KEY = 'autofolio_template_html';
    const TE_FIELDS_KEY = 'autofolio_template_fields';
    const TE_DATA_KEY = 'autofolio_template_data';

    class TemplateEngine {
        constructor() {
            this.parser = new TemplateParser();
            this.renderer = new TemplateRenderer();
            this.currentHtml = localStorage.getItem(TE_HTML_KEY) || '';
            this.currentFields = JSON.parse(localStorage.getItem(TE_FIELDS_KEY)) || [];
            this.currentData = JSON.parse(localStorage.getItem(TE_DATA_KEY)) || {};
        }

        handleUpload(htmlContent) {
            if (!htmlContent) return;
            this.currentHtml = htmlContent;
            localStorage.setItem(TE_HTML_KEY, htmlContent);
            this.currentFields = this.parser.parse(htmlContent);
            localStorage.setItem(TE_FIELDS_KEY, JSON.stringify(this.currentFields));
            this.currentData = {};
            this.currentFields.forEach(field => {
                this.currentData[field] = '';
            });
            localStorage.setItem(TE_DATA_KEY, JSON.stringify(this.currentData));
            window.location.href = 'template-editor.html';
        }

        updateField(key, value) {
            this.currentData[key] = value;
            localStorage.setItem(TE_DATA_KEY, JSON.stringify(this.currentData));
        }

        getRenderedHtml() {
            return this.renderer.render(this.currentHtml, this.currentData);
        }

        exportPortfolio() {
            const finalHtml = this.getRenderedHtml();
            this.renderer.download(finalHtml, 'my-portfolio.html');
        }

        reset() {
            localStorage.removeItem(TE_HTML_KEY);
            localStorage.removeItem(TE_FIELDS_KEY);
            localStorage.removeItem(TE_DATA_KEY);
            this.currentHtml = '';
            this.currentFields = [];
            this.currentData = {};
        }
    }

    _templateEngine = new TemplateEngine();
})();

// Export global
window.templateEngine = _templateEngine;
