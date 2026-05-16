<?php
// $mk_fragment = isset($_GET['mk_fragment']);
// if ($mk_fragment) {
//     require_once __DIR__ . '/app/index.php';
//     exit;
// }
require_once 'includes/db.php';
require_once 'includes/header.php'; 

// Load API Data
$banners_data = require 'api/banners.php'; // Load all banners
$pc_banners = $banners_data['desktop'];    // Extract desktop banners
$mobile_banners_list = $banners_data['mobile']; // Extract mobile banners

$featured = require 'api/featured.php';
$trending_games = require 'api/trending.php';
$marquee_items = require 'api/marquee.php';

function mk_featured_banner_onclick($link) {
    $link = (string)$link;
    if ($link === '/sportsbook/') {
        return "mkSafeLaunch('8a704858d5deb4af1ddc722092ac7614', 'Sports Book'); return false;";
    }
    if ($link === '/sports/') {
        return "mkSafeLaunch('8a704858d5deb4af1ddc722092ac7614', 'Sports'); return false;";
    }
    return "location.href='" . addslashes($link) . "'";
}

// Client-side login check for game launch
echo '<script>
function mkSafeLaunch(gameId, gameName) {
    var isLoggedIn = ' . (isset($_SESSION['user_id']) ? 'true' : 'false') . ';
    if (!isLoggedIn) {
        if (typeof $(\'#loginModal\').modal === \'function\') {
            $(\'#loginModal\').modal(\'show\');
        } else {
            alert(\'Veuillez vous connecter pour jouer.\');
        }
        return false;
    }
    if (typeof launchGame === \'function\') {
        launchGame(gameId, gameName);
    } else {
        console.error(\'launchGame function not found\');
    }
    return false;
}
</script>';


// Basic Mobile Detection function
function is_mobile_device() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}
$is_mobile = is_mobile_device();

function mk_resolve_recent_games(array $recent_game_ids, array $trending_games, $limit = 9) {
    $recent_game_ids = array_values(array_unique(array_filter(array_map('strval', $recent_game_ids))));
    if (empty($recent_game_ids)) {
        return [];
    }
    $limit = max(1, (int)$limit);

    $needed = array_fill_keys($recent_game_ids, true);
    $all_games_map = [];

    foreach ($trending_games as $game) {
        $gid = (string)($game['game_id'] ?? '');
        if ($gid !== '' && isset($needed[$gid])) {
            $all_games_map[$gid] = [
                'game_id' => $gid,
                'name' => (string)($game['name'] ?? 'Game'),
                'image' => (string)($game['image'] ?? '')
            ];
        }
    }

    $json_files = glob(__DIR__ . '/games-json/*.json') ?: [];
    foreach ($json_files as $json_file) {
        if (count($all_games_map) >= count($needed)) {
            break;
        }

        $games = json_decode((string)@file_get_contents($json_file), true);
        if (!is_array($games)) {
            continue;
        }

        foreach ($games as $game) {
            if (!is_array($game)) {
                continue;
            }

            $gid = (string)($game['gameid'] ?? $game['game_id'] ?? '');
            if ($gid === '' || !isset($needed[$gid]) || isset($all_games_map[$gid])) {
                continue;
            }

            $all_games_map[$gid] = [
                'game_id' => $gid,
                'name' => (string)($game['gamename'] ?? $game['name'] ?? 'Game'),
                'image' => (string)($game['image'] ?? '')
            ];
        }
    }

    $recent_games = [];
    foreach ($recent_game_ids as $gid) {
        if (!isset($all_games_map[$gid])) {
            continue;
        }

        $game = $all_games_map[$gid];
        if ($game['image'] === '') {
            continue;
        }
        $recent_games[] = $game;
        if (count($recent_games) >= $limit) {
            break;
        }
    }

    return $recent_games;
}
?>

<!-- Main Banner Slider -->
<div class="premium-slider-wrapper">
    <div id="pc-carousel" class="owl-carousel owl-theme premium-slider">
        <?php
        // Carousel slides
        $slides = [
            [
                'bg' => $base_url . 'images/bg_soccer.jpeg',
                'title' => '',
                'btn1' => 'CONNEXION',
                'btn2' => ''
            ],
            [
                'bg' => $base_url . 'images/bg_alpha.jpeg',
                'title' => '',
                'btn1' => 'CONNEXION',
                'btn2' => ''
            ]
        ];
        
        foreach ($slides as $slide) {
            echo '<div class="item slider-item-alert" style="background: url(\'' . $slide['bg'] . '\') top center / cover no-repeat;">';
            // echo '<div class="slider-overlay-gradient"></div>';
            echo '<div class="slider-content">';
            echo '<h2 style="font-size: 28px; line-height: 1.1;">' . $slide['title'] . '</h2>';
            if (isset($slide['desc'])) {
                echo '<p style="font-size: 14px; font-weight: 500; margin-top: 10px;">' . $slide['desc'] . '</p>';
            }
            echo '<div class="slider-buttons">';
            if ($slide['btn1']) echo '<button class="btn-slider-primary" onclick="$(\'#loginModal\').modal(\'show\');">' . $slide['btn1'] . '</button>';
            if ($slide['btn2']) echo '<button class="btn-slider-secondary" onclick="$(\'#signupModal\').modal(\'show\');">' . $slide['btn2'] . '</button>';
            echo '</div>'; 
            echo '</div>'; 
            echo '</div>'; 
        }
        ?>
    </div>
</div>

<!-- Main Content Area -->
<div class="container-fluid mk-home-container" style="min-height: 80vh; padding: 0 15px;">

    <!-- Top Winners Section -->
    <div class="top-winners-section" style="margin-top: 15px; margin-bottom: 25px;">
        <div class="section-header" style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">
            <span style="font-size: 20px;">🏅</span>
            <h4 style="color: #fff; margin: 0; font-weight: 800; font-size: 16px;">Meilleurs Gagnants</h4>
        </div>
        <div class="top-winners-scroll-wrapper" style="overflow: hidden; white-space: nowrap; position: relative;">
            <div class="top-winners-flex" style="display: inline-flex; animation: scrollWinnersContinuous 40s linear infinite; gap: 12px;">
                <?php
                $top_winners = [
                    ['name' => '5****5', 'win' => '3929.12 د.ت', 'img' => 'https://images.unsplash.com/photo-1518893063132-36e46dbe2428?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'],
                    ['name' => '5****m', 'win' => '3420.00 د.ت', 'img' => 'https://images.unsplash.com/photo-1596838132731-3301c3fd4317?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'],
                    ['name' => '5****4', 'win' => '3261.12 د.ت', 'img' => 'https://images.unsplash.com/photo-1518893063132-36e46dbe2428?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'],
                    ['name' => '0****8', 'win' => '3126.00 د.ت', 'img' => 'https://images.unsplash.com/photo-1605810230434-7631ac76ec81?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'],
                    ['name' => 'K****t', 'win' => '2871.50 د.ت', 'img' => 'https://images.unsplash.com/photo-1596838132731-3301c3fd4317?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80']
                ];
                // Duplicating the array so the continuous scroll is seamless
                $display_tw = array_merge($top_winners, $top_winners, $top_winners);
                foreach ($display_tw as $tw): ?>
                    <div class="tw-card" style="display: inline-block; width: 100px; background: #111; border-radius: 10px; overflow: hidden; border: 1px solid #222; margin-right: 12px;">
                        <img src="<?php echo $tw['img']; ?>" alt="Game" style="width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 8px;">
                        <div class="tw-card-info" style="padding: 8px 5px; text-align: center;">
                            <div class="tw-user" style="color: #eee; font-size: 12px; font-weight: 700; margin-bottom: 2px;"><?php echo $tw['name']; ?></div>
                            <div class="tw-amount" style="color: #28a745; font-weight: 800; font-size: 11px;"><?php echo $tw['win']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>



    <!-- Category Grid -->
    <div class="premium-category-grid" style="margin-top: 10px; margin-bottom: 30px;">
        <div class="category-cards-wrapper">
                <div class="premium-cat-card" onclick="mkSafeLaunch('8a704858d5deb4af1ddc722092ac7614', 'Sports')">
                    <div class="cat-card-inner desktop-cat-img-1" style="background-image: url('<?php echo $base_url; ?>images/slide_1_fr_1768927536.png');">
                        <div class="cat-card-overlay"></div>
                        <div class="cat-card-content">
                            <h3 class="cat-title">PARIS<br>SPORTIFS</h3>
                        </div>
                    </div>
                </div>
                <div class="premium-cat-card" onclick="mkSafeLaunch('8a704858d5deb4af1ddc722092ac7614', 'In-Play')">
                    <div class="cat-card-inner desktop-cat-img-2" style="background-image: url('<?php echo $base_url; ?>images/slide_2_fr_1768927541.png');">
                        <div class="cat-card-overlay"></div>
                        <div class="cat-card-content">
                            <h3 class="cat-title">PARIS EN<br>DIRECT</h3>
                        </div>
                    </div>
                </div>
                <div class="premium-cat-card" onclick="location.href='<?php echo $base_url; ?>casino-games/'">
                    <div class="cat-card-inner desktop-cat-img-3" style="background-image: url('<?php echo $base_url; ?>images/slide_3_fr_1768927547.png');">
                        <div class="cat-card-overlay"></div>
                        <div class="cat-card-content">
                            <h3 class="cat-title">CASINO</h3>
                        </div>
                    </div>
                </div>
                <div class="premium-cat-card" onclick="location.href='<?php echo $base_url; ?>casino-games/live-casino/'">
                    <div class="cat-card-inner desktop-cat-img-4" style="background-image: url('<?php echo $base_url; ?>images/slide_4_fr_1768927552.png');">
                        <div class="cat-card-overlay"></div>
                        <div class="cat-card-content">
                            <h3 class="cat-title">EN DIRECT</h3>
                        </div>
                    </div>
                </div>
                <div class="premium-cat-card" onclick="location.href='<?php echo $base_url; ?>casino-games/virtual-sports/'">
                    <div class="cat-card-inner desktop-cat-img-5" style="background-image: url('<?php echo $base_url; ?>images/slide_5_fr_1768927558.png');">
                        <div class="cat-card-overlay"></div>
                        <div class="cat-card-content">
                            <h3 class="cat-title">VIRTUEL</h3>
                        </div>
                    </div>
                </div>
                <div class="premium-cat-card" onclick="location.href='<?php echo $base_url; ?>promos/'">
                    <div class="cat-card-inner desktop-cat-img-6" style="background-image: url('<?php echo $base_url; ?>images/slide_6_fr_1768927572.png');">
                        <div class="cat-card-overlay"></div>
                        <div class="cat-card-content">
                            <h3 class="cat-title">PROMOTIONS</h3>
                        </div>
                    </div>
                </div>
        </div>
    </div>



    <!-- Recent Section -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <?php
        $recent_stmt = $pdo->prepare("SELECT rg.game_id FROM recent_games rg WHERE rg.user_id = ? ORDER BY rg.played_at DESC LIMIT 40");
        $recent_stmt->execute([$_SESSION['user_id']]);
        $recent_game_ids = $recent_stmt->fetchAll(PDO::FETCH_COLUMN);
        $recent_games = mk_resolve_recent_games($recent_game_ids, $trending_games, 9);
    ?>
    <?php if (!empty($recent_games)): ?>
        <div class="recent-section" style="margin-top: 50px;">
            <div class="section-header">
                <h4 class="recenttit" style="color: var(--primary-neon); text-transform: uppercase; font-weight: 800;">
                    <span data-translate="recent_games">Vos Jeux Récents</span> <i class="fa fa-angle-right" style="margin-left: 10px;"></i>
                </h4>
            </div>
            <div class="recent-carousel-wrapper">
                <div class="recent-carousel owl-carousel owl-theme">
                    <?php foreach ($recent_games as $game): ?>
                    <div class="item recent-item" data-game-id="<?php echo htmlspecialchars($game['game_id']); ?>" onclick="launchGame('<?php echo addslashes($game['game_id']); ?>', '<?php echo addslashes($game['name']); ?>')">
                        <div class="game-img-wrapper" style="position: relative; width: 100%; height: 100%;">
                            <img src="<?php echo htmlspecialchars($game['image']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>">
                            <div class="game-hover-overlay">
                                <div class="play-circle"><i class="fa fa-play"></i></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<style>
/* New Premium Layout Styles */
:root { --primary-neon: #bfff00; }

.winners-carousel-container {
    background: #111;
    border: 1px solid rgba(191, 255, 0, 0.2);
    border-radius: 8px;
    margin: 20px 0;
    padding: 10px;
    display: flex;
    align-items: center;
    overflow: hidden;
}

.winners-title {
    background: var(--primary-neon);
    color: #000;
    padding: 5px 15px;
    font-weight: 800;
    font-size: 12px;
    white-space: nowrap;
    border-radius: 4px;
    margin-right: 20px;
}

.winners-scroll {
    flex: 1;
    overflow: hidden;
    position: relative;
}

.winners-track {
    display: flex;
    gap: 30px;
    animation: scrollWinners 30s linear infinite;
}

@keyframes scrollWinners {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.winner-item {
    display: flex;
    gap: 10px;
    align-items: center;
    white-space: nowrap;
}

.w-name {
    color: #aaa;
    font-size: 13px;
}

.w-amount {
    color: var(--primary-neon);
    font-weight: 700;
    font-size: 13px;
}

/* Top Winners Flex */
.top-winners-scroll-wrapper {
    overflow: hidden;
    padding-bottom: 15px;
}

@keyframes scrollWinnersContinuous {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.top-winners-flex {
    display: inline-flex;
}

.tw-card {
    width: 120px;
    background: #1a1a1a;
    border-radius: 6px;
    overflow: hidden;
    border: none;
    transition: all 0.3s;
}

.tw-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(191, 255, 0, 0.2);
}

.tw-card img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-bottom: 1px solid #333;
}

.tw-card-info {
    padding: 8px 5px;
    text-align: center;
}

.tw-user {
    color: #fff;
    font-size: 11px;
    margin-bottom: 4px;
}

.tw-amount {
    color: #bfff00;
    font-weight: 900;
    font-size: 13px;
    direction: ltr; /* Ensure Arabic/RTL text displays correctly if needed */
}

/* Premium Category Grid */
.category-cards-wrapper {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.premium-cat-card {
    cursor: pointer;
    transition: all 0.3s;
}

.cat-card-inner {
    position: relative;
    height: 100px;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255,255,255,0.1);
}

.cat-card-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.4); /* Slightly lighter for mobile */
    transition: all 0.3s;
}

/* Desktop Only Parity Fixes */
@media (min-width: 768px) {
    .premium-category-grid .category-cards-wrapper {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
    }

    .cat-card-inner {
        height: 160px !important;
        border: 1px solid #333 !important;
        filter: brightness(1.25) contrast(1.15) !important; /* High vibrancy like reference */
        background-blend-mode: normal !important;
    }

    .cat-card-overlay {
        background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.2) 40%, transparent 100%) !important;
        z-index: 1;
    }

    .premium-cat-card:hover .cat-card-inner {
        filter: brightness(1.35) contrast(1.2) !important;
    }

    .cat-card-content {
        padding-bottom: 10px;
        align-items: flex-end !important;
        z-index: 2;
    }

    .cat-card-content h3 {
        font-size: 18px !important;
        font-weight: 900 !important;
        color: #fff !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-shadow: 2px 2px 4px #000;
        margin: 0 !important;
        width: 100%;
        text-align: center;
    }
}

