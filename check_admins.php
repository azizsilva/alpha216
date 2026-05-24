<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT id, email, username, role FROM users WHERE role='admin'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
