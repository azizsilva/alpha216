<?php
if (($_GET['pass']??'') !== 'debug216') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$key  = '06eff561d8a52e749f38d64f95f4c22bc7504bc16c7122d849eabc9f97908d91';
$base = 'https://api.odds-api.io/v3';

function api($path, $params=[]) {
    global $key, $base;
    $params['apiKey'] = $key;
    $url = $base . $path . '?' . http_build_query($params);
    $ctx = stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\n"]]);
    $r = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach (($http_response_header??[]) as $h) { if (preg_match('#HTTP/\S+ (\d+)#',$h,$m)) $code=(int)$m[1]; }
    return [$code, $r ? json_decode($r, true) : null, $r];
}

// ── Deep dive one event ───────────────────────────────────────────────────────
if (!empty($_GET['id'])) {
    $id = $_GET['id'];
    echo "=== DEEP DIVE event $id ===\n\n";
    // Try with just the most reliable bookmakers (avoid 400 from too many)
    $books = 'Bet365,1xbet,22Bet,888Sport,Betano,Unibet,10BET,12bet,18bet';
    [$code,$r,$raw] = api('/odds', ['eventId'=>$id,'bookmakers'=>$books]);
    echo "[HTTP $code]\n";
    $bk = $r['bookmakers'] ?? $r['data']['bookmakers'] ?? [];
    if (!$bk) { echo "No bookmakers. Raw (2000 chars):\n".substr($raw??'',0,2000)."\n"; exit; }
    foreach ($bk as $book=>$v) {
        $list = is_array($v)?$v:($v['odds']??$v['markets']??[]);
        $ns = is_array($list) ? array_map(fn($m)=>($m['name']??'?').'('.count($m['odds']??[]).')',$list) : ['(bad)'];
        echo "[$book] ".count($ns)." mkts: ".implode(' | ',$ns)."\n";
    }
    echo "\n--- Raw (3000 chars) ---\n".substr($raw??'',0,3000)."\n";
    exit;
}

// ── Redis dump ────────────────────────────────────────────────────────────────
if (!empty($_GET['redis'])) {
    // Show what's actually in Redis
    function rs_cmd($fp, $cmd) {
        $req = '*'.count($cmd)."\r\n";
        foreach ($cmd as $p) { $p=(string)$p; $req.='$'.strlen($p)."\r\n".$p."\r\n"; }
        fwrite($fp,$req);
        $line=fgets($fp,4096);
        $t=substr($line,0,1);
        if($t==='+') return trim(substr($line,1));
        if($t===':') return (int)trim(substr($line,1));
        if($t==='$') { $len=(int)trim(substr($line,1)); if($len<0) return null; $d=fread($fp,$len+2); return rtrim($d,"\r\n"); }
        if($t==='*') { $n=(int)trim(substr($line,1)); $a=[]; for($i=0;$i<$n;$i++) $a[]=rs_cmd($fp,[]); return $a; }
        return null;
    }
    $fp=@fsockopen('127.0.0.1',6379,$e,$es,1);
    if(!$fp){echo "Redis not reachable\n";exit;}
    $allIds=rs_cmd($fp,['SMEMBERS','sb:live:all']);
    $liveIds=rs_cmd($fp,['SMEMBERS','sb:live:sport:1']);
    echo "Redis sb:live:all: ".count((array)$allIds)." events\n";
    echo "Redis sb:live:sport:1 (football): ".count((array)$liveIds)." events\n\n";
    $shown=0;
    foreach ((array)$allIds as $id) {
        $raw=rs_cmd($fp,['GET',"sb:ev:$id"]);
        if(!$raw) continue;
        $ev=json_decode($raw,true);
        if(!$ev) continue;
        $home=$ev['home']['name']??'?'; $away=$ev['away']['name']??'?';
        $ts=$ev['time_status']??'?'; $src=$ev['_source']??'?';
        $mkts=count($ev['md_markets']??[]);
        $mktNames=implode(',',array_column($ev['md_markets']??[],'name'));
        echo "[$id] ts=$ts $home vs $away | $mkts mkts: $mktNames | src=$src\n";
        if(++$shown>=20) { echo "...(showing first 20)\n"; break; }
    }
    fclose($fp);
    exit;
}

