<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once 'game_logic.php'; // Use centralized logic

// Log errors to file, but never break JSON output with PHP warnings/notices
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to play.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $b = $stmt->fetchColumn();
    if ($b !== false) $_SESSION['coins'] = (float)$b;
} catch (Exception $e) {
}

// Get Input
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);
$game_id = $data['game_id'] ?? '';
$game_name = trim((string)($data['game_name'] ?? ''));
$prefetch = !empty($data['prefetch']);
$skip_log = $prefetch ? true : (!empty($data['skip_log']));

// Determine Home URL dynamically
// 1. Prefer client-sent 'home_url' (from window.location.href)
// 2. Fallback to HTTP_REFERER if available
// 3. Fallback to SERVER_NAME construction

$home_url = '';

if (!empty($data['home_url'])) {
    $home_url = $data['home_url'];
    // Ensure we store this for the back button logic later
    $_SESSION['game_back_url'] = $home_url;
} elseif (isset($_SERVER['HTTP_REFERER'])) {
    // Use the page user came from
    $home_url = $_SERVER['HTTP_REFERER'];
    $_SESSION['game_back_url'] = $home_url;
} else {
    // Construct from Server
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    $home_url = $protocol . $domainName . '/';
    $_SESSION['game_back_url'] = $home_url;
}

// Final safety fallback if detection failed completely
if (empty($home_url)) {
    // Just use root relative path if we really can't detect anything
    $home_url = '/';
}

if (empty($game_id)) {
    echo json_encode(['success' => false, 'message' => 'Game ID is required.']);
    exit;
}

// Call Centralized Launch Function
$provider = $data['provider'] ?? '';
if (empty($provider) && $game_id === '6260') {
    $provider = 'bti';
} elseif (empty($provider)) {
    $provider = 'gamblly';
}

if ($provider === 'cmswager' || $provider === 'sportsbook') {
    require_once __DIR__ . '/cmswager_launch.php';
    $result = launchCmsWagerGame($_SESSION['user_id'], $pdo, $home_url);
} elseif ($provider === 'bti') {
    $result = launchBtiGame($_SESSION['user_id'], $game_id, $home_url, $pdo, $skip_log);
} else {
    $result = launchGambllyGame($_SESSION['user_id'], $game_id, $home_url, $pdo, $skip_log);
}

if ($result['success']) {
    $tag = 'other';
    $hl = strtolower((string)$home_url);
    if (strpos($hl, '/sportsbook') !== false) $tag = 'sportsbook';
    elseif (strpos($hl, '/sports') !== false) $tag = 'sports';
    $_SESSION['mk_current_game_launch'] = [
        'game_id' => $game_id,
        'name' => $game_name,
        'home_url' => $home_url,
        'tag' => $tag,
        'ts' => time(),
    ];
    if (!$prefetch) {
        $_SESSION['game_url'] = $result['game_url'];
    }
    if (!isset($_SESSION['mk_prefetched_game_urls']) || !is_array($_SESSION['mk_prefetched_game_urls'])) {
        $_SESSION['mk_prefetched_game_urls'] = [];
    }
    $_SESSION['mk_prefetched_game_urls'][$game_id] = [
        'url' => $result['game_url'],
        'home_url' => $home_url,
        'tag' => $tag,
        'ts' => time(),
    ];
    
    if ($prefetch || $skip_log) {
        echo json_encode(['success' => true, 'game_url' => $result['game_url'], 'tag' => $tag]);
    } else {
        if (!isset($_SESSION['mk_play_tokens']) || !is_array($_SESSION['mk_play_tokens'])) {
            $_SESSION['mk_play_tokens'] = [];
        }
        $now = time();
        foreach ($_SESSION['mk_play_tokens'] as $k => $v) {
            if (!is_array($v) || empty($v['ts']) || ($now - (int)$v['ts']) > 21600) {
                unset($_SESSION['mk_play_tokens'][$k]);
            }
        }

        $token = bin2hex(random_bytes(8));
        $safe_name = $game_name !== '' ? $game_name : 'Game';
        $slug = strtolower($safe_name);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') $slug = strtolower($game_id);

        $_SESSION['mk_play_tokens'][$token] = [
            'game_id' => $game_id,
            'game_url' => $result['game_url'],
            'name' => $safe_name,
            'home_url' => $home_url,
            'tag' => $tag,
            'ts' => $now,
        ];

        echo json_encode([
            'success' => true,
            'redirect_url' => 'play/?t=' . $token . '&g=' . $slug,
            'token' => $token,
            'game_name' => $safe_name,
            'game_url' => $result['game_url'],
            'tag' => $tag
        ]);
    }
} else {
    echo json_encode($result);
}
?>
