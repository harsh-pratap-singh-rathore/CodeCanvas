<?php
/**
 * Auth Handler - Google Login Init
 */
session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';

// Check if vendor/autoload is loaded (handled in bootstrap, but ensure Google_Client exists)
if (!class_exists('Google_Client')) {
    die('Google API Client not found. Please run composer install.');
}

$client = new Google_Client();
// Constants from .env via bootstrap, or getenv
$clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?? '';
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?? '';

// Dynamically determine Redirect URI
$envRedirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI') ?? '';
$redirectUri = !empty($envRedirectUri) ? $envRedirectUri : (rtrim(BASE_URL, '/') . '/auth/google-callback.php');

if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
    die('Google OAuth credentials are not properly configured in .env file.');
}

$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

$client->addScope('email');
$client->addScope('profile');

// Generate and save a simple state to mitigate CSRF (optional but good practice)
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$client->setState($state);

// Redirect to Google's OAuth 2.0 server
$authUrl = $client->createAuthUrl();

// We MUST output a location header (no HTML output as requested)
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
