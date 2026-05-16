<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/hierarchy.php';

require_admin_login($admin_base);
require_admin_role(['admin', 'partner', 'super_master', 'master', 'agent'], $admin_base);

$page_title = 'Players';
require '../includes/header.php';

$role = current_admin_role();
$my_id = current_admin_id();

$q = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$dir = $_GET['dir'] ?? 'desc';
$master_id = isset($_GET['master_id']) ? (int)$_GET['master_id'] : null;
$agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;
$status = $_GET['status'] ?? '';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $player_id = (int)($_POST['player_id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $new_password = trim((string)($_POST['new_password'] ?? ''));
        $credit_ref = (float)($_POST['credit_ref'] ?? 0);
        $new_status = $_POST['status'] ?? '';

        if ($player_id <= 0) {
            $error = 'Invalid Player.';
        } elseif ($username === '') {
            $error = 'Username is required.';
        } elseif ($credit_ref < 0) {
            $error = 'Credit limit must be 0 or more.';
        } elseif (!in_array($new_status, ['active', 'locked', 'suspended'], true)) {
            $error = 'Invalid status.';
        } else {
            $dup = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            $dup->execute([$username, $player_id]);
            if ($dup->fetchColumn()) {
                $error = 'Username already exists.';
            } else {
                $sets = ['username = ?', 'credit_ref = ?', 'status = ?'];
                $params = [$username, $credit_ref, $new_status];
                if ($new_password !== '') {
                    $sets[] = 'password = ?';
                    $sets[] = 'password_text = ?';
                    $params[] = md5($new_password);
                    $params[] = $new_password;
                }
                $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ? AND role = 'player'";
                $params[] = $player_id;
                if ($role !== 'admin') {
                    [$downlineSql, $downlineParams] = player_downline_sql_for_role($role, $my_id, 'users');
                    $sql .= " AND " . $downlineSql;
                    $params = array_merge($params, $downlineParams);
                }
                $stmt = $pdo->prepare($sql);
                $ok = $stmt->execute($params);
                if ($ok) $message = 'Player updated.'; else $error = 'Update failed.';
            }
        }
    }
}

if ($role === 'agent') {
    $players = fetch_players_for_agent($pdo, $my_id, $q, $status, $sort, $dir);
} elseif ($role === 'master') {
    $players = fetch_players_for_master($pdo, $my_id, $q, $agent_id, $status, $sort, $dir);
} elseif ($role === 'partner' || $role === 'super_master') {
    $players = fetch_players_for_downline($pdo, $role, $my_id, $q, $agent_id, $status, $sort, $dir);
} else {
    $players = fetch_players_for_admin($pdo, $q, $master_id, $agent_id, $status, $sort, $dir);
}
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

