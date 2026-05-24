<?php
$file = 'c:/wamp64/www/public_html/sportsbook/sync_daemon.php';
$content = file_get_contents($file);

// Replace Live mode block
$live_old = <<<'EOF'
// ═══════════════════════════════════════════════════════════
// MODE: LIVE — ONE API call gets ALL live matches + ALL odds
// Uses /v1/bet365/inplay which returns flat event stream
// (same format as event?FI= but for every live match at once)
// ═══════════════════════════════════════════════════════════
if ($mode === 'live' || $mode === 'full') {
    echo "[LIVE] Fetching ALL live matches + real-time odds...\n";

    // Single call: get entire live event stream with all market PA items
    $resp = api_get_full('/v1/bet365/inplay');
    $stream = [];
    if ($resp && !empty($resp['results'][0]) && is_array($resp['results'][0])) {
        $stream = $resp['results'][0]; // flat array: Event, MarketHeader, PA items
    }

    if (empty($stream)) {
        // Fallback: per-sport inplay_filter (no odds but gets match info)
        echo "[LIVE] inplay stream empty — falling back to inplay_filter per sport\n";
        foreach (ALL_SPORTS as $sid => $sname) {
            $live = api_get('/v1/bet365/inplay_filter', ['sport_id' => $sid]);
            if (empty($live)) { echo "  [$sname] No live\n"; continue; }
            $pdo->beginTransaction();
            $n = 0;
            foreach ($live as $m) { if (save_match($upsert, $m, $sid)) $n++; }
            $pdo->commit();
            $saved += $n;
            echo "  [$sname] $n matches (no odds)\n";
        }
    } else {
        // ── Parse streaming format: sequential Event → PA items ─────────
        // Structure: results[0] = [event_item, mkt_hdr, pa, pa, ..., event_item, ...]
        $curr      = null; // current match data
        $odds_h    = null;
        $odds_x    = null;
        $odds_a    = null;
        $ou_over   = null;
        $ou_under  = null;
        $n_saved   = 0;
        $live_ids  = []; // track which IDs are live (for cleanup)

        function flush_event($upsert, &$curr, $oh, $ox, $oa, $ov, $ou) {
            if (!$curr || empty($curr['id'])) return;
            if ($oh && $ox && $oa) {
                $curr['live_odds'] = ['h'=>$oh,'x'=>$ox,'a'=>$oa,'ou_line'=>2.5,'ou_over'=>$ov,'ou_under'=>$ou,'ts'=>time()];
            }
            save_match($upsert, $curr, (int)($curr['sport_id'] ?? 1));
        }

        $pdo->beginTransaction();
        foreach ($stream as $item) {
            if (!is_array($item)) continue;
            $type = $item['type'] ?? $item['TYPE'] ?? '';

            // ── Event item: new match (has home/away/league)
            if ($type === 'Event' || (!empty($item['id']) && isset($item['home']) && isset($item['away']))) {
                // Flush previous event
                flush_event($upsert, $curr, $odds_h, $odds_x, $odds_a, $ou_over, $ou_under);
                $curr   = $item;
                $odds_h = $odds_x = $odds_a = $ou_over = $ou_under = null;
                $live_ids[] = $item['id'];
            }

            // ── PA (price) item: contains actual odds
            if ($type === 'PA' && $curr) {
                $n2  = $item['N2'] ?? $item['NA'] ?? '';
                $or  = $item['OR'] ?? ''; // OR tells us Home=0, Draw=1, Away=2
                $dec = frac_to_dec($item['OD'] ?? '');
                if (!$dec || $dec < 1.01) continue;
                
                // 1x2 market (using OR attribute)
                if (($n2 === '1' || $or === '0') && !$odds_h) $odds_h = $dec;
                if (($n2 === 'X' || $or === '1') && !$odds_x) $odds_x = $dec;
                if (($n2 === '2' || $or === '2') && !$odds_a) $odds_a = $dec;
                
                // Over/Under
                if ((stripos($n2,'over')  !== false) && !$ou_over)  $ou_over  = $dec;
                if ((stripos($n2,'under') !== false) && !$ou_under) $ou_under = $dec;
            }
        }
        // Flush last event
        flush_event($upsert, $curr, $odds_h, $odds_x, $odds_a, $ou_over, $ou_under);

        $pdo->commit();
        $n_saved = count($live_ids);
        $saved  += $n_saved;
        $odds_fetched = $n_saved;
        echo "  [STREAM] Saved $n_saved live events WITH real-time odds\n";

        // Mark stale inplay matches as ended
        if (!empty($live_ids)) {
            $ph = implode(',', array_fill(0, count($live_ids), '?'));
            try {
                $pdo->prepare("UPDATE sb_matches SET status='ended' WHERE status='inplay' AND id NOT IN ($ph)")
                    ->execute($live_ids);
            } catch (Exception $e) {}
        }
    }
}
EOF;

