<?php

function audit_log($pdo, $action, $entity_type, $entity_id = null, $old = null, $new = null) {
    try {
        $actor_id = (int)($_SESSION['user_id'] ?? 0);
        $actor_role = (string)($_SESSION['role'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $old_json = $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $new_json = $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("INSERT INTO audit_logs (actor_id, actor_role, action, entity_type, entity_id, ip, user_agent, old_json, new_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$actor_id, $actor_role, $action, $entity_type, $entity_id ? (string)$entity_id : null, $ip, $ua, $old_json, $new_json]);
    } catch (Exception $e) {
    }
}

