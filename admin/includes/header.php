<?php
require_once __DIR__ . '/session.php';
if (!isset($admin_base)) {
    $admin_base = '';
}

function admin_active($needle) {
    $needle = trim((string)$needle);
    $needle_parts = parse_url($needle);
    $needle_path = '/' . trim((string)($needle_parts['path'] ?? $needle), '/');
    if ($needle === '/') {
        return false;
    }

    $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    $path = '/' . trim($path, '/');
    $path_match = strcasecmp($path, $needle_path) === 0 || substr_compare($path, $needle_path, -strlen($needle_path), strlen($needle_path), true) === 0;
    if (!$path_match) {
        return false;
    }

    if (!isset($needle_parts['query'])) {
        return true;
    }

    parse_str((string)$needle_parts['query'], $expected_query);
    $actual_query = $_GET ?? [];
    foreach ($expected_query as $key => $value) {
        if (!array_key_exists($key, $actual_query) || (string)$actual_query[$key] !== (string)$value) {
            return false;
        }
    }
    return true;
}

function admin_svg_icon($name, $class = '') {
    $key = strtolower((string)$name);
    $attrs = 'class="ta-svg-icon' . ($class !== '' ? ' ' . htmlspecialchars($class) : '') . '" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"';
    if (strpos($key, 'dashboard') !== false) {
        return '<svg ' . $attrs . '><path d="M5.5 3.25h3.5A2.25 2.25 0 0 1 11.25 5.5V9A2.25 2.25 0 0 1 9 11.25H5.5A2.25 2.25 0 0 1 3.25 9V5.5A2.25 2.25 0 0 1 5.5 3.25ZM15 3.25h3.5a2.25 2.25 0 0 1 2.25 2.25V9a2.25 2.25 0 0 1-2.25 2.25H15A2.25 2.25 0 0 1 12.75 9V5.5A2.25 2.25 0 0 1 15 3.25ZM5.5 12.75h3.5A2.25 2.25 0 0 1 11.25 15v3.5A2.25 2.25 0 0 1 9 20.75H5.5a2.25 2.25 0 0 1-2.25-2.25V15a2.25 2.25 0 0 1 2.25-2.25ZM15 12.75h3.5A2.25 2.25 0 0 1 20.75 15v3.5a2.25 2.25 0 0 1-2.25 2.25H15a2.25 2.25 0 0 1-2.25-2.25V15A2.25 2.25 0 0 1 15 12.75Z" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    if (strpos($key, 'user') !== false || strpos($key, 'team') !== false || strpos($key, 'agent') !== false || strpos($key, 'master') !== false) {
        return '<svg ' . $attrs . '><path d="M12 12.25a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.75 20.25a7.25 7.25 0 0 1 14.5 0M18.5 11.25a3 3 0 0 0 0-6M20.75 19.25a5 5 0 0 0-3.2-4.66" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
    }
    if (strpos($key, 'bank') !== false || strpos($key, 'wallet') !== false || strpos($key, 'payment') !== false || strpos($key, 'qr') !== false || strpos($key, 'exchange') !== false) {
        return '<svg ' . $attrs . '><path d="M3.75 9.25h16.5M5.25 9.25v8.5M9.75 9.25v8.5M14.25 9.25v8.5M18.75 9.25v8.5M3.75 18.75h16.5M4.75 7.25 12 3.25l7.25 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if (strpos($key, 'chart') !== false || strpos($key, 'report') !== false || strpos($key, 'file') !== false || strpos($key, 'list') !== false) {
        return '<svg ' . $attrs . '><path d="M7.25 17.25V12.5M12 17.25V6.75M16.75 17.25v-8M4.75 20.25h14.5a1.5 1.5 0 0 0 1.5-1.5V5.25a1.5 1.5 0 0 0-1.5-1.5H4.75a1.5 1.5 0 0 0-1.5 1.5v13.5a1.5 1.5 0 0 0 1.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
    }
    if (strpos($key, 'settings') !== false) {
        return '<svg ' . $attrs . '><path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M19.3 13.7a7.6 7.6 0 0 0 .05-3.4l2-1.52-2-3.46-2.42.98a7.8 7.8 0 0 0-2.93-1.7L13.65 2h-4l-.35 2.6a7.8 7.8 0 0 0-2.93 1.7l-2.42-.98-2 3.46 2 1.52a7.6 7.6 0 0 0 .05 3.4l-2 1.52 2 3.46 2.42-.98a7.8 7.8 0 0 0 2.93 1.7l.35 2.6h4l.35-2.6a7.8 7.8 0 0 0 2.93-1.7l2.42.98 2-3.46-2-1.52Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';
    }
    if (strpos($key, 'gift') !== false || strpos($key, 'medal') !== false) {
        return '<svg ' . $attrs . '><path d="M4.75 10.25h14.5v9a1.5 1.5 0 0 1-1.5 1.5H6.25a1.5 1.5 0 0 1-1.5-1.5v-9ZM3.75 6.75h16.5v3.5H3.75v-3.5ZM12 6.75v14M12 6.75C10.5 3.75 7.25 3.5 7.25 5.5c0 1.25 1.25 1.25 4.75 1.25ZM12 6.75c1.5-3 4.75-3.25 4.75-1.25 0 1.25-1.25 1.25-4.75 1.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    return '<svg ' . $attrs . '><path d="M12 3.25 20.25 8v8L12 20.75 3.75 16V8L12 3.25Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M12 12 20 7.5M12 12 4 7.5M12 12v8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
}

function admin_side_link($admin_base, $path, $label, $icon, $needles = [], $badge = '') {
    if (empty($needles)) {
        $needles = ['/' . trim($path, '/')];
    }
    $active = false;
    foreach ($needles as $needle) {
        if ($needle !== '' && admin_active($needle)) {
            $active = true;
            break;
        }
    }
    ?>
    <a class="ta-menu-link <?php echo $active ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($admin_base . ltrim($path, '/')); ?>">
      <span class="ta-menu-icon"><?php echo admin_svg_icon($icon); ?></span>
      <span class="ta-menu-text"><?php echo htmlspecialchars($label); ?></span>
      <?php if ($badge !== ''): ?><span class="ta-menu-badge"><?php echo htmlspecialchars($badge); ?></span><?php endif; ?>
    </a>
    <?php
}

function admin_menu_item_active($item) {
    $needles = $item[3] ?? [];
    if (empty($needles)) {
        $needles = ['/' . trim((string)($item[0] ?? ''), '/')];
    }
    foreach ($needles as $needle) {
        if ($needle !== '' && admin_active($needle)) {
            return true;
        }
    }
    return false;
}

function admin_side_submenu($admin_base, $label, $icon, $items) {
    $open = false;
    foreach ($items as $item) {
        if (admin_menu_item_active($item)) {
            $open = true;
            break;
        }
    }
    ?>
    <div class="ta-menu-subtree <?php echo $open ? 'open' : ''; ?>">
      <button class="ta-menu-link ta-menu-toggle" type="button" data-ta-submenu-toggle aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
        <span class="ta-menu-icon"><?php echo admin_svg_icon($icon); ?></span>
        <span class="ta-menu-text"><?php echo htmlspecialchars($label); ?></span>
        <svg class="ta-submenu-chevron" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M4.792 7.396 10 12.604l5.208-5.208" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="ta-submenu">
        <?php foreach ($items as $item): ?>
          <?php $active = admin_menu_item_active($item); ?>
          <a class="ta-submenu-link <?php echo $active ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($admin_base . ltrim($item[0], '/')); ?>">
            <span><?php echo htmlspecialchars($item[1]); ?></span>
            <?php if (($item[4] ?? '') !== ''): ?><small><?php echo htmlspecialchars($item[4]); ?></small><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}

function admin_skeleton_profile() {
    $path = strtolower((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));

    if (strpos($path, '/dashboard') !== false) {
        return 'dashboard';
    }

    foreach ([
        '/payments',
        '/deposit-history',
        '/withdraw-requests',
        '/balance-requests',
        '/transactions',
        '/bet-list',
        '/reports',
        '/downline-search',
        '/risk-management',
    ] as $needle) {
        if (strpos($path, $needle) !== false) {
            return 'form-table';
        }
    }

    foreach ([
        '/create-member',
        '/banking',
        '/deposit',
        '/my-account',
        '/payment-modes',
        '/system-settings',
    ] as $needle) {
        if (strpos($path, $needle) !== false) {
            return 'form';
        }
    }

    foreach ([
        '/masters',
        '/agents',
        '/players',
        '/hierarchy',
    ] as $needle) {
        if (strpos($path, $needle) !== false) {
            return 'cards-table';
        }
    }

    return 'table';
}

function admin_render_skeleton_panel($profile) {
    ?>
    <div id="taSkeleton" class="ta-skeleton-screen ta-skeleton-<?php echo htmlspecialchars($profile); ?>-mode" data-ta-skeleton-profile="<?php echo htmlspecialchars($profile); ?>" aria-hidden="true">
      <div class="ta-skeleton-heading">
        <span></span>
        <strong></strong>
      </div>

      <?php if ($profile === 'dashboard' || $profile === 'cards-table'): ?>
        <div class="ta-skeleton-grid">
          <div class="ta-skeleton-card"><i></i><span></span><strong></strong></div>
          <div class="ta-skeleton-card"><i></i><span></span><strong></strong></div>
        </div>
      <?php endif; ?>

      <?php if ($profile === 'dashboard'): ?>
        <div class="ta-skeleton-panel">
          <span></span>
          <div class="ta-skeleton-bars">
            <?php for ($__ta_i = 0; $__ta_i < 12; $__ta_i++): ?>
              <i style="height: <?php echo 26 + (($__ta_i * 17) % 58); ?>%;"></i>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($profile === 'form' || $profile === 'form-table'): ?>
        <div class="ta-skeleton-form-panel">
          <div class="ta-skeleton-form-head">
            <div class="ta-skeleton-form-copy">
              <strong></strong>
              <span></span>
            </div>
            <i class="ta-skeleton-form-back"></i>
          </div>
          <div class="ta-skeleton-form-body">
            <?php foreach ([100, 82, 78, 108, 118, 136] as $__ta_w): ?>
              <div class="ta-skeleton-field">
                <label style="width: <?php echo (int)$__ta_w; ?>px;"></label>
                <i></i>
              </div>
            <?php endforeach; ?>
            <div class="ta-skeleton-actions">
              <span class="ta-skeleton-action primary"></span>
              <span class="ta-skeleton-action"></span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($profile === 'table' || $profile === 'form-table' || $profile === 'cards-table' || $profile === 'dashboard'): ?>
        <div class="ta-skeleton-table">
          <?php for ($__ta_i = 0; $__ta_i < 7; $__ta_i++): ?>
            <span></span>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

$main_base = $admin_base . '../';
$materio_base = $admin_base . 'assets/materio/';
$materio_assets = $materio_base . 'assets/';
$admin_username = $_SESSION['username'] ?? 'Admin';
$admin_role = $_SESSION['role'] ?? 'admin';
$admin_initials = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $admin_username) ?: 'A', 0, 2));
$admin_logo_url = 'https://tanitbet216.com/tanitbet216.png';
$page_title = $page_title ?? 'Admin';
$admin_skeleton_profile = admin_skeleton_profile();

