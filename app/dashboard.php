<?php
/**
 * USER DASHBOARD
 * Shows user's projects with filtering, inline rename, archive & delete
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';
require_once APP_ROOT . '/app/core/TemplatePreview.php';

$user = [
    'id'       => $_SESSION['user_id'],
    'name'     => $_SESSION['user_name'],
    'email'    => $_SESSION['user_email'],
    'role'     => $_SESSION['user_role'],
    'initials' => strtoupper(substr($_SESSION['user_name'], 0, 1))
];

$view        = $_GET['view'] ?? 'all';
$pageTitle   = 'All Projects';
$filterQuery = "";

switch ($view) {
    case 'archived':
        $pageTitle   = 'Archived Projects';
        $filterQuery = "AND p.status = 'archived'";
        break;
    case 'yours':
        $pageTitle   = 'Your Projects';
        $filterQuery = "AND p.status != 'archived'";
        break;
    case 'all':
    default:
        $pageTitle   = 'All Projects';
        $filterQuery = "AND p.status != 'archived'";
        break;
}

// ── GET UNREAD MESSAGES COUNT ──────────────────────────
$unreadCount = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM messages m
        JOIN projects p ON m.project_id = p.id
        WHERE p.user_id = ? AND m.is_read = 0
    ");
    $stmt->execute([$user['id']]);
    $unreadCount = $stmt->fetchColumn();
} catch (PDOException $e) {}


/**
 * Derives a human-readable category label from a template folder_path.
 * e.g. "templates/business/" → "Business Portfolio"
 *      "templates/developer/" → "Developer Portfolio"
 */
