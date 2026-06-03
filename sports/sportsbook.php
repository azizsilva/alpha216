<?php
// ─── Bootstrap ────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/cmswager_launch.php';

$script    = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$base_path = dirname(dirname($script)) . '/';
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base_url  = $protocol . $_SERVER['HTTP_HOST'] . $base_path;

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url);
    exit;
}

// ─── Get CMS Wager session token ─────────────────────────────────────────────
$cw_token = '';
$cw_error = '';

// Cache session token for 3 hours
$cached = $_SESSION['_cmsw_launch'] ?? null;
if ($cached && !empty($cached['token']) && !empty($cached['ts']) && (time() - $cached['ts']) < 10800) {
    $cw_token = $cached['token'];
} else {
    $result = launchCmsWagerGame($_SESSION['user_id'], $pdo, $base_url . 'sports/sportsbook.php');
    if (!empty($result['_session_token'])) {
        $cw_token = $result['_session_token'];
        $_SESSION['_cmsw_launch'] = ['token' => $cw_token, 'ts' => time()];
    } elseif (!empty($result['_token'])) {
        $cw_token = $result['_token'];
        $_SESSION['_cmsw_launch'] = ['token' => $cw_token, 'ts' => time()];
    } else {
        $cw_error = $result['message'] ?? 'Session error';
    }
}

// Language mapping (your site locale → CMS Wager culture)
$lang = $_SESSION['lang'] ?? 'fr';
$culture_map = ['fr' => 'fr-fr', 'en' => 'en-en', 'ar' => 'ar-ar'];
$culture = $culture_map[$lang] ?? 'fr-fr';

require_once __DIR__ . '/../includes/header.php';
?>
<style>
/* Hide site scrollbar — sportsbook fills full viewport below navbar */
body { overflow: hidden !important; margin: 0; }

#cmsw-wrapper {
  position: fixed;
  top: 50px; /* site navbar height */
  left: 0; right: 0; bottom: 0;
  background: #0f1923;
}

/* The SDK injects its iframe into #appcontent */
#appcontent {
  width: 100%;
  height: 100%;
  position: relative;
}
#appcontent iframe {
  width: 100% !important;
  height: 100% !important;
  min-height: calc(100vh - 50px) !important;
  border: none !important;
  display: block !important;
}

/* Loading overlay — hidden once iframe fires iframeReady */
#cmsw-loading {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 14px;
  background: #0f1923; color: #fff;
  font-family: sans-serif; font-size: 14px;
  pointer-events: none; z-index: 10;
  transition: opacity .4s;
}
#cmsw-loading.hidden { opacity: 0; pointer-events: none; }
.cmsw-spinner {
  width: 40px; height: 40px;
  border: 4px solid rgba(255,255,255,.1);
  border-top-color: #c0181e;
  border-radius: 50%;
  animation: cmsw-spin .75s linear infinite;
}
@keyframes cmsw-spin { to { transform: rotate(360deg); } }

/* Error state */
#cmsw-error {
  display: none;
  position: absolute; inset: 0;
  align-items: center; justify-content: center; flex-direction: column;
  background: #0f1923; color: #fff; gap: 12px;
  font-family: sans-serif; text-align: center; padding: 20px;
}
#cmsw-error h2 { color: #c0181e; margin: 0; }
#cmsw-error p  { color: #aaa; font-size: 13px; max-width: 380px; line-height: 1.6; }
</style>

<div id="cmsw-wrapper">
  <div id="appcontent">
    <div id="cmsw-loading">
      <div class="cmsw-spinner"></div>
      <span style="color:#888">Chargement du sportsbook…</span>
    </div>
    <?php if ($cw_error): ?>
    <div id="cmsw-error" style="display:flex">
      <h2>❌ Erreur</h2>
      <p><?= htmlspecialchars($cw_error) ?></p>
      <p><a href="<?= htmlspecialchars($base_url) ?>" style="color:#c0181e">← Retour à l'accueil</a></p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- CMS Wager Sportsbook SDK -->
<script src="https://testsportsbook.cmswager.com/js/sportsbook.js"></script>
<script>
(function () {
  var token       = <?= json_encode($cw_token ?: 'guest') ?>;
  var culture     = <?= json_encode($culture) ?>;
  var integration = <?= json_encode('alpina216') ?>;
  var platform    = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent) ? 'mobile' : 'desktop';

  if (!token || token === 'guest') {
    document.getElementById('cmsw-loading').style.display = 'none';
    document.getElementById('cmsw-error').style.display   = 'flex';
    return;
  }

  // Hide loading once iframe is ready
  window.addEventListener('message', function (e) {
    if (e.data === 'iframeReady' || (e.data && e.data.action === 'loadDone')) {
      var el = document.getElementById('cmsw-loading');
      if (el) el.classList.add('hidden');
      setTimeout(function () { if (el) el.style.display = 'none'; }, 500);
    }
  });

  // Also hide loading after 6s max (fallback)
  setTimeout(function () {
    var el = document.getElementById('cmsw-loading');
    if (el) { el.classList.add('hidden'); setTimeout(function(){ if(el) el.style.display='none'; }, 500); }
  }, 6000);

  // Launch the sportsbook
  cmsSportbook.startSportbook(platform, token, culture, integration, 'sport');
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
