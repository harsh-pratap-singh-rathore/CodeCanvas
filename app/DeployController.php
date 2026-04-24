<?php
/**
 * DeployController.php — Vercel Deployment Engine
 *
 * Flow:
 *  1. Build the static portfolio (HTML + CSS + JS) via StaticBuilder
 *  2. Pass static files to VercelDeployService
 *  3. Return the live URL & update DB
 */

session_start();
// Prevent timeout and memory limits during large base64 Vercel uploads
set_time_limit(300);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/app/core/auth.php';
require_once APP_ROOT . '/app/StaticBuilder.php';
require_once APP_ROOT . '/app/services/VercelDeployService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$projectId = intval($_POST['id'] ?? 0);
$slug      = trim($_POST['slug'] ?? '');

if (!$projectId || !$slug) {
    echo json_encode(['success' => false, 'error' => 'Missing project ID or slug']);
    exit;
}

// Sanitize slug: lowercase, alphanumeric + hyphens only
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
$slug = trim($slug, '-');

if (strlen($slug) < 3) {
    echo json_encode(['success' => false, 'error' => 'Slug too short (min 3 characters)']);
    exit;
}

try {
    // ─── 1. Verify project ownership ──────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT p.*, t.folder_path 
        FROM projects p 
        LEFT JOIN templates t ON p.template_id = t.id 
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch();

    if (!$project) {
        throw new Exception('Project not found or access denied');
    }

    // Update status to Publishing immediately
    $pdo->prepare("UPDATE projects SET publish_status = 'publishing' WHERE id = ?")->execute([$projectId]);

    // ─── 2. Enforce Strict Vercel API Validation & Failsafe ────────────────────
    $vercelService = new VercelDeployService();
    
    // Create the project (ignores if it exists locally on this team)
    $vercelService->createProject($slug);

    // TIER 2: HARD DOMAIN VERIFICATION
    $hasDomain = $vercelService->verifyProjectHasDomain($slug, "{$slug}.vercel.app");
    
    if (!$hasDomain) {
        try { $vercelService->deleteProject($slug); } catch (Exception $e2) {}
        throw new Exception("Slug '{$slug}' is reserved by another Vercel user.\n\n(Note: Even if you see a 404 page at {$slug}.vercel.app, it means another user created the project but hasn't uploaded a site yet. Vercel still prevents anyone else from using it.)\n\nPlease choose a different URL.");
    }

    // ─── 3. Save finalized slug to DB ──────────────────────────────────────────
    $pdo->prepare("UPDATE projects SET custom_slug = ? WHERE id = ?")->execute([$slug, $projectId]);

    // ─── 5. Build the static site ─────────────────────────────────────────────
    $builder   = new StaticBuilder($pdo, $projectId);
    $buildDir  = $builder->build($slug);

    // ─── 6. Deploy to Vercel ──────────────────────────────────────────────────
    $liveUrl = $vercelService->deploy($buildDir, $slug);

    // ─── 7. Update DB with live URL & status (using correct columns) ──────────
    $pdo->prepare(
        "UPDATE projects SET status = 'published', publish_status = 'published', live_url = ?, build_log = NULL WHERE id = ?"
    )->execute([$liveUrl, $projectId]);

    echo json_encode([
        'success' => true,
        'url'     => $liveUrl,
        'message' => 'Portfolio published successfully on Vercel!',
    ]);

} catch (Exception $e) {
    // Mark build as failed (using correct column: publish_status)
    try {
        $pdo->prepare("UPDATE projects SET publish_status = 'failed', build_log = ? WHERE id = ?")
            ->execute([$e->getMessage(), $projectId]);
    } catch (Exception $_) {}

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
