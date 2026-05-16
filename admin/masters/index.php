<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/hierarchy.php';

require_admin_login($admin_base);
require_admin_role(['admin', 'partner', 'super_master'], $admin_base);

$role = current_admin_role();
$my_id = current_admin_id();
$target_role = admin_child_role($role);
if ($role === 'admin') {
    $requested_role = $_GET['role'] ?? 'partner';
    if (in_array($requested_role, ['partner', 'super_master', 'master'], true)) {
        $target_role = $requested_role;
    }
} elseif ($role === 'partner') {
    $requested_role = $_GET['role'] ?? 'super_master';
    if (in_array($requested_role, ['super_master', 'master'], true)) {
        $target_role = $requested_role;
    }
} elseif ($role === 'super_master') {
    $target_role = 'master';
}
$target_label = admin_role_label($target_role);

$page_title = $target_label . 's';
require '../includes/header.php';

$q = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$dir = $_GET['dir'] ?? 'desc';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $master_id = (int)($_POST['master_id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $new_password = trim((string)($_POST['new_password'] ?? ''));
        $rate = (float)($_POST['rate'] ?? 0);
        $credit_ref = (float)($_POST['credit_ref'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($master_id <= 0) {
            $error = 'Invalid ' . $target_label . '.';
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
            $dup->execute([$username, $master_id]);
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
                $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ? AND role = ?";
                $params[] = $master_id;
                $params[] = $target_role;
                if ($role !== 'admin') {
                    [$downlineSql, $downlineParams] = member_downline_sql_for_role($role, $target_role, $my_id, 'users');
                    $sql .= " AND " . $downlineSql;
                    $params = array_merge($params, $downlineParams);
                }
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    $message = $target_label . ' updated.';
                } else {
                    $error = 'Update failed.';
                }
            }
        }
    }
}

$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
if ($role === 'admin') {
    $masters = fetch_role_children($pdo, $target_role, $parent_id, $q, $sort, $dir);
} else {
    if ($parent_id <= 0) {
        $parent_id = null;
    }
    $masters = fetch_role_downline($pdo, $role, $my_id, $target_role, $parent_id, $q, $sort, $dir);
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
        <h5 class="mb-1"><?php echo htmlspecialchars($target_label); ?>s</h5>
        <div class="text-body-secondary">Admin Shop &gt; Partner &gt; Super Master &gt; Master &gt; Agent &gt; Player</div>
      </div>
      <a class="btn btn-primary" href="<?php echo $admin_base; ?>create-member/?create_role=<?php echo urlencode($target_role); ?><?php echo $parent_id ? '&parent_id=' . urlencode((string)$parent_id) : ''; ?>">Create <?php echo htmlspecialchars($target_label); ?></a>
    </div>
    <form class="ta-list-filter ta-member-filter" method="GET">
      <input type="hidden" name="role" value="<?php echo htmlspecialchars($target_role); ?>">
      <?php if ($parent_id): ?><input type="hidden" name="parent_id" value="<?php echo (int)$parent_id; ?>"><?php endif; ?>
      <div class="ta-filter-field">
        <label class="form-label">Search</label>
        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search master">
      </div>
      <div class="ta-filter-actions">
        <button class="btn btn-outline-primary" type="submit">Search</button>
        <a class="btn btn-outline-secondary" href="./">Reset</a>
      </div>
    </form>
  </div>
  <div class="card-body">
    <div class="table-responsive ta-member-table-wrap">
    <table class="table table-hover custom-table ta-member-table ta-masters-table" id="mastersTable">
      <colgroup>
        <col class="ta-col-id">
        <col class="ta-col-user">
        <col class="ta-col-password">
        <col class="ta-col-rate">
        <col class="ta-col-money">
        <col class="ta-col-money">
        <col class="ta-col-count">
        <col class="ta-col-status">
        <col class="ta-col-actions">
      </colgroup>
      <thead>
        <tr>
          <th><a href="?<?php echo http_build_query(['q' => $q, 'sort' => 'id', 'dir' => ($sort === 'id' && $dir === 'asc') ? 'desc' : 'asc']); ?>">ID</a></th>
          <th><a href="?<?php echo http_build_query(['role' => $target_role, 'parent_id' => $parent_id, 'q' => $q, 'sort' => 'username', 'dir' => ($sort === 'username' && $dir === 'asc') ? 'desc' : 'asc']); ?>"><?php echo htmlspecialchars($target_label); ?></a></th>
          <th>Password</th>
          <th>Commission %</th>
          <th>Balance</th>
          <th>Credit Limit</th>
          <th>Downline</th>
          <th>Status</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($masters)): ?>
          <?php foreach ($masters as $m): ?>
            <tr>
              <td><?php echo (int)$m['id']; ?></td>
              <td>
                <a href="<?php echo $admin_base; ?>masters/?role=<?php echo urlencode(admin_child_role($target_role)); ?>&parent_id=<?php echo (int)$m['id']; ?>">
                  <?php echo htmlspecialchars($m['username']); ?>
                </a>
              </td>
              <td><span class="ta-password-chip"><?php echo htmlspecialchars(admin_display_password($m)); ?></span></td>
              <td><?php echo number_format((float)($m['rate'] ?? 0), 2); ?></td>
              <td><?php echo number_format((float)($m['balance'] ?? 0), 2); ?></td>
              <td><?php echo number_format((float)($m['credit_ref'] ?? 0), 2); ?></td>
              <td><?php echo (int)($m['children_count'] ?? 0); ?></td>
              <td><?php echo htmlspecialchars(strtoupper($m['status'] ?? 'active')); ?></td>
              <td class="text-right">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editMaster<?php echo (int)$m['id']; ?>">Edit</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $admin_base; ?>create-member/?create_role=<?php echo urlencode(admin_child_role($target_role)); ?>&parent_id=<?php echo (int)$m['id']; ?>">Create Downline</a>
              </td>
            </tr>
            <tr class="ta-edit-row">
              <td colspan="9" class="p-0">
                <div class="collapse" id="editMaster<?php echo (int)$m['id']; ?>">
                  <div class="p-3">
                    <form method="POST" class="row g-3 align-items-end">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                  <input type="hidden" name="master_id" value="<?php echo (int)$m['id']; ?>">
                  <div class="col-12 col-md-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars((string)($m['username'] ?? '')); ?>" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">New Password</label>
                    <input type="text" name="new_password" class="form-control" value="" placeholder="Leave blank to keep">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Commission %</label>
                    <input type="number" step="0.01" min="0" max="100" name="rate" class="form-control" value="<?php echo htmlspecialchars((string)($m['rate'] ?? 0)); ?>">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Credit Limit</label>
                    <input type="number" step="0.01" min="0" name="credit_ref" class="form-control" value="<?php echo htmlspecialchars((string)($m['credit_ref'] ?? 0)); ?>">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                      <?php $st = $m['status'] ?? 'active'; ?>
                      <option value="active" <?php echo $st === 'active' ? 'selected' : ''; ?>>Active</option>
                      <option value="locked" <?php echo $st === 'locked' ? 'selected' : ''; ?>>Locked</option>
                      <option value="suspended" <?php echo $st === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editMaster<?php echo (int)$m['id']; ?>">Cancel</button>
                  </div>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="9" class="text-center">No <?php echo htmlspecialchars(strtolower($target_label)); ?>s found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
