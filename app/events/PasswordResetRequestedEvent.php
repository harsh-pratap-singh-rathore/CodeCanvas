<?php
require_once __DIR__ . '/../../config/bootstrap.php';
/**
 * Event: PasswordResetRequestedEvent
 *
 * Dispatched by auth/forgot-password.php after OTP is generated.
 * Sends the OTP email via EmailQueue.
 *
 * Usage:
 *   PasswordResetRequestedEvent::dispatch($email, $name, $otpCode);
 */

require_once APP_ROOT . '/app/services/EmailQueue.php';
require_once APP_ROOT . '/storage/docs/emails/templates/otp.template.php';

class PasswordResetRequestedEvent
{
    /**
     * Dispatch the password reset requested event.
     *
     * @param string $email   User's email address
     * @param string $name    User's display name
     * @param string $otpCode The plaintext 6-digit OTP (NOT the hash)
     */
    public static function dispatch(string $email, string $name, string $otpCode): void
    {
        $subject = 'Your CodeCanvas Password Reset Code';

        $html = emailTemplateOtp([
            'name'           => $name,
            'otp_code'       => $otpCode,
            'expiry_minutes' => 10,
        ]);

        EmailQueue::enqueue($email, $name, $subject, $html);
    }
}
