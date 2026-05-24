<?php
$j = json_decode(file_get_contents('cache/allup_1.json'), true);
$leagues = [];
foreach($j['results'] ?? [] as $r) {
    if(isset($r['league']['name'])) {
        $leagues[$r['league']['name']] = 1;
    }
}
$found = [];
foreach(array_keys($leagues) as $name) {
    if (stripos($name, 'spain') !== false || stripos($name, 'liga') !== false) {
        $found[] = $name;
    }
}
print_r($found);
