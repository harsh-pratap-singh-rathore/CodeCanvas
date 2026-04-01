<?php
/**
 * Auth — Forgot Password  (thin controller)
 *
 * Accepts: POST { email }
 *
 * All OTP logic is handled by OtpService.
 * This file only: validates input → calls service → dispatches event → responds.
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/OtpService.php';
require_once __DIR__ . '/../events/PasswordResetRequestedEvent.php';

header('Content-Type: application/json');

// Generic success msg — always the same to prevent email enumeration
const GENERIC_SUCCESS = 'If an account exists for this email, a reset code has been sent.';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));

// ─── 1. Validate email format ─────────────────────────────────────────────────
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

// ─── 2. Rate limit ────────────────────────────────────────────────────────────
if (!OtpService::checkRateLimit($email)) {
    $cooldown = OtpService::rateLimitCooldown($email);
    $minutes  = ceil($cooldown / 60);
    echo json_encode([
        'success' => false,
        'message' => "Too many reset requests. Please wait {$minutes} minutes and try again.",
    ]);
    exit;
}

// ─── 3. Look up user ─────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT id, name, auth_provider FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Anti-enumeration: identical response whether email exists or not
    if (!$user) {
        usleep(random_int(100000, 300000)); // Timing noise
        echo json_encode(['success' => true, 'message' => GENERIC_SUCCESS]);
        exit;
    }

    // Allow OTP generation for Google accounts too as requested by the user.
    // (removed block here)

    // ─── 4. Generate + Store OTP ─────────────────────────────────────────────
    $otpPlain = OtpService::generateOtp();
    $stored   = OtpService::storeOtp($pdo, $email, $otpPlain);

    if (!$stored) {
        echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
        exit;
    }

    // ─── 5. Send OTP email (failure must not block the response) ─────────────
    PasswordResetRequestedEvent::dispatch($email, $user['name'], $otpPlain);

    echo json_encode(['success' => true, 'message' => GENERIC_SUCCESS]);

} catch (PDOException $e) {
    error_log('[ForgotPassword] DB Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
