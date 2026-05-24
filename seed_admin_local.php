<?php
require_once 'includes/db.php';

try {
    // 1. Update user Aziz to admin and set email
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin', email = ?, password = ?, password_text = ?, status = 'active' WHERE id = 209 OR username = 'Aziz'");
    $stmt->execute(['azizrezgui60@gmail.com', md5('admin123'), 'admin123']);
    
    if ($stmt->rowCount() > 0) {
        echo "Updated user 'Aziz' to Admin with email 'azizrezgui60@gmail.com' and password 'admin123'.\n";
    } else {
        // If Aziz doesn't exist, create it
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, password_text, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Aziz', 'azizrezgui60@gmail.com', md5('admin123'), 'admin123', 'admin', 'active']);
        echo "Created user 'Aziz' as Admin with email 'azizrezgui60@gmail.com' and password 'admin123'.\n";
    }

    // 2. Also ensure default 'admin' user is set correctly
    $stmt = $pdo->prepare("UPDATE users SET password = ?, password_text = ?, status = 'active' WHERE username = 'admin'");
    $stmt->execute([md5('admin123'), 'admin123']);
    echo "Ensured default 'admin' user password is 'admin123'.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
