<?php
$file = 'c:/wamp64/www/public_html/sportsbook/index.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

foreach ($lines as $i => $line) {
    if (strpos($line, '<div class="sb-root"') !== false) {
        echo "Found at line " . ($i+1) . ": $line\n";
    }
}
