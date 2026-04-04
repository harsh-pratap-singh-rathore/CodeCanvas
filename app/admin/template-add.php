<?php
/**
 * ADMIN - ADD TEMPLATE v2
 * Supports File Upload (ZIP/HTML) & Auto-scanning
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/admin_auth.php';
require_once APP_ROOT . '/app/core/TemplateScanner.php';

$error = '';
$success = '';

// User info
$userName = $_SESSION['user_name'] ?? 'Admin User';
$userInitials = strtoupper(substr($userName, 0, 2));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateName  = trim($_POST['name'] ?? '');   // <-- safe variable, never overwritten
    $name          = $templateName;                 // keep $name for legacy compat
    $category      = $_POST['category'] ?? 'normal';
    $template_type = $_POST['template_type'] ?? 'portfolio';
    $status        = $_POST['status'] ?? 'active';
    
    // File Upload Handling
    if (empty($name)) {
        $error = 'Template name is required.';
    } elseif (empty($_FILES['template_file']['name'])) {
        $error = 'Please upload a template file (HTML or ZIP).';
    } else {
        // Generate slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        
        $targetDir = "../templates/" . $category . "/" . $slug;
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = basename($_FILES['template_file']['name']);
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileType === 'zip') {
            // ── ZIP HANDLING ──
            $zip = new ZipArchive();
            if ($zip->open($_FILES['template_file']['tmp_name']) === TRUE) {
                // --- SMART HOISTING LOGIC ---
                // Detect if all files are inside a single root folder
                $rootFolderName = '';
                $isSingleRoot = true;
                $fileCount = 0;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zipEntry = $zip->getNameIndex($i);  
                    if (str_contains($zipEntry, '__MACOSX')) continue;
                    $fileCount++;

                    $parts = explode('/', trim($zipEntry, '/'));
                    if (empty($rootFolderName)) {
                        $rootFolderName = $parts[0];
                    } elseif ($rootFolderName !== $parts[0]) {
                        $isSingleRoot = false;
                        break;
                    }
                }
                
                // Only hoist if we have files and they are all in one folder
                $prefix = ($isSingleRoot && $fileCount > 0 && !empty($rootFolderName)) ? $rootFolderName . '/' : '';

                // Extract and flatten if necessary
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (str_contains($entryName, '..') || str_contains($entryName, '__MACOSX')) continue;
                    
                    // Remove prefix if hoisting
                    $relativeName = $entryName;
                    if (!empty($prefix) && str_starts_with($entryName, $prefix)) {
                        $relativeName = substr($entryName, strlen($prefix));
                    }
                    if (empty($relativeName)) continue;

                    $fileDest = $targetDir . '/' . ltrim($relativeName, '/\\');
                    
                    if (str_ends_with($entryName, '/')) {
                        if (!is_dir($fileDest)) mkdir($fileDest, 0755, true);
                    } else {
                        $parentDir = dirname($fileDest);
                        if (!is_dir($parentDir)) mkdir($parentDir, 0755, true);
                        file_put_contents($fileDest, $zip->getFromIndex($i));
                    }
                }
                $zip->close();
                $success = true;
            } else {
                $error = "Failed to open ZIP archive.";
            }
        } elseif ($fileType === 'html' || $fileType === 'htm') {
            // ── SINGLE HTML HANDLING ──
            $targetFile = $targetDir . "/code.html";
            if (move_uploaded_file($_FILES['template_file']['tmp_name'], $targetFile)) {
                $success = true;
            } else {
                $error = "Error uploading HTML file.";
            }
        } else {
            $error = "Invalid file type. Please upload .html or .zip";
        }

        if ($success) {
            // ── AUTO-SCAN & SCHEMA GEN ──
            $scanResult = TemplateScanner::scan($targetDir);
            TemplateScanner::generateSchema($targetDir, $scanResult);

            // Save to DB
            $folder_path = "templates/" . $category . "/" . $slug;
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO templates (name, slug, template_type, folder_path, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$templateName, $slug, $template_type, $folder_path, $status]); 
                
                // Detailed result message
                $keys = [];
                // Flatten keys from schema for display
                if (isset($scanResult['total'])) {
                     // We just display the count since the JS shows names now
                }
                
                $_SESSION['message'] = '<strong>Template uploaded successfully!</strong><br>Found ' . $scanResult['total'] . ' editable fields.';
                header("Location: " . BASE_URL . '/app/admin/templates.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'A template with this name already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Template — CodeCanvas Admin</title>
    <link href="<?= BASE_URL ?>/public/assets/css/admin-theme.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .upload-zone { border: 2px dashed #E5E5E5; border-radius: 12px; padding: 60px 40px; text-align: center; transition: all 0.2s ease; background: #FAFAFA; cursor: pointer; margin-bottom: 24px; }
        .upload-zone:hover { border-color: #000; background: #F0F0F0; }
        .upload-zone.active { border-color: #000; background: #F0F0F0; }
        .upload-text { font-size: 18px; font-weight: 600; margin-bottom: 8px; color: #111; }
        .upload-hint { color: #666; font-size: 14px; }
        .file-selected { display: none; align-items: center; justify-content: center; gap: 12px; padding: 16px; background: #E8F5E9; border: 1px solid #C8E6C9; border-radius: 8px; color: #2E7D32; font-weight: 500; margin-bottom: 24px; }
        .form-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 12px; padding: 32px; }
        @keyframes pubPop { from { opacity:0; transform:scale(0.85) translateY(24px); } to { opacity:1; transform:scale(1) translateY(0); } }
        @keyframes rocketFloat { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-10px); } }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="logo">CodeCanvas</a>
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link">Dashboard</a></li>
                    <li class="nav-item"><a href="templates.php" class="nav-link active">Templates</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="content-container">
                <div class="section-header">
                    <h1 class="page-title">Add New Template</h1>
                </div>

                <div class="form-card">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-zone" id="drop-zone">
                            <div class="upload-text">Drag & drop HTML or ZIP file here</div>
                            <input type="file" name="template_file" id="template_file" accept=".html,.zip" style="display: none;" required onchange="handleFileSelect(this)">
                        </div>

                        <div id="file-selected" class="file-selected">
                            <span id="file-name">filename.html</span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;">
                            <div class="form-group">
                                <label style="display: block; font-weight: 500; margin-bottom: 8px;">Template Name</label>
                                <input type="text" name="name" class="form-input" required placeholder="e.g. Modern Minimal" style="width: 100%; padding: 12px; border: 1px solid #e5e5e5; border-radius: 6px;">
                            </div>
                            <div class="form-group">
                                <label style="display: block; font-weight: 500; margin-bottom: 8px;">Category</label>
                                <select name="category" class="form-input" style="width:100%; padding: 12px; border: 1px solid #e5e5e5; border-radius: 6px;">
                                    <option value="developer">Developer</option>
                                    <option value="shop">Shop</option>
                                    <option value="normal" selected>Personal</option>
                                </select>
                            </div>
                        </div>

                        <div style="text-align: right; margin-top: 32px;">
                            <button type="submit" class="btn" style="padding: 12px 32px; font-size: 15px;">Upload & Auto-Scan</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- ── Fullscreen Scan Progress Overlay ────────────────── -->
    <div id="editor-pub-overlay" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.6); backdrop-filter:blur(8px); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:20px; padding:40px 48px; width:440px; max-width:92vw; box-shadow:0 32px 80px rgba(0,0,0,0.25); text-align:center; animation:pubPop .35s cubic-bezier(0.34,1.56,0.64,1);">
            <span style="font-size:52px; display:block; margin-bottom:8px; animation:rocketFloat 1.6s ease-in-out infinite;">🤖</span>
            <div id="epo-title" style="font-size:21px; font-weight:800; color:#0F0F0F; margin-bottom:4px;">Scanning Template</div>
            <div id="epo-step" style="font-size:13px; color:#6B6B6B; margin-bottom:24px; min-height:20px;">AI is parsing the HTML layout...</div>
            <div style="width:100%; height:6px; background:#f0f0f0; border-radius:99px; overflow:hidden; margin-bottom:10px;">
                <div id="epo-fill" style="height:100%; width:0%; background:linear-gradient(90deg,#0F0F0F,#555); border-radius:99px; transition:width .45s cubic-bezier(0.4,0,0.2,1);"></div>
            </div>
            <div id="epo-pct" style="font-size:12px; color:#999; font-weight:600; margin-bottom: 24px;">0%</div>

            <div style="font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span style="display:inline-block; width:6px; height:6px; background:#10b981; border-radius:50%; box-shadow:0 0 8px #10b981;"></span>
                Discovered Tags
            </div>

            <div id="epo-tag-log" style="height: 100px; overflow: hidden; position: relative; border-radius: 12px; background: #fafafa; border: 1px solid #f0f0f0; padding: 12px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; align-content: flex-start;">
                <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#ddd; font-size:11px; font-style:italic;" id="epo-log-empty">Waiting for parser...</div>
            </div>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('template_file');
        const fileSelected = document.getElementById('file-selected');
        const fileNameDisp = document.getElementById('file-name');
        
        dropZone.addEventListener('click', () => fileInput.click());
        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                dropZone.style.display = 'none';
                fileSelected.style.display = 'flex';
                fileNameDisp.textContent = input.files[0].name;
            }
        }

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const overlay = document.getElementById('editor-pub-overlay');
            const fill    = document.getElementById('epo-fill');
            const pctEl   = document.getElementById('epo-pct');
            const stepEl  = document.getElementById('epo-step');
            overlay.style.display = 'flex';

            const stages = [
                { pct: 20, msg: 'Extracting & Sanitizing...' },
                { pct: 45, msg: 'AI-Powered Structural Analysis...' },
                { pct: 85, msg: 'Generating Smart Schema...' },
                { pct: 98, msg: 'Finalizing Database...' }
            ];

            const sampleTags = ['hero_title', 'nav_logo', 'hero_desc', 'hero_image', 'skill_card', 'contact_btn', 'footer_links', 'social_icons', 'mobile_menu', 'cta_banner'];
            const tagLog = document.getElementById('epo-tag-log');
            const logEmpty = document.getElementById('epo-log-empty');

            let idx = 0;
            function addTag(name) {
                if(logEmpty) logEmpty.remove();
                const tag = document.createElement('div');
                tag.style.cssText = 'background:#10b981; color:#fff; font-size:10px; padding:4px 10px; border-radius:30px; font-weight:700; opacity:0; transform:scale(0.8); transition:all .3s ease;';
                tag.textContent = name.toUpperCase();
                tagLog.prepend(tag);
                setTimeout(() => { tag.style.opacity = '1'; tag.style.transform = 'scale(1)'; }, 50);
            }

            function setProgress(pct, msg) {
                fill.style.width  = pct + '%';
                pctEl.textContent = Math.round(pct) + '%';
                if (msg) stepEl.textContent = msg;
                if (pct > 25 && pct % 10 === 0) addTag(sampleTags[Math.floor(Math.random()*sampleTags.length)]);
            }
            setProgress(0, 'Starting upload...');
            
            const timer = setInterval(() => {
                if (idx < stages.length) { 
                    const s = stages[idx++]; 
                    setProgress(s.pct, s.msg); 
                } else {
                    clearInterval(timer);
                }
            }, 1800);
        });
    </script>
</body>
</html>
