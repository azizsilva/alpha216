<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Note: header.php also calls session_start, but we need session before header to check logic
// require_once 'includes/db.php'; // header includes db.php usually
require_once '../includes/header.php'; // Adjusted path since file moved to play/

$token = isset($_GET['t']) ? trim((string)$_GET['t']) : '';
$game_url = '';
$game_name = '';
$game_id = '';
$game_tag = '';
if ($token !== '' && isset($_SESSION['mk_play_tokens']) && is_array($_SESSION['mk_play_tokens']) && isset($_SESSION['mk_play_tokens'][$token]) && is_array($_SESSION['mk_play_tokens'][$token])) {
    $entry = $_SESSION['mk_play_tokens'][$token];
    $game_id = (string)($entry['game_id'] ?? '');
    $game_url = (string)($entry['game_url'] ?? '');
    $game_name = (string)($entry['name'] ?? '');
    $game_tag = strtolower((string)($entry['tag'] ?? ''));
}
if ($game_url === '' && isset($_SESSION['game_url']) && !empty($_SESSION['game_url'])) {
    $game_url = (string)$_SESSION['game_url'];
    if (isset($_SESSION['mk_current_game_launch']) && is_array($_SESSION['mk_current_game_launch'])) {
        $game_id = (string)($_SESSION['mk_current_game_launch']['game_id'] ?? $game_id);
        $game_name = (string)($_SESSION['mk_current_game_launch']['name'] ?? $game_name);
        $game_tag = strtolower((string)($_SESSION['mk_current_game_launch']['tag'] ?? $game_tag));
    }
}
if ($game_url === '') {
    echo '<div class="container text-center" style="margin-top: 50px; color: #fff;"><h3><span data-translate="no_game_loaded">No game loaded.</span> <span data-translate="select_game_home">Please select a game from the home page.</span></h3><a href="../index.php" class="btn btn-warning" data-translate="go_back">Go Back</a></div>';
    require_once '../includes/footer.php';
    exit;
}

$sports_api_id = '6260';
$game_name_lc = strtolower($game_name);
$is_inplay_game = (
    strtolower($game_id) === $sports_api_id
    && (strpos($game_name_lc, 'in-play') !== false || strpos($game_name_lc, 'inplay') !== false)
);

// Enforce that we never iframe our own site; only iframe external (e.g., Gamblly) URLs
$site_host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
$parsed = @parse_url($game_url);
$scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
$host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
$is_same_origin = ($host !== '' && $site_host !== '' && $host === $site_host);
$is_http = ($scheme === 'http' || $scheme === 'https');
$is_gamblly = ($host !== '' && (strpos($host, 'gamblly') !== false || strpos($host, 'igamingapis') !== false));

if (!$is_http) {
    header('Location: ../index.php');
    exit;
}
if ($is_same_origin || !$is_gamblly) {
    header('Location: ' . $game_url);
    exit;
}

// Determine Back URL
// Use the 'game_back_url' stored in session during launch
$back_url = '../index.php'; // Default

if (isset($_SESSION['game_back_url']) && !empty($_SESSION['game_back_url'])) {
    $back_url = $_SESSION['game_back_url'];
} elseif (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
     // Fallback
     if (strpos($_SERVER['HTTP_REFERER'], 'play') === false) {
         $back_url = $_SERVER['HTTP_REFERER'];
     }
}
?>

<script>
try {
  document.body.classList.remove('mk-api-game-fullscreen', 'mk-api-game-inplay');
  document.body.classList.add('mk-api-game-fullscreen');
  <?php if ($is_inplay_game): ?>document.body.classList.add('mk-api-game-inplay');<?php endif; ?>
} catch (e) {}
</script>

<!-- Custom Mobile Header for Game Play -->
<div class="mobile-game-header visible-xs">
    <div class="mgh-left">
        <!-- Direct Link to index.php to ensure full page reload/redirect -->
        <a href="<?php echo $back_url; ?>" class="mgh-back" target="_top">
            <i class="fa fa-chevron-left"></i> <span data-translate="back">BACK</span>
        </a>
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
            <!-- Removed onclick="toggleSearch()" to prevent double triggering -->
            <i class="fa fa-search mgh-icon-search"></i>
            <i class="fa fa-bell-o mgh-icon-bell"></i>
        </div>
    </div>
</div>

<div class="game-container" style="position: relative; width: 100%; background: #000;">
    <iframe id="gameIframe" src="<?php echo htmlspecialchars($game_url); ?>" 
            style="width: 100%; height: 100%; border: none;">
    </iframe>
</div>


