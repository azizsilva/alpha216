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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['success' => false, 'message' => 'Invalid request method.']);
}

$user_id = (int)$_SESSION['user_id'];
$amount = (float)($_POST['amount'] ?? 0);
$bank_slot = (int)($_POST['bank_slot'] ?? 0);

if ($amount <= 0) out(['success' => false, 'message' => 'Amount must be positive.']);
if ($amount > 100000000) out(['success' => false, 'message' => 'Amount too large.']);
if (!in_array($bank_slot, [1,2,3], true)) out(['success' => false, 'message' => 'Select a bank.']);

try {
    $stmt = $pdo->prepare("SELECT id, balance, parent_id FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) out(['success' => false, 'message' => 'User not found.']);

    $balance = (float)($u['balance'] ?? 0);
    if ($balance < $amount) out(['success' => false, 'message' => 'Insufficient balance.']);

    $stmt = $pdo->prepare("SELECT * FROM user_withdraw_banks WHERE user_id=? AND bank_slot=? AND enabled=1");
    $stmt->execute([$user_id, $bank_slot]);
    $bank = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bank) out(['success' => false, 'message' => 'Bank details not found.']);

    $agent_id = (int)($u['parent_id'] ?? 0);
    $payee_id = null;
    if ($agent_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='agent'");
        $stmt->execute([$agent_id]);
        $payee_id = $stmt->fetchColumn() ? $agent_id : null;
    }

    $meta = [
        'kind' => 'withdraw_request',
        'bank_slot' => $bank_slot,
        'bank_name' => (string)($bank['bank_name'] ?? ''),
        'ifsc_swift' => (string)($bank['ifsc_swift'] ?? ''),
        'account_no' => (string)($bank['account_no'] ?? ''),
        'account_holder' => (string)($bank['account_holder'] ?? ''),
        'submitted_from' => 'site'
    ];

    $ref = 'WD-' . $user_id . '-' . $bank_slot;

    $stmt = $pdo->prepare("INSERT INTO payments (payer_id, payee_id, created_by, mode_id, type, amount, fee_percent, fee_flat, status, reference, note, meta_json)
        VALUES (?, ?, ?, NULL, 'withdrawal', ?, 0.000, 0.00, 'pending', ?, NULL, ?)");
    $stmt->execute([
        $user_id,
        $payee_id,
        $user_id,
        $amount,
        $ref,
        json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ]);

    out(['success' => true, 'message' => 'Withdraw request submitted.', 'id' => (int)$pdo->lastInsertId()]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Server error.']);
}

