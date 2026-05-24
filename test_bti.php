<?php
require_once 'api/game_logic.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['coins'] = 1000;
$res = launchBtiGame(1, '3978', 'https://365forzza.shop/sportsbook/', null, true);
echo json_encode($res);
