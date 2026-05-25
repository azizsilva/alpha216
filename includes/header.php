<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Calculate Base URL/Path dynamically
$script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$parts = explode('/', $script_path);

// Determine Root Folder Name dynamically
// Strategy: Check if we are inside 'public_html' or 'main' or just root
// If neither is found in URL, assume we are at the root or calculate depth from current file location relative to web root.

// More robust method:
// 1. Get the directory of the current script (e.g., /.../casino-games/index.php)
// 2. We know header.php is located at [ROOT]/includes/header.php
// 3. We can calculate how deep the current script is relative to [ROOT].

// Let's assume the root is where 'includes' folder is located.
// __DIR__ is .../includes
// Parent of __DIR__ is the Root (where index.php, assets/ etc. live)
$root_path_system = dirname(__DIR__); // System path to root

// Current script system path
$current_script_dir = dirname($_SERVER['SCRIPT_FILENAME']);

// Calculate depth by comparing paths
// Normalize paths
$root_path_system = str_replace('\\', '/', $root_path_system);
$current_script_dir = str_replace('\\', '/', $current_script_dir);

$back_path = "";

// Check if current script is inside the root path
if (strpos($current_script_dir, $root_path_system) === 0) {
    // It is inside. Calculate relative path.
    // Remove root path from current path
    $relative_part = substr($current_script_dir, strlen($root_path_system));
    $relative_part = trim($relative_part, '/');
    
    if (!empty($relative_part)) {
        // Count directories
        $depth = count(explode('/', $relative_part));
        $back_path = str_repeat("../", $depth);
    }
} else {
    // Fallback: If paths don't match (symlinks or weird config), default to empty
    // or try standard "public_html" check
    $main_index = false;
    foreach ($parts as $key => $part) {
        if ($part === 'public_html' || $part === 'main') {
            $main_index = $key;
            break; 
        }
    }
    
    if ($main_index !== false) {
        $depth = count($parts) - 1 - $main_index - 1;
        if ($depth > 0) {
            $back_path = str_repeat("../", $depth);
        }
    }
}

// Ensure db.php is included correctly using __DIR__
// db.php is expected to be in the parent directory of 'includes' (i.e., root)
// OR inside 'includes' depending on your setup.
// Based on previous context: require_once __DIR__ . '/../db.php'; -> implies db.php is in ROOT.

if (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    // Fallback
    require_once __DIR__ . '/../db.php';
}

// Fetch Web Settings
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT * FROM web_settings LIMIT 1");
    $web_settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$web_settings) {
        die("Web Settings not found in database. Please import web_settings.sql.");
    }
} else {
    // DB connection failed inside db.php or not included
    die("Database connection not established.");
}

// Set base url dynamically based on script location
$script_name = $_SERVER['SCRIPT_NAME']; 
$base_dir = str_replace('\\', '/', dirname($script_name));
// If we are in a subdirectory of a subdirectory (like /includes), we need the root
// But header.php is always included, so SCRIPT_NAME is the entry point (e.g. /index.php or /casino-games/index.php)
// We need the path to the root directory where index.php resides.

// A more reliable way: 
// We know header.php is in [ROOT]/includes/
// So the root is one level up from the directory of THIS file.
$this_dir = str_replace('\\', '/', __DIR__);
$root_dir_name = str_replace('\\', '/', dirname($this_dir));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

$base_url = '/';
if (stripos($root_dir_name, $doc_root) === 0) {
    $base_url = substr($root_dir_name, strlen($doc_root));
    $base_url = '/' . ltrim($base_url, '/') . '/';
    $base_url = str_replace('//', '/', $base_url);
}

$site_logo_url = 'assets/images/logo_bets.jpeg';
$site_favicon_url = 'tanitbet.jpg';
$web_settings['site_logo'] = $site_logo_url;

$asset_path = $base_url . 'assets/';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? '';
$absolute_base_url = $host ? ($protocol . $host . $base_url) : $base_url;
$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? 'Guest';
$user_role = $_SESSION['role'] ?? '';

// Fetch User Balance & Exposure if Logged In
$user_balance = 0.00; // Available Balance
    $user_wallet_balance = 0.00; // Total Balance
$user_exposure = 0.00;
$user_total_winnings = 0.00;
$user_total_losing = 0.00;

if ($is_logged_in && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT balance, exposure FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $user_wallet_balance = (float)$user_data['balance'];
            // Check if exposure exists (it should based on database.sql)
            $user_exposure = isset($user_data['exposure']) ? (float)$user_data['exposure'] : 0.00;
            
            // Available Balance = Wallet Balance - Exposure
            $user_balance = $user_wallet_balance - $user_exposure;
        }
    } catch (PDOException $e) {
        // Handle error or ignore
    }
}

if ($is_logged_in) {
    $_SESSION['coins'] = $user_wallet_balance;
}

$site_logo_url = 'assets/images/logo_bets.jpeg'; 
$site_favicon_url = 'assets/images/tanitbet.jpg';