.cat-card-content {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;

    justify-content: center;
    z-index: 2;
    text-align: center;
}

.cat-card-content h3 {
    color: #fff;
    margin: 0;
    font-weight: 900;
    font-size: 22px;
    line-height: 1.2;
    text-transform: uppercase;
    text-shadow: 2px 2px 4px #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;
}

.premium-cat-card:hover .cat-card-inner {
    transform: scale(1.02);
    border-color: var(--primary-neon);
    box-shadow: 0 0 20px rgba(191, 255, 0, 0.3);
}

@media (min-width: 768px) {
    .category-cards-wrapper {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
    }
    .cat-card-inner {
        height: 160px;
    }
    .cat-card-content {
        align-items: flex-end;
        padding-bottom: 15px;
    }
    .cat-card-content h3 {
        font-size: 16px;
    }
}

/* Trending & Recent Items */
.trending-item, .recent-item {
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s;
    background: #111;
    cursor: pointer;
}

.trending-item:hover, .recent-item:hover {
    border-color: var(--primary-neon);
    transform: translateY(-5px);
    box-shadow: 0 0 15px rgba(191, 255, 0, 0.2);
}

.trending-item img, .recent-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.trending-item {
    aspect-ratio: 3 / 4;
}

.recent-item {
    aspect-ratio: 1 / 1;
    position: relative;
}

