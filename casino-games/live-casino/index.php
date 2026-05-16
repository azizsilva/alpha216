<?php
include __DIR__ . '/../../includes/header.php';
?>

<div class="lobby-modern-container">
    <div class="lobby-sub-nav" style="background: #111; padding: 15px; border-bottom: 1px solid #222;">
        <div class="lobby-nav-container container" style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <div class="lobby-categories" style="display: flex; gap: 25px; overflow-x: auto; align-items: center; flex: 1;">
                <div class="lobby-cat-item" data-category="hall">
                    <span>🏰</span>
                    <span class="lbl">Hall</span>
                </div>
                <div class="lobby-cat-item" data-category="popular">
                    <span>🔥</span>
                    <span class="lbl">Populaire</span>
                </div>
                <div class="lobby-cat-item" data-category="new">
                    <span>🎁</span>
                    <span class="lbl">Nouveautés</span>
                </div>
                <div class="lobby-cat-item" data-category="slots">
                    <span>🍒</span>
                    <span class="lbl">Machines à Sous</span>
                </div>
                <div class="lobby-cat-item" data-category="crash">
                    <span>🚀</span>
                    <span class="lbl">Jeux de Crash</span>
                </div>
                <div class="lobby-cat-item" data-category="dw">
                    <span>🎯</span>
                    <span class="lbl">D&W</span>
                </div>
                <div class="lobby-cat-item" data-category="pragmatic">
                    <span>🎰</span>
                    <span class="lbl">Pragmatic</span>
                </div>
                <div class="lobby-cat-item" data-category="providers">
                    <span>🎲</span>
                    <span class="lbl">Fournisseur</span>
                </div>
                <div class="lobby-cat-item active" data-category="live">
                    <span>🃏</span>
                    <span class="lbl">Casino en Direct</span>
                </div>
            </div>
            
            <div class="lobby-search-wrapper" style="width: 250px;">
                <div class="lobby-search-input" style="background: #fff; border-radius: 4px; padding: 8px 15px;">
                    <i class="fa fa-search" style="color: #333;"></i>
                    <input type="text" id="gameSearch" placeholder="Chercher" style="color: #333; background: transparent; border: none; outline: none; margin-left: 8px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Page Content Container -->
    <div class="lobby-content-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 class="lobby-section-title" style="color: #fff; font-size: 20px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px;">
                🃏 Casino en Direct
            </h2>
            <div class="lobby-filters" style="display: flex; gap: 10px;">
                <select id="providerFilter" style="background: #222; color: #fff; border: 1px solid #333; padding: 6px 12px; border-radius: 4px; outline: none;">
                    <option value="all">Tous les fournisseurs</option>
                </select>
                <select id="sortFilter" style="background: #222; color: #fff; border: 1px solid #333; padding: 6px 12px; border-radius: 4px; outline: none;">
                    <option value="popular">Plus populaires</option>
                    <option value="az">Alphabétique (A-Z)</option>
                </select>
            </div>
        </div>

        <!-- Game Grid -->
        <div id="game-grid" class="game-grid-row">
            <!-- Skeleton cards while loading -->
            <?php for($i=0; $i<16; $i++): ?>
            <div class="game-item-col">
                <div class="game-card skeleton">
                    <div class="game-img-wrapper">
                        <div class="skeleton-shimmer"></div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<style>
:root {
    --bg-lobby: #1a1a1a;
    --bg-card: #242424;
    --accent: #72f238;
    --text-muted: #888;
}

.lobby-modern-container {
    padding: 20px 0;
    min-height: 100vh;
    background-color: #0f0f0f;
}

/* Nav Wrapper */
.lobby-nav-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 20px;
    background: #000;
    margin-bottom: 20px;
    gap: 20px;
}

.lobby-nav-scroll {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    scrollbar-width: none;
}

.lobby-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    min-width: 80px;
    transition: all 0.3s;
    color: var(--text-muted);
    text-decoration: none !important;
}

.lobby-nav-item .lobby-nav-icon {
    font-size: 20px;
    margin-bottom: 5px;
}

.lobby-nav-item span {
    font-size: 11px;
    white-space: nowrap;
}

.lobby-nav-item.active, .lobby-nav-item:hover {
    color: #fff;
}

.lobby-nav-item.active .lobby-nav-icon {
    background: var(--accent);
    color: #000;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

/* Search Box */
.lobby-search-box .search-input-wrapper {
    position: relative;
    background: #222;
    border-radius: 8px;
    padding: 8px 15px;
    display: flex;
    align-items: center;
    width: 250px;
}

.lobby-search-box input {
    background: transparent;
    border: none;
    color: #fff;
    margin-left: 10px;
    width: 100%;
    outline: none;
}

/* Content Area */
.lobby-content-container {
    background: #1a1a1a;
    margin: 0 20px;
    border-radius: 12px;
    padding: 20px;
}

.lobby-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 20px;
}

.lobby-title-box {
    display: flex;
    align-items: center;
    gap: 15px;
}

.lobby-title-box h2 {
    color: #fff;
    font-size: 20px;
    margin: 0;
}

.back-btn {
    width: 35px;
    height: 35px;
    background: #333;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    text-decoration: none;
}

.lobby-filters {
    display: flex;
    gap: 15px;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #000;
    padding: 5px 15px;
    border-radius: 8px;
    border: 1px solid #333;
}

.filter-group label {
    color: #888;
    font-size: 12px;
    margin: 0;
}

.filter-group select {
    background: transparent;
    border: none;
    color: #fff;
    outline: none;
    font-size: 13px;
    cursor: pointer;
}

/* Game Grid */
.game-grid-row {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
}

