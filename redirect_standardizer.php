<?php
// redirect_standardizer.php
// This script standardizes header("Location: ...") redirects in the app/ directory.

$appDir = 'c:/xampp/htdocs/CodeCanvas/app';

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;

    $path = str_replace('\\', '/', $file->getPathname());
    $content = file_get_contents($path);
    $modified = false;

    // Pattern for header("Location: ...")
    // We handle both double and single quotes
    $patterns = [
        '/header\s*\(\s*[\'"]Location:\s*([^\'"]+)[\'"]\s*\)\s*;/', // header("Location: path")
        '/header\s*\(\s*[\'"]Location:\s*[\'"]\s*\.\s*(BASE_URL\s*\.\s*)?[\'"]([^\'"]+)[\'"]\s*\)\s*;/', // header("Location: " . BASE_URL . "/path") or header("Location: " . "/path")
        '/header\s*\(\s*[\'"]Location:\s*[\'"]\s*\.\s*(\$[^;]+)\s*\)\s*;/', // header("Location: " . $variable)
    ];

    // Let's use a simpler regex to catch the whole line and transform it.
    // The goal is header("Location: " . BASE_URL . "/path"); exit;
    
    // Pattern to catch any header Location call and try to stabilize it
    $pattern = '/header\s*\(\s*[\'"]Location:\s*([^)]+)\)\s*;(\s*exit\s*;)?/i';

    $content = preg_replace_callback($pattern, function($matches) use (&$modified) {
        $locValue = trim($matches[1]);
        $hasExit = !empty($matches[2]);
        
        // Remove surrounding quotes from the captured Location value if it's a simple string
        $cleanLoc = trim($locValue, "'\"");
        
        $newLocValue = "";
        
        // 1. If it already uses BASE_URL
        if (str_contains($locValue, 'BASE_URL')) {
            // Ensure there's a dot between BASE_URL and the path
            // e.g. BASE_URL . '/dashboard.php'
            $newLocValue = $locValue;
        } 
        // 2. If it's a relative path starting with ../ or just a filename
        elseif (!str_starts_with($cleanLoc, 'http') && !str_starts_with($cleanLoc, 'BASE_URL')) {
            // Standardize to BASE_URL + path
            // Remove leading / from cleanLoc
            $pathPart = ltrim($cleanLoc, '/');
            // If it starts with ../, we need to resolve it relative to the app structure.
            // But usually, these targets are intended to be root-level shims.
            // e.g. ../dashboard.php -> dashboard.php
            $pathPart = str_replace('../', '', $pathPart);
            
            // Special case: if it points to /app/
            if (str_starts_with($pathPart, 'app/')) {
                $pathPart = substr($pathPart, 4);
            }
            
            $newLocValue = "BASE_URL . '/" . ltrim($pathPart, '/') . "'";
        } else {
            // It might be a variable or a full URL.
            $newLocValue = $locValue;
        }

        $modified = true;
        $replacement = "header(\"Location: \" . $newLocValue);";
        $replacement .= "\nexit;";
        
        return $replacement;
    }, $content);

    if ($modified) {
        file_put_contents($path, $content);
        echo "Standardized redirects in $path\n";
    }
}
