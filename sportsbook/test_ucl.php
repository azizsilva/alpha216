<?php
require_once dirname(__DIR__, 2) . '/forza/includes/db.php';
$stmt = $pdo->query("SELECT start_time, home_team, away_team FROM sb_matches WHERE league_name LIKE '%champions league%' LIMIT 10");
while($r = $stmt->fetch()) {
    echo date('Y-m-d H:i', $r['start_time']) . ' - ' . $r['home_team'] . ' vs ' . $r['away_team'] . "\n";
}
