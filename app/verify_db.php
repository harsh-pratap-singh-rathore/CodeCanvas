<?php
require APP_ROOT . '/app/config/database.php';
$stmt = $pdo->query('SHOW COLUMNS FROM projects');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    if (in_array($col['Field'], ['deploy_url', 'deploy_status', 'deployed_at', 'netlify_site_id', 'live_url', 'publish_status'])) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
}
