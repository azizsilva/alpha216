<?php
include __DIR__ . '/../../includes/header.php';
?>

<!-- Virtual Sports Grid Section -->
<div class="container" style="min-height: 60vh; margin-top: 12px;">
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

// Helper to show skeleton grid while loading
function showSkeletonGrid() {
    var container = $('#game-grid');
    container.empty();
    container.addClass('game-grid-row');
    
    // Show 8 dummy cards for initial loading (matching our provider count)
    for (var i = 0; i < 8; i++) {
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
        { key: 'SABASports', file: 'SABASports__IBC_.json', name: 'SABASports (IBC) – Sport' },
        { key: 'UnitedGaming', file: 'UnitedGaming.json', name: 'UnitedGaming – Sport' },
        { key: 'LuckySport', file: 'LuckySport.json', name: 'LuckySport – Sport' },
        { key: 'Bti', file: 'Bti.json', name: 'Bti – Sport' },
        { key: 'SBO', file: 'SBO.json', name: 'SBO – Sport' },
        { key: 'CMD', file: 'CMD.json', name: 'CMD – Sport' },
        { key: 'TF', file: 'TF.json', name: 'TF – Esports' },
        { key: 'IA', file: '__.json', name: 'IA (小艾电竞) – Esports' }
    ];

    var loadedCount = 0;
    var totalProviders = providers.length;
    
    // Show skeleton loader instead of spinner
    showSkeletonGrid();

    providers.forEach(function(p) {
        // Use relative path from header.php logic ($back_path)
        var jsonPath = '<?php echo $back_path; ?>games-json/' + p.file;
        
        $.getJSON(jsonPath, function(data) {
            if (data && data.length > 0) {
                var g = data[0]; // Take the first entry
                
                // Determine Image URL
                var img = g.image || g.img || '';
                
                // Manual Fallback for specific providers if JSON image is missing or empty
                if (!img || img.trim() === '') {
                    if (p.key === 'TF') img = 'https://providers.gamblly-api.com/assets/providers/tf/tfgaming.png';
                    else if (p.key === 'SABASports') img = 'https://moneyking365.com/assets/images/SABASports.png';
                    else if (p.key === 'UnitedGaming') img = 'https://providers.gamblly-api.com/assets/providers/unitedgaming/united-gaming.png';
                    else if (p.key === 'LuckySport') img = 'https://moneyking365.com/assets/images/LuckySport.png';
                    else if (p.key === 'Bti') img = 'https://providers.gamblly-api.com/assets/providers/bti/btigaming.avif';
                    else if (p.key === 'SBO') img = 'https://providers.gamblly-api.com/assets/providers/sbo/sbo-sportsbook.png';
                    else if (p.key === 'CMD') img = 'https://providers.gamblly-api.com/assets/providers/CMD/cmd.png';
                    else if (p.key === 'IA') img = 'https://providers.gamblly-api.com/assets/providers/IA-Esport/ia-games.png'; // Fallback for IA
                }

                // Push to gamesData
                gamesData.push({
                    gameid: g.gameid || g.id || '',
                    gamename: p.name, // Use the custom display name
                    image: img,
                    providerKey: p.key
                });
            }
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

function renderGames() {
    var container = $('#game-grid');
    container.empty();
    container.addClass('game-grid-row');
        
    if (gamesData.length === 0) {
        container.html('<div class="col-xs-12 text-center" style="padding: 50px;"><h4 style="color: #fff;">No games found.</h4></div>');
        return;
    }
    
    // Sort games based on the provider list order if possible, or just render as is.
    // Since fetch is async, order might be scrambled.
    // To fix order, we can map the original provider list to the fetched data.
    var orderedGames = [];
    var providersOrder = ['SABASports', 'UnitedGaming', 'LuckySport', 'Bti', 'SBO', 'CMD', 'TF', 'IA'];
    
    providersOrder.forEach(function(key) {
        var found = gamesData.find(function(g) { return g.providerKey === key; });
        if (found) orderedGames.push(found);
    });
    
    orderedGames.forEach(function(game) {
        var safeName = String(game.gamename || '').replace(/'/g, "\\'");
        // Prepare HTML components
        var skeletonHtml = '<div class="skeleton-loader"></div>';
        var imageHtml = '';
        var hasImage = (game.image && game.image.trim() !== '');
        
        if (hasImage) {
             imageHtml = `
                <img src="${game.image}" alt="${game.gamename}"
                     style="opacity: 0; transition: opacity 0.5s ease;"
                     onload="$(this).css('opacity', 1); $(this).siblings('.skeleton-loader').fadeOut(300, function(){ $(this).remove(); });"
                     onerror="$(this).hide();"> 
             `;
        } else {
            // Show placeholder text if no image
             imageHtml = `
                <div style="position: absolute; top:0; left:0; width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#222; color:#fff; font-weight:bold; text-align:center; padding:10px;">
                    ${game.gamename}
                </div>
             `;
             skeletonHtml = ''; // Remove skeleton if we show text immediately
        }
        
        var html = `
            <div class="game-item-col mb-3" style="margin-bottom: 10px;">
                <div class="game-card">
                    <div class="game-img-wrapper">
                        ${skeletonHtml}
                        ${imageHtml}
                        <div class="game-hover">
                            <button class="play-btn" onclick="launchGame('${game.gameid}', '${safeName}')">PLAY NOW</button>
                        </div>
                    </div>
                    <div class="game-info text-center" style="padding: 10px; background: #000; display: none;">
                        <h5 style="color: #c37601; margin: 0; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${game.gamename}</h5>
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
    loadGames();
});
</script>

<style>
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
    padding-top: 133%; /* 3:4 Aspect Ratio (Standard) */
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
</style>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
