<?php
/**
 * APP - PROFILE & SETTINGS
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['full_name']);
        if (empty($name)) {
            $error = "Name cannot be empty.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                $stmt->execute([$name, $userId]);
                $_SESSION['user_name'] = $name;
                $success = "Profile updated successfully.";
            } catch (PDOException $e) {
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
    // NOTE: Password changes are handled via OTP flow through
    // auth/request-password-change.php — no direct password change allowed here.

}

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$initials = strtoupper(substr($userData['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile — CodeCanvas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css">
    <style>
        body { background: #FAFAFA; color: #111; font-family: 'DM Sans', sans-serif; }
        .profile-container { max-width: 600px; margin: 60px auto; padding: 0 24px; }
        .profile-card { background: #fff; border: 1px solid #E5E5E5; border-radius: 8px; padding: 32px; margin-bottom: 24px; }
        .profile-header { display: flex; align-items: center; gap: 20px; margin-bottom: 32px; }
        .profile-avatar-large { 
            width: 80px; height: 80px; background: #000; color: #fff; 
            border-radius: 50%; display: flex; align-items: center; 
            justify-content: center; font-size: 32px; font-weight: 700;
        }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; }
        .form-input { 
            width: 100%; padding: 10px 12px; border: 1px solid #e5e5e5; 
            border-radius: 6px; font-size: 14px; font-family: inherit; box-sizing: border-box;
        }
        .form-input:focus { outline: none; border-color: #000; }
        .btn-save { 
            background: #000; color: #fff; border: none; padding: 10px 24px; 
            border-radius: 6px; font-weight: 500; cursor: pointer; 
        }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 24px; font-size: 14px; }
        .alert-success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .alert-error { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }
    </style>
</head>
<body>

    <header class="dashboard-header">
        <div class="dashboard-header-content">
            <a href="<?= BASE_URL ?>/dashboard.php" class="logo">CodeCanvas</a>
            <div style="flex: 1;"></div>
            <a href="<?= BASE_URL ?>/dashboard.php" style="font-size: 13px; color: #666; text-decoration: none;">Back to Dashboard</a>
        </div>
    </header>

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-avatar-large"><?= $initials ?></div>
            <div>
                <h1 style="font-size: 24px; font-weight: 700;"><?= htmlspecialchars($userData['name']) ?></h1>
                <p style="color: #666; font-size: 14px;"><?= htmlspecialchars($userData['email']) ?></p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Basic Info -->
        <div class="profile-card">
            <h3 style="margin-bottom: 20px; font-size: 16px;">Account Settings</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" value="<?= htmlspecialchars($userData['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" value="<?= htmlspecialchars($userData['email']) ?>" readonly style="background: #F5F5F5; cursor: not-allowed;">
                    <p style="font-size: 11px; color: #888; margin-top: 4px;">Email cannot be changed.</p>
                </div>
                <button type="submit" class="btn-save">Update Profile</button>
            </form>
        </div>

        <!-- Security — OTP-based password change -->
        <div class="profile-card">
            <h3 style="margin-bottom: 8px; font-size: 16px;">Security</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 20px;">
                Password changes require verification via email. A 6-digit code will be sent to
                <strong><?= htmlspecialchars($userData['email']) ?></strong>.
            </p>
            <button id="changePwBtn" onclick="requestPasswordChange()"
                    style="background:#000;color:#fff;border:none;padding:10px 20px;
                           border-radius:6px;font-weight:500;cursor:pointer;font-size:14px;">
                Change Password
            </button>
            <p id="changePwMsg" style="margin-top:12px;font-size:13px;display:none;"></p>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/public/assets/js/mobile-nav.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
    async function requestPasswordChange() {
        const btn = document.getElementById('changePwBtn');
        const msg = document.getElementById('changePwMsg');
        btn.disabled    = true;
        btn.textContent = 'Sending code…';
        msg.style.display = 'none';

        try {
            const res  = await fetch(BASE_URL + '/auth/request-password-change.php', { method: 'POST' });
            const data = await res.json();

            if (data.success) {
                msg.style.color   = '#166534';
                msg.textContent   = '✅ Code sent! Redirecting…';
                msg.style.display = 'block';
                setTimeout(() => { window.location.href = data.redirect; }, 1500);
            } else {
                msg.style.color   = '#991B1B';
                msg.textContent   = '❌ ' + (data.message || 'Could not send code. Try again.');
                msg.style.display = 'block';
                btn.disabled    = false;
                btn.textContent = 'Change Password';
            }
        } catch {
            msg.style.color   = '#991B1B';
            msg.textContent   = '❌ Network error. Please try again.';
            msg.style.display = 'block';
            btn.disabled    = false;
            btn.textContent = 'Change Password';
        }
    }
    </script>
</body>
</html>
