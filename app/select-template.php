<?php
/**
 * SELECT TEMPLATE — Step 2 of Template Selection
 * Flow: Category Selection → Template List → Modal (Preview / Edit) → Editor
 *
 * Reads ?category=developer (or business/shop/normal)
 * Scans templates/{category}/ for code.html files
 * Shows a template card per template found
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';
require_once APP_ROOT . '/app/core/TemplatePreview.php';


/* ── POST: Create project & return redirect URL ──────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $input    = json_decode(file_get_contents('php://input'), true);
        $name     = trim($input['project_name'] ?? '');
        $folder   = trim($input['folder'] ?? '');
        $category = trim($input['category'] ?? '');

        if (!$name)     throw new Exception('Project name is required.');
        if (!$category) throw new Exception('Category is required.');

        // ── Flexible Template Lookup ─────────────────────────
        // 1. Try exact folder match first (best)
        // 2. Fallback to category-based lookup if folder is missing
        $tpl = null;
        if ($folder) {
            $stmt = $pdo->prepare("SELECT id, folder_path FROM templates WHERE folder_path = ? AND status = 'active'");
            $stmt->execute([$folder]);
            $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$tpl) {
            $stmt = $pdo->prepare(
                "SELECT id, folder_path FROM templates
                 WHERE folder_path LIKE ? AND status = 'active'
                 ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute(['templates/' . $category . '/%']);
            $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$tpl) throw new Exception('No active template found for category: ' . htmlspecialchars($category));

        $stmt = $pdo->prepare(
            "INSERT INTO projects (user_id, template_id, project_name, status)
             VALUES (?, ?, ?, 'draft')"
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

/* ── GET: Render page ────────────────────────────────────────── */
$allowed   = ['developer', 'business', 'shop', 'normal'];
$category  = trim($_GET['category'] ?? '');

if (!in_array($category, $allowed, true)) {
    header("Location: " . BASE_URL . '/app/select-category.php');
exit;
}

$categoryLabel = ucfirst($category);

// Fetch templates from database based on the category folder path
$stmt = $pdo->prepare("SELECT * FROM templates WHERE status = 'active' AND folder_path LIKE ? ORDER BY id DESC");
$stmt->execute(['templates/' . $category . '/%']);
$dbTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$templates = [];
foreach ($dbTemplates as $tpl) {
    // Basic meta info
    $variantName = basename($tpl['folder_path']);
    
    // Resolve entry point (previewPath)
    $entryPoint = 'index.html';
    $absPath = __DIR__ . '/../' . rtrim($tpl['folder_path'], '/');
    if (file_exists($absPath . '/code.html')) {
        $entryPoint = 'code.html';
    } else {
        // Search nested
        $nested = glob($absPath . '/*/{code.html,index.html,index.htm}', GLOB_BRACE);
        if (!empty($nested)) $entryPoint = str_replace($absPath . '/', '', $nested[0]);
    }

    $templates[] = [
        'id'          => $tpl['id'],
        'folder_path' => $tpl['folder_path'],
        'previewPath' => BASE_URL . '/' . rtrim($tpl['folder_path'], '/') . '/' . $entryPoint,
        'name'        => $tpl['name'],
        'meta'        => ucfirst($category) . ' · ' . $variantName,
        'slug'        => $tpl['slug'] ?? ''
    ];
}

// Fallback: If DB is empty, scan filesystem for standard templates
if (empty($templates)) {
    $baseDir = __DIR__ . '/../templates/' . $category . '/';
    if (is_dir($baseDir)) {
        $subDirs = glob($baseDir . '*', GLOB_ONLYDIR);
        foreach ((array)$subDirs as $subDir) {
            $htmlFile = null;
            foreach (['code.html', 'index.html', 'index.htm'] as $c) {
                if (file_exists($subDir . '/' . $c)) { $htmlFile = $c; break; }
            }
            // Also check 1 level deep
            if (!$htmlFile) {
                $nested = glob($subDir . '/*/{code.html,index.html,index.htm}', GLOB_BRACE);
                if (!empty($nested)) $htmlFile = str_replace($subDir . '/', '', $nested[0]);
            }

            if ($htmlFile) {
                $variantName = basename($subDir);
                $templates[] = [
                    'folder_path' => 'templates/' . $category . '/' . $variantName,
                    'previewPath' => BASE_URL . '/templates/' . $category . '/' . $variantName . '/' . $htmlFile,
                    'name'        => $categoryLabel . ' — ' . ucfirst($variantName),
                    'meta'        => ucfirst($category) . ' · ' . $variantName,
                ];
            }
        }
    }
}

