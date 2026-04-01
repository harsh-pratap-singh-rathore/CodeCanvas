class TemplateParser {
    constructor() {
        this.placeholders = [];
    }

    /**
     * Parses HTML content and extracts unique placeholders in {{format}}
     */
    parse(htmlContent) {
        if (!htmlContent) return [];

        const regex = /{{(.*?)}}/g;
        let match;
        const found = new Set();

        while ((match = regex.exec(htmlContent)) !== null) {
            const key = match[1].trim();
            if (key) {
                found.add(key);
            }
        }

        this.placeholders = Array.from(found);
        return this.placeholders;
    }

    /**
     * Infer input type based on placeholder name
     */
    inferType(fieldName) {
        const lower = fieldName.toLowerCase();

        // Image Detection
        if (lower.includes('image') || lower.includes('img') || lower.includes('photo') || lower.includes('pic') || lower.includes('avatar') || lower.includes('logo') || lower.includes('icon') || lower.includes('banner') || lower.includes('bg')) {
            return 'image';
        }

        // Other types
        if (lower.includes('email') || lower.includes('mail')) return 'email';
        if (lower.includes('url') || lower.includes('link') || lower.includes('website') || lower.includes('href')) return 'url';
        if (lower.includes('color') || lower.includes('theme')) return 'color';
        if (lower.includes('date') || lower.includes('time')) return 'date';
        if (lower.includes('desc') || lower.includes('about') || lower.includes('bio') || lower.includes('text') || lower.includes('message')) return 'textarea';

        return 'text';
    }
}

// Export
window.TemplateParser = TemplateParser;
