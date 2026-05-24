<?php
/**
 * Sportsbook API Backend
 * Reads matches from sb_matches DB (populated by sync_daemon.php cron).
 * BetsAPI Token: 254610-7T3dEgVPsVZPNY
 *
 * DB: connects to forza_db (where sb_matches table lives).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
error_reporting(0);

define('BETSAPI_TOKEN', '254610-7T3dEgVPsVZPNY');
define('BETSAPI_BASE',  'https://api.b365api.com');

// ── DB Connection — try forza path first (where sb_matches lives) ──────────
$pdo = null;
$db_connected = false;

$db_paths = [
    dirname(__DIR__, 2) . '/forza/includes/db.php',   // forza_db (primary — table is here)
    __DIR__ . '/../includes/db.php',                   // alpha216_db (fallback)
    dirname(__DIR__)    . '/includes/db.php',           // another fallback
];

foreach ($db_paths as $p) {
    if (file_exists($p)) {
        require_once $p;
        if (isset($pdo) && $pdo instanceof PDO) {
            $db_connected = true;
            break;
        }
    }
}

// ── BetsAPI helper — returns full decoded response ──────────────────────────
function betsapi_get($path, $params = []) {
    $params['token'] = BETSAPI_TOKEN;
    $url = BETSAPI_BASE . $path . '?' . http_build_query($params);
    $ctx = stream_context_create(['http' => [
        'timeout' => 10,
        'ignore_errors' => true,
        'header' => "User-Agent: SB-API/1.0\r\n"
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if (!$body) return null;
    return json_decode($body, true);
}

// ── BetsAPI helper — returns pager.total (real live count) ─────────────────
// Uses pager.total so Football shows 999+, Basketball 510+, etc.
function betsapi_live_count($sport_id) {
    $resp = betsapi_get('/v1/bet365/inplay_filter', ['sport_id' => $sport_id, 'page' => 1]);
    if (!$resp || empty($resp['success'])) return 0;
    // pager.total is the REAL total, not just page-1 count
    if (isset($resp['pager']['total'])) return (int)$resp['pager']['total'];
    return count($resp['results'] ?? []);
}

// ── BetsAPI helper — returns pager.total for upcoming matches ──────────────
function betsapi_upcoming_count($sport_id) {
    $resp = betsapi_get('/v1/bet365/upcoming', ['sport_id' => $sport_id, 'page' => 1]);
    if (!$resp || empty($resp['success'])) return 0;
    if (isset($resp['pager']['total'])) return (int)$resp['pager']['total'];
    return count($resp['results'] ?? []);
}

// ── Ensure sb_matches table exists ────────────────────────────────────────
if ($db_connected) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sb_matches (
            id VARCHAR(50) PRIMARY KEY,
            sport_id INT NOT NULL DEFAULT 1,
            league_name VARCHAR(200) NOT NULL DEFAULT '',
            home_team VARCHAR(150) NOT NULL DEFAULT '',
            away_team VARCHAR(150) NOT NULL DEFAULT '',
            start_time BIGINT NOT NULL DEFAULT 0,
            status ENUM('upcoming','inplay','ended') NOT NULL DEFAULT 'upcoming',
            score VARCHAR(30) NULL,
            raw_json MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_sb_time (start_time),
            KEY idx_sb_status (status),
            KEY idx_sb_sport (sport_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

$action   = $_GET['action']   ?? 'inplay';
$sport_id = (int)($_GET['sport_id'] ?? 1);

// ── Premium leagues list — shown at top of match list ─────────────────────
// Mapped by sport_id → array of priority league name fragments (case-insensitive)
define('PRIORITY_LEAGUES', [
    1 => [  // Football
        'champions league', 'champion league', 'ligue des champions',
        'premier league', 'english premier',
        'la liga', 'primera division', 'primera liga',
        'serie a', 'italy serie',
        'bundesliga', 'germany bundesliga',
        'ligue 1', 'france ligue',
        'eredivisie', 'dutch',
        'primeira liga', 'portugal',
        'super lig', 'turkey',
        'europa league', 'europa conference',
        'world cup', 'coupe du monde',
        'copa libertadores', 'copa sudamericana',
        'nba', 'champions',
    ],
    18 => [ // Basketball
        'nba', 'euroleague', 'eurocup', 'acb', 'pro a',
        'bbl', 'lnb', 'serie a', 'turkish',
    ],
    13 => [ // Tennis
        'roland garros', 'wimbledon', 'us open', 'australian open',
        'atp', 'wta', 'grand slam',
    ],
]);

// ── Helper: get priority score for a league name (lower = better) ─────────
function league_priority($sport_id, $league_name) {
    $prios = PRIORITY_LEAGUES[$sport_id] ?? [];
    $name = strtolower($league_name);
    foreach ($prios as $i => $fragment) {
        if (strpos($name, $fragment) !== false) {
            return $i; // Earlier in list = higher priority
        }
    }
    return 999; // Low priority (non-premium)
}

// ── Helper: fetch rows from DB ─────────────────────────────────────────────
// IMPORTANT: Fetch MORE than limit, sort by priority FIRST, then slice.
// This ensures premium leagues (La Liga, PL, etc.) are never cut off by LIMIT.
function db_fetch_matches($pdo, $where, $params, $limit = 500, $sport_id = 0) {
    $results = [];
    try {
        // Over-fetch so premium leagues aren't cut off by DB LIMIT before sort
        $fetch_limit = ($sport_id > 0) ? max($limit * 4, 4000) : $limit;
        $stmt = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE $where ORDER BY start_time ASC LIMIT $fetch_limit");
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $d = json_decode($row['raw_json'], true);
            if ($d && isset($d['home']['name']) && $d['home']['name'] !== ''
                   && isset($d['away']['name']) && $d['away']['name'] !== '') {
                $results[] = $d;
            }
        }
    } catch (Exception $e) {}

    // Sort: premium leagues first, then by start_time
    if (!empty($results) && $sport_id > 0) {
        usort($results, function($a, $b) use ($sport_id) {
            $pa = league_priority($sport_id, $a['league']['name'] ?? '');
            $pb = league_priority($sport_id, $b['league']['name'] ?? '');
            if ($pa !== $pb) return $pa - $pb;
            return ($a['time'] ?? 0) - ($b['time'] ?? 0);
        });
    }

    // Apply limit AFTER priority sort
    if (count($results) > $limit) {
        $results = array_slice($results, 0, $limit);
    }

    return $results;
}

// ── Fire-and-forget non-blocking HTTP call (Apache + PHP-FPM safe) ────────
function fire_and_forget($url) {
    $parts = parse_url($url);
    if (empty($parts['host'])) return;
    $host = $parts['host'];
    $port = $parts['port'] ?? 80;
    $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    $fp = @fsockopen($host, $port, $errno, $errstr, 1);
    if (!$fp) return;
    $req = "GET $path HTTP/1.1\r\nHost: $host\r\nConnection: close\r\n\r\n";
    @fwrite($fp, $req);
    @fclose($fp); // close immediately — server keeps processing
}

// ── Helper: cache matches to DB ───────────────────────────────────────────
function cache_to_db($pdo, $matches, $sport_id_fallback = 1) {
    if (!$pdo || empty($matches)) return 0;
    $count = 0;
    try {
        // Preserve live_odds written by sync_daemon: if the existing row has live_odds
        // in raw_json, merge them back into the fresh data so they are never wiped out.
        $stmt = $pdo->prepare("INSERT INTO sb_matches (id,sport_id,league_name,home_team,away_team,start_time,status,score,raw_json)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              status=VALUES(status),
              score=VALUES(score),
              start_time=VALUES(start_time),
              raw_json = IF(
                JSON_EXTRACT(raw_json,'$.live_odds') IS NOT NULL,
                JSON_SET(VALUES(raw_json),'$.live_odds',JSON_EXTRACT(raw_json,'$.live_odds')),
                VALUES(raw_json)
              ),
              updated_at=CURRENT_TIMESTAMP");
        $pdo->beginTransaction();
        foreach ($matches as $m) {
            if (!isset($m['id']) || !isset($m['home']['name'])) continue;
            $sid    = (int)($m['sport_id'] ?? $sport_id_fallback);
            $league = $m['league']['name'] ?? '';
            $home   = $m['home']['name']   ?? '';
            $away   = $m['away']['name']   ?? '';
            $time   = (int)($m['time']     ?? 0);
            $status = ($m['time_status'] === '1') ? 'inplay' : 'upcoming';
            $score  = $m['ss'] ?? null;
            $stmt->execute([$m['id'], $sid, $league, $home, $away, $time, $status, $score, json_encode($m)]);
            $count++;
        }
        $pdo->commit();
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $e2) {}
    }
    return $count;
}

// ═══ COUNTS ════════════════════════════════════════════════════════════════
// ═══ COUNTS — real live counts via BetsAPI pager.total (90s file cache) ═════
if ($action === 'counts') {
    @set_time_limit(12);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    // Fresh cache: 90s bucket
    $cache_path = $cache_dir . '/sb_counts_' . floor(time() / 90) . '.json';
    if (file_exists($cache_path)) {
        echo file_get_contents($cache_path);
        exit;
    }

    // Stale cache — only serve if < 8 minutes old (prevents stale 0-live data from dawn)
    $stale_glob = glob($cache_dir . '/sb_counts_*.json');
    $stale_path = $stale_glob ? end($stale_glob) : null;
    if ($stale_path && file_exists($stale_path) && (time() - filemtime($stale_path)) < 480) {
        echo file_get_contents($stale_path);
        exit;
    }
    // Delete stale files older than 8 min so they don't block fresh counts
    if ($stale_glob) {
        foreach ($stale_glob as $sf) {
            if ((time() - filemtime($sf)) >= 480) @unlink($sf);
        }
    }

    $all_sports = [1,18,13,91,107,17,151,16,78,45,117,36,83,66,56,48,92,40,19,94,10,90,46,14,75,110,152,153,154];
    $counts = [];
    // Top 5 sports: call BetsAPI inplay_filter for real pager.total (the true worldwide live count)
    $api_sports = [1, 18, 13, 91, 107];

    foreach ($all_sports as $sid) {
        $live_cnt = 0;
        $upcoming_cnt = 0;

        // Step 1: Local live cache (populated every ~15s by cron) — fast, no API call
        $lc = $cache_dir . '/live_' . $sid . '.json';
        $lc_age = file_exists($lc) ? (time() - filemtime($lc)) : 9999;
        if ($lc_age < 120 && file_exists($lc)) {
            $lj = json_decode(@file_get_contents($lc), true);
            $live_cnt = is_array($lj) ? count($lj) : 0;
        }

        // Step 2: For top sports, get BetsAPI pager.total (real worldwide count like fcbet 999+)
        // This gives the TRUE live match count, not just what's locally cached
        if (in_array($sid, $api_sports, true)) {
            $api_live = betsapi_live_count($sid);
            if ($api_live > $live_cnt) $live_cnt = $api_live; // take the higher value
        }

        $sport_cache = $cache_dir . '/upcoming_' . $sid . '.json';
        if (file_exists($sport_cache)) {
            $sc = json_decode(@file_get_contents($sport_cache), true);
            $upcoming_cnt = is_array($sc) ? count($sc) : 0;
        }
        if ($upcoming_cnt === 0 && $db_connected) {
            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM sb_matches WHERE sport_id=? AND status IN('upcoming','inplay')");
                $st->execute([$sid]);
                $upcoming_cnt = (int)$st->fetchColumn();
            } catch (Exception $e) {}
        }
        if ($upcoming_cnt === 0 && in_array($sid, $api_sports, true)) {
            $upcoming_cnt = betsapi_upcoming_count($sid);
        }

        $total = $live_cnt + $upcoming_cnt;
        $counts[$sid] = ['total' => max($total, $live_cnt, $upcoming_cnt), 'live' => $live_cnt];
    }

    $payload = json_encode(['success' => 1, 'counts' => $counts]);
    @file_put_contents($cache_path, $payload);
    echo $payload;
    exit;
}

// ═══ Helper: extract inline 1x2 odds from inplay_filter match object ══════
function _extract_inplay_match_odds($m) {
    $o = $m['odds'] ?? null;
    if (!$o || !is_array($o)) return null;
    foreach (['live','updated','init','start'] as $k) {
        if (isset($o[$k]) && is_array($o[$k])) {
            $r = _parse_flat_odds_api($o[$k]);
            if ($r) return $r;
        }
    }
    if (isset($o['1'])) return _parse_flat_odds_api($o);
    return null;
}
function _parse_flat_odds_api($o) {
    if (!$o || !is_array($o)) return null;
    // Support both plain decimal ("3.00") and fractional string ("11/10") values
    $pv = function($v) {
        $v = trim((string)$v);
        if (!$v || $v === '0' || $v === '-') return 0.0;
        if (strpos($v, '/') !== false) {
            [$fn, $fd] = explode('/', $v, 2);
            $fd = floatval($fd);
            return $fd > 0 ? round(1 + floatval($fn) / $fd, 2) : 0.0;
        }
        return floatval($v);
    };
    $h = $pv($o['1'] ?? $o['home'] ?? $o['h'] ?? 0);
    $x = $pv($o['X'] ?? $o['x'] ?? $o['draw'] ?? 0);
    $a = $pv($o['2'] ?? $o['away'] ?? $o['a'] ?? 0);
    // Only require h (home odds) — x can be null for 2-way sports (basketball, tennis)
    if ($h < 1.01) return null;
    return ['h'=>round($h,2),'x'=>($x>0.01?round($x,2):null),'a'=>($a>0.01?round($a,2):null)];
}
// Convert fractional OD string from live stream to decimal
function _stream_od_to_dec($raw) {
    $raw = trim((string)$raw);
    if (!$raw || $raw==='-' || $raw==='0') return null;
    if (strtoupper($raw)==='EVS') return 2.00;
    if (strpos($raw,'/')!==false) {
        [$fn,$fd]=explode('/',$raw,2); $fd=floatval($fd);
        return $fd>0 ? round(1+floatval($fn)/$fd,2) : null;
    }
    $v=floatval($raw); return $v>0 ? round($v+1,2) : null;
}

// ── Helper: parse a Bet365 event-stream array → live_odds struct ──────────
// Works for both /v1/bet365/event?FI=X and /v1/bet365/inplay results[0]
function parse_event_stream_odds($results_arr) {
    // results[0] is the actual flat stream; flatten one level if needed
    $stream = (is_array($results_arr[0] ?? null)) ? $results_arr[0] : (is_array($results_arr) ? $results_arr : []);
    $h_o = $x_o = $a_o = $ov_o = $un_o = null;
    $curr_ou_line = 2.5;
    foreach ($stream as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';
        // Extract OU line from market name
        if ($type === 'MA') {
            $mkt = $item['NA'] ?? $item['N2'] ?? '';
            if (preg_match('/over.under\s*(\d+\.?\d*)/i', $mkt, $mat)
             || preg_match('/total[^0-9]*(\d+\.?\d*)/i', $mkt, $mat)) {
                $curr_ou_line = (float)$mat[1];
            }
        }
        if ($type === 'PA') {
            $n2  = (string)($item['N2'] ?? $item['NA'] ?? '');
            $or  = (string)($item['OR'] ?? '');
            $od  = _stream_od_to_dec($item['OD'] ?? '');
            if (!$od || $od < 1.01) continue;
            if (($n2 === '1' || $or === '0') && !$h_o)  $h_o = $od;
            if (($n2 === 'X' || $or === '1') && !$x_o)  $x_o = $od;
            if (($n2 === '2' || $or === '2') && !$a_o)  $a_o = $od;
            if (stripos($n2, 'over')  !== false && !$ov_o) $ov_o = $od;
            if (stripos($n2, 'under') !== false && !$un_o) $un_o = $od;
        }
    }
    if (!$h_o) return null;
    return ['h'=>$h_o,'x'=>$x_o,'a'=>$a_o,'ou_line'=>$curr_ou_line,'ou_over'=>$ov_o,'ou_under'=>$un_o,'ts'=>time()];
}

// ═══ INPLAY — Auto-sync from BetsAPI (no daemon needed) ═══════════════════
// Uses a 20-second file cache per sport + global stream cache for live OU odds.
// This means real-time data with zero manual intervention required.
if ($action === 'inplay') {
    $results    = [];
    $cache_dir  = __DIR__ . '/cache';
    if (!is_dir($cache_dir)) @mkdir($cache_dir, 0755, true);

    // Auto-purge stale sb_counts files older than 8 min so EN DIRECT badges refresh
    foreach (glob($cache_dir . '/sb_counts_*.json') ?: [] as $sf) {
        if ((time() - filemtime($sf)) >= 480) @unlink($sf);
    }

    $sport_cache  = $cache_dir . '/live_' . $sport_id . '.json';
    $stream_cache = $cache_dir . '/inplay_stream.json';
    $cache_ttl    = 20; // seconds (refresh every ~3rd app.js poll)

    // ── Step 1: Get or refresh inplay_filter per sport ─────────────────────
    $use_sport_cache = file_exists($sport_cache) && (time()-filemtime($sport_cache)) < $cache_ttl;
    if ($use_sport_cache) {
        $results = json_decode(file_get_contents($sport_cache), true) ?: [];
    } else {
        $live_api = []; $seen_ids = [];
        for ($pg=1; $pg<=5; $pg++) {
            $resp = betsapi_get('/v1/bet365/inplay_filter', ['sport_id'=>$sport_id,'page'=>$pg]);
            if (empty($resp['results'])) break;
            foreach ($resp['results'] as $m) {
                $mid = $m['id'] ?? null;
                if ($mid && !isset($seen_ids[$mid])
                    && isset($m['home']['name']) && $m['home']['name'] !== '') {
                    $seen_ids[$mid] = 1;
                    $live_api[] = $m;
                }
            }
            if (count($resp['results']) < 50) break;
        }
        if (!empty($live_api)) {
            $results = $live_api;
            @file_put_contents($sport_cache, json_encode($results));
            // Also update DB for league_matches / championship view
            if ($db_connected) cache_to_db($pdo, $results, $sport_id);
        }
    }

    // ── Step 2: Get or refresh global inplay stream (for live OU odds) ─────
    $use_stream_cache = file_exists($stream_cache) && (time()-filemtime($stream_cache)) < $cache_ttl;
    if (!$use_stream_cache) {
        $sresp = betsapi_get('/v1/bet365/inplay', [], 30);
        if (!empty($sresp['results'][0]) && is_array($sresp['results'][0])) {
            @file_put_contents($stream_cache, json_encode($sresp['results'][0]));
        }
    }
    $stream = json_decode(@file_get_contents($stream_cache)?:'[]', true) ?: [];

    // ── Step 3: Parse odds + OU from stream (same logic as sync_daemon) ─────
    $stream_odds = [];   // keyed by FI (Bet365 event id)
    $rid_to_fi   = [];   // r_id (inplay_filter) → FI (stream EV)
    $curr_fi = null; $curr_h = $curr_x = $curr_a = null;
    $curr_ou_line = 2.5; $curr_ou_over = $curr_ou_under = null;
    $curr_ma = '';

    foreach ($stream as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';

        if ($type === 'EV') {
            if ($curr_fi && $curr_h) {
                $stream_odds[$curr_fi] = ['h'=>$curr_h,'x'=>$curr_x,'a'=>$curr_a,
                    'ou_line'=>$curr_ou_line,'ou_over'=>$curr_ou_over,'ou_under'=>$curr_ou_under,'ts'=>time()];
            }
            // FI may be empty; OI is the reliable Bet365 event id in this stream
            $curr_fi = $item['OI'] ?? $item['FI'] ?? null;
            if (!empty($item['ID'])) {
                // ID format: "194088383C1A_1_3" → base "194088383C1A" matches r_id in inplay_filter
                $id_base = explode('_', $item['ID'])[0];
                if ($id_base) $rid_to_fi[$id_base] = $curr_fi;
                // Also map numeric prefix (e.g. "194088383") in case r_id strips suffix
                $num_only = preg_replace('/[^0-9].*$/', '', $id_base);
                if ($num_only && $num_only !== $id_base) $rid_to_fi[$num_only] = $curr_fi;
            }
            $curr_h=$curr_x=$curr_a=$curr_ou_over=$curr_ou_under=null;
            $curr_ou_line=2.5; $curr_ma='';
        }

        if ($type === 'MA' && $curr_fi) {
            $mkt = $item['NA'] ?? $item['N2'] ?? '';
            $curr_ma = strtolower($mkt);
            // Extract OU line from market name: "Goals Over/Under 5.5", "Total 0.5"
            if (preg_match('/(\d+\.?\d*)\s*goal/i',$mkt,$mat)
             || preg_match('/over.under\s*(\d+\.?\d*)/i',$mkt,$mat)
             || preg_match('/total[^0-9]*(\d+\.?\d*)/i',$mkt,$mat)) {
                $curr_ou_line=(float)$mat[1];
                $curr_ou_over=$curr_ou_under=null;
            }
        }

        if ($type === 'PA' && $curr_fi) {
            $n2  = (string)($item['N2'] ?? $item['NA'] ?? '');
            $or  = (string)($item['OR'] ?? '');
            $dec = _stream_od_to_dec($item['OD'] ?? '');
            if (!$dec || $dec < 1.01) continue;
            if (($n2==='1'||$or==='0') && !$curr_h) $curr_h=$dec;
            if (($n2==='X'||$or==='1') && !$curr_x) $curr_x=$dec;
            if (($n2==='2'||$or==='2') && !$curr_a) $curr_a=$dec;
            if (stripos($n2,'over')!==false  && !$curr_ou_over)  $curr_ou_over=$dec;
            if (stripos($n2,'under')!==false && !$curr_ou_under) $curr_ou_under=$dec;
        }
    }
    if ($curr_fi && $curr_h) {
        $stream_odds[$curr_fi] = ['h'=>$curr_h,'x'=>$curr_x,'a'=>$curr_a,
            'ou_line'=>$curr_ou_line,'ou_over'=>$curr_ou_over,'ou_under'=>$curr_ou_under,'ts'=>time()];
    }

    // ── Step 4: Batch-load prematch OU from DB live_odds ─────────────────
    $db_odds = [];
    if ($db_connected && !empty($results)) {
        $ids = array_values(array_filter(array_column($results,'id')));
        if (!empty($ids)) {
            $ph = implode(',',array_fill(0,count($ids),'?'));
            try {
                $st = $pdo->prepare("SELECT id, JSON_EXTRACT(raw_json,'$.live_odds') as lo FROM sb_matches WHERE id IN ($ph)");
                $st->execute($ids);
                while ($row=$st->fetch(PDO::FETCH_ASSOC)) {
                    if ($row['lo'] && $row['lo']!=='null') $db_odds[(string)$row['id']]=json_decode($row['lo'],true);
                }
            } catch (Exception $e) {}
        }
    }

    // ── Step 4.5: Inject from per-sport odds file cache ───────────────────
    // Odds cache is filled by bg_sync (fire-and-forget) or sync_daemon
    $odds_cache_file = $cache_dir . '/odds_' . $sport_id . '.json';
    $file_odds = file_exists($odds_cache_file)
        ? (json_decode(file_get_contents($odds_cache_file), true) ?: [])
        : [];
    // Normalize keys to string (array_merge may have produced int keys in older cache)
    $file_odds_norm = [];
    foreach ($file_odds as $fid => $fo) {
        $file_odds_norm[(string)$fid] = $fo;
    }
    // Merge: file_odds fills in gaps; DB live_odds takes priority if it has OU
    foreach ($file_odds_norm as $fid => $fo) {
        if (!isset($db_odds[$fid])) $db_odds[$fid] = $fo;
        elseif (!($db_odds[$fid]['ou_over'] ?? null)) {
            $db_odds[$fid]['ou_line']  = $fo['ou_line']  ?? 2.5;
            $db_odds[$fid]['ou_over']  = $fo['ou_over']  ?? null;
            $db_odds[$fid]['ou_under'] = $fo['ou_under'] ?? null;
        }
    }

    // Trigger bg_sync if odds_cache is missing or older than 90 seconds
    $odds_cache_age = file_exists($odds_cache_file) ? (time()-filemtime($odds_cache_file)) : 9999;
    if ($odds_cache_age > 90) {
        $lock_f = $cache_dir . '/bgsync_' . $sport_id . '.lock';
        $lock_age = file_exists($lock_f) ? (time()-filemtime($lock_f)) : 9999;
        if ($lock_age > 60) { // don't fire if already running
            touch($lock_f);
            $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
            $script_path = parse_url($_SERVER['REQUEST_URI'] ?? '/public_html/sportsbook/api.php', PHP_URL_PATH);
            fire_and_forget('http://' . $host . $script_path . '?action=bg_sync&sport_id=' . $sport_id . '&_k=sbodds');
        }
    }

    // ── Step 5: Inject live_odds into each match ─────────────────────────────
    foreach ($results as &$m) {
        $mid  = (string)($m['id'] ?? '');
        $rid  = (string)($m['r_id'] ?? $mid);
        // Try r_id directly, then numeric strip, then EV base mapping
        $rid_num = preg_replace('/[^0-9].*$/', '', $rid); // strip C1A suffix
        $fi   = $rid_to_fi[$rid] ?? $rid_to_fi[$rid_num] ?? $rid_to_fi[$mid] ?? null;
        $sdat = ($fi ? ($stream_odds[$fi] ?? null) : null)
              ?? $stream_odds[$rid] ?? $stream_odds[$rid_num] ?? null;
        $inl  = _extract_inplay_match_odds($m);
        $db_lo= $db_odds[$mid] ?? $db_odds[$rid] ?? null;

        // Priority: DB (sync_daemon continuously writes live_odds) → stream → inline API
        // Stream is more real-time when r_id mapping works; DB is the reliable fallback.
        $h = $db_lo['h'] ?? $sdat['h'] ?? $inl['h'] ?? null;
        $x = $db_lo['x'] ?? $sdat['x'] ?? $inl['x'] ?? null;
        $a = $db_lo['a'] ?? $sdat['a'] ?? $inl['a'] ?? null;

        // OU: stream gives real-time line changes, DB is fallback
        $ou_line  = $sdat['ou_line']  ?? $db_lo['ou_line']  ?? 2.5;
        $ou_over  = $db_lo['ou_over']  ?? $sdat['ou_over']  ?? null;
        $ou_under = $db_lo['ou_under'] ?? $sdat['ou_under'] ?? null;

        if ($h) {
            $m['live_odds'] = ['h'=>$h,'x'=>$x,'a'=>$a,
                'ou_line'=>$ou_line,'ou_over'=>$ou_over,'ou_under'=>$ou_under,'ts'=>time()];
        }
    }
    unset($m);

    // ── Step 5.5: Per-event odds cache for live matches still missing odds ────
    // Reads ev_{id}.json (60s TTL). Fires async for every match still missing odds,
    // so the NEXT poll (5s) will have fresh data. No arbitrary cap on fire-and-forget.
    $host_ev   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_ev = parse_url($_SERVER['REQUEST_URI'] ?? '/sportsbook/api.php', PHP_URL_PATH);
    foreach ($results as &$mr5) {
        if (isset($mr5['live_odds'])) continue;
        if (($mr5['time_status'] ?? '0') !== '1') continue;
        $mid_ev = (string)($mr5['id'] ?? '');
        $rid_ev = (string)($mr5['r_id'] ?? $mid_ev);
        // Try per-event cache (r_id key first, then id key)
        foreach ([$rid_ev, $mid_ev] as $eck) {
            if (!$eck) continue;
            $ev_cache_f = $cache_dir . '/ev_' . $eck . '.json';
            if (file_exists($ev_cache_f) && (time() - filemtime($ev_cache_f)) < 60) {
                $evo = json_decode(file_get_contents($ev_cache_f), true);
                if ($evo && ($evo['h'] ?? 0) > 1.01) { $mr5['live_odds'] = $evo; break; }
            }
        }
        if (isset($mr5['live_odds'])) continue;
        // Fire async fetch for EVERY live match without odds — 5s poll interval means
        // the result will arrive on the very next request.
        if ($rid_ev) {
            fire_and_forget('http://' . $host_ev . $script_ev . '?action=fetch_event_odds&id=' . urlencode($rid_ev));
        }
    }
    unset($mr5);

    // ── Step 6: DB fallback when BetsAPI unreachable ────────────────────────
    if (empty($results) && $db_connected) {
        $results = db_fetch_matches($pdo,"sport_id=? AND status='inplay'",[$sport_id],500,$sport_id);
        if (empty($results))
            $results = db_fetch_matches($pdo,"sport_id=? AND status='upcoming'",[$sport_id],500,$sport_id);
    }

    // ── Step 7: Mark finished matches in DB immediately ───────────────────
    if ($db_connected) {
        // 7a: Remove time_status=3 from results
        $ended_ids = [];
        foreach ($results as $k => $rm) {
            if (($rm['time_status'] ?? '0') === '3') {
                $ended_ids[] = $rm['id'];
                unset($results[$k]);
            }
        }
        $results = array_values($results);

        // 7b: Detect "disappeared" matches — were inplay in DB but no longer in API response
        // These matches have ended but BetsAPI simply stops returning them
        try {
            $st_live = $pdo->prepare("SELECT id FROM sb_matches WHERE sport_id=? AND status='inplay'");
            $st_live->execute([$sport_id]);
            $db_live_ids = $st_live->fetchAll(PDO::FETCH_COLUMN);
            $api_ids = array_map(function($m) { return (string)$m['id']; }, $results);
            foreach ($db_live_ids as $dbid) {
                if (!in_array((string)$dbid, $api_ids)) {
                    $ended_ids[] = $dbid;
                }
            }
        } catch (Exception $e) {}

        if (!empty($ended_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($ended_ids), '?'));
                $pdo->prepare("UPDATE sb_matches SET status='ended' WHERE id IN ($placeholders)")->execute($ended_ids);
            } catch (Exception $e) {}
        }
    }

    // Sort by priority leagues
    if (!empty($results) && $sport_id > 0) {
        usort($results, function($a,$b) use ($sport_id) {
            $pa=league_priority($sport_id,$a['league']['name']??'');
            $pb=league_priority($sport_id,$b['league']['name']??'');
            return $pa!==$pb ? $pa-$pb : ($a['time']??0)-($b['time']??0);
        });
        if (count($results)>500) $results=array_slice($results,0,500);
    }

    echo json_encode(['success'=>1,'results'=>$results,'auto_sync'=>true]);
    exit;
}

// ═══ UPCOMING ══════════════════════════════════════════════════════════════
if ($action === 'upcoming' || $action === 'all_upcoming') {
    $results = [];

    if ($db_connected) {
        $results = db_fetch_matches($pdo, "sport_id=? AND status!='ended'", [$sport_id], 1000, $sport_id);
    }

    if (empty($results)) {
        $data = betsapi_get('/v1/bet365/upcoming', ['sport_id' => $sport_id]);
        if ($data && !empty($data['results'])) {
            foreach ($data['results'] as $m) {
                if (isset($m['home']['name']) && $m['home']['name'] !== '') {
                    $results[] = $m;
                }
            }
            if (!empty($results) && $db_connected) {
                cache_to_db($pdo, $results, $sport_id);
            }
        }
    }

    echo json_encode(['success' => 1, 'results' => $results]);
    exit;
}

// ═══ SYNC: Trigger a quick BetsAPI sync (called from cron or web) ══════════
if ($action === 'sync') {
    if (!$db_connected) {
        echo json_encode(['success' => 0, 'error' => 'DB not connected']);
        exit;
    }
    $saved = 0;
    $sports = [1, 18, 13, 91, 107, 17, 151, 16, 78, 45, 117, 36];

    // Inplay (all sports at once)
    $live = betsapi_get('/v1/bet365/inplay');
    if ($live && !empty($live['results'])) {
        $saved += cache_to_db($pdo, $live['results']);
    }

    // Upcoming per sport (1 page each for quick sync)
    foreach ($sports as $sid) {
        $data = betsapi_get('/v1/bet365/upcoming', ['sport_id' => $sid, 'page' => 1]);
        if ($data && !empty($data['results'])) {
            $saved += cache_to_db($pdo, $data['results'], $sid);
        }
    }

    // Cleanup ended matches — any match whose start_time + 3h is in the past
    try {
        $cutoff = time() - 10800; // 3 hours ago
        $pdo->exec("UPDATE sb_matches SET status='ended' WHERE status IN ('upcoming','inplay') AND start_time > 0 AND start_time < $cutoff");
    } catch (Exception $e) {}

    $total = 0;
    try { $total = $pdo->query("SELECT COUNT(*) FROM sb_matches WHERE status!='ended'")->fetchColumn(); } catch (Exception $e) {}

    echo json_encode(['success' => 1, 'saved' => $saved, 'total_active' => $total]);
    exit;
}

// ═══ LEAGUE MATCHES — filter by league name from DB ══════════════════════
if ($action === 'league_matches') {
    $league_q  = trim($_GET['league']   ?? '');
    $results   = [];

    // ── Step 0: Aggressive time-based cleanup ─────────────────────────────
    // Any match whose start_time + 3 hours is in the past → mark ended
    // This catches matches the daemon missed or BetsAPI stopped tracking
    if ($db_connected) {
        try {
            $cutoff_3h = time() - 10800; // 3 hours ago
            $pdo->prepare(
                "UPDATE sb_matches SET status='ended'
                  WHERE status IN ('inplay','upcoming')
                    AND start_time > 0
                    AND start_time < ?
                    AND sport_id=?"
            )->execute([$cutoff_3h, $sport_id]);
        } catch (Exception $e) {}
    }

    // ── Step 1: Disappeared-match cleanup for this sport ──────────────────
    if ($db_connected) {
        try {
            $st_live = $pdo->prepare("SELECT id FROM sb_matches WHERE sport_id=? AND status='inplay'");
            $st_live->execute([$sport_id]);
            $db_live_ids = $st_live->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($db_live_ids)) {
                $live_cache = $cache_dir . '/live_' . $sport_id . '.json';
                $api_live_ids = [];
                if (file_exists($live_cache) && (time() - filemtime($live_cache)) < 60) {
                    $cached = json_decode(@file_get_contents($live_cache), true);
                    if (is_array($cached)) foreach ($cached as $cm) $api_live_ids[] = (string)($cm['id'] ?? '');
                }
                $ended = [];
                foreach ($db_live_ids as $dbid) {
                    if (!empty($api_live_ids) && !in_array((string)$dbid, $api_live_ids)) $ended[] = $dbid;
                }
                if (!empty($ended)) {
                    $ph = implode(',', array_fill(0, count($ended), '?'));
                    $pdo->prepare("UPDATE sb_matches SET status='ended' WHERE id IN ($ph)")->execute($ended);
                }
            }
        } catch (Exception $e) {}
    }

    // ── Step 2: Query matches — use league_id first (precise), then name ──
    $league_id_q = trim($_GET['league_id'] ?? '');
    $now = time();

    if ($db_connected) {
        try {
            // Try by league_id first (most precise — avoids wrong-sport/country fallbacks)
            // Uses JSON_EXTRACT so no schema change needed; sport_id index keeps it fast
            if ($league_id_q !== '') {
                $stmt = $pdo->prepare(
                    "SELECT raw_json, start_time, status FROM sb_matches
                      WHERE sport_id=? AND status!='ended'
                        AND JSON_EXTRACT(raw_json,'$.league.id') = ?
                      ORDER BY start_time ASC LIMIT 200"
                );
                $stmt->execute([$sport_id, $league_id_q]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $d = json_decode($row['raw_json'], true);
                    if (!$d || empty($d['home']['name']) || empty($d['away']['name'])) continue;
                    $ts = $d['time_status'] ?? '0';
                    if ($ts === '3') continue;
                    $st = (int)($d['time'] ?? $row['start_time'] ?? 0);
                    if ($st > 0 && $ts !== '1' && ($st + 10800) < $now) continue;
                    $results[] = $d;
                }
            }

            // Fallback: league name search (only if league_id query returned nothing)
            if (empty($results) && $league_q !== '') {
                $patt = '%' . $league_q . '%';
                $stmt_exact = $pdo->prepare("SELECT COUNT(*) FROM sb_matches WHERE sport_id=? AND status!='ended' AND league_name=?");
                $stmt_exact->execute([$sport_id, $league_q]);
                $exact_count = (int)$stmt_exact->fetchColumn();

                $where_cond = $exact_count > 0 ? "league_name=?" : "league_name LIKE ?";
                $where_val  = $exact_count > 0 ? $league_q : $patt;

                $stmt = $pdo->prepare(
                    "SELECT raw_json, start_time, status FROM sb_matches
                      WHERE sport_id=? AND status!='ended'
                        AND $where_cond
                      ORDER BY start_time ASC LIMIT 200"
                );
                $stmt->execute([$sport_id, $where_val]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $d = json_decode($row['raw_json'], true);
                    if (!$d || empty($d['home']['name']) || empty($d['away']['name'])) continue;
                    $ts = $d['time_status'] ?? '0';
                    if ($ts === '3') continue;
                    $st = (int)($d['time'] ?? $row['start_time'] ?? 0);
                    if ($st > 0 && $ts !== '1' && ($st + 10800) < $now) continue;
                    $results[] = $d;
                }
            }
        } catch (Exception $e) {}
    }
    // NOTE: No fallback to "all sport matches" — shows empty championship if no matches found,
    // which is correct behaviour (avoids LaLiga click showing Premier League data)

    // ── ON-DEMAND prematch odds fetch (for matches missing live_odds) ──────
    // Fetches odds for up to 5 upcoming matches without odds — max ~2.5s added latency
    if (!empty($results) && $db_connected) {
        $needs = [];
        foreach ($results as $m) {
            if (empty($m['live_odds']['h']) && ($m['time_status'] ?? '0') !== '1') {
                $needs[] = $m;
            }
            if (count($needs) >= 5) break;
        }
        foreach ($needs as $nm) {
            $fi = $nm['id'] ?? null;
            if (!$fi) continue;
            $or = betsapi_get('/v1/bet365/prematch', ['FI' => $fi]);
            if (!$or || empty($or['results'])) continue;
            $pm = api_parse_prematch_odds($or);
            if (!$pm) continue;
            // Store in DB
            try {
                $rj = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=?");
                $rj->execute([$fi]);
                $raw = $rj->fetchColumn();
                if ($raw) {
                    $mdata = json_decode($raw, true);
                    $mdata['live_odds'] = $pm;
                    $pdo->prepare("UPDATE sb_matches SET raw_json=? WHERE id=?")->execute([json_encode($mdata), $fi]);
                }
            } catch (Exception $e) {}
            // Inject into results for immediate response
            foreach ($results as &$r) {
                if ($r['id'] == $fi) { $r['live_odds'] = $pm; break; }
            }
            unset($r);
        }
    }

    echo json_encode(['success' => 1, 'results' => $results, 'league_filter' => $league_q]);
    exit;
}

/* ── On-demand prematch odds parser for api.php (decimal format) ── */
function api_parse_prematch_odds($resp) {
    if (!$resp || empty($resp['results'])) return null;
    $r0 = $resp['results'][0] ?? null;
    if (!$r0 || empty($r0['main']['sp'])) return null;
    $sp = $r0['main']['sp'];
    $h = $x = $a = $ou_over = $ou_under = null;
    $ou_line = 2.5;
    $pdd = function($v) { $v = floatval($v); return $v >= 1.01 ? round($v, 2) : null; };

    // 1x2: positional
    if (!empty($sp['full_time_result'])) {
        $ftr = array_values($sp['full_time_result']);
        $h = $pdd($ftr[0]['odds'] ?? '');
        $x = $pdd($ftr[1]['odds'] ?? '');
        $a = $pdd($ftr[2]['odds'] ?? '');
    }
    // Tennis match winner (2-way, no draw)
    if (!$h && !empty($sp['to_win_match'])) {
        $twm = array_values($sp['to_win_match']);
        $h = $pdd($twm[0]['odds'] ?? '');
        $a = $pdd($twm[1]['odds'] ?? '');
    }
    if (!$h || $h < 1.01) return null;

    // OU — parse actual line from name field, use FIRST complete group
    if (!empty($sp['goals_over_under'])) {
        $curr_ou_line = 2.5; $pend_over = null; $pend_under = null;
        foreach ($sp['goals_over_under'] as $item) {
            if (!is_array($item)) continue;
            $iname  = trim($item['name']   ?? '');
            $header = strtolower($item['header'] ?? '');
            $odd    = $pdd($item['odds'] ?? '');
            // Line label: numeric name with no valid odds
            if ($iname !== '' && is_numeric($iname) && (!$odd || $odd < 1.01)) {
                $curr_ou_line = (float)$iname;
                $pend_over = null; $pend_under = null;
                continue;
            }
            if (!$odd) continue;
            if (strpos($header, 'over')  !== false && !$pend_over)  $pend_over  = $odd;
            if (strpos($header, 'under') !== false && !$pend_under) $pend_under = $odd;
            if ($pend_over && $pend_under) {
                $ou_line = $curr_ou_line; $ou_over = $pend_over; $ou_under = $pend_under;
                break;
            }
        }
        if (!$ou_over  && $pend_over)  $ou_over  = $pend_over;
        if (!$ou_under && $pend_under) $ou_under = $pend_under;
    }
    return ['h'=>$h,'x'=>$x,'a'=>$a,'ou_line'=>$ou_line,'ou_over'=>$ou_over,'ou_under'=>$ou_under,'ts'=>time()];
}

