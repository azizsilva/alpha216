<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) { require_once __DIR__.'/../app/index.php'; exit; }
// Session-only check — no full db.php needed here (all DB ops are via AJAX to api.php)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { echo '<script>window.location.href="/";</script>'; exit; }
$base = '/';
$script_name = $_SERVER['SCRIPT_NAME'];
if (($pos = strpos($script_name, '/sportsbook/')) !== false) {
    $base = substr($script_name, 0, $pos + 1);
}

// Dynamic dates
$fr_days = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
$dates = [];
for($i=0;$i<7;$i++){
  $ts = strtotime("+$i days");
  $dow = (int)date('w',$ts);
  $dates[] = ['day'=>$i===0?"Aujourd'hui":$fr_days[$dow], 'num'=>date('j',$ts)];
}
?>
<!-- Fonts: preconnect + swap for zero render blocking -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" media="print" onload="this.media='all'">
<noscript>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</noscript>
<link rel="stylesheet" href="<?=$base?>sportsbook/style.css?v=<?=time()?>" id="sb-css-link">
<meta http-equiv="cache-control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="0">

<style id="sb-critical-mobile">
@media (max-width:1100px){
  /* ── MOBILE TOP BAR: FLEX layout — active tab expands (matches fcbet216 exactly) ── */
  .sb-mobile-topbar{
    display:flex!important;
    gap:8px!important;
    align-items:stretch!important;
    padding:8px 10px!important;
    position:sticky!important;
    top:0!important;
    z-index:200!important;
    background:#101010!important;
    border-bottom:1px solid #2a2a2a!important;
  }
  /* All 3 buttons — default: 48px dark gray compact */
  .sb-mobile-topbar .sb-btn-home,
  .sb-mobile-topbar .sb-btn-live,
  .sb-mobile-topbar .sb-btn-stats{
    flex:none!important;width:48px!important;min-width:48px!important;
    height:40px!important;display:flex!important;align-items:center!important;justify-content:center!important;
    border:none!important;border-radius:8px!important;cursor:pointer!important;
    background:#252525!important;color:#979797!important;
    font-family:'Poppins',sans-serif!important;outline:none!important;
  }
  /* ACTIVE button — expands (flex:1), turns GREEN */
  .sb-mobile-topbar .sb-btn-home.active,
  .sb-mobile-topbar .sb-btn-live.active{
    flex:1!important;width:auto!important;min-width:0!important;
    background:#70f669!important;color:rgba(0,0,0,0.87)!important;
  }
  /* EN DIRECT red badge (shown when button is NOT active) */
  .sb-mobile-topbar .sb-btn-live:not(.active) .sb-live-badge{
    background:#e02424!important;color:#fff!important;
    font-size:9px!important;font-weight:800!important;letter-spacing:0.8px!important;
    text-transform:uppercase!important;padding:3px 7px!important;border-radius:4px!important;
    white-space:nowrap!important;
  }
  /* EN DIRECT badge when active — plain text on green bg */
  .sb-mobile-topbar .sb-btn-live.active .sb-live-badge{
    background:transparent!important;color:rgba(0,0,0,0.87)!important;
    font-size:11px!important;font-weight:800!important;letter-spacing:1px!important;
    text-transform:uppercase!important;padding:0!important;border-radius:0!important;
  }
  /* SCROLL FIX */
  body.mk-game-no-chrome #mkApp{overflow-y:auto!important;overflow-x:hidden!important;-webkit-overflow-scrolling:touch!important;}
  .sb-root{height:auto!important;min-height:100%!important;overflow:visible!important;}
  .sb-center{width:100%;height:auto;overflow:visible;}
  .sb-center-scroll{flex:initial!important;overflow:visible!important;height:auto!important;padding:10px 10px 80px!important;}
  /* FOOTER */
  .sb-mob-footer{display:flex!important;}
  /* SIDEBAR CONTENT */
  .sb-mobile-sidebar-content{display:flex!important;}
  /* OFF-CANVAS SIDEBARS */
  .sb-left,.sb-right{position:fixed!important;top:0!important;bottom:0!important;z-index:2000!important;}
  .sb-left{left:0!important;transform:translateX(-100%)!important;width:280px!important;}
  .sb-left.open{transform:translateX(0)!important;box-shadow:10px 0 40px rgba(0,0,0,0.9)!important;}
  .sb-right{right:0!important;transform:translateX(100%)!important;width:320px!important;}
  .sb-right.open{transform:translateX(0)!important;box-shadow:-10px 0 40px rgba(0,0,0,0.9)!important;}
}
</style>

