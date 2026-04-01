<?php
$authFiles = [
    'dashboard.php', 'forgot-password.php', 'google-callback.php', 
    'google-login.php', 'login.php', 'logout.php', 
    'request-password-change.php', 'session.php', 'signup.php', 
    'verify-otp.php', 'verify-reset.php'
];

foreach ($authFiles as $file) {
    $content = "<?php\nrequire_once dirname(__DIR__) . '/app/auth/$file';\nexit;\n";
    file_put_contents("auth/$file", $content);
}

$adminFiles = [
    'dashboard.php', 'db_check_templates.php', 'notifications.php', 
    'template-add.php', 'template-edit.php', 'templates.php'
];

foreach ($adminFiles as $file) {
    if ($file === 'api') continue;
    $content = "<?php\nrequire_once dirname(__DIR__) . '/app/admin/$file';\nexit;\n";
    file_put_contents("admin/$file", $content);
}

// Special case for admin/api if it exists as shims
$adminApiFiles = ['ai-analyze-template.php']; // I saw this in previous grep
if (is_dir('admin/api')) {
    foreach ($adminApiFiles as $file) {
        $content = "<?php\nrequire_once dirname(dirname(__DIR__)) . '/app/admin/api/$file';\nexit;\n";
        file_put_contents("admin/api/$file", $content);
    }
}
