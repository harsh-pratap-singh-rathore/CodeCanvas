<?php
require_once __DIR__ . '/../../config/bootstrap.php';
/**
 * Core Template Scanner
 * Handles HTML scanning for data-edit attributes, AI LLM heuristics,
 * and automatic schema.json generation in CodeCanvas.
 */

class TemplateScanner {
    
    /**
     * Scans a template directory for editable fields.
     */
    public static function scan(string $templateDir) {
        set_time_limit(0); // LLM API calls can be slow — remove PHP execution limit for this process
        $htmlFile = self::findPrimaryHtml($templateDir);
        
        if (!$htmlFile || !file_exists($htmlFile)) {
            return ['fields' => [], 'total' => 0, 'error' => 'No HTML file found.'];
        }

        $html = file_get_contents($htmlFile);
        $dom  = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // --- STEP 1: Pass 1 explicit data-edit attributes (Highest Priority)
        $explicitFields = self::getExplicitFields($xpath);

        // --- STEP 2: LLM Clean & Extraction
        $cleanedHtml = self::cleanHtmlForLLM($dom);
        
        $finalSchema = null;
        
        // --- STEP 3: API Pipeline
        try {
            $rawLlmSchema = self::pass1LlmScan($cleanedHtml);
            if ($rawLlmSchema) {
                // Merge data-edit fields into RAW schema before Pass 2
                $mergedRaw = self::mergeExplicitFieldsToRaw($rawLlmSchema, $explicitFields);
                $optimized = self::pass2LlmOptimization($mergedRaw);
                
                if (self::validateSchema($optimized)) {
                    $finalSchema = $optimized;
                }
            }
        } catch (Exception $e) {
            error_log("LLM Scanner Error: " . $e->getMessage());
        }

        // --- STEP 4: Fallback Mechanism (if LLM completely fails)
        if (!$finalSchema) {
            error_log("CodeCanvas: LLM Pipeline failed or returned invalid response. Falling back to Heuristic Scanner.");
            $heuristicFields = self::getHeuristicFields($xpath, array_column($explicitFields, 'key'));
            $finalSchema = self::formatFallbackSchema(array_merge($explicitFields, $heuristicFields));
        }

        return [
            'schema'   => $finalSchema,
            'total'    => self::countFields($finalSchema),
            'html_file'=> basename($htmlFile)
        ];
    }

