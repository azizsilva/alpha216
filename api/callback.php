<?php

$_cb_log_dir = null;
$_cb_log_dir_label = 'none';
$_cb_file_logging_ok = false;
$_cb_rid = null;

$_cb_cache_dir = __DIR__ . '/../cache';
if (!is_dir($_cb_cache_dir)) {
    @mkdir($_cb_cache_dir, 0775, true);
}

$_cb_log_dir_candidates = [
    ['cache', $_cb_cache_dir],
    ['api', __DIR__],
    ['tmp', sys_get_temp_dir()]
];
foreach ($_cb_log_dir_candidates as $__cand) {
    $__label = (string)($__cand[0] ?? '');
    $__d = $__cand[1] ?? '';
    if (!is_string($__d) || $__d === '') continue;
    if (!is_dir($__d)) continue;
    if (!is_writable($__d)) continue;
    $_cb_log_dir = rtrim(str_replace('\\', '/', $__d), '/');
    $_cb_log_dir_label = $__label !== '' ? $__label : 'dir';
    break;
}

$_cb_log_file = ($_cb_log_dir ? ($_cb_log_dir . '/error.log') : null);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
if ($_cb_log_file) ini_set('error_log', $_cb_log_file);
error_reporting(E_ALL);

ob_start();

header('Content-Type: application/json');
header('X-MK-CB: 1');

$_cb_started_at = microtime(true);
$_cb_response_sent = false;

