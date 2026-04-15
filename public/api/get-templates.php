<?php
/**
 * GET TEMPLATES API
 * Returns a list of active templates for the public templates page.
 * preview_url is built by scanning the actual filesystem for the entry file.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once '../../config/bootstrap.php';
define('IGNORE_DB_ERROR', true);
require_once '../../config/database.php';

/**
 * Sanitize a template name that may have been corrupted during upload
 * (e.g. stored as "shop-template/style.css" instead of "Shop Template").
 * Falls back to deriving a clean title from the slug or folder path.
 */
function sanitizeTemplateName(string $name, string $slug, string $folderPath): string
{
    // Detect corrupted name: contains a slash (path separator) or a file extension
    $looksCorrupted = str_contains($name, '/') || str_contains($name, '\\')
        || preg_match('/\.(css|html?|js|php|zip)$/i', $name);

    if (!$looksCorrupted) {
        return $name; // name is fine
    }

    // Prefer slug, then last segment of folder_path
    $fallback = $slug ?: (basename(rtrim($folderPath, '/')) ?: $name);

    // Convert slug/folder-name to Title Case (replace hyphens/underscores with spaces)
    return ucwords(str_replace(['-', '_'], ' ', $fallback));
}

/**
 * Find the best preview HTML file inside a template folder.
 * Checks for code.html first, then index.html, recursively if needed.
 */
function findEntryFile(string $folderPath): ?string
{
    $root = APP_ROOT . '/' . rtrim($folderPath, '/');

    // 1. Check index.html directly in the folder
    if (file_exists($root . '/index.html')) {
        return rtrim($folderPath, '/') . '/index.html';
    }

    // 2. Check code.html directly in the folder
    if (file_exists($root . '/code.html')) {
        return rtrim($folderPath, '/') . '/code.html';
    }

    // 3. Recurse one level into subdirectories to find index.html or code.html
    foreach (glob($root . '/*', GLOB_ONLYDIR) as $subDir) {
        $subRelative = rtrim($folderPath, '/') . '/' . basename($subDir);

        if (file_exists($subDir . '/index.html')) {
            return $subRelative . '/index.html';
        }
        if (file_exists($subDir . '/code.html')) {
            return $subRelative . '/code.html';
        }

        // 4. One more level deep (e.g. templates/developer/modern-dev-portfolio/portfolio-dev/index.html)
        foreach (glob($subDir . '/*', GLOB_ONLYDIR) as $deepDir) {
            $deepRelative = $subRelative . '/' . basename($deepDir);
            if (file_exists($deepDir . '/index.html')) {
                return $deepRelative . '/index.html';
            }
            if (file_exists($deepDir . '/code.html')) {
                return $deepRelative . '/code.html';
            }
        }
    }

    return null; // not found
}

try {
    $results = [];

    if ($pdo) {
        $stmt = $pdo->query("SELECT id, name, slug, template_type, folder_path, thumbnail_url FROM templates WHERE status = 'active' ORDER BY name ASC");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($templates as $tpl) {
            $path = trim($tpl['folder_path'], '/');
            $parts = explode('/', $path);
            $category = 'other';

            if (count($parts) >= 2 && $parts[0] === 'templates') {
                $category = $parts[1];
            }

            // Resolve the real entry file path
            $entryRelative = findEntryFile($path);
            $previewUrl = $entryRelative
                ? BASE_URL . '/' . $entryRelative
                : BASE_URL . '/' . $path . '/code.html'; // fallback

            $results[] = [
                'id'          => $tpl['id'],
                'name'        => sanitizeTemplateName($tpl['name'], $tpl['slug'] ?? '', $tpl['folder_path'] ?? ''),
                'slug'        => $tpl['slug'],
                'type'        => $tpl['template_type'],
                'category'    => $category,
                'preview_url' => $previewUrl,
                'thumbnail'   => $tpl['thumbnail_url'] ?? null,
            ];
        }
    }

    // Fallback: If DB results are empty, scan filesystem for standard templates
    if (empty($results)) {
        $allowed = ['developer', 'business', 'shop', 'normal'];
        foreach ($allowed as $category) {
            $baseDir = APP_ROOT . '/templates/' . $category . '/';
            if (is_dir($baseDir)) {
                $subDirs = glob($baseDir . '*', GLOB_ONLYDIR);
                foreach ((array)$subDirs as $subDir) {
                    $htmlFile = null;
                    foreach (['index.html', 'code.html', 'index.htm'] as $c) {
                        if (file_exists($subDir . '/' . $c)) { $htmlFile = $c; break; }
                    }
                    if (!$htmlFile) {
                        $nested = glob($subDir . '/*/{index.html,code.html,index.htm}', GLOB_BRACE);
                        if (!empty($nested)) $htmlFile = str_replace($subDir . '/', '', $nested[0]);
                    }

                    if ($htmlFile) {
                        $variantName = basename($subDir);
                        $results[] = [
                            'id'          => null,
                            'name'        => ucfirst($category) . ' — ' . ucfirst($variantName),
                            'slug'        => '',
                            'type'        => 'standard',
                            'category'    => $category,
                            'preview_url' => BASE_URL . '/templates/' . $category . '/' . $variantName . '/' . $htmlFile,
                            'thumbnail'   => null,
                        ];
                    }
                }
            }
        }
    }

    echo json_encode([
        'success'   => true,
        'templates' => $results,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
