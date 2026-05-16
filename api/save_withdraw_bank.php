<?php
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function out($payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'])) out(['success' => false, 'message' => 'User not logged in.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['success' => false, 'message' => 'Invalid request method.']);

$user_id = (int)$_SESSION['user_id'];
$bank_slot = (int)($_POST['bank_slot'] ?? 0);
$bank_name = trim((string)($_POST['bank_name'] ?? ''));
$ifsc_swift = strtoupper(trim((string)($_POST['ifsc_swift'] ?? '')));
$account_no = preg_replace('/\s+/', '', (string)($_POST['account_no'] ?? ''));
$account_holder = trim((string)($_POST['account_holder'] ?? ''));

if (!in_array($bank_slot, [1,2,3], true)) out(['success' => false, 'message' => 'Invalid bank slot.']);
if ($bank_name === '' || strlen($bank_name) > 120) out(['success' => false, 'message' => 'Select a bank.' ]);
if ($ifsc_swift === '' || strlen($ifsc_swift) > 32) out(['success' => false, 'message' => 'Enter IFSC/SWIFT.' ]);
if ($account_no === '' || strlen($account_no) > 40) out(['success' => false, 'message' => 'Enter account number.' ]);
if ($account_holder === '' || strlen($account_holder) > 120) out(['success' => false, 'message' => 'Enter account holder name.' ]);

try {
    $stmt = $pdo->prepare("INSERT INTO user_withdraw_banks (user_id, bank_slot, bank_name, ifsc_swift, account_no, account_holder, enabled)
        VALUES (?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            bank_name=VALUES(bank_name),
            ifsc_swift=VALUES(ifsc_swift),
            account_no=VALUES(account_no),
            account_holder=VALUES(account_holder),
            enabled=1");
    $stmt->execute([$user_id, $bank_slot, $bank_name, $ifsc_swift, $account_no, $account_holder]);

    out([
        'success' => true,
        'bank' => [
            'bank_slot' => $bank_slot,
            'bank_name' => $bank_name,
            'ifsc_swift' => $ifsc_swift,
            'account_no' => $account_no,
            'account_holder' => $account_holder
        ]
    ]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Server error.']);
}

