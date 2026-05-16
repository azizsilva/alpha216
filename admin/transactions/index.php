<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require_admin_login($admin_base);

$page_title = 'Transactions';
require '../includes/header.php';

$role = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

$filters = [
    'user_id' => (int)($_GET['user_id'] ?? 0),
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'source' => $_GET['source'] ?? 'all', // all|payments|legacy
    'export' => $_GET['export'] ?? ''
];

$rows = [];

function date_range_sql($field, $from, $to, &$params) {
    $sql = '';
    if ($from !== '') {
        $sql .= " AND $field >= ?";
        $params[] = $from . " 00:00:00";
    }
    if ($to !== '') {
        $sql .= " AND $field <= ?";
        $params[] = $to . " 23:59:59";
    }
    return $sql;
}

if ($filters['source'] === 'all' || $filters['source'] === 'payments') {
    $params = [];
    $sql = "SELECT p.id AS ref_id, 'PAYMENT' AS source, p.type AS txn_type, p.status AS txn_status,
                p.amount, p.created_at,
                u1.username AS sender, u2.username AS receiver,
                m.name AS mode_name
            FROM payments p
            LEFT JOIN users u1 ON u1.id = p.payer_id
            LEFT JOIN users u2 ON u2.id = p.payee_id
            LEFT JOIN payment_modes m ON m.id = p.mode_id
            WHERE 1=1";

    if ($filters['user_id'] > 0) {
        $sql .= " AND (p.payer_id = ? OR p.payee_id = ? OR p.created_by = ?)";
        array_push($params, $filters['user_id'], $filters['user_id'], $filters['user_id']);
    } elseif ($role !== 'admin') {
        $sql .= " AND (p.payer_id = ? OR p.payee_id = ? OR p.created_by = ?)";
        array_push($params, $user_id, $user_id, $user_id);
    }
    $sql .= date_range_sql('p.created_at', $filters['from'], $filters['to'], $params);
    $sql .= " ORDER BY p.id DESC";
    if ($filters['export'] === '') $sql .= " LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'date' => (string)$r['created_at'],
            'source' => $r['source'],
            'id' => (string)$r['ref_id'],
            'type' => (string)$r['txn_type'],
            'status' => (string)$r['txn_status'],
            'amount' => (string)$r['amount'],
            'mode' => (string)($r['mode_name'] ?? ''),
            'from' => (string)($r['sender'] ?? ''),
            'to' => (string)($r['receiver'] ?? '')
        ];
    }
}

if ($filters['source'] === 'all' || $filters['source'] === 'legacy') {
    $params = [];
    $sql = "SELECT t.id AS ref_id, 'LEGACY' AS source, t.type AS txn_type, 'completed' AS txn_status,
                t.amount, t.created_at,
                u1.username AS sender, u2.username AS receiver,
                '' AS mode_name
            FROM transactions t
            LEFT JOIN users u1 ON u1.id = t.sender_id
            LEFT JOIN users u2 ON u2.id = t.receiver_id
            WHERE 1=1";

    if ($filters['user_id'] > 0) {
        $sql .= " AND (t.sender_id = ? OR t.receiver_id = ?)";
        array_push($params, $filters['user_id'], $filters['user_id']);
    } elseif ($role !== 'admin') {
        $sql .= " AND (t.sender_id = ? OR t.receiver_id = ?)";
        array_push($params, $user_id, $user_id);
    }
    $sql .= date_range_sql('t.created_at', $filters['from'], $filters['to'], $params);
    $sql .= " ORDER BY t.id DESC";
    if ($filters['export'] === '') $sql .= " LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'date' => (string)$r['created_at'],
            'source' => $r['source'],
            'id' => (string)$r['ref_id'],
            'type' => (string)$r['txn_type'],
            'status' => (string)$r['txn_status'],
            'amount' => (string)$r['amount'],
            'mode' => '',
            'from' => (string)($r['sender'] ?? ''),
            'to' => (string)($r['receiver'] ?? '')
        ];
    }
}

usort($rows, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

if ($filters['export'] === 'csv' || $filters['export'] === 'excel') {
    $is_excel = $filters['export'] === 'excel';
    header('Content-Type: ' . ($is_excel ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="transactions.' . ($is_excel ? 'xls' : 'csv') . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Source', 'ID', 'Type', 'Status', 'Amount', 'Mode', 'From', 'To']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['date'], $r['source'], $r['id'], $r['type'], $r['status'], $r['amount'], $r['mode'], $r['from'], $r['to']]);
    }
    fclose($out);
    exit;
}
?>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Transactions</h5>
      <div class="text-body-secondary">Payments + legacy transfers</div>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="mk-filterbar mb-3">
      <div>
        <label class="form-label">Source</label>
        <select class="form-select" name="source">
          <?php foreach (['all' => 'All', 'payments' => 'Payments', 'legacy' => 'Legacy'] as $k => $v): ?>
            <option value="<?php echo $k; ?>" <?php echo $filters['source'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">User ID</label>
        <input class="form-control" type="number" name="user_id" value="<?php echo htmlspecialchars((string)$filters['user_id']); ?>" placeholder="Optional">
      </div>
      <div>
        <label class="form-label">From</label>
        <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
      </div>
      <div>
        <label class="form-label">To</label>
        <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-outline-secondary" href="./">Reset</a>
      </div>
    </form>

    <table class="table table-hover custom-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Source</th>
          <th>ID</th>
          <th>Type</th>
          <th>Status</th>
          <th>Amount</th>
          <th>Mode</th>
          <th>From</th>
          <th>To</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['date']); ?></td>
              <td><?php echo htmlspecialchars($r['source']); ?></td>
              <td><?php echo htmlspecialchars($r['id']); ?></td>
              <td><?php echo htmlspecialchars(strtoupper($r['type'])); ?></td>
              <td><?php echo htmlspecialchars(strtoupper($r['status'])); ?></td>
              <td><?php echo number_format((float)$r['amount'], 2); ?></td>
              <td><?php echo htmlspecialchars($r['mode'] ?: '-'); ?></td>
              <td><?php echo htmlspecialchars($r['from'] ?: '-'); ?></td>
              <td><?php echo htmlspecialchars($r['to'] ?: '-'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="9" class="text-center"><div class="mk-empty-state"><img src="https://moneyking365.com/assets/images/norecode.png" alt="No records"></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