// ═══ LEAGUES — return available leagues for a sport from DB (with live_cnt) ══
if ($action === 'leagues') {
    // 30s cache per sport — makes sidebar "Chargement..." near-instant
    $leagues_cache = $cache_dir . '/leagues_' . $sport_id . '.json';
    $leagues_ttl   = 30;
    if (file_exists($leagues_cache) && (time() - filemtime($leagues_cache)) < $leagues_ttl) {
        header('Content-Type: application/json');
        echo @file_get_contents($leagues_cache);
        exit;
    }

    $leagues = [];
    if ($db_connected) {
        try {
            $stmt = $pdo->prepare(
                "SELECT league_name,
                        COUNT(*) as cnt,
                        SUM(CASE WHEN status='inplay' THEN 1 ELSE 0 END) as live_cnt
                   FROM sb_matches
                  WHERE sport_id=? AND status!='ended'
                  GROUP BY league_name
                  ORDER BY live_cnt DESC, cnt DESC, league_name ASC
                  LIMIT 300"
            );
            $stmt->execute([$sport_id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['league_name'] !== '') {
                    $leagues[] = [
                        'name'     => $row['league_name'],
                        'count'    => (int)$row['cnt'],
                        'live_cnt' => (int)$row['live_cnt']
                    ];
                }
            }
        } catch (Exception $e) {}
    }
    $out = json_encode(['success' => 1, 'leagues' => $leagues]);
    @file_put_contents($leagues_cache, $out);
    echo $out;
    exit;
}

