<?php
$admin_base = '../../';
require '../../includes/db.php';
require '../../includes/auth.php';
require '../../includes/audit.php';

require_admin_login($admin_base);
require_admin_role(['admin'], $admin_base);

$page_title = 'Bonus Report';
require '../../includes/header.php';

$message = '';
$error = '';

$filters = [
    'user_id' => (int)($_GET['user_id'] ?? 0),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'sort' => trim((string)($_GET['sort'] ?? 'created_at')),
    'dir' => trim((string)($_GET['dir'] ?? 'desc')),
    'page' => max(1, (int)($_GET['page'] ?? 1)),
    'per' => (int)($_GET['per'] ?? 50),
    'export' => trim((string)($_GET['export'] ?? ''))
];

$allowedSort = ['id', 'created_at', 'points', 'user_id'];
if (!in_array($filters['sort'], $allowedSort, true)) $filters['sort'] = 'created_at';
$filters['dir'] = strtolower($filters['dir']) === 'asc' ? 'asc' : 'desc';
if ($filters['per'] < 10) $filters['per'] = 10;
if ($filters['per'] > 200) $filters['per'] = 200;
$offset = ($filters['page'] - 1) * $filters['per'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bonus'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $uid = (int)($_POST['user_id'] ?? 0);
        $points = (float)($_POST['points'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        if ($uid <= 0) {
            $error = 'Invalid user.';
        } elseif ($points == 0) {
            $error = 'Points cannot be 0.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO bonus_ledger (user_id, points, note, created_by) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$uid, $points, $note !== '' ? $note : null, (int)($_SESSION['user_id'] ?? 0)])) {
                audit_log($pdo, 'bonus_add', 'bonus_ledger', (string)$pdo->lastInsertId(), null, ['user_id' => $uid, 'points' => $points]);
                $message = 'Bonus entry added.';
            } else {
                $error = 'Insert failed.';
            }
        }
    }
}

$where = " WHERE 1=1";
$params = [];
if ($filters['user_id'] > 0) {
    $where .= " AND b.user_id = ?";
    $params[] = $filters['user_id'];
}
if ($filters['from'] !== '') {
    $where .= " AND b.created_at >= ?";
    $params[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to'] !== '') {
    $where .= " AND b.created_at <= ?";
    $params[] = $filters['to'] . ' 23:59:59';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bonus_ledger b" . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sumStmt = $pdo->prepare("SELECT COALESCE(SUM(b.points),0) FROM bonus_ledger b" . $where);
$sumStmt->execute($params);
$total_points = (float)$sumStmt->fetchColumn();

$sql = "SELECT b.*, u.username
        FROM bonus_ledger b
        LEFT JOIN users u ON u.id = b.user_id
        $where
        ORDER BY b." . $filters['sort'] . " " . strtoupper($filters['dir']) . "
        LIMIT " . (int)$filters['per'] . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (in_array($filters['export'], ['csv', 'excel', 'pdf'], true)) {
    $expSql = "SELECT b.*, u.username
               FROM bonus_ledger b
               LEFT JOIN users u ON u.id = b.user_id
               $where
               ORDER BY b." . $filters['sort'] . " " . strtoupper($filters['dir']);
    $exp = $pdo->prepare($expSql);
    $exp->execute($params);
    $all = $exp->fetchAll();

    if ($filters['export'] === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Bonus Report</title></head><body>';
        echo '<h3>Bonus Report</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0"><thead><tr><th>ID</th><th>Time</th><th>User</th><th>User ID</th><th>Points</th><th>Note</th></tr></thead><tbody>';
        foreach ($all as $r) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string)$r['id']) . '</td>';
            echo '<td>' . htmlspecialchars((string)$r['created_at']) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['username'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string)$r['user_id']) . '</td>';
            echo '<td>' . htmlspecialchars((string)$r['points']) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['note'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<script>window.print();</script>';
        echo '</body></html>';
        exit;
    }

    $filename = $filters['export'] === 'excel' ? 'bonus_report.xls' : 'bonus_report.csv';
    header('Content-Type: ' . ($filters['export'] === 'excel' ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Time', 'User', 'User ID', 'Points', 'Note']);
    foreach ($all as $r) {
        fputcsv($out, [$r['id'], $r['created_at'], $r['username'] ?? '', $r['user_id'], $r['points'], $r['note'] ?? '']);
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
      <h5 class="mb-1">Bonus Report</h5>
      <div class="text-body-secondary">Total points: <?php echo number_format($total_points, 2); ?></div>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end mb-4">
      <div class="col-12 col-md-2">
        <label class="form-label">User ID</label>
        <input type="number" class="form-control" name="user_id" value="<?php echo htmlspecialchars((string)$filters['user_id']); ?>" placeholder="All">
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
      <div class="col-12 col-md-4 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Filter</button>
        <a class="btn btn-outline-secondary w-100" href="?">Reset</a>
      </div>
    </form>

    <form method="POST" class="row g-3 align-items-end mb-4">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <input type="hidden" name="add_bonus" value="1">
      <div class="col-12 col-md-2">
        <label class="form-label">Add User ID</label>
        <input type="number" class="form-control" name="user_id" required>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Points</label>
        <input type="number" step="0.01" class="form-control" name="points" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Note</label>
        <input type="text" class="form-control" name="note" maxlength="255">
      </div>
      <div class="col-12 col-md-2">
        <button class="btn btn-outline-primary w-100" type="submit">Add</button>
      </div>
    </form>

    <table class="table table-hover custom-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Time</th>
          <th>User</th>
          <th>User ID</th>
          <th>Points</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
              <td><?php echo htmlspecialchars((string)($r['username'] ?? '')); ?></td>
              <td><?php echo (int)$r['user_id']; ?></td>
              <td><?php echo number_format((float)$r['points'], 2); ?></td>
              <td><?php echo htmlspecialchars((string)($r['note'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center"><div class="mk-empty-state"><img src="https://moneyking365.com/assets/images/norecode.png" alt="No records"></div></td></tr>
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
