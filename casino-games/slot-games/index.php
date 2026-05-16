<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../../app/index.php';
    exit;
}
include __DIR__ . '/../../includes/header.php';
?>

<link rel="preload" as="image" href="https://providers.gamblly-api.com/assets/providers-icon/pragmaticplaylive-eu.png" fetchpriority="high">

<!-- Full Width Provider Tabs Section -->
<div class="container" style="min-height: 60vh; margin-top: 12px;">
    <!-- Game Providers Tabs -->
    <div class="provider-tabs-container">
        <ul class="nav nav-pills provider-tabs">
            <li class="active"><a href="#all" data-toggle="tab" onclick="filterGames('all')">ALL</a></li>
            <li><a href="#PragmaticPlay" data-toggle="tab" onclick="filterGames('PragmaticPlay')"><div class="skeleton-loader"></div><img class="pragmatic-logo big-icon" src="https://providers.gamblly-api.com/assets/providers-icon/pragmaticplaylive-eu.png" loading="eager" fetchpriority="high" decoding="async" style="opacity: 0; transition: opacity 0.2s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').stop(true, true).fadeOut(100, function(){ $(this).remove(); });" onerror="$(this).siblings('.skeleton-loader').stop(true, true).fadeOut(100); $(this).hide();" alt="PragmaticPlay"></a></li>
            <li><a href="#Jili" data-toggle="tab" onclick="filterGames('Jili')"><div class="skeleton-loader"></div><img src="https://moneyking365.com/assets/images/Jili.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="Jili"></a></li>
            <li><a href="#KINGMAKER" data-toggle="tab" onclick="filterGames('KINGMAKER')"><div class="skeleton-loader"></div><img src="https://d122775g111yth.cloudfront.net/prod/dashboard/static-provider-d11/KINGMAKER.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="KINGMAKER"></a></li>
            <li><a href="#Evoplay" data-toggle="tab" onclick="filterGames('Evoplay')"><div class="skeleton-loader"></div><img src="https://d2iiuh20b82oxt.cloudfront.net/prod/dashboard/provider-image/562399.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="Evoplay"></a></li>
            <li><a href="#Bgaming" data-toggle="tab" onclick="filterGames('Bgaming')"><div class="skeleton-loader"></div><img src="https://moneyking365.com/assets/images/bgaming.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="Bgaming"></a></li>
            <li><a href="#Play'n GO" data-toggle="tab" onclick="filterGames('Play\'n GO')"><div class="skeleton-loader"></div><img src="https://d2iiuh20b82oxt.cloudfront.net/prod/v2-dashboard-pi/casino-provider/20230822/711467.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="Play'n GO"></a></li>
            <li><a href="#Netent" data-toggle="tab" onclick="filterGames('Netent')"><div class="skeleton-loader"></div><img src="https://d2iiuh20b82oxt.cloudfront.net/pi-casino-df-img/slg/netent/logo.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="Netent"></a></li>
            <li><a href="#BigTime Gaming" data-toggle="tab" onclick="filterGames('BigTime Gaming')"><div class="skeleton-loader"></div><img src="https://d2iiuh20b82oxt.cloudfront.net/pi-casino-df-img/slg/bigtimegaming/logo.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="BigTime Gaming"></a></li>
            <li><a href="#NoLimit City" data-toggle="tab" onclick="filterGames('NoLimit City')"><div class="skeleton-loader"></div><img src="https://d2iiuh20b82oxt.cloudfront.net/pi-casino-df-img/slg/nolimitcity/logo.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="NoLimit City"></a></li>
            <li><a href="#Playson" data-toggle="tab" onclick="filterGames('Playson')"><div class="skeleton-loader"></div><img src="https://d2iiuh20b82oxt.cloudfront.net/prod/v2-dashboard-pi/casino-provider/20230822/23632.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="Playson"></a></li>
            <li><a href="#PGSoft" data-toggle="tab" onclick="filterGames('PGSoft')"><div class="skeleton-loader"></div><img src="https://d2iiuh20b82oxt.cloudfront.net/pi-casino-df-img/slg/pgsoft/logo.png" style="opacity: 0; transition: opacity 0.5s ease;" onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300);" onerror="$(this).hide();" alt="PGSoft"></a></li>
        </ul>
    </div>

    

    <!-- Tab Content Area (Dynamic Game Grid) -->
    <div class="tab-content" style="margin-top: 20px; color: #fff;">
        <div id="game-grid" class="row">
            <!-- Games will be loaded here via JavaScript -->
            <div class="col-xs-12 text-center">
                <i class="fa fa-spinner fa-spin" style="font-size: 40px; color: #c37601;"></i>
            </div>
        </div>
    </div>
