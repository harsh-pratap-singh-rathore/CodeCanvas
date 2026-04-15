<?php
/**
 * CodeCanvas — Email Diagnostic Tool
 * Run this in your browser to test if Resend API is configured correctly.
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/app/services/MailService.php';

// Ensure the logs directory exists
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$testEmail = $_GET['email'] ?? '';
$status = '';
$logContent = '';

if ($testEmail) {
    echo "<h2>Testing Email Sending...</h2>";
    echo "<p>To: " . htmlspecialchars($testEmail) . "</p>";
    
    $subject = "🧪 CodeCanvas Email Test: " . date('H:i:s');
    $body = "
        <div style='font-family: sans-serif; padding: 20px; border: 1px solid #10B981; border-radius: 8px;'>
            <h1 style='color: #10B981;'>Test Successful!</h1>
            <p>If you are reading this, your Resend API configuration is working correctly.</p>
            <hr>
            <p style='font-size: 12px; color: #666;'>Sent at: " . date('Y-m-d H:i:s') . "</p>
        </div>";

    $success = MailService::send($testEmail, "Test User", $subject, $body);

    if ($success) {
        $status = "<div style='color: green; font-weight: bold; margin: 20px 0;'>✅ SUCCESS! The email was sent to Resend API. Check your inbox.</div>";
    } else {
        $status = "<div style='color: red; font-weight: bold; margin: 20px 0;'>❌ FAILED! Check the logs below for the exact error.</div>";
    }
}

// Fetch logs
$logFile = $logDir . '/email_errors.log';
if (file_exists($logFile)) {
    $logContent = htmlspecialchars(file_get_contents($logFile));
} else {
    $logContent = "No errors logged yet.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Diagnostic Tool</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 40px auto; padding: 20px; background: #f4f7f6; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #111827; margin-top: 0; }
        .input-group { margin: 20px 0; }
        input { padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; width: 300px; font-size: 16px; }
        button { padding: 12px 24px; background: #10B981; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; }
        button:hover { background: #059669; }
        pre { background: #1f2937; color: #f9fafb; padding: 20px; border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.4; max-height: 400px; }
        .config-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .config-table td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .label { font-weight: bold; color: #374151; width: 150px; }
        .status-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .ok { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="card">
        <h1>📧 Email Diagnostic</h1>
        <p>Use this tool to verify your Resend SDK and API Key configuration.</p>

        <table class="config-table">
            <tr>
                <td class="label">RESEND_API_KEY</td>
                <td>
                    <?php if (defined('RESEND_API_KEY') && RESEND_API_KEY): ?>
                        <span class="status-tag ok">SET (Starts with: <?= substr(RESEND_API_KEY, 0, 7) ?>...)</span>
                    <?php else: ?>
                        <span class="status-tag error">NOT SET in .env</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="label">MAIL_FROM</td>
                <td><code><?= htmlspecialchars(MAIL_FROM) ?></code></td>
            </tr>
        </table>

        <?= $status ?>

        <form method="GET" class="input-group">
            <input type="email" name="email" placeholder="your-email@example.com" required value="<?= htmlspecialchars($testEmail) ?>">
            <button type="submit">Send Test Email</button>
        </form>

        <hr style="margin: 40px 0;">
        
        <h3>Email Error Logs</h3>
        <p style="font-size: 12px; color: #666;">Showing content of <code>/logs/email_errors.log</code></p>
        <pre><?= $logContent ?></pre>
        
        <p style="text-align: center; margin-top: 40px;">
            <a href="dashboard.php" style="color: #6366f1; text-decoration: none;">&larr; Back to Dashboard</a>
        </p>
    </div>
</body>
</html>
