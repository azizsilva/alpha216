<?php
require_once dirname(__DIR__, 2) . '/forza/includes/db.php';
$stmt = $pdo->query("SELECT DISTINCT league_name FROM sb_matches WHERE league_name LIKE '%world cup%' OR league_name LIKE '%champions league%'");
while($r = $stmt->fetch()) {
    echo $r['league_name'] . "\n";
}