<script>
try {
  var t = <?php echo json_encode($game_name !== '' ? $game_name : 'Game'); ?>;
  if (t) document.title = t;
} catch (e) {}
// Prevent recursive iframing of main site
    // Ensure mobile viewport for this page (helps providers choose mobile layout)
    try {
        var vp = document.querySelector('meta[name=viewport]');
        if (!vp) {
            vp = document.createElement('meta');
            vp.setAttribute('name', 'viewport');
            document.head.appendChild(vp);
        }
        vp.setAttribute('content', 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover');
    } catch (e) {}

    (function () {
        function setVhVar() {
            var h = window.innerHeight;
            if (window.visualViewport && typeof window.visualViewport.height === 'number') {
                h = window.visualViewport.height;
            }
            document.documentElement.style.setProperty('--mk-vh', (h * 0.01) + 'px');
        }

        function refreshSoon() {
            requestAnimationFrame(function () {
                setVhVar();
                requestAnimationFrame(setVhVar);
            });
            setTimeout(setVhVar, 0);
            setTimeout(setVhVar, 150);
            setTimeout(setVhVar, 500);
        }

        document.addEventListener('DOMContentLoaded', function () {
            refreshSoon();
        });
        window.addEventListener('load', function () {
            refreshSoon();
        });
        window.addEventListener('resize', refreshSoon);
        window.addEventListener('orientationchange', refreshSoon);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', refreshSoon);
            window.visualViewport.addEventListener('scroll', refreshSoon);
        }
        refreshSoon();
    })();

window.onload = function() {
    var iframe = document.getElementById('gameIframe');
    var mainSiteUrl = window.location.origin; // e.g. https://tanitbet216.com
    var gameUrl = "<?php echo $game_url; ?>";
    
    // Safety check: if the game URL itself is the main site, redirect top
    if (gameUrl.indexOf(mainSiteUrl) !== -1 && gameUrl.indexOf('play/index.php') === -1) {
         // It seems the game URL is pointing to our own site (but not the play page itself)
         // This is a risk. Let's force top redirect if it happens.
         // However, legitimate API calls might be fine.
         // A better check is if the iframe *loads* the main site.
    }

    // Monitor iframe load
    iframe.onload = function() {
        try {
            // If we can access the iframe content (same origin), check if it's the main site
            var iframeLoc = iframe.contentWindow.location.href;
            if (iframeLoc.indexOf(mainSiteUrl) !== -1 && iframeLoc.indexOf('play/index.php') === -1) {
                // The iframe has navigated to a page on our main site!
                // Break out of the iframe
                window.top.location.href = iframeLoc;
            }
        } catch (e) {
            // Cross-origin: we can't read the URL. 
            // This is expected for external games.
            // We can't easily detect if they redirected to our site unless they pass a message.
        }
    };
};

// Break out of iframe if THIS page is loaded inside an iframe
if (window.self !== window.top) {
    window.top.location.href = window.location.href;
}
</script>

