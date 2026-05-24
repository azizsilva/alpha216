<?php
require 'test_api.php';
$v3 = api_get('/v3/bet365/upcoming', ['sport_id' => 1, 'page' => 1]);
file_put_contents('../scratch/bet365_v3_upcoming.json', json_encode(array_slice($v3, 0, 3), JSON_PRETTY_PRINT));
echo "Done v3 upcoming.\n";

$prematch_api = api_get('/v1/bet365/prematch', ['FI' => '11933836']); // some random ID
file_put_contents('../scratch/bet365_prematch_test.json', json_encode($prematch_api, JSON_PRETTY_PRINT));