$player_deposit_methods = [];
if ($is_logged_in && isset($pdo) && ($user_role === '' || $user_role === 'player')) {
    try {
        $stmt = $pdo->prepare("SELECT parent_id FROM users WHERE id=?");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $agent_id = (int)($stmt->fetchColumn() ?? 0);

        if ($agent_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM player_deposit_methods WHERE agent_id=? AND enabled=1 ORDER BY sort_order ASC, id DESC");
            $stmt->execute([$agent_id]);
            $player_deposit_methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE user_id=? AND enabled=1 ORDER BY sort_order ASC, id DESC");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $player_deposit_methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (empty($player_deposit_methods)) {
                $stmt = $pdo->prepare("SELECT * FROM deposit_methods WHERE target_role='master' AND enabled=1 ORDER BY sort_order ASC, id DESC");
                $stmt->execute();
                $player_deposit_methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } catch (Exception $e) {
        $player_deposit_methods = [];
    }
}

$mk_fragment = isset($_GET['mk_fragment']);
if ($mk_fragment) {
    return;
}

if (!headers_sent()) {
    $reqPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    $isApp = (bool)preg_match('#^/app/index\.php$#i', $reqPath);
    $isRootIndex = (bool)preg_match('#^/index\.php$#i', $reqPath);

    // Only canonicalize explicit "/.../index.php" requests to avoid redirect loops on directory URLs like "/sports/".
    if (!$isApp && !$isRootIndex && preg_match('#/index\.php$#i', $reqPath)) {
        $route = preg_replace('#/index\.php$#i', '/', $reqPath);
        if ($route === '') $route = '/';
        if (stripos($route, '/casino-games/slots/') === 0) $route = '/casino-games/slot-games/';
        if (stripos($route, '/casino-games/live/') === 0 && stripos($route, '/casino-games/live-casino/') !== 0) $route = '/casino-games/live-casino/';
        if (stripos($route, '/casino-games/virtual/') === 0 && stripos($route, '/casino-games/virtual-sports/') !== 0) $route = '/casino-games/virtual-sports/';
        $redir = rtrim($absolute_base_url, '/') . $route;
        header('Location: ' . $redir, true, 302);
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Alpha 216 | Premium Sportsbook & Casino</title>
  <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($site_favicon_url); ?>">
<?php
/* ── Detect sportsbook full-screen pages so we can skip Bootstrap CSS ──
   The sportsbook UI is a self-contained design (matches fcbet216) and the
   parent navbar/footer are hidden via `mk-game-no-chrome`. Loading Bootstrap
   3 here only causes UI conflicts (button bg, *, .row, container resets etc.),
   so we drop it entirely on those pages. Custom-style.css is still loaded
   below but its impact is neutralised by `.sb-root` resets in style.css. */
$__sb_path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$__sb_skip_bootstrap = ($__sb_path !== '' && (preg_match('#/sportsbook(?:/|$)#i', $__sb_path) || preg_match('#/sports(?:/|$)#i', $__sb_path)));
?>
<?php if (!$__sb_skip_bootstrap): ?>
  <!-- Bootstrap 3.3.7 CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<?php endif; ?>
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <!-- Owl Carousel 2 CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Work+Sans:300,400,500,700,800" rel="stylesheet">
  
  <!-- Custom CSS -->
  <?php
    $__mk_css_ver = 1;
    try {
        $__p = dirname(__DIR__) . '/assets/css/custom-style.css';
        if (file_exists($__p)) $__mk_css_ver = (int)@filemtime($__p);
    } catch (Exception $e) {
    }
  ?>
  <link rel="stylesheet" href="<?php echo $asset_path; ?>css/custom-style.css?v=<?php echo (int)$__mk_css_ver; ?>">
  
  <!-- jQuery & Bootstrap JS Moved to Top -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script>
    var SITE_BASE_URL = '<?php echo $base_url; ?>';
    var SITE_API_URL = SITE_BASE_URL + 'api/';
    var MK_IS_LOGGED_IN = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    
    function launchGame(gameId, gameName) {
        if (!gameId) return;
        var name = gameName || '';
        console.log('Launching game:', gameId, name);
        
        // Show loading overlay
        var overlay = $('#mkGameLaunchingOverlay');
        if (!overlay.length) {
            overlay = $('<div id="mkGameLaunchingOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999;display:flex;align-items:center;justify-content:center;"><div class="mk-spinner" style="width:40px;height:40px;border:4px solid rgba(255,255,255,0.1);border-top-color:#72f238;border-radius:50%;animation:mk-spin 1s linear infinite;"></div><style>@keyframes mk-spin{to{transform:rotate(360deg)}}</style></div>');
            $('body').append(overlay);
        }
        overlay.fadeIn(200);

        $.ajax({
            url: SITE_API_URL + 'launch_game.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                game_id: gameId, 
                game_name: name,
                home_url: window.location.href
            }),
            success: function(res) {
                if (typeof res === 'string') try { res = JSON.parse(res); } catch(e){}
                if (res && res.success) {
                    // Prefer redirect_url (play page with token) - prepend SITE_BASE_URL for subdir support
                    // Fall back to game_url (direct external URL)
                    var dest;
                    if (res.redirect_url) {
                        var base = (typeof SITE_BASE_URL === 'string' ? SITE_BASE_URL : '/');
                        if (!base.endsWith('/')) base += '/';
                        dest = base + res.redirect_url;
                    } else {
                        dest = res.game_url;
                    }
                    if (dest) {
                        window.location.assign(dest);
                    } else {
                        alert('Erreur lors du lancement du jeu');
                        overlay.fadeOut(200);
                    }
                } else {
                    alert((res && res.message) || 'Erreur lors du lancement du jeu');
                    overlay.fadeOut(200);
                }
            },
            error: function() {
                alert('Erreur réseau. Veuillez réessayer.');
                overlay.fadeOut(200);
            }
        });
    }

    function mkSafeLaunch(id, name) {
        if (!MK_IS_LOGGED_IN) {
            if (typeof $('#loginModal').modal === 'function') {
                $('#loginModal').modal('show');
            } else {
                alert('Veuillez vous connecter pour jouer.');
            }
            return;
        }
        if (id === '6260') {
            window.location.href = '<?php echo $base_url; ?>sportsbook/';
            return;
        }
        launchGame(id, name);
    }
  </script>
  <!-- Bootstrap JS + Owl Carousel are kept loaded everywhere: only their
       CSS conflicted with the sportsbook UI, the JS is needed by other
       page initialisers (e.g. $('.owl-carousel').owlCarousel(...)). -->
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
  <script>  
    (function () {
      try {
        var p = window.location.pathname || '/';
        p = p.replace(/\/{2,}/g, '/');
        if (p.length > 1 && p.endsWith('/')) p = p.slice(0, -1);
        if (p !== (window.location.pathname || '/')) {
          history.replaceState({}, '', p + window.location.search + window.location.hash);
        }
      } catch (e) {}
    })();
  </script>
  <script>
    (function(){
      try {
        var base = SITE_BASE_URL || '/';
        if (base && base.charAt(base.length-1) !== '/') base += '/';
        var path = (window.location.pathname || '/');
        var hash = window.location.hash || '';
        if (hash.indexOf('#/') === 0) path = hash.slice(1);
        path = path.split('?')[0];
        path = path.replace(/\/index\.php$/i, '') || '/';
        if (path.length > 1 && path.endsWith('/')) path = path.slice(0,-1);
        // Strip base prefix so map keys like /sportsbook work regardless of subdirectory
        var baseTrimmed = (base.length > 1 && base.endsWith('/')) ? base.slice(0,-1) : (base === '/' ? '' : base);
        if (baseTrimmed && path.indexOf(baseTrimmed) === 0) {
          path = path.slice(baseTrimmed.length) || '/';
        }
        var map = {
          '/': base + 'index.php?mk_fragment=1',
          '/home': base + '?mk_fragment=1',
          '/deposit-withdraw': base + 'deposit-withdraw/?mk_fragment=1',
          '/account-details': base + 'account-details/?mk_fragment=1',
          '/account-statement': base + 'account-statement/?mk_fragment=1',
          '/profit-loss': base + 'profit-loss/?mk_fragment=1',
          '/bet-history': base + 'bet-history/?mk_fragment=1',
          '/activity-log': base + 'activity-log/?mk_fragment=1',
          '/pinned': base + 'pinned/?mk_fragment=1',
          '/sports': base + 'sports/?mk_fragment=1',
          '/sportsbook': base + 'sportsbook/?mk_fragment=1',
          '/casino': base + 'casino-games/?mk_fragment=1',
          '/casino-games/slot-games': base + 'casino-games/slot-games/?mk_fragment=1',
          '/casino-games/live-casino': base + 'casino-games/live-casino/?mk_fragment=1',
          '/casino-games/virtual-sports': base + 'casino-games/virtual-sports/?mk_fragment=1',
          '/fantasy-games': base + 'fantasy-games/?mk_fragment=1',
          '/play': base + 'play/?mk_fragment=1'
        };
        var protectedMap = {
          '/deposit-withdraw': 1,
          '/account-details': 1,
          '/account-statement': 1,
          '/profit-loss': 1,
          '/bet-history': 1,
          '/activity-log': 1
        };
        var url = map[path];
        if (!url && path && path !== '/') url = base + path.slice(1) + '/?mk_fragment=1';
        if (!url) url = map['/'];
        if (!MK_IS_LOGGED_IN && protectedMap[path]) url = map['/'];
        window.__MK_PREFETCH_CACHE = window.__MK_PREFETCH_CACHE || {};
        var urls = [
          url,
          map['/sports'],
          map['/sportsbook'],
          map['/casino-games'],
          map['/casino-games/slot-games'],
          map['/casino-games/live-casino'],
          map['/casino-games/virtual-sports'],
          map['/fantasy-games'],
          map['/deposit-withdraw'],
          map['/account-details'],
          map['/account-statement'],
          map['/profit-loss'],
          map['/bet-history'],
          map['/activity-log'],
          map['/pinned'],
          map['/play']
        ].filter(function (u) { return !!u; });
        if (!MK_IS_LOGGED_IN) {
          urls = urls.filter(function (u) {
            for (var p in protectedMap) {
              if (Object.prototype.hasOwnProperty.call(protectedMap, p) && map[p] === u) return false;
            }
            return true;
          });
        }
        var seen = {};
        urls = urls.filter(function (u) { if (seen[u]) return false; seen[u] = 1; return true; });
        urls = urls.slice(0, 6);
        function doFetch(u) {
          fetch(u, { credentials: 'same-origin' })
            .then(function(r){ return r.ok ? r.text() : ''; })
            .then(function(html){ if (html) window.__MK_PREFETCH_CACHE[u] = html; })
            .catch(function(){});
        }
        function doFetchSeq(i) {
          if (i >= urls.length) return;
          doFetch(urls[i]);
          setTimeout(function () { doFetchSeq(i + 1); }, 160);
        }
        if (window.requestIdleCallback) {
          requestIdleCallback(function () { doFetchSeq(0); }, { timeout: 2000 });
        } else {
          setTimeout(function () { doFetchSeq(0); }, 220);
        }
      } catch(e){}
    })();
  </script>
  <script>
    (function () {
      try {
        if (!MK_IS_LOGGED_IN) return;
        var __mkClampScheduled = false;
        var __mkClampRunning = false;
        function fmt(n) { n = isFinite(n) ? n : 0; if (n < 0) n = 0; return n.toFixed(2); }
        function clampBalances() {
          if (__mkClampRunning) return;
          __mkClampRunning = true;
          try {
            var avail = Number(MK_MAIN_BALANCE_CI || 0);
            if (!isFinite(avail) || avail < 0) avail = 0;
            var availText = fmt(avail);

            var availEls = document.querySelectorAll('[data-live-balance="available"]');
            availEls.forEach(function (el) { if (el && el.textContent !== availText) el.textContent = availText; });

            var walletEls = document.querySelectorAll('[data-live-balance="wallet"]');
            walletEls.forEach(function (el) {
              if (!el) return;
              var val = parseFloat((el.textContent || '0').replace(/,/g, '')) || 0;
              if (val < 0) val = 0;
              var t = fmt(val);
              if (el.textContent !== t) el.textContent = t;
            });

            var expoEls = document.querySelectorAll('[data-live-balance="exposure"], [data-translate="exp"], [data-translate="exposure"], .bal-exposure-line, .mk-ph-exp-row, .mk-hide-exposure');
            expoEls.forEach(function (el) {
              if (el && el.parentNode) el.parentNode.removeChild(el);
            });
          } catch (e) {
          } finally {
            __mkClampRunning = false;
          }
        }
        function scheduleClamp() {
          if (__mkClampScheduled) return;
          __mkClampScheduled = true;
          setTimeout(function () {
            __mkClampScheduled = false;
            clampBalances();
          }, 60);
        }
        try { window.MK_CLAMP_BALANCES = clampBalances; } catch (e0) {}
        scheduleClamp();

        try {
          var target = document.body || document.documentElement;
          if (window.MutationObserver && target) {
            var ob = new MutationObserver(function () { scheduleClamp(); });
            ob.observe(target, { subtree: true, childList: true });
          }
        } catch (e1) {}
        window.addEventListener('focus', scheduleClamp);
        window.addEventListener('visibilitychange', function(){ if (!document.hidden) scheduleClamp(); });
      } catch (e) {}
    })();
  </script>
  <script>
    (function () {
      try {
        if (!MK_IS_LOGGED_IN) return;
        var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (c && c.saveData) return;
        var now = Date.now();
        var last = 0;
        try { last = parseInt(sessionStorage.getItem('mk_sw_prelaunch_ts') || '0', 10) || 0; } catch (e0) {}
        if (now - last < 4 * 60 * 1000) return;
        try { sessionStorage.setItem('mk_sw_prelaunch_ts', String(now)); } catch (e1) {}

        var apiUrl = (typeof SITE_API_URL !== 'undefined') ? SITE_API_URL + 'launch_game.php' : '/api/launch_game.php';
        var ids = ['3978'];
        function preconnect(origin) {
          try {
            if (!origin) return;
            window.__MK_PRECON = window.__MK_PRECON || {};
            if (window.__MK_PRECON[origin]) return;
            window.__MK_PRECON[origin] = 1;
            var link = document.createElement('link');
            link.rel = 'preconnect';
            link.href = origin;
            link.crossOrigin = 'anonymous';
            document.head.appendChild(link);
          } catch (e) {}
        }
        var SPORTS_ID = '3978';
        var SBO_ID = '3978';
        function prelaunch(gid) {
          var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
          var desiredHome = origin + (gid === SPORTS_ID ? '/sports/?mk=1' : '/');
          return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ game_id: gid, home_url: desiredHome, prefetch: 1, skip_log: true }),
            cache: 'no-store'
          }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
            if (j && j.success && j.game_url) {
              var ok = true;
              try {
                var tag = String(j.tag || '').toLowerCase();
                if (gid === SPORTS_ID && tag !== 'sports') ok = false;
                if (gid === SBO_ID && tag !== 'sports') ok = false;
              } catch (et) {}
              if (!ok) return;
              try {
                window.MK_GAME_CACHE = window.MK_GAME_CACHE || {};
                window.MK_GAME_CACHE[gid] = { url: j.game_url, ts: Date.now(), tag: String(j.tag || '') };
              } catch (e2) {}
              try { preconnect((new URL(j.game_url)).origin); } catch (e3) {}
            }
          }).catch(function () {});
        }
        if (window.requestIdleCallback) {
          requestIdleCallback(function () { ids.forEach(prelaunch); }, { timeout: 2500 });
        } else {
          setTimeout(function () { ids.forEach(prelaunch); }, 450);
        }
      } catch (e) {}
    })();
  </script>

  <style>
      /* Vibrant Neon Green Theme */
      :root {
          --primary-neon: #39FF14;
          --primary-dark: #000000;
          --secondary-dark: #111111;
          --text-white: #ffffff;
      }

      body {
          background-color: #000;
          color: #fff;
          font-family: 'Work Sans', sans-serif;
          padding-top: 50px; /* Adjusted for thinner navbar */
      }
      
      .custom-navbar {
          background-color: rgba(0, 0, 0, 0.95);
          backdrop-filter: blur(10px);
          border-bottom: 1px solid rgba(57, 255, 20, 0.2);
          height: 50px;
          position: fixed;
          top: 0;
          width: 100%;
          z-index: 10000;
      }

      .navmain {
          display: flex;
          align-items: center;
          justify-content: space-between;
          height: 50px;
          padding: 0 20px;
          max-width: 1400px;
          margin: 0 auto;
      }

      .navbar-left-content {
          display: flex;
          align-items: center;
          gap: 20px;
      }

      .navbar-brand img {
          height: 45px;
          width: auto;
      }

      .desktop-nav-menu {
          display: flex;
          list-style: none;
          margin: 0;
          padding: 0;
          gap: 15px;
      }

      .desktop-nav-menu li a {
          color: #fff;
          font-size: 13px;
          font-weight: 700;
          text-transform: uppercase;
          transition: color 0.3s;
          white-space: nowrap;
      }

      .desktop-nav-menu li a:hover {
          color: var(--primary-neon);
      }

      .navbar-right-content {
          display: flex;
          align-items: center;
          gap: 15px;
      }

      /* Custom Buttons */
      .login-btn-new {
          background: #2a2a2a;
          color: #fff;
          border: none;
          padding: 8px 18px;
          border-radius: 6px;
          font-weight: 700;
          font-size: 12px;
          text-transform: uppercase;
          transition: all 0.3s;
      }

      .login-btn-new:hover {
          background: #444;
          color: #fff;
      }

      .signup-btn-new {
          background: #50f660; /* Slightly softer green matching fcbet216 */
          color: #000;
          border: none;
          padding: 8px 18px;
          border-radius: 6px;
          font-weight: 800;
          font-size: 12px;
          text-transform: uppercase;
          transition: all 0.3s;
      }

      .signup-btn-new:hover {
          background: #3ce54c;
          transform: translateY(-1px);
      }

      /* Custom Change Password Modal CSS matching image */
      #changePasswordModal {
          z-index: 10050 !important;
      }
      #changePasswordModal .modal-dialog {
          width: 90%;
          max-width: 400px;
          margin: 100px auto 0;
      }
      #changePasswordModal .modal-content {
          border-radius: 12px;
          overflow: hidden;
          border: none;
          background: #fff;
          box-shadow: 0 5px 15px rgba(0,0,0,0.5);
      }
      #changePasswordModal .modal-header {
          background-color: #000;
          color: #fff;
          border-bottom: none;
          padding: 15px 20px;
          position: relative;
          text-align: center;
      }
      #changePasswordModal .modal-title {
          font-weight: 700;
          font-size: 18px;
          color: #fff;
          margin: 0;
          display: inline-block;
      }
      #changePasswordModal .close {
          color: #bfff00;
          opacity: 1;
          font-size: 24px;
          position: absolute;
          right: 15px;
          top: 15px;
          text-shadow: none;
          background: transparent;
          border: none;
          padding: 0;
          line-height: 1;
      }
      #changePasswordModal .modal-body {
          padding: 20px;
      }
      #changePasswordModal .form-group {
          margin-bottom: 20px;
          position: relative;
      }
      #changePasswordModal .form-control {
          height: 55px;
          border-radius: 8px;
          border: 1px solid #e0e0e0;
          padding-left: 15px;
          padding-right: 45px;
          font-size: 16px;
          color: #333;
          box-shadow: none;
          background: #fff;
      }
      #changePasswordModal .form-control:focus {
          border-color: #bfff00;
      }
      #changePasswordModal .toggelPass {
          position: absolute;
          right: 15px;
          top: 18px;
          color: #bfff00;
          cursor: pointer;
          font-size: 18px;
          z-index: 10;
      }
      #changePasswordModal .modal-footer-btn {
          background: #000 !important;
          background-color: #000 !important;
          color: #fff !important;
          border: none;
          width: 100%;
          height: 55px;
          font-size: 20px;
          font-weight: 700;
          border-radius: 0;
          margin: 0;
          transition: opacity 0.2s;
          display: flex;
          align-items: center;
          justify-content: center;
          text-transform: none;
          -webkit-appearance: none;
          appearance: none;
          -webkit-tap-highlight-color: transparent;
          box-shadow: none !important;
      }
      #changePasswordModal .modal-footer-btn:hover,
      #changePasswordModal .modal-footer-btn:focus,
      #changePasswordModal .modal-footer-btn:active,
      #changePasswordModal .modal-footer-btn:active:focus {
          background: #000 !important;
          background-color: #000 !important;
          color: #fff !important;
          outline: none;
          box-shadow: none !important;
      }
      #changePasswordModal .modal-footer-btn:disabled {
          opacity: 0.75;
          cursor: not-allowed;
          background: #000 !important;
          background-color: #000 !important;
          color: #fff !important;
          -webkit-text-fill-color: #fff;
      }
      #changePasswordModal .modal-body-container {
          padding: 0;
      }
      
      /* Smooth Animation for Modal */
      #changePasswordModal.fade .modal-dialog {
          transform: translateY(-60px);
          opacity: 0;
          transition: transform 0.32s cubic-bezier(0.19, 1, 0.22, 1), opacity 0.32s ease;
          will-change: transform, opacity;
      }
      #changePasswordModal.fade.in .modal-dialog {
          transform: translateY(0);
          opacity: 1;
      }
      #changePasswordModal.cp-hiding .modal-dialog {
          transform: translateY(80px);
          opacity: 0;
      }
      
      /* Popup List Animation */
      .dropdown-list {
          display: block; /* Change from none to block but hidden by height/opacity */
          visibility: hidden;
          opacity: 0;
          position: absolute;
          top: 38px;
          left: 0;
          width: 100%;
          background: #fff;
          border-radius: 4px;
          box-shadow: 0 4px 8px rgba(0,0,0,0.5);
          z-index: 10000;
          overflow: hidden;
          transform: translateY(-10px);
          transition: all 0.3s ease;
          max-height: 0;
      }
      .custom-lang-dropdown.active .dropdown-list {
          visibility: visible;
          opacity: 1;
          transform: translateY(0);
          max-height: 300px; /* Allow enough height for list */
      }
      
      /* Dynamic Text Sizing Class */
      .text-fit-container {
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          display: inline-block;
          max-width: 100%;
      }
      .list-header {
          display: none; /* Hide header completely as requested */
      }
      .dropdown-list ul {
          list-style: none;
          padding: 0;
          margin: 0;
      }
      .dropdown-list ul li {
          padding: 8px 10px;
          color: #000;
          font-size: 14px;
          font-weight: 500;
          text-align: center;
          cursor: pointer;
          border-bottom: 1px solid #eee;
          transition: background 0.2s;
      }
      .dropdown-list ul li:last-child {
          border-bottom: none;
      }
      .dropdown-list ul li:hover {
          background: #f0f0f0;
          color: #bfff00;
      }
      .dropdown-list ul li.active-lang {
          background: #666; /* Grey background for active */
          color: #fff; /* White text */
      }
      .dropdown-list ul li.active-lang:hover {
          background: #555; /* Slightly darker on hover */
          color: #fff;
      }

      /* APK Button - Exact Match */
      .apkButton {
          background: #555; /* Lighter grey like image */
          color: #fff;
          font-size: 10px;
          font-weight: bold;
          padding: 0;
          border-radius: 3px; /* Slightly sharper radius */
          text-transform: uppercase;
          text-decoration: none;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 5px;
          height: 27px;
          width: 57px;
          border: none; /* No border for APK */
          /* NO BORDERS HERE - Separator is distinct element */
      }
      .apkButton:hover {
          background: #666;
          color: #fff;
          text-decoration: none;
      }

      /* Single Explicit Separator Line */
      .nav-separator {
          width: 1px;
          height: 20px; /* Shorter than buttons */
          background: #ffffff !important; /* Force White */
          background-color: #ffffff !important;
          margin: 0 15px; /* Spacing */
          display: block !important;
          opacity: 1 !important;
          position: relative; /* Ensure z-index works */
          z-index: 999;
      }

      /* Hide Separator on Mobile */
      @media (max-width: 767px) {
          .nav-separator {
              display: none !important;
          }
      }

      /* Login/Signup Buttons */
      .guest-actions {
          display: flex;
          align-items: center;
          gap: 10px;
      }
      /* LOG IN - Black bg, Yellow border */
      .loginbtn {
          background: #000;
          color: #fff;
          border: 1px solid #bfff00;
          font-size: 13px; /* Slightly larger text */
          font-weight: bold;
          padding: 0 5px; /* Added padding to prevent edge touching */
          text-transform: uppercase;
          border-radius: 3px;
          cursor: pointer;
          height: 35px;
          width: 75px;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          margin: 0;
          white-space: nowrap;
          overflow: hidden;
      }
      .loginbtn:hover {
          color: #bfff00;
          text-decoration: none;
      }
      /* SIGN UP - Solid Orange/Yellow bg, No border */
      .loginbtn.sigUpd1casino {
          background: #bfff00; /* Match the orange/brown color */
          border: none;
          color: #fff;
      }
      .loginbtn.sigUpd1casino:hover {
          background: #d48632;
          color: #fff;
      }

      /* Click Animation for Login/Signup Buttons */
      .loginbtn, .loginbtn.sigUpd1casino {
          transition: transform 0.1s ease, background 0.3s ease, color 0.3s ease;
      }
      .loginbtn:active, .loginbtn.sigUpd1casino:active {
          transform: scale(0.95); /* Slight shrink effect */
      }
      
      /* Deposit Button */
      .btn-deposit {
          background: #bfff00;
          color: #fff;
          font-weight: 800;
          font-size: 13px;
          border-radius: 4px;
          height: 30px; 
          padding: 0 15px;
          display: flex;
          align-items: center;
          justify-content: center;
          text-decoration: none;
          border: none;
          text-transform: uppercase;
      }
      .btn-deposit:hover {
          background: #d48632;
          color: #fff;
          text-decoration: none;
      }

      /* User Profile Box */
      .user-profile-box {
          display: flex;
          align-items: center;
          gap: 10px;
          cursor: pointer;
          position: relative;
          height: 100%;
          padding-left: 10px;
      }
      .user-profile-info {
          display: flex;
          flex-direction: column;
          align-items: flex-end;
          justify-content: center;
          line-height: 1.2;
      }
      .user-name-display {
          color: #fff;
          font-weight: 700;
          font-size: 14px;
          text-transform: uppercase;
      }
      .user-balance-display {
          color: #bfff00;
          font-weight: 700;
          font-size: 13px;
      }
      .user-balance-display span {
          color: #fff; 
          font-size: 11px;
          margin-left: 2px;
      }
      .user-chevron {
          color: #bfff00;
          font-size: 16px;
          font-weight: bold;
          margin-left: 5px;
          transition: transform 0.3s;
      }
      
      /* ==========================================================================
         User Profile Popup - Unified Smooth UI
         ========================================================================== */
      .user-popup-container {
          position: fixed;
          background: #f4f4f4;
          z-index: 10010; 
          display: flex;
          flex-direction: column;
          overflow: hidden;
          visibility: hidden;
          transition: transform 0.8s cubic-bezier(0.19, 1, 0.22, 1), visibility 0.8s;
          box-shadow: -5px 5px 15px rgba(0,0,0,0.3);
      }
      .user-popup-container.active {
          transform: translate(0, 0) !important;
          visibility: visible;
      }
      
      /* Popup Header Styling */
      .popup-header {
          display: flex;
          align-items: stretch;
          justify-content: flex-start;
          flex-shrink: 0;
          background: #fff;
          color: #000;
      }
      .popup-header-left {
          display: flex;
          align-items: center;
          flex: 1;
          overflow: hidden;
      }
      .popup-header-title {
          font-weight: 900;
          text-transform: uppercase;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          margin: 0;
      }
      
      /* Desktop Player ID Display */
      .player-id-display {
          font-size: clamp(12px, 2vw, 18px); /* Letter short ho jayega smooth ui me */
          font-weight: 900;
          text-transform: uppercase;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          flex: 1;
          height: 100%;
          display: flex;
          align-items: center;
          padding-left: 15px;
          border-bottom: 2px solid #000;
      }
      
      /* Popup Content Area */
      .popup-content {
          padding: 10px;
          display: flex;
          flex-direction: column;
          overflow: hidden;
          flex: 1;
      }
      .fixed-balance-part {
          flex-shrink: 0;
          margin-bottom: 5px;
      }
      .scrollable-menu-part {
          overflow-y: auto;
          flex-grow: 1;
          padding-right: 5px;
      }
      .scrollable-menu-part::-webkit-scrollbar { width: 5px; }
      .scrollable-menu-part::-webkit-scrollbar-thumb { background: #bfff00; border-radius: 10px; }
      
      /* Popup Footer (Fixed Logout) */
      .popup-footer {
          flex-shrink: 0;
          padding: 10px;
          background: #fff;
          border-top: 1px solid #ddd;
          display: flex;
          justify-content: center;
          align-items: center;
      }
      
      /* Common Row Styles */
      .profile-row {
          background: #fff;
          border: 1px solid #ccc;
          border-radius: 4px;
          margin-bottom: 8px;
          padding: 10px 15px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          box-shadow: 0 2px 4px rgba(0,0,0,0.08);
      }
      .row-label { font-weight: 700; color: #000; }
      .row-value { font-weight: 800; color: #000; }
      .row-value.red-text { color: #ff0000; }
      
      .popup-menu-btn {
          background: #fff;
          border: 1px solid #333;
          border-radius: 4px;
          margin-bottom: 6px;
          padding: 12px;
          display: block;
          width: 100%;
          text-align: center;
          color: #000;
          font-weight: 700;
          text-decoration: none;
          transition: all 0.3s;
          -webkit-tap-highlight-color: transparent;
      }
      .popup-menu-btn:hover { background-color: #bfff00; color: #fff; border-color: #bfff00; }
      .popup-menu-btn:focus,
      .popup-menu-btn:active {
          background-color: #bfff00 !important;
          color: #fff !important;
          border-color: #bfff00 !important;
          outline: none !important;
          box-shadow: none !important;
      }
      .popup-menu-btn:visited {
          color: #000;
      }
      
      .popup-logout-btn-large {
          display: block;
          width: 80%;
          background: #bfff00;
          color: #fff;
          border: none;
          border-radius: 12px;
          padding: 12px;
          font-size: 16px;
          font-weight: 800;
          text-align: center;
          text-transform: uppercase;
          text-decoration: none;
          box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      }

      /* Desktop Specific (PC) */
      @media (min-width: 768px) {
          .user-popup-container {
              top: 0; right: 0;
              width: 358px;
              height: auto; max-height: 493px;
              transform: translateX(120%);
              border-bottom-left-radius: 12px;
          }
          .user-popup-container .popup-header { height: 38px; }
          .desktop-back-btn {
              background: #bfff00;
              width: 39px; height: 38px;
              display: flex !important;
              align-items: center; justify-content: center;
              cursor: pointer; flex-shrink: 0;
          }
          .desktop-back-btn img { width: 18px; height: auto; }
          .desktop-title { display: none !important; }
          .mobile-title { display: none !important; }
          .mobile-only-icon, .mobile-only-row { display: none !important; }
          .desktop-only-row { display: flex !important; }
          
          /* Desktop Logout Button Size 178x38 */
          .user-popup-container .popup-footer {
              padding: 15px;
          }
          .user-popup-container .popup-logout-btn-large {
              width: 178px !important;
              height: 38px !important;
              display: flex !important;
              align-items: center;
              justify-content: center;
              padding: 0 !important;
              font-size: 14px !important;
              border-radius: 6px !important;
          }

          /* Hide icons specifically for desktop popup header */
          .user-popup-container .popup-header-right .fa-search,
          .user-popup-container .popup-header-right .fa-bell {
              display: none !important;
          }

          /* Hide icons specifically for desktop popup header */
          .user-popup-container .popup-header-right .fa-search,
          .user-popup-container .popup-header-right .fa-bell {
              display: none !important;
          }
      }
      
      /* Mobile Specific (Phone) */
      @media (max-width: 767px) {
          .user-popup-container {
              top: 0; left: 0;
              width: 100%;
              height: 100%; 
              height: 100vh; /* Viewport height for modern phones */
              transform: translateY(120%);
              z-index: 10010; /* Restored to below footer nav */
          }
          .popup-header {
              height: 60px;
              background: #000;
              color: #fff;
              padding: 0 10px;
              border-bottom: 1px solid #bfff00;
              display: flex !important;
              align-items: center !important;
              justify-content: space-between !important;
          }
          .popup-header-left {
              flex: 0 0 auto !important;
              display: flex !important;
              align-items: center !important;
              gap: 12px !important;
          }
          .popup-header-center {
              flex: 1 !important;
              display: flex !important;
              align-items: center !important;
              justify-content: flex-end !important;
              padding-right: 10px !important;
          }
          .popup-header-right {
              flex: 0 0 auto !important;
              display: flex !important;
              align-items: center !important;
              gap: 12px !important;
          }
          .popup-header-title { 
              font-size: 13px !important; 
              color: #fff !important; 
              font-weight: 800 !important;
              text-transform: uppercase !important;
              letter-spacing: 0.5px !important;
              margin: 0 !important;
          }
          .mobile-title { display: block !important; }
          .desktop-back-btn { display: none !important; }
          .popup-header-balance { color: #fff; font-size: 12px !important; font-weight: 700; white-space: nowrap; }
          .popup-header-right i { font-size: 18px; color: #fff !important; }
          .btn-deposit {
              background: #bfff00; color: #fff;
              border-radius: 4px; padding: 6px 12px;
              font-size: 11px; font-weight: 800;
              text-transform: uppercase; text-decoration: none;
          }

          .popup-content {
              background: #f7f7f7;
              padding: 10px;
          }
          .fixed-balance-part { margin-bottom: 8px; }
          .scrollable-menu-part { padding-right: 0; }

          .mobile-only-row { display: flex !important; }
          .desktop-only-row { display: none !important; }
          .popup-lang-row { display: flex !important; }

          .profile-row {
              border: 1px solid #d7d7d7;
              border-radius: 6px;
              box-shadow: 0 1px 2px rgba(0,0,0,0.06);
              margin-bottom: 10px;
              padding: 10px 12px;
          }
          .row-label {
              font-weight: 800;
              font-size: 13px;
          }
          .row-value {
              font-weight: 800;
              font-size: 13px;
          }
          .row-value.red-text { color: #d32f2f; }

          .username-row { justify-content: space-between; }
          .mobile-username {
              font-weight: 900;
              font-size: 13px;
              color: #000;
              text-transform: uppercase;
              white-space: nowrap;
              overflow: hidden;
              text-overflow: ellipsis;
              max-width: 55%;
          }
          .change-password-text {
              font-weight: 900;
              font-size: 12px;
              color: #000;
              text-transform: uppercase;
              text-decoration: none;
              white-space: nowrap;
          }
          
          /* Hide desktop close icon on mobile popup header to match image */
          .desktop-close-icon { display: none !important; }
          
          /* Footer Logout Visibility Fix */
           .popup-footer {
               background: transparent !important; 
               padding: 10px 10px 90px 10px !important;
               border-top: none !important;
               box-shadow: none !important; 
           }
          .popup-logout-btn-large {
              width: 65% !important;
              border-radius: 10px !important;
              padding: 12px !important;
              font-size: 14px !important;
              box-shadow: 0 2px 6px rgba(0,0,0,0.15);
          }

          .popup-menu-btn {
              border: 1px solid #111;
              border-radius: 6px;
              padding: 12px;
              margin-bottom: 10px;
              font-weight: 800;
              transition: none;
          }
          .popup-menu-btn:hover {
              background: #bfff00;
              color: #fff;
              border-color: #bfff00;
          }
          .popup-menu-btn:focus,
          .popup-menu-btn:active {
              background: #bfff00 !important;
              color: #fff !important;
              border-color: #bfff00 !important;
              outline: none !important;
          }
      }

      /* Sidebar and Overlay Z-Index Fix */
      .sidebar-overlay { z-index: 10009; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); visibility: hidden; opacity: 0; transition: 0.4s; }
      .sidebar-overlay.active { visibility: visible; opacity: 1; }
      .left-sidebar-overlay { z-index: 10020 !important; }
      .mobile-left-sidebar { z-index: 10030 !important; }
      @media (max-width: 767px) { .mobile-only-footer { z-index: 10040 !important; } }
      .menu-btn-item i {
          color: #bfff00 !important; /* Force Yellow/Gold Icon to match sidebar */
          font-size: 16px;
          margin-right: 10px;
          width: 20px; /* Fixed width for alignment */
          text-align: center;
      }
      .menu-btn-item:hover {
          background: #f9f9f9; /* Light grey hover */
          border-color: #bfff00; /* Yellow border on hover */
          text-decoration: none;
          color: #000; /* Keep text black */
      }

      /* Alert Modal Styles */
      .alert-modal-content {
          border-radius: 8px;
          border: none;
          box-shadow: 0 5px 15px rgba(0,0,0,0.3);
          overflow: hidden;
      }
      .alert-modal-header {
          background: #e0e0e0; /* Grey background as per image */
          padding: 10px 15px;
          display: flex;
          align-items: center;
          gap: 10px;
      }
      .alert-modal-title {
          font-weight: 700;
          font-size: 16px;
          color: #000;
          display: flex;
          align-items: center;
          gap: 8px;
      }
      .alert-icon-circle {
          color: #ff9800; /* Orange Icon Color */
          font-size: 18px;
      }
      .alert-modal-body {
          padding: 20px 15px;
          background: #fff;
          color: #333;
          font-size: 14px;
          font-weight: 500;
      }
      .alert-modal-footer {
          padding: 0 15px 15px 15px;
          background: #fff;
          border: none;
      }
      .btn-alert-ok {
          width: 100%;
          background: #bfff00; /* Orange Button */
          color: #fff;
          font-weight: 700;
          border: none;
          border-radius: 4px;
          padding: 8px;
          text-transform: uppercase;
      }
      .btn-alert-ok:hover {
          background: #d48632;
          color: #fff;
      }

      /* Scrollbar for Popup */
      .popup-content::-webkit-scrollbar {
          width: 6px;
      }
      .popup-content::-webkit-scrollbar-track {
          background: #f1f1f1;
      }
      .popup-content::-webkit-scrollbar-thumb {
          background: #ccc;
          border-radius: 3px;
      }
      
      /* Overlay is already defined as .sidebar-overlay */
      
      /* Remove Hover Dropdown Styles */
      /* .user-dropdown-menu styles removed */

      /* Mobile adjustments */
      @media (max-width: 982px) {
          .header-search, .lang-box { display: none !important; }
          .navbar-brand img { height: 60px; }
          .navmain { padding: 0 10px; }
      }

      @media (max-width: 767px) {
        .custom-navbar { height: 60px; }
        
        /* Default Home Page Layout (Standard Block/Flex) */
        .navmain { 
            height: 60px; 
            padding: 0 10px; 
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        
        /* INNER PAGES ONLY: Flex Layout for Menu/Title vs Deposit/Balance */
        body.inner-page .navmain { 
            height: 60px; 
            padding: 0 10px; 
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        
        body { padding-top: 60px; }
          
          .navbar-brand img { 
              /* User Request: 114x40 ratio me header ka image dikhana mobile phone me */
              width: 114px; 
              height: 40px; 
              object-fit: contain;
          }
          
          /* Show APK on mobile but smaller (Default override) */
        /* .apk-box { display: flex !important; }  <-- Removed this global force */
        
        .apkButton {
            width: 65px;
            height: 34px;
            font-size: 10px;
            padding: 0 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #444; /* Slightly lighter for better contrast */
            border-radius: 4px;
            gap: 4px;
            transition: all 0.3s;
        }
        .apkButton:active {
            transform: scale(0.95);
        }
        
        /* Smaller Login/Signup buttons */
        .loginbtn {
            width: 64px;
            height: 34px;
            font-size: 10px; /* Reduced font size */
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Specific dimensions for Signup button */
        .loginbtn.sigUpd1casino {
            width: 65px;
            height: 34px;
        }
        
        /* Deposit Button Adjustment */
        .btn-deposit {
            padding: 4px 10px !important;
            font-size: 11px !important; /* Smaller text "halka small" */
            font-weight: 700 !important;
            height: auto !important;
            min-height: 28px !important; /* Reduced height */
            line-height: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
          
          /* Ensure left content stays on the left - INNER PAGES ONLY */
        body.inner-page .navbar-left-content {
            display: flex;
            align-items: center;
            height: 100%;
            justify-content: flex-start;
            flex: 1; /* Allow it to grow but start from left */
        }
        
        /* Ensure right content stays on the right - INNER PAGES ONLY */
        body.inner-page .navbar-right-content {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            height: 100%;
            gap: 5px;
        }
          
          /* User Info on Mobile */
          .user-details .username { font-size: 11px; }
          .user-details .balance { font-size: 11px; }
          .logout-btn { height: 24px; line-height: 12px; font-size: 10px; }
          .user-info-wrapper { gap: 8px; }
          
          /* Mobile User Balance Styling (58x19 Ratio Concept) */
          .user-name-display { display: none !important; }
          .user-chevron { display: none !important; }
          .user-profile-box { padding: 0 !important; gap: 0 !important; }
          .user-profile-info { 
              align-items: flex-end; 
              justify-content: center;
          }
          .user-balance-display {
              /* Removed fixed width to prevent cutting of large numbers */
              min-width: 58px; 
              width: auto;
              max-width: 100px; /* Optional constraint */
              display: flex;
              flex-direction: column;
              align-items: flex-end;
              justify-content: center;
              text-align: right;
              cursor: default; 
          }
          .bal-amount-line {
              font-size: 13px;
              line-height: 14px;
              white-space: nowrap;
              /* Removed overflow hidden to show full balance */
              color: #ffff00 !important; 
              font-weight: 700;
          }
          .bal-amount-line span {
              font-size: 10px;
              color: #fff;
          }
          .bal-exposure-line {
              display: none !important; /* Force hide EXP */
          }
          /* Ensure balance box does not show EXP even if JS tries */
          .user-balance-display .bal-exposure-line {
              display: none !important;
          }
          [data-live-balance="exposure"],
          [data-translate="exp"],
          [data-translate="exposure"],
          .mk-ph-exp-row,
          .mk-hide-exposure {
              display: none !important;
              visibility: hidden !important;
          }
          
          /* Adjust Right Content Gap */
          .navbar-right-content {
              gap: 5px; /* Keep tight gap */
              align-items: center; /* Ensure vertical alignment */
          }
          /* Ensure balance is fully visible and aligned */
          .user-profile-box {
              order: 2; /* Ensure it's on the right */
          }
          /* Ensure deposit is to the left of balance */
          .btn-deposit {
              order: 1;
              margin-right: 5px;
          }
          /* Override Flex Order for Mobile Header */
          .navbar-right-content {
              display: flex;
              justify-content: flex-end;
          }

          /* --- Dynamic Header Logic for Mobile --- */
          /* Always show logo */
          body.inner-page .navbar-brand img {
              display: block !important;
          }
          body.inner-page .navbar-brand {
              display: block !important;
          }
          
          /* Show Menu Icon & Text Title on Mobile Header */
          .mobile-menu-icon {
              display: block !important;
              color: #bfff00;
              font-size: 24px;
              margin-right: 15px;
              cursor: pointer;
          }
          .mobile-page-title {
              display: block !important;
              color: #fff;
              font-size: 13px; /* Smaller to match image */
              font-weight: 800;
              text-transform: uppercase;
              letter-spacing: 0.5px;
              white-space: nowrap;
              margin-top: 2px;
          }

          /* Hide APK on Inner Pages */
          body.inner-page .apk-box {
              display: none !important;
          }
          /* Show APK only on Home Page Mobile */
          @media (max-width: 767px) {
              body:not(.inner-page) .apk-box {
                  display: flex !important;
              }
              /* Always show logo */
              body.inner-page .navbar-brand {
                  display: block !important;
              }
          }
          
          /* Adjust Navbar Header flex for inner pages */
          body.inner-page .navbar-header {
              display: flex;
              align-items: center;
              justify-content: flex-start; /* Ensure left alignment */
              width: auto; /* Allow auto width to prevent pushing right content */
              flex: 1; /* Allow growing */
              height: 100%; /* Full height */
              padding-left: 0; /* Remove padding */
          }
          /* Mobile Page Title Styling Match */
          body.inner-page .mobile-page-title {
              display: block !important;
              font-size: 13px; /* Slightly smaller to match image */
              font-weight: 800;
              letter-spacing: 0.5px;
              margin-top: 2px; /* Visual adjustment */
          }
          body.mk-pinned-mode .mobile-menu-icon {
              display: none !important;
          }
          body.mk-pinned-mode .navbar-left-content {
              padding-left: 8px;
          }
          /* Override navbar-right-content for mobile to ensure top-right positioning */
          body.inner-page .navbar-right-content {
              position: relative !important;
              right: auto !important;
              top: auto !important;
              height: 60px !important; /* Match header height */
              display: flex !important;
              align-items: center !important;
              flex-wrap: nowrap !important;
              gap: 8px !important; /* Professional spacing */
          }
          /* Show Icons only on Inner Pages */
          body.inner-page .mobile-only-icons {
              display: flex !important;
          }
          
          /* Icons Container for Search and Bell */
          .header-icons-container {
              display: flex;
              align-items: center;
              margin-left: 10px;
              gap: 15px; /* Increased gap */
              order: 3; /* Ensure they are after balance */
          }
          .header-icon-btn {
              color: #fff;
              font-size: 18px;
              cursor: pointer;
              background: transparent;
              border: none;
              padding: 0;
          }
          .header-icon-btn:hover {
              color: #bfff00;
          }
          /* Ensure balance is order 2, deposit order 1 */
          .user-profile-box { order: 2 !important; }
          .btn-deposit { order: 1 !important; margin-right: 5px; }
      }

      .mobile-action-spacer {
          display: none;
      }

      @media (max-width: 767px) {
          .navmain {
              gap: 6px;
              padding-left: 8px !important;
              padding-right: 8px !important;
          }

          .navbar-left-content {
              flex: 0 0 auto;
              min-width: 0;
          }

          .navbar-right-content {
              flex: 1 1 auto;
              min-width: 0;
          }

          body:not(.inner-page) .mobile-action-spacer {
              display: none !important;
          }

          body.inner-page .mobile-action-spacer,
          body.mk-account-mode .mobile-action-spacer {
              display: none !important;
          }

          body:not(.inner-page) .user-profile-box {
              order: 2 !important;
              margin-left: 0 !important;
          }

          body:not(.inner-page) .mobile-menu-icon {
              margin-right: 8px !important;
          }

          body:not(.inner-page) .navbar-brand {
              height: 60px !important;
          }

          body:not(.inner-page) .navbar-brand img {
              width: 114px !important;
              height: 40px !important;
              object-fit: contain;
              object-position: left center;
          }

          body:not(.inner-page) .navbar-right-content {
              flex: 1 1 auto !important;
              justify-content: flex-end !important;
              margin-left: auto !important;
          }

          body:not(.inner-page) .guest-actions {
              margin-left: auto !important;
              order: 10;
          }
      }
      
      /* Add padding to body to prevent overlap */
      body {
          /* padding-top is now handled by media query above */
          top: 0 !important; /* Fix for Google Translate */
      }
      
      /* Hide Google Translate Toolbar */
      .goog-te-banner-frame.skiptranslate {
          display: none !important;
      }
      .goog-te-gadget-icon {
          display: none !important;
      }
      #google_translate_element {
          display: none !important;
      }
      
      /* Remove default search icons in WebKit browsers */
      input[type="search"]::-webkit-search-decoration,
      input[type="search"]::-webkit-search-cancel-button,
      input[type="search"]::-webkit-search-results-button,
      input[type="search"]::-webkit-search-results-decoration {
        -webkit-appearance: none;
      }
      
      /* Mobile Search - Ensure Visible and Top Layer */
.search-container {
    display: block; /* Override none, rely on transform */
    visibility: hidden; /* Hide initially */
}
.search-container.active {
    visibility: visible;
}
/* Removed conflicting media query for top: 60px */

/* Mobile-Specific Search Styles - HEADER OVERLAY (Next to Menu Icon) */
@media (max-width: 767px) {
    .search-container {
        top: 0 !important;
        left: 0 !important; /* Reset to full width first */
        right: 0 !important;
        width: 100% !important; /* Take full width */
        z-index: 10045 !important;
        padding: 0 !important;
        height: 60px !important;
        background: #111 !important;
        overflow: visible !important;
        padding-left: 50px !important; /* Add padding to clear menu icon instead of moving container */
        box-sizing: border-box !important;
    }
    .search-input-wrapper {
        border-radius: 0 !important;
        border: none !important;
        background: #111; /* Match header */
        padding: 0 !important;
        height: 60px !important;
        border-bottom: 1px solid #bfff00 !important;
        display: flex;
        width: 100%; /* Fill remaining space */
    }
    .search-input {
        height: 100%;
        padding-left: 10px;
        font-size: 15px;
        background: #111;
        color: #fff;
        width: 100%; /* Ensure input takes width */
    }
    .search-results {
        position: fixed;
        top: 60px;
        left: 0; /* Full width results */
        right: 0;
        bottom: 0;
        width: 100%;
        background: #fff;
        height: auto !important;
        max-height: none !important;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        margin-top: 0;
        border-radius: 0;
        box-shadow: none;
        padding-bottom: 20px;
        z-index: 10044;
        display: none; /* Controlled by JS */
    }
    /* Ensure close button is visible */
    .search-close-btn {
        flex-shrink: 0;
    }
}
/* Desktop Search Styles - Fix No Results Visibility */
@media (min-width: 768px) {
    .search-container {
        top: 145px !important; /* Below double header */
        padding: 10px 0; /* Add vertical padding */
    }
    /* Constrain width on desktop to match container */
    .search-input-wrapper, .search-results {
        max-width: 1170px; /* Bootstrap container width */
        margin: 0 auto;
        position: relative;
        left: auto;
        top: auto;
        width: 100%;
        background: #fff; /* Ensure results have background */
        z-index: 20001; /* Ensure on top */
    }
    .search-results {
        border: 1px solid #ddd;
        border-top: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .no-results {
        background: #fff; /* White background for desktop to match results */
        color: #000; /* Black text for visibility */
        display: block; /* Ensure visibility */
    }
}
.search-container.active {
    display: block;
    transform: translateY(0);
}
.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    background: #222;
    border: 1px solid #bfff00;
    border-radius: 4px;
    padding: 5px 10px;
}
.search-input {
    flex: 1;
    background: transparent;
    border: none;
    color: #fff;
    font-size: 16px; /* Prevent iOS zoom and easy read */
    padding: 10px; /* Better touch area */
    outline: none;
    min-height: 40px;
}
.search-results {
    background: #fff;
    max-height: 60vh; /* Allow more height on mobile */
    overflow-y: auto;
    margin-top: 5px;
    border-radius: 4px;
    display: none;
}
.search-result-item {
    padding: 12px;
    border-bottom: 1px solid #eee;
    color: #000;
    display: flex;
    align-items: center;
    cursor: pointer;
    background: #fff;
}
.no-results {
    padding: 15px;
    text-align: center;
    color: #fff; /* Visible against dark background if container is dark, or style container to white */
    font-size: 14px;
    background: #222; /* Fallback for no results container */
    border-radius: 4px;
    margin-top: 5px;
}

/* Ensure Modal Backdrop covers the header (z-index 9999) */
      .modal-backdrop {
          z-index: 10040 !important;
      }

      /* Custom Login Modal Styles (Matching Professional UI) */
      .modal {
          text-align: center;
          padding: 0 !important;
      }
      .modal:before {
          content: '';
          display: inline-block;
          height: 100%;
          vertical-align: middle;
          margin-right: -4px;
      }
      .modal-dialog {
          display: inline-block;
          text-align: left;
          vertical-align: middle;
          width: 450px; /* Increased width as requested */
          max-width: 95%; /* Allow more space on mobile */
      }
      .modal-dialog.signup-modal-dialog {
          width: 550px; /* Wider for Signup */
      }
      .modal-dialog.signup-modal-dialog .modal-content.custom-login-modal {
          padding: 40px 40px; /* More padding for larger feel */
      }
      .modal-content.custom-login-modal {
          background: #000;
          border: 1px solid #bfff00;
          border-radius: 20px; /* Rounded corners like image */
          padding: 30px;
          box-shadow: 0 0 20px rgba(255, 184, 12, 0.2);
          position: relative;
          overflow: hidden;
      }
      
      /* Smooth Animation: Slide Top to Center */
      .modal.fade .modal-dialog {
          transform: translateY(-50px);
          opacity: 0;
          transition: transform 0.3s ease-out, opacity 0.3s ease-out;
      }
      .modal.in .modal-dialog {
          transform: translateY(0);
          opacity: 1;
      }

      /* Close Button Circular Style */
      .close-btn-circle {
          position: absolute;
          right: 15px;
          top: 15px;
          width: 30px;
          height: 30px;
          background: transparent;
          border: 1px solid #bfff00;
          border-radius: 50%;
          color: #bfff00;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          z-index: 100;
          transition: all 0.3s;
          padding: 0;
          line-height: 1;
      }
      .close-btn-circle:hover {
          background: #bfff00;
          color: #000;
      }
      .close-btn-circle span {
          font-size: 20px;
          margin-top: -2px; /* Visual alignment */
      }
      .custom-input {
          background: #000 !important;
          border: 1px solid #bfff00 !important;
          border-radius: 8px !important;
          height: 45px !important;
          color: #fff !important;
          font-weight: bold;
          text-transform: none;
          padding-left: 15px;
      }
      .custom-input::placeholder {
          color: #666;
          font-weight: 800;
      }
      .custom-input:focus {
          box-shadow: 0 0 5px #bfff00;
          outline: none;
      }

      /* Buttons */
      .btn-custom-login {
          background: #bfff00;
          color: #fff;
          font-weight: 800;
          font-size: 16px;
          border-radius: 8px;
          height: 45px;
          border: none;
          transition: all 0.3s;
      }
      .btn-custom-login:hover {
          background: #d48632;
          color: #fff;
      }
      
      .btn-custom-demo {
          background: #000;
          color: #fff;
          border: 1px solid #bfff00;
          font-weight: 800;
          font-size: 14px;
          border-radius: 8px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.3s;
      }
      .btn-custom-demo:hover {
          background: #111;
          border-color: #fff;
          color: #fff;
      }

      /* Social Icons */
      .social-icon {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #fff;
          font-size: 20px;
          transition: transform 0.2s;
      }
      .social-icon:hover {
          transform: scale(1.1);
          color: #fff;
      }
      .telegram { background: #0088cc; }
      .instagram { 
          background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); 
      }
      .facebook { background: #3b5998; }
      
      /* Close Button Override */
      .close:hover {
          color: #fff !important;
          opacity: 1 !important;
      }
      
      /* Mobile Responsive Modal Styles */
      @media (max-width: 767px) {
          .modal-dialog, 
          .modal-dialog.signup-modal-dialog {
              width: 95% !important;
              margin: 10px auto;
          }
          .modal-content.custom-login-modal {
              padding: 20px 15px; /* Reduced padding */
              border-radius: 15px;
          }
          .icon-login img {
              max-width: 140px !important; /* Smaller Logo */
          }
          .custom-input {
              height: 40px !important; /* Smaller Inputs */
              font-size: 13px !important;
          }
          .btn-custom-login, .btn-custom-demo {
              height: 40px;
              font-size: 14px;
          }
          .close-btn-circle {
              width: 28px;
              height: 28px;
              top: 10px;
              right: 10px;
          }
          .close-btn-circle span {
              font-size: 18px;
          }
          /* Adjust Social Icons gap */
          .modal-socialLInk {
              gap: 15px !important;
          }
          .social-icon {
              width: 35px;
              height: 35px;
              font-size: 16px;
          }
          /* Ensure signup row fits well */
          .form-group.custom-input-group {
              margin-bottom: 12px;
          }
          /* Hide Secondary Nav on Mobile */
          .secondary-nav-container {
              display: none !important;
          }
      }

      /* Desktop Only: Secondary Navigation */
      @media (min-width: 768px) {
          /* Adjust body padding for double header */
          body {
              padding-top: 65px !important; /* 93px Main + 52px Secondary */
          }
          
          .secondary-nav-container {
              background-color: #000;
              border-top: 1px solid #bfff00 !important;
              border-bottom: 1px solid #bfff00 !important;
              height: 52px;
              width: 100%;
              position: fixed;
              top: 93px;
              left: 0;
              z-index: 9990;
              display: flex;
              align-items: center;
              /* box-shadow removed for flat look like image */
          }
          /* Container inside to match main header width */
          .secondary-nav-inner {
              /* Container class will be added in HTML */
              height: 100%;
              /* padding: 0 15px; */ /* Removed as .container handles it */
              margin: 0 auto;
          }
          .secondary-nav-list {
              display: flex;
              justify-content: space-between; /* Spread items across full width */
              align-items: center;
              margin: 0;
              padding: 0;
              list-style: none;
              height: 100%;
              width: 100%;
              /* gap: 30px; */ /* Removed gap to allow even spacing */
          }
          .secondary-nav-list li {
              /* Remove flex:1 to prevent spreading too much */
              flex: 0 0 auto; /* Auto width based on content */
              height: 100%;
              display: flex;
              align-items: center;
              justify-content: center;
              position: relative;
              padding: 0;
              border: none !important;
          }
          .secondary-nav-list li:last-child {
              border-right: none;
          }
          .secondary-nav-list li a {
              color: #cccccc; /* Greyish White (Darker White) as requested */
              font-size: 16px; /* Max increased font size */
              font-weight: 900; /* Extra Bold */
              text-transform: uppercase;
              text-decoration: none;
              display: flex;
              align-items: center;
              justify-content: center;
              width: 100%;
              height: 100%;
              gap: 4px; /* Reduced gap */
              transition: color 0.3s; /* Only color transition */
              padding: 10px 2px; /* Minimal padding to remove blank space */
              letter-spacing: 0px; /* Tight spacing */
              white-space: nowrap; /* Prevent wrapping */
          }
          .secondary-nav-list li a:hover {
              color: #ffffff; /* Pure White on Hover */
              background: transparent !important; /* No background change */
              text-shadow: 0 0 5px rgba(255, 255, 255, 0.5); /* Smooth glow effect */
          }
          /* Override previous SVG styles within media query for specificity */
          .secondary-nav-list li a svg {
              width: 18px;
              height: 18px;
              fill: #bfff00;
              transition: fill 0.3s;
          }
          .secondary-nav-list li a:hover svg {
              fill: #bfff00;
          }
      }
  </style>

  <style>
      /* Secondary Nav Specific Styles for SVG Icons */
      .secondary-nav-list li a svg {
          width: 22px; /* Even Larger Icon */
          height: 22px;
          fill: #bfff00; /* Default Gold/Orange */
          transition: fill 0.3s;
          flex-shrink: 0; /* Prevent icon shrinking */
      }
      .secondary-nav-list li a:hover svg {
          fill: #bfff00; /* Lighter Gold on Hover */
          filter: drop-shadow(0 0 2px rgba(255, 184, 12, 0.5)); /* Glow effect for icon */
      }
      
      /* Remove dividers css from here too if present */
      .secondary-nav-list li::after {
          display: none !important;
      }
  </style>
  
  <!-- Global Search Container (Hidden by default, slides down) -->
  <div id="globalSearchContainer" class="search-container">
      <!-- Container removed for mobile flush look, applied via CSS if needed, but wrapper is enough -->
      <div class="search-input-wrapper">
          <!-- Icon removed on mobile via CSS -->
          <i class="fa fa-search text-warning hidden-xs"></i> 
          <input type="text" id="globalSearchInput" class="search-input" placeholder="Search by Event/Game" data-translate="search_placeholder">
          <button class="search-close-btn" onclick="toggleSearch()" data-translate="close">Close</button>
      </div>
      <div id="globalSearchResults" class="search-results"></div>
  </div>

  <script type="text/javascript" src="<?php echo $asset_path; ?>js/translations.js?v=<?php echo time(); ?>"></script>
  <script>
      // Initialize Session Language
      <?php 
      $current_lang = $_SESSION['language'] ?? 'en';
      echo "var sessionLang = '$current_lang';";
      echo "var isLoggedIn = " . ($is_logged_in ? 'true' : 'false') . ";";
      echo "var baseUrl = '$base_url';"; // Default base URL for index.php
      ?>
      
      document.addEventListener('DOMContentLoaded', function() {
          // If session language exists, use it. Otherwise use localStorage or default.
          // But actually, we want to sync them.
          // If logged in, session language is authority.
          if (isLoggedIn && sessionLang) {
              changeLanguage(sessionLang);
              localStorage.setItem('selected_language', sessionLang);
          } else {
              // Not logged in, use local storage or default
              const savedLang = localStorage.getItem('selected_language') || 'en';
              changeLanguage(savedLang);
          }
      });
  </script>
  <script>
  function handleFormSubmit(event, formId, errorContainerId) {
      event.preventDefault();
      const form = document.getElementById(formId);
      const errorContainer = document.getElementById(errorContainerId);
      const formData = new FormData(form);
      const submitBtn = form.querySelector('button[type="submit"]');
      
      // Clear previous errors
      errorContainer.style.display = 'none';
      errorContainer.innerHTML = '';
      
      // Disable button
      const originalBtnText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';

      fetch(form.action, {
          method: 'POST',
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              window.location.href = data.redirect || 'index.php';
          } else {
              errorContainer.style.display = 'block';
              errorContainer.innerHTML = '<div class="alert alert-danger" style="padding: 5px; margin-bottom: 10px; font-size: 12px;">' + data.message + '</div>';
          }
      })
      .catch(error => {
          console.error('Error:', error);
          errorContainer.style.display = 'block';
          errorContainer.innerHTML = '<div class="alert alert-danger" style="padding: 5px; margin-bottom: 10px; font-size: 12px;">Something went wrong. Please try again.</div>';
      })
      .finally(() => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
      });
  }
  </script>
  <style>
    /* Global Hide for Desktop */
    .mobile-left-sidebar, .mobile-menu-icon, .left-sidebar-overlay, .mobile-page-title {
        display: none !important;
    }

    /* Mobile Left Sidebar Styles */
    @media (max-width: 767px) {
    /* Un-hide for mobile */
    .mobile-left-sidebar { display: flex !important; }
    .mobile-menu-icon { display: flex !important; }
    .left-sidebar-overlay { display: none; /* Controlled by JS */ }
    /* .mobile-page-title visibility controlled by inner-page logic */
    
    .mobile-left-sidebar {
        position: fixed;
        top: 60px; /* Header Height (mobile) - starts below header */
        width: 280px; /* More professional fixed width */
        height: calc(100% - 60px); /* Full height minus header */
        background: #000;
        z-index: 10005; /* HIGHER THAN user-popup-container (10002) */
        box-shadow: 2px 0 10px rgba(0,0,0,0.5);
        display: flex;
        flex-direction: column;
        border-top: 1px solid #222;
        
        /* Smoother transition using transform instead of left */
        left: 0;
        transform: translateX(-110%); /* More than 100% to hide shadow */
        visibility: hidden;
        transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1), visibility 0.4s;
        will-change: transform; /* Hardware Acceleration */
    }
    .mobile-left-sidebar.active {
        transform: translateX(0);
        visibility: visible;
    }
    
    /* Animation Keyframes for Menu Icon */
    @keyframes bar1-open {
        0% { transform: translateY(0) rotate(0); }
        50% { transform: translateY(8px) rotate(0); }
        100% { transform: translateY(8px) rotate(45deg); }
    }
    @keyframes bar1-close {
        0% { transform: translateY(8px) rotate(45deg); }
        50% { transform: translateY(8px) rotate(0); }
        100% { transform: translateY(0) rotate(0); }
    }
    @keyframes bar3-open {
        0% { transform: translateY(0) rotate(0); }
        50% { transform: translateY(-8px) rotate(0); }
        100% { transform: translateY(-8px) rotate(-45deg); }
    }
    @keyframes bar3-close {
        0% { transform: translateY(-8px) rotate(-45deg); }
        50% { transform: translateY(-8px) rotate(0); }
        100% { transform: translateY(0) rotate(0); }
    }
    @keyframes bar2-open {
        0% { opacity: 1; }
        100% { opacity: 0; }
    }
    @keyframes bar2-close {
        0% { opacity: 0; }
        100% { opacity: 1; }
    }

    /* Animated Menu Icon */
    .mobile-menu-icon {
        width: 30px;
        height: 20px; 
        position: relative;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        margin-right: 15px; 
        /* Force Left Alignment and Reset Margins */
        margin-left: 0 !important; 
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        align-self: center; /* Ensure it centers vertically in the flex container */
        
        /* Soften the icon lines */
        opacity: 0.9;
    }
    .mobile-menu-icon span {
        display: block;
        height: 3px;
        width: 100%;
        background: #bfff00;
        border-radius: 4px; /* Softer edges */
        position: absolute;
        left: 0;
        /* Hardware accelerated transitions */
        will-change: transform, opacity;
    }
    .mobile-menu-icon span:nth-child(1) { top: 0; }
    .mobile-menu-icon span:nth-child(2) { top: 8px; }
    .mobile-menu-icon span:nth-child(3) { top: 16px; }

    /* Apply Animations on Open */
    .mobile-menu-icon.open span:nth-child(1) {
        animation: bar1-open 0.4s forwards;
    }
    .mobile-menu-icon.open span:nth-child(2) {
        animation: bar2-open 0.4s forwards;
    }
    .mobile-menu-icon.open span:nth-child(3) {
        animation: bar3-open 0.4s forwards;
    }

    /* Apply Animations on Close (Reverse) */
    .mobile-menu-icon.closing span:nth-child(1) {
        animation: bar1-close 0.4s forwards;
    }
    .mobile-menu-icon.closing span:nth-child(2) {
        animation: bar2-close 0.4s forwards;
    }
    .mobile-menu-icon.closing span:nth-child(3) {
        animation: bar3-close 0.4s forwards;
    }

    /* Menu Section */
    .left-sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 10px 0;
        display: flex;
        flex-direction: column; /* Force vertical stacking */
    }
    
    .menu-heading {
        color: #fff;
        font-weight: 800;
        font-size: 16px;
        padding: 10px 15px;
        text-transform: uppercase;
        border-bottom: 2px solid #bfff00;
        display: block; /* Ensure block */
        width: fit-content;
        margin-left: 15px;
        margin-bottom: 10px;
    }
    
    .sidebar-menu-item {
        display: flex !important; /* Force flex */
        align-items: center;
        padding: 12px 20px;
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        border-bottom: 1px solid #222;
        transition: background 0.2s;
        width: 100%; /* Full width */
        box-sizing: border-box;
        min-height: 45px; /* Ensure height */
    }
    .sidebar-menu-item:hover, .sidebar-menu-item:focus {
        background: #111;
        color: #bfff00;
        text-decoration: none;
    }
    .sidebar-menu-item i.icon-left {
        width: 25px;
        min-width: 25px; /* Prevent icon shrinking */
        color: #bfff00;
        font-size: 16px;
        margin-right: 10px;
        text-align: center;
    }
    .sidebar-menu-item span.text {
        flex: 1; /* Allow text to take available space */
        white-space: nowrap; /* Prevent wrapping */
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-menu-item i.icon-right {
        margin-left: auto; /* Push to right */
        color: #bfff00;
        min-width: 15px; /* Prevent shrinking */
    }
    .left-sidebar-overlay {
        position: fixed;
        top: 60px; /* Below Header */
        left: 0;
        width: 100%;
        height: calc(100% - 60px);
        background: rgba(0,0,0,0.7);
        z-index: 10004; /* HIGHER THAN user-popup-container (10002) */
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.4s ease, visibility 0.4s;
    }
    .left-sidebar-overlay.active {
        visibility: visible;
        opacity: 1;
    }
    } /* End @media */

    /* Mobile Footer Nav */
    .mobile-only-footer {
        display: none; /* Hidden by default on desktop */
    }

    @media (max-width: 767px) {
        body {
            padding-bottom: 60px; /* Space for footer nav */
        }
        .mobile-only-footer {
            display: block;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 60px;
            background: #1a1a1a;
            border-top: 1px solid #bfff00;
            z-index: 9999;
            transform: translateY(0);
            transition: transform 180ms ease;
        }
        .mobile-only-footer.mk-hidden {
            transform: translateY(100%);
        }
        .mobile-only-footer ul {
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 100%;
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .mobile-only-footer li {
            flex: 1;
            text-align: center;
        }
        .mobile-only-footer li a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            height: 100%;
        }
        .mobile-only-footer li a i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        .mobile-only-footer li.active a i,
        .mobile-only-footer li.active a span {
            color: #bfff00;
        }
    }
  </style>

  <!-- Mobile Left Sidebar HTML -->
  <div id="mobileLeftSidebar" class="mobile-left-sidebar">
      <!-- No Header inside sidebar, uses Main Header -->
      
      <div class="left-sidebar-content">
          <div class="menu-heading" data-translate="menu">MENU</div>
          
          <div class="sidebar-menu-item" onclick="location.href='<?php echo $base_url; ?>live-football'">
              <i class="fa fa-clock-o icon-left"></i> <span class="text" data-translate="live_football">LIVE FOOTBALL</span> <i class="fa fa-chevron-right icon-right"></i>
          </div>

          <div class="sidebar-menu-item" onclick="location.href='<?php echo $base_url; ?>casino-games/live-casino/'">
              <i class="fa fa-dot-circle-o icon-left"></i> <span class="text" data-translate="live_casino">LIVE CASINO</span> <i class="fa fa-chevron-right icon-right"></i>
          </div>
          <div class="sidebar-menu-item" onclick="location.href='<?php echo $base_url; ?>sports/'">
              <i class="fa fa-futbol-o icon-left"></i> <span class="text" data-translate="sports">SPORTS</span> <i class="fa fa-chevron-right icon-right"></i>
          </div>

          <div class="sidebar-menu-item" onclick="location.href='<?php echo $base_url; ?>casino-games/slot-games/'">
              <i class="fa fa-th icon-left"></i> <span class="text" data-translate="slot_games">SLOTS</span> <i class="fa fa-chevron-right icon-right"></i>
          </div>
      </div>
  </div>
  <div id="leftSidebarOverlay" class="left-sidebar-overlay" onclick="toggleMobileMenu()"></div>

  <script>
  // Simple direct toggle function
  function toggleSearch() {
      var container = document.getElementById("globalSearchContainer");
      if (container) {
          var isActive = container.classList.contains("active") || container.style.display === "block";
          if (isActive) {
              container.classList.remove("active");
              setTimeout(function() { container.style.display = "none"; }, 300); 
          } else {
              container.style.display = "block";
              void container.offsetWidth; 
              container.classList.add("active");
              setTimeout(function() {
                  var input = document.getElementById("globalSearchInput");
                  if (input) input.focus();
              }, 100);
          }
      }
  }

  // Close search when clicking outside
  document.addEventListener('click', function(e) {
      var container = document.getElementById("globalSearchContainer");
      var searchTriggers = document.querySelectorAll('.mgh-icon-search, .fa-search, .search-trigger, .header-icon-btn');
      
      // If click is on a trigger, ignore (handled by trigger click event)
      var isTrigger = false;
      searchTriggers.forEach(function(trigger) {
          if (trigger.contains(e.target)) {
              isTrigger = true;
          }
      });
      if (isTrigger) return;

      if (container && container.classList.contains('active')) {
          var isClickInside = container.contains(e.target);
          
          // Check if click is inside results container or input
          if (!isClickInside) {
              toggleSearch();
          }
      }
  });

  // Robust Event Binding
  $(document).ready(function() {
      // Prevent closing when clicking inside the search container itself (input, results)
      $('#globalSearchContainer').on('click', function(e) {
          e.stopPropagation();
      });

      // Bind click to ANY element with search-related classes
      $(document).on('click', '.mgh-icon-search, .fa-search, .search-trigger', function(e) {
          // If it's inside a button, prevent default button action
          e.preventDefault();
          e.stopPropagation();
          console.log("Search icon clicked");
          toggleSearch();
      });
      
      // Bind to parent buttons if the icon itself doesn't catch it
      $(document).on('click', '.header-icon-btn', function(e) {
          if ($(this).find('.fa-search').length > 0) {
              e.preventDefault();
              e.stopPropagation();
              console.log("Search button clicked");
              toggleSearch();
          }
      });

      var searchTimeout;
      $('#globalSearchInput').on('input', function() {
          var query = $(this).val().trim();
          var resultsContainer = $('#globalSearchResults');
          
          if (query.length < 2) {
              resultsContainer.hide().empty();
              return;
          }

          // Clear existing timeout
          if (searchTimeout) clearTimeout(searchTimeout);

          // Set new timeout (Debounce 300ms)
          searchTimeout = setTimeout(function() {
              // Use PHP Backend for Search
              // Ensure baseUrl is defined in JS context, usually echoing $base_url
              var searchApiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '') + 'api/search.php';
              
              $.ajax({
                  url: searchApiUrl,
                  type: 'GET',
                  data: { q: query },
                  dataType: 'json',
                  success: function(data) {
                      console.log("Search results received:", data); // Debug log
                      resultsContainer.empty();
                      
                      // Explicitly show container
                      resultsContainer.show();
                      resultsContainer.css('display', 'block'); 
                      
                      if (data.length > 0) {
                          data.forEach(function(game) {
                              var item = $('<div class="search-result-item" onclick="location.href=\'' + game.url + '\'">' +
                                  '<img src="' + game.img + '" alt="' + game.name + '">' +
                                  '<span>' + game.name + '</span>' +
                                  '</div>');
                              resultsContainer.append(item);
                          });
                      } else {
                          console.log("No results found - showing message");
                          resultsContainer.html('<div class="no-results" style="display:block; color:#000; padding:15px; text-align:center;">No results found</div>');
                      }
                  },
                  error: function(xhr, status, error) {
                      console.error("Search error:", error);
                      resultsContainer.html('<div class="no-results" style="display:block; color:red; padding:15px; text-align:center;">Error searching</div>').show();
                  }
              });
          }, 300);
      });
  });

  function toggleMobileMenu() {
      var sidebar = document.getElementById("mobileLeftSidebar");
      var overlay = document.getElementById("leftSidebarOverlay");
      var icons = document.querySelectorAll(".mobile-menu-icon"); // Select all menu icons
      
      if (sidebar.classList.contains("active")) {
          // Close
          sidebar.classList.remove("active");
          overlay.classList.remove("active");
          icons.forEach(function(icon) {
              icon.classList.remove("open");
              icon.classList.add("closing");
              setTimeout(function() { icon.classList.remove("closing"); }, 400);
          });
      } else {
          // Open
          sidebar.classList.add("active");
          overlay.classList.add("active");
          icons.forEach(function(icon) {
              icon.classList.add("open");
              icon.classList.remove("closing");
          });
      }
  }
  
  // Close when clicking link
  document.addEventListener('DOMContentLoaded', function() {
      var links = document.querySelectorAll('.sidebar-menu-item');
      links.forEach(function(link) {
          link.addEventListener('click', function() {
              // Only toggle if sidebar is active
              var sidebar = document.getElementById("mobileLeftSidebar");
              if (sidebar.classList.contains("active")) {
                  toggleMobileMenu(); 
              }
          });
      });
  });

  (function () {
      function closeSearchIfOpen() {
          try {
              var container = document.getElementById("globalSearchContainer");
              if (container && (container.classList.contains("active") || container.style.display === "block")) {
                  container.classList.remove("active");
                  container.style.display = "none";
              }
              var results = document.getElementById("globalSearchResults");
              if (results) {
                  results.style.display = "none";
                  results.innerHTML = "";
              }
          } catch (e) {}
      }

      function closeAllOverlays() {
          try { closeSearchIfOpen(); } catch (e1) {}
          try { if (typeof closeSidebar === 'function') closeSidebar(); } catch (e2) {}
          try {
              var lm = document.getElementById("mobileLeftSidebar");
              if (lm && lm.classList.contains("active") && typeof toggleMobileMenu === 'function') toggleMobileMenu();
          } catch (e3) {}
      }

      try {
          var prevAfter = window.MK_AFTER_ROUTE_CHANGE;
          window.MK_AFTER_ROUTE_CHANGE = function (path) {
              closeAllOverlays();
              if (typeof prevAfter === 'function') {
                  try { prevAfter(path); } catch (e) {}
              }
          };
      } catch (eA) {}
      try {
          var prevBefore = window.MK_BEFORE_ROUTE_CHANGE;
          window.MK_BEFORE_ROUTE_CHANGE = function (from, to) {
              closeAllOverlays();
              if (typeof prevBefore === 'function') {
                  try { prevBefore(from, to); } catch (e) {}
              }
          };
      } catch (eB) {}
  })();
  </script>
</head>

<?php
$__body_class = '';
$__uri = (string)($_SERVER['REQUEST_URI'] ?? '');
if ($__uri !== '' && (strpos($__uri, 'account-details') !== false || strpos($__uri, 'account-statement') !== false || strpos($__uri, 'profit-loss') !== false || strpos($__uri, 'bet-history') !== false || strpos($__uri, 'activity-log') !== false || strpos($__uri, 'deposit-withdraw') !== false)) {
    $__body_class = 'mk-account-mode';
}
$__path = (string)(parse_url($__uri, PHP_URL_PATH) ?? '');
if ($__path !== '' && (preg_match('#/sports/?$#i', $__path) || preg_match('#/sportsbook/?$#i', $__path))) {
    $__body_class = trim($__body_class . ' mk-game-no-chrome');
}
?>
<body<?php echo $__body_class !== '' ? ' class="' . $__body_class . '"' : ''; ?>>
<!-- No Google Translate Div -->
<!-- Dynamic Header Script to inject class before render if possible (to minimize FOUC) -->
<script>
(function() {
    var path = window.location.pathname;
    path = (path || '/').replace(/\/{2,}/g, '/');
    if (path.length > 1 && path.endsWith('/')) path = path.slice(0, -1);
    var isHome = (path === '/' || /\/index\.php$/i.test(path));
    // Check if inner page immediately
    if (!isHome && (path.indexOf('pinned') !== -1 || path.indexOf('casino-games') !== -1 || path.indexOf('fantasy-games') !== -1 || path.indexOf('play') !== -1 || path.indexOf('sports') !== -1)) {
        document.body.classList.add('inner-page');
    } else {
        document.body.classList.remove('inner-page');
    }
    if (/\/sportsbook(?:\/|$)/i.test(path) || /\/sports(?:\/|$)/i.test(path)) {
        document.body.classList.add('mk-game-no-chrome');
    } else {
        document.body.classList.remove('mk-game-no-chrome');
    }
    if (path.indexOf('pinned') !== -1) {
        document.body.classList.add('mk-pinned-mode');
    } else {
        document.body.classList.remove('mk-pinned-mode');
    }
    if (path.indexOf('account-details') !== -1 ||
        path.indexOf('account-statement') !== -1 ||
        path.indexOf('profit-loss') !== -1 ||
        path.indexOf('bet-history') !== -1 ||
        path.indexOf('activity-log') !== -1 ||
        path.indexOf('deposit-withdraw') !== -1) {
        document.body.classList.add('mk-account-mode');
    }
})();
</script>
<nav class="custom-navbar">
  <div class="navmain">
    
    <!-- Left: Logo & Menu -->
    <div class="navbar-left-content">
      <a class="navbar-brand" href="<?php echo $base_url; ?>">
        <img src="<?php echo $base_url; ?>assets/images/logo_bets.jpeg" alt="Alpha 216" style="height: 60px;">
      </a>
      
      <ul class="desktop-nav-menu hidden-xs hidden-sm">
<li><a href="<?php echo $base_url; ?>sportsbook/">PARIS SPORTIFS</a></li>
        <li><a href="<?php echo $base_url; ?>frontend/dist/index.html">PARIS EN DIRECT</a></li>
        <li><a href="<?php echo $base_url; ?>casino">JEUX D'ADRESSE</a></li>
        <li><a href="<?php echo $base_url; ?>casino-games/live-casino">CASINO EN DIRECT</a></li>
        <li><a href="<?php echo $base_url; ?>casino-games/virtual-sports">VIRTUEL</a></li>
        <li><a href="<?php echo $base_url; ?>play/?g=spaceman">SPACEMAN</a></li>
        <li><a href="<?php echo $base_url; ?>play/?g=zeppelin">ZEPPELIN</a></li>
      </ul>
    </div>

    <!-- Right: Actions -->
    <div class="navbar-right-content">
      <?php if (!$is_logged_in): ?>
          <button class="login-btn-new" onclick="$('#loginModal').modal('show')" data-translate="login">CONNEXION</button>
          <div class="lang-flag hidden-xs" style="margin-left: 10px;">
              <img src="https://flagicons.lipis.dev/flags/4x3/fr.svg" alt="FR" style="width: 24px; border-radius: 2px;">
          </div>
      <?php else: ?>
          <div class="user-profile-box" onclick="openSidebar();">
              <div class="user-profile-info">
                  <div class="user-name-display"><?php echo htmlspecialchars($username); ?></div>
                  <div class="user-balance-display">
                      <span data-live-balance="available" style="color: var(--primary-neon); font-weight: 800;"><?php echo number_format($user_balance, 2); ?></span> <span style="font-size: 10px;">TND</span>
                  </div>
              </div>
              <div class="user-avatar" style="width: 32px; height: 32px; background: #222; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: 10px; border: 1px solid var(--primary-neon);">
                  <i class="fa fa-user" style="color: var(--primary-neon);"></i>
              </div>
          </div>
      <?php endif; ?>
      

    </div>
  </div>
</nav>

<style>
:root {
  --primary-neon: #bfff00;
  --bg-dark: #0a0a0a;
  --bg-card: #141414;
}

body {
    background-color: var(--bg-dark) !important;
    color: #fff !important;
    padding-top: 0 !important;
} 

/* Modern Navbar Styles */
.custom-navbar {
    background: #000;
    border-bottom: 1px solid rgba(191, 255, 0, 0.2);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 80px;
    z-index: 10000;
    display: flex;
    align-items: center;
}

.navmain {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin: 0;
    padding: 0 20px;
}

.navbar-left-content {
    display: flex;
    align-items: center;
    gap: 30px;
}

.navbar-brand img {
    height: 60px;
}

.desktop-nav-menu {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 20px;
}

.desktop-nav-menu a {
    color: #fff;
    text-decoration: none;
    font-weight: 800;
    font-size: 13px;
    letter-spacing: 0.5px;
    transition: all 0.3s;
    text-transform: uppercase;
}

.desktop-nav-menu a:hover {
    color: var(--primary-neon);
    text-shadow: 0 0 10px var(--primary-neon);
}

.navbar-right-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

.login-btn-new {
    background: transparent;
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 15px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
    transition: all 0.3s;
    text-transform: uppercase;
}

.login-btn-new:hover {
    background: rgba(255,255,255,0.1);
    color: #59e052;
    border-color: #59e052;
}

.user-profile-box {
    display: flex;
    align-items: center;
    background: #111;
    padding: 5px 12px;
    border-radius: 30px;
    border: 1px solid rgba(191, 255, 0, 0.2);
    cursor: pointer;
    transition: all 0.3s;
}

.user-profile-box:hover {
    border-color: var(--primary-neon);
}

.user-name-display {
    font-size: 11px;
    color: #888;
    line-height: 1;
    margin-bottom: 2px;
}

.user-balance-display {
    font-size: 14px;
    font-weight: 800;
    color: var(--primary-neon);
    line-height: 1;
}

@media (max-width: 991px) {
    .desktop-nav-menu { display: none; }
}

@media (min-width: 992px) {
  #mkSportsWalletModal .modal-dialog {
    width: 500px !important;
    max-width: 500px !important;
    margin: calc((100vh - 357px) / 2) auto !important;
  }
  #mkSportsWalletModal .modal-content {
    height: 357px !important;
  }
  #mkSportsWalletModal .modal-body {
    padding-top: 12px !important;
    padding-bottom: 12px !important;
  }
  #mkSportsWalletModal .available-wrap {
    margin-top: 8px !important;
    margin-bottom: 12px !important;
  }
  #mkSportsWalletModal .slidecontainer .rangeBox {
    margin-top: 12px !important;
  }
  #mkSportsWalletModal #coinval {
    width: 270px !important;
    height: 40px !important;
  }
}

#mkSportsWalletModal .mk-sw-hero {
  position: relative;
  z-index: 2;
  height: 180px;
  padding: 14px 16px 0;
  color: #fff;
}
#mkSportsWalletModal .mk-sw-bg-wrap {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 180px;
  border-top-left-radius: 16px;
  border-top-right-radius: 16px;
  overflow: hidden;
  pointer-events: none;
}
#mkSportsWalletModal .mk-sw-bg {
  position: absolute;
  inset: 0;
}
#mkSportsWalletModal .mk-sw-bg-base {
  background: linear-gradient(180deg, rgba(26,15,43,0.9) 0%, rgba(42,26,63,0.92) 100%);
}
#mkSportsWalletModal .mk-sw-bg-img {
  background: url('https://moneyking365.com/popupcoin.18a8bbb33acfc07fd651.png') center/cover no-repeat;
  opacity: .9;
  filter: saturate(1.05) contrast(1.05);
}
#mkSportsWalletModal .mk-sw-bg-fog {
  background: linear-gradient(180deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.10) 55%, rgba(0,0,0,0.22) 100%);
}
#mkSportsWalletModal .modal-content {
  overflow: hidden;
}
#mkSportsWalletModal .mk-sw-title {
  margin: 4px 0 0;
  text-align: center;
  color: #fff;
  font-weight: 600;
  font-size: 24px;
  text-shadow: 0 2px 10px rgba(0,0,0,0.35);
}
#mkSportsWalletModal .mk-sw-balances {
  position: absolute;
  left: 16px;
  right: 16px;
  top: 84px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  color: #fff;
}
#mkSportsWalletModal .mk-sw-balances .mk-sw-bal {
  margin: 0;
  font-weight: 700;
  font-size: 14px;
  line-height: 1.1;
}
#mkSportsWalletModal .mk-sw-balances .mk-sw-bal .mk-sw-lbl {
  opacity: .9;
  display: block;
}
#mkSportsWalletModal .mk-sw-balances .mk-sw-bal .mk-sw-val {
  display: block;
  margin: 4px 0 0;
  color: #ffea00;
  font-weight: 800;
  font-size: 20px;
}

