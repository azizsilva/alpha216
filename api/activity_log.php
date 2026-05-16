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
$kind = trim((string)($_GET['kind'] ?? 'all'));
$limit = (int)($_GET['limit'] ?? 100);
if ($limit < 1) $limit = 100;
if ($limit > 300) $limit = 300;

function date_ok($s) {
    return $s !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

function within_range($created, $from, $to) {
    $ts = strtotime((string)$created);
    if (!$ts) return true;
    if (date_ok($from) && $ts < strtotime($from . ' 00:00:00')) return false;
    if (date_ok($to) && $ts > strtotime($to . ' 23:59:59')) return false;
    return true;
}

$rows = [];

try {
    if ($kind === 'all' || $kind === 'account') {
        try {
            $stmt = $pdo->prepare("SELECT id, amount, type, description, created_at
                FROM transactions
                WHERE receiver_id = ? OR sender_id = ?
                ORDER BY id DESC
                LIMIT 150");
            $stmt->execute([$user_id, $user_id]);
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                if (!within_range($r['created_at'] ?? '', $from, $to)) continue;
                $type = (string)($r['type'] ?? '');
                $rows[] = [
                    'created_at' => (string)($r['created_at'] ?? ''),
                    'kind' => 'Account',
                    'action' => ucfirst($type),
                    'detail' => (string)($r['description'] ?? ''),
                    'amount' => number_format((float)($r['amount'] ?? 0), 2, '.', ''),
                    'ip' => '',
                    'sort_ts' => strtotime((string)($r['created_at'] ?? '')) ?: 0,
                ];
            }
        } catch (Exception $e) {
        }
    }

    if ($kind === 'all' || $kind === 'game') {
        try {
            $stmt = $pdo->prepare("SELECT action, game_uid, txn_id, amount_delta, request_ip, created_at
                FROM game_callback_events
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT 150");
            $stmt->execute([$user_id]);
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                if (!within_range($r['created_at'] ?? '', $from, $to)) continue;
                $rows[] = [
                    'created_at' => (string)($r['created_at'] ?? ''),
                    'kind' => 'Game',
                    'action' => strtoupper((string)($r['action'] ?? '')),
                    'detail' => game_name_for_uid($r['game_uid'] ?? ''),
                    'amount' => number_format((float)($r['amount_delta'] ?? 0), 2, '.', ''),
                    'ip' => (string)($r['request_ip'] ?? ''),
                    'sort_ts' => strtotime((string)($r['created_at'] ?? '')) ?: 0,
                ];
            }
        } catch (Exception $e) {
        }
    }

    if ($kind === 'all' || $kind === 'profile') {
        try {
            $stmt = $pdo->prepare("SELECT action, entity_type, entity_id, ip, created_at
                FROM audit_logs
                WHERE actor_id = ?
                ORDER BY id DESC
                LIMIT 150");
            $stmt->execute([$user_id]);
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                if (!within_range($r['created_at'] ?? '', $from, $to)) continue;
                $rows[] = [
                    'created_at' => (string)($r['created_at'] ?? ''),
                    'kind' => 'Profile',
                    'action' => (string)($r['action'] ?? ''),
                    'detail' => trim(((string)($r['entity_type'] ?? '')) . ' ' . ((string)($r['entity_id'] ?? ''))),
                    'amount' => '',
                    'ip' => (string)($r['ip'] ?? ''),
                    'sort_ts' => strtotime((string)($r['created_at'] ?? '')) ?: 0,
                ];
            }
        } catch (Exception $e) {
        }
    }

    usort($rows, function ($a, $b) {
        return ((int)($b['sort_ts'] ?? 0)) <=> ((int)($a['sort_ts'] ?? 0));
    });
    $rows = array_slice($rows, 0, $limit);
    foreach ($rows as &$r) {
        unset($r['sort_ts']);
    }
    unset($r);

    out(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Database Error']);
}
