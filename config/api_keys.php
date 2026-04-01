<?php
// API Configuration
// This file stores API keys and external service configurations.

// Load .env variables manually to ensure they are available
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

return [
    'google_ai' => [
        'api_key' => $_ENV['GOOGLE_AI_KEY'] ?? getenv('GOOGLE_AI_KEY') ?? '',
        'project_id' => '', // Add if needed
        'region' => 'us-central1'
    ],
    'groq' => [
        'api_key' => $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?? ''
    ],
    /*
     |--------------------------------------------------------------------------
     | Vercel Deployment Settings
     |--------------------------------------------------------------------------
     | Used by VercelDeployService to deploy static project websites directly
     | via Vercel's API v13.
     */
    'vercel' => [
        'token'   => $_ENV['VERCEL_TOKEN']   ?? '',
        'team_id' => $_ENV['VERCEL_TEAM_ID'] ?? '', // Optional.
    ],
    // Add other APIs here
];
