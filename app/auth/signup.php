<?php
/**
 * Auth Handler - Signup
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/events/UserRegisteredEvent.php';

// Consistent JSON response
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'redirect' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // Use part of email as name if name is empty
    $name = trim($_POST['name'] ?? '');
    if (empty($name) && !empty($email)) {
        $parts = explode('@', $email);
        $name = ucfirst($parts[0]); 
    }

    if (empty($email) || empty($password)) {
        $response['message'] = 'Please fill in all required fields.';
        echo json_encode($response);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit;
    }

    // Role default
    $role = 'user';
    $status = 'active';

    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $response['message'] = 'That email is already registered.';
            echo json_encode($response);
            exit;
        }

        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, name, role, status) VALUES (?, ?, ?, ?, ?)");
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        if ($stmt->execute([$email, $password_hash, $name, $role, $status])) {
            $user_id = $pdo->lastInsertId();

            // Auto-login session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = $role;

            $response['success'] = true;
            $response['message'] = 'Account created successfully!';
            $response['redirect'] = BASE_URL . '/app/dashboard.php';

            // Dispatch welcome email event (non-blocking — failure won't affect signup)
            UserRegisteredEvent::dispatch($email, $name);

        } else {
            $response['message'] = 'Failed to create account. Please try again.';
        }

    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        // In production: error_log($e->getMessage()); 
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
exit;
?>