<div class="sb-root">

<!-- ══ LEFT SIDEBAR ══ -->
<aside class="sb-left" id="sb-left">
  <div class="sb-sidebar-top">
    <div class="sb-top-bar">
      <button class="sb-btn-home active" onclick="window.sbSwitchTab(this,'inplay',1)"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 14.6666V7.99992H10V14.6666M2 5.99992L8 1.33325L14 5.99992V13.3333C14 13.6869 13.8595 14.026 13.6095 14.2761C13.3594 14.5261 13.0203 14.6666 12.6667 14.6666H3.33333C2.97971 14.6666 2.64057 14.5261 2.39052 14.2761C2.14048 14.026 2 13.6869 2 13.3333V5.99992Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
      <button class="sb-btn-live">EN DIRECT</button>
      <button class="sb-btn-stats"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 13V6H6V13V3H10V13V8H14V13H2Z" stroke="currentColor" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    </div>
    
    <div class="sb-section-toggle sb-favoris-toggle" onclick="window.sbToggleFavs()">
      <div class="sb-toggle-left">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" style="flex-shrink:0"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 0.667C8.254 0.667 8.485 0.811 8.598 1.038L10.503 4.898L14.763 5.52C15.014 5.557 15.223 5.733 15.301 5.974C15.379 6.216 15.314 6.481 15.132 6.658L12.05 9.66L12.777 13.901C12.82 14.151 12.717 14.404 12.512 14.553C12.307 14.702 12.034 14.722 11.81 14.603L8 12.6L4.19 14.603C3.966 14.722 3.693 14.702 3.488 14.553C3.283 14.404 3.18 14.151 3.223 13.901L3.95 9.66L0.868 6.658C0.686 6.481 0.621 6.216 0.699 5.974C0.777 5.733 0.986 5.557 1.237 5.52L5.497 4.898L7.402 1.038C7.514 0.811 7.746 0.667 8 0.667Z" stroke="currentColor" stroke-width="1.2" fill="none"/></svg>
        <span>Favoris</span>
      </div>
      <i class="fa fa-chevron-up" id="sb-favs-chevron"></i>
    </div>
  </div>

  <div class="sb-sidebar-scroll">
    <!-- Collapsible favorites content -->
    <div id="sb-favs-content">
    <div class="sb-league-group-hdr">
      <span class="hdr-title">LES MEILLEURES LIG...</span>
      <div class="sb-mes-ligues">MES LIG... <span>0</span></div>
    </div>

    <div class="sb-tl-list">
      <?php
      $top_leagues = [
        ['id'=>'94',  'name'=>'Coupe du Monde 2026',          'flag'=>'un',     'sport'=>1],
        ['id'=>'572', 'name'=>'Ligue des Champions',           'flag'=>'eu',     'sport'=>1],
        ['id'=>'573', 'name'=>'Ligue Conférence',              'flag'=>'eu',     'sport'=>1],
        ['id'=>'17',  'name'=>'Premier League',                'flag'=>'gb-eng', 'sport'=>1],
        ['id'=>'119', 'name'=>'LaLiga',                        'flag'=>'es',     'sport'=>1],
        ['id'=>'167', 'name'=>'Serie A',                       'flag'=>'it',     'sport'=>1],
        ['id'=>'78',  'name'=>'Bundesliga',                    'flag'=>'de',     'sport'=>1],
        ['id'=>'168', 'name'=>'Ligue 1',                       'flag'=>'fr',     'sport'=>1],
        ['id'=>'320', 'name'=>'Eredivisie',                    'flag'=>'nl',     'sport'=>1],
        ['id'=>'142', 'name'=>'Division 1A',                   'flag'=>'be',     'sport'=>1],
        ['id'=>'599', 'name'=>'Copa Libertadores',             'flag'=>'un',     'sport'=>1],
        ['id'=>'600', 'name'=>'Copa Sudamericana',             'flag'=>'un',     'sport'=>1],
        ['id'=>'el',  'name'=>'Euroligue',                     'flag'=>'eu',     'sport'=>18],
        ['id'=>'nba', 'name'=>'NBA',                           'flag'=>'us',     'sport'=>18],
        ['id'=>'rg1', 'name'=>'Roland Garros, Féminin Simple', 'flag'=>'fr',     'sport'=>13],
        ['id'=>'rg2', 'name'=>'Roland Garros, Hommes Simple',  'flag'=>'fr',     'sport'=>13],
      ];
      foreach($top_leagues as $l): ?>
      <div class="sb-tl-item" onclick="window.sbOpenLeague('<?=$l['id']?>','<?=$l['name']?>','https://flagcdn.com/w20/<?=$l['flag']?>.png',<?=$l['sport']?>)">
        <img src="https://flagcdn.com/w20/<?=$l['flag']?>.png" class="sb-flag-icon">
        <span class="sb-league-name"><?=$l['name']?></span>
      </div>
      <?php endforeach; ?>
    </div>

    </div><!-- /sb-favs-content -->

    <div class="sb-search-wrap">
      <div class="sb-search-box">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:var(--sb-text-2);flex-shrink:0"><path d="M14 14L11.1 11.1M7.33 2C5.92 2 4.56 2.56 3.56 3.56C2.56 4.56 2 5.92 2 7.33C2 8.74 2.56 10.1 3.56 11.1C4.56 12.1 5.92 12.67 7.33 12.67C8.74 12.67 10.1 12.1 11.1 11.1C12.1 10.1 12.67 8.74 12.67 7.33C12.67 5.92 12.1 4.56 11.1 3.56C10.1 2.56 8.74 2 7.33 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <input type="text" id="sb-search-input" class="sb-sidebar-search" placeholder="Entrez l'équipe ou le nom du cha..." oninput="window.sbSearchMatches(this.value)">
      </div>
    </div>

    <div class="sb-menu-label">Menu</div>
    <div class="sb-time-filters">
      <button class="sb-tf active" onclick="window.sbTimeFilter(this,'all')">Tout</button>
      <button class="sb-tf" onclick="window.sbTimeFilter(this,'today')">Aujourd'hui</button>
      <button class="sb-tf" onclick="window.sbTimeFilter(this,'3h')">3h</button>
      <button class="sb-tf" onclick="window.sbTimeFilter(this,'6h')">6h</button>
      <button class="sb-tf" onclick="window.sbTimeFilter(this,'24h')">24h</button>
      <button class="sb-tf-arrow"><i class="fa fa-chevron-right"></i></button>
    </div>

    <div id="sb-sports-list">
      <!-- Skeleton until app.js renders -->
      <div class="sb-sk-sports-list" id="sb-sports-skeleton">
        <?php for($s=0;$s<7;$s++): ?>
        <div class="sb-sk-sport-row">
          <div class="sb-sk-block" style="width:20px;height:20px;border-radius:50%"></div>
          <div class="sb-sk-block" style="width:100px;height:11px;margin-left:10px"></div>
          <div class="sb-sk-block" style="width:36px;height:18px;border-radius:9px;margin-left:auto"></div>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Format de cotes — functional odds format picker -->
    <div class="sb-cotes-section">
      <div class="sb-cotes-label">Format de cotes</div>
      <div class="sb-cotes-opts" id="sb-cotes-opts">
        <div class="sb-cotes-opt" data-fmt="dec" onclick="window.sbSetOddsFormat('dec',this)">
          <span>Décimal (2.00)</span><span class="sb-cotes-check">—</span>
        </div>
        <div class="sb-cotes-opt" data-fmt="amer" onclick="window.sbSetOddsFormat('amer',this)">
          <span>Américain (+100)</span>
        </div>
        <div class="sb-cotes-opt active" data-fmt="frac" onclick="window.sbSetOddsFormat('frac',this)">
          <span>Fractionnaire (1/1)</span>
        </div>
      </div>
    </div>
  </div>
