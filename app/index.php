<?php
// Load app config so BASE_URL (and other constants) are defined
require_once __DIR__ . '/../config/app.php';

// Redirect to public index with 302 Temporary Redirect
// This helps avoid browser caching the redirect incorrectly during development
http_response_code(302);
header("Location: " . BASE_URL . '/public/index.html');
exit;