$child_role = function_exists('admin_child_role') ? admin_child_role($admin_role) : '';
$child_label = function_exists('admin_role_label') ? admin_role_label($child_role) : ucfirst((string)$child_role);
$ops_menu = [
    ['dashboard/', 'Dashboard', 'ri-dashboard-line', ['/dashboard']],
    ['create-member/', 'Create Member', 'ri-user-add-line', ['/create-member']],
];
if ($admin_role === 'admin') {
    $ops_menu[] = ['masters/?role=partner', 'Partners', 'ri-user-star-line', ['/masters?role=partner']];
    $ops_menu[] = ['masters/?role=super_master', 'Super Masters', 'ri-user-star-line', ['/masters?role=super_master']];
    $ops_menu[] = ['masters/?role=master', 'Masters', 'ri-user-star-line', ['/masters?role=master']];
    $ops_menu[] = ['agents/', 'Agents', 'ri-user-settings-line', ['/agents']];
    $ops_menu[] = ['players/', 'Players', 'ri-team-line', ['/players']];
} elseif ($admin_role === 'partner') {
    $ops_menu[] = ['masters/?role=super_master', 'Super Masters', 'ri-user-star-line', ['/masters?role=super_master']];
    $ops_menu[] = ['masters/?role=master', 'Masters', 'ri-user-star-line', ['/masters?role=master']];
    $ops_menu[] = ['agents/', 'Agents', 'ri-user-settings-line', ['/agents']];
    $ops_menu[] = ['players/', 'Players', 'ri-team-line', ['/players']];
} elseif ($admin_role === 'super_master') {
    $ops_menu[] = ['masters/?role=master', 'Masters', 'ri-user-star-line', ['/masters?role=master']];
    $ops_menu[] = ['agents/', 'Agents', 'ri-user-settings-line', ['/agents']];
    $ops_menu[] = ['players/', 'Players', 'ri-team-line', ['/players']];
} elseif ($admin_role === 'master') {
    $ops_menu[] = ['agents/', 'Agents', 'ri-user-settings-line', ['/agents']];
    $ops_menu[] = ['players/', 'Players', 'ri-team-line', ['/players']];
} elseif ($admin_role === 'agent') {
    $ops_menu[] = ['players/', 'Players', 'ri-team-line', ['/players']];
}

