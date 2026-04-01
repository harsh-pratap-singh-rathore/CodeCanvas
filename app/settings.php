<?php
/**
 * APP - SETTINGS
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

$user = [
    'name' => $_SESSION['user_name'],
    'email' => $_SESSION['user_email'],
    'initials' => strtoupper(substr($_SESSION['user_name'], 0, 1))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — CodeCanvas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css">
</head>
<body class="dashboard-layout">
    <header class="dashboard-header">
        <div class="dashboard-header-content">
            <a href="<?= BASE_URL ?>/dashboard.php" class="logo">CodeCanvas</a>
            <div class="user-menu">
                <div class="user-avatar" data-dropdown="user">
                    <span class="avatar-circle"><?php echo htmlspecialchars($user['initials']); ?></span>
                    <div class="dropdown dropdown-right">
                        <div class="dropdown-item"><strong><?php echo htmlspecialchars($user['name']); ?></strong></div>
                        <a href="<?= BASE_URL ?>/profile.php" class="dropdown-item">Profile</a>
                        <a href="<?= BASE_URL ?>/settings.php" class="dropdown-item">Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="auth/logout.php" class="dropdown-item">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container" style="max-width: 800px; margin: 80px auto; padding: 0 24px;">
        <h1>Settings</h1>
        <p style="color: #6B6B6B; margin-bottom: 48px;">Manage your account settings</p>

        <div style="background: white; border: 1px solid #E5E5E5; border-radius: 4px; padding: 32px; margin-bottom: 24px;">
            <h3 style="margin-bottom: 16px;">Account Settings</h3>
            <p style="color: #6B6B6B; font-size: 14px;">Settings options coming soon...</p>
        </div>

        <div style="margin-top: 32px;">
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
        </div>
    </div>

    <script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/public/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>
</body>
</html>
