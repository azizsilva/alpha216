<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';
require '../includes/upload.php';

require_admin_login($admin_base);
require_admin_role(['admin'], $admin_base);

$page_title = 'Payment Modes';
require '../includes/header.php';

$message = '';
$error = '';

function json_assoc($text) {
    $t = trim((string)$text);
    if ($t === '') return [];
    $d = json_decode($t, true);
    return is_array($d) ? $d : [];
}

function build_config_json($channel, $post, $existing_assoc = [], $qr_path = null) {
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
        $map = [
            'bank_name' => 'bank_name',
            'branch' => 'branch',
            'account_name' => 'account_name',
            'account_number' => 'account_number',
            'ifsc' => 'ifsc',
            'swift' => 'swift',
            'iban' => 'iban'
        ];
        foreach ($map as $k => $src) {
            $v = trim((string)($post[$src] ?? ''));
            if ($v !== '') $details[$k] = $v;
        }
    } elseif ($channel === 'wallet') {
        $wallet_name = trim((string)($post['wallet_name'] ?? ''));
        $wallet_address = trim((string)($post['wallet_address'] ?? ''));
        if ($wallet_name !== '') $details['wallet_name'] = $wallet_name;
        if ($wallet_address !== '') $details['wallet_address'] = $wallet_address;
    } elseif (in_array($channel, ['cash','gateway','other'], true)) {
        $instructions = trim((string)($post['instructions'] ?? ''));
        $url = trim((string)($post['url'] ?? ''));
        if ($instructions !== '') $details['instructions'] = $instructions;
        if ($url !== '') $details['url'] = $url;
    }

    if (empty($details)) return [null, ''];

    if (is_array($existing_assoc) && !empty($existing_assoc)) {
        foreach ($existing_assoc as $k => $v) {
            if (!array_key_exists($k, $details)) $details[$k] = $v;
        }
    }

    return [json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ''];
}