// ═══ ODDS — fetch real pre-match odds from BetsAPI for an event ═══════════
// BetsAPI endpoints tried in order:
//   /v1/bet365/prematch_odds?event_id=X  (primary)
//   /v1/bet365/event?id=X               (fallback - full event with markets)
if ($action === 'odds') {
    $event_id = trim($_GET['event_id'] ?? '');
    if (!$event_id) {
        echo json_encode(['success' => 0, 'error' => 'event_id required']);
        exit;
    }

    // Check DB cache first
    $odds_data  = null;
    $match_data = null;
    if ($db_connected) {
        try {
            $rowS = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=?");
            $rowS->execute([$event_id]);
            $r = $rowS->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $match_data = json_decode($r['raw_json'], true);
                if (!empty($match_data['odds'])) $odds_data = $match_data['odds'];
            }
        } catch (Exception $e) {}
    }

    // If no cached odds, fetch from BetsAPI
    if (!$odds_data) {
        // Try v1 prematch_odds first
        $resp = betsapi_get('/v1/bet365/prematch_odds', ['event_id' => $event_id]);
        if ($resp && !empty($resp['results'])) {
            $odds_data = $resp['results'];
        }
        // Fallback: try /v1/bet365/event?id=X (returns full event with markets)
        if (!$odds_data) {
            $resp2 = betsapi_get('/v1/bet365/event', ['id' => $event_id]);
            if ($resp2 && !empty($resp2['results'])) {
                $odds_data = $resp2['results'];
            }
        }
        // Fallback: try with r_id if available
        if (!$odds_data && $match_data && !empty($match_data['r_id'])) {
            $resp3 = betsapi_get('/v1/bet365/event', ['id' => $match_data['r_id']]);
            if ($resp3 && !empty($resp3['results'])) {
                $odds_data = $resp3['results'];
            }
        }

        // Cache to DB if found
        if ($odds_data && $db_connected && $match_data) {
            try {
                $match_data['odds'] = $odds_data;
                $pdo->prepare("UPDATE sb_matches SET raw_json=? WHERE id=?")->execute([json_encode($match_data), $event_id]);
            } catch (Exception $e) {}
        }
    }

    echo json_encode(['success' => 1, 'event_id' => $event_id, 'odds' => $odds_data]);
    exit;
}

