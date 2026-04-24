<?php
require_once APP_ROOT . '/config/bootstrap.php';
/**
 * Static Website Builder Engine
 * Version: 2.1 — Production Deployment Ready (Universal Normalization Fix)
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
             LEFT JOIN templates t ON p.template_id = t.id
             WHERE p.id = ?"
        );
        $stmt->execute([$projectId]);
        $this->project = $stmt->fetch();
        if (!$this->project) throw new Exception("Project not found");
    }

    private function resolveTemplateSource(): void
    {
        if ($this->project['is_ai_generated']) {
            return; // No template source needed for AI projects
        }
        $root       = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $folderPath = rtrim($this->project['folder_path'], '/');
        $baseDir    = $root . '/' . $folderPath;

        $candidates = ['index.html', 'code.html', 'index.htm'];
        foreach ($candidates as $c) {
            if (file_exists($baseDir . '/' . $c)) {
                $this->realTemplateDir  = $baseDir;
                $this->realTemplateFile = $c;
                return;
            }
        }
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
            return $this->buildPath;
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function initDirectory(): void
    {
        foreach (['', 'assets/css', 'assets/js', 'assets/images'] as $dir) {
            $path = $this->buildPath . $dir;
            if (!is_dir($path)) mkdir($path, 0755, true);
        }
    }

    private function processHtml(): string
    {
        if ($this->project['is_ai_generated']) {
            $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
            $path = $root . '/' . ltrim($this->project['html_path'], '/');
            if (file_exists($path)) {
                return file_get_contents($path);
            }
            throw new Exception("AI project HTML file not found at " . $path);
        }

        $html     = file_get_contents($this->realTemplateDir . '/' . $this->realTemplateFile);
        $userData = json_decode($this->project['content_json'] ?? '{}', true) ?: [];

        $schemaPath = $this->realTemplateDir . '/schema.json';
        if (!file_exists($schemaPath)) {
            $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
            $folderPath = rtrim($this->project['folder_path'], '/');
            $schemaPath = $root . '/' . $folderPath . '/schema.json';
        }
        if (file_exists($schemaPath)) {
            require_once APP_ROOT . '/app/publish_helpers.php';
            $schema = json_decode(file_get_contents($schemaPath), true);
            if ($schema && !isset($schema['fields']) && isset($schema['sections'])) {
                $schema['fields'] = [];
                foreach ($schema['sections'] as $sec) {
                    if (isset($sec['fields']) && is_array($sec['fields'])) {
                        foreach ($sec['fields'] as $x) {
                            if (!isset($x['id']) && isset($x['key'])) $x['id'] = $x['key'];
                            $schema['fields'][] = $x;
                        }
                    }
                }
            }
            if ($schema && isset($schema['fields'])) {
                if (!empty($userData['full_name'])) {
                    $rawName = str_replace("\r\n", "\n", $userData['full_name']);
                    $lines   = array_filter(array_map('trim', explode("\n", $rawName)));
                    $seen = []; $deduped = [];
                    foreach (array_values($lines) as $line) {
                        $key = strtolower($line);
                        if (!in_array($key, $seen, true)) { $deduped[] = $line; $seen[] = $key; }
                    }
                    $userData['full_name'] = implode("\n", $deduped);
                }
                $html = applyUserDataToHTML($html, $schema['fields'], $userData);
            }
        } else {
            foreach ($userData as $key => $val) {
                if (is_string($val)) $html = str_replace("{{{$key}}}", htmlspecialchars($val, ENT_QUOTES, 'UTF-8'), $html);
            }
            $html = strtr($html, [
                '{{title}}'       => htmlspecialchars($this->project['project_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                '{{description}}' => htmlspecialchars($this->project['description']   ?? '', ENT_QUOTES, 'UTF-8'),
            ]);
        }

        $projectId    = $this->project['id'];
        $apiBase      = defined('API_BASE_URL') ? API_BASE_URL : (rtrim(BASE_URL, '/') . '/app/api');
        $bridgeScript = "<script>\n"
            . "        window.CODECANVAS_PROJECT_ID = " . json_encode($projectId) . ";\n"
            . "        window.CODECANVAS_API_BASE   = " . json_encode($apiBase) . ";\n"
            . "        document.addEventListener('DOMContentLoaded', function() {\n"
            . "            var contactForm = document.querySelector('#contact form');\n"
            . "            if (!contactForm) return;\n"
            . "            var submitBtn = contactForm.querySelector('button[type=\"submit\"], button:not([type=\"button\"])');\n"
            . "            if (!submitBtn) submitBtn = contactForm.querySelector('button');\n"
            . "            var originalText = submitBtn ? submitBtn.innerText : 'SEND';\n"
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
            . "                        method: 'POST', headers: { 'Content-Type': 'application/json' },\n"
            . "                        body: JSON.stringify(formData)\n"
            . "                    });\n"
            . "                    var data = await res.json();\n"
            . "                    if (data.success) {\n"
            . "                        contactForm.reset(); alert('Message sent successfully!');\n"
            . "                    } else { alert(data.message || 'Error'); }\n"
            . "                } catch (err) { alert('Network error'); } finally {\n"
            . "                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerText = originalText; }\n"
            . "                }\n"
            . "            });\n"
            . "        });\n"
            . "    </script>";
        $html = str_ireplace('</head>', $bridgeScript . "\n</head>", $html);

        $hasLocalCss = (
            file_exists($this->realTemplateDir . '/style.css') ||
            file_exists($this->realTemplateDir . '/assets/css/style.css') ||
            file_exists($this->realTemplateDir . '/css/style.css') ||
            file_exists($this->realTemplateDir . '/css/template.css')
        );
        if ($hasLocalCss) {
            $html = preg_replace_callback('/<link\b([^>]*)rel=["\'](stylesheet)["\']([^>]*)>/i', function ($m) {
                if (preg_match('/href=["\']([^"\']*)["\']/', $m[0], $hm)) {
                    $href = $hm[1];
                    if (str_starts_with($href, 'http') || str_starts_with($href, '//')) return $m[0];
                }
                return '';
            }, $html);
            $html = str_ireplace('</head>', '  <link rel="stylesheet" href="assets/css/style.min.css">' . "\n</head>", $html);
        }
        if (stripos($html, 'mobile-nav.js') === false) $html = str_ireplace('</body>', '  <script src="assets/js/mobile-nav.js"></script>' . "\n</body>", $html);

        $seoTitle = $this->project['seo_title'] ?: $this->project['project_name'];
        if (!empty($seoTitle)) {
            if (preg_match('/<title>(.*?)<\/title>/i', $html)) $html = preg_replace('/<title>(.*?)<\/title>/i', '<title>' . htmlspecialchars($seoTitle) . '</title>', $html);
            else $html = str_ireplace('</head>', '  <title>' . htmlspecialchars($seoTitle) . '</title>' . "\n</head>", $html);
        }
        if (!empty($this->project['favicon_url'])) {
            $html = preg_replace('/<link\s+rel=["\'](icon|shortcut icon)["\'][^>]*>/i', '', $html);
            $html = str_ireplace('</head>', '  <link rel="icon" href="' . htmlspecialchars($this->project['favicon_url']) . '">' . "\n</head>", $html);
        }
        return $html;
    }

    private function processCss(): string
    {
        $root       = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $userData   = json_decode($this->project['content_json'] ?? '{}', true) ?: [];
        $primary    = $userData['primary_color']    ?? '#000000';
        $secondary  = $userData['secondary_color']  ?? '#666666';
        $background = $userData['background_color'] ?? '#ffffff';
        $textColor  = $userData['text_color']       ?? '#0F0F0F';
        $merged     = ":root {\n  --primary-color: {$primary}; --secondary-color: {$secondary}; --background-color: {$background}; --text-color: {$textColor};\n}\n\n";
        $sources    = [ $root . '/public/assets/css/style.css', $root . '/public/assets/css/responsive.css', $this->realTemplateDir . '/style.css', $this->realTemplateDir . '/assets/css/style.css', $this->realTemplateDir . '/css/style.css', $this->realTemplateDir . '/css/template.css' ];
        foreach ($sources as $path) {
            if (file_exists($path)) $merged .= "\n/* === " . basename(dirname($path)) . '/' . basename($path) . " === */\n" . $this->stripDuplicateDeclarations(file_get_contents($path));
        }
        return $merged;
    }

    private function stripDuplicateDeclarations(string $css): string
    {
        $charsetSeen = false; $importsSeen = []; $output = [];
        foreach (explode("\n", $css) as $line) {
            $trimmed = trim($line);
            if (preg_match('/^@charset\s/i', $trimmed)) { if (!$charsetSeen) { $charsetSeen = true; $output[] = $line; } continue; }
            if (preg_match('/^@import\s+[\'"](.+?)[\'"]/i', $trimmed, $m)) { if (!in_array($m[1], $importsSeen, true)) { $importsSeen[] = $m[1]; $output[] = $line; } continue; }
            $output[] = $line;
        }
        return implode("\n", $output);
    }

    private function copyAssets(): void
    {
        if ($this->project['is_ai_generated']) {
            $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
            $srcDir = $root . '/public/output/' . $this->project['id'];
            if (is_dir($srcDir)) {
                $dir = opendir($srcDir);
                while (false !== ($file = readdir($dir))) {
                    if ($file === '.' || $file === '..' || $file === 'index.html') continue;
                    $src = $srcDir . '/' . $file;
                    $dst = $this->buildPath . $file;
                    if (is_dir($src)) $this->recurseCopy($src, $dst); else copy($src, $dst);
                }
                closedir($dir);
            }
            return;
        }

        $dir = opendir($this->realTemplateDir);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..' || $file === $this->realTemplateFile || $file === 'schema.json') continue;
            $src = $this->realTemplateDir . '/' . $file;
            $dst = $this->buildPath . $file;
            if (is_dir($src)) $this->recurseCopy($src, $dst); else copy($src, $dst);
        }
        closedir($dir);
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $mobileNavSrc = $root . '/public/assets/js/mobile-nav.js';
        $mobileNavDst = $this->buildPath . 'assets/js/mobile-nav.js';
        if (file_exists($mobileNavSrc)) { if (!is_dir(dirname($mobileNavDst))) mkdir(dirname($mobileNavDst), 0755, true); copy($mobileNavSrc, $mobileNavDst); }
    }

    private function writeVercelJson(): void
    {
        $vercelConfig = [ "version" => 2, "cleanUrls" => true, "headers" => [ [ "source" => "/(.*)", "headers" => [ [ "key" => "Cache-Control", "value" => "public, max-age=0, must-revalidate" ] ] ], [ "source" => "/assets/css/(.*)", "headers" => [ [ "key" => "Content-Type", "value" => "text/css; charset=utf-8" ], [ "key" => "Cache-Control", "value" => "public, max-age=31536000, immutable" ] ] ], [ "source" => "/assets/js/(.*)", "headers" => [ [ "key" => "Content-Type", "value" => "application/javascript; charset=utf-8" ], [ "key" => "Cache-Control", "value" => "public, max-age=31536000, immutable" ] ] ] ] ];
        file_put_contents($this->buildPath . 'vercel.json', json_encode($vercelConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function validateDeploymentFolder(): void
    {
        foreach (['index.html', 'assets/css', 'assets/js', 'assets/images'] as $req) if (!file_exists($this->buildPath . $req)) $this->errors[] = "Missing critical deployment target: {$req}";
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->buildPath, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $file) if (strtolower($file->getExtension()) === 'php') $this->errors[] = "Security rule violated: PHP file found in build output";
    }

    private function validateBuild(): void
    {
        $htmlPath = $this->buildPath . 'index.html';
        if (!file_exists($htmlPath)) return;
        $html = file_get_contents($htmlPath);
        if (preg_match('/\{\{[a-zA-Z0-9_]+\}\}/', $html, $m)) $this->errors[] = "Unresolved variable: {$m[0]}";
        if (preg_match('/https?:\/\/localhost/i', $html, $m)) $this->errors[] = "Localhost URL found";
        if (!empty($this->errors)) throw new Exception("Validation failed: " . implode('; ', $this->errors));
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src); if (!is_dir($dst)) mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;
            if (is_dir("{$src}/{$file}")) $this->recurseCopy("{$src}/{$file}", "{$dst}/{$file}"); else copy("{$src}/{$file}", "{$dst}/{$file}");
        }
        closedir($dir);
    }
}