$user = [
    'name'     => $_SESSION['user_name'],
    'email'    => $_SESSION['user_email'],
    'initials' => strtoupper(substr($_SESSION['user_name'], 0, 1))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($categoryLabel) ?> Templates — CodeCanvas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css">
    <style>
        /* ── Page Layout ─────────────────────────────────────── */
        .np-wrap {
            max-width: 860px;
            margin: 0 auto;
            padding: 64px 24px 80px;
        }
        .np-heading {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .np-sub {
            color: #666;
            font-size: 14px;
            margin-bottom: 48px;
        }
        .np-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #666;
            text-decoration: none;
            margin-bottom: 32px;
            transition: color .15s;
        }
        .np-back:hover { color: #000; }
        .np-back-arrow { font-size: 16px; }

        /* ── Template Grid ───────────────────────────────────── */
        .tpl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }

        .tpl-card {
            border: 1px solid #E5E5E5;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
            background: #fff;
        }
        .tpl-card:hover {
            border-color: #000;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
        }

        .tpl-thumb {
            width: 100%;
            aspect-ratio: 16/9;
            background: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            border-bottom: 1px solid #F0F0F0;
        }
        
        /* Category Specific Visuals */
        .tpl-visual {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .visual-developer { background: #EEF2FF; color: #4F46E5; }
        .visual-business  { background: #F0FDF4; color: #16A34A; }
        .visual-shop      { background: #FFF7ED; color: #EA580C; }
        .visual-normal    { background: #FDF2F8; color: #DB2777; }

        .tpl-thumb-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.4);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            opacity: 0;
            transition: opacity .2s;
        }
        .tpl-card:hover .tpl-thumb-overlay { opacity: 1; }

        .tpl-info {
            padding: 16px;
            border-top: 1px solid #F0F0F0;
        }
        .tpl-name {
            font-size: 14px;
            font-weight: 600;
            color: #111;
            margin-bottom: 2px;
        }
        .tpl-meta {
            font-size: 12px;
            color: #888;
        }
        .tpl-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            background: #000;
            color: #fff;
            padding: 2px 7px;
            border-radius: 3px;
            margin-left: 6px;
            vertical-align: middle;
        }

        /* ── Empty State ─────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 64px 24px;
            color: #888;
        }
        .empty-state h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .empty-state p {
            font-size: 13px;
        }

        /* ── Modal ───────────────────────────────────────────── */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-backdrop.open { display: flex; }

        .modal {
            background: #fff;
            border-radius: 10px;
            width: 100%;
            max-width: 440px;
            padding: 32px;
            box-shadow: 0 24px 64px rgba(0,0,0,.18);
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 16px; right: 16px;
            background: none; border: none;
            font-size: 20px; cursor: pointer;
            color: #999; line-height: 1;
        }
        .modal-close:hover { color: #000; }

        .modal-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .modal-sub {
            font-size: 13px;
            color: #666;
            margin-bottom: 24px;
        }

        .modal-name-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #D0D0D0;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            outline: none;
            transition: border-color .15s;
        }
        .modal-name-input:focus { border-color: #000; }

        .modal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .modal-btn {
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #000;
            text-align: center;
            transition: background .15s, color .15s;
        }
        .modal-btn-outline {
            background: #fff;
            color: #000;
        }
        .modal-btn-outline:hover { background: #F5F5F5; }
        .modal-btn-primary {
            background: #000;
            color: #fff;
        }
        .modal-btn-primary:hover { background: #222; }

        .modal-spinner {
            display: none;
            text-align: center;
            padding: 12px 0;
            font-size: 13px;
            color: #666;
        }
        .modal-error {
            display: none;
            color: #c00;
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
</head>
<body class="dashboard-layout">

    <!-- Top Bar -->
    <header class="dashboard-header">
        <div class="dashboard-header-content">
            <a href="<?= BASE_URL ?>/dashboard.php" class="logo">CodeCanvas</a>
            <div class="user-menu">
                <div class="user-avatar" data-dropdown="user">
                    <span class="avatar-circle"><?= htmlspecialchars($user['initials']) ?></span>
                    <div class="dropdown dropdown-right">
                        <div class="dropdown-item" style="border-bottom:1px solid #E5E5E5;padding-bottom:12px;margin-bottom:8px;">
                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                            <span style="font-size:12px;color:#6B6B6B;"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <a href="<?= BASE_URL ?>/profile.php" class="dropdown-item"><strong>Profile</strong></a>
                        <div class="dropdown-divider"></div>
                        <a href="<?= BASE_URL ?>/auth/logout.php" class="dropdown-item"><strong>Logout</strong></a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main -->
    <div class="np-wrap">
        <a class="np-back" href="<?= BASE_URL ?>/select-category.php">
            <span class="np-back-arrow">&#8592;</span> All Categories
        </a>

        <h1 class="np-heading"><?= htmlspecialchars($categoryLabel) ?> Templates</h1>
        <p class="np-sub">Click a template to preview or start editing.</p>

        <?php if (empty($templates)): ?>
        <div class="empty-state">
            <h3>No templates yet</h3>
            <p>No templates found in <code>templates/<?= htmlspecialchars($category) ?>/</code>.</p>
        </div>
        <?php else: ?>
        <div class="tpl-grid">
            <?php 
            $icons = [
                'developer' => '👨‍💻',
                'business'  => '💼',
                'shop'      => '🛍️',
                'normal'    => '✍️'
            ];
            $icon = $icons[$category] ?? '📄';
            foreach ($templates as $i => $tpl): 
            ?>
            <div class="tpl-card" onclick="openModal(<?= $i ?>)">
                <div class="tpl-thumb">
                    <?php echo TemplatePreview::getPreviewHtml($tpl); ?>
                    <div class="tpl-thumb-overlay">SELECT & EDIT</div>
                </div>

                <div class="tpl-info">
                    <div class="tpl-name">
                        <?= htmlspecialchars($tpl['name']) ?>
                        <span class="tpl-badge">PREMIUM</span>
                    </div>
                    <div class="tpl-meta"><?= htmlspecialchars($tpl['meta']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal: Preview or Edit -->
    <div class="modal-backdrop" id="modal-backdrop">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <button class="modal-close" onclick="closeModal()" aria-label="Close">&times;</button>

            <div class="modal-title" id="modal-title">Template</div>
            <div class="modal-sub">Enter a project name, then choose to preview or start editing.</div>

            <input
                type="text"
                class="modal-name-input"
                id="modal-project-name"
                placeholder="e.g. My Portfolio"
                maxlength="120"
                autocomplete="off"
            >

            <div class="modal-actions">
                <button class="modal-btn modal-btn-outline" id="btn-preview" onclick="handleAction('preview')">
                    &#128065; Preview Template
                </button>
                <button class="modal-btn modal-btn-primary" id="btn-edit" onclick="handleAction('edit')">
                    &#9999; Edit Template
                </button>
            </div>

            <div class="modal-spinner" id="modal-spinner">Creating project…</div>
            <div class="modal-error" id="modal-error"></div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/navigation.js"></script>
    <script src="<?= BASE_URL ?>/public/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const CATEGORY   = <?= json_encode($category) ?>;
        const TEMPLATES  = <?= json_encode(array_values($templates)) ?>;

        let activeIndex  = 0;

        function openModal(index) {
            activeIndex = index;
            const tpl = TEMPLATES[index];
            document.getElementById('modal-title').textContent = tpl.name;
            document.getElementById('modal-backdrop').classList.add('open');
            setTimeout(() => document.getElementById('modal-project-name').focus(), 80);

            // ── Nav: store selected template in sessionStorage
            NavManager.setTemplate(tpl.name, tpl.previewPath);
        }

        function closeModal() {
            document.getElementById('modal-backdrop').classList.remove('open');
            document.getElementById('modal-error').style.display = 'none';
            document.getElementById('modal-spinner').style.display = 'none';
            document.getElementById('modal-project-name').value = '';
            setModalBusy(false);
        }

        // Close on backdrop click
        document.getElementById('modal-backdrop').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Close on Escape
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

        function setModalBusy(busy) {
            document.getElementById('btn-preview').disabled = busy;
            document.getElementById('btn-edit').disabled = busy;
            document.getElementById('modal-spinner').style.display = busy ? 'block' : 'none';
        }

        async function handleAction(action) {
            const tpl    = TEMPLATES[activeIndex];
            const nameEl = document.getElementById('modal-project-name');
            const errEl  = document.getElementById('modal-error');
            const name   = nameEl.value.trim();

            errEl.style.display = 'none';

            // Preview: open the template HTML directly in a new tab
            if (action === 'preview') {
                // ── Nav: store preview path so preview page can restore on refresh
                NavManager.setPreview(tpl.previewPath);
                window.open(tpl.previewPath, '_blank');
                return;
            }

            // Edit: require a name, create project, redirect to editor
            if (!name) {
                errEl.textContent = 'Please enter a project name first.';
                errEl.style.display = 'block';
                nameEl.focus();
                return;
            }

            setModalBusy(true);
            try {
                const res = await fetch(BASE_URL + '/select-template.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_name: name, category: CATEGORY, folder: tpl.folder_path })

                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errEl.textContent = data.message || 'Something went wrong.';
                    errEl.style.display = 'block';
                    setModalBusy(false);
                }
            } catch (e) {
                errEl.textContent = 'Network error. Please try again.';
                errEl.style.display = 'block';
                setModalBusy(false);
            }
        }

        // Allow Enter key to trigger Edit
        document.getElementById('modal-project-name').addEventListener('keydown', e => {
            if (e.key === 'Enter') handleAction('edit');
        });

        // ── Nav: record page state + install back guard
        NavManager.pushState('template', { category: CATEGORY });
        NavManager.installBackGuard('template');
        NavManager.setCookie('lastVisitedPage', 'select-template');
    </script>
</body>
</html>