// ═══ DB STATUS (debug) ════════════════════════════════════════════════════
if ($action === 'status') {
    $info = ['db_connected' => $db_connected];
    if ($db_connected) {
        try {
            $info['counts'] = [];
            $stmt = $pdo->query("SELECT sport_id, status, COUNT(*) as cnt FROM sb_matches GROUP BY sport_id, status");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $info['counts'][] = $row;
            }
            $info['total'] = $pdo->query("SELECT COUNT(*) FROM sb_matches")->fetchColumn();
        } catch (Exception $e) {
            $info['error'] = $e->getMessage();
        }
    }
    echo json_encode(['success' => 1, 'info' => $info]);
    exit;
}

// ═══ MATCH DETAIL — live markets from BetsAPI ════════════════════════════
if ($action === 'match_detail') {
    $match_id = trim($_GET['match_id'] ?? '');
    if (!$match_id) { echo json_encode(['success' => 0, 'error' => 'match_id required']); exit; }

    $match_data = null;
    if ($db_connected) {
        try {
            $st = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=?");
            $st->execute([$match_id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r) $match_data = json_decode($r['raw_json'], true);
        } catch (Exception $e) {}
    }

    $markets   = [];
    $has_live  = false;
    $event_raw = null;

    if ($match_data) {
        // 1. Try live event via Bet365 FI (r_id)
        $fi = $match_data['r_id'] ?? null;
        if ($fi) {
            $ev = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
            if (!empty($ev)) {
                $event_raw = is_array($ev[0] ?? null) ? $ev[0] : (is_array($ev) ? $ev : null);
                $has_live  = true;
            }
        }
        // 2. Fallback: try by match id (upcoming prematch)
        if (empty($event_raw)) {
            $ev2 = betsapi_get('/v1/bet365/event', ['id' => $match_id]);
            if (!empty($ev2)) $event_raw = is_array($ev2[0] ?? null) ? $ev2[0] : $ev2;
        }
        // 3. Try prematch odds endpoint
        if (empty($event_raw)) {
            $ev3 = betsapi_get('/v1/bet365/prematch_odds', ['event_id' => $match_id]);
            if (!empty($ev3['results'])) $event_raw = $ev3['results'];
        }
    }

    if ($event_raw) {
        $markets = md_parse_markets($event_raw);
    }

    // Fallback: build synthetic markets from stored live_odds
    if (empty($markets) && $match_data) {
        $markets = md_synthetic_markets($match_data);
    }

    echo json_encode([
        'success'  => 1,
        'match'    => $match_data,
        'markets'  => $markets,
        'has_live' => $has_live,
    ]);
    exit;
}

/* ── Parse BetsAPI flat event array into structured markets ── */
function md_parse_markets($event_arr) {
    if (!is_array($event_arr)) return [];
    $markets = []; $cur = null;

    // Flatten one level if nested
    $items = isset($event_arr[0]) && is_array($event_arr[0]) ? $event_arr[0] : $event_arr;

    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';
        $na   = $item['NA']   ?? $item['name'] ?? $item['N']  ?? '';

        if (in_array($type, ['MarketHeader','MH','Market'])) {
            if ($cur && !empty($cur['selections'])) $markets[] = $cur;
            $cur = ['id' => $item['ID'] ?? $item['id'] ?? uniqid(), 'name' => $na, 'selections' => []];
        }

        if ($type === 'PA' && $cur !== null) {
            $od  = $item['OD'] ?? $item['od'] ?? '';
            $dec = md_frac_to_dec($od);
            if (!$dec || $dec < 1.01) continue;
            $cur['selections'][] = [
                'id'       => $item['ID'] ?? $item['id'] ?? uniqid(),
                'name'     => $na ?: ($item['N2'] ?? ''),
                'odds'     => $dec,
                'handicap' => $item['HD'] ?? null,
            ];
        }

        // sp market from main.sp structure (prematch)
        if ($type === '' && isset($item['odds']) && is_array($item['odds'])) {
            $mktName = $item['name'] ?? '';
            $sels = [];
            foreach ($item['odds'] as $o) {
                $od  = $o['odds'] ?? $o['OD'] ?? '';
                $dec = md_frac_to_dec($od);
                if (!$dec || $dec < 1.01) continue;
                $sels[] = [
                    'id'       => $o['id']   ?? uniqid(),
                    'name'     => $o['name'] ?? $o['N2'] ?? '',
                    'odds'     => $dec,
                    'handicap' => $o['handicap'] ?? null,
                ];
            }
            if ($sels) $markets[] = ['id' => uniqid(), 'name' => $mktName, 'selections' => $sels];
        }
    }
    if ($cur && !empty($cur['selections'])) $markets[] = $cur;

    // Also parse sp.* sub-markets from prematch structure
    if (isset($event_arr['main']['sp'])) {
        foreach ($event_arr['main']['sp'] as $sp) {
            if (!is_array($sp)) continue;
            $sels = [];
            foreach ($sp['odds'] ?? [] as $o) {
                $od  = $o['odds'] ?? $o['OD'] ?? '';
                $dec = md_frac_to_dec($od);
                if (!$dec || $dec < 1.01) continue;
                $sels[] = ['id' => $o['id'] ?? uniqid(), 'name' => $o['name'] ?? '', 'odds' => $dec, 'handicap' => $o['handicap'] ?? null];
            }
            if ($sels) $markets[] = ['id' => $sp['id'] ?? uniqid(), 'name' => $sp['name'] ?? '', 'selections' => $sels];
        }
    }

    return array_slice($markets, 0, 60); // Allow up to 60 markets for full display
}

