<?php
require_once 'includes/db.php';

try {
    $new_password = 'admin123';
    $new_hash = md5($new_password);
    
    // Attempt to find the admin user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$new_hash, $user['id']]);
        echo "<h1>✅ Success!</h1>";
        echo "<p>Admin password has been reset to: <strong>$new_password</strong></p>";
        echo "<p><a href='/admin/'>Go to Login</a></p>";
        echo "<p><em>Please delete this file (reset_live_admin.php) after use for security.</em></p>";
    } else {
        echo "<h1>❌ Error</h1>";
        echo "<p>User 'admin' not found in the database.</p>";
    }
} catch (Exception $e) {
    echo "<h1>❌ Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
