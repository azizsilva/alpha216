<?php
$admin_base = '../../';
require '../../includes/db.php';
require '../../includes/auth.php';

require_admin_login($admin_base);
require_admin_role(['admin', 'master', 'agent'], $admin_base);

$page_title = 'Registered Players Report';
require '../../includes/header.php';

$role = $_SESSION['role'] ?? 'admin';
$actor_id = (int)($_SESSION['user_id'] ?? 0);

$filters = [
    'master_id' => (int)($_GET['master_id'] ?? 0),
    'agent_id' => (int)($_GET['agent_id'] ?? 0),
    'status' => trim((string)($_GET['status'] ?? '')),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'q' => trim((string)($_GET['q'] ?? '')),
    'export' => trim((string)($_GET['export'] ?? ''))
];

$allowedStatus = ['active','locked','suspended'];
if (!in_array($filters['status'], $allowedStatus, true)) $filters['status'] = '';

$where = " WHERE p.role='player'";
$params = [];

if ($filters['agent_id'] > 0) {
    $where .= " AND a.id = ?";
    $params[] = $filters['agent_id'];
}
if ($filters['master_id'] > 0) {
    $where .= " AND m.id = ?";
    $params[] = $filters['master_id'];
}
if ($filters['status'] !== '') {
    $where .= " AND p.status = ?";
    $params[] = $filters['status'];
}
if ($filters['from'] !== '') {
    $where .= " AND p.created_at >= ?";
    $params[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to'] !== '') {
    $where .= " AND p.created_at <= ?";
    $params[] = $filters['to'] . ' 23:59:59';
}
if ($filters['q'] !== '') {
    $where .= " AND (p.username LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
    $params[] = '%' . $filters['q'] . '%';
    $params[] = '%' . $filters['q'] . '%';
}

$scope = "";
if ($role === 'agent') {
    $scope = " AND a.id = " . (int)$actor_id;
} elseif ($role === 'master') {
    $scope = " AND m.id = " . (int)$actor_id;
}

$sql = "SELECT p.id, p.username, p.status, p.balance, p.exposure, p.credit_ref, p.created_at,
          a.id AS agent_id, a.username AS agent_name,
          m.id AS master_id, m.username AS master_name
        FROM users p
        LEFT JOIN users a ON a.id = p.parent_id AND a.role='agent'
        LEFT JOIN users m ON m.id = a.parent_id AND m.role='master'
        $where $scope
        ORDER BY p.id DESC
        LIMIT 5000";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totals = [
    'players' => count($rows),
    'balance' => 0.0,
    'exposure' => 0.0,
    'available' => 0.0
];
foreach ($rows as $r) {
    $b = (float)($r['balance'] ?? 0);
    $e = (float)($r['exposure'] ?? 0);
    $totals['balance'] += $b;
    $totals['exposure'] += $e;
    $totals['available'] += ($b - $e);
}

