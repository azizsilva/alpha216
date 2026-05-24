<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT home_team, away_team, live_odds FROM sb_matches WHERE home_team LIKE '%Getafe%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
