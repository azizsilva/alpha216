<?php
// ─── Bootstrap: session + DB + game logic ────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/game_logic.php';

// Detect base URL dynamically (works in any subdirectory)
$script     = str_replace('\\','/',$_SERVER['SCRIPT_NAME']); // /public_html/sports/sportsbook.php
$base_path  = dirname(dirname($script)) . '/';               // /public_html/
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base_url   = $protocol . $_SERVER['HTTP_HOST'] . $base_path;

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_url);
    exit;
}

// ─── Get BtiGaming sportsbook URL (server-side, session guaranteed valid) ──
$game_id  = '6260';
// Always use the whitelisted production domain (never localhost)
$production_domain = '365forzza.shop';
$home_url = 'https://' . $production_domain . '/sports/sportsbook.php';
$game_url = '';

// Clear bad cached URLs that used localhost (one-time cleanup)
$cached = $_SESSION['mk_prefetched_game_urls'][$game_id] ?? null;
if (is_array($cached) && !empty($cached['home_url']) && strpos($cached['home_url'], 'localhost') !== false) {
    // Purge the bad localhost cache entry
    unset($_SESSION['mk_prefetched_game_urls'][$game_id]);
    $cached = null;
}
if (is_array($cached) && !empty($cached['url']) && !empty($cached['ts']) && (time() - (int)$cached['ts']) <= 300) {
    $game_url = (string)$cached['url'];
}

if (!$game_url) {
    $result = launchBtiGame($_SESSION['user_id'], $game_id, $home_url, $pdo, true);
    if (!empty($result['success']) && !empty($result['game_url'])) {
        $game_url = $result['game_url'];
        if (!isset($_SESSION['mk_prefetched_game_urls'])) $_SESSION['mk_prefetched_game_urls'] = [];
        $_SESSION['mk_prefetched_game_urls'][$game_id] = ['url' => $game_url, 'ts' => time(), 'tag' => 'sports', 'home_url' => $home_url];
    }
}

// ─── Now include the existing site header (outputs full <head> + navbar) ──
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Altenar Layout CSS (injected after site header) -->
<style>
body { overflow: hidden !important; }
/* Override body padding so our layout fills the screen below the navbar */
.sp-wrapper {
  display: flex;
  height: calc(100vh - 50px); /* 50px = site navbar height */
  margin-top: 0;
  overflow: hidden;
}

