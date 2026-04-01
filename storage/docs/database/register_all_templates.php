<?php
/**
 * REGISTER ALL CATEGORY TEMPLATES
 * Run this once to insert all four category templates into the DB.
 * Visit: http://localhost/CodeCanvas/database/register_all_templates.php
 */

require_once '../config/database.php';

$templates = [
    [
        'name'          => 'Developer Portfolio',
        'slug'          => 'developer-portfolio',
        'template_type' => 'portfolio',
        'folder_path'   => 'templates/developer/',
    ],
    [
        'name'          => 'Business Portfolio',
        'slug'          => 'business-portfolio',
        'template_type' => 'business',
        'folder_path'   => 'templates/business/',
    ],
    [
        'name'          => 'Shop Template',
        'slug'          => 'shop-template',
        'template_type' => 'business',
        'folder_path'   => 'templates/shop/',
    ],
    [
        'name'          => 'Normal Portfolio',
        'slug'          => 'normal-portfolio',
        'template_type' => 'personal',
        'folder_path'   => 'templates/normal/',
    ],
];

echo '<style>body{font-family:sans-serif;padding:24px;} table{border-collapse:collapse;} td,th{border:1px solid #ccc;padding:8px 12px;}</style>';
echo '<h2>Template Registration</h2>';

try {
    foreach ($templates as $tpl) {
        $stmt = $pdo->prepare("SELECT id FROM templates WHERE slug = ?");
        $stmt->execute([$tpl['slug']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Make sure it's active
            $upd = $pdo->prepare("UPDATE templates SET status = 'active', folder_path = ? WHERE slug = ?");
            $upd->execute([$tpl['folder_path'], $tpl['slug']]);
            echo '<p style="color:orange;">⚠️ <strong>' . htmlspecialchars($tpl['name']) . '</strong> already exists (ID: ' . $existing['id'] . ') — ensured active.</p>';
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO templates (name, slug, template_type, folder_path, status)
                 VALUES (?, ?, ?, ?, 'active')"
            );
            $ins->execute([$tpl['name'], $tpl['slug'], $tpl['template_type'], $tpl['folder_path']]);
            $id = $pdo->lastInsertId();
            echo '<p style="color:green;">✅ <strong>' . htmlspecialchars($tpl['name']) . '</strong> registered (ID: ' . $id . ').</p>';
        }
    }

    // Show all templates
    echo '<h3>All Templates in DB:</h3>';
    $stmt = $pdo->query("SELECT id, name, slug, template_type, folder_path, status FROM templates ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<table><tr><th>ID</th><th>Name</th><th>Slug</th><th>Type</th><th>Folder Path</th><th>Status</th></tr>';
    foreach ($rows as $row) {
        $color = $row['status'] === 'active' ? '#006600' : '#cc0000';
        echo '<tr>';
        foreach ($row as $col => $val) {
            echo '<td style="color:' . ($col === 'status' ? $color : 'inherit') . '">' . htmlspecialchars($val) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';

    echo '<br><a href="../app/select-category.php">→ Go to Category Selection</a>';

} catch (PDOException $e) {
    echo '<p style="color:red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
