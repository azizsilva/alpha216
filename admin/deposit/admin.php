<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';
require '../includes/upload.php';

require_admin_login($admin_base);
require_admin_role(['admin'], $admin_base);

$target = $admin_base . 'deposit/';
header('Location: ' . $target);
exit;

$page_title = 'Deposit';
require '../includes/header.php';

$my_id = (int)current_admin_id();

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
        $wallet_name = trim((string)($post['wallet_name'] ?? ''));
        $wallet_number = trim((string)($post['wallet_number'] ?? ''));
        if ($wallet_name !== '') $details['wallet_name'] = $wallet_name;
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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $label = trim((string)($_POST['label'] ?? ''));
        $channel = (string)($_POST['channel'] ?? 'other');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($label === '') $error = 'Label is required.';
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
                    $stmt = $pdo->prepare("INSERT INTO deposit_methods (owner_id, target_role, label, channel, enabled, sort_order, details_json)
                        VALUES (?, 'master', ?, ?, ?, ?, ?)");
                    $stmt->execute([$my_id, $label, $channel, $enabled, $sort_order, $details_json]);
                    audit_log($pdo, 'create', 'deposit_method', (string)$pdo->lastInsertId(), null, [
                        'owner_id' => $my_id,
                        'target_role' => 'master',
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
                        $old = $pdo->prepare("SELECT * FROM deposit_methods WHERE id=? AND owner_id=? AND target_role='master'");
                        $old->execute([$id, $my_id]);
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
                            $stmt = $pdo->prepare("UPDATE deposit_methods SET label=?, channel=?, enabled=?, sort_order=?, details_json=? WHERE id=? AND owner_id=? AND target_role='master'");
                            $stmt->execute([$label, $channel, $enabled, $sort_order, $details_json, $id, $my_id]);
                            audit_log($pdo, 'update', 'deposit_method', (string)$id, $before, [
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
                        $old = $pdo->prepare("SELECT * FROM deposit_methods WHERE id=? AND owner_id=? AND target_role='master'");
                        $old->execute([$id, $my_id]);
                        $before = $old->fetch();
                        if (!$before) $error = 'Method not found.';
                        else {
                            $stmt = $pdo->prepare("DELETE FROM deposit_methods WHERE id=? AND owner_id=? AND target_role='master'");
                            $stmt->execute([$id, $my_id]);
                            audit_log($pdo, 'delete', 'deposit_method', (string)$id, $before, null);
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

$mine = $pdo->prepare("SELECT * FROM deposit_methods WHERE owner_id=? AND target_role='master' ORDER BY sort_order ASC, id DESC");
$mine->execute([$my_id]);
$mine = $mine->fetchAll();
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
      <div class="card-header d-flex align-items-start justify-content-between gap-2 flex-wrap">
        <div>
          <h5 class="mb-1">Add Deposit Method</h5>
          <div class="text-body-secondary">Visible to masters</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="<?php echo $admin_base; ?>deposit/">Deposit Methods</a>
      </div>
      <div class="card-body">
        <form method="POST" class="row g-3" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="action" value="create">
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
            <label class="form-label">Wallet Name</label>
            <input class="form-control" name="wallet_name">
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

          <div class="col-12 d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit">Save</button>
            <button class="btn btn-outline-secondary" type="reset">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-1">Manage</h5>
        <div class="text-body-secondary">Your methods for masters</div>
      </div>
      <div class="card-body">
        <?php if (!empty($mine)): ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Label</th>
                  <th>Channel</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($mine as $m): ?>
                  <?php $ed = json_assoc($m['details_json'] ?? ''); ?>
                  <tr>
                    <td>
                      <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="fw-semibold"><?php echo htmlspecialchars((string)$m['label']); ?></div>
                        <?php if ((string)($m['channel'] ?? '') === 'upi'): ?>
                          <?php
                            $qr = (string)($ed['qr_path'] ?? ($ed['qr_url'] ?? ''));
                            $qr_href = $qr !== '' ? ($qr[0] === '/' ? $qr : ($admin_base . $qr)) : '';
                          ?>
                          <?php if ($qr_href !== ''): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($qr_href); ?>" target="_blank" rel="noopener">QR</a>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                      <?php if ((string)($m['channel'] ?? '') === 'upi' && !empty($ed['upi_id'])): ?>
                        <div class="text-body-secondary small">UPI: <?php echo htmlspecialchars((string)$ed['upi_id']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?php echo strtoupper((string)$m['channel']); ?></td>
                    <td><?php echo (int)($m['enabled'] ?? 0) === 1 ? 'Enabled' : 'Disabled'; ?></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editDeposit<?php echo (int)$m['id']; ?>">Edit</button>
                      <form method="POST" class="d-inline-flex">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                        <input type="hidden" name="label" value="<?php echo htmlspecialchars((string)$m['label']); ?>">
                        <input type="hidden" name="channel" value="<?php echo htmlspecialchars((string)$m['channel']); ?>">
                        <input type="hidden" name="sort_order" value="<?php echo (int)($m['sort_order'] ?? 0); ?>">
                        <?php if ((int)($m['enabled'] ?? 0) === 1): ?>
                          <input type="hidden" name="enabled" value="1">
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                      </form>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="4" class="p-0">
                      <div class="collapse" id="editDeposit<?php echo (int)$m['id']; ?>">
                        <div class="p-3">
                          <form method="POST" class="row g-3" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="action" value="update">
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
                          <button class="btn btn-primary" type="submit">Update</button>
                          <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editDeposit<?php echo (int)$m['id']; ?>">Close</button>
                        </div>
                          </form>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center text-body-secondary py-4">No methods added yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
  .mk-fields { display: none; }
  .mk-fields.is-active { display: block; }
  .mk-edit-fields { display: none; }
  .mk-edit-fields.is-active { display: block; }
</style>

<script>
  (function () {
    var select = document.getElementById('mkDepositChannel');
    if (!select) return;
    function toggleFields() {
      var ch = select.value;
      var all = document.querySelectorAll('.mk-fields');
      for (var i = 0; i < all.length; i++) all[i].classList.remove('is-active');
      var act = document.querySelectorAll('.mk-' + ch);
      for (var j = 0; j < act.length; j++) act[j].classList.add('is-active');
    }
    select.addEventListener('change', toggleFields);
    toggleFields();

    function toggleEdit(scopeName, channel) {
      var scope = document.querySelector('.mk-edit-scope[data-mk-scope="' + scopeName + '"]');
      if (!scope) return;
      var all = scope.querySelectorAll('.mk-edit-fields');
      for (var i = 0; i < all.length; i++) all[i].classList.remove('is-active');
      var active = scope.querySelectorAll('.mk-edit-' + channel);
      for (var j = 0; j < active.length; j++) active[j].classList.add('is-active');
    }

    var editSelects = document.querySelectorAll('select.mk-edit-channel[data-mk-scope]');
    for (var k = 0; k < editSelects.length; k++) {
      (function (sel) {
        var scope = sel.getAttribute('data-mk-scope');
        sel.addEventListener('change', function () {
          toggleEdit(scope, sel.value);
        });
        toggleEdit(scope, sel.value);
      })(editSelects[k]);
    }
  })();
</script>

<?php require '../includes/footer.php'; ?>
