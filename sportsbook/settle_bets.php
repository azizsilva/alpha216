<?php
/**
 * settle_bets.php — runs the automatic bet settlement engine.
 *
 * USAGE:
 *   CLI / cron (no auth):   php settle_bets.php
 *   PM2 loop (handled by ws_daemon spawning this every 60s)
 *   HTTP (admin only):      /sportsbook/settle_bets.php?key=SECRET
 *
 * It loads pending tickets, grades each against final scores, and pays out the
 * ones whose outcome is certain. Safe to run as often as you like (idempotent:
 * already-settled tickets are skipped under a row lock).
 */

$IS_CLI = (php_sapi_name() === 'cli');

// Shared secret for HTTP triggering (also accepted from ws_daemon).
if (!defined('SETTLE_SECRET')) define('SETTLE_SECRET', 'alp_settle_5b9c3e1f');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/bet_settle_lib.php';

if (!$IS_CLI) {
    header('Content-Type: application/json');
    $key = $_GET['key'] ?? ($_POST['key'] ?? '');
    $ok  = ($key === SETTLE_SECRET);
    if (!$ok) {
        // allow admin session as alternative
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!empty($_SESSION['user_id'])) {
            try {
                $s = $pdo->prepare("SELECT role FROM users WHERE id=?");
                $s->execute([$_SESSION['user_id']]);
                $role = $s->fetchColumn();
                $ok = in_array($role, ['admin','super_admin','provider'], true);
            } catch (\Throwable $e) {}
        }
    }
    if (!$ok) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'forbidden']); exit; }
}

$t0  = microtime(true);
$sum = sbset_run($pdo, ['limit' => 2000]);
$sum['elapsed_ms'] = round((microtime(true) - $t0) * 1000);

if ($IS_CLI) {
    echo sprintf(
        "[settle %s] checked=%d settled=%d (won=%d lost=%d refunded=%d) pending=%d paid=%.2f %dms\n",
        date('Y-m-d H:i:s'), $sum['checked'], $sum['settled'], $sum['won'], $sum['lost'],
        $sum['refunded'], $sum['pending'], $sum['paid'], $sum['elapsed_ms']
    );
} else {
    echo json_encode(['success'=>true, 'summary'=>$sum]);
}
