<?php
/**
 * SYSTEM HEALTH CHECK
 * Verifies all critical files exist and are reachable
 */

$files = [
    'Config' => [
        '../config/database.php',
        '../app/core/auth.php',
        '../app/core/admin_auth.php'
    ],
    'Auth Backend' => [
        '../app/auth/login.php',
        '../app/auth/signup.php',
        '../app/auth/logout.php'
    ],
    'Public Frontend' => [
        'login.html',
        'signup.html',
        'index.html',
        'assets/css/style.css'
    ],
    'App (Main)' => [
        '../app/dashboard.php',
        '../new-project.php',
        '../profile.php',
        '../settings.php'
    ],
    'Admin Panel' => [
        '../admin/dashboard.php',
        '../admin/templates.php'
    ],
    'Database' => [
        '../storage/docs/database/unified_auth_schema.sql'
    ]
];

$missing = [];
echo "<h1>System Health Check</h1>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; max-width: 800px;'>";
echo "<tr style='background: #f0f0f0;'><th>Category</th><th>File</th><th>Status</th></tr>";

foreach ($files as $category => $fileList) {
    foreach ($fileList as $file) {
        $path = __DIR__ . '/' . $file;
        $exists = file_exists($path);
        $status = $exists ? "<span style='color:green'>✅ Found</span>" : "<span style='color:red'>❌ MISSING</span>";
        
        echo "<tr>";
        echo "<td><strong>$category</strong></td>";
        echo "<td>$file</td>";
        echo "<td>$status</td>";
        echo "</tr>";
        
        if (!$exists) $missing[] = $file;
    }
}
echo "</table>";

if (empty($missing)) {
    echo "<h3>🎉 All critical files are present!</h3>";
    echo "<p>System is ready. <a href='login.html'>Go to Login</a></p>";
} else {
    echo "<h3>⚠️ Issues Found!</h3>";
    echo "<p>The following files are missing:</p><ul>";
    foreach ($missing as $file) {
        echo "<li>$file</li>";
    }
    echo "</ul>";
}
?>
