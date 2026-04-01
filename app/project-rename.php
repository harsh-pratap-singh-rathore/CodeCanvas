<?php
/**
 * PROJECT RENAME — Updates project_name
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
    $newName   = trim($input['name'] ?? '');

    if (!$projectId) throw new Exception('Invalid project ID.');
    if ($newName === '') throw new Exception('Project name cannot be empty.');
    if (strlen($newName) > 100) throw new Exception('Name is too long (max 100 chars).');

    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) throw new Exception('Project not found or access denied.');

    // Update name
    $stmt = $pdo->prepare("UPDATE projects SET project_name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$newName, $projectId, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'name' => $newName]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
