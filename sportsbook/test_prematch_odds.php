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
    return json_decode($res, true);
}

// Get the latest upcoming matches from my test
$upcoming = file_get_contents('../scratch/bet365_upcoming_odds.json');
$upcoming = json_decode($upcoming, true);

if (!empty($upcoming)) {
    $first = $upcoming[0];
    echo "Testing prematch for: " . $first['id'] . "\n";
    $prematch = api_get('/v1/bet365/prematch', ['FI' => $first['id']]);
    file_put_contents('../scratch/bet365_prematch.json', json_encode($prematch, JSON_PRETTY_PRINT));
    echo "Saved prematch to scratch/bet365_prematch.json\n";
} else {
    echo "No upcoming matches found in scratch file\n";
}
