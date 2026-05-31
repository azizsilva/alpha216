<?php
/**
 * bet_settle_lib.php — Automatic bet settlement engine (graded from final scores).
 *
 * Pure library: defines functions only, NO output, NO session. Include it from
 * settle_bets.php (CLI/cron) or api.php (admin-triggered run).
 *
 * Flow:
 *   1. Load pending tickets from `sportsbook_bets`.
 *   2. For each selection, resolve the match's FINAL score from:
 *         Redis  sb:final:{id}  (durable 7-day snapshot written by ws_daemon)
 *      → Redis  sb:ev:{id}      (only if time_status == '3')
 *      → MySQL  sb_matches      (status 'ended', score / raw_json.ss)
 *   3. Grade each leg against the full-time score for the markets we can settle
 *      with certainty (1X2, Double chance, Total, BTTS, Odd/Even, Draw No Bet,
 *      Correct score). Anything else stays 'unknown' → ticket left pending for
 *      manual settlement (never auto-paid wrongly).
 *   4. Combine legs per ticket mode and pay out (balance + transaction + GGR).
 *
 * Money safety: a ticket is only auto-settled when its outcome is CERTAIN.
 * Ungradeable markets (corners, cards, half-time, handicap, props, system bets)
 * are deliberately left pending so an admin can settle them via settle.php.
 */

if (!function_exists('sbset_redis_raw')) {

/* ── Redis access (phpredis if present, else raw RESP over fsockopen) ────────── */
function sbset_redis_conn() {
    static $conn = null, $tried = false;
    if ($tried) return $conn;
    $tried = true;
    if (class_exists('Redis')) {
        try { $r = new Redis(); if (@$r->connect('127.0.0.1', 6379, 0.5)) { $conn = $r; return $conn; } } catch (\Throwable $e) {}
    }
    // raw socket fallback
    $fp = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 1.0);
    if ($fp) { $conn = ['fp' => $fp]; }
    return $conn;
}
function sbset_resp_read($fp) {
    $line = @fgets($fp, 8192);
    if ($line === false || $line === '') return null;
    $t = $line[0]; $d = rtrim(substr($line, 1));
    switch ($t) {
        case '+': return $d;
        case '-': return null;
        case ':': return (int)$d;
        case '$':
            $len = (int)$d; if ($len === -1) return null;
            $buf = '';
            while (strlen($buf) < $len + 2) { $c = @fread($fp, $len + 2 - strlen($buf)); if ($c === false || $c === '') break; $buf .= $c; }
            return substr($buf, 0, $len);
        case '*':
            $cnt = (int)$d; if ($cnt === -1) return null;
            $arr = []; for ($i = 0; $i < $cnt; $i++) $arr[] = sbset_resp_read($fp);
            return $arr;
    }
    return null;
}
function sbset_resp_cmd($fp, $parts) {
    $req = '*' . count($parts) . "\r\n";
    foreach ($parts as $p) { $p = (string)$p; $req .= '$' . strlen($p) . "\r\n" . $p . "\r\n"; }
    if (@fwrite($fp, $req) === false) return null;
    return sbset_resp_read($fp);
}
function sbset_redis_raw($cmd, $args) {
    $c = sbset_redis_conn();
    if (!$c) return null;
    if ($c instanceof Redis) {
        try {
            switch ($cmd) {
                case 'GET':      return $c->get($args[0]);
                case 'SMEMBERS': $r = $c->sMembers($args[0]); return is_array($r) ? $r : [];
            }
        } catch (\Throwable $e) { return null; }
        return null;
    }
    return sbset_resp_cmd($c['fp'], array_merge([$cmd], $args));
}
function sbset_redis_get($key)      { return sbset_redis_raw('GET', [$key]); }
function sbset_redis_smembers($key) { $r = sbset_redis_raw('SMEMBERS', [$key]); return is_array($r) ? $r : []; }