#mkSportsWalletModal #coinval {
  width: 270px;
  height: 40px;
  border-radius: 10px;
}
#mkSportsWalletModal .rangeBox {
  border-radius: 6px !important;
}

#mkSportsWalletModal #mkSwRange {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 14px;
  border-radius: 999px;
  background: #bdbdbd;
  outline: none;
}
#mkSportsWalletModal #mkSwRange::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #d4a017;
  border: 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  cursor: pointer;
}
#mkSportsWalletModal #mkSwRange::-moz-range-thumb {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #d4a017;
  border: 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  cursor: pointer;
}
#mkSportsWalletModal #mkSwRange::-moz-range-track {
  height: 14px;
  border-radius: 999px;
  background: #bdbdbd;
}
#mkSportsWalletModal .bodypabal {
  padding-bottom: 0 !important;
}
#mkSportsWalletModal .footermm {
  margin: 14px -16px 0 !important;
  padding: 0 !important;
}
#mkSportsWalletModal #mkSwGo {
  display: block;
  width: 100%;
  border-radius: 0 0 16px 16px;
  height: 56px;
  line-height: 56px;
  padding: 0;
}
#mkSportsWalletModal .modal-header .close {
  border-radius: 999px;
}
#mkSportsWalletModal .modal-header .close img {
  transition: filter 160ms ease, transform 160ms ease;
  transform: translateZ(0);
}
#mkSportsWalletModal .modal-header .close:hover img,
#mkSportsWalletModal .modal-header .close:focus img,
#mkSportsWalletModal .modal-header .close:active img {
  filter: drop-shadow(0 2px 0 rgba(0,0,0,0.35)) drop-shadow(0 10px 18px rgba(0,0,0,0.35));
  transform: translateZ(0);
}
@media (max-width: 767px) {
  #mkSportsWalletModal .modal-header .close {
    right: -10px !important;
    top: -10px !important;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  #mkSportsWalletModal .modal-header .close img {
    width: 46px !important;
    height: 46px !important;
  }
}
</style>

