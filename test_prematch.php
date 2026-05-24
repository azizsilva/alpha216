<?php
define('BETSAPI_TOKEN', '254610-7T3dEgVPsVZPNY');
define('BETSAPI_BASE', 'https://api.b365api.com');
$fi = '195125766'; // from upcoming
$resp = json_decode(file_get_contents(BETSAPI_BASE . '/v3/bet365/prematch?FI=' . $fi . '&token=' . BETSAPI_TOKEN), true);
file_put_contents('scratch/bet365_prematch.json', json_encode($resp, JSON_PRETTY_PRINT));
echo "Done";