$live_new = <<<'EOF'
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

    // Parse stream into a map of FI => odds
    $odds_map = [];
    $curr_fi = null;
    $odds_h = null; $odds_x = null; $odds_a = null;
    $ou_over = null; $ou_under = null;

    foreach ($stream as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? $item['TYPE'] ?? '';

        if ($type === 'EV' || $type === 'Event' || !empty($item['FI'])) {
            if ($curr_fi && $odds_h && $odds_x && $odds_a) {
                $odds_map[$curr_fi] = ['h'=>$odds_h,'x'=>$odds_x,'a'=>$odds_a,'ou_line'=>2.5,'ou_over'=>$ou_over,'ou_under'=>$ou_under,'ts'=>time()];
            }
            $curr_fi = $item['FI'] ?? $item['OI'] ?? null;
            $odds_h = $odds_x = $odds_a = $ou_over = $ou_under = null;
        }

        if ($type === 'PA' && $curr_fi) {
            $n2  = $item['N2'] ?? $item['NA'] ?? '';
            $or  = $item['OR'] ?? ''; 
            $dec = frac_to_dec($item['OD'] ?? '');
            if (!$dec || $dec < 1.01) continue;
            
            if (($n2 === '1' || $or === '0') && !$odds_h) $odds_h = $dec;
            if (($n2 === 'X' || $or === '1') && !$odds_x) $odds_x = $dec;
            if (($n2 === '2' || $or === '2') && !$odds_a) $odds_a = $dec;
            
            if ((stripos($n2,'over')  !== false) && !$ou_over)  $ou_over  = $dec;
            if ((stripos($n2,'under') !== false) && !$ou_under) $ou_under = $dec;
        }
    }
    if ($curr_fi && $odds_h && $odds_x && $odds_a) {
        $odds_map[$curr_fi] = ['h'=>$odds_h,'x'=>$odds_x,'a'=>$odds_a,'ou_line'=>2.5,'ou_over'=>$ou_over,'ou_under'=>$ou_under,'ts'=>time()];
    }

    // 2. Fetch match objects per sport using inplay_filter and inject the odds
    $live_ids = [];
    foreach (ALL_SPORTS as $sid => $sname) {
        $live = api_get('/v1/bet365/inplay_filter', ['sport_id' => $sid]);
        if (empty($live)) { echo "  [$sname] No live matches\n"; continue; }
        
        $pdo->beginTransaction();
        $n = 0;
        foreach ($live as &$m) {
            $fi = str_replace('C1A', '', $m['r_id'] ?? '');
            if ($fi && isset($odds_map[$fi])) {
                $m['live_odds'] = $odds_map[$fi];
                $odds_fetched++;
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
EOF;

// Replace Upcoming mode block
$upcoming_old = <<<'EOF'
// ═══════════════════════════════════════════════════════════
// MODE: UPCOMING — Sync upcoming match schedules
// ═══════════════════════════════════════════════════════════
if ($mode === 'upcoming' || $mode === 'full') {
    $pages = ($mode === 'full') ? 10 : 4; // Full: 10 pages (500 matches), Quick: 4 pages
    echo "[UPCOMING] Syncing upcoming matches ($pages pages/sport)...\n";

    foreach (ALL_SPORTS as $sid => $sname) {
        $all = [];
        for ($pg = 1; $pg <= $pages; $pg++) {
            $res = api_get('/v1/bet365/upcoming', ['sport_id' => $sid, 'page' => $pg]);
            if (empty($res)) break;
            $all = array_merge($all, $res);
            if (count($res) < 50) break;
        }
        if (empty($all)) { echo "  [$sname] No upcoming\n"; continue; }

        $pdo->beginTransaction();
        $n = 0;
        foreach ($all as $m) {
            if (save_match($upsert, $m, $sid)) $n++;
        }
        $pdo->commit();
        echo "  [$sname] $n upcoming\n";
        $saved += $n;
    }
}
EOF;

$upcoming_new = <<<'EOF'
// ═══════════════════════════════════════════════════════════
// MODE: UPCOMING — Sync upcoming match schedules
// ═══════════════════════════════════════════════════════════
if ($mode === 'upcoming' || $mode === 'full') {
    $pages = ($mode === 'full') ? 10 : 4; 
    echo "[UPCOMING] Syncing upcoming matches ($pages pages/sport)...\n";

    // Premium leagues that we want to fetch full prematch odds for!
    $premium_leagues = [
        'champions league', 'premier league', 'la liga', 'serie a', 'bundesliga', 
        'ligue 1', 'europa league', 'world cup', 'nba'
    ];

    foreach (ALL_SPORTS as $sid => $sname) {
        $all = [];
        for ($pg = 1; $pg <= $pages; $pg++) {
            $res = api_get('/v1/bet365/upcoming', ['sport_id' => $sid, 'page' => $pg]);
            if (empty($res)) break;
            $all = array_merge($all, $res);
            if (count($res) < 50) break;
        }
        if (empty($all)) { echo "  [$sname] No upcoming\n"; continue; }

        $pdo->beginTransaction();
        $n = 0; $odds_added = 0;
        foreach ($all as &$m) {
            $lname = strtolower($m['league']['name'] ?? '');
            $is_premium = false;
            foreach ($premium_leagues as $pl) {
                if (strpos($lname, $pl) !== false) { $is_premium = true; break; }
            }

            // If premium, fetch prematch odds from BetsAPI!
            if ($is_premium && isset($m['id'])) {
                // Fetch odds only if not already saved recently
                $odds_resp = api_get_full('/v1/bet365/prematch', ['FI' => $m['id']]);
                if ($odds_resp && isset($odds_resp['results'][0]['main']['sp']['full_time_result'])) {
                    $ftr = $odds_resp['results'][0]['main']['sp']['full_time_result'];
                    $m['live_odds'] = [
                        'h' => frac_to_dec($ftr[0]['odds'] ?? ''),
                        'x' => frac_to_dec($ftr[1]['odds'] ?? ''),
                        'a' => frac_to_dec($ftr[2]['odds'] ?? ''),
                        'ou_line' => 2.5,
                        'ou_over' => 0,
                        'ou_under' => 0,
                        'ts' => time()
                    ];
                    $odds_added++;
                }
            }

            if (save_match($upsert, $m, $sid)) $n++;
        }
        $pdo->commit();
        echo "  [$sname] $n upcoming (Prematch odds fetched for $odds_added premium matches)\n";
        $saved += $n;
    }
}
EOF;

$content = str_replace($live_old, $live_new, $content);
$content = str_replace($upcoming_old, $upcoming_new, $content);

file_put_contents($file, $content);
echo "Replaced content successfully.";
