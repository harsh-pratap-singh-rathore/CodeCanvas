<?php
require_once __DIR__ . '/../../config/bootstrap.php';
/**
 * Event: PortfolioMessageReceivedEvent
 *
 * Dispatched by app/api/contact.php after a visitor submits a message.
 * Notifies the portfolio owner via email.
 *
 * Usage:
 *   PortfolioMessageReceivedEvent::dispatch($ownerEmail, $ownerName, [
 *       'visitor_name'    => 'Jane Doe',
 *       'visitor_email'   => 'jane@example.com',
 *       'portfolio_name'  => 'My Design Portfolio',
 *       'message_preview' => 'Hi, I loved your work and...',
 *       'dashboard_link'  => 'https://codecanvas.page/app/messages.php?id=42',
 *   ]);
 */

require_once APP_ROOT . '/app/services/EmailQueue.php';
require_once APP_ROOT . '/storage/docs/emails/templates/newMessage.template.php';

class PortfolioMessageReceivedEvent
{
    /**
     * Dispatch the portfolio message received event.
     *
     * @param string $ownerEmail The portfolio owner's email
     * @param string $ownerName  The portfolio owner's name
     * @param array  $data       Context data — see @param of template
     */
    public static function dispatch(string $ownerEmail, string $ownerName, array $data): void
    {
        $subject = "📬 New message on your '{$data['portfolio_name']}' portfolio";

        $html = emailTemplateNewMessage(array_merge($data, [
            'owner_name' => $ownerName,
        ]));

        EmailQueue::enqueue($ownerEmail, $ownerName, $subject, $html);
    }
}
