<?php
$TOKEN  = '3ef5c8c7684cd494e47347e4b6c53df7';
$SECRET = 'c856e884370d3be58ae4a15f5fed6d54';
$SERVER_URL = 'https://igamingapis.live/api/v1';

function ENCRYPT_PAYLOAD_ECB(array $DATA, string $KEY): string {
    $JSON = json_encode($DATA);
    $ENC  = openssl_encrypt($JSON, 'AES-256-ECB', $KEY, OPENSSL_RAW_DATA);
    return base64_encode($ENC);
}

// Let's try to query 'games_list' or 'matches' or similar commands
$commands = ['game_list', 'games', 'sports', 'matches', 'live_matches', 'get_games'];

foreach ($commands as $cmd) {
    echo "Testing command: $cmd\n";
    $PAYLOAD = [
        'user_id' => '23213',
        'token' => $TOKEN,
        'timestamp' => round(microtime(true) * 1000),
        'command' => $cmd,
        'action' => $cmd,
        'type' => $cmd
    ];
    $ENCRYPTED = ENCRYPT_PAYLOAD_ECB($PAYLOAD, $SECRET);
    $URL = $SERVER_URL . '?payload=' . urlencode($ENCRYPTED) . '&token=' . urlencode($TOKEN);
    $response = @file_get_contents($URL);
    echo "Response: " . substr($response, 0, 500) . "\n\n";
}
?>
