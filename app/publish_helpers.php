<?php
require_once __DIR__ . '/../config/bootstrap.php';
/**
 * CodeCanvas — Publishing Helper Functions
 * Central include for ALL schema-based HTML injection functions.
 * Safe to require_once from multiple files — all wrapped in function_exists guards.
 */

// ══════════════════════════════════════════════════════════════════
// SCHEMA-BASED HTML REPLACEMENT ENGINE
// ══════════════════════════════════════════════════════════════════

if (!function_exists('applyUserDataToHTML')):
/**
 * Apply all user data fields to the raw HTML string using schema selectors.
 * Uses DOMDocument + XPath to find elements by CSS selector.
 */
function applyUserDataToHTML(string $html, array $fields, array $userData): string
{
    if (empty($html)) return $html;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    foreach ($fields as $field) {
        $id       = $field['id']       ?? '';
        $type     = $field['type']     ?? 'text';
        $selector = $field['selector'] ?? '';
        $value    = $userData[$id]     ?? null;

        if ($value === null || $value === '' || !$selector) continue;

        $xPathQuery = cssToXPath($selector);
        $nodes      = $xpath->query($xPathQuery);

        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $node) {
                switch ($type) {
                    case 'text':
                    case 'email':
                    case 'textarea':
                        // Safe DOM child removal
                        while ($node->firstChild) {
                            $node->removeChild($node->firstChild);
                        }
                        // Handle \n → <br> (important for full_name two-line display)
                        $strVal = trim((string)$value);
                        $lines  = explode("\n", $strVal);
                        foreach ($lines as $i => $line) {
                            if ($i > 0) $node->appendChild($dom->createElement('br'));
                            $node->appendChild($dom->createTextNode(trim($line)));
                        }
                        if ($type === 'email' && $node->nodeName === 'a') {
                            $node->setAttribute('href', 'mailto:' . $strVal);
                        }
                        break;

                    case 'image':
                        $strVal = (string)$value;
                        if (str_starts_with($strVal, 'data:') || str_starts_with($strVal, 'http')) {
                            if ($node->nodeName === 'img') {
                                $node->setAttribute('src', $strVal);
                            } else {
                                $style    = $node->getAttribute('style');
                                $newStyle = preg_replace('/background-image:\s*url\([^)]*\)/i', 'background-image: url("' . $strVal . '")', $style);
                                if ($newStyle === $style) $newStyle .= '; background-image: url("' . $strVal . '");';
                                $node->setAttribute('style', $newStyle);
                            }
                        }
                        break;

                    case 'file':
                        // Resume / PDF — update anchor href + ensure download attribute
                        $strVal = (string)$value;
                        if ($node->nodeName === 'a' && (str_starts_with($strVal, 'data:') || str_starts_with($strVal, 'http'))) {
                            $node->setAttribute('href', $strVal);
                            if (!$node->hasAttribute('download')) {
                                $node->setAttribute('download', 'Resume.pdf');
                            }
                        }
                        break;

                    case 'array':
                        // Handled in post-DOM pass below
                        break;

                    case 'group':
                        // Handled in post-DOM pass below
                        break;
                }
            }
        }
    }

    $output = $dom->saveHTML();
    $html   = str_replace('<?xml encoding="UTF-8">', '', $output);

    // Post-DOM pass: generic repeater handling
    foreach ($fields as $field) {
        $id    = $field['id']   ?? '';
        $type  = $field['type'] ?? '';
        $value = $userData[$id] ?? null;
        if (!$value || !is_array($value)) continue;

        if ($type === 'array' || $type === 'group') {
            if ($id === 'typing_words') {
                $html = replaceTypingWords($html, (array)$value);
            } else {
                $html = replaceRepeaterGeneric($html, $id, (array)$value);
            }
        }
    }

    return $html;
}
endif;

if (!function_exists('replaceRepeaterGeneric')):
/**
 * Generic repeater replacement for PHP publishing.
 * Mirror of the JavaScript updateRepeaterGeneric.
 */
