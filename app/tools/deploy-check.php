<?php
/**
 * Deploy Check — Pre-deployment Validator
 *
 * Scans a deployment folder for common issues before going live.
 *
 * Usage:
 *   CLI:  php app/tools/deploy-check.php deployments/my-portfolio
 *   HTTP: GET /app/tools/deploy-check.php?slug=my-portfolio  (admin only)
 *
 * Returns JSON: { "slug": "...", "passed": bool, "errors": [], "warnings": [] }
 */

// ─── Auth guard (HTTP mode) ───────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    session_start();
require_once APP_ROOT . '/config/bootstrap.php';
    require_once APP_ROOT . '/config/app.php';
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    $slug = trim($_GET['slug'] ?? '');
} else {
    $slug = trim($argv[1] ?? '');
}

if (empty($slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide ?slug=<deployment-slug>']);
    exit;
}

$root       = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
$deployPath = $root . '/deployments/' . basename($slug) . '/';
$indexFile  = $deployPath . 'index.html';

$errors   = [];
$warnings = [];

// ─── 1. Deployment folder exists ─────────────────────────────────────────────
if (!is_dir($deployPath)) {
    echo json_encode(['slug' => $slug, 'passed' => false,
        'errors' => ["Deployment folder not found: deployments/{$slug}/"], 'warnings' => []]);
    exit;
}

// ─── 2. index.html exists ────────────────────────────────────────────────────
if (!file_exists($indexFile)) {
    $errors[] = 'index.html is missing';
} else {
    $html = file_get_contents($indexFile);

    // 3. Unresolved template variables
    if (preg_match_all('/\{\{[a-zA-Z0-9_]+\}\}/', $html, $vars)) {
        foreach ($vars[0] as $v) {
            $errors[] = "Unresolved template variable: {$v}";
        }
    }

    // 4. Hardcoded localhost URLs
    if (preg_match_all('/https?:\/\/localhost[^\s"\'<>]*/i', $html, $lhMatches)) {
        foreach (array_unique($lhMatches[0]) as $url) {
            $errors[] = "Hardcoded localhost URL: {$url}";
        }
    }

    // 5. Duplicate stylesheet links
    preg_match_all('/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>/i', $html, $sheets);
    if (count($sheets[0]) > 1) {
        $warnings[] = count($sheets[0]) . ' <link rel="stylesheet"> tags found — expected 1';
    }

    // 6. Missing assets referenced in HTML
    $assetPatterns = [
        '/href=["\'](?!https?:\/\/|mailto:|#|\/\/)([^"\'?#]+\.(?:css|js))["\']/' => 'href',
        '/src=["\'](?!https?:\/\/|data:)([^"\'?#]+\.(?:js|png|jpg|jpeg|webp|svg|gif))["\']/' => 'src',
    ];
    foreach ($assetPatterns as $pattern => $attr) {
        preg_match_all($pattern, $html, $refMatches);
        foreach ($refMatches[1] as $relPath) {
            $absPath = $deployPath . ltrim($relPath, '/');
            if (!file_exists($absPath)) {
                $warnings[] = "Missing asset ({$attr}): {$relPath}";
            }
        }
    }

    // 7. CSS file exists and is non-empty
    $cssFile = $deployPath . 'assets/css/style.min.css';
    if (!file_exists($cssFile)) {
        $errors[] = 'assets/css/style.min.css is missing';
    } elseif (filesize($cssFile) < 10) {
        $warnings[] = 'style.min.css appears empty or near-empty';
    }

    // 8. netlify.toml present
    if (!file_exists($deployPath . 'netlify.toml')) {
        $warnings[] = 'netlify.toml not found — add it for Netlify drag-and-drop support';
    }
}

// ─── Output ───────────────────────────────────────────────────────────────────
$result = [
    'slug'     => $slug,
    'passed'   => empty($errors),
    'errors'   => $errors,
    'warnings' => $warnings,
    'checked'  => date('Y-m-d H:i:s'),
];

if (PHP_SAPI === 'cli') {
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    header('Content-Type: application/json');
    echo json_encode($result);
}
