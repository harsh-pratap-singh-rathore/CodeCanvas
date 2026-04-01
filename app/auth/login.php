<?php
/**
 * Auth Handler - Login
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'redirect' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $response['message'] = 'Please fill all fields.';
        echo json_encode($response);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check status if active
            if (($user['status'] ?? 'active') !== 'active') {
                $response['message'] = 'Account inactive.';
                echo json_encode($response);
                exit;
            }

            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                $response['success'] = true;
                if ($user['role'] === 'admin') {
                    $response['redirect'] = BASE_URL . '/admin/dashboard.php';
                } else {
                    $response['redirect'] = BASE_URL . '/dashboard.php';
                }
            } else {
                $response['message'] = 'Invalid credentials.';
            }
        } else {
            $response['message'] = 'Invalid credentials.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error.';
    }
}

echo json_encode($response);
?>
