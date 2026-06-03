<?php
/**
 * CMS Wager — server-side session creator
 * Called by launch_game.php when provider='cmswager'
 * Returns: { success: true, game_url: "https://[sb_domain]/?token=SESSION&lang=fr" }
 */

define('CMS_WAGER_API_BASE',    'https://api.cmswager.com');
define('CMS_WAGER_CLIENT_USER', 'doublembet');
define('CMS_WAGER_CLIENT_PASS', 'STNb58@ps!');
define('CMS_WAGER_SB_URL',      '');   // ← FILL IN when CMS Wager provides the iframe domain
define('CMS_WAGER_CURRENCY',    'TND');
define('CMS_WAGER_LANGUAGE',    'fr');

/**
 * Get a fresh operator JWT (valid 1 hour — cache in session).
 */
function cmswager_get_operator_token() {
    // Cache in PHP session for up to 50 minutes
    if (!empty($_SESSION['_cmsw_jwt']) && !empty($_SESSION['_cmsw_jwt_exp']) && time() < $_SESSION['_cmsw_jwt_exp']) {
        return $_SESSION['_cmsw_jwt'];
    }

    $resp = cmswager_post(CMS_WAGER_API_BASE . '/api/v1/get_token', [
        'ClientUsername' => CMS_WAGER_CLIENT_USER,
        'ClientPassword' => CMS_WAGER_CLIENT_PASS,
    ]);

    if (empty($resp['data']['token'])) {
        error_log('[CMS Wager] get_token failed: ' . json_encode($resp));
        return null;
    }

    $jwt = $resp['data']['token'];
    $_SESSION['_cmsw_jwt']     = $jwt;
    $_SESSION['_cmsw_jwt_exp'] = time() + 3000; // 50 min
    return $jwt;
}

/**
 * Create a player session token and return the sportsbook iframe URL.
 * $userId   — your internal user ID (used as CMS Wager username)
 * $pdo      — PDO for storing the session token
 * $homUrl   — back-button URL
 */
function launchCmsWagerGame($userId, $pdo, $homeUrl = '') {
    $jwt = cmswager_get_operator_token();
    if (!$jwt) return ['success' => false, 'message' => 'CMS Wager auth failed'];

    // Username sent to CMS Wager = your user ID (string)
    $cwUsername = 'user_' . $userId;
    $cwPassword = 'P_' . md5($userId . CMS_WAGER_CLIENT_PASS); // deterministic per-user password

    $resp = cmswager_post(
        CMS_WAGER_API_BASE . '/v1/user/login',
        [
            'Username' => $cwUsername,
            'Password' => $cwPassword,
            'Type'     => 'real',
            'Currency' => CMS_WAGER_CURRENCY,
            'Language' => CMS_WAGER_LANGUAGE,
        ],
        $jwt
    );

    if (empty($resp['data']['token'])) {
        error_log('[CMS Wager] user/login failed for userId=' . $userId . ': ' . json_encode($resp));
        return ['success' => false, 'message' => 'CMS Wager session failed'];
    }

    $sessionToken = $resp['data']['token'];

    // Store session token in DB for wallet callback validation
    try {
        // Upsert: delete old, insert new
        $pdo->prepare("DELETE FROM cmswager_sessions WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("
            INSERT INTO cmswager_sessions (user_id, token, cw_username, expires_at, created_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW())
        ")->execute([$userId, $sessionToken, $cwUsername]);
    } catch (Exception $e) {
        error_log('[CMS Wager] Session store error: ' . $e->getMessage());
    }

    // Build iframe URL
    $sbBase = CMS_WAGER_SB_URL;
    if (empty($sbBase)) {
        // Placeholder until CMS Wager provides the URL
        return [
            'success'    => false,
            'message'    => 'CMS Wager sportsbook URL not yet configured. Contact CMS Wager support.',
            '_token'     => $sessionToken,
            '_note'      => 'Token generated OK — waiting for iframe domain from CMS Wager',
        ];
    }

    $params = http_build_query([
        'token' => $sessionToken,
        'lang'  => CMS_WAGER_LANGUAGE,
        'cur'   => CMS_WAGER_CURRENCY,
    ]);
    $iframeUrl = rtrim($sbBase, '/') . '/?' . $params;

    return ['success' => true, 'game_url' => $iframeUrl, '_session_token' => $sessionToken];
}

/**
 * HTTP POST helper (JSON).
 */
function cmswager_post($url, array $body, $bearerToken = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => array_filter([
            'Content-Type: application/json',
            'Accept: application/json',
            $bearerToken ? 'Authorization: Bearer ' . $bearerToken : null,
        ]),
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp ? (json_decode($resp, true) ?: []) : [];
}