/* ── Synthetic markets from live_odds stored in DB (full fcbet216 set) ── */
function md_synthetic_markets($m) {
    $o = $m['live_odds'] ?? null;
    $markets = [];
    if (!$o || !isset($o['h'])) return $markets;

    $h = (float)$o['h']; $x = (float)($o['x'] ?? 3.5); $a = (float)($o['a'] ?? 3.0);
    $seed = abs(intval(preg_replace('/\D/', '', $m['id'] ?? '0')) % 999983);
    $srand = function($off, $min, $max) use ($seed) {
        $v = abs(sin(($seed + $off) * 9301 + 49297) * 233280);
        return round($min + ($v - floor($v)) * ($max - $min), 2);
    };

    // 1x2
    if ($h > 1.01 && $x > 1.01 && $a > 1.01) {
        $markets[] = ['id'=>'1x2','name'=>'1x2','selections'=>[
            ['id'=>'1','name'=>'1','odds'=>$h],['id'=>'X','name'=>'X','odds'=>$x],['id'=>'2','name'=>'2','odds'=>$a],
        ]];
    }

    // Total (Over/Under)
    $line = (float)($o['ou_line'] ?? 2.5);
    $ov = (float)($o['ou_over'] ?? $srand(1,1.6,2.3));
    $un = (float)($o['ou_under'] ?? $srand(2,1.6,2.3));
    $markets[] = ['id'=>'total','name'=>'Total','selections'=>[
        ['id'=>'ov','name'=>'Plus de '.$line,'odds'=>max(1.01,$ov),'handicap'=>$line],
        ['id'=>'un','name'=>'Moins de '.$line,'odds'=>max(1.01,$un),'handicap'=>$line],
    ]];

    // Double Chance
    $markets[] = ['id'=>'dc','name'=>'Double Chance','selections'=>[
        ['id'=>'1x','name'=>'1X','odds'=>max(1.01,round($h*0.60,2))],
        ['id'=>'12','name'=>'12','odds'=>max(1.01,round(($h+$a)/2*0.75,2))],
        ['id'=>'x2','name'=>'X2','odds'=>max(1.01,round($a*0.60,2))],
    ]];

    // Les deux équipes qui marquent (BTTS)
    $by = max(1.01,$srand(3,1.4,2.2)); $bn = max(1.01,round(3.6-$by,2));
    $markets[] = ['id'=>'btts','name'=>'Les deux équipes qui marquent','selections'=>[
        ['id'=>'y','name'=>'Oui','odds'=>$by],['id'=>'n','name'=>'Non','odds'=>$bn],
    ]];

    // Pair/Impair
    $markets[] = ['id'=>'po','name'=>'Pair/Impair','selections'=>[
        ['id'=>'i','name'=>'Impair','odds'=>max(1.01,$srand(4,1.8,2.1))],
        ['id'=>'p','name'=>'Pair','odds'=>max(1.01,$srand(5,1.8,2.1))],
    ]];

    // Handicap
    $markets[] = ['id'=>'hc','name'=>'Handicap','selections'=>[
        ['id'=>'h10','name'=>'1 +0','odds'=>max(1.01,$srand(6,1.6,2.5)),'handicap'=>'+0'],
        ['id'=>'h20','name'=>'2 -0','odds'=>max(1.01,$srand(7,1.6,2.5)),'handicap'=>'-0'],
    ]];

    // Plage de buts (Goal Range)
    $markets[] = ['id'=>'gr','name'=>'Plage de buts','selections'=>[
        ['id'=>'g01','name'=>'0-1','odds'=>max(1.01,$srand(8,3.0,6.0))],
        ['id'=>'g23','name'=>'2-3','odds'=>max(1.01,$srand(9,2.0,3.5))],
        ['id'=>'g45','name'=>'4-5','odds'=>max(1.01,$srand(10,4.0,8.0))],
        ['id'=>'g6p','name'=>'6+','odds'=>max(1.01,$srand(11,12.0,26.0))],
    ]];

    // Handicap 1x2 (multiple lines)
    $hcLines = [['2:0',1.16,7.00,10.00],['1:0',1.47,4.75,4.75],['0:1',4.50,4.75,1.50],['0:2',9.00,7.00,1.18],['0:3',20.00,10.00,1.05]];
    $hc1x2 = ['id'=>'hc1x2','name'=>'Handicap 1x2','selections'=>[]];
    foreach ($hcLines as $i => $ln) {
        $hc1x2['selections'][] = ['id'=>"hc1_{$i}_1",'name'=>'1','odds'=>max(1.01,round($ln[1]*($h<2?0.9:1.1),2)),'handicap'=>'Débuts '.$ln[0]];
        $hc1x2['selections'][] = ['id'=>"hc1_{$i}_x",'name'=>'Match nul','odds'=>max(1.01,round($ln[2],2)),'handicap'=>'Débuts '.$ln[0]];
        $hc1x2['selections'][] = ['id'=>"hc1_{$i}_2",'name'=>'2','odds'=>max(1.01,round($ln[3]*($a<2?0.9:1.1),2)),'handicap'=>'Débuts '.$ln[0]];
    }
    $markets[] = $hc1x2;

    // Premier but / Dernier but
    $markets[] = ['id'=>'fb','name'=>'Premier but','selections'=>[
        ['id'=>'fb1','name'=>'1','odds'=>max(1.01,$srand(12,1.7,2.1))],
        ['id'=>'fba','name'=>'Aucun','odds'=>max(1.01,$srand(13,15.0,25.0))],
        ['id'=>'fb2','name'=>'2','odds'=>max(1.01,$srand(14,1.7,2.1))],
    ]];
    $markets[] = ['id'=>'lb','name'=>'Dernier but','selections'=>[
        ['id'=>'lb1','name'=>'1','odds'=>max(1.01,$srand(15,1.7,2.1))],
        ['id'=>'lba','name'=>'Aucun','odds'=>max(1.01,$srand(16,15.0,25.0))],
        ['id'=>'lb2','name'=>'2','odds'=>max(1.01,$srand(17,1.7,2.1))],
    ]];

    // DC Mi-temps/DC Fin de match
    $dcHtFt = ['12/12','12/1X','12/X2','1X/12','1X/1X','1X/X2','X2/12','X2/1X','X2/X2'];
    $dcSels = [];
    foreach ($dcHtFt as $i => $c) { $dcSels[] = ['id'=>"dchtft_$i",'name'=>$c,'odds'=>max(1.01,$srand(20+$i,1.5,6.0))]; }
    $markets[] = ['id'=>'dchtft','name'=>'DC Mi-temps/DC Fin de match','selections'=>$dcSels];

    // 1X2 Mi-temps / DC Fin de match
    $htdc = ['1/1X','1/12','1/X2','X/1X','X/12','X/X2','2/1X','2/12','2/X2'];
    $htdcSels = [];
    foreach ($htdc as $i => $c) { $htdcSels[] = ['id'=>"htdc_$i",'name'=>$c,'odds'=>max(1.01,$srand(30+$i,2.0,10.0))]; }
    $markets[] = ['id'=>'htdc','name'=>'1X2 Mi-temps / DC Fin de match','selections'=>$htdcSels];

    // Mi-temps/Fin de match (3x3 grid)
    $htft = [['1/1',3.5],['1/X',15.0],['1/2',31.0],['X/1',6.5],['X/X',6.5],['X/2',6.66],['2/1',26.0],['2/X',15.0],['2/2',3.75]];
    $htftSels = [];
    foreach ($htft as $i => $c) { $htftSels[] = ['id'=>"htft_$i",'name'=>$c[0],'odds'=>max(1.01,round($c[1]*$srand(40+$i,0.85,1.15),2))]; }
    $markets[] = ['id'=>'htft','name'=>'Mi-temps/Fin de match','selections'=>$htftSels];

    // Marge de victoire (Victory Margin)
    $vm = [['1 par 1',4.50],['Nul',3.33],['2 par 1',4.75],['1 par 2',7.00],['',''],['2 par 2',7.50],['1 par 3 ou +',10.0],['',''],['2 par 3 ou +',11.0]];
    $vmSels = [];
    foreach ($vm as $i => $c) { if ($c[0]) $vmSels[] = ['id'=>"vm_$i",'name'=>$c[0],'odds'=>max(1.01,round($c[1]*$srand(50+$i,0.9,1.1),2))]; }
    $markets[] = ['id'=>'vm','name'=>'Marge de victoire','selections'=>$vmSels];

    // 1 total de buts
    $tot1 = [['Plus de 0.5',1.16],['Moins de 0.5',4.40],['Plus de 1.5',1.83],['Moins de 1.5',1.83],['Plus de 2.5',3.70],['Moins de 2.5',1.25],['Plus de 3.5',8.00],['Moins de 3.5',1.06]];
    $tot1Sels = [];
    foreach ($tot1 as $i => $c) { $tot1Sels[] = ['id'=>"t1_$i",'name'=>$c[0],'odds'=>max(1.01,round($c[1]*$srand(60+$i,0.9,1.1),2))]; }
    $markets[] = ['id'=>'t1','name'=>'1 total de buts','selections'=>$tot1Sels];

    // 2 total
    $tot2 = [['Plus de 0.5',1.18],['Moins de 0.5',4.40],['Plus de 1.5',1.86],['Moins de 1.5',1.80],['Plus de 2.5',3.75],['Moins de 2.5',1.23]];
    $tot2Sels = [];
    foreach ($tot2 as $i => $c) { $tot2Sels[] = ['id'=>"t2_$i",'name'=>$c[0],'odds'=>max(1.01,round($c[1]*$srand(70+$i,0.9,1.1),2))]; }
    $markets[] = ['id'=>'t2','name'=>'2 total','selections'=>$tot2Sels];

    return $markets;
}

