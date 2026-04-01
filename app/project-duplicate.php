<?php
/**
 * PROJECT DUPLICATE
 * Creates a copy of an existing project and its data.
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
    $input = json_decode(file_get_contents('php://input'), true);
    $projectId = (int)($input['project_id'] ?? 0);
    $userId = $_SESSION['user_id'];

    if (!$projectId) {
        throw new Exception('Invalid project ID.');
    }

    // 1. Fetch original project
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $userId]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        throw new Exception('Project not found or access denied.');
    }

    // 2. Insert copy
    $copyName = $original['project_name'] . ' (Copy)';
    $stmt = $pdo->prepare("
        INSERT INTO projects (user_id, template_id, project_name, brand_name, description, skills, contact, content_json, project_type, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
    ");
    $stmt->execute([
        $userId,
        $original['template_id'],
        $copyName,
        $original['brand_name'],
        $original['description'],
        $original['skills'],
        $original['contact'],
        $original['content_json'],
        $original['project_type']
    ]);

    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Project duplicated successfully.',
        'new_id'  => $newId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
