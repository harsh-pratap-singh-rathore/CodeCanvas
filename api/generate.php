<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_time_limit(600); // Allow up to 10 minutes for slow Ollama models

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate Limiting (5 per minute per user)
$userId = $_SESSION['user_id'];
$rateLimitKey = 'rate_limit_' . $userId;
$currentTime = time();
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = [];
}
$_SESSION[$rateLimitKey] = array_filter($_SESSION[$rateLimitKey], function($time) use ($currentTime) {
    return ($currentTime - $time) < 60;
});
if (count($_SESSION[$rateLimitKey]) >= 5) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Try again in a minute.']);
    exit;
}
$_SESSION[$rateLimitKey][] = $currentTime;

$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';

if (empty($prompt)) {
    http_response_code(400);
    echo json_encode(['error' => 'Prompt is required']);
    exit;
}

$groqKey = $_ENV['GROQ_API_KEY'] ?? ''; 
$ollamaUrl = $_ENV['OLLAMA_URL'] ?? 'http://127.0.0.1:11434/api/generate';

function groqRequest($messages, $max_tokens, $temperature) {
    global $groqKey;
    if (!$groqKey) throw new Exception("GROQ_API_KEY is not set.");
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $groqKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'llama-3.3-70b-versatile',
        'messages' => $messages,
        'max_tokens' => $max_tokens,
        'temperature' => $temperature,
        'stream' => false
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $msg = "Groq API Error ($httpCode): " . ($err ?: $response);
        throw new Exception($msg);
    }
    $data = json_decode($response, true);
    return [
        'content' => trim($data['choices'][0]['message']['content'] ?? ''),
        'usage' => $data['usage']['total_tokens'] ?? 0
    ];
}

function ollamaRequest($prompt_text, $num_predict = 8192, $retries = 2) {
    global $ollamaUrl;
    
    for ($i = 0; $i <= $retries; $i++) {
        $ch = curl_init($ollamaUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gemma4:31b-cloud', // Must match their exact model config
            'prompt' => $prompt_text,
            'stream' => false,
            'options' => [
                'temperature' => 0.4,
                'num_predict' => $num_predict,
                'num_ctx' => 16384
            ]
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 minutes max
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'content' => trim($data['response'] ?? ''),
                'usage' => ($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0)
            ];
        }
        
        // If it's a server overload error (502, 503, 504), wait and retry
        if ($i < $retries && in_array($httpCode, [502, 503, 504])) {
            sleep(5); // Wait 5 seconds before retrying
            continue;
        }
        
        throw new Exception("Ollama API Error ($httpCode)");
    }
}

// Stage 0: Enhance Logic
function executeEnhance($prompt) {
    $systemPrompt = "You are a senior UI/UX designer and prompt enhancement expert.\n\nYour task is to EXPAND the user's input into a highly detailed design specification.\n\nCRITICAL RULES:\n* User input is sacred. Never change name, technologies, theme, or contact preferences.\n* Use EXACT name provided. NEVER use placeholders.\n* Include ALL provided technologies.\n* Keep exact contact button concepts.\n\nOUTPUT:\nReturn only a long detailed design specification. No JSON. No code.";
    return groqRequest([
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $prompt]
    ], 1000, 0.7);
}

// Stage 1: JSON Blueprint Logic
function executeStage1($enhancedPrompt) {
    $systemPrompt = "You are a portfolio data engineer. Convert specifications into STRICT JSON.\n\nJSON STRUCTURE (MANDATORY):\n{\n  \"designSystem\": { \"primaryColor\": \"#hex\", \"accentColor\": \"#hex\", \"backgroundColor\": \"#hex\", \"textColor\": \"#hex\", \"font\": \"\", \"style\": \"\" },\n  \"navbar\": { \"brand\": \"\", \"links\": [\"About\", \"Projects\", \"Skills\", \"Contact\"] },\n  \"hero\": { \"name\": \"\", \"title\": \"\", \"subtitle\": \"\", \"cta\": \"\" },\n  \"about\": { \"heading\": \"\", \"description\": \"80+ words\" },\n  \"projects\": { \"heading\": \"\", \"items\": [ { \"title\": \"\", \"description\": \"40+ words\", \"tags\": [] } ] },\n  \"skills\": { \"heading\": \"\", \"items\": [] },\n  \"contact\": { \"heading\": \"\", \"description\": \"40+ words\", \"type\": \"\", \"platforms\": [] },\n  \"footer\": { \"text\": \"\" }\n}\n\nSTRICT VALIDATION:\n* ALL keys must exist. No incomplete JSON.\n* Content rules: projects >= 3, skills >= 8, about >= 80 words.\n\nOUTPUT: Return ONLY valid JSON.";
    $res = groqRequest([
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $enhancedPrompt]
    ], 1500, 0.7);
    $content = $res['content'];
    if (strpos($content, '```') !== false) {
        if (preg_match('/```(?:json)?([\s\S]*?)```/', $content, $matches)) {
            $content = trim($matches[1]);
        }
    }
    $blueprint = json_decode($content, true);
    if (!is_array($blueprint) || !isset($blueprint['hero']['name'])) {
        throw new Exception("Blueprint Validation Failed: Name missing or invalid JSON. \n" . $content);
    }
    return ['content' => escapeshellarg(json_encode($blueprint)), 'blueprint' => $blueprint, 'usage' => $res['usage']];
}

