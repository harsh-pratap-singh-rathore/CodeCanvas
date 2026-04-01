<?php
/**
 * API: Get Messages
 * Fetches the latest messages for the logged-in user in real time.
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT m.*, p.project_name
        FROM messages m
        JOIN projects p ON m.project_id = p.id
        WHERE p.user_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates for JS
    foreach ($messages as &$msg) {
        $msg['formatted_date'] = date('M j, Y — g:i A', strtotime($msg['created_at']));
    }

    echo json_encode([
        'status' => 'success',
        'data' => $messages
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
