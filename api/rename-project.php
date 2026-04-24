<?php
session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$projectId = $input['id'] ?? '';
$newName = $input['name'] ?? '';

if (empty($projectId) || empty(trim($newName))) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$stmt = $pdo->prepare("UPDATE projects SET project_name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
$stmt->execute([trim($newName), $projectId, $_SESSION['user_id']]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found or not owned by you']);
}
