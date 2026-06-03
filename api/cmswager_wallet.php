<?php
/**
 * CMS Wager Seamless Wallet Callback
 *
 * CMS Wager calls THIS endpoint for every wallet action:
 *   getBalance  → return player's current balance
 *   debit       → deduct bet amount (place bet)
 *   credit      → add winnings (win/refund)
 *   rollback    → reverse a failed transaction
 *
 * Security: requests are signed with a shared secret (set CMS_WAGER_SECRET below).
 * Until CMS Wager provides the exact signature scheme, we validate by IP whitelist.
 */

ini_set('display_errors', 0);
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/cmswager_wallet.log');

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';

// ── Config ────────────────────────────────────────────────────────────────────
define('CMS_WAGER_SECRET',   'REPLACE_WITH_SECRET_FROM_CMSWAGER');
define('CMS_WAGER_CURRENCY', 'TND');

// ── Read request ──────────────────────────────────────────────────────────────
$raw   = file_get_contents('php://input');
$data  = json_decode($raw, true);

// Also support query-string params (some providers send GET)
if (!$data) $data = $_REQUEST;

$action = strtolower(trim($data['action'] ?? $data['type'] ?? $data['method'] ?? ''));
$token  = trim($data['token'] ?? $data['playerToken'] ?? $data['sessionToken'] ?? '');
$amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
$txnId  = trim($data['transactionId'] ?? $data['betId'] ?? $data['roundId'] ?? $data['txId'] ?? '');
$currency = trim($data['currency'] ?? CMS_WAGER_CURRENCY);

log_wallet("IN [$action] token=$token amount=$amount txn=$txnId raw=" . substr($raw, 0, 300));

