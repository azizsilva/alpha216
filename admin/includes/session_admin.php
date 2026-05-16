<?php

function admin_session_save_path() {
    return __DIR__ . '/../session/php';
}

function admin_session_parse_file($contents) {
    $out = [];
    if (!is_string($contents) || $contents === '') return $out;

    $patterns = [
        'user_id' => '/user_id\|i:(\d+);/',
        'username' => '/username\|s:\d+:"([^"]*)";/s',
        'role' => '/role\|s:\d+:"([^"]*)";/s',
        'coins' => '/coins\|d:([0-9\.\-]+);/',
        'login_time' => '/login_time\|i:(\d+);/',
        'login_ip' => '/login_ip\|s:\d+:"([^"]*)";/s',
        'login_device_type' => '/login_device_type\|s:\d+:"([^"]*)";/s',
        'login_browser' => '/login_browser\|s:\d+:"([^"]*)";/s',
        'login_os' => '/login_os\|s:\d+:"([^"]*)";/s',
        'login_user_agent' => '/login_user_agent\|s:\d+:"([^"]*)";/s'
    ];

    foreach ($patterns as $k => $re) {
        if (preg_match($re, $contents, $m)) {
            $out[$k] = $m[1];
        }
    }
    return $out;
}

function admin_session_list_by_user($user_id) {
    $dir = admin_session_save_path();
    if (!is_dir($dir)) return [];

    $files = glob($dir . '/sess_*');
    if (!is_array($files) || empty($files)) return [];

    $sessions = [];
    foreach ($files as $file) {
        $base = basename($file);
        if (strpos($base, 'sess_') !== 0) continue;

        $raw = @file_get_contents($file);
        if ($raw === false) continue;
        $meta = admin_session_parse_file($raw);
        if (!isset($meta['user_id']) || (int)$meta['user_id'] !== (int)$user_id) continue;

        $sid = substr($base, 5);
        $sessions[] = [
            'file' => $base,
            'sid' => $sid,
            'mtime' => @filemtime($file) ?: 0,
            'meta' => $meta
        ];
    }

    usort($sessions, function($a, $b) {
        return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
    });

    return $sessions;
}

function admin_session_delete_by_filename($file_basename) {
    $file_basename = basename((string)$file_basename);
    if (strpos($file_basename, 'sess_') !== 0) return false;
    $path = admin_session_save_path() . '/' . $file_basename;
    if (!is_file($path)) return false;
    return @unlink($path);
}

