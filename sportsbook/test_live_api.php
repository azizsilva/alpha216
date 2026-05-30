<?php
// Quick live test: dump raw market names from odds-api.io for first live football event
// Visit: /sportsbook/test_live_api.php?pass=debug216
if (($_GET['pass']??'') !== 'debug216') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$key = '06eff561d8a52e749f38d64f95f4c22bc7504bc16c7122d849eabc9f97908d91';
$base = 'https://api.odds-api.io/v3';

function api_get($url) {
    $ctx = stream_context_create(['http'=>['timeout'=>10,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\n"]]);
    $r = file_get_contents($url, false, $ctx);
    return $r ? json_decode($r, true) : null;
}

// 1. Get live football events
echo "=== LIVE FOOTBALL EVENTS ===\n";
$events = api_get("$base/events/live?sport=football&apiKey=$key");
$arr = is_array($events) ? $events : ($events['data'] ?? $events['events'] ?? []);
echo "Count: " . count($arr) . "\n\n";
if (!$arr) { echo "No live events found.\n"; exit; }

// Take first 3 events
$sample = array_slice($arr, 0, 3);
foreach ($sample as $ev) {
    $id = $ev['id'] ?? '?';
    $home = is_array($ev['home_team']??null) ? ($ev['home_team']['name']??'?') : ($ev['home_team']??($ev['home']??'?'));
    $away = is_array($ev['away_team']??null) ? ($ev['away_team']['name']??'?') : ($ev['away_team']??($ev['away']??'?'));
    echo "Event $id: $home vs $away\n";

    // 2. Get odds for this event with ALL bookmakers
    $books = 'Bet365,1xbet,22Bet,888Sport,Betano,Unibet,10BET,12bet,18bet';
    $odds = api_get("$base/odds?eventId=$id&bookmakers=" . urlencode($books) . "&apiKey=$key");
    $bk = $odds['bookmakers'] ?? $odds['data']['bookmakers'] ?? [];
    echo "  Bookmakers in response: " . implode(', ', array_keys($bk)) . "\n";
    foreach ($bk as $book => $mkts) {
        $list = is_array($mkts) ? $mkts : ($mkts['odds']??$mkts['markets']??[]);
        if (!is_array($list)) continue;
        $names = array_map(fn($m) => $m['name']??'?', $list);
        echo "  [$book] markets (" . count($names) . "): " . implode(' | ', $names) . "\n";
    }
    echo "\n";
}

// 3. Check /odds/multi with a batch
echo "=== /odds/multi batch test ===\n";
$ids = implode(',', array_column(array_slice($arr,0,5), 'id'));
$books = 'Bet365,1xbet,22Bet,888Sport';
$multi = api_get("$base/odds/multi?eventIds=" . urlencode($ids) . "&bookmakers=" . urlencode($books) . "&apiKey=$key");
$evs = is_array($multi) ? $multi : ($multi['data']??[]);
foreach ((array)$evs as $mev) {
    $id = $mev['id']??'?';
    $bk = $mev['bookmakers']??[];
    echo "Event $id:\n";
    foreach ($bk as $book => $mkts) {
        $list = is_array($mkts) ? $mkts : ($mkts['odds']??$mkts['markets']??[]);
        $names = is_array($list) ? array_map(fn($m) => $m['name']??'?', $list) : [];
        echo "  [$book]: " . implode(' | ', $names) . "\n";
    }
}
