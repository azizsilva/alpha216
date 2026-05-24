<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->query("SELECT id, username, email, role, password, password_text, status FROM users WHERE role IN ('admin', 'partner', 'super_master')");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "No admin/partner/super_master users found.\n";
    } else {
        echo "High-level users found:\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']} | Username: {$user['username']} | Email: {$user['email']} | Role: {$user['role']} | Pass (Text): {$user['password_text']} | Status: {$user['status']}\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
