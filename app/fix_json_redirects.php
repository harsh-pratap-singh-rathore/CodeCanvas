<?php
$root = __DIR__;
$appDir = $root . '/app';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));

foreach ($it as $file) {
    if ($file->isDir()) continue;
    $path = $file->getRealPath();
    if (!str_ends_with($path, '.php')) continue;
    if (str_contains($path, 'vendor')) continue;
    
    $content = file_get_contents($path);
    $original = $content;
    
    // Pattern 1: $response['redirect'] = '../app/dashboard.php';
    // Match any 'redirect' => '...' or $response['redirect'] = '...'
    // and replace relative paths starting with ../ or ./ or just a file name with BASE_URL based paths.
    
    $content = preg_replace_callback("/('redirect'|\"redirect\")\s*(=>|=)\s*(['\"])\s*(?!\/|http|https|#)(?:\.\.\/)*([^'\"]+)\s*(['\"])/", function($m) {
        $prefix = $m[1] . ' ' . $m[2] . ' ';
        $quote = $m[3];
        $path = $m[4];
        
        // If it starts with ../app/ it should be BASE_URL/
        // If it starts with ../public/ it should be BASE_URL/public/
        if (str_starts_with($path, 'app/')) {
             return $prefix . 'BASE_URL . "/' . substr($path, 4) . '"';
        } elseif (str_starts_with($path, 'public/')) {
             return $prefix . 'BASE_URL . "/' . $path . '"';
        } else {
             // Just a file or something else, make it BASE_URL/path
             // But check if it's already a full URL or something
             return $prefix . 'BASE_URL . "/' . $path . '"';
        }
    }, $content);

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Standardized JSON redirect in: $path\n";
    }
}
echo "JSON redirect standardization complete.\n";