.game-title {
    display: none;
}

.game-provider {
    display: none;
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

.recent-item:hover .game-hover-overlay {
    opacity: 1;
}

.play-circle {
    width: 50px;
    height: 50px;
    background: var(--primary-neon);
    color: #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 0 15px var(--primary-neon);
}

/* Premium Slider Coverflow Styles */
.premium-slider-wrapper {
    position: relative;
    width: 100%;
    padding: 20px 0 40px;
}

.premium-slider-bg {
    position: absolute;
    inset: -20px;
    background: radial-gradient(circle, rgba(57, 255, 20, 0.4) 0%, #000 60%);
    filter: blur(40px);
    z-index: 0;
    pointer-events: none;
}

.premium-slider {
    position: relative;
    z-index: 1;
}

.premium-slider .item {
    position: relative;
    height: 480px;
    border-radius: 0px;
    overflow: hidden;
    transition: all 0.5s ease;
    opacity: 1;
    box-shadow: none;
    image-rendering: -webkit-optimize-contrast;
}

@media (max-width: 768px) {
    .premium-slider .item {
        height: 320px !important;
    }
}

.premium-slider .owl-item.center .item {
    transform: scale(1);
    opacity: 1;
    border: 1px solid rgba(191, 255, 0, 0.3);
    box-shadow: 0 10px 40px rgba(0,0,0,0.5), 0 0 30px rgba(57, 255, 20, 0.2);
}

.slider-overlay-gradient {
    display: none;
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(10,30,10,0.9) 0%, rgba(10,30,10,0.6) 40%, transparent 100%);
}

.slider-content {
    position: absolute;
    left: 50px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}

.slider-content h2 {
    color: #fff;
    font-size: 38px;
    font-weight: 900;
    line-height: 1.1;
    margin-bottom: 30px;
}

.slider-content h2 span {
    color: var(--primary-neon);
}

.slider-buttons {
    display: flex;
    gap: 15px;
}

.btn-slider-primary {
    background: var(--primary-neon);
    color: #000;
    border: none;
    padding: 12px 25px;
    font-size: 14px;
    font-weight: 800;
    border-radius: 6px;
    text-transform: uppercase;
    transition: all 0.3s;
}

.btn-slider-primary:hover {
    background: #fff;
    transform: scale(1.05);
}

.btn-slider-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 12px 25px;
    font-size: 14px;
    font-weight: 800;
    border-radius: 6px;
    text-transform: uppercase;
    transition: all 0.3s;
}

