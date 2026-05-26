<?php
/**
 * ───────────────────────────────────────────────────────────────
 *   tick_live.php  —  Fast Live Tick Daemon
 * ───────────────────────────────────────────────────────────────
 * Runs forever as a long-lived CLI process. Every TICK_SECONDS
 * (default 2s) it fetches the freshest data DIRECTLY from
 * BetsAPI and writes to the per-sport file caches that the
 * sportsbook/api.php read from:
 *
 *   /cache/inplay_stream.json   ← /v1/bet365/inplay      (live stream)
 *   /cache/live_1.json          ← /v1/bet365/inplay_filter?sport_id=1 (football)
 *   /cache/live_18.json         ← basketball
 *   /cache/live_13.json         ← tennis
 *   /cache/live_91.json         ← volleyball
 *   /cache/live_17.json         ← ice hockey
 *   /cache/live_78.json         ← handball
 *
 * Because the cache is ALREADY warm when a user request lands,
 * sportsbook/api.php reads the file directly and returns in
 * a few milliseconds — no BetsAPI round-trip inside the request.
 *
 * START IT (Windows):
 *   php C:\wamp64\www\public_html\sportsbook\tick_live.php
 *
 * OR via the .bat:
 *   C:\wamp64\www\public_html\sportsbook\tick_live.bat
 *
 * Stop with Ctrl+C. Only one instance can run at a time
 * (enforced via a lock file).
 */

if (PHP_SAPI !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1','::1','::ffff:127.0.0.1'])) {
        http_response_code(403); die('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

@set_time_limit(0);
@ini_set('memory_limit', '256M');

define('BETSAPI_TOKEN', '254610-7T3dEgVPsVZPNY');
define('BETSAPI_BASE',  'https://api.b365api.com');
define('CACHE_DIR',     __DIR__ . '/cache');
define('LOCK_FILE',     CACHE_DIR . '/tick_live.lock');

if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);

// ── Tunable parameters ─────────────────────────────────────────
// STREAM-ONLY mode: we call /v1/bet365/inplay ONCE per tick and
// extract everything from it (scores, timers, stats, odds stubs,
// team names). This is 1 API call per tick instead of 7+, so we
// can safely run at 2s intervals on any BetsAPI plan:
//   - /v1/bet365/inplay (stream): 1 call / 2s = 30/min
//   - /v1/bet365/inplay_filter (football, fallback for team names):
//     1 call every 30 ticks = 1/min
//   Total: ~31 req/min — safe on all plans including free tier.
//
// To go faster: lower TICK_SECONDS (need Volume Package for <2s).
$TICK_SECONDS         = 2;    // base loop interval — safe on all plans
$STREAM_EVERY         = 1;    // refresh stream every tick (2s)
$FOOTBALL_FILTER_EVERY = 30;  // refresh football inplay_filter every 30 ticks (60s, for team names)
$OTHER_FILTER_EVERY   = 60;   // other sports every 60 ticks (120s)
$FOOTBALL_SPORT_ID    = 1;
$OTHER_LIVE_SPORTS    = [18, 13, 91, 17, 78];
$MAX_INPLAY_PAGES     = 3;
$LOG_EVERY_N_TICKS    = 30;   // log every ~60s

// ── Lock: only one instance runs ──────────────────────────────
//   We use a real OS-level advisory lock via flock(LOCK_EX | LOCK_NB).
//   Two big advantages over the old mtime-based pseudo-lock:
//     (1) the OS auto-releases the lock when the process dies, even
//         on SIGKILL — no more "stale lock from crashed parent blocks
//         every restart for 10s" failure mode that bit systemd.
//     (2) it's atomic — no race between file_exists() and touch().
//
//   We keep the file handle open for the lifetime of the process in a
//   global so the GC doesn't close it. The handle is closed (and the
//   lock released) automatically on shutdown.
$lock_fh = @fopen(LOCK_FILE, 'c');
if (!$lock_fh) {
    fwrite(STDERR, "[tick_live] Cannot open lock file at " . LOCK_FILE . " — is the cache dir writable?\n");
    exit(2);
}
if (!flock($lock_fh, LOCK_EX | LOCK_NB)) {
    // Read the holder PID for a useful error message.
    rewind($lock_fh);
    $holder = trim((string)@fread($lock_fh, 32));
    @fclose($lock_fh);
    fwrite(STDERR, "[tick_live] Another instance already holds the lock (pid={$holder}). Exiting.\n");
    fwrite(STDERR, "[tick_live] If you're sure no other instance is running, kill pid {$holder} or rm " . LOCK_FILE . "\n");
    exit(1);
}
// Write our PID into the lock file so external tools can identify the holder.
@ftruncate($lock_fh, 0);
rewind($lock_fh);
@fwrite($lock_fh, getmypid() . "\n");
@fflush($lock_fh);
// Keep handle alive for process lifetime.
$GLOBALS['_tick_lock_fh'] = $lock_fh;

// POSIX signal handlers — pcntl is unavailable on Windows, no-op there.
// On clean signal, flush + close so we exit cleanly. flock release is
// automatic so we don't need to unlink the file.
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    @call_user_func('pcntl_async_signals', true);
    foreach (['SIGINT', 'SIGTERM'] as $sig_name) {
        if (defined($sig_name)) {
            @call_user_func('pcntl_signal', constant($sig_name), function() {
                fwrite(STDOUT, "[tick_live] Received signal — exiting cleanly.\n");
                exit(0);
            });
        }
    }
}

