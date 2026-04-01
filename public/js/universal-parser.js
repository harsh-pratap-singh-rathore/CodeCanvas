/**
 * Universal Template Parser System
 * Handles automated template parsing, field extraction, and content replacement.
 */

const UniversalParser = (function() {
    
    // Private state
    let _doc = null;
    let _fields = [];

    /**
     * Initializes a new parser instance
     */
    function createParser() {
        _doc = null;
        _fields = [];
        return this;
    }

    /**
     * Loads HTML template from URL
     * @param {string} url 
     * @returns {Promise<Document>}
     */
    async function loadTemplate(url) {
        try {
            const response = await fetch(url);
            const html = await response.text();
            const parser = new DOMParser();
            _doc = parser.parseFromString(html, 'text/html');
            return _doc;
        } catch (error) {
            console.error('Template loading failed:', error);
            return null;
        }
    }

    /**
     * Extracts all editable fields from the loaded document
     * @param {Document} [doc] - Optional document to parse, defaults to loaded one
     * @returns {Object} JSON structure with fields and metadata
     */
    function extractFields(doc) {
        const targetDoc = doc || _doc;
        if (!targetDoc) throw new Error("No document loaded");

        _fields = [];
        let fieldCounter = 1;
        const selectorMap = new Set();
        
        // Helper to check leaf node (ignores comment/text nodes unless they are the only content)
        // Strictly: validation says "Only detect leaf nodes (no children)"
        // interpreted as: Element has no Element children.
        const isLeaf = (node) => {
            return node.children.length === 0;
        };

        const walker = targetDoc.createTreeWalker(
            targetDoc.body,
            NodeFilter.SHOW_ELEMENT,
            {
                acceptNode: (node) => {
                    const tag = node.tagName.toLowerCase();
                    if (['script', 'style', 'meta', 'link', 'svg', 'path', 'noscript', 'iframe'].includes(tag)) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );

        while (walker.nextNode()) {
            const node = walker.currentNode;
            const tag = node.tagName.toLowerCase();
            const isLeafNode = isLeaf(node);

            // 1. Text Fields
            // Rules: leaf nodes only, specific tags
            if (['h1','h2','h3','h4','h5','h6','p','span','div','li','a','button'].includes(tag)) {
                if (isLeafNode) {
                    const text = node.textContent.trim();
                    // Validation: ignore empty, whitespace, < 2 chars, > 300 chars
                    if (text.length >= 2 && text.length <= 300) {
                        _addField(node, 'text', text, fieldCounter++, selectorMap, targetDoc);
                    }
                }
            }

            // 1b. Link Fields (A - href)
            // Can be non-leaf (e.g. wrapper link), but we detect the HREF property.
            if (tag === 'a' && node.hasAttribute('href')) {
                const href = node.getAttribute('href').trim();
                // Ignore hash-only or javascript or empty
                if (href && href.length > 1 && !href.startsWith('javascript:')) {
                    _addField(node, 'link', href, fieldCounter++, selectorMap, targetDoc);
                }
            }

            // 2. Image Fields
            if (tag === 'img' && node.hasAttribute('src')) {
                const src = node.getAttribute('src').trim();
                if (src) {
                    _addField(node, 'image', src, fieldCounter++, selectorMap, targetDoc);
                }
            }

            // 3. Input Fields
            if (tag === 'input') {
                const type = node.getAttribute('type') || 'text';
                if (['text', 'email', 'tel', 'url', 'search', 'number'].includes(type.toLowerCase())) {
                   const val = node.value || node.getAttribute('value') || node.getAttribute('placeholder') || '';
                   _addField(node, 'input', val, fieldCounter++, selectorMap, targetDoc);
                }
            }
            if (tag === 'textarea') {
                const val = node.value || node.textContent || node.getAttribute('placeholder') || '';
                _addField(node, 'textarea', val, fieldCounter++, selectorMap, targetDoc);
            }
        }

        return {
            fields: _fields,
            metadata: {
                totalFields: _fields.length
            }
        };
    }

    /**
     * Adds a field to the internal list if unique
     */
    function _addField(node, type, value, index, selectorMap, doc) {
        const selector = generateSelector(node, doc);
        const uniqueKey = `${selector}::${type}`;

        if (selectorMap.has(uniqueKey)) return;
        selectorMap.add(uniqueKey);

        _fields.push({
            id: `field_${index}`,
            type: type,
            tag: node.tagName,
            defaultValue: value,
            selector: selector
        });
    }

    /**
     * Generates a robust, unique CSS selector
     * Priority: ID > Unique Class > Path
     */
    function generateSelector(element, doc) {
        if (element.id) {
            // Validate ID uniqueness just in case
            if (doc.querySelectorAll(`#${CSS.escape(element.id)}`).length === 1) {
                return `#${CSS.escape(element.id)}`;
            }
        }

        if (element.className && typeof element.className === 'string') {
            const classes = element.className.trim().split(/\s+/);
            for (const cls of classes) {
                if (!cls) continue;
                // Escaping class name for selector
                const safeCls = CSS.escape(cls);
                const selector = `.${safeCls}`;
                if (doc.querySelectorAll(selector).length === 1) {
                    return selector;
                }
            }
        }

        // Path generation
        let path = [];
        let current = element;
        
        while (current && current.tagName) {
            const tag = current.tagName.toLowerCase();
            if (tag === 'html') break;
            if (tag === 'body') {
                path.unshift('body');
                break;
            }

            // If we hit an ID on the way up, use it as root and stop
            if (current.id && doc.querySelectorAll(`#${CSS.escape(current.id)}`).length === 1) {
                path.unshift(`#${CSS.escape(current.id)}`);
                break;
            }

            let selector = tag;
            
            // Calculate nth-child
            let parent = current.parentElement;
            if (parent) {
                const children = parent.children;
                if (children.length > 1) {
                    let index = 1;
                    for (let i = 0; i < children.length; i++) {
                        if (children[i] === current) break;
                        if (children[i].tagName === current.tagName) index++; // strict type index? prompt says nth-child
                    }
                    // nth-child is 1-based index among ALL children
                    let nth = Array.prototype.indexOf.call(children, current) + 1;
                    selector += `:nth-child(${nth})`;
                }
            }
            
            path.unshift(selector);
            current = current.parentElement;
        }

        return path.join(' > ');
    }

    /**
     * Generates dynamic form DOM elements
     */
    function generateForm(fields) {
        const wrapper = document.createElement('div');
        wrapper.className = 'universal-generated-form';

        fields.forEach(field => {
            const group = document.createElement('div');
            group.style.cssText = "margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;";

            const label = document.createElement('label');
            label.style.cssText = "display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; font-family: monospace; color: #555;";
            label.textContent = `${field.type.toUpperCase()} - ${field.selector}`;
            
            let input;
            if (field.type === 'textarea' || (field.type === 'text' && field.defaultValue.length > 60)) {
                input = document.createElement('textarea');
                input.rows = 4;
            } else {
                input = document.createElement('input');
                input.type = 'text';
            }

            input.name = field.id;
            input.value = field.defaultValue;
            input.dataset.fieldId = field.id;
            input.style.cssText = "width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;";

            group.appendChild(label);
            group.appendChild(input);
            wrapper.appendChild(group);
        });

        return wrapper;
    }

    /**
     * Applies user data to the document
     * @param {Array} fields - The fields array returned by extractFields
     * @param {Object} userData - Key-value pair { field_id: new_value }
     */
    function applyUserData(fields, userData) {
        if (!_doc) return;

        fields.forEach(field => {
            const data = userData[field.id];
            if (data === undefined) return;

            // Find element in the current doc
            const element = _doc.querySelector(field.selector);
            if (!element) return;

            switch (field.type) {
                case 'image':
                    element.setAttribute('src', data);
                    break;
                case 'link':
                    element.setAttribute('href', data);
                    break;
                case 'input':
                case 'textarea':
                    element.value = data;
                    element.setAttribute('value', data); // Also set attr for export
                    break;
                case 'text':
                default:
                    element.textContent = data;
            }
        });
    }

    return {
        createParser,
        loadTemplate,
        extractFields,
        generateSelector,
        generateForm,
        applyUserData
    };

})();

// Export for module systems if needed, else global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UniversalParser;
} else {
    window.UniversalParser = UniversalParser;
}
