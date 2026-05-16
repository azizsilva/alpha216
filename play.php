<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Note: header.php also calls session_start, but we need session before header to check logic
// require_once 'includes/db.php'; // header includes db.php usually
require_once 'includes/header.php';

// Check if game URL exists in session
if (!isset($_SESSION['game_url']) || empty($_SESSION['game_url'])) {
    echo '<div class="container text-center" style="margin-top: 50px; color: #fff;"><h3><span data-translate="no_game_loaded">No game loaded.</span> <span data-translate="select_game_home">Please select a game from the home page.</span></h3><a href="index.php" class="btn btn-warning" data-translate="go_back">Go Back</a></div>';
    require_once 'includes/footer.php';
    exit;
}

$game_url = $_SESSION['game_url'];
$game_meta = (isset($_SESSION['mk_current_game_launch']) && is_array($_SESSION['mk_current_game_launch'])) ? $_SESSION['mk_current_game_launch'] : [];
$game_id = strtolower((string)($game_meta['game_id'] ?? ''));
$game_name = (string)($game_meta['name'] ?? '');
$game_tag = strtolower((string)($game_meta['tag'] ?? ''));
$sports_api_id = '8a704858d5deb4af1ddc722092ac7614';
$game_name_lc = strtolower($game_name);
$is_inplay_game = (
    $game_id === $sports_api_id
    && (strpos($game_name_lc, 'in-play') !== false || strpos($game_name_lc, 'inplay') !== false)
);

// Determine Back URL
// 1. Check GET parameter 'ref'
// 2. Check HTTP_REFERER (if not same page)
// 3. Default to index.php
$back_url = 'index.php';

if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    // Basic sanitization
    $back_url = htmlspecialchars($_GET['ref']);
} elseif (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    // Ensure we don't redirect back to play.php itself
    if (strpos($_SERVER['HTTP_REFERER'], 'play.php') === false) {
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
        <a href="<?php echo $back_url; ?>" class="mgh-back">
            <i class="fa fa-chevron-left"></i> <span data-translate="back">BACK</span>
        </a>
    </div>
    <div class="mgh-right">
        <a href="#" class="btn btn-sm mgh-deposit" data-translate="deposit">Deposit</a>
        <div class="mgh-balance">
            <div class="bal-line">
                <span class="bal-val-ci"><?php echo number_format($_SESSION['coins'], 2); ?></span> 
                <span class="bal-unit">TND</span>
            </div>
            <div class="bal-line">
                <span class="bal-val-exp">0.00</span> 
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
    <iframe src="<?php echo htmlspecialchars($game_url); ?>" 
            style="width: 100%; height: 100%; border: none;" 
            allowfullscreen>
    </iframe>
</div>

<style>
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
    }

    @media (max-width: 767px) {
        body.mk-api-game-fullscreen:not(.mk-api-game-inplay) .game-container {
            top: 50% !important;
            bottom: auto !important;
            height: min(100vh, calc(100vw * 1.78)) !important;
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
            transform: none !important;
        }
    }

    /* Prevent double scrollbars */
    body {
        overflow: hidden; 
    }
    
    /* Responsive Game Container Height */
    .game-container {
        height: calc(100vh - 50px); /* Default for PC (Header ~50px) */
    }
    @media (max-width: 767px) {
        .game-container {
            height: calc(100vh - 60px); /* Mobile Header Height */
        }
        /* Hide Default Header on Mobile for this page */
        .top-header, .header-container {
            display: none !important;
        }
    }

    /* Mobile Game Header Styles */
    .mobile-game-header {
        background: #000;
        height: 60px;
        display: flex !important; /* Override Bootstrap visible-xs display:block */
        justify-content: space-between;
        align-items: center;
        padding: 0 12px;
        border-bottom: 1px solid #222;
        box-shadow: 0 2px 10px rgba(0,0,0,0.8);
        font-family: 'Roboto', sans-serif;
        width: 100%;
        flex-wrap: nowrap;
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

<?php require_once 'includes/footer.php'; ?>
