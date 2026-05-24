<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->query("SELECT id, username, email, role, password, password_text, status FROM users WHERE email LIKE '%aziz%' OR username LIKE '%aziz%'");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "No matching users found.\n";
    } else {
        echo "Matching users found:\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']} | Username: {$user['username']} | Email: {$user['email']} | Role: {$user['role']} | Pass (Hash): {$user['password']} | Pass (Text): {$user['password_text']} | Status: {$user['status']}\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
