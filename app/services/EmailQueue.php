<?php
require_once __DIR__ . '/../../config/bootstrap.php';
/**
 * EmailQueue — Future-Ready Async Email Stub
 *
 * Currently delegates to MailService synchronously.
 * Swap the internals of `enqueue()` to a real queue system
 * (Redis, DB queue, AWS SQS, etc.) when ready — zero changes
 * needed in any event class that calls this.
 *
 * Designed for scalability: 10,000+ users without code changes.
 */

require_once APP_ROOT . '/app/services/MailService.php';

class EmailQueue
{
    /**
     * Enqueue an email for delivery.
     *
     * Currently: synchronous (sends immediately).
     * Future: push to queue worker.
     *
     * @param string $to      Recipient email
     * @param string $toName  Recipient name
     * @param string $subject Subject line
     * @param string $html    HTML body
     * @return bool
     */
    public static function enqueue(string $to, string $toName, string $subject, string $html): bool
    {
        // ───────────────────────────────────────────────────────────
        // TODO (future): Push $payload to queue/job table instead.
        //
        // $payload = [
        //     'to'      => $to,
        //     'toName'  => $toName,
        //     'subject' => $subject,
        //     'html'    => $html,
        //     'queued_at' => time(),
        // ];
        // return QueueDriver::push('email', $payload);
        // ───────────────────────────────────────────────────────────

        // Current: synchronous pass-through
        return MailService::send($to, $toName, $subject, $html);
    }
}
