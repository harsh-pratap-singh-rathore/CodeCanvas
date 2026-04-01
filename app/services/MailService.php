<?php
/**
 * MailService — CodeCanvas Transactional Email Service
 *
 * Single-responsibility mail-sending class.
 * All email dispatch in the platform goes through this class.
 *
 * Usage:
 *   MailService::send('user@example.com', 'John', 'Subject', $htmlBody);
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// (removed invalid use statement)
use Resend\Client;

class MailService
{
    private static ?Client $client = null;

    /**
     * Returns a shared Resend client instance (singleton).
     */
    private static function getClient(): Client
    {
        if (self::$client === null) {
            self::$client = Resend::client(RESEND_API_KEY);
        }
        return self::$client;
    }

    /**
     * Send an HTML email.
     *
     * @param string $to      Recipient email address
     * @param string $toName  Recipient display name
     * @param string $subject Email subject line
     * @param string $html    Full HTML body
     * @return bool           true on success, false on failure
     */
    public static function send(string $to, string $toName, string $subject, string $html): bool
    {
        // Basic email validation
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::logError("Invalid email address: {$to}");
            return false;
        }

        try {
            $client = self::getClient();

            $client->emails->send([
                'from'    => MAIL_FROM,
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $html,
            ]);

            return true;

        } catch (\Resend\Exceptions\ResendException $e) {
            self::logError("Resend API error sending to {$to}: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            self::logError("Unexpected error sending to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log email failures to a protected log file.
     * User flows must NOT be interrupted by email failures.
     */
    private static function logError(string $message): void
    {
        $logDir  = __DIR__ . '/../../logs';
        $logFile = $logDir . '/email_errors.log';

        // Ensure log directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $entry     = "[{$timestamp}] [MailService] {$message}" . PHP_EOL;

        error_log($entry, 3, $logFile);
    }
}
