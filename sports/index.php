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
    $tagOk = isset($cached['tag']) && strtolower((string)$cached['tag']) === 'sports';
    if (!$isSports || $isSportsbook || !$tagOk) {
        $cached = null;
    }
}
if (is_array($cached) && !empty($cached['url']) && !empty($cached['ts']) && (time() - (int)$cached['ts']) <= 300) {
    $game_url = (string)$cached['url'];
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

<div class="game-container" style="position: relative; width: 100%; background: #000;">
  <div id="mkSportsLoadingText" style="display:<?php echo $game_url ? 'none' : 'flex'; ?>; position:absolute; inset:0; align-items:center; justify-content:center; background:#000; z-index:10; color:#bbb; font-weight:800;">Loading...</div>
  <iframe id="gameFrame" src="<?php echo $game_url ? htmlspecialchars($game_url) : 'about:blank'; ?>" style="width: 100%; height: 100%; border: none;" allowfullscreen></iframe>
</div>

<?php if ($game_url): ?>
<script>
(function () {
  try {
    var u = new URL("<?php echo htmlspecialchars($game_url); ?>");
    if (!u || !u.origin) return;
    var link = document.createElement('link');
    link.rel = 'preconnect';
    link.href = u.origin;
    link.crossOrigin = 'anonymous';
    document.head.appendChild(link);
  } catch (e) {}
})();
</script>
<?php endif; ?>

<?php if (!$game_url): ?>
<script>
(function () {
  try {
    var iframe = document.getElementById('gameFrame');
    var loading = document.getElementById('mkSportsLoadingText');
    var apiUrl = (typeof SITE_API_URL !== 'undefined') ? SITE_API_URL + 'launch_game.php' : '/api/launch_game.php';
    var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
    var gid = '8a704858d5deb4af1ddc722092ac7614';
    try {
      if (window.MK_GAME_CACHE && window.MK_GAME_CACHE[gid] && window.MK_GAME_CACHE[gid].url) {
        var hit = window.MK_GAME_CACHE[gid];
        var hitTag = String(hit.tag || '').toLowerCase();
        if (hit && hit.url && (Date.now() - (hit.ts || 0)) < 5 * 60 * 1000 && hitTag === 'sports' && !looksSportsbook(hit.url)) {
          try {
            var uHit = new URL(hit.url);
            var linkHit = document.createElement('link');
            linkHit.rel = 'preconnect';
            linkHit.href = uHit.origin;
            linkHit.crossOrigin = 'anonymous';
            document.head.appendChild(linkHit);
          } catch (eH0) {}
          try { iframe.src = hit.url; } catch (eH1) {}
          try { if (loading) loading.style.display = 'none'; } catch (eH2) {}
          return;
        }
        if (hitTag && hitTag !== 'sports') {
          try { delete window.MK_GAME_CACHE[gid]; } catch (eDel) {}
        }
      }
    } catch (eHC) {}

    function looksSportsbook(u) {
      u = String(u || '').toLowerCase();
      return u.indexOf('sportsbook') !== -1 || u.indexOf('saba') !== -1 || u.indexOf('sbo') !== -1;
    }
    function doLaunch(homeUrl) {
      return fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ game_id: gid, home_url: homeUrl, prefetch: 1, skip_log: true }),
        cache: 'no-store'
      }).then(function (r) { return r.ok ? r.json() : null; });
    }
    doLaunch(origin + '/sports/?mk=1').then(function (j) {
      if (j && j.success && j.game_url) {
        if (String(j.tag || '').toLowerCase() !== 'sports') {
          return doLaunch(origin + '/sports/?mk=1&force=1');
        }
        if (looksSportsbook(j.game_url)) {
          return doLaunch(origin + '/sports/?mk=1&force=1').then(function (j2) { return (j2 && j2.success && j2.game_url) ? j2 : j; });
        }
        return j;
      }
      return j;
    }).then(function (j) {
      if (j && j.success && j.game_url) {
        try {
          window.MK_GAME_CACHE = window.MK_GAME_CACHE || {};
          window.MK_GAME_CACHE[gid] = { url: j.game_url, ts: Date.now(), tag: String(j.tag || 'sports') };
        } catch (eC0) {}
        try {
          var u = new URL(j.game_url);
          var link = document.createElement('link');
          link.rel = 'preconnect';
          link.href = u.origin;
          link.crossOrigin = 'anonymous';
          document.head.appendChild(link);
        } catch (e0) {}
        try { iframe.src = j.game_url; } catch (e1) {}
        try { if (loading) loading.style.display = 'none'; } catch (e2) {}
      } else {
        try { if (loading) loading.textContent = 'Failed to load.'; } catch (e3) {}
      }
    }).catch(function () {
      try { if (loading) loading.textContent = 'Failed to load.'; } catch (e4) {}
    });
  } catch (e) {}
})();
</script>
<?php endif; ?>

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
  body { padding-top: 0 !important; overflow: hidden; }
  .game-container {
    position: fixed;
    inset: 0;
    width: 100vw !important;
    height: calc(var(--mk-vh) * 100);
    max-width: none;
    margin: 0;
    padding: 0;
  }
  .game-container iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
  }
  .mobile-game-header { display: none !important; }
  @media (max-width: 767px) {
    .top-header, .header-container, .custom-navbar, .secondary-nav-container, #mobile-footer-nav { display: none !important; }
    body, html { height: 100%; overflow: hidden !important; }
    .game-container {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      height: calc(var(--mk-vh) * 100);
    }
    .game-container iframe {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      width: 100%;
      height: 100%;
    }
    .top-header, .header-container, .custom-navbar, .secondary-nav-container, #mobile-footer-nav { display: none !important; }
  }
  @supports (height: 100dvh) {
    @media (max-width: 767px) {
      .game-container { height: 100dvh; }
    }
  }
  .mobile-game-header { background: #000; height: 60px; display: none !important; justify-content: space-between; align-items: center; padding: 0 12px; border-bottom: 1px solid #222; box-shadow: 0 2px 10px rgba(0,0,0,0.8); font-family: 'Roboto', sans-serif; width: 100%; flex-wrap: nowrap; }
  @media (max-width: 767px) { .mobile-game-header { display: none !important; } }
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