</aside>

<!-- ══ CENTER PANEL ══ -->
<section class="sb-center">
  <!-- Mobile-only top bar (hidden on desktop) -->
  <div class="sb-mobile-topbar">
    <button class="sb-btn-home active" onclick="window.sbSwitchTab(this,'home',1)"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 14.6666V7.99992H10V14.6666M2 5.99992L8 1.33325L14 5.99992V13.3333C14 13.6869 13.8595 14.026 13.6095 14.2761C13.3594 14.5261 13.0203 14.6666 12.6667 14.6666H3.33333C2.97971 14.6666 2.64057 14.5261 2.39052 14.2761C2.14048 14.026 2 13.6869 2 13.3333V5.99992Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    <button class="sb-btn-live" onclick="window.sbSwitchTab(this,'live',1)"><span class="sb-live-badge">EN DIRECT</span></button>
    <button class="sb-btn-stats"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 13V6H6V13V3H10V13V8H14V13H2Z" stroke="currentColor" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
  </div>

  <div class="sb-center-header">
    <!-- Date selector — use div not button to avoid Bootstrap overrides -->
    <div class="sb-date-row">
      <?php foreach($dates as $i=>$d): ?>
      <div class="sb-date-item<?=$i===0?' active':''?>" onclick="window.sbFilterByDate(this, <?=$i?>)" role="button" tabindex="0">
        <span class="sb-date-lbl"><?=htmlspecialchars($d['day'])?></span>
        <span class="sb-date-num"><?=$d['num']?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Favoris row — between date bar and sport tabs (matches fcbet216) -->
    <div class="sb-favoris-row" onclick="window.sbToggleFavs()" id="sb-favoris-row">
      <div class="sb-fav-left">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 0.667C8.254 0.667 8.485 0.811 8.598 1.038L10.503 4.898L14.763 5.52C15.014 5.557 15.223 5.733 15.301 5.974C15.379 6.216 15.314 6.481 15.132 6.658L12.05 9.66L12.777 13.901C12.82 14.151 12.717 14.404 12.512 14.553C12.307 14.702 12.034 14.722 11.81 14.603L8 12.6L4.19 14.603C3.966 14.722 3.693 14.702 3.488 14.553C3.283 14.404 3.18 14.151 3.223 13.901L3.95 9.66L0.868 6.658C0.686 6.481 0.621 6.216 0.699 5.974C0.777 5.733 0.986 5.557 1.237 5.52L5.497 4.898L7.402 1.038C7.514 0.811 7.746 0.667 8 0.667Z" stroke="currentColor" stroke-width="1.2" fill="none"/></svg>
        <span>Favoris</span>
      </div>
      <i class="fa fa-chevron-down" id="sb-favoris-chevron"></i>
    </div>
    <div class="sb-favoris-content" id="sb-favoris-content" style="display:none">
      <div class="sb-no-favs"><span>Aucun favori ajouté</span></div>
    </div>

    <!-- Sport navigation — matches fcbet216 exactly -->
    <div class="sb-sport-nav">
      <!-- Streaming button with LIVE badge -->
      <button class="sb-sport-item sb-nav-streaming" onclick="window.sbSwitchTab(this,'inplay',0)">
        <div class="sb-sport-icon" style="position:relative">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="3" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <span class="sb-nav-live-dot"></span>
        </div>
        <span class="sb-sport-lbl">Streami...</span>
      </button>
      <!-- En direct -->
      <button class="sb-sport-item sb-nav-endirect" onclick="window.sbSwitchLive(this)">
        <div class="sb-sport-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><polygon points="10,8 17,12 10,16" fill="currentColor"/></svg>
        </div>
        <span class="sb-sport-lbl">En direct</span>
      </button>
      <!-- Dynamic sport tabs -->
      <div class="sb-sport-scroll" id="sb-sport-nav-list">
        <div class="sb-skeleton-circ"></div>
        <div class="sb-skeleton-circ"></div>
        <div class="sb-skeleton-circ"></div>
      </div>
      <!-- Tous les marchés -->
      <button class="sb-sport-item sb-nav-all" onclick="window.sbSwitchTab(this,'upcoming',1)">
        <div class="sb-sport-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" stroke="currentColor" stroke-width="1.5"/><path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <span class="sb-sport-lbl">Tous les...</span>
      </button>
      <button class="sb-nav-arrow" id="sb-nav-arrow-btn" onclick="window.sbScrollNav()"><i class="fa fa-chevron-right"></i></button>
    </div>
  </div>

  <div class="sb-center-scroll">
    <!-- EN DIRECT live match cards (horizontal scroll, above Cotes boostées) -->
    <div class="sb-en-direct-row" id="sb-en-direct-cards">
      <!-- Skeleton until renderEnDirectCards() runs -->
      <div class="sb-sk-boost-card"></div>
      <div class="sb-sk-boost-card"></div>
      <div class="sb-sk-boost-card"></div>
    </div>



    <!-- Mobile-only: Search + Leagues inline (inside scroll so they scroll with matches) -->
    <div class="sb-mobile-sidebar-content">
      <div class="sb-search-wrap">
        <div class="sb-search-box">
          <svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="color:var(--sb-text-2);flex-shrink:0"><path d="M14 14L11.1 11.1M7.33 2C5.92 2 4.56 2.56 3.56 3.56C2.56 4.56 2 5.92 2 7.33C2 8.74 2.56 10.1 3.56 11.1C4.56 12.1 5.92 12.67 7.33 12.67C8.74 12.67 10.1 12.1 11.1 11.1C12.1 10.1 12.67 8.74 12.67 7.33C12.67 5.92 12.1 4.56 11.1 3.56C10.1 2.56 8.74 2 7.33 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <input type="text" class="sb-sidebar-search" placeholder="Entrez l'équipe ou le nom du championnat" oninput="window.sbSearchMatches(this.value)">
        </div>
      </div>
      <!-- LES MEILLEURES LIGUES / MES LIGUES tabs -->
      <div class="sb-mob-league-tabs">
        <div class="sb-league-group-hdr">
          <span class="hdr-title sb-mob-tab active" onclick="window.sbMobLeagueTab(this,'best')">LES MEILLEURES LIGUES</span>
          <span class="sb-mob-tab" onclick="window.sbMobLeagueTab(this,'my')"><strong>MES LIGUES</strong> <span class="sb-badge-green">0</span></span>
          <span class="sb-mob-collapse" onclick="this.closest('.sb-mob-league-tabs').classList.toggle('collapsed')">—</span>
        </div>
        <div class="sb-mob-best-leagues" id="sb-mob-best-leagues">
          <div class="sb-tl-list">
            <?php foreach($top_leagues as $l): ?>
            <div class="sb-tl-item" onclick="window.sbOpenLeague('<?=$l['id']?>','<?=$l['name']?>','https://flagcdn.com/w20/<?=$l['flag']?>.png',<?=$l['sport']?>)">
              <img src="https://flagcdn.com/w20/<?=$l['flag']?>.png" class="sb-flag-icon">
              <span class="sb-league-name"><?=$l['name']?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="sb-mob-my-leagues" id="sb-mob-my-leagues" style="display:none">
          <div class="sb-mob-empty-state">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none"><rect x="8" y="4" width="32" height="40" rx="4" stroke="var(--sb-text-3)" stroke-width="1.5"/><path d="M16 16h16M16 24h16M16 32h8" stroke="var(--sb-text-3)" stroke-width="1.5" stroke-linecap="round"/></svg>
            <p>Aucune ligue ajoutée</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Match listings — richer skeleton until JS renders -->
    <div id="sb-matches-body">
      <div class="sb-skeleton-container" id="sb-initial-skeleton">
        <?php for($s=0;$s<5;$s++): ?>
        <div class="sb-sk-group">
          <!-- League header row -->
          <div class="sb-sk-row sb-sk-header">
            <div class="sb-sk-block" style="width:18px;height:18px;border-radius:50%"></div>
            <div class="sb-sk-block" style="width:130px;height:11px;margin-left:8px"></div>
            <div class="sb-sk-block" style="width:40px;height:11px;margin-left:auto"></div>
          </div>
          <!-- Match row 1 -->
          <div class="sb-sk-match">
            <div class="sb-sk-match-left">
              <div class="sb-sk-block" style="width:90px;height:10px;margin-bottom:6px"></div>
              <div class="sb-sk-block" style="width:110px;height:10px"></div>
            </div>
            <div class="sb-sk-match-odds">
              <div class="sb-sk-btn"></div>
              <div class="sb-sk-btn"></div>
              <div class="sb-sk-btn"></div>
              <div class="sb-sk-btn"></div>
              <div class="sb-sk-btn"></div>
            </div>
          </div>
          <!-- Match row 2 -->
          <div class="sb-sk-match">
            <div class="sb-sk-match-left">
              <div class="sb-sk-block" style="width:115px;height:10px;margin-bottom:6px"></div>
              <div class="sb-sk-block" style="width:80px;height:10px"></div>
            </div>
            <div class="sb-sk-match-odds">
              <div class="sb-sk-btn"></div>
              <div class="sb-sk-btn"></div>
              <div class="sb-sk-btn"></div>
              <div class="sb-sk-btn"></div>
              <div class="sb-sk-btn"></div>
            </div>
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</section>

