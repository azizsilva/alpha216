<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function out($payload) {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function game_name_for_uid($uid) {
    static $map = null;
    $uid = trim((string)$uid);
    if ($uid === '') return '-';
    if ($map === null) {
        $map = [];
        $dir = __DIR__ . '/../games-json';
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $json = @file_get_contents($file);
            if ($json === false) continue;
            $items = json_decode($json, true);
            if (!is_array($items)) continue;
            foreach ($items as $g) {
                if (!is_array($g)) continue;
                $id = (string)($g['gameid'] ?? $g['game_id'] ?? $g['id'] ?? '');
                $name = trim((string)($g['gamename'] ?? $g['game_name'] ?? $g['name'] ?? ''));
                if ($id !== '' && $name !== '') $map[$id] = $name;
            }
        }
    }
    return $map[$uid] ?? $uid;
}

if (!isset($_SESSION['user_id'])) {
    out(['success' => false, 'message' => 'Unauthorized']);
}

$user_id = (int)$_SESSION['user_id'];
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$game_uid = trim((string)($_GET['game_uid'] ?? ''));

$where = " WHERE user_id = ? AND result_status = 1";
$params = [$user_id];

if ($game_uid !== '') {
    $where .= " AND game_uid = ?";
    $params[] = $game_uid;
}
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where .= " AND created_at >= ?";
    $params[] = $from . ' 00:00:00';
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where .= " AND created_at <= ?";
    $params[] = $to . ' 23:59:59';
}

try {
    $stmt = $pdo->prepare("SELECT
            COALESCE(NULLIF(game_uid, ''), '-') AS game_uid,
            DATE(created_at) AS play_date,
            SUM(CASE WHEN action='bet' THEN bet_amount ELSE 0 END) AS total_bet,
            SUM(CASE WHEN action='win' THEN win_amount ELSE 0 END) AS total_win,
            SUM(CASE WHEN action='refund' THEN bet_amount ELSE 0 END) AS total_refund,
            COUNT(*) AS events
        FROM game_callback_events
        $where
        GROUP BY DATE(created_at), COALESCE(NULLIF(game_uid, ''), '-')
        ORDER BY play_date DESC, game_uid ASC
        LIMIT 500");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $total_bet = 0.0;
    $total_win = 0.0;
    $total_refund = 0.0;
    foreach ($rows as &$r) {
        $bet = (float)($r['total_bet'] ?? 0);
        $win = (float)($r['total_win'] ?? 0);
        $refund = (float)($r['total_refund'] ?? 0);
        $net = $win - $bet + $refund;
        $total_bet += $bet;
        $total_win += $win;
        $total_refund += $refund;
        $r['game_name'] = game_name_for_uid($r['game_uid'] ?? '');
        $r['total_bet'] = number_format($bet, 2, '.', '');
        $r['total_win'] = number_format($win, 2, '.', '');
        $r['total_refund'] = number_format($refund, 2, '.', '');
        $r['net_pl'] = number_format($net, 2, '.', '');
    }
    unset($r);

    out([
        'success' => true,
        'rows' => $rows,
        'totals' => [
            'total_bet' => number_format($total_bet, 2, '.', ''),
            'total_win' => number_format($total_win, 2, '.', ''),
            'total_refund' => number_format($total_refund, 2, '.', ''),
            'net_pl' => number_format($total_win - $total_bet + $total_refund, 2, '.', ''),
        ],
    ]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Database Error']);
}
