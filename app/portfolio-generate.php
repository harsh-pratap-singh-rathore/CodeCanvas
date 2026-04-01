<?php
/**
 * PORTFOLIO GENERATOR — Server-Side HTML Generation
 * CodeCanvas | POST endpoint: generates final portfolio HTML for download
 *
 * Accepts:
 *   POST JSON: { project_id: int, data?: object }
 *   OR GET:    ?id=<project_id>  (uses saved content_json)
 *
 * Returns: Final HTML file as download
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

// ── Determine request mode ────────────────────────────────────
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isPost) {
    $input     = json_decode(file_get_contents('php://input'), true);
    $projectId = $input['project_id'] ?? null;
    $overrideData = $input['data'] ?? null; // optional: use fresh data from frontend
} else {
    $projectId    = $_GET['id'] ?? null;
    $overrideData = null;
}

if (!$projectId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing project_id']);
    exit;
}

try {
    // ── 1. Fetch project ──────────────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT p.*, t.name AS template_name, t.folder_path
         FROM projects p
         JOIN templates t ON p.template_id = t.id
         WHERE p.id = ? AND p.user_id = ?"
    );
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Project not found or unauthorized']);
        exit;
    }

    // ── 2. Load template HTML ─────────────────────────────────────
    $templatePath = __DIR__ . '/../templates/developer/code.html';
    if (!file_exists($templatePath)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Template file not found']);
        exit;
    }
    $html = file_get_contents($templatePath);

    // ── 3. Load schema ────────────────────────────────────────────
    $schemaPath = __DIR__ . '/../templates/developer/schema.json';
    if (!file_exists($schemaPath)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Schema file not found']);
        exit;
    }
    $schema = json_decode(file_get_contents($schemaPath), true);
    if (!$schema || !isset($schema['fields'])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid schema.json']);
        exit;
    }

    // ── 4. Resolve user data ──────────────────────────────────────
    // Priority: fresh POST data > saved content_json
    if ($overrideData && is_array($overrideData)) {
        $userData = $overrideData;
        // Also save this fresh data to DB
        $pdo->prepare("UPDATE projects SET content_json = ?, updated_at = NOW() WHERE id = ?")
            ->execute([json_encode($userData), $projectId]);
    } else {
        $userData = [];
        if (!empty($project['content_json'])) {
            $decoded = json_decode($project['content_json'], true);
            if (is_array($decoded)) {
                $userData = $decoded;
            }
        }
    }

    // ── 5. Apply user data to HTML ────────────────────────────────
    $html = applyUserDataToHTML($html, $schema['fields'], $userData);

    // ── 6. Stream as download ─────────────────────────────────────
    $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $project['project_name'] ?? 'portfolio') . '.html';

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . strlen($html));

    echo $html;
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// ══════════════════════════════════════════════════════════════════
// SCHEMA-BASED HTML REPLACEMENT ENGINE (PHP)
// Mirrors the JS applyUserDataToHTML() in project-editor.js
// ══════════════════════════════════════════════════════════════════

function applyUserDataToHTML(string $html, array $fields, array $userData): string
{
    foreach ($fields as $field) {
        $id    = $field['id']   ?? '';
        $type  = $field['type'] ?? 'text';
        $value = $userData[$id] ?? null;

        if ($value === null || $value === '') continue;

        switch ($type) {
            case 'text':
            case 'email':
            case 'textarea':
                $html = replaceInnerText($html, $field['selector'] ?? '', (string)$value);
                if ($type === 'email' && !empty($field['hrefSelector'])) {
                    $html = replaceAttribute($html, 'href', 'mailto:', (string)$value);
                }
                break;

            case 'image':
                $strVal = (string)$value;
                if (str_starts_with($strVal, 'data:') || str_starts_with($strVal, 'http')) {
                    $html = replaceImageSrc($html, $field['selector'] ?? '', $strVal);
                }
                break;

            case 'array':
                if ($id === 'skills' && is_array($value) && count($value) > 0) {
                    $html = replaceSkillsGrid($html, $value);
                }
                if ($id === 'typing_words' && is_array($value) && count($value) > 0) {
                    $html = replaceTypingWords($html, $value);
                }
                break;

            case 'group':
                if ($id === 'projects' && is_array($value) && count($value) > 0) {
                    $html = replaceProjectsGrid($html, $value);
                }
                break;
        }
    }

    return $html;
}

function replaceInnerText(string $html, string $selector, string $newText): string
{
    $escaped = htmlspecialchars($newText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    switch ($selector) {
        case '.hacker-name':
            return preg_replace('/(class="hacker-name[^"]*">)([\s\S]*?)(<\/h1>)/u', '${1}' . $escaped . '${3}', $html, 1) ?? $html;

        case 'nav a.font-hacker':
            return preg_replace('/(<a\s[^>]*font-hacker[^>]*tracking-widest[^>]*>)([^<]*)(<\/a>)/u', '${1}' . $escaped . '${3}', $html, 1) ?? $html;

        case '.flex.flex-col.md\\:flex-row span.text-gray-400':
            return preg_replace('/(<span\s[^>]*text-lg[^>]*font-light[^>]*text-gray-400[^>]*>)([^<]*)(<\/span>)/u', '${1}' . $escaped . '${3}', $html, 1) ?? $html;

        case '#about p.text-gray-400.leading-relaxed':
            return preg_replace('/(<p\s[^>]*leading-relaxed[^>]*text-lg[^>]*font-light[^>]*>)([\s\S]*?)(<\/p>)/u', '${1}' . $escaped . '${3}', $html, 1) ?? $html;

        case '#about .grid.grid-cols-2 div:first-child h4':
            return preg_replace('/(<h4\s[^>]*text-3xl[^>]*>)([^<]*)(<\/h4>)/u', '${1}' . $escaped . '${3}', $html, 1) ?? $html;

        case '#about .grid.grid-cols-2 div:last-child h4':
            return replaceNthMatch($html, '/(<h4\s[^>]*text-3xl[^>]*>)([^<]*)(<\/h4>)/u', 1, '${1}' . $escaped . '${3}');

        case "#contact a[href^='mailto']":
            return preg_replace('/(<a\s[^>]*href="mailto:[^"]*"[^>]*>)([^<]*)(<\/a>)/u', '${1}' . $escaped . '${3}', $html, 1) ?? $html;

        case '#contact .space-y-8 div:nth-child(2) p.text-white':
            return preg_replace('/(<p\s[^>]*class="text-white text-sm"[^>]*>)([^<]*)(<\/p>)/u', '${1}' . $escaped . '${3}', $html, 1) ?? $html;

        case 'footer p:first-child':
            return preg_replace('/(<p\s[^>]*text-\[10px\][^>]*text-gray-700[^>]*>)([^<]*)(<\/p>)/u', '${1}' . $escaped . '${3}', $html, 1) ?? $html;

        default:
            return $html;
    }
}

function replaceNthMatch(string $html, string $pattern, int $n, string $replacement): string
{
    $count = 0;
    $result = preg_replace_callback($pattern, function ($matches) use (&$count, $n, $replacement) {
        if ($count === $n) {
            $count++;
            $out = str_replace('${1}', $matches[1] ?? '', $replacement);
            $out = str_replace('${2}', $matches[2] ?? '', $out);
            $out = str_replace('${3}', $matches[3] ?? '', $out);
            return $out;
        }
        $count++;
        return $matches[0];
    }, $html);
    return $result ?? $html;
}

function replaceAttribute(string $html, string $attr, string $prefix, string $newValue): string
{
    $escapedPrefix = preg_quote($prefix, '/');
    $pattern = '/(' . preg_quote($attr, '/') . '="' . $escapedPrefix . ')[^"]*(")/' ;
    return preg_replace($pattern, '${1}' . htmlspecialchars($newValue, ENT_QUOTES) . '${2}', $html) ?? $html;
}

function replaceImageSrc(string $html, string $selector, string $newSrc): string
{
    if ($selector === '#about img.person-svg') {
        $html = preg_replace('/(<img\s[^>]*class="[^"]*person-svg[^"]*"[^>]*src=")[^"]*(")/u', '${1}' . $newSrc . '${2}', $html, 1) ?? $html;
        $html = preg_replace('/(<img\s[^>]*src=")[^"]*("[^>]*class="[^"]*person-svg[^"]*")/u', '${1}' . $newSrc . '${2}', $html, 1) ?? $html;
    }
    return $html;
}

function replaceSkillsGrid(string $html, array $skills): string
{
    $skillsHTML = '';
    foreach ($skills as $skill) {
        $icon = htmlspecialchars($skill['icon'] ?? 'code', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = htmlspecialchars($skill['name'] ?? '',   ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $skillsHTML .= "\n                <div class=\"group bg-black/40 border border-white/5 glow-border rounded-lg p-6 hover:bg-white/5 transition-all duration-300 flex flex-col items-center justify-center gap-3 cursor-default\">"
            . "\n                    <span class=\"material-icons text-3xl text-gray-600 group-hover:text-white transition-colors\">{$icon}</span>"
            . "\n                    <span class=\"font-hacker text-xs text-gray-400 group-hover:text-white tracking-wider transition-colors\">{$name}</span>"
            . "\n                </div>";
    }
    return preg_replace(
        '/(<div\s[^>]*grid-cols-2[^>]*md:grid-cols-4[^>]*lg:grid-cols-5[^>]*gap-4[^>]*>)([\s\S]*?)(<\/div>)/u',
        '${1}' . $skillsHTML . "\n            " . '${3}',
        $html, 1
    ) ?? $html;
}

function replaceTypingWords(string $html, array $words): string
{
    $wordsJSON = json_encode(array_values($words), JSON_UNESCAPED_UNICODE);
    return preg_replace('/window\.words\s*=\s*\[[^\]]*\]/', 'window.words = ' . $wordsJSON, $html, 1) ?? $html;
}

function replaceProjectsGrid(string $html, array $projects): string
{
    $projectsHTML = '';
    foreach ($projects as $p) {
        $title       = htmlspecialchars($p['title']       ?? '',  ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $category    = htmlspecialchars($p['category']    ?? '',  ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = htmlspecialchars($p['description'] ?? '',  ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $link        = htmlspecialchars($p['link']        ?? '#', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $image       = htmlspecialchars($p['image']       ?? '',  ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $tagsHTML = '';
        foreach (($p['tags'] ?? []) as $tag) {
            $t = htmlspecialchars($tag, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $tagsHTML .= "<span class=\"border border-white/10 px-2 py-0.5 rounded\">{$t}</span>";
        }

        $projectsHTML .= "\n                <div class=\"group relative rounded-lg overflow-hidden bg-black/60 border border-white/5 glow-border transition-all duration-300\">"
            . "\n                    <div class=\"aspect-[4/3] overflow-hidden\">"
            . "\n                        <img alt=\"{$title}\" class=\"w-full h-full object-cover grayscale transition-all duration-700 group-hover:scale-110 group-hover:grayscale-0 opacity-70 group-hover:opacity-100\" src=\"{$image}\" />"
            . "\n                    </div>"
            . "\n                    <div class=\"absolute inset-0 bg-gradient-to-t from-black via-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6\">"
            . "\n                        <div class=\"translate-y-4 group-hover:translate-y-0 transition-transform duration-300\">"
            . "\n                            <span class=\"text-[10px] font-hacker text-gray-400 mb-2 block tracking-widest\">{$category}</span>"
            . "\n                            <h4 class=\"text-lg font-bold text-white mb-1\">{$title}</h4>"
            . "\n                            <p class=\"text-xs text-gray-400 mb-3\">{$description}</p>"
            . "\n                            <div class=\"flex gap-2 text-[10px] font-hacker text-gray-500 mb-3\">{$tagsHTML}</div>"
            . "\n                            <a class=\"text-white text-xs font-hacker border-b border-white/20 pb-0.5 hover:border-white\" href=\"{$link}\">View_Project</a>"
            . "\n                        </div>"
            . "\n                    </div>"
            . "\n                </div>";
    }

    return preg_replace(
        '/(<div\s[^>]*grid-cols-1[^>]*md:grid-cols-2[^>]*lg:grid-cols-3[^>]*gap-6[^>]*>)([\s\S]*?)(<\/div>)/u',
        '${1}' . $projectsHTML . "\n            " . '${3}',
        $html, 1
    ) ?? $html;
}
?>
