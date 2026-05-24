<?php
$_SERVER['DOCUMENT_ROOT'] = 'c:/wamp64/www';
$this_dir = str_replace('\\', '/', __DIR__); 
$root_dir_name = str_replace('\\', '/', dirname($this_dir));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

$base_url = '/';
if (strpos($root_dir_name, $doc_root) === 0) {
    $base_url = substr($root_dir_name, strlen($doc_root));
    $base_url = '/' . ltrim($base_url, '/') . '/';
    $base_url = str_replace('//', '/', $base_url);
}
echo "this_dir: $this_dir\n";
echo "root_dir_name: $root_dir_name\n";
echo "doc_root: $doc_root\n";
echo "base_url: $base_url\n";