/* ── HTTP fetch — returns decoded array or null on any error ── */
function tick_http_get($path, $params = [], $timeout = 8) {
    $params['token'] = BETSAPI_TOKEN;
    $url = BETSAPI_BASE . $path . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'SBTickLive/1.0',
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno || !$body) {
        fwrite(STDERR, "[tick_live] curl error={$errno} http={$http} path={$path}\n");
        return null;
    }
    $data = json_decode($body, true);
    if (!$data) {
        fwrite(STDERR, "[tick_live] JSON parse failed path={$path} body=" . substr($body,0,120) . "\n");
        return null;
    }
    // Log API-level errors (429, auth, etc.) so we always see them in the log.
    if (empty($data['success'])) {
        $err = $data['error'] ?? 'unknown';
        $det = $data['error_detail'] ?? '';
        fwrite(STDOUT, "[tick_live] API error http={$http} error=\"{$err}\" detail=\"{$det}\" path={$path}\n");
        // 429 = quota exhausted — store the timestamp so the loop can back off.
        if ($http === 429 || strpos($err, 'TOO_MANY') !== false) {
            $GLOBALS['_tick_429_at'] = time();
        }
        return null;
    }
    return $data;
}

/* ── Atomically write the JSON cache file ──────────────────── */
function tick_atomic_write($path, $data) {
    $tmp = $path . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, json_encode($data)) === false) return false;
    @rename($tmp, $path);
    // No need to touch LOCK_FILE — we hold an flock() for the
    // lifetime of the process. The OS guarantees mutual exclusion.
    return true;
}

/* ── Refresh /v1/bet365/inplay (the live stream) ──────────────
 * Also parses the stream to extract per-match score/timer/stats
 * and patches the per-sport live_X.json files in-place.
 * This is the KEY optimisation: one API call updates everything.
 * ─────────────────────────────────────────────────────────── */
