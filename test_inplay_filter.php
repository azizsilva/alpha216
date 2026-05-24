<?php
define('BETSAPI_TOKEN', '254610-7T3dEgVPsVZPNY');
define('BETSAPI_BASE', 'https://api.b365api.com');
$resp = json_decode(file_get_contents(BETSAPI_BASE . '/v1/bet365/inplay_filter?sport_id=1&token=' . BETSAPI_TOKEN), true);
file_put_contents('scratch/bet365_inplay_filter.json', json_encode(array_slice($resp['results'], 0, 5), JSON_PRETTY_PRINT));
echo "Done";