$finance_menu = [
    ['banking/', 'Banking', 'ri-bank-line', ['/banking']],
    ['deposit/', 'Deposit Methods', 'ri-qr-code-line', ['/deposit']],
    ['payments/', 'Payments', 'ri-wallet-3-line', ['/payments']],
    ['deposit-history/', 'Deposit History', 'ri-download-cloud-2-line', ['/deposit-history']],
    ['withdraw-requests/', 'Withdraw Requests', 'ri-upload-cloud-2-line', ['/withdraw-requests']],
    ['balance-requests/', 'Balance Requests', 'ri-exchange-dollar-line', ['/balance-requests']],
    ['transactions/', 'Transactions', 'ri-arrow-left-right-line', ['/transactions']],
];

$reports_menu = [
    ['bet-list/', 'Bet List', 'ri-file-list-3-line', ['/bet-list']],
    ['reports/', 'Reports', 'ri-bar-chart-2-line', ['/reports']],
    ['reports/profit-loss/', 'Profit/Loss', 'ri-line-chart-line', ['/reports/profit-loss']],
    ['reports/players/', 'Players Report', 'ri-user-search-line', ['/reports/players']],
];
if ($admin_role === 'admin') {
    $reports_menu[] = ['reports/bonus/', 'Bonus Report', 'ri-gift-line', ['/reports/bonus'], 'NEW'];
    $reports_menu[] = ['reports/loyalty/', 'Loyalty Report', 'ri-medal-line', ['/reports/loyalty']];
    $reports_menu[] = ['reports/settlements/', 'Settlement Report', 'ri-secure-payment-line', ['/reports/settlements']];
}

