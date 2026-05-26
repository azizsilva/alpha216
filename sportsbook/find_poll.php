<?php
$code = file_get_contents('app.js');
preg_match_all('/(function applyLiveRefresh|pollInterval|POLL_MS|setInterval.*poll|startPoll|scheduleNext)/m', $code, $m);
foreach (array_unique($m[0]) as $match) echo trim($match) . PHP_EOL;

// Also find line numbers
$lines = explode("\n", $code);
foreach ($lines as $i => $line) {
    if (preg_match('/function applyLiveRefresh|pollInterval\s*=|POLL_MS|scheduleNext/', $line)) {
        echo ($i+1) . ': ' . trim($line) . PHP_EOL;
    }
}