function tick_refresh_stream() {
    $resp = tick_http_get('/v1/bet365/inplay');
    if (!$resp || empty($resp['results'][0]) || !is_array($resp['results'][0])) return 0;
    $stream = $resp['results'][0];
    tick_atomic_write(CACHE_DIR . '/inplay_stream.json', $stream);

    // ── Parse stream: extract score / timer / stats per FI / r_id ──
    // The stream is a flat array of typed items: EV, MA, PA, LA …
    // Each EV item is one live match and carries SS (score), TM/TS/MD
    // (timer), S1-S8 (stats: corners, cards, attacks …).
    $ev_by_fi    = [];   // FI → match patch {ss, timer, stats}
    $ev_by_id    = [];   // ID-base → match patch
    $curr_fi     = null;
    $curr_id     = null;
    foreach ($stream as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';
        if ($type === 'EV') {
            $curr_fi  = $item['OI'] ?? $item['FI'] ?? null;
            $id_raw   = $item['ID'] ?? '';
            $curr_id  = $id_raw ? explode('_', $id_raw)[0] : null;
            $patch = [];
            $ss = $item['SS'] ?? $item['ss'] ?? null;
            if ($ss !== null && $ss !== '') $patch['ss'] = $ss;
            // Timer: TM=minute, TS=seconds, MD=period
            $tm = $item['TM'] ?? $item['tm'] ?? null;
            $ts = $item['TS'] ?? $item['ts'] ?? null;
            $md = $item['MD'] ?? $item['md'] ?? null;
            if ($tm !== null) {
                $patch['timer'] = [
                    'tm' => (int)$tm,
                    'ts' => (int)($ts ?? 0),
                    'md' => (string)($md ?? '0'),
                ];
            }
            // Stats S1-S8 (corners, yellow cards, red cards, etc.)
            $stats = [];
            foreach (['S1','S2','S3','S4','S5','S6','S7','S8'] as $sk) {
                $sv = $item[$sk] ?? null;
                if ($sv !== null && $sv !== '') $stats[$sk] = $sv;
            }
            if ($stats) $patch['stats_ev'] = $stats;
            if ($curr_fi)  $ev_by_fi[$curr_fi]  = $patch;
            if ($curr_id)  $ev_by_id[$curr_id]  = $patch;
        }
    }

    // ── Patch per-sport live_X.json with fresh scores/timers ──
    // Load each cached sport file, update matching matches, write back.
    $patched_total = 0;
    foreach (glob(CACHE_DIR . '/live_*.json') ?: [] as $lf) {
        $arr = json_decode(@file_get_contents($lf), true);
        if (!is_array($arr) || empty($arr)) continue;
        $changed = false;
        foreach ($arr as &$m) {
            if (!is_array($m)) continue;
            $mid  = (string)($m['id']   ?? '');
            $rid  = (string)($m['r_id'] ?? '');
            $rid_n = $rid ? explode('_', $rid)[0] : $mid;
            // Find the stream patch for this match
            $patch = $ev_by_fi[$rid] ?? $ev_by_fi[$rid_n] ?? $ev_by_id[$rid_n] ?? $ev_by_id[$mid] ?? null;
            if (!$patch) continue;
            if (isset($patch['ss'])    && $patch['ss']    !== '') { $m['ss']    = $patch['ss'];    $changed = true; }
            if (isset($patch['timer']))                            { $m['timer'] = $patch['timer']; $changed = true; }
            if (isset($patch['stats_ev'])) {
                // Map EV S1-S8 to named stats keys used by the frontend.
                // BetsAPI stream S-fields for football (sport 1):
                //   S1=corners, S2=yellow_cards, S3=red_cards,
                //   S4=dangerous_attacks, S5=shots_on_target, S6=attacks,
                //   S7=on_target (alt), S8=off_target (alt)
                $se = $patch['stats_ev'];
                $ns = $m['stats'] ?? [];
                $map = ['S1'=>'corners','S2'=>'yellow_cards','S3'=>'red_cards',
                        'S4'=>'dangerous_attacks','S5'=>'shots_on_target','S6'=>'attacks'];
                foreach ($map as $sk => $nk) {
                    if (!isset($se[$sk])) continue;
                    $raw = $se[$sk];
                    // Values come as "H,A" pairs e.g. "3,2"
                    $parts = explode(',', (string)$raw);
                    if (count($parts) >= 2) {
                        $ns[$nk] = [(int)$parts[0], (int)$parts[1]];
                        $changed = true;
                    }
                }
                if ($changed) $m['stats'] = $ns;
            }
            $patched_total++;
        }
        unset($m);
        if ($changed) tick_atomic_write($lf, $arr);
    }

    return count($stream);
}

