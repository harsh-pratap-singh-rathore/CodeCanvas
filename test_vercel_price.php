<?php
require_once __DIR__ . '/config/bootstrap.php';
$apiKeys = include __DIR__ . '/config/api_keys.php';
$token = $apiKeys['vercel']['token'];

function checkVercelApp($slug, $token) {
    $domain = $slug . ".vercel.app";
    $url = "https://api.vercel.com/v4/domains/price?name=" . urlencode($domain);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "$slug ($code) -> $resp\n";
}

checkVercelApp('rathore5', $token);
checkVercelApp('google', $token);
checkVercelApp('test-deploy-slug-1', $token);
checkVercelApp('ksdfjhsdkjfh111', $token);
