<?php
// validation_script.php
// This script runs php -l on all PHP files in the project.

$dirs = ['app', 'config', 'auth', 'admin'];
$errors = [];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') continue;
        $path = $file->getPathname();
        $output = [];
        $returnVar = 0;
        exec("C:\\xampp\\php\\php.exe -l \"$path\"", $output, $returnVar);
        if ($returnVar !== 0) {
            $errors[] = implode("\n", $output);
        }
    }
}

// Check root files too
foreach (glob("*.php") as $file) {
    $output = [];
    $returnVar = 0;
    exec("C:\\xampp\\php\\php.exe -l \"$file\"", $output, $returnVar);
    if ($returnVar !== 0) {
        $errors[] = implode("\n", $output);
    }
}

if (empty($errors)) {
    echo "SUCCESS: All files passed PHP lint check.\n";
} else {
    echo "FAILURE: Found " . count($errors) . " syntax errors:\n";
    echo implode("\n", $errors);
}
