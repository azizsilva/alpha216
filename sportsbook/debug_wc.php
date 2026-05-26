<?php
define('BETSAPI_TOKEN', '254610-7T3dEgVPsVZPNY');
define('BETSAPI_BASE',  'https://api.b365api.com');

function betsapi_get($path, $params = []) {
    $params['token'] = BETSAPI_TOKEN;
    $url = BETSAPI_BASE . $path . '?' . http_build_query($params);
    echo "URL: $url\n";
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    echo "Raw: " . substr($body, 0, 300) . "\n";
    return json_decode($body, true);
}

// Check DB directly
$db_paths = [
    dirname(__DIR__, 2) . '/forza/includes/db.php',
    __DIR__ . '/../includes/db.php',
];
$pdo = null;
foreach ($db_paths as $p) {
    if (file_exists($p)) {
        require_once $p;
        if (isset($pdo) && $pdo instanceof PDO) { echo "DB connected via: $p\n"; break; }
    }
}

if ($pdo) {
    $st = $pdo->query("SELECT DISTINCT league_name FROM sb_matches WHERE sport_id=1 AND status!='ended' ORDER BY league_name LIMIT 50");
    echo "\n=== Leagues in DB ===\n";
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ln) echo $ln . "\n";
    
    $cnt = $pdo->query("SELECT COUNT(*) FROM sb_matches WHERE sport_id=1 AND status!='ended'")->fetchColumn();
    echo "\nTotal active football matches in DB: $cnt\n";
}
