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

// Top leagues — single source for desktop sidebar + mobile panel (fcbet216 reference)
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

function sb_render_tl_item(array $l): void {
  $is_globe = in_array($l['flag'], ['un', 'eu'], true);
  $id   = htmlspecialchars((string)$l['id'], ENT_QUOTES, 'UTF-8');
  $name = htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8');
  $flag = htmlspecialchars($l['flag'], ENT_QUOTES, 'UTF-8');
  $sport = (int)$l['sport'];
  $flagUrl = 'https://flagcdn.com/w20/' . $flag . '.png';
  echo '<div class="sb-tl-item" data-league-id="' . $id . '" data-league-label="' . $name . '" onclick="window.sbOpenLeague(\'' . $id . '\',\'' . $name . '\',\'' . $flagUrl . '\',' . $sport . ')">';
  if ($is_globe) {
    echo '<span class="sb-globe-icon" aria-hidden="true">';
    echo '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="6.4" stroke="currentColor" stroke-width="1.2"/><path d="M1.6 8H14.4M8 1.6C9.7 3.5 10.6 5.7 10.6 8C10.6 10.3 9.7 12.5 8 14.4M8 1.6C6.3 3.5 5.4 5.7 5.4 8C5.4 10.3 6.3 12.5 8 14.4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';
    echo '</span>';
  } else {
    echo '<img src="' . $flagUrl . '" class="sb-flag-icon" alt="" width="22" height="16" loading="lazy">';
  }
  echo '<span class="sb-league-name">' . $name . '</span>';
  echo '</div>';
}
?>
<!-- ── INSTANT PAINT — runs before any external CSS loads ──────────────
     Kills the "black flash on reload" by painting the dark sportsbook
     background + a centered loading splash the moment the HTML parser
     reaches this <style>. The splash is removed by app.js once the
     first matches finish rendering (see sbHideBootSplash in app.js). -->
<style id="sb-boot-paint">
html, body { background: #0a0a0a !important; margin: 0; padding: 0; color: #fff; }
body.mk-game-no-chrome { padding-top: 0 !important; overflow: hidden; }
.sb-root { background: #0a0a0a; min-height: 100vh; }
#sb-boot-splash {
  position: fixed; inset: 0; z-index: 99999;
  background: #0a0a0a;
  display: flex; align-items: center; justify-content: center;
  transition: opacity .25s ease;
}
#sb-boot-splash.hide { opacity: 0; pointer-events: none; }
#sb-boot-splash .ring {
  width: 38px; height: 38px; border-radius: 50%;
  border: 3px solid rgba(255,255,255,0.10);
  border-top-color: #70f669;
  animation: sb-boot-spin .8s linear infinite;
}
@keyframes sb-boot-spin { to { transform: rotate(360deg); } }
</style>

<!-- Fonts: preconnect + swap for zero render blocking -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;900&family=Poppins:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" media="print" onload="this.media='all'">
<noscript>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;900&family=Poppins:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</noscript>
<?php
// Cache-bust = max(filemtime, this file's own filemtime). The OR with time()
// is a last-ditch fallback; we also concat a manual build stamp so when the
// version changes everyone gets fresh CSS/JS even on hosts where filemtime
// is cached by opcache or the CDN ignores stat changes.
$sb_build_stamp = 'b20260525co';   // bump this when you ship a new deploy
$sb_css_v = ($sb_build_stamp . '.' . (@filemtime(__DIR__ . '/style.css') ?: time()));
$sb_js_v  = ($sb_build_stamp . '.' . (@filemtime(__DIR__ . '/app.js')   ?: time()));
?>
<!-- Preload tells the browser to start fetching these at high priority
     before parsing reaches the actual <link>/<script> tags. -->
