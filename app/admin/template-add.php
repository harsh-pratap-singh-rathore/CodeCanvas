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
                    $zipEntry = $zip->getNameIndex($i);  // FIXED: was $name (bug!)
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
                $stmt->execute([$templateName, $slug, $template_type, $folder_path, $status]); // use $templateName — safe
                
                // Detailed result message
                $keys = [];
                if (isset($scanResult['schema']['sections'])) {
                    foreach ($scanResult['schema']['sections'] as $sec) {
                        if (isset($sec['fields'])) {
                            foreach ($sec['fields'] as $f) {
                                if (isset($f['key'])) $keys[] = $f['key'];
                            }
                        }
                    }
                }
                $fieldsList = array_slice($keys, 0, 15);
                $fieldsStr = implode(', ', $fieldsList);
                if (count($keys) > 15) $fieldsStr .= '...';
                
                $_SESSION['message'] = '<strong>Template uploaded successfully!</strong><br>Found ' . $scanResult['total'] . ' editable fields: <small style="display:block;margin-top:4px;color:#666;">' . htmlspecialchars($fieldsStr) . '</small>';
                header("Location: " . BASE_URL . '/admin/templates.php');
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
        .upload-zone {
            border: 2px dashed #E5E5E5;
            border-radius: 12px;
            padding: 60px 40px;
            text-align: center;
            transition: all 0.2s ease;
            background: #FAFAFA;
            cursor: pointer;
            margin-bottom: 24px;
        }

        .upload-zone:hover {
            border-color: #000;
            background: #F0F0F0;
        }

        .upload-zone.active {
            border-color: #000;
            background: #F0F0F0;
        }

        .upload-icon {
            color: #999;
            margin-bottom: 24px;
        }

        .upload-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #111;
        }

        .upload-hint {
            color: #666;
            font-size: 14px;
        }

        .file-selected {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px;
            background: #E8F5E9;
            border: 1px solid #C8E6C9;
            border-radius: 8px;
            color: #2E7D32;
            font-weight: 500;
            margin-bottom: 24px;
        }

        /* Form Layout */
        .form-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 32px;
        }

        @keyframes pubPop {
            from { opacity:0; transform:scale(0.85) translateY(24px); }
            to   { opacity:1; transform:scale(1) translateY(0); }
        }
        @keyframes rocketFloat {
            0%,100% { transform:translateY(0); }
            50%     { transform:translateY(-10px); }
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
                            <svg class="nav-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="templates.php" class="nav-link active">
                            <svg class="nav-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                            Templates
                        </a>
                    </li>
                     <!-- Logout -->
                     <li class="nav-item" style="margin-top: 24px; border-top: 1px solid #f5f5f5; padding-top: 24px;">
                         <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-link">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
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
                <div class="search-bar"></div>
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

            <div class="content-container">
                <div class="mb-4" style="margin-bottom: 24px;">
                    <a href="templates.php" style="color: #666; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 4px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        Back to Templates
                    </a>
                </div>

                <div class="section-header">
                    <div>
                        <h1 class="page-title">Add New Template</h1>
                        <p class="page-subtitle">Upload your HTML template file</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div style="background: #FFEBEE; color: #C62828; padding: 12px; border-radius: 6px; margin-bottom: 24px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="form-card">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        
                        <!-- Drag & Drop Zone -->
                        <div class="upload-zone" id="drop-zone">
                            <div style="margin-bottom: 24px;">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                            </div>
                            <div class="upload-text">Drag & drop HTML or ZIP file here</div>
                            <div class="upload-hint">or click to browse from your computer</div>
                            <input type="file" name="template_file" id="template_file" accept=".html,.zip" style="display: none;" required onchange="handleFileSelect(this)">
                        </div>

                        <!-- Selected File Display -->
                        <div id="file-selected" class="file-selected">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            <span id="file-name">filename.html</span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;">
                            <div class="form-group" style="margin-bottom: 24px;">
                                <label style="display: block; font-weight: 500; margin-bottom: 8px;">Template Name</label>
                                <input type="text" name="name" class="form-input" required placeholder="e.g. Modern Minimal" style="width: 100%; padding: 12px; border: 1px solid #e5e5e5; border-radius: 6px;">
                            </div>

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label style="display: block; font-weight: 500; margin-bottom: 8px;">Category (Where to upload)</label>
                                <select name="category" required style="width: 100%; padding: 12px; border: 1px solid #e5e5e5; border-radius: 6px; background: #fff;">
                                    <option value="developer">Developer</option>
                                    <option value="business">Business</option>
                                    <option value="shop">Shop</option>
                                    <option value="normal" selected>Normal/Personal</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label style="display: block; font-weight: 500; margin-bottom: 8px;">Type</label>
                                <select name="template_type" required style="width: 100%; padding: 12px; border: 1px solid #e5e5e5; border-radius: 6px; background: #fff;">
                                    <option value="personal">Personal</option>
                                    <option value="portfolio" selected>Portfolio</option>
                                    <option value="business">Business</option>
                                    <option value="e-commerce">E-Commerce</option>
                                </select>
                            </div>
                        </div>

                        <!-- Specification Hint -->
                        <div style="background: #E8F5E9; border: 1px solid #C8E6C9; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                            <h4 style="color: #2E7D32; font-size: 14px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                New Scanning System Active
                            </h4>
                            <p style="font-size: 13px; color: #1B5E20; line-height: 1.5;">
                                The system now supports <strong>data-edit</strong> attributes for richer control (images, URLs, colors). 
                                Legacy <code>{{key}}</code> placeholders are still detected. ZIP uploads are now fully supported.
                            </p>
                        </div>

                        <div style="text-align: right; margin-top: 16px; margin-bottom: 24px;">
                            <button type="submit" class="btn" style="padding: 12px 32px; font-size: 15px;">Upload & Auto-Scan Template</button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <!-- ── Fullscreen Scan Progress Overlay ────────────────── -->
    <div id="editor-pub-overlay" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.6); backdrop-filter:blur(8px); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:20px; padding:40px 48px; width:440px; max-width:92vw; box-shadow:0 32px 80px rgba(0,0,0,0.25); text-align:center; animation:pubPop .35s cubic-bezier(0.34,1.56,0.64,1);">
            <span id="epo-rocket" style="font-size:52px; display:block; margin-bottom:8px; animation:rocketFloat 1.6s ease-in-out infinite;">🤖</span>
            <div id="epo-title" style="font-size:21px; font-weight:800; color:#0F0F0F; margin-bottom:4px;">Scanning Template</div>
            <div id="epo-step" style="font-size:13px; color:#6B6B6B; margin-bottom:24px; min-height:20px;">AI is parsing the HTML layout...</div>
            <div style="width:100%; height:6px; background:#f0f0f0; border-radius:99px; overflow:hidden; margin-bottom:10px;">
                <div id="epo-fill" style="height:100%; width:0%; background:linear-gradient(90deg,#0F0F0F,#555); border-radius:99px; transition:width .45s cubic-bezier(0.4,0,0.2,1);"></div>
            </div>
            <div id="epo-pct" style="font-size:12px; color:#999; font-weight:600;">0%</div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        // UI Elements
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('template_file');
        const fileSelected = document.getElementById('file-selected');
        const fileNameDisp = document.getElementById('file-name');
        
        // Drag & Drop / Click Logic
        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('active');
        });

        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('active'));

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('active');
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect(fileInput.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) handleFileSelect(e.target.files[0]);
        });

        function handleFileSelect(file) {
            if (!file) return;

            // UI Update
            dropZone.style.display = 'none';
            fileSelected.style.display = 'flex';
            fileNameDisp.textContent = file.name;
        }

        const style = document.createElement('style');
        style.textContent = `
            #drop-zone.active { border-color: #000; background: #F5F5F5; }
        `;
        document.head.appendChild(style);

        // Form Interception for the Modal
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
            let idx = 0;
            function setProgress(pct, msg) {
                fill.style.width  = pct + '%';
                pctEl.textContent = Math.round(pct) + '%';
                if (msg) stepEl.textContent = msg;
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
