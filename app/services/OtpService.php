<?php
require_once __DIR__ . '/../../config/bootstrap.php';
/**
 * OtpService — CodeCanvas
 *
 * Single-responsibility service for all OTP operations.
 * Controllers NEVER handle OTP logic directly — they only call this service.
 */
class OtpService
{
    private const OTP_EXPIRY_SECONDS  = 600;  // 10 minutes
    private const RATE_MAX_REQUESTS   = 3;
    private const RATE_WINDOW_SECONDS = 900;  // 15 minutes

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a cryptographically secure 6-digit OTP string.
     */
    public static function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }

    /**
     * Hash and store an OTP for a given email.
     * Auto-creates the otp_tokens table if missing.
     * Invalidates any previous unused tokens before inserting.
     */
    public static function storeOtp(PDO $pdo, string $email, string $otpPlain): bool
    {
        try {
            // Auto-create table if it doesn't exist (safety net)
            self::ensureTable($pdo);

            // Invalidate all previous unused tokens for this email
            $pdo->prepare("UPDATE otp_tokens SET used = 1 WHERE email = ? AND used = 0")
                ->execute([$email]);

            // Hash the OTP
            $hash = password_hash($otpPlain, PASSWORD_DEFAULT);

            // *** TIMEZONE FIX ***
            // expires_at is computed by MySQL (DATE_ADD(NOW(), INTERVAL 10 MINUTE))
            // so it uses the exact same timezone as the WHERE expires_at > NOW() check.
            // Never use PHP date() here — PHP and MySQL may run in different timezones.
            $pdo->prepare("
                INSERT INTO otp_tokens (email, token_hash, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
            ")->execute([$email, $hash]);

            error_log("[OtpService] Token stored for {$email} (expires in 10 min, MySQL time)");
            return true;

        } catch (PDOException $e) {
            error_log('[OtpService::storeOtp] DB Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify a submitted OTP against all valid stored tokens.
     * Fetches all non-expired, unused tokens and tries password_verify on each.
     * On match, marks ALL unused tokens for the email as used.
     */
    public static function verifyOtp(PDO $pdo, string $email, string $otpPlain): bool
    {
        try {
            self::ensureTable($pdo);

            $stmt = $pdo->prepare("
                SELECT id, token_hash
                FROM   otp_tokens
                WHERE  email      = ?
                  AND  used       = 0
                  AND  expires_at > NOW()
                ORDER  BY created_at DESC
            ");
            $stmt->execute([$email]);
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($tokens)) {
                error_log("[OtpService] verifyOtp: no valid tokens found for {$email}");
                return false;
            }

            error_log("[OtpService] verifyOtp: checking " . count($tokens) . " token(s) for {$email}");

            foreach ($tokens as $token) {
                if (password_verify($otpPlain, $token['token_hash'])) {
                    // Match — mark ALL unused tokens for this email as used
                    $pdo->prepare("UPDATE otp_tokens SET used = 1 WHERE email = ? AND used = 0")
                        ->execute([$email]);
                    error_log("[OtpService] verifyOtp: SUCCESS for {$email}");
                    return true;
                }
            }

            error_log("[OtpService] verifyOtp: wrong code submitted for {$email}");
            return false;

        } catch (PDOException $e) {
            error_log('[OtpService::verifyOtp] DB Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Session-based rate limit: max 3 OTP requests per 15 min per email.
     * Returns true if allowed, false if limit hit.
     */
    public static function checkRateLimit(string $email): bool
    {
        $sessionKey = 'otp_rate_' . md5(strtolower(trim($email)));
        $now        = time();

        $attempts = $_SESSION[$sessionKey] ?? [];
        $attempts = array_values(
            array_filter($attempts, fn($t) => ($now - $t) < self::RATE_WINDOW_SECONDS)
        );

        if (count($attempts) >= self::RATE_MAX_REQUESTS) {
            return false;
        }

        $attempts[]            = $now;
        $_SESSION[$sessionKey] = $attempts;
        return true;
    }

    /**
     * Returns remaining cooldown seconds, or 0 if not limited.
     */
    public static function rateLimitCooldown(string $email): int
    {
        $sessionKey = 'otp_rate_' . md5(strtolower(trim($email)));
        $now        = time();

        $attempts = array_filter(
            $_SESSION[$sessionKey] ?? [],
            fn($t) => ($now - $t) < self::RATE_WINDOW_SECONDS
        );

        if (count($attempts) < self::RATE_MAX_REQUESTS) {
            return 0;
        }

        return self::RATE_WINDOW_SECONDS - ($now - min($attempts));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Auto-create the otp_tokens table if it doesn't exist.
     * Safety net — protects against forgotten migration.
     */
    private static function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `otp_tokens` (
                `id`         INT          NOT NULL AUTO_INCREMENT,
                `email`      VARCHAR(255) NOT NULL,
                `token_hash` VARCHAR(255) NOT NULL,
                `expires_at` DATETIME     NOT NULL,
                `used`       TINYINT(1)   NOT NULL DEFAULT 0,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_email_used`  (`email`, `used`),
                INDEX `idx_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
