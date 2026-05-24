<?php
require_once 'includes/odds-api.php';
$res = odds_api_get('/events/live', ['sport' => 'soccer']);
echo json_encode($res, JSON_PRETTY_PRINT);
