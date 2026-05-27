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
// HARD no-cache for every response — live odds / timer / score must
// never be served from any intermediate cache (browser, CDN, proxy).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
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

// --- PROVIDER ODDS ENGINE (MARGIN) ---
$global_margin_pct = 11.0;
try {
    if ($db_connected && $pdo) {
        $c_stmt = $pdo->query("SELECT setting_value FROM provider_config WHERE setting_key='global_margin_percent'");
        if ($c_row = $c_stmt->fetch(PDO::FETCH_ASSOC)) {
            $global_margin_pct = (float)$c_row['setting_value'];
        }
    }
} catch (Exception $e) {}

function apply_margin_to_odds($odds_decimal) {
    global $global_margin_pct;
    $odds = (float)$odds_decimal;
    if ($odds <= 1.05) return $odds;
    $prob = 1 / $odds;
    $new_prob = $prob * (1 + ($global_margin_pct / 100));
    if ($new_prob >= 1) return 1.01;
    return round(1 / $new_prob, 2);
}

function apply_margin_to_markets(&$markets) {
    if (!is_array($markets)) return;
    foreach ($markets as &$m) {
        if (isset($m['selections']) && is_array($m['selections'])) {
            foreach ($m['selections'] as &$sel) {
                if (isset($sel['odds'])) {
                    $sel['odds'] = apply_margin_to_odds($sel['odds']);
                }
            }
        }
        if (isset($m['h'])) $m['h'] = apply_margin_to_odds($m['h']);
        if (isset($m['x'])) $m['x'] = apply_margin_to_odds($m['x']);
        if (isset($m['a'])) $m['a'] = apply_margin_to_odds($m['a']);
    }
}
// -------------------------------------

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
                JSON_EXTRACT(raw_json,'$.live_odds') IS NOT NULL
                  OR JSON_EXTRACT(raw_json,'$.timer') IS NOT NULL,
                JSON_SET(
                  JSON_SET(
                    VALUES(raw_json),
                    '$.live_odds',
                    COALESCE(JSON_EXTRACT(raw_json,'$.live_odds'), JSON_EXTRACT(VALUES(raw_json),'$.live_odds'))
                  ),
                  '$.timer',
                  COALESCE(JSON_EXTRACT(VALUES(raw_json),'$.timer'), JSON_EXTRACT(VALUES(raw_json),'$.timer'))
                ),
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

        // Step 1: Local live cache — accept up to 5 minutes old (was 120s, too tight)
        $lc = $cache_dir . '/live_' . $sid . '.json';
        if (file_exists($lc)) {
            $lj = json_decode(@file_get_contents($lc), true);
            $local_live = is_array($lj) ? count($lj) : 0;
            if ($local_live > $live_cnt) $live_cnt = $local_live;
        }

        // Step 1b: DB inplay count (fast, reliable — always synced by daemon)
        if ($db_connected) {
            try {
                $st_il = $pdo->prepare("SELECT COUNT(*) FROM sb_matches WHERE sport_id=? AND status='inplay'");
                $st_il->execute([$sid]);
                $db_live = (int)$st_il->fetchColumn();
                if ($db_live > $live_cnt) $live_cnt = $db_live;
            } catch (Exception $e) {}
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

// ═══ Helper: normalize timer from BetsAPI (inplay stream EV or v3/event/view) ══
function _normalize_timer($src) {
    if (!$src || !is_array($src)) return null;
    $tm = $src['tm'] ?? $src['TM'] ?? '';
    $ts = $src['ts'] ?? $src['TS'] ?? '';
    $md = $src['md'] ?? $src['MD'] ?? '';
    if ($tm === '' && $ts === '' && $md === '') return null;
    return ['tm' => (string)$tm, 'ts' => (string)$ts, 'md' => (string)$md];
}

// Validate one side of a stat pair: must look like a small non-negative
// integer (BetsAPI sometimes leaks timestamps like "20260524011600" into
// red_cards / yellow_cards arrays — those must be rejected).
function _is_valid_stat_value($s) {
    if ($s === null) return false;
    $s = trim((string)$s);
    if ($s === '') return true; // empty cell is OK, treated as 0
    if (!preg_match('/^-?\d+$/', $s)) return false;
    $n = (int)$s;
    return $n >= 0 && $n <= 999;
}

// Parse "home,away" or array/object into [home, away] strings.
// Returns null if values look like timestamps / non-counts.
function _pair_stat_val($v) {
    if ($v === null || $v === '') return null;
    if (is_string($v) && strpos($v, ',') !== false) {
        $p = array_map('trim', explode(',', $v, 2));
        $h = (string)($p[0] ?? '0'); $a = (string)($p[1] ?? '0');
        if (!_is_valid_stat_value($h) || !_is_valid_stat_value($a)) return null;
        return [$h, $a];
    }
    if (is_array($v)) {
        if (array_key_exists(0, $v) || array_key_exists(1, $v) || isset($v['home']) || isset($v['away'])) {
            $h = (string)($v[0] ?? $v['home'] ?? '0');
            $a = (string)($v[1] ?? $v['away'] ?? '0');
            if (!_is_valid_stat_value($h) || !_is_valid_stat_value($a)) return null;
            return [$h, $a];
        }
    }
    if (is_numeric($v)) {
        if (!_is_valid_stat_value((string)$v)) return null;
        return [(string)$v, '0'];
    }
    return null;
}

// Parse bet365 event stream ST timeline records and count corners /
// yellow cards / red cards per team. BetsAPI v3 often returns these as
// event timestamps rather than counts, so this LA-text parser is the
// most reliable source for football live counters.
function _parse_stream_timeline_stats($stream, $home_name = '', $away_name = '') {
    if (!is_array($stream)) return null;

    $home_lc = strtolower(trim((string)$home_name));
    $away_lc = strtolower(trim((string)$away_name));

    // Helper: match a team name from a LA snippet to home or away.
    $team_side = function($team) use ($home_lc, $away_lc) {
        $t = strtolower(trim((string)$team));
        if ($t === '') return null;
        if ($home_lc && (strpos($home_lc, $t) !== false || strpos($t, $home_lc) !== false)) return 0;
        if ($away_lc && (strpos($away_lc, $t) !== false || strpos($t, $away_lc) !== false)) return 1;
        // Word-level overlap (covers truncations like "Xelaju" vs "Xelaju MC")
        $tw = preg_split('/\s+/', $t);
        foreach ([$home_lc => 0, $away_lc => 1] as $cand => $idx) {
            if (!$cand) continue;
            foreach ($tw as $w) {
                if (strlen($w) >= 4 && strpos($cand, $w) !== false) return $idx;
            }
        }
        return null;
    };

    $corners      = [0, 0];
    $yellow_cards = [0, 0];
    $red_cards    = [0, 0];

    foreach ($stream as $row) {
        if (!is_array($row)) continue;
        if (($row['type'] ?? '') !== 'ST') continue;
        $la = (string)($row['LA'] ?? '');
        if ($la === '') continue;

        // Corner: "88' - 11th Corner - Xelaju"  (skip "Race to N Corners")
        if (preg_match('/-\s*\d+(?:st|nd|rd|th)?\s+Corner\s*-\s*(.+?)\s*$/i', $la, $m)
            && stripos($la, 'Race to') === false) {
            $idx = $team_side($m[1]);
            if ($idx !== null) $corners[$idx]++;
            continue;
        }

        // Yellow card: "45' ~ 5th Yellow Card ~  ~(Xelaju)"
        if (preg_match('/\d+(?:st|nd|rd|th)?\s+Yellow Card[^()]*\(([^)]+)\)/i', $la, $m)) {
            $idx = $team_side($m[1]);
            if ($idx !== null) $yellow_cards[$idx]++;
            continue;
        }

        // Red card: "62' ~ 1st Red Card ~  ~(CSD Municipal)"
        if (preg_match('/\d+(?:st|nd|rd|th)?\s+Red Card[^()]*\(([^)]+)\)/i', $la, $m)) {
            $idx = $team_side($m[1]);
            if ($idx !== null) $red_cards[$idx]++;
            continue;
        }
    }

    $out = [];
    if ($corners[0] || $corners[1])           $out['corners']      = [(string)$corners[0],      (string)$corners[1]];
    if ($yellow_cards[0] || $yellow_cards[1]) $out['yellow_cards'] = [(string)$yellow_cards[0], (string)$yellow_cards[1]];
    if ($red_cards[0] || $red_cards[1])       $out['red_cards']    = [(string)$red_cards[0],    (string)$red_cards[1]];
    return $out ?: null;
}

// Merge two stats arrays — preferring whichever side has data per key.
function _merge_stats($a, $b) {
    if (!$a) return $b ?: null;
    if (!$b) return $a;
    foreach ($b as $k => $v) {
        if (!isset($a[$k]) || $a[$k] === null || $a[$k] === '') $a[$k] = $v;
    }
    return $a;
}

// Bet365 inplay EV row — S1..S8 map to soccer counters (fcbet216 stat bar order).
function _parse_stream_ev_stats($ev) {
    if (!$ev || !is_array($ev)) return null;

    $map = [
        'S1' => 'on_target',
        'S2' => 'off_target',
        'S3' => 'attacks',
        'S4' => 'dangerous_attacks',
        'S6' => 'corners',
        'S7' => 'yellow_cards',
        'S8' => 'red_cards',
    ];
    $out = [];
    foreach ($map as $sk => $canonical) {
        if (!isset($ev[$sk]) || $ev[$sk] === '') continue;
        $pair = _pair_stat_val($ev[$sk]);
        if ($pair) $out[$canonical] = $pair;
    }
    return $out ?: null;
}

function _parse_bet365_event_stats($results) {
    if (!$results || !is_array($results)) return null;
    $stream = is_array($results[0] ?? null) ? $results[0] : $results;
    foreach ($stream as $item) {
        if (!is_array($item)) continue;
        if (($item['type'] ?? $item['TYPE'] ?? '') === 'EV') {
            $st = _parse_stream_ev_stats($item);
            if ($st) return $st;
        }
    }
    return null;
}

// Normalize BetsAPI stats into { yellow_cards:[home,away], ... } arrays.
function _normalize_stats($raw) {
    if (!$raw || !is_array($raw)) return null;

    $aliases = [
        'yellow_cards'      => ['yellow_cards','yellowcards','yellowcard','yellow card','yellow card(s)'],
        'red_cards'         => ['red_cards','redcards','redcard','red card','red card(s)'],
        'corners'           => ['corners','corner','corner_kicks','corner kicks'],
        'on_target'         => ['on_target','on target','shots_on_target','shots on target','shots on goal','goal attempts','goals on target'],
        'off_target'        => ['off_target','off target','shots_off_target','shots off target'],
        'dangerous_attacks' => ['dangerous_attacks','dangerous attacks'],
        'attacks'           => ['attacks','attack'],
    ];

    // List-of-objects format: [{name:"Corners", home:"2", away:"1"}, ...]
    if (isset($raw[0]) && is_array($raw[0]) && (isset($raw[0]['name']) || isset($raw[0]['NA']))) {
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $label = strtolower(trim((string)($row['name'] ?? $row['NA'] ?? '')));
            $pair  = _pair_stat_val($row['value'] ?? $row['VA'] ?? [
                $row['home'] ?? $row['T1'] ?? null,
                $row['away'] ?? $row['T2'] ?? null,
            ]);
            if (!$pair) continue;
            foreach ($aliases as $canonical => $keys) {
                foreach ($keys as $k) {
                    if (strpos($label, str_replace('_', ' ', $k)) !== false || $label === $k) {
                        $out[$canonical] = $pair;
                        break 2;
                    }
                }
            }
        }
        if ($out) return $out;
    }

    $out = [];
    $lower = [];
    foreach ($raw as $k => $v) {
        if (is_string($k)) $lower[strtolower($k)] = $v;
    }
    foreach ($aliases as $canonical => $keys) {
        foreach ($keys as $k) {
            $lk = strtolower($k);
            if (!isset($lower[$lk]) && !isset($raw[$k])) continue;
            $v = $lower[$lk] ?? $raw[$k];
            $pair = _pair_stat_val($v);
            if ($pair) {
                $out[$canonical] = $pair;
                break;
            }
        }
    }
    return $out ?: null;
}