/* ── String helpers ─────────────────────────────────────────────────────────── */
function sbset_norm($s) {
    $s = (string)$s;
    $s = strtr($s, [
        'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i',
        'ô'=>'o','ö'=>'o','û'=>'u','ù'=>'u','ü'=>'u','ç'=>'c','’'=>"'",
        'À'=>'a','Â'=>'a','É'=>'e','È'=>'e','Ê'=>'e','Î'=>'i','Ô'=>'o','Û'=>'u','Ç'=>'c',
    ]);
    $s = strtolower($s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

/* ── Parse a final score string "2-1" / "2:1" → [home, away] ints or null ────── */
function sbset_parse_score($ss) {
    if (!is_string($ss) || $ss === '') return null;
    if (!preg_match('/(\d+)\s*[-:]\s*(\d+)/', $ss, $m)) return null;
    return [(int)$m[1], (int)$m[2]];
}

/* ── Resolve a finished match result. Returns:
 *     ['finished'=>true,'h'=>int,'a'=>int,'sport_id'=>str]  when settled,
 *     ['finished'=>false]                                   when still running,
 *     null                                                  when unknown/not found.
 */
function sbset_get_result($pdo, $matchId) {
    $matchId = (string)$matchId;
    if ($matchId === '') return null;

    // 1) durable final snapshot
    $raw = sbset_redis_get("sb:final:{$matchId}");
    if ($raw) {
        $ev = json_decode($raw, true);
        if (is_array($ev)) {
            $sc = sbset_parse_score($ev['ss'] ?? '');
            if ($sc) return ['finished'=>true, 'h'=>$sc[0], 'a'=>$sc[1], 'sport_id'=>(string)($ev['sport_id'] ?? '1')];
        }
    }
    // 2) live event still in Redis and marked finished
    $raw = sbset_redis_get("sb:ev:{$matchId}");
    if ($raw) {
        $ev = json_decode($raw, true);
        if (is_array($ev)) {
            $ts = (string)($ev['time_status'] ?? '');
            $sc = sbset_parse_score($ev['ss'] ?? '');
            if ($ts === '3' && $sc) return ['finished'=>true, 'h'=>$sc[0], 'a'=>$sc[1], 'sport_id'=>(string)($ev['sport_id'] ?? '1')];
            // present but not finished → still running
            return ['finished'=>false];
        }
    }
    // 3) MySQL durable fallback
    try {
        $st = $pdo->prepare("SELECT sport_id, status, score, raw_json FROM sb_matches WHERE id=? LIMIT 1");
        $st->execute([$matchId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $status = strtolower((string)($row['status'] ?? ''));
            $sc = sbset_parse_score($row['score'] ?? '');
            if (!$sc && !empty($row['raw_json'])) {
                $rj = json_decode($row['raw_json'], true);
                if (is_array($rj)) $sc = sbset_parse_score($rj['ss'] ?? ($rj['score'] ?? ''));
            }
            if (in_array($status, ['ended','finished','settled'], true)) {
                if ($sc) return ['finished'=>true, 'h'=>$sc[0], 'a'=>$sc[1], 'sport_id'=>(string)($row['sport_id'] ?? '1')];
                return null; // ended but no parseable score → manual
            }
            return ['finished'=>false];
        }
    } catch (\Throwable $e) {}

    return null;
}

/* ── Resolve a leg's matchId from its stored fields ─────────────────────────── */
function sbset_leg_match_id($leg) {
    if (!empty($leg['matchId'])) return (string)$leg['matchId'];
    $id = (string)($leg['id'] ?? '');
    if ($id !== '') {
        $head = explode('_', $id)[0];
        if (preg_match('/^\d+$/', $head)) return $head;
        if (preg_match('/(\d{4,})/', $id, $m)) return $m[1];
    }
    return '';
}

/* ── Grade ONE leg against a final score.
 *   Returns 'won' | 'lost' | 'void' | 'unknown'.
 *   'unknown' = market we won't auto-grade → ticket stays pending.
 */
function sbset_grade_leg($market, $sel, $res) {
    $h = (int)$res['h']; $a = (int)$res['a']; $tot = $h + $a;
    $m = sbset_norm($market);
    $s = sbset_norm($sel);

    // Families we explicitly cannot settle from a full-time score.
    $isBlocked = preg_match('/mi-temps|mi temps|1ere|2eme|half|corner|carton|card|booking|tir|shot|offside|hors-jeu|penalty|remplac|joueur|player|scorer|goalscorer|but\/|race|2-way|specials|handicap/u', $m);

    /* Correct score / Score exact */
    if (preg_match('/score exact|correct score/u', $m)) {
        if (preg_match('/(\d+)\D+(\d+)/', $s, $mm)) {
            return ((int)$mm[1] === $h && (int)$mm[2] === $a) ? 'won' : 'lost';
        }
        return 'unknown';
    }

    /* Double chance */
    if (preg_match('/double chance/u', $m)) {
        $has1 = (strpos($s, '1') !== false);
        $has2 = (strpos($s, '2') !== false);
        $hasX = (strpos($s, 'x') !== false || strpos($s, 'nul') !== false);
        if ($has1 && $hasX && !$has2) return ($h >= $a) ? 'won' : 'lost'; // 1X
        if ($has1 && $has2 && !$hasX) return ($h !== $a) ? 'won' : 'lost'; // 12
        if ($hasX && $has2 && !$has1) return ($a >= $h) ? 'won' : 'lost'; // X2
        return 'unknown';
    }

    /* Draw No Bet / Remboursé si nul */
    if (preg_match('/rembourse si nul|draw no bet|dnb/u', $m)) {
        if ($h === $a) return 'void';
        if ($s === '1' || strpos($s, '1') === 0) return ($h > $a) ? 'won' : 'lost';
        if ($s === '2' || strpos($s, '2') === 0) return ($a > $h) ? 'won' : 'lost';
        return 'unknown';
    }

    /* Both teams to score */
    if (preg_match('/deux equipes|btts|both teams/u', $m)) {
        $yes = ($h > 0 && $a > 0);
        if (preg_match('/oui|yes|gg|^g/u', $s)) return $yes ? 'won' : 'lost';
        if (preg_match('/non|^no|ng/u', $s))    return $yes ? 'lost' : 'won';
        return 'unknown';
    }

    /* Odd / Even */
    if (preg_match('/pair|impair|odd|even/u', $m)) {
        $odd = ($tot % 2) === 1;
        if (preg_match('/impair|odd/u', $s)) return $odd ? 'won' : 'lost';
        if (preg_match('/pair|even/u', $s))  return $odd ? 'lost' : 'won';
        return 'unknown';
    }

    /* Total goals (full match only — exclude team/corner/card/half totals) */
    if (!$isBlocked && (preg_match('/^total( de buts)?$/u', $m) || preg_match('/over.?under|plus.?moins|nombre de buts/u', $m))
        && !preg_match('/equipe|team/u', $m)) {
        if (preg_match('/(\d+(?:[.,]\d+)?)/', $s, $mm)) {
            $line = (float)str_replace(',', '.', $mm[1]);
            $over  = preg_match('/plus|over|sup|\+|>/u', $s);
            $under = preg_match('/moins|under|inf|<|^-/u', $s);
            if (!$over && !$under) return 'unknown';
            if ($over)  { if ($tot > $line) return 'won'; if ($tot == $line) return 'void'; return 'lost'; }
            else        { if ($tot < $line) return 'won'; if ($tot == $line) return 'void'; return 'lost'; }
        }
        return 'unknown';
    }

    /* 1X2 / Result / Moneyline (full-time) — only if not a blocked family */
    if (!$isBlocked && (preg_match('/1\s?x\s?2|resultat|moneyline|^ml$|vainqueur/u', $m)
        || (in_array($s, ['1','x','2','nul','match nul'], true) && !preg_match('/equipe|total/u', $m)))) {
        if ($s === '1') return ($h > $a) ? 'won' : 'lost';
        if ($s === 'x' || $s === 'nul' || $s === 'match nul') return ($h === $a) ? 'won' : 'lost';
        if ($s === '2') return ($a > $h) ? 'won' : 'lost';
        return 'unknown';
    }

    return 'unknown';
}

/* ── Grade a Bet Builder / same-game item from its legs[] ───────────────────── */
function sbset_grade_bb($item, $res) {
    $anyLost = false; $anyVoid = false; $anyUnknown = false; $n = 0;
    foreach (($item['legs'] ?? []) as $leg) {
        $n++;
        $g = sbset_grade_leg($leg['market'] ?? '', $leg['name'] ?? ($leg['sel'] ?? ''), $res);
        if ($g === 'lost') $anyLost = true;
        elseif ($g === 'void') $anyVoid = true;
        elseif ($g === 'unknown') $anyUnknown = true;
    }
    if (!$n) return 'unknown';
    if ($anyLost) return 'lost';
    if ($anyUnknown || $anyVoid) return 'unknown'; // BB void recomputation → manual
    return 'won';
}

/* ── Decide a ticket outcome. Returns:
 *     ['ready'=>true,'result'=>'won'|'lost'|'refunded','payout'=>float] OR
 *     ['ready'=>false]  (leave pending)
 */
function sbset_grade_ticket($pdo, $bet) {
    $slip = json_decode($bet['slip'] ?? '[]', true);
    if (!is_array($slip) || !count($slip)) return ['ready'=>false];

    $mode = strtolower(trim((string)($bet['mode'] ?? '')));
    if ($mode === '' ) {
        // infer: product of leg odds ≈ stored total_odds → combi, else simple
        $prod = 1.0; $cnt = 0;
        foreach ($slip as $it) { $prod *= (float)($it['val'] ?? 1); $cnt++; }
        $mode = ($cnt > 1 && abs($prod - (float)$bet['total_odds']) < 0.05) ? 'combi' : ($cnt > 1 ? 'simple' : 'combi');
    }
    if ($mode === 'system') return ['ready'=>false]; // system payouts → manual only

    $amount = (float)$bet['amount'];

    // Grade each slip item
    $items = [];
    foreach ($slip as $it) {
        $mid = sbset_leg_match_id($it);
        $res = $mid !== '' ? sbset_get_result($pdo, $mid) : null;
        if ($res === null || empty($res['finished'])) {
            $items[] = ['g'=>'pending'];
            continue;
        }
        if (!empty($it['isBB']) && !empty($it['legs'])) {
            $g = sbset_grade_bb($it, $res);
        } else {
            $g = sbset_grade_leg($it['market'] ?? '', $it['sel'] ?? '', $res);
        }
        $items[] = ['g'=>$g, 'odds'=>(float)($it['val'] ?? 1), 'stake'=>(float)($it['stake'] ?? 0)];
    }

    if ($mode === 'combi') {
        // any leg lost → ticket lost immediately (even if others unfinished)
        foreach ($items as $x) if ($x['g'] === 'lost') return ['ready'=>true,'result'=>'lost','payout'=>0.0];
        // not ready if anything still pending or ungradeable
        foreach ($items as $x) if ($x['g'] === 'pending' || $x['g'] === 'unknown') return ['ready'=>false];
        // all won/void
        $allVoid = true; $odds = 1.0;
        foreach ($items as $x) {
            if ($x['g'] === 'won')  { $allVoid = false; $odds *= ($x['odds'] > 1 ? $x['odds'] : 1.0); }
            // void → odds factor 1.0
            else if ($x['g'] === 'void') { /* keep */ }
        }
        if ($allVoid) return ['ready'=>true,'result'=>'refunded','payout'=>$amount];
        $hasVoid = false; foreach ($items as $x) if ($x['g'] === 'void') $hasVoid = true;
        $payout = ($hasVoid) ? ($amount * $odds) : (float)$bet['potential_returns'];
        return ['ready'=>true,'result'=>'won','payout'=>round($payout, 2)];
    }

    // simple: every item must be resolved (finished + gradeable)
    foreach ($items as $x) if ($x['g'] === 'pending' || $x['g'] === 'unknown') return ['ready'=>false];
    $legCount = count($items);
    $defStake = $legCount ? ($amount / $legCount) : 0;
    $payout = 0.0; $allVoid = true; $anyWon = false;
    foreach ($items as $x) {
        $stk = $x['stake'] > 0 ? $x['stake'] : $defStake;
        if ($x['g'] === 'won')  { $payout += $stk * ($x['odds'] > 1 ? $x['odds'] : 1.0); $allVoid = false; $anyWon = true; }
        elseif ($x['g'] === 'void') { $payout += $stk; }
        else { $allVoid = false; } // lost
    }
    if ($allVoid)        return ['ready'=>true,'result'=>'refunded','payout'=>round($amount, 2)];
    if ($payout <= 0.0)  return ['ready'=>true,'result'=>'lost','payout'=>0.0];
    return ['ready'=>true,'result'=>'won','payout'=>round($payout, 2)];
}

/* ── Persist a ticket outcome (balance + transaction + GGR + status) ────────── */
function sbset_apply_settlement($pdo, $bet, $result, $payout) {
    $bet_id = (int)$bet['id'];
    $pdo->beginTransaction();
    try {
        // Re-check it's still pending under lock to avoid double-pay
        $st = $pdo->prepare("SELECT status FROM sportsbook_bets WHERE id=? FOR UPDATE");
        $st->execute([$bet_id]);
        $cur = $st->fetchColumn();
        if ($cur !== 'pending') { $pdo->rollBack(); return false; }

        $payout = (float)$payout;
        $ggr = (float)$bet['amount'] - $payout;
        if ($result === 'refunded') $ggr = 0.0;

        if ($payout > 0) {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id=?")
                ->execute([$payout, $bet['user_id']]);
            $desc = ($result === 'refunded' ? 'Remboursement Pari - Ticket #' : 'Gain Sportif - Ticket #') . $bet_id;
            $pdo->prepare("INSERT INTO transactions (sender_id,receiver_id,amount,type,description) VALUES (0,?,?,'deposit',?)")
                ->execute([$bet['user_id'], $payout, $desc]);
        }

        $pdo->prepare("UPDATE sportsbook_bets SET status=?, settled_at=NOW() WHERE id=?")
            ->execute([$result, $bet_id]);

        $pdo->prepare("INSERT INTO sportsbook_ggr (bet_id,user_id,stake,payout,ggr,result) VALUES (?,?,?,?,?,?)")
            ->execute([$bet_id, $bet['user_id'], $bet['amount'], $payout, $ggr, $result]);

        $pdo->commit();
        return true;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}

/* ── Ensure tables / columns the engine relies on exist ─────────────────────── */
function sbset_ensure_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sportsbook_ggr (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bet_id INT NOT NULL, user_id INT NOT NULL,
        stake DECIMAL(15,2) NOT NULL, payout DECIMAL(15,2) DEFAULT 0,
        ggr DECIMAL(15,2) NOT NULL, result ENUM('won','lost','refunded') NOT NULL,
        settled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(settled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM sportsbook_bets")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        if (!in_array('mode', $cols, true))       $pdo->exec("ALTER TABLE sportsbook_bets ADD COLUMN mode VARCHAR(16) NOT NULL DEFAULT 'simple'");
        if (!in_array('settled_at', $cols, true)) $pdo->exec("ALTER TABLE sportsbook_bets ADD COLUMN settled_at DATETIME NULL");
    } catch (\Throwable $e) {}
}

/* ── Main entry: settle all pending tickets that are ready ──────────────────── */
function sbset_run($pdo, $opts = []) {
    $limit = (int)($opts['limit'] ?? 1000);
    sbset_ensure_schema($pdo);

    $sum = ['checked'=>0,'settled'=>0,'won'=>0,'lost'=>0,'refunded'=>0,'pending'=>0,'paid'=>0.0];

    $st = $pdo->prepare("SELECT * FROM sportsbook_bets WHERE status='pending' ORDER BY created_at ASC LIMIT ?");
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    $bets = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bets as $bet) {
        $sum['checked']++;
        $g = sbset_grade_ticket($pdo, $bet);
        if (empty($g['ready'])) { $sum['pending']++; continue; }
        $ok = sbset_apply_settlement($pdo, $bet, $g['result'], $g['payout']);
        if ($ok) {
            $sum['settled']++;
            $sum[$g['result']]++;
            $sum['paid'] += (float)$g['payout'];
        }
    }
    return $sum;
}

} // function_exists guard
