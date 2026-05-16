<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function out($payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    out(['success' => false, 'message' => 'User not logged in.']);
}

$user_id = (int)$_SESSION['user_id'];
$wallet = (string)($_GET['wallet'] ?? 'all');
$type = (string)($_GET['type'] ?? 'all');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');

$limit = (int)($_GET['limit'] ?? 200);
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;
$offset = (int)($_GET['offset'] ?? 0);
if ($offset < 0) $offset = 0;

try {
    $stmt = $pdo->prepare("SELECT balance, credit_ref FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $current_balance = (float)($u['balance'] ?? 0);
    $credit_ref = (float)($u['credit_ref'] ?? 0);

    $where = "receiver_id = ?";
    $params = [$user_id];

    if ($type === 'deposit' || $type === 'withdrawal') {
        $where .= " AND type = ?";
        $params[] = $type;
    }

    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where .= " AND DATE(created_at) >= ?";
        $params[] = $from;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where .= " AND DATE(created_at) <= ?";
        $params[] = $to;
    }

    $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id, amount, type, description, created_at
                           FROM transactions
                           WHERE $where
                           ORDER BY id DESC
                           LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $txns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $running = $current_balance;
    $rows = [];
    foreach ($txns as $t) {
        $amt = (float)($t['amount'] ?? 0);
        $tType = (string)($t['type'] ?? '');
        $delta = 0.0;
        $deposit = '0.00';
        $withdraw = '0.00';

        if ($tType === 'deposit') {
            $delta = $amt;
            $deposit = number_format($amt, 2);
        } elseif ($tType === 'withdrawal') {
            $delta = -$amt;
            $withdraw = number_format($amt, 2);
        }

        $newBal = $running;
        $oldBal = $running - $delta;
        $running = $oldBal;

        $rows[] = [
            'deposit' => $deposit,
            'withdraw' => $withdraw,
            'balance' => number_format($newBal, 2),
            'remark' => (string)($t['description'] ?? ''),
            'date_time' => (string)($t['created_at'] ?? ''),
            'old_balance' => number_format($oldBal, 2),
            'credit_reference' => number_format($credit_ref, 2),
            'old_credit_reference' => number_format($credit_ref, 2),
            'ref_pl' => '0.00',
        ];
    }

    out(['success' => true, 'rows' => $rows, 'limit' => $limit, 'offset' => $offset]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Server error.']);
}

