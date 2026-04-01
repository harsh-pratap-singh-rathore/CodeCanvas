<?php
/**
 * project-unpublish.php
 *
 * Removes the live status of a Vercel-deployed project.
 * Note: Vercel projects can be deleted via the API, 
 * but for this implementation we simply mark the DB status as 'draft'
 * and remove the `deploy_url`.
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

$projectId = $_POST['project_id'] ?? null;

if (!$projectId) {
    echo json_encode(['success' => false, 'message' => 'No project ID provided.']);
    exit;
}

try {
    // 1. Verify ownership
    $stmt = $pdo->prepare("SELECT id, custom_slug FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Project not found or access denied.']);
        exit;
    }

    // 2. Delete the project from Vercel so it goes offline completely
    if (!empty($project['custom_slug'])) {
        require_once APP_ROOT . '/app/services/VercelDeployService.php';
        try {
            $vercelService = new VercelDeployService();
            $vercelService->deleteProject($project['custom_slug']);
        } catch (Exception $e) {
            // Log it but continue updating DB status
            error_log("Failed to delete Vercel project: " . $e->getMessage());
        }
    }

    // 2. Mark as draft
    $update = $pdo->prepare("UPDATE projects SET status = 'draft', publish_status = 'draft', live_url = NULL WHERE id = ?");
    $update->execute([$projectId]);

    echo json_encode(['success' => true, 'message' => 'Project unpublished successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
