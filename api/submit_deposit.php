<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

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
$txn_id = trim((string)($_POST['txn_id'] ?? ''));
$method_id = (int)($_POST['method_id'] ?? 0);

if ($amount <= 0) out(['success' => false, 'message' => 'Amount must be positive.']);
if ($amount > 100000000) out(['success' => false, 'message' => 'Amount too large.']);
if ($txn_id === '' || strlen($txn_id) > 100) out(['success' => false, 'message' => 'Txn ID is required.']);
if ($method_id <= 0) out(['success' => false, 'message' => 'Select a payment method.']);

try {
    $stmt = $pdo->prepare("SELECT parent_id FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $agent_id = (int)($stmt->fetchColumn() ?? 0);

    $method = null;
    $method_source = '';

    if ($agent_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM player_deposit_methods WHERE id=? AND agent_id=? AND enabled=1");
        $stmt->execute([$method_id, $agent_id]);
        $method = $stmt->fetch(PDO::FETCH_ASSOC);
        $method_source = 'player_deposit_methods';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE id=? AND user_id=? AND enabled=1");
        $stmt->execute([$method_id, $user_id]);
        $method = $stmt->fetch(PDO::FETCH_ASSOC);
        $method_source = 'user_deposit_methods';
        if (!$method) {
            $stmt = $pdo->prepare("SELECT * FROM deposit_methods WHERE id=? AND target_role='master' AND enabled=1");
            $stmt->execute([$method_id]);
            $method = $stmt->fetch(PDO::FETCH_ASSOC);
            $method_source = 'deposit_methods';
        }
    }

    if (!$method) out(['success' => false, 'message' => 'Invalid payment method.']);

    [$proof_path, $err] = mk_save_proof_upload($_FILES['proof_file'] ?? null, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'proofs', 'uploads/proofs');
    if ($err !== '') out(['success' => false, 'message' => $err]);

    $details = [];
    if (!empty($method['details_json'])) {
        $tmp = json_decode((string)$method['details_json'], true);
        if (is_array($tmp)) $details = $tmp;
    }

    $meta = [
        'method_source' => $method_source,
        'method_id' => (int)$method['id'],
        'method_label' => (string)($method['label'] ?? ''),
        'method_channel' => (string)($method['channel'] ?? ''),
        'method_details' => $details,
        'proof_path' => $proof_path,
        'submitted_from' => 'site'
    ];

    $payee_id = $agent_id > 0 ? $agent_id : null;

    $stmt = $pdo->prepare("INSERT INTO payments (payer_id, payee_id, created_by, mode_id, type, amount, fee_percent, fee_flat, status, reference, note, meta_json)
        VALUES (?, ?, ?, NULL, 'deposit', ?, 0.000, 0.00, 'pending', ?, NULL, ?)");
    $stmt->execute([
        $user_id,
        $payee_id,
        $user_id,
        $amount,
        $txn_id,
        json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ]);

    out(['success' => true, 'message' => 'Deposit request submitted.', 'id' => (int)$pdo->lastInsertId()]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Server error.']);
}