.game-item-col {
    position: relative;
}

.game-card {
    background: var(--bg-card);
    border-radius: 12px;
    overflow: hidden;
    position: relative;
    aspect-ratio: 1 / 1;
    cursor: pointer;
    transition: transform 0.3s;
}

.game-card:hover {
    transform: translateY(-5px);
}

.game-img-wrapper {
    width: 100%;
    height: 100%;
    position: relative;
}

.game-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.game-hover-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.game-card:hover .game-hover-overlay {
    opacity: 1;
}

.play-circle {
    width: 50px;
    height: 50px;
    background: var(--accent);
    color: #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 0 15px var(--accent);
}

.game-info-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.5) 50%, transparent 100%);
    padding: 30px 10px 10px;
    text-align: center;
    z-index: 2;
}

.game-title {
    color: #fff;
    font-size: 18px;
    font-weight: 900;
    text-transform: uppercase;
    line-height: 1.1;
    margin-bottom: 4px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.8);
}

.game-provider {
    color: #ccc;
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Skeleton */
.skeleton .skeleton-shimmer {
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #222 25%, #333 50%, #222 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

@media (max-width: 1200px) {
    .game-grid-row { grid-template-columns: repeat(5, 1fr); }
}

@media (max-width: 991px) {
    .game-grid-row { grid-template-columns: repeat(4, 1fr); }
}

@media (max-width: 767px) {
    .lobby-header-row {
        flex-direction: column;
        align-items: flex-start;
    }
    .lobby-nav-wrapper {
        flex-direction: column;
        align-items: flex-start;
    }
    .lobby-search-box .search-input-wrapper {
        width: 100%;
    }
    .game-grid-row {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    .lobby-content-container {
        padding: 10px;
        margin: 0;
    }
}
</style>

<script>
$(document).ready(function() {
    var allGames = [];
    var providers = new Set();

    function loadGames() {
        const jsonFiles = [
            'Evolution_Live.json', 'PragmaticPlay_Live_-_Asia.json', 
            'Sexy.json', 'Ezugi.json', 'DreamGaming.json', 'CreedRoomz.json',
            'WM.json', 'SaGaming.json', 'CasinoGame.json', 'GAMINGSOFT-AI_LIVE_CASINO.json'
        ];
        
        let loaded = 0;
        const base = (typeof window.SITE_BASE_URL === 'string' ? window.SITE_BASE_URL : '/');
        jsonFiles.forEach(file => {
            $.getJSON(base + 'games-json/' + file, function(data) {
                data.forEach(game => {
                    const provider = file.split('.')[0].replace('__', ' ').replace('_', ' ');
                    game.provider = provider;
                    providers.add(provider);
                    allGames.push(game);
                });
            }).always(() => {
                loaded++;
                if(loaded === jsonFiles.length) {
                    initFilters();
                    renderGames(allGames);
                }
            });
        });
    }

    function initFilters() {
        const select = $('#providerFilter');
        providers.forEach(p => {
            select.append(`<option value="${p}">${p}</option>`);
        });

        $('#providerFilter, #sortFilter, #gameSearch').on('change input', function() {
            filterAndRender();
        });

        $('.lobby-cat-item').on('click', function() {
            const cat = $(this).data('category');
            
            // Handle navigation correctly
            if (cat === 'hall') window.location.href = SITE_BASE_URL + 'casino';
            else if (cat === 'live') window.location.href = SITE_BASE_URL + 'casino-games/live-casino/';
            else if (cat === 'virtual') window.location.href = SITE_BASE_URL + 'casino-games/virtual-sports/';
            else if (cat === 'slots' || cat === 'popular' || cat === 'new' || cat === 'crash' || cat === 'dw' || cat === 'pragmatic' || cat === 'providers') {
                // For other categories, if we are in live-casino, we should redirect to main casino with hash or just main casino
                window.location.href = SITE_BASE_URL + 'casino';
            }
            else {
                $('.lobby-cat-item').removeClass('active');
                $(this).addClass('active');
                filterAndRender();
            }
        });
    }

    function filterAndRender() {
        const search = $('#gameSearch').val().toLowerCase();
        const provider = $('#providerFilter').val();
        const category = $('.lobby-cat-item.active').data('category');
        const sort = $('#sortFilter').val();

        let filtered = allGames.filter(g => {
            const matchesSearch = g.gamename.toLowerCase().includes(search);
            const matchesProvider = provider === 'all' || g.provider === provider;
            
            // Basic category mapping for live casino
            let matchesCategory = true;
            if(category === 'table') matchesCategory = g.gamename.toLowerCase().includes('roulette') || g.gamename.toLowerCase().includes('baccarat') || g.gamename.toLowerCase().includes('blackjack');
            
            return matchesSearch && matchesProvider && matchesCategory;
        });

        if(sort === 'az') {
            filtered.sort((a,b) => a.gamename.localeCompare(b.gamename));
        }

        renderGames(filtered);
    }

    function renderGames(games) {
        const container = $('#game-grid');
        container.empty();
        
        if(games.length === 0) {
            container.html('<div style="color:#888; grid-column: 1/-1; text-align:center; padding: 50px;">Aucun jeu trouvé</div>');
            return;
        }

        games.forEach(game => {
            const card = `
                <div class="game-item-col">
                    <div class="game-card" onclick="mkSafeLaunch('${game.gameid}', '${game.gamename.replace(/'/g, "\\'")}')">
                        <div class="game-img-wrapper">
                            <img src="${game.image}" alt="${game.gamename}" loading="lazy">
                            <div class="game-hover-overlay">
                                <div class="play-circle"><i class="fas fa-play"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.append(card);
        });
    }

    loadGames();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
