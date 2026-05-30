<?php
// Live API debug — full diagnosis
if (($_GET['pass']??'') !== 'debug216') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$key  = '06eff561d8a52e749f38d64f95f4c22bc7504bc16c7122d849eabc9f97908d91';
$base = 'https://api.odds-api.io/v3';
$books_all = 'Bet365,1xbet,22Bet,888Sport,Betano,Unibet,10BET,12bet,18bet,William Hill,Pinnacle,bwin,Betfair';

function api($path, $params=[]) {
    global $key, $base;
    $params['apiKey'] = $key;
    $url = $base . $path . '?' . http_build_query($params);
    $ctx = stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\n"]]);
    $r = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach (($http_response_header??[]) as $h) { if (preg_match('#HTTP/\S+ (\d+)#', $h, $m)) $code=(int)$m[1]; }
    echo "[HTTP $code] $url\n";
    return $r ? json_decode($r, true) : null;
}

function show_bk($bk, $pad='  ') {
    if (!is_array($bk)||!$bk){echo $pad."(none)\n";return;}
    foreach ($bk as $book=>$v) {
        $list = is_array($v)?$v:($v['odds']??$v['markets']??[]);
        if (!is_array($list)){echo $pad."[$book]: (bad shape)\n";continue;}
        $ns = array_map(fn($m)=>($m['name']??'?').'('.count($m['odds']??[]).')', $list);
        echo $pad."[$book] ".count($ns)." mkts: ".implode(' | ',$ns)."\n";
    }
}

// ── Deep dive a specific event ────────────────────────────────────────────────
if (!empty($_GET['id'])) {
    $id = $_GET['id'];
    echo "=== DEEP DIVE event $id ===\n\n";

    echo "-- /odds (all books) --\n";
    $r = api('/odds', ['eventId'=>$id, 'bookmakers'=>$books_all]);
    show_bk($r['bookmakers']??$r['data']['bookmakers']??[]);

    echo "\n-- Raw /odds (first 5000 chars) --\n";
    $ctx = stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true,'header'=>"Accept: application/json\r\n"]]);
    $raw = @file_get_contents("$base/odds?eventId=$id&bookmakers=".urlencode($books_all)."&apiKey=$key",false,$ctx);
    echo substr($raw??'(empty)',0,5000)."\n";
    exit;
}

// ── Check available bookmakers on this account ────────────────────────────────
echo "=== BOOKMAKERS AVAILABLE ON THIS ACCOUNT ===\n";
$bkList = api('/bookmakers');
echo json_encode($bkList)."\n\n";

// ── Live events ───────────────────────────────────────────────────────────────
echo "=== LIVE FOOTBALL EVENTS ===\n";
$events = api('/events/live', ['sport'=>'football']);
$arr = is_array($events)?$events:($events['data']??$events['events']??[]);
echo "Total: ".count($arr)." events\n\n";

// Show top 5 with full market dump
$sample = array_slice($arr, 0, 5);
foreach ($sample as $ev) {
    $id   = $ev['id']??'?';
    $home = is_string($ev['home_team']??null)?$ev['home_team']:(is_array($ev['home_team']??null)?($ev['home_team']['name']??'?'):($ev['home']??'?'));
    $away = is_string($ev['away_team']??null)?$ev['away_team']:(is_array($ev['away_team']??null)?($ev['away_team']['name']??'?'):($ev['away']??'?'));
    $lg   = $ev['league_name']??$ev['competition']??$ev['league']??'?';
    echo "Event $id [$lg]: $home vs $away\n";
    echo "  Deep dive: ?pass=debug216&id=$id\n";
    $r = api('/odds', ['eventId'=>$id, 'bookmakers'=>$books_all]);
    show_bk($r['bookmakers']??$r['data']['bookmakers']??[]);
    echo "\n";
}

// ── Prematch/upcoming events ──────────────────────────────────────────────────
echo "=== UPCOMING FOOTBALL EVENTS (prematch odds) ===\n";
$up = api('/events', ['sport'=>'football', 'status'=>'upcoming']);
$uarr = is_array($up)?$up:($up['data']??$up['events']??[]);
echo "Total upcoming: ".count($uarr)."\n";
$usample = array_slice($uarr, 0, 3);
foreach ($usample as $ev) {
    $id   = $ev['id']??'?';
    $home = is_string($ev['home_team']??null)?$ev['home_team']:(is_array($ev['home_team']??null)?($ev['home_team']['name']??'?'):($ev['home']??'?'));
    $away = is_string($ev['away_team']??null)?$ev['away_team']:(is_array($ev['away_team']??null)?($ev['away_team']['name']??'?'):($ev['away']??'?'));
    echo "Upcoming $id: $home vs $away\n";
    echo "  Deep dive: ?pass=debug216&id=$id\n";
    $r = api('/odds', ['eventId'=>$id, 'bookmakers'=>$books_all]);
    show_bk($r['bookmakers']??$r['data']['bookmakers']??[]);
    echo "\n";
}

// ── Test WebSocket markets list ───────────────────────────────────────────────
echo "=== WEBSOCKET SUBSCRIPTION TEST ===\n";
$ws = api('/ws/info');
echo json_encode($ws)."\n\n";

// ── Check what markets the API supports ──────────────────────────────────────
echo "=== AVAILABLE MARKETS (from API) ===\n";
$mkts = api('/markets');
echo json_encode($mkts)."\n";
