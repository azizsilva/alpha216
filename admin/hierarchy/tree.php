<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';

require_admin_login($admin_base);
require_admin_role(['admin', 'master', 'agent'], $admin_base);

header('Content-Type: application/json');

function out($payload) {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$role = $_GET['role'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

$my_role = current_admin_role();
$my_id = (int)current_admin_id();

try {
    if ($my_role !== 'admin') {
        if ($my_role === 'master') {
            if ($role === 'admin') out(['success' => false]);
            if ($role === 'master' && $id !== $my_id) out(['success' => false]);
            if ($role === 'agent') {
                $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'agent' AND parent_id = ? LIMIT 1");
                $stmt->execute([$id, $my_id]);
                if (!$stmt->fetchColumn()) out(['success' => false]);
            }
        } elseif ($my_role === 'agent') {
            if ($role !== 'agent' || $id !== $my_id) out(['success' => false]);
        } else {
            out(['success' => false]);
        }
    }

    if ($role === 'admin') {
        $sql = "SELECT u.id, u.username, u.role, u.status, u.balance, u.exposure,
                  (SELECT COUNT(*) FROM users a WHERE a.role='agent' AND a.parent_id=u.id) AS children_count
                FROM users u
                WHERE u.role='master'";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (u.username LIKE ? OR CAST(u.id AS CHAR) LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sql .= " ORDER BY u.id DESC LIMIT 500";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        out(['success' => true, 'nodes' => $stmt->fetchAll()]);
    }

    if ($role === 'master') {
        if ($id <= 0) out(['success' => true, 'nodes' => []]);
        $sql = "SELECT a.id, a.username, a.role, a.status, a.balance, a.exposure,
                  (SELECT COUNT(*) FROM users p WHERE p.role='player' AND p.parent_id=a.id) AS children_count
                FROM users a
                WHERE a.role='agent' AND a.parent_id = ?";
        $params = [$id];
        if ($q !== '') {
            $sql .= " AND (a.username LIKE ? OR CAST(a.id AS CHAR) LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sql .= " ORDER BY a.id DESC LIMIT 1000";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        out(['success' => true, 'nodes' => $stmt->fetchAll()]);
    }

    if ($role === 'agent') {
        if ($id <= 0) out(['success' => true, 'nodes' => []]);
        $sql = "SELECT p.id, p.username, p.role, p.status, p.balance, p.exposure,
                  0 AS children_count
                FROM users p
                WHERE p.role='player' AND p.parent_id = ?";
        $params = [$id];
        if ($q !== '') {
            $sql .= " AND (p.username LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sql .= " ORDER BY p.id DESC LIMIT 5000";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        out(['success' => true, 'nodes' => $stmt->fetchAll()]);
    }

    out(['success' => false]);
} catch (Exception $e) {
    out(['success' => false, 'message' => 'Database Error']);
}

