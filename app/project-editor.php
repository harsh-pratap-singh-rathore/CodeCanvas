<?php
/**
 * PROJECT EDITOR — Schema-Based Template Customization
 * Loads schema.json, generates dynamic form, live preview via postMessage
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId    = $_SESSION['user_id'];

// Load project (must belong to current user)
try {
    $stmt = $pdo->prepare("
        SELECT p.*, t.name AS template_name, t.folder_path, t.slug AS template_slug
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
    die('Error loading project: ' . $e->getMessage());
}

// Resolve template folder & entry file
$folderPath = rtrim($project['folder_path'] ?? 'templates/developer', '/');
$absFolder  = APP_ROOT . '/' . $folderPath;

$entryPoint = 'code.html';
foreach (['code.html', 'index.html', 'index.htm'] as $candidate) {
    if (file_exists($absFolder . '/' . $candidate)) {
        $entryPoint = $candidate;
        break;
    }
}
// Check one level deep (e.g. modern-dev-portfolio/portfolio-dev/index.html)
if (!file_exists($absFolder . '/' . $entryPoint)) {
    $nested = glob($absFolder . '/*/{code.html,index.html,index.htm}', GLOB_BRACE);
    if (!empty($nested)) {
        $entryPoint = str_replace($absFolder . '/', '', $nested[0]);
    }
}

$templateUrl = BASE_URL . '/' . $folderPath . '/' . $entryPoint;

// Schema
$schemaPath   = $absFolder . '/schema.json';
$hasSchema    = file_exists($schemaPath);
$schemaUrl    = $hasSchema ? BASE_URL . '/' . $folderPath . '/schema.json' : null;
$virtualSchema = null;

