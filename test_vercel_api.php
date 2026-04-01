<?php
require_once __DIR__ . '/config/bootstrap.php';
$apiKeys = include __DIR__ . '/config/api_keys.php';
$token = $apiKeys['vercel']['token'] ?? '';
$teamId = $apiKeys['vercel']['team_id'] ?? '';

$slug = 'rathore5';
$domain = "{$slug}.vercel.app";

$url = "https://api.vercel.com/v4/domains/status?name={$domain}";
if ($teamId) $url .= "&teamId={$teamId}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}"
]);
// CA cert for windows local
$caBundle = 'C:/xampp/php/extras/ssl/cacert.pem';
if (file_exists($caBundle)) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
} else {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
}

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: $code\n";
echo "Response: $resp\n";