// Stage 2: Consistent HTML Generation Logic
function executeStage2($blueprintData) {
    $prompt = "You are a world-class UI/UX designer and intelligent design engine.\nYour job is to generate a UNIQUE portfolio design while STRICTLY respecting user intent for: " . ($blueprintData['hero']['name'] ?? 'Portfolio') . ".\n\n---\nPRIORITY RULE (VERY IMPORTANT)\n1. USER INTENT = HIGHEST PRIORITY\n2. DESIGN VARIATION = SECONDARY\n\n---\nUSER INTENT HANDLING\nIf user specifies theme (dark, light, neon, creamy, etc.), layout, colors, typography, or style (brutalism, glassmorphism, etc.), YOU MUST strictly follow those instructions, NOT override them, NOT replace them with your own style.\n\n---\nEXAMPLE\nUser says: \"minimal creamy portfolio\"\nYOU MUST: use minimal layout, use creamy palette.\nDO NOT switch to brutalism. DO NOT add neon effects.\n\n---\nFLEXIBLE DESIGN LOGIC\nOnly apply creative variation IF user did NOT define style clearly or gave a vague prompt.\n\n---\nHYBRID MODE\nIf user gives partial instruction (Example: \"dark portfolio for developer\"), keep dark theme fixed but vary layout, animations, components.\n\n---\nANTI-REPETITION RULE\nEven when following user intent: DO NOT repeat same layout structure, DO NOT reuse same UI components, DO NOT reuse same spacing patterns.\n\n---\nSTRUCTURE FLEXIBILITY\nYou may reorder sections, merge sections, change layout style BUT MUST keep user intent intact.\n\n---\nCOMPONENT VARIATION\nSkills / Projects / Contact: vary layout styles BUT stay consistent with user theme.\n\n---\nTYPOGRAPHY RULE\nIf user specifies font style: respect it. If not: choose based on design theme.\n\n---\nCOLOR RULE\nIf user specifies colors: strictly use them. If not: generate unique palette.\n\n---\nDATA SOURCE & USER INTENT\nUse the provided JSON data EXACTLY for your structure, text, and design directions:\n" . json_encode($blueprintData) . "\nDo not modify values. Do not replace name or content.\n\n---\nEDITOR COMPATIBILITY (CRITICAL)\nYou MUST preserve all data-edit attributes for ALL editable elements. Use structured keys like:\ndata-edit=\"hero.name\", data-edit=\"hero.title\", data-edit=\"about.description\", data-edit=\"projects.i.title\", data-edit=\"projects.i.description\", data-edit=\"skills.i\", data-edit=\"contact.description\"\nYou MUST wrap the entire main content inside: <div id=\"portfolio-root\">...</div>\n\n---\nFAIL CONDITIONS\nFAIL if: user-defined theme is ignored, design contradicts prompt, output looks familiar to generic templates.\n\n---\nOUTPUT\nGenerate a COMPLETE single-file HTML layout with inline CSS. Format: <!DOCTYPE html> followed by full HTML. Return ONLY valid HTML. No explanations.\n\nYOU ARE NOT JUST CREATIVE. YOU ARE INTELLIGENT AND CONTROLLED. BALANCE BOTH.";
    $res = ollamaRequest($prompt, 8192);
    $html = $res['content'];
    if (strpos($html, '```') !== false) {
        if (preg_match('/```(?:html)?([\s\S]*?)```/', $html, $matches)) {
            $html = trim($matches[1]);
        }
    }
    return ['content' => $html, 'usage' => $res['usage']];
}

try {
    $totalTokens = 0;
    
    $enhancedResult = executeEnhance($prompt);
    $totalTokens += $enhancedResult['usage'];
    
    // Stage 1 (With Retry)
    try {
        $stage1Result = executeStage1($enhancedResult['content']);
    } catch (Exception $e) {
        $stage1Result = executeStage1($enhancedResult['content']); // Retry once
    }
    $totalTokens += $stage1Result['usage'];
    
    // Stage 2 (With Retry)
    try {
        $stage2Result = executeStage2($stage1Result['blueprint']);
    } catch (Exception $e) {
        $stage2Result = executeStage2($stage1Result['blueprint']); // Retry once
    }
    $totalTokens += $stage2Result['usage'];
    
    $htmlRaw = $stage2Result['content'];
    
    // Validate DOCTYPE
    if (stripos($htmlRaw, '<!DOCTYPE') === false) {
        // Fallback injection if Ollama misses it
        $htmlRaw = "<!DOCTYPE html>\n" . preg_replace('/^<html/i', '<html', $htmlRaw);
    }
    
    if (stripos($htmlRaw, '<!DOCTYPE html>') === false && stripos($htmlRaw, '<html') === false) {
         throw new Exception("HTML generation severely corrupted.");
    }

    $userId = $_SESSION['user_id'];
    $title = mb_strimwidth($prompt, 0, 40, "...");
    
    // INSERT DB ROW FIRST TO GET ID
    $stmt = $pdo->prepare("INSERT INTO projects (user_id, project_name, status, html_path, content_json, created_at) VALUES (?, ?, 'draft', '', ?, NOW())");
    $stmt->execute([$userId, $title, json_encode($stage1Result['blueprint'])]);
    $dbId = $pdo->lastInsertId();

    // USE DB ID FOR FOLDER
    $outputDir = APP_ROOT . '/public/output/' . $dbId;
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    file_put_contents($outputDir . '/index.html', $htmlRaw);
    
    $htmlPath = '/public/output/' . $dbId . '/index.html';
    
    $pdo->prepare("UPDATE projects SET html_path = ? WHERE id = ?")->execute([$htmlPath, $dbId]);

    echo json_encode([
        'url' => BASE_URL . $htmlPath,
        'id' => $dbId,
        'db_id' => $dbId,
        'totalTokens' => $totalTokens
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
