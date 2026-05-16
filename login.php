<?php
session_start();
require 'includes/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for Demo Login
    if (isset($_POST['login_type']) && $_POST['login_type'] === 'demo') {
        // Clear previous session to ensure clean login
        session_unset();
        
        $_SESSION['user_id'] = 999999; // Demo ID
        $_SESSION['username'] = 'DEMO3';
        $_SESSION['role'] = 'player';
        $_SESSION['coins'] = 0.00; // Demo Balance as requested: 0 balance
        header("Location: index.php");
        exit;
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'player'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            // Using MD5 for password verification
            if (md5($password) === $user['password']) {
                session_unset(); // Clear previous session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['coins'] = $user['balance'];
                header("Location: index.php");
                exit;
            } else {
                $error = "Incorrect Password. Please try again.";
            }
        } else {
            $error = "Username not found. Please register or contact support.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// Redirect if already logged in (only if not a POST request to avoid blocking login attempts)
if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($_SESSION['role'] == 'player') {
        header("Location: index.php");
    } else {
        header("Location: admin/dashboard.php");
    }
    exit;
}

$asset_path = 'https://tanitbet216.com/ui/www.moneyking365.com/';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Xbet216 - Login</title>
  <link rel="icon" type="image/x-icon" href="https://tanitbet216.com/tanitbet.png">

  <!-- CSS Assets -->
  <link href="<?php echo $asset_path; ?>assets/css/bootstrap.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Work+Sans&display=swap" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/css/style.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/css/design-structure.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/css/style-whitelabale.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/css/score-theme.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>assets/style-glob.css" rel="stylesheet">
  <link href="<?php echo $asset_path; ?>styles.447e2c5daf6415369575.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet">

  <style>
      body {
          background-color: #000;
          color: #fff;
          height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          font-family: 'Work Sans', sans-serif;
      }
      .login-wrapper {
          width: 100%;
          max-width: 400px;
          padding: 30px;
          background: #111;
          border-radius: 10px;
          border: 1px solid #E5943F; /* Gold border */
          box-shadow: 0 0 20px rgba(229, 148, 63, 0.2);
      }
      .logo-area {
          text-align: center;
          margin-bottom: 30px;
      }
      .logo-text {
          color: #E5943F;
          font-weight: 900;
          font-size: 32px;
          font-family: 'Arial Black', sans-serif;
          letter-spacing: -1px;
      }
      .form-control {
          background: #222;
          border: 1px solid #444;
          color: #fff;
          height: 45px;
      }
      .form-control:focus {
          background: #333;
          color: #fff;
          border-color: #E5943F;
          box-shadow: none;
      }
      .btn-login {
          background: #E5943F;
          color: #000;
          font-weight: bold;
          border: none;
          height: 45px;
          text-transform: uppercase;
          letter-spacing: 1px;
      }
      .btn-login:hover {
          background: #d48632;
          color: #000;
      }
  </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="login-wrapper">
                <div class="logo-area">
                     <img src="https://tanitbet216.com/tanitbet.png" alt="Royalwinbet" style="max-width: 250px;">
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="background-color: #dc3545; color: white; border: none; font-size: 14px;">
                        <i class="fa fa-exclamation-triangle mr-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label style="color: #aaa; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter your username" style="background: #222; border: 1px solid #444; color: #fff; padding: 10px;" required>
                    </div>
                    <div class="form-group">
                        <label style="color: #aaa; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" style="background: #222; border: 1px solid #444; color: #fff; padding: 10px;" required>
                    </div>
                    <button type="submit" class="btn btn-login btn-block" style="margin-top: 20px;">SECURE LOGIN</button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="index.php" style="color: #E5943F;">Back to Site</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $asset_path; ?>assets/js/jquery-3.2.1.min.js"></script>
<script src="<?php echo $asset_path; ?>assets/js/bootstrap.min.js"></script>
</body>
</html>