/* ── Refresh /v1/bet365/inplay_filter per sport ────────────── */
function tick_refresh_sport($sport_id, $max_pages = 5) {
    $seen = []; $out = [];
    for ($pg = 1; $pg <= $max_pages; $pg++) {
        $resp = tick_http_get('/v1/bet365/inplay_filter', ['sport_id' => $sport_id, 'page' => $pg]);
        if (empty($resp['results'])) break;
        foreach ($resp['results'] as $m) {
            $mid = $m['id'] ?? null;
            if ($mid && !isset($seen[$mid]) && !empty($m['home']['name'])) {
                $seen[$mid] = 1;
                $out[] = $m;
            }
        }
        if (count($resp['results']) < 50) break;
    }
    if (!$out) return 0;
    tick_atomic_write(CACHE_DIR . '/live_' . $sport_id . '.json', $out);
    return count($out);
}

/* ── Main loop ─────────────────────────────────────────────── */
$tick = 0;
$started_at = time();
$total_stream_items = 0;
$total_sport_matches = 0;

$all_sports = array_merge([$FOOTBALL_SPORT_ID], $OTHER_LIVE_SPORTS);
fwrite(STDOUT, "[tick_live] Started PID=" . getmypid()
    . " interval={$TICK_SECONDS}s stream=every{$STREAM_EVERY}t"
    . " football_filter=every{$FOOTBALL_FILTER_EVERY}t"
    . " other_filter=every{$OTHER_FILTER_EVERY}t\n");
fwrite(STDOUT, "[tick_live] STREAM-ONLY mode: 1 API call per tick = ~" . round(60/$TICK_SECONDS) . " req/min\n");

while (true) {
    $tick++;
    $loop_start = microtime(true);

    // ── 429 back-off ──────────────────────────────────────────
    // If the last API call returned 429 (quota exhausted), pause all
    // BetsAPI calls for 60 seconds and log once per minute so the
    // operator knows what's happening. Quota typically resets at the
    // top of the hour. Only the log + lock-file touch happen here.
    $last_429 = $GLOBALS['_tick_429_at'] ?? 0;
    if ($last_429 > 0 && (time() - $last_429) < 60) {
        if ($tick % 10 === 0) {
            $wait = 60 - (time() - $last_429);
            fwrite(STDOUT, "[tick_live] 429 quota exhausted — backing off, retry in ~{$wait}s\n");
        }
        $elapsed   = microtime(true) - $loop_start;
        $sleep_for = max(0, $TICK_SECONDS - $elapsed);
        if ($sleep_for > 0) usleep((int)($sleep_for * 1_000_000));
        continue;
    }

    // ── PRIMARY: stream every tick ────────────────────────────
    // ONE call gives us fresh SS/TM/MD/stats for ALL live matches.
    // tick_refresh_stream() also patches per-sport live_X.json files
    // in-place so the home page and match detail page both see fresh
    // data within $TICK_SECONDS seconds.
    if ($tick % $STREAM_EVERY === 0) {
        $n = tick_refresh_stream();
        if ($n > 0) $total_stream_items += $n;
    }

    // ── SECONDARY: inplay_filter for team names / league ──────
    // Team names and league names almost never change mid-match.
    // We only need this to populate the match list initially and
    // to pick up newly-started matches. Very infrequent calls.
    if ($tick % $FOOTBALL_FILTER_EVERY === 0) {
        $cnt = tick_refresh_sport($FOOTBALL_SPORT_ID, $MAX_INPLAY_PAGES);
        if ($cnt > 0) $total_sport_matches += $cnt;
    }
    if ($tick % $OTHER_FILTER_EVERY === 0) {
        $rr_idx = (intdiv($tick, $OTHER_FILTER_EVERY) - 1) % count($OTHER_LIVE_SPORTS);
        $sid    = $OTHER_LIVE_SPORTS[$rr_idx];
        $cnt    = tick_refresh_sport($sid, $MAX_INPLAY_PAGES);
        if ($cnt > 0) $total_sport_matches += $cnt;
    }

    if ($tick % $LOG_EVERY_N_TICKS === 0) {
        $uptime = time() - $started_at;
        fwrite(STDOUT, sprintf(
            "[tick_live] tick=%d uptime=%ds stream_items=%d sport_matches=%d\n",
            $tick, $uptime, $total_stream_items, $total_sport_matches
        ));
    }

    $elapsed   = microtime(true) - $loop_start;
    $sleep_for = max(0, $TICK_SECONDS - $elapsed);
    if ($sleep_for > 0) usleep((int)($sleep_for * 1_000_000));
}
