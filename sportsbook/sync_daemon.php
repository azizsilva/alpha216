<?php
/**
 * Sportsbook Sync Daemon — BetsAPI → DB
 * ─────────────────────────────────────────────────────────────
 *  mode=live      (every 1 min)  — live matches + real Bet365 odds
 *  mode=upcoming  (every 5 min)  — upcoming match schedules
 *  mode=full      (every hour)   — full refresh of all sports
 *
 * Windows Task Scheduler (run as Administrator):
 *   Program:   C:\wamp64\bin\php\php8.2.0\php.exe
 *   Arguments: C:\wamp64\www\public_html\sportsbook\sync_daemon.php --mode=live
 *   Trigger:   Every 1 minute
 *
 * Web trigger (localhost only):
 *   http://localhost/public_html/sportsbook/sync_daemon.php?mode=live
 *   http://localhost/public_html/sportsbook/sync_daemon.php?mode=upcoming
 *   http://localhost/public_html/sportsbook/sync_daemon.php?mode=full
 */

if (PHP_SAPI !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1','::1','::ffff:127.0.0.1'])) {
        http_response_code(403); die('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// Unlimited time for CLI (cron), 300s max for web trigger
if (PHP_SAPI === 'cli') set_time_limit(0);
else set_time_limit(300);
ini_set('display_errors', 1);

/* ── Mode ─────────────────────────────────────────────────── */
$mode = 'full';
if (PHP_SAPI === 'cli') {
    foreach ($argv ?? [] as $a) {
        if (strpos($a,'--mode=') === 0) $mode = substr($a,7);
    }
} else {
    $mode = $_GET['mode'] ?? 'full';
}

define('BETSAPI_TOKEN', '254610-7T3dEgVPsVZPNY');
define('BETSAPI_BASE',  'https://api.b365api.com');

/* ── Sports ───────────────────────────────────────────────── */
define('ALL_SPORTS', [
    1=>'Football',18=>'Basketball',13=>'Tennis',91=>'Volleyball',
    107=>'Table Tennis',17=>'Ice Hockey',151=>'E-Sports',16=>'American Football',
    78=>'Handball',45=>'Badminton',117=>'MMA',36=>'E-Football',
    83=>'E-Basketball',66=>'Baseball',56=>'Rugby',48=>'Cricket',
]);

/* ── DB ───────────────────────────────────────────────────── */
$pdo = null;
foreach ([
    dirname(__DIR__,2).'/forza/includes/db.php',
    __DIR__.'/../includes/db.php',
] as $p) {
    if (file_exists($p)) { require_once $p; if (isset($pdo) && $pdo instanceof PDO) { echo "[DB] $p\n"; break; } }
}
if (!$pdo) die("[ERROR] No DB connection\n");

/* ── Ensure table + columns ────────────────────────────────── */
$pdo->exec("CREATE TABLE IF NOT EXISTS sb_matches (
    id VARCHAR(50) PRIMARY KEY,
    sport_id INT NOT NULL DEFAULT 1,
    league_id VARCHAR(30) NULL,
    league_name VARCHAR(200) NOT NULL DEFAULT '',
    home_team VARCHAR(150) NOT NULL DEFAULT '',
    away_team VARCHAR(150) NOT NULL DEFAULT '',
    start_time BIGINT NOT NULL DEFAULT 0,
    status ENUM('upcoming','inplay','ended') NOT NULL DEFAULT 'upcoming',
    score VARCHAR(30) NULL,
    timer_info VARCHAR(30) NULL,
    raw_json MEDIUMTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sb_status (status), KEY idx_sb_sport (sport_id),
    KEY idx_sb_time (start_time), KEY idx_sb_league (league_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
foreach (['timer_info','league_id'] as $col) {
    try { $pdo->exec("ALTER TABLE sb_matches ADD COLUMN $col VARCHAR(50) NULL"); } catch(Exception $e) {}
}

/* ── BetsAPI call ──────────────────────────────────────────── */
function api_get($path, $params = [], $timeout = 15) {
    $params['token'] = BETSAPI_TOKEN;
    $url = BETSAPI_BASE . $path . '?' . http_build_query($params);
    $ctx = stream_context_create(['http'=>[
        'timeout' => $timeout,
        'ignore_errors' => true,
        'header' => "User-Agent: SBSync/5.0\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if (!$body) return [];
    $d = json_decode($body, true);
    if (empty($d['success'])) return [];
    return $d['results'] ?? [];
}

// Returns FULL decoded response (for inplay endpoint which returns nested stream)
function api_get_full($path, $params = [], $timeout = 30) {
    $params['token'] = BETSAPI_TOKEN;
    $url = BETSAPI_BASE . $path . '?' . http_build_query($params);
    $ctx = stream_context_create(['http'=>[
        'timeout' => $timeout,
        'ignore_errors' => true,
        'header' => "User-Agent: SBSync/5.0\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if (!$body) return null;
    return json_decode($body, true) ?: null;
}

/* ── Fractional → Decimal odds ─────────────────────────────── */
function frac_to_dec($frac) {
    $frac = trim((string)$frac);
    if (!$frac || $frac === '-' || $frac === '0') return null;
    if (strtoupper($frac) === 'EVS') return 2.00;
    if (strpos($frac, '/') !== false) {
        [$n, $d] = explode('/', $frac, 2);
        $d = floatval($d);
        if ($d == 0) return null;
        return round(1 + floatval($n) / $d, 2);
    }
    $v = floatval($frac);
    return ($v > 0) ? round($v + 1, 2) : null; // Decimal already? Add 1 as it might be European
}

/* ── Parse real Bet365 odds from BetsAPI event response ─────── */
function parse_odds($event_data) {
    if (empty($event_data['results'][0])) return null;
    $items = is_array($event_data['results'][0]) ? $event_data['results'][0] : [];

    $h = $x = $a = null;
    $ou_line = null; $ou_over = null; $ou_under = null;

    foreach ($items as $item) {
        if (!is_array($item) || ($item['type'] ?? '') !== 'PA') continue;

        $n2 = $item['N2'] ?? '';
        $od = $item['OD'] ?? '';
        if (!$od || $od === '0') continue;

        $dec = frac_to_dec($od);
        if (!$dec || $dec < 1.01) continue;

        // 1x2 market: N2 = 1, X, 2
        if ($n2 === '1'  && !$h) $h = $dec;
        if ($n2 === 'X'  && !$x) $x = $dec;
        if ($n2 === '2'  && !$a) $a = $dec;

        // Over/Under: N2 = "Over" or "Under" often paired with handicap
        if ((stripos($n2,'over') !== false || stripos($n2,'Over') !== false) && !$ou_over)  $ou_over = $dec;
        if ((stripos($n2,'under') !== false || stripos($n2,'Under') !== false) && !$ou_under) $ou_under = $dec;
    }

    if (!$h || !$x || !$a) return null;

    return [
        'h'  => $h,  'x'  => $x,  'a'  => $a,
        'ou_line'  => $ou_line  ?? 2.5,
        'ou_over'  => $ou_over  ?? null,
        'ou_under' => $ou_under ?? null,
        'ts' => time(), // timestamp so UI knows how fresh
    ];
}

/* ── Upsert match ──────────────────────────────────────────── */
$upsert = $pdo->prepare("
    INSERT INTO sb_matches (id,sport_id,league_id,league_name,home_team,away_team,start_time,status,score,timer_info,raw_json)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        sport_id   =VALUES(sport_id),
        league_name=VALUES(league_name),
        league_id  =VALUES(league_id),
        status     =VALUES(status),
        score      =VALUES(score),
        timer_info =VALUES(timer_info),
        start_time =VALUES(start_time),
        raw_json   =VALUES(raw_json),
        updated_at =CURRENT_TIMESTAMP
");

/* ── Extract 1x2 odds from BetsAPI match object (inplay_filter includes them) ── */
function extract_inplay_odds($m) {
    $o = $m['odds'] ?? null;
    if (!$o) return null;
    // Try multiple BetsAPI formats
    foreach (['live','updated','init','start'] as $k) {
        if (isset($o[$k]) && is_array($o[$k])) {
            $r = _parse_odds_flat($o[$k]);
            if ($r) return $r;
        }
    }
    // Flat format {"1":"2.01","X":"3.55","2":"4.23"}
    if (isset($o['1'])) return _parse_odds_flat($o);
    return null;
}
function _parse_odds_flat($o) {
    $h = (float)($o['1'] ?? $o['home'] ?? $o['h'] ?? 0);
    $x = (float)($o['X'] ?? $o['x'] ?? $o['draw'] ?? 0);
    $a = (float)($o['2'] ?? $o['away'] ?? $o['a'] ?? 0);
    if ($h < 1.01 || $x < 1.01 || $a < 1.01) return null;
    return ['h' => round($h,2), 'x' => round($x,2), 'a' => round($a,2), 'ts' => time()];
}

function save_match($upsert, $m, $sport_id_fallback = 1) {
    if (!isset($m['id'])) return false;
    $sid    = (int)($m['sport_id'] ?? $sport_id_fallback);
    $lid    = $m['league']['id']   ?? null;
    $league = $m['league']['name'] ?? '';
    $home   = $m['home']['name']   ?? '';
    $away   = $m['away']['name']   ?? '';
    if ($home === '' || $away === '') return false;
    $time   = (int)($m['time']     ?? 0);
    $status = ($m['time_status'] === '1') ? 'inplay' : 'upcoming';
    $score  = $m['ss']            ?? null;
    $timer  = null;
    if (isset($m['timer'])) {
        $tm = $m['timer']['tm'] ?? '';
        $timer = ($m['timer']['md'] ?? '') === '1' ? 'Mi-temps' : ($tm ? $tm."'" : null);
    }
    // Extract live odds directly from the inplay_filter response (no extra API call needed)
    $inplay_odds = extract_inplay_odds($m);
    if ($inplay_odds && !isset($m['live_odds'])) {
        $m['live_odds'] = $inplay_odds;
    }
    try {
        $upsert->execute([$m['id'],$sid,$lid,$league,$home,$away,$time,$status,$score,$timer,json_encode($m)]);
        return true;
    } catch (Exception $e) { return false; }
}

$saved = 0; $odds_fetched = 0;
$start = microtime(true);

// ═══════════════════════════════════════════════════════════
// MODE: LIVE — Get real match objects from inplay_filter, 
// then map odds from inplay stream!
// ═══════════════════════════════════════════════════════════
if ($mode === 'live' || $mode === 'full') {
    echo "[LIVE] Fetching ALL live matches + real-time odds...\n";

    // 1. Get raw odds stream first
    $resp = api_get_full('/v1/bet365/inplay');
    $stream = [];
    if ($resp && !empty($resp['results'][0]) && is_array($resp['results'][0])) {
        $stream = $resp['results'][0]; // flat array: EV, MA, PA items
    }

    // Parse stream into a map of FI => odds AND stats
    $odds_map  = [];
    $stats_map = []; // keyed by FI — corners, yellow/red cards, attacks
    $rid_to_fi = []; // Map r_id (from inplay_filter) to FI/OI (from stream)
    $curr_fi = null;
    $odds_h = null; $odds_x = null; $odds_a = null;
    $ou_over = null; $ou_under = null;
    // EV S-field → canonical stat name (Bet365 inplay stream)
    // S1=on_target S2=off_target S3=attacks S4=dangerous_attacks
    // S6=corners S7=yellow_cards S8=red_cards
    $ev_stat_map = [
        'S3' => 'attacks',
        'S4' => 'dangerous_attacks',
        'S6' => 'corners',
        'S7' => 'yellow_cards',
        'S8' => 'red_cards',
    ];

    // Validate a stat value — reject timestamps / crazy numbers
    $valid_stat = function($v) {
        $s = trim((string)$v);
        if (!preg_match('/^\d+$/', $s)) return false;
        $n = (int)$s;
        return $n >= 0 && $n <= 999;
    };
    // Parse a "home,away" stat pair from an EV S-field value
    $parse_stat_pair = function($raw) use ($valid_stat) {
        if ($raw === null || $raw === '') return null;
        if (is_string($raw) && strpos($raw, ',') !== false) {
            $p = array_map('trim', explode(',', $raw, 2));
            if ($valid_stat($p[0]) && $valid_stat($p[1])) return [(string)$p[0], (string)$p[1]];
        }
        if (is_array($raw) && isset($raw[0]) && isset($raw[1])) {
            if ($valid_stat($raw[0]) && $valid_stat($raw[1])) return [(string)$raw[0], (string)$raw[1]];
        }
        return null;
    };

    foreach ($stream as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';

        if ($type === 'EV' || $type === 'Event') {
            // Save previous event's odds
            if ($curr_fi && $odds_h) {
                $odds_map[$curr_fi] = [
                    'h'        => $odds_h,
                    'x'        => $odds_x,
                    'a'        => $odds_a,
                    'ou_line'  => 2.5,
                    'ou_over'  => $ou_over,
                    'ou_under' => $ou_under,
                    'ts'       => time()
                ];
            }
            $curr_fi = $item['FI'] ?? $item['OI'] ?? null;
            if ($curr_fi && !empty($item['ID'])) {
                $base_id = explode('_', $item['ID'])[0];
                $rid_to_fi[$base_id] = $curr_fi;
                // Also map numeric-only prefix
                $num_only = preg_replace('/[^0-9].*$/', '', $base_id);
                if ($num_only && $num_only !== $base_id) $rid_to_fi[$num_only] = $curr_fi;
            }
            // ── Parse live stats from EV S-fields ──────────────
            $ev_stats = [];
            foreach ($ev_stat_map as $sk => $canonical) {
                if (!isset($item[$sk]) || $item[$sk] === '') continue;
                $pair = $parse_stat_pair($item[$sk]);
                if ($pair) $ev_stats[$canonical] = $pair;
            }
            if ($ev_stats && $curr_fi) $stats_map[$curr_fi] = $ev_stats;
            $odds_h = $odds_x = $odds_a = $ou_over = $ou_under = null;
        }

        if ($type === 'PA' && $curr_fi) {
            $n2  = (string)($item['N2'] ?? $item['NA'] ?? '');
            $or  = (string)($item['OR'] ?? '');
            $dec = frac_to_dec($item['OD'] ?? '');
            if (!$dec || $dec < 1.01) continue;
            if (($n2 === '1' || $or === '0') && !$odds_h) $odds_h = $dec;
            if (($n2 === 'X' || $or === '1') && !$odds_x) $odds_x = $dec;
            if (($n2 === '2' || $or === '2') && !$odds_a) $odds_a = $dec;
            if ((stripos($n2,'over')  !== false) && !$ou_over)  $ou_over  = $dec;
            if ((stripos($n2,'under') !== false) && !$ou_under) $ou_under = $dec;
        }
    }
    if ($curr_fi && $odds_h) {
        $odds_map[$curr_fi] = ['h'=>$odds_h,'x'=>$odds_x,'a'=>$odds_a,'ou_line'=>2.5,'ou_over'=>$ou_over,'ou_under'=>$ou_under,'ts'=>time()];
    }

    // 2. Fetch match objects per sport (paginated) and inject the odds
    //    5 pages × 50 matches = up to 250 matches per sport per sync cycle
    $live_ids = [];
    foreach (ALL_SPORTS as $sid => $sname) {
        $live = [];
        for ($pg = 1; $pg <= 5; $pg++) {
            $page_res = api_get('/v1/bet365/inplay_filter', ['sport_id' => $sid, 'page' => $pg]);
            if (empty($page_res)) break;
            $live = array_merge($live, $page_res);
            if (count($page_res) < 50) break; // last page
        }
        if (empty($live)) { echo "  [$sname] No live matches\n"; continue; }
        
        $pdo->beginTransaction();
        $n = 0;
        foreach ($live as &$m) {
            $rid = $m['r_id'] ?? '';
            // Try r_id direct, then base strip
            $base_rid = explode('_', $rid)[0];
            $fi = $rid_to_fi[$rid] ?? $rid_to_fi[$base_rid] ?? null;
            if ($fi && isset($odds_map[$fi])) {
                $m['live_odds'] = $odds_map[$fi];
                $odds_fetched++;
            }
            // Inject live stats (corners, cards, attacks) from stream
            if ($fi && isset($stats_map[$fi])) {
                $m['stats'] = $stats_map[$fi];
            }
            if (save_match($upsert, $m, $sid)) {
                $n++;
                $live_ids[] = $m['id'];
            }
        }
        $pdo->commit();
        $saved += $n;
        echo "  [$sname] $n matches saved\n";
    }

    echo "  [STREAM] Matched real-time odds for $odds_fetched live events\n";

    // Mark stale inplay matches as ended
    if (!empty($live_ids)) {
        $ph = implode(',', array_fill(0, count($live_ids), '?'));
        try {
            $pdo->prepare("UPDATE sb_matches SET status='ended' WHERE status='inplay' AND id NOT IN ($ph)")
                ->execute($live_ids);
        } catch (Exception $e) {}
    }
}

// ═══════════════════════════════════════════════════════════
// MODE: UPCOMING — Sync upcoming match schedules + prematch odds
// ═══════════════════════════════════════════════════════════
if ($mode === 'upcoming' || $mode === 'full') {
    $pages = ($mode === 'full') ? 10 : 4;
    echo "[UPCOMING] Syncing upcoming matches ($pages pages/sport) + prematch odds...\n";

    foreach (ALL_SPORTS as $sid => $sname) {
        $all = [];
        for ($pg = 1; $pg <= $pages; $pg++) {
            $res = api_get('/v1/bet365/upcoming', ['sport_id' => $sid, 'page' => $pg]);
            if (empty($res)) break;
            $all = array_merge($all, $res);
            if (count($res) < 50) break;
        }
        if (empty($all)) { echo "  [$sname] No upcoming\n"; continue; }

        // Preload existing cached odds from DB (6-hour cache) to avoid re-fetching
        $cached_odds = [];
        try {
            $cs = $pdo->prepare(
                "SELECT id, JSON_UNQUOTE(JSON_EXTRACT(raw_json,'$.live_odds')) as lo
                   FROM sb_matches WHERE sport_id=? AND status='upcoming'"
            );
            $cs->execute([$sid]);
            while ($r = $cs->fetch(PDO::FETCH_ASSOC)) {
                if ($r['lo'] && $r['lo'] !== 'null') {
                    $lo = json_decode($r['lo'], true);
                    // Cache if odds are < 1 hour old (short enough to refresh OU lines regularly)
                    if (!empty($lo['h']) && !empty($lo['ts']) && (time() - (int)$lo['ts']) < 3600) {
                        $cached_odds[$r['id']] = $lo;
                    }
                }
            }
        } catch (Exception $e) {}

        $pdo->beginTransaction();
        $n = 0; $odds_added = 0;
        // 30 fresh prematch odds per sport — SKIPS virtual/e-soccer
        // So quota is used only for real matches (Premier League, La Liga, etc.)
        $odds_quota = 30;

        foreach ($all as &$m) {
            $lname_lc = strtolower($m['league']['name'] ?? '');

            // ── Skip virtual / e-sports matches (no real prematch odds)
            $is_virtual = strpos($lname_lc,'esoccer')  !== false
                       || strpos($lname_lc,'esport')   !== false
                       || strpos($lname_lc,'8 mins')   !== false
                       || strpos($lname_lc,'efootball') !== false
                       || strpos($lname_lc,'virtual')  !== false
                       || strpos($lname_lc,'h2h gg')   !== false;

            // Use cached odds if fresh
            if (isset($cached_odds[$m['id']])) {
                $m['live_odds'] = $cached_odds[$m['id']];
            }

            // Fetch fresh prematch odds for REAL matches only
            if (!$is_virtual && !isset($m['live_odds']) && $odds_quota > 0 && isset($m['id'])) {
                $odds_resp = api_get_full('/v1/bet365/prematch', ['FI' => $m['id']], 8);
                $pm = parse_prematch_odds($odds_resp);
                if ($pm) {
                    $m['live_odds'] = $pm;
                    $odds_added++;
                }
                $odds_quota--;
            }

            if (save_match($upsert, $m, $sid)) $n++;
        }
        $pdo->commit();
        echo "  [$sname] $n upcoming ($odds_added new prematch odds)\n";
        $saved += $n;
    }
}

/* ── Parse a DECIMAL odd (prematch returns decimal format, not fractional) ── */
function parse_decimal($val) {
    $v = floatval((string)$val);
    return ($v >= 1.01) ? round($v, 2) : null;
}

/* ── Parse prematch odds from BetsAPI /v1/bet365/prematch response ──
   KEY FACTS about the prematch endpoint:
   - odds are in DECIMAL format (e.g. 1.30, 5.00) — NOT fractional!
   - full_time_result[0] = home (1), [1] = draw (X), [2] = away (2) — positional
   - goals_over_under contains O/U 2.5 market
*/
function parse_prematch_odds($resp) {
    if (!$resp || empty($resp['results'])) return null;

    $h = null; $x = null; $a = null;
    $ou_over = null; $ou_under = null; $ou_line = 2.5;
    $h = null; $x = null; $a = null;

    // ── PRIMARY: /v1/bet365/prematch returns results[0].main.sp ─────────
    // Prematch odds are in DECIMAL format (1.30, 5.00) NOT fractional!
    // full_time_result array is POSITIONAL: [0]=home(1) [1]=draw(X) [2]=away(2)
    $r0 = $resp['results'][0] ?? null;
    if ($r0 && !empty($r0['main']['sp'])) {
        $sp = $r0['main']['sp'];

        // ── Football 1x2: full_time_result[0]=home [1]=draw [2]=away (positional)
        if (!empty($sp['full_time_result']) && is_array($sp['full_time_result'])) {
            $ftr = array_values($sp['full_time_result']);
            $h = parse_decimal($ftr[0]['odds'] ?? '');
            $x = parse_decimal($ftr[1]['odds'] ?? '');
            $a = parse_decimal($ftr[2]['odds'] ?? '');
        }

        // ── Tennis match winner: to_win_match[0]=player1, [1]=player2 (2-way, no draw)
        if (!$h && !empty($sp['to_win_match']) && is_array($sp['to_win_match'])) {
            $twm = array_values($sp['to_win_match']);
            $h = parse_decimal($twm[0]['odds'] ?? '');
            $a = parse_decimal($twm[1]['odds'] ?? '');  // x=null (no draw in tennis)
        }

        // ── Basketball: game_lines moneyline (header = "Moneyline" or "ML")
        if (!$h && !empty($sp['game_lines']) && is_array($sp['game_lines'])) {
            $ml = [];
            foreach ($sp['game_lines'] as $item) {
                $hdr = strtolower(trim($item['header'] ?? ''));
                $od  = parse_decimal($item['odds'] ?? '');
                if ($od && ($hdr === 'moneyline' || $hdr === 'ml')) {
                    $ml[] = $od;
                }
            }
            if (count($ml) >= 2) { $h = $ml[0]; $a = $ml[1]; }
        }

        // Over/Under — parse actual line from name field, use FIRST complete group
        // Format: [{name:"3.5",header:" ",odds:"0"},{header:"Over",odds:"2.20"},{header:"Under",odds:"1.68"},...]
        if (!empty($sp['goals_over_under']) && is_array($sp['goals_over_under'])) {
            $curr_ou_line = 2.5; $pend_over = null; $pend_under = null;
            foreach ($sp['goals_over_under'] as $item) {
                if (!is_array($item)) continue;
                $iname  = trim($item['name']   ?? '');
                $header = strtolower($item['header'] ?? '');
                $odd    = parse_decimal($item['odds'] ?? '');
                // Line label: numeric name with no valid odds — marks the start of a new OU group
                if ($iname !== '' && is_numeric($iname) && (!$odd || $odd < 1.01)) {
                    $curr_ou_line = (float)$iname;
                    $pend_over = null; $pend_under = null;
                    continue;
                }
                if (!$odd || $odd < 1.01) continue;
                if (strpos($header,'over')  !== false && !$pend_over)  $pend_over  = $odd;
                if (strpos($header,'under') !== false && !$pend_under) $pend_under = $odd;
                // First complete group = featured OU line (matches what fcbet216 shows)
                if ($pend_over && $pend_under) {
                    $ou_line  = $curr_ou_line;
                    $ou_over  = $pend_over;
                    $ou_under = $pend_under;
                    break;
                }
            }
            // If only one side found (market partially suspended), keep what we have
            if (!$ou_over && $pend_over)   $ou_over  = $pend_over;
            if (!$ou_under && $pend_under) $ou_under = $pend_under;
        }
    }

    // ── FALLBACK: flat PA stream format (event?FI= style) ───────────────
    // This format uses fractional odds (4/6 format) → use frac_to_dec
    if (!$h && !empty($resp['results'])) {
        $items = $resp['results'];
        // Unwrap if nested: results[0] = [item, item, ...]
        if (is_array($items[0] ?? null) && is_array($items[0][0] ?? null)) {
            $items = $items[0];
        }
        $curr_h = $curr_x = $curr_a = null;
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $type = $item['type'] ?? $item['TYPE'] ?? '';
            if ($type === 'PA') {
                $n2  = (string)($item['N2'] ?? '');
                $or  = (string)($item['OR'] ?? '');
                $dec = frac_to_dec($item['OD'] ?? '');
                if (!$dec || $dec < 1.01) continue;
                if (($n2 === '1' || $or === '0') && !$curr_h) $curr_h = $dec;
                if (($n2 === 'X' || $or === '1') && !$curr_x) $curr_x = $dec;
                if (($n2 === '2' || $or === '2') && !$curr_a) $curr_a = $dec;
                if (stripos($n2,'over')  !== false && !$ou_over)  $ou_over  = frac_to_dec($item['OD']);
                if (stripos($n2,'under') !== false && !$ou_under) $ou_under = frac_to_dec($item['OD']);
            }
        }
        if ($curr_h && $curr_h >= 1.01) { $h = $curr_h; $x = $curr_x; $a = $curr_a; }
    }

    if (!$h || $h < 1.01) return null;

    return ['h'=>$h,'x'=>$x,'a'=>$a,'ou_line'=>$ou_line,'ou_over'=>$ou_over,'ou_under'=>$ou_under,'ts'=>time()];
}

// ═══════════════════════════════════════════════════════════
// MODE: FULL — Cleanup old data
// ═══════════════════════════════════════════════════════════
if ($mode === 'full') {
    echo "[CLEANUP] Marking old upcoming as ended...\n";
    $cutoff = time() - 10800; // 3 hours ago
    try {
        $c = $pdo->exec("UPDATE sb_matches SET status='ended' WHERE status='upcoming' AND start_time > 0 AND start_time < $cutoff");
        echo "  Marked $c matches as ended\n";
    } catch (Exception $e) {}

    try {
        $d = $pdo->exec("DELETE FROM sb_matches WHERE status='ended' AND updated_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
        echo "  Deleted $d old ended matches\n";
    } catch (Exception $e) {}
}

/* ── Summary ────────────────────────────────────────────────── */
$elapsed = round(microtime(true) - $start, 2);
$active = 0;
try { $active = $pdo->query("SELECT COUNT(*) FROM sb_matches WHERE status!='ended'")->fetchColumn(); } catch(Exception $e) {}

$summary = ['success' => true, 'mode' => $mode, 'saved' => $saved, 'odds_fetched' => $odds_fetched, 'active' => $active, 'elapsed' => $elapsed];
echo "\n[DONE] " . json_encode($summary) . "\n";
if (PHP_SAPI !== 'cli') echo json_encode($summary);
