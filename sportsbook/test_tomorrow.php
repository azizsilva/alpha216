<?php
$url = 'https://api.b365api.com/v1/bet365/upcoming?sport_id=1&token=254610-7T3dEgVPsVZPNY&day=20260524';
$resp = file_get_contents($url);
$d = json_decode($resp, true);
$found = [];
foreach($d['results'] ?? [] as $r) {
  if (stripos($r['league']['name'], 'premier') !== false || stripos($r['league']['name'], 'england') !== false) {
    $found[] = $r['league']['name'] . ' : ' . $r['home']['name'] . ' vs ' . $r['away']['name'];
  }
}
print_r($found);
