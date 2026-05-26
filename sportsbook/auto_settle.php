<?php
/**
 * Automated Sportsbook Settlement Engine Daemon
 * Intended to be run via Cron: * * * * * php /path/to/auto_settle.php
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../local.env.php';

// BetsAPI Token
$api_token = $_ENV['BETSAPI_TOKEN'] ?? '184918-62dOQJ7G13uH4M'; 

echo "[".date('Y-m-d H:i:s')."] Starting Auto-Settle Daemon...\n";

// 1. Fetch Pending Bets
$stmt = $pdo->query("SELECT * FROM sportsbook_bets WHERE status='pending'");
$pending_bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pending_bets)) {
    echo "No pending bets to settle.\n";
    exit;
}

foreach ($pending_bets as $bet) {
    echo "Evaluating Bet #{$bet['id']} (User: {$bet['user_id']}, Stake: {$bet['amount']})...\n";
    
    $slip = json_decode($bet['slip'], true);
    if (!$slip || empty($slip)) continue;

    $all_won = true;
    $any_lost = false;
    $match_ended_count = 0;

    foreach ($slip as $leg) {
        // We assume leg['id'] contains the match ID in some format.
        // Example: 'event_12345_1x2' -> extract '12345'
        preg_match('/\d+/', $leg['id'], $matches);
        $match_id = $matches[0] ?? null;

        if (!$match_id) {
            echo "  [!] Could not parse match ID from leg: {$leg['id']}\n";
            $all_won = false;
            continue;
        }

        // --- SENIOR BACKEND FALLBACK LOGIC ---
        // For the sake of this platform, since we cannot parse every BetsAPI market string perfectly without a premium grade feed,
        // we leave the status as pending if we can't definitively grade it.
        // The Provider Dashboard 'Live Risk Tracker' will be the primary source of truth for grading.
        
        echo "  [*] Match ID {$match_id} identified. Awaiting official result feed integration.\n";
        $all_won = false; // Prevent premature payout
    }

    // Example Settlement Logic (Once Feed is active)
    if ($any_lost) {
        echo "  => Bet #{$bet['id']} LOST. Updating DB...\n";
        // House keeps the stake (GGR = positive)
        $pdo->prepare("UPDATE sportsbook_bets SET status='lost', settled_at=NOW() WHERE id=?")->execute([$bet['id']]);
        $pdo->prepare("INSERT INTO sportsbook_ggr (bet_id, user_id, stake, payout, ggr, result) VALUES (?,?,?,?,?,?)")
            ->execute([$bet['id'], $bet['user_id'], $bet['amount'], 0, $bet['amount'], 'lost']);
    } elseif ($all_won && $match_ended_count == count($slip)) {
        echo "  => Bet #{$bet['id']} WON! Processing Payout...\n";
        // Payout to User
        $pdo->prepare("UPDATE sportsbook_bets SET status='won', settled_at=NOW() WHERE id=?")->execute([$bet['id']]);
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$bet['potential_returns'], $bet['user_id']]);
        
        // Log GGR (negative = loss to house)
        $ggr = $bet['amount'] - $bet['potential_returns'];
        $pdo->prepare("INSERT INTO sportsbook_ggr (bet_id, user_id, stake, payout, ggr, result) VALUES (?,?,?,?,?,?)")
            ->execute([$bet['id'], $bet['user_id'], $bet['amount'], $bet['potential_returns'], $ggr, 'won']);
    } else {
        echo "  => Bet #{$bet['id']} remains PENDING.\n";
    }
}

echo "[".date('Y-m-d H:i:s')."] Daemon finished.\n";
