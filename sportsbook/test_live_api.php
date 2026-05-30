<?php
// Live API debug — dumps raw market names from odds-api.io
// Usage: /sportsbook/test_live_api.php?pass=debug216[&id=EVENT_ID]
if (($_GET['pass']??'') !== 'debug216') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$key  = '06eff561d8a52e749f38d64f95f4c22bc7504bc16c7122d849eabc9f97908d91';
$base = 'https://api.odds-api.io/v3';
$books_all = 'Bet365,1xbet,22Bet,888Sport,Betano,Unibet,10BET,12bet,18bet';

function api_get($url) {
    $ctx = stream_context_create(['http'=>['timeout'=>12,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\nAuthorization: Bearer " . $GLOBALS['key'] . "\r\n"]]);
    $r = @file_get_contents($url, false, $ctx);
    $hdr = $http_response_header ?? [];
    $code = 0;
    foreach ($hdr as $h) { if (preg_match('#HTTP/\S+ (\d+)#', $h, $m)) $code = (int)$m[1]; }
    echo "  [HTTP $code] $url\n";
    return $r ? json_decode($r, true) : null;
}

function dump_bookmakers($bk, $indent='  ') {
    if (!is_array($bk) || !$bk) { echo $indent . "(no bookmakers)\n"; return; }
    foreach ($bk as $book => $mkts) {
        $list = is_array($mkts) ? $mkts : ($mkts['odds']??$mkts['markets']??[]);
        if (!is_array($list)) { echo $indent . "[$book]: (non-array: " . gettype($mkts) . ")\n"; continue; }
        $names = array_map(fn($m) => ($m['name']??'?') . '(' . count($m['odds']??[]) . ')', $list);
        echo $indent . "[$book] " . count($names) . " markets: " . implode(' | ', $names) . "\n";
    }
}

// If specific event ID given, deep-dive it
if (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    echo "=== DEEP DIVE event $id ===\n\n";

    echo "--- /odds (all bookmakers) ---\n";
    $r = api_get("$base/odds?eventId=$id&bookmakers=" . urlencode($books_all) . "&apiKey=$key");
    dump_bookmakers($r['bookmakers'] ?? $r['data']['bookmakers'] ?? []);

    echo "\n--- /odds/multi (single event) ---\n";
    $r2 = api_get("$base/odds/multi?eventIds=$id&bookmakers=" . urlencode($books_all) . "&apiKey=$key");
    $evs = is_array($r2) ? $r2 : ($r2['data']??[]);
    foreach ((array)$evs as $ev) {
        if (($ev['id']??'') == $id) { dump_bookmakers($ev['bookmakers']??[]); }
    }

    echo "\n--- Raw response (first 3000 chars) ---\n";
    $ctx = stream_context_create(['http'=>['timeout'=>12,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\n"]]);
    $raw = @file_get_contents("$base/odds?eventId=$id&bookmakers=" . urlencode($books_all) . "&apiKey=$key", false, $ctx);
    echo substr($raw, 0, 3000) . "\n";
    exit;
}

// 1. Get ALL live football events, show top 10 with market counts
echo "=== LIVE FOOTBALL EVENTS ===\n";
$events = api_get("$base/events/live?sport=football&apiKey=$key");
$arr = is_array($events) ? $events : ($events['data'] ?? $events['events'] ?? []);
echo "Total: " . count($arr) . " events\n\n";
if (!$arr) { echo "No live events.\n"; exit; }

// Sort: prefer bigger leagues (World Cup, etc.) — show top 8
usort($arr, fn($a,$b) => strcmp($b['league_name']??$b['competition']??'', $a['league_name']??$a['competition']??''));
$sample = array_slice($arr, 0, 8);
foreach ($sample as $ev) {
    $id   = $ev['id'] ?? '?';
    $home = is_array($ev['home_team']??null) ? ($ev['home_team']['name']??'?') : ($ev['home_team']??($ev['home']??'?'));
    $away = is_array($ev['away_team']??null) ? ($ev['away_team']['name']??'?') : ($ev['away_team']??($ev['away']??'?'));
    $lg   = $ev['league_name'] ?? $ev['competition'] ?? '?';
    $min  = $ev['minute'] ?? $ev['elapsed'] ?? '?';
    echo "Event $id [$lg] {$min}': $home vs $away\n";
    echo "  → Deep dive: ?pass=debug216&id=$id\n";

    // Quick single-book check
    $r = api_get("$base/odds?eventId=$id&bookmakers=Bet365,1xbet,22Bet&apiKey=$key");
    $bk = $r['bookmakers'] ?? $r['data']['bookmakers'] ?? [];
    dump_bookmakers($bk, '    ');
    echo "\n";
}

// 2. /odds/multi batch
echo "=== /odds/multi BATCH (first 5 events) ===\n";
$ids = implode(',', array_column(array_slice($arr,0,5), 'id'));
$multi = api_get("$base/odds/multi?eventIds=" . urlencode($ids) . "&bookmakers=" . urlencode($books_all) . "&apiKey=$key");
$mevs = is_array($multi) ? $multi : ($multi['data']??[]);
if (empty($mevs)) {
    echo "  Raw multi response: " . substr(json_encode($multi),0,500) . "\n";
} else {
    foreach ((array)$mevs as $mev) {
        $bk = $mev['bookmakers']??[];
        $totalMkts = 0; foreach ($bk as $b) { $l=is_array($b)?$b:($b['odds']??[]); $totalMkts+=count($l); }
        echo "  Event {$mev['id']}: " . count($bk) . " bookmakers, $totalMkts total markets\n";
        dump_bookmakers($bk, '    ');
    }
}

// 3. Check account info
echo "\n=== ACCOUNT / PLAN INFO ===\n";
$info = api_get("$base/account?apiKey=$key");
echo "  " . json_encode($info) . "\n";
