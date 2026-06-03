<?php
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

// ── Get fresh session token every time (never cache — tokens expire) ─────────
unset($_SESSION['_cmsw_launch']); // always fresh
$result   = launchCmsWagerGame($_SESSION['user_id'], $pdo, $base_url . 'sports/sportsbook.php');
$cw_token = $result['_session_token'] ?? $result['_token'] ?? '';
$cw_error = (!$cw_token) ? ($result['message'] ?? 'Session error') : '';

// ── Build iframe URL directly (no SDK — avoids double-declaration conflict) ───
$culture = 'fr-fr';
$platform = 'mobile'; // server can't detect UA, JS will fix it client-side
$iframe_url = '';
if ($cw_token) {
    $iframe_url = 'https://test1.cmswager.com/?' . http_build_query([
        'language'    => $culture,
        'token'       => $cw_token,
        'integration' => 'doublembet',  // use the account name as integration ID
        'platform'    => $platform,
        'defaultpage' => 'sport',
    ]);
}

// ── Skip full site header — this page is full-screen (no navbar needed) ───────
// Just output a minimal HTML page that fills the viewport
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sportsbook — Alpina 216</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:100%; height:100%; overflow:hidden; background:#0f1923; }
#cmsw-frame {
  position:fixed; inset:0;
  width:100%; height:100%;
  border:none; display:block;
}
#cmsw-loading {
  position:fixed; inset:0;
  display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  gap:16px; background:#0f1923; color:#fff;
  font-family:sans-serif; font-size:14px;
  transition:opacity .4s;
}
#cmsw-loading.done { opacity:0; pointer-events:none; }
.spinner {
  width:44px; height:44px;
  border:4px solid rgba(255,255,255,.1);
  border-top-color:#70f669;
  border-radius:50%;
  animation:spin .75s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }
#cmsw-error {
  position:fixed; inset:0;
  display:none; flex-direction:column;
  align-items:center; justify-content:center;
  gap:14px; background:#0f1923; color:#fff;
  font-family:sans-serif; text-align:center; padding:20px;
}
#cmsw-error h2 { color:#c0181e; font-size:20px; }
#cmsw-error p  { color:#aaa; font-size:14px; max-width:380px; line-height:1.6; }
#cmsw-error a  { color:#70f669; }
</style>
</head>
<body>

<?php if ($iframe_url): ?>

<div id="cmsw-loading">
  <div class="spinner"></div>
  <span style="color:#888">Chargement du sportsbook…</span>
</div>

<iframe
  id="cmsw-frame"
  src="<?= htmlspecialchars($iframe_url) ?>"
  allow="autoplay; fullscreen"
  allowfullscreen
  style="opacity:0;transition:opacity .4s"
  onload="this.style.opacity='1';document.getElementById('cmsw-loading').classList.add('done')">
</iframe>

<script>
// Auto-detect platform and reload with correct value if needed
(function(){
  var isMobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
  var current  = new URLSearchParams(window.location.search).get('_plat');
  // Reload once with correct platform injected into iframe src
  if (!current) {
    var fr = document.getElementById('cmsw-frame');
    if (fr) {
      var src = fr.src;
      src = src.replace('platform=mobile', 'platform=' + (isMobile ? 'mobile' : 'desktop'));
      fr.src = src;
    }
  }
  // Fallback: hide loading after 8s
  setTimeout(function(){
    var el = document.getElementById('cmsw-loading');
    if (el) el.classList.add('done');
  }, 8000);
})();
</script>

<?php else: ?>

<div id="cmsw-error" style="display:flex">
  <h2>❌ Erreur de connexion</h2>
  <p><?= htmlspecialchars($cw_error) ?></p>
  <p><a href="<?= htmlspecialchars($base_url) ?>">← Retour à l'accueil</a></p>
</div>

<?php endif; ?>

</body>
</html>
