<?php
/**
 * Auth — Request Password Change (Logged-in Users)
 *
 * Accepts: POST (no body required — reads email from session)
 *
 * Used when a logged-in user clicks "Change Password" in profile settings.
 * Triggers the same OTP flow as forgot-password, using the session email.
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/OtpService.php';
require_once __DIR__ . '/../events/PasswordResetRequestedEvent.php';
require_once __DIR__ . '/../core/auth.php'; // Ensures user is logged in

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ─── 1. Get email from authenticated session ───────────────────────────────
$email = strtolower(trim($_SESSION['user_email'] ?? ''));
$name  = $_SESSION['user_name'] ?? 'User';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

// ─── 2. Rate limit (same rules as forgot-password) ────────────────────────
if (!OtpService::checkRateLimit($email)) {
    $cooldown = OtpService::rateLimitCooldown($email);
    $minutes  = ceil($cooldown / 60);
    echo json_encode([
        'success' => false,
        'message' => "Too many requests. Please wait {$minutes} minutes.",
    ]);
    exit;
}

// ─── 3. Generate + store OTP ───────────────────────────────────────────────
try {
    $otpPlain = OtpService::generateOtp();
    $stored   = OtpService::storeOtp($pdo, $email, $otpPlain);

    if (!$stored) {
        echo json_encode(['success' => false, 'message' => 'Could not generate reset code. Try again.']);
        exit;
    }

    // ─── 4. Dispatch email event ───────────────────────────────────────────
    PasswordResetRequestedEvent::dispatch($email, $name, $otpPlain);

    echo json_encode([
        'success'  => true,
        'message'  => 'A verification code has been sent to your email.',
        'email'    => $email,  // Frontend uses this to pre-fill verify-reset.html
        'redirect' => BASE_URL . '/public/verify-reset.html?email=' . urlencode($email),
    ]);

} catch (PDOException $e) {
    error_log('[RequestPasswordChange] DB Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}
