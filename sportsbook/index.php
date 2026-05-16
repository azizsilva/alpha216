<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
session_start();
require_once '../includes/db.php';
require_once '../api/game_logic.php';
$base_url = '/';

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "/";</script>';
    exit;
}

$game_id = '8a704858d5deb4af1ddc722092ac7614';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$home_url = $protocol . $_SERVER['HTTP_HOST'] . '/sports/?mk=1';
$game_url = '';
$error_msg = '';
$cached = $_SESSION['mk_prefetched_game_urls'][$game_id] ?? null;
if (is_array($cached) && !empty($cached['url']) && !empty($cached['ts']) && (time() - (int)$cached['ts']) <= 300) {
    $ch = (string)($cached['home_url'] ?? '');
    $isSports = $ch !== '' && (bool)preg_match('#/sports(?:/|\\?|$)#i', $ch);
    $isSportsbook = $ch !== '' && (bool)preg_match('#/sportsbook(?:/|\\?|$)#i', $ch);
    $tagOk = !isset($cached['tag']) || strtolower((string)$cached['tag']) === 'sports';
    if (!$isSports || $isSportsbook || !$tagOk) {
        $cached = null;
    }
}
if (is_array($cached) && !empty($cached['url']) && !empty($cached['ts']) && (time() - (int)$cached['ts']) <= 300) {
    $game_url = (string)$cached['url'];
} else {
    $launch_result = launchGambllyGame($_SESSION['user_id'], $game_id, $home_url, $pdo, true);
    if ($launch_result['success']) {
        $game_url = $launch_result['game_url'];
    } else {
        $error_msg = $launch_result['message'];
    }
}

if ($game_url) {
    if (!isset($_SESSION['mk_play_tokens']) || !is_array($_SESSION['mk_play_tokens'])) {
        $_SESSION['mk_play_tokens'] = [];
    }
    $token = bin2hex(random_bytes(16));
    $_SESSION['mk_play_tokens'][$token] = [
        'game_id' => $game_id,
        'game_url' => $game_url,
        'name' => 'Sportsbook',
        'tag' => 'sports',
        'ts' => time(),
        'home_url' => $home_url
    ];
    $_SESSION['mk_current_game_launch'] = [
        'game_id' => $game_id,
        'name' => 'Sportsbook',
        'tag' => 'sports'
    ];
    $_SESSION['game_url'] = $game_url;
    $_SESSION['game_back_url'] = $home_url;
    $_GET['t'] = $token;
    echo '<script>try{history.replaceState({}, "", "/play/?t=' . $token . '");}catch(e){} try{document.body.classList.remove("mk-api-game-fullscreen");}catch(e2){} try{var t=document.getElementById("mobilePageTitle"); if(t){t.setAttribute("data-translate","game_play"); t.innerText="GAME PLAY";}}catch(e3){} </script>';
    require_once __DIR__ . '/../play/index.php';
    exit;
}

require_once '../includes/header.php';

?>

<div class="mobile-game-header visible-xs">
  <div class="mgh-left">
    <a href="<?php echo $base_url; ?>" class="mgh-back"><i class="fa fa-chevron-left"></i> BACK</a>
  </div>
  <div class="mgh-right">
    <a href="#" class="btn btn-sm mgh-deposit" data-translate="deposit">Deposit</a>
    <div class="mgh-balance">
      <div class="bal-line">
        <span class="bal-val-ci" data-live-balance="wallet"><?php echo number_format($_SESSION['coins'], 2); ?></span> 
        <span class="bal-unit">TND</span>
      </div>
      <div class="bal-line">
        <span class="bal-val-exp" data-live-balance="exposure">0.00</span> 
        <span class="bal-unit">EXP</span>
      </div>
    </div>
    <div class="mgh-icons">
      <i class="fa fa-search mgh-icon-search"></i>
      <i class="fa fa-bell-o mgh-icon-bell"></i>
    </div>
  </div>
 </div>