if (in_array($filters['export'], ['csv', 'excel', 'pdf'], true)) {
    if ($filters['export'] === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Registered Players Report</title></head><body>';
        echo '<h3>Registered Players Report</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0"><thead><tr><th>ID</th><th>Username</th><th>Agent</th><th>Master</th><th>Status</th><th>Balance</th><th>Exposure</th><th>Created</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string)$r['id']) . '</td>';
            echo '<td>' . htmlspecialchars((string)$r['username']) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['agent_name'] ?? '')) . ' (#' . htmlspecialchars((string)($r['agent_id'] ?? 0)) . ')</td>';
            echo '<td>' . htmlspecialchars((string)($r['master_name'] ?? '')) . ' (#' . htmlspecialchars((string)($r['master_id'] ?? 0)) . ')</td>';
            echo '<td>' . htmlspecialchars(strtoupper((string)($r['status'] ?? ''))) . '</td>';
            echo '<td>' . htmlspecialchars(number_format((float)($r['balance'] ?? 0), 2)) . '</td>';
            echo '<td>' . htmlspecialchars(number_format((float)($r['exposure'] ?? 0), 2)) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['created_at'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<script>window.print();</script>';
        echo '</body></html>';
        exit;
    }

    $filename = $filters['export'] === 'excel' ? 'players_report.xls' : 'players_report.csv';
    header('Content-Type: ' . ($filters['export'] === 'excel' ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Username', 'Agent', 'Master', 'Status', 'Balance', 'Exposure', 'Created']);
    foreach ($rows as $r) {
        $agent = ($r['agent_name'] ?? '') . ' (#' . ($r['agent_id'] ?? 0) . ')';
        $master = ($r['master_name'] ?? '') . ' (#' . ($r['master_id'] ?? 0) . ')';
        fputcsv($out, [
            $r['id'],
            $r['username'],
            $agent,
            $master,
            strtoupper((string)($r['status'] ?? '')),
            number_format((float)($r['balance'] ?? 0), 2, '.', ''),
            number_format((float)($r['exposure'] ?? 0), 2, '.', ''),
            (string)($r['created_at'] ?? '')
        ]);
    }
    fclose($out);
    exit;
}
?>

<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Registered Players Report</h5>
      <div class="text-body-secondary">
        Players: <?php echo (int)$totals['players']; ?> |
        Wallet: <?php echo number_format((float)$totals['balance'], 2); ?> |
        Exposure: <?php echo number_format((float)$totals['exposure'], 2); ?> |
        Available: <?php echo number_format((float)$totals['available'], 2); ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="mk-filterbar mb-4">
      <div>
        <label class="form-label">Master</label>
        <input type="number" class="form-control" name="master_id" value="<?php echo htmlspecialchars((string)$filters['master_id']); ?>" placeholder="All">
      </div>
      <div>
        <label class="form-label">Agent</label>
        <input type="number" class="form-control" name="agent_id" value="<?php echo htmlspecialchars((string)$filters['agent_id']); ?>" placeholder="All">
      </div>
      <div>
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="" <?php echo $filters['status']==='' ? 'selected' : ''; ?>>All</option>
          <option value="active" <?php echo $filters['status']==='active' ? 'selected' : ''; ?>>Active</option>
          <option value="locked" <?php echo $filters['status']==='locked' ? 'selected' : ''; ?>>Locked</option>
          <option value="suspended" <?php echo $filters['status']==='suspended' ? 'selected' : ''; ?>>Suspended</option>
        </select>
      </div>
      <div>
        <label class="form-label">From</label>
        <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
      </div>
      <div>
        <label class="form-label">To</label>
        <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
      </div>
      <div>
        <label class="form-label">Search</label>
        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="ID / Username">
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-outline-secondary" href="?">Reset</a>
      </div>
    </form>

    <table class="table table-hover custom-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Agent</th>
          <th>Master</th>
          <th>Status</th>
          <th>Wallet</th>
          <th>Exposure</th>
          <th>Available</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $b = (float)($r['balance'] ?? 0);
              $e = (float)($r['exposure'] ?? 0);
              $av = $b - $e;
            ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><?php echo htmlspecialchars((string)$r['username']); ?></td>
              <td><?php echo htmlspecialchars((string)($r['agent_name'] ?? '')); ?> (#<?php echo (int)($r['agent_id'] ?? 0); ?>)</td>
              <td><?php echo htmlspecialchars((string)($r['master_name'] ?? '')); ?> (#<?php echo (int)($r['master_id'] ?? 0); ?>)</td>
              <td><?php echo htmlspecialchars(strtoupper((string)($r['status'] ?? ''))); ?></td>
              <td><?php echo number_format($b, 2); ?></td>
              <td><?php echo number_format($e, 2); ?></td>
              <td><?php echo number_format($av, 2); ?></td>
              <td><?php echo htmlspecialchars((string)($r['created_at'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="9" class="text-center"><div class="mk-empty-state"><img src="https://moneyking365.com/assets/images/norecode.png" alt="No records"></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require '../../includes/footer.php'; ?>
