<?php
/**
 * ADMIN - EDIT TEMPLATE v2
 * Matches new admin theme
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/admin_auth.php';

$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Get template
try {
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute([$id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        $_SESSION['message'] = 'Template not found.';
        header("Location: " . BASE_URL . '/app/templates.php');
exit;
    }
} catch (PDOException $e) {
    $_SESSION['message'] = 'Error loading template.';
    header("Location: " . BASE_URL . '/app/templates.php');
exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'rescan') {
        // ── RESCAN LOGIC ──
        require_once APP_ROOT . '/app/core/TemplateScanner.php';
        $fullPath = __DIR__ . '/../' . $template['folder_path'];
        if (is_dir($fullPath)) {
            $scanResult = TemplateScanner::scan($fullPath);
            TemplateScanner::generateSchema($fullPath, $scanResult);
            
            $fieldsList = array_map(fn($f) => $f['key'], array_slice($scanResult['fields'], 0, 15));
            $fieldsStr = implode(', ', $fieldsList);
            if (count($scanResult['fields']) > 15) $fieldsStr .= '...';

            $success = "Template rescanned! Found " . $scanResult['total'] . " fields: " . htmlspecialchars($fieldsStr);
        } else {
            $error = "Template directory not found: " . $template['folder_path'];
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $template_type = $_POST['template_type'] ?? '';
        $folder_path = trim($_POST['folder_path'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Validate
        if (empty($name)) {
            $error = 'Template name is required.';
        } elseif (empty($template_type) || !in_array($template_type, ['personal', 'portfolio', 'business', 'shop', 'e-commerce'])) {
            $error = 'Please select a valid template type.';
        } elseif (empty($folder_path)) {
            $error = 'Folder path is required.';
        } else {
            // Generate slug if name changed
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE templates 
                    SET name = ?, slug = ?, template_type = ?, folder_path = ?, status = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $slug, $template_type, $folder_path, $status, $id]);
                
                $_SESSION['message'] = 'Template updated successfully.';
                header("Location: " . BASE_URL . '/app/templates.php');
exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'A template with this name already exists.';
                } else {
                    $error = 'Database error. Please try again.';
                }
            }
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $template;
}

// User info for sidebar/nav
$userName = $_SESSION['user_name'] ?? 'Admin User';
$userInitials = strtoupper(substr($userName, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Template — CodeCanvas Admin</title>
    <link href="<?= BASE_URL ?>/public/assets/css/admin-theme.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .edit-form-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 32px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group { margin-bottom: 24px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-input { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #e5e5e5; 
            border-radius: 6px; 
            font-size: 14px;
        }
        .form-input:focus { outline: none; border-color: #000; }
        
        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 14px;
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
                    <!-- Placeholder for consistency -->
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
                <div class="mb-4" style="margin-bottom: 24px;">
                    <a href="templates.php" style="color: #666; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 4px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        Back to Templates
                    </a>
                </div>

                <div class="section-header">
                    <div>
                        <h1 class="page-title">Edit Template</h1>
                        <p class="page-subtitle">Update details for <strong><?= htmlspecialchars($template['name']) ?></strong></p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div style="background: #E8F5E9; color: #2E7D32; padding: 12px; border-radius: 6px; margin-bottom: 24px; font-size: 14px;">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <div class="edit-form-card">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name" class="form-label">Template Name <span style="color:red">*</span></label>
                            <input type="text" id="name" name="name" class="form-input" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. Modern Portfolio">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label for="template_type" class="form-label">Template Type <span style="color:red">*</span></label>
                                <select id="template_type" name="template_type" class="form-input" required>
                                    <option value="">Select type...</option>
                                    <option value="personal" <?= ($_POST['template_type'] ?? '') === 'personal' ? 'selected' : '' ?>>Personal</option>
                                    <option value="portfolio" <?= ($_POST['template_type'] ?? '') === 'portfolio' ? 'selected' : '' ?>>Portfolio</option>
                                    <option value="business" <?= ($_POST['template_type'] ?? '') === 'business' ? 'selected' : '' ?>>Business</option>
                                    <option value="shop" <?= ($_POST['template_type'] ?? '') === 'shop' ? 'selected' : '' ?>>Shop</option>
                                    <option value="e-commerce" <?= ($_POST['template_type'] ?? '') === 'e-commerce' ? 'selected' : '' ?>>E-Commerce</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-input">
                                    <option value="active" <?= ($_POST['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="folder_path" class="form-label">Folder Path <span style="color:red">*</span></label>
                            <input type="text" id="folder_path" name="folder_path" class="form-input" required value="<?= htmlspecialchars($_POST['folder_path'] ?? '') ?>" placeholder="templates/...">
                            <p style="font-size: 12px; color: #888; margin-top: 6px;">Relative path to the template folder on server</p>
                        </div>
                        
                        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #F0F0F0; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <button type="submit" name="action" value="rescan" class="btn" style="background: #6200EA; color: white;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: middle; margin-right: 4px;"><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path></svg>
                                    Rescan Template
                                </button>
                            </div>
                            <div style="display: flex; gap: 12px;">
                                <a href="templates.php" class="btn btn-outline" style="border: 1px solid #e5e5e5; background: #fff; color: #333;">Cancel</a>
                                <button type="submit" class="btn">Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Detected Fields Preview -->
                <div class="edit-form-card" style="margin-top: 24px; background: #fcfcfc;">
                    <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        Detected Fields (from schema.json)
                    </h3>
                    <?php
                        $schemaPath = __DIR__ . '/../' . $template['folder_path'] . '/schema.json';
                        if (file_exists($schemaPath)) {
                            $schema = json_decode(file_get_contents($schemaPath), true);
                            if ($schema && isset($schema['fields']) && !empty($schema['fields'])) {
                                echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">';
                                foreach ($schema['fields'] as $f) {
                                    $k = htmlspecialchars($f['key'] ?? $f['id'] ?? 'unknown');
                                    $t = htmlspecialchars($f['type'] ?? 'text');
                                    echo "<div style='background:#fff; border:1px solid #eee; padding:10px; border-radius:6px; font-size:12px;'>
                                            <div style='font-weight:600; color:#111; margin-bottom:4px;'>$k</div>
                                            <div style='color:#666; font-family:monospace; font-size:10px; text-transform:uppercase;'>$t</div>
                                          </div>";
                                }
                                echo '</div>';
                            } else {
                                echo '<p style="font-size:13px; color:#999;">No fields found in schema.json. Try rescanning.</p>';
                            }
                        } else {
                            echo '<p style="font-size:13px; color:#999;">schema.json not found. Click "Rescan Template" to generate it.</p>';
                        }
                    ?>
                </div>

            </div>
        </main>
    </div>

    <script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/public/assets/js/admin.js"></script>
</body>
</html>
