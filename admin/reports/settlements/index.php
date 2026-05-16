<?php
$admin_base = '../../';
require '../../includes/db.php';
require '../../includes/auth.php';
require '../../includes/audit.php';

require_admin_login($admin_base);
require_admin_role(['admin'], $admin_base);

$page_title = 'Settlement Report';
require '../../includes/header.php';

$message = '';
$error = '';

$filters = [
    'from_user_id' => (int)($_GET['from_user_id'] ?? 0),
    'to_user_id' => (int)($_GET['to_user_id'] ?? 0),
    'status' => trim((string)($_GET['status'] ?? '')),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'sort' => trim((string)($_GET['sort'] ?? 'created_at')),
    'dir' => trim((string)($_GET['dir'] ?? 'desc')),
    'page' => max(1, (int)($_GET['page'] ?? 1)),
    'per' => (int)($_GET['per'] ?? 50),
    'export' => trim((string)($_GET['export'] ?? ''))
];

$allowedSort = ['id', 'created_at', 'amount', 'status', 'from_user_id', 'to_user_id'];
if (!in_array($filters['sort'], $allowedSort, true)) $filters['sort'] = 'created_at';
$filters['dir'] = strtolower($filters['dir']) === 'asc' ? 'asc' : 'desc';
if ($filters['per'] < 10) $filters['per'] = 10;
if ($filters['per'] > 200) $filters['per'] = 200;
$offset = ($filters['page'] - 1) * $filters['per'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_settlement'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $from_uid = (int)($_POST['from_user_id'] ?? 0);
        $to_uid = (int)($_POST['to_user_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'completed'));
        $note = trim((string)($_POST['note'] ?? ''));

        $allowedStatus = ['pending','completed','failed'];
        if (!in_array($status, $allowedStatus, true)) $status = 'completed';

        if ($from_uid <= 0 || $to_uid <= 0 || $from_uid === $to_uid) {
            $error = 'Invalid users.';
        } elseif ($amount <= 0) {
            $error = 'Amount must be > 0.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO settlements (from_user_id, to_user_id, amount, status, note, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$from_uid, $to_uid, $amount, $status, $note !== '' ? $note : null, (int)($_SESSION['user_id'] ?? 0)])) {
                audit_log($pdo, 'settlement_add', 'settlements', (string)$pdo->lastInsertId(), null, ['from_user_id' => $from_uid, 'to_user_id' => $to_uid, 'amount' => $amount, 'status' => $status]);
                $message = 'Settlement entry added.';
            } else {
                $error = 'Insert failed.';
            }
        }
    }
}

