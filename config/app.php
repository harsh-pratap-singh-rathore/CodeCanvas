<?php
/**
 * CodeCanvas — Application Configuration
 *
 * Single source of truth for environment detection and URL constants.
 * Included early by bootstrap.php — available everywhere.
 *
 * Constants defined:
 *   BASE_URL   — full URL to the public web root (no trailing slash)
 *   APP_ROOT   — absolute filesystem path to the project root
 *   APP_ENV    — 'local' | 'production'
 */

// ─── Filesystem Root ─────────────────────────────────────────────────────────
// Always two levels up from this file (config/ → project root)
defined('APP_ROOT') || define('APP_ROOT', dirname(__DIR__));

// ─── Environment Detection ────────────────────────────────────────────────────
$host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
// Remove port if present
if (str_contains($host, ':')) {
    $host = explode(':', $host)[0];
}

// Add any additional production hostnames here
$productionHosts = [
    'codecanvas.page',
    'www.codecanvas.page',
];

if (in_array($host, $productionHosts, true)) {
    // ── Production ──────────────────────────────────────────────────────────
    defined('APP_ENV')  || define('APP_ENV',  'production');
    defined('BASE_URL') || define('BASE_URL', 'https://' . $host);
} else {
    // ── Local / Staging ─────────────────────────────────────────────────────
    // Detect protocol (HTTP/HTTPS)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    // 1. Get absolute filesystem paths
    // APP_ROOT is always defined as dirname(__DIR__) relative to this file
    $rootPath = str_replace('\\', '/', realpath(APP_ROOT) ?: APP_ROOT);
    $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $scriptPath = str_replace('\\', '/', realpath($scriptFilename) ?: $scriptFilename);

    // 2. Default to empty if we can't detect
    $baseDir = '';

    // 3. If the script is within the APP_ROOT, calculate how many levels deep it is
    if (!empty($scriptPath) && str_starts_with($scriptPath, $rootPath)) {
        $relative = substr($scriptPath, strlen($rootPath));
        $levels = substr_count(trim($relative, '/'), '/');
        
        // 4. Go up from SCRIPT_NAME the same number of levels + 1 (for the filename)
        $tempBase = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!empty($tempBase)) {
            for ($i = 0; $i <= $levels; $i++) {
                $tempBase = dirname($tempBase);
            }
            $baseDir = rtrim(str_replace('\\', '/', $tempBase), '/');
        }
    } else {
        // Fallback for CLI or cases where paths don't match
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/');
        // If we contain /app or /public, try to strip them as a last resort
        if (str_contains($baseDir, '/app')) {
            $baseDir = substr($baseDir, 0, strrpos($baseDir, '/app'));
        } elseif (str_contains($baseDir, '/public')) {
            $baseDir = substr($baseDir, 0, strrpos($baseDir, '/public'));
        }
    }

    defined('APP_ENV')  || define('APP_ENV',  'local');
    defined('BASE_URL') || define('BASE_URL', "{$scheme}://{$host}{$baseDir}");
}


// ─── API Base (used in injected JS for portfolio contact forms) ───────────────
defined('API_BASE_URL') || define('API_BASE_URL', BASE_URL . '/app/api');
