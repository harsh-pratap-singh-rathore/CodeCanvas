<?php
// require_standardizer_v2.php
// This script standardizes require/include paths in the app/ directory.

$appDir = 'c:/xampp/htdocs/CodeCanvas/app';
$projectRoot = 'c:/xampp/htdocs/CodeCanvas';

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;

    $path = str_replace('\\', '/', $file->getPathname());
    $content = file_get_contents($path);
    $modified = false;

    // Patterns
    $patterns = [
        '/require_once\s+(__DIR__\s*\.\s*)?[\'"]([^\'"]+)[\'"]/',
        '/require\s+(__DIR__\s*\.\s*)?[\'"]([^\'"]+)[\'"]/',
        '/include_once\s+(__DIR__\s*\.\s*)?[\'"]([^\'"]+)[\'"]/',
        '/include\s+(__DIR__\s*\.\s*)?[\'"]([^\'"]+)[\'"]/',
    ];

    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, function($matches) use ($path, $projectRoot, &$modified) {
            $matchedPath = $matches[2];
            $baseDir = dirname($path);
            
            // Resolve the path
            $fullOriginalPath = $baseDir . '/' . ltrim($matchedPath, '/');
            
            // Clean up ../ and ./
            while (strpos($fullOriginalPath, '/../') !== false) {
                $fullOriginalPath = preg_replace('/\/[^\/]+\/\.\.\//', '/', $fullOriginalPath, 1);
            }
            $fullOriginalPath = str_replace('/./', '/', $fullOriginalPath);
            $fullOriginalPath = str_replace('\\', '/', $fullOriginalPath);

            if (str_starts_with($fullOriginalPath, $projectRoot)) {
                $relativePathFromRoot = substr($fullOriginalPath, strlen($projectRoot));
                
                // Determine keyword
                $fullMatch = $matches[0];
                $keyword = 'require_once';
                if (str_starts_with($fullMatch, 'require_once')) $keyword = 'require_once';
                elseif (str_starts_with($fullMatch, 'require')) $keyword = 'require';
                elseif (str_starts_with($fullMatch, 'include_once')) $keyword = 'include_once';
                elseif (str_starts_with($fullMatch, 'include')) $keyword = 'include';

                // SPECIAL EXCEPTION: Bootstrap and Config entry points
                // These must use __DIR__ to ensure APP_ROOT is loaded if they are the first ones.
                // However, the user said ALL should use APP_ROOT.
                // I will use a safe wrapper or assume bootstrap is handled.
                // To be safe and compliant with "Standardize ALL", I'll use APP_ROOT.
                
                $modified = true;
                return "$keyword APP_ROOT . '$relativePathFromRoot'";
            }

            return $matches[0];
        }, $content);
    }

    // If we used APP_ROOT, we should ensure it's defined or we include bootstrap first using __DIR__
    // Let's check if bootstrap is included using APP_ROOT.
    if ($modified && !str_contains($content, "defined('APP_ROOT')")) {
        // Find the first require_once APP_ROOT . '/config/bootstrap.php'
        // and replace it with a __DIR__ based one to "bootstrap" the root.
        
        $bootstrapPattern = "/(require_once|require|include_once|include)\s+APP_ROOT\s*\.\s*['\"]\/config\/bootstrap\.php['\"]/";
        $foundBootstrap = false;
        
        $content = preg_replace_callback($bootstrapPattern, function($matches) use ($path, $projectRoot, &$foundBootstrap) {
            $foundBootstrap = true;
            $keyword = $matches[1];
            
            // Calculate relative path to config/bootstrap.php from current file
            $currentDir = dirname($path);
            $targetPath = $projectRoot . '/config/bootstrap.php';
            
            // Simple relative calculation
            $rel = '';
            $temp = $currentDir;
            while (!str_starts_with($targetPath, $temp)) {
                $temp = dirname($temp);
                $rel .= '../';
            }
            $rel .= substr($targetPath, strlen($temp) + 1);
            
            return "$keyword __DIR__ . '/" . ltrim($rel, '/') . "'";
        }, $content, 1);
        
        if (!$foundBootstrap) {
            // If no bootstrap, maybe it's config/app.php
            $appPattern = "/(require_once|require|include_once|include)\s+APP_ROOT\s*\.\s*['\"]\/config\/app\.php['\"]/";
            $content = preg_replace_callback($appPattern, function($matches) use ($path, $projectRoot) {
                $keyword = $matches[1];
                $currentDir = dirname($path);
                $targetPath = $projectRoot . '/config/app.php';
                $rel = '';
                $temp = $currentDir;
                while (!str_starts_with($targetPath, $temp)) {
                    $temp = dirname($temp);
                    $rel .= '../';
                }
                $rel .= substr($targetPath, strlen($temp) + 1);
                return "$keyword __DIR__ . '/" . ltrim($rel, '/') . "'";
            }, $content, 1);
        }
    }

    if ($modified) {
        file_put_contents($path, $content);
        echo "Standardized requires in $path\n";
    }
}
