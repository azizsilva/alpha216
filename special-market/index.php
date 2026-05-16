<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
session_start();
require_once '../includes/db.php';
require_once '../includes/header.php';
require_once '../api/game_logic.php';

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "' . $base_url . '";</script>';
    exit;
}

$desktop_game_id = '1f7fbf84bf1bcc08c3a7ea27db75f366';
$mobile_game_id = '07baf9e1388d32cd4cee0c0c91b23020';
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
$ch_mobile = isset($_SERVER['HTTP_SEC_CH_UA_MOBILE']) ? (string)$_SERVER['HTTP_SEC_CH_UA_MOBILE'] : '';
$is_mobile = false;
if ($ch_mobile === '?1') {
    $is_mobile = true;
} elseif ($ua !== '') {
    $is_mobile = (bool)preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\\.browser|up\\.link|webos|wos)/i", $ua);
}
$game_id = $is_mobile ? $mobile_game_id : $desktop_game_id;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$home_url = $protocol . $_SERVER['HTTP_HOST'] . '/special-market/';
$launch_result = launchGambllyGame($_SESSION['user_id'], $game_id, $home_url, $pdo, true);

$game_url = '';
$error_msg = '';
if ($launch_result['success']) {
    $game_url = $launch_result['game_url'];
} else {
    $error_msg = $launch_result['message'];
}
?>

<div class="mobile-game-header visible-xs">
  <div class="mgh-left">
    <a href="<?php echo $base_url; ?>" class="mgh-back"><i class="fa fa-chevron-left"></i> <span data-translate="back">BACK</span></a>
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

<div class="game-container">
  <?php if ($game_url): ?>
    <iframe id="gameFrame" src="<?php echo htmlspecialchars($game_url); ?>"></iframe>
  <?php else: ?>
    <div class="mk-game-error">
      <i class="fa fa-exclamation-triangle"></i>
      <p><?php echo htmlspecialchars($error_msg); ?></p>
      <a href="<?php echo $base_url; ?>" class="btn btn-warning" data-translate="go_back">Go Back</a>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  function setVhVar() {
    var h = window.innerHeight;
    if (window.visualViewport && typeof window.visualViewport.height === 'number') h = window.visualViewport.height;
    document.documentElement.style.setProperty('--mk-vh', (h * 0.01) + 'px');
  }
  function refresh() { requestAnimationFrame(setVhVar); setTimeout(setVhVar, 0); setTimeout(setVhVar, 150); }
  window.addEventListener('resize', refresh);
  window.addEventListener('orientationchange', refresh);
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', refresh);
    window.visualViewport.addEventListener('scroll', refresh);
  }
  refresh();
})();
</script>

<style>
:root { --mk-vh: 1vh; }
body { overflow: hidden; }
.game-container {
  position: relative;
  width: 100%;
  background: #000;
  height: calc(100vh - 145px);
}
.game-container iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}
.mk-game-error {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: #000;
  z-index: 11;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-direction: column;
  color: #fff;
  text-align: center;
  padding: 16px;
}
.mk-game-error i { font-size: 40px; color: #ff4444; margin-bottom: 12px; }
.mk-game-error p { margin: 0 0 16px 0; }

@media (max-width: 767px) {
  .top-header, .header-container, .custom-navbar, .secondary-nav-container { display: none !important; }
  body, html { height: 100%; overflow: hidden !important; }
  .mobile-game-header { display: flex !important; position: fixed; top: 0; left: 0; right: 0; z-index: 10050; }
  .game-container {
    position: fixed;
    top: calc(60px + env(safe-area-inset-top));
    left: 0;
    right: 0;
    bottom: 0;
    height: calc((var(--mk-vh) * 100) - 60px - env(safe-area-inset-top));
  }
  .game-container iframe {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 100%;
  }
}

@supports (height: 100dvh) {
  @media (max-width: 767px) {
    .game-container { height: calc(100dvh - 60px - env(safe-area-inset-top)); }
  }
}

.mobile-game-header {
  background: #000;
  height: calc(60px + env(safe-area-inset-top));
  padding-top: env(safe-area-inset-top);
  display: none;
  justify-content: space-between;
  align-items: center;
  padding-left: 12px;
  padding-right: 12px;
  border-bottom: 1px solid #222;
  box-shadow: 0 2px 10px rgba(0,0,0,0.8);
  font-family: 'Roboto', sans-serif;
  width: 100%;
  flex-wrap: nowrap;
  box-sizing: border-box;
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
footer { display: none; }
</style>

<?php require_once '../includes/footer.php'; ?>
