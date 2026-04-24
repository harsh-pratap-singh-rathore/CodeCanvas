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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$projectId = $input['id'] ?? '';
$htmlContent = $input['html'] ?? '';

if (empty($projectId) || empty($htmlContent)) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID and HTML content are required']);
    exit;
}

// Clean project ID traversal
$projectId = basename($projectId); 

$outputDir = APP_ROOT . '/public/output/' . $projectId;
$indexPath = $outputDir . '/index.html';

if (!file_exists($indexPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Project not found on disk']);
    exit;
}

// Ownership Validation (Using exact ID since we now map folder name to DB ID)
$stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$projectId, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied or project not found']);
    exit;
}

if (!preg_match('/<!DOCTYPE html>/i', $htmlContent)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid HTML: Missing DOCTYPE']);
    exit;
}

// Update DB timestamp
$pdo->prepare("UPDATE projects SET updated_at = NOW() WHERE id = ?")->execute([$projectId]);

file_put_contents($indexPath, $htmlContent);

echo json_encode(['success' => true]);
