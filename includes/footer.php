<?php
if (!isset($asset_path)) {
    $asset_path = 'assets/';
}

$mk_fragment = isset($_GET['mk_fragment']);
if ($mk_fragment) {
    return;
}
?>
</main>
<!-- Mobile Bottom Navigation (Hidden on Desktop) -->
<div class="mobile-bottom-nav">
    <a href="/casino" class="mbn-item">
        <span style="font-size: 24px;">🍒</span>
        <span>Casino</span>
    </a>
    <a href="#" onclick="mkSafeLaunch('8a704858d5deb4af1ddc722092ac7614', 'Sports'); return false;" class="mbn-item">
        <span style="font-size: 24px;">⚽</span>
        <span>Paris sportifs</span>
    </a>
    <a href="<?php echo $base_url; ?>promos" class="mbn-item">
        <span style="font-size: 24px;">🎁</span>
        <span>Promos</span>
    </a>
    <a href="<?php echo $base_url; ?>search" class="mbn-item">
        <span style="font-size: 24px;">🔍</span>
        <span>Recherche</span>
    </a>
    <div class="mbn-item" onclick="document.getElementById('mobileLeftSidebar').classList.toggle('active')">
        <span style="font-size: 24px;">🍔</span>
        <span>Menu</span>
    </div>
</div>
 
<footer class="premium-footer">
    <div class="container-fluid">
        <!-- Top Logo -->
        <div class="fc-footer-logo-wrap text-center">
            <img src="<?php echo $base_url; ?>assets/images/logo_bets.jpeg" alt="alpina216" class="fc-main-logo">
        </div>

        <!-- Main Links -->
        <div class="fc-footer-main-links">
            <a href="/sports">PARIS SPORTIFS</a>
            <a href="/sportsbook">PARIS EN DIRECT</a>
            <a href="/casino">JEUX D'ADRESSE</a>
            <a href="/casino-games/live-casino">CASINO EN DIRECT</a>
            <a href="/casino-games/virtual-sports">VIRTUEL</a>
            <a href="/casino-games/spaceman">SPACEMAN</a>
            <a href="/casino-games/zeppelin">ZEPPELIN</a>
        </div>

        <!-- Secondary Links -->
        <div class="fc-footer-secondary-links">
            <a href="/about">À Propos de Nous</a>
            <a href="/privacy">Politique de Confidentialité</a>
            <a href="/terms">Termes & Conditions</a>
            <a href="/responsible-gambling">Jeu Responsable</a>
            <a href="/rules">Règles du Bookmaker</a>
            <a href="/promos">Promotions</a>
        </div>

        <!-- Providers Section -->
        <div class="fc-providers-section">
            <h4 class="fc-section-title">FOURNISSEURS DE CASINOS</h4>
            <div class="fc-providers-grid">
                <!-- Row 1 -->
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/pragmratic.png" alt="Pragmatic Play"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1658314_evolution_logo_online_250x100-white.png" alt="Evolution"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1654603_Amatic%20250x100.png" alt="Amatic"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1654605_PGSoft-250x100.png" alt="PG Soft"></div>
                <div class="provider-box"><span class="provider-text-logo">3 OAKS</span></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1654685_Spribe-250x100.png" alt="Spribe"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1659107_habanero-white-250x100.png" alt="Habanero"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1770316_Pragmatic20Live.png" alt="Pragmatic Live"></div>

                <!-- Row 2 -->
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1655807_Ruby-Play-250x100.png" alt="Ruby Play"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1658410_Ezugi.png" alt="Ezugi"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1726671_Hacksaw_logo.png" alt="Hacksaw Gaming"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1765463_Enjoy.png" alt="Enjoy"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1684250_Upgaming_250x100.png" alt="Upgaming"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1770316_Playson.png" alt="Playson"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1654681_flexsy_250x100_white.png" alt="Flexsy"></div>
                <div class="provider-box"><img src="<?php echo $base_url; ?>images/1653303_kagaming.png" alt="KA Gaming"></div>
            </div>
        </div>


        <!-- Crypto Section -->
        <div class="fc-crypto-section">
            <h4 class="fc-section-title">CRYPTO PAYMENTS</h4>
            <div class="fc-crypto-icons">
                <img src="https://cryptologos.cc/logos/bitcoin-btc-logo.svg?v=025" alt="BTC" class="crypto-icon">
                <img src="https://cryptologos.cc/logos/tether-usdt-logo.svg?v=025" alt="USDT" class="crypto-icon">
                <img src="https://cryptologos.cc/logos/solana-sol-logo.svg?v=025" alt="SOL" class="crypto-icon">
                <img src="https://cryptologos.cc/logos/ethereum-eth-logo.svg?v=025" alt="ETH" class="crypto-icon">
                <img src="https://cryptologos.cc/logos/xrp-xrp-logo.svg?v=025" alt="XRP" class="crypto-icon">
                <img src="https://cryptologos.cc/logos/usd-coin-usdc-logo.svg?v=025" alt="USDC" class="crypto-icon">
                <img src="https://cryptologos.cc/logos/bnb-bnb-logo.svg?v=025" alt="BNB" class="crypto-icon">
            </div>
        </div>

        <!-- Copyright -->
        <div class="fc-copyright-section">
            <p>Copyright 2026 &copy; alpina216</p>
            <p>All Rights Reserved 2026</p>
        </div>

        <!-- Responsible Gaming -->
        <div class="fc-responsible-section">
            <div class="fc-age-badge">18<sup>+</sup></div>
            <a href="/responsible-gambling" class="fc-responsible-btn">JOUEZ DE<br>MANIÈRE<br>RESPONSABLE</a>
            <div class="fc-separator-line"></div>
            <a href="#" class="fc-social-icon"><i class="fa fa-facebook"></i></a>
        </div>

        <!-- Disclaimer -->
        <div class="fc-disclaimer-section">
            <p>Tous les produits sont exploités par alpina216. Le gain maximal par mise est 100.000 TND, voir termes et conditions.</p>
        </div>
    </div>
