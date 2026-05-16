<?php
require_once 'includes/db.php';
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    print_r($tables);
    
    if (in_array('users', $tables)) {
        $stmt = $pdo->query("DESCRIBE users");
        $cols = $stmt->fetchAll();
        echo "\nStructure of 'users' table:\n";
        print_r($cols);
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        echo "\nUser count: " . $stmt->fetchColumn() . "\n";
    } else {
        echo "\n'users' table NOT FOUND!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
