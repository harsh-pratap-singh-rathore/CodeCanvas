class TemplateRenderer {
    constructor() { }

    /**
     * Renders the HTML template by replacing placeholders with data values.
     * @param {string} htmlTemplate - The raw HTML template string with {{placeholders}}
     * @param {object} data - Key-value pair object where keys match placeholders
     * @returns {string} - The processed HTML string ready for rendering
     */
    render(htmlTemplate, data) {
        if (!htmlTemplate) return '';
        if (!data) return htmlTemplate;

        // Replace all occurrences of {{key}} with data[key]
        // If data[key] is missing, replace with empty string or keep placeholder?
        // Usually, empty string is safer to avoid showing {{name}} to end user.

        return htmlTemplate.replace(/{{(.*?)}}/g, (match, p1) => {
            const key = p1.trim();
            // Check if data has the key (even if empty string)
            if (Object.prototype.hasOwnProperty.call(data, key)) {
                // Ensure we escape HTML to prevent XSS if the template is not trusted?
                // For this MVP, we assume trusted input or basic replacement.
                // However, let's just do direct replacement as requested.
                return data[key] !== undefined && data[key] !== null ? data[key] : '';
            }
            // If key not found in data, return empty string to clear the placeholder
            return '';
        });
    }

    /**
     * Download the rendered HTML file.
     * @param {string} htmlContent - The final HTML content
     * @param {string} filename - The desired filename
     */
    download(htmlContent, filename = 'portfolio.html') {
        const blob = new Blob([htmlContent], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a); // Required for Firefox
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
}

window.TemplateRenderer = TemplateRenderer;
