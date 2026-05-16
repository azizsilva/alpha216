<?php
include __DIR__ . '/../includes/header.php';
?>

<div class="premium-lobby-container">
    <!-- Lobby Top Banner (Optional/Promo) -->
    <div class="lobby-promo-banner">
        <div class="promo-content">
            <span class="promo-tag">NEW GAMES</span>
            <h1>EXPERIENCE THE BEST CASINO</h1>
            <p>Play over 5000+ premium games from top providers</p>
            <button class="promo-btn">EXPLORE NOW</button>
        </div>
    </div>

    <!-- Lobby Navigation Bar -->
    <div class="lobby-sub-nav">
        <div class="lobby-nav-container container">
            <div class="lobby-categories">
                <div class="lobby-cat-item active" data-category="all">
                    <i class="fa fa-th-large"></i>
                    <span>Hall</span>
                </div>
                <div class="lobby-cat-item" data-category="popular">
                    <i class="fa fa-fire"></i>
                    <span>Populaire</span>
                </div>
                <div class="lobby-cat-item" data-category="slots">
                    <i class="fa fa-gamepad"></i>
                    <span>Slots</span>
                </div>
                <div class="lobby-cat-item" data-category="live">
                    <i class="fa fa-video-camera"></i>
                    <span>Live Casino</span>
                </div>
                <div class="lobby-cat-item" data-category="table">
                    <i class="fa fa-diamond"></i>
                    <span>Table</span>
                </div>
                <div class="lobby-cat-item" data-category="virtual">
                    <i class="fa fa-desktop"></i>
                    <span>Virtual</span>
                </div>
            </div>
            
            <div class="lobby-search-wrapper">
                <div class="lobby-search-input">
                    <i class="fa fa-search"></i>
                    <input type="text" id="gameSearch" placeholder="Rechercher un jeu...">
                </div>
            </div>
        </div>
    </div>

    <div class="lobby-main-content container">
        <div class="lobby-filter-strip">
            <div class="filter-left">
                <span class="results-count"><span id="gameCount">0</span> Jeux trouvés</span>
            </div>
            <div class="filter-right">
                <div class="filter-dropdown">
                    <label>Provider:</label>
                    <select id="providerFilter">
                        <option value="all">Tous les fournisseurs</option>
                    </select>
                </div>
                <div class="filter-dropdown">
                    <label>Trier:</label>
                    <select id="sortFilter">
                        <option value="popular">Plus populaires</option>
                        <option value="az">Alphabétique (A-Z)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Game Grid -->
        <h2 class="lobby-section-title" style="color: #bfff00; font-size: 16px; font-weight: 900; text-transform: uppercase; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">RECENT GAMES <i class="fa fa-angle-right"></i></h2>
        <div id="game-grid" class="premium-game-grid">
            <!-- Skeleton cards while loading -->
            <?php for($i=0; $i<18; $i++): ?>
            <div class="game-card-wrapper skeleton">
                <div class="game-card-inner">
                    <div class="skeleton-media"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<style>
:root {
    --lobby-bg: #0b0b0b;
    --card-bg: #151515;
    --accent-neon: #bfff00;
    --text-white: #ffffff;
    --text-gray: #888888;
    --glass-bg: rgba(255, 255, 255, 0.03);
    --glass-border: rgba(255, 255, 255, 0.08);
}

.premium-lobby-container {
    background-color: var(--lobby-bg);
    min-height: 100vh;
    padding-bottom: 80px;
    font-family: 'Roboto', sans-serif;
}

/* Promo Banner */
.lobby-promo-banner {
    height: 300px;
    background: linear-gradient(135deg, #111 0%, #000 100%);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 0;
}

.lobby-promo-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('assets/images/slide_3_fr_1769540149.png') center/cover no-repeat;
    opacity: 0.3;
    filter: blur(2px);
}

.promo-content {
    position: relative;
    z-index: 2;
    text-align: center;
}

.promo-tag {
    background: var(--accent-neon);
    color: #000;
    padding: 4px 12px;
    font-weight: 800;
    font-size: 12px;
    border-radius: 4px;
    letter-spacing: 1px;
}