<style>
    :root { --mk-vh: 1vh; --mk-header-offset: 60px; }

    body.mk-api-game-fullscreen {
        padding-top: 0 !important;
        overflow: hidden !important;
    }
    body.mk-api-game-fullscreen .custom-navbar,
    body.mk-api-game-fullscreen .secondary-nav-container,
    body.mk-api-game-fullscreen .mobile-game-header {
        display: none !important;
    }
    body.mk-api-game-fullscreen:not(.mk-api-game-inplay) #mobile-footer-nav,
    body.mk-api-game-fullscreen:not(.mk-api-game-inplay) footer {
        display: none !important;
    }
    body.mk-api-game-fullscreen .game-container {
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        z-index: 10000;
    }
    body.mk-api-game-fullscreen.mk-api-game-inplay .game-container {
        bottom: 64px !important;
        height: calc(100vh - 64px) !important;
    }
    body.mk-api-game-fullscreen .game-container iframe {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important;
        max-width: none !important;
    }

    @media (max-width: 767px) {
        body.mk-api-game-fullscreen:not(.mk-api-game-inplay) .game-container {
            top: 50% !important;
            bottom: auto !important;
            height: min(calc(var(--mk-vh) * 100), calc(100vw * 1.78)) !important;
            transform: translateY(-50%);
            background: transparent !important;
        }

        body.mk-api-game-fullscreen:not(.mk-api-game-inplay) .game-container iframe {
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
            transform: none !important;
        }
    }

    /* Prevent double scrollbars globally on this page */
    body, html {
        overflow: hidden !important;
        height: 100%;
        margin: 0;
        width: 100%;
        max-width: 100%;
        min-width: 0 !important;
        /* Desktop padding handled by header.php default */
    }

    /* Mobile: Full screen game below custom header */
    @media (max-width: 767px) {
        :root { --mk-header-offset: calc(60px + env(safe-area-inset-top)); }
        body.mk-api-game-fullscreen { --mk-header-offset: 0px; }
        body, html { 
            overflow: hidden !important;
            height: 100%;
        }
        body {
            padding-top: 0 !important;
        }
    }
    
    /* Responsive Game Container Height */
    .game-container {
        /* Desktop: Full height of REMAINING space */
        /* Body padding is 145px on desktop (93px Main + 52px Secondary) */
        /* So we just fill the rest */
        height: calc(100vh - 145px); 
        width: 100%;
        margin: 0;
        padding-top: 0; /* Remove extra padding, rely on body padding */
        box-sizing: border-box; 
        overflow: hidden; 
    }
    
    /* Ensure iframe is responsive */
    .game-container iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
        max-width: 100vw;
        width: 100vw;
    }

    /* Custom Yellow Scrollbar for the entire page/iframe content - DESKTOP ONLY */
    @media (min-width: 768px) {
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #111; 
        }
        ::-webkit-scrollbar-thumb {
            background: #c37601; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #d48632; 
        }
    }
    
    /* Hide scrollbar on mobile */
    @media (max-width: 767px) {
        ::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
            background: transparent;
        }
        body, html {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
        }
    }

    @media (max-width: 767px) {
        .game-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            height: calc(var(--mk-vh) * 100);
            padding-top: 0;
            margin: 0;
        }
        .game-container iframe {
            position: absolute;
            top: calc(-1 * var(--mk-header-offset));
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: calc(100% + var(--mk-header-offset));
            max-width: none;
            touch-action: pan-y;
        }
        body.mk-api-game-fullscreen:not(.mk-api-game-inplay) .game-container iframe {
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            transform: none !important;
        }
        .mobile-game-header { position: fixed; top: 0; left: 0; right: 0; height: var(--mk-header-offset); padding-top: env(safe-area-inset-top); box-sizing: border-box; }
        .top-header, .header-container, .custom-navbar, .secondary-nav-container {
            display: none !important;
        }
    }

    @supports (height: 100dvh) {
        @media (max-width: 767px) {
            .game-container {
                height: 100dvh;
            }
        }
    }

    /* Mobile Game Header Styles - Hidden on Desktop */
    .mobile-game-header {
        background: #000;
        height: 60px;
        display: none; /* Default hidden */
        justify-content: space-between;
        align-items: center;
        padding: 0 12px;
        border-bottom: 1px solid #222;
        box-shadow: 0 2px 10px rgba(0,0,0,0.8);
        font-family: 'Roboto', sans-serif;
        width: 100%;
        flex-wrap: nowrap;
    }
    /* Only show on mobile */
    @media (max-width: 767px) {
        .mobile-game-header {
            display: flex !important;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10050;
        }
    }
    
    .mgh-left {
        flex: 0 0 auto;
    }
    .mgh-back {
        color: #fff;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 13px;
        text-decoration: none;
        display: flex;
        align-items: center;
        letter-spacing: 0.5px;
    }
    .mgh-back:hover, .mgh-back:focus {
        color: #fff;
        text-decoration: none;
    }
    .mgh-back i {
        color: #c37601;
        font-weight: 900;
        margin-right: 4px;
        font-size: 16px;
    }
    .mgh-right {
        display: flex;
        align-items: center;
        flex-wrap: nowrap; /* Prevent collapsing */
        gap: 10px;
    }
    .mgh-deposit {
        background: #c37601;
        color: #fff;
        border: none;
        font-weight: 600;
        padding: 6px 10px;
        font-size: 12px;
        border-radius: 4px;
        text-transform: capitalize;
        white-space: nowrap;
    }
    .mgh-deposit:hover {
        background: #a05f00;
        color: #fff;
    }
    .mgh-balance {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        line-height: 1.2;
        font-size: 11px;
        font-weight: 700;
        min-width: auto;
    }
    .bal-line {
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 3px;
    }
    .bal-val-ci { color: #FFD700; font-size: 12px; }
    .bal-val-exp { color: #FF0000; font-size: 11px; }
    .bal-unit { color: #fff; font-size: 10px; opacity: 0.9; }
    
    .mgh-icons {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-left: 2px;
    }
    .mgh-icon-search {
        color: #fff;
        font-size: 18px;
        cursor: pointer;
    }
    .mgh-icon-bell {
        color: #c37601;
        font-size: 18px;
        cursor: pointer;
    }
    
    /* Hide footer on game page to maximize space */
    body:not(.mk-api-game-inplay) footer {
        display: none;
    }
</style>

<?php require_once '../includes/footer.php'; ?>
