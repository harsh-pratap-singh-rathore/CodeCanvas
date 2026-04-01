<?php
require_once APP_ROOT . '/app/config/database.php';
$stmt = $pdo->prepare('SELECT id, email, role, auth_provider, password_hash FROM users WHERE email = ?');
$stmt->execute(['admin@codecanvas.com']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user) {
    echo "ID: " . $user['id'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Provider: " . ($user['auth_provider'] ?: 'local') . "\n";
    echo "Hash: " . $user['password_hash'] . "\n";
    echo "Verify admin123: " . (password_verify('admin123', $user['password_hash']) ? 'YES' : 'NO') . "\n";
} else {
    echo "Admin user not found.\n";
}
?>
