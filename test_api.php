<?php
$token = '3ef5c8c7684cd494e47347e4b6c53df7';
$secret = 'c856e884370d3be58ae4a15f5fed6d54';
$payload = ['token' => $token, 'timestamp' => round(microtime(true) * 1000)];
$json = json_encode($payload);
$hash = base64_encode(openssl_encrypt($json, 'AES-256-ECB', $secret, OPENSSL_RAW_DATA));
$ch = curl_init('https://igamingapis.live/api/v1/matches');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['hash' => $hash]);
$res = curl_exec($ch);
echo $res;