.promo-content h1 {
    color: #fff;
    font-size: 42px;
    font-weight: 900;
    margin: 15px 0;
    text-shadow: 0 0 20px rgba(0,0,0,0.5);
}

.promo-content p {
    color: var(--text-gray);
    font-size: 16px;
    margin-bottom: 25px;
}

.promo-btn {
    background: transparent;
    border: 2px solid var(--accent-neon);
    color: var(--accent-neon);
    padding: 12px 30px;
    font-weight: 700;
    border-radius: 30px;
    transition: all 0.3s;
}

.promo-btn:hover {
    background: var(--accent-neon);
    color: #000;
    box-shadow: 0 0 15px rgba(191, 255, 0, 0.4);
}

/* Sub Nav */
.lobby-sub-nav {
    background: #111;
    border-bottom: 1px solid var(--glass-border);
    position: sticky;
    top: 0; 
    z-index: 100;
    padding: 10px 0;
}

.lobby-nav-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.lobby-categories {
    display: flex;
    gap: 5px;
    overflow-x: auto;
    scrollbar-width: none;
}

.lobby-cat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.3s;
    border-radius: 8px;
    white-space: nowrap;
}

.lobby-cat-item i {
    font-size: 18px;
    color: var(--accent-neon);
}

.lobby-cat-item span {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
}

.lobby-cat-item:hover, .lobby-cat-item.active {
    background: var(--glass-bg);
    color: #fff;
}

.lobby-cat-item.active {
    border-bottom: 2px solid var(--accent-neon);
    border-radius: 8px 8px 0 0;
}

/* Search */
.lobby-search-wrapper {
    flex-shrink: 0;
}

.lobby-search-input {
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 30px;
    padding: 8px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    width: 300px;
}

.lobby-search-input i {
    color: var(--text-gray);
}

.lobby-search-input input {
    background: transparent;
    border: none;
    color: #fff;
    width: 100%;
    outline: none;
    font-size: 14px;
}

/* Filter Strip */
.lobby-filter-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 30px 0 20px;
    padding: 15px 20px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
}

.results-count {
    color: var(--text-gray);
    font-size: 13px;
    font-weight: 600;
}

.filter-right {
    display: flex;
    gap: 20px;
}

.filter-dropdown {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-dropdown label {
    color: var(--text-gray);
    font-size: 12px;
    margin-bottom: 0;
}

.filter-dropdown select {
    background: #000;
    border: 1px solid #333;
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    outline: none;
    cursor: pointer;
}

/* Game Grid */
.premium-game-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 20px;
}

.game-card-wrapper {
    position: relative;
    aspect-ratio: 1 / 1.45; 
}

.game-card-inner {
    width: 100%;
    height: 100%;
    background: var(--card-bg);
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    border: 1px solid var(--glass-border);
}

.game-card-inner:hover {
    transform: translateY(-8px);
    border-color: var(--accent-neon);
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
}

.game-card-inner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.game-card-inner:hover img {
    transform: scale(1.1);
}

