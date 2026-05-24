<?php
require 'includes/db.php';
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Tables found: " . implode(', ', $tables) . "\n";
