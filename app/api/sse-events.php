<?php
/**
 * API: Server-Sent Events (SSE) for Real-Time Updates
 * Keeps connection open and streams new data.
 */

session_start();
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';

// Release session lock so the user can continue navigating the site 
// while this long-polling script runs in the background.
$user_id = $_SESSION['user_id'] ?? null;
session_write_close();
set_time_limit(0);

if (!$user_id) {
    echo "data: {\"error\": \"Unauthorized\"}\n\n";
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
// Optional: Disable output buffering for SSE
if (ob_get_level()) {
    ob_end_clean();
}

$lastMessageTime = null;
$lastNotifTime = null;

// Initial fetch to get max timestamps
try {
    $stmt = $pdo->prepare("SELECT MAX(created_at) FROM messages m JOIN projects p ON m.project_id = p.id WHERE p.user_id = ?");
    $stmt->execute([$user_id]);
    $lastMessageTime = $stmt->fetchColumn() ?: '1970-01-01 00:00:00';

    $stmt2 = $pdo->prepare("SELECT MAX(created_at) FROM notifications WHERE user_id = ?");
    $stmt2->execute([$user_id]);
    $lastNotifTime = $stmt2->fetchColumn() ?: '1970-01-01 00:00:00';
} catch (Exception $e) {}

// Send a heart-beat right away to establish connection
echo "event: ping\ndata: {}\n\n";
flush();

// Long-polling loop
while (true) {
    // 1. Check if client disconnected
    if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
        break;
    }

    try {
        $hasNew = false;
        $payload = ['messages' => false, 'notifications' => false];

        // Check for new messages
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages m JOIN projects p ON m.project_id = p.id WHERE p.user_id = ? AND m.created_at > ?");
        $stmt->execute([$user_id, $lastMessageTime]);
        if ($stmt->fetchColumn() > 0) {
            $hasNew = true;
            $payload['messages'] = true;

            // Update local time
            $s = $pdo->prepare("SELECT MAX(created_at) FROM messages m JOIN projects p ON m.project_id = p.id WHERE p.user_id = ?");
            $s->execute([$user_id]);
            $lastMessageTime = $s->fetchColumn();
        }

        // Check for new notifications
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND created_at > ?");
        $stmt2->execute([$user_id, $lastNotifTime]);
        if ($stmt2->fetchColumn() > 0) {
            $hasNew = true;
            $payload['notifications'] = true;

            // Update local time
            $s = $pdo->prepare("SELECT MAX(created_at) FROM notifications WHERE user_id = ?");
            $s->execute([$user_id]);
            $lastNotifTime = $s->fetchColumn();
        }

        if ($hasNew) {
            echo "event: update\n";
            echo "data: " . json_encode($payload) . "\n\n";
            flush();
        }

    } catch (Exception $e) {}

    // Sleep before checking again (e.g. 2 seconds)
    sleep(2);
}
