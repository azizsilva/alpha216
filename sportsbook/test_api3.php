<?php
$resp = file_get_contents('https://api.b365api.com/v1/bet365/result?sport_id=1&token=254610-7T3dEgVPsVZPNY');
print_r(json_decode($resp, true));