$where = " WHERE 1=1";
$params = [];
if ($filters['from_user_id'] > 0) {
    $where .= " AND s.from_user_id = ?";
    $params[] = $filters['from_user_id'];
}
if ($filters['to_user_id'] > 0) {
    $where .= " AND s.to_user_id = ?";
    $params[] = $filters['to_user_id'];
}
if ($filters['status'] !== '' && in_array($filters['status'], ['pending','completed','failed'], true)) {
    $where .= " AND s.status = ?";
    $params[] = $filters['status'];
}
if ($filters['from'] !== '') {
    $where .= " AND s.created_at >= ?";
    $params[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to'] !== '') {
    $where .= " AND s.created_at <= ?";
    $params[] = $filters['to'] . ' 23:59:59';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM settlements s" . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sumStmt = $pdo->prepare("SELECT COALESCE(SUM(s.amount),0) FROM settlements s" . $where);
$sumStmt->execute($params);
$total_amount = (float)$sumStmt->fetchColumn();

$sql = "SELECT s.*,
          fu.username AS from_username,
          tu.username AS to_username
        FROM settlements s
        LEFT JOIN users fu ON fu.id = s.from_user_id
        LEFT JOIN users tu ON tu.id = s.to_user_id
        $where
        ORDER BY s." . $filters['sort'] . " " . strtoupper($filters['dir']) . "
        LIMIT " . (int)$filters['per'] . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (in_array($filters['export'], ['csv', 'excel', 'pdf'], true)) {
    $expSql = "SELECT s.*,
                fu.username AS from_username,
                tu.username AS to_username
              FROM settlements s
              LEFT JOIN users fu ON fu.id = s.from_user_id
              LEFT JOIN users tu ON tu.id = s.to_user_id
              $where
              ORDER BY s." . $filters['sort'] . " " . strtoupper($filters['dir']);
    $exp = $pdo->prepare($expSql);
    $exp->execute($params);
    $all = $exp->fetchAll();

    if ($filters['export'] === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Settlement Report</title></head><body>';
        echo '<h3>Settlement Report</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0"><thead><tr><th>ID</th><th>Time</th><th>From</th><th>To</th><th>Amount</th><th>Status</th><th>Note</th></tr></thead><tbody>';
        foreach ($all as $r) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string)$r['id']) . '</td>';
            echo '<td>' . htmlspecialchars((string)$r['created_at']) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['from_username'] ?? '')) . ' (#' . htmlspecialchars((string)$r['from_user_id']) . ')</td>';
            echo '<td>' . htmlspecialchars((string)($r['to_username'] ?? '')) . ' (#' . htmlspecialchars((string)$r['to_user_id']) . ')</td>';
            echo '<td>' . htmlspecialchars((string)$r['amount']) . '</td>';
            echo '<td>' . htmlspecialchars((string)$r['status']) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['note'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<script>window.print();</script>';
        echo '</body></html>';
        exit;
    }

    $filename = $filters['export'] === 'excel' ? 'settlement_report.xls' : 'settlement_report.csv';
    header('Content-Type: ' . ($filters['export'] === 'excel' ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Time', 'From', 'To', 'Amount', 'Status', 'Note']);
    foreach ($all as $r) {
        $from = ($r['from_username'] ?? '') . ' (#' . $r['from_user_id'] . ')';
        $to = ($r['to_username'] ?? '') . ' (#' . $r['to_user_id'] . ')';
        fputcsv($out, [$r['id'], $r['created_at'], $from, $to, $r['amount'], $r['status'], $r['note'] ?? '']);
    }
    fclose($out);
    exit;
}

function page_link($filters, $overrides = []) {
    $q = array_merge($filters, $overrides);
    unset($q['export']);
    return '?' . http_build_query($q);
}

$pages = $filters['per'] > 0 ? (int)ceil($total / $filters['per']) : 1;
if ($pages < 1) $pages = 1;
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
      <h5 class="mb-1">Settlement Report</h5>
      <div class="text-body-secondary">Total amount: <?php echo number_format($total_amount, 2); ?></div>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end mb-4">
      <div class="col-12 col-md-2">
        <label class="form-label">From User ID</label>
        <input type="number" class="form-control" name="from_user_id" value="<?php echo htmlspecialchars((string)$filters['from_user_id']); ?>" placeholder="All">
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">To User ID</label>
        <input type="number" class="form-control" name="to_user_id" value="<?php echo htmlspecialchars((string)$filters['to_user_id']); ?>" placeholder="All">
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="" <?php echo $filters['status']==='' ? 'selected' : ''; ?>>All</option>
          <option value="pending" <?php echo $filters['status']==='pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="completed" <?php echo $filters['status']==='completed' ? 'selected' : ''; ?>>Completed</option>
          <option value="failed" <?php echo $filters['status']==='failed' ? 'selected' : ''; ?>>Failed</option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">From</label>
        <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">To</label>
        <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Per Page</label>
        <select class="form-select" name="per">
          <?php foreach ([25,50,100,200] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $filters['per']===$pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Filter</button>
        <a class="btn btn-outline-secondary w-100" href="?">Reset</a>
      </div>
    </form>

    <form method="POST" class="row g-3 align-items-end mb-4">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <input type="hidden" name="add_settlement" value="1">
      <div class="col-12 col-md-2">
        <label class="form-label">From User ID</label>
        <input type="number" class="form-control" name="from_user_id" required>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">To User ID</label>
        <input type="number" class="form-control" name="to_user_id" required>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Amount</label>
        <input type="number" step="0.01" class="form-control" name="amount" required>
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
        <label class="form-label">Note</label>
        <input type="text" class="form-control" name="note" maxlength="255">
      </div>
      <div class="col-12 col-md-1">
        <button class="btn btn-outline-primary w-100" type="submit">Add</button>
      </div>
    </form>

    <table class="table table-hover custom-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Time</th>
          <th>From</th>
          <th>To</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
              <td><?php echo htmlspecialchars((string)($r['from_username'] ?? '')); ?> (#<?php echo (int)$r['from_user_id']; ?>)</td>
              <td><?php echo htmlspecialchars((string)($r['to_username'] ?? '')); ?> (#<?php echo (int)$r['to_user_id']; ?>)</td>
              <td><?php echo number_format((float)$r['amount'], 2); ?></td>
              <td><?php echo htmlspecialchars(strtoupper((string)$r['status'])); ?></td>
              <td><?php echo htmlspecialchars((string)($r['note'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="text-center"><div class="mk-empty-state"><img src="https://moneyking365.com/assets/images/norecode.png" alt="No records"></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
      <div class="text-body-secondary">Total: <?php echo (int)$total; ?></div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary <?php echo $filters['page']<=1 ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars(page_link($filters, ['page' => max(1, $filters['page'] - 1)])); ?>">Prev</a>
        <div class="text-body-secondary" style="align-self:center;">Page <?php echo (int)$filters['page']; ?> / <?php echo (int)$pages; ?></div>
        <a class="btn btn-sm btn-outline-secondary <?php echo $filters['page']>=$pages ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars(page_link($filters, ['page' => min($pages, $filters['page'] + 1)])); ?>">Next</a>
      </div>
    </div>
  </div>
</div>

<?php require '../../includes/footer.php'; ?>
