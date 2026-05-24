<?php
$apiUrl = 'https://igamingapis.com/api/game/launch'; // Guessing endpoint
$token = '3ef5c8c7684cd494e47347e4b6c53df7';
$secret = 'c856e884370d3be58ae4a15f5fed6d54';
$gameId = '6260';

$payload = [
    'token' => $token,
    'secret' => $secret,
    'player' => 'test_user_123',
    'game' => $gameId,
    'currency' => 'TND',
    'balance' => 100.00,
    'home_url' => 'https://365forzza.shop/sports/sportsbook.php'
];

$endpoints = [
    'https://api.igamingapis.com/v1/launch',
    'https://igamingapis.com/api/v1/game/launch',
    'https://igamingapis.com/api/launch',
    'https://api.softapi2.shop/v1/launch'
];

foreach ($endpoints as $url) {
    echo "Testing $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Code: $httpCode | Response: $response\n\n";
}
