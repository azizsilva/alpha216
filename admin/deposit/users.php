<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';
require '../includes/upload.php';

require_admin_login($admin_base);
require_admin_role(['admin'], $admin_base);

$page_title = 'Deposit (Auto Users)';
require '../includes/header.php';

function json_assoc($text) {
    $t = trim((string)$text);
    if ($t === '') return [];
    $d = json_decode($t, true);
    return is_array($d) ? $d : [];
}

function build_details_json($channel, $post, $existing_assoc = [], $qr_path = null) {
    $channel = (string)$channel;
    $details = [];
    $notes = trim((string)($post['notes'] ?? ''));
    if ($notes !== '') $details['notes'] = $notes;

    if ($channel === 'upi') {
        $upi_id = trim((string)($post['upi_id'] ?? ''));
        $holder = trim((string)($post['holder_name'] ?? ''));
        if ($upi_id !== '') $details['upi_id'] = $upi_id;
        if ($holder !== '') $details['holder_name'] = $holder;
        if (is_string($qr_path) && trim($qr_path) !== '') $details['qr_path'] = trim($qr_path);
        elseif (is_array($existing_assoc) && !empty($existing_assoc['qr_path'])) $details['qr_path'] = (string)$existing_assoc['qr_path'];
        elseif (is_array($existing_assoc) && !empty($existing_assoc['qr_url'])) $details['qr_url'] = (string)$existing_assoc['qr_url'];
    } elseif ($channel === 'bank') {
        $details_map = [
            'bank_name' => 'bank_name',
            'branch' => 'branch',
            'account_name' => 'account_name',
            'account_number' => 'account_number',
            'ifsc' => 'ifsc',
            'swift' => 'swift',
            'iban' => 'iban'
        ];
        foreach ($details_map as $k => $src) {
            $v = trim((string)($post[$src] ?? ''));
            if ($v !== '') $details[$k] = $v;
        }
    } elseif ($channel === 'wallet') {
        $wallet_kind = trim((string)($post['wallet_kind'] ?? 'wallet'));
        $wallet_network = trim((string)($post['wallet_network'] ?? ''));
        $wallet_name = trim((string)($post['wallet_name'] ?? ''));
        if ($wallet_kind === 'usdt') {
            if (!in_array($wallet_network, ['TRC20','BEP20'], true)) $wallet_network = 'TRC20';
            $wallet_name = 'USDT (' . $wallet_network . ')';
            $details['wallet_kind'] = 'usdt';
            $details['wallet_network'] = $wallet_network;
        } else {
            $details['wallet_kind'] = 'wallet';
        }
        if ($wallet_name !== '') $details['wallet_name'] = $wallet_name;
        $wallet_number = trim((string)($post['wallet_number'] ?? ''));
        if ($wallet_number !== '') $details['wallet_number'] = $wallet_number;
        if (is_string($qr_path) && trim($qr_path) !== '') $details['qr_path'] = trim($qr_path);
        elseif (is_array($existing_assoc) && !empty($existing_assoc['qr_path'])) $details['qr_path'] = (string)$existing_assoc['qr_path'];
        elseif (is_array($existing_assoc) && !empty($existing_assoc['qr_url'])) $details['qr_url'] = (string)$existing_assoc['qr_url'];
    } elseif (in_array($channel, ['cash','gateway','other'], true)) {
        $instructions = trim((string)($post['instructions'] ?? ''));
        $url = trim((string)($post['url'] ?? ''));
        if ($instructions !== '') $details['instructions'] = $instructions;
        if ($url !== '') $details['url'] = $url;
    }

    if (empty($details)) return null;
    return json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

$selected_user_id = (int)($_GET['user_id'] ?? 0);
$message = '';
$error = '';

try {
    $users_stmt = $pdo->query("SELECT id, username FROM users WHERE role='player' AND (parent_id IS NULL OR parent_id=0) ORDER BY id DESC LIMIT 500");
    $auto_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $auto_users = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $selected_user_id = (int)($_POST['user_id'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $channel = (string)($_POST['channel'] ?? 'other');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($selected_user_id <= 0) $error = 'Select a user.';
        elseif ($action === 'copy_admin_defaults') {
            try {
                $stmt = $pdo->prepare("SELECT label, channel, enabled, sort_order, details_json
                                       FROM deposit_methods
                                       WHERE target_role='master' AND enabled=1
                                       ORDER BY sort_order ASC, id DESC");
                $stmt->execute();
                $defaults = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $pdo->beginTransaction();
                $del = $pdo->prepare("DELETE FROM user_deposit_methods WHERE user_id=?");
                $del->execute([$selected_user_id]);

                if (!empty($defaults)) {
                    $ins = $pdo->prepare("INSERT INTO user_deposit_methods (user_id, label, channel, enabled, sort_order, details_json)
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($defaults as $d) {
                        $ins->execute([
                            $selected_user_id,
                            (string)($d['label'] ?? ''),
                            (string)($d['channel'] ?? 'other'),
                            (int)($d['enabled'] ?? 1),
                            (int)($d['sort_order'] ?? 0),
                            $d['details_json'] ?? null
                        ]);
                    }
                }

                $pdo->commit();
                audit_log($pdo, 'copy', 'user_deposit_method', (string)$selected_user_id, null, ['source' => 'admin_defaults']);
                $message = 'Admin default methods applied to user.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to apply defaults.';
            }
        } elseif ($label === '') $error = 'Label is required.';
        elseif (!in_array($channel, ['upi','bank','wallet','cash','gateway','other'], true)) $error = 'Invalid channel.';
        else {
            try {
                if ($action === 'create') {
                    $qr_path = null;
                    if ($channel === 'upi' || $channel === 'wallet') {
                        [$qr_path, $qr_err] = mk_save_qr_upload($_FILES['qr_file'] ?? null, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr', 'uploads/qr');
                        if ($qr_err !== '') $error = $qr_err;
                    }
                    $details_json = $error === '' ? build_details_json($channel, $_POST, [], $qr_path) : null;
                    if ($error !== '') throw new Exception($error);
                    $stmt = $pdo->prepare("INSERT INTO user_deposit_methods (user_id, label, channel, enabled, sort_order, details_json)
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$selected_user_id, $label, $channel, $enabled, $sort_order, $details_json]);
                    audit_log($pdo, 'create', 'user_deposit_method', (string)$pdo->lastInsertId(), null, [
                        'user_id' => $selected_user_id,
                        'label' => $label,
                        'channel' => $channel,
                        'enabled' => $enabled,
                        'sort_order' => $sort_order
                    ]);
                    $message = 'Deposit method created.';
                } elseif ($action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) $error = 'Invalid method.';
                    else {
                        $old = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE id=? AND user_id=?");
                        $old->execute([$id, $selected_user_id]);
                        $before = $old->fetch();
                        if (!$before) $error = 'Method not found.';
                        else {
                            $existing_assoc = json_assoc($before['details_json'] ?? '');
                            $qr_path = null;
                            if ($channel === 'upi' || $channel === 'wallet') {
                                [$qr_path, $qr_err] = mk_save_qr_upload($_FILES['qr_file'] ?? null, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr', 'uploads/qr');
                                if ($qr_err !== '') $error = $qr_err;
                            }
                            $built = $error === '' ? build_details_json($channel, $_POST, $existing_assoc, $qr_path) : null;
                            if ($error !== '') throw new Exception($error);
                            $details_json = $built === null ? ($before['details_json'] ?? null) : $built;
                            $stmt = $pdo->prepare("UPDATE user_deposit_methods SET label=?, channel=?, enabled=?, sort_order=?, details_json=? WHERE id=? AND user_id=?");
                            $stmt->execute([$label, $channel, $enabled, $sort_order, $details_json, $id, $selected_user_id]);
                            audit_log($pdo, 'update', 'user_deposit_method', (string)$id, $before, [
                                'label' => $label,
                                'channel' => $channel,
                                'enabled' => $enabled,
                                'sort_order' => $sort_order,
                                'details_json' => $details_json
                            ]);
                            $message = 'Deposit method updated.';
                        }
                    }
                } elseif ($action === 'delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) $error = 'Invalid method.';
                    else {
                        $old = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE id=? AND user_id=?");
                        $old->execute([$id, $selected_user_id]);
                        $before = $old->fetch();
                        if (!$before) $error = 'Method not found.';
                        else {
                            $stmt = $pdo->prepare("DELETE FROM user_deposit_methods WHERE id=? AND user_id=?");
                            $stmt->execute([$id, $selected_user_id]);
                            audit_log($pdo, 'delete', 'user_deposit_method', (string)$id, $before, null);
                            $message = 'Deposit method deleted.';
                        }
                    }
                } else {
                    $error = 'Invalid action.';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$methods = [];
if ($selected_user_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE user_id=? ORDER BY sort_order ASC, id DESC");
        $stmt->execute([$selected_user_id]);
        $methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $methods = [];
    }
}
?>

<div class="row">
  <div class="col-12">
    <?php if ($message !== ''): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div>
          <h5 class="mb-1">Auto Users Deposit</h5>
          <div class="text-body-secondary">Manage deposit QR/addresses for users without an agent</div>
        </div>
        <a class="btn btn-outline-primary" href="<?php echo $admin_base; ?>deposit/admin.php">Back</a>
      </div>
      <div class="card-body">
        <form method="GET" class="row g-3">
          <div class="col-12">
            <label class="form-label">User</label>
            <select class="form-select" name="user_id" onchange="this.form.submit()">
              <option value="0">Select auto-registered user</option>
              <?php foreach ($auto_users as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>" <?php echo $selected_user_id === (int)$u['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string)$u['username']); ?> (#<?php echo (int)$u['id']; ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <hr>
        <form method="POST" class="mb-3" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="action" value="copy_admin_defaults">
          <input type="hidden" name="user_id" value="<?php echo (int)$selected_user_id; ?>">
          <button class="btn btn-outline-dark" type="submit" <?php echo $selected_user_id <= 0 ? 'disabled' : ''; ?> onclick="return confirm('Replace this user\\'s methods with admin defaults?')">
            Use Admin Default Methods
          </button>
        </form>

        <form method="POST" class="row g-3" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="user_id" value="<?php echo (int)$selected_user_id; ?>">

          <div class="col-12">
            <label class="form-label">Label</label>
            <input class="form-control" name="label" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Channel</label>
            <select class="form-select" name="channel" id="mkDepositChannel">
              <?php foreach (['upi','bank','wallet','cash','gateway','other'] as $ch): ?>
                <option value="<?php echo $ch; ?>"><?php echo strtoupper($ch); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Sort Order</label>
            <input class="form-control" type="number" name="sort_order" value="0">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="enabled" id="mkDepositEnabled" checked>
              <label class="form-check-label" for="mkDepositEnabled">Enabled</label>
            </div>
          </div>

          <div class="col-12 mk-fields mk-upi">
            <label class="form-label">UPI ID</label>
            <input class="form-control" name="upi_id">
          </div>
          <div class="col-12 mk-fields mk-upi">
            <label class="form-label">Holder Name</label>
            <input class="form-control" name="holder_name">
          </div>
          <div class="col-12 mk-fields mk-upi">
            <label class="form-label">QR Code (optional)</label>
            <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
          </div>

          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">Bank Name</label>
            <input class="form-control" name="bank_name">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">Branch</label>
            <input class="form-control" name="branch">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">Account Name</label>
            <input class="form-control" name="account_name">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">Account Number</label>
            <input class="form-control" name="account_number">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">IFSC</label>
            <input class="form-control" name="ifsc">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">SWIFT (optional)</label>
            <input class="form-control" name="swift">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">IBAN (optional)</label>
            <input class="form-control" name="iban">
          </div>

          <div class="col-12 mk-fields mk-wallet">
            <label class="form-label">Wallet Type</label>
            <select class="form-select" name="wallet_kind" id="mkWalletKind">
              <option value="wallet">Wallet</option>
              <option value="usdt">USDT</option>
            </select>
          </div>
          <div class="col-12 mk-fields mk-wallet" id="mkWalletNetworkWrap" style="display:none;">
            <label class="form-label">USDT Network</label>
            <select class="form-select" name="wallet_network">
              <option value="TRC20">TRC20</option>
              <option value="BEP20">BEP20</option>
            </select>
          </div>
          <div class="col-12 mk-fields mk-wallet" id="mkWalletNameWrap">
            <label class="form-label">Wallet Name</label>
            <input class="form-control" name="wallet_name" placeholder="BTC / Skrill / ..." >
          </div>
          <div class="col-12 mk-fields mk-wallet">
            <label class="form-label">Wallet Address / Number</label>
            <input class="form-control" name="wallet_number">
          </div>
          <div class="col-12 mk-fields mk-wallet">
            <label class="form-label">Wallet QR (optional)</label>
            <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
          </div>

          <div class="col-12 mk-fields mk-cash mk-gateway mk-other">
            <label class="form-label">Instructions</label>
            <textarea class="form-control" name="instructions" rows="2"></textarea>
          </div>
          <div class="col-12 mk-fields mk-gateway mk-other">
            <label class="form-label">URL (optional)</label>
            <input class="form-control" name="url">
          </div>
          <div class="col-12">
            <label class="form-label">Notes (optional)</label>
            <textarea class="form-control" name="notes" rows="2"></textarea>
          </div>

          <div class="col-12">
            <button class="btn btn-primary" type="submit" <?php echo $selected_user_id <= 0 ? 'disabled' : ''; ?>>Add Method</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-1">User Methods</h5>
        <div class="text-body-secondary">Methods shown to the selected user</div>
      </div>
      <div class="card-body">
        <?php if ($selected_user_id <= 0): ?>
          <div class="text-body-secondary">Select a user to view/edit methods.</div>
        <?php elseif (empty($methods)): ?>
          <div class="text-body-secondary">No methods created yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Label</th>
                  <th>Channel</th>
                  <th>Enabled</th>
                  <th>Sort</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($methods as $m): ?>
                  <?php $ed = json_assoc($m['details_json'] ?? ''); ?>
                  <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars((string)$m['label']); ?></td>
                    <td><?php echo htmlspecialchars(strtoupper((string)$m['channel'])); ?></td>
                    <td><?php echo (int)($m['enabled'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                    <td><?php echo (int)($m['sort_order'] ?? 0); ?></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editMethod<?php echo (int)$m['id']; ?>">Edit</button>
                    </td>
                  </tr>
                  <tr class="collapse" id="editMethod<?php echo (int)$m['id']; ?>">
                    <td colspan="5">
                      <form method="POST" class="row g-3" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="user_id" value="<?php echo (int)$selected_user_id; ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">

                        <div class="col-12 col-md-5">
                          <label class="form-label">Label</label>
                          <input class="form-control" name="label" value="<?php echo htmlspecialchars((string)$m['label']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                          <label class="form-label">Channel</label>
                          <select class="form-select mk-edit-channel" name="channel" data-mk-scope="edit<?php echo (int)$m['id']; ?>">
                            <?php foreach (['upi','bank','wallet','cash','gateway','other'] as $ch): ?>
                              <option value="<?php echo $ch; ?>" <?php echo (string)$m['channel'] === $ch ? 'selected' : ''; ?>><?php echo strtoupper($ch); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-12 col-md-2">
                          <label class="form-label">Sort</label>
                          <input class="form-control" type="number" name="sort_order" value="<?php echo (int)($m['sort_order'] ?? 0); ?>">
                        </div>
                        <div class="col-12 col-md-2 d-flex align-items-end">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enabled" id="mkEnabled<?php echo (int)$m['id']; ?>" <?php echo (int)($m['enabled'] ?? 0) === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="mkEnabled<?php echo (int)$m['id']; ?>">Enabled</label>
                          </div>
                        </div>

                        <div class="col-12 mk-edit-scope" data-mk-scope="edit<?php echo (int)$m['id']; ?>">
                          <div class="row g-3">
                            <div class="col-12 mk-edit-fields mk-edit-upi">
                              <label class="form-label">UPI ID</label>
                              <input class="form-control" name="upi_id" value="<?php echo htmlspecialchars((string)($ed['upi_id'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-upi">
                              <label class="form-label">Holder Name</label>
                              <input class="form-control" name="holder_name" value="<?php echo htmlspecialchars((string)($ed['holder_name'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-upi">
                              <label class="form-label">QR Code (optional)</label>
                              <?php
                                $qr = (string)($ed['qr_path'] ?? ($ed['qr_url'] ?? ''));
                                $qr_href = $qr !== '' ? ($qr[0] === '/' ? $qr : ($admin_base . $qr)) : '';
                              ?>
                              <?php if ($qr_href !== ''): ?>
                                <div class="mb-2">
                                  <a href="<?php echo htmlspecialchars($qr_href); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo htmlspecialchars($qr_href); ?>" alt="QR" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);background:#fff;">
                                  </a>
                                </div>
                              <?php endif; ?>
                              <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
                            </div>

                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">Bank Name</label>
                              <input class="form-control" name="bank_name" value="<?php echo htmlspecialchars((string)($ed['bank_name'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">Branch</label>
                              <input class="form-control" name="branch" value="<?php echo htmlspecialchars((string)($ed['branch'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">Account Name</label>
                              <input class="form-control" name="account_name" value="<?php echo htmlspecialchars((string)($ed['account_name'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">Account Number</label>
                              <input class="form-control" name="account_number" value="<?php echo htmlspecialchars((string)($ed['account_number'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">IFSC</label>
                              <input class="form-control" name="ifsc" value="<?php echo htmlspecialchars((string)($ed['ifsc'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">SWIFT (optional)</label>
                              <input class="form-control" name="swift" value="<?php echo htmlspecialchars((string)($ed['swift'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">IBAN (optional)</label>
                              <input class="form-control" name="iban" value="<?php echo htmlspecialchars((string)($ed['iban'] ?? '')); ?>">
                            </div>

                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">Wallet Type</label>
                              <select class="form-select" name="wallet_kind">
                                <option value="wallet" <?php echo (string)($ed['wallet_kind'] ?? 'wallet') === 'wallet' ? 'selected' : ''; ?>>Wallet</option>
                                <option value="usdt" <?php echo (string)($ed['wallet_kind'] ?? '') === 'usdt' ? 'selected' : ''; ?>>USDT</option>
                              </select>
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">USDT Network</label>
                              <select class="form-select" name="wallet_network">
                                <option value="TRC20" <?php echo (string)($ed['wallet_network'] ?? 'TRC20') === 'TRC20' ? 'selected' : ''; ?>>TRC20</option>
                                <option value="BEP20" <?php echo (string)($ed['wallet_network'] ?? '') === 'BEP20' ? 'selected' : ''; ?>>BEP20</option>
                              </select>
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">Wallet Name</label>
                              <input class="form-control" name="wallet_name" value="<?php echo htmlspecialchars((string)($ed['wallet_name'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">Wallet Address / Number</label>
                              <input class="form-control" name="wallet_number" value="<?php echo htmlspecialchars((string)($ed['wallet_number'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">Wallet QR (optional)</label>
                              <?php
                                $qr = (string)($ed['qr_path'] ?? ($ed['qr_url'] ?? ''));
                                $qr_href = $qr !== '' ? ($qr[0] === '/' ? $qr : ($admin_base . $qr)) : '';
                              ?>
                              <?php if ($qr_href !== ''): ?>
                                <div class="mb-2">
                                  <a href="<?php echo htmlspecialchars($qr_href); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo htmlspecialchars($qr_href); ?>" alt="QR" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);background:#fff;">
                                  </a>
                                </div>
                              <?php endif; ?>
                              <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
                            </div>

                            <div class="col-12 mk-edit-fields mk-edit-cash mk-edit-gateway mk-edit-other">
                              <label class="form-label">Instructions</label>
                              <textarea class="form-control" name="instructions" rows="2"><?php echo htmlspecialchars((string)($ed['instructions'] ?? '')); ?></textarea>
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-gateway mk-edit-other">
                              <label class="form-label">URL (optional)</label>
                              <input class="form-control" name="url" value="<?php echo htmlspecialchars((string)($ed['url'] ?? '')); ?>">
                            </div>
                            <div class="col-12">
                              <label class="form-label">Notes (optional)</label>
                              <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars((string)($ed['notes'] ?? '')); ?></textarea>
                            </div>
                          </div>
                        </div>

                        <div class="col-12 d-flex gap-2 flex-wrap">
                          <button class="btn btn-primary" type="submit" name="action" value="update">Update</button>
                          <button class="btn btn-outline-danger" type="submit" name="action" value="delete" onclick="return confirm('Delete this method?')">Delete</button>
                        </div>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  function applyScope(select, scope) {
    var wrap = document.querySelector('.mk-edit-scope[data-mk-scope="' + scope + '"]');
    if (!wrap) return;
    var ch = (select && select.value) ? select.value : 'other';
    var fields = wrap.querySelectorAll('.mk-edit-fields');
    for (var i = 0; i < fields.length; i++) fields[i].style.display = 'none';
    var active = wrap.querySelectorAll('.mk-edit-' + ch);
    for (var j = 0; j < active.length; j++) active[j].style.display = '';
  }

  var createSelect = document.getElementById('mkDepositChannel');
  if (createSelect) {
    var all = document.querySelectorAll('.mk-fields');
    function applyCreate() {
      var ch = createSelect.value;
      for (var i = 0; i < all.length; i++) all[i].style.display = 'none';
      var active = document.querySelectorAll('.mk-' + ch);
      for (var j = 0; j < active.length; j++) active[j].style.display = '';
      var kind = document.getElementById('mkWalletKind');
      var nw = document.getElementById('mkWalletNetworkWrap');
      var nameWrap = document.getElementById('mkWalletNameWrap');
      if (ch === 'wallet' && kind && nw && nameWrap) {
        if (kind.value === 'usdt') {
          nw.style.display = '';
          nameWrap.style.display = 'none';
        } else {
          nw.style.display = 'none';
          nameWrap.style.display = '';
        }
      }
    }
    createSelect.addEventListener('change', applyCreate);
    var kind = document.getElementById('mkWalletKind');
    if (kind) kind.addEventListener('change', applyCreate);
    applyCreate();
  }

  var edits = document.querySelectorAll('.mk-edit-channel');
  for (var k = 0; k < edits.length; k++) {
    (function(sel) {
      var scope = sel.getAttribute('data-mk-scope');
      sel.addEventListener('change', function() { applyScope(sel, scope); });
      applyScope(sel, scope);
    })(edits[k]);
  }
});
</script>

<?php require '../includes/footer.php'; ?>
