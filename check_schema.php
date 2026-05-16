<?php
session_start();
require_once 'includes/db.php';

echo "<h2>Database Schema Check</h2>";

try {
    // 1. Check if 'language' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'language'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✅ Column 'language' exists.<br>";
        
        // 2. Check current user's language (if logged in)
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT language FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $lang = $stmt->fetchColumn();
            echo "Current User Language (DB): " . htmlspecialchars($lang) . "<br>";
            echo "Current User Language (Session): " . htmlspecialchars($_SESSION['language'] ?? 'None') . "<br>";
        } else {
            echo "No user logged in to check specific record.<br>";
        }
    } else {
        echo "❌ Column 'language' DOES NOT exist. Adding it now...<br>";
        $sql = "ALTER TABLE users ADD COLUMN language VARCHAR(5) NOT NULL DEFAULT 'en'";
        $pdo->exec($sql);
        echo "✅ Column 'language' added successfully.<br>";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>