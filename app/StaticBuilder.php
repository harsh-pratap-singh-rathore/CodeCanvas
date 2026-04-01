<?php
require_once APP_ROOT . '/config/bootstrap.php';
/**
 * Static Website Builder Engine
 * Version: 2.0 — Production Deployment Ready
 *
 * Changes from v1.0:
 *  - CSS pipeline: merges global.css + template CSS, deduplicates imports
 *  - Output: assets/css/style.min.css (single stylesheet)
 *  - HTML link updated to point to style.min.css
 *  - netlify.toml generated in every deployment folder
 *  - Uses APP_ROOT constant for portable path resolution
 */

class StaticBuilder
{
    private $pdo;
    private $project;
    private $buildPath;
    private $errors = [];
    private $realTemplateDir;
    private $realTemplateFile;

    public function __construct($pdo, $projectId)
    {
        $this->pdo = $pdo;
        $this->loadProjectData($projectId);
        $this->resolveTemplateSource();
    }

    private function loadProjectData($projectId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, t.folder_path, t.slug AS template_slug
             FROM projects p
             JOIN templates t ON p.template_id = t.id
             WHERE p.id = ?"
        );
        $stmt->execute([$projectId]);
        $this->project = $stmt->fetch();
        if (!$this->project) throw new Exception("Project not found");
    }

    private function resolveTemplateSource(): void
    {
        $root       = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $folderPath = rtrim($this->project['folder_path'], '/');
        $baseDir    = $root . '/' . $folderPath;

        $candidates = ['code.html', 'index.html', 'index.htm'];
        
        // 1. Check root
        foreach ($candidates as $c) {
            if (file_exists($baseDir . '/' . $c)) {
                $this->realTemplateDir  = $baseDir;
                $this->realTemplateFile = $c;
                return;
            }
        }

        // 2. Check 1 level deep (modern dev portfolio case)
        $subDirs = glob($baseDir . '/*', GLOB_ONLYDIR);
        foreach ($subDirs as $dir) {
            foreach ($candidates as $c) {
                if (file_exists($dir . '/' . $c)) {
                    $this->realTemplateDir  = $dir;
                    $this->realTemplateFile = $c;
                    return;
                }
            }
        }

        throw new Exception("Could not find a valid HTML template in {$folderPath}");
    }

    public function build(string $slug): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $this->buildPath = $root . "/deployments/{$slug}/";

        try {
            $this->initDirectory();

            $html = $this->processHtml();
            file_put_contents($this->buildPath . 'index.html', $html);

            // BUNDLE CSS if local files exist
            $hasLocalCss = (
                file_exists($this->realTemplateDir . '/style.css') ||
                file_exists($this->realTemplateDir . '/assets/css/style.css') ||
                file_exists($this->realTemplateDir . '/css/style.css') ||
                file_exists($this->realTemplateDir . '/css/template.css')
            );

            if ($hasLocalCss) {
                $css = $this->processCss();
                if (!is_dir($this->buildPath . 'assets/css')) mkdir($this->buildPath . 'assets/css', 0755, true);
                file_put_contents($this->buildPath . 'assets/css/style.min.css', $css);
            }

            $this->copyAssets();
            $this->writeVercelJson();
            $this->validateDeploymentFolder();
            $this->validateBuild();

            // Status is now handled by DeployController directly to 'deploying' before build
            return $this->buildPath;

        } catch (Exception $e) {
            throw $e;
        }
    }

    // ─── Directory setup ──────────────────────────────────────────────────────

    private function initDirectory(): void
    {
        foreach (['', 'assets/css', 'assets/js', 'assets/images'] as $dir) {
            $path = $this->buildPath . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    // ─── HTML processing ──────────────────────────────────────────────────────

    private function processHtml(): string
    {
        $html     = file_get_contents($this->realTemplateDir . '/' . $this->realTemplateFile);
        $userData = json_decode($this->project['content_json'] ?? '{}', true) ?: [];

        // ── Schema-based injection (same engine as project-publish.php) ────────
        $schemaPath = $this->realTemplateDir . '/schema.json';
        if (!file_exists($schemaPath)) {
            // Fallback to one level up (base template dir)
            $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
            $folderPath = rtrim($this->project['folder_path'], '/');
            $schemaPath = $root . '/' . $folderPath . '/schema.json';
        }
        if (file_exists($schemaPath)) {
            // Load publish helpers (applyUserDataToHTML, replaceSkillsGrid, etc.)
            require_once APP_ROOT . '/app/publish_helpers.php';

            $schema = json_decode(file_get_contents($schemaPath), true);

            if ($schema && isset($schema['fields'])) {
                // Normalise full_name to fix duplicate-line bug
                if (!empty($userData['full_name'])) {
                    $rawName = str_replace("\r\n", "\n", $userData['full_name']);
                    $lines   = array_filter(array_map('trim', explode("\n", $rawName)));
                    $seen    = []; $deduped = [];
                    foreach (array_values($lines) as $line) {
                        $key = strtolower($line);
                        if (!in_array($key, $seen, true)) { $deduped[] = $line; $seen[] = $key; }
                    }
                    $userData['full_name'] = implode("\n", $deduped);
                }

                $html = applyUserDataToHTML($html, $schema['fields'], $userData);
            }
        } else {
            // Legacy {{placeholder}} substitution for non-schema templates
            foreach ($userData as $key => $val) {
                if (is_string($val)) {
                    $html = str_replace("{{{$key}}}", htmlspecialchars($val, ENT_QUOTES, 'UTF-8'), $html);
                }
            }
            // SEO tokens
            $html = strtr($html, [
                '{{title}}'       => htmlspecialchars($this->project['project_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                '{{description}}' => htmlspecialchars($this->project['description']   ?? '', ENT_QUOTES, 'UTF-8'),
            ]);
        }

        // ── Inject contact-form bridge script ─────────────────────────────────
        $projectId    = $this->project['id'];
        // Use API_BASE_URL so deployed Vercel portfolios call the live CodeCanvas server
        // e.g. https://codecanvas.page/app/api — NOT localhost
        $apiBase      = defined('API_BASE_URL') ? API_BASE_URL : (rtrim(BASE_URL, '/') . '/app/api');
        $bridgeScript = "<script>\n"
            . "        window.CODECANVAS_PROJECT_ID = " . json_encode($projectId) . ";\n"
            . "        window.CODECANVAS_API_BASE   = " . json_encode($apiBase) . ";\n"
            . "\n"
            . "        document.addEventListener('DOMContentLoaded', function() {\n"
            . "            var contactForm = document.querySelector('#contact form');\n"
            . "            if (!contactForm) return;\n"
            . "            var submitBtn = contactForm.querySelector('button[type=\"submit\"], button:not([type=\"button\"])');\n"
            . "            if (!submitBtn) submitBtn = contactForm.querySelector('button');\n"
            . "            var originalText = submitBtn ? submitBtn.innerText : 'SEND';\n"
            . "\n"
            . "            contactForm.addEventListener('submit', async function(e) {\n"
            . "                e.preventDefault();\n"
            . "                if (submitBtn) { submitBtn.disabled = true; submitBtn.innerText = 'SENDING...'; }\n"
            . "                var formData = {\n"
            . "                    project_id: window.CODECANVAS_PROJECT_ID,\n"
            . "                    name:    (contactForm.querySelector('[name=\"name\"],#name') || {}).value || '',\n"
            . "                    email:   (contactForm.querySelector('[name=\"email\"],#email') || {}).value || '',\n"
            . "                    subject: (contactForm.querySelector('[name=\"subject\"],#subject') || {}).value || 'Portfolio Contact',\n"
            . "                    message: (contactForm.querySelector('[name=\"message\"],#message') || {}).value || ''\n"
            . "                };\n"
            . "                try {\n"
            . "                    var res = await fetch(window.CODECANVAS_API_BASE + '/contact.php', {\n"
            . "                        method: 'POST',\n"
            . "                        headers: { 'Content-Type': 'application/json' },\n"
            . "                        body: JSON.stringify(formData)\n"
            . "                    });\n"
            . "                    var data = await res.json();\n"
            . "                    if (data.success) {\n"
            . "                        contactForm.reset();\n"
            . "                        alert('Message sent successfully! The portfolio owner will be notified.');\n"
            . "                    } else {\n"
            . "                        alert(data.message || 'Failed to send message. Please try again.');\n"
            . "                    }\n"
            . "                } catch (err) {\n"
            . "                    alert('Network error. Please check your connection and try again.');\n"
            . "                } finally {\n"
            . "                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerText = originalText; }\n"
            . "                }\n"
            . "            });\n"
            . "        });\n"
            . "    </script>";
        $html = str_ireplace('</head>', $bridgeScript . "\n</head>", $html);


        // ── 4. CSS stripping/injection ───────────────────────────────────────
        $hasLocalCss = (
            file_exists($this->realTemplateDir . '/style.css') ||
            file_exists($this->realTemplateDir . '/assets/css/style.css') ||
            file_exists($this->realTemplateDir . '/css/style.css') ||
            file_exists($this->realTemplateDir . '/css/template.css')
        );

        if ($hasLocalCss) {
            // Strip ONLY SaaS dashboard stylesheets (relative paths / localhost).
            // Pattern: <link rel="stylesheet" href="..."> where href does NOT start with http/https
            $html = preg_replace_callback(
                '/<link\b([^>]*)rel=["\'](stylesheet)["\']([^>]*)>/i',
                function ($m) {
                    $full = $m[0];
                    if (preg_match('/href=["\']([^"\']*)["\']/', $full, $hm)) {
                        $href = $hm[1];
                        if (
                            str_starts_with($href, 'https://') ||
                            str_starts_with($href, 'http://')  ||
                            str_starts_with($href, '//')
                        ) {
                            return $full; // preserve CDN links
                        }
                    }
                    return ''; // strip local/relative stylesheet links only
                },
                $html
            );

            // Inject the bundled stylesheet
            $html = str_ireplace(
                '</head>',
                '  <link rel="stylesheet" href="assets/css/style.min.css">' . "\n</head>",
                $html
            );
        }

        // ── Inject mobile-nav.js ───────────────────────────────────────────────
        if (stripos($html, 'mobile-nav.js') === false) {
            $html = str_ireplace(
                '</body>',
                '  <script src="assets/js/mobile-nav.js"></script>' . "\n</body>",
                $html
            );
        }

        // ── 5. SEO & Favicon Overrides ───────────────────────────────────────
        $seoTitle = $this->project['seo_title'] ?: $this->project['project_name'];
        if (!empty($seoTitle)) {
            // Replace existing <title> tag content or add one if missing
            if (preg_match('/<title>(.*?)<\/title>/i', $html)) {
                $html = preg_replace('/<title>(.*?)<\/title>/i', '<title>' . htmlspecialchars($seoTitle) . '</title>', $html);
            } else {
                $html = str_ireplace('</head>', '  <title>' . htmlspecialchars($seoTitle) . '</title>' . "\n</head>", $html);
            }
        }

        if (!empty($this->project['favicon_url'])) {
            $faviconTag = '  <link rel="icon" href="' . htmlspecialchars($this->project['favicon_url']) . '">';
            // Strip existing favicon tags first
            $html = preg_replace('/<link\s+rel=["\'](icon|shortcut icon)["\'][^>]*>/i', '', $html);
            $html = str_ireplace('</head>', $faviconTag . "\n</head>", $html);
        }

        return $html;
    }

    // ─── CSS Pipeline ─────────────────────────────────────────────────────────

    /**
     * Merge global.css + template CSS → one deduplicated stylesheet.
     * Injects user CSS custom properties at the top.
     */
    private function processCss(): string
    {
        $root       = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $folderPath = rtrim($this->project['folder_path'], '/');
        $userData   = json_decode($this->project['content_json'] ?? '{}', true) ?: [];

        // ── 1. CSS custom properties from user data ───────────────────────────
        $primary       = $userData['primary_color']    ?? '#000000';
        $secondary     = $userData['secondary_color']  ?? '#666666';
        $background    = $userData['background_color'] ?? '#ffffff';
        $textColor     = $userData['text_color']       ?? '#0F0F0F';

        $rootVars  = ":root {\n";
        $rootVars .= "  --primary-color:    {$primary};\n";
        $rootVars .= "  --secondary-color:  {$secondary};\n";
        $rootVars .= "  --background-color: {$background};\n";
        $rootVars .= "  --text-color:       {$textColor};\n";
        $rootVars .= "}\n\n";

        // ── 2. Collect CSS layers in order ────────────────────────────────────
        // Priority: global reset → template main → template extras
        $sources = [
            $root . '/public/assets/css/style.css',            // global SaaS CSS
            $root . '/public/assets/css/responsive.css',       // global SaaS mobile responsiveness
            $this->realTemplateDir . '/style.css',             // template root
            $this->realTemplateDir . '/assets/css/style.css',  // template assets/css
            $this->realTemplateDir . '/css/style.css',         // template css/style.css
            $this->realTemplateDir . '/css/template.css',      // template css/template.css
        ];

        $merged = $rootVars;
        foreach ($sources as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $merged .= "\n/* === " . basename(dirname($path)) . '/' . basename($path) . " === */\n";
                $merged .= $this->stripDuplicateDeclarations($content);
            }
        }

        return $merged;
    }

    /**
     * Remove duplicate @charset and @import lines (keep first occurrence).
     */
    private function stripDuplicateDeclarations(string $css): string
    {
        $charsetSeen = false;
        $importsSeen = [];
        $lines       = explode("\n", $css);
        $output      = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // @charset — keep only first occurrence globally
            if (preg_match('/^@charset\s/i', $trimmed)) {
                if (!$charsetSeen) {
                    $charsetSeen = true;
                    $output[]    = $line;
                }
                continue;
            }

            // @import — deduplicate by URL
            if (preg_match('/^@import\s+[\'"](.+?)[\'"]/i', $trimmed, $m)) {
                $importUrl = $m[1];
                if (!in_array($importUrl, $importsSeen, true)) {
                    $importsSeen[] = $importUrl;
                    $output[]      = $line;
                }
                continue;
            }

            $output[] = $line;
        }

        return implode("\n", $output);
    }

    // ─── Asset copying ────────────────────────────────────────────────────────

    private function copyAssets(): void
    {
        // ── 1. Copy ALL template files/folders from realTemplateDir ──────────
        $dir = opendir($this->realTemplateDir);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..' || $file === $this->realTemplateFile || $file === 'schema.json') continue;
            
            $src = $this->realTemplateDir . '/' . $file;
            $dst = $this->buildPath . $file;
            
            if (is_dir($src)) {
                $this->recurseCopy($src, $dst);
            } else {
                copy($src, $dst);
            }
        }
        closedir($dir);

        // ── 2. Add mobile-nav.js dependency ──────────────────────────────────
        $root         = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $mobileNavSrc = $root . '/public/assets/js/mobile-nav.js';
        $mobileNavDst = $this->buildPath . 'assets/js/mobile-nav.js'; // It expects it in assets/js
        
        if (file_exists($mobileNavSrc)) {
            if (!is_dir(dirname($mobileNavDst))) {
                mkdir(dirname($mobileNavDst), 0755, true);
            }
            copy($mobileNavSrc, $mobileNavDst);
        }
    }

    // ─── Build validation ─────────────────────────────────────────────────────

    /**
     * Generate vercel.json for correct content types and routing in Vercel.
     * Required because the Vercel API might guess content types incorrectly for base64 uploads without it.
     */
    private function writeVercelJson(): void
    {
        $vercelConfig = [
            "version" => 2,
            "cleanUrls" => true,
            "headers" => [
                [
                    "source" => "/(.*)",
                    "headers" => [
                        [
                            "key" => "Cache-Control",
                            "value" => "public, max-age=0, must-revalidate"
                        ]
                    ]
                ],
                [
                    "source" => "/assets/css/(.*)",
                    "headers" => [
                        [
                            "key" => "Content-Type",
                            "value" => "text/css; charset=utf-8"
                        ],
                        [
                            "key" => "Cache-Control",
                            "value" => "public, max-age=31536000, immutable"
                        ]
                    ]
                ],
                [
                    "source" => "/assets/js/(.*)",
                    "headers" => [
                        [
                            "key" => "Content-Type",
                            "value" => "application/javascript; charset=utf-8"
                        ],
                        [
                            "key" => "Cache-Control",
                            "value" => "public, max-age=31536000, immutable"
                        ]
                    ]
                ]
            ]
        ];

        file_put_contents(
            $this->buildPath . 'vercel.json',
            json_encode($vercelConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Stricly validates that the essential build directories and files exist.
     * Prevents partial/broken deployments.
     */
    private function validateDeploymentFolder(): void
    {
        $required = [
            'index.html',
            'assets/css',
            'assets/js',
            'assets/images'
        ];

        foreach ($required as $req) {
            $path = $this->buildPath . $req;
            if (!file_exists($path)) {
                $this->errors[] = "Missing critical deployment target: {$req}";
            }
        }
        
        // Ensure no PHP files leaked into build
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->buildPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            if (strtolower($file->getExtension()) === 'php') {
                $this->errors[] = "Security rule violated: PHP file found in build output ({$file->getFilename()})";
            }
        }
    }


    private function validateBuild(): void
    {
        $htmlPath = $this->buildPath . 'index.html';
        if (!file_exists($htmlPath)) return;
        
        $html = file_get_contents($htmlPath);

        // Unresolved template variables
        if (preg_match('/\{\{[a-zA-Z0-9_]+\}\}/', $html, $m)) {
            $this->errors[] = "Unresolved variable: {$m[0]}";
        }

        // Absolute localhost paths still present
        if (preg_match('/https?:\/\/localhost/i', $html, $m)) {
            $this->errors[] = "Localhost URL found — will break in production";
        }

        // --- Broken internal link checker ---
        // Check local CSS includes
        preg_match_all('/<link\b[^>]*rel=["\']stylesheet["\'][^>]*href=["\'](assets\/[^"\']+)["\'][^>]*>/i', $html, $cssMatches);
        foreach ($cssMatches[1] as $cssPath) {
            if (!file_exists($this->buildPath . $cssPath)) {
                $this->errors[] = "Broken link: Missing CSS file {$cssPath}";
            }
        }
        if (count($cssMatches[0]) > 1) {
             // For production we ideally want 1 bundled CSS, but don't strictly block deploy unless missing.
             // Just warn in logs if multiple found. 
             error_log('[StaticBuilder] Warning: ' . count($cssMatches[0]) . ' local CSS files found. Recommend bundling.');
        }

        // Check local JS includes
        preg_match_all('/<script\b[^>]*src=["\'](assets\/[^"\']+)["\'][^>]*>/i', $html, $jsMatches);
        foreach ($jsMatches[1] as $jsPath) {
            if (!file_exists($this->buildPath . $jsPath)) {
                $this->errors[] = "Broken link: Missing JS file {$jsPath}";
            }
        }
        
        // Check local images
        preg_match_all('/<img\b[^>]*src=["\'](assets\/[^"\']+)["\'][^>]*>/i', $html, $imgMatches);
        foreach ($imgMatches[1] as $imgPath) {
            if (!file_exists($this->buildPath . $imgPath)) {
                $this->errors[] = "Broken link: Missing image file {$imgPath}";
            }
        }

        // If errors found, throw exception to firmly block deployment
        if (!empty($this->errors)) {
            $errorMsg = '[StaticBuilder] Validation strictly blocked deployment due to errors: ' . implode('; ', $this->errors);
            error_log($errorMsg);
            throw new Exception("Validation failed: " . implode('; ', $this->errors));
        }
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;
            if (is_dir("{$src}/{$file}")) {
                $this->recurseCopy("{$src}/{$file}", "{$dst}/{$file}");
            } else {
                copy("{$src}/{$file}", "{$dst}/{$file}");
            }
        }
        closedir($dir);
    }
}
