<?php
/**
 * ADMIN DASHBOARD v2
 * Matches the requested theme structure
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/admin_auth.php';

// Get stats
try {
    // Total
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM templates");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Active
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM templates WHERE status = 'active'");
    $active = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Inactive
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM templates WHERE status = 'inactive'");
    $inactive = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

} catch (PDOException $e) {
    $total = 0;
    $active = 0;
    $inactive = 0;
    $totalUsers = 0;
}

// User initials for avatar
$userName = $_SESSION['user_name'] ?? 'Admin User';
$userInitials = strtoupper(substr($userName, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CodeCanvas</title>
    <link href="<?= BASE_URL ?>/public/assets/css/admin-theme.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="logo">CodeCanvas</a>
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link active">
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
                        <a href="<?= BASE_URL ?>/admin/notifications.php" class="nav-link">
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search...">
                </div>
                <div class="navbar-actions">
                    <button class="icon-button">
                        <svg viewBox="0 0 24 24">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </button>
                    <div class="admin-profile">
                        <div class="avatar"><?php echo $userInitials; ?></div>
                        <div class="admin-info">
                            <div class="admin-name"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="admin-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Dashboard Page -->
            <div class="content-container">
                <div class="page-header">
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Overview of your CodeCanvas platform</p>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Templates</div>
                        <div class="stat-value"><?php echo number_format($total); ?></div>
                        <div class="stat-change" style="color: #2E7D32;">• Live in library</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Active Templates</div>
                        <div class="stat-value"><?php echo number_format($active); ?></div>
                        <div class="stat-change">Visible to users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Inactive Templates</div>
                        <div class="stat-value"><?php echo number_format($inactive); ?></div>
                        <div class="stat-change">Drafts / Hidden</div>
                    </div>
                    <!-- Total Users -->
                    <div class="stat-card">
                        <div class="stat-label">Total Users</div>
                        <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                        <div class="stat-change" style="color: #2E7D32;">Registered accounts</div>
                    </div>
                </div>

                <!-- Quick Actions / Recent Activity Placeholder -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">Quick Actions</h2>
                    </div>
                    <div style="display: flex; gap: 16px;">
                        <a href="template-add.php" class="btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add New Template
                        </a>
                        <a href="templates.php" class="btn" style="background: white; color: black; border: 1px solid #e5e5e5;">
                            Manage Library
                        </a>
                    </div>
                </div>

                <!-- Recent Users Table (Static Example from Theme) -->
                <!-- Ideally this would be fetched from DB, but keeping as visual placeholder per theme request -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">Recent Activity</h2>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>System Login</td>
                                    <td><?php echo htmlspecialchars($userName); ?></td>
                                    <td><?php echo date('M j, Y H:i'); ?></td>
                                    <td><span style="padding: 4px 12px; background: #E8F5E9; color: #2E7D32; border-radius: 4px; font-size: 12px; font-weight: 500;">Success</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- ── Logout Modal ──────────────────────────────────────── -->
    <div id="logout-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:10px; width:100%; max-width:400px; padding:28px; box-shadow:0 24px 64px rgba(0,0,0,.18);">
            <h3 style="font-size:18px; font-weight:700; margin:0 0 10px;">Confirm Logout</h3>
            <p style="font-size:14px; color:#666; margin:0 0 24px;">Are you sure you want to log out of the admin panel?</p>
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <button onclick="closeLogout()" style="padding:10px 20px; border-radius:6px; background:#fff; border:1px solid #e5e5e5; cursor:pointer; font-weight:600;">Cancel</button>
                <button onclick="executeLogout()" style="padding:10px 20px; border-radius:6px; background:#000; color:#fff; border:none; cursor:pointer; font-weight:600;">Logout</button>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/admin.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        function openLogout(e) {
            if (e) e.preventDefault();
            document.getElementById('logout-backdrop').style.display = 'flex';
        }
        function closeLogout() {
            document.getElementById('logout-backdrop').style.display = 'none';
        }
        function executeLogout() {
            window.location.href = BASE_URL + '/auth/logout.php';
        }
        
        // Update logout link
        document.querySelector('a[href$="logout.php"]').onclick = openLogout;
    </script>
</body>
</html>