</div>

<script>
// Game Data
var gamesData = [];
var selectedProvider = 'all';
var selectedCategory = 'all';
var mkFeaturedPragmaticGameId = 'b066c2bdef0f2d541a2317ed5fdac3b4';
var mkPinnedAllSlotGames = [
    '5 lions megaways',
    'gates of olympus',
    'gates of olympus 1000',
    'madame destiny tm',
    'madame destiny megaways',
    'sweet bonanza',
    'sweet bonanza 1000',
    'big bass'
];

function mkNormGameName(name) {
    return String(name || '')
        .toLowerCase()
        .replace(/™|tm/g, 'tm')
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function mkPinnedSlotRank(game) {
    if (game && String(game.gameid || '').toLowerCase() === mkFeaturedPragmaticGameId) return 0;
    var name = mkNormGameName(game && game.gamename);
    for (var i = 0; i < mkPinnedAllSlotGames.length; i++) {
        var wanted = mkNormGameName(mkPinnedAllSlotGames[i]);
        if (name === wanted || name.indexOf(wanted) !== -1) return i;
    }
    return 9999;
}

// Helper to show skeleton grid while loading
function showSkeletonGrid() {
    var container = $('#game-grid');
    container.empty();
    container.addClass('game-grid-row');
    
    // Show 24 dummy cards for initial loading
    for (var i = 0; i < 24; i++) {
        var html = `
            <div class="game-item-col mb-3" style="margin-bottom: 10px;">
                <div class="game-card">
                    <div class="game-img-wrapper">
                        <div class="skeleton-loader"></div>
                    </div>
                </div>
            </div>
        `;
        container.append(html);
    }
}

// Helper to fetch JSON
function loadGames() {
    var providers = [
        { key: 'PragmaticPlay', file: 'PragmaticPlay_-_EU.json', name: 'PragmaticPlay - EU' },
        { key: 'PragmaticPlay', file: 'PragmaticPlay_-_Asia.json', name: 'PragmaticPlay - Asia', onlyGameId: mkFeaturedPragmaticGameId },
        { key: 'Jili', file: 'Jili.json', name: 'Jili' },
        { key: 'KINGMAKER', file: 'kmideal.json', name: 'KINGMAKER' },
        { key: 'Evoplay', file: 'Evoplay_-_Asia.json', name: 'Evoplay' },
        { key: 'Bgaming', file: 'Bgaming.json', name: 'Bgaming' },
        { key: 'Play\'n GO', file: 'Play_n_Go.json', name: 'Play\'n GO' },
        { key: 'Netent', file: 'Netent.json', name: 'Netent' },
        { key: 'BigTime Gaming', file: 'BigTime_Gaming.json', name: 'BigTime Gaming' },
        { key: 'NoLimit City', file: 'NoLimit_City.json', name: 'NoLimit City' },
        { key: 'Playson', file: 'Playson.json', name: 'Playson' },
        { key: 'PGSoft', file: 'PGSoft.json', name: 'PGSoft' }
    ];

    var loadedCount = 0;
    var totalProviders = providers.length;
    
    // Show skeleton loader instead of spinner
    showSkeletonGrid();

    providers.forEach(function(p) {
        // Use relative path from header.php logic ($back_path)
        var jsonPath = (typeof SITE_BASE_URL === 'string' ? SITE_BASE_URL : '/') + 'games-json/' + p.file;
        
        // Handle placeholders or missing files gracefully
        if(p.file === '__.json') {
             loadedCount++;
             if (loadedCount === totalProviders) {
                renderGames('all');
             }
             return; 
        }

        $.getJSON(jsonPath, function(data) {
            if (p.onlyGameId) {
                data = data.filter(function(g) {
                    return String(g.gameid || g.id || '').toLowerCase() === String(p.onlyGameId).toLowerCase();
                });
            }
            // Add provider key to each game for filtering
            var mappedGames = data.map(function(g) {
                // Normalize game object structure if needed
                return {
                    gameid: g.gameid || g.id || '',
                    gamename: g.gamename || g.name || 'Unknown Game',
                    image: g.image || g.img || '',
                    providerKey: p.key,
                    providerName: p.name
                };
            });
            gamesData = gamesData.concat(mappedGames);
        })
        .fail(function() {
            console.log("Failed to load: " + p.name);
        })
        .always(function() {
            loadedCount++;
            if (loadedCount === totalProviders) {
                renderGames();
            }
        });
    });
}

function filterGames(provider) {
    selectedProvider = provider || 'all';
    renderGames();
}

function setCategory(cat) {
    selectedCategory = (cat && cat !== 'all' && cat !== 'cat_all') ? 'slot' : 'all';
    renderGames();
}

function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[ch];
    });
}

