<?php
/**
 * APP - PROJECT SETTINGS
 * Edit project metadata (Name, Description, etc.)
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch project first to check ownership and status
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header("Location: " . BASE_URL . '/app/dashboard.php');
exit;
    }
} catch (PDOException $e) {
    die("Error loading project.");
}

$isArchived = ($project['status'] === 'archived');

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isArchived) {
    $projectName = trim($_POST['project_name']);
    $brandName = trim($_POST['brand_name']);
    $description = trim($_POST['description']);
    $skills = trim($_POST['skills']);
    $contact = trim($_POST['contact']);
    $seoTitle = trim($_POST['seo_title']);
    $faviconUrl = trim($_POST['favicon_url']);

    if (empty($projectName)) {
        $error = "Project Name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET project_name = ?, brand_name = ?, description = ?, skills = ?, contact = ?, seo_title = ?, favicon_url = ? 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$projectName, $brandName, $description, $skills, $contact, $seoTitle, $faviconUrl, $projectId, $userId]);
            $success = "Project updated successfully.";
            
            // Refresh project data
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$projectId, $userId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$user = [
    'name' => $_SESSION['user_name'],
    'initials' => strtoupper(substr($_SESSION['user_name'], 0, 1))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings: <?= htmlspecialchars($project['project_name']) ?> — CodeCanvas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css">
    <style>
        body { background: #FAFAFA; color: #111; font-family: 'DM Sans', sans-serif; }
        
        .settings-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .settings-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .form-group { margin-bottom: 24px; }
        .form-label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; }
        .form-input { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #e5e5e5; 
            border-radius: 6px; 
            font-size: 14px; 
            font-family: inherit;
        }
        .form-input:focus { outline: none; border-color: #000; }
        textarea.form-input { min-height: 100px; resize: vertical; }

        .btn-save {
            background: #000;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-save:hover { background: #333; }
        
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 24px; font-size: 14px; }
        .alert-success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .alert-error { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }

        .disabled-overlay {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>

    <!-- Simple Nav -->
    <nav style="background: #fff; border-bottom: 1px solid #e5e5e5; padding: 16px 32px;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; align-items: center; gap: 12px;">
            <a href="<?= BASE_URL ?>/project-editor.php?id=<?= $projectId ?>" style="text-decoration: none; color: #666; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Editor
            </a>
            <div style="height: 20px; width: 1px; background: #e5e5e5;"></div>
            <span style="font-weight: 600;">Project Settings</span>
        </div>
    </nav>

    <div class="settings-container">
        <div style="margin-bottom: 24px;">
            <h1 style="font-size: 24px; font-weight: 600; margin-bottom: 8px;">General Settings</h1>
            <p style="color: #666;">Update your project details and metadata.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($isArchived): ?>
            <div class="alert alert-error" style="background: #FFF3E0; color: #E65100; border-color: #FFCC80;">
                This project is <strong>Archived</strong>. You must unarchive it to make changes.
            </div>
        <?php endif; ?>

        <div class="settings-card <?= $isArchived ? 'disabled-overlay' : '' ?>">
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Project Name</label>
                    <input type="text" name="project_name" class="form-input" value="<?= htmlspecialchars($project['project_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Brand / Display Name</label>
                    <input type="text" name="brand_name" class="form-input" value="<?= htmlspecialchars($project['brand_name']) ?>">
                    <p style="font-size: 12px; color: #888; margin-top: 4px;">The main title shown on your website (e.g. Your Name).</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Description / Bio</label>
                    <textarea name="description" class="form-input"><?= htmlspecialchars($project['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Skills / Subtitle</label>
                    <input type="text" name="skills" class="form-input" value="<?= htmlspecialchars($project['skills']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Info</label>
                    <input type="text" name="contact" class="form-input" value="<?= htmlspecialchars($project['contact']) ?>">
                </div>

                <div style="margin: 32px 0 16px; border-top: 1px solid #eee; padding-top: 24px;">
                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">SEO & Browser Appearance</h3>
                    <p style="font-size: 13px; color: #666; margin-bottom: 16px;">Customize how your site appears in browser tabs and search results.</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Site Browser Title</label>
                    <input type="text" name="seo_title" class="form-input" value="<?= htmlspecialchars($project['seo_title'] ?? '') ?>" placeholder="e.g. John Doe | Senior Designer">
                    <p style="font-size: 12px; color: #888; margin-top: 4px;">The text that appears in the browser tab. Leave empty to use project name.</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Favicon URL</label>
                    <input type="text" name="favicon_url" class="form-input" value="<?= htmlspecialchars($project['favicon_url'] ?? '') ?>" placeholder="https://example.com/favicon.ico">
                    <p style="font-size: 12px; color: #888; margin-top: 4px;">Link to an .ico or .png file for the browser tab icon.</p>
                </div>

                <div style="border-top: 1px solid #eee; padding-top: 24px; margin-top: 32px; text-align: right;">
                    <a href="<?= BASE_URL ?>/project-view.php?id=<?= $projectId ?>" class="btn-save" style="background: #fff; color: #333; border: 1px solid #ddd; margin-right: 12px; text-decoration: none;">Cancel</a>
                    <button type="submit" class="btn-save" <?= $isArchived ? 'disabled' : '' ?>>Save Changes</button>
                </div>
            </form>
        </div>
    </div>

<script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>
</body>
</html>