<div class="card ta-member-card ta-players-card">
  <div class="card-header ta-list-head ta-member-head ta-players-head">
    <div class="ta-member-head-main ta-players-head-main">
      <div>
        <h5 class="mb-1">Players</h5>
        <div class="text-body-secondary">Players appear here only for management and reporting</div>
      </div>
      <?php if ($role !== 'master'): ?>
        <?php
          $create_player_url = $admin_base . 'create-member/';
          if ($role === 'admin') {
              $create_player_url .= '?create_role=player';
              if ($agent_id) $create_player_url .= '&agent_id=' . urlencode((string)$agent_id);
          }
        ?>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars($create_player_url); ?>">Create Player</a>
      <?php endif; ?>
    </div>
    <form class="mk-filterbar ta-player-filter ta-member-filter ta-players-filter" method="GET">
        <?php if ($role === 'admin'): ?>
          <div class="ta-filter-field">
            <label class="form-label">Master</label>
            <input type="number" class="form-control" name="master_id" value="<?php echo htmlspecialchars((string)($master_id ?? '')); ?>" placeholder="ID">
          </div>
        <?php endif; ?>
        <?php if ($role !== 'agent'): ?>
          <div class="ta-filter-field">
            <label class="form-label">Agent</label>
            <input type="number" class="form-control" name="agent_id" value="<?php echo htmlspecialchars((string)($agent_id ?? '')); ?>" placeholder="ID">
          </div>
        <?php endif; ?>
        <div class="ta-filter-field">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="">All</option>
            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="locked" <?php echo $status === 'locked' ? 'selected' : ''; ?>>Locked</option>
            <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
          </select>
        </div>
        <div class="ta-filter-field">
          <label class="form-label">Search</label>
          <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Username / ID">
        </div>
        <div class="ta-filter-actions">
          <button class="btn btn-outline-primary" type="submit">Apply</button>
          <a class="btn btn-outline-secondary" href="./">Reset</a>
        </div>
    </form>
  </div>
  <div class="card-body">
    <div class="table-responsive ta-member-table-wrap ta-players-table-wrap">
    <table class="table table-hover custom-table ta-member-table ta-players-table">
      <colgroup>
        <col class="ta-col-id">
        <col class="ta-col-user">
        <col class="ta-col-password">
        <?php if ($role !== 'agent'): ?><col class="ta-col-parent"><?php endif; ?>
        <?php if ($role === 'admin'): ?><col class="ta-col-parent"><?php endif; ?>
        <col class="ta-col-money">
        <col class="ta-col-money">
        <col class="ta-col-money">
        <col class="ta-col-money">
        <col class="ta-col-status">
        <col class="ta-col-actions">
      </colgroup>
      <thead>
        <tr>
          <th>Player ID</th>
          <th>Player</th>
          <th>Password</th>
          <?php if ($role !== 'agent'): ?><th>Parent Agent</th><?php endif; ?>
          <?php if ($role === 'admin'): ?><th>Parent Master</th><?php endif; ?>
          <th>Balance</th>
          <th>Exposure</th>
          <th>Profit/Loss</th>
          <th>Credit Limit</th>
          <th>Status</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($players)): ?>
          <?php foreach ($players as $p): ?>
            <tr>
              <td><?php echo (int)$p['id']; ?></td>
              <td><?php echo htmlspecialchars($p['username']); ?></td>
              <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($p)); ?></span></td>
              <?php if ($role !== 'agent'): ?><td><?php echo htmlspecialchars($p['agent_name'] ?? '-'); ?></td><?php endif; ?>
              <?php if ($role === 'admin'): ?><td><?php echo htmlspecialchars($p['master_name'] ?? '-'); ?></td><?php endif; ?>
              <td><?php echo number_format((float)($p['balance'] ?? 0), 2); ?></td>
              <td><?php echo number_format((float)($p['exposure'] ?? 0), 2); ?></td>
              <td><?php echo number_format(0, 2); ?></td>
              <td><?php echo number_format((float)($p['credit_ref'] ?? 0), 2); ?></td>
              <td><?php echo htmlspecialchars(strtoupper($p['status'] ?? 'active')); ?></td>
              <td class="text-right">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editPlayer<?php echo (int)$p['id']; ?>">Edit</button>
              </td>
            </tr>
            <tr class="ta-edit-row">
              <td colspan="<?php echo $role === 'admin' ? '11' : ($role === 'agent' ? '9' : '10'); ?>" class="p-0">
                <div class="collapse" id="editPlayer<?php echo (int)$p['id']; ?>">
                  <div class="p-3">
                    <form method="POST" class="row g-3 align-items-end">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                  <input type="hidden" name="player_id" value="<?php echo (int)$p['id']; ?>">
                  <div class="col-12 col-md-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars((string)($p['username'] ?? '')); ?>" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">New Password</label>
                    <input type="text" name="new_password" class="form-control" value="" placeholder="Leave blank to keep">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Credit Limit</label>
                    <input type="number" step="0.01" min="0" name="credit_ref" class="form-control" value="<?php echo htmlspecialchars((string)($p['credit_ref'] ?? 0)); ?>">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                      <?php $st = $p['status'] ?? 'active'; ?>
                      <option value="active" <?php echo $st === 'active' ? 'selected' : ''; ?>>Active</option>
                      <option value="locked" <?php echo $st === 'locked' ? 'selected' : ''; ?>>Locked</option>
                      <option value="suspended" <?php echo $st === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editPlayer<?php echo (int)$p['id']; ?>">Cancel</button>
                  </div>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="<?php echo $role === 'admin' ? '11' : ($role === 'agent' ? '9' : '10'); ?>" class="text-center">No players found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
