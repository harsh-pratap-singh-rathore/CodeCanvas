<?php
/**
 * ADMIN - EDIT TEMPLATE v3 (Visual Mapping Edition)
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
        header("Location: " . BASE_URL . '/app/admin/templates.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['message'] = 'Error loading template.';
    header("Location: " . BASE_URL . '/app/admin/templates.php');
    exit;
}

// Handle form submission (Simplified for example, keep identical to v2 logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'rescan') {
        require_once APP_ROOT . '/app/core/TemplateScanner.php';
        $fullPath = APP_ROOT . '/' . $template['folder_path'];
        if (is_dir($fullPath)) {
            $scanResult = TemplateScanner::scan($fullPath);
            TemplateScanner::generateSchema($fullPath, $scanResult);
            $success = "Template rescanned! Found " . $scanResult['total'] . " fields.";
        } else {
            $error = "Template directory not found.";
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $template_type = $_POST['template_type'] ?? '';
        $folder_path = trim($_POST['folder_path'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            $error = 'Template name is required.';
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
            $stmt = $pdo->prepare("UPDATE templates SET name = ?, slug = ?, template_type = ?, folder_path = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $template_type, $folder_path, $status, $id]);
            $success = 'Changes saved successfully.';
            // Refresh local data
            $template['name'] = $name;
            $template['folder_path'] = $folder_path;
            $template['template_type'] = $template_type;
            $template['status'] = $status;
        }
    }
}

$userInitials = strtoupper(substr($_SESSION['user_name'] ?? 'AD', 0, 2));

// --- Correct Preview URL Logic (Finding the template's HTML) ---
$folder = trim($template['folder_path'] ?? '', '/');
$candidates = ['index.html', 'index.htm', 'code.html', 'home.html', 'main.html'];
$foundFile = 'index.html'; // default
foreach ($candidates as $c) {
    if (file_exists(APP_ROOT . '/' . $folder . '/' . $c)) {
        $foundFile = $c;
        break;
    }
}
$previewUrl = BASE_URL . '/app/admin/template-proxy.php?path=' . urlencode($folder . '/' . $foundFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Template — CodeCanvas Admin</title>
    <link href="<?= BASE_URL ?>/public/assets/css/admin-theme.css" rel="stylesheet">
    <style>
        .split-layout {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 24px;
            align-items: flex-start;
            margin-top: 24px;
        }

        /* Preview Sidebar */
        .preview-sticky {
            position: sticky;
            top: 24px;
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            height: calc(100vh - 48px);
            border: 1px solid #222;
            box-shadow: 0 40px 100px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }
        .preview-header {
            padding: 12px 16px;
            background: #111;
            border-bottom: 1px solid #222;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #888;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .preview-iframe-wrapper {
            flex: 1;
            position: relative;
            background: #fff;
        }
        .preview-iframe-wrapper iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }

        /* Form Styling */
        .card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #444; }
        .form-input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        
        /* Field Tags */
        .field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }
        .field-tag {
            background: #fff;
            border: 1px solid #eee;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .field-tag:hover {
            border-color: #000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .field-tag.active {
            border-color: #6200EA;
            background: #f5f0ff;
        }
        .tag-key { font-weight: 700; font-size: 12px; color: #111; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .tag-type { font-size: 10px; color: #888; text-transform: uppercase; font-family: monospace; }
        
        .type-badge { width: 6px; height: 6px; border-radius: 50%; }
        .type-text { background: #3b82f6; }
        .type-image { background: #ec4899; }
        .type-link { background: #10b981; }

        .btn-rescan {
            background: #6200EA;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-rescan:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar (Omitted for brevity, keep your original) -->
        <main class="main-content" style="padding: 0;">
            <div class="split-layout">
                <!-- Left Side: Forms -->
                <div style="padding: 40px;">
                    <div style="max-width: 800px;">
                        <a href="templates.php" style="color: #888; text-decoration:none; font-size: 13px; display:inline-flex; align-items:center; gap:6px; margin-bottom: 32px;">
                            ← Back to Templates
                        </a>
                        
                        <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 8px;">Edit Template</h1>
                        <p style="color: #666; margin-bottom: 40px;">Manage metadata and visual field mapping for <strong><?= htmlspecialchars($template['name']) ?></strong></p>

                        <?php if($success): ?>
                            <div style="background:#f0fdf4; color:#16a34a; padding:16px; border-radius:12px; margin-bottom: 32px; border:1px solid #bbf7d0; font-size:14px; font-weight:600;">✅ <?= $success ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="card">
                                <h3 style="font-size:16px; margin-bottom:20px;">General Settings</h3>
                                <div class="form-group">
                                    <label class="form-label">Template Display Name</label>
                                    <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($template['name']) ?>">
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label class="form-label">Category</label>
                                        <select name="template_type" class="form-input">
                                            <option value="portfolio" <?= $template['template_type']=='portfolio'?'selected':'' ?>>Portfolio</option>
                                            <option value="shop" <?= $template['template_type']=='shop'?'selected':'' ?>>Shop</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-input">
                                            <option value="active" <?= $template['status']=='active'?'selected':'' ?>>Active</option>
                                            <option value="inactive" <?= $template['status']=='inactive'?'selected':'' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Folder Path</label>
                                    <input type="text" name="folder_path" class="form-input" value="<?= htmlspecialchars($template['folder_path']) ?>">
                                </div>
                                <button type="submit" class="btn" style="background:#000; color:#fff; width:100%; padding:12px; border-radius:10px; font-weight:700; margin-top:12px;">Update Metadata</button>
                            </div>

                            <div class="card">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                                    <h3 style="font-size:16px;">Detected Field Map</h3>
                                    <button type="submit" name="action" value="rescan" class="btn-rescan">
                                        ↺ Rescan AI Tags
                                    </button>
                                </div>
                                
                                <div class="field-grid">
                                    <?php
                                    $schemaPath = APP_ROOT . '/' . $template['folder_path'] . '/schema.json';
                                    if (file_exists($schemaPath)) {
                                        $schema = json_decode(file_get_contents($schemaPath), true);
                                        $fields = $schema['fields'] ?? $schema['sections'] ?? []; 
                                        
                                        foreach($fields as $id => $f) {
                                            $k = $f['key'] ?? $f['id'] ?? $id;
                                            $t = $f['type'] ?? 'text';
                                            $selector = $f['selector'] ?? '';
                                            echo "<div class='field-tag' data-selector='".htmlspecialchars($selector)."'>
                                                    <div class='tag-key'><span class='type-badge type-$t'></span> $k</div>
                                                    <div class='tag-type'>$t</div>
                                                  </div>";
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Side: Sticky Preview (Visual Map) -->
                <div class="preview-side" style="padding-right: 24px;">
                    <div class="preview-sticky">
                        <div class="preview-header">
                            <span>Live Visual Map</span>
                            <div style="display:flex; gap:8px;">
                                <div style="width:8px; height:8px; border-radius:50%; background:#ff5f57;"></div>
                                <div style="width:8px; height:8px; border-radius:50%; background:#ffbd2e;"></div>
                                <div style="width:8px; height:8px; border-radius:50%; background:#27c93f;"></div>
                            </div>
                        </div>
                        <div class="preview-iframe-wrapper">
                            <iframe id="preview-frame" src="<?= $previewUrl ?>"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const frame = document.getElementById('preview-frame');
        
        document.querySelectorAll('.field-tag').forEach(tag => {
            const selector = tag.getAttribute('data-selector');
            
            tag.addEventListener('mouseenter', () => {
                tag.classList.add('active');
                // Communicate with Iframe to highlight
                frame.contentWindow.postMessage({
                    action: 'highlight',
                    selector: selector
                }, '*');
            });

            tag.addEventListener('mouseleave', () => {
                tag.classList.remove('active');
                frame.contentWindow.postMessage({
                    action: 'unhighlight'
                }, '*');
            });
        });

        // Add CSS to the iframe via message for highlighting
        frame.addEventListener('load', () => {
            const highlightStyle = `
                [data-canvas-highlight] {
                    outline: 4px solid #6200EA !important;
                    outline-offset: 4px !important;
                    transition: all 0.2s ease !important;
                    position: relative !important;
                    z-index: 9999 !important;
                }
                [data-canvas-highlight]::after {
                    content: "FOUND TAG";
                    position: absolute;
                    top: -24px;
                    left: 0;
                    background: #6200EA;
                    color: white;
                    font-size: 10px;
                    font-weight: 800;
                    padding: 2px 6px;
                    border-radius: 4px;
                    white-space: nowrap;
                }
            `;
            frame.contentWindow.postMessage({
                action: 'inject-css',
                css: highlightStyle
            }, '*');
        });
        
        // Listener for iframe communication would be inside index.php/TemplatePreview
    </script>
</body>
</html>
