<?php
/**
 * PROJECT PUBLISHER — Schema-Based Portfolio Generator
 * CodeCanvas | Loads template + schema, applies user data, outputs final HTML
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

require_once APP_ROOT . '/app/publish_helpers.php';

$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    header("Location: " . BASE_URL . '/app/dashboard.php');
    exit;
}

try {
    // ── 1. Fetch project, template & user info ────────────────────
    // Join users table to get name/email for notifications
    $stmt = $pdo->prepare(
        "SELECT p.*, t.name AS template_name, t.folder_path, u.email, u.name AS user_name
         FROM projects p
         JOIN templates t ON p.template_id = t.id
         JOIN users u ON p.user_id = u.id
         WHERE p.id = ? AND p.user_id = ?"
    );
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header("Location: " . BASE_URL . '/app/dashboard.php');
        exit;
    }

    // ── 2. Load template HTML ─────────────────────────────────────
    $folderPath = rtrim($project['folder_path'] ?? 'templates/developer/', '/');
    $absFolder = __DIR__ . '/../' . $folderPath;
    $entryFile = null;

    $candidates = ['index.html', 'code.html', 'index.htm'];
    foreach ($candidates as $c) {
        if (file_exists($absFolder . '/' . $c)) {
            $entryFile = '/' . $c;
            break;
        }
    }
    
    if (!$entryFile) {
        $subDirs = glob($absFolder . '/*', GLOB_ONLYDIR);
        foreach ($subDirs as $dir) {
            foreach ($candidates as $c) {
                if (file_exists($dir . '/' . $c)) {
                    $entryFile = '/' . basename($dir) . '/' . $c;
                    break 2;
                }
            }
        }
    }
    
    if (!$entryFile) $entryFile = '/index.html';
    $templatePath = $absFolder . $entryFile;

    if (!file_exists($templatePath)) {
        die('Template file not found: ' . htmlspecialchars($templatePath));
    }
    $html = file_get_contents($templatePath);

    // ── 3. Load schema ────────────────────────────────────────────
    $schemaPath = __DIR__ . '/../' . $folderPath . '/schema.json';
    if (!file_exists($schemaPath)) {
        die('Schema file not found: ' . htmlspecialchars($schemaPath));
    }
    $schema = json_decode(file_get_contents($schemaPath), true);
    
    if ($schema && !isset($schema['fields']) && isset($schema['sections'])) {
        $schema['fields'] = [];
        foreach ($schema['sections'] as $sec) {
            if (isset($sec['fields']) && is_array($sec['fields'])) {
                foreach ($sec['fields'] as $x) {
                    if (!isset($x['id']) && isset($x['key'])) {
                        $x['id'] = $x['key'];
                    }
                    $schema['fields'][] = $x;
                }
            }
        }
    }
    if (!$schema || !isset($schema['fields'])) {
        die('Invalid schema.json');
    }

    // ── 4. Load user data ─────────────────────────────────────────
    $userData = [];
    if (!empty($project['content_json'])) {
        $decoded = json_decode($project['content_json'], true);
        if (is_array($decoded)) {
            $userData = $decoded;
        }
    }

    // ── 5. Apply user data to HTML ────────────────────────────────
    $html = applyUserDataToHTML($html, $schema['fields'], $userData);

    // ── 5.1 Inject Project ID & Contact Logic ──────────────────────
    $bridgeScript = "
    <script>
        const BASE_URL = " . json_encode(BASE_URL) . ";
        window.CODECANVAS_PROJECT_ID = " . json_encode($projectId) . ";
        window.CODECANVAS_API_BASE = " . json_encode(rtrim(BASE_URL, '/') . '/../app/api') . ";
        
        document.addEventListener('DOMContentLoaded', () => {
            const contactForm = document.querySelector('#contact form');
            if (contactForm) {
                const submitBtn = contactForm.querySelector('button');
                const originalText = submitBtn ? submitBtn.innerText : 'SEND_MESSAGE';
                
                contactForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerText = 'SENDING...';
                    }
                    
                    const formData = {
                        project_id: window.CODECANVAS_PROJECT_ID,
                        name: contactForm.querySelector('#name')?.value,
                        email: contactForm.querySelector('#email')?.value,
                        subject: contactForm.querySelector('#subject')?.value,
                        message: contactForm.querySelector('#message')?.value
                    };
                    
                    try {
                        const res = await fetch(window.CODECANVAS_API_BASE + '/contact.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(formData)
                        });
                        const data = await res.json();
                        alert(data.message);
                        if (data.success) contactForm.reset();
                    } catch (err) {
                        console.error('Contact error:', err);
                        alert('Error sending message. Please try again.');
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerText = originalText;
                        }
                    }
                });
            }
        });
    </script>";
    
    $html = str_replace('</body>', $bridgeScript . "\n</body>", $html);

    // ── 6. Handle Vercel Deployment ─────────────────────────────
    require_once APP_ROOT . '/app/services/VercelDeployService.php';
    require_once APP_ROOT . '/app/StaticBuilder.php';
    
    $builder = new StaticBuilder($pdo, $projectId);
    $slug = $_POST['slug'] ?? $project['custom_slug'] ?? '';
    if (empty($slug)) {
        $slug = preg_replace('/[^a-z0-9]/', '-', strtolower($project['project_name'] ?? 'portfolio'));
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        if (empty($project['custom_slug'])) $slug .= '-' . substr(md5(uniqid()), 0, 4);
    }

    try {
        $buildDir  = $builder->build($slug);
        $vercelService = new VercelDeployService();
        $liveUrl = $vercelService->deploy($buildDir, $slug);

        // Update DB status
        $upd = $pdo->prepare("UPDATE projects SET status = 'published', publish_status = 'published', custom_slug = ?, live_url = ? WHERE id = ?");
        $upd->execute([$slug, $liveUrl, $projectId]);

        // ── 7. Dispatch Notification Email ──────────────────────────
        try {
            require_once APP_ROOT . '/app/events/PortfolioDeployedEvent.php';
            PortfolioDeployedEvent::dispatch(
                $project['email'], 
                $project['user_name'], 
                $project['project_name'], 
                $liveUrl
            );
        } catch (Exception $e) {
            // Log but don't fail publishing if email fails
            error_log("[Publish] Email dispatch failed: " . $e->getMessage());
        }

        header('Location: dashboard.php?published=' . $projectId . '&slug=' . urlencode($slug) . '&live_url=' . urlencode($liveUrl));
        exit;

    } catch (Exception $e) {
        $resetStatus = $pdo->prepare("UPDATE projects SET publish_status = 'failed', build_log = ? WHERE id = ?");
        $resetStatus->execute([$e->getMessage(), $projectId]);
        header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
        exit;
    }

} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}

// ══════════════════════════════════════════════════════════════════
// SCHEMA-BASED HTML REPLACEMENT ENGINE (PHP)
// ══════════════════════════════════════════════════════════════════

function applyUserDataToHTML(string $html, array $fields, array $userData): string
{
    if (empty($html)) return $html;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    foreach ($fields as $field) {
        $id       = $field['id']       ?? $field['key'] ?? '';
        $type     = $field['type']     ?? 'text';
        $selector = $field['selector'] ?? '';
        $value    = $userData[$id]     ?? null;

        if ($value === null || $value === '' || !$selector) continue;

        $xPathQuery = cssToXPath($selector);
        $nodes = $xpath->query($xPathQuery);

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
                                $style = $node->getAttribute('style');
                                $newStyle = preg_replace('/background-image:\s*url\([^)]*\)/i', 'background-image: url("' . $strVal . '")', $style);
                                if ($newStyle === $style) $newStyle .= '; background-image: url("' . $strVal . '");';
                                $node->setAttribute('style', $newStyle);
                            }
                        }
                        break;
                }
            }
        }
    }

    $output = $dom->saveHTML();
    $html = str_replace('<?xml encoding="UTF-8">', '', $output);

    foreach ($fields as $field) {
        $id = $field['id'] ?? $field['key'] ?? '';
        $type = $field['type'];
        $value = $userData[$id] ?? null;
        if (!$value) continue;

        if ($type === 'array' && $id === 'skills')   $html = replaceSkillsGrid($html, (array)$value);
        if ($type === 'array' && $id === 'typing_words') $html = replaceTypingWords($html, (array)$value);
        if ($type === 'group' && $id === 'projects') $html = replaceProjectsGrid($html, (array)$value);
    }
    return $html;
}

function cssToXPath(string $selector): string
{
    $parts = preg_split('/\s+(?![^\[]*\])/', trim($selector));
    $xpathParts = [];
    foreach ($parts as $part) {
        if ($part === '>') { $xpathParts[] = '/'; continue; }
        $tag = '*';
        $conditions = [];
        if (preg_match('/^([a-zA-Z0-9*]+)/', $part, $m)) {
            $tag = $m[1];
            $part = substr($part, strlen($tag));
        }
        if (preg_match_all('/\.([a-zA-Z0-9_\-]+)/', $part, $m)) {
            foreach ($m[1] as $class) $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
        }
        if (preg_match_all('/#([a-zA-Z0-9_\-]+)/', $part, $m)) {
            foreach ($m[1] as $id) $conditions[] = "@id='{$id}'";
        }
        if (preg_match_all('/\[([a-zA-Z0-9_\-]+)(?:=(?:"([^"]*)"|\'([^\']*)\'|([^\]\s]*)))?\]/', $part, $m)) {
            foreach ($m[1] as $i => $attr) {
                $val = $m[2][$i] ?: $m[3][$i] ?: $m[4][$i];
                if ($val !== '' && $val !== null) $conditions[] = "@{$attr}='{$val}'";
                else $conditions[] = "@{$attr}";
            }
        }
        $xpathPart = ".//{$tag}";
        if ($conditions) $xpathPart .= "[" . implode(' and ', $conditions) . "]";
        $xpathParts[] = $xpathPart;
    }
    $final = implode('', $xpathParts);
    $final = str_replace(['.///', './/'], ['/', './/'], $final);
    if (!str_starts_with($final, '/')) $final = '/' . $final;
    return $final;
}
?>