// Unified stats fetch — v3 / v1 first (already-aggregated counts), then
// bet365 stream timeline (ST records' LA text) for the most reliable
// per-team corner/card counts, then bet365 EV S1..S8 (rare on most feeds).
function _fetch_event_stats($event_id, $fi = null) {
    $stats = null;
    $home_name = ''; $away_name = '';

    $v3 = betsapi_get('/v3/event/view', ['event_id' => $event_id, 'source' => 'bet365']);
    if (!empty($v3['results'][0])) {
        $r0 = $v3['results'][0];
        $home_name = $r0['home']['name'] ?? '';
        $away_name = $r0['away']['name'] ?? '';
        if (!empty($r0['stats'])) {
            $stats = _normalize_stats($r0['stats']);
        }
    }

    if (!$stats) {
        $v1 = betsapi_get('/v1/event/view', ['event_id' => $event_id]);
        if (!empty($v1['results'][0]['stats'])) {
            $stats = _normalize_stats($v1['results'][0]['stats']);
        }
        if (!$home_name && !empty($v1['results'][0]['home']['name'])) $home_name = $v1['results'][0]['home']['name'];
        if (!$away_name && !empty($v1['results'][0]['away']['name'])) $away_name = $v1['results'][0]['away']['name'];
    }

    if ($fi) {
        $ev = betsapi_get('/v1/bet365/event', ['FI' => $fi, 'stats' => '1']);
        if (!empty($ev['results'])) {
            $stream = is_array($ev['results'][0]) ? $ev['results'][0] : $ev['results'];

            if (!$home_name || !$away_name) {
                foreach ($stream as $row) {
                    if (!is_array($row) || ($row['type'] ?? '') !== 'EV') continue;
                    $na = (string)($row['NA'] ?? '');
                    if (preg_match('/^(.+?)\s+v\s+(.+)$/', $na, $mm)) {
                        if (!$home_name) $home_name = trim($mm[1]);
                        if (!$away_name) $away_name = trim($mm[2]);
                    }
                    break;
                }
            }

            // Timeline first — counts EVERY card/corner event in
            // the match LA timeline, most reliable for football.
            $tl = _parse_stream_timeline_stats($stream, $home_name, $away_name);
            $ev_stat_row = _parse_bet365_event_stats($ev['results']);
            $built = $tl ?: [];
            $built = _merge_stats($built, $ev_stat_row ?: []);
            // v3 / v1 stats only fill in missing keys
            if ($stats) $built = _merge_stats($built, $stats);
            $stats = $built ?: $stats;
        }
    }

    return $stats;
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
            $parts = explode('/', $v, 2);
            $fn = $parts[0] ?? 0;
            $fd = $parts[1] ?? 0;
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
        $parts = explode('/', $raw, 2);
        $fn = $parts[0] ?? 0;
        $fd = $parts[1] ?? 0;
        $fd=floatval($fd);
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
        // Extract OU line from market name (MA = inplay stream, MG = event stream)
        if ($type === 'MA' || $type === 'MG') {
            $mkt = $item['NA'] ?? $item['N2'] ?? '';
            if (preg_match('/over.under\s*(\d+\.?\d*)/i', $mkt, $mat)
             || preg_match('/total[^0-9]*(\d+\.?\d*)/i', $mkt, $mat)) {
                // Only take the line if we haven't captured OU odds yet
                // (so the FIRST / main OU market wins over secondary ones)
                if (!$ov_o && !$un_o) $curr_ou_line = (float)$mat[1];
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
    $is_football  = ((int)$sport_id === 1);
    // ── Cache TTLs ─────────────────────────────────────────────
    // Use aggressive 2s TTL always — when daemon runs it writes every 2s
    // so cache is always fresh. When daemon is off, api.php self-refreshes
    // ── Daemon-aware cache strategy ────────────────────────────────────────
    // tick_live.php is the ONLY process that should call BetsAPI for live
    // data. api.php must read from the files tick_live writes — never call
    // BetsAPI itself for inplay / stream data, regardless of cache age.
    //
    // Why: if api.php also calls BetsAPI, every user page-load burns quota
    // on top of tick_live. With 1s frontend polling and many users, this
    // exhausts the quota in minutes and causes the 429 death-spiral.
    //
    // Rule: when tick_live's lock file exists (daemon is running or was
    // running recently), NEVER call BetsAPI for inplay/stream — serve from
    // cache file however old it is (tick_live will update it shortly).
    // Only fall back to a direct BetsAPI call when no lock file exists AND
    // no cache file exists (truly cold start with no daemon).
    $tick_lock    = $cache_dir . '/tick_live.lock';
    $daemon_alive = file_exists($tick_lock); // lock exists = daemon ever ran
    // How old is the cache allowed to be before we show "no matches"?
    // When daemon is alive: infinite — tick_live will refresh it.
    // When daemon is dead and no cache: 0 — must fetch live.
    $cache_max_age = $daemon_alive ? PHP_INT_MAX : 30;
    // Volume Plan: unlimited calls — tighten TTLs for maximum freshness.
    $cache_ttl     = 1;
    $ev_cache_ttl  = $is_football ? 1 : 2;
    $ev_stale_ttl  = 1;
    $odds_bg_ttl   = $is_football ? 4 : 6;
    $ev_refresh_cap = 50;

    // ── Step 1: Get inplay_filter per sport ────────────────────────────────
    $sport_cache_age = file_exists($sport_cache) ? (time() - filemtime($sport_cache)) : PHP_INT_MAX;
    $use_sport_cache = ($sport_cache_age < $cache_max_age);
    if ($use_sport_cache) {
        $results = json_decode(file_get_contents($sport_cache), true) ?: [];
    } elseif (!$daemon_alive) {
        // No daemon, no usable cache — fetch directly (cold start only).
        $live_api = []; $seen_ids = [];
        for ($pg=1; true; $pg++) {
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
            $pager = $resp['pager'] ?? [];
            if (($pager['page'] ?? 1) >= ($pager['total_pages'] ?? 1)) break;
        }
        if (!empty($live_api)) {
            $results = $live_api;
            @file_put_contents($sport_cache, json_encode($results));
            if ($db_connected) cache_to_db($pdo, $results, $sport_id);
        }
    }
    // else: daemon alive but cache missing — tick_live will write it soon,
    // just return empty for this request. No BetsAPI call.

    // ── Step 2: Get global inplay stream ───────────────────────────────────
    $stream_cache_age = file_exists($stream_cache) ? (time() - filemtime($stream_cache)) : PHP_INT_MAX;
    $use_stream_cache = ($stream_cache_age < $cache_max_age);
    if (!$use_stream_cache && !$daemon_alive) {
        // Cold start only — daemon is dead and no cache.
        $sresp = betsapi_get('/v1/bet365/inplay', []);
        if (!empty($sresp['results'][0]) && is_array($sresp['results'][0])) {
            @file_put_contents($stream_cache, json_encode($sresp['results'][0]));
        }
    }
    $stream = json_decode(@file_get_contents($stream_cache)?:'[]', true) ?: [];

    // ── Step 3: Parse odds + OU + timer from stream (same logic as sync_daemon) ─
    $stream_odds = [];       // keyed by FI (Bet365 event id)
    $stream_timers = [];     // keyed by FI
    $stream_timers_rid = []; // keyed by r_id base (194099376C1A)
    $stream_stats = [];      // keyed by FI
    $stream_stats_rid = [];  // keyed by r_id base
    $stream_ss = [];         // keyed by FI — fresh score from EV.SS
    $stream_ss_rid = [];     // keyed by r_id base — fallback when FI map fails
    $rid_to_fi   = [];       // r_id (inplay_filter) → FI (stream EV)
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
            $ev_timer = _normalize_timer($item);
            $ev_stats = _parse_stream_ev_stats($item);
            if ($ev_timer && $curr_fi) {
                $stream_timers[$curr_fi] = $ev_timer;
            }
            if ($ev_stats && $curr_fi) {
                $stream_stats[$curr_fi] = $ev_stats;
            }
            $ev_ss = $item['SS'] ?? $item['ss'] ?? null;
            if ($curr_fi && $ev_ss !== null && $ev_ss !== '') {
                $stream_ss[$curr_fi] = $ev_ss;
            }
            if (!empty($item['ID'])) {
                // ID format: "194088383C1A_1_3" → base "194088383C1A" matches r_id in inplay_filter
                $id_base = explode('_', $item['ID'])[0];
                if ($id_base) {
                    $rid_to_fi[$id_base] = $curr_fi;
                    if ($ev_timer) $stream_timers_rid[$id_base] = $ev_timer;
                    if ($ev_stats) $stream_stats_rid[$id_base] = $ev_stats;
                    if ($ev_ss !== null && $ev_ss !== '') $stream_ss_rid[$id_base] = $ev_ss;
                }
                // Also map numeric prefix (e.g. "194088383") in case r_id strips suffix
                $num_only = preg_replace('/[^0-9].*$/', '', $id_base);
                if ($num_only && $num_only !== $id_base) {
                    $rid_to_fi[$num_only] = $curr_fi;
                    if ($ev_timer) $stream_timers_rid[$num_only] = $ev_timer;
                    if ($ev_ss !== null && $ev_ss !== '') $stream_ss_rid[$num_only] = $ev_ss;
                }
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

    // ── Step 4: Batch-load live_odds + timer from DB raw_json ────────────
    $db_odds = [];
    $db_timers = [];
    $db_ss = [];
    if ($db_connected && !empty($results)) {
        $ids = array_values(array_filter(array_column($results,'id')));
        if (!empty($ids)) {
            $ph = implode(',',array_fill(0,count($ids),'?'));
            try {
                $st = $pdo->prepare("SELECT id, JSON_EXTRACT(raw_json,'$.live_odds') as lo, JSON_EXTRACT(raw_json,'$.timer') as tm, JSON_UNQUOTE(JSON_EXTRACT(raw_json,'$.ss')) as ss FROM sb_matches WHERE id IN ($ph)");
                $st->execute($ids);
                while ($row=$st->fetch(PDO::FETCH_ASSOC)) {
                    $rid_key = (string)$row['id'];
                    if ($row['lo'] && $row['lo']!=='null') $db_odds[$rid_key]=json_decode($row['lo'],true);
                    if ($row['tm'] && $row['tm']!=='null') {
                        $t = _normalize_timer(json_decode($row['tm'], true));
                        if ($t) $db_timers[$rid_key] = $t;
                    }
                    if ($row['ss'] && $row['ss']!=='null') {
                        $db_ss[$rid_key] = $row['ss'];
                    }
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

    // Trigger bg_sync if odds cache stale
    $odds_cache_age = file_exists($odds_cache_file) ? (time()-filemtime($odds_cache_file)) : 9999;
    if ($odds_cache_age > $odds_bg_ttl) {
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

        // Per-event cache (fetch_event_odds) — freshest when available
        $ev_lo = null;
        foreach ([$rid, $rid_num, $mid] as $eck) {
            if (!$eck) continue;
            $evf = $cache_dir . '/ev_' . $eck . '.json';
            if (file_exists($evf) && (time() - filemtime($evf)) < $ev_cache_ttl) {
                $ev_lo = json_decode(file_get_contents($evf), true);
                if ($ev_lo && ($ev_lo['h'] ?? 0) > 1.01) break;
                $ev_lo = null;
            }
        }

        $is_live_m = (($m['time_status'] ?? '') === '1');
        if ($is_live_m) {
            // LIVE: real-time stream wins over stale DB (critical at half-time / goal events)
            $h = $ev_lo['h'] ?? $sdat['h'] ?? $inl['h'] ?? $db_lo['h'] ?? null;
            $x = $ev_lo['x'] ?? $sdat['x'] ?? $inl['x'] ?? $db_lo['x'] ?? null;
            $a = $ev_lo['a'] ?? $sdat['a'] ?? $inl['a'] ?? $db_lo['a'] ?? null;
            $ou_line  = $ev_lo['ou_line']  ?? $sdat['ou_line']  ?? $db_lo['ou_line']  ?? 2.5;
            $ou_over  = $ev_lo['ou_over']  ?? $sdat['ou_over']  ?? $db_lo['ou_over']  ?? null;
            $ou_under = $ev_lo['ou_under'] ?? $sdat['ou_under'] ?? $db_lo['ou_under'] ?? null;
            $ts_src   = $ev_lo['ts'] ?? $sdat['ts'] ?? $inl['ts'] ?? $db_lo['ts'] ?? time();
        } else {
            $h = $db_lo['h'] ?? $sdat['h'] ?? $inl['h'] ?? null;
            $x = $db_lo['x'] ?? $sdat['x'] ?? $inl['x'] ?? null;
            $a = $db_lo['a'] ?? $sdat['a'] ?? $inl['a'] ?? null;
            $ou_line  = $db_lo['ou_line']  ?? $sdat['ou_line']  ?? 2.5;
            $ou_over  = $db_lo['ou_over']  ?? $sdat['ou_over']  ?? null;
            $ou_under = $db_lo['ou_under'] ?? $sdat['ou_under'] ?? null;
            $ts_src   = $db_lo['ts'] ?? $sdat['ts'] ?? time();
        }

        if ($h) {
            $m['live_odds'] = ['h'=>$h,'x'=>$x,'a'=>$a,
                'ou_line'=>$ou_line,'ou_over'=>$ou_over,'ou_under'=>$ou_under,'ts'=>(int)$ts_src];
        }

        // Live match clock — inplay_filter omits timer; stream EV has TM/TS/MD
        if ($is_live_m) {
            $timer = null;
            foreach ([$rid, $rid_num, $mid] as $tk) {
                if (!$tk || !isset($stream_timers_rid[$tk])) continue;
                $timer = $stream_timers_rid[$tk];
                break;
            }
            if (!$timer && $fi && isset($stream_timers[$fi])) {
                $timer = $stream_timers[$fi];
            }
            if (!$timer) {
                $timer = _normalize_timer($db_timers[$mid] ?? $db_timers[$rid] ?? null);
            }
            if (!$timer) {
                $timer = _normalize_timer($m['timer'] ?? null);
            }
            if ($timer) {
                $m['timer'] = $timer;
            }
            // Live stats — stream S1..S8, then cached/API stats
            $m_stats = null;
            foreach ([$rid, $rid_num, $mid] as $stk) {
                if (!$stk || !isset($stream_stats_rid[$stk])) continue;
                $m_stats = $stream_stats_rid[$stk];
                break;
            }
            if (!$m_stats && $fi && isset($stream_stats[$fi])) {
                $m_stats = $stream_stats[$fi];
            }
            if (!$m_stats && !empty($m['stats'])) {
                $m_stats = _normalize_stats($m['stats']);
            }
            if ($m_stats) {
                $m['stats'] = $m_stats;
            }
            // ── Live score: prefer fresh EV.SS from the bet365 stream.
            //    Lookup by FI first, then by r_id base / numeric prefix /
            //    match id — covers every format BetsAPI returns.
            $fresh_ss = null;
            if ($fi && isset($stream_ss[$fi]))     $fresh_ss = $stream_ss[$fi];
            elseif (isset($stream_ss_rid[$rid]))   $fresh_ss = $stream_ss_rid[$rid];
            elseif (isset($stream_ss_rid[$rid_num])) $fresh_ss = $stream_ss_rid[$rid_num];
            elseif (isset($stream_ss_rid[$mid]))   $fresh_ss = $stream_ss_rid[$mid];
            
            if ($fresh_ss === null || $fresh_ss === '') {
                $fresh_ss = $db_ss[$mid] ?? $db_ss[$rid] ?? null;
            }

            if ($fresh_ss !== null && $fresh_ss !== '') {
                $m['ss'] = $fresh_ss;
            }
        }
    }
    unset($m);

    // ── Step 5.5: Refresh per-event odds for ALL live matches (not only missing) ──
    // Fires async fetch when ev_ cache is stale.
    $host_ev   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_ev = parse_url($_SERVER['REQUEST_URI'] ?? '/sportsbook/api.php', PHP_URL_PATH);
    $ev_refresh_n = 0;
    foreach ($results as &$mr5) {
        if (($mr5['time_status'] ?? '0') !== '1') continue;
        $mid_ev = (string)($mr5['id'] ?? '');
        $rid_ev = (string)($mr5['r_id'] ?? $mid_ev);
        $ev_stale = true;
        foreach ([$rid_ev, $mid_ev] as $eck) {
            if (!$eck) continue;
            $ev_cache_f = $cache_dir . '/ev_' . $eck . '.json';
            if (file_exists($ev_cache_f) && (time() - filemtime($ev_cache_f)) < $ev_stale_ttl) {
                $ev_stale = false;
                break;
            }
        }
        if (!$ev_stale || $ev_refresh_n >= $ev_refresh_cap) continue;
        if ($rid_ev || $mid_ev) {
            fire_and_forget('http://' . $host_ev . $script_ev . '?action=fetch_event_odds&id=' . urlencode($rid_ev ?: $mid_ev));
            $ev_refresh_n++;
        }
    }
    unset($mr5);

    // ── Step 6: DB fallback when BetsAPI unreachable ────────────────────────
    if (empty($results) && $db_connected) {
        $results = db_fetch_matches($pdo,"sport_id=? AND status='inplay'",[$sport_id],500,$sport_id);
        if (empty($results))
            $results = db_fetch_matches($pdo,"sport_id=? AND status='upcoming'",[$sport_id],500,$sport_id);
    }

    // ── Step 6b: ALWAYS merge DB-live matches for TOP EUROPEAN LEAGUES ──
    // BetsAPI inplay_filter sometimes drops matches from major leagues
    // (paginated past p5, transient API hiccup, etc). We refuse to let
    // Premier League / Bundesliga / La Liga / Serie A / Ligue 1 /
    // Champions League / Europa / Conference / World Cup / Coupe du
    // Monde / Eredivisie / Primeira Liga / Allsvenskan etc. disappear
    // from the home-page live list when the DB has them marked inplay.
    if ($db_connected && (int)$sport_id === 1) {
        try {
            $top_league_patterns = [
                'Premier League','Bundesliga','LaLiga','La Liga','Serie A',
                'Ligue 1','Champions League','Europa League','Conference League',
                'Eredivisie','Primeira Liga','First Division A','Süper Lig',
                'FIFA World Cup','Copa Libertadores','Copa Sudamericana',
                'Saudi Professional','MLS','Brasileiro Serie A',
                'DFB Pokal','Coupe de France','FA Cup','Coppa Italia','Copa del Rey',
                'Allsvenskan','Bundesliga Play-Offs','Bundesliga II'
            ];
            $where = [];
            $params = [];
            foreach ($top_league_patterns as $p) {
                $where[] = 'league_name LIKE ?';
                $params[] = '%' . $p . '%';
            }
            $sql = "SELECT raw_json FROM sb_matches
                     WHERE sport_id=1 AND status='inplay' AND (" . implode(' OR ', $where) . ")
                     LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $existing_ids = array_flip(array_map(function($m){ return (string)($m['id'] ?? ''); }, $results));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $d = json_decode($row['raw_json'], true);
                if (!$d || empty($d['home']['name']) || empty($d['away']['name'])) continue;
                if (($d['time_status'] ?? '') === '3') continue;
                $mid = (string)($d['id'] ?? '');
                if ($mid === '' || isset($existing_ids[$mid])) continue;
                // Make sure time_status reflects inplay since the DB says so.
                $d['time_status'] = '1';
                $results[] = $d;
                $existing_ids[$mid] = true;
            }
        } catch (Exception $e) {}
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

    // ── Step 7.5: Append Upcoming Matches (for Prochainement & Points forts) ──
    // The home page 'inplay' request needs upcoming matches for the "Prochainement" 
    // and "Points forts" sections. We fetch them from the local DB.
    if ($db_connected) {
        try {
            $up_res = db_fetch_matches($pdo, "sport_id=? AND status='upcoming'", [$sport_id], 150, $sport_id);
            if (!empty($up_res)) {
                $existing_ids = array_flip(array_map(function($m){ return (string)($m['id'] ?? ''); }, $results));
                foreach ($up_res as $um) {
                    $mid = (string)($um['id'] ?? '');
                    if ($mid && !isset($existing_ids[$mid])) {
                        $results[] = $um;
                        $existing_ids[$mid] = true;
                    }
                }
            }
        } catch (Exception $e) {}
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
    $up_cache_dir = __DIR__ . '/cache';

    if ($db_connected) {
        $results = db_fetch_matches($pdo, "sport_id=? AND status!='ended'", [$sport_id], 1000, $sport_id);
    }

    // Fallback: fetch from BetsAPI (all pages up to 5) if DB empty
    if (empty($results)) {
        for ($pg = 1; true; $pg++) {
            $data = betsapi_get('/v1/bet365/upcoming', ['sport_id' => $sport_id, 'page' => $pg]);
            if (!$data || empty($data['results'])) break;
            foreach ($data['results'] as $m) {
                if (isset($m['home']['name']) && $m['home']['name'] !== '') {
                    $results[] = $m;
                }
            }
            // Stop if last page
            $pager = $data['pager'] ?? [];
            if (($pager['page'] ?? 1) >= ($pager['total_pages'] ?? 1)) break;
        }
        if (!empty($results) && $db_connected) {
            cache_to_db($pdo, $results, $sport_id);
        }
    }

    // ── Inject odds from cache (same as inplay Step 4.5) ──────────────────
    $odds_cache_up = $up_cache_dir . '/odds_' . $sport_id . '.json';
    $up_odds = file_exists($odds_cache_up)
        ? (json_decode(@file_get_contents($odds_cache_up), true) ?: [])
        : [];

    // Also check per-match ev_ cache files
    foreach ($results as &$m_up) {
        $mid_up = (string)($m_up['id'] ?? '');
        if (!$mid_up) continue;
        // Already has odds from DB live_odds field?
        if (!empty($m_up['live_odds']) && ($m_up['live_odds']['h'] ?? 0) > 1.01) continue;
        // Try the odds cache
        if (!empty($up_odds[$mid_up]) && ($up_odds[$mid_up]['h'] ?? 0) > 1.01) {
            $m_up['live_odds'] = $up_odds[$mid_up];
        } else {
            // Try per-match ev_ file
            $ev_file_up = $up_cache_dir . '/ev_' . $mid_up . '.json';
            if (file_exists($ev_file_up) && (time() - filemtime($ev_file_up)) < 120) {
                $ev_data_up = json_decode(@file_get_contents($ev_file_up), true);
                if (!empty($ev_data_up['live_odds'])) $m_up['live_odds'] = $ev_data_up['live_odds'];
            }
        }
    }
    unset($m_up);

    // ── Trigger bg_sync if odds cache stale (same threshold as inplay) ──────
    $odds_cache_age_up = file_exists($odds_cache_up) ? (time() - filemtime($odds_cache_up)) : 9999;
    if ($odds_cache_age_up > 90 && !empty($results)) {
        $lock_up = $up_cache_dir . '/bgsync_' . $sport_id . '.lock';
        $lock_age_up = file_exists($lock_up) ? (time() - filemtime($lock_up)) : 9999;
        if ($lock_age_up > 60) {
            touch($lock_up);
            $host_up  = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
            $path_up  = parse_url($_SERVER['REQUEST_URI'] ?? '/sportsbook/api.php', PHP_URL_PATH);
            fire_and_forget('http://' . $host_up . $path_up . '?action=bg_sync_upcoming&sport_id=' . $sport_id . '&_k=sbodds');
        }
    }

    // ── FAST RETURN + ASYNC prematch odds fill ──────────────────────────
    // Collect upcoming matches still missing odds; respond to client
    // FIRST so the UI paints immediately, then fetch prematch odds in
    // the background and persist them to DB so the next poll cycle
    // shows real values instead of 🔒 locks.
    $needs_async_up = [];
    foreach ($results as $m_chk) {
        if (empty($m_chk['live_odds']['h']) || (float)($m_chk['live_odds']['h'] ?? 0) < 1.01) {
            if (!empty($m_chk['id'])) $needs_async_up[] = $m_chk['id'];
        }
    }
    // Cap to avoid hammering BetsAPI per request — daemon picks up the rest.
    // Bumped from 12 to 20 so sports with many matches (basketball, tennis)
    // fill their odds within ~3-4 poll cycles instead of 8-10.
    $needs_async_up = array_slice(array_values(array_unique($needs_async_up)), 0, 20);

    $json_out_up = json_encode(['success' => 1, 'results' => $results,
                                 'odds_pending' => count($needs_async_up)]);
    header('Content-Type: application/json');
    if (function_exists('fastcgi_finish_request')) {
        echo $json_out_up;
        fastcgi_finish_request();
    } else {
        header('Content-Length: ' . strlen($json_out_up));
        header('Connection: close');
        echo $json_out_up;
        @ob_end_flush(); @flush();
    }

    if (!empty($needs_async_up) && $db_connected) {
        @set_time_limit(120);
        foreach ($needs_async_up as $fi) {
            if (!$fi) continue;
            // Per-match dedup: skip if we tried within the last 5 minutes,
            // even if we got no odds back. Prevents 5 polls × 12 matches
            // = 60 BetsAPI calls/min on a single sport.
            $pm_lock = $up_cache_dir . '/pm_' . $fi . '.lock';
            if (file_exists($pm_lock) && (time() - filemtime($pm_lock)) < 300) continue;
            @touch($pm_lock);
            $pm = null;
            $or = betsapi_get('/v1/bet365/prematch', ['FI' => $fi]);
            if ($or && !empty($or['results'])) $pm = api_parse_prematch_odds($or); if ($pm) apply_margin_to_markets($pm);
            if (!$pm) {
                $or2 = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
                if ($or2 && !empty($or2['results'])) {
                    $pm = api_parse_prematch_odds($or2);
                    if (!$pm && function_exists('parse_event_stream_odds')) {
                        $pm = parse_event_stream_odds($or2['results'] ?? []);
                    }
                }
            }
            if (!$pm || ($pm['h'] ?? 0) < 1.01) continue;
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
        }
    }
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

    // Upcoming per sport (fetch all available pages)
    foreach ($sports as $sid) {
        $pg = 1;
        while (true) {
            $data = betsapi_get('/v1/bet365/upcoming', ['sport_id' => $sid, 'page' => $pg]);
            if (!$data || empty($data['results'])) break;
            
            $saved += cache_to_db($pdo, $data['results'], $sid);
            
            $pager = $data['pager'] ?? [];
            if (($pager['page'] ?? 1) >= ($pager['total_pages'] ?? 1)) break;
            $pg++;
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
            // Uses JSON_EXTRACT so no schema change needed; sport_id index keeps it fast.
            // CAST to CHAR covers both JSON string "78" and numeric 78.
            if ($league_id_q !== '') {
                $stmt = $pdo->prepare(
                    "SELECT raw_json, start_time, status FROM sb_matches
                      WHERE sport_id=? AND status!='ended'
                        AND CAST(JSON_EXTRACT(raw_json,'$.league.id') AS CHAR) = ?
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
    // ── Step 2b: Scan live cache files for live matches ──
    // The DB cleanup in Step 0/1 can incorrectly mark a match as 'ended'
    // if its start_time is > 3 hours ago (matches in ET/very long games).
    // The tick_live daemon's cache files are the ground truth for live data —
    // scan them here to rescue any live match the DB cleanup may have missed.
    $live_file = $cache_dir . '/live_' . $sport_id . '.json';
    if (file_exists($live_file)) {
        $lj = json_decode(@file_get_contents($live_file), true);
        if (is_array($lj)) {
            // Build a set of IDs already in $results so we don't duplicate
            $found_ids = [];
            foreach ($results as $r) { if (isset($r['id'])) $found_ids[(string)$r['id']] = true; }
            foreach ($lj as $mm) {
                if (!is_array($mm)) continue;
                if ((string)($mm['time_status'] ?? '0') !== '1') continue;
                $mmid = (string)($mm['id'] ?? '');
                if ($mmid && isset($found_ids[$mmid])) continue;
                $lg_name = $mm['league']['name'] ?? '';
                $match_q = $league_q !== '' ? $league_q : $league_id_q;
                if ($match_q === '') continue;
                // Broad case-insensitive substring match on league name
                if (stripos($lg_name, $match_q) === false && stripos($match_q, $lg_name) === false) continue;
                if (empty($mm['home']['name']) || empty($mm['away']['name'])) continue;
                $results[] = $mm;
                $found_ids[$mmid] = true;
            }
        }
    }

    // ── Step 3: BetsAPI direct fallback ──
    // When the local DB has nothing for this league (typical for far-future
    // tournaments like FIFA World Cup 2026, UEFA Conference League off-
    // season, or any league we haven't started caching yet), hit BetsAPI
    // directly so we always show SOMETHING instead of "Aucun match
    // disponible pour cette ligue".
    if (empty($results)) {
        // Build the list of BetsAPI league_ids we should hit. Start with
        // whatever the client passed (might already match), then search
        // by name to discover the real BetsAPI ids.
        $api_league_ids = [];
        if ($league_id_q !== '' && ctype_digit((string)$league_id_q)) {
            $api_league_ids[] = (string)$league_id_q;
        }
        if ($league_q !== '') {
            $sr = betsapi_get('/v1/league', ['sport_id' => $sport_id, 'search' => $league_q]);
            if (!empty($sr['results']) && is_array($sr['results'])) {
                foreach ($sr['results'] as $lg) {
                    $lid = (string)($lg['id'] ?? '');
                    $lname = strtolower((string)($lg['name'] ?? ''));
                    if (!$lid) continue;
                    // Loose name match — must contain at least one significant
                    // word from the query so we don't pull random leagues.
                    $qlc = strtolower($league_q);
                    if (strpos($lname, $qlc) !== false || strpos($qlc, $lname) !== false) {
                        if (!in_array($lid, $api_league_ids, true)) $api_league_ids[] = $lid;
                    }
                }
            }
        }
        // Fetch upcoming for each candidate league_id (up to 3 pages each).
        foreach ($api_league_ids as $lid) {
            $page = 1;
            while ($page <= 3) {
                $up = betsapi_get('/v3/events/upcoming', [
                    'sport_id'  => $sport_id,
                    'league_id' => $lid,
                    'page'      => $page,
                ]);
                $upr = $up['results'] ?? [];
                if (!is_array($upr) || empty($upr)) break;
                foreach ($upr as $m) {
                    if (!is_array($m)) continue;
                    if (empty($m['home']['name']) || empty($m['away']['name'])) continue;
                    if (($m['time_status'] ?? '0') === '3') continue;
                    $results[] = $m;
                }
                if (count($upr) < 50) break;
                $page++;
            }
            if (!empty($results)) break;   // one match found is enough
        }
    }
    // NOTE: No fallback to "all sport matches" — shows empty championship if no matches found,
    // which is correct behaviour (avoids LaLiga click showing Premier League data)

    // ── FAST RETURN + ASYNC odds fill ──────────────────────────────────────
    // 1. Collect matches still missing odds (upcoming only)
    $needs_async = [];
    foreach ($results as $m) {
        if (empty($m['live_odds']['h']) && ($m['time_status'] ?? '0') !== '1') {
            $needs_async[] = $m['id'] ?? null;
        }
    }
    $needs_async = array_values(array_filter($needs_async));

    // 2. Respond to client IMMEDIATELY — do NOT block for odds fetches
    $json_out = json_encode(['success' => 1, 'results' => $results, 'league_filter' => $league_q,
                             'odds_pending' => count($needs_async)]);
    header('Content-Type: application/json');

    // Close HTTP connection first, then fetch odds in background so client gets instant response
    if (function_exists('fastcgi_finish_request')) {
        echo $json_out;
        fastcgi_finish_request();
    } else {
        // Apache fallback: set Content-Length so client knows when to close
        header('Content-Length: ' . strlen($json_out));
        header('Connection: close');
        echo $json_out;
        @ob_end_flush(); @flush();
    }

    // ── Background: fetch prematch odds for missing matches ───────────────
    // Runs AFTER response is sent — client does not wait for this
    if (!empty($needs_async) && $db_connected) {
        @set_time_limit(120);
        // Build id → r_id map for correct Bet365 FI lookups
        $id_to_rid = [];
        foreach ($results as $m) {
            $mid = $m['id'] ?? '';
            $rid = $m['r_id'] ?? $mid;
            if ($mid) $id_to_rid[$mid] = $rid ?: $mid;
        }
        foreach ($needs_async as $mid) {
            if (!$mid) continue;
            $fi = $id_to_rid[$mid] ?? $mid; // use r_id (real Bet365 FI) if available
            $pm = null;
            // Try prematch endpoint (uses Bet365 FI)
            $or = betsapi_get('/v1/bet365/prematch', ['FI' => $fi]);
            if ($or && !empty($or['results'])) $pm = api_parse_prematch_odds($or); if ($pm) apply_margin_to_markets($pm);
            // Fallback: event stream
            if (!$pm) {
                $or2 = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
                if ($or2 && !empty($or2['results'])) {
                    $pm = api_parse_prematch_odds($or2);
                    if (!$pm) $pm = parse_event_stream_odds($or2['results'] ?? []);
                }
            }
            // Also try with the DB id directly (some events use numeric id as FI)
            if (!$pm && $fi !== $mid) {
                $or3 = betsapi_get('/v1/bet365/prematch', ['FI' => $mid]);
                if ($or3 && !empty($or3['results'])) $pm = api_parse_prematch_odds($or3);
            }
            if (!$pm || ($pm['h'] ?? 0) < 1.01) continue;
            // Store in DB so next poll returns with odds
            try {
                $rj = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=?");
                $rj->execute([$mid]);
                $raw = $rj->fetchColumn();
                if ($raw) {
                    $mdata = json_decode($raw, true);
                    $mdata['live_odds'] = $pm;
                    $pdo->prepare("UPDATE sb_matches SET raw_json=? WHERE id=?")->execute([json_encode($mdata), $mid]);
                }
            } catch (Exception $e) {}
        }
    }
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

// ═══ MATCH LIVE — lightweight poll for score/timer/odds (match detail page) ═══
if ($action === 'match_live') {
    $match_id = trim($_GET['match_id'] ?? '');
    if (!$match_id) { echo json_encode(['success' => 0, 'error' => 'match_id required']); exit; }

    // ── PER-MATCH RESPONSE CACHE ─────────────────────────────────────────────
    //   match_live used to fire 3 fresh BetsAPI calls (/v3/event/view,
    //   _fetch_event_stats, /v1/bet365/event) on every single poll —
    //   600-2400ms per request, multiplied by every user on the page.
    //
    //   We now coalesce identical requests inside a 1-second window
    //   into a single backend call. The first request in a window does
    //   the work and writes ml_<id>.json; concurrent and follow-up
    //   requests during the same second read the cached blob in <10ms.
    //
    //   Latency budget after this change (football match detail page):
    //     BetsAPI source delay :  2-10s  (upstream, immutable)
    //     Response cache TTL   :  0-1s
    //     Frontend poll        :  0-1s
    //     ── Total perceived  :  ~2-3s best case, ~12s worst case
    //
    //   To go faster than this we'd need either:
    //     (a) Sportradar / Stats Perform (paid feeds, no 2-10s delay)
    //     (b) SSE/WebSocket push from this server to the browser
    //         (eliminates the 0-1s poll component only — BetsAPI
    //          source delay still dominates).
    //
    $ml_cache_dir = __DIR__ . '/cache';
    if (!is_dir($ml_cache_dir)) @mkdir($ml_cache_dir, 0755, true);
    $ml_cache_file = $ml_cache_dir . '/ml_' . preg_replace('/[^A-Za-z0-9_-]/', '', $match_id) . '.json';
    $ml_lock_file  = $ml_cache_dir . '/ml_' . preg_replace('/[^A-Za-z0-9_-]/', '', $match_id) . '.lock';
    // Volume Plan: TTL = 1s for maximum freshness.
    // tick_live patches live_X.json every 1-2s via the stream.
    $ml_ttl_seconds = 1;

    if (file_exists($ml_cache_file) && (time() - filemtime($ml_cache_file)) < $ml_ttl_seconds) {
        header('X-SB-Cache: HIT');
        echo @file_get_contents($ml_cache_file);
        exit;
    }

    // Stampede protection: if a sibling request is already fetching for
    // this match, serve the stale cache (up to 5s old) rather than
    // piling more BetsAPI calls on. Only takes effect if a request is
    // mid-flight (lock file fresh).
    if (file_exists($ml_lock_file)) {
        $lock_age = time() - filemtime($ml_lock_file);
        if ($lock_age < 5 && file_exists($ml_cache_file)) {
            $stale_age = time() - filemtime($ml_cache_file);
            if ($stale_age < 8) { // accept stale up to 8s
                header('X-SB-Cache: STALE-WHILE-REFRESH age=' . $stale_age);
                echo @file_get_contents($ml_cache_file);
                exit;
            }
        }
    }
    @touch($ml_lock_file);
    register_shutdown_function(function() use ($ml_lock_file) { @unlink($ml_lock_file); });

    $match_data = null;
    $fi = $match_id;
    if ($db_connected) {
        try {
            $st = $pdo->prepare("SELECT id, r_id, raw_json FROM sb_matches WHERE id=? OR r_id=? LIMIT 1");
            $st->execute([$match_id, $match_id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $match_data = json_decode($r['raw_json'], true);
                $match_id = $r['id'];
                $fi = $r['r_id'] ?: $match_id;
            }
        } catch (Exception $e) {}
    }

    // Fallback: lookup match in per-sport live cache files when DB is unavailable.
    if (!$match_data) {
        foreach ([1, 13, 18, 17, 12, 78] as $cache_sid) {
            $lf = __DIR__ . '/cache/live_' . $cache_sid . '.json';
            if (!file_exists($lf)) continue;
            $lj = json_decode(@file_get_contents($lf), true) ?: [];
            foreach ($lj as $mm) {
                if (!is_array($mm)) continue;
                $mmid = (string)($mm['id'] ?? '');
                $mrid = (string)($mm['r_id'] ?? '');
                if ($mmid === $match_id || $mrid === $match_id || preg_replace('/[^0-9].*$/', '', $mrid) === $match_id) {
                    $match_data = $mm;
                    $match_id = $mmid ?: $match_id;
                    $fi = $mrid ?: $fi;
                    break 2;
                }
            }
        }
    }

    // Fresh score + timer + stats from v3 / v1 / bet365 stream
    $v3 = betsapi_get('/v3/event/view', ['event_id' => $match_id, 'source' => 'bet365']);
    if (!empty($v3['results'][0])) {
        $ev3 = $v3['results'][0];
        if ($match_data === null) $match_data = $ev3;
        foreach (['ss','timer','time_status','scores'] as $fk) {
            if (isset($ev3[$fk]) && $ev3[$fk] !== '' && $ev3[$fk] !== null) {
                $match_data[$fk] = $ev3[$fk];
            }
        }
    }
    $fresh_stats = _fetch_event_stats($match_id, $fi);
    if ($fresh_stats) {
        $match_data['stats'] = $fresh_stats;
    } elseif (!empty($match_data['stats'])) {
        // ONLY accept normalized (validated) stats — never fall back to raw,
        // because BetsAPI leaks timestamps into red_cards/yellow_cards arrays.
        $norm = _normalize_stats($match_data['stats']);
        if ($norm) $match_data['stats'] = $norm;
        else unset($match_data['stats']);
    }

    // Fresh live odds from event stream
    $ev = betsapi_get('/v1/bet365/event', ['FI' => $fi, 'stats' => '1']);
    if (!empty($ev['results'])) {
        $pm = parse_event_stream_odds($ev['results']);
        $stream = is_array($ev['results'][0]) ? $ev['results'][0] : $ev['results'];

        // Timer + score from stream EV — ALWAYS prefer this over v3
        // because v3 is a 5–60s snapshot whereas EV is the freshest
        // bet365 signal. We only fall back to v3 if EV has nothing.
        $home_n = $match_data['home']['name'] ?? '';
        $away_n = $match_data['away']['name'] ?? '';
        foreach ($stream as $sitem) {
            if (!is_array($sitem) || (($sitem['type'] ?? $sitem['TYPE'] ?? '') !== 'EV')) continue;
            $ev_ss = $sitem['SS'] ?? $sitem['ss'] ?? '';
            if ($ev_ss !== '') $match_data['ss'] = $ev_ss;
            $stimer = _normalize_timer($sitem);
            if ($stimer) {
                $ev_tm_n = (int)($stimer['tm'] ?? 0);
                $ev_md   = (string)($stimer['md'] ?? '');
                // Only replace v3's timer if EV carries a non-zero minute
                // or a period marker; otherwise keep v3 (which we already
                // wrote above) so we don't downgrade to {tm:0}.
                if ($ev_tm_n > 0 || $ev_md === '1' || $ev_md === '3'
                    || empty($match_data['timer'])) {
                    $match_data['timer'] = $stimer;
                }
            }
            if (!$home_n || !$away_n) {
                $na = (string)($sitem['NA'] ?? '');
                if (preg_match('/^(.+?)\s+v\s+(.+)$/', $na, $mm)) {
                    if (!$home_n) $home_n = trim($mm[1]);
                    if (!$away_n) $away_n = trim($mm[2]);
                }
            }
            break;
        }

        // Stream-based stats — timeline LA parser is most reliable
        // for football. Then EV.S6/S7/S8 live counters. v3's
        // _fetch_event_stats is the SLOWEST source and only used as
        // a last-resort fallback to fill in missing keys.
        $tl_stats     = _parse_stream_timeline_stats($stream, $home_n, $away_n);
        $ev_stat_row  = _parse_bet365_event_stats($ev['results']);
        $built = $tl_stats ?: [];
        $built = _merge_stats($built, $ev_stat_row ?: []);
        if ($fresh_stats) $built = _merge_stats($built, $fresh_stats);
        if (!empty($built)) {
            $match_data['stats'] = $built;
        }
        if ($pm && ($pm['h'] ?? 0) > 1.01) {
            $pm['ts'] = time();
            $match_data['live_odds'] = $pm;
            $cache_dir_ml = __DIR__ . '/cache';
            @file_put_contents($cache_dir_ml . '/ev_' . $fi . '.json', json_encode($pm));
            @file_put_contents($cache_dir_ml . '/ev_' . $match_id . '.json', json_encode($pm));
            if ($db_connected && $match_data) {
                try {
                    $pdo->prepare("UPDATE sb_matches SET raw_json=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
                        ->execute([json_encode($match_data), $match_id]);
                } catch (Exception $e) {}
            }
        }
        $markets = md_parse_markets(is_array($ev['results'][0]) ? $ev['results'][0] : $ev['results']);

        // ── Update live_odds.ou_line from the REAL markets ────────────────
        // parse_event_stream_odds only reads one OU line; the real market
        // tree may have several lines (2.5, 3.5, 4.5 …). After a goal the
        // Bet365 main line moves up, but the stale DB value keeps the old
        // anchor. Scan the parsed markets and store the lowest active line
        // (strictly above current_goals + 0.5) so buildFallbackMarkets
        // uses the correct anchor next time.
        if (!empty($markets)) {
            $cur_goals = 0;
            if (!empty($match_data['ss'])) {
                $sp = preg_split('/[-:]/', $match_data['ss']);
                if (count($sp) >= 2) $cur_goals = max(0, (int)$sp[0] + (int)$sp[1]);
            }
            $floor_line = $cur_goals + 0.5;   // minimum meaningful OU line
            $best_line  = null;
            foreach ($markets as $mkt) {
                $mn = strtolower($mkt['name'] ?? '');
                if (!preg_match('/over.under|total goals|goals over|^total$/i', $mkt['name'] ?? '')) continue;
                // Extract line from market name ("Goals Over/Under 3.5" → 3.5)
                if (!preg_match('/(\d+\.?\d*)/', $mkt['name'] ?? '', $lm)) continue;
                $line = (float)$lm[1];
                if ($line < $floor_line - 0.01) continue; // already settled
                if ($best_line === null || $line < $best_line) {
                    $best_line = $line;
                    // Also update ou_over/under from this market's selections
                    foreach ($mkt['selections'] as $sel) {
                        $sn = strtolower($sel['name'] ?? '');
                        if (strpos($sn, 'over')  !== false || strpos($sn, 'plus')  !== false) {
                            $match_data['live_odds']['ou_over'] = $sel['odds'];
                        }
                        if (strpos($sn, 'under') !== false || strpos($sn, 'moins') !== false) {
                            $match_data['live_odds']['ou_under'] = $sel['odds'];
                        }
                    }
                }
            }
            if ($best_line !== null) {
                if (!isset($match_data['live_odds']) || !is_array($match_data['live_odds'])) {
                    $match_data['live_odds'] = [];
                }
                $match_data['live_odds']['ou_line'] = $best_line;
            }
        }
    } else {
        if (empty($markets) && !$is_live_m) {
            // Fetch all markets if the match is not Live (Prematch)
            $ev_pre = betsapi_get('/v1/bet365/prematch', ['FI' => $fi ?: $match_id]);
            if (!empty($ev_pre['results'])) {
                $markets = md_parse_markets($ev_pre['results'][0]);
            } else {
                $markets = [];
            }
        } else {
            $markets = [];
        }
    }

    $ml_resp = json_encode(['success' => 1, 'match' => $match_data, 'markets' => $markets]);
    // Write to per-match response cache so the next poll within
    // $ml_ttl_seconds returns instantly without hitting BetsAPI.
    @file_put_contents($ml_cache_file, $ml_resp);
    @unlink($ml_lock_file);
    header('X-SB-Cache: MISS');
    echo $ml_resp;
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
            $ev = betsapi_get('/v1/bet365/event', ['FI' => $fi, 'stats' => '1']);
            if (!empty($ev['results'])) {
                $event_raw = is_array($ev['results'][0]) ? $ev['results'][0] : $ev['results'];
                $has_live  = true;
            }
        }
        // 2. Fallback: try PREMATCH endpoint properly! (Fetches asian, goals, half tabs)
        if (empty($event_raw)) {
            $ev2 = betsapi_get('/v1/bet365/prematch', ['FI' => $fi ?: $match_id]);
            if (!empty($ev2['results'])) {
                $event_raw = $ev2['results'][0]; 
            }
        }
    }

    // ── Step 3: v3/event/view — fresh stats, timer, scores + prematch sp odds ──
    $v3_data = betsapi_get('/v3/event/view', ['event_id' => $match_id, 'source' => 'bet365', 'odds' => '1']);
    if (!empty($v3_data['results'][0])) {
        $ev3 = $v3_data['results'][0];
        if ($match_data === null) $match_data = $ev3;
        // Merge live state into stored data so client gets fresh values
        foreach (['stats','timer','ss','scores','time_status','r_id'] as $fk) {
            if (!empty($ev3[$fk])) $match_data[$fk] = $ev3[$fk];
        }
        if (!empty($ev3['stats'])) {
            $norm = _normalize_stats($ev3['stats']);
            if ($norm) $match_data['stats'] = $norm;
        }
        // Half-time score
        if (!empty($ev3['scores'])) $match_data['scores'] = $ev3['scores'];
        // Supply team image_ids if missing
        foreach (['home','away'] as $side) {
            if (!empty($ev3[$side]['image_id']) && empty($match_data[$side]['image_id'])) {
                $match_data[$side]['image_id'] = $ev3[$side]['image_id'];
            }
        }
        // Use v3 sp markets as fallback if no live event tree
        // Pass the full sp array; md_parse_markets handles {name, odds:[...]} objects
        if (empty($event_raw) && !empty($ev3['main']['sp'])) {
            $event_raw = array_values($ev3['main']['sp']);
        }
    }

    if ($event_raw) {
        $markets = md_parse_markets($event_raw);
    }


    // Ensure stats are normalized and populated (v3 → v1 → bet365 timeline).
    // We NEVER keep raw stats — only validated counts pass through.
    if ($match_data) {
        $fi_md = $match_data['r_id'] ?? null;
        $have  = false;
        if (!empty($match_data['stats']) && is_array($match_data['stats'])) {
            $norm = _normalize_stats($match_data['stats']);
            if ($norm) { $match_data['stats'] = $norm; $have = true; }
            else { unset($match_data['stats']); }
        }
        if (!$have) {
            $fetched = _fetch_event_stats($match_id, $fi_md);
            if ($fetched) $match_data['stats'] = $fetched;
            elseif ($event_raw) {
                $stream_arr = is_array($event_raw[0] ?? null) ? $event_raw : [$event_raw];
                $home_n = $match_data['home']['name'] ?? '';
                $away_n = $match_data['away']['name'] ?? '';
                $tl = _parse_stream_timeline_stats($stream_arr, $home_n, $away_n);
                if ($tl) $match_data['stats'] = $tl;
                else {
                    $stream_st = _parse_bet365_event_stats(is_array($event_raw[0] ?? null) ? [$event_raw] : $event_raw);
                    if ($stream_st) $match_data['stats'] = $stream_st;
                }
            }
        }
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
// BetsAPI /v1/bet365/event returns a flat stream where:
//   MG = Market Group header (the market name row)
//   MA = Market Alternative header (sub-group, also treated as market)
//   PA = Participant/selection row (odds line)
/* ── Parse BetsAPI flat event array into structured markets ── */
function md_parse_markets($event_arr) {
    if (!is_array($event_arr)) return [];
    $markets = [];
    $cur_mg = '';
    $cur = null;

    // 1. Correctly un-nest the BetsAPI response
    $items = [];
    if (isset($event_arr['results'])) {
        $event_arr = $event_arr['results'];
    }
    if (isset($event_arr[0]) && is_array($event_arr[0]) && isset($event_arr[0][0])) {
        $items = $event_arr[0]; // Nested stream
    } elseif (isset($event_arr[0]) && is_array($event_arr[0])) {
        $items = $event_arr; // Flat stream or Prematch array
    } else {
        $items = [$event_arr]; // Single object
    }

    // 2. Translate Bet365 English names to French for the UI
    $translate = function($name) {
        $map = [
            'full time result' => '1x2',
            'match goals' => 'Total',
            'alternative match goals' => 'Total',
            'asian handicap' => 'Handicap',
            'double chance' => 'Double Chance',
            'both teams to score' => 'Les deux équipes qui marquent',
            'match corners' => 'Corners',
            'cards' => 'Cartons',
            'half time result' => '1ère mi-temps - 1x2',
            'half time goals' => '1ère mi-temps - total'
        ];
        $low = strtolower(trim($name));
        return isset($map[$low]) ? $map[$low] : $name;
    };

    // ── 3. Parse Flat Stream (Live Matches) ──
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';
        $na   = $item['NA']   ?? $item['name'] ?? $item['N']  ?? '';

        if ($type === 'MG' || in_array($type, ['MarketHeader', 'MH', 'Market'])) {
            if ($cur && !empty($cur['selections'])) $markets[] = $cur;
            $cur_mg = $translate($na);
            $cur = [
                'id'         => $item['ID'] ?? $item['id'] ?? uniqid(),
                'name'       => $cur_mg,
                'ma_hc'      => null,
                'selections' => [],
            ];
            continue;
        }

        if ($type === 'MA') {
            if ($cur && !empty($cur['selections'])) $markets[] = $cur;
            $mkt_name = $cur_mg;
            $hc = null;
            if ($na !== '' && $translate($na) !== $cur_mg) {
                if (is_numeric(trim($na)) || preg_match('/^[+-]?\d+(\.\d+)?$/', trim($na))) {
                    $hc = trim($na);
                } else {
                    $mkt_name = $translate($na);
                }
            }
            $cur = [
                'id'         => $item['ID'] ?? uniqid(),
                'name'       => $mkt_name,
                'ma_hc'      => $hc,
                'selections' => [],
            ];
            continue;
        }

        if ($type === 'PA' && $cur !== null) {
            $od  = $item['OD'] ?? $item['od'] ?? '';
            $dec = md_frac_to_dec($od);
            if (!$dec || $dec < 1.01) continue;

            $sel_hc = $item['HD'] ?? $item['hd'] ?? $cur['ma_hc'];
            $sel_name = $na ?: ($item['N2'] ?? '');

            // Translate selection names
            $l_sel = strtolower($sel_name);
            if ($l_sel === 'over') $sel_name = 'Plus de';
            if ($l_sel === 'under') $sel_name = 'Moins de';
            if ($l_sel === 'yes') $sel_name = 'Oui';
            if ($l_sel === 'no') $sel_name = 'Non';
            if ($l_sel === 'draw') $sel_name = 'X';

            $cur['selections'][] = [
                'id'       => $item['ID'] ?? uniqid(),
                'name'     => $sel_name,
                'odds'     => $dec,
                'handicap' => $sel_hc,
            ];
            continue;
        }
    }
    if ($cur && !empty($cur['selections'])) $markets[] = $cur;

    // ── 4. Parse Prematch Tabs (Upcoming Matches) ──
    $tabs = ['main', 'asian', 'goals', 'half', 'others', 'specials', 'schedule'];
    foreach ($items as $source) {
        if (!is_array($source)) continue;
        foreach ($tabs as $tab) {
            if (isset($source[$tab]['sp'])) {
                foreach ($source[$tab]['sp'] as $sp_key => $sp) {
                    if (!is_array($sp)) continue;
                    $sels = [];
                    foreach ($sp['odds'] ?? [] as $o) {
                        $od  = $o['odds'] ?? $o['OD'] ?? '';
                        $dec = md_frac_to_dec($od);
                        if (!$dec || $dec < 1.01) continue;
                        
                        $s_name = $o['name'] ?? $o['NA'] ?? $o['N2'] ?? '';
                        $ls_name = strtolower($s_name);
                        if ($ls_name === 'over') $s_name = 'Plus de';
                        if ($ls_name === 'under') $s_name = 'Moins de';
                        if ($ls_name === 'yes') $s_name = 'Oui';
                        if ($ls_name === 'no') $s_name = 'Non';
                        if ($ls_name === 'draw') $s_name = 'X';

                        $sels[] = [
                            'id'       => $o['id'] ?? uniqid(),
                            'name'     => $s_name,
                            'odds'     => $dec,
                            'handicap' => $o['handicap'] ?? $o['HD'] ?? null,
                        ];
                    }
                    if (!empty($sels)) {
                        $markets[] = [
                            'id'         => $sp['id'] ?? $sp_key ?? uniqid(),
                            'name'       => $translate($sp['name'] ?? ''),
                            'selections' => $sels,
                        ];
                    }
                }
            }
        }
    }

    // ── Deduplicate by name ──
    $seen = []; $deduped = [];
    foreach ($markets as $mkt) {
        $k = strtolower(trim($mkt['name']));
        if (!$k || isset($seen[$k])) continue;
        $seen[$k] = true;
        $deduped[] = $mkt;
    }

    return array_slice($deduped, 0, 150); // Return up to 150 real markets
}

/* ── Fractional → Decimal conversion ── */
function md_frac_to_dec($frac) {
    $frac = trim((string)$frac);
    if (!$frac || $frac === '-') return null;
    if (strtoupper($frac) === 'EVS') return 2.00;
    if (strpos($frac, '/') !== false) {
        $parts = explode('/', $frac, 2);
        $n = isset($parts[0]) ? floatval($parts[0]) : 0;
        $d = isset($parts[1]) ? floatval($parts[1]) : 0;
        return $d == 0 ? null : round(1 + $n / $d, 2);
    }
    $v = floatval($frac);
    return ($v > 0) ? round($v + 1, 2) : null;
}

// ══ LIVE REFRESH — FAST CACHE READ ONLY ══
// POST ?action=live_refresh with JSON body: {"ids":["matchId1",...]} (max 24)
if ($action === 'live_refresh') {
    $ids = [];
    $body = file_get_contents('php://input');
    if ($body) {
        $parsed = json_decode($body, true);
        $ids = array_slice(array_filter((array)($parsed['ids'] ?? [])), 0, 24);
    } else {
        $raw_ids = trim($_GET['ids'] ?? '');
        if ($raw_ids) $ids = array_slice(array_filter(explode(',', $raw_ids)), 0, 24);
    }

    $refreshed = [];
    $stream_cache = __DIR__ . '/cache/inplay_stream.json';
    $stream = json_decode(@file_get_contents($stream_cache)?:'[]', true) ?: [];

    // Parse stream
    $stream_odds = []; $stream_timers_rid = []; $stream_ss_rid = []; $stream_stats_rid = [];
    $rid_to_fi = [];
    $curr_fi = null; $curr_h = null; $curr_x = null; $curr_a = null;
    $curr_ou_line = 2.5; $curr_ou_over = null; $curr_ou_under = null;

    foreach ($stream as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';

        if ($type === 'EV') {
            if ($curr_fi && $curr_h) {
                $stream_odds[$curr_fi] = ['h'=>$curr_h,'x'=>$curr_x,'a'=>$curr_a,'ou_line'=>$curr_ou_line,'ou_over'=>$curr_ou_over,'ou_under'=>$curr_ou_under,'ts'=>time()];
            }
            $curr_fi = $item['OI'] ?? $item['FI'] ?? null;
            $ev_timer = _normalize_timer($item);
            $ev_stats = _parse_stream_ev_stats($item);
            $ev_ss = $item['SS'] ?? $item['ss'] ?? null;
            $id_base = !empty($item['ID']) ? explode('_', $item['ID'])[0] : null;
            if ($id_base) {
                if ($ev_timer) $stream_timers_rid[$id_base] = $ev_timer;
                if ($ev_stats) $stream_stats_rid[$id_base] = $ev_stats;
                if ($ev_ss !== null && $ev_ss !== '') $stream_ss_rid[$id_base] = $ev_ss;
                $rid_to_fi[$id_base] = $curr_fi;
            }
            $curr_h = $curr_x = $curr_a = $curr_ou_over = $curr_ou_under = null; $curr_ou_line = 2.5;
            continue;
        }
        if ($type === 'MA') {
            $curr_ma = $item['NA'] ?? $item['N2'] ?? '';
            if (preg_match('/(\d+\.?\d*)/', $curr_ma, $mat)) $curr_ou_line = (float)$mat[1];
            continue;
        }
        if ($type === 'PA') {
            $n2 = (string)($item['N2'] ?? $item['NA'] ?? '');
            $or = (string)($item['OR'] ?? '');
            $od = md_frac_to_dec($item['OD'] ?? $item['od'] ?? '');
            if (!$od || $od < 1.01) continue;
            if (($n2 === '1' || $or === '0') && !$curr_h) $curr_h = $od;
            if (($n2 === 'X' || $or === '1') && !$curr_x) $curr_x = $od;
            if (($n2 === '2' || $or === '2') && !$curr_a) $curr_a = $od;
            if (stripos($n2, 'over')  !== false && !$curr_ou_over)  $curr_ou_over = $od;
            if (stripos($n2, 'under') !== false && !$curr_ou_under) $curr_ou_under = $od;
        }
    }
    if ($curr_fi && $curr_h) {
        $stream_odds[$curr_fi] = ['h'=>$curr_h,'x'=>$curr_x,'a'=>$curr_a,'ou_line'=>$curr_ou_line,'ou_over'=>$curr_ou_over,'ou_under'=>$curr_ou_under,'ts'=>time()];
    }

    $all_live = [];
    foreach ([1, 13, 18, 17, 12, 78, 91, 36] as $sid) {
        $j = json_decode(@file_get_contents(__DIR__ . '/cache/live_' . $sid . '.json')?:'[]', true) ?: [];
        foreach ($j as $m) {
            $mid = (string)($m['id'] ?? '');
            if ($mid) $all_live[$mid] = $m;
        }
    }

    foreach ($ids as $match_id) {
        $m = $all_live[$match_id] ?? null;
        if (!$m) {
            if ($db_connected) {
                try {
                    $rq = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=? LIMIT 1");
                    $rq->execute([$match_id]);
                    $rj = $rq->fetchColumn();
                    if ($rj) $m = json_decode($rj, true);
                } catch (Exception $e) {}
            }
            if (!$m) continue;
        }
        
        $ss = $m['ss'] ?? null;
        $timer = $m['timer'] ?? null;
        $ts_stat = (string)($m['time_status'] ?? '1');
        $stats = $m['stats'] ?? null;
        $live_odds = null;
        
        $rid = (string)($m['r_id'] ?? '');
        $rid_num = $rid ? preg_replace('/[^0-9].*$/', '', $rid) : null;
        $fi = $rid_to_fi[$rid] ?? $rid_to_fi[$rid_num] ?? $rid_to_fi[$match_id] ?? null;

        // Score
        $fresh_ss = null;
        if ($fi && isset($stream_ss[$fi]))     $fresh_ss = $stream_ss[$fi];
        elseif (isset($stream_ss_rid[$rid]))   $fresh_ss = $stream_ss_rid[$rid];
        elseif (isset($stream_ss_rid[$rid_num])) $fresh_ss = $stream_ss_rid[$rid_num];
        elseif (isset($stream_ss_rid[$match_id])) $fresh_ss = $stream_ss_rid[$match_id];
        if ($fresh_ss !== null && $fresh_ss !== '') $ss = $fresh_ss;

        // Timer
        $fresh_timer = null;
        if ($fi && isset($stream_timers[$fi]))     $fresh_timer = $stream_timers[$fi];
        elseif (isset($stream_timers_rid[$rid]))   $fresh_timer = $stream_timers_rid[$rid];
        elseif (isset($stream_timers_rid[$rid_num])) $fresh_timer = $stream_timers_rid[$rid_num];
        elseif (isset($stream_timers_rid[$match_id])) $fresh_timer = $stream_timers_rid[$match_id];
        
        if ($fresh_timer) {
            $ev_tm_n = (int)($fresh_timer['tm'] ?? 0);
            $ev_md   = (string)($fresh_timer['md'] ?? '');
            if ($ev_tm_n > 0 || $ev_md === '1' || $ev_md === '3' || empty($timer)) {
                $timer = $fresh_timer;
            }
        }

        // Stats
        $fresh_stats = null;
        if ($fi && isset($stream_stats[$fi]))     $fresh_stats = $stream_stats[$fi];
        elseif (isset($stream_stats_rid[$rid]))   $fresh_stats = $stream_stats_rid[$rid];
        elseif (isset($stream_stats_rid[$rid_num])) $fresh_stats = $stream_stats_rid[$rid_num];
        elseif (isset($stream_stats_rid[$match_id])) $fresh_stats = $stream_stats_rid[$match_id];
        if ($fresh_stats) $stats = _merge_stats($fresh_stats, $stats);

        // Odds
        if ($fi && isset($stream_odds[$fi])) {
            $live_odds = $stream_odds[$fi];
            apply_margin_to_markets($live_odds);
        }

        $refreshed[$match_id] = [
            'ss' => $ss,
            'timer' => _normalize_timer($timer),
            'time_status' => $ts_stat,
            'stats' => $stats,
            'live_odds' => $live_odds
        ];
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
        $existing = $cached_bg[$mid] ?? null;
        $is_live_bm = (($bm['time_status'] ?? '') === '1');
        // Live odds MUST refresh often (half-time, goals) — never skip live matches with stale cache
        if ($is_live_bm) {
            $ex_ts = (int)($existing['ts'] ?? 0);
            if ($existing && ($ex_ts > time() - 20)) continue;
        } elseif ($existing && isset($existing['ou_over']) && $existing['ou_over']) {
            continue; // upcoming: skip if OU already cached
        }
        $fi = $bm['r_id'] ?? $mid;
        $pm = null;
        // For LIVE matches: use event FI (stream format) first — prematch doesn't return live odds
        if ($is_live_bm) {
            $or_ev = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
            if (!empty($or_ev['results'])) {
                $pm = parse_event_stream_odds($or_ev['results']); if ($pm) apply_margin_to_markets($pm);
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
                $pm = parse_event_stream_odds($or['results']); if ($pm) apply_margin_to_markets($pm);
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

// ═══ BG_SYNC_UPCOMING — async odds fetch for upcoming matches ══════════════
// Same as bg_sync but loads from DB upcoming matches (not just live cache)
if ($action === 'bg_sync_upcoming' && ($_GET['_k'] ?? '') === 'sbodds') {
    $bgu_cache_dir = __DIR__ . '/cache';
    if (!is_dir($bgu_cache_dir)) @mkdir($bgu_cache_dir, 0755, true);
    $lock_bgu       = $bgu_cache_dir . '/bgsync_' . $sport_id . '.lock';
    $odds_cache_bgu = $bgu_cache_dir . '/odds_' . $sport_id . '.json';

    set_time_limit(90);
    ignore_user_abort(true);

    $cached_bgu = file_exists($odds_cache_bgu) ? (json_decode(file_get_contents($odds_cache_bgu), true) ?: []) : [];

    // Load upcoming matches from DB (not just live cache)
    $up_matches = [];
    if ($db_connected) {
        $up_matches = db_fetch_matches($pdo, "sport_id=? AND status IN('upcoming','inplay')", [$sport_id], 200, $sport_id);
    }

    // Also load from BetsAPI if DB empty
    if (empty($up_matches)) {
        for ($pg_bgu = 1; true; $pg_bgu++) {
            $d_bgu = betsapi_get('/v1/bet365/upcoming', ['sport_id' => $sport_id, 'page' => $pg_bgu]);
            if (!$d_bgu || empty($d_bgu['results'])) break;
            foreach ($d_bgu['results'] as $mb) {
                if (isset($mb['home']['name']) && $mb['home']['name'] !== '') $up_matches[] = $mb;
            }
            $pager = $d_bgu['pager'] ?? [];
            if (($pager['page'] ?? 1) >= ($pager['total_pages'] ?? 1)) break;
        }
    }

    $new_bgu = [];
    $budget_bgu = microtime(true);
    foreach ($up_matches as $bmu) {
        if (microtime(true) - $budget_bgu > 55.0) break;
        $mid_bgu = (string)($bmu['id'] ?? '');
        if (!$mid_bgu) continue;
        // Skip if already cached
        $ex_bgu = $cached_bgu[$mid_bgu] ?? null;
        if ($ex_bgu && isset($ex_bgu['h']) && $ex_bgu['h'] > 1.01) continue;

        $fi_bgu = $bmu['r_id'] ?? $mid_bgu;
        $pm_bgu = null;
        $is_live_bgu = (($bmu['time_status'] ?? '') === '1');

        if ($is_live_bgu) {
            $or_ev_bgu = betsapi_get('/v1/bet365/event', ['FI' => $fi_bgu]);
            if (!empty($or_ev_bgu['results'])) {
                $pm_bgu = parse_event_stream_odds($or_ev_bgu['results']); if ($pm_bgu) apply_margin_to_markets($pm_bgu);
                if (!$pm_bgu) $pm_bgu = api_parse_prematch_odds($or_ev_bgu);
            }
        }
        if (!$pm_bgu) {
            $or_bgu = betsapi_get('/v1/bet365/prematch', ['FI' => $fi_bgu]);
            if (!$or_bgu || empty($or_bgu['results'])) {
                $or_bgu = betsapi_get('/v1/bet365/event', ['FI' => $fi_bgu]);
            }
            $pm_bgu = ($or_bgu && !empty($or_bgu['results'])) ? api_parse_prematch_odds($or_bgu) : null;
            if (!$pm_bgu && $or_bgu && !empty($or_bgu['results'])) {
                $pm_bgu = parse_event_stream_odds($or_bgu['results']); if ($pm_bgu) apply_margin_to_markets($pm_bgu);
            }
        }
        if ($pm_bgu && $pm_bgu['h'] > 1.01) {
            $new_bgu[$mid_bgu] = $pm_bgu;
            if ($db_connected) {
                try {
                    $rq_bgu = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=? LIMIT 1");
                    $rq_bgu->execute([$mid_bgu]);
                    $raw_bgu = $rq_bgu->fetchColumn();
                    if ($raw_bgu) {
                        $md_bgu = json_decode($raw_bgu, true) ?: [];
                        $md_bgu['live_odds'] = $pm_bgu;
                        $pdo->prepare("UPDATE sb_matches SET raw_json=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([json_encode($md_bgu), $mid_bgu]);
                    }
                } catch (Exception $e) {}
            }
        }
    }

    if (!empty($new_bgu)) {
        $merged_bgu = $new_bgu + $cached_bgu;
        if (count($merged_bgu) > 600) $merged_bgu = array_slice($merged_bgu, -500, 500, true);
        @file_put_contents($odds_cache_bgu, json_encode($merged_bgu));
    }

    @unlink($lock_bgu);
    echo json_encode(['success' => 1, 'fetched' => count($new_bgu)]);
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
    // Skip if cached recently (10s for live — was 30s, caused stale odds)
    if (file_exists($feo_file) && (time() - filemtime($feo_file)) < 10) {
        echo json_encode(['success'=>1,'cached'=>true]); exit;
    }
    ignore_user_abort(true);
    // 1. Try event FI (stream format) — works for live matches
    $feo_pm = null;
    $feo_ev = betsapi_get('/v1/bet365/event', ['FI' => $feo_id]);
    if (!empty($feo_ev['results'])) {
        $feo_pm = parse_event_stream_odds($feo_ev['results']); if ($feo_pm) apply_margin_to_markets($feo_pm);
        // Also try prematch structure parser as fallback
        if (!$feo_pm) $feo_pm = api_parse_prematch_odds($feo_ev); if ($feo_pm) apply_margin_to_markets($feo_pm);
    }
    // 2. Fallback: prematch endpoint
    if (!$feo_pm) {
        $feo_pre = betsapi_get('/v1/bet365/prematch', ['FI' => $feo_id]);
        if (!empty($feo_pre['results'])) $feo_pm = api_parse_prematch_odds($feo_pre); if ($feo_pm) apply_margin_to_markets($feo_pm);
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

// ═══ MY BETS — list user's placed bets with optional status / date filter ═══
if ($action === 'my_bets') {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success'=>0,'error'=>'Non autorisé','bets'=>[]]); exit;
    }
    if (!$db_connected) {
        echo json_encode(['success'=>0,'error'=>'DB indisponible','bets'=>[]]); exit;
    }
    $uid    = (int)$_SESSION['user_id'];
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'open';
    $from   = isset($_GET['from'])   ? trim($_GET['from'])   : '';
    $to     = isset($_GET['to'])     ? trim($_GET['to'])     : '';

    // Map UI tab names to DB status values
    // 'open'    → pending (Ouvrir)
    // 'settled' → won|lost|refunded (Calculé)
    // 'won'     → won (Gagné)
    // 'lost'    → lost (Perdu)
    // 'refunded'→ refunded (Retirer)
    $where  = ['user_id = ?'];
    $params = [$uid];
    if ($status === 'open' || $status === 'pending') {
        $where[] = "status = 'pending'";
    } elseif ($status === 'settled' || $status === 'calcule') {
        $where[] = "status IN ('won','lost','refunded')";
    } elseif ($status === 'won' || $status === 'gagne') {
        $where[] = "status = 'won'";
    } elseif ($status === 'lost' || $status === 'perdu') {
        $where[] = "status = 'lost'";
    } elseif ($status === 'refunded' || $status === 'retirer') {
        $where[] = "status = 'refunded'";
    }
    if ($from !== '') { $where[] = "created_at >= ?"; $params[] = $from . ' 00:00:00'; }
    if ($to   !== '') { $where[] = "created_at <= ?"; $params[] = $to   . ' 23:59:59'; }

    try {
        $sql = "SELECT id, amount, total_odds, potential_returns, slip, status,
                       settled_at, created_at
                FROM sportsbook_bets
                WHERE " . implode(' AND ', $where) . "
                ORDER BY created_at DESC
                LIMIT 200";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $bets = [];
        foreach ($rows as $r) {
            $slip = json_decode($r['slip'], true);
            $bets[] = [
                'id'                => (int)$r['id'],
                'amount'            => (float)$r['amount'],
                'total_odds'        => (float)$r['total_odds'],
                'potential_returns' => (float)$r['potential_returns'],
                'status'            => $r['status'],
                'created_at'        => $r['created_at'],
                'settled_at'        => $r['settled_at'],
                'slip'              => is_array($slip) ? $slip : [],
            ];
        }
        echo json_encode(['success'=>1, 'bets'=>$bets]);
    } catch (Exception $e) {
        echo json_encode(['success'=>0, 'error'=>'DB error', 'bets'=>[]]);
    }
    exit;
}

// ═══ TOP LEAGUES LIVE — league names with in-play matches (multi-sport) ═══
if ($action === 'top_leagues_live') {
    $cache_dir_tl = __DIR__ . '/cache';
    // Return {name, sport_id} pairs so the client can scope EN DIRECT
    // badges to the correct sport. Previously a Handball "1. Bundesliga"
    // would trigger the badge on the Football Bundesliga sidebar item.
    $live_pairs = [];
    $seen = [];
    $push = function($ln, $sid) use (&$live_pairs, &$seen) {
        $ln  = trim((string)$ln);
        $sid = (int)$sid;
        if ($ln === '' || $sid <= 0) return;
        $key = $sid . '|' . strtolower($ln);
        if (isset($seen[$key])) return;
        $seen[$key] = true;
        $live_pairs[] = ['name' => $ln, 'sport_id' => $sid];
    };

    foreach (glob($cache_dir_tl . '/live_*.json') ?: [] as $lf) {
        // Derive sport_id from filename (live_1.json -> 1, live_18.json -> 18 ...)
        if (!preg_match('~live_(\d+)\.json$~', $lf, $mm)) continue;
        $sid = (int)$mm[1];
        $arr = json_decode(@file_get_contents($lf), true);
        if (!is_array($arr)) continue;
        foreach ($arr as $m) {
            if ((string)($m['time_status'] ?? '') !== '1') continue;
            $push($m['league']['name'] ?? '', $m['sport_id'] ?? $sid);
        }
    }
    // Also scan the global inplay stream for any leagues not yet in a sport cache
    $stream = $cache_dir_tl . '/inplay_stream.json';
    if (file_exists($stream)) {
        $sarr = json_decode(@file_get_contents($stream), true);
        if (is_array($sarr)) {
            foreach ($sarr as $m) {
                if ((string)($m['time_status'] ?? '') !== '1') continue;
                $push($m['league']['name'] ?? '', $m['sport_id'] ?? 1);
            }
        }
    }
    // Back-compat: also return flat names so old clients keep working.
    $names_flat = array_values(array_unique(array_map(function($p) { return $p['name']; }, $live_pairs)));
    echo json_encode([
        'success' => 1,
        'live_leagues' => $names_flat,
        'live_pairs'   => $live_pairs,
    ]);
    exit;
}

echo json_encode(['success' => 0, 'error' => 'Invalid action: ' . htmlspecialchars($action)]);
