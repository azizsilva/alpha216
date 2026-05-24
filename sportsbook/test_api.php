<?php
$TOKEN = '254610-7T3dEgVPsVZPNY';
$BASE = 'https://api.b365api.com';

function api_get($path, $params = []) {
    global $TOKEN, $BASE;
    $params['token'] = $TOKEN;
    $url = $BASE . $path . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    curl_close($ch);
    $j = json_decode($res, true);
    return $j['results'] ?? [];
}

function api_get_full($path, $params = []) {
    global $TOKEN, $BASE;
    $params['token'] = $TOKEN;
    $url = $BASE . $path . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$upcoming = api_get('/v1/bet365/upcoming', ['sport_id' => 1, 'page' => 1]);
file_put_contents('../scratch/bet365_upcoming_odds.json', json_encode(array_slice($upcoming, 0, 3), JSON_PRETTY_PRINT));

$inplay = api_get_full('/v1/bet365/inplay', []);
file_put_contents('../scratch/bet365_inplay_stream.json', json_encode(array_slice($inplay['results'][0] ?? [], 0, 50), JSON_PRETTY_PRINT));
echo "Done fetching test data.\n";