<div class="modal fade" id="mkSportsWalletModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false" style="z-index:10060;">
  <div class="modal-dialog" role="document" style="top:0%; max-width:480px; width:calc(100% - 24px); margin:4vh auto;">
    <div class="modal-content popimgcoin" style="border-radius:16px; overflow:visible; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
      <div class="mk-sw-bg-wrap">
        <div class="mk-sw-bg mk-sw-bg-base"></div>
        <div class="mk-sw-bg mk-sw-bg-img"></div>
        <div class="mk-sw-bg mk-sw-bg-fog"></div>
      </div>

      <div class="modal-header" style="border:0; padding:0; position:relative; z-index:2;">
        <button aria-label="Close" class="close" data-dismiss="modal" type="button" style="position:absolute; right:-18px; top:-18px; background:transparent; border:0; padding:0; opacity:1;">
          <img src="https://moneyking365.com/assets/images/removeicon.png" width="50" height="50" style="display:block; filter:drop-shadow(0 2px 0 rgba(0,0,0,0.35));" alt="Close">
        </button>
      </div>

      <div class="mk-sw-hero">
        <h2 class="boxMain mk-sw-title" id="mkSwTitle">Sports Books</h2>
        <div class="available-wrap mk-sw-balances">
          <h4 class="text-left mk-sw-bal">
            <span class="mk-sw-lbl">Main Balance</span>
            <label id="mkSwMainBal" class="mk-sw-val" data-live-balance="available">0.00</label>
          </h4>
          <h4 class="text-right mk-sw-bal" style="text-align:right;">
            <span class="mk-sw-lbl" id="commonVendorBalanceTitle">Wallet Balance</span>
            <label id="mkSwWalletBal" class="mk-sw-val">0.00</label>
          </h4>
        </div>
      </div>

      <div class="modal-body bodypabal" style="position:relative; z-index:2; padding:14px 16px 0; background:#fff; border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
        <div class="slidecontainer">
          <div style="display:flex; justify-content:center;">
            <input id="coinval" type="number" value="0" min="0" inputmode="numeric" style="border:2px solid #d4a017; background:#fff; text-align:center; font-weight:900; color:#000; box-shadow:0 2px 8px rgba(0,0,0,0.10);">
          </div>

          <div class="rangeBox" style="margin-top:14px; background:#fff; border:1px solid #cfcfd6; overflow:hidden;">
            <div class="inBox" style="display:flex; align-items:stretch;">
              <span id="mkSwMin" class="ripple" style="width:70px; display:flex; align-items:center; justify-content:center; font-weight:900; color:#111; background:#fff; border-right:1px solid #cfcfd6; cursor:pointer;">0</span>
              <div style="flex:1; padding:16px 14px; background:#fff;">
                <input id="mkSwRange" class="slider" type="range" min="0" max="0" value="0">
              </div>
              <span id="mkSwMax" class="ripple" style="width:80px; display:flex; align-items:center; justify-content:center; font-weight:900; color:#111; background:#fff; border-left:1px solid #cfcfd6; cursor:pointer;">Max</span>
            </div>
          </div>
        </div>

        <div class="modal-footer footermm" style="border:0; padding:14px 0 0; background:transparent;">
          <button id="mkSwGo" class="btn btn-primary" type="button" style="width:100%; border-radius:0 0 16px 16px; background:#bfff00; border:0; font-weight:900; height:52px;">Transfer and Enter</button>
        </div>
      </div>

      <input id="mkSwInput" type="hidden" value="0">
    </div>
  </div>
  <input type="hidden" id="mkSwTarget" value="">
