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
        $htmlHash = md5($html);
        $cacheFile = dirname($htmlFile) . '/.scan_cache_' . $htmlHash . '.json';

        // Check Cache first
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && isset($cached['schema'])) {
                return $cached;
            }
        }

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
        
        // --- STEP 3: API Pipeline (Modernized to ONE-PASS)
        try {
            $optimized = self::runOnePassLlmScan($cleanedHtml, $explicitFields);
            
            if ($optimized && self::validateSchema($optimized)) {
                $finalSchema = $optimized;
            }
        } catch (Exception $e) {
            error_log("CodeCanvas LLM Scanner Error: " . $e->getMessage());
        }

        // --- STEP 4: Fallback Mechanism (if LLM completely fails)
        if (!$finalSchema) {
            error_log("CodeCanvas: LLM Pipeline failed or returned invalid response. Falling back to Heuristic Scanner.");
            $heuristicFields = self::getHeuristicFields($xpath, array_column($explicitFields, 'key'));
            $finalSchema = self::formatFallbackSchema(array_merge($explicitFields, $heuristicFields));
        }

        $result = [
            'schema'   => $finalSchema,
            'total'    => self::countFields($finalSchema),
            'html_file'=> basename($htmlFile)
        ];

        // Save to cache
        file_put_contents($cacheFile, json_encode($result));

        return $result;
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
        $removeTags = ['script', 'style', 'svg', 'meta', 'link', 'noscript', 'iframe', 'canvas', 'video', 'audio', 'object', 'embed', 'head'];
        $xpath = new DOMXPath($dom);
        
        foreach ($removeTags as $tag) {
            $nodes = $xpath->query("//{$tag}");
            foreach ($nodes as $node) {
                if ($node->parentNode) $node->parentNode->removeChild($node);
            }
        }

        $preserveTags = ['header', 'footer', 'section', 'h1', 'h2', 'h3', 'h4', 'p', 'img', 'a', 'ul', 'li', 'button'];
        
        $allNodes = $xpath->query("//*");
        $nodesToRemove = [];
        
        foreach ($allNodes as $node) {
            if ($node->nodeName === '#text') continue;

            // Remove attributes except key structural ones
            $removeAttrs = [];
            foreach ($node->attributes as $attrName => $attrNode) {
                if (!in_array($attrName, ['class', 'src', 'alt', 'id']) && !str_starts_with($attrName, 'data-edit')) {
                    $removeAttrs[] = $attrName;
                }
            }
            foreach ($removeAttrs as $attr) {
                $node->removeAttribute($attr);
            }

            // Truncate text content
            if ($node->childNodes->length === 1 && $node->firstChild->nodeType === XML_TEXT_NODE) {
                $text = trim($node->nodeValue);
                if (strlen($text) > 80) {
                    $node->nodeValue = substr($text, 0, 80) . '...';
                }
            }

            $nodeName = strtolower($node->nodeName);
            if (!in_array($nodeName, $preserveTags) && !in_array($nodeName, ['html', 'body', 'div', 'span'])) {
                $nodesToRemove[] = $node;
            }
        }

        // Simplify non-essential tags
        foreach ($nodesToRemove as $node) {
            if (!$node->parentNode) continue;
            while ($node->childNodes->length > 0) {
                $node->parentNode->insertBefore($node->childNodes->item(0), $node);
            }
            $node->parentNode->removeChild($node);
        }

        $cleanHtml = $dom->saveHTML();
        if (strlen($cleanHtml) > 25000) { 
            $cleanHtml = substr($cleanHtml, 0, 25000) . "</body></html>";
        }
        
        return $cleanHtml;
    }

    private static function callLLM(string $systemPrompt, string $userPrompt) {
        $provider = $_ENV['LLM_PROVIDER'] ?? getenv('LLM_PROVIDER') ?? 'nvidia';
        $apiKey   = ($provider === 'nvidia') ? ($_ENV['NVIDIA_API_KEY'] ?? getenv('NVIDIA_API_KEY')) : ($_ENV['GOOGLE_AI_KEY'] ?? getenv('GOOGLE_AI_KEY'));
        $model    = $_ENV['LLM_MODEL'] ?? getenv('LLM_MODEL') ?? 'deepseek-ai/deepseek-v3.2';
        $timeout  = (int)($_ENV['LLM_TIMEOUT'] ?? getenv('LLM_TIMEOUT') ?? 30);

        if ($provider === 'google' || str_contains($model, 'gemini')) {
            return self::callGoogleAI($apiKey, $model, $systemPrompt, $userPrompt, $timeout);
        }

        // Default NVIDIA implementation
        $url = 'https://integrate.api.nvidia.com/v1/chat/completions';
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.1,
            'max_tokens' => 2500,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) throw new Exception("LLM cURL Error: " . $err);

        $json = json_decode($response, true);
        $content = $json['choices'][0]['message']['content'] ?? '';
        
        if (empty($content)) {
            throw new Exception("Empty response from LLM. Raw: " . substr((string)$response, 0, 200));
        }

        // Clean JSON formatting
        $content = preg_replace('/^```json\s*|\s*```$/i', '', trim($content));
        if (!str_starts_with($content, '{')) {
            preg_match('/\{.*\}/s', $content, $matches);
            $content = $matches[0] ?? '{}';
        }

        return json_decode($content, true);
    }

    private static function callGoogleAI($apiKey, $model, $system, $user, $timeout) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
        $data = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $system . "\n\nUser Task: " . $user]]]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        return json_decode($text, true);
    }

    /**
     * Complete One-Pass Scan & Optimization
     */
    private static function runOnePassLlmScan(string $cleanedHtml, array $explicitFields) {
        $system = "You are a professional website template parser. 
        Analyze the following HTML and extract essential editable content sections (Navigation, Hero, About, Services, Portfolio, Contact, Footer).
        
        Strict Rules:
        1. Identify sections and fields for a visual builder.
        2. Field Types: text, textarea, image, link, color.
        3. Use precise CSS selectors.
        4. If a field exists in 'REQUIRED FIELDS' list, YOU MUST INCLUDE IT in the correct section.
        5. Group logically into sections.
        6. REPEATABLE sections (like services or cards) should mark their section as 'type': 'repeat'.
        
        REQUIRED FIELDS (Preconfigured via data-edit):
        " . json_encode($explicitFields) . "
        
        Return pure JSON in this format:
        {
          \"sections\": [
            {
              \"id\": \"hero\",
              \"label\": \"Hero Section\",
                 \"type\": \"normal\",
              \"fields\": [
                { \"key\": \"hero_title\", \"label\": \"Headline\", \"type\": \"text\", \"selector\": \"h1\", \"default\": \"Hello World\" }
              ]
            }
          ]
        }";

        $user = "Process this HTML and merge/optimize with the required fields:\n\n" . $cleanedHtml;
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
