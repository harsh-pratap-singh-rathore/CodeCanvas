<?php
/**
 * NOTIFICATIONS PAGE
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

$user = [
    'id'       => $_SESSION['user_id'],
    'name'     => $_SESSION['user_name'],
    'initials' => strtoupper(substr($_SESSION['user_name'], 0, 1))
];

// Fetch Notifications
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Auto mark as read when viewing
    $upd = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $upd->execute([$user['id']]);
} catch (PDOException $e) {
    $notifications = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — CodeCanvas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css">
    <style>
        .notif-list {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            background: #fff;
            border: 1px solid #E5E5E5;
            padding: 16px 20px;
            border-radius: 8px;
        }
        .notif-icon {
            width: 32px;
            height: 32px;
            background: #f5f5f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notif-content { flex: 1; }
        .notif-title { font-weight: 700; font-size: 14px; margin-bottom: 2px; }
        .notif-text { font-size: 13px; color: #666; }
        .notif-date { font-size: 11px; color: #888; margin-top: 4px; }
    </style>
</head>
<body class="dashboard-layout">
    <header class="dashboard-header">
        <div class="dashboard-header-content">
            <a href="<?= BASE_URL ?>/dashboard.php" class="logo">CodeCanvas</a>
            <div class="header-right">
                <div class="user-avatar">
                    <span class="avatar-circle"><?php echo $user['initials']; ?></span>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <aside class="dashboard-sidebar">
            <nav class="sidebar-nav">
                <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-link">← Back to Projects</a>
            </nav>
        </aside>

        <main class="dashboard-main">
            <div class="dashboard-page-header">
                <h1>Notifications</h1>
            </div>

            <div id="notifications-container">
            <?php if (empty($notifications)): ?>
                <div style="text-align:center; padding: 40px; color:#888;">
                    No notifications yet.
                </div>
            <?php else: ?>
                <div class="notif-list">
                    <?php foreach ($notifications as $n): ?>
                        <div class="notif-item">
                            <div class="notif-icon">
                                <?php if ($n['type'] === 'message'): ?> ✉️ <?php else: ?> 🔔 <?php endif; ?>
                            </div>
                            <div class="notif-content">
                                <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="notif-text"><?php echo htmlspecialchars($n['content']); ?></div>
                                <div class="notif-date"><?php echo date('M j, Y — g:i A', strtotime($n['created_at'])); ?></div>
                                <?php if ($n['link']): ?>
                                    <a href="<?php echo $n['link']; ?>" style="font-size:12px; font-weight:600; color:#000; margin-top:8px; display:inline-block;">View Details</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
        </main>
    </div>
<script>const BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>
<script>
    const container = document.getElementById('notifications-container');
    
    // Auto-fetch using SSE event triggers (defined in mobile-nav.js)

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function updateNotificationsList(data) {
        if (!data || data.length === 0) {
            container.innerHTML = `<div style="text-align:center; padding: 40px; color:#888;">No notifications yet.</div>`;
            return;
        }

        let html = '<div class="notif-list">';
        data.forEach(n => {
            const icon = n.type === 'message' ? '✉️' : '🔔';
            const linkHtml = n.link ? `<a href="${escapeHtml(n.link)}" style="font-size:12px; font-weight:600; color:#000; margin-top:8px; display:inline-block;">View Details</a>` : '';
            
            html += `
                <div class="notif-item">
                    <div class="notif-icon">${icon}</div>
                    <div class="notif-content">
                        <div class="notif-title">${escapeHtml(n.title)}</div>
                        <div class="notif-text">${escapeHtml(n.content)}</div>
                        <div class="notif-date">${escapeHtml(n.formatted_date)}</div>
                        ${linkHtml}
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
    }
</script>
</body>
</html>