<!-- ══ RIGHT SIDEBAR ══ -->
<aside class="sb-right" id="sb-right">

  <!-- Match en direct viewer — shown when a match is opened -->
  <div class="sb-match-viewer" id="sb-match-viewer" style="display:none">
    <div class="sb-pitch-wrap">
      <div class="sb-pitch" id="sb-pitch">
        <div class="sb-pitch-line-center"></div>
        <div class="sb-pitch-circle"></div>
        <div class="sb-pitch-box left"></div>
        <div class="sb-pitch-box right"></div>
        <div class="sb-pitch-label" id="sb-pitch-label"></div>
      </div>
    </div>
    <div class="sb-viewer-tabs">
      <button class="sb-vt active" onclick="window.sbViewerTab(this,'live')">
        <i class="fa fa-play"></i> EN DIRECT
      </button>
      <button class="sb-vt" onclick="window.sbViewerTab(this,'h2h')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="4" stroke="currentColor" stroke-width="1.5"/><circle cx="15" cy="8" r="4" stroke="currentColor" stroke-width="1.5"/><path d="M3 20c0-4.4 2.7-8 6-8M21 20c0-4.4-2.7-8-6-8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> FACE-À-FACE
      </button>
      <button class="sb-vt" onclick="window.sbViewerTab(this,'incidents')">
        <i class="fa fa-list"></i> INCIDENTS
      </button>
      <button class="sb-vt" onclick="window.sbViewerTab(this,'stats')">
        <i class="fa fa-bar-chart"></i> STATS
      </button>
    </div>
  </div>

  <div class="sb-widget">
    <div class="sb-widget-hdr">Code rapide <i class="fa fa-info-circle"></i></div>
    <div class="sb-widget-body">
      <input type="text" class="sb-dark-inp" placeholder="Entrez le code rapide">
      <label class="sb-quick-toggle">
        <input type="checkbox"> <span>Utilisez le mode rapide</span>
      </label>
    </div>
  </div>

  <div class="sb-widget">
    <div class="sb-widget-hdr">Rechercher des paris</div>
    <div class="sb-widget-body">
      <div class="sb-search-row">
        <select class="sb-dark-sel"><option>Bet Code</option></select>
        <input type="text" class="sb-dark-inp" placeholder="Entrez le numéro d...">
      </div>
    </div>
  </div>

  <div class="sb-widget">
    <div class="sb-widget-hdr">FICHE DE PARI <i class="fa fa-minus"></i></div>
    <div id="sb-slip-body"></div>
  </div>

  <div class="sb-widget">
    <div class="sb-widget-hdr">Paris populaires</div>
    <div id="sb-popular-bets"></div>
  </div>
