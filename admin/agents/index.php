<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/hierarchy.php';

require_admin_login($admin_base);
require_admin_role(['admin', 'partner', 'super_master', 'master'], $admin_base);

$page_title = 'Agents';
require '../includes/header.php';

$role = current_admin_role();
$my_id = current_admin_id();

$q = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$dir = $_GET['dir'] ?? 'desc';
$master_id = isset($_GET['master_id']) ? (int)$_GET['master_id'] : null;

$message = '';
$error = '';

if ($role === 'master') {
    $master_id = $my_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $agent_id = (int)($_POST['agent_id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $new_password = trim((string)($_POST['new_password'] ?? ''));
        $rate = (float)($_POST['rate'] ?? 0);
        $credit_ref = (float)($_POST['credit_ref'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($agent_id <= 0) {
            $error = 'Invalid Agent.';
        } elseif ($username === '') {
            $error = 'Username is required.';
        } elseif ($rate < 0 || $rate > 100) {
            $error = 'Commission must be between 0 and 100.';
        } elseif ($credit_ref < 0) {
            $error = 'Credit limit must be 0 or more.';
        } elseif (!in_array($status, ['active', 'locked', 'suspended'], true)) {
            $error = 'Invalid status.';
        } else {
            $dup = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            $dup->execute([$username, $agent_id]);
            if ($dup->fetchColumn()) {
                $error = 'Username already exists.';
            } else {
                $sets = ['username = ?', 'rate = ?', 'credit_ref = ?', 'status = ?'];
                $params = [$username, $rate, $credit_ref, $status];
                if ($new_password !== '') {
                    $sets[] = 'password = ?';
                    $sets[] = 'password_text = ?';
                    $params[] = md5($new_password);
                    $params[] = $new_password;
                }
                $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ? AND role = 'agent'";
                $params[] = $agent_id;
                if ($role !== 'admin') {
                    [$downlineSql, $downlineParams] = agent_downline_sql_for_role($role, $my_id, 'users');
                    $sql .= " AND " . $downlineSql;
                    $params = array_merge($params, $downlineParams);
                }
                $stmt = $pdo->prepare($sql);
                $ok = $stmt->execute($params);
                if ($ok) $message = 'Agent updated.'; else $error = 'Update failed.';
            }
        }
    }
}

if ($role === 'admin') {
    $agents = fetch_agents_for_admin($pdo, $q, $master_id, $sort, $dir);
} else {
    $agents = fetch_agents_for_downline($pdo, $role, $my_id, $q, $sort, $dir);
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

<div class="card ta-member-card">
  <div class="card-header ta-list-head ta-member-head">
    <div class="ta-member-head-main">
      <div>
        <h5 class="mb-1">Agents</h5>
        <div class="text-body-secondary"><?php echo $role === 'admin' ? 'All agents' : 'Agents under your master'; ?></div>
      </div>
      <?php
        $create_agent_url = $admin_base . 'create-member/?create_role=agent';
        if ($role === 'admin' && $master_id) $create_agent_url .= '&master_id=' . urlencode((string)$master_id);
      ?>
      <a class="btn btn-primary" href="<?php echo htmlspecialchars($create_agent_url); ?>">Create Agent</a>
    </div>
      <form class="ta-list-filter ta-agent-filter ta-member-filter" method="GET">
        <?php if ($role === 'admin'): ?>
          <div class="ta-filter-field">
            <label class="form-label">Master</label>
            <input type="number" class="form-control" name="master_id" value="<?php echo htmlspecialchars((string)($master_id ?? '')); ?>" placeholder="Master ID">
          </div>
        <?php endif; ?>
        <div class="ta-filter-field">
          <label class="form-label">Search</label>
          <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search agent">
        </div>
        <div class="ta-filter-actions">
          <button class="btn btn-outline-primary" type="submit">Search</button>
          <a class="btn btn-outline-secondary" href="./">Reset</a>
        </div>
      </form>
  </div>
  <div class="card-body">
    <div class="table-responsive ta-member-table-wrap">
    <table class="table table-hover custom-table ta-member-table ta-agents-table">
      <colgroup>
        <col class="ta-col-id">
        <col class="ta-col-user">
        <col class="ta-col-password">
        <?php if ($role === 'admin'): ?><col class="ta-col-parent"><?php endif; ?>
        <col class="ta-col-rate">
        <col class="ta-col-money">
        <col class="ta-col-money">
        <col class="ta-col-count">
        <col class="ta-col-status">
        <col class="ta-col-actions">
      </colgroup>
      <thead>
        <tr>
          <th>ID</th>
          <th>Agent</th>
          <th>Password</th>
          <?php if ($role === 'admin'): ?><th>Master</th><?php endif; ?>
          <th>Commission %</th>
          <th>Balance</th>
          <th>Credit Limit</th>
          <th>Players</th>
          <th>Status</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($agents)): ?>
          <?php foreach ($agents as $a): ?>
            <tr>
              <td><?php echo (int)$a['id']; ?></td>
              <td>
                <a href="<?php echo $admin_base; ?>players/?agent_id=<?php echo (int)$a['id']; ?>">
                  <?php echo htmlspecialchars($a['username']); ?>
                </a>
              </td>
              <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($a)); ?></span></td>
              <?php if ($role === 'admin'): ?><td><?php echo htmlspecialchars($a['master_name'] ?? '-'); ?></td><?php endif; ?>
              <td><?php echo number_format((float)($a['rate'] ?? 0), 2); ?></td>
              <td><?php echo number_format((float)($a['balance'] ?? 0), 2); ?></td>
              <td><?php echo number_format((float)($a['credit_ref'] ?? 0), 2); ?></td>
              <td><?php echo (int)($a['players_count'] ?? 0); ?></td>
              <td><?php echo htmlspecialchars(strtoupper($a['status'] ?? 'active')); ?></td>
              <td class="text-right">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editAgent<?php echo (int)$a['id']; ?>">Edit</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $admin_base; ?>players/?agent_id=<?php echo (int)$a['id']; ?>">View Players</a>
              </td>
            </tr>
            <tr class="ta-edit-row">
              <td colspan="<?php echo $role === 'admin' ? '10' : '9'; ?>" class="p-0">
                <div class="collapse" id="editAgent<?php echo (int)$a['id']; ?>">
                  <div class="p-3">
                    <form method="POST" class="row g-3 align-items-end">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                  <input type="hidden" name="agent_id" value="<?php echo (int)$a['id']; ?>">
                  <div class="col-12 col-md-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars((string)($a['username'] ?? '')); ?>" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">New Password</label>
                    <input type="text" name="new_password" class="form-control" value="" placeholder="Leave blank to keep">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Commission %</label>
                    <input type="number" step="0.01" min="0" max="100" name="rate" class="form-control" value="<?php echo htmlspecialchars((string)($a['rate'] ?? 0)); ?>">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Credit Limit</label>
                    <input type="number" step="0.01" min="0" name="credit_ref" class="form-control" value="<?php echo htmlspecialchars((string)($a['credit_ref'] ?? 0)); ?>">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                      <?php $st = $a['status'] ?? 'active'; ?>
                      <option value="active" <?php echo $st === 'active' ? 'selected' : ''; ?>>Active</option>
                      <option value="locked" <?php echo $st === 'locked' ? 'selected' : ''; ?>>Locked</option>
                      <option value="suspended" <?php echo $st === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editAgent<?php echo (int)$a['id']; ?>">Cancel</button>
                  </div>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="<?php echo $role === 'admin' ? '10' : '9'; ?>" class="text-center">No agents found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
