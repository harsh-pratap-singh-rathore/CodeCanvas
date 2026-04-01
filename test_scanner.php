<?php
require_once __DIR__ . '/app/core/TemplateScanner.php';

$testDir = __DIR__ . '/storage/test_template';
if (!is_dir($testDir)) {
    mkdir($testDir, 0777, true);
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>My Portfolio</title>
</head>
<body>
    <header class="header">
        <h1 data-edit="hero_title">Welcome to my site</h1>
        <p>I am a developer.</p>
        <img src="avatar.jpg" alt="Avatar">
    </header>
    <section class="projects">
        <h2>My Projects</h2>
        <div class="project-card">
            <h3 data-edit="project_name">Project 1</h3>
            <p>Description goes here</p>
        </div>
        <div class="project-card">
            <h3>Project 2</h3>
            <p>Description 2</p>
        </div>
    </section>
</body>
</html>
HTML;

file_put_contents($testDir . '/index.html', $html);

echo "Testing TemplateScanner...\n";
$result = TemplateScanner::scan($testDir);

echo "Total Editable Fields: " . $result['total'] . "\n";
echo "HTML File Scanned: " . $result['html_file'] . "\n\n";

if (isset($result['schema'])) {
    echo json_encode($result['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "No schema generated or error occurred.\n";
    if (isset($result['error'])) echo "Error: " . $result['error'] . "\n";
}