.game-overlay-hover {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.game-card-inner:hover .game-overlay-hover {
    opacity: 1;
}

.play-btn-circle {
    width: 54px;
    height: 54px;
    background: var(--accent-neon);
    color: #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    box-shadow: 0 0 15px var(--accent-neon);
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

/* Skeletons */
.skeleton .skeleton-media {
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #151515 25%, #222 50%, #151515 75%);
    background-size: 200% 100%;
    animation: lobbyShimmer 1.5s infinite;
}

@keyframes lobbyShimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* Responsive */
@media (max-width: 1200px) {
    .premium-game-grid { grid-template-columns: repeat(5, 1fr); }
}

@media (max-width: 991px) {
    .premium-game-grid { grid-template-columns: repeat(4, 1fr); }
    .lobby-search-input { width: 220px; }
    .promo-content h1 { font-size: 32px; }
}

@media (max-width: 767px) {
    .premium-game-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .lobby-sub-nav { padding: 5px 0; }
    .lobby-nav-container { flex-direction: column; gap: 10px; }
    .lobby-search-input { width: 100%; }
    .lobby-filter-strip { flex-direction: column; gap: 15px; align-items: flex-start; }
    .filter-right { width: 100%; justify-content: space-between; }
    .lobby-promo-banner { height: 200px; }
    .promo-content h1 { font-size: 24px; }
    .promo-content p { font-size: 13px; }
    .premium-lobby-container { padding-bottom: 60px; }
    .lobby-categories { width: 100%; }
}
</style>

<script>
$(document).ready(function() {
    var allGames = [];
    var providers = new Set();
    var currentCategory = 'all';

    function loadAllGames() {
        const jsonFiles = [
            'MAC88.json', 'Ace__AgGaming_.json', 'Evolution_Live.json', 
            'Ezugi.json', 'Sexy.json', 'PragmaticPlay_Live_-_Asia.json', 'DreamGaming.json',
            'CQ9.json', 'JDB.json', 'Mini.json', 'Smartsoft.json', 'turbogames-world.json',
            'Netent.json', 'KY_Gaming.json'
        ];
        
        let loadedCount = 0;
        const base = (typeof window.SITE_BASE_URL === 'string' ? window.SITE_BASE_URL : '/');

        jsonFiles.forEach(file => {
            $.getJSON(base + 'games-json/' + file, function(data) {
                if (Array.isArray(data)) {
                    data.forEach(game => {
                        let prov = file.replace('.json', '').replace(/__/g, ' ').replace(/_/g, ' ');
                        game.provider_label = prov;
                        providers.add(prov);
                        allGames.push(game);
                    });
                }
            }).always(() => {
                loadedCount++;
                if (loadedCount === jsonFiles.length) {
                    initLobby();
                }
            });
        });
    }

    function initLobby() {
        const provSelect = $('#providerFilter');
        Array.from(providers).sort().forEach(p => {
            provSelect.append(`<option value="${p}">${p}</option>`);
        });

        $('.lobby-cat-item').on('click', function() {
            $('.lobby-cat-item').removeClass('active');
            $(this).addClass('active');
            currentCategory = $(this).data('category');
            renderLobby();
        });

        $('#providerFilter, #sortFilter').on('change', renderLobby);
        $('#gameSearch').on('input', renderLobby);

        renderLobby();
    }

    function renderLobby() {
        const search = $('#gameSearch').val().toLowerCase();
        const provider = $('#providerFilter').val();
        const sort = $('#sortFilter').val();

        let filtered = allGames.filter(g => {
            const matchesSearch = g.gamename.toLowerCase().includes(search);
            const matchesProvider = provider === 'all' || g.provider_label === provider;
            
            let matchesCategory = true;
            if (currentCategory === 'slots') {
                matchesCategory = !g.gamename.toLowerCase().includes('live') && !g.gamename.toLowerCase().includes('roulette') && !g.gamename.toLowerCase().includes('baccarat');
            } else if (currentCategory === 'live') {
                matchesCategory = g.gamename.toLowerCase().includes('live') || g.gamename.toLowerCase().includes('roulette') || g.gamename.toLowerCase().includes('baccarat');
            } else if (currentCategory === 'virtual') {
                matchesCategory = g.gamename.toLowerCase().includes('virtual');
            }
            
            return matchesSearch && matchesProvider && matchesCategory;
        });

        if (sort === 'az') {
            filtered.sort((a, b) => a.gamename.localeCompare(b.gamename));
        }

        $('#gameCount').text(filtered.length);

        const container = $('#game-grid');
        container.empty();

        if (filtered.length === 0) {
            container.html('<div style="grid-column: 1/-1; text-align:center; padding: 100px; color: #555;"><i class="fa fa-search" style="font-size: 40px; margin-bottom: 20px;"></i><p>AUCUN JEU TROUVÉ</p></div>');
            return;
        }

        filtered.forEach(game => {
            const card = `
                <div class="game-card-wrapper">
                    <div class="game-card-inner" onclick="mkSafeLaunch('${game.gameid}', '${game.gamename.replace(/'/g, "\\'")}')">
                        <img src="${game.image}" alt="${game.gamename}" loading="lazy">
                        <div class="game-overlay-hover">
                            <div class="play-btn-circle"><i class="fa fa-play"></i></div>
                        </div>
                    </div>
                </div>
            `;
            container.append(card);
        });
    }

    loadAllGames();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
