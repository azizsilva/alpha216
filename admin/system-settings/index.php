<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';

require_admin_login($admin_base);
require_admin_role(['admin'], $admin_base);

$page_title = 'System Settings';
require '../includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $settings = [
            'global_commission_rate' => trim((string)($_POST['global_commission_rate'] ?? '')),
            'global_credit_limit' => trim((string)($_POST['global_credit_limit'] ?? '')),
            'exposure_threshold' => trim((string)($_POST['exposure_threshold'] ?? '')),
            'payment_gateway_status' => trim((string)($_POST['payment_gateway_status'] ?? '')),
            'notifications_enabled' => isset($_POST['notifications_enabled']) ? '1' : '0'
        ];

        try {
            foreach ($settings as $k => $v) {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
                $stmt->execute([$k, $v, (int)($_SESSION['user_id'] ?? 0)]);
            }
            audit_log($pdo, 'update', 'system_settings', 'bulk', null, $settings);
            $message = 'Settings saved.';
        } catch (Exception $e) {
            $error = 'Save failed.';
        }
    }
}

$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$rows = $stmt->fetchAll();
$map = [];
foreach ($rows as $r) $map[$r['setting_key']] = $r['setting_value'];
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
      <h5 class="mb-1">System Settings</h5>
      <div class="text-body-secondary">Global financial and gateway flags</div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <div class="col-12 col-md-4">
        <label class="form-label">Global Commission Rate (%)</label>
        <input class="form-control" name="global_commission_rate" value="<?php echo htmlspecialchars((string)($map['global_commission_rate'] ?? '')); ?>" placeholder="e.g. 100">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Global Credit Limit</label>
        <input class="form-control" name="global_credit_limit" value="<?php echo htmlspecialchars((string)($map['global_credit_limit'] ?? '')); ?>" placeholder="e.g. 0">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Exposure Threshold</label>
        <input class="form-control" name="exposure_threshold" value="<?php echo htmlspecialchars((string)($map['exposure_threshold'] ?? '')); ?>" placeholder="e.g. 0">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Payment Gateway Status</label>
        <select class="form-select" name="payment_gateway_status">
          <?php $st = (string)($map['payment_gateway_status'] ?? 'disabled'); ?>
          <option value="disabled" <?php echo $st === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
          <option value="sandbox" <?php echo $st === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
          <option value="live" <?php echo $st === 'live' ? 'selected' : ''; ?>>Live</option>
        </select>
        <div class="form-text">Credentials should be stored outside codebase.</div>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Notifications</label>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="notifications_enabled" id="notifications_enabled" <?php echo ($map['notifications_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
          <label class="form-check-label" for="notifications_enabled">Enable transaction notifications</label>
        </div>
      </div>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

<?php require '../includes/footer.php'; ?>

