<?php
$j = json_decode(file_get_contents('upcoming_sample.json'), true);
$leagues = [];
foreach($j['results'] as $r) {
    $id = $r['league']['id'];
    $name = $r['league']['name'];
    $leagues[$id] = $name;
}
foreach($leagues as $id => $name) echo "$id | $name\n";