function replaceRepeaterGeneric(string $html, string $parentKey, array $items): string
{
    // Find the container with data-edit-repeat
    $pattern = '/(<[^>]+data-edit-repeat=["\']' . preg_quote($parentKey, '/') . '["\'][^>]*>)([\s\S]*?)(<\/[^>]+>)/i';
    
    if (!preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
        return $html;
    }

    $containerOpen = $matches[1][0];
    $innerContent  = $matches[2][0];
    $containerClose = $matches[3][0];
    $innerStart    = $matches[2][1];

    // Extract the template (first child)
    // We'll use a simplified approach since nested regex is hard in PHP without DOM
    // But for publishing, we usually have a clean template.
    if (!preg_match('/<([a-z0-9]+)[\s\S]*?<\/\1>/i', $innerContent, $templateMatches)) {
        return $html;
    }
    $template = $templateMatches[0];

    $newInnerHtml = '';
    foreach ($items as $item) {
        $clone = $template;
        
        // 1. Recursive placeholder replacement {{key}}
        foreach ($item as $k => $v) {
            $val = is_array($v) ? implode(', ', $v) : (string)$v;
            $clone = str_replace("{{{$k}}}", htmlspecialchars($val, ENT_QUOTES, 'UTF-8'), $clone);
        }

        // 2. data-edit attribute replacement
        foreach ($item as $k => $v) {
            $val = is_array($v) ? implode(', ', $v) : (string)$v;
            $esc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            
            // Text: data-edit="key"
            $keysToCheck = [$k, "{$parentKey}_{$k}", "{$parentKey}-{$k}"];
            foreach($keysToCheck as $kk) {
                // InnerText
                $clone = preg_replace(
                    '/(<[^>]+data-edit=["\']' . preg_quote($kk, '/') . '["\'][^>]*>)([\s\S]*?)(<\/[^>]+>)/i',
                    '$1' . $esc . '$3',
                    $clone
                );
                // Image
                $clone = preg_replace(
                    '/(<img[^>]+data-edit-img=["\']' . preg_quote($kk, '/') . '["\'][^>]*src=["\'])([^"\']*)(["\'])/i',
                    '$1' . $esc . '$3',
                    $clone
                );
                // Link
                $clone = preg_replace(
                    '/(<a[^>]+data-edit-link=["\']' . preg_quote($kk, '/') . '["\'][^>]*href=["\'])([^"\']*)(["\'])/i',
                    '$1' . $esc . '$3',
                    $clone
                );
            }

            // Special case for material-icons
            if ($k === 'icon') {
                 $clone = preg_replace(
                    '/(<span[^>]+class=["\'][^"\']*material-icons[^"\']*["\'][^>]*>)([\s\S]*?)(<\/span>)/i',
                    '$1' . $esc . '$3',
                    $clone
                 );
            }
        }
        $newInnerHtml .= "\n" . $clone;
    }

    // Replace the entire inner content of the container
    // Need to find the REAL end of the container (balanced tags)
    // For now, we'll assume the container doesn't have other children we care about
    // or we'll use our built-in _replaceInsideGrid logic if we want to be safe.
    
    // Actually, let's use the provided _replaceInsideGrid if it's there
    if (function_exists('_replaceInsideGrid')) {
        return _replaceInsideGrid($html, $innerStart, $newInnerHtml);
    }

    return substr($html, 0, $innerStart) . $newInnerHtml . substr($html, $innerStart + strlen($innerContent));
}
endif;

if (!function_exists('cssToXPath')):
/**
 * Very basic CSS selector → XPath converter.
 * Supports: .class, #id, tag, tag.class, parent child, parent > child
 */
function cssToXPath(string $selector): string
{
    $parts      = preg_split('/\s+/', trim($selector));
    $xpathParts = [];

    foreach ($parts as $part) {
        if ($part === '>') { $xpathParts[] = '/'; continue; }

        if (preg_match('/^([a-zA-Z0-9*]*)\.([a-zA-Z0-9_\-]+)$/', $part, $m)) {
            $tag          = $m[1] ?: '*';
            $class        = $m[2];
            $xpathParts[] = ".//{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
            continue;
        }

        if (str_starts_with($part, '.')) {
            $class        = substr($part, 1);
            $xpathParts[] = ".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
            continue;
        }

        if (str_starts_with($part, '#')) {
            $id           = substr($part, 1);
            $xpathParts[] = ".//*[@id='{$id}']";
            continue;
        }

        $xpathParts[] = ".//{$part}";
    }

    $final = implode('', $xpathParts);
    $final = str_replace(['.///', './/'], ['/', './/'], $final);
    if (!str_starts_with($final, '/')) $final = '/' . $final;

    return $final;
}
endif;


