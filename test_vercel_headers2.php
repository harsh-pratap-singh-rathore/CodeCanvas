<?php
ob_start();
function getHeaders($domain) {
    $url = "https://$domain";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    echo "--- $domain --- ($info[http_code])\n";
    $lines = explode("\n", $resp);
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l && (strpos($l, 'x-') === 0 || strpos($l, 'HTTP') === 0 || strpos($l, 'server:') === 0)) {
            echo "  $l\n";
        }
    }
}

getHeaders('rathore5.vercel.app');
getHeaders('xkdjeksdj1111111.vercel.app');
getHeaders('google.vercel.app');

file_put_contents('headers_utf8.txt', ob_get_clean());
echo "Done";
