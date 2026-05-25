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
$TICK_SECONDS    = 2;     // base loop interval
$STREAM_EVERY    = 1;     // refresh /v1/bet365/inplay every N ticks (2s)
$SPORT_EVERY     = 2;     // refresh inplay_filter every N ticks (4s)
$LIVE_SPORTS     = [1, 18, 13, 91, 17, 78];  // football, basketball, tennis, vb, ice hockey, handball
$MAX_INPLAY_PAGES = 5;    // up to 250 matches per sport
$LOG_EVERY_N_TICKS = 10;  // log a stats line every N ticks

// ── Lock: only one instance runs ──────────────────────────────
if (file_exists(LOCK_FILE)) {
    $age = time() - filemtime(LOCK_FILE);
    if ($age < 10) {
        fwrite(STDERR, "[tick_live] Another instance is running (lock age {$age}s). Exiting.\n");
        exit(1);
    }
    @unlink(LOCK_FILE);
}
@touch(LOCK_FILE);

// Cleanup lock on exit
register_shutdown_function(function() { @unlink(LOCK_FILE); });
// POSIX signal handlers — pcntl is unavailable on Windows, no-op there.
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    @call_user_func('pcntl_async_signals', true);
    foreach (['SIGINT', 'SIGTERM'] as $sig_name) {
        if (defined($sig_name)) {
            @call_user_func('pcntl_signal', constant($sig_name), function() {
                @unlink(LOCK_FILE); exit(0);
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
    @touch(LOCK_FILE);   // keep lock fresh
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

fwrite(STDOUT, "[tick_live] Started PID=" . getmypid() . " interval={$TICK_SECONDS}s sports=[" . implode(',', $LIVE_SPORTS) . "]\n");

while (true) {
    $tick++;
    $loop_start = microtime(true);

    // Always refresh stream every tick — it's the freshest source.
    if ($tick % $STREAM_EVERY === 0) {
        $n = tick_refresh_stream();
        if ($n > 0) $total_stream_items += $n;
    }

    // Rotate sports — refresh each in a round-robin so we don't burn rate limit.
    // At every 2nd tick, refresh ONE sport from the list.
    if ($tick % $SPORT_EVERY === 0) {
        $sport_idx = (intdiv($tick, $SPORT_EVERY) - 1) % count($LIVE_SPORTS);
        $sid = $LIVE_SPORTS[$sport_idx];
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
