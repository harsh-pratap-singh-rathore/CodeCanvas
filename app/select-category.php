<?php
/**
 * SELECT CATEGORY — Step 1 of Template Selection
 * Flow: Category Selection → Template List → Modal (Preview / Edit) → Editor
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

$user = [
    'name'     => $_SESSION['user_name'],
    'email'    => $_SESSION['user_email'],
    'initials' => strtoupper(substr($_SESSION['user_name'], 0, 1))
];

$categories = [
    [
        'slug'        => 'developer',
        'label'       => 'Developer',
        'description' => 'Portfolio sites for developers and engineers.',
        'icon'        => '&lt;/&gt;',
    ],
    [
        'slug'        => 'business',
        'label'       => 'Business',
        'description' => 'Landing pages for businesses and services.',
        'icon'        => '&#9783;',
    ],
    [
        'slug'        => 'shop',
        'label'       => 'Shop',
        'description' => 'Product and e-commerce storefront pages.',
        'icon'        => '&#128722;',
    ],
    [
        'slug'        => 'normal',
        'label'       => 'Normal',
        'description' => 'Simple personal and bio pages.',
        'icon'        => '&#9786;',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Category — CodeCanvas</title>
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

        /* ── Category Grid ───────────────────────────────────── */
        .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }

        .cat-card {
            border: 1px solid #E5E5E5;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
            background: #fff;
            text-decoration: none;
            display: block;
        }
        .cat-card:hover {
            border-color: #000;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
        }

        .cat-icon {
            width: 100%;
            aspect-ratio: 4/3;
            background: #0a0a0a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: rgba(255,255,255,.7);
            font-family: monospace;
            letter-spacing: -0.02em;
            user-select: none;
        }

        .cat-info {
            padding: 16px;
            border-top: 1px solid #F0F0F0;
        }
        .cat-name {
            font-size: 14px;
            font-weight: 600;
            color: #111;
            margin-bottom: 4px;
        }
        .cat-desc {
            font-size: 12px;
            color: #888;
            line-height: 1.4;
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
        <h1 class="np-heading">Choose a Category</h1>
        <p class="np-sub">Select the type of website you want to build.</p>

        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
            <a class="cat-card" href="<?= BASE_URL ?>/select-template.php?category=<?= urlencode($cat['slug']) ?>">
                <div class="cat-icon"><?= $cat['icon'] ?></div>
                <div class="cat-info">
                    <div class="cat-name"><?= htmlspecialchars($cat['label']) ?></div>
                    <div class="cat-desc"><?= htmlspecialchars($cat['description']) ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/navigation.js"></script>
    <script src="<?= BASE_URL ?>/public/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        // ── Navigation state: record category selection ──────────
        (function () {
            var cards = document.querySelectorAll('.cat-card');
            cards.forEach(function (card) {
                card.addEventListener('click', function (e) {
                    // Extract category slug from the href query string
                    var href = card.getAttribute('href') || '';
                    var match = href.match(/[?&]category=([^&]+)/);
                    if (match) {
                        NavManager.setCategory(decodeURIComponent(match[1]));
                    }
                    // Navigation continues normally (no preventDefault)
                });
            });

            // Mark this page in cookie so we know where the user was
            NavManager.setCookie('lastVisitedPage', 'select-category');

            // Push history state so back-button from template page works
            NavManager.pushState('category');
        }());
    </script>
</body>
</html>
