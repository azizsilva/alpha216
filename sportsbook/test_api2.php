<?php
$url = 'https://api.b365api.com/v1/bet365/result?sport_id=1&token=254610-7T3dEgVPsVZPNY';
$resp = file_get_contents($url);
$d = json_decode($resp, true);
echo "Success: " . ($d['success'] ?? 0) . "\n";
echo "Matches: " . count($d['results'] ?? []) . "\n";
