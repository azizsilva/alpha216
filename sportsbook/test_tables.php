<?php
require_once dirname(__DIR__, 2) . '/forza/includes/db.php';
$stmt = $pdo->query('SHOW TABLES');
while($r = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $r[0] . "\n";
}
