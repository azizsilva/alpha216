<?php
define('BETSAPI_TOKEN', '254610-7T3dEgVPsVZPNY');
define('BETSAPI_BASE', 'https://api.b365api.com');
$resp = json_decode(file_get_contents(BETSAPI_BASE . '/v1/bet365/inplay?token=' . BETSAPI_TOKEN), true);
if (!is_dir('scratch')) mkdir('scratch');
file_put_contents('scratch/bet365_inplay.json', json_encode(array_slice($resp['results'][0], 0, 500), JSON_PRETTY_PRINT));
echo "Done";
