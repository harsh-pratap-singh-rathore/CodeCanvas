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
    <title>Your Profile — CodeCanvas v2</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/public/assets/images/logo.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css?v=2.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/responsive.css?v=2.0">
    
    <style>
        body { font-family: 'Inter', sans-serif; background: #fafafa; color: #0a0a0a; line-height: 1.6; }
        
        /* Premium Dashboard Header */
        .dashboard-header {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05) !important;
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 0 24px;
        }
        
        .dashboard-header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        .logo-v2 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #0a0a0a;
            text-decoration: none;
        }

        .badge-v2 {
            font-size: 10px;
            font-weight: 800;
            background: transparent;
            color: #0a0a0a;
            border: 1.5px solid #0a0a0a;
            padding: 2px 8px;
            border-radius: 99px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-family: 'Inter', sans-serif;
        }

        .btn-back {
            background: transparent; color: #0a0a0a; border: 1px solid #e5e5e5; border-radius: 99px; padding: 8px 16px; font-weight: 600; text-decoration: none; font-size: 13px; transition: all 0.2s;
        }

        .btn-back:hover {
            border-color: #0a0a0a; background: #fff;
        }

        /* Profile Container */
        .profile-container { max-width: 600px; margin: 80px auto; padding: 0 24px; }
        
        .profile-header { display: flex; align-items: center; gap: 24px; margin-bottom: 40px; animation: fadeUp 0.6s ease-out; }
        
        .profile-avatar-large { 
            width: 88px; height: 88px; background: #0a0a0a; color: #fff; 
            border-radius: 50%; display: flex; align-items: center; 
            justify-content: center; font-size: 32px; font-weight: 700; font-family: 'Outfit', sans-serif;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .profile-name { font-family: 'Outfit', sans-serif; font-size: 32px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 4px; }
        .profile-email { color: #6b6b6b; font-size: 15px; font-weight: 500; }

        .profile-card { 
            background: #fff; border: 1px solid #e5e5e5; border-radius: 20px; padding: 40px; margin-bottom: 24px; 
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.03); animation: fadeUp 0.8s ease-out;
        }

        .profile-card h3 { font-family: 'Outfit', sans-serif; font-size: 20px; margin-bottom: 24px; border-bottom: 1px solid #f0f0f0; padding-bottom: 16px; }

        .form-group-v2 { margin-bottom: 24px; }
        .form-label-v2 { display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #0a0a0a; }
        .form-input-v2 { 
            width: 100%; padding: 14px 16px; border: 1px solid transparent; background: #f9f9f9;
            border-radius: 12px; font-size: 15px; font-family: 'Inter', sans-serif; box-sizing: border-box; transition: all 0.2s;
        }
        .form-input-v2:focus { outline: none; border-color: #0a0a0a; background: #fff; }
        
        .form-input-readonly { background: #f0f0f0 !important; color: #6b6b6b; border: 1px solid #e5e5e5; cursor: not-allowed; }

        .btn-save-v2 { 
            background: #0a0a0a; color: #fff; border: none; padding: 12px 24px; 
            border-radius: 99px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; font-size: 14px;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-save-v2:hover { transform: scale(1.02); opacity: 0.9; }

        .alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 32px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 12px; animation: fadeUp 0.4s ease-out; }
        .alert-success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .alert-error { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <header class="dashboard-header">
        <div class="dashboard-header-content">
            <a href="<?= BASE_URL ?>/app/dashboard.php" class="logo-v2">
                CodeCanvas <span class="badge-v2">v2</span>
            </a>
            <a href="<?= BASE_URL ?>/app/dashboard.php" class="btn-back">Return to Dashboard</a>
        </div>
    </header>

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-avatar-large"><?= $initials ?></div>
            <div>
                <div class="profile-name"><?= htmlspecialchars($userData['name']) ?></div>
                <div class="profile-email"><?= htmlspecialchars($userData['email']) ?></div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Basic Info -->
        <div class="profile-card">
            <h3>Account Settings</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group-v2">
                    <label class="form-label-v2">Full Name</label>
                    <input type="text" name="full_name" class="form-input-v2" autocomplete="off" value="<?= htmlspecialchars($userData['name']) ?>" required>
                </div>
                <div class="form-group-v2">
                    <label class="form-label-v2">Email Address</label>
                    <input type="email" class="form-input-v2 form-input-readonly" value="<?= htmlspecialchars($userData['email']) ?>" readonly>
                    <p style="font-size: 12px; color: #6b6b6b; margin-top: 6px; font-weight: 500;">Email address is permanently bound to this account.</p>
                </div>
                <button type="submit" class="btn-save-v2">
                    Save Changes
                </button>
            </form>
        </div>

        <!-- Security — OTP-based password change -->
        <div class="profile-card">
            <h3 style="border-bottom: none; margin-bottom: 8px;">Security & Authentication</h3>
            <p style="font-size: 14px; color: #6b6b6b; margin-bottom: 24px; line-height: 1.6;">
                For your security, CodeCanvas enforces 2-Factor authentication for password changes. A secure 6-digit confirmation code will be dispatched to <strong><?= htmlspecialchars($userData['email']) ?></strong>.
            </p>
            <button id="changePwBtn" onclick="requestPasswordChange()" class="btn-save-v2" style="background: white; color: #0a0a0a; border: 1px solid #e5e5e5; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0110 0v4"></path></svg>
                Request Password Change
            </button>
            <p id="changePwMsg" style="margin-top:16px; font-size:14px; font-weight: 500; display:none;"></p>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/main.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        
        async function requestPasswordChange() {
            const btn = document.getElementById('changePwBtn');
            const msg = document.getElementById('changePwMsg');
            btn.disabled    = true;
            btn.innerHTML = 'Dispatching code...';
            msg.style.display = 'none';

            try {
                const res  = await fetch(BASE_URL + '/auth/request-password-change.php', { method: 'POST' });
                const data = await res.json();

                if (data.success) {
                    msg.style.color   = '#166534';
                    msg.innerHTML   = '<span style="display:flex;align-items:center;gap:6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Code sent! Redirecting securely...</span>';
                    msg.style.display = 'block';
                    setTimeout(() => { window.location.href = data.redirect; }, 1500);
                } else {
                    msg.style.color   = '#991B1B';
                    msg.textContent   = 'Error: ' + (data.message || 'Could not dispatch code. Try again.');
                    msg.style.display = 'block';
                    btn.disabled    = false;
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0110 0v4"></path></svg> Request Password Change';
                }
            } catch {
                msg.style.color   = '#991B1B';
                msg.textContent   = 'Network latency error. Please try again.';
                msg.style.display = 'block';
                btn.disabled    = false;
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0110 0v4"></path></svg> Request Password Change';
            }
        }
    </script>
</body>
</html>
