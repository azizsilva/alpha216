<?php
session_start();
session_destroy();

// Redirect to base URL dynamically
// This ensures it works in subdirectories too
$this_dir = str_replace('\\', '/', __DIR__);
$root_dir_name = $this_dir; // logout.php is in root
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

$base_url = '/';
if (strpos($root_dir_name, $doc_root) === 0) {
    $base_url = substr($root_dir_name, strlen($doc_root));
    $base_url = '/' . ltrim($base_url, '/') . '/';
    $base_url = str_replace('//', '/', $base_url);
}

header("Location: " . $base_url);
exit;
?>
