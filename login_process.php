<?php
session_start();
require_once 'includes/db.php';

// Disable error display to avoid breaking JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

function login_log($msg) {
    file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    try {
        login_log("Attempting login for user: $username");
        
        // Prepare statement
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'player'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            login_log("User found. ID: " . $user['id']);
            
            // Verify Password
            $password_valid = false;
            
            if (password_verify($password, $user['password'])) {
                $password_valid = true;
                login_log("Password valid (password_verify)");
            } elseif (md5($password) === $user['password']) {
                $password_valid = true;
                login_log("Password valid (md5)");
            } elseif (isset($user['password_text']) && $password === $user['password_text']) {
                $password_valid = true;
                login_log("Password valid (password_text plain match)");
            }

            if ($password_valid) {
                // If it was md5 or plain, upgrade it to md5 if that's the preferred legacy format, 
                // or just leave it. The original code tried to upgrade to md5.
                if (!preg_match('/^[a-f0-9]{32}$/i', (string)($user['password'] ?? ''))) {
                    try {
                        $stmt2 = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                        $stmt2->execute([md5($password), $user['id']]);
                    } catch (Exception $e) {
                        login_log("Failed to update password to md5: " . $e->getMessage());
                    }
                }
                
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['language'] = $user['language'] ?? 'en';
                $_SESSION['coins'] = $user['balance'] ?? 0;
                
                login_log("Login success for: $username");
                // Calculate base url
                $this_dir = str_replace('\\', '/', __DIR__);
                $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
                $base_url = '/';
                if (strpos($this_dir, $doc_root) === 0) {
                    $base_url = substr($this_dir, strlen($doc_root));
                    $base_url = '/' . ltrim($base_url, '/') . '/';
                    $base_url = str_replace('//', '/', $base_url);
                }
                echo json_encode(['success' => true, 'redirect' => $base_url]);
                exit;
            } else {
                login_log("Invalid password for: $username");
                echo json_encode(['success' => false, 'message' => 'Invalid Password.']);
                exit;
            }
        } else {
            login_log("User not found: $username");
            echo json_encode(['success' => false, 'message' => 'User not exist.']);
            exit;
        }
    } catch (Throwable $e) {
        login_log("Login Exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Login failed. ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
