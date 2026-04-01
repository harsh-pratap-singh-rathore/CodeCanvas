<?php
/**
 * NEW PROJECT — Entry Point
 * GET  → redirects to select-category.php (category selection step)
 * POST → creates project & returns JSON redirect (used by select-template.php)
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

/* ── POST: Create project & return redirect URL ──────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $name  = trim($input['project_name'] ?? '');
        if (!$name) throw new Exception('Project name is required.');

        // Developer Portfolio is the only template (ID 7)
        $stmt = $pdo->prepare("SELECT id, folder_path FROM templates WHERE slug = 'developer-portfolio' AND status = 'active'");
        $stmt->execute();
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) throw new Exception('Developer Portfolio template not found.');

        $stmt = $pdo->prepare(
            "INSERT INTO projects (user_id, template_id, project_name, project_type, status)
             VALUES (?, ?, ?, 'portfolio', 'draft')"
        );
        $stmt->execute([$_SESSION['user_id'], $tpl['id'], $name]);
        $projectId = $pdo->lastInsertId();

        echo json_encode([
            'success'   => true,
            'projectId' => $projectId,
            'redirect' => BASE_URL . '/project-editor.php?id=' . $projectId
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ── GET: Redirect to category selection ─────────────────────── */
header("Location: " . BASE_URL . '/app/select-category.php');
exit;