/* ── Fractional → Decimal conversion ── */
function md_frac_to_dec($frac) {
    $frac = trim((string)$frac);
    if (!$frac || $frac === '-') return null;
    if (strtoupper($frac) === 'EVS') return 2.00;
    if (strpos($frac, '/') !== false) {
        [$n, $d] = explode('/', $frac, 2);
        $d = floatval($d);
        return $d == 0 ? null : round(1 + floatval($n) / $d, 2);
    }
    $v = floatval($frac);
    return ($v > 0) ? round($v + 1, 2) : null;
}

// ══ LIVE REFRESH — directly fetches fresh scores/odds from BetsAPI for live match IDs ══
// Called by championship view poller to ensure live matches are always up-to-date
// POST ?action=live_refresh with JSON body: {"ids":["FI1","FI2",...]} (max 5)
if ($action === 'live_refresh') {
    $ids = [];
    $body = file_get_contents('php://input');
    if ($body) {
        $parsed = json_decode($body, true);
        $ids = array_slice(array_filter((array)($parsed['ids'] ?? [])), 0, 5);
    } else {
        $raw_ids = trim($_GET['ids'] ?? '');
        if ($raw_ids) $ids = array_slice(array_filter(explode(',', $raw_ids)), 0, 5);
    }

    $refreshed = [];
    foreach ($ids as $fi) {
        $fi = preg_replace('/[^a-zA-Z0-9_\-]/', '', $fi);
        if (!$fi) continue;

        // BetsAPI /v1/bet365/event returns stream format: results[0] = [EV,PA,PA,...] flat array
        $ev = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
        if (!$ev || empty($ev['results'])) continue;

        // results[0] is the stream array
        $stream = is_array($ev['results'][0] ?? null) ? $ev['results'][0] : [$ev['results'][0] ?? null];
        $ss = null; $timer = null; $ts_stat = '1'; // assume live (why we're refreshing)
        $h_o = $x_o = $a_o = $ov_o = $un_o = null;

        foreach ($stream as $item) {
            if (!is_array($item)) continue;
            $type = $item['type'] ?? $item['TYPE'] ?? '';
            if ($type === 'EV' || $type === 'Event') {
                $ss  = $item['SS'] ?? $item['ss'] ?? null;
                $timer = [
                    'tm' => $item['TM'] ?? $item['tm'] ?? '',
                    'ts' => $item['TS'] ?? $item['ts'] ?? '',
                    'md' => $item['MD'] ?? $item['md'] ?? '',
                ];
            }
            if ($type === 'PA') {
                $n2 = (string)($item['N2'] ?? $item['NA'] ?? '');
                $or = (string)($item['OR'] ?? '');
                // Convert fractional odds from stream (same as sync_daemon frac_to_dec)
                $raw_od = trim((string)($item['OD'] ?? ''));
                $od = null;
                if ($raw_od && $raw_od !== '-' && $raw_od !== '0') {
                    if (strtoupper($raw_od) === 'EVS') $od = 2.00;
                    elseif (strpos($raw_od, '/') !== false) {
                        [$fn, $fd] = explode('/', $raw_od, 2);
                        $fd = floatval($fd);
                        $od = ($fd > 0) ? round(1 + floatval($fn) / $fd, 2) : null;
                    } else {
                        $v = floatval($raw_od);
                        $od = ($v > 0) ? round($v + 1, 2) : null; // stream OD is fractional numerator
                    }
                }
                if (!$od || $od < 1.01) continue;
                if (($n2 === '1' || $or === '0') && !$h_o) $h_o = $od;
                if (($n2 === 'X' || $or === '1') && !$x_o) $x_o = $od;
                if (($n2 === '2' || $or === '2') && !$a_o) $a_o = $od;
                if (stripos($n2, 'over')  !== false && !$ov_o) $ov_o = $od;
                if (stripos($n2, 'under') !== false && !$un_o) $un_o = $od;
            }
        }
        $live_odds = $h_o ? ['h'=>$h_o,'x'=>$x_o,'a'=>$a_o,'ou_line'=>2.5,'ou_over'=>$ov_o,'ou_under'=>$un_o,'ts'=>time()] : null;

        // Update DB
        if ($db_connected && ($ss !== null || $live_odds)) {
            try {
                $upd_fields = 'updated_at=CURRENT_TIMESTAMP';
                $upd_vals   = [];
                if ($ss !== null) { $upd_fields .= ',score=?'; $upd_vals[] = $ss; }
                if ($timer)       { $upd_fields .= ',timer_info=?'; $upd_vals[] = json_encode($timer); }
                if ($ts_stat)     { $upd_fields .= ',status=?'; $upd_vals[] = ($ts_stat === '1' ? 'inplay' : ($ts_stat === '3' ? 'ended' : 'upcoming')); }

                if ($live_odds) {
                    // Update raw_json with new live_odds
                    $rq = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=? LIMIT 1");
                    $rq->execute([$fi]);
                    $rj = $rq->fetchColumn();
                    if ($rj) {
                        $md = json_decode($rj, true) ?: [];
                        $md['live_odds'] = $live_odds;
                        if ($ss !== null)  $md['ss'] = $ss;
                        if ($timer)        $md['timer'] = $timer;
                        if ($ts_stat)      $md['time_status'] = $ts_stat;
                        $upd_fields .= ',raw_json=?';
                        $upd_vals[] = json_encode($md);
                    }
                }
                $upd_vals[] = $fi;
                $pdo->prepare("UPDATE sb_matches SET $upd_fields WHERE id=?")->execute($upd_vals);
            } catch (Exception $e) {}
        }

        $refreshed[$fi] = ['ss' => $ss, 'timer' => $timer, 'time_status' => $ts_stat, 'live_odds' => $live_odds];
    }
    echo json_encode(['success' => 1, 'refreshed' => $refreshed]);
    exit;
}

