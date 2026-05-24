<?php
header('Content-Type: text/plain');
$TOKEN = '254610-7T3dEgVPsVZPNY';
$BASE  = 'https://api.b365api.com';
$ctx   = stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true,'header'=>"User-Agent: Test/1.0\r\n"]]);

function get($url) { global $ctx; return json_decode(@file_get_contents($url,false,$ctx),true); }

// 1. Try inplay_filter for each major sport
foreach ([1=>'Football',18=>'Basketball',13=>'Tennis'] as $sid => $sname) {
    $d = get("$BASE/v1/bet365/inplay_filter?sport_id=$sid&token=$TOKEN");
    $cnt = count($d['results'] ?? []);
    $total = $d['pager']['total'] ?? '?';
    echo "$sname: $cnt returned, total=$total, success=" . ($d['success'] ?? 'NO') . "\n";
    if ($cnt > 0) {
        $m = $d['results'][0];
        echo "  Keys: " . implode(', ', array_keys($m)) . "\n";
        echo "  odds field: " . (isset($m['odds']) ? json_encode($m['odds']) : 'NONE') . "\n";
        echo "  r_id: " . ($m['r_id'] ?? 'NONE') . "\n";
        break; // Show structure once
    }
}
echo "\n";

// 2. Try inplay (all sports, includes odds?)
$d2 = get("$BASE/v1/bet365/inplay?token=$TOKEN");
echo "inplay endpoint: success=" . ($d2['success'] ?? 'NO') . ", count=" . count($d2['results'] ?? []) . "\n";
if (!empty($d2['results'][0])) {
    $m2 = $d2['results'][0];
    echo "Keys: " . implode(', ', array_keys($m2)) . "\n";
    echo "odds: " . (isset($m2['odds']) ? 'YES' : 'NO') . "\n";
}
echo "\n";

// 3. Check DB for r_id and try event?FI
$db_paths = [dirname(__DIR__,2).'/forza/includes/db.php', __DIR__.'/../includes/db.php'];
foreach ($db_paths as $p) { if (file_exists($p)) { require_once $p; if (isset($pdo)) break; } }
if (isset($pdo)) {
    $st = $pdo->query("SELECT raw_json FROM sb_matches WHERE status='inplay' AND raw_json LIKE '%r_id%' LIMIT 1");
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $data = json_decode($row['raw_json'], true);
        $fi = $data['r_id'] ?? null;
        echo "DB match r_id=$fi, home=" . ($data['home']['name'] ?? '?') . "\n";
        if ($fi) {
            $ev = get("$BASE/v1/bet365/event?FI=$fi&token=$TOKEN");
            $results = $ev['results'] ?? [];
            $types = array_unique(array_column($results, 'type'));
            echo "Event endpoint: success=" . ($ev['success'] ?? 'NO') . ", items=" . count($results) . ", types=" . implode(',', $types) . "\n";
            // Find PA items (odds)
            $pa = array_filter($results, fn($x) => ($x['type'] ?? '') === 'PA');
            $sample = array_slice(array_values($pa), 0, 6);
            foreach ($sample as $p) {
                echo "  PA: N2=" . ($p['N2'] ?? '?') . " OD=" . ($p['OD'] ?? '?') . "\n";
            }
        }
    } else {
        echo "No DB match with r_id found\n";
    }
    // Also show what raw_json looks like
    $st2 = $pdo->query("SELECT raw_json FROM sb_matches WHERE status='inplay' LIMIT 1");
    $row2 = $st2->fetch(PDO::FETCH_ASSOC);
    if ($row2) {
        $d3 = json_decode($row2['raw_json'], true);
        echo "\nDB raw_json keys: " . implode(', ', array_keys($d3)) . "\n";
        echo "has live_odds: " . (isset($d3['live_odds']) ? 'YES' : 'NO') . "\n";
    }
}
