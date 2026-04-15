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
        // Universal Normalization: handle templates with 'key' if 'id' is missing
        $id       = $field['id']       ?? $field['key'] ?? '';
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
                        while ($node->firstChild) {
                            $node->removeChild($node->firstChild);
                        }
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
                        $strVal = (string)$value;
                        if ($node->nodeName === 'a' && (str_starts_with($strVal, 'data:') || str_starts_with($strVal, 'http'))) {
                            $node->setAttribute('href', $strVal);
                            if (!$node->hasAttribute('download')) {
                                $node->setAttribute('download', 'Resume.pdf');
                            }
                        }
                        break;
                }
            }
        }
    }

    $output = $dom->saveHTML();
    $html   = str_replace('<?xml encoding="UTF-8">', '', $output);

    foreach ($fields as $field) {
        $id    = $field['id']   ?? $field['key'] ?? '';
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
function replaceRepeaterGeneric(string $html, string $parentKey, array $items): string
{
    $pattern = '/(<[^>]+data-edit-repeat=["\']' . preg_quote($parentKey, '/') . '["\'][^>]*>)([\s\S]*?)(<\/[^>]+>)/i';
    if (!preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) return $html;

    $innerContent  = $matches[2][0];
    $innerStart    = $matches[2][1];

    if (!preg_match('/<([a-z0-9]+)[\s\S]*?<\/\1>/i', $innerContent, $templateMatches)) return $html;
    $template = $templateMatches[0];

    $newInnerHtml = '';
    foreach ($items as $item) {
        $clone = $template;
        foreach ($item as $k => $v) {
            $val = is_array($v) ? implode(', ', $v) : (string)$v;
            $clone = str_replace("{{{$k}}}", htmlspecialchars($val, ENT_QUOTES, 'UTF-8'), $clone);
        }
        foreach ($item as $k => $v) {
            $val = is_array($v) ? implode(', ', $v) : (string)$v;
            $esc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            $keysToCheck = [$k, "{$parentKey}_{$k}", "{$parentKey}-{$k}"];
            foreach($keysToCheck as $kk) {
                $clone = preg_replace('/(<[^>]+data-edit=["\']' . preg_quote($kk, '/') . '["\'][^>]*>)([\s\S]*?)(<\/[^>]+>)/i', '$1' . $esc . '$3', $clone);
                $clone = preg_replace('/(<img[^>]+data-edit-img=["\']' . preg_quote($kk, '/') . '["\'][^>]*src=["\'])([^"\']*)(["\'])/i', '$1' . $esc . '$3', $clone);
                $clone = preg_replace('/(<a[^>]+data-edit-link=["\']' . preg_quote($kk, '/') . '["\'][^>]*href=["\'])([^"\']*)(["\'])/i', '$1' . $esc . '$3', $clone);
            }
            if ($k === 'icon') {
                 $clone = preg_replace('/(<span[^>]+class=["\'][^"\']*material-icons[^"\']*["\'][^>]*>)([\s\S]*?)(<\/span>)/i', '$1' . $esc . '$3', $clone);
            }
        }
        $newInnerHtml .= "\n" . $clone;
    }
    if (function_exists('_replaceInsideGrid')) return _replaceInsideGrid($html, $innerStart, $newInnerHtml);
    return substr($html, 0, $innerStart) . $newInnerHtml . substr($html, $innerStart + strlen($innerContent));
}
endif;

if (!function_exists('cssToXPath')):
function cssToXPath(string $selector): string
{
    // 1. Tokenize by whitespace (ignoring spaces inside brackets)
    $parts = preg_split('/\s+(?![^\[]*\])/', trim($selector));
    $xpathParts = [];

    foreach ($parts as $part) {
        if ($part === '>') { $xpathParts[] = '/'; continue; }

        $tag = '*';
        $conditions = [];

        // Identify Tag (if first char isn't a special one)
        if (preg_match('/^([a-zA-Z0-9*]+)/', $part, $m)) {
            $tag = $m[1];
            $part = substr($part, strlen($tag));
        }

        // Identify Classes (.class)
        if (preg_match_all('/\.([a-zA-Z0-9_\-]+)/', $part, $m)) {
            foreach ($m[1] as $class) {
                $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
            }
        }

        // Identify IDs (#id)
        if (preg_match_all('/#([a-zA-Z0-9_\-]+)/', $part, $m)) {
            foreach ($m[1] as $id) {
                $conditions[] = "@id='{$id}'";
            }
        }

        // Identify Attributes ([attr="val"])
        if (preg_match_all('/\[([a-zA-Z0-9_\-]+)(?:=(?:"([^"]*)"|\'([^\']*)\'|([^\]\s]*)))?\]/', $part, $m)) {
            foreach ($m[1] as $i => $attr) {
                $val = $m[2][$i] ?: $m[3][$i] ?: $m[4][$i];
                if ($val !== '' && $val !== null) {
                    $conditions[] = "@{$attr}='{$val}'";
                } else {
                    $conditions[] = "@{$attr}";
                }
            }
        }

        $xpathPart = ".//{$tag}";
        if ($conditions) {
            $xpathPart .= "[" . implode(' and ', $conditions) . "]";
        }
        $xpathParts[] = $xpathPart;
    }

    $final = implode('', $xpathParts);
    $final = str_replace(['.///', './/'], ['/', './/'], $final);
    if (!str_starts_with($final, '/')) $final = '/' . $final;

    return $final;
}
endif;

function replaceSkillsGrid(string $html, array $skills): string
{
    $skillsHTML = '';
    foreach ($skills as $skill) {
        $icon = htmlspecialchars($skill['icon'] ?? 'code', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = htmlspecialchars($skill['name'] ?? '',     ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $skillsHTML .= "\n <div class=\"group bg-black/40 border border-white/5 glow-border rounded-lg p-6 hover:bg-white/5 transition-all duration-300 flex flex-col items-center justify-center gap-3 cursor-default\">\n <span class=\"material-icons text-3xl text-gray-600 group-hover:text-white transition-colors\">{$icon}</span>\n <span class=\"font-hacker text-xs text-gray-400 group-hover:text-white tracking-wider transition-colors\">{$name}</span>\n </div>";
    }
    if (!preg_match('/<div[^>]+\bclass=["\'][^"\']*\bgrid-cols-2\b[^"\']*\bmd:grid-cols-4\b[^"\']*\blg:grid-cols-5\b[^"\']*["\'][^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) return $html;
    return _replaceInsideGrid($html, (int)$match[0][1] + strlen($match[0][0]), $skillsHTML);
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
        $projectsHTML .= "\n <div class=\"group relative rounded-lg overflow-hidden bg-black/60 border border-white/5 glow-border transition-all duration-300\">\n <div class=\"aspect-[4/3] overflow-hidden\">\n <img alt=\"{$title}\" class=\"w-full h-full object-cover grayscale transition-all duration-700 group-hover:scale-110 group-hover:grayscale-0 opacity-70 group-hover:opacity-100\" src=\"{$image}\" />\n </div>\n <div class=\"absolute inset-0 bg-gradient-to-t from-black via-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6\">\n <div class=\"translate-y-4 group-hover:translate-y-0 transition-transform duration-300\">\n <span class=\"text-[10px] font-hacker text-gray-400 mb-2 block tracking-widest\">{$category}</span>\n <h4 class=\"text-lg font-bold text-white mb-1\">{$title}</h4>\n <p class=\"text-xs text-gray-400 mb-3\">{$description}</p>\n <div class=\"flex gap-2 text-[10px] font-hacker text-gray-500 mb-3\">{$tagsHTML}</div>\n <a class=\"text-white text-xs font-hacker border-b border-white/20 pb-0.5 hover:border-white\" href=\"{$link}\">View_Project</a>\n </div>\n </div>\n </div>";
    }
    if (!preg_match('/<div[^>]+\bclass=["\'][^"\']*\bgrid-cols-1\b[^"\']*\bmd:grid-cols-2\b[^"\']*\blg:grid-cols-3\b[^"\']*["\'][^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) return $html;
    return _replaceInsideGrid($html, (int)$match[0][1] + strlen($match[0][0]), $projectsHTML);
}

function _replaceInsideGrid(string $html, int $innerStart, string $newContent): string
{
    $depth = 1; $pos = $innerStart; $len = strlen($html);
    while ($pos < $len && $depth > 0) {
        $nextOpen = strpos($html, '<div', $pos); $nextClose = strpos($html, '</div>', $pos);
        if ($nextClose === false) break;
        if ($nextOpen !== false && $nextOpen < $nextClose) { $depth++; $pos = $nextOpen + 4; }
        else { $depth--; if ($depth === 0) return substr($html, 0, $innerStart) . $newContent . "\n " . substr($html, $nextClose); $pos = $nextClose + 6; }
    }
    return $html;
}
