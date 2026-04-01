<?php
/**
 * CodeCanvas — Application Bootstrap
 *
 * Loads Composer autoloader and environment configuration.
 * Must be require_once'd before any file that uses email or SDK features.
 *
 * Usage:
 *   require_once __DIR__ . '/bootstrap.php';
 */

// ─── 0. Application Config (BASE_URL, APP_ENV, APP_ROOT) ─────────────────────
require_once __DIR__ . '/app.php';

// ─── 1. Vendor Autoloader ─────────────────────────────────────────────────────

$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    $isCli = PHP_SAPI === 'cli';
    $msg = '[CodeCanvas Bootstrap] vendor/autoload.php not found. '
         . 'Run: composer install  (in the project root)';

    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

require_once $autoloadPath;

// ─── 2. Load .env ────────────────────────────────────────────────────────────
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and blank lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        // Only process key=value pairs
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            // Remove surrounding quotes if present
            $value = trim($value, '"\'');
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// ─── 3. Define Application Constants ────────────────────────────────────────
if (!defined('RESEND_API_KEY')) {
    $key = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?? '';
    if (empty($key)) {
        error_log('[CodeCanvas] RESEND_API_KEY is not set in .env');
    }
    define('RESEND_API_KEY', $key);
}

if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', $_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?? 'CodeCanvas <noreply@codecanvas.page>');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production');
}

// ─── 4. Configure Error Reporting by Environment ────────────────────────────
if (APP_ENV === 'local') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
