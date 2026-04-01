<?php
require_once APP_ROOT . '/app/config/database.php';
try {
    $stmt = $pdo->query("DESCRIBE templates");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "Field: {$col['Field']} | Type: {$col['Type']}\n";
    }
} catch (PDOException $e) {
    echo "Query failed: " . $e->getMessage();
}
?>