</footer>

<style>
.premium-footer {
    background: #111;
    color: #fff;
    padding: 60px 0 20px;
    border-top: 1px solid #222;
    margin-top: 50px;
    font-family: 'Work Sans', sans-serif;
}

.fc-footer-logo-wrap {
    margin-bottom: 40px;
}

.fc-main-logo {
    height: 60px;
    filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.2));
}

.fc-footer-main-links {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 20px 30px;
    margin-bottom: 25px;
}

.fc-footer-main-links a {
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.fc-footer-main-links a:hover {
    color: #bfff00;
}

.fc-footer-secondary-links {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 15px 25px;
    margin-bottom: 50px;
}

.fc-footer-secondary-links a {
    color: #ccc;
    font-size: 12px;
}

.fc-footer-secondary-links a:hover {
    color: #fff;
}

.fc-section-title {
    text-align: center;
    color: #39FF14;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 25px;
    letter-spacing: 0.5px;
}

.fc-providers-section {
    padding: 40px 10px;
    background: #0f0f0f;
}

.fc-providers-section .fc-section-title {
    color: #bfff00;
    font-size: 14px;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 25px;
    letter-spacing: 1px;
}

.fc-providers-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    max-width: 1200px;
    margin: 0 auto;
}

@media (min-width: 768px) {
    .fc-providers-grid {
        grid-template-columns: repeat(8, 1fr);
    }
}

.provider-box {
    background: #1a1a1a;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    border-radius: 4px;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
}

.provider-box:hover {
    background: #222;
    border-color: #bfff00;
}

.provider-box img {
    max-width: 100%;
    max-height: 80%;
    object-fit: contain;
    /* Neon Green Filter: #bfff00 */
    filter: brightness(0) saturate(100%) invert(86%) sepia(87%) saturate(1054%) hue-rotate(24deg) brightness(108%) contrast(105%);
}

