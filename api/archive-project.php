<?php
session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$projectId = $input['project_id'] ?? '';
$action = $input['action'] ?? 'archive';

if (empty($projectId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project ID required']);
    exit;
}

$status = ($action === 'unarchive') ? 'active' : 'archived';

$stmt = $pdo->prepare("UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
$stmt->execute([$status, $projectId, $_SESSION['user_id']]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Project not found or access denied']);
}
