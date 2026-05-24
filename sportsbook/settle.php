<?php
/**
 * Sportsbook GGR Settlement Engine
 * Admin settles matches → won bets get paid, lost bets are profit
 */
header('Content-Type: application/json');
session_start();
require_once '../includes/db.php';

// Only admin can settle
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Non autorisé']);
    exit;
}
$stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || !in_array($user['role'], ['admin','super_admin'])) {
    echo json_encode(['success'=>false,'message'=>'Admin requis']);
    exit;
}

// Ensure tables exist
$pdo->exec("
CREATE TABLE IF NOT EXISTS sportsbook_bets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    total_odds DECIMAL(10,4) NOT NULL,
    potential_returns DECIMAL(15,2) NOT NULL,
    slip JSON NOT NULL,
    status ENUM('pending','won','lost','refunded') DEFAULT 'pending',
    settled_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id), INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sportsbook_ggr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bet_id INT NOT NULL,
    user_id INT NOT NULL,
    stake DECIMAL(15,2) NOT NULL,
    payout DECIMAL(15,2) DEFAULT 0,
    ggr DECIMAL(15,2) NOT NULL COMMENT 'Negative=loss to house, Positive=profit',
    result ENUM('won','lost','refunded') NOT NULL,
    settled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(settled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// ── SETTLE A BET ──────────────────────────────────────────────────────────
if ($action === 'settle') {
    $bet_id = (int)($_POST['bet_id'] ?? 0);
    $result  = $_POST['result'] ?? ''; // 'won','lost','refunded'
    if (!$bet_id || !in_array($result, ['won','lost','refunded'])) {
        echo json_encode(['success'=>false,'message'=>'Paramètres invalides']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM sportsbook_bets WHERE id=? AND status='pending'");
    $stmt->execute([$bet_id]);
    $bet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bet) {
        echo json_encode(['success'=>false,'message'=>'Pari introuvable ou déjà réglé']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $payout = 0;
        $ggr    = 0;

        if ($result === 'won') {
            $payout = (float)$bet['potential_returns'];
            $ggr    = (float)$bet['amount'] - $payout; // negative = loss to house
            // Credit winner
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id=?");
            $stmt->execute([$payout, $bet['user_id']]);
            // Log transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (sender_id,receiver_id,amount,type,description) VALUES (0,?,?,'deposit',?)");
            $stmt->execute([$bet['user_id'], $payout, 'Gain Sportif - Ticket #'.$bet_id]);
        } elseif ($result === 'refunded') {
            $payout = (float)$bet['amount'];
            $ggr    = 0;
            // Refund stake
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id=?");
            $stmt->execute([$payout, $bet['user_id']]);
            $stmt = $pdo->prepare("INSERT INTO transactions (sender_id,receiver_id,amount,type,description) VALUES (0,?,?,'deposit',?)");
            $stmt->execute([$bet['user_id'], $payout, 'Remboursement Pari - Ticket #'.$bet_id]);
        } else {
            // Lost — stake already deducted, house keeps it
            $ggr = (float)$bet['amount']; // full stake = profit
        }

        // Update bet status
        $stmt = $pdo->prepare("UPDATE sportsbook_bets SET status=?, settled_at=NOW() WHERE id=?");
        $stmt->execute([$result, $bet_id]);

        // Record GGR
        $stmt = $pdo->prepare("INSERT INTO sportsbook_ggr (bet_id,user_id,stake,payout,ggr,result) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$bet_id, $bet['user_id'], $bet['amount'], $payout, $ggr, $result]);

        $pdo->commit();
        echo json_encode([
            'success'=>true,
            'message'=>'Pari réglé: '.$result,
            'payout'=>$payout,
            'ggr'=>$ggr
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// ── GGR REPORT ───────────────────────────────────────────────────────────
if ($action === 'ggr_report') {
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $to   = $_GET['to']   ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            SUM(stake)              AS total_stakes,
            SUM(payout)             AS total_payouts,
            SUM(ggr)                AS total_ggr,
            SUM(CASE WHEN result='won'      THEN 1 ELSE 0 END) AS won_count,
            SUM(CASE WHEN result='lost'     THEN 1 ELSE 0 END) AS lost_count,
            SUM(CASE WHEN result='refunded' THEN 1 ELSE 0 END) AS refunded_count,
            COUNT(*)                AS total_bets,
            ROUND(SUM(ggr)/NULLIF(SUM(stake),0)*100, 2) AS margin_pct
        FROM sportsbook_ggr
        WHERE settled_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $stmt->execute([$from, $to]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Daily breakdown
    $stmt = $pdo->prepare("
        SELECT DATE(settled_at) AS day, SUM(stake) AS stakes, SUM(payout) AS payouts, SUM(ggr) AS ggr
        FROM sportsbook_ggr
        WHERE settled_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(settled_at)
        ORDER BY day DESC
    ");
    $stmt->execute([$from, $to]);
    $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true, 'summary'=>$summary, 'daily'=>$daily]);
    exit;
}

// ── PENDING BETS LIST ─────────────────────────────────────────────────────
if ($action === 'pending') {
    $stmt = $pdo->prepare("
        SELECT sb.*, u.username 
        FROM sportsbook_bets sb 
        JOIN users u ON sb.user_id=u.id
        WHERE sb.status='pending'
        ORDER BY sb.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    echo json_encode(['success'=>true, 'bets'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Action invalide']);
