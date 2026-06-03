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

// ─── Get CMS Wager sportsbook URL ────────────────────────────────────────────
$game_url   = '';
$cw_error   = '';
$cw_token   = '';

// Cache in session for 3 hours to avoid re-generating on every page load
$cached = $_SESSION['_cmsw_launch'] ?? null;
if ($cached && !empty($cached['url']) && !empty($cached['ts']) && (time() - $cached['ts']) < 10800) {
    $game_url = $cached['url'];
    $cw_token = $cached['token'] ?? '';
} else {
    $home_url = $base_url . 'sports/sportsbook.php';
    $result   = launchCmsWagerGame($_SESSION['user_id'], $pdo, $home_url);
    if (!empty($result['success']) && !empty($result['game_url'])) {
        $game_url  = $result['game_url'];
        $cw_token  = $result['_session_token'] ?? '';
        $_SESSION['_cmsw_launch'] = ['url' => $game_url, 'token' => $cw_token, 'ts' => time()];
    } else {
        $cw_error = $result['message'] ?? 'Erreur de chargement';
        // Show token for debugging if URL not yet configured
        $cw_token = $result['_token'] ?? '';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
body { overflow: hidden !important; }
.cmsw-wrapper {
  position: fixed;
  top: 50px; /* site navbar height */
  left: 0; right: 0; bottom: 0;
  background: #111;
  display: flex;
  flex-direction: column;
}
.cmsw-iframe {
  flex: 1;
  border: none;
  width: 100%;
  height: 100%;
}
.cmsw-loading {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  flex-direction: column;
  background: #111; color: #fff; gap: 16px;
  font-family: sans-serif;
}
.cmsw-spinner {
  width: 44px; height: 44px;
  border: 4px solid rgba(255,255,255,.15);
  border-top-color: #c0181e;
  border-radius: 50%;
  animation: cmsw-spin .8s linear infinite;
}
@keyframes cmsw-spin { to { transform: rotate(360deg); } }
.cmsw-error {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  flex-direction: column;
  background: #111; color: #fff; gap: 12px; text-align: center;
  padding: 20px; font-family: sans-serif;
}
.cmsw-error h2 { color: #c0181e; margin: 0; font-size: 18px; }
.cmsw-error p  { color: #aaa; font-size: 14px; margin: 0; max-width: 400px; line-height: 1.6; }
.cmsw-error a  { color: #c0181e; }
.cmsw-pending-box {
  background: #1e1e1e; border: 1px solid #333; border-radius: 8px;
  padding: 20px 24px; max-width: 460px; text-align: left;
}
.cmsw-pending-box h3 { color: #f0f0f0; margin: 0 0 10px; font-size: 15px; }
.cmsw-pending-box code {
  display: block; background: #2a2a2a; border-radius: 4px;
  padding: 8px 12px; font-size: 12px; color: #4caf50; word-break: break-all;
  margin: 8px 0;
}
.cmsw-pending-box p { color: #888; font-size: 13px; margin: 4px 0; }
</style>

<div class="cmsw-wrapper" id="cmsw-wrapper">

<?php if ($game_url): ?>
  <!-- ── Sportsbook iframe ────────────────────────────────────────────────── -->
  <div class="cmsw-loading" id="cmsw-loading">
    <div class="cmsw-spinner"></div>
    <span style="font-size:13px;color:#888">Chargement du sportsbook...</span>
  </div>
  <iframe
    class="cmsw-iframe"
    id="cmsw-iframe"
    src="<?= htmlspecialchars($game_url) ?>"
    allowfullscreen
    allow="autoplay; fullscreen"
    style="opacity:0;transition:opacity .3s"
    onload="document.getElementById('cmsw-loading').style.display='none';this.style.opacity='1'">
  </iframe>

<?php elseif ($cw_token): ?>
  <!-- ── Token generated but iframe URL not yet configured ──────────────── -->
  <div class="cmsw-error">
    <h2>⚙️ Configuration en cours</h2>
    <div class="cmsw-pending-box">
      <h3>Session CMS Wager créée avec succès</h3>
      <p>Token joueur :</p>
      <code><?= htmlspecialchars($cw_token) ?></code>
      <p>En attente de l'URL d'intégration de CMS Wager.</p>
      <p>Envoyez à CMS Wager :</p>
      <code>Callback URL: <?= htmlspecialchars($base_url) ?>api/cmswager_wallet.php</code>
      <p style="margin-top:12px;color:#666;font-size:12px">
        Une fois qu'ils fournissent l'URL iframe, mettez à jour<br>
        <code style="color:#4caf50">CMS_WAGER_SB_URL</code> dans <code style="color:#4caf50">cmswager_launch.php</code>
      </p>
    </div>
    <a href="<?= htmlspecialchars($base_url) ?>" style="font-size:13px;margin-top:8px">← Retour à l'accueil</a>
  </div>

<?php else: ?>
  <!-- ── Error ──────────────────────────────────────────────────────────── -->
  <div class="cmsw-error">
    <h2>❌ Erreur Sportsbook</h2>
    <p><?= htmlspecialchars($cw_error ?: 'Impossible de charger le sportsbook.') ?></p>
    <p>Veuillez réessayer ou <a href="<?= htmlspecialchars($base_url) ?>">retourner à l'accueil</a>.</p>
  </div>
<?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
