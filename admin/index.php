<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/session_logger.php';
require_once __DIR__ . '/includes/auth.php';
require 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    if (!in_array(($_SESSION['role'] ?? ''), admin_panel_roles(), true)) {
        header("Location: ../");
        exit;
    }
    $r = $_GET['r'] ?? '';
    if (is_string($r) && $r !== '' && substr($r, 0, 1) === '/' && preg_match('#^/admin/#', $r)) {
        header("Location: " . $r);
        exit;
    }
    header("Location: /admin/dashboard/");
    exit;
}

$error = '';
$panel_role = admin_panel_role_from_request();
$panel_role = $panel_role !== '' ? $panel_role : 'admin';
$panel_label = 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user) {
            if (md5($password) === $user['password']) {
                if (!in_array(($user['role'] ?? ''), admin_panel_roles(), true)) {
                    $error = "Player account cannot login from admin panel.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['coins'] = $user['balance'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
                    $_SESSION['login_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $_SESSION['login_device_type'] = detect_device_type($_SESSION['login_user_agent']);
                    $_SESSION['login_browser'] = detect_browser($_SESSION['login_user_agent']);
                    $_SESSION['login_os'] = detect_os($_SESSION['login_user_agent']);
                    admin_session_log_login($user['id'], $user['username'], $user['role']);
                    $r = $_GET['r'] ?? '';
                    if (is_string($r) && $r !== '' && substr($r, 0, 1) === '/' && preg_match('#^/admin/#', $r)) {
                        header("Location: " . $r);
                        exit;
                    }
                    header("Location: /admin/dashboard/");
                    exit;
                }
            } else {
                $error = "Incorrect Password. Please check your credentials.";
            }
        } else {
            $error = "Admin username not found.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

$admin_logo_url = '/assets/images/xbet_logo.png';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($panel_label); ?> Login | AlphaAdmin</title>
    <link rel="icon" type="image/x-icon" href="/tanitbet.jpg" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet" />
    <style>
      * { box-sizing: border-box; }
      html, body { min-height: 100%; }
      body {
        margin: 0;
        min-height: 100vh;
        font-family: "Outfit", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        color: #f9fafb;
        background: #070b14;
      }
      .ta-login-page {
        min-height: 100vh;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 28px;
        overflow: hidden;
        background:
          radial-gradient(circle at 50% 20%, rgba(70, 95, 255, .18), transparent 22rem),
          linear-gradient(180deg, #0b101b 0%, #070b14 54%, #050812 100%);
      }
      .ta-login-page::before {
        content: "";
        position: absolute;
        left: -8vw;
        right: -8vw;
        bottom: -23vh;
        height: 54vh;
        border-radius: 50% 50% 0 0 / 34% 34% 0 0;
        background: linear-gradient(180deg, rgba(17, 24, 39, .96), rgba(12, 18, 31, .98));
        box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
        transform: rotate(-2deg);
      }
      .ta-login-page::after {
        content: "";
        position: absolute;
        left: 15vw;
        bottom: 17vh;
        width: 94px;
        height: 94px;
        border-radius: 50%;
        background:
          radial-gradient(circle at 30% 22%, rgba(255,255,255,.24), transparent 22%),
          radial-gradient(circle at 64% 72%, rgba(0,0,0,.72), transparent 52%),
          linear-gradient(135deg, #737985, #10141b 72%);
        box-shadow: 32px 36px 34px rgba(0,0,0,.34);
      }
      .ta-login-card {
        position: relative;
        z-index: 1;
        width: min(100%, 440px);
        min-height: 520px;
        padding: 44px 44px 36px;
        border: 1px solid rgba(0, 210, 255, .25);
        border-radius: 12px;
        background: rgba(17, 24, 39, .96);
        box-shadow: 0 26px 80px rgba(0,0,0,.38);
        backdrop-filter: blur(18px);
      }
      .ta-login-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        margin-bottom: 28px;
        color: #00d2ff;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: .02em;
      }
      .ta-login-logo {
        width: 170px;
        height: auto;
        display: block;
      }
      .ta-login-title {
        margin: 0 0 28px;
        color: #f9fafb;
        font-size: 30px;
        line-height: 1.35;
        font-weight: 500;
        letter-spacing: 0;
      }
      .ta-field {
        margin-bottom: 26px;
      }
      .ta-field-floating {
        position: relative;
        margin-bottom: 26px;
      }
      .ta-floating-label {
        position: absolute;
        left: 14px;
        top: -10px;
        z-index: 2;
        padding: 0 7px;
        background: #111827;
        color: #00d2ff;
        font-size: 14px;
        line-height: 20px;
      }
      .ta-input-wrap {
        position: relative;
      }
      .ta-input,
      .ta-input-password {
        width: 100%;
        height: 60px;
        border: 1px solid #344054;
        border-radius: 8px;
        padding: 0 18px;
        color: #f9fafb;
        background: #0f172a;
        font: inherit;
        font-size: 17px;
        outline: none;
        transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
      }
      .ta-input::placeholder,
      .ta-input-password::placeholder {
        color: #98a2b3;
      }
      .ta-field-floating .ta-input {
        border-color: #00d2ff;
        box-shadow: 0 0 0 1px rgba(0, 210, 255, .1);
      }
      .ta-input:focus,
      .ta-input-password:focus {
        border-color: #00d2ff;
        box-shadow: 0 0 0 4px rgba(0, 210, 255, .15);
      }
      .ta-password-toggle {
        position: absolute;
        top: 50%;
        right: 14px;
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 0;
        background: transparent;
        color: #d0d5dd;
        cursor: pointer;
        transform: translateY(-50%);
      }
      .ta-input-password {
        padding-right: 54px;
      }
      .ta-login-alert {
        margin: -4px 0 24px;
        color: #ff4d4f;
        font-size: 17px;
        line-height: 1.4;
      }
      .ta-login-btn {
        width: 100%;
        height: 50px;
        margin-top: 2px;
        border: 0;
        border-radius: 8px;
        background: linear-gradient(135deg, #00d2ff, #0099ff);
        color: #ffffff;
        font: inherit;
        font-size: 17px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 14px 28px rgba(0, 210, 255, .2);
      }
      .ta-login-note {
        margin-top: 28px;
        color: #98a2b3;
        font-size: 13px;
        text-align: center;
      }
      @media (max-width: 640px) {
        .ta-login-page {
          align-items: flex-start;
          padding: 78px 12px 24px;
        }
        .ta-login-page::before {
          bottom: -18vh;
          height: 46vh;
        }
        .ta-login-page::after {
          display: none;
        }
        .ta-login-card {
          min-height: auto;
          padding: 28px 20px 24px;
          border-radius: 10px;
        }
        .ta-login-brand {
          margin-bottom: 22px;
        }
        .ta-login-logo {
          width: 156px;
        }
        .ta-login-title {
          font-size: 27px;
        }
      }
    </style>
  </head>
  <body>
    <div class="ta-login-page">
      <main class="ta-login-card">
        <div class="ta-login-brand">
          <img class="ta-login-logo" src="<?php echo htmlspecialchars($admin_logo_url); ?>" alt="TanitAdmin">
        </div>
        <h1 class="ta-login-title"><?php echo htmlspecialchars($panel_label); ?> Login<br>Alpha 216 Games</h1>

          <?php if ($error): ?>
            <div class="ta-login-alert"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php'); ?>" method="POST">
            <div class="ta-field-floating">
              <label class="ta-floating-label" for="username">Email or Username</label>
              <div class="ta-input-wrap">
                <input class="ta-input" type="text" id="username" name="username" placeholder="Enter your email or username" required autofocus />
              </div>
            </div>

            <div class="ta-field">
              <div class="ta-input-wrap">
                <input class="ta-input-password" type="password" id="password" name="password" placeholder="Password" required />
                <button class="ta-password-toggle" type="button" aria-label="Show password" data-password-toggle>
                  <svg class="ta-eye-off" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M3.25 12s3.25-6.25 8.75-6.25c5.5 0 8.75 6.25 8.75 6.25a16.7 16.7 0 0 1-2.88 3.5M14.12 14.12A3 3 0 0 1 9.88 9.88M6.75 6.75l10.5 10.5M9.43 4.98A9.4 9.4 0 0 1 12 4.75c5.5 0 8.75 6.25 8.75 6.25M4.75 4.75l14.5 14.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            </div>

            <button class="ta-login-btn" type="submit">Log In</button>
          </form>
          <div class="ta-login-note">Alpha 216 secure <?php echo htmlspecialchars(strtolower($panel_label)); ?> access</div>
      </main>
    </div>
    <script>
      document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var input = document.getElementById('password');
          if (!input) return;
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
      });
    </script>
  </body>
</html>