function normalize_allowed_roles($s) {
    $parts = array_filter(array_map('trim', explode(',', (string)$s)));
    $allowed = ['admin', 'master', 'agent'];
    $out = [];
    foreach ($parts as $p) {
        $p = strtolower($p);
        if (in_array($p, $allowed, true) && !in_array($p, $out, true)) $out[] = $p;
    }
    return implode(',', $out ?: ['admin']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        $name = trim((string)($_POST['name'] ?? ''));
        $channel = $_POST['channel'] ?? 'other';
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $fee_percent = (float)($_POST['fee_percent'] ?? 0);
        $fee_flat = (float)($_POST['fee_flat'] ?? 0);
        $allowed_roles = normalize_allowed_roles($_POST['allowed_roles'] ?? '');

        if ($action === 'create') {
            if ($name === '') $error = 'Name is required.';
            elseif (!in_array($channel, ['upi','bank','wallet','cash','gateway','other'], true)) $error = 'Invalid channel.';
            elseif ($fee_percent < 0 || $fee_percent > 100) $error = 'Fee percent must be 0-100.';
            elseif ($fee_flat < 0) $error = 'Fee flat must be 0 or more.';
            else {
                $config_json = null;
                $qr_path = null;
                if ($channel === 'upi') {
                    [$qr_path, $qr_err] = mk_save_qr_upload($_FILES['qr_file'] ?? null, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr', 'uploads/qr');
                    if ($qr_err !== '') $error = $qr_err;
                }
                [$built, $build_err] = build_config_json($channel, $_POST, [], $qr_path);
                if ($error === '' && $build_err !== '') $error = $build_err;
                else $config_json = $built;

                $stmt = $pdo->prepare("INSERT INTO payment_modes (name, channel, enabled, fee_percent, fee_flat, allowed_roles, config_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($error === '' && $stmt->execute([$name, $channel, $enabled, $fee_percent, $fee_flat, $allowed_roles, $config_json ?: null])) {
                    audit_log($pdo, 'create', 'payment_mode', (string)$pdo->lastInsertId(), null, ['name' => $name, 'channel' => $channel]);
                    $message = 'Payment mode created.';
                } elseif ($error === '') {
                    $error = 'Create failed.';
                }
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $error = 'Invalid mode.';
            elseif ($name === '') $error = 'Name is required.';
            elseif (!in_array($channel, ['upi','bank','wallet','cash','gateway','other'], true)) $error = 'Invalid channel.';
            elseif ($fee_percent < 0 || $fee_percent > 100) $error = 'Fee percent must be 0-100.';
            elseif ($fee_flat < 0) $error = 'Fee flat must be 0 or more.';
            else {
                $old = $pdo->prepare("SELECT * FROM payment_modes WHERE id = ?");
                $old->execute([$id]);
                $before = $old->fetch();
                if (!$before) $error = 'Invalid mode.';

                $config_json = null;
                if ($error === '') {
                    $existing_assoc = json_assoc($before['config_json'] ?? '');
                    $qr_path = null;
                    if ($channel === 'upi') {
                        [$qr_path, $qr_err] = mk_save_qr_upload($_FILES['qr_file'] ?? null, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr', 'uploads/qr');
                        if ($qr_err !== '') $error = $qr_err;
                    }
                    [$built, $build_err] = build_config_json($channel, $_POST, $existing_assoc, $qr_path);
                    if ($error === '' && $build_err !== '') $error = $build_err;
                    else $config_json = $built;
                }

                $stmt = $pdo->prepare("UPDATE payment_modes SET name=?, channel=?, enabled=?, fee_percent=?, fee_flat=?, allowed_roles=?, config_json=? WHERE id=?");
                if ($error === '' && $stmt->execute([$name, $channel, $enabled, $fee_percent, $fee_flat, $allowed_roles, $config_json ?: null, $id])) {
                    audit_log($pdo, 'update', 'payment_mode', (string)$id, $before, ['name' => $name, 'channel' => $channel, 'enabled' => $enabled, 'fee_percent' => $fee_percent, 'fee_flat' => $fee_flat, 'allowed_roles' => $allowed_roles]);
                    $message = 'Payment mode updated.';
                } elseif ($error === '') {
                    $error = 'Update failed.';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) $error = 'Invalid mode.';
            else {
                $old = $pdo->prepare("SELECT * FROM payment_modes WHERE id = ?");
                $old->execute([$id]);
                $before = $old->fetch();
                $stmt = $pdo->prepare("DELETE FROM payment_modes WHERE id = ?");
                if ($stmt->execute([$id])) {
                    audit_log($pdo, 'delete', 'payment_mode', (string)$id, $before, null);
                    $message = 'Payment mode deleted.';
                } else $error = 'Delete failed.';
            }
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT * FROM payment_modes";
$params = [];
if ($q !== '') {
    $sql .= " WHERE name LIKE ?";
    $params[] = '%' . $q . '%';
}
$sql .= " ORDER BY enabled DESC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$modes = $stmt->fetchAll();
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

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Payment Modes</h5>
      <div class="text-body-secondary">Enable/disable and set fees</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <form method="GET" class="d-flex gap-2">
        <input class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search mode" />
        <button class="btn btn-outline-primary" type="submit">Search</button>
      </form>
      <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createMode">Add Mode</button>
    </div>
  </div>
  <div class="card-body">
    <div class="collapse mb-4" id="createMode">
      <form method="POST" class="row g-3 align-items-end" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="action" value="create">
        <div class="col-12 col-md-3">
          <label class="form-label">Name</label>
          <input class="form-control" name="name" required />
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">Channel</label>
          <select class="form-select pm-channel" name="channel" data-pm-scope="pmCreate">
            <option value="upi">UPI</option>
            <option value="bank">Bank</option>
            <option value="wallet">Wallet</option>
            <option value="cash">Cash</option>
            <option value="gateway">Gateway</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="col-6 col-md-1">
          <label class="form-label">Enabled</label>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="enabled" checked>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Fee %</label>
          <input class="form-control" type="number" step="0.001" min="0" max="100" name="fee_percent" value="0">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Fee Flat</label>
          <input class="form-control" type="number" step="0.01" min="0" name="fee_flat" value="0">
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">Allowed Roles</label>
          <input class="form-control" name="allowed_roles" value="admin,master,agent" placeholder="admin,master,agent">
        </div>
        <div class="col-12">
          <label class="form-label">Details</label>
          <div class="row g-3 pm-scope" data-pm-scope="pmCreate">
            <div class="col-12 col-md-4 pm-fields pm-upi">
              <label class="form-label">UPI ID</label>
              <input class="form-control" name="upi_id" placeholder="example@upi" />
            </div>
            <div class="col-12 col-md-4 pm-fields pm-upi">
              <label class="form-label">Holder Name</label>
              <input class="form-control" name="holder_name" placeholder="Name" />
            </div>
            <div class="col-12 col-md-4 pm-fields pm-upi">
              <label class="form-label">QR Code (optional)</label>
              <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp" />
            </div>

            <div class="col-12 col-md-4 pm-fields pm-bank">
              <label class="form-label">Bank Name</label>
              <input class="form-control" name="bank_name" />
            </div>
            <div class="col-12 col-md-4 pm-fields pm-bank">
              <label class="form-label">Branch</label>
              <input class="form-control" name="branch" />
            </div>
            <div class="col-12 col-md-4 pm-fields pm-bank">
              <label class="form-label">Account Owner Name</label>
              <input class="form-control" name="account_name" />
            </div>
            <div class="col-12 col-md-4 pm-fields pm-bank">
              <label class="form-label">Account Number</label>
              <input class="form-control" name="account_number" />
            </div>
            <div class="col-12 col-md-4 pm-fields pm-bank">
              <label class="form-label">IFSC</label>
              <input class="form-control" name="ifsc" />
            </div>
            <div class="col-12 col-md-4 pm-fields pm-bank">
              <label class="form-label">SWIFT (optional)</label>
              <input class="form-control" name="swift" />
            </div>
            <div class="col-12 col-md-4 pm-fields pm-bank">
              <label class="form-label">IBAN (optional)</label>
              <input class="form-control" name="iban" />
            </div>

            <div class="col-12 col-md-4 pm-fields pm-wallet">
              <label class="form-label">Wallet Name</label>
              <input class="form-control" name="wallet_name" placeholder="Bkash/Nagad/USDT/..." />
            </div>
            <div class="col-12 col-md-8 pm-fields pm-wallet">
              <label class="form-label">Wallet Address / Number</label>
              <input class="form-control" name="wallet_address" />
            </div>

            <div class="col-12 pm-fields pm-cash pm-gateway pm-other">
              <label class="form-label">Instructions</label>
              <textarea class="form-control" name="instructions" rows="2" placeholder="Deposit instructions"></textarea>
            </div>
            <div class="col-12 pm-fields pm-gateway pm-other">
              <label class="form-label">URL (optional)</label>
              <input class="form-control" name="url" placeholder="https://..." />
            </div>
            <div class="col-12">
              <label class="form-label">Notes (optional)</label>
              <textarea class="form-control" name="notes" rows="2"></textarea>
            </div>
            <input type="hidden" name="extra_json" value="">
            <input type="hidden" name="manual_override" value="0">
            <input type="hidden" name="config_json" value="">
          </div>
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit">Create</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#createMode">Cancel</button>
        </div>
      </form>
    </div>

    <table class="table table-hover custom-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Channel</th>
          <th>Enabled</th>
          <th>Fee %</th>
          <th>Fee Flat</th>
          <th>Roles</th>
          <th>Details</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($modes)): ?>
          <?php foreach ($modes as $m): ?>
            <?php $cfg = json_assoc($m['config_json'] ?? ''); ?>
            <tr>
              <td><?php echo (int)$m['id']; ?></td>
              <td><?php echo htmlspecialchars($m['name']); ?></td>
              <td><?php echo htmlspecialchars(strtoupper($m['channel'])); ?></td>
              <td><?php echo ((int)$m['enabled']) === 1 ? '<span class="badge bg-label-success">Yes</span>' : '<span class="badge bg-label-secondary">No</span>'; ?></td>
              <td><?php echo number_format((float)$m['fee_percent'], 3); ?></td>
              <td><?php echo number_format((float)$m['fee_flat'], 2); ?></td>
              <td><?php echo htmlspecialchars($m['allowed_roles']); ?></td>
              <td>
                <?php if (!empty($cfg)): ?>
                  <div class="small">
                    <?php foreach ($cfg as $k => $v): ?>
                      <div>
                        <span class="text-body-secondary"><?php echo htmlspecialchars((string)$k); ?>:</span>
                        <?php if (((string)$k === 'qr_path' || (string)$k === 'qr_url') && is_string($v) && trim($v) !== ''): ?>
                          <a href="<?php echo htmlspecialchars($v); ?>" target="_blank" rel="noopener">QR</a>
                        <?php else: ?>
                          <?php echo htmlspecialchars(is_scalar($v) ? (string)$v : json_encode($v)); ?>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span class="text-body-secondary">-</span>
                <?php endif; ?>
              </td>
              <td class="text-right">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editMode<?php echo (int)$m['id']; ?>">Edit</button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
            <tr>
              <td colspan="9" class="p-0">
                <div class="collapse" id="editMode<?php echo (int)$m['id']; ?>">
                  <div class="p-3">
                    <form method="POST" class="row g-3 align-items-end" enctype="multipart/form-data">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                  <div class="col-12 col-md-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="<?php echo htmlspecialchars($m['name']); ?>" required />
                  </div>
                  <div class="col-12 col-md-2">
                    <label class="form-label">Channel</label>
                    <select class="form-select pm-channel" name="channel" data-pm-scope="pmEdit<?php echo (int)$m['id']; ?>">
                      <?php foreach (['upi','bank','wallet','cash','gateway','other'] as $ch): ?>
                        <option value="<?php echo $ch; ?>" <?php echo $m['channel'] === $ch ? 'selected' : ''; ?>><?php echo strtoupper($ch); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-6 col-md-1">
                    <label class="form-label">Enabled</label>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="enabled" <?php echo ((int)$m['enabled']) === 1 ? 'checked' : ''; ?>>
                    </div>
                  </div>
                  <div class="col-6 col-md-2">
                    <label class="form-label">Fee %</label>
                    <input class="form-control" type="number" step="0.001" min="0" max="100" name="fee_percent" value="<?php echo htmlspecialchars((string)$m['fee_percent']); ?>">
                  </div>
                  <div class="col-6 col-md-2">
                    <label class="form-label">Fee Flat</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="fee_flat" value="<?php echo htmlspecialchars((string)$m['fee_flat']); ?>">
                  </div>
                  <div class="col-12 col-md-2">
                    <label class="form-label">Allowed Roles</label>
                    <input class="form-control" name="allowed_roles" value="<?php echo htmlspecialchars($m['allowed_roles']); ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Details</label>
                    <div class="row g-3 pm-scope" data-pm-scope="pmEdit<?php echo (int)$m['id']; ?>">
                      <div class="col-12 col-md-4 pm-fields pm-upi">
                        <label class="form-label">UPI ID</label>
                        <input class="form-control" name="upi_id" value="<?php echo htmlspecialchars((string)($cfg['upi_id'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-4 pm-fields pm-upi">
                        <label class="form-label">Holder Name</label>
                        <input class="form-control" name="holder_name" value="<?php echo htmlspecialchars((string)($cfg['holder_name'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-4 pm-fields pm-upi">
                        <label class="form-label">QR Code (optional)</label>
                        <?php
                          $qr = (string)($cfg['qr_path'] ?? ($cfg['qr_url'] ?? ''));
                        ?>
                        <?php if ($qr !== ''): ?>
                          <div class="mb-2">
                            <a href="<?php echo htmlspecialchars($qr); ?>" target="_blank" rel="noopener">Current QR</a>
                          </div>
                        <?php endif; ?>
                        <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp" />
                      </div>

                      <div class="col-12 col-md-4 pm-fields pm-bank">
                        <label class="form-label">Bank Name</label>
                        <input class="form-control" name="bank_name" value="<?php echo htmlspecialchars((string)($cfg['bank_name'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-4 pm-fields pm-bank">
                        <label class="form-label">Branch</label>
                        <input class="form-control" name="branch" value="<?php echo htmlspecialchars((string)($cfg['branch'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-4 pm-fields pm-bank">
                        <label class="form-label">Account Owner Name</label>
                        <input class="form-control" name="account_name" value="<?php echo htmlspecialchars((string)($cfg['account_name'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-4 pm-fields pm-bank">
                        <label class="form-label">Account Number</label>
                        <input class="form-control" name="account_number" value="<?php echo htmlspecialchars((string)($cfg['account_number'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-4 pm-fields pm-bank">
                        <label class="form-label">IFSC</label>
                        <input class="form-control" name="ifsc" value="<?php echo htmlspecialchars((string)($cfg['ifsc'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-4 pm-fields pm-bank">
                        <label class="form-label">SWIFT (optional)</label>
                        <input class="form-control" name="swift" value="<?php echo htmlspecialchars((string)($cfg['swift'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-4 pm-fields pm-bank">
                        <label class="form-label">IBAN (optional)</label>
                        <input class="form-control" name="iban" value="<?php echo htmlspecialchars((string)($cfg['iban'] ?? '')); ?>" />
                      </div>

                      <div class="col-12 col-md-4 pm-fields pm-wallet">
                        <label class="form-label">Wallet Name</label>
                        <input class="form-control" name="wallet_name" value="<?php echo htmlspecialchars((string)($cfg['wallet_name'] ?? '')); ?>" />
                      </div>
                      <div class="col-12 col-md-8 pm-fields pm-wallet">
                        <label class="form-label">Wallet Address / Number</label>
                        <input class="form-control" name="wallet_address" value="<?php echo htmlspecialchars((string)($cfg['wallet_address'] ?? '')); ?>" />
                      </div>

                      <div class="col-12 pm-fields pm-cash pm-gateway pm-other">
                        <label class="form-label">Instructions</label>
                        <textarea class="form-control" name="instructions" rows="2"><?php echo htmlspecialchars((string)($cfg['instructions'] ?? '')); ?></textarea>
                      </div>
                      <div class="col-12 pm-fields pm-gateway pm-other">
                        <label class="form-label">URL (optional)</label>
                        <input class="form-control" name="url" value="<?php echo htmlspecialchars((string)($cfg['url'] ?? '')); ?>" />
                      </div>
                      <div class="col-12">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars((string)($cfg['notes'] ?? '')); ?></textarea>
                      </div>
                      <input type="hidden" name="extra_json" value="">
                      <input type="hidden" name="manual_override" value="0">
                      <input type="hidden" name="config_json" value="">
                    </div>
                  </div>
                  <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editMode<?php echo (int)$m['id']; ?>">Cancel</button>
                  </div>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="9" class="text-center">No payment modes found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
  .pm-fields { display: none; }
  .pm-fields.is-active { display: block; }
</style>

<script>
  (function () {
    function toggle(scopeName, channel) {
      var scope = document.querySelector('.pm-scope[data-pm-scope="' + scopeName + '"]');
      if (!scope) return;
      var all = scope.querySelectorAll('.pm-fields');
      for (var i = 0; i < all.length; i++) all[i].classList.remove('is-active');
      var active = scope.querySelectorAll('.pm-' + channel);
      for (var j = 0; j < active.length; j++) active[j].classList.add('is-active');
    }

    var createSelect = document.querySelector('#createMode select[name="channel"]');
    if (createSelect) {
      createSelect.addEventListener('change', function () {
        toggle('pmCreate', createSelect.value);
      });
      toggle('pmCreate', createSelect.value);
    }

    var editSelects = document.querySelectorAll('select.pm-channel[data-pm-scope]');
    for (var k = 0; k < editSelects.length; k++) {
      (function (sel) {
        sel.addEventListener('change', function () {
          toggle(sel.getAttribute('data-pm-scope'), sel.value);
        });
        toggle(sel.getAttribute('data-pm-scope'), sel.value);
      })(editSelects[k]);
    }
  })();
</script>

<?php require '../includes/footer.php'; ?>
