<?php
/**
 * ADMIN API — User Management
 * Actions: toggle_status, delete
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$userId = (int) ($input['user_id'] ?? 0);

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

// Prevent self-deletion
if ($userId === (int) $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Cannot modify your own account']);
    exit;
}

try {
    switch ($action) {
        case 'toggle_status':
            $newStatus = $input['status'] ?? 'inactive';
            if (!in_array($newStatus, ['active', 'inactive'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid status']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            echo json_encode(['success' => true, 'status' => $newStatus]);
            break;

        case 'delete':
            // Delete user (projects cascade due to FK)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->execute([$userId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Cannot delete admin users or user not found']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    error_log('Admin user API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