    /**
     * Resolves the primary HTML entry file inside the extracted zip folder.
     */
    private static function findPrimaryHtml(string $templateDir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templateDir));
        $htmlFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'html') {
                $htmlFiles[] = $file->getPathname();
            }
        }

        $candidates = ['index.html', 'home.html', 'landing.html', 'default.html'];
        foreach ($candidates as $candidate) {
            foreach ($htmlFiles as $file) {
                if (strtolower(basename($file)) === $candidate) {
                    return $file;
                }
            }
        }

        // 5. first .html file found
        return !empty($htmlFiles) ? $htmlFiles[0] : null;
    }

    /**
     * Pass 0: Finds hard-coded data-edit attributes and ensures they are mapped.
     */
    private static function getExplicitFields(DOMXPath $xpath) {
        $fields = [];
        $usedKeys = [];
        
        $attrMap = [
            'data-edit'        => null,      // auto-detect text vs textarea
            'data-edit-img'    => 'image',
            'data-edit-link'   => 'link',
            'data-edit-color'  => 'color',
            'data-edit-repeat' => 'repeat',
            'data-edit-file'   => 'file'
        ];

        foreach ($attrMap as $attr => $forcedType) {
            $nodes = $xpath->query("//*[@{$attr}]");
            if (!$nodes) continue;

            foreach ($nodes as $node) {
                $key = trim($node->getAttribute($attr));
                if (empty($key) || in_array($key, $usedKeys, true)) continue;

                if ($forcedType !== null) {
                    $type = $forcedType;
                } else {
                    $blockTags = ['p', 'div', 'section', 'article', 'blockquote', 'li', 'header', 'footer'];
                    $type = in_array(strtolower($node->nodeName), $blockTags, true) ? 'textarea' : 'text';
                }

                $default = '';
                if ($attr === 'data-edit-img') {
                    $default = $node->getAttribute('src');
                } elseif ($attr === 'data-edit-link') {
                    $default = $node->getAttribute('href');
                } elseif ($attr === 'data-edit-color') {
                    preg_match('/color:\s*([^;]+)/i', $node->getAttribute('style'), $cm);
                    $default = trim($cm[1] ?? '#ffffff');
                } elseif ($attr === 'data-edit-repeat') {
                    $type = 'repeat';
                } else {
                    $default = trim($node->textContent);
                }

                $fields[] = [
                    'key'          => $key,
                    'type'         => $type,
                    'label'        => ucwords(str_replace(['_','-'], ' ', $key)),
                    'selector'     => "[{$attr}=\"{$key}\"]",
                    'default'      => is_array($default) ? $default : substr((string)$default, 0, 500)
                ];
                $usedKeys[] = $key;
            }
        }
        return $fields;
    }

    /**
     * Step 3 cleaning logic: reduce token size without losing structure.
     */
    private static function cleanHtmlForLLM(DOMDocument $dom) {
        // Remove entire nodes
        $removeTags = ['script', 'style', 'svg', 'meta', 'link', 'noscript', 'iframe', 'canvas', 'video', 'audio', 'object', 'embed'];
        $xpath = new DOMXPath($dom);
        
        foreach ($removeTags as $tag) {
            $nodes = $xpath->query("//{$tag}");
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        $preserveTags = ['section', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'img', 'a', 'ul', 'li', 'button'];
        
        $allNodes = $xpath->query("//*");
        $nodesToRename = [];
        
        foreach ($allNodes as $node) {
            if ($node->nodeName === '#text') continue;

            // Remove attributes except class, href, src, alt, and data-edit
            $removeAttrs = [];
            foreach ($node->attributes as $attrName => $attrNode) {
                if (!in_array($attrName, ['class', 'href', 'src', 'alt']) && !str_starts_with($attrName, 'data-edit')) {
                    $removeAttrs[] = $attrName;
                }
            }
            foreach ($removeAttrs as $attr) {
                $node->removeAttribute($attr);
            }

            // Truncate very long text natively
            if ($node->childNodes->length === 1 && $node->firstChild->nodeType === XML_TEXT_NODE) {
                $text = trim($node->nodeValue);
                if (strlen($text) > 200) {
                    $node->nodeValue = substr($text, 0, 200);
                }
            }

            $nodeName = strtolower($node->nodeName);
            if (!in_array($nodeName, $preserveTags) && $nodeName !== 'html' && $nodeName !== 'body' && $nodeName !== 'head') {
                $nodesToRename[] = $node;
            }
        }

        // Rename non-preserved structural tags to div/span instead of stripping them or deleting them
        foreach ($nodesToRename as $node) {
            $isInline = in_array(strtolower($node->nodeName), ['b', 'i', 'strong', 'em', 'u', 'mark', 'small', 'del', 'ins', 'sub', 'sup']);
            $newTag = $isInline ? 'span' : 'div';
            $newNode = $dom->createElement($newTag);
            
            if ($node->attributes) {
                foreach ($node->attributes as $attr) {
                    $newNode->setAttribute($attr->nodeName, $attr->nodeValue);
                }
            }
            while ($node->childNodes->length > 0) {
                $newNode->appendChild($node->childNodes->item(0));
            }
            $node->parentNode->replaceChild($newNode, $node);
        }

        $cleanHtml = $dom->saveHTML();
        if (strlen($cleanHtml) > 50000) { 
            // fallback truncation
            $cleanHtml = substr($cleanHtml, 0, 50000) . "</body></html>";
        }
        
        return $cleanHtml;
    }

    /**
     * Pipeline Helper: Calls NVIDIA DeepSeek LLM
     */
    private static function callLLM(string $systemPrompt, string $userPrompt, $retry = false) {
        $apiKey = "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";
        $model = "deepseek-ai/deepseek-v3.2";
        $timeout = 90; // 90s per call — if LLM hangs longer than this, fall back to heuristics

        $url = 'https://integrate.api.nvidia.com/v1/chat/completions';
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.1,
            'max_tokens' => 2048,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) throw new Exception("cURL Error: " . $err);

        $json = json_decode($response, true);
        if (!$json || !isset($json['choices'][0]['message']['content'])) {
            // No retry — let the caller's catch block trigger heuristic fallback immediately
            throw new Exception("Invalid API response format from LLM. Response: " . substr((string)$response, 0, 300));
        }

        $content = $json['choices'][0]['message']['content'];
        
        // Ensure strict JSON content parsing
        $content = preg_replace('/```json/i', '', $content);
        $content = preg_replace('/```/', '', $content);
        $content = trim($content);
        
        // Find JSON block if it still returned extra text
        if (str_starts_with($content, '{') === false) {
            $start = strpos($content, '{');
            $end = strrpos($content, '}');
            if ($start !== false && $end !== false) {
                $content = substr($content, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($content, true);
        if (!$decoded) {
            throw new Exception("LLM returned non-JSON content.");
        }

        return $decoded;
    }

    /**
     * Pass 1: Raw Structure Detection
     */
    private static function pass1LlmScan(string $cleanedHtml) {
        $system = "You are an intelligent HTML section parser for a visual website builder.
Analyze the HTML and extract ALL editable content sections: navigation, hero, about, services, portfolio/work, testimonials, contact, footer.

For EACH section found, return:
- id: lowercase snake_case (e.g. 'hero', 'navigation', 'services')
- label: Human-readable name (e.g. 'Hero Section', 'Navigation', 'Services')
- fields: array of editable fields inside the section

For EACH field:
- key: unique snake_case identifier (e.g. 'hero_title', 'nav_about_link')
- label: Human-readable field name (e.g. 'Hero Title', 'About Link')
- type: one of: text, textarea, image, link, email, color
- selector: precise CSS selector targeting THIS element
- default: current content of the element (if any)

RETURN EXACT FORMAT:
{
  \"sections\": [
    {
      \"id\": \"navigation\",
      \"label\": \"Navigation\",
      \"fields\": [
        {
          \"key\": \"nav_logo\",
          \"label\": \"Logo Text\",
          \"type\": \"text\",
          \"selector\": \".logo\",
          \"default\": \"Brand\"
        }
      ]
    },
    {
      \"id\": \"hero\",
      \"label\": \"Hero Section\",
      \"fields\": [
        {
          \"key\": \"hero_title\",
          \"label\": \"Hero Title\",
          \"type\": \"text\",
          \"selector\": \"h1\",
          \"default\": \"We Build Modern Businesses\"
        }
      ]
    }
  ]
}

STRICT RULES:
Return JSON only. No explanations. No markdown. No extra text. No comments. Only valid JSON.";

        $user = "Extract the RAW schema from the following cleaned HTML template:\n\n" . $cleanedHtml;
        return self::callLLM($system, $user);
    }

    /**
     * Merges high-confidence data-edit fields into RAW schema.
     */
    private static function mergeExplicitFieldsToRaw(array $raw, array $explicit) {
        if (empty($explicit)) return $raw;
        
        // We will just bundle them into a special high-priority explicit section and let pass 2 handle grouping
        if (!isset($raw['sections'])) $raw['sections'] = [];
        $raw['sections'][] = [
            'id' => 'preconfigured',
            'fields' => $explicit
        ];
        return $raw;
    }

    /**
     * Pass 2: Optimization
     */
    private static function pass2LlmOptimization(array $rawSchema) {
        $system = "You are a schema optimizer for a visual website builder editor.
Given the raw JSON schema from pass 1, optimize it:
1. Remove duplicate or empty fields.
2. Merge related fields into logical sections.
3. Every section MUST have: 'id' (snake_case), 'label' (Human Readable Title Case), 'fields' array.
4. Every field MUST have: 'key', 'label', 'type', 'selector'. Add 'default' if known.
5. Normalize field keys (e.g. heading_1 -> hero_title, paragraph_1 -> hero_description).
6. IF a field is in 'preconfigured' section, NEVER delete it. Move it to the most logical section.
7. Group fields by website sections: Navigation, Hero Section, About, Services, Work/Portfolio, Testimonials, Contact, Footer.
8. For repeated structures (cards, items) use type: 'repeat' at section level.
9. Field labels must be human-readable (e.g. 'Hero Headline', 'Email Address', 'Profile Photo').

FINAL JSON FORMAT REQUIRED:
{
  \"sections\": [
    {
      \"id\": \"navigation\",
      \"label\": \"Navigation\",
      \"fields\": [
        {
          \"key\": \"nav_logo\",
          \"label\": \"Logo Text\",
          \"type\": \"text\",
          \"selector\": \".logo\",
          \"default\": \"Brand\"
        }
      ]
    },
    {
      \"id\": \"hero\",
      \"label\": \"Hero Section\",
      \"fields\": [
        {
          \"key\": \"hero_title\",
          \"label\": \"Hero Title\",
          \"type\": \"text\",
          \"selector\": \"h1\"
        }
      ]
    }
  ]
}

