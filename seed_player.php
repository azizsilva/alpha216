<?php
require_once __DIR__ . '/includes/db.php';
global $pdo;

$username = 'aziz123';
$password = 'aziz123';
$hash     = md5($password);
$role     = 'player';
$balance  = 10000.00; // Starting balance for play

// Check if already exists
$check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$check->execute([$username]);
if ($check->fetch()) {
    echo "Player '$username' already exists. Updating password and balance...\n";
    $upd = $pdo->prepare("UPDATE users SET password = ?, password_text = ?, balance = ?, status = 'active' WHERE username = ?");
    $upd->execute([$hash, $password, $balance, $username]);
    echo "Updated successfully.\n";
} else {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, password_text, role, balance, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$username, $hash, $password, $role, $balance]);
    echo "Player '$username' created successfully with balance $balance.\n";
}
