<?php

function safe_sort_dir($dir) {
    return strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
}

function safe_like($s) {
    $s = trim((string)$s);
    return $s;
}

function admin_display_password($row) {
    $plain = trim((string)($row['password_text'] ?? ''));
    if ($plain !== '') {
        return $plain;
    }
    return '';
}

function fetch_masters($pdo, $q = '', $sort = 'created_at', $dir = 'desc') {
    return fetch_role_children($pdo, 'master', null, $q, $sort, $dir);
}

function fetch_role_children($pdo, $role, $parent_id = null, $q = '', $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'rate', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    $sql = "SELECT u.*,
              (SELECT COUNT(*) FROM users c WHERE c.parent_id=u.id) AS children_count,
              (SELECT COUNT(*) FROM users a WHERE a.role='agent' AND a.parent_id=u.id) AS agents_count
            FROM users u
            WHERE u.role=?";
    $params = [$role];
    if ($parent_id !== null) {
        $sql .= " AND u.parent_id=?";
        $params[] = (int)$parent_id;
    }
    if ($q !== '') {
        $sql .= " AND (u.username LIKE ? OR CAST(u.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY $sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_agents_for_admin($pdo, $q = '', $master_id = null, $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'rate', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    $sql = "SELECT a.*, m.username AS master_name,
              (SELECT COUNT(*) FROM users p WHERE p.role='player' AND p.parent_id=a.id) AS players_count
            FROM users a
            LEFT JOIN users m ON m.id = a.parent_id AND m.role='master'
            WHERE a.role='agent'";
    $params = [];
    if ($master_id) {
        $sql .= " AND a.parent_id = ?";
        $params[] = (int)$master_id;
    }
    if ($q !== '') {
        $sql .= " AND (a.username LIKE ? OR CAST(a.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY a.$sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_agents_for_master($pdo, $master_id, $q = '', $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'rate', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    $sql = "SELECT a.*,
              (SELECT COUNT(*) FROM users p WHERE p.role='player' AND p.parent_id=a.id) AS players_count
            FROM users a
            WHERE a.role='agent' AND a.parent_id = ?";
    $params = [(int)$master_id];
    if ($q !== '') {
        $sql .= " AND (a.username LIKE ? OR CAST(a.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY a.$sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function member_downline_sql_for_role($session_role, $target_role, $user_id, $alias = 'u') {
    $alias = preg_replace('/[^a-z0-9_]/i', '', (string)$alias) ?: 'u';
    $session_role = (string)$session_role;
    $target_role = (string)$target_role;
    $user_id = (int)$user_id;

    if ($session_role === 'partner') {
        if ($target_role === 'super_master') {
            return [$alias . ".parent_id = ?", [$user_id]];
        }
        if ($target_role === 'master') {
            return [$alias . ".parent_id IN (SELECT sm.id FROM users sm WHERE sm.role='super_master' AND sm.parent_id = ?)", [$user_id]];
        }
    }

    if ($session_role === 'super_master' && $target_role === 'master') {
        return [$alias . ".parent_id = ?", [$user_id]];
    }

    return ['1=0', []];
}

function fetch_role_downline($pdo, $session_role, $user_id, $target_role, $parent_id = null, $q = '', $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'rate', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    [$downlineSql, $params] = member_downline_sql_for_role($session_role, $target_role, $user_id, 'u');
    $sql = "SELECT u.*,
              (SELECT COUNT(*) FROM users c WHERE c.parent_id=u.id) AS children_count,
              (SELECT COUNT(*) FROM users a WHERE a.role='agent' AND a.parent_id=u.id) AS agents_count
            FROM users u
            WHERE u.role=? AND $downlineSql";
    array_unshift($params, $target_role);

    if ($parent_id !== null) {
        $sql .= " AND u.parent_id=?";
        $params[] = (int)$parent_id;
    }
    if ($q !== '') {
        $sql .= " AND (u.username LIKE ? OR CAST(u.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY u.$sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function agent_downline_sql_for_role($role, $user_id, $alias = 'a') {
    $alias = preg_replace('/[^a-z0-9_]/i', '', (string)$alias) ?: 'a';
    if ($role === 'master') {
        return [$alias . ".parent_id = ?", [(int)$user_id]];
    }
    if ($role === 'super_master') {
        return [$alias . ".parent_id IN (SELECT m.id FROM users m WHERE m.role='master' AND m.parent_id = ?)", [(int)$user_id]];
    }
    if ($role === 'partner') {
        return [$alias . ".parent_id IN (
            SELECT m.id
            FROM users m
            JOIN users sm ON sm.id = m.parent_id AND sm.role='super_master'
            WHERE m.role='master' AND sm.parent_id = ?
        )", [(int)$user_id]];
    }
    return ['1=0', []];
}

function player_downline_sql_for_role($role, $user_id, $alias = 'p') {
    $alias = preg_replace('/[^a-z0-9_]/i', '', (string)$alias) ?: 'p';
    if ($role === 'agent') {
        return [$alias . ".parent_id = ?", [(int)$user_id]];
    }
    if ($role === 'master') {
        return [$alias . ".parent_id IN (SELECT a.id FROM users a WHERE a.role='agent' AND a.parent_id = ?)", [(int)$user_id]];
    }
    if ($role === 'super_master') {
        return [$alias . ".parent_id IN (
            SELECT a.id
            FROM users a
            JOIN users m ON m.id = a.parent_id AND m.role='master'
            WHERE a.role='agent' AND m.parent_id = ?
        )", [(int)$user_id]];
    }
    if ($role === 'partner') {
        return [$alias . ".parent_id IN (
            SELECT a.id
            FROM users a
            JOIN users m ON m.id = a.parent_id AND m.role='master'
            JOIN users sm ON sm.id = m.parent_id AND sm.role='super_master'
            WHERE a.role='agent' AND sm.parent_id = ?
        )", [(int)$user_id]];
    }
    return ['1=0', []];
}

function fetch_agents_for_downline($pdo, $role, $user_id, $q = '', $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'rate', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    [$downlineSql, $params] = agent_downline_sql_for_role($role, $user_id, 'a');
    $sql = "SELECT a.*, m.username AS master_name,
              (SELECT COUNT(*) FROM users p WHERE p.role='player' AND p.parent_id=a.id) AS players_count
            FROM users a
            LEFT JOIN users m ON m.id = a.parent_id AND m.role='master'
            WHERE a.role='agent' AND $downlineSql";
    if ($q !== '') {
        $sql .= " AND (a.username LIKE ? OR CAST(a.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY a.$sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_players_for_admin($pdo, $q = '', $master_id = null, $agent_id = null, $status = '', $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'balance', 'exposure', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    $sql = "SELECT p.*, a.username AS agent_name, a.id AS agent_id, m.username AS master_name, m.id AS master_id
            FROM users p
            LEFT JOIN users a ON a.id = p.parent_id AND a.role='agent'
            LEFT JOIN users m ON m.id = a.parent_id AND m.role='master'
            WHERE p.role='player'";
    $params = [];
    if ($master_id) {
        $sql .= " AND m.id = ?";
        $params[] = (int)$master_id;
    }
    if ($agent_id) {
        $sql .= " AND a.id = ?";
        $params[] = (int)$agent_id;
    }
    if ($status !== '') {
        $sql .= " AND p.status = ?";
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= " AND (p.username LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY p.$sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_players_for_master($pdo, $master_id, $q = '', $agent_id = null, $status = '', $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'balance', 'exposure', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    $sql = "SELECT p.*, a.username AS agent_name, a.id AS agent_id
            FROM users p
            JOIN users a ON a.id = p.parent_id AND a.role='agent'
            WHERE p.role='player' AND a.parent_id = ?";
    $params = [(int)$master_id];
    if ($agent_id) {
        $sql .= " AND a.id = ?";
        $params[] = (int)$agent_id;
    }
    if ($status !== '') {
        $sql .= " AND p.status = ?";
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= " AND (p.username LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY p.$sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_players_for_agent($pdo, $agent_id, $q = '', $status = '', $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'balance', 'exposure', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    $sql = "SELECT p.*
            FROM users p
            WHERE p.role='player' AND p.parent_id = ?";
    $params = [(int)$agent_id];
    if ($status !== '') {
        $sql .= " AND p.status = ?";
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= " AND (p.username LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY p.$sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_players_for_downline($pdo, $role, $user_id, $q = '', $agent_id = null, $status = '', $sort = 'created_at', $dir = 'desc') {
    $allowed = ['id', 'username', 'balance', 'exposure', 'credit_ref', 'status', 'created_at'];
    if (!in_array($sort, $allowed, true)) $sort = 'created_at';
    $dirSql = safe_sort_dir($dir);
    $q = safe_like($q);

    [$downlineSql, $params] = player_downline_sql_for_role($role, $user_id, 'p');
    $sql = "SELECT p.*, a.username AS agent_name, a.id AS agent_id, m.username AS master_name, m.id AS master_id
            FROM users p
            LEFT JOIN users a ON a.id = p.parent_id AND a.role='agent'
            LEFT JOIN users m ON m.id = a.parent_id AND m.role='master'
            WHERE p.role='player' AND $downlineSql";
    if ($agent_id) {
        $sql .= " AND a.id = ?";
        $params[] = (int)$agent_id;
    }
    if ($status !== '') {
        $sql .= " AND p.status = ?";
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= " AND (p.username LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY p.$sort $dirSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

