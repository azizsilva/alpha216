<?php
require_once 'includes/db.php';

try {
    echo "Connected successfully to database: " . $db . "\n";
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "Table 'users' does not exist!\n";
        exit;
    }

    $stmt = $pdo->query("SELECT id, username, role, status FROM users");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "No users found in the database.\n";
    } else {
        echo "Users found:\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']} | Username: {$user['username']} | Role: {$user['role']} | Status: {$user['status']}\n";
        }
    }
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
