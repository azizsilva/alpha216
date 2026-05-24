<?php
require_once dirname(__DIR__, 2) . '/forza/includes/db.php';
$stmt = $pdo->query('SELECT league_name, COUNT(*) as c FROM sb_matches GROUP BY league_name ORDER BY c DESC LIMIT 20');
while($r = $stmt->fetch()) {
    echo $r['league_name'] . ': ' . $r['c'] . "\n";
}