</aside>

</div>

<!-- Mobile Bar — 5 items matching fcbet216 -->
<div class="sb-mob-footer">
  <button class="sb-mob-btn" onclick="window.location.href='/'">
    <!-- Cherry icon matching fcbet216 Casino button -->
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="7.5" cy="17.5" r="3.5" stroke="currentColor" stroke-width="1.5"/>
      <circle cx="16.5" cy="17.5" r="3.5" stroke="currentColor" stroke-width="1.5"/>
      <path d="M7.5 14C7.5 14 8 10 11 8C11 8 13 10 16.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M11 8C11 8 11 5 13 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <span class="sb-mob-lbl">Casino</span>
  </button>
  <button class="sb-mob-btn active">
    <svg width="22" height="22" viewBox="0 0 32 33" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M28.3514 5.82922C24.4299 1.15119 18.5499-0.498047 12.9999 0.802002C7.44989 2.1021 3.06249 6.48951 1.76239 12.0395C0.46229 17.5895 2.11244 23.4695 5.82244 27.1795C9.53244 30.8895 15.4124 32.5396 20.9624 31.2396C26.5124 29.9396 30.8998 25.5522 32.1998 19.9522C33.4998 14.3522 31.8499 8.50718 28.3514 5.82922Z" fill="currentColor" opacity="0.15"/><circle cx="16" cy="16.5" r="5" fill="currentColor"/></svg>
    <span class="sb-mob-lbl">Paris sportifs</span>
  </button>
  <button class="sb-mob-btn" onclick="window.location.href='/'">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="3" y="8" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 8V6a4 4 0 018 0v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 8v13M3 12h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    <span class="sb-mob-lbl">Promos</span>
  </button>
  <button class="sb-mob-btn" onclick="document.querySelector('.sb-sidebar-search') && document.querySelector('.sb-sidebar-search').focus()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    <span class="sb-mob-lbl">Recherche</span>
  </button>
  <button class="sb-mob-btn" onclick="window.sbToggleLeft()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 12h18M3 6h18M3 18h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    <span class="sb-mob-lbl">Menu</span>
  </button>
</div>

<script src="<?=$base?>sportsbook/app.js?v=<?=time()?>"></script>
