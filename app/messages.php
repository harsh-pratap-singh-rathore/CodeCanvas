<?php
/**
 * MESSAGES PAGE
 * Displays contact form submissions from user's portfolios.
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

// Handle Mark as Read
if (isset($_GET['read'])) {
    $msgId = (int)$_GET['read'];
    $stmt = $pdo->prepare("
        UPDATE messages m
        JOIN projects p ON m.project_id = p.id
        SET m.is_read = 1 
        WHERE m.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$msgId, $user['id']]);
    header("Location: " . BASE_URL . '/app/messages.php');
exit;
}

// Fetch Messages
try {
    $stmt = $pdo->prepare("
        SELECT m.*, p.project_name
        FROM messages m
        JOIN projects p ON m.project_id = p.id
        WHERE p.user_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — CodeCanvas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css">
    <style>
        .msg-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 24px;
        }
        .msg-card {
            background: #fff;
            border: 1px solid #E5E5E5;
            border-radius: 8px;
            padding: 24px;
            transition: border-color .15s;
            position: relative;
        }
        .msg-card.unread {
            border-left: 4px solid #000;
        }
        .msg-card:hover { border-color: #000; }
        
        .msg-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .msg-from { font-weight: 700; font-size: 16px; color: #000; }
        .msg-date { font-size: 12px; color: #888; }
        .msg-project { 
            font-size: 11px; 
            font-weight: 700; 
            color: #666; 
            text-transform: uppercase; 
            letter-spacing: .05em;
            background: #f5f5f5;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 8px;
        }
        .msg-subject { font-weight: 600; color: #333; margin-bottom: 8px; }
        .msg-body { font-size: 14px; color: #555; line-height: 1.6; white-space: pre-wrap; }
        
        .msg-actions {
            margin-top: 16px;
            display: flex;
            gap: 12px;
        }
        .msg-btn {
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            color: #000;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }
        .msg-btn:hover { color: #666; border-color: #666; }

        .empty-msgs {
            text-align: center;
            padding: 60px 20px;
            background: #f9f9f9;
            border: 1px dashed #D0D0D0;
            border-radius: 12px;
            color: #888;
        }
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
                <h1>Portfolio Messages</h1>
                <p style="color:#666; font-size:14px;">Messages sent by visitors via your published portfolios.</p>
            </div>

            <div id="messages-container">
            <?php if (empty($messages)): ?>
                <div class="empty-msgs">
                    <p>No messages yet. Once visitors use your contact form, they will appear here.</p>
                </div>
            <?php else: ?>
                <div class="msg-list">
                    <?php foreach ($messages as $msg): ?>
                        <div class="msg-card <?php echo $msg['is_read'] ? '' : 'unread'; ?>">
                            <div class="msg-header">
                                <div class="msg-from"><?php echo htmlspecialchars($msg['visitor_name']); ?></div>
                                <div class="msg-date"><?php echo date('M j, Y — g:i A', strtotime($msg['created_at'])); ?></div>
                            </div>
                            <div class="msg-project">Project: <?php echo htmlspecialchars($msg['project_name']); ?></div>
                            <div class="msg-subject">Subject: <?php echo htmlspecialchars($msg['subject']); ?></div>
                            <div class="msg-body"><?php echo htmlspecialchars($msg['message']); ?></div>
                            
                            <div class="msg-actions">
                                <a href="mailto:<?php echo htmlspecialchars($msg['visitor_email']); ?>" class="msg-btn">Reply via Email</a>
                                <?php if (!$msg['is_read']): ?>
                                    <a href="<?= BASE_URL ?>/messages.php?read=<?php echo $msg['id']; ?>" class="msg-btn">Mark as Read</a>
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
    const container = document.getElementById('messages-container');
    
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

    function updateMessagesList(data) {
        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-msgs">
                    <p>No messages yet. Once visitors use your contact form, they will appear here.</p>
                </div>
            `;
            return;
        }

        let html = '<div class="msg-list">';
        data.forEach(msg => {
            const isUnread = String(msg.is_read) === '0' || msg.is_read === false;
            const unreadClass = isUnread ? 'unread' : '';
            
            let markReadHtml = '';
            if (isUnread) {
                markReadHtml = `<a href="${BASE_URL}/messages.php?read=${msg.id}" class="msg-btn">Mark as Read</a>`;
            }

            html += `
                <div class="msg-card ${unreadClass}">
                    <div class="msg-header">
                        <div class="msg-from">${escapeHtml(msg.visitor_name)}</div>
                        <div class="msg-date">${escapeHtml(msg.formatted_date)}</div>
                    </div>
                    <div class="msg-project">Project: ${escapeHtml(msg.project_name)}</div>
                    <div class="msg-subject">Subject: ${escapeHtml(msg.subject)}</div>
                    <div class="msg-body">${escapeHtml(msg.message)}</div>
                    
                    <div class="msg-actions">
                        <a href="mailto:${escapeHtml(msg.visitor_email)}" class="msg-btn">Reply via Email</a>
                        ${markReadHtml}
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
