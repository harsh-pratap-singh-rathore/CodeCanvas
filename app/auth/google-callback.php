<?php
/**
 * Auth Handler - Google OAuth Callback
 */
session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/events/UserRegisteredEvent.php';

if (!class_exists('Google_Client')) {
    die('Google API Client not found.');
}

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?? '');
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?? '');

// Dynamically determine Redirect URI based on environment
if (defined('APP_ENV') && APP_ENV === 'production') {
    $redirectUri = 'https://codecanvas.page/auth/google-callback.php';
} else {
    $envRedirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI') ?? '';
    $redirectUri = !empty($envRedirectUri) ? $envRedirectUri : (rtrim(BASE_URL, '/') . '/auth/google-callback.php');
}
$client->setRedirectUri($redirectUri);

// Simple CSRF check if state is used
if (isset($_GET['state'])) {
    if (empty($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        unset($_SESSION['oauth_state']);
        header("Location: " . BASE_URL . '/public/login.html?error=invalid_state');
exit;
    }
    unset($_SESSION['oauth_state']);
}

// Check for errors from Google
if (isset($_GET['error'])) {
    error_log('[CodeCanvas] Google OAuth Error: ' . $_GET['error']);
    header("Location: " . BASE_URL . '/public/login.html?error=oauth_failed');
exit;
}

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        if (array_key_exists('error', $token)) {
            throw new Exception(join(', ', $token));
        }

        $client->setAccessToken($token);

        // Get profile info
        $google_oauth = new Google\Service\Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $email = $google_account_info->email;
        $name = $google_account_info->name;
        $google_id = $google_account_info->id;

        if (empty($email)) {
            throw new Exception("Google account did not provide an email address.");
        }

        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // CASE A — USER EXISTS
            
            // If they are local but logging in with Google, we should update their google_id and auth_provider
            if (empty($user['google_id'])) {
                $updateStmt = $pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'google' WHERE id = ?");
                $updateStmt->execute([$google_id, $user['id']]);
            }

            if (($user['status'] ?? 'active') !== 'active') {
                header("Location: " . BASE_URL . '/public/login.html?error=account_inactive');
exit;
            }

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['auth_method'] = 'google';

            $redirectUrl = ($user['role'] === 'admin') ? '/admin/index.php' : '/app/dashboard.php';
            header("Location: " . BASE_URL . $redirectUrl);
exit;

        } else {
            // CASE B — NEW USER
            
            $role = 'user';
            $status = 'active';

            // Create new record
            $stmt = $pdo->prepare("INSERT INTO users (email, name, role, status, google_id, auth_provider) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$email, $name, $role, $status, $google_id, 'google'])) {
                $user_id = $pdo->lastInsertId();

                // Set session
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = $role;
                $_SESSION['auth_method'] = 'google';

                // Dispatch welcome event
                UserRegisteredEvent::dispatch($email, $name);

                header("Location: " . BASE_URL . "/app/dashboard.php");
exit;
            } else {
                throw new Exception("Database insertion failed for new Google user.");
            }
        }

    } catch (Exception $e) {
        error_log('[CodeCanvas] Google Callback Exception: ' . $e->getMessage());
        // On local, expose the real error so it's easier to debug
        $errorParam = (defined('APP_ENV') && APP_ENV === 'local')
            ? 'oauth_callback_failed&detail=' . urlencode($e->getMessage())
            : 'oauth_callback_failed';
        header("Location: " . BASE_URL . '/public/login.html?error=' . $errorParam);
exit;
    }
}

// Fallback
header("Location: " . BASE_URL . '/public/login.html');
exit;
