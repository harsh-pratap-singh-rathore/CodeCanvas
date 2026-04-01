<?php
/**
 * PROJECT DELETE — Permanently deletes a project
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

    if (!$projectId) throw new Exception('Invalid project ID.');
    // Fetch project slug and verify ownership before delete
    $stmt = $pdo->prepare("SELECT id, custom_slug FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    if (!$project) {
        throw new Exception('Project not found or access denied.');
    }

    // Completely delete the project from Vercel if it exists
    if (!empty($project['custom_slug'])) {
        require_once APP_ROOT . '/app/services/VercelDeployService.php';
        try {
            $vercelService = new VercelDeployService();
            $vercelService->deleteProject($project['custom_slug']);
        } catch (Exception $e) {
            error_log("Failed to delete Vercel project during project-delete: " . $e->getMessage());
        }
    }
    // Delete the project from DB
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
