<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/../../config/bootstrap.php';

session_start();
session_unset();
session_destroy();

// Redirect to login page
header("Location: " . BASE_URL . '/public/login.html');
exit;
