<?php
/**
 * ADMIN DASHBOARD — PREMIUM EDITION
 * Full admin panel with stats, charts, users & projects management.
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';

// ── Auth: must be logged in AND admin role ──────────────────
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login.html');
    exit;
}

$admin = [
    'id'       => $_SESSION['user_id'],
    'name'     => $_SESSION['user_name'],
    'email'    => $_SESSION['user_email'],
    'initials' => strtoupper(substr($_SESSION['user_name'], 0, 1))
];

// ── Pull Stats ──────────────────────────────────────────────
$totalUsers = 0; $activeUsers = 0; $inactiveUsers = 0;
$totalProjects = 0; $liveProjects = 0; $draftProjects = 0; $failedProjects = 0;
$totalMessages = 0; $unreadMessages = 0;
$recentUsers = []; $recentProjects = [];
$userGrowth = []; $projectGrowth = [];

try {
    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $inactiveUsers = $totalUsers - $activeUsers;

    $totalProjects = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status != 'archived'")->fetchColumn();
    $liveProjects = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE (publish_status = 'deployed' OR publish_status = 'published' OR (live_url IS NOT NULL AND live_url != '')) AND status != 'archived'")->fetchColumn();
    $failedProjects = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE publish_status = 'failed' AND status != 'archived'")->fetchColumn();
    $draftProjects = $totalProjects - $liveProjects - $failedProjects;

    $totalMessages = (int) $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $unreadMessages = (int) $pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn();

    // Recent 8 users
    $recentUsers = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();

    // Recent 8 projects
    $recentProjects = $pdo->query("
        SELECT p.id, p.project_name, p.status, p.publish_status, p.live_url, p.created_at, p.updated_at, u.name as user_name, u.email as user_email
        FROM projects p LEFT JOIN users u ON p.user_id = u.id
        ORDER BY p.updated_at DESC LIMIT 8
    ")->fetchAll();

    // User growth — last 7 days
    $growthStmt = $pdo->query("
        SELECT DATE(created_at) as day, COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY day ASC
    ");
    $userGrowth = $growthStmt->fetchAll();

    // Project growth — last 7 days
    $projGrowthStmt = $pdo->query("
        SELECT DATE(created_at) as day, COUNT(*) as count 
        FROM projects 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY day ASC
    ");
    $projectGrowth = $projGrowthStmt->fetchAll();

} catch (PDOException $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
}

// Prepare chart data
$chartLabels = [];
$chartUserData = [];
$chartProjectData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M j', strtotime("-$i days"));
    $chartLabels[] = $label;
    
    $found = false;
    foreach ($userGrowth as $ug) {
        if ($ug['day'] === $date) { $chartUserData[] = (int)$ug['count']; $found = true; break; }
    }
    if (!$found) $chartUserData[] = 0;
    
    $found = false;
    foreach ($projectGrowth as $pg) {
        if ($pg['day'] === $date) { $chartProjectData[] = (int)$pg['count']; $found = true; break; }
    }
    if (!$found) $chartProjectData[] = 0;
}

$livePercent = $totalProjects > 0 ? round(($liveProjects / $totalProjects) * 100) : 0;
$activePercent = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CodeCanvas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --green: #10b981;
            --green-light: #d1fae5;
            --green-dark: #059669;
            --red: #ef4444;
            --red-light: #fef2f2;
            --amber: #f59e0b;
            --amber-light: #fffbeb;
            --blue: #3b82f6;
            --blue-light: #eff6ff;
            --bg: #f6f6f6;
            --card: #fff;
            --border: #eee;
            --text: #0a0a0a;
            --muted: #999;
            --radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ═══════════════════════════════════════════════════════
           HEADER
           ═══════════════════════════════════════════════════════ */
        .header {
            height: 64px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(12px);
            background: rgba(255,255,255,0.9);
        }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .brand { font-size: 18px; font-weight: 900; letter-spacing: -0.03em; }
        .admin-tag {
            font-size: 9px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.1em; color: #fff; background: var(--green);
            padding: 3px 10px; border-radius: 99px;
        }
        .header-right { display: flex; align-items: center; gap: 14px; }
        .header-btn {
            padding: 7px 16px; border-radius: 8px; font-size: 12px; font-weight: 700;
            cursor: pointer; border: 1px solid var(--border); background: #fff;
            color: var(--text); transition: all 0.2s; font-family: inherit;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .header-btn:hover { background: #fafafa; border-color: #ddd; }
        .header-btn-danger { color: var(--red); border-color: #fecaca; }
        .header-btn-danger:hover { background: var(--red-light); }
        .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--text); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800;
        }
        .user-info { text-align: right; }
        .user-name { font-size: 13px; font-weight: 700; }
        .user-role { font-size: 10px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }

        /* ═══════════════════════════════════════════════════════
           LAYOUT
           ═══════════════════════════════════════════════════════ */
        .main { max-width: 1280px; margin: 0 auto; padding: 32px 24px 60px; }

        .page-header { margin-bottom: 32px; }
        .page-title { font-size: 28px; font-weight: 900; letter-spacing: -0.04em; margin-bottom: 4px; }
        .page-sub { font-size: 14px; color: var(--muted); font-weight: 500; }

        /* ═══════════════════════════════════════════════════════
           STAT CARDS
           ═══════════════════════════════════════════════════════ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px 24px 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.06);
        }
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: #eee;
            transition: background 0.3s;
        }
        .stat-card.green::after { background: var(--green); }
        .stat-card.blue::after { background: var(--blue); }
        .stat-card.amber::after { background: var(--amber); }
        .stat-card.red::after { background: var(--red); }

        .stat-icon {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; margin-bottom: 16px;
        }
        .stat-icon.green { background: var(--green-light); }
        .stat-icon.blue { background: var(--blue-light); }
        .stat-icon.amber { background: var(--amber-light); }
        .stat-icon.red { background: var(--red-light); }

        .stat-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--muted); margin-bottom: 8px;
        }
        .stat-value {
            font-size: 32px; font-weight: 900; letter-spacing: -0.04em;
            line-height: 1;
        }
        .stat-meta {
            display: flex; align-items: center; gap: 8px;
            margin-top: 10px; font-size: 12px; font-weight: 600;
        }
        .stat-pill {
            padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 700;
        }
        .pill-green { background: var(--green-light); color: var(--green-dark); }
        .pill-red { background: var(--red-light); color: var(--red); }
        .pill-amber { background: var(--amber-light); color: var(--amber); }

        /* ═══════════════════════════════════════════════════════
           CHART SECTION
           ═══════════════════════════════════════════════════════ */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 32px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px;
        }
        .card-title { font-size: 15px; font-weight: 800; letter-spacing: -0.02em; }
        .card-badge {
            font-size: 10px; font-weight: 700; color: var(--muted);
            background: #f5f5f5; padding: 4px 10px; border-radius: 99px;
        }
        .chart-container { position: relative; height: 220px; }

        /* Donut center label */
        .donut-wrap { position: relative; display: flex; align-items: center; justify-content: center; height: 220px; }
        .donut-center {
            position: absolute; text-align: center;
        }
        .donut-center .donut-val { font-size: 28px; font-weight: 900; letter-spacing: -0.03em; }
        .donut-center .donut-label { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }

        /* ═══════════════════════════════════════════════════════
           ACTIVITY + QUICK STATS
           ═══════════════════════════════════════════════════════ */
        .mid-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 32px;
        }

        /* Progress bars */
        .progress-item { margin-bottom: 16px; }
        .progress-item:last-child { margin-bottom: 0; }
        .progress-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 6px;
        }
        .progress-label { font-size: 13px; font-weight: 700; }
        .progress-value { font-size: 13px; font-weight: 800; color: var(--muted); }
        .progress-track {
            width: 100%; height: 8px; background: #f0f0f0;
            border-radius: 99px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; border-radius: 99px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .fill-green { background: var(--green); }
        .fill-blue { background: var(--blue); }
        .fill-amber { background: var(--amber); }
        .fill-red { background: var(--red); }

        /* Timeline */
        .timeline-item {
            display: flex; gap: 14px; padding: 14px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .timeline-item:last-child { border-bottom: none; }
        .timeline-dot {
            width: 8px; height: 8px; border-radius: 50%;
            margin-top: 6px; flex-shrink: 0;
        }
        .dot-green { background: var(--green); }
        .dot-blue { background: var(--blue); }
        .dot-amber { background: var(--amber); }
        .timeline-text { font-size: 13px; font-weight: 500; color: #555; line-height: 1.4; }
        .timeline-text strong { color: var(--text); font-weight: 700; }
        .timeline-time { font-size: 11px; color: #ccc; font-weight: 600; margin-top: 3px; }

        /* ═══════════════════════════════════════════════════════
           TABLES
           ═══════════════════════════════════════════════════════ */
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 14px;
        }
        .section-title { font-size: 17px; font-weight: 800; letter-spacing: -0.02em; }
        .section-badge { font-size: 10px; font-weight: 700; color: var(--muted); background: #f5f5f5; padding: 4px 10px; border-radius: 99px; }
        .tables-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }

        .table-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden;
        }
        .table-card-header {
            padding: 18px 24px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .tbl { width: 100%; border-collapse: collapse; }
        .tbl th {
            padding: 12px 20px; font-size: 10px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.08em;
            color: #bbb; text-align: left; background: #fafafa;
            border-bottom: 1px solid var(--border);
        }
        .tbl td {
            padding: 12px 20px; font-size: 13px; font-weight: 500;
            color: #444; border-bottom: 1px solid #f7f7f7; vertical-align: middle;
        }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: #fcfcfc; }
        .td-bold { font-weight: 700; color: var(--text); }
        .td-muted { color: #bbb; font-size: 12px; }
        .td-small { font-size: 11px; color: #aaa; }

        .badge {
            display: inline-block; padding: 3px 10px; border-radius: 99px;
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .b-active { background: var(--green-light); color: var(--green-dark); }
        .b-inactive { background: #f5f5f5; color: #999; }
        .b-admin { background: var(--text); color: #fff; }
        .b-user { background: #f5f5f5; color: #666; }
        .b-live { background: var(--green-light); color: var(--green-dark); }
        .b-draft { background: #f5f5f5; color: #999; }
        .b-building { background: var(--amber-light); color: #b45309; }
        .b-failed { background: var(--red-light); color: var(--red); }

        .tbl-actions { display: flex; gap: 4px; }
        .tbl-btn {
            padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 700;
            cursor: pointer; border: 1px solid var(--border); background: #fff;
            color: #888; transition: all 0.15s; font-family: inherit;
        }
        .tbl-btn:hover { background: #f5f5f5; color: var(--text); }
        .tbl-btn-danger:hover { background: var(--red-light); color: var(--red); border-color: #fecaca; }

        .live-link { color: var(--green-dark); text-decoration: none; font-weight: 700; font-size: 11px; }
        .live-link:hover { text-decoration: underline; }

        /* ═══════════════════════════════════════════════════════
           TOAST + MODAL
           ═══════════════════════════════════════════════════════ */
        #admin-toast {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 24px; background: var(--text); color: #fff;
            border-radius: 12px; font-size: 13px; font-weight: 600;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            transform: translateY(100px); opacity: 0;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); z-index: 9999;
        }
        #admin-toast.show { transform: translateY(0); opacity: 1; }
        #admin-toast.error { background: var(--red); }

        .modal-bg {
            display: none; position: fixed; inset: 0;
            background: rgba(255,255,255,0.8); backdrop-filter: blur(20px);
            z-index: 5000; align-items: center; justify-content: center;
        }
        .modal-bg.open { display: flex; }
        .modal-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 20px; padding: 36px; width: 100%; max-width: 400px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.08); text-align: center;
        }
        .modal-card h3 { font-size: 18px; font-weight: 800; margin-bottom: 8px; }
        .modal-card p { font-size: 13px; color: #888; margin-bottom: 24px; line-height: 1.6; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .modal-btn {
            padding: 10px 24px; border-radius: 10px; font-size: 13px; font-weight: 700;
            cursor: pointer; border: 1px solid var(--border); background: #fff;
            color: var(--text); font-family: inherit; transition: all 0.2s;
        }
        .modal-btn:hover { background: #f5f5f5; }
        .modal-btn-red { background: var(--red); color: #fff; border-color: var(--red); }
        .modal-btn-red:hover { background: #dc2626; }

        /* ═══════════════════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .charts-row, .mid-row, .tables-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .stats-row { grid-template-columns: 1fr; }
            .main { padding: 20px 16px; }
            .header { padding: 0 16px; }
        }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .stat-card, .card, .table-card {
            animation: fadeUp 0.5s ease forwards;
        }
        .stat-card:nth-child(2) { animation-delay: 0.05s; }
        .stat-card:nth-child(3) { animation-delay: 0.1s; }
        .stat-card:nth-child(4) { animation-delay: 0.15s; }
    </style>
</head>
<body>

    <!-- ═══ HEADER ═══════════════════════════════════════════ -->
    <header class="header">
        <div class="header-left">
            <span class="brand">CodeCanvas</span>
            <span class="admin-tag">Admin Panel</span>
        </div>
        <div class="header-right">
            <a href="<?= BASE_URL ?>/app/dashboard.php" class="header-btn">← Dashboard</a>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($admin['name']) ?></div>
                <div class="user-role">Administrator</div>
            </div>
            <div class="avatar"><?= $admin['initials'] ?></div>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="header-btn header-btn-danger">Logout</a>
        </div>
    </header>

    <!-- ═══ MAIN CONTENT ════════════════════════════════════ -->
    <main class="main">

        <div class="page-header">
            <h1 class="page-title">Dashboard Overview</h1>
            <p class="page-sub">Welcome back, <?= htmlspecialchars($admin['name']) ?>. Here's what's happening.</p>
        </div>

        <!-- ═══ STAT CARDS ══════════════════════════════════ -->
        <div class="stats-row">
            <div class="stat-card green">
                <div class="stat-icon green">👥</div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= $totalUsers ?></div>
                <div class="stat-meta">
                    <span class="stat-pill pill-green"><?= $activeUsers ?> active</span>
                    <span style="color: #ccc;">•</span>
                    <span style="color: var(--muted); font-size: 12px; font-weight: 600;"><?= $inactiveUsers ?> inactive</span>
                </div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon blue">📁</div>
                <div class="stat-label">Total Portfolios</div>
                <div class="stat-value"><?= $totalProjects ?></div>
                <div class="stat-meta">
                    <span class="stat-pill pill-green"><?= $liveProjects ?> live</span>
                    <span style="color: #ccc;">•</span>
                    <span style="color: var(--muted); font-size: 12px; font-weight: 600;"><?= $draftProjects ?> draft</span>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green">🌐</div>
                <div class="stat-label">Live Portfolios</div>
                <div class="stat-value" style="color: var(--green);"><?= $liveProjects ?></div>
                <div class="stat-meta">
                    <span class="stat-pill pill-green"><?= $livePercent ?>% deployed</span>
                </div>
            </div>
            <div class="stat-card amber">
                <div class="stat-icon amber">💬</div>
                <div class="stat-label">Messages</div>
                <div class="stat-value"><?= $totalMessages ?></div>
                <div class="stat-meta">
                    <?php if ($unreadMessages > 0): ?>
                        <span class="stat-pill pill-amber"><?= $unreadMessages ?> unread</span>
                    <?php else: ?>
                        <span style="color: var(--muted); font-size: 12px; font-weight: 600;">All read ✓</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═══ CHARTS ══════════════════════════════════════ -->
        <div class="charts-row">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Growth — Last 7 Days</span>
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; color: var(--green);">
                            <span style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block;"></span> Users
                        </span>
                        <span style="display: flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; color: var(--blue);">
                            <span style="width:8px;height:8px;border-radius:50%;background:var(--blue);display:inline-block;"></span> Projects
                        </span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Portfolio Status</span>
                </div>
                <div class="donut-wrap">
                    <canvas id="statusChart"></canvas>
                    <div class="donut-center">
                        <div class="donut-val"><?= $totalProjects ?></div>
                        <div class="donut-label">Total</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ MID ROW: HEALTH + ACTIVITY ══════════════════ -->
        <div class="mid-row">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Platform Health</span>
                </div>
                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Active Users</span>
                        <span class="progress-value"><?= $activePercent ?>%</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill fill-green" style="width: <?= $activePercent ?>%;"></div></div>
                </div>
                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Live Portfolios</span>
                        <span class="progress-value"><?= $livePercent ?>%</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill fill-green" style="width: <?= $livePercent ?>%;"></div></div>
                </div>
                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Draft Portfolios</span>
                        <span class="progress-value"><?= $totalProjects > 0 ? round(($draftProjects / $totalProjects) * 100) : 0 ?>%</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill fill-amber" style="width: <?= $totalProjects > 0 ? round(($draftProjects / $totalProjects) * 100) : 0 ?>%;"></div></div>
                </div>
                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Failed Deploys</span>
                        <span class="progress-value"><?= $totalProjects > 0 ? round(($failedProjects / $totalProjects) * 100) : 0 ?>%</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill fill-red" style="width: <?= $totalProjects > 0 ? round(($failedProjects / $totalProjects) * 100) : 0 ?>%;"></div></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Recent Activity</span>
                </div>
                <?php
                // Merge users + projects into timeline
                $timeline = [];
                foreach (array_slice($recentUsers, 0, 3) as $u) {
                    $timeline[] = [
                        'type' => 'user',
                        'text' => '<strong>' . htmlspecialchars($u['name']) . '</strong> joined CodeCanvas',
                        'time' => $u['created_at'],
                        'dot' => 'dot-green'
                    ];
                }
                foreach (array_slice($recentProjects, 0, 3) as $p) {
                    $action = !empty($p['live_url']) ? 'deployed' : 'created';
                    $timeline[] = [
                        'type' => 'project',
                        'text' => '<strong>' . htmlspecialchars($p['user_name'] ?? 'User') . '</strong> ' . $action . ' "' . htmlspecialchars($p['project_name']) . '"',
                        'time' => $p['updated_at'],
                        'dot' => $action === 'deployed' ? 'dot-blue' : 'dot-amber'
                    ];
                }
                usort($timeline, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
                foreach (array_slice($timeline, 0, 5) as $item):
                ?>
                <div class="timeline-item">
                    <div class="timeline-dot <?= $item['dot'] ?>"></div>
                    <div>
                        <div class="timeline-text"><?= $item['text'] ?></div>
                        <div class="timeline-time"><?= date('M j, g:i A', strtotime($item['time'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($timeline)): ?>
                    <div style="text-align: center; padding: 40px 0; color: #ccc; font-size: 13px;">No recent activity</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ TABLES ══════════════════════════════════════ -->
        <div class="tables-row">
            <!-- Users Table -->
            <div class="table-card">
                <div class="table-card-header">
                    <span class="card-title">Users</span>
                    <span class="card-badge"><?= $totalUsers ?> total</span>
                </div>
                <table class="tbl">
                    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr id="user-row-<?= $u['id'] ?>">
                            <td>
                                <div class="td-bold"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="td-small"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td><span class="badge <?= $u['role'] === 'admin' ? 'b-admin' : 'b-user' ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><span class="badge <?= $u['status'] === 'active' ? 'b-active' : 'b-inactive' ?>"><?= ucfirst($u['status']) ?></span></td>
                            <td>
                                <?php if ($u['id'] != $admin['id']): ?>
                                <div class="tbl-actions">
                                    <button class="tbl-btn" onclick="toggleUser(<?= $u['id'] ?>,'<?= $u['status'] ?>')"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                    <button class="tbl-btn tbl-btn-danger" onclick="confirmDel('user',<?= $u['id'] ?>,'<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')">Delete</button>
                                </div>
                                <?php else: ?>
                                <span class="td-muted" style="font-size:10px;">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Projects Table -->
            <div class="table-card">
                <div class="table-card-header">
                    <span class="card-title">Recent Projects</span>
                    <span class="card-badge"><?= $totalProjects ?> total</span>
                </div>
                <table class="tbl">
                    <thead><tr><th>Project</th><th>Deploy</th><th>URL</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentProjects as $p):
                            $db = 'b-draft'; $dl = 'Draft';
                            if (in_array($p['publish_status'], ['deployed','published']) || !empty($p['live_url'])) { $db = 'b-live'; $dl = 'Live'; }
                            elseif (in_array($p['publish_status'], ['building','publishing'])) { $db = 'b-building'; $dl = 'Building'; }
                            elseif ($p['publish_status'] === 'failed') { $db = 'b-failed'; $dl = 'Failed'; }
                        ?>
                        <tr id="project-row-<?= $p['id'] ?>">
                            <td>
                                <div class="td-bold"><?= htmlspecialchars($p['project_name']) ?></div>
                                <div class="td-small"><?= htmlspecialchars($p['user_name'] ?? '') ?></div>
                            </td>
                            <td><span class="badge <?= $db ?>"><?= $dl ?></span></td>
                            <td>
                                <?php if (!empty($p['live_url'])): ?>
                                    <a href="<?= htmlspecialchars($p['live_url']) ?>" target="_blank" class="live-link">Visit ↗</a>
                                <?php else: ?>
                                    <span class="td-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="tbl-btn tbl-btn-danger" onclick="confirmDel('project',<?= $p['id'] ?>,'<?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Toast -->
    <div id="admin-toast"></div>

    <!-- Confirm Modal -->
    <div class="modal-bg" id="modal">
        <div class="modal-card">
            <h3 id="m-title">Are you sure?</h3>
            <p id="m-msg">This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="modal-btn" onclick="closeModal()">Cancel</button>
                <button class="modal-btn modal-btn-red" id="m-confirm">Delete</button>
            </div>
        </div>
    </div>

    <script>
    const BASE = '<?= BASE_URL ?>';

    // ═══ CHARTS ═══════════════════════════════════════════════
    Chart.defaults.font.family = 'Inter';

    // Growth Chart
    new Chart(document.getElementById('growthChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Users',
                    data: <?= json_encode($chartUserData) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                },
                {
                    label: 'Projects',
                    data: <?= json_encode($chartProjectData) ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.06)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11, weight: 600 }, color: '#ccc' }, grid: { color: '#f5f5f5' }, border: { display: false } },
                x: { ticks: { font: { size: 11, weight: 600 }, color: '#ccc' }, grid: { display: false }, border: { display: false } }
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });

    // Donut Chart
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Live', 'Draft', 'Failed'],
            datasets: [{
                data: [<?= $liveProjects ?>, <?= $draftProjects ?>, <?= $failedProjects ?>],
                backgroundColor: ['#10b981', '#e5e7eb', '#fca5a5'],
                borderWidth: 0,
                spacing: 3,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: 700 } }
                }
            }
        }
    });

    // ═══ TOAST ═════════════════════════════════════════════════
    function toast(msg, err = false) {
        const t = document.getElementById('admin-toast');
        t.textContent = msg;
        t.className = err ? 'error' : '';
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3000);
    }

    // ═══ MODAL ════════════════════════════════════════════════
    function openModal(title, msg, label, fn) {
        document.getElementById('m-title').textContent = title;
        document.getElementById('m-msg').textContent = msg;
        document.getElementById('m-confirm').textContent = label;
        document.getElementById('modal').classList.add('open');
        document.getElementById('m-confirm').onclick = () => { closeModal(); fn(); };
    }
    function closeModal() { document.getElementById('modal').classList.remove('open'); }

    // ═══ USER ACTIONS ═════════════════════════════════════════
    async function toggleUser(id, status) {
        const ns = status === 'active' ? 'inactive' : 'active';
        try {
            const r = await fetch(BASE + '/admin/api/users.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'toggle_status',user_id:id,status:ns}) });
            const d = await r.json();
            if (d.success) { toast('User ' + (ns === 'active' ? 'activated' : 'deactivated')); setTimeout(() => location.reload(), 600); }
            else toast(d.error || 'Failed', true);
        } catch(e) { toast('Network error', true); }
    }

    function confirmDel(type, id, name) {
        const label = type === 'user' ? 'Delete User' : 'Delete Project';
        const msg = type === 'user'
            ? `Delete "${name}"? All their projects will be removed.`
            : `Delete "${name}"? This cannot be undone.`;
        openModal(label, msg, label, () => execDel(type, id));
    }

    async function execDel(type, id) {
        const url = type === 'user' ? '/admin/api/users.php' : '/admin/api/projects.php';
        const body = type === 'user' ? {action:'delete',user_id:id} : {action:'delete',project_id:id};
        try {
            const r = await fetch(BASE + url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body) });
            const d = await r.json();
            if (d.success) {
                toast(type === 'user' ? 'User deleted' : 'Project deleted');
                const row = document.getElementById((type === 'user' ? 'user-row-' : 'project-row-') + id);
                if (row) { row.style.opacity='0'; row.style.transform='translateX(20px)'; row.style.transition='all 0.3s'; setTimeout(()=> row.remove(), 300); }
            } else toast(d.error || 'Failed', true);
        } catch(e) { toast('Network error', true); }
    }
    </script>
</body>
</html>
