<?php
$admin_base = '../../';
require '../../includes/db.php';
require '../../includes/auth.php';

require_admin_login($admin_base);
require_admin_role(['admin', 'master', 'agent'], $admin_base);

$page_title = 'Profit/Loss Report';
require '../../includes/header.php';

$role = $_SESSION['role'] ?? 'admin';
$actor_id = (int)($_SESSION['user_id'] ?? 0);

$filters = [
    'user_id' => (int)($_GET['user_id'] ?? 0),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'game_uid' => trim((string)($_GET['game_uid'] ?? '')),
    'export' => trim((string)($_GET['export'] ?? ''))
];

$where = " WHERE e.result_status = 1";
$params = [];

if ($filters['user_id'] > 0) {
    $where .= " AND e.user_id = ?";
    $params[] = $filters['user_id'];
}
if ($filters['game_uid'] !== '') {
    $where .= " AND e.game_uid = ?";
    $params[] = $filters['game_uid'];
}
if ($filters['from'] !== '') {
    $where .= " AND e.created_at >= ?";
    $params[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to'] !== '') {
    $where .= " AND e.created_at <= ?";
    $params[] = $filters['to'] . ' 23:59:59';
}

$scopeJoin = "";
$scopeWhere = "";
if ($role === 'agent') {
    $scopeJoin = " JOIN users p ON p.id = e.user_id AND p.role='player' ";
    $scopeWhere = " AND p.parent_id = " . (int)$actor_id;
} elseif ($role === 'master') {
    $scopeJoin = " JOIN users p ON p.id = e.user_id AND p.role='player'
                   JOIN users a ON a.id = p.parent_id AND a.role='agent' ";
    $scopeWhere = " AND a.parent_id = " . (int)$actor_id;
}

$sql = "SELECT e.user_id,
          COALESCE(u.username, '') AS username,
          SUM(CASE WHEN e.action='bet' THEN e.bet_amount ELSE 0 END) AS total_bet,
          SUM(CASE WHEN e.action='win' THEN e.win_amount ELSE 0 END) AS total_win,
          SUM(CASE WHEN e.action='refund' THEN e.bet_amount ELSE 0 END) AS total_refund,
          COUNT(*) AS events
        FROM game_callback_events e
        LEFT JOIN users u ON u.id = e.user_id
        $scopeJoin
        $where $scopeWhere
        GROUP BY e.user_id, u.username
        ORDER BY e.user_id DESC
        LIMIT 5000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totals = [
    'total_bet' => 0.0,
    'total_win' => 0.0,
    'total_refund' => 0.0,
    'net_pl' => 0.0,
    'events' => 0
];
foreach ($rows as $r) {
    $tb = (float)($r['total_bet'] ?? 0);
    $tw = (float)($r['total_win'] ?? 0);
    $tr = (float)($r['total_refund'] ?? 0);
    $totals['total_bet'] += $tb;
    $totals['total_win'] += $tw;
    $totals['total_refund'] += $tr;
    $totals['net_pl'] += ($tw - $tb + $tr);
    $totals['events'] += (int)($r['events'] ?? 0);
}

if (in_array($filters['export'], ['csv', 'excel', 'pdf'], true)) {
    if ($filters['export'] === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Profit/Loss Report</title></head><body>';
        echo '<h3>Profit/Loss Report</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0"><thead><tr><th>User</th><th>User ID</th><th>Total Bet</th><th>Total Win</th><th>Total Refund</th><th>Net P/L</th><th>Events</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $tb = (float)($r['total_bet'] ?? 0);
            $tw = (float)($r['total_win'] ?? 0);
            $tr = (float)($r['total_refund'] ?? 0);
            $net = $tw - $tb + $tr;
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string)($r['username'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string)$r['user_id']) . '</td>';
            echo '<td>' . htmlspecialchars(number_format($tb, 2)) . '</td>';
            echo '<td>' . htmlspecialchars(number_format($tw, 2)) . '</td>';
            echo '<td>' . htmlspecialchars(number_format($tr, 2)) . '</td>';
            echo '<td>' . htmlspecialchars(number_format($net, 2)) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['events'] ?? 0)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<script>window.print();</script>';
        echo '</body></html>';
        exit;
    }

    $filename = $filters['export'] === 'excel' ? 'profit_loss_report.xls' : 'profit_loss_report.csv';
    header('Content-Type: ' . ($filters['export'] === 'excel' ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['User', 'User ID', 'Total Bet', 'Total Win', 'Total Refund', 'Net P/L', 'Events']);
    foreach ($rows as $r) {
        $tb = (float)($r['total_bet'] ?? 0);
        $tw = (float)($r['total_win'] ?? 0);
        $tr = (float)($r['total_refund'] ?? 0);
        $net = $tw - $tb + $tr;
        fputcsv($out, [$r['username'] ?? '', $r['user_id'], number_format($tb, 2, '.', ''), number_format($tw, 2, '.', ''), number_format($tr, 2, '.', ''), number_format($net, 2, '.', ''), $r['events'] ?? 0]);
    }
    fclose($out);
    exit;
}
?>

<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Profit/Loss Report</h5>
      <div class="text-body-secondary">Net P/L: <?php echo number_format((float)$totals['net_pl'], 2); ?></div>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="mk-filterbar mb-4">
      <div>
        <label class="form-label">User ID</label>
        <input type="number" class="form-control" name="user_id" value="<?php echo htmlspecialchars((string)$filters['user_id']); ?>" placeholder="All">
      </div>
      <div>
        <label class="form-label">Game UID</label>
        <input type="text" class="form-control" name="game_uid" value="<?php echo htmlspecialchars($filters['game_uid']); ?>" placeholder="All">
      </div>
      <div>
        <label class="form-label">From</label>
        <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
      </div>
      <div>
        <label class="form-label">To</label>
        <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-outline-secondary" href="?">Reset</a>
      </div>
    </form>

    <table class="table table-hover custom-table">
      <thead>
        <tr>
          <th>User</th>
          <th>User ID</th>
          <th>Total Bet</th>
          <th>Total Win</th>
          <th>Total Refund</th>
          <th>Net P/L</th>
          <th>Events</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $tb = (float)($r['total_bet'] ?? 0);
              $tw = (float)($r['total_win'] ?? 0);
              $tr = (float)($r['total_refund'] ?? 0);
              $net = $tw - $tb + $tr;
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($r['username'] ?? '')); ?></td>
              <td><?php echo (int)$r['user_id']; ?></td>
              <td><?php echo number_format($tb, 2); ?></td>
              <td><?php echo number_format($tw, 2); ?></td>
              <td><?php echo number_format($tr, 2); ?></td>
              <td><?php echo number_format($net, 2); ?></td>
              <td><?php echo (int)($r['events'] ?? 0); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="text-center"><div class="mk-empty-state"><img src="https://moneyking365.com/assets/images/norecode.png" alt="No records"></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require '../../includes/footer.php'; ?>
