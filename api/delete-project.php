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

if (empty($projectId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID required']);
    exit;
}

$stmt = $pdo->prepare("SELECT html_path FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$projectId, $_SESSION['user_id']]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found or access denied']);
    exit;
}

// New logic for DB ID folder structure
if (!empty($project['html_path'])) {
    $parts = explode('/', trim($project['html_path'], '/'));
    // Expected path: public/output/{id}/index.html
    if (count($parts) >= 3 && $parts[1] === 'output') {
        $folderId = $parts[2];
        $dir = APP_ROOT . '/public/output/' . $folderId;
        if (is_dir($dir)) {
            // Recursive delete helper
            function rrmdir($dir) {
                if (is_dir($dir)) {
                    $objects = scandir($dir);
                    foreach ($objects as $object) {
                        if ($object != "." && $object != "..") {
                            if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                                rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                            else
                                unlink($dir. DIRECTORY_SEPARATOR .$object);
                        }
                    }
                    rmdir($dir);
                }
            }
            rrmdir($dir);
        }
    }
}

$stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
$stmt->execute([$projectId]);

echo json_encode(['success' => true]);
