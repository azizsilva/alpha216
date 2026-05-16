<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';

require_admin_login($admin_base);

$page_title = 'Payments';
require '../includes/header.php';

$role = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

function role_allows_mode($mode_roles, $role) {
    $arr = array_filter(array_map('trim', explode(',', (string)$mode_roles)));
    foreach ($arr as $r) {
        if (strtolower($r) === strtolower($role)) return true;
    }
    return false;
}

function can_transfer_to($pdo, $actor_role, $actor_id, $target_id) {
    if ($actor_role === 'admin') return true;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND parent_id = ?");
    $stmt->execute([(int)$target_id, (int)$actor_id]);
    return (bool)$stmt->fetchColumn();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $type = $_POST['type'] ?? 'deposit';
        $amount = (float)($_POST['amount'] ?? 0);
        $target_id = (int)($_POST['target_id'] ?? 0);
        $status = $_POST['status'] ?? 'completed';
        $mode_id = (int)($_POST['mode_id'] ?? 0);
        $reference = trim((string)($_POST['reference'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if (!in_array($type, ['deposit','withdrawal','adjustment','refund'], true)) $error = 'Invalid type.';
        elseif ($amount <= 0) $error = 'Amount must be positive.';
        elseif (!in_array($status, ['pending','completed','failed'], true)) $error = 'Invalid status.';
        elseif ($target_id <= 0) $error = 'Select a user.';
        elseif (!$user_id) $error = 'Invalid session.';
        elseif (!$mode_id) $error = 'Select a payment mode.';
        elseif (!can_transfer_to($pdo, $role, $user_id, $target_id) && $role !== 'admin') $error = 'Permission denied.';
        else {
            $modeStmt = $pdo->prepare("SELECT * FROM payment_modes WHERE id = ? AND enabled = 1");
            $modeStmt->execute([$mode_id]);
            $mode = $modeStmt->fetch();
            if (!$mode) {
                $error = 'Invalid payment mode.';
            } elseif (!role_allows_mode($mode['allowed_roles'] ?? '', $role)) {
                $error = 'This payment mode is not allowed for your role.';
            } else {
                $pdo->beginTransaction();
                try {
                    $payer_id = null;
                    $payee_id = null;

                    if (in_array($type, ['deposit','adjustment'], true)) {
                        $payer_id = $role === 'admin' ? null : $user_id;
                        $payee_id = $target_id;
                    } elseif (in_array($type, ['withdrawal','refund'], true)) {
                        $payer_id = $target_id;
                        $payee_id = $role === 'admin' ? null : $user_id;
                    }

                    $stmt = $pdo->prepare("INSERT INTO payments (payer_id, payee_id, created_by, mode_id, type, amount, fee_percent, fee_flat, status, reference, note, completed_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
                    $stmt->execute([
                        $payer_id, $payee_id, $user_id, $mode_id, $type, $amount,
                        (float)$mode['fee_percent'], (float)$mode['fee_flat'],
                        $status, $reference ?: null, $note ?: null, $completed_at
                    ]);
                    $payment_id = (int)$pdo->lastInsertId();

                    if ($status === 'completed') {
                        $lock_ids = [(int)$user_id, (int)$target_id];
                        sort($lock_ids);
                        $balStmt = $pdo->prepare("SELECT id, balance FROM users WHERE id IN (?, ?) FOR UPDATE");
                        $balStmt->execute($lock_ids);
                        $locked_users = $balStmt->fetchAll();
                        $balances = [];
                        foreach ($locked_users as $locked_user) {
                            $balances[(int)$locked_user['id']] = (float)$locked_user['balance'];
                        }
                        if (!isset($balances[(int)$user_id], $balances[(int)$target_id])) {
                            throw new Exception('User not found.');
                        }

                        $new_actor_balance = $balances[(int)$user_id];
                        $new_target_balance = $balances[(int)$target_id];

                        if (in_array($type, ['deposit','adjustment'], true)) {
                            if ($role !== 'admin') {
                                if ($new_actor_balance < $amount) throw new Exception('Insufficient balance.');
                                $new_actor_balance -= $amount;
                            }
                            $new_target_balance += $amount;

                            $t = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'deposit', ?)");
                            $t->execute([$user_id, $target_id, $amount, "Payment#$payment_id Deposit $amount"]);
                        } else {
                            if ($new_target_balance < $amount) throw new Exception('User has insufficient balance.');
                            $new_target_balance -= $amount;

                            if ($role !== 'admin') {
                                $new_actor_balance += $amount;
                            }

                            $t = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'withdrawal', ?)");
                            $t->execute([$target_id, $user_id, $amount, "Payment#$payment_id Withdrawal $amount"]);
                        }

                        $upd = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                        $upd->execute([$new_actor_balance, $user_id]);
                        $upd->execute([$new_target_balance, $target_id]);

                        if ($role !== 'admin') {
                            $_SESSION['coins'] = $new_actor_balance;
                        }
                    }

                    audit_log($pdo, 'create', 'payment', (string)$payment_id, null, ['type' => $type, 'amount' => $amount, 'status' => $status, 'target_id' => $target_id, 'mode_id' => $mode_id]);
                    $pdo->commit();
                    $message = 'Payment created.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        if ($id <= 0) $error = 'Invalid payment.';
        elseif (!in_array($new_status, ['pending','completed','failed','reversed'], true)) $error = 'Invalid status.';
        else {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) $error = 'Payment not found.';
            else {
                if ($role !== 'admin') {
                    $error = 'Permission denied.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $before = $p;
                        if ($p['status'] === $new_status) throw new Exception('No change.');

                        if ($new_status === 'completed' && $p['status'] !== 'completed') {
                            $target_id = (int)($p['payee_id'] ?? 0);
                            $source_id = (int)($p['payer_id'] ?? 0);
                            $amount = (float)$p['amount'];
                            if (in_array($p['type'], ['deposit','adjustment'], true)) {
                                if ($source_id > 0) {
                                    $balStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                                    $balStmt->execute([$source_id]);
                                    if ((float)$balStmt->fetchColumn() < $amount) throw new Exception('Insufficient balance.');
                                    $upd = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                                    $upd->execute([$amount, $source_id]);
                                }
                                if ($target_id > 0) {
                                    $upd = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                                    $upd->execute([$amount, $target_id]);
                                }
                            } else {
                                if ($target_id > 0) {
                                    $balStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                                    $balStmt->execute([$target_id]);
                                    if ((float)$balStmt->fetchColumn() < $amount) throw new Exception('User has insufficient balance.');
                                    $upd = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                                    $upd->execute([$amount, $target_id]);
                                }
                                if ($source_id > 0) {
                                    $upd = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                                    $upd->execute([$amount, $source_id]);
                                }
                            }
                            $upd = $pdo->prepare("UPDATE payments SET status='completed', completed_at=NOW() WHERE id=?");
                            $upd->execute([$id]);
                        } elseif ($new_status === 'reversed' && $p['status'] === 'completed') {
                            $target_id = (int)($p['payee_id'] ?? 0);
                            $source_id = (int)($p['payer_id'] ?? 0);
                            $amount = (float)$p['amount'];
                            if (in_array($p['type'], ['deposit','adjustment'], true)) {
                                if ($target_id > 0) {
                                    $balStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                                    $balStmt->execute([$target_id]);
                                    if ((float)$balStmt->fetchColumn() < $amount) throw new Exception('Cannot reverse: payee insufficient balance.');
                                    $upd = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                                    $upd->execute([$amount, $target_id]);
                                }
                                if ($source_id > 0) {
                                    $upd = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                                    $upd->execute([$amount, $source_id]);
                                }
                            } else {
                                if ($source_id > 0) {
                                    $balStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                                    $balStmt->execute([$source_id]);
                                    if ((float)$balStmt->fetchColumn() < $amount) throw new Exception('Cannot reverse: receiver insufficient balance.');
                                    $upd = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                                    $upd->execute([$amount, $source_id]);
                                }
                                if ($target_id > 0) {
                                    $upd = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                                    $upd->execute([$amount, $target_id]);
                                }
                            }
                            $upd = $pdo->prepare("UPDATE payments SET status='reversed' WHERE id=?");
                            $upd->execute([$id]);
                        } else {
                            $upd = $pdo->prepare("UPDATE payments SET status=? WHERE id=?");
                            $upd->execute([$new_status, $id]);
                        }
                        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
                        $stmt->execute([$id]);
                        $after = $stmt->fetch();
                        audit_log($pdo, 'update_status', 'payment', (string)$id, $before, $after);
                        $pdo->commit();
                        $message = 'Payment updated.';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

$modes = $pdo->query("SELECT * FROM payment_modes WHERE enabled = 1 ORDER BY name ASC")->fetchAll();

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => $_GET['status'] ?? '',
    'type' => $_GET['type'] ?? '',
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? ''
];

$sql = "SELECT p.*, m.name AS mode_name,
            u1.username AS payer_name, u2.username AS payee_name, c.username AS creator_name
        FROM payments p
        LEFT JOIN payment_modes m ON m.id = p.mode_id
        LEFT JOIN users u1 ON u1.id = p.payer_id
        LEFT JOIN users u2 ON u2.id = p.payee_id
        LEFT JOIN users c ON c.id = p.created_by
        WHERE 1=1";
$params = [];
if ($filters['status'] !== '' && in_array($filters['status'], ['pending','completed','failed','reversed'], true)) {
    $sql .= " AND p.status = ?";
    $params[] = $filters['status'];
}
if ($filters['type'] !== '' && in_array($filters['type'], ['deposit','withdrawal','adjustment','refund'], true)) {
    $sql .= " AND p.type = ?";
    $params[] = $filters['type'];
}
if ($filters['from'] !== '') {
    $sql .= " AND p.created_at >= ?";
    $params[] = $filters['from'] . " 00:00:00";
}
if ($filters['to'] !== '') {
    $sql .= " AND p.created_at <= ?";
    $params[] = $filters['to'] . " 23:59:59";
}
if ($filters['q'] !== '') {
    $sql .= " AND (CAST(p.id AS CHAR) LIKE ? OR u1.username LIKE ? OR u2.username LIKE ? OR c.username LIKE ? OR p.reference LIKE ?)";
    $q = '%' . $filters['q'] . '%';
    array_push($params, $q, $q, $q, $q, $q);
}

if ($role !== 'admin') {
    $sql .= " AND (p.created_by = ? OR p.payer_id = ? OR p.payee_id = ?)";
    array_push($params, $user_id, $user_id, $user_id);
}

$sql .= " ORDER BY p.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<?php if ($message): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Create Payment</h5>
      <div class="text-body-secondary">Deposits, withdrawals, adjustments</div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" class="row g-3 align-items-end">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <input type="hidden" name="create_payment" value="1">
      <div class="col-12 col-md-2">
        <label class="form-label">Type</label>
        <select class="form-select" name="type">
          <option value="deposit">Deposit</option>
          <option value="withdrawal">Withdrawal</option>
          <option value="adjustment">Adjustment</option>
          <option value="refund">Refund</option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="completed">Completed</option>
          <option value="pending">Pending</option>
          <option value="failed">Failed</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Payment Mode</label>
        <select class="form-select" name="mode_id" required>
          <option value="">Select Mode</option>
          <?php foreach ($modes as $m): ?>
            <?php if (role_allows_mode($m['allowed_roles'] ?? '', $role)): ?>
              <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Amount</label>
        <div class="input-group">
          <span class="input-group-text">TND</span>
          <input class="form-control" type="number" step="0.01" min="0.01" name="amount" required>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Target User ID</label>
        <input class="form-control" type="number" name="target_id" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Reference</label>
        <input class="form-control" name="reference" placeholder="UTR / TXN ID">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Note</label>
        <input class="form-control" name="note" placeholder="Optional note">
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Create</button>
        <?php if ($role === 'admin'): ?>
          <a class="btn btn-outline-secondary w-100" href="<?php echo $admin_base; ?>payment-modes/">Modes</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Payments</h5>
      <div class="text-body-secondary">Latest 200</div>
    </div>
    <form method="GET" class="d-flex gap-2 flex-wrap">
      <input class="form-control" name="q" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="Search id/user/ref">
      <select class="form-select" name="type">
        <option value="">All types</option>
        <?php foreach (['deposit','withdrawal','adjustment','refund'] as $t): ?>
          <option value="<?php echo $t; ?>" <?php echo $filters['type'] === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select" name="status">
        <option value="">All status</option>
        <?php foreach (['pending','completed','failed','reversed'] as $s): ?>
          <option value="<?php echo $s; ?>" <?php echo $filters['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
        <?php endforeach; ?>
      </select>
      <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
      <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
      <button class="btn btn-outline-primary" type="submit">Filter</button>
    </form>
  </div>
  <div class="card-body">
    <table class="table table-hover custom-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Status</th>
          <th>Amount</th>
          <th>Mode</th>
          <th>Payer</th>
          <th>Payee</th>
          <th>Created By</th>
          <th>Created</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($payments)): ?>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?php echo (int)$p['id']; ?></td>
              <td><?php echo htmlspecialchars(strtoupper($p['type'])); ?></td>
              <td><?php echo htmlspecialchars(strtoupper($p['status'])); ?></td>
              <td><?php echo number_format((float)$p['amount'], 2); ?></td>
              <td><?php echo htmlspecialchars($p['mode_name'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($p['payer_name'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($p['payee_name'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($p['creator_name'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars((string)$p['created_at']); ?></td>
              <td class="text-right">
                <?php if (($role === 'admin')): ?>
                  <form method="POST" class="d-flex gap-2 justify-content-end flex-wrap">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                    <select class="form-select form-select-sm" name="new_status" style="width: 140px;">
                      <?php foreach (['pending','completed','failed','reversed'] as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo ucfirst($st); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
                  </form>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="10" class="text-center">No payments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