$settings_menu = [
    ['my-account/', 'My Account', 'ri-user-line', ['/my-account']],
];
if ($admin_role === 'admin') {
    $settings_menu[] = ['payment-modes/', 'Payment Modes', 'ri-bank-card-line', ['/payment-modes']];
    $settings_menu[] = ['system-settings/', 'System Settings', 'ri-settings-4-line', ['/system-settings']];
}
?>
<!doctype html>
<html lang="en" class="ta-html">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($page_title); ?> | Tanit Admin</title>
    <link rel="icon" type="image/x-icon" href="https://tanitbet216.com/tanitbet.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet" />
    <?php $admin_ui_css_v = @filemtime(__DIR__ . '/admin-ui.css') ?: time(); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($admin_base); ?>includes/admin-ui.css?v=<?php echo (int)$admin_ui_css_v; ?>" />
  </head>
  <body class="ta-body ta-loading">
    <div class="ta-app" id="mkAdminShell">
      <aside class="ta-sidebar" id="mkAdminSidebar">
        <div class="ta-sidebar-head">
          <button class="ta-head-icon ta-sidebar-close" type="button" data-mk-sidebar-toggle aria-label="Close menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M6.22 7.28 10.94 12l-4.72 4.72M17.78 7.28 13.06 12l4.72 4.72" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </button>
          <a class="ta-brand" href="<?php echo htmlspecialchars($admin_base); ?>dashboard/">
            <img class="ta-brand-logo" src="<?php echo htmlspecialchars($admin_logo_url); ?>" alt="TanitAdmin">
          </a>
        </div>

        <nav class="ta-menu">
          <div class="ta-menu-group">
            <div class="ta-menu-title">Menu</div>
            <?php admin_side_link($admin_base, 'dashboard/', 'Dashboard', 'ri-dashboard-line', ['/dashboard']); ?>
            <?php admin_side_submenu($admin_base, 'Members', 'ri-team-line', array_values(array_filter($ops_menu, function ($item) { return ($item[0] ?? '') !== 'dashboard/'; }))); ?>
            <?php admin_side_submenu($admin_base, 'Finance', 'ri-wallet-3-line', $finance_menu); ?>
          </div>

          <div class="ta-menu-group">
            <div class="ta-menu-title">Reports</div>
            <?php admin_side_submenu($admin_base, 'Reports', 'ri-bar-chart-2-line', $reports_menu); ?>
          </div>

          <div class="ta-menu-group">
            <div class="ta-menu-title">Others</div>
            <?php admin_side_submenu($admin_base, 'Settings', 'ri-settings-4-line', $settings_menu); ?>
          </div>
        </nav>
      </aside>

      <div class="ta-sidebar-overlay" data-mk-sidebar-toggle></div>

      <section class="ta-main">
        <header class="ta-topbar">
          <div class="ta-top-left">
            <button class="ta-head-icon ta-sidebar-trigger" type="button" data-mk-sidebar-toggle aria-label="Open menu">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M4 12h10M4 17h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            </button>
          </div>
          <a class="ta-brand ta-top-brand" href="<?php echo htmlspecialchars($admin_base); ?>dashboard/">
            <img class="ta-brand-logo" src="<?php echo htmlspecialchars($admin_logo_url); ?>" alt="TanitAdmin">
          </a>
          <div class="ta-top-actions">
            <div class="dropdown">
              <button class="ta-profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Admin menu">
                <span class="ta-avatar-initials"><?php echo htmlspecialchars($admin_initials); ?></span>
                <span><?php echo htmlspecialchars($admin_username); ?></span>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M4.792 7.396 10 12.604l5.208-5.208" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </button>
              <ul class="dropdown-menu dropdown-menu-end ta-user-menu">
                <li class="ta-user-info">
                  <span class="ta-avatar-initials"><?php echo htmlspecialchars($admin_initials); ?></span>
                  <span><strong><?php echo htmlspecialchars($admin_username); ?></strong><small><?php echo htmlspecialchars(strtoupper(admin_role_label($admin_role))); ?></small></span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($admin_base); ?>my-account/">My Account</a></li>
                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($main_base); ?>" target="_blank" rel="noopener">Open Website</a></li>
                <li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars($admin_base); ?>logout.php">Logout</a></li>
              </ul>
            </div>
            <div class="dropdown ta-mobile-actions">
              <button class="ta-head-icon ta-mobile-more" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M6 12h.01M12 12h.01M18 12h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
              </button>
              <ul class="dropdown-menu dropdown-menu-end ta-user-menu">
                <li class="ta-user-info">
                  <span class="ta-avatar-initials"><?php echo htmlspecialchars($admin_initials); ?></span>
                  <span><strong><?php echo htmlspecialchars($admin_username); ?></strong><small><?php echo htmlspecialchars(strtoupper(admin_role_label($admin_role))); ?></small></span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($admin_base); ?>my-account/">My Account</a></li>
                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($main_base); ?>" target="_blank" rel="noopener">Open Website</a></li>
                <li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars($admin_base); ?>logout.php">Logout</a></li>
              </ul>
            </div>
          </div>
        </header>

        <?php admin_render_skeleton_panel($admin_skeleton_profile); ?>

        <main class="ta-content">
          <div class="ta-page-heading">
            <div>
              <p>Control Panel</p>
              <h1><?php echo htmlspecialchars($page_title); ?></h1>
            </div>
            <a class="ta-site-link" href="<?php echo htmlspecialchars($main_base); ?>" target="_blank" rel="noopener">
              <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M11.667 3.333h5v5M16.667 3.333 9.167 10.833M8.333 5H5a1.667 1.667 0 0 0-1.667 1.667V15A1.667 1.667 0 0 0 5 16.667h8.333A1.667 1.667 0 0 0 15 15v-3.333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span>Open Site</span>
            </a>
          </div>