STRICT RULES: Return JSON only. No explanations. No markdown. No extra text. No comments. Only valid JSON.";
        $user = "Optimize this raw schema:\n\n" . json_encode($rawSchema);
        return self::callLLM($system, $user);
    }

    private static function validateSchema($schema) {
        if (!is_array($schema) || !isset($schema['sections']) || !is_array($schema['sections'])) {
            return false;
        }
        foreach ($schema['sections'] as $sec) {
            if (!isset($sec['id']) || !isset($sec['fields'])) return false;
        }
        return true;
    }

    /**
     * Fallback Heuristics Scanner 
     */
    private static function getHeuristicFields(DOMXPath $xpath, array $usedKeys) {
        $fields = [];
        $aiSuggestions = [
            ['(//h1[not(@data-edit)])[1]',                        'hero_title',       'text',     'inner'],
            ['(//h2[not(@data-edit)])[1]',                        'section_heading',  'text',     'inner'],
            ['(//p[not(@data-edit) and string-length(.) > 30])[1]','hero_description', 'textarea', 'inner'],
            ['(//img[not(@data-edit-img)])[1]',                   'hero_image',       'image',    'src'],
            ['(//a[contains(@href,"mailto:")])[1]',               'contact_email',    'text',     'inner'],
            ['(//nav//a[not(@data-edit)])[1]',                    'nav_logo',         'text',     'inner'],
        ];

        foreach ($aiSuggestions as [$query, $key, $type, $valueMode]) {
            if (in_array($key, $usedKeys, true)) continue;

            $nodes = $xpath->query($query);
            if (!$nodes || $nodes->length === 0) continue;
            $node = $nodes->item(0);

            $fields[] = [
                'key'          => $key,
                'type'         => $type,
                'label'        => ucwords(str_replace(['_','-'], ' ', $key)),
                'selector'     => strtolower($node->nodeName) // simplified fallback selector
            ];
            $usedKeys[] = $key;
        }
        return $fields;
    }

    /**
     * Formats fallback fields into sections grouped by key prefix.
     * e.g. hero_title → Hero Section, nav_logo → Navigation, contact_email → Contact
     */
    private static function formatFallbackSchema($fields) {
        // Map key prefixes to section definitions
        $prefixMap = [
            'nav'         => ['id' => 'navigation',   'label' => 'Navigation'],
            'hero'        => ['id' => 'hero',          'label' => 'Hero Section'],
            'about'       => ['id' => 'about',         'label' => 'About'],
            'mission'     => ['id' => 'about',         'label' => 'About'],
            'vision'      => ['id' => 'about',         'label' => 'About'],
            'service'     => ['id' => 'services',      'label' => 'Services'],
            'feature'     => ['id' => 'services',      'label' => 'Services'],
            'portfolio'   => ['id' => 'portfolio',     'label' => 'Portfolio'],
            'project'     => ['id' => 'portfolio',     'label' => 'Portfolio'],
            'pricing'     => ['id' => 'pricing',       'label' => 'Pricing'],
            'plan'        => ['id' => 'pricing',       'label' => 'Pricing'],
            'testimonial' => ['id' => 'testimonials',  'label' => 'Testimonials'],
            'client'      => ['id' => 'testimonials',  'label' => 'Testimonials'],
            'team'        => ['id' => 'team',          'label' => 'Team'],
            'stat'        => ['id' => 'stats',         'label' => 'Stats'],
            'faq'         => ['id' => 'faq',           'label' => 'FAQ'],
            'contact'     => ['id' => 'contact',       'label' => 'Contact'],
            'footer'      => ['id' => 'footer',        'label' => 'Footer'],
            'social'      => ['id' => 'footer',        'label' => 'Footer'],
        ];

        $sectionOrder = ['navigation','hero','about','services','portfolio','pricing','testimonials','team','stats','faq','contact','footer','general'];
        $sections = [];

        foreach ($fields as $field) {
            $key = $field['key'] ?? '';
            $assigned = 'general';
            foreach ($prefixMap as $prefix => $sectionDef) {
                if (str_starts_with($key, $prefix)) {
                    $assigned = $sectionDef['id'];
                    break;
                }
            }
            if (!isset($sections[$assigned])) {
                // Look up label from prefixMap values
                $label = 'General Settings';
                foreach ($prefixMap as $sectionDef) {
                    if ($sectionDef['id'] === $assigned) { $label = $sectionDef['label']; break; }
                }
                $sections[$assigned] = ['id' => $assigned, 'label' => $label, 'fields' => []];
            }
            $sections[$assigned]['fields'][] = $field;
        }

        // Sort sections by the defined order
        $ordered = [];
        foreach ($sectionOrder as $id) {
            if (isset($sections[$id])) $ordered[] = $sections[$id];
        }

        return ['sections' => $ordered];
    }

    private static function countFields($schema) {
        $count = 0;
        if (isset($schema['sections']) && is_array($schema['sections'])) {
            foreach ($schema['sections'] as $sec) {
                if (isset($sec['fields']) && is_array($sec['fields'])) {
                    $count += count($sec['fields']);
                }
            }
        }
        return $count;
    }

    /**
     * Write final layout to disk
     */
    public static function generateSchema(string $templateDir, array $scanResult) {
        $schema = [
            'generated' => date('Y-m-d H:i:s'),
            'sections'  => $scanResult['schema']['sections'] ?? []
        ];
        
        $path = $templateDir . '/schema.json';
        file_put_contents($path, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $schema;
    }
}
