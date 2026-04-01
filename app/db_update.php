<?php
require_once __DIR__ . '/../config/bootstrap.php';
/**
 * DB UPDATE HELPER
 * Run this once to update the projects table schema to support 'archived' status
 */

require_once APP_ROOT . '/config/database.php';

try {
    echo "<h2>Database Update</h2>";

    // 1. MODIFY status column to include APP_ROOT . '/app/archived'
    echo "Updating 'projects' table status enum...<br>";
    $sql = "ALTER TABLE projects MODIFY COLUMN status ENUM('draft', 'published', 'archived') DEFAULT 'draft'";
    $pdo->exec($sql);
    echo "<span style='color:green'>Success: Added 'archived' to status enum.</span><br><br>";

    // 2. Verify columns (Optional, just for debug)
    echo "Verifying 'projects' columns:<br>";
    $stmt = $pdo->query("DESCRIBE projects");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";

    echo "<hr>";
    echo "<h3>Update Complete. You can delete this file.</h3>";
    echo "<a href='<?= BASE_URL ?>/dashboard.php'>Go back to Dashboard</a>";

} catch (PDOException $e) {
    echo "<span style='color:red'>Error: " . $e->getMessage() . "</span>";
}
?>
