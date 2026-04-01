<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
try {
    $stmt = $pdo->query("DESCRIBE templates");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
} catch (PDOException $e) {
    echo $e->getMessage();
}
