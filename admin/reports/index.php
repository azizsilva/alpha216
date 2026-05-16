<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require_admin_login($admin_base);

$page_title = 'Reports';
require '../includes/header.php';

$role = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

$players_count = 0;
$payments_summary = [];
$recent_audit = [];

if ($role === 'admin') {
    $players_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='player'")->fetchColumn();
    $payments_summary = $pdo->query("SELECT status, COUNT(*) AS cnt, SUM(amount) AS amt FROM payments GROUP BY status")->fetchAll();
    $recent_audit = $pdo->query("SELECT a.*, u.username AS actor_name FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 50")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users p WHERE p.role='player' AND p.parent_id = ?");
    if ($role === 'agent') {
        $stmt->execute([$user_id]);
        $players_count = (int)$stmt->fetchColumn();
    } elseif ($role === 'master') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users p JOIN users a ON a.id=p.parent_id AND a.role='agent' WHERE p.role='player' AND a.parent_id = ?");
        $stmt->execute([$user_id]);
        $players_count = (int)$stmt->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt, SUM(amount) AS amt FROM payments WHERE (payer_id=? OR payee_id=? OR created_by=?) GROUP BY status");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $payments_summary = $stmt->fetchAll();
}
?>

<div class="row g-3 mb-4">
  <div class="col-12 col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-body-secondary">Registered Players</div>
            <h4 class="mb-0"><?php echo (int)$players_count; ?></h4>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="../players/">Open</a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-8">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="text-body-secondary">Transactions</div>
            <div class="small text-body-secondary">Use filters + export CSV</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-primary" href="../transactions/">Transaction Report</a>
            <a class="btn btn-sm btn-outline-secondary" href="../bet-list/">Bet List</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($role === 'admin'): ?>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="text-body-secondary">Bonus</div>
            <div class="small text-body-secondary">Points ledger + exports</div>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="./bonus/">Open</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="text-body-secondary">Loyalty</div>
            <div class="small text-body-secondary">Points ledger + totals</div>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="./loyalty/">Open</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="text-body-secondary">Settlements</div>
            <div class="small text-body-secondary">Status + exports</div>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="./settlements/">Open</a>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Payments Summary</h5>
      <div class="text-body-secondary">By status</div>
    </div>
    <a class="btn btn-outline-primary" href="../payments/">Open Payments</a>
  </div>
  <div class="card-body">
    <table class="table table-hover custom-table">
      <thead>
        <tr>
          <th>Status</th>
          <th>Count</th>
          <th>Total Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($payments_summary)): ?>
          <?php foreach ($payments_summary as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars(strtoupper($r['status'] ?? '-')); ?></td>
              <td><?php echo (int)($r['cnt'] ?? 0); ?></td>
              <td><?php echo number_format((float)($r['amt'] ?? 0), 2); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="3" class="text-center">No data.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($role === 'admin'): ?>
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h5 class="mb-1">Audit Trail</h5>
        <div class="text-body-secondary">Latest 50 actions</div>
      </div>
    </div>
    <div class="card-body">
      <table class="table table-hover custom-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Actor</th>
            <th>Role</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Entity ID</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recent_audit)): ?>
            <?php foreach ($recent_audit as $a): ?>
              <tr>
                <td><?php echo htmlspecialchars((string)$a['created_at']); ?></td>
                <td><?php echo htmlspecialchars($a['actor_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(strtoupper($a['actor_role'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars($a['action'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($a['entity_type'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($a['entity_id'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($a['ip'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center">No audit logs.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>