function cb_log_line($line) {
    global $_cb_log_file, $_cb_file_logging_ok, $_cb_rid;
    try {
        $ts = date('Y-m-d H:i:s');
        $prefix = $_cb_rid ? ('rid=' . $_cb_rid . ' ') : '';
        if (!$_cb_log_file) {
            @error_log("[$ts] " . $prefix . $line);
            return;
        }
        $ok = @file_put_contents($_cb_log_file, "[$ts] " . $prefix . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($ok !== false) $_cb_file_logging_ok = true;
        if ($ok === false) {
            @error_log("[$ts] LOG_WRITE_FAIL " . $line);
        }
    } catch (Exception $e) {
    }
}

try { $_cb_rid = bin2hex(random_bytes(8)); } catch (Exception $e) { $_cb_rid = (string)mt_rand(); }

cb_log_line('BOOT ' . json_encode([
    'php' => PHP_VERSION,
    'sapi' => php_sapi_name(),
    'log_dir' => $_cb_log_dir,
    'log_file' => $_cb_log_file,
    'cwd' => getcwd()
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

try {
    header('X-MK-CB-LOG-DIR: ' . $_cb_log_dir_label);
} catch (Exception $e) {
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['probe'])) {
    cb_log_line('PROBE qs=' . ($_SERVER['QUERY_STRING'] ?? ''));
    try {
        header('X-MK-CB-LOG: ' . ($_cb_file_logging_ok ? 'ok' : 'fail'));
        header('X-MK-CB-LOG-DIR: ' . $_cb_log_dir_label);
    } catch (Exception $e) {
    }
    echo json_encode(['status' => true, 'balance' => 0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../includes/db.php';

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $types = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED'
    ];
    $t = $types[$errno] ?? ('E_' . (int)$errno);
    $f = str_replace('\\', '/', (string)$errfile);
    cb_log_line('PHP ' . $t . ' ' . $errstr . ' @ ' . $f . ':' . (int)$errline);
    return false;
});

function json_out($payload) {
    global $_cb_response_sent, $_cb_started_at, $_cb_file_logging_ok, $_cb_log_dir_label;
    $_cb_response_sent = true;
    $ms = (int)round((microtime(true) - $_cb_started_at) * 1000);
    try {
        header('X-MK-CB-LOG: ' . ($_cb_file_logging_ok ? 'ok' : 'fail'));
        header('X-MK-CB-LOG-DIR: ' . $_cb_log_dir_label);
    } catch (Exception $e) {
    }
    cb_log_line('RESP ' . $ms . 'ms ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

register_shutdown_function(function () {
    global $_cb_response_sent, $_cb_started_at;
    $err = error_get_last();
    if (!$err) return;
    $fatal = in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    if (!$fatal) return;
    cb_log_line('FATAL ' . json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    if (!$_cb_response_sent) {
        $ms = (int)round((microtime(true) - $_cb_started_at) * 1000);
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Server Error', 'ms' => $ms], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
});

$input = file_get_contents('php://input');
$input_len = is_string($input) ? strlen($input) : 0;
if ($input_len === 0 && empty($_POST) && empty($_GET)) {
    cb_log_line('PING method=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ct=' . ($_SERVER['CONTENT_TYPE'] ?? ''));
    json_out(['status' => true, 'balance' => 0]);
}
if ($input_len > 1024 * 1024) {
    json_out(['status' => false, 'message' => 'Payload too large', 'balance' => 0]);
}
$data = null;
if ($input_len > 0) {
    $data = json_decode($input, true);
}

if (!is_array($data)) {
    if (!empty($_POST) && is_array($_POST)) {
        $data = $_POST;
    } elseif (!empty($_GET) && is_array($_GET)) {
        $data = $_GET;
    } else {
        cb_log_line('REQ invalid_json len=' . $input_len);
        json_out(['status' => false, 'message' => 'Invalid JSON', 'balance' => 0]);
    }
}

try {
    $hdrs = [];
    if (function_exists('getallheaders')) {
        $hdrs = getallheaders();
    } else {
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($k, 5));
                $hdrs[$name] = $v;
            }
        }
    }
    foreach ($hdrs as $hk => $hv) {
        if (preg_match('/(authorization|cookie|token|secret|signature|api[-_]?key)/i', (string)$hk)) {
            $s = (string)$hv;
            if (strlen($s) <= 12) $hdrs[$hk] = '***';
            else $hdrs[$hk] = substr($s, 0, 6) . '...' . substr($s, -4);
        }
    }
    $ctx = [
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'ct' => $_SERVER['CONTENT_TYPE'] ?? '',
        'qs' => $_SERVER['QUERY_STRING'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'len' => $input_len
    ];
    cb_log_line('CTX ' . json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    cb_log_line('HDR ' . json_encode($hdrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    if ($input_len > 0) {
        $raw = $input;
        if (is_string($raw) && strlen($raw) > 20000) $raw = substr($raw, 0, 20000) . '...TRUNCATED';
        cb_log_line('RAW ' . (is_string($raw) ? $raw : ''));
    } else if (!empty($_POST)) {
        $rawPost = json_encode($_POST, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($rawPost) && strlen($rawPost) > 20000) $rawPost = substr($rawPost, 0, 20000) . '...TRUNCATED';
        cb_log_line('RAW_POST ' . $rawPost);
    }
} catch (Exception $e) {
}

if (!isset($data['player_uid']) || !isset($data['action'])) {
    cb_log_line('REQ missing_fields ' . json_encode(['keys' => array_keys($data)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    json_out(['status' => false, 'message' => 'Invalid Request', 'balance' => 0]);
}

$player_uid = (string)$data['player_uid'];
$player_uid = trim($player_uid);
$action = (string)$data['action'];
$bet_amount = isset($data['bet_amount']) ? (float)$data['bet_amount'] : 0.0;
$win_amount = isset($data['win_amount']) ? (float)$data['win_amount'] : 0.0;
$txn_id = isset($data['txn_id']) ? trim((string)$data['txn_id']) : '';
$game_uid = isset($data['game_uid']) ? trim((string)$data['game_uid']) : null;
$game_round = isset($data['game_round']) ? trim((string)$data['game_round']) : null;
$provider_ts = isset($data['timestamp']) ? trim((string)$data['timestamp']) : '';
$provider_dt = null;
if ($provider_ts !== '') {
    $t = strtotime($provider_ts);
    if ($t !== false) $provider_dt = date('Y-m-d H:i:s', $t);
}

$request_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$request_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($player_uid === '') {
    json_out(['status' => false, 'message' => 'Invalid player_uid', 'balance' => 0]);
}

if ($txn_id === '') {
    $txn_id = hash('sha256', $input ?: json_encode($data));
}

cb_log_line('REQ ' . json_encode([
    'player_uid' => $player_uid,
    'action' => $action,
    'bet_amount' => $bet_amount,
    'win_amount' => $win_amount,
    'game_uid' => $game_uid,
    'txn_id' => $txn_id,
    'game_round' => $game_round,
    'timestamp' => $provider_ts,
    'ip' => $request_ip,
    'ua' => $request_ua
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

try {
    $pdo->beginTransaction();

    $user = null;
    if (ctype_digit($player_uid)) {
        $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([(int)$player_uid]);
        $user = $stmt->fetch();
    }
    if (!$user) {
        $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE username = ? FOR UPDATE");
        $stmt->execute([$player_uid]);
        $user = $stmt->fetch();
    }
    if (!$user && preg_match('/(\d{1,18})/', $player_uid, $m)) {
        $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([(int)$m[1]]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        $pdo->rollBack();
        json_out(['status' => false, 'message' => 'User not found', 'balance' => 0]);
    }

    $current_balance = floatval($user['balance']);
    $new_balance = $current_balance;
    $delta = 0.0;

    $act = strtolower(trim((string)$action));
    if (in_array($act, ['balance', 'get_balance', 'check'], true)) {
        $pdo->commit();
        json_out(['status' => true, 'balance' => $current_balance]);
    }
    if (!in_array($act, ['bet', 'win', 'refund'], true)) {
        $pdo->commit();
        json_out(['status' => false, 'message' => 'Invalid action', 'balance' => $current_balance]);
    }
    $action = $act;

    $existing = $pdo->prepare("SELECT result_status, result_message, balance_after, balance_before FROM game_callback_events WHERE txn_id = ? AND action = ? LIMIT 1");
    $existing->execute([$txn_id, $action]);
    $prev = $existing->fetch();
    if ($prev) {
        $pdo->commit();
        $ok = (int)($prev['result_status'] ?? 0) === 1;
        $bal = $ok ? (float)($prev['balance_after'] ?? $current_balance) : (float)($prev['balance_before'] ?? $current_balance);
        $msg = (string)($prev['result_message'] ?? '');
        $payload = ['status' => $ok, 'balance' => $bal];
        if (!$ok) $payload['message'] = $msg !== '' ? $msg : 'Failed';
        json_out($payload);
    }

    $result_status = 1;
    $result_message = null;

    if ($action === 'bet') {
        if ($bet_amount <= 0) {
            $result_status = 0;
            $result_message = 'Invalid bet amount';
        } elseif ($current_balance < $bet_amount) {
            $result_status = 0;
            $result_message = 'Insufficient Balance';
        } else {
            $delta = -1 * $bet_amount;
            $new_balance = $current_balance + $delta;
        }
    } elseif ($action === 'win') {
        if ($win_amount < 0) {
            $result_status = 0;
            $result_message = 'Invalid win amount';
        } else {
            $delta = $win_amount;
            $new_balance = $current_balance + $delta;
        }
    } elseif ($action === 'refund') {
        if ($bet_amount <= 0) {
            $result_status = 0;
            $result_message = 'Invalid refund amount';
        } else {
            $delta = $bet_amount;
            $new_balance = $current_balance + $delta;
        }
    }

    if ($result_status === 1) {
        $update_stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $update_stmt->execute([$new_balance, $user['id']]);
    }

    $ins = $pdo->prepare("INSERT INTO game_callback_events
        (user_id, username, action, game_uid, txn_id, game_round, provider_ts, bet_amount, win_amount, amount_delta, balance_before, balance_after, result_status, result_message, request_ip, request_ua, raw_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([
        (int)$user['id'],
        $player_uid,
        $action,
        $game_uid ?: null,
        $txn_id,
        $game_round ?: null,
        $provider_dt,
        (float)$bet_amount,
        (float)$win_amount,
        (float)$delta,
        (float)$current_balance,
        (float)$new_balance,
        (int)$result_status,
        $result_message,
        $request_ip ?: null,
        $request_ua ?: null,
        $input ?: null
    ]);

    if ($result_status === 1 && $game_round) {
        if ($action === 'bet' && $bet_amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO game_round_exposures (user_id, game_round, amount) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
            $stmt->execute([(int)$user['id'], $game_round, (float)$bet_amount]);
        } elseif ($action === 'win' || $action === 'refund') {
            $stmt = $pdo->prepare("DELETE FROM game_round_exposures WHERE user_id = ? AND game_round = ?");
            $stmt->execute([(int)$user['id'], $game_round]);
        }
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM game_round_exposures WHERE user_id = ?");
        $stmt->execute([(int)$user['id']]);
        $exposure_total = (float)$stmt->fetchColumn();
        $stmt = $pdo->prepare("UPDATE users SET exposure = ? WHERE id = ?");
        $stmt->execute([$exposure_total, (int)$user['id']]);
    }

    $pdo->commit();

    if ($result_status === 1) {
        json_out(['status' => true, 'balance' => $new_balance]);
    }
    json_out(['status' => false, 'message' => $result_message ?: 'Failed', 'balance' => $current_balance]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cb_log_line('EX ' . ($e->getMessage() ?: 'Exception'));
    json_out(['status' => false, 'message' => 'Database Error', 'balance' => 0]);
}
?>
