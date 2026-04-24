<?php
session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';

if (empty($prompt)) {
    http_response_code(400);
    echo json_encode(['error' => 'Prompt is required']);
    exit;
}

$groqKey = $_ENV['GROQ_API_KEY'] ?? '';

function callGroq($model, $messages) {
    global $groqKey;
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $groqKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => 1000,
        'temperature' => 0.7
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
    $content = $data['choices'][0]['message']['content'] ?? '';
    $usage = $data['usage']['total_tokens'] ?? 0;
    return ['content' => trim($content), 'usage' => $usage];
}

try {
    $systemPrompt = "You are a senior UI/UX designer and prompt enhancement expert.\n\nYour task is to EXPAND the user's input into a highly detailed design specification.\n\nCRITICAL RULES:\n* User input is sacred. Never change name, technologies, theme, or contact preferences.\n* Use EXACT name provided. NEVER use placeholders.\n* Include ALL provided technologies.\n* Keep exact contact button concepts.\n\nOUTPUT:\nReturn only a long detailed design specification. No JSON. No code.";
    
    $res = callGroq('llama-3.3-70b-versatile', [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $prompt]
    ]);
    
    echo json_encode([
        'enhancedPrompt' => $res['content'],
        'tokens' => $res['usage']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
