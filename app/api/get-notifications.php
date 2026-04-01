<?php
/**
 * API: Get Notifications
 * Fetches the latest notifications for the logged in user as JSON.
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
        SELECT id, type, title, content, link, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If there is an action to mark as read, we could do it here,
    // but typically we do it when rendering or checking explicitly.
    // Let's just return them.
    if (isset($_GET['mark_read']) && $_GET['mark_read'] == '1') {
        $upd = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $upd->execute([$user_id]);
    }
    
    // Format dates for JS
    foreach ($notifications as &$n) {
        $n['formatted_date'] = date('M j, Y — g:i A', strtotime($n['created_at']));
    }

    echo json_encode([
        'status' => 'success',
        'data' => $notifications
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
