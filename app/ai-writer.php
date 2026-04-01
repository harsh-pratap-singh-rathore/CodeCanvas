<?php
/**
 * AI WRITER - Backend for Groq API
 * Receives keywords and context, returns generated content.
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/app/core/auth.php';

// Load API Keys
$config = require APP_ROOT . '/config/api_keys.php';
$apiKey = $config['groq']['api_key'] ?? '';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'AI API key not configured. Please add your Groq key to config/api_keys.php']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $keywords = trim($input['keywords'] ?? '');
    $context = trim($input['context'] ?? ''); // e.g. "About Me section for a developer portfolio"

    if (empty($keywords)) {
        throw new Exception('Please provide some keywords or a brief description.');
    }

    $prompt = "You are a professional copywriter for a website builder called CodeCanvas.
    Generate a high-quality, engaging paragraph for a website target section based on these keywords.
    Target Section Context: $context
    Keywords/Input: $keywords
    
    Rules:
    1. Return ONLY the generated paragraph text. 
    2. No intro, no outro, no quotes.
    3. Keep it professional, concise, and focused on the keywords.
    4. Maximum 3-4 sentences.";

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    $payload = json_encode([
        'model' => 'llama-3.3-70b-versatile', // Groq's current versatile high-performance model
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 250
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('AI Service Error (HTTP ' . $httpCode . '): ' . $response);
    }

    $data = json_decode($response, true);
    $generatedText = $data['choices'][0]['message']['content'] ?? '';

    echo json_encode([
        'success' => true,
        'content' => trim($generatedText)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
