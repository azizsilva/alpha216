<?php
session_start();
require_once 'includes/db.php';
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = trim($_POST['mobile']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $countryCode = $_POST['countryCode'] ?? '+91';

    if (empty($mobile) || empty($username) || empty($password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $password_hash = md5($password);

    try {
        // Check if username or mobile exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR mobile = ?");
        $stmt->execute([$username, $mobile]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username or Mobile Number already exists.']);
            exit;
        }

        // Add mobile column dynamically if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'mobile'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN mobile VARCHAR(15) DEFAULT NULL");
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, password, mobile, role, balance, status, language, created_at) VALUES (?, ?, ?, 'player', 0.00, 'active', 'en', NOW())");
        $stmt->execute([$username, $password_hash, $mobile]);

        // Auto Login
        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'player';
        $_SESSION['language'] = 'en'; // Default
        
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Registration Failed.']);
        exit;
    }
}
?>
