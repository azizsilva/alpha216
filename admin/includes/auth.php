<?php
require_once __DIR__ . '/session.php';

function admin_role_map() {
    return [
        'admin' => ['label' => 'Admin', 'panel' => 'admin', 'path' => '/admin/'],
        'partner' => ['label' => 'Partner', 'panel' => 'partner', 'path' => '/admin/'],
        'super_master' => ['label' => 'Super Admin', 'panel' => 'super-master', 'path' => '/admin/'],
        'master' => ['label' => 'Admin', 'panel' => 'master', 'path' => '/admin/'],
        'agent' => ['label' => 'Shop', 'panel' => 'agent', 'path' => '/admin/'],
        'player' => ['label' => 'Player', 'panel' => '', 'path' => '/'],
    ];
}

function admin_panel_roles() {
    return ['admin', 'partner', 'super_master', 'master', 'agent'];
}

function admin_role_label($role) {
    $roles = admin_role_map();
    return $roles[$role]['label'] ?? ucfirst(str_replace('_', ' ', (string)$role));
}

function admin_panel_role_from_request() {
    $path = strtolower((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));
    $first = trim(explode('/', trim($path, '/'))[0] ?? '', '/');
    if ($first === 'super-master') return 'super_master';
    if (in_array($first, ['admin', 'partner', 'master', 'agent'], true)) return $first;
    return '';
}

function admin_role_panel_path($role) {
    $roles = admin_role_map();
    return $roles[$role]['path'] ?? '/admin/';
}

function admin_child_role($role) {
    $chain = ['admin', 'partner', 'super_master', 'master', 'agent', 'player'];
    $i = array_search($role, $chain, true);
    return ($i !== false && isset($chain[$i + 1])) ? $chain[$i + 1] : '';
}

function admin_parent_role($role) {
    $chain = ['admin', 'partner', 'super_master', 'master', 'agent', 'player'];
    $i = array_search($role, $chain, true);
    return ($i !== false && $i > 0) ? $chain[$i - 1] : '';
}

function require_admin_login($admin_base = '') {
    if (!isset($_SESSION['user_id'])) {
        $r = $_SERVER['REQUEST_URI'] ?? '';
        $qs = $r !== '' ? ('?r=' . urlencode($r)) : '';
        header("Location: " . $admin_base . "index.php" . $qs);
        exit;
    }

    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, admin_panel_roles(), true)) {
        header("Location: " . admin_role_panel_path($role));
        exit;
    }
}

function current_admin_role() {
    return $_SESSION['role'] ?? '';
}

function current_admin_id() {
    return $_SESSION['user_id'] ?? 0;
}

function require_admin_role($allowed_roles, $admin_base = '') {
    $role = current_admin_role();
    if (!in_array($role, $allowed_roles, true)) {
        header("Location: " . $admin_base . "dashboard/");
        exit;
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}