<link rel="preload" href="<?=$base?>sportsbook/style.css?v=<?=$sb_css_v?>" as="style">
<link rel="preload" href="<?=$base?>sportsbook/app.js?v=<?=$sb_js_v?>" as="script">
<link rel="stylesheet" href="<?=$base?>sportsbook/style.css?v=<?=$sb_css_v?>" id="sb-css-link">
<!-- HTML page is NOT cached — every navigation pulls the latest markup with
     the freshest CSS/JS filemtime version stamps so we never serve stale UI.
     (The CSS/JS files themselves are still long-cached via filemtime.) -->
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
    background:rgb(49,49,49)!important;
    border-bottom:1px solid rgba(255,255,255,0.04)!important;
  }
  /* All 3 topbar buttons — equal flex share, EN DIRECT centered (no icon).
     Background matches the bar's gray family (rgb(58,58,58) sits just one
     step lighter than the bar's rgb(49,49,49) so the button is visible as
     a chip without ever falling into the near-black look of rgba black). */
  .sb-mobile-topbar .sb-btn-home,
  .sb-mobile-topbar .sb-btn-live,
  .sb-mobile-topbar .sb-btn-stats{
    flex:1!important;min-width:0!important;
    height:40px!important;display:flex!important;align-items:center!important;justify-content:center!important;
    border:1px solid rgba(255,255,255,0.04)!important;border-radius:8px!important;cursor:pointer!important;
    background:rgb(58,58,58)!important;color:#cfcfcf!important;
    font-family:'Poppins',sans-serif!important;outline:none!important;
    padding:0!important;
  }
  /* Defensive: in case the inline soccer-icon span sneaks back in, hide it */
  .sb-mobile-topbar .sb-btn-live .sb-btn-live-sport{display:none!important;}
  /* ACTIVE: only change color — size stays equal */
  .sb-mobile-topbar .sb-btn-home.active,
  .sb-mobile-topbar .sb-btn-live.active{
    background:#70f669!important;color:rgba(0,0,0,0.87)!important;
  }
  /* EN DIRECT red badge (shown when button is NOT active) — fcbet216 .cqljKD EXACT */
  .sb-mobile-topbar .sb-btn-live:not(.active) .sb-live-badge{
    background:rgb(251,51,51)!important;background-color:rgb(251,51,51)!important;
    color:rgb(255,255,255)!important;
    font-family:'Roboto',sans-serif!important;
    font-size:12px!important;font-weight:500!important;line-height:12px!important;
    padding:2px 4px!important;border:0!important;border-radius:4px!important;
    letter-spacing:0!important;text-transform:uppercase!important;
    white-space:nowrap!important;
  }
  /* EN DIRECT badge when active — KEEP red pill on green tab (fcbet216 image 3) */
  .sb-mobile-topbar .sb-btn-live.active .sb-live-badge,
  .sb-mobile-topbar button.sb-btn-live.active .sb-live-badge,
  .sb-mobile-topbar .sb-btn-live.active > .sb-live-badge{
    background:rgb(251,51,51)!important;background-color:rgb(251,51,51)!important;background-image:none!important;
    color:rgb(255,255,255)!important;
    font-family:'Roboto',sans-serif!important;
    font-size:12px!important;font-weight:500!important;line-height:12px!important;
    letter-spacing:0!important;text-transform:uppercase!important;
    padding:2px 4px!important;border:0!important;border-radius:4px!important;
    box-shadow:none!important;display:inline-block!important;white-space:nowrap!important;
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
  .sb-right{bottom:0!important;left:0!important;right:0!important;top:auto!important;width:100%!important;max-height:82vh!important;border-radius:16px 16px 0 0!important;transform:translateY(100%)!important;border-left:none!important;border-top:1px solid rgba(255,255,255,0.10)!important;background:#181818!important;}
  .sb-right.open{transform:translateY(0)!important;box-shadow:0 -10px 60px rgba(0,0,0,0.9)!important;}
}
/* ── Match cards — fcbet216 prelive design (Bootstrap no longer loaded here) ── */
.sb-root .mc{background:rgb(49,49,49);border:1px solid rgba(255,255,255,0.04);border-radius:8px;margin-bottom:8px;width:100%;display:flex;flex-direction:column;overflow:hidden;}
.sb-root .mc-hdr-live{display:flex;justify-content:space-between;align-items:center;padding:8px 12px 4px;gap:6px;}
.sb-root .mc-hl-left{display:flex;align-items:center;gap:6px;flex:1;min-width:0;}
.sb-root .mc-hl-right{display:flex;align-items:center;gap:4px;flex-shrink:0;}
.sb-root .mc-badge-bb{background:#71f669;color:rgba(0,0,0,.87);font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;line-height:1.5;letter-spacing:.2px;}
.sb-root .mc-live-badge{background:rgb(251,51,51);color:rgb(255,255,255);font-family:'Roboto',sans-serif;font-size:12px;font-weight:500;line-height:12px;padding:2px 4px;border:0;border-radius:4px;letter-spacing:0;text-transform:uppercase;white-space:nowrap;display:inline-flex;align-items:center;}
.sb-root .mc-live-min{color:rgba(255,255,255,0.75);font-size:12px;font-weight:500;margin-left:4px;white-space:nowrap;}
/* Sport icon BEFORE BB — flat on card surface, no badge bg (images 3 & 4) */
.sb-root .mc-hdr-live .mc-sport-badge-inline{width:auto!important;height:auto!important;border-radius:0!important;border:none!important;background:transparent!important;color:rgba(255,255,255,0.78)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;margin-right:6px!important;padding:0!important;visibility:visible!important;opacity:1!important;}
.sb-root .mc-hdr-live .mc-sport-badge-inline svg{width:16px!important;height:16px!important;display:block!important;visibility:visible!important;opacity:1!important;color:rgba(255,255,255,0.85)!important;}
/* Inline league bullet/flag/name on the live header row */
.sb-root .mc-live-sep{color:rgba(255,255,255,0.35);font-size:10px;margin:0 2px 0 4px;flex-shrink:0;}
.sb-root .mc-league-flag--inline{width:16px;height:11px;object-fit:cover;border-radius:1px;flex-shrink:0;}
.sb-root .mc-league-name--inline{font-size:11px;color:rgba(255,255,255,0.55);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;margin-left:2px;}
.sb-root .mc-league-row{padding:0 12px 8px;display:flex;align-items:center;gap:6px;}
.sb-root .mc-league-info{display:flex;align-items:center;gap:6px;flex:1;min-width:0;overflow:hidden;}
.sb-root .mc-league-flag{width:16px;height:11px;object-fit:cover;border-radius:1px;flex-shrink:0;}
.sb-root .mc-league-name{color:rgba(255,255,255,0.55);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
/* Global font — Roboto matches alpina216.com */
.sb-root,.sb-root *{font-family:'Roboto',sans-serif;}
/* Per-team rows — used for BOTH live and prelive (fcbet216 image 2 spec).
   Each row = [shirt-inline] [team name flex-1] [score]. */
.sb-root .mc-teams-wrap--rows{display:flex;flex-direction:column;align-items:stretch;justify-content:flex-start;gap:2px;padding:2px 12px 8px;width:100%;cursor:pointer;}
.sb-root .mc-teams-wrap--rows .mc-team-row{display:flex;flex-direction:row;align-items:center;justify-content:flex-start;gap:0;width:100%;min-height:22px;}
/* Inline shirt cell — plain shirt SVG with hairline white stroke, NO circle
   wrapper (fcbet216 "En direct maintenant" reference). The shirt sits
   inline at the start of each team row. */
.sb-root .mc-shirt-cell{width:20px;height:20px;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;background:transparent!important;border:0!important;border-radius:0!important;overflow:visible;box-sizing:border-box;margin-right:8px;}
.sb-root .mc-shirt-cell .mc-jersey-svg{width:18px;height:18px;display:block;flex-shrink:0;stroke:rgba(255,255,255,0.95);stroke-width:0.6;}

/* ── Separate league row — flag · League · Country (fcbet216 image 2) ── */
.sb-root .mc-league-row{display:flex;align-items:center;gap:6px;padding:2px 12px 4px;font-family:'Roboto',sans-serif;font-size:11px;color:rgba(255,255,255,0.55);line-height:14px;}
.sb-root .mc-league-row .mc-league-flag{width:14px;height:10px;object-fit:cover;border-radius:1px;flex-shrink:0;}
.sb-root .mc-league-row .mc-league-name,.sb-root .mc-league-row .mc-league-country{font-size:11px;font-weight:500;color:rgba(255,255,255,0.55);}
.sb-root .mc-league-row .mc-live-sep{color:rgba(255,255,255,0.35);font-size:9px;}
.sb-root .mc-teams-wrap--rows .mc-t-name{flex:1 1 0;text-align:left;font-family:'Roboto',sans-serif;font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;margin-right:auto;line-height:18px;}
.sb-root .mc-teams-wrap--rows .mc-t-score{flex-shrink:0;font-family:'Roboto',sans-serif;font-size:13px;font-weight:700;color:#fff;min-width:16px;text-align:right;margin-left:auto;padding-left:8px;line-height:18px;}
/* LIVE: two shirts SIDE-BY-SIDE on the left + stacked names+scores on the right */
.sb-root .mc-teams-wrap--side{display:flex;flex-direction:row;align-items:center;gap:10px;padding:4px 12px 6px;width:100%;cursor:pointer;}
.sb-root .mc-jerseys-side{display:flex;flex-direction:row;align-items:center;gap:8px;flex-shrink:0;padding:0 4px 0 0;}
/* Live jersey — fcbet216 EXACT: 26px circle, 1px white border, shirts close together */
.sb-root .mc-jersey-cell,.sb-root .mc-jersey-cell--circle{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:rgb(48,48,48);border:1px solid rgba(255,255,255,0.95);flex-shrink:0;overflow:hidden;box-sizing:border-box;}
.sb-root .mc-jersey-cell .mc-jersey-svg,.sb-root .mc-jersey-cell--circle .mc-jersey-svg{width:16px;height:16px;display:block;stroke:rgba(255,255,255,0.95);stroke-width:0.6;}
.sb-root .mc-jerseys-side{display:flex;flex-direction:row;align-items:center;gap:4px;flex-shrink:0;padding:0 6px 0 0;}
/* UPCOMING — fcbet216 spec: circle-shirt rows + EN DIRECT btn + stats icon */
/* UPCOMING card (image 3 ref): shirts side-by-side LEFT, stacked names CENTER, EN DIRECT pill + signal RIGHT */
.sb-root .mc-teams-wrap--upcoming{display:flex;flex-direction:row;align-items:center;gap:10px;padding:2px 12px 6px;width:100%;cursor:pointer;}
.sb-root .mc-teams-wrap--upcoming .mc-jerseys-side{display:flex;flex-direction:row;align-items:center;gap:6px;flex-shrink:0;padding:0;}
.sb-root .mc-teams-wrap--upcoming .mc-teams-stacked{flex:1 1 0;display:flex;flex-direction:column;gap:2px;min-width:0;}
.sb-root .mc-teams-wrap--upcoming .mc-team-row--upcoming{display:flex;align-items:center;width:100%;min-height:18px;}
.sb-root .mc-teams-wrap--upcoming .mc-team-row--upcoming .mc-t-name{flex:1 1 0;font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;line-height:1.25;}
/* Defensive: any legacy markup using these classes also renders plain shirt */
.sb-root .mc-shirt-cell--circle,.sb-root .mc-shirt-cell{display:inline-flex!important;align-items:center;justify-content:center;width:20px;height:20px;border-radius:0!important;background:transparent!important;border:0!important;flex-shrink:0;overflow:visible;box-sizing:border-box;margin-right:8px;}
.sb-root .mc-shirt-cell--circle .mc-jersey-svg,.sb-root .mc-shirt-cell .mc-jersey-svg{width:18px;height:18px;display:block;stroke:rgba(255,255,255,0.95);stroke-width:0.6;}
/* SLC carousel (Type B "before Cotes boostées") — fcbet216 image 3 EXACT */
.sb-root .slc-jersey-wrap,.sb-root .mc-jersey-wrap,.sb-root .slc-jerseys-overlap .mc-jersey-wrap{width:28px!important;height:28px!important;border-radius:50%!important;border:1px solid rgba(255,255,255,0.85)!important;background:rgb(48,48,48)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;overflow:hidden!important;box-sizing:border-box!important;}
.sb-root .slc-jerseys-overlap{display:flex!important;flex-shrink:0!important;align-items:center!important;gap:6px!important;}
.sb-root .slc-jerseys-overlap .mc-jersey-wrap:first-child,.sb-root .slc-jerseys-overlap .mc-jersey-wrap:last-child{margin:0!important;z-index:auto!important;position:relative!important;}
.sb-root .slc-jerseys-overlap .mc-jersey-svg{width:18px!important;height:18px!important;}
.sb-root .slc-hdr .slc-sport-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;color:rgba(255,255,255,0.78)!important;margin-right:2px!important;}
.sb-root .slc-hdr .slc-sport-icon svg{width:14px!important;height:14px!important;display:block!important;}
.sb-root .slc-hdr{padding:10px 14px 6px!important;}
.sb-root .slc-mid{padding:4px 14px 8px!important;}
/* SLC EN DIRECT — fcbet216 .cqljKD EXACT */
.sb-root .slc-live-badge{background:rgb(251,51,51)!important;color:rgb(255,255,255)!important;font-family:'Roboto',sans-serif!important;font-size:12px!important;font-weight:500!important;line-height:12px!important;padding:2px 4px!important;border:0!important;border-radius:4px!important;letter-spacing:0!important;text-transform:uppercase!important;flex-shrink:0!important;white-space:nowrap!important;}
/* SLC odds chip — fcbet216 .glRfhP EXACT user-provided spec */
body .sb-root .slc-odd-btn,body .sb-root button.slc-odd-btn{flex:1!important;background:rgb(74,74,74)!important;background-color:rgb(74,74,74)!important;background-image:none!important;border:1px solid rgba(255,255,255,0)!important;border-radius:4px!important;height:44px!important;padding:0 12px!important;display:flex!important;flex-direction:row!important;align-items:center!important;justify-content:space-between!important;gap:6px!important;cursor:pointer!important;font-family:'Roboto',sans-serif!important;box-shadow:rgba(13,13,13,0) 0px 0px 0px 0px,rgba(13,13,13,0) 0px 0px 0px 0px inset!important;outline:none!important;min-width:0!important;color:rgb(113,246,105)!important;text-align:start!important;}
body .sb-root .slc-odd-lbl{color:rgba(255,255,255,0.55)!important;font-family:'Roboto',sans-serif!important;font-size:12px!important;font-weight:500!important;}
body .sb-root .slc-odd-val{color:rgb(113,246,105)!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:700!important;}
.sb-root .mc-upcoming-actions{display:flex;flex-direction:row;align-items:center;gap:6px;flex-shrink:0;}
.sb-root .mc-ed-pill{background:transparent;border:1px solid rgba(255,255,255,0.35);border-radius:4px;color:#fff;font-size:9px;font-weight:700;letter-spacing:0.4px;text-transform:uppercase;padding:3px 6px;white-space:nowrap;font-family:'Roboto',sans-serif;line-height:1.2;display:inline-flex;align-items:center;}
.sb-root .mc-signal-ico{color:rgba(255,255,255,0.65);display:inline-flex;align-items:flex-end;justify-content:center;width:16px;height:16px;}
.sb-root .mc-signal-ico svg{display:block;width:14px;height:14px;}
/* Sidebar leagues panel — separate from search card, fcbet216 image 1 */
.sb-sidebar-search-card{background:rgb(49,49,49);border:1px solid rgba(255,255,255,0.06);border-radius:10px;overflow:hidden;margin:8px 8px 10px;}
.sb-sidebar-search-card .sb-search-wrap{padding:10px 12px;}
.sb-sidebar-search-card .sb-search-box{background:rgba(0,0,0,0.30);border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:0 10px;height:36px;display:flex;align-items:center;gap:8px;}
/* Panel surface — fcbet216 reference: very dark, just slightly above the
   page bg so individual rows read FLAT instead of like lifted boxes. */
.sb-top-leagues-card,body .sb-root .sb-top-leagues-card{margin:0 8px 10px!important;background:#1c1c1c!important;background-color:#1c1c1c!important;border:1px solid rgba(255,255,255,0.06)!important;border-radius:10px!important;overflow:hidden!important;display:flex!important;flex-direction:column!important;max-height:520px!important;}
.sb-top-leagues-card .sb-league-group-hdr{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,0.06);gap:10px;display:flex;align-items:center;flex:0 0 auto;}
.sb-top-leagues-card .sb-tl-item{border-bottom:1px solid rgba(255,255,255,0.06)!important;}
.sb-top-leagues-card .sb-tl-list,body .sb-root .sb-top-leagues-card .sb-tl-list{flex:1 1 auto!important;overflow-y:auto!important;overflow-x:hidden!important;-webkit-overflow-scrolling:touch!important;scrollbar-width:thin!important;scrollbar-color:rgba(255,255,255,0.18) transparent!important;}
.sb-top-leagues-card .sb-tl-list::-webkit-scrollbar{width:4px!important;}
.sb-top-leagues-card .sb-tl-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.18)!important;border-radius:4px!important;}
.sb-top-leagues-card .sb-tl-list::-webkit-scrollbar-track{background:transparent!important;}
.sb-mob-leagues-panel .sb-tl-list,body .sb-root .sb-mob-leagues-panel .sb-tl-list{max-height:480px!important;overflow-y:auto!important;overflow-x:hidden!important;-webkit-overflow-scrolling:touch!important;scrollbar-width:thin!important;scrollbar-color:rgba(255,255,255,0.18) transparent!important;}
.sb-mob-leagues-panel .sb-tl-list::-webkit-scrollbar{width:4px!important;}
.sb-mob-leagues-panel .sb-tl-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.18)!important;border-radius:4px!important;}
.sb-mob-leagues-panel .sb-tl-list::-webkit-scrollbar-track{background:transparent!important;}
.sb-mob-search-panel{background:transparent;border:0;border-radius:0;overflow:visible;margin:0 2px 10px;}
.sb-mob-search-panel .sb-search-wrap{padding:0;}
.sb-mob-search-panel .sb-search-box{background:rgb(38,38,38);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:0 14px;height:44px;display:flex;align-items:center;gap:10px;}
.sb-mob-search-panel .sb-search-box svg,.sb-mob-search-panel .sb-search-box .sb-search-icon{width:18px;height:18px;color:rgba(255,255,255,0.55);flex-shrink:0;}
.sb-mob-search-panel .sb-sidebar-search{flex:1 1 auto;width:100%;min-width:0;background:transparent;border:0;outline:none;color:#fff;font-family:'Roboto',sans-serif;font-size:13px;padding:0;}
.sb-mob-search-panel .sb-sidebar-search::placeholder{color:rgba(255,255,255,0.45);font-size:13px;opacity:1;}
.sb-mob-leagues-panel,body .sb-root .sb-mob-leagues-panel{background:#1c1c1c!important;background-color:#1c1c1c!important;border:1px solid rgba(255,255,255,0.06)!important;border-radius:10px!important;overflow:hidden!important;margin:0 2px 12px!important;}
.sb-mob-leagues-panel .sb-league-group-hdr{background:transparent;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,0.06);gap:10px;display:flex;align-items:center;}
.sb-mob-leagues-panel .sb-mob-tab{font-size:11px!important;font-weight:700!important;color:#fff!important;letter-spacing:.4px!important;white-space:nowrap!important;text-transform:uppercase!important;opacity:1!important;background:none;border:none;padding:0;cursor:pointer;}
.sb-mob-leagues-panel .sb-mob-tab[data-tab="my"]{color:rgba(255,255,255,.85)!important;}
.sb-mob-leagues-panel .sb-mob-tab.active{color:#fff!important;}
.sb-mob-leagues-panel .sb-lh-minus{margin-left:auto!important;color:#fff!important;opacity:.85!important;font-size:18px!important;line-height:1!important;}
.sb-root .sb-upcoming-tabs::-webkit-scrollbar{display:none!important;width:0!important;height:0!important;}
.sb-root .sb-upcoming-tabs{scrollbar-width:none!important;-ms-overflow-style:none!important;}
.sb-root .sb-live-mkt-btn{color:#fff!important;text-shadow:none!important;box-shadow:none!important;background:transparent!important;border:none!important;border-radius:0!important;}
.sb-root .sb-live-mkt-panel{background:rgb(49,49,49)!important;border:1px solid rgba(255,255,255,0.06)!important;border-radius:12px!important;overflow:visible!important;}
/* Live-page market dropdown — spaced rounded rows (fcbet216 reference image). */
body .sb-root .sb-live-mkt-drop,.sb-root .sb-live-mkt-drop,.sb-live-mkt-drop{display:none!important;background:transparent!important;border:0!important;border-radius:0!important;overflow:visible!important;position:relative!important;z-index:10!important;margin-top:6px!important;padding:4px!important;flex-direction:column!important;gap:6px!important;}
body .sb-root .sb-live-mkt-wrap.sb-lmdt-open .sb-live-mkt-drop,.sb-root .sb-live-mkt-wrap.sb-lmdt-open .sb-live-mkt-drop,.sb-live-mkt-wrap.sb-lmdt-open .sb-live-mkt-drop{display:flex!important;}
body .sb-root .sb-lmk-item,.sb-root .sb-lmk-item,.sb-lmk-item{width:100%!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;padding:14px 18px!important;background:rgb(74,74,74)!important;background-color:rgb(74,74,74)!important;background-image:none!important;border:0!important;border-radius:6px!important;color:rgba(255,255,255,0.92)!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:500!important;text-align:left!important;cursor:pointer!important;outline:none!important;box-shadow:none!important;margin:0!important;line-height:1.2!important;-webkit-tap-highlight-color:transparent!important;}
body .sb-root .sb-lmk-item:hover,.sb-root .sb-lmk-item:hover,.sb-lmk-item:hover{background:rgba(255,255,255,0.08)!important;color:#fff!important;}
.sb-root[data-view="livepage"] .sb-mob-search-panel{display:flex!important;}
.sb-root[data-view="livepage"] .sb-mob-search-panel .sb-search-box{border:1px solid #70f669!important;}
.sb-mob-leagues-panel .sb-tl-item{background:transparent;border:none;border-bottom:1px solid rgba(255,255,255,0.06);padding:12px 14px;border-radius:0;min-height:44px;gap:12px;display:flex;align-items:center;}
.sb-mob-leagues-panel .sb-tl-item:last-child{border-bottom:none;}
.sb-mob-leagues-panel .sb-league-name{font-size:13px;font-weight:600;color:#fff;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sb-globe-icon,body .sb-root .sb-globe-icon,body .sb-mob-leagues-panel .sb-globe-icon,body .sb-top-leagues-card .sb-globe-icon{width:22px!important;height:22px!important;border-radius:5px!important;background:#4dd0e1!important;color:#fff!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;}
body .sb-root .sb-globe-icon svg{display:block!important;}
body .sb-root .sb-globe-icon svg circle,body .sb-root .sb-globe-icon svg path{stroke:#fff!important;}
.sb-mob-leagues-panel .sb-flag-icon{width:22px;height:16px;object-fit:cover;border-radius:3px;flex-shrink:0;}
.sb-mob-leagues-panel .sb-tl-live-badge{margin-left:auto;background:rgb(251,51,51);color:rgb(255,255,255);font-family:'Roboto',sans-serif;font-size:12px;font-weight:500;line-height:12px;padding:2px 4px;border:0;border-radius:4px;letter-spacing:0;text-transform:uppercase;}
.sb-root .mc-hdr-live .mc-date--inline{color:rgba(255,255,255,0.75);font-size:12px;font-weight:500;margin-left:2px;white-space:nowrap;}
.sb-root .mc-teams-wrap--side .mc-teams-stacked{flex:1 1 0;display:flex;flex-direction:column;gap:4px;min-width:0;}
.sb-root .mc-teams-wrap--side .mc-team-row--live{display:flex;flex-direction:row;align-items:center;justify-content:flex-start;gap:8px;width:100%;min-height:20px;}
.sb-root .mc-teams-wrap--side .mc-team-row--live .mc-t-name{flex:1 1 0;font-family:'Roboto',sans-serif;font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;margin-right:auto;line-height:18px;}
.sb-root .mc-teams-wrap--side .mc-team-row--live .mc-t-score{flex-shrink:0;font-family:'Roboto',sans-serif;font-size:14px;font-weight:700;color:#fff;min-width:16px;text-align:right;margin-left:auto;padding-left:8px;line-height:18px;}
/* Odds row — fcbet216 reference image 1: tight gap to team rows + lighter buttons */
.sb-root .mc-odds-bot{display:flex!important;gap:6px!important;padding:0 12px 10px!important;align-items:center!important;margin-top:2px!important}
/* Odds chip — fcbet216 EXACT .glRfhP CSS provided by user:
   bg rgb(74,74,74), value rgb(113,246,105), radius 4px, 1px
   transparent border, padding 0 on the wrapper, no shadow. */
.sb-root button.mc-odd-btn,.sb-root .mc-odd-btn{background:rgb(74,74,74)!important;background-color:rgb(74,74,74)!important;background-image:none!important;border:1px solid rgba(255,255,255,0)!important;border-radius:4px!important;height:40px!important;min-height:40px!important;display:flex!important;flex-direction:row!important;align-items:center!important;justify-content:space-between!important;padding:0 12px!important;flex:1 1 0!important;width:auto!important;min-width:0!important;box-shadow:rgba(13,13,13,0) 0px 0px 0px 0px,rgba(13,13,13,0) 0px 0px 0px 0px inset!important;text-shadow:none!important;overflow:hidden!important;position:relative!important;touch-action:manipulation!important;user-select:none!important;-webkit-tap-highlight-color:transparent!important;cursor:pointer!important;color:rgb(113,246,105)!important;text-align:start!important;}
.sb-root .mc-odd-btn .mc-odd-lbl{color:rgba(255,255,255,0.55)!important;font-family:'Roboto',sans-serif!important;font-size:12px!important;font-weight:500!important;}
.sb-root .mc-odd-btn .mc-odd-val{color:rgb(113,246,105)!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:700!important;}
.sb-root .mc-odd-lbl{color:rgba(255,255,255,0.55)!important;font-size:11px!important;font-weight:500!important}
.sb-root .mc-odd-val{color:rgb(113,246,105)!important;font-size:14px!important;font-weight:700!important}
/* Chevron button — same gray family as odds buttons */
.sb-root button.mc-chevron-btn{width:40px!important;height:40px!important;background:rgb(58,58,58)!important;border:1px solid rgba(255,255,255,0.04)!important;border-radius:6px!important;box-shadow:none!important;flex-shrink:0!important;cursor:pointer!important;}
/* Collapse hides only odds btns — chevron stays visible to allow re-expand */
.sb-root .mc.mc-collapsed .mc-odd-btn,.sb-root .mc.mc-collapsed .mc-no-market{display:none!important;}
.sb-root button.mc-chevron-btn.mc-chevron-up svg{transform:rotate(180deg)!important;}
.sb-root button.mc-chevron-btn svg{transition:transform 0.2s ease!important;}
.sb-root .sb-league-section-hdr{background:#1e1e1e!important;border-width:0!important;border-radius:6px!important;margin:12px 0 6px!important;padding:10px 12px!important;display:flex!important;align-items:center!important}
.sb-root .sb-league-block{margin-bottom:8px!important}
/* Championship market tabs — must scroll horizontally, never wrap */
.sb-root .sb-champ-mkt-tabs{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;overflow-y:hidden!important;gap:4px!important;padding-bottom:4px!important;margin-bottom:10px!important;scrollbar-width:none!important;}
/* Old 2-row pill grid is no longer rendered — hide any legacy markup */
.sb-root .sb-champ-cat-grid{display:none!important;}

/* ── Championship market DROPDOWN — fcbet216 .HeaderMarketsSelectorContainer.
   Inline critical CSS so it paints right even before style.css loads, and
   so Bootstrap NEVER wins. Body > .sb-root prefix forces specificity. ── */
body .sb-root .sb-champ-mkt-acc{background:rgb(38,38,38)!important;border:1px solid rgba(255,255,255,0.06)!important;border-radius:8px!important;margin:12px 0!important;overflow:hidden!important;padding:0!important;}
body .sb-root .sb-champ-mkt-acc-hdr{display:flex!important;align-items:center!important;justify-content:space-between!important;width:100%!important;background:transparent!important;border:0!important;padding:14px 16px!important;cursor:pointer!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:600!important;line-height:16px!important;color:#fff!important;text-align:left!important;outline:none!important;box-shadow:none!important;}
body .sb-root .sb-champ-mkt-acc-lbl{flex:1 1 auto!important;text-align:left!important;color:#fff!important;}
body .sb-root .sb-champ-mkt-acc-tgl{flex:0 0 auto!important;font-size:18px!important;font-weight:700!important;color:rgba(255,255,255,0.85)!important;width:16px!important;text-align:center!important;line-height:1!important;margin-left:12px!important;}
body .sb-root .sb-champ-mkt-acc-body{padding:0!important;border-top:1px solid rgba(255,255,255,0.06)!important;display:flex!important;flex-direction:column!important;background:transparent!important;}
body .sb-root .sb-champ-mkt-acc.collapsed .sb-champ-mkt-acc-body{display:none!important;}
/* Each option row — fcbet216 .SelectMenuOptionContainer EXACT */
body .sb-root .sb-champ-mkt-opt,body .sb-root button.sb-champ-mkt-opt{display:block!important;width:100%!important;text-align:start!important;padding:14px 16px!important;border:0!important;border-radius:2px!important;background:transparent!important;background-color:transparent!important;background-image:none!important;color:rgb(255,255,255)!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:500!important;line-height:16px!important;cursor:pointer!important;outline:none!important;box-shadow:none!important;margin:0!important;-webkit-appearance:none!important;-moz-appearance:none!important;appearance:none!important;text-transform:none!important;letter-spacing:0!important;}
body .sb-root .sb-champ-mkt-opt + .sb-champ-mkt-opt{border-top:1px solid rgba(255,255,255,0.06)!important;}
body .sb-root .sb-champ-mkt-opt:hover{background:rgba(255,255,255,0.05)!important;}
body .sb-root .sb-champ-mkt-opt:focus,body .sb-root .sb-champ-mkt-opt:active{background:rgba(255,255,255,0.06)!important;outline:none!important;}
/* Sport nav — fcbet216 design: rounded contained strip with tile cards
   and left/right scroll chevrons. Inline critical CSS matches style.css
   so there's no flicker on first paint. */
.sb-sport-nav{position:relative;background:transparent;border:none;height:auto;min-height:70px;flex-shrink:0;overflow:visible;padding:0;margin:12px 5px 8px 12px;}
.sb-sport-nav-inner{display:flex;align-items:center;height:70px;gap:6px;padding:7px 36px;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:none;-ms-overflow-style:none;background:#1a1a1a;border:1px solid rgba(255,255,255,0.06);border-radius:10px;scroll-behavior:smooth;width:100%;}
.sb-sport-nav-inner::-webkit-scrollbar{display:none;height:0!important;width:0!important;}
.sb-sport-nav-inner::-webkit-scrollbar-thumb,.sb-sport-nav-inner::-webkit-scrollbar-track{display:none!important;}
#sb-sport-nav-list{display:contents;}
/* Scroll chevrons */
/* Sport nav — EXACT fcbet216 spec (user-provided DevTools CSS) */
.sb-sport-nav-inner{background:rgb(49,49,49)!important;border:none!important;border-radius:6px!important;padding:8px 44px!important;gap:8px!important;}
.sb-sport-item{background:rgb(58,58,58)!important;border:1px solid rgba(255,255,255,0.04)!important;border-radius:4px!important;padding:8px!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;min-width:72px!important;height:56px!important;}
.sb-nav-arrow{display:flex;position:absolute;top:50%;transform:translateY(-50%);width:32px!important;height:32px!important;border-radius:100px!important;background:rgba(255,255,255,0.54)!important;border:none!important;color:rgba(0,0,0,0.87)!important;align-items:center;justify-content:center;cursor:pointer;z-index:3;padding:0!important;margin:0!important;box-shadow:none!important;}
.sb-nav-arrow.is-hidden{opacity:0;pointer-events:none;}
.sb-nav-arrow.left{left:8px;} .sb-nav-arrow.right{right:8px;}
.sb-nav-arrow svg{width:16px!important;height:16px!important;display:block;}
/* Sport nav tiles — default WHITE label + WHITE svg (idle); green only when user explicitly picks. */
.sb-root .sb-sport-item{background:rgb(58,58,58);border:1px solid rgba(255,255,255,0.04);border-radius:4px;padding:8px;display:grid;grid-template-rows:auto auto;justify-items:center;align-content:space-around;row-gap:4px;min-width:72px;height:56px;color:#ffffff;font-family:'Roboto',sans-serif;font-size:14px;cursor:pointer;flex-shrink:0;}
.sb-root .sb-sport-item.active{background:#70f669;color:rgba(0,0,0,.87);border-color:#70f669;font-weight:700;}
.sb-root .sb-sport-item .sb-sport-icon,.sb-root .sb-sport-item .sb-sport-icon svg{display:flex;width:20px;height:20px;overflow:visible;}
.sb-root .sb-sport-item .sb-sport-icon svg{filter:brightness(0) invert(1);opacity:1;}
.sb-root .sb-sport-item.active .sb-sport-icon svg{filter:brightness(0);opacity:1;}
/* Sport page (after tile click) — header + category cards */
.sb-root .sb-sport-page{display:flex;flex-direction:column;gap:10px;padding:0 2px;}
.sb-root .sb-sport-page-header{display:flex;align-items:center;gap:10px;margin:4px 0 6px;}
.sb-root .sb-sp-back{width:40px;height:40px;border-radius:8px;background:rgb(58,58,58);border:1px solid rgba(255,255,255,0.04);color:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0;flex-shrink:0;}
.sb-root .sb-sp-pill{display:inline-flex;align-items:center;background:#70f669;color:rgba(0,0,0,.87);font-weight:700;font-size:14px;padding:8px 18px;border-radius:8px;line-height:1;}
.sb-root .sb-cat-card{display:grid;grid-template-columns:24px 1fr auto;align-items:center;gap:12px;width:100%;background:rgb(49,49,49);border:1px solid rgba(255,255,255,0.04);border-radius:10px;padding:14px 16px;color:#fff;cursor:pointer;text-align:left;}
.sb-root .sb-cat-card:hover{background:rgb(58,58,58);}
.sb-root .sb-cat-ico{display:inline-flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.85);}
.sb-root .sb-cat-label{font-size:14px;font-weight:600;color:#fff;letter-spacing:.2px;}
.sb-root .sb-cat-count{font-size:13px;color:rgba(255,255,255,0.65);font-variant-numeric:tabular-nums;}
.sb-root .sb-sk-cat-card{height:52px;background:linear-gradient(90deg,rgba(255,255,255,0.04),rgba(255,255,255,0.08),rgba(255,255,255,0.04));background-size:200% 100%;animation:sb-sp-shimmer 1.4s linear infinite;border-radius:10px;}
@keyframes sb-sp-shimmer{0%{background-position:200% 0;}100%{background-position:-200% 0;}}
/* Sport filter pills — Bootstrap 3 collapses SVG icons */
.sb-root .sb-upcoming-tabs{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:6px!important;padding-bottom:8px!important;}
.sb-root .sb-upcoming-tab{display:inline-flex!important;align-items:center!important;gap:6px!important;height:38px!important;white-space:nowrap!important;flex-shrink:0!important;background:rgb(49,49,49)!important;border:1px solid rgba(255,255,255,0.04)!important;border-radius:12px!important;padding:0 16px!important;font-size:13px!important;font-weight:600!important;color:#fff!important;box-shadow:none!important;}
.sb-root .sb-upcoming-tab.active{background:#70f669!important;border-color:#70f669!important;color:rgba(0,0,0,.87)!important;}
.sb-root .sb-upcoming-tab .sb-tab-icon{display:inline-flex!important;align-items:center!important;width:16px!important;height:16px!important;flex-shrink:0!important;overflow:visible!important;}
.sb-root .sb-upcoming-tab .sb-tab-icon svg{display:block!important;width:16px!important;height:16px!important;filter:brightness(0) invert(1)!important;opacity:.8!important;overflow:visible!important;}
.sb-root .sb-upcoming-tab.active .sb-tab-icon svg{filter:brightness(0)!important;opacity:1!important;}
/* Championship top tabs — horizontal scroll */
.sb-root .sb-champ-top-tabs{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;gap:0!important;scrollbar-width:none!important;}
/* Sport-page mode — hide home sections so the sport view is clean */
.sb-root[data-view="sportpage"] .sb-date-row,
.sb-root[data-view="sportpage"] .sb-favoris-row,
.sb-root[data-view="sportpage"] .sb-favoris-content,
.sb-root[data-view="sportpage"] .sb-sport-nav,
.sb-root[data-view="sportpage"] .sb-en-direct-row,
.sb-root[data-view="sportpage"] #sb-en-direct-cards,
.sb-root[data-view="sportpage"] .sb-boost-section,
.sb-root[data-view="sportpage"] #sb-boost-section,
.sb-root[data-view="sportpage"] #sb-boost-dots,
.sb-root[data-view="sportpage"] .sb-mob-leagues-panel{display:none!important;}
/* EN DIRECT tab — clean live-only view (matches fcbet216 mobile live page) */
.sb-root[data-view="livepage"] .sb-date-row,
.sb-root[data-view="livepage"] .sb-favoris-row,
.sb-root[data-view="livepage"] .sb-favoris-content,
.sb-root[data-view="livepage"] .sb-sport-nav,
.sb-root[data-view="livepage"] .sb-en-direct-row,
.sb-root[data-view="livepage"] #sb-en-direct-cards,
.sb-root[data-view="livepage"] .sb-boost-section,
.sb-root[data-view="livepage"] #sb-boost-section,
.sb-root[data-view="livepage"] #sb-boost-dots,
.sb-root[data-view="livepage"] .sb-mob-leagues-panel,
.sb-root[data-view="livepage"] .sb-mob-inline-leagues,
.sb-root[data-view="livepage"] .sb-points-forts-block{display:none!important;}
.sb-root[data-view="livepage"] #sb-matches-body{padding-top:8px!important;}
/* PERIOD PAGE (fcbet216 /sportsbook/prelive?page=period&date=...) —
   clicking a future date pill switches into this view. Keep ONLY the
   date row at top. Hide the BIG sport-nav tile row (we render compact
   sport pills inside the period view instead) + every home-only block. */
.sb-root[data-view="periodpage"] .sb-favoris-row,
.sb-root[data-view="periodpage"] .sb-favoris-content,
.sb-root[data-view="periodpage"] .sb-sport-nav,
.sb-root[data-view="periodpage"] .sb-en-direct-row,
.sb-root[data-view="periodpage"] #sb-en-direct-cards,
.sb-root[data-view="periodpage"] .sb-boost-section,
.sb-root[data-view="periodpage"] #sb-boost-section,
.sb-root[data-view="periodpage"] #sb-boost-dots,
.sb-root[data-view="periodpage"] .sb-mob-search-panel,
.sb-root[data-view="periodpage"] .sb-mob-leagues-panel,
.sb-root[data-view="periodpage"] .sb-mob-inline-leagues,
.sb-root[data-view="periodpage"] .sb-points-forts-block{display:none!important;}
.sb-root[data-view="periodpage"] #sb-matches-body{padding-top:8px!important;}
/* Period page — market shortcut grid (Populaire / 1x2 / Total / Double chance
   / Les deux équipes qui marquent / Voir tous les marchés) — 2 cells per row */
.sb-root .sb-period-shortcuts{display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important;margin:8px 0!important;}
.sb-root .sb-period-shortcut{background:rgb(49,49,49)!important;color:#fff!important;border:0!important;border-radius:4px!important;padding:14px 12px!important;font-family:'Roboto',sans-serif!important;font-size:13px!important;font-weight:600!important;line-height:1.2!important;cursor:pointer!important;text-align:center!important;outline:none!important;}
.sb-root .sb-period-shortcut.active{background:rgb(113,246,105)!important;color:#000!important;font-weight:700!important;}
/* Period page — league accordions: bg rgb(49,49,49), no radius, compact h */
.sb-root[data-view="periodpage"] .sb-league-acc{background:rgb(49,49,49)!important;border:0!important;border-radius:0!important;margin-top:6px!important;}
.sb-root[data-view="periodpage"] .sb-league-acc:first-child{margin-top:4px!important;}
.sb-root[data-view="periodpage"] .sb-league-acc-hdr{padding:8px 12px!important;min-height:36px!important;}
.sb-root[data-view="periodpage"] .sb-league-acc-body{background:transparent!important;border-top:0!important;padding:4px 0 6px!important;}
/* Compact sport pills row inside the period view */
.sb-root .sb-period-sport-tabs{margin:6px 0 10px!important;}
/* fcbet216 .TopSportBarButton / .fA-dBjY EXACT — sport-nav tiles */
.sb-root .sb-sport-item{background:rgb(74,74,74)!important;border:0!important;box-shadow:0 2px 4px rgba(0,0,0,0.25),0 1px 2px rgba(0,0,0,0.18)!important;border-radius:4px!important;padding:8px!important;display:grid!important;grid-template-rows:auto auto!important;justify-items:center!important;justify-content:space-around!important;gap:4px!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:500!important;line-height:15px!important;color:rgb(255,255,255)!important;width:auto!important;min-width:72px!important;height:56px!important;margin:0!important;-webkit-tap-highlight-color:transparent!important;}
.sb-root .sb-sport-item:hover{background:rgb(82,82,82)!important;box-shadow:0 3px 6px rgba(0,0,0,0.3),0 1px 3px rgba(0,0,0,0.22)!important;}
.sb-root .sb-sport-item.active{background:var(--sb-green)!important;color:var(--sb-green-font)!important;border:0!important;font-weight:700!important;}
/* fcbet216 .HeaderMarketsSelectorContainer + .hMbNfr EXACT — 1x2 dropdown */
.sb-root .sb-champ-mkt-acc{background:rgb(49,49,49)!important;border:0!important;border-radius:4px!important;padding:4px!important;margin:12px 0!important;overflow:visible!important;}
.sb-root .sb-champ-mkt-acc-hdr{display:grid!important;grid-template-columns:1fr 20px!important;place-items:center!important;width:100%!important;background:rgb(74,74,74)!important;border:0!important;border-radius:4px!important;padding:12px 10px!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:500!important;line-height:16px!important;color:rgb(255,255,255)!important;cursor:pointer!important;outline:none!important;box-shadow:rgba(13,13,13,0) 0 0 0 0,rgba(13,13,13,0) 0 0 0 0 inset!important;text-shadow:rgba(13,13,13,0) 0 0 0!important;margin:0!important;}
.sb-root .sb-champ-mkt-acc-lbl{text-align:center!important;color:rgb(255,255,255)!important;}
.sb-root .sb-champ-mkt-acc-tgl{font-size:14px!important;font-weight:500!important;color:rgba(255,255,255,0.85)!important;width:20px!important;text-align:center!important;line-height:1!important;margin:0!important;}
.sb-root .sb-champ-mkt-acc-body{padding:4px 0 0!important;border-top:0!important;display:flex!important;flex-direction:column!important;gap:4px!important;background:transparent!important;}
.sb-root .sb-champ-mkt-acc.collapsed .sb-champ-mkt-acc-body{display:none!important;}
body .sb-root .sb-champ-mkt-opt,body .sb-root button.sb-champ-mkt-opt{display:grid!important;grid-template-columns:1fr 20px!important;place-items:center!important;width:100%!important;padding:12px 10px!important;border:0!important;border-radius:4px!important;background:rgb(74,74,74)!important;background-color:rgb(74,74,74)!important;background-image:none!important;color:rgb(255,255,255)!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:500!important;line-height:16px!important;cursor:pointer!important;outline:none!important;box-shadow:rgba(13,13,13,0) 0 0 0 0,rgba(13,13,13,0) 0 0 0 0 inset!important;text-shadow:rgba(13,13,13,0) 0 0 0!important;margin:0!important;-webkit-appearance:none!important;-moz-appearance:none!important;appearance:none!important;text-transform:none!important;letter-spacing:0!important;}
body .sb-root .sb-champ-mkt-opt+.sb-champ-mkt-opt{border-top:0!important;}
body .sb-root .sb-champ-mkt-opt:hover,body .sb-root .sb-champ-mkt-opt:focus,body .sb-root .sb-champ-mkt-opt:active{background:rgb(82,82,82)!important;background-color:rgb(82,82,82)!important;outline:none!important;}
body .sb-root .sb-champ-mkt-opt.active{background:rgb(113,246,105)!important;background-color:rgb(113,246,105)!important;color:rgba(0,0,0,0.87)!important;}
/* INLINE MATCH MARKETS — chevron on a card expands tabs + accordions
   (Principaux / Bet Builder / Teams H2H / 1 minute) inside the card. */
.sb-root .mc-inline-md{margin:6px 0 0!important;padding:0!important;background:transparent!important;border-top:1px solid rgba(255,255,255,0.05)!important;}
.sb-root .mc-inline-md:empty{display:none!important;}
.sb-root .mc-inline-md .mc-md-loading,.sb-root .mc-inline-md .mc-md-empty{padding:12px!important;color:rgba(255,255,255,0.55)!important;font-size:13px!important;text-align:center!important;}
.sb-root .mc-inline-md .mc-md-inner{padding:6px 4px 4px!important;}
.sb-root .mc-inline-md .mc-md-tabs{display:flex!important;flex-wrap:nowrap!important;align-items:center!important;gap:6px!important;overflow-x:auto!important;scrollbar-width:none!important;padding:4px 0 8px!important;}
.sb-root .mc-inline-md .mc-md-tabs::-webkit-scrollbar{display:none!important;}
.sb-root .mc-inline-md .mc-md-tab{flex:0 0 auto!important;background:rgb(49,49,49)!important;color:rgba(255,255,255,0.85)!important;border:0!important;border-radius:4px!important;padding:8px 14px!important;font-family:'Roboto',sans-serif!important;font-size:12px!important;font-weight:600!important;cursor:pointer!important;outline:none!important;white-space:nowrap!important;}
.sb-root .mc-inline-md .mc-md-tab.active{background:rgb(113,246,105)!important;color:#000!important;font-weight:700!important;}
.sb-root .mc-inline-md .mc-md-info{flex:0 0 auto!important;width:22px!important;height:22px!important;border-radius:50%!important;background:transparent!important;border:1.5px solid #f04a4a!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;cursor:pointer!important;margin-left:2px!important;}
.sb-root .mc-inline-md .mc-md-markets{padding:0!important;}
.sb-root .mc-inline-md .md-market-group{margin-top:8px!important;}
.sb-root .mc-inline-md .md-market-group:first-child{margin-top:0!important;}
.sb-root .mc-inline-md .mc-sk{display:inline-block!important;background:linear-gradient(90deg,rgba(255,255,255,0.06),rgba(255,255,255,0.10),rgba(255,255,255,0.06))!important;background-size:200% 100%!important;border-radius:4px!important;animation:mcSkShimmer 1.2s ease-in-out infinite!important;}
.sb-root .mc-inline-md .mc-sk-tab{height:32px!important;border-radius:4px!important;}
.sb-root .mc-inline-md .mc-sk-row{display:block!important;width:100%!important;height:56px!important;margin-top:8px!important;}
.sb-root .mc-inline-md .mc-sk-row:first-child{margin-top:0!important;}
@keyframes mcSkShimmer{0%{background-position:200% 0;}100%{background-position:-200% 0;}}
/* Match-detail view: hide ALL home siblings + the mobile topbar / date /
 * favoris / sport nav / live carousel / boost section so the match-detail
 * header is the only thing on screen (fcbet216 parity). */
.sb-root[data-view="matchdetail"] .sb-date-row,
.sb-root[data-view="matchdetail"] .sb-favoris-row,
.sb-root[data-view="matchdetail"] .sb-favoris-content,
.sb-root[data-view="matchdetail"] .sb-sport-nav,
.sb-root[data-view="matchdetail"] .sb-en-direct-row,
.sb-root[data-view="matchdetail"] #sb-en-direct-cards,
.sb-root[data-view="matchdetail"] .sb-boost-section,
.sb-root[data-view="matchdetail"] #sb-boost-section,
.sb-root[data-view="matchdetail"] #sb-boost-dots,
.sb-root[data-view="matchdetail"] .sb-mob-leagues-panel,
.sb-root[data-view="matchdetail"] .sb-mobile-topbar,
.sb-root[data-view="matchdetail"] .sb-upcoming-tabs,
.sb-root[data-view="matchdetail"] .sb-sport-page,
.sb-root[data-view="matchdetail"] .sb-mob-inline-leagues{display:none!important;}
.sb-root[data-view="matchdetail"] #sb-matches-body{margin-top:0!important;padding-top:0!important;}
/* Cotes boostées — dark card header matching fcbet216 (image 3) */
.sb-root .sb-boost-header,body .sb-root .sb-boost-header{display:flex!important;align-items:center!important;gap:8px!important;font-size:14px!important;font-weight:700!important;color:#fff!important;margin:0 0 8px!important;padding:12px 14px!important;letter-spacing:.2px!important;line-height:1.2!important;background:rgb(49,49,49)!important;border:1px solid rgba(255,255,255,0.07)!important;border-radius:6px!important;}
.sb-root .sb-boost-header svg{width:15px!important;height:15px!important;color:#fff!important;fill:#fff!important;flex-shrink:0!important;}
/* Circular floating bet slip FAB — fcbet216 reference image */
.sb-floating-bet-badge,body .sb-root .sb-floating-bet-badge{display:none;position:fixed;bottom:88px;right:16px;z-index:3000;flex-direction:column;align-items:center;gap:4px;cursor:pointer;font-family:'Roboto',sans-serif;user-select:none;-webkit-tap-highlight-color:transparent;}
.sb-fb-circle,body .sb-root .sb-fb-circle{position:relative;width:52px;height:52px;border-radius:50%;background:#2c2c2c;border:1px solid rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,0.55);}
.sb-fb-circle svg{color:rgba(255,255,255,0.88);width:22px;height:22px;}
.sb-fb-count,body .sb-root .sb-fb-count{position:absolute;top:-6px;right:-6px;min-width:22px;height:22px;padding:0 6px;background:rgb(113,246,105);color:#000;border-radius:999px;font-size:11px;font-weight:800;line-height:22px;text-align:center;border:2px solid #1e1e1e;box-shadow:0 2px 4px rgba(0,0,0,0.4);}
.sb-fb-label,body .sb-root .sb-fb-label{font-size:11px;font-weight:600;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,0.7);white-space:nowrap;}
/* ── Fiche de Pari — critical inline (fcbet216 reference) ── */
body .sb-root .slip-tabs,.sb-root .slip-tabs{display:flex!important;gap:4px!important;padding:10px 12px 0!important;background:#121212!important;}
body .sb-root .slip-tab,.sb-root .slip-tab{flex:1!important;background:transparent!important;border:none!important;border-radius:6px 6px 0 0!important;color:rgba(255,255,255,0.5)!important;font-size:13px!important;padding:11px 6px!important;cursor:pointer!important;}
body .sb-root .slip-tab.active,.sb-root .slip-tab.active{background:rgb(49,49,49)!important;color:#fff!important;font-weight:600!important;}
body .sb-root .slip-item,.sb-root .slip-item{margin:10px 12px!important;padding:0!important;border-radius:8px!important;background:rgb(49,49,49)!important;border:none!important;overflow:hidden!important;}
body .sb-root .slip-item.combi,.sb-root .slip-item.combi{background:rgba(120,75,65,0.35)!important;}
body .sb-root .slip-item-body,.sb-root .slip-item-body{padding:12px 14px 14px!important;}
body .sb-root .slip-item-hdr,.sb-root .slip-item-hdr{display:flex!important;align-items:flex-start!important;gap:8px!important;padding:12px 14px 0!important;}
body .sb-root .slip-match-nm,.sb-root .slip-match-nm{font-size:13px!important;font-weight:700!important;color:#fff!important;flex:1!important;}
body .sb-root .slip-market-nm,.sb-root .slip-market-nm{font-size:11px!important;color:rgba(255,255,255,0.45)!important;margin:6px 0 4px!important;padding:0!important;}
body .sb-root .slip-sel-row,.sb-root .slip-sel-row{display:flex!important;align-items:center!important;margin:0 0 10px!important;padding:0!important;}
body .sb-root .slip-sel-lbl,.sb-root .slip-sel-lbl{font-size:15px!important;font-weight:700!important;color:#fff!important;flex:1!important;}
body .sb-root .slip-stake-row,.sb-root .slip-stake-row{display:flex!important;align-items:center!important;gap:12px!important;margin:4px 0 0!important;padding:0!important;}
body .sb-root .slip-stake-inp,.sb-root .slip-stake-inp{width:96px!important;min-width:96px!important;height:40px!important;background:rgb(38,38,38)!important;border:1px solid rgba(255,255,255,0.14)!important;border-radius:6px!important;color:#fff!important;font-size:14px!important;font-weight:600!important;padding:0 12px!important;text-align:left!important;box-shadow:none!important;-webkit-appearance:none!important;}
body .sb-root .slip-stake-inp:focus,body .sb-root .slip-stake-inp.active,.sb-root .slip-stake-inp:focus,.sb-root .slip-stake-inp.active{border-color:rgb(113,246,105)!important;}
body .sb-root .slip-gagner,.sb-root .slip-gagner{font-size:12px!important;color:rgba(255,255,255,0.55)!important;margin-left:auto!important;}
body .sb-root .slip-gagner strong,.sb-root .slip-gagner strong{color:rgb(113,246,105)!important;font-weight:700!important;}
body .sb-root .slip-promo,.sb-root .slip-promo{margin:10px 12px!important;padding:12px 14px!important;border:1px solid rgba(112,246,105,0.3)!important;border-radius:8px!important;}
body .sb-root .slip-clear-btn,.sb-root .slip-clear-btn{width:calc(100% - 24px)!important;margin:8px 12px!important;padding:12px!important;border:1px solid rgba(255,255,255,0.14)!important;border-radius:8px!important;color:rgb(113,246,105)!important;}
body .sb-root .slip-summary,.sb-root .slip-summary{border-top:1px solid rgba(255,255,255,0.08)!important;padding:12px 14px!important;}
body .sb-root .slip-place-btn,.sb-root .slip-place-btn{background:rgb(113,246,105)!important;background-color:rgb(113,246,105)!important;color:#000!important;border:0!important;border-radius:8px!important;font-weight:800!important;display:flex!important;align-items:center!important;justify-content:center!important;}
.slip-stake-editor{display:none!important;}
.slip-stake-editor.slip-stake-editor--open{display:block!important;}
@media(min-width:1101px){.slip-stake-editor{display:none!important;}}
/* ── Virtual stake numpad — critical inline CSS, fcbet216 image 2 ── */
.slip-quick-stakes,body .sb-root .slip-quick-stakes{display:grid!important;grid-template-columns:repeat(4,1fr)!important;gap:8px!important;padding:0 12px 12px!important;}
.slip-quick-stake,body .sb-root .slip-quick-stake{background:rgb(49,49,49)!important;border:1px solid rgba(255,255,255,0.05)!important;border-radius:6px!important;color:rgb(113,246,105)!important;font-family:'Roboto',sans-serif!important;font-size:14px!important;font-weight:700!important;padding:12px 0!important;cursor:pointer!important;outline:none!important;}
.slip-numpad,body .sb-root .slip-numpad{display:grid!important;grid-template-columns:repeat(6,1fr) 1.4fr!important;gap:8px!important;padding:0 12px 12px!important;font-family:'Roboto',sans-serif!important;}
.slip-np-key,body .sb-root .slip-np-key{background:rgb(49,49,49)!important;border:1px solid rgba(255,255,255,0.05)!important;border-radius:6px!important;color:rgb(113,246,105)!important;font-size:17px!important;font-weight:700!important;padding:12px 0!important;cursor:pointer!important;outline:none!important;min-height:44px!important;}
.slip-np-del,body .sb-root .slip-np-del{display:flex!important;align-items:center!important;justify-content:center!important;color:rgba(255,255,255,0.75)!important;}
.slip-np-ok,body .sb-root .slip-np-ok{grid-row:1/span 2!important;grid-column:7!important;background:rgb(49,49,49)!important;border:1px solid rgba(255,255,255,0.05)!important;border-radius:6px!important;color:rgb(113,246,105)!important;font-size:18px!important;font-weight:800!important;cursor:pointer!important;outline:none!important;align-self:stretch!important;}
.sb-root .sb-boost-row{padding:0;margin-bottom:4px;}
.sb-root .sb-boost-card{padding:10px 14px 12px;}
.sb-root .sb-boost-card .sb-teams-text{margin:2px 0 6px;line-height:1.25;}
.sb-root .sb-boost-card .sb-boost-picks{margin:0 0 4px;}
.sb-root .sb-boost-card .sb-boost-line{position:relative;display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.85);min-height:18px;padding:1px 0;line-height:1.25;}
.sb-root .sb-boost-dot{position:relative;width:8px;height:8px;border-radius:50%;background:#70f669;display:inline-block;flex-shrink:0;z-index:2;margin:0;}
.sb-root .sb-boost-card .sb-boost-picks > .sb-boost-line:not(:last-child) .sb-boost-dot::after{content:"";position:absolute;left:50%;top:100%;width:2px;height:12px;transform:translateX(-50%);background:#70f669;border-radius:1px;z-index:1;}
.sb-root .sb-odds-row{padding:6px 14px;margin-top:8px;}

/* ── Match-detail tabs row — critical inline CSS so Bootstrap doesn't
 * collapse pill spacing ("Bet Builder1 minute2ème mi-ter"). Mirrors
 * the style.css rules with !important on every property to be safe. */
.md-tabs-wrap{display:flex!important;align-items:center!important;gap:8px!important;background:#0f0f0f!important;border-bottom:1px solid rgba(255,255,255,.06)!important;padding:8px 10px!important;min-height:50px!important;}
.md-tabs-row{display:flex!important;align-items:center!important;gap:8px!important;overflow-x:auto!important;overflow-y:hidden!important;flex:1 1 auto!important;padding:0!important;margin:0!important;min-width:0!important;-ms-overflow-style:none;scrollbar-width:none;}
.md-tabs-row::-webkit-scrollbar{display:none!important;height:0!important;width:0!important;}
.md-tab{background:rgba(255,255,255,.05)!important;border:1px solid rgba(255,255,255,.06)!important;color:rgba(255,255,255,.85)!important;cursor:pointer;padding:8px 14px!important;font-size:13px!important;font-family:'Roboto',sans-serif!important;font-weight:500!important;flex-shrink:0!important;flex-grow:0!important;white-space:nowrap!important;outline:none!important;border-radius:8px!important;margin:0!important;line-height:1.2!important;height:34px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;box-shadow:none!important;text-shadow:none!important;}
.md-tab.active{background:#70f669!important;border-color:#70f669!important;color:#000!important;font-weight:700!important;border-radius:8px!important;margin:0!important;padding:8px 14px!important;}
.md-tab-search-btn,.md-tab-search-close{background:#70f669!important;border:none!important;color:#000!important;cursor:pointer;padding:0!important;margin:0!important;width:34px!important;height:34px!important;min-width:34px!important;border-radius:8px!important;outline:none!important;display:flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;box-shadow:none!important;}
.md-tab-search-btn svg,.md-tab-search-close svg{width:14px!important;height:14px!important;color:#000!important;}
.md-search-panel{display:none!important;align-items:center!important;gap:8px!important;flex:1 1 auto!important;min-width:0!important;padding:0!important;margin:0!important;}
.md-tabs-wrap.md-tabs-wrap--search .md-search-panel{display:flex!important;}
.md-tabs-wrap.md-tabs-wrap--search .md-tabs-row,
.md-tabs-wrap.md-tabs-wrap--search .md-tab-search-btn,
.md-tabs-wrap.md-tabs-wrap--search .md-tab-info-btn{display:none!important;}
.md-search-input{flex:1 1 auto!important;min-width:0!important;background:#2a2a2a!important;border:1px solid rgba(255,255,255,.08)!important;color:#fff!important;border-radius:8px!important;padding:8px 12px!important;font-size:13px!important;font-family:'Roboto',sans-serif!important;outline:none!important;height:34px!important;box-shadow:none!important;}
.md-tab-info-btn{background:transparent!important;border:1.5px solid #f04a4a!important;color:#f04a4a!important;cursor:pointer;padding:0!important;width:28px!important;height:28px!important;min-width:28px!important;border-radius:50%!important;outline:none!important;display:flex!important;align-items:center!important;justify-content:center!important;flex-shrink:0!important;margin:0!important;box-shadow:none!important;}
.md-tab-info-btn svg{width:14px!important;height:14px!important;color:#f04a4a!important;}

/* Match-detail market cards + odd buttons — critical inline CSS so
 * the layout is correct even before style.css loads. Selectors are
 * doubled up (`.sb-root button.md-odd-btn` + `.md-odd-btn`) to bump
 * specificity past the `.sb-root button { background: transparent }`
 * reset that was making the buttons look flat in production. */
.md-markets,.sb-root .md-markets{padding:10px 10px 80px!important;background:#0f0f0f!important;}
.sb-root .md-market-group,.md-market-group{background:rgb(49,49,49)!important;background-color:rgb(49,49,49)!important;border:0!important;border-color:rgba(255,255,255,0)!important;border-radius:6px!important;margin-bottom:8px!important;overflow:hidden;box-shadow:rgba(13,13,13,0) 0 0 0 0!important;}
.sb-root .md-mkt-body,.md-mkt-body{padding:2px 10px 12px!important;display:flex!important;flex-direction:column!important;gap:6px!important;}
.sb-root .md-mkt-row,.md-mkt-row{display:flex!important;gap:8px!important;align-items:stretch!important;}
.sb-root button.md-odd-btn,.sb-root .md-odd-btn,button.md-odd-btn,.md-odd-btn{flex:1 1 0!important;min-width:0!important;background:rgb(74,74,74)!important;background-color:rgb(74,74,74)!important;background-image:none!important;border:1px solid rgba(255,255,255,.04)!important;border-radius:6px!important;padding:10px 14px!important;display:flex!important;justify-content:space-between!important;align-items:center!important;cursor:pointer!important;font-family:'Roboto',sans-serif!important;outline:none!important;min-height:44px!important;height:44px!important;gap:10px!important;box-shadow:none!important;text-shadow:none!important;margin:0!important;color:#fff!important;}
.sb-root button.md-odd-btn:hover,.sb-root .md-odd-btn:hover,.md-odd-btn:hover{background:rgb(82,82,82)!important;background-color:rgb(82,82,82)!important;border-color:rgba(255,255,255,.08)!important;}
.md-mkt-hdr{display:flex!important;align-items:center!important;padding:12px 14px 10px!important;gap:10px!important;border-bottom:0!important;font-family:'Roboto',sans-serif!important;}
.md-mkt-star{color:rgba(255,255,255,.35)!important;flex-shrink:0;}
.md-mkt-star svg{width:14px!important;height:14px!important;}
.md-mkt-name{font-size:14px!important;font-weight:600!important;flex:1!important;color:#fff!important;line-height:1.2!important;font-family:'Roboto',sans-serif!important;}
.md-mkt-bb{background:#70f669!important;color:#000!important;font-size:10px!important;font-weight:800!important;padding:2px 6px!important;border-radius:3px!important;flex-shrink:0;letter-spacing:.2px!important;line-height:1.2!important;font-family:'Roboto',sans-serif!important;}
.md-mkt-ctrl{color:rgba(255,255,255,.45)!important;flex-shrink:0;display:flex;align-items:center;}
.md-mkt-ctrl svg{width:14px!important;height:14px!important;}
.md-o-name{font-size:14px!important;color:#fff!important;flex:1 1 auto!important;text-align:left!important;font-weight:500!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:'Roboto',sans-serif!important;line-height:1.2!important;}
.md-o-val{font-size:15px!important;font-weight:700!important;color:rgb(113,246,105)!important;display:inline-flex;align-items:center;gap:4px;font-family:'Roboto',sans-serif!important;line-height:1.2!important;}
.md-slider-wrap{display:flex!important;flex-direction:column!important;align-items:center!important;gap:6px!important;padding:10px 4px 6px!important;width:100%!important;}
.md-slider-val{font-size:14px!important;color:#70f669!important;font-weight:700!important;text-align:center!important;font-family:'Roboto',sans-serif!important;line-height:1!important;}
.md-slider{width:100%!important;-webkit-appearance:none!important;appearance:none!important;height:6px!important;background:linear-gradient(to right,#70f669 50%,rgba(255,255,255,.18) 50%)!important;border-radius:3px!important;outline:none!important;cursor:pointer!important;margin:0!important;}
.md-slider::-webkit-slider-thumb{-webkit-appearance:none!important;appearance:none!important;width:18px!important;height:18px!important;background:#70f669!important;border-radius:50%!important;cursor:pointer!important;box-shadow:0 0 0 2px rgba(255,255,255,.08),0 0 8px rgba(112,246,105,.6)!important;border:none!important;}
.md-slider::-moz-range-thumb{width:18px!important;height:18px!important;background:#70f669!important;border-radius:50%!important;cursor:pointer!important;border:none!important;}
.md-o-arrow{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;line-height:1;font-size:9px;}
.md-o-arrow--up{color:#2bcd62;}
.md-o-arrow--down{color:#f04a4a;}
@keyframes mdOddFlashUp{0%{background:rgba(43,205,98,.22);}100%{background:rgb(56,56,56);}}
@keyframes mdOddFlashDown{0%{background:rgba(240,74,74,.22);}100%{background:rgb(56,56,56);}}
.md-odd-btn.md-odd-flash--up{animation:mdOddFlashUp 2.4s ease-out 1;}
.md-odd-btn.md-odd-flash--down{animation:mdOddFlashDown 2.4s ease-out 1;}
</style>

<!-- Loading splash — visible from the very first paint until app.js
     finishes the initial render. Removed by sbHideBootSplash() in app.js. -->
<div id="sb-boot-splash"><div class="ring"></div></div>

<div class="sb-root">

<!-- ══ LEFT SIDEBAR ══ -->
<aside class="sb-left" id="sb-left">
  <div class="sb-sidebar-top">
    <div class="sb-top-bar">
      <button class="sb-btn-home active" onclick="window.sbSwitchTab(this,'inplay',1)"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 14.6666V7.99992H10V14.6666M2 5.99992L8 1.33325L14 5.99992V13.3333C14 13.6869 13.8595 14.026 13.6095 14.2761C13.3594 14.5261 13.0203 14.6666 12.6667 14.6666H3.33333C2.97971 14.6666 2.64057 14.5261 2.39052 14.2761C2.14048 14.026 2 13.6869 2 13.3333V5.99992Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
      <button class="sb-btn-live" onclick="window.sbSwitchTab(this,'live',1)"><span class="sb-live-badge">EN DIRECT</span></button>
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
    <!-- Search card — above leagues (fcbet216 sidebar order) -->
    <div class="sb-sidebar-search-card">
      <div class="sb-search-wrap">
        <div class="sb-search-box">
          <svg width="13" height="13" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:var(--sb-text-2);flex-shrink:0"><path d="M14 14L11.1 11.1M7.33 2C5.92 2 4.56 2.56 3.56 3.56C2.56 4.56 2 5.92 2 7.33C2 8.74 2.56 10.1 3.56 11.1C4.56 12.1 5.92 12.67 7.33 12.67C8.74 12.67 10.1 12.1 11.1 11.1C12.1 10.1 12.67 8.74 12.67 7.33C12.67 5.92 12.1 4.56 11.1 3.56C10.1 2.56 8.74 2 7.33 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <input type="text" id="sb-search-input" class="sb-sidebar-search" placeholder="Entrez l'équipe ou le nom du championnat..." oninput="window.sbSearchMatches(this.value)">
        </div>
      </div>
    </div>

    <!-- League tabs: LES MEILLEURES LIGUES / MES LIGUES -->
    <div id="sb-favs-content" class="sb-top-leagues-card">
    <div class="sb-league-group-hdr">
      <button class="sb-mob-tab active" data-tab="best" onclick="window.sbMobLeagueTab(this,'best')">LES MEILLEURS LIGUES</button>
      <button class="sb-mob-tab" data-tab="my" onclick="window.sbMobLeagueTab(this,'my')">MES LIGUES <span class="sb-mes-cnt">0</span></button>
      <span class="sb-lh-minus">—</span>
    </div>

    <!-- LES MEILLEURS LIGUES content — always shows the fixed top-league list -->
    <div id="sb-mob-best-leagues">
    <div class="sb-tl-list">
      <?php foreach ($top_leagues as $l) { sb_render_tl_item($l); } ?>
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
    <div class="sb-sport-nav" id="sb-sport-nav">
      <!-- EXACT fcbet216 ScrollButton SVG markup (user-supplied):
           d="M6 4L10 8L6 12" stroke-width 1.5 — rendered as a circular
           light-on-dark button via the .sb-nav-arrow CSS spec. -->
      <button class="sb-nav-arrow left is-hidden" id="sb-nav-arrow-left" aria-label="Scroll left" type="button"
        onclick="window.sbScrollSportNav(-1)">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="transform:rotate(180deg)"><path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <button class="sb-nav-arrow right" id="sb-nav-arrow-right" aria-label="Scroll right" type="button"
        onclick="window.sbScrollSportNav(1)">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="sb-sport-nav-inner" id="sb-sport-nav-inner">
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

    <!-- ══ MOBILE RECHERCHE DE PARIS (Bet Code) ══
         Visible on mobile only, matches fcbet216 image -->
    <div class="sb-mob-bet-search-panel" style="margin: 0 12px 16px; background: rgb(49, 49, 49); padding: 14px; border-radius: 8px;">
      <div style="font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 12px; font-family: 'Roboto', sans-serif;">Rechercher des paris</div>
      <div class="sb-search-row" style="display: flex; gap: 8px;">
        <select class="sb-dark-sel" style="background: transparent; border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 10px 12px; border-radius: 4px; font-size: 14px; font-weight: 500; outline: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg width=\"10\" height=\"6\" viewBox=\"0 0 10 6\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M1 1L5 5L9 1\" stroke=\"white\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>'); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px;"><option>Bet Code</option></select>
        <input type="text" class="sb-dark-inp" placeholder="Entrez le numéro d'identific..." style="flex: 1; background: transparent; border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 10px 12px; border-radius: 4px; font-size: 14px; outline: none; min-width: 0;">
      </div>
    </div>

    <!-- ══ MOBILE INLINE SEARCH ══
         Stand-alone card ABOVE the leagues panel (fcbet216 layout) -->
    <div class="sb-mob-inline-leagues sb-mob-search-panel">
      <div class="sb-search-wrap">
        <div class="sb-search-box">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:var(--sb-text-2);flex-shrink:0"><path d="M14 14L11.1 11.1M7.33 2C5.92 2 4.56 2.56 3.56 3.56C2.56 4.56 2 5.92 2 7.33C2 8.74 2.56 10.1 3.56 11.1C4.56 12.1 5.92 12.67 7.33 12.67C8.74 12.67 10.1 12.1 11.1 11.1C12.1 10.1 12.67 8.74 12.67 7.33C12.67 5.92 12.1 4.56 11.1 3.56C10.1 2.56 8.74 2 7.33 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <input type="text" class="sb-sidebar-search" placeholder="Entrez l'équipe ou le nom du championnat..." oninput="window.sbSearchMatches(this.value)">
        </div>
      </div>
    </div><!-- /sb-mob-search-panel -->

    <!-- ══ MOBILE INLINE LEAGUES PANEL ══
         Separate card: LES MEILLEURES LIGUES / MES LIGUES tabs + list.
         Visible on mobile only (hidden on desktop via CSS). -->
    <div class="sb-mob-inline-leagues sb-mob-leagues-panel">
      <!-- League tabs header -->
      <div class="sb-league-group-hdr">
        <button class="sb-mob-tab active" data-tab="best" onclick="window.sbMobLeagueTab(this,'best')">LES MEILLEURS LIGUES</button>
        <button class="sb-mob-tab" data-tab="my" onclick="window.sbMobLeagueTab(this,'my')">MES LIGUES <span class="sb-mes-cnt">0</span></button>
        <span class="sb-lh-minus">—</span>
      </div>

      <!-- LES MEILLEURS LIGUES content -->
      <div id="sb-inline-best-leagues">
        <div class="sb-tl-list">
          <?php foreach ($top_leagues as $l) { sb_render_tl_item($l); } ?>
        </div>
      </div>

      <!-- MES LIGUES empty state -->
      <div id="sb-inline-my-leagues" style="display:none">
        <div class="sb-empty-leagues-state">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:#666;margin-bottom:10px"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <span>Aucune ligue ajoutée</span>
        </div>
      </div>
    </div><!-- /sb-mob-leagues-panel -->

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

  <!-- Drag handle (mobile) — tap to close -->
  <div class="sb-drawer-handle" onclick="window.sbToggleRight()"></div>

  <!-- ═══ FICHE DE PARI — always first ═══ -->
  <div class="sb-slip-panel" id="sb-slip-panel">
    <div class="sb-slip-hdr">
      <span class="sb-slip-hdr-title">FICHE DE PARI</span>
      <span class="sb-slip-hdr-badge" id="sb-slip-count" style="display:none">0</span>
      <button class="sb-slip-hdr-close" onclick="window.sbCollapseSlip()" title="Réduire">&#8212;</button>
    </div>
    <div id="sb-slip-body"></div>
  </div>

  <!-- ═══ Secondary widgets (below bet slip) ═══ -->
  <div class="sb-right-secondary">

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

    <!-- Team / championship search — added above Paris populaires per
         the reference image (matches the fcbet216 sidebar order). -->
    <div class="sb-widget sb-widget--team-search">
      <div class="sb-widget-body" style="padding-top:6px">
        <div class="sb-search-box sb-search-box--right">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:var(--sb-text-2);flex-shrink:0"><path d="M14 14L11.1 11.1M7.33 2C5.92 2 4.56 2.56 3.56 3.56C2.56 4.56 2 5.92 2 7.33C2 8.74 2.56 10.1 3.56 11.1C4.56 12.1 5.92 12.67 7.33 12.67C8.74 12.67 10.1 12.1 11.1 11.1C12.1 10.1 12.67 8.74 12.67 7.33C12.67 5.92 12.1 4.56 11.1 3.56C10.1 2.56 8.74 2 7.33 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <input type="text" class="sb-sidebar-search" placeholder="Entrez l'équipe ou le nom du championnat" oninput="window.sbSearchMatches(this.value)">
        </div>
      </div>
    </div>

    <div class="sb-widget">
      <div class="sb-widget-hdr">Paris populaires</div>
      <div id="sb-popular-bets"></div>
    </div>

  </div><!-- end sb-right-secondary -->

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

<script src="<?=$base?>sportsbook/app.js?v=<?=$sb_js_v?>"></script>
