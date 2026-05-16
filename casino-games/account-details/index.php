<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$username = (string)($_SESSION['username'] ?? '');
$role = (string)($_SESSION['role'] ?? '');

$csrf = (string)($_SESSION['account_details_csrf'] ?? '');
if ($csrf === '') {
    $csrf = bin2hex(random_bytes(16));
    $_SESSION['account_details_csrf'] = $csrf;
}

$msg = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'change_password') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $err = 'Invalid request.';
    } else {
        $old = (string)($_POST['old_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($old === '' || $new === '' || $confirm === '') {
            $err = 'All password fields are required.';
        } elseif ($new !== $confirm) {
            $err = 'Passwords do not match.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT password FROM users WHERE id=?');
                $stmt->execute([$user_id]);
                $hash = (string)($stmt->fetchColumn() ?? '');
                $ok = false;
                if ($hash !== '' && md5($old) === $hash) {
                    $ok = true;
                } elseif ($hash !== '' && function_exists('password_verify') && password_verify($old, $hash)) {
                    $ok = true;
                }
                if (!$ok) {
                    $err = 'Old password is incorrect.';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET password=?, password_text=? WHERE id=?');
                    if ($stmt->execute([md5($new), $new, $user_id])) {
                        $msg = 'Password changed successfully.';
                    } else {
                        $err = 'Password update failed.';
                    }
                }
            } catch (Exception $e) {
                $err = 'Password update failed.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/profile-header.php';
?>

<script>
  document.body.classList.add('mk-account-mode');
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('.mk-side-menu a') : null;
    if (!a) return;
    a.classList.remove('mk-shine');
    void a.offsetWidth;
    a.classList.add('mk-shine');
  });
</script>

<div class="mk-account-page">
  <div class="mk-account-layout">
    <aside class="mk-account-sidebar">
      <div class="mk-side-title" data-translate="profile">PROFILE</div>
      <ul class="mk-side-menu">
        <li class="active"><a href="./"><i class="fa fa-user"></i> <span data-translate="account_detail">ACCOUNT DETAILS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>account-statement/"><i class="fa fa-file-text-o"></i> <span data-translate="account_statement">ACCOUNT STATEMENT</span></a></li>
        <li><a href="#"><i class="fa fa-line-chart"></i> <span data-translate="profit_loss">PROFIT AND LOSS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>bet-history/"><i class="fa fa-history"></i> <span data-translate="bet_history">BET HISTORY</span></a></li>
        <li><a href="#"><i class="fa fa-list"></i> <span data-translate="activity_log">ACTIVITY LOG</span></a></li>
        <li><a href="#"><i class="fa fa-bell-o"></i> <span data-translate="notification_history">NOTIFICATION HISTORY</span></a></li>
        <li><a href="#"><i class="fa fa-gift"></i> <span data-translate="bonus_history">BONUS HISTORY</span></a></li>
      </ul>
    </aside>

    <main class="mk-account-content">
      <div class="mk-account-inner">
        <?php if ($msg !== ''): ?>
          <div class="mk-account-alert ok"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if ($err !== ''): ?>
          <div class="mk-account-alert err"><?php echo htmlspecialchars($err); ?></div>
        <?php endif; ?>

        <div class="mk-account-grid">
          <section class="mk-card">
            <div class="mk-card-head" data-translate="profile">Profile</div>
            <div class="mk-card-body">
              <div class="form-group">
                <label data-translate="username">Username</label>
                <input class="form-control" type="text" value="<?php echo htmlspecialchars($username); ?>" disabled>
              </div>
              <div class="form-group">
                <label data-translate="email">Email</label>
                <input class="form-control" type="email" value="" disabled>
              </div>
            </div>
          </section>

          <section class="mk-card">
            <div class="mk-card-head" data-translate="change_password">Change Password</div>
            <div class="mk-card-body">
              <form method="POST" autocomplete="off" novalidate>
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <div class="form-group mk-pass-row">
                  <label data-translate="new_password">New Password</label>
                  <input class="form-control" type="password" name="new_password" placeholder="Enter New Password" data-translate="enter_new_password" required>
                </div>
                <div class="form-group mk-pass-row">
                  <label data-translate="confirm_password">Confirm Password</label>
                  <input class="form-control" type="password" name="confirm_password" placeholder="Confirm Password" data-translate="confirm_password" required>
                </div>
                <div class="form-group mk-pass-row">
                  <label data-translate="old_password">Old Password</label>
                  <input class="form-control" type="password" name="old_password" placeholder="Old Password" data-translate="old_password" required>
                </div>
                <div class="mk-card-actions">
                  <button class="btn btn-warning" type="submit" data-translate="change_password">CHANGE PASSWORD</button>
                </div>
              </form>
            </div>
          </section>
        </div>
      </div>
    </main>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
