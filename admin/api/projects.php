<?php
/**
 * ADMIN API — Project Management
 * Actions: delete
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
$projectId = (int) ($input['project_id'] ?? 0);

if (!$projectId) {
    echo json_encode(['success' => false, 'error' => 'Invalid project ID']);
    exit;
}

try {
    switch ($action) {
        case 'delete':
            // Also delete project files from storage
            $stmt = $pdo->prepare("SELECT id, user_id FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();

            if (!$project) {
                echo json_encode(['success' => false, 'error' => 'Project not found']);
                exit;
            }

            // Delete from DB
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);

            // Try to delete project files
            $projectDir = APP_ROOT . '/storage/projects/' . $project['user_id'] . '/' . $projectId;
            if (is_dir($projectDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($projectDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }
                rmdir($projectDir);
            }

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    error_log('Admin project API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