</div>

<script>
(function(){
  function openSwModal(path, title) {
    $('#mkSwTarget').val(path || '/sports/');
    $('#mkSwTitle').text(title || 'Sports Book');
    $('#mkSwInput').val('0'); $('#mkSwRange').attr('max','0').val('0'); $('#coinval').val('0');
    $('#mkSportsWalletModal').modal('show');
    try {
      var sportsId = '3978';
      var sbId = '3978';
      var gid = (String(title || '').toLowerCase().indexOf('book') !== -1) ? sbId : sportsId;
      if (typeof MK_prefetchGame === 'function') MK_prefetchGame(gid);
      else {
        var apiUrl = (typeof SITE_API_URL !== 'undefined') ? SITE_API_URL + 'launch_game.php' : 'api/launch_game.php';
        if (window.fetch) {
          var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
          var desiredHome = origin + (gid === sportsId ? '/sports/?mk=1' : '/');
          fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ game_id: gid, home_url: desiredHome, prefetch: 1, skip_log: true }),
            cache: 'no-store'
          }).catch(function(){});
        }
      }
    } catch(e){}
    // Show exact main balance from session and clamp non-negative
    try {
      function setMax(maxVal) {
        var m = Math.max(0, Math.floor(Number(maxVal || 0)));
        $('#mkSwRange').attr('max', String(m));
        var cur = Number($('#coinval').val() || 0);
        if (!isFinite(cur) || cur < 0) cur = 0;
        if (cur > m) cur = m;
        $('#coinval').val(String(cur));
        $('#mkSwRange').val(String(cur));
        $('#mkSwInput').val(String(cur));
      }
      var main = Math.max(0, Number(window.MK_MAIN_BALANCE_CI || 0));
      $('#mkSwMainBal').text(main.toFixed(2));
      setMax(main);
    } catch(e){}
  }
  try { window.openSwModal = openSwModal; } catch(e) {}
  function bindSw() {
    var r = $('#mkSwRange'), i = $('#coinval');
    function updHidden(val){ $('#mkSwInput').val(String(val)); }
    r.on('input change', function(){ i.val(this.value); updHidden(this.value); });
    i.on('input change', function(){
      var v = Number(this.value || 0);
      if (!isFinite(v) || v < 0) v = 0;
      var max = Number(r.attr('max') || 0);
      if (v > max) v = max;
      this.value = String(v);
      r.val(String(v));
      updHidden(v);
    });
    $('#mkSwMin').on('click', function(){ r.val('0'); i.val('0'); updHidden(0); });
    $('#mkSwMax').on('click', function(){
      var m = r.attr('max') || '0';
      r.val(m); i.val(m); updHidden(m);
    });
    i.on('keydown', function(e){
      try {
        var k = e && (e.key || e.keyCode);
        if (k === 'Enter' || k === 13) {
          e.preventDefault();
          $('#mkSwGo').trigger('click');
        }
      } catch (eK) {}
    });
  }
  $(document).ready(function(){
    bindSw();
    $('#mkSportsWalletModal').on('click', '.close, .modal-header img', function(e){
      try { e.preventDefault(); } catch(_){}
      $('#mkSportsWalletModal').modal('hide');
    });
    $('#mkSportsWalletModal').on('click', '#mkSwGo', function(){
      var tgt = $('#mkSwTarget').val() || '';
      function nav(){
        if (!tgt) return;
        try {
          var abs = new URL(String(tgt), window.location.origin);
          tgt = (abs.pathname || '/') + (abs.search || '') + (abs.hash || '');
        } catch (eU) {}
        try {
          if (typeof history.pushState === 'function') {
            history.pushState({}, '', tgt);
            try {
              window.dispatchEvent(new PopStateEvent('popstate'));
            } catch (eEvt) {
              try {
                var ev = document.createEvent('Event');
                ev.initEvent('popstate', true, true);
                window.dispatchEvent(ev);
              } catch (eEvt2) {}
            }
          } else {
            window.location.href = tgt;
          }
        } catch (eN) {
          window.location.href = tgt;
        }
      }
      try { $('#mkSportsWalletModal').one('hidden.bs.modal', nav); } catch (eB) {}
      try { $('#mkSportsWalletModal').modal('hide'); } catch(e){}
      setTimeout(nav, 650);
    });
    document.addEventListener('click', function(e){
      var t = e && e.target ? e.target : null;
      var href = '';
      try {
        var a = t && t.closest ? t.closest('a') : null;
        if (a) href = a.getAttribute('href') || '';
        if (!href) {
          var ocEl = t && t.closest ? t.closest('[onclick]') : null;
          var oc = ocEl ? String(ocEl.getAttribute('onclick') || '') : '';
          var m = oc.match(/location\.href\s*=\s*['"]([^'"]+)['"]/i);
          if (m && m[1]) href = m[1];
        }
      } catch (eH) {}
      if (!href) return;
      var h = String(href || '');
      var hashIdx = h.indexOf('#/');
      if (hashIdx >= 0) h = h.slice(hashIdx + 1);
      h = h.split('?')[0].split('#')[0];
      h = h.replace(/\\/g, '/').replace(/^(\.\.\/)+/g, '/').replace(/^\.\//, '');
      var low = h.toLowerCase();

      if (/(^|\/)sportsbook(\/|$)/i.test(low) || low.indexOf('sportsbook/') !== -1) {
        e.preventDefault(); e.stopImmediatePropagation();
        try {
          var up = document.getElementById('userPopup'); if (up) up.classList.remove('active');
          var ov = document.getElementById('sidebarOverlay'); if (ov) ov.classList.remove('active');
        } catch (eC) {}
        
        window.location.href = '<?php echo $base_url; ?>sportsbook/'; return;
        return;
      }
    }, true);
  });
})();
</script>

