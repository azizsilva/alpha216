<?php
$c = file_get_contents('app.js');
foreach(explode("\n", $c) as $i => $l) {
  if (stripos($l, 'window.sb') !== false) {
    echo ($i+1) . ': ' . trim($l) . "\n";
  }
}
