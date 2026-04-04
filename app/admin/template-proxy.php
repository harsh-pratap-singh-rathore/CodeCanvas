<?php
/**
 * Visual Mapping Proxy
 * Injects the highlighting listener into a template for the admin editor.
 */
require_once __DIR__ . '/../../config/bootstrap.php';

$path = $_GET['path'] ?? '';
if (empty($path)) exit('No path provided.');

$fullPath = rtrim(APP_ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
if (!file_exists($fullPath)) exit('File not found: ' . $fullPath);

$html = file_get_contents($fullPath);

// Inject Highlight Listener
$script = '
<script>
    window.addEventListener("message", function(e) {
        if (!e.data || !e.data.action) return;
        if (e.data.action === "highlight") {
            document.querySelectorAll("[data-canvas-highlight]").forEach(el => el.removeAttribute("data-canvas-highlight"));
            const el = document.querySelector(e.data.selector);
            if (el) {
                el.setAttribute("data-canvas-highlight", "true");
                el.scrollIntoView({ behavior: "smooth", block: "center" });
            }
        } else if (e.data.action === "unhighlight") {
            document.querySelectorAll("[data-canvas-highlight]").forEach(el => el.removeAttribute("data-canvas-highlight"));
        } else if (e.data.action === "inject-css") {
            const style = document.createElement("style");
            style.textContent = e.data.css;
            document.head.appendChild(style);
        }
    });
</script>
';

if (str_contains($html, '</body>')) {
    // 1. Inject Highlight Script
    $html = str_replace('</body>', $script . '</body>', $html);
    
    // 2. Inject <base> tag after <head> to fix relative CSS/Images
    $baseUrl = BASE_URL . '/' . trim(dirname($path), '/\\') . '/';
    $baseTag = '<base href="' . htmlspecialchars($baseUrl) . '">';
    
    if (str_contains($html, '<head>')) {
        $html = str_replace('<head>', '<head>' . $baseTag, $html);
    } else {
        $html = str_replace('<html>', '<html><head>' . $baseTag . '</head>', $html);
    }
    
    echo $html;
} else {
    echo $html . $script;
}
