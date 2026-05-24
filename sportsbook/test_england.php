<?php
$found = false;
for($i=1; $i<=15; $i++){
  if(!file_exists('cache/allup_'.$i.'.json')) continue;
  $j = json_decode(file_get_contents('cache/allup_'.$i.'.json'), true);
  foreach($j['results'] ?? [] as $r){
    if(stripos($r['league']['name'], 'england') !== false) {
      echo $r['league']['name'] . "\n";
      $found = true;
    }
  }
}
if(!$found) echo 'No England leagues found.';
