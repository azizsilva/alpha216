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

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Le montant doit être supérieur à zéro.']);
    exit;
}

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
        throw new Exception("Solde insuffisant.");
    }

    // Deduct balance
    $new_balance = (float)$user['balance'] - $amount;
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $user_id]);

    // Ensure bets table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS sportsbook_bets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        total_odds DECIMAL(10,2) NOT NULL,
        potential_returns DECIMAL(15,2) NOT NULL,
        slip JSON NOT NULL,
        status ENUM('pending', 'won', 'lost', 'refunded') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert Bet
    $potential_returns = $amount * $total_odds;
    $stmt = $pdo->prepare("INSERT INTO sportsbook_bets (user_id, amount, total_odds, potential_returns, slip, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$user_id, $amount, $total_odds, $potential_returns, $slip]);
    $bet_id = $pdo->lastInsertId();

    // Insert Transaction History
    $stmt = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) VALUES (?, ?, ?, 'withdrawal', ?)");
    // sender is user, receiver is 0 (system)
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