.btn-slider-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: #fff;
}

/* Custom Navigation for PC Carousel */
#pc-carousel .owl-nav {
    position: absolute;
    top: 50%;
    width: 100%;
    transform: translateY(-50%);
    pointer-events: none; /* Let clicks pass through except on buttons */
}
#pc-carousel .owl-nav button.owl-prev,
#pc-carousel .owl-nav button.owl-next {
    position: absolute;
    width: 40px;
    height: 40px;
    background: #000 !important;
    border-radius: 50%;
    color: #fff !important;
    font-size: 18px !important;
    pointer-events: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255,255,255,0.2) !important;
    transition: all 0.3s;
}
#pc-carousel .owl-nav button.owl-prev:hover,
#pc-carousel .owl-nav button.owl-next:hover {
    border-color: var(--primary-neon) !important;
    color: var(--primary-neon) !important;
    box-shadow: 0 0 10px rgba(191, 255, 0, 0.3);
}
#pc-carousel .owl-nav button.owl-prev { left: 10px; }
#pc-carousel .owl-nav button.owl-next { right: 10px; }

#pc-carousel .owl-dots {
    position: absolute;
    bottom: -30px;
    width: 100%;
    display: flex;
    justify-content: center;
    gap: 8px;
}
#pc-carousel .owl-dots .owl-dot span {
    width: 20px;
    height: 4px;
    background: rgba(255,255,255,0.2);
    display: block;
    border-radius: 2px;
    transition: all 0.3s;
}
#pc-carousel .owl-dots .owl-dot.active span {
    background: var(--primary-neon);
    width: 30px;
}
.game-card-premium:hover {
    transform: scale(1.03);
}

.game-card-premium:hover .game-overlay {
    opacity: 1 !important;
}

.game-img-container img {
    transition: filter 0.3s;
}

/* Lobby Nav Styles */
.lobby-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    padding: 8px 15px;
    color: #888;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 80px;
}

.lobby-item i {
    font-size: 20px;
}

.lobby-item span {
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.lobby-item:hover, .lobby-item.active {
    color: var(--primary-neon);
}

.lobby-item.active {
    background: rgba(191, 255, 0, 0.1);
    border-radius: 6px;
}

.lobby-item.active i {
    color: var(--primary-neon);
}
</style>

<?php require_once 'includes/modals-ui.php'; ?>
<?php require_once 'includes/footer.php'; ?>
