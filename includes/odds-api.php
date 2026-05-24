<?php

function odds_api_key() {
    $k = getenv('ODDS_API_KEY');
    if (is_string($k) && trim($k) !== '') return trim($k);
    $k = getenv('ODDS_API_IO_KEY');
    if (is_string($k) && trim($k) !== '') return trim($k);
    return '5bae09f6733542f217de37a1d00dbbfca862582011d923e47064e0c5a667e19d';
}

function odds_api_base_url() {
    return 'https://api.odds-api.io/v3';
}

function odds_api_cache_dir() {
    return __DIR__ . '/../cache';
}

function odds_api_cache_get($key, $ttl_seconds) {
    global $redis;
    if (isset($redis)) {
        $val = $redis->get('odds_' . $key);
        if ($val) {
            $data = json_decode($val, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }
    
    $dir = odds_api_cache_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $path = $dir . '/odds_' . $key . '.json';
    if (!is_file($path)) return null;
    $age = time() - (int)@filemtime($path);
    if ($age < 0 || $age > (int)$ttl_seconds) return null;
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function odds_api_cache_set($key, $data, $ttl_seconds = 20) {
    global $redis;
    if (isset($redis)) {
        $redis->set('odds_' . $key, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $redis->expire('odds_' . $key, $ttl_seconds);
        return;
    }

    $dir = odds_api_cache_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $path = $dir . '/odds_' . $key . '.json';
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function odds_api_http_get_json($url) {
    $body = null;
    $status = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "Accept: application/json\r\n"
            ]
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $h, $m)) {
                    $status = (int)$m[1];
                    break;
                }
            }
        }
    }

    $json = is_string($body) ? json_decode($body, true) : null;
    return [$status, is_array($json) ? $json : null, is_string($body) ? $body : ''];
}

function odds_api_get($path, $params = [], $ttl_seconds = 20) {
    $path = '/' . ltrim((string)$path, '/');
    $params = is_array($params) ? $params : [];

    $cache_key = hash('sha256', $path . '?' . http_build_query($params));
    $cached = odds_api_cache_get($cache_key, $ttl_seconds);
    if ($cached !== null) return $cached;

    $url = odds_api_base_url() . $path;
    if (!empty($params)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }

    if (isset($params['apiKey']) && trim((string)$params['apiKey']) === '') {
        return [
            '__error' => true,
            'status' => 0,
            'message' => 'Missing ODDS API key'
        ];
    }

    [$status, $json, $raw] = odds_api_http_get_json($url);
    if ($status >= 200 && $status < 300 && is_array($json)) {
        odds_api_cache_set($cache_key, $json, $ttl_seconds);
        return $json;
    }

    return [
        '__error' => true,
        'status' => $status,
        'message' => is_array($json) && isset($json['message']) ? (string)$json['message'] : 'Request failed'
    ];
}