// ── Test /odds/multi response shape ──────────────────────────────────────────
if (!empty($_GET['multi'])) {
    $ids = $_GET['multi'];
    $books = 'Bet365,1xbet,22Bet,888Sport,Betano,Unibet,10BET';
    [$code,$r,$raw] = api('/odds/multi', ['eventIds'=>$ids,'bookmakers'=>$books]);
    echo "[HTTP $code] /odds/multi?eventIds=$ids\n\n";
    echo "Raw (5000 chars):\n".substr($raw??'(empty)',0,5000)."\n";
    exit;
}

// ── Main: show live events + /odds/multi shape + upcoming ─────────────────────
echo "=== LIVE FOOTBALL EVENTS ===\n";
[$code,$events] = api('/events/live', ['sport'=>'football']);
$arr = is_array($events)?$events:($events['data']??$events['events']??[]);
echo "[HTTP $code] Total: ".count($arr)." live events\n\n";

// Show first 5 live events basic info
foreach (array_slice($arr,0,5) as $ev) {
    $id=$ev['id']??'?';
    $home=is_string($ev['home_team']??null)?$ev['home_team']:(is_array($ev['home_team']??null)?($ev['home_team']['name']??'?'):($ev['home']??'?'));
    $away=is_string($ev['away_team']??null)?$ev['away_team']:(is_array($ev['away_team']??null)?($ev['away_team']['name']??'?'):($ev['away']??'?'));
    $lg=$ev['league_name']??$ev['competition']??'?';
    echo "Live $id [$lg]: $home vs $away  → ?pass=debug216&id=$id\n";
}

echo "\n=== /odds/multi SHAPE TEST (first 3 live IDs) ===\n";
$testIds = implode(',', array_column(array_slice($arr,0,3),'id'));
if ($testIds) {
    $books = 'Bet365,1xbet,22Bet,888Sport,Betano,Unibet,10BET';
    [$code,$r,$raw] = api('/odds/multi', ['eventIds'=>$testIds,'bookmakers'=>$books]);
    echo "[HTTP $code] eventIds=$testIds\n";
    echo "Raw (4000 chars):\n".substr($raw??'(empty)',0,4000)."\n\n";
} else { echo "No live IDs to test\n\n"; }

echo "=== UPCOMING EVENTS — /events (no status filter) ===\n";
[$code,$up] = api('/events', ['sport'=>'football']);
$uarr=is_array($up)?$up:($up['data']??$up['events']??[]);
echo "[HTTP $code] Total from /events: ".count($uarr)."\n";
// Filter upcoming
$now=time(); $upcoming=[];
foreach ((array)$uarr as $ev) {
    $st=strtolower($ev['status']??'');
    if (in_array($st,['settled','cancelled','finished','ended','live'])) continue;
    $upcoming[]=$ev;
}
echo "Upcoming (non-live/finished): ".count($upcoming)."\n";
foreach (array_slice($upcoming,0,5) as $ev) {
    $id=$ev['id']??'?';
    $home=is_string($ev['home_team']??null)?$ev['home_team']:(is_array($ev['home_team']??null)?($ev['home_team']['name']??'?'):($ev['home']??'?'));
    $away=is_string($ev['away_team']??null)?$ev['away_team']:(is_array($ev['away_team']??null)?($ev['away_team']['name']??'?'):($ev['away']??'?'));
    $lg=$ev['league_name']??$ev['competition']??'?'; $d=$ev['date']??$ev['starts_at']??'?';
    echo "  $id [$lg] $d: $home vs $away  → ?pass=debug216&id=$id\n";
}

echo "\n=== REDIS STATE ===\n";
echo "→ ?pass=debug216&redis=1\n";

echo "\n=== KEY ENDPOINT SHAPE TESTS ===\n";
// Test /events/upcoming
[$c2] = api('/events/upcoming', ['sport'=>'football']);
echo "/events/upcoming HTTP: $c2\n";
// Test /events with status param
[$c3] = api('/events', ['sport'=>'football','status'=>'upcoming']);
echo "/events?status=upcoming HTTP: $c3\n";
// Test /leagues
[$c4,$lg] = api('/leagues', ['sport'=>'football']);
$lgArr=is_array($lg)?$lg:($lg['data']??[]);
echo "/leagues HTTP: $c4, count: ".count($lgArr)."\n";
if ($lgArr) { echo "Sample league: ".json_encode($lgArr[0])."\n"; }
