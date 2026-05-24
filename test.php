<?php
$TOKEN  = '3ef5c8c7684cd494e47347e4b6c53df7';
$SECRET = 'c856e884370d3be58ae4a15f5fed6d54';
$SERVER_URL = 'https://igamingapis.live/api/v1/gameLaunch';

$PAYLOAD = [
    'user_id' => '1',
    'balance' => '1000',
    'game_uid' => '6260',
    'token' => $TOKEN,
    'timestamp' => round(microtime(true) * 1000),
    'return' => 'https://tanitbet716.com/',
    'callback' => 'https://tanitbet716.com/api/callback.php',
    'currency_code' => 'TND',
    'language' => 'fr'
];

$JSON = json_encode($PAYLOAD);
$ENC  = openssl_encrypt($JSON, 'AES-256-ECB', $SECRET, OPENSSL_RAW_DATA);
$ENCRYPTED = base64_encode($ENC);

$URL = $SERVER_URL . '?payload=' . urlencode($ENCRYPTED) . '&token=' . urlencode($TOKEN);

$ch = curl_init($URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$response = curl_exec($ch);
curl_close($ch);
echo $response;
