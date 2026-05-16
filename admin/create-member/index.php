<?php
$admin_base = '../';
$base_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
require '../includes/db.php';
require '../includes/auth.php';
require_admin_login($admin_base);

$page_title = 'Create';
require '../includes/header.php';

$role = $_SESSION['role'];
$allowed_create = false;
$target_role = '';
$parent_id = (int)($_SESSION['user_id'] ?? 0);

$requested_role = $_GET['create_role'] ?? '';
if ($role === 'admin' && in_array($requested_role, ['partner', 'super_master', 'master', 'agent', 'player'], true)) {
    $target_role = $requested_role;
}

if ($role === 'admin') {
    $allowed_create = true;
    if ($target_role === '') $target_role = 'partner';
} elseif (in_array($role, ['partner', 'super_master', 'master', 'agent'], true)) {
    $allowed_create = true;
    $target_role = admin_child_role($role);
    $parent_id = (int)($_SESSION['user_id'] ?? 0);
}

if (!$allowed_create) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>You do not have permission to create users.</div></div>";
    require '../includes/footer.php';
    exit;
}

$message = '';
$error = '';

$parents = [];
$parent_role = admin_parent_role($target_role);
$agents = [];
if ($role === 'admin') {
    if ($parent_role !== '') {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE role=? ORDER BY username ASC");
        $stmt->execute([$parent_role]);
        $parents = $stmt->fetchAll();
    }
}

$pref_parent_id = (int)($_GET['parent_id'] ?? ($_GET['master_id'] ?? ($_GET['agent_id'] ?? 0)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $credit_ref = floatval($_POST['credit_ref'] ?? 0);
    $rate = floatval($_POST['rate'] ?? 100);
    $post_target_role = $_POST['target_role'] ?? $target_role;
    if ($role === 'admin' && in_array($post_target_role, ['partner', 'super_master', 'master', 'agent', 'player'], true)) {
        $target_role = $post_target_role;
    }
    $parent_role = admin_parent_role($target_role);

    if ($role === 'admin' && $target_role !== 'partner') {
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        if ($parent_id <= 0) {
            $error = "Please select " . admin_role_label($parent_role) . ".";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role=?");
            $stmt->execute([$parent_id, $parent_role]);
            if (!$stmt->fetch()) $error = "Invalid parent selected.";
        }
    } elseif ($role === 'admin' && $target_role === 'partner') {
        $parent_id = (int)($_SESSION['user_id'] ?? 0);
    } else {
        $parent_id = (int)($_SESSION['user_id'] ?? 0);
    }

    if (!$error && (empty($username) || empty($password))) {
        $error = "All fields are required.";
    } elseif (!$error && $password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists. Please choose another one.";
        } else {
            // Create User
            $hashed_password = md5($password);
            // Default balance is 0. Credit Ref is the limit/reference.
            $stmt = $pdo->prepare("INSERT INTO users (username, password, password_text, role, parent_id, balance, credit_ref, rate, exposure, status) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 0, 'active')");
            if ($stmt->execute([$username, $hashed_password, $password, $target_role, $parent_id, $credit_ref, $rate])) {
                $message = "New " . admin_role_label($target_role) . " <strong>" . htmlspecialchars($username) . "</strong> created successfully!";
            } else {
                $error = "Error creating user. Please try again.";
            }
        }
    }
}
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h5 class="mb-1">Create New <?php echo htmlspecialchars(admin_role_label($target_role)); ?></h5>
          <div class="text-body-secondary">Add member to downline</div>
        </div>
        <a href="../dashboard/" class="btn btn-outline-secondary">Back</a>
      </div>
      <div class="card-body">
        <?php if ($message): ?>
          <div class="alert alert-success" role="alert"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="row g-4">
          <?php if ($role === 'admin'): ?>
            <div class="col-12">
              <div class="ta-form-field">
                <label for="target_role">Create Role</label>
                <select name="target_role" id="target_role" class="form-select">
                  <option value="partner" <?php echo $target_role === 'partner' ? 'selected' : ''; ?>>Partner</option>
                  <option value="super_master" <?php echo $target_role === 'super_master' ? 'selected' : ''; ?>>Super Master</option>
                  <option value="master" <?php echo $target_role === 'master' ? 'selected' : ''; ?>>Master</option>
                  <option value="agent" <?php echo $target_role === 'agent' ? 'selected' : ''; ?>>Agent</option>
                  <option value="player" <?php echo $target_role === 'player' ? 'selected' : ''; ?>>Player</option>
                </select>
              </div>
            </div>
            <div class="col-12 col-md-6" id="parentWrap" style="<?php echo $target_role !== 'partner' ? '' : 'display:none;'; ?>">
              <div class="ta-form-field">
                <label for="parent_id" id="parentLabel">Parent <?php echo htmlspecialchars(admin_role_label($parent_role)); ?></label>
                <select name="parent_id" id="parent_id" class="form-select">
                  <option value="">Select Parent</option>
                  <?php foreach ($parents as $m): ?>
                    <option value="<?php echo (int)$m['id']; ?>" <?php echo $pref_parent_id === (int)$m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['username']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          <?php endif; ?>
          <div class="col-12 col-md-6">
            <div class="ta-form-field">
              <label for="username">Username</label>
              <input type="text" name="username" id="username" class="form-control" placeholder="Username" required />
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="ta-form-field">
              <label for="rate">Partnership Rate (%)</label>
              <input type="number" name="rate" id="rate" class="form-control" value="100" step="0.1" min="0" max="100" placeholder="Rate" required />
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="ta-form-field">
              <label for="password">Password</label>
              <input type="password" name="password" id="password" class="form-control" placeholder="Password" required />
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="ta-form-field">
              <label for="confirm_password">Confirm Password</label>
              <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password" required />
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="ta-form-field">
              <label for="credit_ref">Credit Reference (Limit)</label>
              <input type="number" name="credit_ref" id="credit_ref" class="form-control" value="0" min="0" step="1" placeholder="Credit Reference" />
            </div>
          </div>

          <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Create <?php echo htmlspecialchars(admin_role_label($target_role)); ?></button>
            <a href="../dashboard/" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>

        <?php if ($role === 'admin'): ?>
          <script>
            (function () {
              var roleEl = document.getElementById('target_role');
              var parentWrap = document.getElementById('parentWrap');
              var parentSel = document.getElementById('parent_id');
              function sync() {
                var v = roleEl ? roleEl.value : 'partner';
                if (parentWrap) parentWrap.style.display = v === 'partner' ? 'none' : '';
                if (parentSel) parentSel.required = v !== 'partner';
              }
              if (roleEl) roleEl.addEventListener('change', function () {
                window.location.href = './?create_role=' + encodeURIComponent(roleEl.value);
              });
              sync();
            })();
          </script>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
