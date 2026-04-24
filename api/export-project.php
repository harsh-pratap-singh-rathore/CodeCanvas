<?php
session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$projectId = $_GET['id'] ?? '';
if (empty($projectId)) {
    die("Project ID required");
}

$stmt = $pdo->prepare("SELECT html_path, project_name FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$projectId, $_SESSION['user_id']]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("Access denied");
}

// Expected path: public/output/{id}/index.html
$parts = explode('/', trim($project['html_path'], '/'));
if (count($parts) >= 3 && $parts[1] === 'output') {
    $folderId = $parts[2];
    $dir = APP_ROOT . '/public/output/' . $folderId;
    
    if (!is_dir($dir)) {
        die("Project files not found");
    }
    
    $zip = new ZipArchive();
    $zipName = sys_get_temp_dir() . '/' . $folderId . '.zip';
    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Cannot create zip archive");
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
    
    $downloadName = preg_replace('/[^A-Za-z0-9_-]/', '_', $project['project_name']) . '_export.zip';
    
    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($zipName));
    readfile($zipName);
    unlink($zipName);
    exit;
} else {
    die("Invalid project path mapped: " . $project['html_path']);
}
