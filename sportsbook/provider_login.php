<?php
session_start();
require '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        // Authenticate specifically for provider role
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'provider'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check password (using password_verify if hashed, or plaintext/md5 fallback based on system)
            // Let's support both plaintext and md5 just in case
            $valid = false;
            if ($user['password'] === $password) { $valid = true; } // Plaintext
            else if ($user['password'] === md5($password)) { $valid = true; } // MD5
            else if (password_verify($password, $user['password'])) { $valid = true; } // Hash

            if ($valid) {
                // Clear old session
                session_unset();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'] ?? 'Provider';
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['coins'] = $user['balance'];
                
                header("Location: provider_dashboard.php");
                exit;
            } else {
                $error = "Mot de passe incorrect.";
            }
        } else {
            $error = "Accès refusé. Compte Provider introuvable.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}

// Redirect if already logged in as provider
if (isset($_SESSION['role']) && $_SESSION['role'] === 'provider') {
    header("Location: provider_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Login - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0a0e17;
            --bg-card: #151b2b;
            --primary: #3b82f6;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            background-color: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .login-card h2 {
            margin-top: 0;
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 8px;
        }
        .login-card p.subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }
        .btn-submit:hover {
            background: #2563eb;
        }
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .padlock {
            text-align: center;
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="padlock">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h2>Provider Access</h2>
        <p class="subtitle">Secure authentication gateway</p>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="provider_login.php">
            <div class="form-group">
                <label>Adresse Email</label>
                <input type="email" name="email" class="form-control" placeholder="provider@gmail.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-submit">Se Connecter</button>
        </form>
    </div>

</body>
</html>
