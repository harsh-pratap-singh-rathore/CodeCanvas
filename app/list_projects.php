<?php
require_once APP_ROOT . '/app/config/database.php';
$stmt = $pdo->prepare('SELECT content_json FROM projects WHERE id = 4');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if($row) print_r(json_decode($row['content_json'], true));
else echo "NOT FOUND\n";
