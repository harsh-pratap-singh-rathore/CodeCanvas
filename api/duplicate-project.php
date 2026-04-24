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

if (empty($projectId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project ID required']);
    exit;
}

try {
    // 1. Fetch original project
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        throw new Exception('Project not found or access denied.');
    }

    // 2. Insert copy record
    $copyName = $original['project_name'] . ' (Copy)';
    $stmt = $pdo->prepare("
        INSERT INTO projects (user_id, project_name, status, html_path, content_json, created_at, updated_at)
        VALUES (?, ?, ?, '', ?, NOW(), NOW())
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $copyName,
        'draft',
        $original['content_json']
    ]);
    $newId = $pdo->lastInsertId();

    // 3. Clone physical storage
    if (!empty($original['html_path'])) {
        $parts = explode('/', trim($original['html_path'], '/'));
        if (count($parts) >= 3 && $parts[1] === 'output') {
            $srcId = $parts[2];
            $srcDir = APP_ROOT . '/public/output/' . $srcId;
            $destDir = APP_ROOT . '/public/output/' . $newId;

            if (is_dir($srcDir)) {
                mkdir($destDir, 0777, true);
                
                function recurse_copy($src, $dst) {
                    $dir = opendir($src);
                    @mkdir($dst);
                    while(false !== ( $file = readdir($dir)) ) {
                        if (( $file != '.' ) && ( $file != '..' )) {
                            if ( is_dir($src . '/' . $file) ) {
                                recurse_copy($src . '/' . $file, $dst . '/' . $file);
                            } else {
                                copy($src . '/' . $file, $dst . '/' . $file);
                            }
                        }
                    }
                    closedir($dir);
                }
                recurse_copy($srcDir, $destDir);

                $newHtmlPath = '/public/output/' . $newId . '/index.html';
                $pdo->prepare("UPDATE projects SET html_path = ? WHERE id = ?")->execute([$newHtmlPath, $newId]);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Project duplicated successfully.',
        'new_id' => $newId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
