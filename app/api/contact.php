<?php
/**
 * CONTACT API
 * Handles message submissions from published portfolios.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/events/PortfolioMessageReceivedEvent.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }

    $projectId    = trim($input['project_id'] ?? '');
    $visitorName  = trim($input['name'] ?? '');
    $visitorEmail = trim($input['email'] ?? '');
    $subject      = trim($input['subject'] ?? 'New Message from Portfolio');
    $messageText  = trim($input['message'] ?? '');

    if (!$projectId || !$visitorName || !$visitorEmail || !$messageText) {
        throw new Exception('Missing required fields.');
    }

    // 1. Verify Project & Get Owner
    $stmt = $pdo->prepare("SELECT user_id, project_name FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();

    if (!$project) {
        throw new Exception('Invalid project ID.');
    }

    $userId = $project['user_id'];

    // 2. Save Message
    $ins = $pdo->prepare("
        INSERT INTO messages (project_id, visitor_name, visitor_email, subject, message)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute([$projectId, $visitorName, $visitorEmail, $subject, $messageText]);
    $messageId = $pdo->lastInsertId();

    // 3. Create Notification for Owner
    $notif = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, content, link)
        VALUES (?, 'message', ?, ?, ?)
    ");
    $title = "New Message: " . $visitorName;
    $content = "You received a new message on your project: " . $project['project_name'];
    $link = "messages.php?id=" . $messageId;
    
    $notif->execute([$userId, $title, $content, $link]);

    echo json_encode([
        'success' => true,
        'message' => 'Your message has been sent successfully!'
    ]);

    // Fetch portfolio owner's email + name, then dispatch email notification
    // This is outside the main try block to ensure it never breaks the user response
    try {
        $ownerStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $ownerStmt->execute([$userId]);
        $owner = $ownerStmt->fetch();

        if ($owner) {
            $dashboardLink = BASE_URL . '/app/messages.php';
            $preview = mb_substr($messageText, 0, 300);

            PortfolioMessageReceivedEvent::dispatch($owner['email'], $owner['name'], [
                'visitor_name'    => $visitorName,
                'visitor_email'   => $visitorEmail,
                'portfolio_name'  => $project['project_name'],
                'message_preview' => $preview,
                'dashboard_link'  => $dashboardLink,
            ]);
        }
    } catch (Exception $e) {
        // Email failure must never affect the visitor's form submission
        error_log('[Contact] Email dispatch error: ' . $e->getMessage());
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
