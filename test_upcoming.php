<?php
require 'c:/wamp64/www/public_html/includes/header.php';
require 'c:/wamp64/www/public_html/sportsbook/api_funcs.php';
$resp = api_get('/v1/bet365/upcoming', ['sport_id'=>1, 'page'=>1]);
file_put_contents('scratch/bet365_upcoming.json', json_encode(array_slice($resp, 0, 5), JSON_PRETTY_PRINT));
echo 'Done';
