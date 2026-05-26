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
// RATE-LIMIT-SAFE defaults. BetsAPI free/standard plans have a quota
// of ~500-1000 req/min. With TICK_SECONDS=5:
//   - /v1/bet365/inplay (stream)           : 1 call / 5s  = 12/min
//   - /v1/bet365/inplay_filter (football)  : 1 call / 10s =  6/min
//   - Other sports round-robin (5 sports)  : 1 call / 20s =  3/min each
//   Total: ~33 req/min — well within any plan.
// If you have a Volume Package (higher quota), you can lower TICK_SECONDS.
$TICK_SECONDS      = 5;     // base loop interval — change to 2 with Volume Package
$STREAM_EVERY      = 1;     // refresh /v1/bet365/inplay every tick
$FOOTBALL_EVERY    = 2;     // refresh football inplay_filter every 2 ticks (10s)
$OTHER_SPORT_EVERY = 4;     // refresh non-football sport round-robin every 4 ticks (20s)
$FOOTBALL_SPORT_ID = 1;
$OTHER_LIVE_SPORTS = [18, 13, 91, 17, 78];  // basketball, tennis, volleyball, ice hockey, handball
$MAX_INPLAY_PAGES  = 3;     // 3 pages = 150 matches — reduces calls vs 5 pages
$LOG_EVERY_N_TICKS = 12;    // log a stats line every N ticks (~60s)

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

/* ── HTTP fetch ─────────────────────────────────────────────── */
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
    $body = curl_exec($ch);
    curl_close($ch);
    if (!$body) return null;
    return json_decode($body, true) ?: null;
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

/* ── Refresh /v1/bet365/inplay (the live stream) ──────────── */
function tick_refresh_stream() {
    $resp = tick_http_get('/v1/bet365/inplay');
    if (!$resp || empty($resp['results'][0]) || !is_array($resp['results'][0])) return 0;
    tick_atomic_write(CACHE_DIR . '/inplay_stream.json', $resp['results'][0]);
    return count($resp['results'][0]);
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

fwrite(STDOUT, "[tick_live] Started PID=" . getmypid() . " interval={$TICK_SECONDS}s football=every{$FOOTBALL_EVERY}t others=[" . implode(',', $OTHER_LIVE_SPORTS) . "] every{$OTHER_SPORT_EVERY}t\n");

while (true) {
    $tick++;
    $loop_start = microtime(true);

    // Always refresh global inplay stream every tick — it's the freshest
    // source and contains EV.SS / EV.TM / EV.MD / S1-S8 stats for ALL
    // live matches in one call. This is what powers live_refresh.
    if ($tick % $STREAM_EVERY === 0) {
        $n = tick_refresh_stream();
        if ($n > 0) $total_stream_items += $n;
    }

    // FOOTBALL FIRST. Refresh /v1/bet365/inplay_filter?sport_id=1 every
    // FOOTBALL_EVERY ticks (default 2s). Football is the only sport where
    // sub-2s freshness materially changes the UX (goals, cards, HT).
    if ($tick % $FOOTBALL_EVERY === 0) {
        $cnt = tick_refresh_sport($FOOTBALL_SPORT_ID, $MAX_INPLAY_PAGES);
        if ($cnt > 0) $total_sport_matches += $cnt;
    }

    // Other sports: round-robin every OTHER_SPORT_EVERY ticks (4s).
    // Each sport refreshes every (count*OTHER_SPORT_EVERY) = 20s.
    // That's fine for basketball/tennis/etc. (slower-changing scores).
    if ($tick % $OTHER_SPORT_EVERY === 0) {
        $rr_idx = (intdiv($tick, $OTHER_SPORT_EVERY) - 1) % count($OTHER_LIVE_SPORTS);
        $sid = $OTHER_LIVE_SPORTS[$rr_idx];
        $cnt = tick_refresh_sport($sid, $MAX_INPLAY_PAGES);
        if ($cnt > 0) $total_sport_matches += $cnt;
    }

    if ($tick % $LOG_EVERY_N_TICKS === 0) {
        $uptime = time() - $started_at;
        fwrite(STDOUT, sprintf("[tick_live] tick=%d uptime=%ds stream_items=%d sport_matches=%d\n",
            $tick, $uptime, $total_stream_items, $total_sport_matches));
    }

    $elapsed = microtime(true) - $loop_start;
    $sleep_for = max(0, $TICK_SECONDS - $elapsed);
    if ($sleep_for > 0) usleep((int)($sleep_for * 1_000_000));
}
