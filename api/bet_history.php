<?php
session_start();
require_once '../includes/db.php';

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

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$game_uid = isset($_GET['game_uid']) ? trim((string)$_GET['game_uid']) : '';

if ($limit < 1) $limit = 50;
if ($limit > 200) $limit = 200;
if ($offset < 0) $offset = 0;

$where = " WHERE user_id = ?";
$params = [$user_id];

if ($action !== '' && in_array($action, ['bet','win','refund'], true)) {
    $where .= " AND action = ?";
    $params[] = $action;
}

if ($game_uid !== '') {
    $where .= " AND game_uid = ?";
    $params[] = $game_uid;
}

if ($from !== '') {
    $where .= " AND created_at >= ?";
    $params[] = $from . " 00:00:00";
}

if ($to !== '') {
    $where .= " AND created_at <= ?";
    $params[] = $to . " 23:59:59";
}

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM game_callback_events" . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT id, action, game_uid, txn_id, game_round, provider_ts, bet_amount, win_amount, amount_delta,
                balance_before, balance_after, result_status, result_message, request_ip, created_at
            FROM game_callback_events" . $where . "
            ORDER BY id DESC
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['game_name'] = game_name_for_uid($row['game_uid'] ?? '');
    }
    unset($row);

    out([
        'success' => true,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'rows' => $rows
    ]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Database Error']);
}