<script>
(function () {
  try {
    function setFooterVar() {
      var el = document.getElementById('mobile-footer-nav');
      var h = 0;
      try {
        if (el && window.getComputedStyle) {
          var ds = getComputedStyle(el).display;
          if (ds && ds !== 'none') {
            if (el.classList && el.classList.contains('mk-hidden')) h = 0;
            else h = el.offsetHeight || 0;
          }
        }
      } catch (e) {}
      try { document.documentElement.style.setProperty('--mk-mobile-footer-h', String(h) + 'px'); } catch (e2) {}
    }
    setFooterVar();
    window.addEventListener('resize', function(){ setTimeout(setFooterVar, 60); });
    window.addEventListener('orientationchange', function(){ setTimeout(setFooterVar, 60); });
    document.addEventListener('DOMContentLoaded', function(){ setTimeout(setFooterVar, 0); });
    setTimeout(setFooterVar, 300);

    var startY = 0;
    var lastDir = 0;
    function setFooterHidden(hidden) {
      try {
        var el = document.getElementById('mobile-footer-nav');
        if (!el) return;
        if (!window.matchMedia || !window.matchMedia('(max-width: 767px)').matches) return;
        if (hidden) el.classList.add('mk-hidden');
        else el.classList.remove('mk-hidden');
        setTimeout(setFooterVar, 0);
      } catch (e) {}
    }
    window.addEventListener('touchstart', function(e){
      try {
        if (!e.touches || !e.touches[0]) return;
        startY = e.touches[0].clientY;
      } catch (er) {}
    }, { passive: true });
    window.addEventListener('touchmove', function(e){
      try {
        if (!e.touches || !e.touches[0]) return;
        var y = e.touches[0].clientY;
        var dy = y - startY;
        if (Math.abs(dy) < 12) return;
        if (dy < 0 && lastDir !== 1) { lastDir = 1; setFooterHidden(true); }
        if (dy > 0 && lastDir !== -1) { lastDir = -1; setFooterHidden(false); }
        startY = y;
      } catch (er2) {}
    }, { passive: true });
  } catch (e) {}
})();
</script>
<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="loginModalLabel" data-backdrop="static" data-keyboard="false" style="z-index: 10050;">
  <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 400px;">
    <div class="modal-content" style="background: #2b2b2b; border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        
        <!-- Header -->
        <div class="modal-header" style="background: transparent; border: none; padding: 20px 20px 10px; position: relative;">
            <h4 class="modal-title" style="color: #fff; font-weight: 700; text-align: center; width: 100%; text-transform: uppercase; font-size: 20px;" data-translate="secure_login">CONNEXION</h4>
            <button aria-label="Close" class="close" data-dismiss="modal" type="button" style="color: #fff; position: absolute; right: 20px; top: 20px; opacity: 1; text-shadow: none; font-size: 24px; background: rgba(255,255,255,0.1); width: 32px; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; padding: 0; border: none;">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        
        <div class="modal-body" style="padding: 20px 30px 40px;">
            <div id="login-error" style="display:none; margin-bottom: 20px;"></div>
            
            <form id="loginForm" action="<?php echo $absolute_base_url; ?>login_process.php" method="POST" onsubmit="handleFormSubmit(event, 'loginForm', 'login-error')">
                
                <!-- Username -->
                <div class="form-group" style="margin-bottom: 20px; position: relative;">
                    <div style="position: absolute; left: 15px; top: 12px; color: #666; font-size: 18px;">
                        <i class="fa fa-user"></i>
                    </div>
                    <input class="form-control" name="username" type="text" placeholder="Utilisateur" data-translate="username" required
                           style="background: #fff; border: none; border-radius: 8px; height: 48px; padding-left: 45px; color: #333; font-weight: 500; font-size: 15px;">
                </div>
                
                <!-- Password -->
                <div class="form-group" style="margin-bottom: 10px; position: relative;">
                    <div style="position: absolute; left: 15px; top: 12px; color: #666; font-size: 18px;">
                        <i class="fa fa-lock"></i>
                    </div>
                    <input class="form-control" id="login_password" name="password" type="password" placeholder="Mot de passe" data-translate="password" required
                           style="background: #fff; border: none; border-radius: 8px; height: 48px; padding-left: 45px; padding-right: 45px; color: #333; font-weight: 500; font-size: 15px;">
                    <span class="fa fa-fw fa-eye" onclick="togglePassword('login_password', this)" 
                          style="position: absolute; right: 15px; top: 15px; color: #999; cursor: pointer; font-size: 16px;"></span>
                </div>
                
                <!-- Forgot Password Link -->
                <div style="text-align: right; margin-bottom: 25px;">
                    <a href="#" style="color: #bbb; font-size: 13px; text-decoration: none;" data-translate="forgot_password_q">Nom d'utilisateur/Mot de passe oublié</a>
                </div>
                
                <!-- Login Button -->
                <button class="btn btn-block" type="submit" 
                        style="background: #59e052; color: #000; font-weight: 800; font-size: 16px; border-radius: 8px; height: 50px; border: none; text-transform: uppercase; transition: all 0.3s; box-shadow: 0 4px 15px rgba(89, 224, 82, 0.3);" 
                        data-translate="login">SE CONNECTER</button>
                
            </form>
        </div>
    </div>
  </div>
