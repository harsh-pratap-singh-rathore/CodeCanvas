<?php
/**
 * Database Connection — CodeCanvas SaaS
 * PDO with environment-aware credentials.
 *
 * Switch between LOCAL and PRODUCTION by toggling the sections below.
 * In production: only the PRODUCTION block should be active.
 */

// ─── PRODUCTION ─────────────────────────────────────────────────────────────
// These are loaded from .env when deploying to a remote server.
// define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
// define('DB_NAME', $_ENV['DB_NAME'] ?? 'u193155059_codecanvas');
// define('DB_USER', $_ENV['DB_USER'] ?? 'u193155059_codecanvas');
// define('DB_PASS', $_ENV['DB_PASS'] ?? 'Codecanvas555');

// ─── LOCAL — XAMPP ─────────────────────────────────────────────────────────
// For local dev, pull from .env if available, else fall back to XAMPP defaults.
define('DB_HOST', $_ENV['DB_HOST']   ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME']   ?? 'codecanvas');
define('DB_USER', $_ENV['DB_USER']   ?? 'root');
define('DB_PASS', $_ENV['DB_PASS']   ?? ''); 



// ─── PDO options ───────────────────────────────────────────────────────────
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $options
    );
} catch (PDOException $e) {
    error_log('[CodeCanvas] Database connection failed: ' . $e->getMessage());

    if (!defined('IGNORE_DB_ERROR')) {
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'A database error occurred. Please try again later.',
        ]);
        exit;
    }
    $pdo = null;
}