// Saved user data
$savedData = [];
if (!empty($project['user_data'])) {
    $savedData = json_decode($project['user_data'], true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['project_name']) ?> — Editor · CodeCanvas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }
        body { display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #0F0F0F; background: #fff; font-size: 14px; }

        .editor-topbar { height: 48px; background: #fff; border-bottom: 1px solid #E5E5E5; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; flex-shrink: 0; z-index: 100; }
        .editor-topbar-brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .editor-topbar-logo  { font-size: 15px; font-weight: 700; color: #0F0F0F; text-decoration: none; }
        .editor-topbar-sep   { width: 1px; height: 18px; background: #E5E5E5; }
        .editor-topbar-title { font-size: 13px; font-weight: 500; color: #0F0F0F; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .editor-topbar-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

        .save-indicator { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #6B6B6B; }
        .save-dot { width: 6px; height: 6px; border-radius: 50%; background: #22c55e; }
        .save-dot.unsaved { background: #f59e0b; }

        .editor-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000; align-items: center; justify-content: center; }
        .editor-modal-backdrop.open { display: flex; }
        .editor-modal { background: #fff; border-radius: 8px; padding: 28px; width: 100%; max-width: 400px; box-shadow: 0 16px 48px rgba(0,0,0,0.15); }
        .editor-modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
        .editor-modal p  { font-size: 13px; color: #6B6B6B; margin-bottom: 16px; }
        .editor-modal input[type="text"] {
            padding: 10px;
            border: 1px solid #E5E5E5;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 20px;
            outline: none;
        }
        .editor-modal input:focus { border-color: #000; }
        .editor-modal-actions { display: flex; justify-content: flex-end; gap: 10px; }

        /* Editor Body */
        .editor-body {
            display: grid;
            grid-template-columns: 380px 1fr;
            flex: 1;
            overflow: hidden;
        }

        /* Sidebar */
        .editor-sidebar {
            background: #fff;
            border-right: 1px solid #E5E5E5;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid #E5E5E5;
            flex-shrink: 0;
        }

        .sidebar-header h2 {
            font-size: 13px;
            font-weight: 600;
            color: #0F0F0F;
            margin: 0 0 2px 0;
        }

        .sidebar-header p {
            font-size: 11px;
            color: #6B6B6B;
            margin: 0;
        }

        .sidebar-form {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        /* Section Groups */
        .sec-group {
            border: 1px solid #E5E5E5;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .sec-group-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            cursor: pointer;
            background: #FAFAFA;
            user-select: none;
            transition: background 0.15s;
        }

        .sec-group-head:hover { background: #F5F5F5; }

        .sec-group-title {
            font-size: 11px;
            font-weight: 600;
            color: #0F0F0F;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .sec-chevron {
            color: #6B6B6B;
            font-size: 16px;
            transition: transform 0.2s;
        }

        .sec-group.open .sec-chevron { transform: rotate(180deg); }

        .sec-group-body {
            display: none;
            padding: 14px;
            border-top: 1px solid #E5E5E5;
            flex-direction: column;
            gap: 14px;
            background: #fff;
        }

        .sec-group.open .sec-group-body { display: flex; }

        /* Field Groups */
        .f-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .f-label {
            font-size: 11px;
            font-weight: 500;
            color: #0F0F0F;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .f-type-badge {
            font-size: 9px;
            padding: 1px 5px;
            border-radius: 3px;
            background: #F5F5F5;
            color: #6B6B6B;
            border: 1px solid #E5E5E5;
            font-weight: 400;
            letter-spacing: 0;
        }

        .f-hint {
            font-size: 10px;
            color: #6B6B6B;
            line-height: 1.4;
        }

        /* Inputs */
        .f-group input[type="text"],
        .f-group input[type="email"],
        .f-group textarea {
            width: 100%;
            padding: 8px 10px;
            font-size: 13px;
            font-family: inherit;
            border: 1px solid #E5E5E5;
            border-radius: 4px;
            background: #fff;
            color: #0F0F0F;
            transition: border-color 0.15s;
            resize: vertical;
        }

        .f-group input:focus,
        .f-group textarea:focus {
            outline: none;
            border-color: #0F0F0F;
        }

        .f-group input::placeholder,
        .f-group textarea::placeholder {
            color: #6B6B6B;
            opacity: 0.5;
        }

        /* Image Upload */
        .img-upload-wrap {
            position: relative;
            border: 2px dashed #E5E5E5;
            border-radius: 4px;
            padding: 14px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
            overflow: hidden;
        }

        .img-upload-wrap:hover {
            border-color: #0F0F0F;
            background: #FAFAFA;
        }

        .img-upload-wrap input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
            padding: 0;
            border: none;
            background: none;
        }

        .img-upload-icon {
            font-size: 20px;
            color: #6B6B6B;
            display: block;
            margin-bottom: 4px;
        }

        /* Manual Update Trigger */
        .f-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .f-apply-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #4b5563;
            border-radius: 4px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            opacity: 0;
            pointer-events: none;
        }

        .f-input-wrapper:focus-within .f-apply-btn,
        .f-input-wrapper:hover .f-apply-btn {
            opacity: 1;
            pointer-events: auto;
        }

        .f-apply-btn:hover {
            background: #000;
            color: #fff;
            border-color: #000;
        }

        .f-apply-btn .material-icons {
            font-size: 16px;
        }

        .f-input-wrapper input,
        .f-input-wrapper textarea {
            padding-right: 35px !important;
        }

        .img-upload-text {
            font-size: 10px;
            color: #6B6B6B;
        }

        .img-preview {
            width: 100%;
            max-height: 90px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #E5E5E5;
            margin-top: 6px;
            display: none;
        }

        /* Array Fields */
        .array-controls {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .array-items-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .array-item-row {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #FAFAFA;
            border: 1px solid #E5E5E5;
            border-radius: 4px;
            padding: 5px 8px;
        }

        .array-item-row input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 2px 4px;
            font-size: 12px;
            font-family: inherit;
            color: #0F0F0F;
            min-width: 0;
        }

        .array-item-row input:focus { outline: none; }

        .array-item-row select {
            background: #fff;
            border: 1px solid #E5E5E5;
            border-radius: 3px;
            color: #0F0F0F;
            font-size: 10px;
            padding: 2px 4px;
            width: 90px;
        }

        .icon-preview {
            font-size: 14px;
            color: #6B6B6B;
            width: 20px;
            text-align: center;
        }

        .remove-btn {
            background: none;
            border: none;
            color: #6B6B6B;
            cursor: pointer;
            padding: 2px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            font-size: 15px;
            transition: color 0.15s;
            flex-shrink: 0;
        }

        .remove-btn:hover { color: #ef4444; }

        .add-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            border: 1px dashed #E5E5E5;
            border-radius: 4px;
            color: #6B6B6B;
            cursor: pointer;
            padding: 7px 12px;
            font-size: 11px;
            font-family: inherit;
            width: 100%;
            transition: all 0.15s;
        }

        .add-btn:hover {
            border-color: #0F0F0F;
            color: #0F0F0F;
        }

        .add-btn .material-icons { font-size: 14px; }

        /* Project Items */
        .project-form-item {
            background: #FAFAFA;
            border: 1px solid #E5E5E5;
            border-radius: 4px;
            overflow: hidden;
        }

        .project-form-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px;
            cursor: pointer;
            user-select: none;
        }

        .project-form-header:hover { background: #F5F5F5; }

        .project-form-title {
            font-size: 11px;
            font-weight: 500;
            color: #0F0F0F;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .project-form-title .material-icons {
            font-size: 13px;
            color: #6B6B6B;
        }

        .project-form-body {
            display: none;
            padding: 12px;
            flex-direction: column;
            gap: 10px;
            border-top: 1px solid #E5E5E5;
            background: #fff;
        }

        .project-form-item.open .project-form-body { display: flex; }

        .project-thumb-preview {
            width: 100%;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #E5E5E5;
            display: none;
        }

        /* Preview Pane */
        .editor-preview {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #F5F5F5;
        }

        .preview-toolbar {
            height: 44px;
            background: #fff;
            border-bottom: 1px solid #E5E5E5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            flex-shrink: 0;
        }

        .preview-label {
            font-size: 11px;
            font-weight: 500;
            color: #6B6B6B;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .preview-label .material-icons { font-size: 14px; }

        .preview-controls {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .live-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            color: #6B6B6B;
        }

        .live-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22c55e;
            animation: pulse-dot 2s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .vp-btn {
            background: transparent;
            border: 1px solid #E5E5E5;
            border-radius: 4px;
            color: #6B6B6B;
            cursor: pointer;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            transition: all 0.15s;
            font-size: 14px;
        }

        .vp-btn:hover,
        .vp-btn.active {
            border-color: #0F0F0F;
            color: #0F0F0F;
        }

        .icon-btn {
            background: transparent;
            border: 1px solid #E5E5E5;
            border-radius: 4px;
            color: #6B6B6B;
            cursor: pointer;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            font-size: 14px;
            transition: all 0.15s;
        }

        .icon-btn:hover {
            border-color: #0F0F0F;
            color: #0F0F0F;
        }

        .preview-wrap {
            flex: 1;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            background: #F0F0F0;
            transition: padding 0.3s;
        }

        .preview-wrap.mobile  { padding: 20px; }
        .preview-wrap.tablet  { padding: 20px 40px; }

        #preview-iframe {
            width: 100%;
            height: 100%;
            border: none;
            transition: all 0.3s;
        }

        .preview-wrap.mobile #preview-iframe {
            max-width: 390px;
            height: calc(100% - 40px);
            border-radius: 20px;
            border: 2px solid #E5E5E5;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        }

        .preview-wrap.tablet #preview-iframe {
            max-width: 768px;
            height: calc(100% - 40px);
            border-radius: 8px;
            border: 2px solid #E5E5E5;
        }

        /* Loading State */
        .form-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            gap: 12px;
            color: #6B6B6B;
            font-size: 12px;
        }

        .form-loading .spinner {
            width: 24px;
            height: 24px;
            border: 2px solid #E5E5E5;
            border-top-color: #0F0F0F;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        /* AI Writer Styles */
        .f-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 2px;
        }
        .ai-writer-btn {
            background: #fdf2f8; /* Soft pink/purple tint for AI */
            color: #be185d;
            border: 1px solid #fbcfe8;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            margin-bottom: 5px;
        }
        .ai-writer-btn:hover {
            background: #fbcfe8;
            transform: scale(1.05);
        }
        .ai-writer-btn .material-icons {
            font-size: 12px;
        }
        .ai-writer-btn.loading {
            opacity: 0.6;
            cursor: wait;
        }
        .ai-writer-btn.loading .material-icons {
            animation: spin 1s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s;
            font-family: inherit;
            text-decoration: none;
        }

        .btn .material-icons { font-size: 15px; }

        .btn-primary {
            background: #0F0F0F;
            color: #fff;
            border-color: #0F0F0F;
        }

        .btn-primary:hover { background: #333; }

        .btn-secondary {
            background: #fff;
            color: #0F0F0F;
            border-color: #E5E5E5;
        }

        .btn-secondary:hover { background: #FAFAFA; }

        .btn-ghost {
            background: transparent;
            color: #6B6B6B;
            border-color: transparent;
        }

        .btn-ghost:hover { color: #0F0F0F; }

        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* Bottom Bar */
        .editor-bottombar {
            height: 48px;
            background: #fff;
            border-top: 1px solid #E5E5E5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            flex-shrink: 0;
        }

        .bottombar-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bottombar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .update-status {
            font-size: 11px;
            color: #6B6B6B;
        }

        /* Flash animation */
        @keyframes flash-update {
            0%   { background: rgba(0,0,0,0.06); }
            100% { background: transparent; }
        }

        .field-updated { animation: flash-update 0.5s ease; }

        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 9999;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            animation: toast-in 0.3s ease;
        }

        @keyframes toast-in {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .toast.success { background: #0F0F0F; color: #fff; }
        .toast.error   { background: #ef4444; color: #fff; }
        .toast.info    { background: #fff; color: #0F0F0F; border: 1px solid #E5E5E5; }

        .toast .material-icons { font-size: 16px; }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <header class="editor-topbar">
        <div class="editor-topbar-brand">
            <a href="<?= BASE_URL ?>/dashboard.php" class="editor-topbar-logo">CodeCanvas</a>
            <div class="editor-topbar-sep"></div>
            <div class="editor-topbar-title" id="display-project-name" onclick="openRenameModal()" title="Rename Project" style="cursor:pointer; display:flex; align-items:center; gap:8px; padding: 4px 8px; border-radius: 4px; transition: background 0.15s;">
                <span><?= htmlspecialchars($project['project_name']) ?></span>
                <span class="material-icons" style="font-size:16px; color:#999;">edit_note</span>
            </div>
            <style>#display-project-name:hover { background: #F0F0F0; }</style>
        </div>
        <div class="editor-topbar-actions">
            <div class="save-indicator">
                <div class="save-dot" id="save-dot"></div>
                <span id="save-status">All changes saved</span>
            </div>
            <button class="btn btn-secondary btn-sm" id="btn-reset">
                <span class="material-icons">restart_alt</span> Reset
            </button>
            <button class="btn btn-secondary btn-sm" id="btn-save-draft">
                <span class="material-icons">save</span> Save Draft
            </button>
            <button class="btn btn-primary btn-sm" id="btn-generate">
                <span class="material-icons">download</span> Download
            </button>
            <a href="<?= BASE_URL ?>/project-settings.php?id=<?= $projectId ?>" class="btn btn-secondary btn-sm" title="Project Settings">
                <span class="material-icons">settings</span> Settings
            </a>
            <?php 
            $pubStatus = $project['publish_status'] ?? 'draft';
            if (($pubStatus === 'deployed' || $pubStatus === 'published') && !empty($project['live_url'])): ?>
                <a href="<?= htmlspecialchars($project['live_url'] ?? '') ?>" target="_blank" class="btn btn-secondary btn-sm" style="color:#16a34a; border-color:#e1f9e6; background:#f0fdf4;" title="Visit Live Site">
                    <span class="material-icons" style="font-size:16px;">open_in_new</span> View Live Site
                </a>
                <button class="btn btn-primary btn-sm" id="publish-btn" style="background:#16a34a; border-color:#16a34a;">
                    <span class="material-icons">published_with_changes</span> Re-publish
                </button>
            <?php else: ?>
                <button class="btn btn-primary btn-sm" id="publish-btn">
                    <span class="material-icons">public</span> Publish
                </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- Editor Body -->
    <div class="editor-body">

        <!-- Sidebar: Form -->
        <aside class="editor-sidebar">
            <div class="sidebar-header">
                <h2>Edit Your Portfolio</h2>
                <p>Changes update the preview in real-time</p>
            </div>
            <div class="sidebar-form" id="form-sections">
                <div class="form-loading">
                    <div class="spinner"></div>
                    Loading form fields...
                </div>
            </div>
        </aside>

        <!-- Preview Pane -->
        <div class="editor-preview">
            <div class="preview-toolbar">
                <span class="preview-label">
                    <span class="material-icons">preview</span>
                    Live Preview
                </span>
                <div class="preview-controls">
                    <div class="live-badge">
                        <div class="live-dot"></div>
                        <span id="preview-status">Live Preview</span>
                    </div>
                    <?php if (($project['publish_status'] ?? '') === 'deployed' || ($project['publish_status'] ?? '') === 'published'): ?>
                        <a href="<?= htmlspecialchars($project['live_url'] ?? '#') ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:11px; height:28px; padding:0 10px; border-radius:4px; margin-left:12px; display:flex; align-items:center; gap:5px; color:#16a34a; border-color:#dcfce7; background:#f0fdf4; text-decoration:none;">
                            <span class="material-icons" style="font-size:14px;">rocket_launch</span> Visit Site
                        </a>
                    <?php endif; ?>
                    <div style="display:flex; gap:4px; margin-left:8px;">
                        <button class="vp-btn active" data-vp="desktop" title="Desktop">
                            <span class="material-icons">desktop_windows</span>
                        </button>
                        <button class="vp-btn" data-vp="tablet" title="Tablet">
                            <span class="material-icons">tablet</span>
                        </button>
                        <button class="vp-btn" data-vp="mobile" title="Mobile">
                            <span class="material-icons">smartphone</span>
                        </button>
                    </div>
                    <button class="icon-btn" id="btn-refresh-preview" title="Refresh Preview">
                        <span class="material-icons">refresh</span>
                    </button>
                    <button class="icon-btn" id="btn-fullscreen" title="Fullscreen">
                        <span class="material-icons">open_in_full</span>
                    </button>
                </div>
            </div>
            <div class="preview-wrap" id="preview-wrap">
                <iframe id="preview-iframe" src="portfolio-preview.php?template=<?= urlencode($templateUrl) ?>" title="Portfolio Preview"></iframe>
            </div>
        </div>

    </div>

    <!-- Bottom Bar -->
    <div class="editor-bottombar">
        <div class="bottombar-left">
            <div class="live-badge">
                <div class="live-dot"></div>
                <span class="update-status" id="update-status">Ready</span>
            </div>
        </div>
        <div class="bottombar-right">
            <button class="btn btn-secondary btn-sm" id="btn-save-draft-bottom">
                <span class="material-icons">save</span> Save Draft
            </button>
            <button class="btn btn-primary btn-sm" id="btn-generate-bottom">
                <span class="material-icons">download</span> Download Portfolio
            </button>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Project Data -->
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const PROJECT_ID      = <?= json_encode($projectId) ?>;
        const SAVED_DATA      = <?= json_encode($savedData) ?>;
        const SCHEMA_URL      = <?= json_encode($schemaUrl) ?>;
        const VIRTUAL_SCHEMA  = <?= json_encode($virtualSchema) ?>;
        const TEMPLATE_URL    = <?= json_encode($templateUrl) ?>;
        const IS_SCHEMA_BASED = <?= json_encode($hasSchema) ?>;
        const PUBLISH_STATUS  = <?= json_encode($project['publish_status'] ?? 'draft') ?>;
        const CUSTOM_SLUG     = <?= json_encode($project['custom_slug'] ?? '') ?>;

        // Update Publish button label if already live
        (function() {
            const btn = document.getElementById('publish-btn');
            if (PUBLISH_STATUS === 'published' || PUBLISH_STATUS === 'deployed') {
                btn.innerHTML = '<span class="material-icons">published_with_changes</span> Re-publish';
                btn.style.background = '#16a34a';
                btn.style.borderColor = '#16a34a';
            }
        })();
    </script>

    <!-- Navigation State -->
    <script src="<?= BASE_URL ?>/public/assets/js/navigation.js"></script>
    <script>
        // ── Nav: run guard (redirects if PROJECT_ID is missing/invalid)
        NavManager.guardEditor();

        // ── Nav: persist project + template state to sessionStorage + cookies
        NavManager.setProject(PROJECT_ID);
        NavManager.setTemplate(<?= json_encode($project['template_name']) ?>, TEMPLATE_URL);

        // Derive category from folder path and store if not already set
        (function () {
            var folderParts = <?= json_encode($folderPath) ?>.split('/');
            // folder_path is like "templates/business" — second segment is category
            var cat = folderParts.length >= 2 ? folderParts[1] : '';
            if (cat && !NavManager.getCategory()) {
                NavManager.setCategory(cat);
            }
        }());

        // ── Nav: push history state + install back guard
        NavManager.pushState('editor', { projectId: PROJECT_ID });
        NavManager.installBackGuard('editor');
    </script>

    <!-- Editor Logic -->
    <!-- Rename Modal -->
    <div class="editor-modal-backdrop" id="rename-modal-backdrop">
        <div class="editor-modal">
            <h3>Rename Project</h3>
            <p>Enter a new name for your project.</p>
            <input type="text" id="rename-project-input" value="<?= htmlspecialchars($project['project_name']) ?>" maxlength="100">
            <div class="editor-modal-actions">
                <button class="btn btn-secondary btn-sm" onclick="closeRenameModal()">Cancel</button>
                <button class="btn btn-primary btn-sm" id="btn-rename-confirm" onclick="executeRename()">Save Name</button>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/project-editor.js?v=<?= time() ?>"></script>
    <script>
        function openRenameModal() {
            document.getElementById('rename-modal-backdrop').classList.add('open');
            setTimeout(() => document.getElementById('rename-project-input').select(), 100);
        }
        function closeRenameModal() {
            document.getElementById('rename-modal-backdrop').classList.remove('open');
        }
        async function executeRename() {
            const input = document.getElementById('rename-project-input');
            const name = input.value.trim();
            const btn = document.getElementById('btn-rename-confirm');

            if (!name) return;

            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const res = await fetch(BASE_URL + '/project-rename.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: <?= $projectId ?>, name })
                });
                const data = await res.json();
                if (data.success) {
                    document.querySelector('#display-project-name span').textContent = data.name;
                    closeRenameModal();
                    showToast('Project renamed successfully', 'success');
                } else {
                    showToast(data.message || 'Error renaming', 'error');
                }
            } catch (e) {
                showToast('Network error', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Name';
            }
        }
        // Handle backdrop click
        document.getElementById('rename-modal-backdrop').addEventListener('click', function(e) {
            if (e.target === this) closeRenameModal();
        });
        // Handle Enter key
        document.getElementById('rename-project-input').addEventListener('keydown', e => {
            if (e.key === 'Enter') executeRename();
        });
    </script>
<script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>

<!-- ── Publish Modal ──────────────────────────────────────── -->
<div class="editor-modal-backdrop" id="editor-publish-backdrop">
    <div class="editor-modal" style="max-width:440px; position:relative; overflow:hidden;">
        <h3 id="ep-modal-title">🚀 Deploy Your Portfolio</h3>
        <p>Choose a URL slug for your site. We'll build and host it instantly on Netlify.</p>
        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:11px; font-weight:600; color:#555; margin-bottom:8px; text-transform:uppercase; letter-spacing:.05em;">Site URL Slug</label>
            <div style="display:flex; align-items:center; border:1px solid #E5E5E5; border-radius:4px; overflow:hidden; background:#FAFAFA;">
                <span style="padding:10px 10px; font-size:12px; color:#6B6B6B; border-right:1px solid #E5E5E5; white-space:nowrap;">https://</span>
                <input type="text" id="editor-publish-slug" placeholder="my-awesome-portfolio"
                       style="flex:1; border:none; background:transparent; padding:10px; font-size:13px; outline:none; font-family:inherit;">
                <span style="padding:10px 10px; font-size:12px; color:#6B6B6B; border-left:1px solid #E5E5E5; white-space:nowrap;">.vercel.app</span>
            </div>
            <div id="editor-slug-feedback" style="margin-top:6px; font-size:12px; min-height:18px;"></div>
        </div>
        <div class="editor-modal-actions">
            <button class="btn btn-secondary btn-sm" onclick="closeEditorPublish()">Cancel</button>
            <button class="btn btn-primary btn-sm" id="editor-publish-submit" disabled>Deploy Now</button>
        </div>
    </div>
</div>

<!-- ── Fullscreen Publish Progress Overlay ────────────────── -->
<div id="editor-pub-overlay" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.6); backdrop-filter:blur(8px); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:20px; padding:40px 48px; width:440px; max-width:92vw; box-shadow:0 32px 80px rgba(0,0,0,0.25); text-align:center; animation:pubPop .35s cubic-bezier(0.34,1.56,0.64,1);">
        <span id="epo-rocket" style="font-size:52px; display:block; margin-bottom:8px; animation:rocketFloat 1.6s ease-in-out infinite;">🚀</span>
        <div id="epo-title" style="font-size:21px; font-weight:800; color:#0F0F0F; margin-bottom:4px;">Publishing Your Site</div>
        <div id="epo-step" style="font-size:13px; color:#6B6B6B; margin-bottom:24px; min-height:20px;">Preparing your portfolio...</div>
        <div style="width:100%; height:6px; background:#f0f0f0; border-radius:99px; overflow:hidden; margin-bottom:10px;">
            <div id="epo-fill" style="height:100%; width:0%; background:linear-gradient(90deg,#0F0F0F,#555); border-radius:99px; transition:width .45s cubic-bezier(0.4,0,0.2,1);"></div>
        </div>
        <div id="epo-pct" style="font-size:12px; color:#999; font-weight:600;">0%</div>
    </div>
</div>

<style>
    @keyframes pubPop {
        from { opacity:0; transform:scale(0.85) translateY(24px); }
        to   { opacity:1; transform:scale(1) translateY(0); }
    }
    @keyframes rocketFloat {
        0%,100% { transform:translateY(0); }
        50%     { transform:translateY(-10px); }
    }
    .slug-ok  { color:#16a34a; }
    .slug-err { color:#dc2626; }
</style>

<script>
/* ── Editor Publish Modal Logic ─────────────────────────── */
let _epSlugTimer = null;

document.getElementById('publish-btn').addEventListener('click', async () => {
    // First save current data
    saveToServer && saveToServer();
    // Pre-fill slug if available
    const slugInput = document.getElementById('editor-publish-slug');
    slugInput.value = CUSTOM_SLUG || '';
    document.getElementById('editor-slug-feedback').innerHTML = '';
    document.getElementById('editor-publish-submit').disabled = !CUSTOM_SLUG;
    
    // Update Modal text for re-publish
    if (PUBLISH_STATUS === 'published' || PUBLISH_STATUS === 'deployed') {
        document.getElementById('ep-modal-title').textContent = '🚀 Update Live Site';
        document.getElementById('editor-publish-submit').textContent = 'Re-publish Now';
    } else {
        document.getElementById('ep-modal-title').textContent = '🚀 Deploy Your Portfolio';
        document.getElementById('editor-publish-submit').textContent = 'Deploy Now';
    }

    if (CUSTOM_SLUG) epValidateSlug(CUSTOM_SLUG);
    // Show modal
    document.getElementById('editor-publish-backdrop').classList.add('open');
    setTimeout(() => slugInput.focus(), 100);
});

function closeEditorPublish() {
    document.getElementById('editor-publish-backdrop').classList.remove('open');
}

document.getElementById('editor-publish-backdrop').addEventListener('click', function(e) {
    if (e.target === this) closeEditorPublish();
});

document.getElementById('editor-publish-slug').addEventListener('input', function() {
    let slug = this.value;
    // Immediate sanitize
    const clean = slug.toLowerCase().replace(/[^a-z0-9-]/g, '');
    if (slug !== clean) {
        this.value = clean;
        slug = clean;
    }
    
    const btn  = document.getElementById('editor-publish-submit');
    const fb   = document.getElementById('editor-slug-feedback');

    btn.disabled = true;
    if (slug.length < 3) {
        fb.innerHTML = '<span class="slug-err">Min 3 characters</span>';
        return;
    }
    fb.innerHTML = '<span style="color:#888;">Checking availability...</span>';
    clearTimeout(_epSlugTimer);
    _epSlugTimer = setTimeout(() => epValidateSlug(slug), 600);
});

async function epValidateSlug(slug) {
    const btn = document.getElementById('editor-publish-submit');
    const fb  = document.getElementById('editor-slug-feedback');
    
    try {
        const fd = new FormData();
        fd.append('slug', slug);
        fd.append('project_id', PROJECT_ID);
        
        const response = await fetch(BASE_URL + '/check-slug.php', { method: 'POST', body: fd });
        const data = await response.json();
        
        if (data.available) {
            fb.innerHTML = '<span class="slug-ok">✓ Available</span>';
            btn.disabled = false;
        } else {
            fb.innerHTML = `<span class="slug-err">❌ ${data.error || 'Taken on Vercel. Try another.'}</span>`;
            btn.disabled = true;
        }
    } catch(e) {
        fb.innerHTML = '<span class="slug-err">Error checking availability.</span>';
        btn.disabled = true;
    }
}

document.getElementById('editor-publish-submit').addEventListener('click', async () => {
    const slug = document.getElementById('editor-publish-slug').value.trim();
    if (!slug || slug.length < 3) return;
    
    const btn = document.getElementById('editor-publish-submit');
    btn.disabled = true;
    document.getElementById('editor-slug-feedback').innerHTML = '<span style="color:#888;">Double checking availability...</span>';
    
    // Final verify before deployment
    try {
        const fdCheck = new FormData();
        fdCheck.append('slug', slug);
        fdCheck.append('project_id', PROJECT_ID);
        const checkRes = await fetch(BASE_URL + '/check-slug.php', { method: 'POST', body: fdCheck });
        const checkData = await checkRes.json();
        if (!checkData.available) {
            document.getElementById('editor-slug-feedback').innerHTML = `<span class="slug-err">❌ ${checkData.error || 'Taken on Vercel. Try another.'}</span>`;
            return;
        }
    } catch(e) { }

    // Close modal
    closeEditorPublish();

    // Show fullscreen overlay
    const overlay = document.getElementById('editor-pub-overlay');
    const fill    = document.getElementById('epo-fill');
    const pctEl   = document.getElementById('epo-pct');
    const stepEl  = document.getElementById('epo-step');
    const titleEl = document.getElementById('epo-title');
    const rocket  = document.getElementById('epo-rocket');
    overlay.style.display = 'flex';

    const stages = [
        { pct: 10, msg: 'Saving your changes...' },
        { pct: 25, msg: 'Checking URL availability...' },
        { pct: 45, msg: 'Validating folders & links...' },
        { pct: 70, msg: 'Pushing files to Vercel...' },
        { pct: 85, msg: 'Configuring domain alias...' },
        { pct: 95, msg: 'Almost there...' },
    ];
    let idx = 0;
    function setProgress(pct, msg) {
        fill.style.width  = pct + '%';
        pctEl.textContent = Math.round(pct) + '%';
        if (msg) stepEl.textContent = msg;
    }
    setProgress(0, stages[0].msg);
    const timer = setInterval(() => {
        if (idx < stages.length) { const s = stages[idx++]; setProgress(s.pct, s.msg); }
    }, 900);

    // Save first, then deploy
    try {
        await fetch(BASE_URL + '/project-save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ project_id: PROJECT_ID, data: userData })
        });
    } catch (e) { /* continue anyway */ }

    const fd = new FormData();
    fd.append('id', PROJECT_ID);
    fd.append('slug', slug);

    fetch(BASE_URL + '/app/DeployController.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        clearInterval(timer);
        if (data.success) {
            setProgress(100, 'Site is live! 🎉');
            titleEl.textContent = 'Published!';
            rocket.textContent  = '✅';
            setTimeout(() => {
                overlay.style.display = 'none';
                // Redirect to dashboard to see live card
                window.location.href = BASE_URL + '/dashboard.php?published=' + PROJECT_ID + '&live_url=' + encodeURIComponent(data.url || '');
            }, 1400);
        } else {
            overlay.style.display = 'none';
            alert('Publish failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(() => {
        clearInterval(timer);
        overlay.style.display = 'none';
        alert('Connection error. Please try again.');
    });
});
</script>
</body>
</html>