</div>

<script>
function togglePassword(fieldId, icon) {
    var input = document.getElementById(fieldId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = "password";
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Popup Language Dropdown
    var pDropdown = document.getElementById('popupLangDropdown');
    var pLangList = document.getElementById('popupLangList');
    
    if (pDropdown) {
        pDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
            if (!pDropdown.contains(e.target)) {
                pDropdown.classList.remove('active');
            }
        });
    }

    if (pLangList) {
        var items = pLangList.getElementsByTagName('li');
        for (var i = 0; i < items.length; i++) {
            items[i].addEventListener('click', function(e) {
                e.stopPropagation();
                var langCode = this.getAttribute('data-lang');
                if (typeof changeLanguage === 'function') {
                    changeLanguage(langCode);
                }
                pDropdown.classList.remove('active');
            });
        }
    }
});
</script>

<!-- Right Popup (Fixed Width) -->
<div id="userPopup" class="user-popup-container">
    <!-- Header -->
    <div class="popup-header">
        <div class="popup-header-left">
            <div class="desktop-back-btn" onclick="closeSidebar()">
                <img src="https://moneyking365.com/assets/images/arrowleft.png" alt="Back" style="width: 25px; height: auto;">
            </div>
            <!-- Animated Hamburger for Mobile in Popup -->
            <div class="mobile-menu-icon mobile-only-icon" onclick="toggleMobileMenu(); event.stopPropagation();">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <span class="popup-header-title mobile-title" data-translate="profile">PROFILE</span>
            <span class="player-id-display desktop-only-row"><?php echo htmlspecialchars($username); ?></span>
        </div>
        
        <div class="popup-header-right">
            <div class="popup-header-balance mobile-only-row" style="margin-right: 10px; font-size: 12px; font-weight: 700; color: #fff;">
                <span data-live-balance="available"><?php echo number_format($user_balance, 2); ?></span> TND
            </div>
            <div class="popup-header-balance desktop-only-row" style="display:none;">
                <!-- Desktop balance removed as requested -->
            </div>
            <i class="fa fa-search" onclick="toggleSearch(); event.stopPropagation();" style="cursor: pointer; font-size: 18px; color: #fff; margin-right: 12px;"></i>
            <i class="fa fa-bell" style="cursor: pointer; font-size: 18px; color: #fff;"></i>
        </div>
    </div>
    
    <!-- Content -->
    <div class="popup-content">
        <!-- Fixed Balance Part (Available to Exposure) -->
        <div class="fixed-balance-part">
            <!-- Username & Change Password (Mobile Only inside rows) -->
            <div class="profile-row username-row mobile-only-row">
                <span class="mobile-username"><?php echo htmlspecialchars($username); ?></span>
                <a href="#" class="change-password-text" data-toggle="modal" data-target="#changePasswordModal" data-backdrop="false" data-translate="change_password">CHANGE PASSWORD</a>
            </div>

            <!-- Balance Info -->
            <div class="profile-row">
                <span class="row-label" data-translate="available_balance">Available Balance</span>
                <span class="row-value" data-live-balance="available"><?php echo number_format($user_balance, 2); ?></span>
            </div>
            <div class="profile-row">
                <span class="row-label" data-translate="wallet_balance">Wallet Balance</span>
                <span class="row-value" data-live-balance="wallet"><?php echo number_format($user_wallet_balance, 2); ?></span>
            </div>
            <div class="profile-row">
                <span class="row-label" data-translate="winnings">Winnings</span>
                <span class="row-value" style="color:#0a8f3a;"><?php echo ($user_total_winnings > 0 ? '+' : '') . number_format($user_total_winnings, 2); ?></span>
            </div>
            <div class="profile-row">
                <span class="row-label" data-translate="losing">Losing</span>
                <span class="row-value red-text"><?php echo number_format($user_total_losing, 2); ?></span>
            </div>
        </div>

        <!-- Scrollable Menu Part (Account Detail to Logout) -->
        <div class="scrollable-menu-part">
            <!-- Language Custom Dropdown (Mobile Only) -->
            <div class="popup-lang-row profile-row mobile-only-row">
                <span class="row-label" data-translate="lang">Lang</span>
                <div class="lang-box" style="display: block !important;">
                    <div class="custom-lang-dropdown" id="popupLangDropdown" style="width: 130px; border: 1px solid #ddd; margin-right: -10px;">
                        <div class="selected-view" style="background: #fff; border: none; height: 32px; display: flex; align-items: center; justify-content: space-between;">
                            <span class="current-text" id="popupCurrentLang" style="color: #000; font-size: 11px;">
                                <?php echo $langs[$curr] ?? 'ENGLISH'; ?>
                            </span>
                            <div class="icon-box" style="background: #eee; color: #000; height: 100%; width: 25px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa fa-caret-down"></i>
                            </div>
                        </div>
                        <div class="dropdown-list" style="top: 35px; background: #fff; border: 1px solid #ddd;">
                            <ul id="popupLangList">
                                <?php foreach($langs as $code => $name): ?>
                                    <li data-lang="<?php echo $code; ?>" <?php if($curr == $code) echo 'class="active-lang"'; ?> style="color: #000; border-bottom: 1px solid #eee;"><?php echo $name; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menu Items (Buttons) -->
            <div style="margin-top: 5px; padding-bottom: 10px;">
                <a href="#" class="popup-menu-btn" data-toggle="modal" data-target="#changePasswordModal" data-backdrop="false" data-translate="change_password">Change Password</a>
                <a href="<?php echo $base_url; ?>account-details/" class="popup-menu-btn" data-translate="account_detail">Account Detail</a>
                <a href="<?php echo $base_url; ?>account-statement/" class="popup-menu-btn" data-translate="account_statement">Account Statement</a>
                <a href="<?php echo $base_url; ?>profit-loss/" class="popup-menu-btn" data-translate="profit_loss">Profit And Loss</a>
                <a href="<?php echo $base_url; ?>bet-history/" class="popup-menu-btn" data-translate="bet_history">Bet History</a>
                <a href="<?php echo $base_url; ?>activity-log/" class="popup-menu-btn" data-translate="activity_log">Activity Log</a>
            </div>
        </div>
    </div>
    
    <!-- Fixed Footer for Logout -->
    <div class="popup-footer">
        <a href="<?php echo $absolute_base_url; ?>logout.php" class="popup-logout-btn-large" data-translate="logout">LOGOUT</a>
    </div>
</div>

<!-- Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>

<script>
  function openSidebar() {
    var leftSidebar = document.getElementById("mobileLeftSidebar");
    if (leftSidebar && leftSidebar.classList.contains("active")) {
        toggleMobileMenu();
    }
    var popup = document.getElementById("userPopup");
    if (popup) {
        popup.classList.add("active");
    }
    // Toggle overlay for desktop
    var overlay = document.getElementById("sidebarOverlay");
    if (overlay) {
        overlay.classList.add("active");
    }
    
    var footerItems = document.querySelectorAll('.mobile-only-footer li');
    footerItems.forEach(function(item) {
        item.classList.remove('active');
        var textSpan = item.querySelector('span');
        if (textSpan && textSpan.getAttribute('data-translate') === 'profile') {
            item.classList.add('active');
        }
    });
}

function closeSidebar() {
    var popup = document.getElementById("userPopup");
    if (popup) {
        popup.classList.remove("active");
    }
    // Toggle overlay for desktop
    var overlay = document.getElementById("sidebarOverlay");
    if (overlay) {
        overlay.classList.remove("active");
    }
    // Ensure search overlay is closed (prevents z-index covering)
    try {
        var sc = document.querySelector('.search-container');
        if (sc) sc.classList.remove('active');
        var sr = document.querySelector('.search-results');
        if (sr) sr.style.display = 'none';
    } catch (eS) {}
    
    var footerItems = document.querySelectorAll('.mobile-only-footer li');
    var path = window.location.pathname;
    footerItems.forEach(function(item) {
        item.classList.remove('active');
        var textSpan = item.querySelector('span');
        if (textSpan && textSpan.getAttribute('data-translate') === 'home' && (path === '/' || path.endsWith('index.php'))) {
            item.classList.add('active');
        }
    });
}

// Dynamic Mobile Header Logic
document.addEventListener("DOMContentLoaded", function() {
    // Determine if this is the home page
    var path = window.location.pathname;
    // Check various home path possibilities
    var isHome = (path === '/' || path.endsWith('/index.php') || path.endsWith('/main/'));
    
    // Explicitly check if we are in a sub-directory that counts as "inner page"
    if (path.indexOf('pinned') !== -1 || path.indexOf('casino-games') !== -1 || path.indexOf('fantasy-games') !== -1 || path.indexOf('play/') !== -1 || path.indexOf('sports') !== -1) {
        isHome = false;
    }

    if (!isHome) {
        document.body.classList.add('inner-page');
        var titleEl = document.getElementById('mobilePageTitle');
        if (titleEl) {
            var key = 'casino';
            var fallback = 'CASINO';
            if (path.indexOf('pinned') !== -1) { key = 'pinned'; fallback = 'PINNED'; }
            else if (path.indexOf('live-casino') !== -1) { key = 'live_casino'; fallback = 'LIVE CASINO'; }
            else if (path.indexOf('virtual-sports') !== -1) { key = 'virtual_sports'; fallback = 'VIRTUAL SPORTS'; }
            else if (path.indexOf('slot-games') !== -1) { key = 'slot_games'; fallback = 'SLOT GAMES'; }
            else if (path.indexOf('fantasy-games') !== -1) { key = 'fantasy_games'; fallback = 'FANTASY GAMES'; }
            else if (path.indexOf('sports') !== -1) { key = 'sports'; fallback = 'SPORTS'; }
            else if (path.indexOf('play') !== -1) { key = 'game_play'; fallback = 'GAME PLAY'; }

            titleEl.setAttribute('data-translate', key);
            titleEl.innerText = fallback;

            var lang = (typeof isLoggedIn !== 'undefined' && isLoggedIn && typeof sessionLang !== 'undefined' && sessionLang) ? sessionLang : (localStorage.getItem('selected_language') || 'en');
            if (typeof changeLanguage === 'function') {
                changeLanguage(lang);
            }
        }
    } else {
        document.body.classList.remove('inner-page');
        document.body.classList.remove('mk-pinned-mode');
        var homeTitleEl = document.getElementById('mobilePageTitle');
        if (homeTitleEl) {
            homeTitleEl.setAttribute('data-translate', 'home');
            homeTitleEl.innerText = 'HOME';
        }
    }
});
</script>
<script>
// Auto-close profile popup when any actionable item inside it is tapped/clicked
(function(){
  try {
    document.addEventListener('click', function(e){
      var p = document.getElementById('userPopup');
      if (!p || !p.classList.contains('active')) return;
      if (!e.target) return;
      if (!p.contains(e.target)) return;
      var actionEl = e.target.closest && e.target.closest('a,button');
      if (actionEl) {
        try { closeSidebar(); } catch (er) {}
      }
    }, true);
  } catch (ex) {}
})();
</script>

