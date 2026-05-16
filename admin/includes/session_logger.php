<?php

function detect_device_type($ua) {
    $ua = strtolower($ua ?? '');
    if (strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) return 'tablet';
    if (strpos($ua, 'mobi') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) return 'mobile';
    return 'desktop';
}

function detect_browser($ua) {
    $ua = $ua ?? '';
    if (stripos($ua, 'Edg/') !== false) return 'Edge';
    if (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false) return 'Chrome';
    if (stripos($ua, 'Firefox/') !== false) return 'Firefox';
    if (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome/') === false) return 'Safari';
    return 'Other';
}

function detect_os($ua) {
    $ua = $ua ?? '';
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Android') !== false) return 'Android';
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iOS';
    if (stripos($ua, 'Mac OS X') !== false) return 'macOS';
    if (stripos($ua, 'Linux') !== false) return 'Linux';
    return 'Other';
}

function admin_session_log_login($user_id, $username, $role) {
    $logsDir = __DIR__ . '/../session/logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0775, true);
    }

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $data = [
        'event' => 'login',
        'time' => gmdate('c'),
        'user_id' => (int)$user_id,
        'username' => (string)$username,
        'role' => (string)$role,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        'user_agent' => $ua,
        'device_type' => detect_device_type($ua),
        'browser' => detect_browser($ua),
        'os' => detect_os($ua),
        'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        'host' => $_SERVER['HTTP_HOST'] ?? '',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'session_id_hash' => hash('sha256', session_id())
    ];

    $rand = bin2hex(random_bytes(6));
    $ts = gmdate('Ymd_His');
    $file = $logsDir . '/u' . (int)$user_id . '_' . $ts . '_' . $rand . '.json';
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

