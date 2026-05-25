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

            // Timeline-based counts are the most reliable when present.
            $tl = _parse_stream_timeline_stats($stream, $home_name, $away_name);
            if ($tl) $stats = _merge_stats($tl, $stats);

            if (!$stats) {
                $stats = _parse_bet365_event_stats($ev['results']);
            }
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
    $is_football  = ((int)$sport_id === 1);
    $cache_ttl    = $is_football ? 3 : 5; // lower delay for live football
    $ev_cache_ttl = $is_football ? 6 : 10;
    $ev_stale_ttl = $is_football ? 4 : 8;
    $odds_bg_ttl  = $is_football ? 10 : 18;
    $ev_refresh_cap = $is_football ? 40 : 20;

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
            if ($curr_fi && !empty($item['SS'])) {
                $stream_ss[$curr_fi] = $item['SS'];
            }
            if (!empty($item['ID'])) {
                // ID format: "194088383C1A_1_3" → base "194088383C1A" matches r_id in inplay_filter
                $id_base = explode('_', $item['ID'])[0];
                if ($id_base) {
                    $rid_to_fi[$id_base] = $curr_fi;
                    if ($ev_timer) $stream_timers_rid[$id_base] = $ev_timer;
                    if ($ev_stats) $stream_stats_rid[$id_base] = $ev_stats;
                }
                // Also map numeric prefix (e.g. "194088383") in case r_id strips suffix
                $num_only = preg_replace('/[^0-9].*$/', '', $id_base);
                if ($num_only && $num_only !== $id_base) {
                    $rid_to_fi[$num_only] = $curr_fi;
                    if ($ev_timer) $stream_timers_rid[$num_only] = $ev_timer;
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
    if ($db_connected && !empty($results)) {
        $ids = array_values(array_filter(array_column($results,'id')));
        if (!empty($ids)) {
            $ph = implode(',',array_fill(0,count($ids),'?'));
            try {
                $st = $pdo->prepare("SELECT id, JSON_EXTRACT(raw_json,'$.live_odds') as lo, JSON_EXTRACT(raw_json,'$.timer') as tm FROM sb_matches WHERE id IN ($ph)");
                $st->execute($ids);
                while ($row=$st->fetch(PDO::FETCH_ASSOC)) {
                    $rid_key = (string)$row['id'];
                    if ($row['lo'] && $row['lo']!=='null') $db_odds[$rid_key]=json_decode($row['lo'],true);
                    if ($row['tm'] && $row['tm']!=='null') {
                        $t = _normalize_timer(json_decode($row['tm'], true));
                        if ($t) $db_timers[$rid_key] = $t;
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
            if ($fi && !empty($stream_ss[$fi])) {
                $m['ss'] = $stream_ss[$fi];
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
        for ($pg = 1; $pg <= 5; $pg++) {
            $data = betsapi_get('/v1/bet365/upcoming', ['sport_id' => $sport_id, 'page' => $pg]);
            if (!$data || empty($data['results'])) break;
            foreach ($data['results'] as $m) {
                if (isset($m['home']['name']) && $m['home']['name'] !== '') {
                    $results[] = $m;
                }
            }
            // Stop if last page
            if (!isset($data['pager']['page']) || $data['pager']['page'] >= ($data['pager']['total_pages'] ?? 1)) break;
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
            if ($or && !empty($or['results'])) $pm = api_parse_prematch_odds($or);
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
        foreach ($needs_async as $fi) {
            if (!$fi) continue;
            $pm = null;
            // Try prematch endpoint first
            $or = betsapi_get('/v1/bet365/prematch', ['FI' => $fi]);
            if ($or && !empty($or['results'])) $pm = api_parse_prematch_odds($or);
            // Fallback: event endpoint
            if (!$pm) {
                $or2 = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
                if ($or2 && !empty($or2['results'])) {
                    $pm = api_parse_prematch_odds($or2);
                    if (!$pm) $pm = parse_event_stream_odds($or2['results'] ?? []);
                }
            }
            if (!$pm || ($pm['h'] ?? 0) < 1.01) continue;
            // Store in DB so next poll returns with odds
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

        // Timer + score from stream EV
        $home_n = $match_data['home']['name'] ?? '';
        $away_n = $match_data['away']['name'] ?? '';
        foreach ($stream as $sitem) {
            if (!is_array($sitem) || ($sitem['type'] ?? '') !== 'EV') continue;
            if (!empty($sitem['SS'])) $match_data['ss'] = $sitem['SS'];
            $stimer = _normalize_timer($sitem);
            if ($stimer) $match_data['timer'] = $stimer;
            if (!$home_n || !$away_n) {
                $na = (string)($sitem['NA'] ?? '');
                if (preg_match('/^(.+?)\s+v\s+(.+)$/', $na, $mm)) {
                    if (!$home_n) $home_n = trim($mm[1]);
                    if (!$away_n) $away_n = trim($mm[2]);
                }
            }
            break;
        }

        // Stream-based stats — timeline LA parser is most reliable for football
        $tl_stats = _parse_stream_timeline_stats($stream, $home_n, $away_n);
        $stream_stats = $tl_stats ?: _parse_bet365_event_stats($ev['results']);
        if ($stream_stats) {
            $match_data['stats'] = $fresh_stats
                ? _merge_stats($stream_stats, $fresh_stats)
                : $stream_stats;
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
    } else {
        $markets = [];
    }

    echo json_encode(['success' => 1, 'match' => $match_data, 'markets' => $markets]);
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
            // results[0] is the flat stream array; results itself may already be the array
            if (!empty($ev['results'])) {
                $event_raw = is_array($ev['results'][0]) ? $ev['results'][0] : $ev['results'];
                $has_live  = true;
            }
        }
        // 2. Fallback: try by match id (upcoming prematch)
        if (empty($event_raw)) {
            $ev2 = betsapi_get('/v1/bet365/event', ['id' => $match_id]);
            if (!empty($ev2['results'])) {
                $event_raw = is_array($ev2['results'][0]) ? $ev2['results'][0] : $ev2['results'];
            }
        }
        // 3. Try prematch odds endpoint
        if (empty($event_raw)) {
            $ev3 = betsapi_get('/v1/bet365/prematch_odds', ['event_id' => $match_id]);
            if (!empty($ev3['results'])) $event_raw = $ev3['results'];
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

    // Fallback: build synthetic markets from stored live_odds
    if (empty($markets) && $match_data) {
        $markets = md_synthetic_markets($match_data);
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
function md_parse_markets($event_arr) {
    if (!is_array($event_arr)) return [];
    $markets = []; $cur = null;

    // Navigate to the flat items array.
    // Shape A: already a flat array of item objects [ {type,NA,...}, ... ]
    // Shape B: nested [ [{type,NA,...}, ...] ]  (results[0] from bet365/event)
    // Shape C: { results: [ [{...}] ] }  (full response accidentally passed)
    if (isset($event_arr['results'])) {
        $event_arr = is_array($event_arr['results'][0] ?? null)
            ? $event_arr['results'][0]
            : ($event_arr['results'] ?? []);
    }
    $items = (isset($event_arr[0]) && is_array($event_arr[0])) ? $event_arr[0] : $event_arr;

    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';
        $na   = $item['NA']   ?? $item['name'] ?? $item['N']  ?? '';

        // MG = Market Group (the actual BetsAPI type for market headers)
        // Also handle legacy MarketHeader/MH/Market codes for inplay stream
        if (in_array($type, ['MG', 'MA', 'MarketHeader', 'MH', 'Market'])) {
            if ($cur && !empty($cur['selections'])) $markets[] = $cur;
            $cur = [
                'id'         => $item['ID'] ?? $item['id'] ?? uniqid(),
                'name'       => $na,
                'selections' => [],
            ];
            continue;
        }

        // PA = Participant (selection with odds)
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
            continue;
        }

        // sp/prematch market object: {name, odds:[{name, odds},...]}
        if (isset($item['odds']) && is_array($item['odds'])) {
            $sels = [];
            foreach ($item['odds'] as $o) {
                $od  = $o['odds'] ?? $o['OD'] ?? '';
                $dec = md_frac_to_dec($od);
                if (!$dec || $dec < 1.01) continue;
                $sels[] = [
                    'id'       => $o['id']   ?? uniqid(),
                    'name'     => $o['name'] ?? $o['NA'] ?? $o['N2'] ?? '',
                    'odds'     => $dec,
                    'handicap' => $o['handicap'] ?? $o['HD'] ?? null,
                ];
            }
            if ($sels) {
                $markets[] = [
                    'id'         => $item['id'] ?? uniqid(),
                    'name'       => $item['name'] ?? $na,
                    'selections' => $sels,
                ];
            }
            continue;
        }
    }
    if ($cur && !empty($cur['selections'])) $markets[] = $cur;

    // Also parse sp.* sub-markets from prematch v3 structure
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

    // Deduplicate by name (keep first occurrence)
    $seen = []; $deduped = [];
    foreach ($markets as $mkt) {
        $k = strtolower(trim($mkt['name']));
        if (!$k || isset($seen[$k])) continue;
        $seen[$k] = true;
        $deduped[] = $mkt;
    }

    return array_slice($deduped, 0, 80); // up to 80 real markets
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
    foreach ($ids as $match_id) {
        $match_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $match_id);
        if (!$match_id) continue;

        // Resolve r_id (Bet365 FI) from DB
        $fi = $match_id;
        $db_mid = $match_id;
        if ($db_connected) {
            try {
                $lq = $pdo->prepare("SELECT id, r_id, raw_json FROM sb_matches WHERE id=? OR r_id=? LIMIT 1");
                $lq->execute([$match_id, $match_id]);
                $lr = $lq->fetch(PDO::FETCH_ASSOC);
                if ($lr) {
                    $db_mid = $lr['id'];
                    $fi = $lr['r_id'] ?: $match_id;
                }
            } catch (Exception $e) {}
        }

        // v3/event/view — fresh score + timer + stats (half-time, 2nd half)
        $v3 = betsapi_get('/v3/event/view', ['event_id' => $db_mid, 'source' => 'bet365']);
        $ss = null; $timer = null; $ts_stat = '1'; $stats = null;
        $home_nm = ''; $away_nm = '';
        if (!empty($v3['results'][0])) {
            $ev3 = $v3['results'][0];
            $ss = $ev3['ss'] ?? null;
            $timer = _normalize_timer($ev3['timer'] ?? null);
            $ts_stat = (string)($ev3['time_status'] ?? '1');
            if (!empty($ev3['stats'])) $stats = _normalize_stats($ev3['stats']);
            $home_nm = (string)($ev3['home']['name'] ?? '');
            $away_nm = (string)($ev3['away']['name'] ?? '');
        }

        // BetsAPI /v1/bet365/event — fresh live odds stream
        $ev = betsapi_get('/v1/bet365/event', ['FI' => $fi]);
        if (!$ev || empty($ev['results'])) continue;

        // ── Augment stats from the bet365 event stream. v3 often
        //    misses cards / corners; the bet365 EV row carries them as
        //    S6 / S7 / S8 (corners / yellow / red) and the ST rows
        //    carry full event timelines. Both sources merged together
        //    give the same counters fcbet216 shows on its live cards.
        $ev_stream_stats = _parse_bet365_event_stats($ev['results']);
        $timeline_stats  = _parse_stream_timeline_stats(
            is_array($ev['results'][0] ?? null) ? $ev['results'][0] : $ev['results'],
            $home_nm, $away_nm
        );
        $stats = _merge_stats($stats, $ev_stream_stats);
        $stats = _merge_stats($stats, $timeline_stats);

        $stream = is_array($ev['results'][0] ?? null) ? $ev['results'][0] : [$ev['results'][0] ?? null];
        $h_o = $x_o = $a_o = $ov_o = $un_o = null;
        $ou_line = 2.5;

        foreach ($stream as $item) {
            if (!is_array($item)) continue;
            $type = $item['type'] ?? $item['TYPE'] ?? '';
            if ($type === 'EV' || $type === 'Event') {
                if (!$ss) $ss = $item['SS'] ?? $item['ss'] ?? null;
                if (!$timer) {
                    $timer = _normalize_timer([
                        'tm' => $item['TM'] ?? $item['tm'] ?? '',
                        'ts' => $item['TS'] ?? $item['ts'] ?? '',
                        'md' => $item['MD'] ?? $item['md'] ?? '',
                    ]);
                }
            }
            if ($type === 'MA') {
                $mkt = $item['NA'] ?? $item['N2'] ?? '';
                if (preg_match('/(\d+\.?\d*)/', $mkt, $mat)) $ou_line = (float)$mat[1];
            }
            if ($type === 'PA') {
                $n2 = (string)($item['N2'] ?? $item['NA'] ?? '');
                $or = (string)($item['OR'] ?? '');
                $od = md_frac_to_dec($item['OD'] ?? $item['od'] ?? '');
                if (!$od || $od < 1.01) continue;
                if (($n2 === '1' || $or === '0') && !$h_o) $h_o = $od;
                if (($n2 === 'X' || $or === '1') && !$x_o) $x_o = $od;
                if (($n2 === '2' || $or === '2') && !$a_o) $a_o = $od;
                if (stripos($n2, 'over')  !== false && !$ov_o) $ov_o = $od;
                if (stripos($n2, 'under') !== false && !$un_o) $un_o = $od;
            }
        }
        $live_odds = $h_o ? ['h'=>$h_o,'x'=>$x_o,'a'=>$a_o,'ou_line'=>$ou_line,'ou_over'=>$ov_o,'ou_under'=>$un_o,'ts'=>time()] : null;

        // Persist to DB + ev_ cache
        if ($live_odds) {
            $ev_file = __DIR__ . '/cache/ev_' . $fi . '.json';
            @file_put_contents($ev_file, json_encode($live_odds));
            @file_put_contents(__DIR__ . '/cache/ev_' . $db_mid . '.json', json_encode($live_odds));
        }
        if ($db_connected) {
            try {
                $rq = $pdo->prepare("SELECT raw_json FROM sb_matches WHERE id=? LIMIT 1");
                $rq->execute([$db_mid]);
                $rj = $rq->fetchColumn();
                $md = $rj ? (json_decode($rj, true) ?: []) : [];
                if ($ss !== null)  $md['ss'] = $ss;
                if ($timer)        $md['timer'] = $timer;
                if ($ts_stat)      $md['time_status'] = $ts_stat;
                if ($stats)        $md['stats'] = $stats;
                if ($live_odds)    $md['live_odds'] = $live_odds;
                $pdo->prepare("UPDATE sb_matches SET score=?, timer_info=?, status=?, raw_json=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([
                        $ss,
                        $timer ? json_encode($timer) : null,
                        ($ts_stat === '1' ? 'inplay' : ($ts_stat === '3' ? 'ended' : 'upcoming')),
                        json_encode($md),
                        $db_mid
                    ]);
            } catch (Exception $e) {}
        }

        $refreshed[$db_mid] = ['ss' => $ss, 'timer' => _normalize_timer($timer), 'time_status' => $ts_stat, 'stats' => $stats, 'live_odds' => $live_odds];
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
        for ($pg_bgu = 1; $pg_bgu <= 3; $pg_bgu++) {
            $d_bgu = betsapi_get('/v1/bet365/upcoming', ['sport_id' => $sport_id, 'page' => $pg_bgu]);
            if (!$d_bgu || empty($d_bgu['results'])) break;
            foreach ($d_bgu['results'] as $mb) {
                if (isset($mb['home']['name']) && $mb['home']['name'] !== '') $up_matches[] = $mb;
            }
            if (!isset($d_bgu['pager']['page']) || $d_bgu['pager']['page'] >= ($d_bgu['pager']['total_pages'] ?? 1)) break;
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
                $pm_bgu = parse_event_stream_odds($or_ev_bgu['results']);
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
                $pm_bgu = parse_event_stream_odds($or_bgu['results']);
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

// ═══ TOP LEAGUES LIVE — league names with in-play matches (multi-sport) ═══
if ($action === 'top_leagues_live') {
    $cache_dir_tl = __DIR__ . '/cache';
    $live_names = [];
    // Scan ALL live_*.json files so every sport's live leagues are detected
    // (previously only 1/13/18 were scanned — Tennis live outside those IDs was missed).
    foreach (glob($cache_dir_tl . '/live_*.json') ?: [] as $lf) {
        $arr = json_decode(@file_get_contents($lf), true);
        if (!is_array($arr)) continue;
        foreach ($arr as $m) {
            if ((string)($m['time_status'] ?? '') !== '1') continue;
            $ln = trim($m['league']['name'] ?? '');
            if ($ln !== '') $live_names[] = $ln;
        }
    }
    // Also scan the global inplay stream for any leagues not yet in a sport cache
    $stream = $cache_dir_tl . '/inplay_stream.json';
    if (file_exists($stream)) {
        $sarr = json_decode(@file_get_contents($stream), true);
        if (is_array($sarr)) {
            foreach ($sarr as $m) {
                if ((string)($m['time_status'] ?? '') !== '1') continue;
                $ln = trim($m['league']['name'] ?? '');
                if ($ln !== '') $live_names[] = $ln;
            }
        }
    }
    echo json_encode(['success' => 1, 'live_leagues' => array_values(array_unique($live_names))]);
    exit;
}

echo json_encode(['success' => 0, 'error' => 'Invalid action: ' . htmlspecialchars($action)]);
