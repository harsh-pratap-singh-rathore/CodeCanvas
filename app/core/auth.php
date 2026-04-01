<?php
/**
 * Core Auth Middleware
 * Ensures user is logged in.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page using the BASE_URL constant
    header("Location: " . BASE_URL . '/public/login.html');
    exit;
}
