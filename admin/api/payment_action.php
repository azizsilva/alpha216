<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';

require_admin_login($admin_base);
require_admin_role(['admin','master','agent'], $admin_base);

header('Content-Type: application/json');

function out($payload) {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
if (!csrf_check($csrf)) out(['success' => false, 'message' => 'Invalid request.']);

$action = (string)($_POST['action'] ?? '');
$payment_id = (int)($_POST['payment_id'] ?? 0);
if ($payment_id <= 0) out(['success' => false, 'message' => 'Invalid payment.']);
if (!in_array($action, ['approve','reject'], true)) out(['success' => false, 'message' => 'Invalid action.']);

$role = (string)current_admin_role();
$actor_id = (int)current_admin_id();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id=? FOR UPDATE");
    $stmt->execute([$payment_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new Exception('Payment not found.');

    $type = (string)($p['type'] ?? '');
    $status = (string)($p['status'] ?? '');
    if ($status !== 'pending') throw new Exception('Already processed.');

    $payer_id = (int)($p['payer_id'] ?? 0);
    $payee_id = (int)($p['payee_id'] ?? 0);
    $amount = (float)($p['amount'] ?? 0);
    if ($amount <= 0) throw new Exception('Invalid amount.');

    $meta = [];
    if (!empty($p['meta_json'])) {
        $tmp = json_decode((string)$p['meta_json'], true);
        if (is_array($tmp)) $meta = $tmp;
    }
    $kind = (string)($meta['kind'] ?? '');

    $now = date('Y-m-d H:i:s');

    if ($type === 'deposit') {
        $can = false;
        if ($role === 'agent' && $payee_id === $actor_id) $can = true;
        if ($role === 'admin' && $payee_id === 0) $can = true;
        if (!$can) throw new Exception('Permission denied.');

        if ($action === 'reject') {
            $meta['rejected_by'] = $actor_id;
            $meta['rejected_role'] = $role;
            $meta['rejected_at'] = $now;
            $stmt = $pdo->prepare("UPDATE payments SET status='failed', meta_json=?, completed_at=? WHERE id=?");
            $stmt->execute([json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $payment_id]);
            audit_log($pdo, 'reject', 'payment', (string)$payment_id, $p, ['status' => 'failed']);
            $pdo->commit();
            out(['success' => true, 'status' => 'failed']);
        }

        if ($payer_id <= 0) throw new Exception('Invalid payer.');

        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");
        $stmt->execute([$payer_id]);
        $bal = $stmt->fetchColumn();
        if ($bal === false) throw new Exception('User not found.');

        $new_bal = (float)$bal + $amount;

        try {
            $desc = 'Deposit approved (payment #' . $payment_id . ')';
            $sender = $payee_id > 0 ? $payee_id : $payer_id;
            $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'deposit', ?)");
            $stmt->execute([$sender, $payer_id, $amount, $desc]);
        } catch (Exception $e) {
        }

        $stmt = $pdo->prepare("UPDATE users SET balance=? WHERE id=?");
        $stmt->execute([$new_bal, $payer_id]);

        $meta['approved_by'] = $actor_id;
        $meta['approved_role'] = $role;
        $meta['approved_at'] = $now;
        $stmt = $pdo->prepare("UPDATE payments SET status='completed', meta_json=?, completed_at=? WHERE id=?");
        $stmt->execute([json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $payment_id]);

        audit_log($pdo, 'approve', 'payment', (string)$payment_id, $p, ['status' => 'completed', 'payer_balance' => $new_bal]);
        $pdo->commit();
        out(['success' => true, 'status' => 'completed', 'payer_id' => $payer_id, 'payer_balance' => $new_bal]);
    }

    if ($type === 'adjustment' && $kind === 'balance_request') {
        $can = false;
        if ($role === 'master' && $payer_id === $actor_id) $can = true;
        if ($role === 'admin' && $payer_id === $actor_id) $can = true;
        if (!$can) throw new Exception('Permission denied.');

        if ($action === 'reject') {
            $meta['rejected_by'] = $actor_id;
            $meta['rejected_role'] = $role;
            $meta['rejected_at'] = $now;
            $stmt = $pdo->prepare("UPDATE payments SET status='failed', meta_json=?, completed_at=? WHERE id=?");
            $stmt->execute([json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $payment_id]);
            audit_log($pdo, 'reject', 'payment', (string)$payment_id, $p, ['status' => 'failed']);
            $pdo->commit();
            out(['success' => true, 'status' => 'failed']);
        }

        if ($payer_id <= 0 || $payee_id <= 0) throw new Exception('Invalid participants.');

        $a = min($payer_id, $payee_id);
        $b = max($payer_id, $payee_id);

        $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id IN (?, ?) FOR UPDATE");
        $stmt->execute([$a, $b]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $balances = [];
        foreach ($rows as $r) $balances[(int)$r['id']] = (float)$r['balance'];
        if (!isset($balances[$payer_id]) || !isset($balances[$payee_id])) throw new Exception('User not found.');

        if ($balances[$payer_id] < $amount) throw new Exception('Insufficient balance.');
        $balances[$payer_id] -= $amount;
        $balances[$payee_id] += $amount;

        try {
            $desc = 'Balance transfer (payment #' . $payment_id . ')';
            $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'deposit', ?)");
            $stmt->execute([$payer_id, $payee_id, $amount, $desc]);
        } catch (Exception $e) {
        }

        $u1 = $pdo->prepare("UPDATE users SET balance=? WHERE id=?");
        $u1->execute([$balances[$payer_id], $payer_id]);
        $u1->execute([$balances[$payee_id], $payee_id]);

        $meta['approved_by'] = $actor_id;
        $meta['approved_role'] = $role;
        $meta['approved_at'] = $now;
        $stmt = $pdo->prepare("UPDATE payments SET status='completed', meta_json=?, completed_at=? WHERE id=?");
        $stmt->execute([json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $payment_id]);

        audit_log($pdo, 'approve', 'payment', (string)$payment_id, $p, [
            'status' => 'completed',
            'payer_balance' => $balances[$payer_id],
            'payee_balance' => $balances[$payee_id]
        ]);

        $pdo->commit();
        out(['success' => true, 'status' => 'completed', 'payer_id' => $payer_id, 'payer_balance' => $balances[$payer_id], 'payee_id' => $payee_id, 'payee_balance' => $balances[$payee_id]]);
    }

    if ($type === 'withdrawal') {
        $can = false;
        if ($role === 'agent' && $payee_id === $actor_id) $can = true;
        if ($role === 'admin' && $payee_id === 0) $can = true;
        if (!$can) throw new Exception('Permission denied.');

        if ($action === 'reject') {
            $meta['rejected_by'] = $actor_id;
            $meta['rejected_role'] = $role;
            $meta['rejected_at'] = $now;
            $stmt = $pdo->prepare("UPDATE payments SET status='failed', meta_json=?, completed_at=? WHERE id=?");
            $stmt->execute([json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $payment_id]);
            audit_log($pdo, 'reject', 'payment', (string)$payment_id, $p, ['status' => 'failed']);
            $pdo->commit();
            out(['success' => true, 'status' => 'failed']);
        }

        if ($payer_id <= 0) throw new Exception('Invalid payer.');

        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");
        $stmt->execute([$payer_id]);
        $bal = $stmt->fetchColumn();
        if ($bal === false) throw new Exception('User not found.');
        $bal = (float)$bal;
        if ($bal < $amount) throw new Exception('Insufficient balance.');

        $new_bal = $bal - $amount;

        try {
            $desc = 'Withdraw approved (payment #' . $payment_id . ')';
            $receiver = $payee_id > 0 ? $payee_id : $actor_id;
            $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'withdrawal', ?)");
            $stmt->execute([$payer_id, $receiver, $amount, $desc]);
        } catch (Exception $e) {
        }

        $stmt = $pdo->prepare("UPDATE users SET balance=? WHERE id=?");
        $stmt->execute([$new_bal, $payer_id]);

        $meta['approved_by'] = $actor_id;
        $meta['approved_role'] = $role;
        $meta['approved_at'] = $now;
        $stmt = $pdo->prepare("UPDATE payments SET status='completed', meta_json=?, completed_at=? WHERE id=?");
        $stmt->execute([json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $payment_id]);

        audit_log($pdo, 'approve', 'payment', (string)$payment_id, $p, ['status' => 'completed', 'payer_balance' => $new_bal]);
        $pdo->commit();
        out(['success' => true, 'status' => 'completed', 'payer_id' => $payer_id, 'payer_balance' => $new_bal]);
    }

    throw new Exception('Unsupported payment type.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['success' => false, 'message' => $e->getMessage()]);
}