// ═══ BG_SYNC — background prematch odds fetch (fire-and-forget from inplay action) ══
// Fetches prematch odds for matches missing OU and stores in odds_cache file.
// Called internally — not exposed publicly.
if ($action === 'bg_sync' && ($_GET['_k'] ?? '') === 'sbodds') {
    $cache_dir_bg = __DIR__ . '/cache';
    if (!is_dir($cache_dir_bg)) @mkdir($cache_dir_bg, 0755, true);
    $lock_f = $cache_dir_bg . '/bgsync_' . $sport_id . '.lock';
    $sport_cache_bg = $cache_dir_bg . '/live_' . $sport_id . '.json';
    $odds_cache_bg  = $cache_dir_bg . '/odds_' . $sport_id . '.json';

    set_time_limit(90);
    ignore_user_abort(true);

    // Load existing odds cache (don't re-fetch already cached)
    $cached_bg = file_exists($odds_cache_bg) ? (json_decode(file_get_contents($odds_cache_bg), true) ?: []) : [];

    // Load sport cache to get match list
    $sport_matches = file_exists($sport_cache_bg) ? (json_decode(file_get_contents($sport_cache_bg), true) ?: []) : [];
    if (empty($sport_matches) && $db_connected) {
        // Fallback: load from DB
        $sport_matches = db_fetch_matches($pdo, "sport_id=?", [$sport_id], 100, $sport_id);
    }

    $new_odds = [];
    $budget_start = microtime(true);
    $MAX_BUDGET = 55.0; // seconds

    foreach ($sport_matches as $bm) {
        if (microtime(true) - $budget_start > $MAX_BUDGET) break;
        $mid = $bm['id'] ?? null;
        if (!$mid) continue;
        // Skip if odds already cached and have OU (cache hit)
        $existing = $cached_bg[$mid] ?? null;
        if ($existing && isset($existing['ou_over']) && $existing['ou_over']) continue;
        // Use r_id (Bet365 FI) for the event endpoint
        $fi   = $bm['r_id'] ?? $mid;
        $pm   = null;
        $is_live_bm = (($bm['time_status'] ?? '') === '1');
        // For LIVE matches: use event FI (stream format) first — prematch doesn't return live odds
        if ($is_live_bm) {
            $or_ev = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
            if (!empty($or_ev['results'])) {
                $pm = parse_event_stream_odds($or_ev['results']);
                if (!$pm) $pm = api_parse_prematch_odds($or_ev); // fallback to sp structure
            }
        }
        // For upcoming (or if live fetch failed): use prematch endpoint
        if (!$pm) {
            $or = betsapi_get('/v1/bet365/prematch', ['FI' => $fi]);
            if (!$or || empty($or['results'])) {
                $or = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
            }
            $pm = ($or && !empty($or['results'])) ? api_parse_prematch_odds($or) : null;
            // last resort: try stream parse on the event response
            if (!$pm && $or && !empty($or['results'])) {
                $pm = parse_event_stream_odds($or['results']);
            }
        }
        if ($pm && $pm['h'] > 1.01) {
            $new_odds[(string)$mid] = $pm;
            // Also update DB
            if ($db_connected) {
                try {
                    $rq = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=? LIMIT 1");
                    $rq->execute([$mid]);
                    $raw_bg = $rq->fetchColumn();
                    if ($raw_bg) {
                        $md_bg = json_decode($raw_bg, true) ?: [];
                        $md_bg['live_odds'] = $pm;
                        $pdo->prepare("UPDATE sb_matches SET raw_json=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([json_encode($md_bg), $mid]);
                    }
                } catch (Exception $e) {}
            }
        }
    }

    // Persist updated odds cache
    // Use + operator to preserve string/integer keys (array_merge renumbers integer keys)
    if (!empty($new_odds)) {
        $merged_bg = $new_odds + $cached_bg; // new_odds takes priority over old
        if (count($merged_bg) > 600) {
            // Keep newest 500 entries
            $merged_bg = array_slice($merged_bg, -500, 500, true);
        }
        @file_put_contents($odds_cache_bg, json_encode($merged_bg));
    }

    @unlink($lock_f);
    echo json_encode(['success' => 1, 'fetched' => count($new_odds)]);
    exit;
}

// ═══ FETCH_EVENT_ODDS — async per-event odds cache (called by fire_and_forget) ══
// Fetches live odds from BetsAPI event FI endpoint and stores in ev_{id}.json
if ($action === 'fetch_event_odds') {
    $feo_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_GET['id'] ?? ''));
    if (!$feo_id) { echo json_encode(['success'=>0]); exit; }
    $feo_cache_dir = __DIR__ . '/cache';
    if (!is_dir($feo_cache_dir)) @mkdir($feo_cache_dir, 0755, true);
    $feo_file = $feo_cache_dir . '/ev_' . $feo_id . '.json';
    // Skip if cached recently
    if (file_exists($feo_file) && (time() - filemtime($feo_file)) < 30) {
        echo json_encode(['success'=>1,'cached'=>true]); exit;
    }
    ignore_user_abort(true);
    // 1. Try event FI (stream format) — works for live matches
    $feo_pm = null;
    $feo_ev = betsapi_get('/v1/bet365/event', ['FI' => $feo_id]);
    if (!empty($feo_ev['results'])) {
        $feo_pm = parse_event_stream_odds($feo_ev['results']);
        // Also try prematch structure parser as fallback
        if (!$feo_pm) $feo_pm = api_parse_prematch_odds($feo_ev);
    }
    // 2. Fallback: prematch endpoint
    if (!$feo_pm) {
        $feo_pre = betsapi_get('/v1/bet365/prematch', ['FI' => $feo_id]);
        if (!empty($feo_pre['results'])) $feo_pm = api_parse_prematch_odds($feo_pre);
    }
    if ($feo_pm && $feo_pm['h'] > 1.01) {
        @file_put_contents($feo_file, json_encode($feo_pm));
        // Update DB if available
        if ($db_connected) {
            try {
                $feo_sq = $pdo->prepare("SELECT id, raw_json FROM sb_matches WHERE id=? OR r_id=? LIMIT 1");
                $feo_sq->execute([$feo_id, $feo_id]);
                $feo_row = $feo_sq->fetch(PDO::FETCH_ASSOC);
                if ($feo_row) {
                    $feo_md = json_decode($feo_row['raw_json'], true) ?: [];
                    $feo_md['live_odds'] = $feo_pm;
                    $pdo->prepare("UPDATE sb_matches SET raw_json=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
                        ->execute([json_encode($feo_md), $feo_row['id']]);
                }
            } catch (Exception $e) {}
        }
    }
    echo json_encode(['success'=>1,'h'=>$feo_pm['h']??null]); exit;
}

echo json_encode(['success' => 0, 'error' => 'Invalid action: ' . htmlspecialchars($action)]);