.provider-text-logo {
    color: #bfff00;
    font-weight: 800;
    font-size: 11px;
    text-align: center;
    line-height: 1.2;
}

.fc-crypto-section {
    margin-bottom: 40px;
}

.fc-crypto-icons {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.crypto-icon {
    width: 30px;
    height: 30px;
    background: #fff;
    border-radius: 50%;
    padding: 5px;
}

.fc-copyright-section {
    text-align: center;
    margin-bottom: 40px;
}

.fc-copyright-section p {
    color: #fff;
    font-size: 12px;
    margin: 0 0 5px;
}

.fc-responsible-section {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-bottom: 40px;
}

.fc-age-badge {
    width: 40px;
    height: 40px;
    border: 2px solid #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
}

.fc-age-badge sup {
    font-size: 10px;
    top: -0.2em;
}

.fc-responsible-btn {
    border: 1px solid #fff;
    padding: 5px 15px;
    color: #fff;
    font-size: 10px;
    text-align: center;
    font-weight: 700;
    line-height: 1.2;
    border-radius: 4px;
}

.fc-responsible-btn:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.fc-separator-line {
    height: 30px;
    width: 1px;
    background: #555;
}

.fc-social-icon {
    width: 35px;
    height: 35px;
    background: #fff;
    color: #222;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.fc-social-icon:hover {
    background: #ccc;
    color: #000;
}

.fc-disclaimer-section {
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
}

.fc-disclaimer-section p {
    color: #888;
    font-size: 11px;
    line-height: 1.5;
    margin: 0;
}

@media (max-width: 991px) {
    .fc-providers-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 767px) {
    .fc-providers-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .fc-footer-main-links {
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
    .fc-footer-secondary-links {
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    .fc-responsible-section {
        flex-wrap: wrap;
    }
}
</style>

<!-- Footer Scripts -->
<?php
$mk_js_ver = 1;
$mk_app_js_ver = 1;
try {
    $p = dirname(__DIR__) . '/assets/js/game-launcher.js';
    if (file_exists($p)) $mk_js_ver = (int)@filemtime($p);
    $app_p = dirname(__DIR__) . '/assets/js/app-main.js';
    if (file_exists($app_p)) $mk_app_js_ver = (int)@filemtime($app_p);
} catch (Exception $e) {
}
?>

<script src="<?php echo $absolute_base_url; ?>includes/balance-sync.js?v=<?php echo (int)$mk_js_ver; ?>"></script>
<script src="<?php echo $asset_path; ?>js/app-main.js?v=<?php echo (int)$mk_app_js_ver; ?>"></script>
<script>
    (function () {
        try {
            var loggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
            if (!loggedIn) return;

            var SPORTS_ID = '8a704858d5deb4af1ddc722092ac7614';
            var SPORTSBOOK_ID = '8a704858d5deb4af1ddc722092ac7614';
            var ROUTE_MAP = {
                '/sports': { id: SPORTS_ID, title: 'Sports' },
                '/sportsbook': { id: SPORTSBOOK_ID, title: 'Sports Book' }
            };

            function normalizePath(p) {
                p = String(p || '/');
                p = p.split('?')[0];
                if (!p.startsWith('/')) p = '/' + p;
                p = p.replace(/\/index\.php$/i, '');
                if (p === '') p = '/';
                if (p.length > 1 && p.endsWith('/')) p = p.slice(0, -1);
                return p;
            }

            function ensureStash() {
                var el = document.getElementById('mkPreloadStash');
                if (el) return el;
                el = document.createElement('div');
                el.id = 'mkPreloadStash';
                el.style.position = 'fixed';
                el.style.left = '-9999px';
                el.style.top = '-9999px';
                el.style.width = '1px';
                el.style.height = '1px';
                el.style.overflow = 'hidden';
                el.style.opacity = '0';
                el.style.pointerEvents = 'none';
                document.body.appendChild(el);
                return el;
            }

            function ensureFrame(gameId) {
                window.__MK_PRELOADED_FRAMES = window.__MK_PRELOADED_FRAMES || {};
                var f = window.__MK_PRELOADED_FRAMES[gameId];
                if (f && f.tagName === 'IFRAME') return f;
                var stash = ensureStash();
                f = document.createElement('iframe');
                f.setAttribute('allowfullscreen', 'true');
                f.setAttribute('data-game-id', gameId);
                f.style.width = '100%';
                f.style.height = '100%';
                f.style.border = '0';
                stash.appendChild(f);
                window.__MK_PRELOADED_FRAMES[gameId] = f;
                return f;
            }

            function mountFrameForRoute(route) {
                try {
                    route = normalizePath(route);
                    var cfg = ROUTE_MAP[route];
                    if (!cfg) return;
                    var gameId = cfg.id;
                    var f = window.__MK_PRELOADED_FRAMES && window.__MK_PRELOADED_FRAMES[gameId];
                    if (!f || !f.src) return;

                    var scope = document;
                    var container = scope.querySelector('.game-container');
                    if (!container) return;
                    var existing = container.querySelector('iframe');
                    if (existing && existing !== f) {
                        try { existing.parentNode.removeChild(existing); } catch (eR) {}
                    }
                    f.id = 'gameFrame';
                    f.style.width = '100%';
                    f.style.height = '100%';
                    f.style.border = '0';
                    try { container.appendChild(f); } catch (eA) {}
                } catch (e) {}
            }

            function unmountFrameForRoute(route) {
                try {
                    route = normalizePath(route);
                    var cfg = ROUTE_MAP[route];
                    if (!cfg) return;
                    var gameId = cfg.id;
                    var f = window.__MK_PRELOADED_FRAMES && window.__MK_PRELOADED_FRAMES[gameId];
                    if (!f) return;
                    var stash = ensureStash();
                    if (f.parentNode && f.parentNode !== stash) stash.appendChild(f);
                } catch (e) {}
            }

            window.MK_BEFORE_ROUTE_CHANGE = function (from, to) {
                try { if (from) unmountFrameForRoute(from); } catch (e) {}
            };
            window.MK_AFTER_ROUTE_CHANGE = function (path) {
                try { mountFrameForRoute(path); } catch (e) {}
            };

            var games = [
                { id: SPORTS_ID, key: '__MK_PRELAUNCH_TS_sports_api' }
            ];
            var now = Date.now();
            if (window.__MK_PRELAUNCH_STARTED) return;
            window.__MK_PRELAUNCH_STARTED = 1;

            var apiUrl = (typeof SITE_API_URL !== 'undefined') ? SITE_API_URL + 'launch_game.php' : 'api/launch_game.php';

            var run = function () {
                try {
                    var filtered = [];
                    for (var i = 0; i < games.length; i++) {
                        var g = games[i];
                        var last = 0;
                        try { last = Number(sessionStorage.getItem(g.key) || 0) || 0; } catch (e1) {}
                        if (now - last >= 4 * 60 * 1000) filtered.push(g);
                    }
                    if (!filtered.length) return;

                    var idx = 0;
                    var doOne = function () {
                        var g = filtered[idx++];
                        if (!g) return;
                        try { sessionStorage.setItem(g.key, String(Date.now())); } catch (e2) {}
                        var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
                        var payload = { game_id: g.id, home_url: origin + '/sports/?mk=1', prefetch: 1, skip_log: true };

                        if (window.fetch) {
                            fetch(apiUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(payload),
                                cache: 'no-store'
                            }).then(function (r) {
                                return r.json().catch(function () { return null; });
                            }).then(function (res) {
                                if (res && res.success && res.game_url) {
                                    window.MK_GAME_CACHE = window.MK_GAME_CACHE || {};
                                    window.MK_GAME_CACHE[g.id] = { url: res.game_url, ts: Date.now() };
                                    try {
                                        var fr = ensureFrame(g.id);
                                        if (fr && fr.src !== res.game_url) fr.src = res.game_url;
                                    } catch (eF) {}
                                    try {
                                        var cur = normalizePath(window.location.pathname || '/');
                                        mountFrameForRoute(cur);
                                    } catch (eM) {}
                                    try {
                                        var u = new URL(res.game_url, window.location.origin);
                                        if (typeof MK_preconnectOrigin === 'function') MK_preconnectOrigin(u.origin);
                                    } catch (e3) {}
                                }
                            }).catch(function () {}).finally(function () {
                                setTimeout(doOne, 120);
                            });
                        } else if (window.$ && $.ajax) {
                            $.ajax({
                                url: apiUrl,
                                type: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify(payload),
                                timeout: 2800,
                                complete: function () { setTimeout(doOne, 120); }
                            });
                        }
                    };
                    doOne();
                } catch (e) {}
            };

            try { setTimeout(run, 450); } catch (eT0) {}
            if ('requestIdleCallback' in window) requestIdleCallback(run, { timeout: 1200 });
        } catch (e) {}
    })();
</script>
<script>
    window.MK_INIT_UI = function(){
        function resetOwl($el) {
            try {
                if ($el && $el.length && $el.data('owl.carousel')) {
                    $el.trigger('destroy.owl.carousel');
                    $el.find('.owl-stage-outer').children().unwrap();
                    $el.removeClass('owl-center owl-loaded owl-text-select-on');
                }
            } catch (e) {
            }
        }
        // Mobile Banner Carousel
        resetOwl($(".mobile-banner-carousel"));
        $(".mobile-banner-carousel").owlCarousel({
            items: 1,
            loop: true,
            autoplay: true,
            autoplayTimeout: 3000,
            autoplayHoverPause: true,
            dots: false, /* Explicitly false */
            nav: false,
            margin: 4, /* "gap halo 3-4px ka" - This creates gap between items in carousel */
            stagePadding: 0, /* Ensure full width */
            smartSpeed: 600
        });

        // Main Banner Carousel
        resetOwl($("#pc-carousel"));
        $("#pc-carousel").owlCarousel({
            center: true,
            items: 1.3,
            loop: true,
            margin: 20,
            nav: true,
            navText: ["<i class='fa fa-chevron-left'></i>", "<i class='fa fa-chevron-right'></i>"],
            dots: true,
            autoplay: true,
            autoplayTimeout: 5000,
            autoplayHoverPause: true,
            smartSpeed: 800,
            responsive: {
                0: { items: 1, center: false, margin: 10 },
                768: { items: 1.2, center: true, margin: 15 },
                1000: { items: 1.3, center: true, margin: 20 }
            }
        });

        // Trending Carousel
        resetOwl($(".trending-carousel"));
        $(".trending-carousel").owlCarousel({
            loop: true,
            margin: 10, // Adjusted gap
            margin: 10, // Adjusted gap
            nav: true, // Enable navigation buttons
            navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"], // Custom icons
            dots: false,
            autoplay: false, // Force disabled
            autoplayTimeout: 999999, // Extremely long timeout as backup
            autoplayHoverPause: true,
            smartSpeed: 400, // Faster transition
            slideBy: 'page', // Slide by number of visible items
            responsive: {
                0: {
                    items: 3,
                    margin: 5,
                    slideBy: 3
                },
                600: {
                    items: 4,
                    margin: 10,
                    slideBy: 4
                },
                1000: {
                    items: 5, // 5 items on PC
                    margin: 15,
                    slideBy: 5
                }
            },
            onTranslate: function(event) {
                // Before slide starts: reset animation classes
                // We want existing items to stay normal size, new items to start small
                // But CSS handles "incoming" logic via .owl-item.active.animated-zoom
                // We need to ensure we don't shrink the *outgoing* items.
                // The current CSS: .owl-item.animated-zoom .trending-item { transform: scale(1); }
                // Default CSS: transform: scale(0.9);
                
                // When slide starts, the "active" class changes.
                // We want to keep the "old" active items at scale(1) until they are gone? 
                // Or just let them slide out. 
                
                // Actually, the user said: "jo slide hokr jayega wo wahi same size me rahega"
                // "jo slide hokr new ayega wo halka soft zoom out raehga"
                
                // This means:
                // Outgoing items: Should NOT shrink back to 0.9. They should stay 1.0 while leaving.
                // Incoming items: Should start at 0.9 and grow to 1.0.
                
                // We can achieve this by adding a class to items that have been viewed once.
                // Or simply ensure the "active" state transition handles it.
                
                // Let's rely on onTranslated to add the zoom class to NEW items.
                // And for outgoing items, if we remove the class, they might shrink.
                // To prevent shrinking, we can add a 'viewed' class that keeps them at scale(1).
            },
            onTranslated: function(event) {
                // Trigger animation after slide
                var activeItems = $(event.target).find('.owl-item.active');
                activeItems.removeClass('animated-zoom static-view');
                
                activeItems.each(function(index) {
                    if (index < 2) {
                        $(this).addClass('static-view'); // First 2 stay normal
                    } else {
                        $(this).addClass('animated-zoom'); // Others zoom in
                    }
                });
            },
            onInitialized: function(event) {
                var activeItems = $(event.target).find('.owl-item.active');
                activeItems.each(function(index) {
                    if (index < 2) {
                        $(this).addClass('static-view');
                    } else {
                        $(this).addClass('animated-zoom');
                    }
                });
            }
        });
        
        // Recent Games Carousel (Match Trending Size)
        resetOwl($(".recent-carousel"));
        $(".recent-carousel").owlCarousel({
            loop: false,
            rewind: true,
            margin: 25, // Match Trending Gap
            nav: true, // Enable nav for recent too
            navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"],
            dots: false,
            autoplay: false, // Ensure autoplay is off
            smartSpeed: 400, // Faster transition
            slideBy: 'page', // Slide by one page
            responsive: {
                0: {
                    items: 3, // Match Trending
                    margin: 5,
                    slideBy: 3
                },
                600: {
                    items: 4, // Match Trending
                    margin: 10,
                    slideBy: 4
                },
                1000: {
                    items: 5, // Match Trending
                    margin: 15,
                    slideBy: 5
                }
            },
            onTranslated: function(event) {
                var activeItems = $(event.target).find('.owl-item.active');
                activeItems.removeClass('animated-zoom static-view');
                activeItems.each(function(index) {
                    if (index < 2) {
                        $(this).addClass('static-view');
                    } else {
                        $(this).addClass('animated-zoom');
                    }
                });
            },
            onInitialized: function(event) {
                var activeItems = $(event.target).find('.owl-item.active');
                activeItems.each(function(index) {
                    if (index < 2) {
                        $(this).addClass('static-view');
                    } else {
                        $(this).addClass('animated-zoom');
                    }
                });
            }
        });

        // Game Launch Integration
        $(document).off('click.mkGameCards', '.trending-item, .recent-item, .casino-box');
        $(document).on('click.mkGameCards', '.trending-item, .recent-item, .casino-box', function(e) {
            e.preventDefault();
            
            var gameId = $(this).data('game-id');
            // If casino-box doesn't have game-id yet, fallback or ignore
            if (!gameId) {
                // Try to find image alt or something if needed, but for now stick to trending-item which has data-game-id
                if ($(this).hasClass('casino-box')) {
                    // console.log('Casino box clicked, no game ID yet');
                    return; 
                }
                return;
            }

            // Use centralized launcher
            if (typeof launchGame === 'function') {
                launchGame(gameId);
            } else {
                console.error('launchGame function not found');
            }
        });
    };
    $(document).ready(function(){ if (typeof window.MK_INIT_UI === 'function') window.MK_INIT_UI(); });
</script>
</body>
</html>
