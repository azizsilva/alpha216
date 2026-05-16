<?php
session_start();
require_once '../includes/db.php';

// Debug Logging
error_log("Language Save Request: " . print_r($_POST, true)); // Check POST data
error_log("Session ID: " . session_id());
error_log("User ID in Session: " . ($_SESSION['user_id'] ?? 'Not Set'));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Handle JSON input properly
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);
error_log("JSON Input: " . $json_input);

$lang = $data['lang'] ?? '';

// Validate language code
$valid_langs = ['en', 'hi', 'ta', 'te', 'kn', 'mr', 'gu', 'bn', 'ml'];
if (!in_array($lang, $valid_langs)) {
    echo json_encode(['success' => false, 'message' => 'Invalid language code']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE id = ?");
    $result = $stmt->execute([$lang, $_SESSION['user_id']]);
    
    if ($result) {
        $_SESSION['language'] = $lang; // Update session
        error_log("Language updated to $lang for User ID " . $_SESSION['user_id']);
        echo json_encode(['success' => true]);
    } else {
        error_log("Update query failed");
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>