/* ══ LEFT SIDEBAR ══ */
.sp-left {
  width: 245px; min-width: 245px;
  background: #fff;
  border-right: 1px solid #ddd;
  display: flex; flex-direction: column;
  overflow-y: auto; overflow-x: hidden;
}
.sp-left::-webkit-scrollbar { width: 4px; }
.sp-left::-webkit-scrollbar-thumb { background: #ddd; border-radius: 2px; }

.sp-mini-nav { display: flex; border-bottom: 1px solid #eee; flex-shrink: 0; }
.sp-mini-btn {
  flex: 1; padding: 10px 0;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; border-right: 1px solid #eee;
  font-size: 14px; color: #555; text-decoration: none;
}
.sp-mini-btn:last-child { border-right: none; }
.sp-mini-btn:hover { background: #f9f9f9; }
.sp-live-pill {
  background: #c0181e; color: white;
  font-size: 9px; font-weight: 800;
  padding: 3px 6px; border-radius: 3px;
}

.sp-red-head {
  background: #c0181e; color: white;
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  padding: 8px 10px;
  display: flex; justify-content: space-between; align-items: center;
  cursor: pointer; user-select: none; flex-shrink: 0;
}
.sp-rh-right { display: flex; align-items: center; gap: 6px; font-size: 10px; }
.sp-rh-badge { background: rgba(0,0,0,.2); padding: 2px 6px; border-radius: 10px; }

.sp-league-item {
  display: flex; align-items: center; gap: 8px;
  padding: 7px 10px;
  font-size: 12px; font-weight: 600; color: #333;
  cursor: pointer; border-bottom: 1px solid #f5f5f5;
  text-decoration: none; transition: background .15s;
}
.sp-league-item:hover { background: #fef5f5; color: #c0181e; }
.sp-flag { font-size: 14px; line-height: 1; flex-shrink: 0; }
.sp-live-badge {
  margin-left: auto; background: #c0181e; color: white;
  font-size: 8px; font-weight: 800; padding: 2px 5px; border-radius: 2px; flex-shrink: 0;
}

.sp-search {
  display: flex; align-items: center;
  padding: 8px 10px; border-bottom: 1px solid #eee; flex-shrink: 0;
}
.sp-search-ico { color: #bbb; margin-right: 6px; }
.sp-search input {
  flex: 1; border: none; outline: none;
  font-size: 12px; color: #333; background: transparent;
}
.sp-search input::placeholder { color: #bbb; }

.sp-menu-label {
  padding: 7px 10px 3px;
  font-size: 10px; font-weight: 800; color: #aaa; text-transform: uppercase; flex-shrink: 0;
}
.sp-time-filter {
  display: flex; flex-wrap: wrap; gap: 4px;
  padding: 4px 10px 8px; border-bottom: 1px solid #eee; flex-shrink: 0;
}
.sp-tbtn {
  padding: 4px 9px; border: 1px solid #ddd; border-radius: 3px;
  font-size: 11px; font-weight: 600; cursor: pointer;
  background: white; color: #555; transition: all .15s;
}
.sp-tbtn.active, .sp-tbtn:hover { background: #c0181e; color: white; border-color: #c0181e; }

.sp-sport-row {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 10px; font-size: 12px; font-weight: 600; color: #333;
  cursor: pointer; border-bottom: 1px solid #f5f5f5; transition: background .15s;
}
.sp-sport-row:hover { background: #fef5f5; }
.sp-sport-row .si { font-size: 16px; width: 22px; text-align: center; flex-shrink: 0; }
.sp-sport-row .sn { flex: 1; }
.sp-sport-row .sr { display: flex; align-items: center; gap: 4px; }
.sp-sl { background: #c0181e; color: white; font-size: 8px; font-weight: 800; padding: 2px 4px; border-radius: 2px; }
.sp-sc { color: #888; font-size: 11px; }
.sp-chev { color: #ccc; font-size: 11px; }

/* ══ CENTER ══ */
.sp-center { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

.sp-date-bar {
  background: white; border-bottom: 1px solid #ddd;
  display: flex; align-items: stretch; padding: 0 8px; flex-shrink: 0;
}
.sp-dbtn {
  padding: 6px 13px; font-size: 11px; font-weight: 700; color: #888;
  cursor: pointer; display: flex; flex-direction: column; align-items: center;
  border-bottom: 3px solid transparent; transition: all .15s; line-height: 1.3;
}
.sp-dbtn .dn { font-size: 15px; font-weight: 800; color: #333; }
.sp-dbtn.active { color: #c0181e; border-bottom-color: #c0181e; }
.sp-dbtn.active .dn { color: #c0181e; }
.sp-dbtn:hover:not(.active) { color: #333; }

.sp-sports-bar {
  background: #1e1e1e;
  display: flex; align-items: center;
  overflow-x: auto; flex-shrink: 0;
}
.sp-sports-bar::-webkit-scrollbar { height: 3px; }
.sp-sports-bar::-webkit-scrollbar-thumb { background: #444; }
.sp-tab {
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 15px; cursor: pointer;
  color: #888; font-size: 11px; font-weight: 600; white-space: nowrap;
  border-bottom: 3px solid transparent; transition: all .15s; flex-shrink: 0;
}
.sp-tab:hover { color: #fff; }
.sp-tab.active { color: #fff; border-bottom-color: #c0181e; }
.sp-tab .ti { font-size: 20px; margin-bottom: 2px; }

.sp-iframe-area { flex: 1; position: relative; overflow: hidden; }
.sp-iframe-area iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: none; }
.sp-loading {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  background: #f5f5f5; color: #999; font-size: 14px; font-weight: 600;
  gap: 10px;
}
.sp-spinner {
  width: 32px; height: 32px;
  border: 4px solid #eee; border-top-color: #c0181e;
  border-radius: 50%;
  animation: sp-spin .8s linear infinite;
}
@keyframes sp-spin { to { transform: rotate(360deg); } }

/* ══ RIGHT SIDEBAR ══ */
.sp-right {
  width: 280px; min-width: 280px;
  background: white; border-left: 1px solid #ddd;
  display: flex; flex-direction: column; overflow-y: auto;
}
.sp-right::-webkit-scrollbar { width: 4px; }
.sp-right::-webkit-scrollbar-thumb { background: #ddd; }

.sp-rhead {
  background: #c0181e; color: white;
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  padding: 9px 12px; display: flex; justify-content: space-between; align-items: center;
  flex-shrink: 0;
}
.sp-rhead .rhico {
  width: 15px; height: 15px; border: 1px solid rgba(255,255,255,.5);
  border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
  font-size: 9px; cursor: help;
}
.sp-rbody { padding: 10px 12px; border-bottom: 1px solid #eee; }
.sp-inp {
  width: 100%; border: 1px solid #ddd; border-radius: 4px;
  padding: 8px 10px; font-size: 12px; outline: none; color: #333; margin-bottom: 6px;
}
.sp-inp:focus { border-color: #c0181e; }
.sp-cbrow { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #555; }
.sp-cbrow input { accent-color: #c0181e; }
.sp-bcrow { display: flex; gap: 6px; }
.sp-bcrow select {
  border: 1px solid #ddd; border-radius: 4px;
  padding: 8px; font-size: 12px; font-weight: 600;
  outline: none; background: white; color: #333;
}
.sp-bcrow input { flex: 1; border: 1px solid #ddd; border-radius: 4px; padding: 8px; font-size: 12px; outline: none; }
.sp-slip-empty { padding: 20px 12px; text-align: center; border-bottom: 1px solid #eee; }
.sp-slip-ico { font-size: 40px; color: #ccc; margin-bottom: 8px; }
.sp-slip-empty p { color: #999; font-size: 12px; margin-bottom: 10px; line-height: 1.5; }
.sp-pop-item { padding: 10px 12px; border-bottom: 1px solid #f5f5f5; cursor: pointer; }
.sp-pop-item:hover { background: #fef9f9; }
.sp-pop-name { font-size: 12px; font-weight: 600; color: #333; margin-bottom: 3px; }
.sp-pop-meta { font-size: 11px; color: #888; display: flex; gap: 6px; align-items: center; }
.sp-pop-odd { margin-left: auto; font-weight: 700; color: #c0181e; font-size: 12px; }
</style>

<!-- 3-Column Altenar Layout -->
<div class="sp-wrapper">

  <!-- ══ LEFT SIDEBAR ══ -->
  <aside class="sp-left">
    <div class="sp-mini-nav">
      <a href="<?php echo $base_url; ?>" class="sp-mini-btn">🏠</a>
      <div class="sp-mini-btn"><span class="sp-live-pill">EN DIRECT</span></div>
      <div class="sp-mini-btn">📊</div>
    </div>

    <div class="sp-red-head">
      <span>⭐ FAVORIS</span>
      <span class="sp-rh-right">▾</span>
    </div>

    <div class="sp-red-head" style="background:#d01c22;">
      <span>LES MEILLEURES LIG...</span>
      <span class="sp-rh-right">
        <span class="sp-rh-badge">MES LIGUES 0</span>
        <span>—</span>
      </span>
    </div>

    <a class="sp-league-item"><span class="sp-flag">🌐</span> Euro 2028</a>
    <a class="sp-league-item"><span class="sp-flag">🌐</span> Ligue des Champions</a>
    <a class="sp-league-item"><span class="sp-flag">🇦🇷</span> Copa Libertadores</a>
    <a class="sp-league-item"><span class="sp-flag">🏴󠁧󠁢󠁥󠁮󠁧󠁿</span> Premier League</a>
    <a class="sp-league-item"><span class="sp-flag">🇪🇸</span> LaLiga</a>
    <a class="sp-league-item"><span class="sp-flag">🇮🇹</span> Serie A</a>
    <a class="sp-league-item"><span class="sp-flag">🇩🇪</span> Bundesliga <span class="sp-live-badge">EN DIRECT</span></a>
    <a class="sp-league-item"><span class="sp-flag">🇫🇷</span> Ligue 1</a>
    <a class="sp-league-item"><span class="sp-flag">🇫🇷</span> Coupe de France</a>
    <a class="sp-league-item"><span class="sp-flag">🌐</span> Ligue Professionnelle</a>
    <a class="sp-league-item"><span class="sp-flag">🌐</span> Euroligue</a>

    <div class="sp-search">
      <span class="sp-search-ico">🔍</span>
      <input type="text" placeholder="Entrez l'équipe ou le nom du cha...">
    </div>

    <div class="sp-menu-label">MENU</div>
    <div class="sp-time-filter">
      <button class="sp-tbtn active">Tout</button>
      <button class="sp-tbtn">Aujourd'hui</button>
      <button class="sp-tbtn">3h</button>
      <button class="sp-tbtn">6h</button>
      <button class="sp-tbtn">24h</button>
      <button class="sp-tbtn">Demain</button>
    </div>

    <div class="sp-sport-row"><span class="si">⚽</span><span class="sn">Football</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">999+</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🎾</span><span class="sn">Tennis</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">352</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🏀</span><span class="sn">Basketball</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">593</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🏐</span><span class="sn">Volleyball</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">29</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🏒</span><span class="sn">Hockey su...</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">59</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🏓</span><span class="sn">Tennis de...</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">337</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🎮</span><span class="sn">E-sports +</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">213</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">⚽</span><span class="sn">E-Football</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">97</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🏀</span><span class="sn">E-Basketb...</span><span class="sr"><span class="sp-sl">EN DIRECT</span><span class="sp-sc">29</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🚴</span><span class="sn">Cyclisme</span><span class="sr"><span class="sp-sc">14</span><span class="sp-chev">›</span></span></div>
    <div class="sp-sport-row"><span class="si">🤖</span><span class="sn">Football AI</span><span class="sr"><span class="sp-sc">13</span><span class="sp-chev">›</span></span></div>
  </aside>

  <!-- ══ CENTER ══ -->
  <section class="sp-center">
    <div class="sp-date-bar">
      <div class="sp-dbtn active">Aujourd'hui <span class="dn">21</span></div>
      <div class="sp-dbtn">Ven <span class="dn">22</span></div>
      <div class="sp-dbtn">Sam <span class="dn">23</span></div>
      <div class="sp-dbtn">Dim <span class="dn">24</span></div>
      <div class="sp-dbtn">Lun <span class="dn">25</span></div>
      <div class="sp-dbtn">Mar <span class="dn">26</span></div>
      <div class="sp-dbtn">Mer <span class="dn">27</span></div>
    </div>

    <div class="sp-sports-bar">
      <div class="sp-tab active"><span class="ti">▶</span>En direct</div>
      <div class="sp-tab"><span class="ti">⚽</span>Football</div>
      <div class="sp-tab"><span class="ti">🎾</span>Tennis</div>
      <div class="sp-tab"><span class="ti">🏀</span>Basket...</div>
      <div class="sp-tab"><span class="ti">🏐</span>Volleyb...</div>
      <div class="sp-tab"><span class="ti">🏒</span>Hocke...</div>
      <div class="sp-tab"><span class="ti">🏆</span>Tous le...</div>
    </div>

    <div class="sp-iframe-area">
      <?php if ($game_url): ?>
        <iframe src="<?php echo htmlspecialchars($game_url); ?>" allowfullscreen></iframe>
      <?php else: ?>
        <div class="sp-loading" id="sp-loading">
          <div class="sp-spinner"></div> Chargement du sportsbook...
        </div>
        <script>
        (function(){
          var apiPath = window.location.pathname.split('/sports/')[0] + '/api/launch_game.php';
          fetch(apiPath, {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({game_id:'6260', provider:'bti', home_url:'https://365forzza.shop/sports/sportsbook.php', prefetch:1, skip_log:true})
          }).then(function(r){ return r.json(); }).then(function(j){
            var area = document.querySelector('.sp-iframe-area');
            var load = document.getElementById('sp-loading');
            if(j && j.success && j.game_url){
              var fr = document.createElement('iframe');
              fr.src = j.game_url;
              fr.allowFullscreen = true;
              fr.style.cssText='position:absolute;inset:0;width:100%;height:100%;border:none;';
              if(load) load.remove();
              area.appendChild(fr);
            } else {
              if(load) load.innerHTML='❌ Erreur. <a href="<?php echo $base_url; ?>" style="color:#c0181e">Retour</a>';
            }
          }).catch(function(){
            var load = document.getElementById('sp-loading');
            if(load) load.innerHTML='❌ Erreur réseau. <a href="<?php echo $base_url; ?>" style="color:#c0181e">Retour</a>';
          });
        })();
        </script>
      <?php endif; ?>
    </div>
  </section>

  <!-- ══ RIGHT SIDEBAR ══ -->
  <aside class="sp-right">
    <div class="sp-rhead">CODE RAPIDE <span class="rhico">?</span></div>
    <div class="sp-rbody">
      <input type="text" class="sp-inp" placeholder="Entrez le code rapide">
      <div class="sp-cbrow">
        <input type="checkbox" id="sp-rapid">
        <label for="sp-rapid">Utilisez le mode rapide</label>
      </div>
    </div>

    <div class="sp-rhead">RECHERCHER DES PARIS</div>
    <div class="sp-rbody">
      <div class="sp-bcrow">
        <select><option>Bet Code</option></select>
        <input type="text" placeholder="Entrez le numéro d...">
      </div>
    </div>

    <div class="sp-rhead">FICHE DE PARI <span>—</span></div>
    <div class="sp-slip-empty">
      <div class="sp-slip-ico">📋</div>
      <p>Pas de sélections sur la fiche de pari</p>
      <input type="text" class="sp-inp" placeholder="Entrez le numéro de rés...">
    </div>

    <div class="sp-rhead">PARIS POPULAIRES</div>
    <div class="sp-pop-item">
      <div class="sp-pop-name">Atletico Mineiro vs. Cienciano</div>
      <div class="sp-pop-meta">1x2 <span class="sp-pop-odd">1.20</span></div>
    </div>
    <div class="sp-pop-item">
      <div class="sp-pop-name">Liverpool FC vs. Brentford</div>
      <div class="sp-pop-meta">1x2 <span class="sp-pop-odd">1.83</span></div>
    </div>
    <div class="sp-pop-item">
      <div class="sp-pop-name">Real Madrid vs. Athletic Club</div>
      <div class="sp-pop-meta">1x2 <span class="sp-pop-odd">1.48</span></div>
    </div>
    <div class="sp-pop-item">
      <div class="sp-pop-name">Vérone vs. AS Roma</div>
      <div class="sp-pop-meta">1x2 <span class="sp-pop-odd">10.00</span></div>
    </div>
  </aside>

</div>

<script>
document.querySelectorAll('.sp-tbtn').forEach(function(b){
  b.addEventListener('click',function(){
    document.querySelectorAll('.sp-tbtn').forEach(function(x){x.classList.remove('active');});
    b.classList.add('active');
  });
});
document.querySelectorAll('.sp-dbtn').forEach(function(b){
  b.addEventListener('click',function(){
    document.querySelectorAll('.sp-dbtn').forEach(function(x){x.classList.remove('active');});
    b.classList.add('active');
  });
});
document.querySelectorAll('.sp-tab').forEach(function(b){
  b.addEventListener('click',function(){
    document.querySelectorAll('.sp-tab').forEach(function(x){x.classList.remove('active');});
    b.classList.add('active');
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
