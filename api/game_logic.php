<?php
// Centralized Game Launch Logic
// This function handles the API communication with Gamblly
function launchGambllyGame($user_id, $game_id, $home_url, $pdo, $skip_log = false) {
    // API Credentials
    $api_url = 'https://game.gamblly-api.com/production/v1/gameLaunch.php';
    $api_key = 'bc7bea14630CodeHub94a20b7b915a33';

    $member_account = is_numeric($user_id) ? (string)(int)$user_id : trim((string)$user_id);
    $coins = 0;
    if ($pdo && is_numeric($user_id)) {
        try {
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user_id]);
            $b = $stmt->fetchColumn();
            if ($b !== false) {
                $coins = (float)$b;
                if (isset($_SESSION)) $_SESSION['coins'] = $coins;
            }
        } catch (Exception $e) {
        }
    }
    if (isset($_SESSION['coins'])) $coins = (float)$_SESSION['coins'];

    // DEMO Account Check: If user is demo, force balance to 0
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'demo@gmail.com') {
        $coins = 0;
    }

    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    $ch_mobile = isset($_SERVER['HTTP_SEC_CH_UA_MOBILE']) ? (string)$_SERVER['HTTP_SEC_CH_UA_MOBILE'] : '';
    $is_mobile = false;
    if ($ch_mobile === '?1') {
        $is_mobile = true;
    } elseif ($ua !== '') {
        $is_mobile = (bool)preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\\.browser|up\\.link|webos|wos)/i", $ua);
    }
    $platform = $is_mobile ? 2 : 1;

    // Prepare Request Payload
    $payload = [
        'member_account' => trim($member_account),
        'game_uid' => $game_id,
        'credit_amount' => (string)$coins,
        'currency_code' => 'TND',
        'language' => 'en',
        'platform' => $platform,
        'api_key' => $api_key,
        'home_url' => trim($home_url, " `\t\n\r\0\x0B")
    ];

    // DEBUG LOG: Log Payload
    file_put_contents(__DIR__ . '/game_launch_debug.log', "[" . date('Y-m-d H:i:s') . "] REQUEST: " . json_encode($payload) . "\n", FILE_APPEND);

    // Initialize cURL
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload); // Multipart/Form-Data
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Expect:'
    ]);
    $default_ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    curl_setopt($ch, CURLOPT_USERAGENT, $ua !== '' ? $ua : $default_ua);
    // Disable SSL Verification for Development/Testing
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // DEBUG LOG: Log Response
    file_put_contents(__DIR__ . '/game_launch_debug.log', "[" . date('Y-m-d H:i:s') . "] RESPONSE: " . $response . " | CURL ERROR: " . $curl_error . "\n", FILE_APPEND);

    if ($curl_error) {
        return ['success' => false, 'message' => 'API Connection Error: ' . $curl_error];
    }

    $result = json_decode($response, true);

    if (isset($result['game_url']) && !empty($result['game_url'])) {
        // Log to Recent Games (Only if skip_log is FALSE)
        if (!$skip_log && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO recent_games (user_id, game_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE played_at = CURRENT_TIMESTAMP");
                $stmt->execute([$user_id, $game_id]);
            } catch (Exception $e) {
                // Ignore logging errors
            }
        }
        return ['success' => true, 'game_url' => $result['game_url']];
    } else {
        $msg = $result['msg'] ?? 'Failed to launch game. Provider returned no URL.';
        return ['success' => false, 'message' => $msg, 'debug' => $result];
    }
}
?>
