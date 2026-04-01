<?php
require_once __DIR__ . '/../../config/bootstrap.php';
/**
 * Event: UserRegisteredEvent
 *
 * Dispatched by auth/signup.php after a user is created.
 * Sends the Welcome email via EmailQueue.
 *
 * Usage:
 *   require_once APP_ROOT . '/app/events/UserRegisteredEvent.php';
 *   UserRegisteredEvent::dispatch($email, $name);
 */

require_once APP_ROOT . '/app/services/EmailQueue.php';
require_once APP_ROOT . '/storage/docs/emails/templates/welcome.template.php';

class UserRegisteredEvent
{
    /**
     * Dispatch the user registered event.
     *
     * @param string $email New user's email address
     * @param string $name  New user's display name
     */
    public static function dispatch(string $email, string $name): void
    {
        $subject = 'Welcome to CodeCanvas — Your Portfolio Builder';

        $html = emailTemplateWelcome([
            'name'  => $name,
            'email' => $email,
        ]);

        EmailQueue::enqueue($email, $name, $subject, $html);
    }
}