<div class="game-container" style="position: relative; width: 100%; background: #000;">
  <div class="mk-iframe-secondary-nav hidden-xs">
    <div class="mk-iframe-secondary-inner container">
      <ul class="mk-iframe-secondary-list">
        <li><a href="<?php echo $base_url; ?>sports/"><i class="fa fa-futbol-o"></i> <span data-translate="sports">SPORTS</span></a></li>
        <li><a href="<?php echo $base_url; ?>casino-games/"><i class="fa fa-diamond"></i> <span data-translate="ace_casino">ACE CASINO</span></a></li>
        <li><a href="<?php echo $base_url; ?>casino-games/live-casino/"><i class="fa fa-dot-circle-o"></i> <span data-translate="live_casino">LIVE CASINO</span></a></li>
        <li><a href="<?php echo $base_url; ?>sportsbook/"><i class="fa fa-book"></i> <span data-translate="sports_book">SPORTS BOOK</span></a></li>
        <li><a href="<?php echo $base_url; ?>casino-games/virtual-sports/"><i class="fa fa-desktop"></i> <span data-translate="virtual_sports">VIRTUAL SPORTS</span></a></li>
        <li><a href="<?php echo $base_url; ?>casino-games/slot-games/"><i class="fa fa-th"></i> <span data-translate="slot_games">SLOT GAME</span></a></li>
      </ul>
    </div>
  </div>
  <?php if ($game_url): ?>
    <iframe id="gameFrame" src="<?php echo htmlspecialchars($game_url); ?>" style="width: 100%; height: 100%; border: none;"></iframe>
  <?php else: ?>
    <div id="gameError" style="display: flex; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #000; z-index: 11; justify-content: center; align-items: center; flex-direction: column; color: #fff;">
      <i class="fa fa-exclamation-triangle" style="font-size: 40px; color: #ff4444; margin-bottom: 15px;"></i>
      <p id="errorText" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error_msg); ?></p>
      <a href="<?php echo $base_url; ?>" class="btn btn-warning" data-translate="go_back">Go Back</a>
    </div>
  <?php endif; ?>
</div>

<?php if (!$game_url): ?>
<script>
(function () {
  try {
    var gid = '8a704858d5deb4af1ddc722092ac7614';
    if (window.MK_GAME_CACHE && window.MK_GAME_CACHE[gid] && window.MK_GAME_CACHE[gid].url) {
      var hit = window.MK_GAME_CACHE[gid];
      var hitTag = String(hit.tag || '').toLowerCase();
      if (hit && hit.url && (Date.now() - (hit.ts || 0)) < 5 * 60 * 1000 && hitTag === 'sports') {
        var iframe = document.getElementById('gameFrame');
        if (iframe) iframe.src = hit.url;
        var err = document.getElementById('gameError');
        if (err) err.style.display = 'none';
      }
    }
  } catch (e) {}
})();
</script>
<?php endif; ?>

<style>
  body { padding-top: 0 !important; overflow: hidden; }
  .game-container { position: fixed; top: 0; left: 0; right: 0; bottom: 0; height: 100vh; }
  @media (max-width: 767px) {
    .game-container { position: fixed; top: 0; left: 0; right: 0; bottom: 0; height: 100vh; }
    .top-header, .header-container, .custom-navbar, .secondary-nav-container, #mobile-footer-nav { display: none !important; }
  }
  .secondary-nav-container { display: none !important; }
  .mk-iframe-secondary-nav { display: none !important; }
  .game-container iframe { position: absolute; top: -102px; left: 0; right: 0; width: 100%; height: calc(100% + 102px); }
  .mobile-game-header { background: #000; height: 60px; display: none !important; justify-content: space-between; align-items: center; padding: 0 12px; border-bottom: 1px solid #222; box-shadow: 0 2px 10px rgba(0,0,0,0.8); font-family: 'Roboto', sans-serif; width: 100%; flex-wrap: nowrap; }
  @media (max-width: 767px) { .mobile-game-header { display: none !important; } }
  @media (max-width: 767px) {
    .game-container iframe { top: 0; height: 100%; }
  }
  .mgh-left { flex: 0 0 auto; }
  .mgh-back { color: #fff; font-weight: 700; text-transform: uppercase; font-size: 13px; text-decoration: none; display: flex; align-items: center; letter-spacing: 0.5px; }
  .mgh-back i { color: #c37601; font-weight: 900; margin-right: 4px; font-size: 16px; }
  .mgh-right { display: flex; align-items: center; flex-wrap: nowrap; gap: 10px; }
  .mgh-deposit { background: #c37601; color: #fff; border: none; font-weight: 600; padding: 6px 10px; font-size: 12px; border-radius: 4px; text-transform: capitalize; white-space: nowrap; }
  .mgh-deposit:hover { background: #a05f00; color: #fff; }
  .mgh-balance { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2; font-size: 11px; font-weight: 700; min-width: auto; }
  .bal-line { white-space: nowrap; display: flex; align-items: center; gap: 3px; }
  .bal-val-ci { color: #FFD700; font-size: 12px; }
  .bal-val-exp { color: #FF0000; font-size: 11px; }
  .bal-unit { color: #fff; font-size: 10px; opacity: 0.9; }
  .mgh-icons { display: flex; align-items: center; gap: 12px; margin-left: 2px; }
  .mgh-icon-search { color: #fff; font-size: 18px; cursor: pointer; }
  .mgh-icon-bell { color: #c37601; font-size: 18px; cursor: pointer; }
</style>

<?php require_once '../includes/footer.php'; ?>