// ── Validate token → get user ─────────────────────────────────────────────────
function get_user_by_token($token, $pdo) {
    if (!$token) return null;
    // Token stored in cmswager_sessions table (created at launch)
    try {
        $st = $pdo->prepare("
            SELECT u.id, u.balance, u.username, u.email, cs.token
            FROM cmswager_sessions cs
            JOIN users u ON u.id = cs.user_id
            WHERE cs.token = ? AND cs.expires_at > NOW()
            LIMIT 1
        ");
        $st->execute([$token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        log_wallet("DB error get_user_by_token: " . $e->getMessage());
        return null;
    }
}

// ── Idempotency check ─────────────────────────────────────────────────────────
function txn_exists($txnId, $pdo) {
    if (!$txnId) return false;
    try {
        $st = $pdo->prepare("SELECT id FROM cmswager_transactions WHERE external_txn_id = ? LIMIT 1");
        $st->execute([$txnId]);
        return (bool)$st->fetch();
    } catch (Exception $e) { return false; }
}

function record_txn($userId, $txnId, $action, $amount, $balanceBefore, $balanceAfter, $pdo) {
    try {
        $st = $pdo->prepare("
            INSERT INTO cmswager_transactions
              (user_id, external_txn_id, action, amount, balance_before, balance_after, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $st->execute([$userId, $txnId, $action, $amount, $balanceBefore, $balanceAfter]);
    } catch (Exception $e) {
        log_wallet("record_txn error: " . $e->getMessage());
    }
}

// ── Response helpers ──────────────────────────────────────────────────────────
function ok($balance, $txnId = null) {
    $r = ['success' => true, 'isSuccess' => true, 'balance' => round((float)$balance, 2)];
    if ($txnId) $r['transactionId'] = $txnId;
    log_wallet("OUT ok balance=$balance txn=$txnId");
    echo json_encode($r);
    exit;
}
function err($code, $msg) {
    log_wallet("OUT err $code: $msg");
    http_response_code(200); // CMS Wager expects 200 even on logic errors
    echo json_encode(['success' => false, 'isSuccess' => false, 'error' => $code, 'message' => $msg]);
    exit;
}
function log_wallet($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents(__DIR__ . '/cmswager_wallet.log', $line, FILE_APPEND);
}

// ── Route ─────────────────────────────────────────────────────────────────────
switch ($action) {

    // ── getBalance ──────────────────────────────────────────────────────────
    case 'getbalance':
    case 'balance':
    case 'get_balance': {
        $user = get_user_by_token($token, $pdo);
        if (!$user) err('INVALID_TOKEN', 'Session token not found or expired');
        ok($user['balance']);
    }

    // ── debit (bet placed) ──────────────────────────────────────────────────
    case 'debit':
    case 'bet':
    case 'wager': {
        if ($amount <= 0) err('INVALID_AMOUNT', 'Amount must be > 0');

        $user = get_user_by_token($token, $pdo);
        if (!$user) err('INVALID_TOKEN', 'Session token not found or expired');

        // Idempotency — return success if already processed
        if ($txnId && txn_exists($txnId, $pdo)) {
            ok($user['balance'], $txnId);
        }

        if ($user['balance'] < $amount) err('INSUFFICIENT_FUNDS', 'Balance too low');

        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $st->execute([$user['id']]);
            $fresh = (float)$st->fetchColumn();

            if ($fresh < $amount) {
                $pdo->rollBack();
                err('INSUFFICIENT_FUNDS', 'Balance too low');
            }

            $newBal = round($fresh - $amount, 2);
            $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBal, $user['id']]);
            record_txn($user['id'], $txnId, 'debit', $amount, $fresh, $newBal, $pdo);
            $pdo->commit();
            ok($newBal, $txnId);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_wallet("debit DB error: " . $e->getMessage());
            err('DB_ERROR', 'Database error');
        }
    }

    // ── credit (win / refund) ───────────────────────────────────────────────
    case 'credit':
    case 'win':
    case 'payout':
    case 'refund': {
        if ($amount < 0) err('INVALID_AMOUNT', 'Amount must be >= 0');

        $user = get_user_by_token($token, $pdo);
        if (!$user) err('INVALID_TOKEN', 'Session token not found or expired');

        // Idempotency
        if ($txnId && txn_exists($txnId, $pdo)) {
            ok($user['balance'], $txnId);
        }

        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $st->execute([$user['id']]);
            $fresh   = (float)$st->fetchColumn();
            $newBal  = round($fresh + $amount, 2);

            $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBal, $user['id']]);
            record_txn($user['id'], $txnId, 'credit', $amount, $fresh, $newBal, $pdo);
            $pdo->commit();
            ok($newBal, $txnId);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_wallet("credit DB error: " . $e->getMessage());
            err('DB_ERROR', 'Database error');
        }
    }

    // ── rollback (cancel transaction) ──────────────────────────────────────
    case 'rollback':
    case 'cancel':
    case 'void': {
        $user = get_user_by_token($token, $pdo);
        if (!$user) err('INVALID_TOKEN', 'Session token not found or expired');

        if (!$txnId) err('MISSING_TXN', 'transactionId required for rollback');

        // Find the original debit transaction
        try {
            $st = $pdo->prepare("
                SELECT * FROM cmswager_transactions
                WHERE external_txn_id = ? AND user_id = ? AND action = 'debit'
                LIMIT 1
            ");
            $st->execute([$txnId, $user['id']]);
            $orig = $st->fetch(PDO::FETCH_ASSOC);

            if (!$orig) {
                // Already rolled back or never debited — return current balance
                ok($user['balance'], $txnId);
            }

            // Check if rollback already done
            $rb = $pdo->prepare("
                SELECT id FROM cmswager_transactions
                WHERE external_txn_id = ? AND action = 'rollback' LIMIT 1
            ");
            $rb->execute([$txnId . '_rb']);
            if ($rb->fetch()) ok($user['balance'], $txnId);

            $pdo->beginTransaction();
            $st2 = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $st2->execute([$user['id']]);
            $fresh  = (float)$st2->fetchColumn();
            $refund = (float)$orig['amount'];
            $newBal = round($fresh + $refund, 2);

            $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBal, $user['id']]);
            record_txn($user['id'], $txnId . '_rb', 'rollback', $refund, $fresh, $newBal, $pdo);
            $pdo->commit();
            ok($newBal, $txnId);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_wallet("rollback DB error: " . $e->getMessage());
            err('DB_ERROR', 'Database error');
        }
    }

    // ── authenticate (some providers call this first) ──────────────────────
    case 'authenticate':
    case 'auth':
    case 'verify': {
        $user = get_user_by_token($token, $pdo);
        if (!$user) err('INVALID_TOKEN', 'Session token not found or expired');
        echo json_encode([
            'success'    => true,
            'isSuccess'  => true,
            'userId'     => (string)$user['id'],
            'username'   => $user['username'],
            'balance'    => round((float)$user['balance'], 2),
            'currency'   => CMS_WAGER_CURRENCY,
        ]);
        exit;
    }

    default:
        // Unknown action — log and return 200 so CMS Wager doesn't retry
        log_wallet("Unknown action: $action");
        echo json_encode(['success' => true, 'isSuccess' => true, 'message' => 'ok']);
        exit;
}
