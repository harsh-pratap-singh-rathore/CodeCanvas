<?php
require_once APP_ROOT . '/app/app/core/TemplateScanner.php';

echo "SaaS Integration Verification\n";
echo "---------------------------\n";

$targetDir = __DIR__ . '/tmp_template';

if (!is_dir($targetDir)) {
    die("Error: tmp_template directory not found. Please run the template generation steps first.\n");
}

echo "Scanning: $targetDir\n";
$result = TemplateScanner::scan($targetDir);

echo "Found " . $result['total'] . " fields.\n";
foreach ($result['fields'] as $f) {
    echo "- [" . $f['type'] . "] " . $f['id'] . " (" . $f['label'] . ")\n";
}

echo "\nGenerating schema.json...\n";
TemplateScanner::generateSchema($targetDir, $result);

if (file_exists($targetDir . '/schema.json')) {
    echo "SUCCESS: schema.json generated in $targetDir\n";
    $schema = json_decode(file_get_contents($targetDir . '/schema.json'), true);
    echo "Schema Field Count: " . count($schema['fields']) . "\n";
} else {
    echo "FAILED: schema.json not found.\n";
}
?>
