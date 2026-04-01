<?php
/**
 * PROJECT ARCHIVE — Toggles project status between draft/archived
 * POST-only, JSON, ownership-verified
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input     = json_decode(file_get_contents('php://input'), true);
    $projectId = intval($input['project_id'] ?? 0);
    $action    = $input['action'] ?? 'archive'; // 'archive' or 'unarchive'

    if (!$projectId) throw new Exception('Invalid project ID.');

    // Verify ownership & get current status
    $stmt = $pdo->prepare("SELECT id, status FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) throw new Exception('Project not found or access denied.');

    $newStatus = ($action === 'archive') ? 'archived' : 'draft';

    $stmt = $pdo->prepare("UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$newStatus, $projectId, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'status' => $newStatus]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
