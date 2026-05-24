<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE email = ?");
    $stmt->execute(['azizrezgui60@gmail.com']);
    $user = $stmt->fetch();

    if ($user) {
        echo "User found by email: ID: {$user['id']} | Username: {$user['username']} | Role: {$user['role']}\n";
    } else {
        echo "No user found with email: azizrezgui60@gmail.com\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