/**
 * CodeCanvas — Publishing Helper Functions
 * Handles replacement of dynamic sections in the developer template.
 *
 * replaceSkillsGrid()   — Replaces the tech-stack grid with user's skills
 * replaceTypingWords()  — Swaps the typing animation word list
 * replaceProjectsGrid() — Replaces featured projects grid
 *
 * Both grid functions use a BALANCED-DIV WALK instead of a lazy regex,
 * so ALL old content inside the container is replaced cleanly — no duplicates.
 */

/**
 * Replace the entire skills grid content.
 * Finds: <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4"> ... </div>
 */
function replaceSkillsGrid(string $html, array $skills): string
{
    $skillsHTML = '';
    foreach ($skills as $skill) {
        $icon = htmlspecialchars($skill['icon'] ?? 'code', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = htmlspecialchars($skill['name'] ?? '',     ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $skillsHTML .=
            "\n                <div class=\"group bg-black/40 border border-white/5 glow-border rounded-lg p-6 hover:bg-white/5 transition-all duration-300 flex flex-col items-center justify-center gap-3 cursor-default\">"
            . "\n                    <span class=\"material-icons text-3xl text-gray-600 group-hover:text-white transition-colors\">{$icon}</span>"
            . "\n                    <span class=\"font-hacker text-xs text-gray-400 group-hover:text-white tracking-wider transition-colors\">{$name}</span>"
            . "\n                </div>";
    }

    // Locate the opening tag of the skills grid
    if (!preg_match(
        '/<div[^>]+\bclass=["\'][^"\']*\bgrid-cols-2\b[^"\']*\bmd:grid-cols-4\b[^"\']*\blg:grid-cols-5\b[^"\']*["\'][^>]*>/i',
        $html, $match, PREG_OFFSET_CAPTURE
    )) {
        return $html; // grid not found — return unchanged
    }

    $openTag    = $match[0][0];
    $openStart  = (int)$match[0][1];
    $innerStart = $openStart + strlen($openTag);

    return _replaceInsideGrid($html, $innerStart, $skillsHTML);
}

/**
 * Replace the typing animation words array in the inline <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;.
 */
function replaceTypingWords(string $html, array $words): string
{
    $wordsJSON = json_encode(array_values($words), JSON_UNESCAPED_UNICODE);
    $result = preg_replace(
        '/window\.words\s*=\s*\[[^\]]*\]/',
        'window.words = ' . $wordsJSON,
        $html,
        1
    );
    return $result ?? $html;
}

/**
 * Replace the entire projects grid content.
 * Finds: <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"> ... </div>
 */
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

        $projectsHTML .=
            "\n                <div class=\"group relative rounded-lg overflow-hidden bg-black/60 border border-white/5 glow-border transition-all duration-300\">"
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

    // Locate the opening tag of the projects grid
    if (!preg_match(
        '/<div[^>]+\bclass=["\'][^"\']*\bgrid-cols-1\b[^"\']*\bmd:grid-cols-2\b[^"\']*\blg:grid-cols-3\b[^"\']*["\'][^>]*>/i',
        $html, $match, PREG_OFFSET_CAPTURE
    )) {
        return $html; // grid not found — return unchanged
    }

    $openTag    = $match[0][0];
    $openStart  = (int)$match[0][1];
    $innerStart = $openStart + strlen($openTag);

    return _replaceInsideGrid($html, $innerStart, $projectsHTML);
}

/**
 * Internal helper: given the position right after a grid container's opening tag,
 * walks forward counting nested <div> depth to find the matching closing </div>,
 * then replaces everything between openStart and that </div> with $newContent.
 */
function _replaceInsideGrid(string $html, int $innerStart, string $newContent): string
{
    $depth = 1;
    $pos   = $innerStart;
    $len   = strlen($html);

    while ($pos < $len && $depth > 0) {
        $nextOpen  = strpos($html, '<div',  $pos);
        $nextClose = strpos($html, '</div>', $pos);

        if ($nextClose === false) break; // malformed HTML — give up

        if ($nextOpen !== false && $nextOpen < $nextClose) {
            $depth++;
            $pos = $nextOpen + 4;       // skip past '<div'
        } else {
            $depth--;
            if ($depth === 0) {
                // Splice: keep HTML before innerStart, insert new content, keep from </div> onward
                return substr($html, 0, $innerStart)
                    . $newContent . "\n            "
                    . substr($html, $nextClose);
            }
            $pos = $nextClose + 6;      // skip past '</div>'
        }
    }

    return $html; // fallback: nothing replaced
}
