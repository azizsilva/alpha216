<?php
$key = '5bae09f6733542f217de37a1d00dbbfca862582011d923e47064e0c5a667e19d';
$url = "https://api.the-odds-api.com/v4/sports?apiKey=$key";
$res = file_get_contents($url);
echo substr($res, 0, 500);
