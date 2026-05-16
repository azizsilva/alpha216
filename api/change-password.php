<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

function out($payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    out(['success' => false, 'message' => 'User not logged in.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['success' => false, 'message' => 'Invalid request method.']);
}

$user_id = (int)$_SESSION['user_id'];
$old_password = $_POST['old_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($old_password) || empty($new_password)) {
    out(['success' => false, 'message' => 'All fields are required.']);
}

try {
    // Verify old password (support legacy hashes)
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        out(['success' => false, 'message' => 'User not found.']);
    }

    $stored = (string)($user['password'] ?? '');
    $md5_old = md5($old_password);
    $ok = false;
    if ($stored === $md5_old) {
        $ok = true;
    } elseif ($stored !== '' && function_exists('password_verify') && password_verify($old_password, $stored)) {
        $ok = true;
    }

    if (!$ok) {
        out(['success' => false, 'message' => 'Old password is incorrect.']);
    }

    // Update to new password
    $hashed_new_password = md5($new_password);
    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($update_stmt->execute([$hashed_new_password, $user_id])) {
        out(['success' => true, 'message' => 'Password changed successfully!']);
    } else {
        out(['success' => false, 'message' => 'Error updating password.']);
    }

} catch (PDOException $e) {
    out(['success' => false, 'message' => 'Database error.']);
}
?>
