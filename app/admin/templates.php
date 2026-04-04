<?php
/**
 * ADMIN TEMPLATES v2
 * Manage templates with "Grid Card" layout
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/admin_auth.php';
require_once APP_ROOT . '/app/core/TemplatePreview.php';

// Handle Toggle Status Action
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $currentStatus = $_GET['current_status'] ?? 'active';
    $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
    try {
        $pdo->prepare("UPDATE templates SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        header("Location: " . BASE_URL . '/admin/templates.php');
exit;
    } catch (PDOException $e) {}
}

// Helper for recursive directory removal
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR . $object) && !is_link($dir."/".$object))
                    rrmdir($dir. DIRECTORY_SEPARATOR . $object);
                else
                    unlink($dir. DIRECTORY_SEPARATOR . $object);
            }
        }
        rmdir($dir);
    }
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        // 1. Check if projects are using this template
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE template_id = ?");
        $stmt->execute([$id]);
        $projectCount = $stmt->fetchColumn();

        if ($projectCount > 0) {
            $_SESSION['message'] = "Cannot delete: This template is being used by $projectCount project(s).";
            $_SESSION['message_type'] = "error";
            header("Location: " . BASE_URL . '/admin/templates.php');
exit;
        }

        // 2. Get folder path
        $stmt = $pdo->prepare("SELECT folder_path FROM templates WHERE id = ?");
        $stmt->execute([$id]);
        $tpl = $stmt->fetch();

        // 3. Delete from database first
        $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ?");
        $stmt->execute([$id]);

        // 4. If DB delete successful, cleanup files
        if ($tpl && !empty($tpl['folder_path'])) {
            $fullPath = realpath(__DIR__ . '/../' . $tpl['folder_path']);
            // Security check: ensure path is inside templates folder
            if ($fullPath && strpos($fullPath, realpath(__DIR__ . '/../templates')) === 0 && is_dir($fullPath)) {
                rrmdir($fullPath);
            }
        }

        $_SESSION['message'] = "Template deleted successfully.";
        header("Location: " . BASE_URL . '/admin/templates.php');
exit;
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error deleting template: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: " . BASE_URL . '/admin/templates.php');
exit;
    }
}

// Fetch templates
try {
    $stmt = $pdo->query("SELECT * FROM templates ORDER BY created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}

// User info
$userName = $_SESSION['user_name'] ?? 'Admin User';
$userInitials = strtoupper(substr($userName, 0, 2));

function sanitizeTemplateName(string $name, string $slug, string $folderPath): string
{
    $looksCorrupted = str_contains($name, '/') || str_contains($name, '\\')
        || preg_match('/\.(css|html?|js|php|zip)$/i', $name);

    if (!$looksCorrupted) {
        return $name;
    }

    $fallback = $slug ?: (basename(rtrim($folderPath, '/')) ?: $name);
    return ucwords(str_replace(['-', '_'], ' ', $fallback));
}

function getTemplateThumbnail($template) {
    $folderPath = rtrim($template['folder_path'] ?? 'templates/developer', '/');
    $absPath = __DIR__ . '/../' . $folderPath;
    
    // Dynamically find entry point
    $entryPoint = 'index.html';
    $candidates = ['code.html', 'index.html', 'index.htm'];
    
    foreach ($candidates as $c) {
        if (file_exists($absPath . '/' . $c)) {
            $entryPoint = $c;
            break;
        }
    }
    
    // Check 1 level deep
    if ($entryPoint === 'index.html' && !file_exists($absPath . '/index.html')) {
        $nested = glob($absPath . '/*/{code.html,index.html,index.htm}', GLOB_BRACE);
        if (!empty($nested)) $entryPoint = str_replace($absPath . '/', '', $nested[0]);
    }
    
    $previewUrl = '../' . $folderPath . '/' . $entryPoint;
    
    $hasSchema = file_exists(__DIR__ . '/../' . $folderPath . '/schema.json');
    $schemaBadge = $hasSchema ? 
        '<div style="position:absolute; top:8px; right:8px; background:#16a34a; color:#fff; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:600; z-index:10; box-shadow:0 2px 4px rgba(0,0,0,0.1);">SCHEMA-SYNC</div>' : 
        '<div style="position:absolute; top:8px; right:8px; background:#f59e0b; color:#fff; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:600; z-index:10; box-shadow:0 2px 4px rgba(0,0,0,0.1);">LEGACY</div>';

    return '<div class="template-card-visual" style="position:relative;">
                ' . $schemaBadge . '
                <iframe src="' . htmlspecialchars($previewUrl) . '" tabindex="-1" aria-hidden="true" loading="lazy"></iframe>
                <div class="template-visual-overlay"></div>
            </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Templates — CodeCanvas</title>
    <link href="<?= BASE_URL ?>/public/assets/css/admin-theme.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        /* Custom Grid Layout Styles */
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .template-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
        }

        .template-card:hover {
            border-color: #000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        .template-thumbnail-area {
            height: 180px;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            background: #f9f9f9;
            overflow: hidden;
        }

        .template-card-visual {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .template-card-visual iframe {
            width: 400%;
            height: 400%;
            transform: scale(0.25);
            transform-origin: top left;
            border: none;
            pointer-events: none;
            background: #fff;
        }

        .template-visual-overlay {
            position: absolute;
            inset: 0;
            z-index: 2;
        }

        .template-info-area {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .template-title {
            font-size: 18px;
            font-weight: 600;
            color: #111;
            margin: 0;
            line-height: 1.3;
        }

        .template-type-badge {
            font-size: 12px;
            color: #111;
            background: #f0f0f0;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: capitalize;
            font-weight: 500;
            margin-bottom: 16px;
            display: inline-block;
        }
        
        /* Status Badge on Thumbnail */
        .card-status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 10;
        }

        .card-status-badge.active {
            background: #fff;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .card-status-badge.inactive {
            background: #fff;
            color: #C62828;
            border: 1px solid #FFCDD2;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .template-actions-row {
            margin-top: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding-top: 20px;
            border-top: 1px solid #f5f5f5;
        }
        
        .action-btn {
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-edit {
            background: #fff;
            border: 1px solid #e5e5e5;
            color: #333;
        }
        .btn-edit:hover {
            border-color: #000;
            color: #000;
        }

        .btn-deactivate {
            background: #FFEBEE;
            color: #D32F2F;
            border: 1px solid #FFCDD2;
        }
        .btn-deactivate:hover {
            background: #D32F2F;
            color: #fff;
            border-color: #D32F2F;
        }

        .btn-activate {
            background: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
        }
        .btn-activate:hover {
            background: #2E7D32;
            color: #fff;
            border-color: #2E7D32;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="logo">CodeCanvas</a>
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="templates.php" class="nav-link active">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="9" y1="3" x2="9" y2="21"></line>
                            </svg>
                            Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/admin/notifications.php" class="nav-link">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            Notifications
                        </a>
                    </li>
                    <!-- Logout -->
                    <li class="nav-item" style="margin-top: 24px; border-top: 1px solid #f5f5f5; padding-top: 24px;">
                         <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-link">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search templates...">
                </div>
                <div class="navbar-actions">
                    <div class="admin-profile">
                        <div class="avatar"><?php echo $userInitials; ?></div>
                        <div class="admin-info">
                            <div class="admin-name"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="admin-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <div class="content-container">
                <?php if (isset($_SESSION['message'])): 
                    $mType = $_SESSION['message_type'] ?? 'success';
                    $mBg = ($mType === 'error') ? '#FFEBEE' : '#E8F5E9';
                    $mColor = ($mType === 'error') ? '#C62828' : '#2E7D32';
                    $mBorder = ($mType === 'error') ? '#FFCDD2' : '#C8E6C9';
                ?>
                    <div style="background: <?= $mBg ?>; color: <?= $mColor ?>; padding: 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid <?= $mBorder ?>; display: flex; align-items: center; justify-content: space-between;">
                        <span><?= $_SESSION['message'] ?></span>
                        <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; color:<?= $mColor ?>; font-weight:bold;">×</button>
                    </div>
                    <?php 
                        unset($_SESSION['message']); 
                        unset($_SESSION['message_type']);
                    ?>
                <?php endif; ?>

                <div class="section-header">
                    <div>
                        <h1 class="page-title">Templates</h1>
                        <p class="page-subtitle">Manage your website templates library</p>
                    </div>
                    <a href="template-add.php" class="btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add New Template
                    </a>
                </div>

                <div class="templates-grid">
                    <?php if (empty($templates)): ?>
                        <div style="grid-column: 1 / -1; padding: 40px; text-align: center; color: #666; background: #fff; border: 1px solid #e5e5e5; border-radius: 8px;">
                            <p style="margin-bottom: 16px;">No templates found.</p>
                            <a href="template-add.php" class="btn btn-outline">Add your first template</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <?php 
                                $isActive = ($template['status'] === 'active');
                                $statusLabel = $isActive ? 'Active' : 'Inactive';
                                $statusClass = $isActive ? 'active' : 'inactive';
                            ?>
                             <div class="template-card">
                                 <div class="template-thumbnail-area template-preview-container shimmer">
                                     <?= TemplatePreview::getPreviewHtml($template) ?>
                                     <span class="card-status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                 </div>
                                
                                <div class="template-info-area">
                                    <div class="template-header">
                                        <h3 class="template-title"><?= htmlspecialchars(sanitizeTemplateName($template['name'], $template['slug'] ?? '', $template['folder_path'] ?? '')) ?></h3>
                                    </div>
                                    
                                    <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                                        <?php if (!empty($template['template_type'])): ?>
                                            <span class="template-type-badge">
                                                <?= htmlspecialchars(ucfirst($template['template_type'])) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php 
                                            // Derive category from folder_path
                                            $parts = explode('/', trim($template['folder_path'], '/'));
                                            $catName = (count($parts) >= 2 && $parts[0] === 'templates') ? $parts[1] : 'Other';
                                        ?>
                                        <span class="template-type-badge" style="background: #E3F2FD; color: #1976D2;">
                                            Category: <?= htmlspecialchars(ucfirst($catName)) ?>
                                        </span>
                                    </div>

                                    <div class="template-actions-row" style="grid-template-columns: 1fr 1fr 1fr;">
                                        <a href="template-edit.php?id=<?= $template['id'] ?>" class="action-btn btn-edit">
                                            Edit
                                        </a>

                                        <?php if ($isActive): ?>
                                            <a href="?action=toggle&id=<?= $template['id'] ?>&current_status=active" class="action-btn btn-deactivate" title="Deactivate">
                                                Hide
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=toggle&id=<?= $template['id'] ?>&current_status=inactive" class="action-btn btn-activate" title="Activate">
                                                Show
                                            </a>
                                        <?php endif; ?>

                                        <a href="?action=delete&id=<?= $template['id'] ?>" class="action-btn" style="background: #FFF; border: 1px solid #FFEBEE; color: #D32F2F;" onclick="return confirm('Are you sure you want to delete this template and all its files?')">
                                            Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- ── Logout Modal ──────────────────────────────────────── -->
    <div id="logout-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:10px; width:100%; max-width:400px; padding:28px; box-shadow:0 24px 64px rgba(0,0,0,.18);">
            <h3 style="font-size:18px; font-weight:700; margin:0 0 10px;">Confirm Logout</h3>
            <p style="font-size:14px; color:#666; margin:0 0 24px;">Are you sure you want to log out of the admin panel?</p>
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <button onclick="closeLogout()" style="padding:10px 20px; border-radius:6px; background:#fff; border:1px solid #e5e5e5; cursor:pointer; font-weight:600;">Cancel</button>
                <button onclick="executeLogout()" style="padding:10px 20px; border-radius:6px; background:#000; color:#fff; border:none; cursor:pointer; font-weight:600;">Logout</button>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/admin.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        function openLogout(e) {
            if (e) e.preventDefault();
            document.getElementById('logout-backdrop').style.display = 'flex';
        }
        function closeLogout() {
            document.getElementById('logout-backdrop').style.display = 'none';
        }
        function executeLogout() {
            window.location.href = BASE_URL + '/auth/logout.php';
        }
        
        // Update logout link
        document.querySelector('a[href$="logout.php"]').onclick = openLogout;

        // --- Template Integrity Check ---
        window.addEventListener('DOMContentLoaded', () => {
            fetch(BASE_URL + '/admin/check_template_integrity.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.missing.length > 0) {
                        console.warn('Templates missing preview files:', data.missing);
                        const banner = document.createElement('div');
                        banner.style.cssText = 'background:#FEF2F2; color:#991B1B; padding:12px 24px; font-size:13px; font-weight:600; border-bottom:1px solid #FECACA;';
                        banner.innerHTML = `⚠️ Integrity Alert: ${data.missing.length} templates are missing preview images. <a href="#" style="text-decoration:underline;margin-left:8px;" onclick="alert('Missing: ' + JSON.stringify(data.missing.map(m => m.name)))">View Details</a>`;
                        const main = document.querySelector('.main-content');
                        if (main) main.prepend(banner);
                    }
                })
                .catch(err => console.error('Integrity check failed:', err));
        });
    </script>
</body>
</html>
