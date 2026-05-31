<?php
/**
 * Place Bet Backend Handler
 */
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé. Veuillez vous connecter.']);
    exit;
}

require_once '../includes/db.php';

// Get JSON Input
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['amount']) || !isset($data['slip'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$amount = (float)$data['amount'];
$slip = json_encode($data['slip']);
$total_odds = (float)$data['total_odds'];
$mode = strtolower(trim($data['mode'] ?? 'simple'));
if (!in_array($mode, ['simple','combi','combine','system','systeme'], true)) $mode = 'simple';
if ($mode === 'combine') $mode = 'combi';
if ($mode === 'systeme') $mode = 'system';

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Le montant doit être supérieur à zéro.']);
    exit;
}

// ── Ensure schema exists BEFORE opening the transaction (DDL auto-commits in MySQL) ──
$pdo->exec("CREATE TABLE IF NOT EXISTS sportsbook_bets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL DEFAULT 0,
    amount          DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_odds      DECIMAL(10,4) NOT NULL DEFAULT 1,
    potential_returns DECIMAL(15,2) NOT NULL DEFAULT 0,
    slip            JSON NULL,
    status          ENUM('pending','won','lost','refunded') NOT NULL DEFAULT 'pending',
    settled_at      DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Patch if table was created with old minimal schema
$_cols = array_column($pdo->query("SHOW COLUMNS FROM sportsbook_bets")->fetchAll(PDO::FETCH_ASSOC), 'Field');
if (!in_array('user_id', $_cols))           $pdo->exec("ALTER TABLE sportsbook_bets ADD COLUMN user_id INT NOT NULL DEFAULT 0 AFTER id");
if (!in_array('total_odds', $_cols))        $pdo->exec("ALTER TABLE sportsbook_bets ADD COLUMN total_odds DECIMAL(10,4) NOT NULL DEFAULT 1");
if (!in_array('potential_returns', $_cols)) $pdo->exec("ALTER TABLE sportsbook_bets ADD COLUMN potential_returns DECIMAL(15,2) NOT NULL DEFAULT 0");
if (!in_array('slip', $_cols))              $pdo->exec("ALTER TABLE sportsbook_bets ADD COLUMN slip JSON NULL");
if (!in_array('settled_at', $_cols))        $pdo->exec("ALTER TABLE sportsbook_bets ADD COLUMN settled_at DATETIME NULL");
if (!in_array('mode', $_cols))              $pdo->exec("ALTER TABLE sportsbook_bets ADD COLUMN mode VARCHAR(16) NOT NULL DEFAULT 'simple'");
// transactions table fallback
$pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL DEFAULT 0,
    receiver_id INT NOT NULL DEFAULT 0,
    amount      DECIMAL(15,2) NOT NULL DEFAULT 0,
    type        VARCHAR(50) NOT NULL DEFAULT 'withdrawal',
    description TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sender (sender_id),
    KEY idx_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    $pdo->beginTransaction();

    // Lock the user row for balance check
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Utilisateur introuvable.");
    }

    if ((float)$user['balance'] < $amount) {
        throw new Exception("Solde insuffisant. Solde actuel: " . number_format((float)$user['balance'], 2) . " TND");
    }

    // Deduct balance
    $new_balance = (float)$user['balance'] - $amount;
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $user_id]);

    // Insert Bet
    $potential_returns = $amount * $total_odds;
    $stmt = $pdo->prepare("INSERT INTO sportsbook_bets (user_id, amount, total_odds, potential_returns, slip, mode, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$user_id, $amount, $total_odds, $potential_returns, $slip, $mode]);
    $bet_id = $pdo->lastInsertId();

    // Insert Transaction History
    $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'withdrawal', ?)");
    $stmt->execute([$user_id, 0, $amount, 'Pari Sportif - Ticket #' . $bet_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Pari placé avec succès!',
        'new_balance' => $new_balance,
        'bet_id' => $bet_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
