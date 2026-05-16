<?php
require_once __DIR__ . '/../includes/odds-api.php';

header('Content-Type: application/json; charset=utf-8');

function out($payload, $code = 200) {
    if (!headers_sent()) http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$endpoint = trim((string)($_GET['endpoint'] ?? ''));

$allowed = [
    'sports' => ['path' => '/sports', 'auth' => false, 'params' => []],
    'bookmakers' => ['path' => '/bookmakers', 'auth' => false, 'params' => []],
    'leagues' => ['path' => '/leagues', 'auth' => true, 'params' => ['sport']],
    'events' => ['path' => '/events', 'auth' => true, 'params' => ['sport', 'league', 'status', 'from', 'to', 'bookmaker', 'participantId']],
    'events_live' => ['path' => '/events/live', 'auth' => true, 'params' => ['sport']],
    'odds' => ['path' => '/odds', 'auth' => true, 'params' => ['eventId', 'bookmakers']],
    'odds_multi' => ['path' => '/odds/multi', 'auth' => true, 'params' => ['eventIds', 'bookmakers']]
];

if (!isset($allowed[$endpoint])) {
    out(['success' => false, 'message' => 'Invalid endpoint'], 400);
}

$def = $allowed[$endpoint];
$params = [];
foreach ($def['params'] as $p) {
    if (isset($_GET[$p]) && (string)$_GET[$p] !== '') {
        $params[$p] = (string)$_GET[$p];
    }
}

if (!empty($def['auth'])) {
    $params['apiKey'] = odds_api_key();
}

$data = odds_api_get($def['path'], $params, 20);
if (is_array($data) && isset($data['__error'])) {
    out(['success' => false, 'message' => $data['message'] ?? 'Request failed', 'status' => $data['status'] ?? 0], 502);
}

out(['success' => true, 'data' => $data]);

