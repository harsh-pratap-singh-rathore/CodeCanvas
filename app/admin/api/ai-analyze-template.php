<?php
/**
 * AI TEMPLATE ANALYZER
 * Backend for Groq API to analyze HTML template placeholders.
 */

session_start();
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once APP_ROOT . '/app/admin/config/database.php';
require_once APP_ROOT . '/app/core/admin_auth.php'; // Ensure only admin can use this

// Load API Keys
$config = require APP_ROOT . '/app/admin/config/api_keys.php';
$apiKey = $config['groq']['api_key'] ?? '';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (empty($apiKey) || $apiKey === 'YOUR_GROQ_API_KEY') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'AI API key not configured.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $htmlSnippet = $input['html_snippet'] ?? '';
    $placeholders = $input['placeholders'] ?? [];

    if (empty($placeholders)) {
        echo json_encode(['success' => true, 'analysis' => 'No placeholders detected to analyze.']);
        exit;
    }

    $placeholdersStr = implode(', ', $placeholders);
    
    $prompt = "You are an expert web developer for CodeCanvas. 
    I have an HTML template with the following placeholders: [$placeholdersStr].
    
    Tasks:
    1. Briefly explain what this template seems to be for (e.g., 'A modern developer portfolio with a focus on dark mode').
    2. For each placeholder, provide a 1-sentence description of what the user should input there.
    3. Categorize these placeholders into 'Text', 'Images', or 'Settings'.

    Format the response as a clean HTML snippet (using <div>, <h4>, <ul>, <li>) that can be directly inserted into a dashboard. 
    Do NOT include any markdown code blocks, intro, or outro. Just the HTML.";

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    $payload = json_encode([
        'model' => 'llama-3.3-70b-versatile',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a professional technical analyst.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.5,
        'max_tokens' => 1000
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
        throw new Exception('AI Service Error (HTTP ' . $httpCode . ')');
    }

    $data = json_decode($response, true);
    $analysisHtml = $data['choices'][0]['message']['content'] ?? '';

    echo json_encode([
        'success' => true,
        'analysis' => trim($analysisHtml)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
