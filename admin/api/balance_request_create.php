<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';

require_admin_login($admin_base);
require_admin_role(['master','agent'], $admin_base);

header('Content-Type: application/json');

function out($payload) {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
if (!csrf_check($csrf)) out(['success' => false, 'message' => 'Invalid request.']);

$role = (string)current_admin_role();
$actor_id = (int)current_admin_id();

$amount = (float)($_POST['amount'] ?? 0);
$note = trim((string)($_POST['note'] ?? ''));
if ($amount <= 0) out(['success' => false, 'message' => 'Amount must be positive.']);
if ($amount > 100000000) out(['success' => false, 'message' => 'Amount too large.']);
if (strlen($note) > 255) $note = substr($note, 0, 255);

try {
    $parent_id = 0;
    $parent_role = '';
    $stmt = $pdo->prepare("SELECT parent_id FROM users WHERE id=?");
    $stmt->execute([$actor_id]);
    $parent_id = (int)($stmt->fetchColumn() ?? 0);
    if ($parent_id <= 0) throw new Exception('No parent account found.');

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $stmt->execute([$parent_id]);
    $parent_role = (string)($stmt->fetchColumn() ?? '');

    $payer_id = 0;
    $payee_id = 0;
    $flow = '';

    if ($role === 'agent') {
        if ($parent_role !== 'master') throw new Exception('Parent is not master.');
        $payer_id = $parent_id;
        $payee_id = $actor_id;
        $flow = 'agent_to_master';
    } elseif ($role === 'master') {
        if ($parent_role !== 'admin') throw new Exception('Parent is not admin.');
        $payer_id = $parent_id;
        $payee_id = $actor_id;
        $flow = 'master_to_admin';
    } else {
        throw new Exception('Permission denied.');
    }

    $meta = [
        'kind' => 'balance_request',
        'flow' => $flow,
        'requested_by' => $actor_id,
        'requested_role' => $role,
        'requested_at' => date('Y-m-d H:i:s')
    ];

    $stmt = $pdo->prepare("INSERT INTO payments (payer_id, payee_id, created_by, mode_id, type, amount, fee_percent, fee_flat, status, reference, note, meta_json)
        VALUES (?, ?, ?, NULL, 'adjustment', ?, 0.000, 0.00, 'pending', NULL, ?, ?)");
    $stmt->execute([$payer_id, $payee_id, $actor_id, $amount, $note !== '' ? $note : null, json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $id = (int)$pdo->lastInsertId();

    audit_log($pdo, 'create', 'balance_request', (string)$id, null, [
        'payer_id' => $payer_id,
        'payee_id' => $payee_id,
        'amount' => $amount,
        'flow' => $flow
    ]);

    out(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    out(['success' => false, 'message' => $e->getMessage()]);
}

