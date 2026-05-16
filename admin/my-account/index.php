<?php
$admin_base = '../';
$base_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';
require '../includes/session_logger.php';
require '../includes/session_admin.php';
require_admin_login($admin_base);

$page_title = 'Settings';
require '../includes/header.php';

$profile_message = '';
$profile_error = '';

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_username = (string)($_SESSION['username'] ?? '');
$current_role = (string)($_SESSION['role'] ?? '');

$stmt = $pdo->prepare("SELECT balance, status FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$me = $stmt->fetch();
$current_balance = $me ? (float)($me['balance'] ?? 0) : 0.0;
$current_status = $me ? (string)($me['status'] ?? 'active') : 'active';

function admin_client_ip() {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    foreach ($candidates as $c) {
        $c = trim((string)$c);
        if ($c === '') continue;
        if (strpos($c, ',') !== false) $c = trim(explode(',', $c)[0]);
        if (filter_var($c, FILTER_VALIDATE_IP)) return $c;
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $profile_error = 'Invalid request.';
    } else {
        $current_password = (string)($_POST['current_password'] ?? '');
        $new_password = (string)($_POST['new_password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');

        if ($new_password === '' || $confirm_password === '' || $current_password === '') {
            $profile_error = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $profile_error = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$current_user_id]);
            $hash = (string)$stmt->fetchColumn();
            if (md5($current_password) !== $hash) {
                $profile_error = 'Current password is incorrect.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = ?, password_text = ? WHERE id = ?");
                if ($stmt->execute([md5($new_password), $new_password, $current_user_id])) {
                    audit_log($pdo, 'change_password', 'user', (string)$current_user_id, null, ['user_id' => $current_user_id]);
                    $profile_message = 'Password updated successfully.';
                } else {
                    $profile_error = 'Password update failed.';
                }
            }
        }
    }
}

$login_ip = (string)($_SESSION['login_ip'] ?? '');
if ($login_ip === '') {
    $login_ip = admin_client_ip();
    if ($login_ip !== '') $_SESSION['login_ip'] = $login_ip;
}
$login_ua = $_SESSION['login_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
$login_device = $_SESSION['login_device_type'] ?? '';
$login_browser = $_SESSION['login_browser'] ?? '';
$login_os = $_SESSION['login_os'] ?? '';

if ($login_ua !== '' && ($login_device === '' || $login_browser === '' || $login_os === '')) {
    if ($login_device === '') {
        $login_device = detect_device_type($login_ua);
        $_SESSION['login_device_type'] = $login_device;
    }
    if ($login_browser === '') {
        $login_browser = detect_browser($login_ua);
        $_SESSION['login_browser'] = $login_browser;
    }
    if ($login_os === '') {
        $login_os = detect_os($login_ua);
        $_SESSION['login_os'] = $login_os;
    }
}
$cookie_expires = time() + (60 * 60 * 24 * 365 * 10);

$session_message = '';
$session_error = '';
$is_admin = $current_role === 'admin';
$view_user_id = $current_user_id;

$user_sessions = admin_session_list_by_user($view_user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expire_session'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $session_error = 'Invalid request.';
    } else {
        $file = $_POST['session_file'] ?? '';
        $current_file = 'sess_' . session_id();
        if ($view_user_id === $current_user_id && basename((string)$file) === $current_file) {
            $session_error = 'You cannot expire the current session from here. Use Logout.';
        } else {
            if (admin_session_delete_by_filename($file)) {
                audit_log($pdo, 'expire_session', 'session', basename((string)$file), null, ['user_id' => $view_user_id]);
                $session_message = 'Session expired.';
                $user_sessions = admin_session_list_by_user($view_user_id);
            } else {
                $session_error = 'Unable to expire session.';
            }
        }
    }
}
?>

