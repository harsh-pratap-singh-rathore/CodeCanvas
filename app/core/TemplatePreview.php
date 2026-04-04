<?php
/**
 * Core: Template Preview Helper
 */
class TemplatePreview {
    /**
     * Generates HTML for a template preview.
     * Prioritizes a live iframe preview for the "WOW" factor.
     */
    public static function getPreviewHtml($template, $isLive = true) {
        $name = htmlspecialchars($template['name'] ?? $template['project_name'] ?? $template['template_name'] ?? 'Template');
        $slug = $template['slug'] ?? '';
        $folderPath = $template['folder_path'] ?? '';
        
        // If we want a live preview and have a folder path
        if ($isLive && $folderPath) {
            // Normalize slashes for cross-platform compatibility
            $normalizedFolder = str_replace('\\', '/', $folderPath);
            $basePath = rtrim(APP_ROOT, '/\\') . '/' . trim($normalizedFolder, '/');
            $htmlFile = null;
            
            // Candidates in order of priority
            $candidates = ['code.html', 'index.html', 'index.htm', 'home.html', 'main.html', 'landing.html', 'shop.html'];
            
            // 1. Check root (Case-insensitive check for common servers)
            $filesInDir = @scandir($basePath);
            if ($filesInDir) {
                // Try to find an exact match first
                foreach ($candidates as $c) {
                    if (file_exists($basePath . '/' . $c)) {
                        $htmlFile = $c;
                        break;
                    }
                }
                
                // Fallback to case-insensitive match
                if (!$htmlFile) {
                    foreach ($filesInDir as $f) {
                        if (in_array(strtolower($f), $candidates)) {
                            $htmlFile = $f;
                            break;
                        }
                    }
                }
            }
           
            // Check subdirectories (1 level deep)
            if (!$htmlFile) {
                $subDirs = glob($basePath . '/*', GLOB_ONLYDIR);
                if ($subDirs !== false) {
                    foreach ($subDirs as $dir) {
                        // Check candidates in the subdirectory
                        foreach ($candidates as $c) {
                            if (file_exists($dir . '/' . $c)) {
                                $htmlFile = basename($dir) . '/' . $c;
                                break 2;
                            }
                        }
                        // Case-insensitive check in subdirectory
                        $filesInSub = @scandir($dir);
                        if ($filesInSub) {
                            foreach ($filesInSub as $f) {
                                if (in_array(strtolower($f), $candidates)) {
                                    $htmlFile = basename($dir) . '/' . $f;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            if ($htmlFile) {
                $url = BASE_URL . '/' . trim($folderPath, '/') . '/' . $htmlFile;
                return '
                    <div class="live-preview-container" style="position:absolute; inset:0; overflow:hidden; background:#000;">
                        <iframe src="' . $url . '" 
                                style="position:absolute; top:0; left:0; width: 285.7%; height: 285.7%; border:none; transform: scale(0.35); transform-origin:0 0; pointer-events:none; background:#000;"
                                loading="lazy">
                        </iframe>
                        <div style="position:absolute; inset:0; z-index:5;"></div>
                    </div>';
            }
        }

        // Fallback to Image Preview if Live is off or file missing
        $imagePath    = $template['preview_image_path'] ?? null;
        $fallbackPath = $template['preview_fallback_path'] ?? null;
        $slug         = $template['slug'] ?? '';
        
        $root = rtrim(APP_ROOT, '/\\');
        
        if ($imagePath && file_exists($root . '/' . ltrim($imagePath, '/\\'))) {
            $src = BASE_URL . '/' . ltrim($imagePath, '/\\');
        } elseif (!empty($slug) && file_exists($root . '/public/assets/templates/' . $slug . '/preview.webp')) {
            $src = BASE_URL . '/public/assets/templates/' . $slug . '/preview.webp';
        } elseif ($fallbackPath && file_exists($root . '/' . ltrim($fallbackPath, '/\\'))) {
            $src = BASE_URL . '/' . ltrim($fallbackPath, '/\\');
        } else {
            return self::generatePlaceholder($name);
        }

        return '<img src="' . $src . '" 
                     alt="' . $name . '" 
                     loading="lazy" 
                     class="template-preview-img"
                     style="width:100%; height:100%; object-fit:cover; display:block;">';
    }

    private static function generatePlaceholder($name) {
        return '<div class="template-placeholder" data-name="' . $name . '" 
                     style="width:100%; height:100%; background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); 
                            display:flex; align-items:center; justify-content:center; color:#888; font-weight:700; font-size:14px; text-align:center; padding:20px;">
                    ' . $name . '
                </div>';
    }
}
