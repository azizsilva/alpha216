<?php
require_once 'includes/db.php';
global $pdo;

try {
    if (!$pdo) {
        throw new Exception("Database connection failed. Please check includes/db.php settings.");
    }
    
    $email = 'Owner@alpina216.com';
    $password = 'Owner@alpina216.com';
    $username = 'Owner'; // We still need a username, 'Owner' is clear.
    $hash = md5($password);
    $role = 'admin';
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update existing user
        $update = $pdo->prepare("UPDATE users SET password = ?, role = ?, email = ?, password_text = ? WHERE id = ?");
        $update->execute([$hash, $role, $email, $password, $user['id']]);
        echo "<h1>✅ Account Updated!</h1>";
        echo "<p>User <strong>$email</strong> has been updated to admin.</p>";
    } else {
        // Insert new user
        $stmt = $pdo->query("DESCRIBE users");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $fields = ['username', 'password', 'role'];
        $values = [$username, $hash, $role];
        
        if (in_array('password_text', $cols)) {
            $fields[] = 'password_text';
            $values[] = $password;
        }
        if (in_array('balance', $cols)) {
            $fields[] = 'balance';
            $values[] = 0.00;
        }
        if (in_array('email', $cols)) {
            $fields[] = 'email';
            $values[] = $email;
        }
        if (in_array('mobile', $cols)) {
            $fields[] = 'mobile';
            $values[] = '0000000000';
        }

        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $insert = $pdo->prepare($sql);
        $insert->execute($values);
        
        echo "<h1>✅ Account Created!</h1>";
        echo "<p>User <strong>$username</strong> is now your super admin.</p>";
    }
    echo "<p><a href='/admin/'>Go to Login</a></p>";
    echo "<p><em>Please delete this file (seed_owner.php) after use for security.</em></p>";

} catch (Exception $e) {
    echo "<h1>❌ Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
