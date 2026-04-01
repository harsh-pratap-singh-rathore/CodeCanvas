<?php
/**
 * Admin Auth Middleware
 * Ensures user is logged in and has 'admin' role.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
}

// Check logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . '/public/login.html');
exit;
}

// Check role
if ($_SESSION['user_role'] !== 'admin') {
    // Redirect non-admins to user app
    header("Location: " . BASE_URL . '/dashboard.php');
exit;
}
?>
