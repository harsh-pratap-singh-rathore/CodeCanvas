<?php
$files = [
    'ai-writer.php', 'check-slug.php', 'dashboard.php', 'db_update.php', 
    'describe_templates.php', 'fix-passwords.php', 'fix_json_redirects.php', 
    'get_schema.php', 'get_schema_v2.php', 'index.php', 'list_projects.php', 
    'messages.php', 'migrate_production.php', 'new-project.php', 
    'notifications.php', 'portfolio-generate.php', 'portfolio-preview.php', 
    'profile.php', 'project-archive.php', 'project-delete.php', 
    'project-duplicate.php', 'project-editor.php', 'project-publish.php', 
    'project-rename.php', 'project-save.php', 'project-settings.php', 
    'project-unpublish.php', 'project-view.php', 'reset_admin_password.php', 
    'select-category.php', 'select-template.php', 'settings.php', 'setup.php', 
    'verify-database.php', 'verify-db-detailed.php', 'verify_admin.php', 
    'verify_db.php', 'verify_saas_integration.php'
];

foreach ($files as $file) {
    $content = "<?php\nrequire_once __DIR__ . '/app/$file';\nexit;\n";
    file_put_contents($file, $content);
    echo "Converted $file to shim\n";
}
