<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

function out($payload) {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    out(['success' => false, 'message' => 'Unauthorized']);
}

$user_id = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT balance, exposure FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if (!$row) {
        out(['success' => false, 'message' => 'User not found']);
    }
    $balance = (float)($row['balance'] ?? 0);
    $exposure = (float)($row['exposure'] ?? 0);
    $available = $balance - $exposure;
    $_SESSION['coins'] = $balance;
    out([
        'success' => true,
        'balance' => $balance,
        'exposure' => $exposure,
        'available' => $available
    ]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Database Error']);
}

