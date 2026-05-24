<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->query("DESCRIBE users");
    $cols = $stmt->fetchAll();
    echo "Columns in 'users' table:\n";
    foreach ($cols as $col) {
        echo "{$col['Field']} ({$col['Type']})\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
