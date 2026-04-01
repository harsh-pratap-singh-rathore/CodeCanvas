<?php
require_once __DIR__ . '/../../config/bootstrap.php';
/**
 * Event: PortfolioDeployedEvent
 *
 * Dispatched by project-publish.php after a successful Vercel deployment.
 * Sends a notification email to the user.
 *
 * Usage:
 *   require_once APP_ROOT . '/app/events/PortfolioDeployedEvent.php';
 *   PortfolioDeployedEvent::dispatch($email, $userName, $projectName, $liveUrl);
 */

require_once APP_ROOT . '/app/services/EmailQueue.php';
require_once APP_ROOT . '/storage/docs/emails/templates/deployed.template.php';

class PortfolioDeployedEvent
{
    /**
     * Dispatch the portfolio deployed event.
     *
     * @param string $email       User's email
     * @param string $userName    User's name
     * @param string $projectName Name of the project
     * @param string $liveUrl     Final live URL
     */
    public static function dispatch(string $email, string $userName, string $projectName, string $liveUrl): void
    {
        $subject = "🚀 Your Portfolio is Live: {$projectName}";

        $html = emailTemplateDeployed([
            'name'        => $userName,
            'projectName' => $projectName,
            'liveUrl'     => $liveUrl,
        ]);

        // We use EmailQueue to send asynchronously (via cron/background if configured)
        // or synchronously if EmailQueue::enqueue() processes immediately.
        EmailQueue::enqueue($email, $userName, $subject, $html);
    }
}