function renderGames() {
    var container = $('#game-grid');
    container.empty();
    
    // Create a row container if not exists or just use container as flex container
    // We'll use the container itself as the row
    container.addClass('game-grid-row');
    
    var filteredGames = gamesData.slice();
    if (!(selectedProvider === 'all' || selectedProvider === 'ALL')) {
        filteredGames = filteredGames.filter(function(g) { return g.providerKey === selectedProvider; });
    }
    if (!(selectedCategory === 'all')) {
    }
    if (selectedProvider === 'all' || selectedProvider === 'ALL') {
        filteredGames.sort(function(a, b) {
            var ar = mkPinnedSlotRank(a);
            var br = mkPinnedSlotRank(b);
            if (ar !== br) return ar - br;
            return 0;
        });
    }
        
    if (filteredGames.length === 0) {
        container.html('<div class="col-xs-12 text-center" style="padding: 50px;"><h4 style="color: #fff;">No games found for this provider.</h4></div>');
        return;
    }
    
    filteredGames.forEach(function(game) {
        var safeName = String(game.gamename || '').replace(/'/g, "\\'");
        // Prepare HTML components
        var skeletonHtml = '<div class="skeleton-loader"></div>';
        var imageHtml = '';
        var fallbackHtml = '';
        var hasImage = (game.image && game.image.trim() !== '');
        
        if (hasImage) {
             imageHtml = `
                <img src="${game.image}" alt="${game.gamename}"
                     style="opacity: 0; transition: opacity 0.5s ease;"
                     onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300, function(){ $(this).remove(); });"
                     onerror="$(this).hide();"> 
             `;
        } else {
            skeletonHtml = '';
            fallbackHtml = '<div class="game-name-fallback">' + escapeHtml(game.gamename) + '</div>';
        }
        
        var html = `
            <div class="game-item-col mb-3" style="margin-bottom: 10px;">
                <div class="game-card">
                    <div class="game-img-wrapper">
                        ${skeletonHtml}
                        ${imageHtml}
                        ${fallbackHtml}
                        <div class="game-hover">
                            <button class="play-btn" onclick="mkSafeLaunch('${game.gameid}', '${safeName}')">PLAY NOW</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.append(html);
    });
}

// Add hover effect via JS/CSS
$(document).on('mouseenter', '.game-card', function() {
    $(this).find('.game-hover').css('opacity', '1');
}).on('mouseleave', '.game-card', function() {
    $(this).find('.game-hover').css('opacity', '0');
});

// Load games on start
$(document).ready(function() {
    $('.provider-tabs img').each(function() {
        if (this.complete && this.naturalWidth > 0) {
            $(this).css('opacity', 1);
            $(this).siblings('.skeleton-loader').stop(true, true).fadeOut(100, function(){ $(this).remove(); });
        }
    });

    loadGames();

    $('.category-tabs a').off('click.mkCat').on('click.mkCat', function(e){
        e.preventDefault();
        $('.category-tabs li').removeClass('active');
        $(this).parent('li').addClass('active');
        var h = $(this).attr('href') || '';
        var c = h.replace('#cat_', '');
        setCategory(c);
    });
    
    // Fix Tab Click Active State & Scroll to View
    $('.provider-tabs a').off('click.mkProv').on('click.mkProv', function(e) {
        e.preventDefault();
        $('.provider-tabs li').removeClass('active');
        $(this).parent('li').addClass('active');

        try {
            var oc = String($(this).attr('onclick') || '');
            var m = oc.match(/filterGames\(\s*'([^']*)'/i);
            if (m && m[1] !== undefined) filterGames(m[1]);
            else {
                var hh = String($(this).attr('href') || '').replace('#', '');
                if (hh && hh.toLowerCase() === 'all') filterGames('all');
            }
        } catch (e2) {}
        
        // Scroll the active tab into view smoothly
        var container = $('.provider-tabs-container');
        var tab = $(this).parent('li');
        var containerWidth = container.width();
        var tabWidth = tab.outerWidth();
        var tabLeft = tab.position().left;
        var scrollLeft = container.scrollLeft();
        
        // Calculate center position
        var targetScroll = scrollLeft + tabLeft - (containerWidth / 2) + (tabWidth / 2);
        
        container.animate({
            scrollLeft: targetScroll
        }, 300); // 300ms for smooth scroll
    });
});
</script>

<style>
/* Provider Tabs Styling */
.provider-tabs-container,
.category-tabs-container {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden; /* Ensure no vertical scroll */
    white-space: nowrap;
    padding: 10px 0; /* Add top/bottom padding to prevent shadow clipping */
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox Hidden Mobile */
    -ms-overflow-style: none;  /* IE and Edge Hidden Mobile */
    text-align: center; /* Center content */
}

/* Hide scrollbar by default (Mobile First approach) */
@media (max-width: 991px) {
    .provider-tabs-container::-webkit-scrollbar, 
    .category-tabs-container::-webkit-scrollbar {
        display: none !important; /* Force Hidden on mobile */
        width: 0 !important;
        height: 0 !important;
        background: transparent;
    }
}

@media (min-width: 992px) {
    /* 1. Provider Tabs: No Wrap, SHOW Custom Ultra-Thin Slider */
    .provider-tabs-container {
        scrollbar-width: thin;
        scrollbar-color: #c37601 #111;
        overflow-x: auto; /* Enable scroll */
        white-space: nowrap; /* Prevent wrap */
        padding-bottom: 5px;
        padding-left: 0;
        box-sizing: border-box;
        width: 100%;
    }
    .provider-tabs-container::-webkit-scrollbar {
        display: block !important;
        height: 3px !important;
        background: #111;
        width: 100%;
        -webkit-appearance: none;
    }
    .provider-tabs {
        display: flex;
        flex-wrap: nowrap; /* No Wrap */
        justify-content: flex-start;
        width: max-content;
        padding: 0;
        gap: 15px;
        box-sizing: border-box;
    }
    /* Force first item to have left margin */
    .provider-tabs li:first-child {
        margin-left: 0; /* Aligned via container */
    }

    /* 2. Category Tabs: No Wrap, SHOW Custom Ultra-Thin Slider */
    .category-tabs-container {
        scrollbar-width: thin;
        scrollbar-color: #c37601 #111;
        overflow-x: auto; /* Enable scroll */
        white-space: nowrap; /* Prevent wrap */
        padding-bottom: 5px; /* Less padding for tight fit */
        padding-left: 0; /* Remove container padding */
        box-sizing: border-box;
    }
    
    /* Custom Scrollbar to mimic a progress bar/slider */
    .category-tabs-container::-webkit-scrollbar {
        display: block !important;
        height: 3px !important; /* Extremely thin line */
        background: #111; /* Dark track matching bg */
        width: 100%;
        -webkit-appearance: none;
    }
    
    .category-tabs-container::-webkit-scrollbar-thumb {
        background-color: #c37601 !important; 
        border-radius: 0; /* Square edges */
        border: none; /* No border */
        display: block !important;
    }
    
    .category-tabs-container::-webkit-scrollbar-track {
        background-color: #111 !important; 
        border-radius: 0;
        margin: 0 30px; /* Indent track slightly from edges */
    }
    
    .category-tabs-container::-webkit-scrollbar-thumb:hover {
        background-color: #e5a700 !important;
    }

    .category-tabs {
        display: inline-flex; /* Single line */
        flex-wrap: nowrap;
        justify-content: flex-start; /* Start align for scroll */
        width: auto; /* Auto width */
        padding: 0; /* Remove container padding */
        box-sizing: border-box;
    }
    /* Force first item to have left margin */
    .category-tabs li:first-child {
        margin-left: 30px;
    }
}

.provider-tabs {
    display: inline-flex; /* Use inline-flex so it wraps content size */
    flex-wrap: nowrap;
    gap: 15px;
    justify-content: flex-start;
    margin: 0;
    padding: 0 5px; /* Reduced padding for mobile to shift left */
    list-style: none; /* Remove bullets */
    min-width: 100%; /* Ensure it takes at least full width */
    box-sizing: border-box;
}

/* On larger screens, Center Align (Reverted) - DELETED (Handled above) */

.provider-tabs li {
    flex: 0 0 auto; /* Prevent shrinking/growing */
    display: inline-block;
}
.provider-tabs li a {
    width: 130px; /* Slightly wider than 107 to look better */
    height: 50px; /* Slightly taller than 40 for "big" look */
    border: 2px solid #fff; /* Bolder border */
    border-radius: 6px;
    padding: 0;
    background: #000;
    color: #fff;
    font-weight: 700;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    transition: all 0.3s ease;
    box-shadow: none;
    outline: none !important;
    position: relative; /* Ensure absolute children (skeleton) are contained */
    overflow: hidden; /* Ensure skeleton doesn't overflow corners */
}
/* Override Bootstrap Nav Pills Active State */
.nav-pills > li.active > a, 
.nav-pills > li.active > a:focus, 
.nav-pills > li.active > a:hover {
    color: #fff;
    background-color: #000 !important; /* Force Black */
    border-color: #c37601 !important;
}
.provider-tabs li a:focus, .provider-tabs li a:active {
    outline: none !important;
    text-decoration: none;
    box-shadow: none !important;
    background-color: #000 !important;
}
.provider-tabs li.active a, .provider-tabs li a:hover {
    border-color: #c37601;
    background: #000 !important; /* Ensure hover is also black */
    box-shadow: none; 
    outline: none !important;
}
.provider-tabs li a img {
    max-width: 90%;
    max-height: 35px; /* Ensure logo is visible and large within box */
    object-fit: contain;
    filter: brightness(1.2); /* Make logos pop on black bg */
}
/* Specific style for icons that need to be bigger */
.provider-tabs li a img.big-icon {
    max-height: 45px; /* Increased size for Evo, Sexy, PP */
    max-width: 95%;
}
.provider-tabs li a img.pragmatic-logo {
    width: 118px;
    height: 46px;
    max-width: none;
    max-height: none;
    object-fit: cover;
    object-position: center;
    transform: scale(1.18);
}

/* Category Tabs Styling */
.category-tabs-container {
    width: 100%;
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    text-align: center;
    border-bottom: 2px solid #333;
    padding-bottom: 10px; /* Base padding */
    box-sizing: border-box;
}

.category-tabs {
    display: inline-flex;
    flex-wrap: nowrap;
    gap: 5px; /* Reduced gap significantly */
    justify-content: flex-start;
    margin: 0;
    padding: 0 10px; /* Reduced padding */
    list-style: none;
    min-width: 100%;
    box-sizing: border-box;
}

.category-tabs li {
    flex: 0 0 auto;
    display: inline-block;
    padding-bottom: 10px;
    position: relative;
}

.category-tabs li a {
    background: transparent !important;
    color: #fff;
    font-size: 15px;
    padding: 5px 20px;
    border: 2px solid transparent; /* Bolder border placeholder */
    border-radius: 6px;
    font-weight: 700; /* Bolder text */
    transition: all 0.3s;
    text-transform: capitalize;
    display: block;
    text-decoration: none;
}

/* Hover State */
.category-tabs li a:hover {
    color: #c37601;
    border-color: rgba(255, 184, 12, 0.5); /* Faint yellow on hover */
    background: transparent !important;
}

/* Active State - Bold Yellow Box Border */
.category-tabs li.active a {
    border: 2px solid #c37601 !important; /* Bold Yellow Border */
    color: #fff;
    background: transparent !important;
    box-shadow: 0 0 10px rgba(255, 184, 12, 0.2); /* Slight glow */
}

/* Remove default Bootstrap active background */
.nav-pills > li.active > a, 
.nav-pills > li.active > a:focus, 
.nav-pills > li.active > a:hover {
    background-color: transparent !important;
    border: 2px solid #c37601 !important;
    color: #fff;
}

/* Custom Grid for 8 items per row on Desktop and 3 on Mobile */
.game-grid-row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -5px; /* Adjust gutter */
}
.game-item-col {
    padding: 0 5px; /* Small gutter */
    width: 33.333%; /* 3 items per row on mobile (default) */
}
@media (min-width: 768px) {
    .game-item-col {
        width: 25%; /* 4 items on tablet */
    }
}
@media (min-width: 992px) {
    .game-item-col {
        width: 16.666%; /* 6 items on small desktop */
    }
}
@media (min-width: 1200px) {
    .game-item-col {
        width: 12.5%; /* 8 items per row on large desktop */
    }
}

/* Game Card Styling */
.game-card {
    position: relative;
    overflow: hidden;
    border-radius: 2px; /* 2px curve */
    border: 1px solid transparent;
    transition: border 0.3s ease, box-shadow 0.3s ease; /* Only transition border/shadow, not transform */
    background: #1a1a1a;
}
.game-card:hover {
    border: 2px solid #c37601;
    box-shadow: 0 5px 15px rgba(195, 118, 1, 0.3);
}
.game-img-wrapper {
    position: relative;
    padding-top: 133%; /* 3:4 Aspect Ratio */
}
.game-img-wrapper img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: none !important; /* Strictly no transition for zoom */
}
.game-name-fallback {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 10px;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.25;
    background: linear-gradient(180deg, #2a2a2a 0%, #111 100%);
    border: 1px solid rgba(195, 118, 1, 0.35);
    z-index: 1;
}
/* Strictly Disable Zoom Effect */
.game-card:hover .game-img-wrapper img {
    transform: none !important; /* Force no transform */
}

/* Mobile Specific Adjustments */
@media (max-width: 767px) {
    .game-card:hover, .game-card:active, .game-card:focus {
        border: 1px solid transparent !important; /* No border on mobile click */
        box-shadow: none !important; /* No shadow on mobile click */
        transform: none !important;
    }
    .game-hover {
        /* On mobile, show overlay on click/touch? Or always hidden until active? 
           Usually mobile has no hover. We can show it on active state or just make the whole card clickable.
           But request says "click krne pr smoothly sirf play now ka text dikhao".
           So on click (active/focus), show overlay.
        */
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    /* Show overlay on focus/active for mobile touch */
    .game-card:active .game-hover, 
    .game-card:focus-within .game-hover {
        opacity: 1;
    }
    
    /* Ensure play button is visible/centered when overlay shows */
    .play-btn {
        transform: translateY(0) !important; /* Always centered on mobile overlay */
    }
}

.game-hover {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6); /* Dark overlay */
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 10; /* Ensure it is above the skeleton loader */
}
.game-card:hover .game-hover {
    opacity: 1;
}
.play-btn {
    background: #c37601; /* Default Yellow for Desktop */
    border: none;
    color: #fff; /* White Text */
    font-weight: 600; /* Bolder text for smaller size */
    padding: 5px 15px; /* Reduced padding for smaller size */
    border-radius: 2px; /* Rectangular */
    text-transform: uppercase;
    font-size: 12px; /* Reduced font size */
    cursor: pointer;
    transform: translateY(20px);
    transition: all 0.3s ease;
    box-shadow: none !important; /* No shadow/glow */
    min-width: 80px; /* Ensure not too small */
}
.game-card:hover .play-btn {
    transform: translateY(0);
}
.play-btn:hover {
    background: #c37601; /* Keep same color on hover */
    color: #fff;
    opacity: 0.9; /* Slight opacity change instead of color change */
}

/* Mobile Specific Adjustments */
@media (max-width: 767px) {
    /* ... existing mobile styles ... */
    
    /* Ensure play button is visible/centered when overlay shows */
    .play-btn {
        transform: translateY(0) !important; /* Always centered on mobile overlay */
        
        /* Mobile Specific Button Style (Black) */
        background: #000 !important; 
        color: #fff !important;
        font-size: 12px !important;
        padding: 6px 15px !important;
        border-radius: 4px !important;
        box-shadow: 0 2px 5px rgba(0,0,0,0.5) !important;
        font-weight: 600 !important;
    }
}

/* Skeleton Loader Styling */
.skeleton-loader {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #333; /* Dark base color matching user image */
    border-radius: 2px;
    z-index: 2; /* Above the hidden image */
    overflow: hidden;
}

/* Shine Effect */
.skeleton-loader::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.1) 50%, /* Soft shine */
        transparent 100%
    );
    animation: shimmer 1.2s ease-in-out infinite alternate;
}

@keyframes shimmer {
    0% {
        left: -100%;
    }
    100% {
        left: 100%;
    }
}

/* Final provider tab chrome fix: keep horizontal scrolling, hide the overlay line. */
.provider-tabs-container {
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    padding-bottom: 14px !important;
}

.provider-tabs-container::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

@media (max-width: 767px) {
    .provider-tabs-container {
        padding-top: 8px !important;
        padding-bottom: 16px !important;
    }
}
</style>

<script>
$(document).ready(function() {
    function checkOverflowCategory() {
        var container = $('.category-tabs-container');
        var list = $('.category-tabs');
        
        if (list.outerWidth() > container.width()) {
            list.css('justify-content', 'flex-start');
        } else {
            if ($(window).width() >= 992) {
                 list.css('justify-content', 'center');
            } else {
                 list.css('justify-content', 'center');
            }
        }
    }
    
    $(window).resize(checkOverflowCategory);
    setTimeout(checkOverflowCategory, 100);

    // Auto-Scroll to Active Tab logic
    $('.category-tabs a').on('click', function() {
        var $li = $(this).parent();
        var container = $('.category-tabs-container');
        
        // Calculate position to scroll
        var scrollLeft = $li.position().left + container.scrollLeft() - (container.width() / 2) + ($li.width() / 2);
        
        container.animate({
            scrollLeft: scrollLeft
        }, 300);
    });
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
