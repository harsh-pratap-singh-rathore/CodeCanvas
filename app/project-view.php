<?php
/**
 * APP - PROJECT VIEW v2
 * Shows project details + Archive actions
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Handle Actions (Archive/Unarchive)
if ($action === 'archive' || $action === 'unarchive') {
    $newStatus = ($action === 'archive') ? 'archived' : 'draft'; // Unarchive -> Draft
    try {
        $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$newStatus, $projectId, $userId]);
        header("Location: " . BASE_URL . "/app/project-view.php?id=$projectId");
exit;
    } catch (PDOException $e) {
        // Handle error
    }
}

// Fetch project
try {
    $stmt = $pdo->prepare("
        SELECT p.*, t.name as template_name, t.folder_path
        FROM projects p
        LEFT JOIN templates t ON p.template_id = t.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header("Location: " . BASE_URL . '/app/dashboard.php');
exit;
    }
} catch (PDOException $e) {
    die("Error loading project: " . $e->getMessage());
}

$user = [
    'name' => $_SESSION['user_name'],
    'initials' => strtoupper(substr($_SESSION['user_name'], 0, 1))
];

$isArchived = ($project['status'] === 'archived');

// Resolve entry point dynamically
$folderPath = rtrim($project['folder_path'] ?? 'templates/developer', '/');
$absPath = __DIR__ . '/../' . $folderPath;
$entryPoint = 'index.html';
$candidates = ['code.html', 'index.html', 'index.htm'];
foreach ($candidates as $c) {
    if (file_exists($absPath . '/' . $c)) {
        $entryPoint = $c;
        break;
    }
}
if ($entryPoint === 'index.html' && !file_exists($absPath . '/index.html')) {
    $nested = glob($absPath . '/*/{code.html,index.html,index.htm}', GLOB_BRACE);
    if (!empty($nested)) $entryPoint = str_replace($absPath . '/', '', $nested[0]);
}
$previewUrl = '../' . $folderPath . '/' . $entryPoint;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['project_name']) ?> — CodeCanvas</title>
    <!-- Public Assets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css">
    <style>
        /* Shared Styles (re-using from dashboard where possible) */
        body { background: #FAFAFA; color: #111; font-family: 'DM Sans', sans-serif; }

        .top-nav {
            background: #fff;
            padding: 16px 32px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .left-nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .back-link {
            text-decoration: none;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.1s;
        }
        .back-link:hover { color: #000; }

        .project-title {
            font-size: 16px;
            font-weight: 600;
            color: #000;
        }

        .status-badge {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }
        .status-badge.draft { background: #f0f0f0; color: #666; }
        .status-badge.published { background: #000; color: #fff; }
        .status-badge.archived { background: #FFF3E0; color: #E65100; border: 1px solid #FFCC80; }

        /* Actions Bar */
        .actions-right {
            display: flex;
            gap: 12px;
        }

        /* Main Content Grid */
        .main-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 32px;
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 32px;
        }

        /* Content Preview Area */
        .preview-area {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            min-height: 600px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }

        .preview-placeholder-text {
            color: #999;
            text-align: center;
            margin-top: 100px;
        }

        /* Sidebar Info */
        .sidebar-box {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 24px;
        }

        .sidebar-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 16px;
            letter-spacing: 0.05em;
        }

        .info-row {
            margin-bottom: 12px;
        }
        .info-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 2px;
        }
        .info-data {
            font-size: 15px;
            font-weight: 500;
            color: #111;
        }

        .btn-full {
            display: block;
            width: 100%;
            text-align: center;
            margin-bottom: 8px;
        }

        .btn-archive {
            color: #D32F2F;
            border-color: #FFCDD2;
            background: #FFEBEE;
        }
        .btn-archive:hover {
            background: #FFCDD2;
        }
        
        .btn-unarchive {
            color: #155724;
            border-color: #c3e6cb;
            background: #d4edda;
        }
    </style>
</head>
<body>

    <!-- Nav -->
    <nav class="top-nav">
        <div class="left-nav">
            <a href="<?= BASE_URL ?>/dashboard.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Dashboard
            </a>
            <div style="height: 20px; width: 1px; background: #e5e5e5;"></div>
            <span class="project-title"><?= htmlspecialchars($project['project_name']) ?></span>
            <span class="status-badge <?= $project['status'] ?>"><?= ucfirst($project['status']) ?></span>
        </div>
        
        <div class="actions-right">
            <?php if (!$isArchived): ?>
                <a href="portfolio-preview.php?template=<?= urlencode($previewUrl) ?>" target="_blank" class="btn btn-outline">Preview</a>
                <a href="<?= BASE_URL ?>/project-editor.php?id=<?= $projectId ?>" class="btn btn-primary">Edit in Builder</a>
            <?php else: ?>
                <span style="font-size: 13px; color: #666; display: flex; align-items: center;">Read-only mode (Archived)</span>
            <?php endif; ?>
        </div>
    </nav>

    <div class="main-container">
        
        <!-- Main Preview -->
        <div class="preview-area" style="padding: 0; overflow: hidden;">
            <iframe
                src="portfolio-preview.php?template=<?= urlencode($previewUrl) ?>"
                style="width:100%; height:600px; border:none; display:block;"
                title="Portfolio Preview"
            ></iframe>
        </div>

        <!-- Sidebar -->
        <aside>
            <div class="sidebar-box">
                <div class="sidebar-title">Project Details</div>
                
                <div class="info-row">
                    <div class="info-label">Type</div>
                    <div class="info-data"><?= ucfirst($project['project_type']) ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Template</div>
                    <div class="info-data"><?= htmlspecialchars($project['template_name']) ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Created</div>
                    <div class="info-data"><?= date('M j, Y', strtotime($project['created_at'])) ?></div>
                </div>
            </div>

            <div class="sidebar-box" style="margin-top: 24px;">
                <div class="sidebar-title">Actions</div>
                
                <?php if (!$isArchived): ?>
                    <a href="<?= BASE_URL ?>/project-settings.php?id=<?= $projectId ?>" class="btn btn-outline btn-full">Project Settings</a>
                    <a href="?id=<?= $projectId ?>&action=archive" class="btn btn-outline btn-full btn-archive" onclick="return confirm('Are you sure you want to archive this project? It will be moved to the Archived tab.')">Archive Project</a>
                <?php else: ?>
                    <a href="?id=<?= $projectId ?>&action=unarchive" class="btn btn-outline btn-full btn-unarchive">Unarchive (Restore)</a>
                    <a href="#" class="btn btn-outline btn-full" style="color: #bbb; border-color: #eee; cursor: not-allowed;" onclick="return false;">Delete Permanently</a>
                <?php endif; ?>
            </div>
        </aside>

    </div>

<script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>
</body>
</html>
