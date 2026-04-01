<?php
/**
 * REGISTER BUSINESS TEMPLATE
 * Run this once to insert the Business Portfolio template into the DB.
 * Visit: http://localhost/CodeCanvas/database/register_business_template.php
 */

require_once '../config/database.php';

try {
    // Check if already registered
    $stmt = $pdo->prepare("SELECT id FROM templates WHERE slug = 'business-portfolio'");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo '<p style="color:orange;font-family:sans-serif;">⚠️ Business Portfolio template already registered (ID: ' . $existing['id'] . '). Nothing changed.</p>';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO templates (name, slug, template_type, folder_path, status)
             VALUES ('Business Portfolio', 'business-portfolio', 'business', 'templates/business/', 'active')"
        );
        $stmt->execute();
        $id = $pdo->lastInsertId();
        echo '<p style="color:green;font-family:sans-serif;">✅ Business Portfolio template registered successfully! (ID: ' . $id . ')</p>';
    }

    // Show all templates
    echo '<h3 style="font-family:sans-serif;">All Templates:</h3>';
    $stmt = $pdo->query("SELECT id, name, slug, template_type, folder_path, status FROM templates ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<table border="1" cellpadding="6" style="font-family:sans-serif;border-collapse:collapse;">';
    echo '<tr><th>ID</th><th>Name</th><th>Slug</th><th>Type</th><th>Folder Path</th><th>Status</th></tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $val) echo '<td>' . htmlspecialchars($val) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    echo '<br><a href="../app/select-category.php" style="font-family:sans-serif;">→ Go to Category Selection</a>';

} catch (PDOException $e) {
    echo '<p style="color:red;font-family:sans-serif;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