<?php if ($profile_message): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?php echo htmlspecialchars($profile_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>
<?php if ($profile_error): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?php echo htmlspecialchars($profile_error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>
<?php if ($session_message): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?php echo htmlspecialchars($session_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>
<?php if ($session_error): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?php echo htmlspecialchars($session_error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="card shadow-none border-0">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3 border-0">
    <div>
      <h5 class="mb-1">My Account</h5>
      <div class="text-body-secondary">Profile, password and sessions</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</button>
      <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>

  <div class="card-body pt-0">
    <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
      <div class="avatar avatar-xl">
        <span class="avatar-initial rounded-circle bg-label-primary">
          <?php echo strtoupper(substr($current_username, 0, 1)); ?>
        </span>
      </div>
      <div class="flex-grow-1">
        <h4 class="mb-1"><?php echo htmlspecialchars($current_username); ?></h4>
        <div class="d-flex gap-2 flex-wrap">
          <span class="badge bg-label-warning text-dark"><?php echo htmlspecialchars(strtoupper($current_role)); ?></span>
          <span class="badge <?php echo $current_status === 'active' ? 'bg-label-success' : 'bg-label-danger'; ?>">
            <?php echo htmlspecialchars(strtoupper($current_status)); ?>
          </span>
        </div>
      </div>
    </div>

    <div class="table-responsive mb-4">
      <table class="table table-sm mb-0">
        <tbody>
          <tr>
            <th style="width: 220px;">User ID</th>
            <td class="fw-semibold"><?php echo (int)$current_user_id; ?></td>
          </tr>
          <tr>
            <th>Wallet Balance</th>
            <td class="fw-semibold"><?php echo $current_role === 'admin' ? '&infin;' : number_format($current_balance, 2); ?></td>
          </tr>
          <tr>
            <th>Cookie Expires</th>
            <td class="fw-semibold"><?php echo htmlspecialchars(date('Y-m-d H:i:s', $cookie_expires)); ?></td>
          </tr>
        </tbody>
      </table>
    </div>

        <table class="table table-hover custom-table">
          <thead>
            <tr>
              <th>Last Active</th>
              <th>IP</th>
              <th>Device</th>
              <th>Browser</th>
              <th>OS</th>
              <th>Status</th>
              <th class="text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($user_sessions)): ?>
              <?php foreach ($user_sessions as $s): ?>
                <?php
                  $is_current = ($view_user_id === $current_user_id) && (('sess_' . session_id()) === ($s['file'] ?? ''));
                  $meta = $s['meta'] ?? [];
                  $ip = $meta['login_ip'] ?? '';
                  $device = $meta['login_device_type'] ?? '';
                  $browser = $meta['login_browser'] ?? '';
                  $os = $meta['login_os'] ?? '';
                  $ua = $meta['login_user_agent'] ?? '';
                  if ($ua !== '' && ($device === '' || $browser === '' || $os === '')) {
                    if ($device === '') $device = detect_device_type($ua);
                    if ($browser === '') $browser = detect_browser($ua);
                    if ($os === '') $os = detect_os($ua);
                  }
                  $last = !empty($s['mtime']) ? date('Y-m-d H:i:s', (int)$s['mtime']) : '-';
                ?>
                <tr>
                  <td><?php echo htmlspecialchars($last); ?></td>
                  <td><?php echo htmlspecialchars($ip ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($device ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($browser ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($os ?: '-'); ?></td>
                  <td><?php echo $is_current ? '<span class="badge bg-label-success">Current</span>' : '<span class="badge bg-label-secondary">Other</span>'; ?></td>
                  <td class="text-right">
                    <?php if ($is_current): ?>
                      <a href="../logout.php" class="btn btn-sm btn-danger">Logout</a>
                    <?php else: ?>
                      <form method="POST" class="d-inline-flex">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="expire_session" value="1">
                        <input type="hidden" name="session_file" value="<?php echo htmlspecialchars($s['file'] ?? ''); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Expire</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center">No sessions found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
  </div>
</div>

<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="change_password" value="1">
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <div class="mb-0">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