<!-- Mobile Footer Navigation Removed (Replaced by Footer Emoji Nav) -->

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-labelledby="changePasswordTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
                <h4 class="modal-title" id="changePasswordTitle" data-translate="change_password">Change Password</h4>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm" novalidate>
                    <div class="form-group">
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="New Password" required>
                        <span class="toggelPass fa fa-fw fa-eye" onclick="togglePasswordVisibility('new_password', this)"></span>
                    </div>
                    <div class="form-group">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                        <span class="toggelPass fa fa-fw fa-eye" onclick="togglePasswordVisibility('confirm_password', this)"></span>
                    </div>
                    <div class="form-group">
                        <input type="password" id="old_password" name="old_password" class="form-control" placeholder="Old Password" required>
                        <span class="toggelPass fa fa-fw fa-eye" onclick="togglePasswordVisibility('old_password', this)"></span>
                    </div>
                    <div id="changePasswordMessage" style="margin-top: 10px; text-align: center; font-size: 14px; font-weight: 600;"></div>
                </form>
            </div>
            <button id="changePasswordSubmitBtn" type="button" class="modal-footer-btn" onclick="submitChangePassword()">Change Password</button>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(id, icon) {
    var input = document.getElementById(id);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

function submitChangePassword() {
    var btn = $('#changePasswordSubmitBtn');
    if (btn.prop('disabled')) return;

    var newPwd = $('#new_password').val();
    var confirmPwd = $('#confirm_password').val();
    var oldPwd = $('#old_password').val();
    var msgDiv = $('#changePasswordMessage');
    
    if (!newPwd || !confirmPwd || !oldPwd) {
        msgDiv.css('color', 'red').text('All fields are required.');
        return;
    }
    
    if (newPwd !== confirmPwd) {
        msgDiv.css('color', 'red').text('New password and confirm password do not match.');
        return;
    }
    
    msgDiv.css('color', '#bfff00').text('Processing...');
    btn.prop('disabled', true);
    
    $.ajax({
        url: (typeof baseUrl !== 'undefined' ? baseUrl : '') + 'api/change-password.php',
        type: 'POST',
        data: {
            new_password: newPwd,
            old_password: oldPwd
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                msgDiv.css('color', 'green').text(response.message);
                setTimeout(function() {
                    $('#changePasswordModal').modal('hide');
                }, 1500);
            } else {
                msgDiv.css('color', 'red').text(response.message);
                btn.prop('disabled', false);
            }
        },
        error: function() {
            msgDiv.css('color', 'red').text('An error occurred. Please try again.');
            btn.prop('disabled', false);
        }
    });
}

$(document).ready(function() {
    var modal = $('#changePasswordModal');
    var sidebarOverlay = $('#sidebarOverlay');

    modal.on('show.bs.modal', function () {
        modal.removeClass('cp-hiding');
        modal.removeData('cp_force_hide');
        $('#changePasswordMessage').text('');
        $('#changePasswordForm')[0].reset();
        $('#old_password, #new_password, #confirm_password').attr('type', 'password');
        $('#changePasswordModal .toggelPass').removeClass('fa-eye-slash').addClass('fa-eye');
        $('#changePasswordSubmitBtn').prop('disabled', false);

        if (sidebarOverlay.hasClass('active')) {
            sidebarOverlay.data('cp_restore_active', '1');
            sidebarOverlay.removeClass('active');
        }
    });

    modal.on('hide.bs.modal', function (e) {
        if (modal.data('cp_force_hide') === '1') {
            modal.removeData('cp_force_hide');
            return;
        }
        if (!modal.hasClass('cp-hiding')) {
            e.preventDefault();
            modal.addClass('cp-hiding');
            setTimeout(function () {
                modal.data('cp_force_hide', '1');
                modal.modal('hide');
            }, 320);
        }
    });

    modal.on('hidden.bs.modal', function() {
        modal.removeClass('cp-hiding');
        if (sidebarOverlay.data('cp_restore_active') === '1') {
            sidebarOverlay.addClass('active');
        }
        sidebarOverlay.removeData('cp_restore_active');
    });

    $('#changePasswordForm').on('submit', function(e) {
        e.preventDefault();
        submitChangePassword();
    });
});
</script>

<script>
document.addEventListener('click', function(e) {
    var btn = e.target && e.target.closest ? e.target.closest('.popup-menu-btn') : null;
    if (btn && typeof btn.blur === 'function') btn.blur();
});
</script>

<style>
/* Unified game search */
.header-search {
    position: relative;
}
.header-search .completer-holder {
    position: relative;
}
.header-search .completer-input {
    padding-right: 38px !important;
}
.mk-desktop-search-btn {
    position: absolute;
    right: 0;
    top: 0;
    width: 36px;
    height: 30px;
    border: 0;
    background: transparent;
    color: #bfff00;
    font-size: 22px;
    line-height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.mk-search-dropdown {
    display: none;
    position: absolute;
    top: 34px;
    right: 0;
    width: 375px;
    max-height: 425px;
    overflow-y: auto;
    background: #fff;
    color: #111;
    border: 1px solid #ddd;
    box-shadow: 0 8px 18px rgba(0,0,0,0.28);
    z-index: 10080;
}
.mk-search-dropdown.active {
    display: block;
}
.mk-search-title {
    padding: 12px 14px 8px;
    color: #333;
    font-size: 14px;
    font-weight: 500;
    border-bottom: 1px solid #333;
    margin: 0 10px 6px;
}
.mk-search-row {
    min-height: 38px;
    padding: 9px 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #111;
    font-size: 14px;
    cursor: pointer;
    background: #fff;
}
.mk-search-row:hover,
.mk-search-row:focus {
    background: #f2f2f2;
}
.mk-search-row-name {
    flex: 1 1 auto;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mk-search-recent-icon {
    color: #777;
    flex: 0 0 auto;
}
.mk-search-remove {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: #555;
    font-size: 18px;
    font-weight: 800;
    width: 28px;
    height: 28px;
    line-height: 1;
}
.mk-search-empty {
    padding: 12px 14px 16px;
    color: #777;
    font-size: 13px;
}

@media (max-width: 767px) {
    .search-container {
        display: none;
        position: fixed !important;
        top: 2px !important;
        left: 42px !important;
        right: 84px !important;
        width: auto !important;
        height: 36px !important;
        padding: 0 !important;
        background: transparent !important;
        z-index: 10070 !important;
        overflow: visible !important;
        visibility: visible !important;
        box-sizing: border-box !important;
        transform: none !important;
    }
    .search-container.active {
        display: block !important;
    }
    .search-input-wrapper {
        height: 36px !important;
        width: 100% !important;
        border: 1px solid #bfff00 !important;
        border-radius: 0 !important;
        background: #21172c !important;
        padding: 0 !important;
        display: flex !important;
        align-items: stretch !important;
        box-sizing: border-box !important;
    }
    .search-input {
        height: 34px !important;
        min-height: 34px !important;
        padding: 0 9px !important;
        font-size: 13px !important;
        color: #fff !important;
        background: #21172c !important;
    }
    .search-close-btn {
        flex: 0 0 58px !important;
        width: 58px !important;
        height: 34px !important;
        border: 0 !important;
        border-left: 1px solid #bfff00 !important;
        background: #bfff00 !important;
        color: #fff !important;
        font-size: 12px !important;
        font-weight: 800 !important;
        padding: 0 !important;
    }
    .search-results {
        display: none;
        position: absolute !important;
        top: 36px !important;
        left: 0 !important;
        right: auto !important;
        bottom: auto !important;
        width: 100% !important;
        max-height: 55vh !important;
        overflow-y: auto !important;
        background: #fff !important;
        color: #111 !important;
        margin: 0 !important;
        border: 1px solid #e0e0e0 !important;
        border-top: 0 !important;
        border-radius: 0 0 3px 3px !important;
        box-shadow: 0 8px 18px rgba(0,0,0,0.22) !important;
        z-index: 10071 !important;
    }
    .search-results.active {
        display: block !important;
    }
    .mk-search-title {
        padding: 12px 10px 8px;
        margin: 0 10px 6px;
        font-size: 13px;
    }
    .mk-search-row {
        min-height: 34px;
        padding: 8px 12px;
        font-size: 13px;
    }
}
</style>

<script>
(function () {
    var recentKey = 'mk_recent_game_searches_v1';
    var maxRecent = 8;
    var searchSeq = 0;
    var searchTimer = null;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getRecent() {
        try {
            var data = JSON.parse(localStorage.getItem(recentKey) || '[]');
            return Array.isArray(data) ? data.filter(function (g) { return g && g.game_id && g.name; }).slice(0, maxRecent) : [];
        } catch (e) {
            return [];
        }
    }

    function setRecent(items) {
        try { localStorage.setItem(recentKey, JSON.stringify(items.slice(0, maxRecent))); } catch (e) {}
    }

    function addRecent(game) {
        if (!game || !game.game_id || !game.name) return;
        var items = getRecent().filter(function (g) { return String(g.game_id) !== String(game.game_id); });
        items.unshift({
            game_id: String(game.game_id),
            name: String(game.name),
            provider: String(game.provider || ''),
            img: String(game.img || ''),
            url: String(game.url || ''),
            ts: Date.now()
        });
        setRecent(items);
    }

    function removeRecent(gameId) {
        setRecent(getRecent().filter(function (g) { return String(g.game_id) !== String(gameId); }));
    }

    function rowHtml(game, recent) {
        var icon = recent ? '<i class="fa fa-history mk-search-recent-icon"></i>' : '';
        var remove = recent ? '<button class="mk-search-remove" type="button" data-remove-game-id="' + esc(game.game_id) + '" aria-label="Remove">&times;</button>' : '';
        return '<div class="mk-search-row" tabindex="0" data-game-id="' + esc(game.game_id) + '" data-game-name="' + esc(game.name) + '" data-provider="' + esc(game.provider || '') + '" data-img="' + esc(game.img || '') + '" data-url="' + esc(game.url || '') + '">' +
            icon + '<span class="mk-search-row-name">' + esc(game.name) + '</span>' + remove + '</div>';
    }

    function renderRecent(box) {
        var items = getRecent();
        var html = '<div class="mk-search-title">Recent Search</div>';
        if (items.length) {
            html += items.map(function (g) { return rowHtml(g, true); }).join('');
        } else {
            html += '<div class="mk-search-empty">No recent search</div>';
        }
        box.innerHTML = html;
        showBox(box);
    }

    function renderResults(box, results) {
        if (!results || !results.length) {
            box.innerHTML = '<div class="mk-search-empty">No results found</div>';
        } else {
            box.innerHTML = results.map(function (g) { return rowHtml(g, false); }).join('');
        }
        showBox(box);
    }

    function showBox(box) {
        if (!box) return;
        box.classList.add('active');
        box.style.display = 'block';
    }

    function hideBox(box) {
        if (!box) return;
        box.classList.remove('active');
        box.style.display = 'none';
    }

    function searchGames(query, box) {
        query = String(query || '').trim();
        clearTimeout(searchTimer);
        if (!query) {
            renderRecent(box);
            return;
        }
        var seq = ++searchSeq;
        searchTimer = setTimeout(function () {
            var api = (typeof baseUrl !== 'undefined' ? baseUrl : '/') + 'api/search.php?q=' + encodeURIComponent(query);
            fetch(api, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    if (seq !== searchSeq) return;
                    renderResults(box, Array.isArray(data) ? data : []);
                })
                .catch(function () {
                    if (seq !== searchSeq) return;
                    box.innerHTML = '<div class="mk-search-empty">Error searching</div>';
                    showBox(box);
                });
        }, 180);
    }

    function launchSearchGame(row) {
        if (!row) return;
        var game = {
            game_id: row.getAttribute('data-game-id') || '',
            name: row.getAttribute('data-game-name') || '',
            provider: row.getAttribute('data-provider') || '',
            img: row.getAttribute('data-img') || '',
            url: row.getAttribute('data-url') || ''
        };
        if (!game.game_id) return;
        addRecent(game);
        closeSearch();
        if (typeof launchGame === 'function') {
            launchGame(game.game_id, game.name);
        } else if (game.url) {
            window.location.href = game.url;
        }
    }

    function ensureDesktopSearch() {
        var wrap = document.querySelector('.header-search');
        var input = wrap ? wrap.querySelector('.completer-input') : null;
        if (!wrap || !input) return null;
        var holder = wrap.querySelector('.completer-holder') || wrap;
        var btn = wrap.querySelector('.mk-desktop-search-btn');
        if (!btn) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mk-desktop-search-btn';
            btn.innerHTML = '<i class="fa fa-search"></i>';
            holder.appendChild(btn);
        }
        var box = wrap.querySelector('.mk-search-dropdown');
        if (!box) {
            box = document.createElement('div');
            box.className = 'mk-search-dropdown';
            wrap.appendChild(box);
        }
        if (!input.getAttribute('data-mk-search-bound')) {
            input.setAttribute('data-mk-search-bound', '1');
            input.addEventListener('focus', function () { searchGames(input.value, box); });
            input.addEventListener('input', function () { searchGames(input.value, box); });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hideBox(box);
                if (e.key === 'Enter') {
                    var first = box.querySelector('.mk-search-row');
                    if (first) {
                        e.preventDefault();
                        launchSearchGame(first);
                    }
                }
            });
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                input.focus();
                searchGames(input.value, box);
            });
        }
        return { input: input, box: box };
    }

    function ensureMobileSearch() {
        var container = document.getElementById('globalSearchContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'globalSearchContainer';
            container.className = 'search-container';
            document.body.appendChild(container);
        } else if (container.parentNode !== document.body && document.body) {
            document.body.appendChild(container);
        }
        container.innerHTML = '<div class="search-input-wrapper">' +
            '<input type="text" id="globalSearchInput" class="search-input" placeholder="Search by Event/Game" autocomplete="off">' +
            '<button class="search-close-btn" type="button">Close</button>' +
            '</div><div id="globalSearchResults" class="search-results"></div>';
        var input = container.querySelector('#globalSearchInput');
        var box = container.querySelector('#globalSearchResults');
        var close = container.querySelector('.search-close-btn');
        input.addEventListener('input', function () { searchGames(input.value, box); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSearch();
            if (e.key === 'Enter') {
                var first = box.querySelector('.mk-search-row');
                if (first) {
                    e.preventDefault();
                    launchSearchGame(first);
                }
            }
        });
        close.addEventListener('click', function (e) {
            e.preventDefault();
            closeSearch();
        });
        bindRowClicks(box);
        return { container: container, input: input, box: box };
    }

    function isMobile() {
        return window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
    }

    function openSearch() {
        if (isMobile()) {
            var mob = ensureMobileSearch();
            mob.container.classList.add('active');
            mob.container.style.display = 'block';
            mob.input.value = '';
            renderRecent(mob.box);
            setTimeout(function () { try { mob.input.focus(); } catch (e) {} }, 40);
            return;
        }
        var desk = ensureDesktopSearch();
        if (desk) {
            desk.input.focus();
            searchGames(desk.input.value, desk.box);
        }
    }

    function closeSearch() {
        var desk = ensureDesktopSearch();
        if (desk) hideBox(desk.box);
        var mob = document.getElementById('globalSearchContainer');
        if (mob) {
            mob.classList.remove('active');
            mob.style.display = 'none';
            var results = mob.querySelector('#globalSearchResults');
            if (results) hideBox(results);
        }
    }

    function bindRowClicks(root) {
        if (!root || root.getAttribute('data-mk-row-bound')) return;
        root.setAttribute('data-mk-row-bound', '1');
        root.addEventListener('click', function (e) {
            var remove = e.target && e.target.closest ? e.target.closest('[data-remove-game-id]') : null;
            if (remove) {
                e.preventDefault();
                e.stopPropagation();
                removeRecent(remove.getAttribute('data-remove-game-id'));
                renderRecent(root);
                return;
            }
            var row = e.target && e.target.closest ? e.target.closest('.mk-search-row') : null;
            if (row) launchSearchGame(row);
        });
        root.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            var row = e.target && e.target.closest ? e.target.closest('.mk-search-row') : null;
            if (row) {
                e.preventDefault();
                launchSearchGame(row);
            }
        });
    }

    function initSearch() {
        try {
            if (window.jQuery) {
                $(document).off('click', '.mgh-icon-search, .fa-search, .search-trigger');
                $(document).off('click', '.header-icon-btn');
            }
        } catch (e) {}

        var desk = ensureDesktopSearch();
        if (desk) bindRowClicks(desk.box);

        document.addEventListener('click', function (e) {
            var t = e.target;
            var searchIcon = t && t.closest ? t.closest('.header-icon-btn, .popup-header-right .fa-search, .mk-desktop-search-btn') : null;
            if (searchIcon) {
                if (searchIcon.classList.contains('header-icon-btn') && !searchIcon.querySelector('.fa-search')) return;
                e.preventDefault();
                e.stopPropagation();
                openSearch();
                return;
            }
            var desktopWrap = document.querySelector('.header-search');
            var mobileWrap = document.getElementById('globalSearchContainer');
            if (desktopWrap && desktopWrap.contains(t)) return;
            if (mobileWrap && mobileWrap.contains(t)) return;
            if (!isMobile()) {
                var d = ensureDesktopSearch();
                if (d) hideBox(d.box);
            }
        }, true);

        document.addEventListener('click', function () {
            var mob = document.getElementById('globalSearchResults');
            if (mob) bindRowClicks(mob);
            var d = document.querySelector('.mk-search-dropdown');
            if (d) bindRowClicks(d);
        }, true);
    }

    window.toggleSearch = function () {
        var mob = document.getElementById('globalSearchContainer');
        var active = mob && mob.classList.contains('active');
        if (isMobile() && active) closeSearch();
        else openSearch();
    };
    window.MK_OPEN_SEARCH = openSearch;
    window.MK_CLOSE_SEARCH = closeSearch;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearch);
    } else {
        initSearch();
    }
})();
</script>

<main id="mkApp" class="mk-app-shell">
