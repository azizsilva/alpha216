<?php
require_once __DIR__ . '/includes/db.php';
global $pdo;

$username = 'aziz123';
$password = 'aziz123';
$hash     = md5($password);
$role     = 'player';
$balance  = 10000.00; // Starting balance for play

try {
// Ensure the users table exists first so it doesn't throw a Fatal Error on empty databases
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(191) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    password_text VARCHAR(255) NULL,
    role VARCHAR(50) DEFAULT 'player',
    balance DECIMAL(15,2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Check if already exists
$check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$check->execute([$username]);
if ($check->fetch()) {
    echo "Player '$username' already exists. Updating password and balance...\n";
    $upd = $pdo->prepare("UPDATE users SET password = ?, password_text = ?, balance = ?, status = 'active' WHERE username = ?");
    $upd->execute([$hash, $password, $balance, $username]);
    echo "Updated successfully.\n";
} else {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, password_text, role, balance, status, mobile) VALUES (?, ?, ?, ?, ?, 'active', ?)");
    $stmt->execute([$username, $hash, $password, $role, $balance, '0000000000']);
    echo "Player '$username' created successfully with balance $balance.\n";
}

} catch (Throwable $e) {
    echo "<h1>CRITICAL ERROR DETECTED</h1>";
    echo "<pre>" . print_r($e->getMessage(), true) . "</pre>";
    echo "<pre>" . print_r($e->getTraceAsString(), true) . "</pre>";
}
