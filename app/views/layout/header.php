<?php
/**
 * Global Header Layout
 * Include at the top of any app page: require_once APP_ROOT . '/app/views/layout/header.php';
 *
 * Requires:
 *   - session_start() already called
 *   - BASE_URL defined (via config/app.php)
 *
 * Variables you can set BEFORE including this file:
 *   $pageTitle  — string, page <title> suffix
 */

$currentUser = $_SESSION['user_name']  ?? null;
$currentRole = $_SESSION['user_role']  ?? null;
$isLoggedIn  = !empty($_SESSION['user_id']);

$pageTitle = isset($pageTitle) ? ' — ' . htmlspecialchars($pageTitle) : '';
$homeHref  = $isLoggedIn
    ? BASE_URL . '/dashboard.php'
    : BASE_URL . '/public/index.html';
?>
<header class="cc-header">
    <div class="cc-header-inner">
        <!-- CC Wordmark Logo -->
        <a href="<?= htmlspecialchars($homeHref) ?>" class="cc-logo" aria-label="CodeCanvas Home">
            <span class="cc-logo-mark">CC</span>
            <span class="cc-logo-label">CodeCanvas</span>
        </a>

        <!-- Slot for page-level nav (populated by including page) -->
        <nav class="cc-header-nav" id="cc-header-nav"></nav>

        <!-- User section -->
        <?php if ($isLoggedIn): ?>
        <div class="cc-header-user">
            <span class="cc-avatar" aria-hidden="true">
                <?= strtoupper(substr($currentUser ?? 'U', 0, 1)) ?>
            </span>
            <span class="cc-username"><?= htmlspecialchars($currentUser ?? '') ?></span>
        </div>
        <?php endif; ?>
    </div>
</header>

<style>
/* ── Global Header Styles ─────────────────────────────────────────────── */
.cc-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: #fff;
    border-bottom: 1px solid #E5E5E5;
    height: 56px;
}
.cc-header-inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 16px;
}
/* CC Logo */
.cc-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    flex-shrink: 0;
}
.cc-logo-mark {
    width: 32px;
    height: 32px;
    background: #000;
    color: #fff;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: -0.5px;
    font-family: 'DM Sans', 'Inter', sans-serif;
}
.cc-logo-label {
    font-size: 15px;
    font-weight: 700;
    color: #000;
    letter-spacing: -0.3px;
    font-family: 'DM Sans', 'Inter', sans-serif;
}
.cc-logo:hover .cc-logo-label { opacity: 0.7; }
/* Nav slot */
.cc-header-nav {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 4px;
}
/* User chip */
.cc-header-user {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
    flex-shrink: 0;
}
.cc-avatar {
    width: 28px;
    height: 28px;
    background: #000;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}
.cc-username {
    font-size: 13px;
    font-weight: 500;
    color: #333;
}
</style>
