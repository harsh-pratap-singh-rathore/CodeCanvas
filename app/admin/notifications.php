<?php
/**
 * ADMIN NOTIFICATIONS
 * Global view of all contact messages and system alerts.
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/admin_auth.php';

// User info for header
$userName = $_SESSION['user_name'] ?? 'Admin';
$userInitials = strtoupper(substr($userName, 0, 1));

// Handle Actions (Mark as Read, Delete)
if (isset($_GET['action'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($_GET['action'] === 'read' && $id) {
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?")->execute([$id]);
        header("Location: " . BASE_URL . '/admin/notifications.php?success=read');
exit;
    }
    if ($_GET['action'] === 'delete' && $id) {
        $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$id]);
        header("Location: " . BASE_URL . '/admin/notifications.php?success=deleted');
exit;
    }
}

// Fetch global messages (all users)
try {
    $stmt = $pdo->query("
        SELECT m.*, p.project_name, u.name as owner_name, u.email as owner_email
        FROM messages m
        JOIN projects p ON m.project_id = p.id
        JOIN users u ON p.user_id = u.id
        ORDER BY m.created_at DESC
        LIMIT 100
    ");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messages = [];
}

// Fetch system notifications
try {
    $stmt = $pdo->query("
        SELECT n.*, u.name as user_name
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Notifications — Admin</title>
    <link href="<?= BASE_URL ?>/public/assets/css/admin-theme.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .page-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 32px;
            align-items: start;
        }
        @media (max-width: 1100px) {
            .page-grid { grid-template-columns: 1fr; }
        }

        .notif-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 16px;
            transition: all 0.2s;
        }
        .notif-card.unread { border-left: 4px solid #000; }
        .notif-card:hover { border-color: #000; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .notif-header { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .notif-meta { font-size: 12px; color: #888; display: flex; gap: 12px; }
        .notif-owner { font-weight: 700; color: #000; font-size: 13px; background: #f5f5f5; padding: 2px 8px; border-radius: 4px; }
        
        .msg-from { font-weight: 700; font-size: 16px; margin-bottom: 4px; }
        .msg-project { font-size: 12px; color: #666; margin-bottom: 12px; }
        .msg-content { font-size: 14px; line-height: 1.6; color: #444; white-space: pre-wrap; background: #fafafa; padding: 16px; border-radius: 8px; }

        .notif-actions { margin-top: 16px; display: flex; gap: 12px; }
        .btn-link { font-size: 12px; font-weight: 600; text-decoration: none; color: #000; border-bottom: 1px solid #000; transition: opacity 0.2s; }
        .btn-link:hover { opacity: 0.6; }

        .sidebar-box { background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 12px; padding: 20px; }
        .sys-notif-item { padding: 12px 0; border-bottom: 1px solid #eee; }
        .sys-notif-item:last-child { border-bottom: none; }
        .sys-notif-title { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
        .sys-notif-text { font-size: 12px; color: #666; line-height: 1.4; }
        .sys-notif-date { font-size: 10px; color: #aaa; margin-top: 4px; }
    </style>
</head>
<body class="admin-layout">

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
                        <a href="templates.php" class="nav-link">
                            <svg class="nav-icon" viewBox="0 0 24 24">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="9" y1="3" x2="9" y2="21"></line>
                            </svg>
                            Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/admin/notifications.php" class="nav-link active">
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

        <main class="main-content">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search messages...">
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

            <div class="content-container">
                <div class="page-header">
                    <h1 class="page-title">Notifications</h1>
                    <p class="page-subtitle">Global activity and portfolio messages</p>
                </div>

                <div class="page-grid">
                    <!-- Global Messages -->
                    <div class="main-column">
                        <h2 class="section-title" style="margin-bottom: 24px;">Global Portfolio Messages</h2>
                        
                        <?php if (empty($messages)): ?>
                            <div style="background:#fff; padding:60px; text-align:center; border:1px dashed #ccc; border-radius:12px; color:#999;">
                                No portfolio messages found in the system.
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="notif-card <?php echo $msg['is_read'] ? '' : 'unread'; ?>">
                                    <div class="notif-header">
                                        <div class="msg-from">
                                            <?php echo htmlspecialchars($msg['visitor_name']); ?> 
                                            <span style="font-weight:400; color:#888; font-size:14px;">(<?php echo htmlspecialchars($msg['visitor_email']); ?>)</span>
                                        </div>
                                        <div class="notif-meta">
                                            <span><?php echo date('M j, Y • g:i A', strtotime($msg['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="msg-project">
                                        To: <span class="notif-owner"><?php echo htmlspecialchars($msg['owner_name']); ?></span> 
                                        via project <strong><?php echo htmlspecialchars($msg['project_name']); ?></strong>
                                    </div>
                                    <div class="msg-content"><?php echo htmlspecialchars($msg['message']); ?></div>
                                    
                                    <div class="notif-actions">
                                        <?php if (!$msg['is_read']): ?>
                                            <a href="<?= BASE_URL ?>/notifications.php?action=read&id=<?php echo $msg['id']; ?>" class="btn-link">Mark as Read</a>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>/notifications.php?action=delete&id=<?php echo $msg['id']; ?>" class="btn-link" style="color:#ef4444; border-color:#ef4444;" onclick="return confirm('Delete this message?')">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- System Events -->
                    <aside class="side-column">
                        <div class="sidebar-box">
                            <h3 style="font-size: 14px; text-transform:uppercase; letter-spacing:1px; margin-bottom:16px;">System Activity</h3>
                            
                            <?php if (empty($notifications)): ?>
                                <p style="font-size:12px; color:#888;">No recent system activity.</p>
                            <?php else: ?>
                                <?php foreach ($notifications as $n): ?>
                                    <div class="sys-notif-item">
                                        <div class="sys-notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                        <div class="sys-notif-text">
                                            <?php echo htmlspecialchars($n['content']); ?>
                                            <?php if (!empty($n['user_name'])): ?>
                                                <br><span style="color:#888;">User: <?php echo htmlspecialchars($n['user_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="sys-notif-date"><?php echo date('M j, H:i', strtotime($n['created_at'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
