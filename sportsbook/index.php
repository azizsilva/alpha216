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
<?php $sb_css_v = @filemtime(__DIR__ . '/style.css') ?: time(); ?>
<link rel="stylesheet" href="<?=$base?>sportsbook/style.css?v=<?=$sb_css_v?>" id="sb-css-link">
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
  /* All 3 topbar buttons — equal flex share, consistent height */
  .sb-mobile-topbar .sb-btn-home,
  .sb-mobile-topbar .sb-btn-live,
  .sb-mobile-topbar .sb-btn-stats{
    flex:1!important;min-width:0!important;
    height:40px!important;display:flex!important;align-items:center!important;justify-content:center!important;
    border:none!important;border-radius:8px!important;cursor:pointer!important;
    background:#252525!important;color:#979797!important;
    font-family:'Poppins',sans-serif!important;outline:none!important;
  }
  /* ACTIVE: only change color — size stays equal */
  .sb-mobile-topbar .sb-btn-home.active,
  .sb-mobile-topbar .sb-btn-live.active{
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
/* ── Match cards — beat Bootstrap 3 from parent site ── */
.sb-root .mc{background:rgb(49,49,49)!important;border-width:0!important;border-color:transparent!important;border-radius:6px!important;margin-bottom:6px!important;display:flex!important;flex-direction:column!important}
.sb-root .mc-hdr-live{display:flex!important;justify-content:space-between!important;padding:10px 12px 4px!important}
.sb-root .mc-badge-bb{background:#71f669!important;color:rgba(0,0,0,.87)!important;font-size:10px!important;font-weight:700!important;padding:2px 5px!important;border-radius:3px!important}
.sb-root .mc-live-badge{background:#e02424!important;color:#fff!important;font-size:9px!important;font-weight:800!important;padding:2px 7px!important;border-radius:4px!important;border:none!important}
.sb-root .mc-live-min{color:rgba(255,255,255,0.55)!important;font-size:11px!important}
.sb-root .mc-league-row{padding:0 12px 8px!important;display:flex!important;align-items:center!important}
.sb-root .mc-league-name{color:rgba(255,255,255,0.55)!important;font-size:11px!important}
/* H2H teams layout */
.sb-root .mc-teams-wrap{display:flex!important;align-items:center!important;padding:2px 12px 10px!important;width:100%!important;cursor:pointer!important}
.sb-root .mc-h2h{display:flex!important;align-items:center!important;gap:6px!important;width:100%!important}
.sb-root .mc-h2h-home,.sb-root .mc-h2h-away{flex:1!important;display:flex!important;align-items:center!important;gap:6px!important;min-width:0!important}
.sb-root .mc-h2h-away{flex-direction:row-reverse!important}
.sb-root .mc-h2h-score{display:flex!important;align-items:center!important;gap:3px!important;flex-shrink:0!important;min-width:48px!important;justify-content:center!important}
.sb-root .mc-sv{font-size:17px!important;font-weight:800!important;color:#fff!important;line-height:1!important}
.sb-root .mc-sc-sep{font-size:12px!important;color:rgba(255,255,255,0.4)!important}
.sb-root .mc-vs-lbl{font-size:11px!important;font-weight:700!important;color:rgba(255,255,255,0.4)!important}
.sb-root .mc-t-name{color:#fff!important;font-size:12px!important;font-weight:600!important;flex:1!important;min-width:0!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
.sb-root .mc-h2h-home .mc-t-name{text-align:left!important}
.sb-root .mc-h2h-away .mc-t-name{text-align:right!important}
.sb-root .mc-jersey-svg{width:26px!important;height:26px!important;flex-shrink:0!important;display:inline-block!important;overflow:visible!important}
.sb-root .mc-odds-bot{display:flex!important;gap:5px!important;padding:0 12px 12px!important}
.sb-root button.mc-odd-btn,.sb-root .mc-odd-btn{background:rgb(49,49,49)!important;border:1px solid rgba(255,255,255,0.22)!important;border-radius:6px!important;height:40px!important;display:flex!important;flex-direction:row!important;align-items:center!important;justify-content:space-between!important;padding:0 10px!important;flex:1!important;min-width:0!important;box-shadow:none!important;text-shadow:none!important}
.sb-root .mc-odd-lbl{color:rgba(255,255,255,0.6)!important;font-size:11px!important;font-weight:500!important}
.sb-root .mc-odd-val{color:#71f669!important;font-size:14px!important;font-weight:700!important}
.sb-root button.mc-chevron-btn{width:40px!important;height:40px!important;background:rgb(49,49,49)!important;border:1px solid rgba(255,255,255,0.22)!important;border-radius:6px!important}
.sb-root .sb-league-section-hdr{background:#1e1e1e!important;border-width:0!important;border-radius:6px!important;margin:12px 0 6px!important;padding:10px 12px!important;display:flex!important;align-items:center!important}
.sb-root .sb-league-block{margin-bottom:8px!important}
/* Championship market tabs — must scroll horizontally, never wrap */
.sb-root .sb-champ-mkt-tabs{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;overflow-y:hidden!important;gap:4px!important;padding-bottom:4px!important;margin-bottom:10px!important;scrollbar-width:none!important;}
/* Market category grid — always 2-col on narrow screens, Bootstrap-proof */
@media(max-width:600px){
  .sb-root .sb-champ-cat-grid{display:grid!important;grid-template-columns:repeat(2,1fr)!important;gap:5px!important;}
  .sb-root .sb-ccg-btn{height:36px!important;min-height:36px!important;padding:0 8px!important;font-size:11px!important;display:flex!important;align-items:center!important;justify-content:center!important;border-radius:6px!important;text-align:center!important;box-sizing:border-box!important;line-height:1.2!important;}
}
/* Sport nav — single fully-scrollable row */
.sb-sport-nav{overflow:hidden!important;height:58px!important;background:#181818!important;border-bottom:1px solid #2a2a2a!important;flex-shrink:0!important;}
.sb-sport-nav-inner{display:flex!important;align-items:center!important;height:100%!important;gap:2px!important;padding:0 6px!important;overflow-x:auto!important;overflow-y:hidden!important;-webkit-overflow-scrolling:touch!important;scrollbar-width:none!important;}
.sb-sport-nav-inner::-webkit-scrollbar{display:none!important;}
#sb-sport-nav-list{display:contents!important;}
/* Sport nav tiles — Bootstrap 3 overrides button bg; force transparent */
.sb-root .sb-sport-item{background:transparent!important;border:none!important;box-shadow:none!important;display:flex!important;flex-direction:column!important;align-items:center!important;}
.sb-root .sb-sport-item.active{background:#70f669!important;color:rgba(0,0,0,.87)!important;}
.sb-root .sb-sport-item .sb-sport-icon,.sb-root .sb-sport-item .sb-sport-icon svg{display:flex!important;width:20px!important;height:20px!important;overflow:visible!important;}
.sb-root .sb-sport-item .sb-sport-icon svg{filter:brightness(0) invert(1)!important;opacity:.55!important;}
.sb-root .sb-sport-item.active .sb-sport-icon svg{filter:brightness(0)!important;opacity:1!important;}
/* Sport filter pills — Bootstrap 3 collapses SVG icons */
.sb-root .sb-upcoming-tabs{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:4px!important;padding-bottom:8px!important;}
.sb-root .sb-upcoming-tab{display:inline-flex!important;align-items:center!important;gap:6px!important;height:30px!important;white-space:nowrap!important;flex-shrink:0!important;background:#252525!important;border:1px solid #2a2a2a!important;border-radius:20px!important;padding:0 12px!important;font-size:12px!important;color:#fff!important;box-shadow:none!important;}
.sb-root .sb-upcoming-tab.active{background:#70f669!important;border-color:#70f669!important;color:rgba(0,0,0,.87)!important;}
.sb-root .sb-upcoming-tab .sb-tab-icon{display:inline-flex!important;align-items:center!important;width:16px!important;height:16px!important;flex-shrink:0!important;overflow:visible!important;}
.sb-root .sb-upcoming-tab .sb-tab-icon svg{display:block!important;width:16px!important;height:16px!important;filter:brightness(0) invert(1)!important;opacity:.8!important;overflow:visible!important;}
.sb-root .sb-upcoming-tab.active .sb-tab-icon svg{filter:brightness(0)!important;opacity:1!important;}
/* Championship top tabs — horizontal scroll */
.sb-root .sb-champ-top-tabs{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:0!important;scrollbar-width:none!important;}
</style>

<div class="sb-root">

<!-- ══ LEFT SIDEBAR ══ -->
<aside class="sb-left" id="sb-left">
  <div class="sb-sidebar-top">
    <div class="sb-top-bar">
      <button class="sb-btn-home active" onclick="window.sbSwitchTab(this,'inplay',1)"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 14.6666V7.99992H10V14.6666M2 5.99992L8 1.33325L14 5.99992V13.3333C14 13.6869 13.8595 14.026 13.6095 14.2761C13.3594 14.5261 13.0203 14.6666 12.6667 14.6666H3.33333C2.97971 14.6666 2.64057 14.5261 2.39052 14.2761C2.14048 14.026 2 13.6869 2 13.3333V5.99992Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
      <button class="sb-btn-live"><span class="sb-live-badge">EN DIRECT</span></button>
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
    <!-- League tabs: LES MEILLEURES LIGUES / MES LIGUES -->
    <div id="sb-favs-content">
    <div class="sb-league-group-hdr">
      <button class="sb-mob-tab active" data-tab="best" onclick="window.sbMobLeagueTab(this,'best')">LES MEILLEURES LIGUES</button>
      <button class="sb-mob-tab" data-tab="my" onclick="window.sbMobLeagueTab(this,'my')">MES LIGUES <span class="sb-mes-cnt">0</span></button>
      <span class="sb-lh-minus">—</span>
    </div>

    <!-- LES MEILLEURES LIGUES content — always shows the fixed top-league list -->
    <div id="sb-mob-best-leagues">
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
    </div><!-- /sb-mob-best-leagues -->

    <!-- MES LIGUES content — empty state (user hasn't starred any leagues yet) -->
    <div id="sb-mob-my-leagues" style="display:none">
      <div class="sb-empty-leagues-state">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:#444;margin-bottom:10px"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <span>Aucune ligue ajoutée</span>
      </div>
    </div><!-- /sb-mob-my-leagues -->

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
        <div class="sb-cotes-opt active" data-fmt="dec" onclick="window.sbSetOddsFormat('dec',this)">
          <span>Décimal (2.00)</span><span class="sb-cotes-check">✓</span>
        </div>
        <div class="sb-cotes-opt" data-fmt="amer" onclick="window.sbSetOddsFormat('amer',this)">
          <span>Américain (+100)</span>
        </div>
        <div class="sb-cotes-opt" data-fmt="frac" onclick="window.sbSetOddsFormat('frac',this)">
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

    <!-- Sport navigation — single scrollable row, matches fcbet216 -->
    <div class="sb-sport-nav">
      <div class="sb-sport-nav-inner">
      <!-- Streaming button with LIVE badge — exact fcbet SVG -->
      <button class="sb-sport-item sb-nav-streaming" onclick="window.sbSwitchTab(this,'inplay',0)">
        <div class="sb-sport-icon" style="position:relative">
          <svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2217_25_i)"><path d="M28.99 15.5649C29.55 15.5649 30 16.0149 30 16.5749V28.4349C30 28.9949 29.55 29.4449 28.99 29.4449H3.01C2.45 29.4449 2 28.9949 2 28.4349V16.5749C2 16.0149 2.45 15.5649 3.01 15.5649H29M29 13.5649H3.01C1.35 13.5649 0 14.9149 0 16.5749V28.4349C0 30.0949 1.35 31.4449 3.01 31.4449H29C30.66 31.4449 32.01 30.0949 32.01 28.4349V16.5749C32.01 14.9149 30.66 13.5649 29 13.5649Z" fill="currentColor"/><path d="M10.18 26.445H6.86995C6.38995 26.445 5.99995 26.055 5.99995 25.575V19.435C5.99995 18.955 6.38995 18.565 6.86995 18.565C7.34995 18.565 7.73995 18.955 7.73995 19.435V24.715H10.18C10.66 24.715 11.05 25.105 11.05 25.585C11.05 26.065 10.66 26.455 10.18 26.455V26.445Z" fill="currentColor"/><path d="M12.79 26.445C12.31 26.445 11.92 26.055 11.92 25.575V19.435C11.92 18.955 12.31 18.565 12.79 18.565C13.27 18.565 13.66 18.955 13.66 19.435V25.575C13.66 26.055 13.27 26.445 12.79 26.445Z" fill="currentColor"/><path d="M17.75 26.445C17.3899 26.445 17.07 26.225 16.94 25.885L14.58 19.745C14.41 19.295 14.63 18.795 15.08 18.625C15.53 18.455 16.03 18.675 16.2 19.125L17.75 23.165L19.3 19.125C19.47 18.675 19.9699 18.455 20.42 18.625C20.87 18.795 21.09 19.295 20.92 19.745L18.56 25.885C18.43 26.215 18.11 26.445 17.75 26.445Z" fill="currentColor"/><path d="M26.89 25.575C26.89 26.055 26.5 26.445 26.02 26.445H22.71C22.23 26.445 21.84 26.055 21.84 25.575V19.435C21.84 18.955 22.23 18.565 22.71 18.565H26.02C26.5 18.565 26.89 18.955 26.89 19.435C26.89 19.915 26.5 20.305 26.02 20.305H23.58V21.645H25.08C25.5599 21.645 25.95 22.035 25.95 22.515C25.95 22.995 25.5599 23.385 25.08 23.385H23.58V24.725H26.02C26.5 24.725 26.89 25.115 26.89 25.595V25.575Z" fill="currentColor"/><path d="M14.4129 4.67822C14.782 4.30917 14.782 3.71083 14.4129 3.34178C14.0439 2.97274 13.4455 2.97274 13.0765 3.34178C11.4475 4.97083 11.4475 7.61917 13.0765 9.24822C13.4455 9.61726 14.0439 9.61726 14.4129 9.24822C14.782 8.87917 14.782 8.28083 14.4129 7.91178C13.522 7.02083 13.522 5.56917 14.4129 4.67822Z" fill="currentColor"/><path d="M16.2447 4.85C15.4467 4.85 14.7997 5.49695 14.7997 6.295C14.7997 7.09305 15.4467 7.74 16.2447 7.74C17.0428 7.74 17.6897 7.09305 17.6897 6.295C17.6897 5.49695 17.0428 4.85 16.2447 4.85Z" fill="currentColor"/><path d="M19.4129 3.34178C19.0439 2.97274 18.4455 2.97274 18.0765 3.34178C17.7075 3.71083 17.7075 4.30917 18.0765 4.67822C18.9675 5.56917 18.9675 7.02083 18.0765 7.91178C17.7075 8.28083 17.7075 8.87917 18.0765 9.24822C18.4455 9.61726 19.0439 9.61726 19.4129 9.24822C21.042 7.61917 21.042 4.97083 19.4129 3.34178Z" fill="currentColor"/><path d="M22.3579 1.27678C21.9889 0.907739 21.3905 0.907739 21.0215 1.27678C20.6525 1.64583 20.6525 2.24417 21.0215 2.61322C23.0525 4.64417 23.0525 7.94583 21.0215 9.97678C20.6525 10.3458 20.6525 10.9442 21.0215 11.3132C21.3905 11.6823 21.9889 11.6823 22.3579 11.3132C25.127 8.54417 25.127 4.04583 22.3579 1.27678Z" fill="currentColor"/><path d="M11.4679 2.61322C11.837 2.24417 11.837 1.64583 11.4679 1.27678C11.0989 0.907739 10.5005 0.907739 10.1315 1.27678C7.36246 4.04583 7.36246 8.54417 10.1315 11.3132C10.5005 11.6823 11.0989 11.6823 11.4679 11.3132C11.837 10.9442 11.837 10.3458 11.4679 9.97678C9.43698 7.94583 9.43698 4.64417 11.4679 2.61322Z" fill="currentColor"/></g><defs><clipPath id="clip0_2217_25_i"><rect width="32" height="32" fill="white"/></clipPath></defs></svg>
          <span class="sb-nav-live-dot"></span>
        </div>
        <span class="sb-sport-lbl">Streami...</span>
      </button>
      <!-- En direct — exact fcbet play-circle SVG -->
      <button class="sb-sport-item sb-nav-endirect" onclick="window.sbSwitchLive(this)">
        <div class="sb-sport-icon">
          <svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_1_207_i)"><path fill-rule="evenodd" clip-rule="evenodd" d="M23 17.732C24.3333 16.9622 24.3333 15.0377 23 14.2679L14 9.07177C12.6667 8.30198 11 9.26422 11 10.8038L11 21.1961C11 22.7357 12.6667 23.698 14 22.9282L23 17.732ZM13 10.8038L22 16L13 21.1961L13 10.8038Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M32 16C32 24.8366 24.8366 32 16 32C7.16344 32 0 24.8366 0 16C0 7.16344 7.16344 0 16 0C24.8366 0 32 7.16344 32 16ZM30 16C30 23.732 23.732 30 16 30C8.26801 30 2 23.732 2 16C2 8.26801 8.26801 2 16 2C23.732 2 30 8.26801 30 16Z" fill="currentColor"/></g><defs><clipPath id="clip0_1_207_i"><rect width="32" height="32" fill="white"/></clipPath></defs></svg>
        </div>
        <span class="sb-sport-lbl">En direct</span>
      </button>
      <!-- Dynamic sport tabs — rendered by renderSportNav() -->
      <div id="sb-sport-nav-list">
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
      </div><!-- /sb-sport-nav-inner -->
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

    <!-- Cotes boostées — matches fcbet216 prelive layout -->
    <div class="sb-boost-section" id="sb-boost-section">
      <div class="sb-boost-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg>
        <span>Cotes boostées</span>
      </div>
      <div class="sb-boost-row" id="sb-boosted-odds">
        <div class="sb-sk-boost-card"></div>
        <div class="sb-sk-boost-card"></div>
      </div>
    </div>

    <!-- ══ MOBILE INLINE LEAGUE SECTION ══
         Search bar + LES MEILLEURES LIGUES / MES LIGUES tabs
         Visible on mobile only (hidden on desktop via CSS)
         Appears inline in the main scroll — matches fcbet216 mobile layout -->
    <div class="sb-mob-inline-leagues sb-mob-leagues-panel">
      <!-- Search bar -->
      <div class="sb-search-wrap">
        <div class="sb-search-box">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:var(--sb-text-2);flex-shrink:0"><path d="M14 14L11.1 11.1M7.33 2C5.92 2 4.56 2.56 3.56 3.56C2.56 4.56 2 5.92 2 7.33C2 8.74 2.56 10.1 3.56 11.1C4.56 12.1 5.92 12.67 7.33 12.67C8.74 12.67 10.1 12.1 11.1 11.1C12.1 10.1 12.67 8.74 12.67 7.33C12.67 5.92 12.1 4.56 11.1 3.56C10.1 2.56 8.74 2 7.33 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <input type="text" class="sb-sidebar-search" placeholder="Entrez l'équipe ou le nom du championnat" oninput="window.sbSearchMatches(this.value)">
        </div>
      </div>

      <!-- League tabs header -->
      <div class="sb-league-group-hdr">
        <button class="sb-mob-tab active" data-tab="best" onclick="window.sbMobLeagueTab(this,'best')">LES MEILLEURES LIGUES</button>
        <button class="sb-mob-tab" data-tab="my" onclick="window.sbMobLeagueTab(this,'my')">MES LIGUES <span class="sb-mes-cnt">0</span></button>
        <span class="sb-lh-minus">—</span>
      </div>

      <!-- LES MEILLEURES LIGUES content -->
      <div id="sb-inline-best-leagues">
        <div class="sb-tl-list">
          <?php foreach($top_leagues as $l): ?>
          <div class="sb-tl-item" onclick="window.sbOpenLeague('<?=$l['id']?>','<?=$l['name']?>','https://flagcdn.com/w20/<?=$l['flag']?>.png',<?=$l['sport']?>)">
            <img src="https://flagcdn.com/w20/<?=$l['flag']?>.png" class="sb-flag-icon" alt="<?=$l['name']?>">
            <span class="sb-league-name"><?=$l['name']?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- MES LIGUES empty state -->
      <div id="sb-inline-my-leagues" style="display:none">
        <div class="sb-empty-leagues-state">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:#444;margin-bottom:10px"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <span>Aucune ligue ajoutée</span>
        </div>
      </div>
    </div><!-- /sb-mob-inline-leagues -->

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

<script src="<?=$base?>sportsbook/app.js?v=<?=$sb_css_v?>"></script>