function getCategoryLabel(string $folderPath): string {
    $parts    = explode('/', trim($folderPath, '/'));
    // folder_path is like "templates/business" — second segment is category
    $category = count($parts) >= 2 ? $parts[1] : ($parts[0] ?? 'portfolio');
    return ucfirst($category) . ' Portfolio';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/public/assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> — CodeCanvas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css">
    <style>
        /* Active Link */
        .sidebar-link.active {
            font-weight: 600;
            color: #000;
            background: #F5F5F5;
        }
        .sidebar-badge {
            background: #000;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
        }


        /* ── Project Card ───────────────────────────────────── */
        .project-card {
            position: relative;
            background: #fff;
            border: 1px solid #E5E5E5;
            border-radius: 8px;
            overflow: visible; /* allow context menu to overflow */
            transition: border-color .15s, box-shadow .15s;
        }
        .project-card:hover {
            border-color: #000;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
        }
        .project-card-inner {
            overflow: hidden;
            border-radius: 8px;
        }

        .project-card-visual {
            width: 100%;
            aspect-ratio: 16/10;
            background: #f9f9f9;
            border-bottom: 1px solid #F0F0F0;
            overflow: hidden;
            position: relative;
        }
        .project-card-visual iframe {
            width: 400%;
            height: 400%;
            transform: scale(0.25);
            transform-origin: top left;
            border: none;
            pointer-events: none;
            background: #fff;
        }
        .project-card-visual-overlay {
            position: absolute;
            inset: 0;
            background: transparent;
            z-index: 2;
        }

        /* ── 3-dot Menu ─────────────────────────────────────── */
        .card-menu-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            border-radius: 5px;
            border: 1px solid #E5E5E5;
            background: #fff;
            color: #555;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            transition: border-color .15s, background .15s;
            z-index: 20;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .project-card:hover .card-menu-btn { display: flex; }
        .card-menu-btn:hover { border-color: #000; background: #FAFAFA; }

        .card-dropdown {
            display: none;
            position: absolute;
            top: 40px;
            right: 8px;
            background: #fff;
            border: 1px solid #E5E5E5;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            min-width: 168px;
            z-index: 100;
            overflow: hidden;
        }
        .card-dropdown.open { display: block; }

        .card-dd-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            font-size: 13px;
            color: #222;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            transition: background .1s;
        }
        .card-dd-item:hover { background: #F5F5F5; }
        .card-dd-item.danger { color: #c00; }
        .card-dd-item.danger:hover { background: #fff5f5; }
        .card-dd-sep { border: none; border-top: 1px solid #F0F0F0; margin: 3px 0; }

        /* ── Inline Rename ──────────────────────────────────── */
        .project-card-title-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .project-card-title {
            font-size: 15px;
            font-weight: 700;
            color: #0F0F0F;
            flex: 1;
            min-width: 0;
        }

        /* ── Modals (shared) ────────────────────────────────── */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal-backdrop.open { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            padding: 28px;
            box-shadow: 0 24px 64px rgba(0,0,0,.18);
        }
        .modal-box h3 {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .modal-box p {
            font-size: 13px;
            color: #666;
            margin: 0 0 18px;
        }
        .modal-input {
            width: 100%;
            padding: 9px 11px;
            border: 1px solid #E5E5E5;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            margin-bottom: 18px;
            box-sizing: border-box;
        }
        .modal-input:focus { outline: none; border-color: #000; }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .modal-btn {
            padding: 9px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #E5E5E5;
            background: #fff;
            color: #333;
            transition: background .15s;
        }
        .modal-btn:hover { background: #F5F5F5; }
        .modal-btn-primary {
            background: #0F0F0F;
            color: #fff;
            border-color: #0F0F0F;
        }
        .modal-btn-primary:hover { background: #333; }
        .modal-btn-primary:disabled { opacity: .5; cursor: not-allowed; }
        .modal-btn-danger {
            background: #0F0F0F;
            color: #fff;
            border-color: #0F0F0F;
        }
        .modal-btn-danger:hover { background: #c00; border-color: #c00; }
        .modal-btn-danger:disabled { opacity: .5; cursor: not-allowed; }

        /* Category badge */
        .project-type { text-transform: uppercase; font-size: 10px; font-weight: 700; letter-spacing: .06em; color: #6B6B6B; }

        /* Published download bar */
        .card-published-bar {
            padding: 10px 16px;
            background: #FAFDF7;
            border-top: 1px solid #D4EDDA;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .card-published-label {
            font-size: 11px;
            font-weight: 600;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .card-download-btn {
            font-size: 11px;
            font-weight: 700;
            padding: 5px 12px;
            background: #0F0F0F;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
        }
        .card-download-btn:hover { background: #333; }

        /* ── Visit Site hover overlay on live cards ─────────── */
        .card-visit-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
            transition: background .2s;
        }
        .project-card:hover .card-visit-overlay {
            background: rgba(0,0,0,0.38);
        }
        .card-visit-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 20px;
            background: #fff;
            color: #0F0F0F;
            font-size: 13px;
            font-weight: 700;
            border-radius: 6px;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
            opacity: 0;
            transform: translateY(6px);
            transition: opacity .2s, transform .2s;
            white-space: nowrap;
        }
        .project-card:hover .card-visit-btn {
            opacity: 1;
            transform: translateY(0);
        }
        .card-visit-btn:hover {
            background: #0F0F0F;
            color: #fff;
        }
        /* Visit button in bottom bar */
        .card-visit-bar-btn {
            font-size: 11px;
            font-weight: 700;
            padding: 5px 12px;
            background: #1a7f37;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .card-visit-bar-btn:hover { background: #145d28; }

        /* ── New Project Card Styles ────────────────────────── */
        .project-card-new {
            border: 2px dashed #A0A0A0;
            background: #FAFAFA;
            opacity: 0.8;
            transition: all 0.2s;
        }
        .project-card-new:hover {
            opacity: 1;
            background: #fff;
            border-style: solid;
            border-color: #000;
        }
        .project-card-new .project-card-link {
            display: block;
            text-decoration: none;
        }
        .project-card-new .project-card-visual {
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border-bottom: none;
        }
        .project-card-new .project-card-icon {
            font-size: 54px;
            color: #bbb;
            font-weight: 300;
        }
        .project-card-new .project-card-title {
            font-size: 16px;
            color: #888;
            font-weight: 600;
            margin: 0;
            text-align: center;
        }
        .spinner-small {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(0,0,0,0.1);
            border-top: 2px solid #000;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
    </style>
</head>
<body class="dashboard-layout">

    <!-- Top Bar -->
    <header class="dashboard-header">
        <div class="dashboard-header-content">
            <a href="<?= BASE_URL ?>/dashboard.php" class="logo">
                CodeCanvas
            </a>
            <div class="user-menu">
                <div class="user-avatar" data-dropdown="user">
                    <span class="avatar-circle"><?php echo htmlspecialchars($user['initials']); ?></span>
                    <div class="dropdown dropdown-right">
                        <div class="dropdown-item" style="border-bottom:1px solid #E5E5E5;padding-bottom:12px;margin-bottom:8px;">
                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                            <span style="font-size:12px;color:#6B6B6B;"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <a href="<?= BASE_URL ?>/profile.php" class="dropdown-item"><strong>Profile</strong></a>
                        <div class="dropdown-divider"></div>
                        <a href="<?= BASE_URL ?>/auth/logout.php" class="dropdown-item" onclick="openLogout(event)"><strong>Logout</strong></a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Left Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="sidebar-section">
                <a href="<?= BASE_URL ?>/new-project.php" class="btn btn-primary" style="width:100%;margin-bottom:24px;">+ New Project</a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Projects</div>
                <nav class="sidebar-nav">
                    <a href="<?= BASE_URL ?>/dashboard.php?view=all"      class="sidebar-link <?php echo ($view === 'all')      ? 'active' : ''; ?>">All Projects</a>
                    <a href="<?= BASE_URL ?>/dashboard.php?view=yours"    class="sidebar-link <?php echo ($view === 'yours')    ? 'active' : ''; ?>">Your Projects</a>
                    <a href="<?= BASE_URL ?>/dashboard.php?view=archived" class="sidebar-link <?php echo ($view === 'archived') ? 'active' : ''; ?>">Archived</a>
                </nav>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Communication</div>
                <nav class="sidebar-nav">
                    <a href="<?= BASE_URL ?>/messages.php" class="sidebar-link">
                        Messages
                        <?php if ($unreadCount > 0): ?>
                            <span class="sidebar-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= BASE_URL ?>/notifications.php" class="sidebar-link">Notifications</a>
                </nav>
            </div>

        </aside>

        <!-- Main Content -->
        <main class="dashboard-main">
            <div class="dashboard-page-header">
                <h1><?php echo $pageTitle; ?></h1>
                <div class="search-input-wrapper">
                    <input type="text" class="search-input" placeholder="Search in your projects…" id="search-input">
                </div>
            </div>

            <div class="projects-grid" id="projects-grid">

                <?php if ($view !== 'archived'): ?>
                <!-- New Project Card -->
                <div class="project-card project-card-new">
                    <div class="project-card-inner">
                        <a href="<?= BASE_URL ?>/new-project.php" class="project-card-link">
                            <div class="project-card-visual">
                                <div class="project-card-icon">+</div>
                            </div>
                            <div class="project-card-header" style="visibility:hidden; padding: 12px 16px;">
                                <span class="project-type">&nbsp;</span>
                            </div>
                            <div class="project-card-body" style="padding:16px; border-top:1px solid #F0F0F0;">
                                <h3 class="project-card-title">New Project</h3>
                                <p class="project-card-meta">&nbsp;</p>
                            </div>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                try {
                    $sql = "SELECT p.*, t.folder_path, t.slug, t.preview_image_path, t.preview_fallback_path, t.name as template_name
                            FROM projects p
                            LEFT JOIN templates t ON p.template_id = t.id
                            WHERE p.user_id = ? $filterQuery
                            ORDER BY p.updated_at DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user['id']]);
                    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($projects) > 0):
                        foreach ($projects as $project):
                            $statusClass = 'publish-status-draft';
                            $statusLabel = ucfirst($project['publish_status'] ?? 'draft');
                            
                            if ($project['publish_status'] === 'deployed' || $project['publish_status'] === 'published') {
                                $statusClass = 'publish-status-deployed';
                                $statusLabel = 'Live';
                            } elseif ($project['publish_status'] === 'publishing' || $project['publish_status'] === 'deploying') {
                                $statusClass = 'publish-status-building';
                                $statusLabel = 'Building';
                            } elseif ($project['publish_status'] === 'failed') {
                                $statusClass = 'publish-status-failed';
                                $statusLabel = 'Failed';
                            }

                            $updated = new DateTime($project['updated_at']);
                            $timeAgo = $updated->format('M j, Y');

                            // ── Category-aware label ──────────────────────────
                            $folderPath    = $project['folder_path'] ?? 'templates/developer/';
                            $categoryLabel = getCategoryLabel($folderPath);

                            $isArchived = ($project['status'] === 'archived');
                ?>
                <div class="project-card"
                     data-project-id="<?php echo $project['id']; ?>"
                     data-project-name="<?php echo htmlspecialchars($project['project_name'], ENT_QUOTES); ?>"
                     data-archived="<?php echo $isArchived ? '1' : '0'; ?>">

                    <!-- 3-dot context menu button -->
                    <button class="card-menu-btn" title="Options"
                            onclick="toggleMenu(event, <?php echo $project['id']; ?>)">⋯</button>

                    <!-- Dropdown -->
                    <div class="card-dropdown" id="menu-<?php echo $project['id']; ?>">
                        <?php if (!$isArchived): ?>
                        <button class="card-dd-item" onclick="openRename(<?php echo $project['id']; ?>, <?php echo htmlspecialchars(json_encode($project['project_name'])); ?>)">
                            Rename
                        </button>
                        <a href="<?= BASE_URL ?>/project-settings.php?id=<?php echo $project['id']; ?>" class="card-dd-item" style="text-decoration:none;">
                            Settings
                        </a>
                        <?php endif; ?>
                        <button class="card-dd-item" onclick="openArchive(<?php echo $project['id']; ?>, <?php echo htmlspecialchars(json_encode($project['project_name'])); ?>, <?php echo $isArchived ? 'true' : 'false'; ?>)">
                            <?php echo $isArchived ? 'Unarchive' : 'Archive'; ?>
                        </button>
                         <button class="card-dd-item" onclick="openDuplicate(<?php echo $project['id']; ?>, <?php echo htmlspecialchars(json_encode($project['project_name'])); ?>)">
                            Duplicate
                        </button>
                        <?php if ($project['publish_status'] === 'deployed' || $project['publish_status'] === 'published'): ?>
                        <button class="card-dd-item danger" onclick="openUnpublish(<?php echo $project['id']; ?>)">
                            Unpublish
                        </button>
                        <?php endif; ?>
                        <div class="card-dd-sep"></div>
                        <button class="card-dd-item danger" onclick="openDelete(<?php echo $project['id']; ?>, <?php echo htmlspecialchars(json_encode($project['project_name'])); ?>)">
                            Delete
                        </button>
                    </div>

                    <!-- Card inner (link area) -->
                    <div class="project-card-inner">
                        <a href="<?= BASE_URL ?>/project-editor.php?id=<?php echo $project['id']; ?>" class="project-card-link">
                            <div class="project-card-visual template-preview-container shimmer">
                                <?php echo TemplatePreview::getPreviewHtml($project); ?>
                                <div class="project-card-visual-overlay"></div>
                                <?php if (!empty($project['live_url'] ?? '') && ($project['publish_status'] === 'deployed' || $project['publish_status'] === 'published')): ?>
                                <div class="card-visit-overlay" onclick="event.preventDefault(); event.stopPropagation(); window.open('<?php echo htmlspecialchars($project['live_url'] ?? ''); ?>', '_blank');">
                                    <span class="card-visit-btn">&#8599; Visit Site</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="project-card-header">
                                <span class="project-type"><?php echo htmlspecialchars($categoryLabel); ?></span>
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                            </div>
                            <div class="project-card-body" style="padding:16px;border-top:1px solid #F0F0F0;">
                                <div class="project-card-title-wrap">
                                    <h3 class="project-card-title" id="title-<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['project_name']); ?></h3>
                                </div>
                                <p class="project-card-meta">Updated <?php echo $timeAgo; ?></p>
                            </div>
                        </a>
                        <?php if ($project['publish_status'] === 'publishing' || $project['publish_status'] === 'deploying'): ?>
                        <div class="card-published-bar">
                            <span class="card-published-label">⏳ Building Site...</span>
                            <div class="spinner-small"></div>
                        </div>
                        <?php elseif ($project['publish_status'] === 'deployed' || $project['publish_status'] === 'published'): ?>
                        <div class="card-published-bar">
                            <span class="card-published-label">✅ Live</span>
                            <div style="display:flex;gap:5px;align-items:center;">
                                <button onclick="openPublish(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars(addslashes($project['custom_slug'])); ?>', true)" class="card-download-btn" title="Re-publish" style="background:#0F0F0F;color:#fff;">↺ Re-publish</button>
                                <button onclick="openUnpublish(<?php echo $project['id']; ?>)" class="card-download-btn" title="Unpublish" style="background:#fff;color:#c00;border:1px solid #f5c6c6;">⊘</button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card-published-bar" style="justify-content: flex-end;">
                            <button onclick="openPublish(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars(addslashes($project['custom_slug'] ?? '')); ?>', false)" class="card-download-btn" style="background:#0F0F0F;color:#fff;">🚀 Publish</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                        endforeach;
                    elseif ($view === 'archived'):
                        echo '<div style="color:#666;grid-column:1/-1;padding:20px;">No archived projects found.</div>';
                    endif;
                } catch (PDOException $e) {
                    echo '<div class="error-message">Error loading projects.</div>';
                }
                ?>
            </div>
        </main>
    </div>

    <!-- ── Rename Modal ──────────────────────────────────────── -->
    <div class="modal-backdrop" id="rename-backdrop">
        <div class="modal-box">
            <h3>Rename Project</h3>
            <p>Enter a new name for your project.</p>
            <input type="text" class="modal-input" id="rename-input" maxlength="100" placeholder="Project name…">
            <div class="modal-actions">
                <button class="modal-btn" onclick="closeRename()">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="rename-confirm-btn" onclick="executeRename()">Save</button>
            </div>
        </div>
    </div>

    <!-- ── Archive Modal ────────────────────────────────────── -->
    <div class="modal-backdrop" id="archive-backdrop">
        <div class="modal-box">
            <h3 id="archive-modal-title">Archive Project?</h3>
            <p id="archive-modal-msg">This project will be moved to Archived.</p>
            <div class="modal-actions">
                <button class="modal-btn" onclick="closeArchive()">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="archive-confirm-btn" onclick="executeArchive()">Confirm</button>
            </div>
        </div>
    </div>

    <!-- ── Duplicate Modal ────────────────────────────────────── -->
    <div class="modal-backdrop" id="duplicate-backdrop">
        <div class="modal-box">
            <h3>Duplicate Project?</h3>
            <p id="duplicate-modal-msg">A copy of this project will be created.</p>
            <div class="modal-actions">
                <button class="modal-btn" onclick="closeDuplicate()">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="duplicate-confirm-btn" onclick="executeDuplicate()">Duplicate</button>
            </div>
        </div>
    </div>

    <!-- ── Delete Modal ──────────────────────────────────────── -->
    <div class="modal-backdrop" id="delete-backdrop">
        <div class="modal-box">
            <h3>Delete Project?</h3>
            <p id="delete-modal-msg">This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="modal-btn" onclick="closeDelete()">Cancel</button>
                <button class="modal-btn modal-btn-danger" id="delete-confirm-btn" onclick="executeDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- ── Logout Modal ──────────────────────────────────────── -->
    <div class="modal-backdrop" id="logout-backdrop">
        <div class="modal-box">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to log out of CodeCanvas?</p>
            <div class="modal-actions">
                <button class="modal-btn" onclick="closeLogout()">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="logout-confirm-btn" onclick="executeLogout()">Logout</button>
            </div>
        </div>
    </div>

    <!-- ── Unpublish Modal ────────────────────────────────────── -->
    <div class="modal-backdrop" id="unpublish-backdrop">
        <div class="modal-box">
            <h3>Unpublish Portfolio</h3>
            <p>Are you sure you want to take this portfolio offline? This will completely delete the site from Vercel.</p>
            <form id="unpublish-form">
                <input type="hidden" name="project_id" id="unpublish-project-id">
                <div class="modal-actions">
                    <button type="button" class="modal-btn" onclick="closeUnpublish()">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-danger">Unpublish Now</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Production Deployment Modal ────────────────────────── -->
    <div class="modal-backdrop" id="publish-backdrop">
        <div class="modal-box">
            <h3 id="publish-modal-title">Deploy Your Website</h3>
            <p id="publish-modal-desc">Choose a custom URL for your project. Your site will be built and hosted instantly.</p>
            
            <form id="publish-form">
                <input type="hidden" name="id" id="publish-project-id">
                
                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px; color:#555;">SITE URL SLUG</label>
                    <div class="slug-input-group">
                        <span class="slug-prefix">https://</span>
                        <input type="text" name="slug" id="publish-slug" placeholder="my-awesome-site" 
                               class="slug-input-field" required autocomplete="off">
                        <span class="slug-suffix">.vercel.app</span>
                    </div>
                    <div id="slug-feedback" class="slug-feedback" style="margin-top:6px; font-size:12px; min-height: 15px;"></div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="modal-btn" id="publish-cancel-btn" onclick="closePublish()">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary" id="publish-submit-btn" disabled>Deploy Now</button>
                </div>
            </form>

            <!-- Building Progress Overlay -->
            <div id="publish-loading" style="display:none; position:absolute; inset:0; background:rgba(255,255,255,0.95); z-index:10; flex-direction:column; align-items:center; justify-content:center; border-radius:10px; padding: 40px; text-align: center;">
                <div class="shimmer" style="width:60px; height:60px; border-radius:12px; margin-bottom: 20px;"></div>
                <h4 style="margin:0 0 8px; font-weight:700;">Building Your Site</h4>
                <p style="font-size:13px; color:#666; margin:0 0 20px;">We are injecting your content and optimizing assets...</p>
                <div style="width:100%; height:4px; background:#f0f0f0; border-radius:2px; overflow:hidden;">
                    <div id="build-progress-bar" style="width:30%; height:100%; background:#000; transition: width 0.3s ease;"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* ── Publish Progress Overlay ─────────────────────── */
        #publish-progress-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
        }
        #publish-progress-overlay.visible {
            display: flex;
            animation: overlayFadeIn .25s ease;
        }
        @keyframes overlayFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .pub-card {
            background: #fff;
            border-radius: 16px;
            padding: 36px 40px;
            width: 420px;
            max-width: 92vw;
            box-shadow: 0 24px 64px rgba(0,0,0,0.2);
            text-align: center;
            animation: pubCardPop .3s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes pubCardPop {
            from { opacity:0; transform: scale(0.88) translateY(20px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }
        .pub-rocket {
            font-size: 48px;
            margin-bottom: 6px;
            display: block;
            animation: rocketFloat 1.6s ease-in-out infinite;
        }
        @keyframes rocketFloat {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-8px); }
        }
        .pub-title {
            font-size: 20px;
            font-weight: 800;
            color: #0F0F0F;
            margin: 10px 0 4px;
        }
        .pub-step {
            font-size: 13px;
            color: #666;
            margin-bottom: 24px;
            min-height: 20px;
            transition: opacity .3s;
        }
        .pub-track {
            width: 100%;
            height: 6px;
            background: #f0f0f0;
            border-radius: 99px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .pub-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #0F0F0F, #555);
            border-radius: 99px;
            transition: width 0.4s cubic-bezier(0.4,0,0.2,1);
        }
        .pub-pct {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

    </style>

    <!-- ── Fullscreen Publish Progress Overlay ───────────────── -->
    <div id="publish-progress-overlay">
        <div class="pub-card">
            <span class="pub-rocket" id="pub-rocket">🚀</span>
            <div class="pub-title" id="pub-title">Publishing Your Site</div>
            <div class="pub-step" id="pub-step">Preparing your portfolio...</div>
            <div class="pub-track">
                <div class="pub-fill" id="pub-fill"></div>
            </div>
            <div class="pub-pct" id="pub-pct">0%</div>
        </div>
    </div>

    <!-- ── Publish Success Modal ───────────────────────────────── -->
    <div class="modal-backdrop" id="publish-success-backdrop">
        <div class="modal-box" style="text-align: center;">
            <div style="font-size: 48px; margin-bottom: 20px;">🚀</div>
            <h3>Project Published!</h3>
            <p>Your portfolio is now live on Netlify.</p>
            
            <div style="background: #f5f5f5; padding: 12px; border-radius: 6px; margin: 20px 0; font-family: monospace; font-size: 13px; word-break: break-all; border: 1px dashed #ccc;" id="live-url-display">
                <!-- URL will be inserted here -->
            </div>

            <div class="modal-actions" style="justify-content: center;">
                <button class="modal-btn modal-btn-primary" onclick="copyLiveUrl()">Copy Link</button>
                <a href="" id="view-live-link" target="_blank" class="modal-btn">Visit Site</a>
                <button class="modal-btn" onclick="closePublishSuccess()">Close</button>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/navigation.js"></script>
    <script src="<?= BASE_URL ?>/public/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>

    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        NavManager.setCookie('lastVisitedPage', 'dashboard');
        NavManager.pushState('dashboard');

        /* ── Logout ──────────────────────────────────────────── */
        function openLogout(e) {
            if (e) e.preventDefault();
            closeAllMenus();
            document.getElementById('logout-backdrop').classList.add('open');
        }

        function closeLogout() {
            document.getElementById('logout-backdrop').classList.remove('open');
        }

        function executeLogout() {
            window.location.href = BASE_URL + '/auth/logout.php';
        }

        /* ── Search filter ───────────────────────────────────── */
        document.getElementById('search-input').addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.project-card[data-project-id]').forEach(card => {
                const name = (card.dataset.projectName || '').toLowerCase();
                card.style.display = name.includes(q) ? '' : 'none';
            });
        });

        /* ── Publish Success Handling ────────────────────────── */
        function checkPublishStatus() {
            const urlParams = new URLSearchParams(window.location.search);
            const publishedId = urlParams.get('published');
            const unlistedId = urlParams.get('unlisted');
            const liveUrl = urlParams.get('live_url');
            const error = urlParams.get('error');

            if (error) {
                alert("Publish Error: " + decodeURIComponent(error));
                window.history.replaceState({}, document.title, window.location.pathname);
                return;
            }

            if (unlistedId) {
                // Success message for unpublish
                window.history.replaceState({}, document.title, window.location.pathname);
                // Could add a toast here
            }

            if (publishedId && liveUrl) {
                const decUrl = decodeURIComponent(liveUrl);
                document.getElementById('live-url-display').textContent = decUrl;
                document.getElementById('view-live-link').href = decUrl;
                document.getElementById('publish-success-backdrop').classList.add('open');
                
                // Clear URL params without reload
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        /* ── Production Deployment Flow ───────────────────────── */
        let _slugCheckTimeout = null;

        function closePublish() {
            document.getElementById('publish-backdrop').classList.remove('open');
            document.getElementById('publish-loading').style.display = 'none';
        }

        function openPublish(id, slug) {
            document.getElementById('publish-project-id').value = id;
            const slugInput = document.getElementById('publish-slug');
            slugInput.value = slug || '';
            document.getElementById('publish-backdrop').classList.add('open');
            document.getElementById('slug-feedback').innerHTML = '';
            document.getElementById('publish-submit-btn').disabled = !slug;
            
            if (slug) validateSlug(slug, id);
        }

        document.getElementById('publish-slug').addEventListener('input', function() {
            let slug = this.value;
            // Immediate sanitize
            const clean = slug.toLowerCase().replace(/[^a-z0-9-]/g, '');
            if (slug !== clean) {
                this.value = clean;
                slug = clean;
            }
            
            const btn = document.getElementById('publish-submit-btn');
            const feedback = document.getElementById('slug-feedback');

            if (slug.length < 3) {
                btn.disabled = true;
                feedback.innerHTML = '<span style="color:#888;">Min 3 characters</span>';
                return;
            }

            btn.disabled = true;
            feedback.innerHTML = '<span style="color:#888;">Checking availability...</span>';

            clearTimeout(_slugCheckTimeout);
            _slugCheckTimeout = setTimeout(() => {
                validateSlug(slug);
            }, 600);
        });

        async function validateSlug(slug) {
            const btn = document.getElementById('publish-submit-btn');
            const feedback = document.getElementById('slug-feedback');
            const projectId = document.getElementById('publish-project-id').value;
            
            try {
                const fd = new FormData();
                fd.append('slug', slug);
                fd.append('project_id', projectId);
                
                const response = await fetch(BASE_URL + '/check-slug.php', { method: 'POST', body: fd });
                const data = await response.json();
                
                if (data.available) {
                    feedback.innerHTML = '<span style="color:#22c55e;">✔ Available</span>';
                    btn.disabled = false;
                } else {
                    feedback.innerHTML = `<span style="color:#eab308;">❌ ${data.error || 'Taken on Vercel.'}</span>`;
                    btn.disabled = true;
                }
            } catch (e) {
                feedback.innerHTML = '<span style="color:#ef4444;">Error checking availability.</span>';
                btn.disabled = true;
            }
        }

        document.getElementById('publish-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const id   = document.getElementById('publish-project-id').value;
            const slug = document.getElementById('publish-slug').value;
            const btn = document.getElementById('publish-submit-btn');
            
            // Final verify before deployment
            btn.disabled = true;
            document.getElementById('slug-feedback').innerHTML = '<span style="color:#888;">Double checking availability...</span>';
            
            const fd = new FormData();
            fd.append('slug', slug);
            fd.append('project_id', id);
            const response = await fetch(BASE_URL + '/check-slug.php', { method: 'POST', body: fd });
            const data = await response.json();
            
            if (!data.available) {
                document.getElementById('slug-feedback').innerHTML = `<span style="color:#ef4444; font-weight:600;">❌ Taken on Vercel. Try another.</span>`;
                return; // Stop deployment
            }

            // Close the deploy modal
            document.getElementById('publish-backdrop').classList.remove('open');

            // Show fullscreen progress overlay
            const overlay  = document.getElementById('publish-progress-overlay');
            const fill     = document.getElementById('pub-fill');
            const pctEl    = document.getElementById('pub-pct');
            const stepEl   = document.getElementById('pub-step');
            const titleEl  = document.getElementById('pub-title');
            const rocketEl = document.getElementById('pub-rocket');
            overlay.classList.add('visible');

            // Staged messages with progress %
            const stages = [
                { pct: 15, msg: 'Double checking URL availability...' },
                { pct: 35, msg: 'Extracting template files...' },
                { pct: 55, msg: 'Validating folders & links...' },
                { pct: 75, msg: 'Pushing files to Vercel...' },
                { pct: 90, msg: 'Configuring domain alias...' },
                { pct: 96, msg: 'Almost there...' },
            ];
            let stageIdx = 0;
            function setProgress(pct, msg) {
                fill.style.width  = pct + '%';
                pctEl.textContent = Math.round(pct) + '%';
                if (msg) stepEl.textContent = msg;
            }
            setProgress(0, stages[0].msg);
            const stageTimer = setInterval(() => {
                if (stageIdx < stages.length) {
                    const s = stages[stageIdx++];
                    setProgress(s.pct, s.msg);
                }
            }, 900);

            const formData = new FormData();
            formData.append('id',   id);
            formData.append('slug', slug);

            fetch(BASE_URL + '/app/DeployController.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                clearInterval(stageTimer);
                if (data.success) {
                    setProgress(100, 'Site is live! 🎉');
                    titleEl.textContent  = 'Published!';
                    rocketEl.textContent = '✅';
                    setTimeout(() => {
                        overlay.classList.remove('visible');
                        window.location.reload();
                    }, 1400);
                } else {
                    overlay.classList.remove('visible');
                    alert('Publish failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(() => {
                clearInterval(stageTimer);
                overlay.classList.remove('visible');
                alert('Connection error. Please try again.');
            });
        });

        /* ── Unpublish Logic ─────────────────────────────────── */
        function openUnpublish(id) {
            document.getElementById('unpublish-project-id').value = id;
            document.getElementById('unpublish-backdrop').classList.add('open');
        }

        function closeUnpublish() {
            document.getElementById('unpublish-backdrop').classList.remove('open');
        }

        document.getElementById('unpublish-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Unpublishing...';

            const fd = new FormData(this);
            fetch(BASE_URL + '/project-unpublish.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Unpublish failed: ' + data.message);
                        btn.disabled = false;
                        btn.textContent = 'Unpublish Now';
                    }
                })
                .catch(err => {
                    alert('Network error. Please try again.');
                    btn.disabled = false;
                    btn.textContent = 'Unpublish Now';
                });
        });

        function closePublishSuccess() {
            document.getElementById('publish-success-backdrop').classList.remove('open');
        }

        function copyLiveUrl() {
            const url = document.getElementById('live-url-display').textContent;
            navigator.clipboard.writeText(url).then(() => {
                const btn = event.target;
                const oldText = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(() => btn.textContent = oldText, 2000);
            });
        }

        checkPublishStatus();

        /* ── 3-dot Context Menu ──────────────────────────────── */
        let _openMenu = null;

        function toggleMenu(e, projectId) {
            e.preventDefault();
            e.stopPropagation();
            const menu = document.getElementById('menu-' + projectId);
            if (_openMenu && _openMenu !== menu) _openMenu.classList.remove('open');
            menu.classList.toggle('open');
            _openMenu = menu.classList.contains('open') ? menu : null;
        }

        document.addEventListener('click', () => {
            if (_openMenu) { _openMenu.classList.remove('open'); _openMenu = null; }
        });

        /* ── Rename ──────────────────────────────────────────── */
        let _renameId = null;

        function openRename(projectId, currentName) {
            closeAllMenus();
            _renameId = projectId;
            document.getElementById('rename-input').value = currentName;
            document.getElementById('rename-confirm-btn').disabled = false;
            document.getElementById('rename-confirm-btn').textContent = 'Save';
            document.getElementById('rename-backdrop').classList.add('open');
            setTimeout(() => document.getElementById('rename-input').select(), 50);
        }

        function closeRename() {
            document.getElementById('rename-backdrop').classList.remove('open');
            _renameId = null;
        }

        async function executeRename() {
            if (!_renameId) return;
            const name = document.getElementById('rename-input').value.trim();
            if (!name) { alert('Name cannot be empty.'); return; }

            const btn = document.getElementById('rename-confirm-btn');
            btn.disabled = true; btn.textContent = 'Saving…';

            try {
                const res  = await fetch(BASE_URL + '/project-rename.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: _renameId, name })
                });
                const data = await res.json();

                if (data.success) {
                    // Update card title in DOM
                    const titleEl = document.getElementById('title-' + _renameId);
                    if (titleEl) titleEl.textContent = data.name;
                    // Update data-project-name for search
                    const card = document.querySelector('.project-card[data-project-id="' + _renameId + '"]');
                    if (card) card.dataset.projectName = data.name;
                    closeRename();
                } else {
                    alert(data.message || 'Failed to rename.');
                    btn.disabled = false; btn.textContent = 'Save';
                }
            } catch (e) {
                alert('Network error. Please try again.');
                btn.disabled = false; btn.textContent = 'Save';
            }
        }

        /* ── Archive ─────────────────────────────────────────── */
        let _archiveId = null, _archiveAction = 'archive';

        function openArchive(projectId, projectName, isCurrentlyArchived) {
            closeAllMenus();
            _archiveId     = projectId;
            _archiveAction = isCurrentlyArchived ? 'unarchive' : 'archive';

            document.getElementById('archive-modal-title').textContent =
                isCurrentlyArchived ? 'Unarchive Project?' : 'Archive Project?';
            document.getElementById('archive-modal-msg').textContent =
                isCurrentlyArchived
                    ? '"' + projectName + '" will be restored to your active projects.'
                    : '"' + projectName + '" will be moved to Archived. You can restore it later.';
            document.getElementById('archive-confirm-btn').textContent =
                isCurrentlyArchived ? 'Unarchive' : 'Archive';
            document.getElementById('archive-confirm-btn').disabled = false;
            document.getElementById('archive-backdrop').classList.add('open');
        }

        function closeArchive() {
            document.getElementById('archive-backdrop').classList.remove('open');
            _archiveId = null;
        }

        async function executeArchive() {
            if (!_archiveId) return;
            const btn = document.getElementById('archive-confirm-btn');
            btn.disabled = true; btn.textContent = 'Working…';

            try {
                const res  = await fetch(BASE_URL + '/project-archive.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: _archiveId, action: _archiveAction })
                });
                const data = await res.json();

                if (data.success) {
                    // Fade out & remove card (the filter no longer matches)
                    const card = document.querySelector('.project-card[data-project-id="' + _archiveId + '"]');
                    if (card) {
                        card.style.transition = 'opacity .25s, transform .25s';
                        card.style.opacity    = '0';
                        card.style.transform  = 'scale(0.95)';
                        setTimeout(() => card.remove(), 260);
                    }
                    closeArchive();
                } else {
                    alert(data.message || 'Action failed.');
                    btn.disabled = false; btn.textContent = _archiveAction === 'archive' ? 'Archive' : 'Unarchive';
                }
            } catch (e) {
                alert('Network error. Please try again.');
                btn.disabled = false; btn.textContent = _archiveAction === 'archive' ? 'Archive' : 'Unarchive';
            }
        }

        /* ── Delete ──────────────────────────────────────────── */
        let _deleteId = null;

        function openDelete(projectId, projectName) {
            closeAllMenus();
            _deleteId = projectId;
            document.getElementById('delete-modal-msg').textContent =
                '"' + projectName + '" will be permanently deleted. This cannot be undone.';
            document.getElementById('delete-confirm-btn').disabled = false;
            document.getElementById('delete-confirm-btn').textContent = 'Delete';
            document.getElementById('delete-backdrop').classList.add('open');
        }

        function closeDelete() {
            document.getElementById('delete-backdrop').classList.remove('open');
            _deleteId = null;
        }

        async function executeDelete() {
            if (!_deleteId) return;
            const btn = document.getElementById('delete-confirm-btn');
            btn.disabled = true; btn.textContent = 'Deleting…';

            try {
                const res  = await fetch(BASE_URL + '/project-delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: _deleteId })
                });
                const data = await res.json();

                if (data.success) {
                    const card = document.querySelector('.project-card[data-project-id="' + _deleteId + '"]');
                    if (card) {
                        card.style.transition = 'opacity .25s, transform .25s';
                        card.style.opacity    = '0';
                        card.style.transform  = 'scale(0.95)';
                        setTimeout(() => card.remove(), 260);
                    }
                    closeDelete();
                } else {
                    alert(data.message || 'Failed to delete.');
                    btn.disabled = false; btn.textContent = 'Delete';
                }
            } catch (e) {
                alert('Network error. Please try again.');
                btn.disabled = false; btn.textContent = 'Delete';
            }
        }

        /* ── Duplicate ────────────────────────────────────────── */
        let _duplicateId = null;

        function openDuplicate(projectId, projectName) {
            closeAllMenus();
            _duplicateId = projectId;
            document.getElementById('duplicate-modal-msg').textContent = 
                'Create a copy of "' + projectName + '"? All your data will be cloned.';
            document.getElementById('duplicate-confirm-btn').disabled = false;
            document.getElementById('duplicate-confirm-btn').textContent = 'Duplicate';
            document.getElementById('duplicate-backdrop').classList.add('open');
        }

        function closeDuplicate() {
            document.getElementById('duplicate-backdrop').classList.remove('open');
            _duplicateId = null;
        }

        async function executeDuplicate() {
            if (!_duplicateId) return;
            const btn = document.getElementById('duplicate-confirm-btn');
            btn.disabled = true; btn.textContent = 'Duplicating…';

            try {
                const res = await fetch(BASE_URL + '/project-duplicate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: _duplicateId })
                });
                const data = await res.json();

                if (data.success) {
                    // Just reload to show the new project
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to duplicate.');
                    btn.disabled = false; btn.textContent = 'Duplicate';
                }
            } catch (e) {
                alert('Network error. Please try again.');
                btn.disabled = false; btn.textContent = 'Duplicate';
            }
        }

        /* ── Shared helpers ──────────────────────────────────── */
        function closeAllMenus() {
            document.querySelectorAll('.card-dropdown.open').forEach(m => m.classList.remove('open'));
            _openMenu = null;
        }

        // Close modals on backdrop click / Escape
        ['rename-backdrop','archive-backdrop','delete-backdrop'].forEach(id => {
            const el = document.getElementById(id);
            el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeRename(); closeArchive(); closeDelete(); closeAllMenus();
            }
        });
        // Submit rename on Enter
        document.getElementById('rename-input').addEventListener('keydown', e => {
            if (e.key === 'Enter') executeRename();
        });
    </script>
</body>
</html>
