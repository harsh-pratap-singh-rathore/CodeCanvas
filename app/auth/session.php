<?php
/**
 * Session Helper
 * Manages user sessions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'initials' => $_SESSION['user_initials']
    ];
}

/**
 * Set user session
 */
function setUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_initials'] = $user['avatar_initials'];
}

/**
 * Clear user session
 */
function clearUserSession() {
    session_unset();
    session_destroy();
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . '/login.html');
exit;
        exit();
    }
}

/**
 * Generate avatar initials from name
 */
function generateInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
