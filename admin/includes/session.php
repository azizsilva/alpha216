<?php

$lifetime = 60 * 60 * 24 * 365 * 10;
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$cookieParams = [
    'lifetime' => $lifetime,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
];

ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string)$lifetime);
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '1000');

$savePath = __DIR__ . '/../session/php';
if (!is_dir($savePath)) {
    @mkdir($savePath, 0775, true);
}
if (is_dir($savePath) && is_writable($savePath)) {
    @session_save_path($savePath);
}

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieParams);
} else {
    session_set_cookie_params($lifetime, $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (PHP_VERSION_ID >= 70300) {
    setcookie(session_name(), session_id(), [
        'expires' => time() + $lifetime,
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => $cookieParams['secure'],
        'httponly' => $cookieParams['httponly'],
        'samesite' => $cookieParams['samesite']
    ]);
} else {
    setcookie(session_name(), session_id(), time() + $lifetime, $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
}

