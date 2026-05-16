<?php
session_start();
require_once 'includes/db.php';

function getOrCreateDemoUser($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role='demo' AND status='active' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) return $user;

    $tries = 0;
    while ($tries < 3) {
        $tries++;
        try {
            $username = 'demo_user_' . rand(1000, 9999);
            $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (username, mobile, password, role, balance, status, language)
                    VALUES (:username, :mobile, :password, 'demo', 0.00, 'active', 'en')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':username' => $username,
                ':mobile' => '0000000000',
                ':password' => $password
            ]);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute([':id' => $pdo->lastInsertId()]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Demo Login Error: " . $e->getMessage());
        }
    }

    return false;
}

$demoUser = getOrCreateDemoUser($pdo);

if ($demoUser) {
    session_unset();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $demoUser['id'];
    $_SESSION['username'] = $demoUser['username'];
    $_SESSION['role'] = 'player';
    $_SESSION['is_demo'] = true;
    $_SESSION['language'] = $demoUser['language'] ?? 'en';
    $_SESSION['coins'] = (float)($demoUser['balance'] ?? 0);
    
    // Redirect to Home
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $base = $host ? ($protocol . $host . '/') : '';
    header("Location: " . ($base ? $base . "index.php" : "index.php"));
    exit();
} else {
    // Handle Error
    $_SESSION['error'] = "Unable to login with Demo account. Please try again later.";
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $base = $host ? ($protocol . $host . '/') : '';
    header("Location: " . ($base ? $base . "index.php" : "index.php"));
    exit();
}
?>
