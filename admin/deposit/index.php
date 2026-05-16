<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';
require '../includes/audit.php';
require '../includes/upload.php';

require_admin_login($admin_base);

$role = (string)current_admin_role();
if (!in_array($role, ['admin','master','agent'], true)) {
    header("Location: " . $admin_base . "dashboard/");
    exit;
}

$page_title = 'Deposit Methods';
require '../includes/header.php';

$my_id = (int)current_admin_id();

function json_assoc($text) {
    $t = trim((string)$text);
    if ($t === '') return [];
    $d = json_decode($t, true);
    return is_array($d) ? $d : [];
}

function build_details_json($channel, $post, $existing_assoc = [], $qr_path = null) {
    $channel = (string)$channel;
    $details = [];
    $notes = trim((string)($post['notes'] ?? ''));
    if ($notes !== '') $details['notes'] = $notes;

    if ($channel === 'upi') {
        $upi_id = trim((string)($post['upi_id'] ?? ''));
        $holder = trim((string)($post['holder_name'] ?? ''));
        $upi_bank_name = trim((string)($post['upi_bank_name'] ?? ''));
        if ($upi_id !== '') $details['upi_id'] = $upi_id;
        if ($holder !== '') $details['holder_name'] = $holder;
        if ($upi_bank_name !== '') $details['bank_name'] = $upi_bank_name;
        if (is_string($qr_path) && trim($qr_path) !== '') $details['qr_path'] = trim($qr_path);
        elseif (is_array($existing_assoc) && !empty($existing_assoc['qr_path'])) $details['qr_path'] = (string)$existing_assoc['qr_path'];
        elseif (is_array($existing_assoc) && !empty($existing_assoc['qr_url'])) $details['qr_url'] = (string)$existing_assoc['qr_url'];
    } elseif ($channel === 'bank') {
        $details_map = [
            'bank_name' => 'bank_name',
            'branch' => 'branch',
            'account_name' => 'account_name',
            'account_number' => 'account_number',
            'ifsc' => 'ifsc',
            'swift' => 'swift',
            'iban' => 'iban'
        ];
        foreach ($details_map as $k => $src) {
            $v = trim((string)($post[$src] ?? ''));
            if ($v !== '') $details[$k] = $v;
        }
    } elseif ($channel === 'wallet') {
        $wallet_kind = trim((string)($post['wallet_kind'] ?? 'wallet'));
        $wallet_network = trim((string)($post['wallet_network'] ?? ''));
        $wallet_name = trim((string)($post['wallet_name'] ?? ''));
        if ($wallet_kind === 'usdt') {
            if (!in_array($wallet_network, ['TRC20','BEP20'], true)) $wallet_network = 'TRC20';
            $wallet_name = 'USDT (' . $wallet_network . ')';
            $details['wallet_kind'] = 'usdt';
            $details['wallet_network'] = $wallet_network;
        } else {
            $details['wallet_kind'] = 'wallet';
            if ($wallet_name !== '') $details['wallet_name'] = $wallet_name;
        }

        $wallet_number = trim((string)($post['wallet_number'] ?? ''));
        if ($wallet_name !== '') $details['wallet_name'] = $wallet_name;
        if ($wallet_number !== '') $details['wallet_number'] = $wallet_number;

        if (is_string($qr_path) && trim($qr_path) !== '') $details['qr_path'] = trim($qr_path);
        elseif (is_array($existing_assoc) && !empty($existing_assoc['qr_path'])) $details['qr_path'] = (string)$existing_assoc['qr_path'];
        elseif (is_array($existing_assoc) && !empty($existing_assoc['qr_url'])) $details['qr_url'] = (string)$existing_assoc['qr_url'];
    } elseif (in_array($channel, ['cash','gateway','other'], true)) {
        $instructions = trim((string)($post['instructions'] ?? ''));
        $url = trim((string)($post['url'] ?? ''));
        if ($instructions !== '') $details['instructions'] = $instructions;
        if ($url !== '') $details['url'] = $url;
    }

    if (empty($details)) return null;
    return json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function resolve_qr_href($admin_base, $qr) {
    $qr = trim((string)$qr);
    if ($qr === '') return '';
    if (preg_match('#^https?://#i', $qr)) return $qr;
    if ($qr[0] === '/') return $qr;
    return $admin_base . $qr;
}

$message = '';
$error = '';

$store = [
    'table' => '',
    'owner_val' => 0,
    'target_role' => null,
    'label' => '',
    'subtitle' => ''
];

if ($role === 'admin') {
    $store['table'] = 'deposit_methods';
    $store['owner_val'] = $my_id;
    $store['target_role'] = 'master';
    $store['label'] = 'Visible to masters';
    $store['subtitle'] = 'Methods set by admin';
} elseif ($role === 'master') {
    $store['table'] = 'deposit_methods';
    $store['owner_val'] = $my_id;
    $store['target_role'] = 'agent';
    $store['label'] = 'Visible to agents';
    $store['subtitle'] = 'Methods set by master';
} else {
    $store['table'] = 'player_deposit_methods';
    $store['owner_val'] = $my_id;
    $store['target_role'] = null;
    $store['label'] = 'Visible to players';
    $store['subtitle'] = 'Methods set by agent';
}

$upstream = [];
try {
    if ($role === 'master') {
        $stmt = $pdo->prepare("SELECT * FROM deposit_methods WHERE target_role='master' AND enabled=1 ORDER BY sort_order ASC, id DESC");
        $stmt->execute();
        $upstream = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($role === 'agent') {
        $master_id = 0;
        $stmt = $pdo->prepare("SELECT parent_id FROM users WHERE id=?");
        $stmt->execute([$my_id]);
        $master_id = (int)($stmt->fetchColumn() ?? 0);
        if ($master_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM deposit_methods WHERE owner_id=? AND target_role='agent' AND enabled=1 ORDER BY sort_order ASC, id DESC");
            $stmt->execute([$master_id]);
            $upstream = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (Exception $e) {
    $upstream = [];
}

$auto_user_id = (int)($_GET['user_id'] ?? 0);
$auto_users = [];
$auto_user_methods = [];
if ($role === 'admin') {
    try {
        $users_stmt = $pdo->query("SELECT id, username FROM users WHERE role='player' AND (parent_id IS NULL OR parent_id=0) ORDER BY id DESC LIMIT 500");
        $auto_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $auto_users = [];
    }

    if ($auto_user_id > 0) {
        try {
            $chk = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='player' AND (parent_id IS NULL OR parent_id=0)");
            $chk->execute([$auto_user_id]);
            if (!$chk->fetchColumn()) {
                $auto_user_id = 0;
            }
        } catch (Exception $e) {
            $auto_user_id = 0;
        }
    }

    if ($auto_user_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE user_id=? ORDER BY sort_order ASC, id DESC");
            $stmt->execute([$auto_user_id]);
            $auto_user_methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $auto_user_methods = [];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($role === 'admin' && strpos($action, 'user_') === 0) {
            $sel_user_id = (int)($_POST['user_id'] ?? 0);
            if ($sel_user_id <= 0) {
                $error = 'Select a user.';
            } else {
                try {
                    $chk = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='player' AND (parent_id IS NULL OR parent_id=0)");
                    $chk->execute([$sel_user_id]);
                    if (!$chk->fetchColumn()) throw new Exception('Invalid user.');

                    if ($action === 'user_copy_admin_defaults') {
                        $stmt = $pdo->prepare("SELECT label, channel, enabled, sort_order, details_json
                                               FROM deposit_methods
                                               WHERE target_role='master' AND enabled=1
                                               ORDER BY sort_order ASC, id DESC");
                        $stmt->execute();
                        $defaults = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                        $pdo->beginTransaction();
                        $del = $pdo->prepare("DELETE FROM user_deposit_methods WHERE user_id=?");
                        $del->execute([$sel_user_id]);

                        if (!empty($defaults)) {
                            $ins = $pdo->prepare("INSERT INTO user_deposit_methods (user_id, label, channel, enabled, sort_order, details_json)
                                                  VALUES (?, ?, ?, ?, ?, ?)");
                            foreach ($defaults as $d) {
                                $ins->execute([
                                    $sel_user_id,
                                    (string)($d['label'] ?? ''),
                                    (string)($d['channel'] ?? 'other'),
                                    (int)($d['enabled'] ?? 1),
                                    (int)($d['sort_order'] ?? 0),
                                    $d['details_json'] ?? null
                                ]);
                            }
                        }
                        $pdo->commit();
                        audit_log($pdo, 'copy', 'user_deposit_method', (string)$sel_user_id, null, ['source' => 'admin_defaults']);
                        $message = 'Admin default methods applied to user.';
                        $auto_user_id = $sel_user_id;
                    } else {
                        $label = trim((string)($_POST['label'] ?? ''));
                        $channel = (string)($_POST['channel'] ?? 'other');
                        $enabled = isset($_POST['enabled']) ? 1 : 0;
                        $sort_order = (int)($_POST['sort_order'] ?? 0);

                        if ($label === '') throw new Exception('Label is required.');
                        if (!in_array($channel, ['upi','bank','wallet','cash','gateway','other'], true)) throw new Exception('Invalid channel.');

                        if ($action === 'user_create') {
                            $qr_path = null;
                            if ($channel === 'upi' || $channel === 'wallet') {
                                [$qr_path, $qr_err] = mk_save_qr_upload($_FILES['qr_file'] ?? null, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr', 'uploads/qr');
                                if ($qr_err !== '') throw new Exception($qr_err);
                            }
                            $details_json = build_details_json($channel, $_POST, [], $qr_path);
                            $stmt = $pdo->prepare("INSERT INTO user_deposit_methods (user_id, label, channel, enabled, sort_order, details_json)
                                VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$sel_user_id, $label, $channel, $enabled, $sort_order, $details_json]);
                            audit_log($pdo, 'create', 'user_deposit_method', (string)$pdo->lastInsertId(), null, [
                                'user_id' => $sel_user_id,
                                'label' => $label,
                                'channel' => $channel
                            ]);
                            $message = 'User deposit method created.';
                            $auto_user_id = $sel_user_id;
                        } elseif ($action === 'user_update') {
                            $id = (int)($_POST['id'] ?? 0);
                            if ($id <= 0) throw new Exception('Invalid method.');
                            $old = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE id=? AND user_id=?");
                            $old->execute([$id, $sel_user_id]);
                            $before = $old->fetch();
                            if (!$before) throw new Exception('Method not found.');

                            $existing_assoc = json_assoc($before['details_json'] ?? '');
                            $qr_path = null;
                            if ($channel === 'upi' || $channel === 'wallet') {
                                [$qr_path, $qr_err] = mk_save_qr_upload($_FILES['qr_file'] ?? null, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr', 'uploads/qr');
                                if ($qr_err !== '') throw new Exception($qr_err);
                            }
                            $built = build_details_json($channel, $_POST, $existing_assoc, $qr_path);
                            $details_json = $built === null ? ($before['details_json'] ?? null) : $built;

                            $stmt = $pdo->prepare("UPDATE user_deposit_methods SET label=?, channel=?, enabled=?, sort_order=?, details_json=? WHERE id=? AND user_id=?");
                            $stmt->execute([$label, $channel, $enabled, $sort_order, $details_json, $id, $sel_user_id]);
                            audit_log($pdo, 'update', 'user_deposit_method', (string)$id, $before, [
                                'label' => $label,
                                'channel' => $channel,
                                'enabled' => $enabled,
                                'sort_order' => $sort_order,
                                'details_json' => $details_json
                            ]);
                            $message = 'User deposit method updated.';
                            $auto_user_id = $sel_user_id;
                        } elseif ($action === 'user_delete') {
                            $id = (int)($_POST['id'] ?? 0);
                            if ($id <= 0) throw new Exception('Invalid method.');
                            $old = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE id=? AND user_id=?");
                            $old->execute([$id, $sel_user_id]);
                            $before = $old->fetch();
                            if (!$before) throw new Exception('Method not found.');
                            $stmt = $pdo->prepare("DELETE FROM user_deposit_methods WHERE id=? AND user_id=?");
                            $stmt->execute([$id, $sel_user_id]);
                            audit_log($pdo, 'delete', 'user_deposit_method', (string)$id, $before, null);
                            $message = 'User deposit method deleted.';
                            $auto_user_id = $sel_user_id;
                        } else {
                            throw new Exception('Invalid action.');
                        }
                    }
                } catch (Exception $e) {
                    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                    $error = $error !== '' ? $error : $e->getMessage();
                }
            }
        } else {
        if ($action === 'clone_upstream') {
            $up_id = (int)($_POST['upstream_id'] ?? 0);
            if ($up_id <= 0) {
                $error = 'Invalid upstream method.';
            } elseif (!in_array($role, ['master','agent'], true)) {
                $error = 'Permission denied.';
            } else {
                try {
                    $up = null;
                    if ($role === 'master') {
                        $stmt = $pdo->prepare("SELECT * FROM deposit_methods WHERE id=? AND target_role='master' AND enabled=1 ORDER BY id DESC");
                        $stmt->execute([$up_id]);
                        $up = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $stmt = $pdo->prepare("SELECT parent_id FROM users WHERE id=? AND role='agent'");
                        $stmt->execute([$my_id]);
                        $master_id = (int)($stmt->fetchColumn() ?? 0);
                        if ($master_id > 0) {
                            $stmt = $pdo->prepare("SELECT * FROM deposit_methods WHERE id=? AND owner_id=? AND target_role='agent' AND enabled=1 ORDER BY id DESC");
                            $stmt->execute([$up_id, $master_id]);
                            $up = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                    }
                    if (!$up) throw new Exception('Upstream method not found.');

                    $baseLabel = trim((string)($up['label'] ?? ''));
                    if ($baseLabel === '') $baseLabel = 'Method';
                    $finalLabel = $baseLabel;
                    $n = 1;
                    while (true) {
                        if ($store['table'] === 'deposit_methods') {
                            $chk = $pdo->prepare("SELECT 1 FROM deposit_methods WHERE owner_id=? AND target_role=? AND label=? LIMIT 1");
                            $chk->execute([$store['owner_val'], $store['target_role'], $finalLabel]);
                        } else {
                            $chk = $pdo->prepare("SELECT 1 FROM player_deposit_methods WHERE agent_id=? AND label=? LIMIT 1");
                            $chk->execute([$store['owner_val'], $finalLabel]);
                        }
                        if (!$chk->fetchColumn()) break;
                        $n++;
                        $finalLabel = $baseLabel . ' (' . $n . ')';
                        if ($n > 50) throw new Exception('Too many duplicates.');
                    }

                    $channel = (string)($up['channel'] ?? 'other');
                    $details_json = $up['details_json'] ?? null;
                    $enabled = 1;
                    $sort_order = (int)($up['sort_order'] ?? 0);
                    $source_method_id = (int)($up['id'] ?? 0);

                    if ($store['table'] === 'deposit_methods') {
                        $stmt = $pdo->prepare("INSERT INTO deposit_methods (owner_id, target_role, label, channel, enabled, sort_order, details_json, source_method_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$store['owner_val'], $store['target_role'], $finalLabel, $channel, $enabled, $sort_order, $details_json, $source_method_id]);
                        audit_log($pdo, 'clone', 'deposit_method', (string)$pdo->lastInsertId(), null, [
                            'owner_id' => $store['owner_val'],
                            'target_role' => $store['target_role'],
                            'label' => $finalLabel,
                            'source_method_id' => $source_method_id
                        ]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO player_deposit_methods (agent_id, label, channel, enabled, sort_order, details_json, source_method_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$store['owner_val'], $finalLabel, $channel, $enabled, $sort_order, $details_json, $source_method_id]);
                        audit_log($pdo, 'clone', 'player_deposit_method', (string)$pdo->lastInsertId(), null, [
                            'agent_id' => $store['owner_val'],
                            'label' => $finalLabel,
                            'source_method_id' => $source_method_id
                        ]);
                    }
                    $message = 'Upstream method added.';
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        } else {
        $label = trim((string)($_POST['label'] ?? ''));
        $channel = (string)($_POST['channel'] ?? 'other');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($label === '') $error = 'Label is required.';
        elseif (!in_array($channel, ['upi','bank','wallet','cash','gateway','other'], true)) $error = 'Invalid channel.';
        else {
            try {
                if ($action === 'create') {
                    $qr_path = null;
                    if ($channel === 'upi' || $channel === 'wallet') {
                        [$qr_path, $qr_err] = mk_save_qr_upload($_FILES['qr_file'] ?? null, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr', 'uploads/qr');
                        if ($qr_err !== '') $error = $qr_err;
                    }
                    $details_json = $error === '' ? build_details_json($channel, $_POST, [], $qr_path) : null;
                    if ($error !== '') throw new Exception($error);

                    if ($store['table'] === 'deposit_methods') {
                        $stmt = $pdo->prepare("INSERT INTO deposit_methods (owner_id, target_role, label, channel, enabled, sort_order, details_json, source_method_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NULL)");
                        $stmt->execute([$store['owner_val'], $store['target_role'], $label, $channel, $enabled, $sort_order, $details_json]);
                        audit_log($pdo, 'create', 'deposit_method', (string)$pdo->lastInsertId(), null, [
                            'owner_id' => $store['owner_val'],
                            'target_role' => $store['target_role'],
                            'label' => $label,
                            'channel' => $channel,
                            'enabled' => $enabled,
                            'sort_order' => $sort_order
                        ]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO player_deposit_methods (agent_id, label, channel, enabled, sort_order, details_json, source_method_id)
                            VALUES (?, ?, ?, ?, ?, ?, NULL)");
                        $stmt->execute([$store['owner_val'], $label, $channel, $enabled, $sort_order, $details_json]);
                        audit_log($pdo, 'create', 'player_deposit_method', (string)$pdo->lastInsertId(), null, [
                            'agent_id' => $store['owner_val'],
                            'label' => $label,
                            'channel' => $channel,
                            'enabled' => $enabled,
                            'sort_order' => $sort_order
                        ]);
                    }

                    $message = 'Deposit method created.';
                } elseif ($action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) $error = 'Invalid method.';
                    else {
                        if ($store['table'] === 'deposit_methods') {
                            $old = $pdo->prepare("SELECT * FROM deposit_methods WHERE id=? AND owner_id=? AND target_role=?");
                            $old->execute([$id, $store['owner_val'], $store['target_role']]);
                        } else {
                            $old = $pdo->prepare("SELECT * FROM player_deposit_methods WHERE id=? AND agent_id=?");
                            $old->execute([$id, $store['owner_val']]);
                        }
                        $before = $old->fetch();
                        if (!$before) $error = 'Method not found.';
                        else {
                            $existing_assoc = json_assoc($before['details_json'] ?? '');
                            $qr_path = null;
                            if ($channel === 'upi' || $channel === 'wallet') {
                                [$qr_path, $qr_err] = mk_save_qr_upload($_FILES['qr_file'] ?? null, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr', 'uploads/qr');
                                if ($qr_err !== '') $error = $qr_err;
                            }
                            $built = $error === '' ? build_details_json($channel, $_POST, $existing_assoc, $qr_path) : null;
                            if ($error !== '') throw new Exception($error);
                            $details_json = $built === null ? ($before['details_json'] ?? null) : $built;

                            if ($store['table'] === 'deposit_methods') {
                                $stmt = $pdo->prepare("UPDATE deposit_methods SET label=?, channel=?, enabled=?, sort_order=?, details_json=? WHERE id=? AND owner_id=? AND target_role=?");
                                $stmt->execute([$label, $channel, $enabled, $sort_order, $details_json, $id, $store['owner_val'], $store['target_role']]);
                                audit_log($pdo, 'update', 'deposit_method', (string)$id, $before, [
                                    'label' => $label,
                                    'channel' => $channel,
                                    'enabled' => $enabled,
                                    'sort_order' => $sort_order,
                                    'details_json' => $details_json
                                ]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE player_deposit_methods SET label=?, channel=?, enabled=?, sort_order=?, details_json=? WHERE id=? AND agent_id=?");
                                $stmt->execute([$label, $channel, $enabled, $sort_order, $details_json, $id, $store['owner_val']]);
                                audit_log($pdo, 'update', 'player_deposit_method', (string)$id, $before, [
                                    'label' => $label,
                                    'channel' => $channel,
                                    'enabled' => $enabled,
                                    'sort_order' => $sort_order,
                                    'details_json' => $details_json
                                ]);
                            }

                            $message = 'Deposit method updated.';
                        }
                    }
                } elseif ($action === 'delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) $error = 'Invalid method.';
                    else {
                        if ($store['table'] === 'deposit_methods') {
                            $old = $pdo->prepare("SELECT * FROM deposit_methods WHERE id=? AND owner_id=? AND target_role=?");
                            $old->execute([$id, $store['owner_val'], $store['target_role']]);
                        } else {
                            $old = $pdo->prepare("SELECT * FROM player_deposit_methods WHERE id=? AND agent_id=?");
                            $old->execute([$id, $store['owner_val']]);
                        }
                        $before = $old->fetch();
                        if (!$before) $error = 'Method not found.';
                        else {
                            if ($store['table'] === 'deposit_methods') {
                                $stmt = $pdo->prepare("DELETE FROM deposit_methods WHERE id=? AND owner_id=? AND target_role=?");
                                $stmt->execute([$id, $store['owner_val'], $store['target_role']]);
                                audit_log($pdo, 'delete', 'deposit_method', (string)$id, $before, null);
                            } else {
                                $stmt = $pdo->prepare("DELETE FROM player_deposit_methods WHERE id=? AND agent_id=?");
                                $stmt->execute([$id, $store['owner_val']]);
                                audit_log($pdo, 'delete', 'player_deposit_method', (string)$id, $before, null);
                            }
                            $message = 'Deposit method deleted.';
                        }
                    }
                } else {
                    $error = 'Invalid action.';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        }
        }
    }
}

$mine = [];
try {
    if ($store['table'] === 'deposit_methods') {
        $stmt = $pdo->prepare("SELECT * FROM deposit_methods WHERE owner_id=? AND target_role=? ORDER BY sort_order ASC, id DESC");
        $stmt->execute([$store['owner_val'], $store['target_role']]);
        $mine = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM player_deposit_methods WHERE agent_id=? ORDER BY sort_order ASC, id DESC");
        $stmt->execute([$store['owner_val']]);
        $mine = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    $mine = [];
}

if ($role === 'admin' && $auto_user_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_deposit_methods WHERE user_id=? ORDER BY sort_order ASC, id DESC");
        $stmt->execute([$auto_user_id]);
        $auto_user_methods = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $auto_user_methods = [];
    }
}
?>

<div class="row">
  <div class="col-12">
    <?php if ($message !== ''): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
  <?php if (!empty($upstream)): ?>
  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-1">Upstream Methods</h5>
        <div class="text-body-secondary">Methods you can use to deposit</div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <?php foreach ($upstream as $m): ?>
            <?php $d = json_assoc($m['details_json'] ?? ''); ?>
            <?php
              $channel = (string)($m['channel'] ?? '');
              $icon = 'ri-information-line';
              if ($channel === 'upi') $icon = 'ri-qr-code-line';
              elseif ($channel === 'bank') $icon = 'ri-bank-line';
              elseif ($channel === 'wallet') $icon = 'ri-wallet-3-line';
              elseif ($channel === 'cash') $icon = 'ri-money-rupee-circle-line';
              elseif ($channel === 'gateway') $icon = 'ri-bank-card-line';
              $wn = strtolower((string)($d['wallet_name'] ?? ''));
              if ($channel === 'wallet' && (strpos($wn, 'usdt') !== false || strpos($wn, 'crypto') !== false)) $icon = 'ri-bit-coin-line';
              $qr = (string)($d['qr_path'] ?? ($d['qr_url'] ?? ''));
              $qr_href = resolve_qr_href($admin_base, $qr);
            ?>
            <div class="col-12">
              <div class="border rounded p-3">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                  <div class="d-flex align-items-start gap-2">
                    <i class="<?php echo htmlspecialchars($icon); ?>" style="font-size: 18px; line-height: 1;"></i>
                    <div>
                      <div class="fw-semibold"><?php echo htmlspecialchars((string)($m['label'] ?? '')); ?></div>
                      <div class="text-body-secondary small"><?php echo htmlspecialchars(strtoupper($channel)); ?></div>
                      <?php if ($channel === 'upi' && !empty($d['upi_id'])): ?>
                        <div class="small mt-1"><span class="text-body-secondary">UPI:</span> <?php echo htmlspecialchars((string)$d['upi_id']); ?></div>
                      <?php endif; ?>
                      <?php if ($channel === 'bank' && !empty($d['account_number'])): ?>
                        <div class="small mt-1"><span class="text-body-secondary">A/C:</span> <?php echo htmlspecialchars((string)$d['account_number']); ?></div>
                      <?php endif; ?>
                      <?php if ($channel === 'wallet' && !empty($d['wallet_number'])): ?>
                        <div class="small mt-1"><span class="text-body-secondary">ADDR:</span> <?php echo htmlspecialchars((string)$d['wallet_number']); ?></div>
                      <?php endif; ?>
                      <?php if (!empty($d['notes'])): ?>
                        <div class="small mt-1 text-body-secondary"><?php echo htmlspecialchars((string)$d['notes']); ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="d-flex align-items-start gap-2">
                    <?php if ($qr_href !== '' && ($channel === 'upi' || $channel === 'wallet')): ?>
                      <a href="<?php echo htmlspecialchars($qr_href); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo htmlspecialchars($qr_href); ?>" alt="QR" style="max-width:120px;max-height:120px;border-radius:10px;border:1px solid rgba(0,0,0,0.08);background:#fff;">
                      </a>
                    <?php endif; ?>
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                      <input type="hidden" name="action" value="clone_upstream">
                      <input type="hidden" name="upstream_id" value="<?php echo (int)$m['id']; ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit">Use</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-header d-flex align-items-start justify-content-between gap-2 flex-wrap">
        <div>
          <h5 class="mb-1">Configure Methods</h5>
          <div class="text-body-secondary"><?php echo htmlspecialchars($store['label']); ?></div>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" class="row g-3" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="action" value="create">
          <div class="col-12">
            <label class="form-label">Label</label>
            <input class="form-control" name="label" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Channel</label>
            <select class="form-select" name="channel" id="mkDepositChannel">
              <?php foreach (['upi','bank','wallet','cash','gateway','other'] as $ch): ?>
                <option value="<?php echo $ch; ?>"><?php echo strtoupper($ch); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Sort Order</label>
            <input class="form-control" type="number" name="sort_order" value="0">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="enabled" id="mkDepositEnabled" checked>
              <label class="form-check-label" for="mkDepositEnabled">Enabled</label>
            </div>
          </div>

          <div class="col-12 mk-fields mk-upi">
            <label class="form-label">UPI ID</label>
            <input class="form-control" name="upi_id">
          </div>
          <div class="col-12 mk-fields mk-upi">
            <label class="form-label">Holder Name</label>
            <input class="form-control" name="holder_name">
          </div>
          <div class="col-12 mk-fields mk-upi">
            <label class="form-label">Bank Name (optional)</label>
            <input class="form-control" name="upi_bank_name">
          </div>
          <div class="col-12 mk-fields mk-upi">
            <label class="form-label">QR Code (optional)</label>
            <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
          </div>

          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">Bank Name</label>
            <input class="form-control" name="bank_name">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">Branch</label>
            <input class="form-control" name="branch">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">Account Name</label>
            <input class="form-control" name="account_name">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">Account Number</label>
            <input class="form-control" name="account_number">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">IFSC</label>
            <input class="form-control" name="ifsc">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">SWIFT (optional)</label>
            <input class="form-control" name="swift">
          </div>
          <div class="col-12 mk-fields mk-bank">
            <label class="form-label">IBAN (optional)</label>
            <input class="form-control" name="iban">
          </div>

          <div class="col-12 mk-fields mk-wallet">
            <label class="form-label">Wallet Type</label>
            <select class="form-select" name="wallet_kind" id="mkWalletKind">
              <option value="wallet">Wallet</option>
              <option value="usdt">USDT</option>
            </select>
          </div>
          <div class="col-12 mk-fields mk-wallet" id="mkWalletNetworkWrap" style="display:none;">
            <label class="form-label">USDT Network</label>
            <select class="form-select" name="wallet_network">
              <option value="TRC20">TRC20</option>
              <option value="BEP20">BEP20</option>
            </select>
          </div>
          <div class="col-12 mk-fields mk-wallet" id="mkWalletNameWrap">
            <label class="form-label">Wallet Name</label>
            <input class="form-control" name="wallet_name" placeholder="USDT (TRC20) / BTC / ...">
          </div>
          <div class="col-12 mk-fields mk-wallet">
            <label class="form-label">Wallet Address / Number</label>
            <input class="form-control" name="wallet_number">
          </div>
          <div class="col-12 mk-fields mk-wallet">
            <label class="form-label">Wallet QR (optional)</label>
            <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
          </div>

          <div class="col-12 mk-fields mk-cash mk-gateway mk-other">
            <label class="form-label">Instructions</label>
            <textarea class="form-control" name="instructions" rows="2"></textarea>
          </div>
          <div class="col-12 mk-fields mk-gateway mk-other">
            <label class="form-label">URL (optional)</label>
            <input class="form-control" name="url">
          </div>
          <div class="col-12">
            <label class="form-label">Notes (optional)</label>
            <textarea class="form-control" name="notes" rows="2"></textarea>
          </div>

          <div class="col-12">
            <button class="btn btn-primary" type="submit">Add Method</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-1">My Methods</h5>
        <div class="text-body-secondary"><?php echo htmlspecialchars($store['subtitle']); ?></div>
      </div>
      <div class="card-body">
        <?php if (empty($mine)): ?>
          <div class="text-body-secondary">No methods added yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Label</th>
                  <th>Channel</th>
                  <th>Enabled</th>
                  <th>Sort</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($mine as $m): ?>
                  <?php $ed = json_assoc($m['details_json'] ?? ''); ?>
                  <?php
                    $ch = (string)($m['channel'] ?? '');
                    $qr = (string)($ed['qr_path'] ?? ($ed['qr_url'] ?? ''));
                    $qr_href = resolve_qr_href($admin_base, $qr);
                  ?>
                  <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars((string)$m['label']); ?></td>
                    <td><?php echo htmlspecialchars(strtoupper($ch)); ?></td>
                    <td><?php echo (int)($m['enabled'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                    <td><?php echo (int)($m['sort_order'] ?? 0); ?></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editMethod<?php echo (int)$m['id']; ?>">Edit</button>
                    </td>
                  </tr>
                  <tr class="collapse" id="editMethod<?php echo (int)$m['id']; ?>">
                    <td colspan="5">
                      <form method="POST" class="row g-3" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                        <div class="col-12 col-md-5">
                          <label class="form-label">Label</label>
                          <input class="form-control" name="label" value="<?php echo htmlspecialchars((string)$m['label']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                          <label class="form-label">Channel</label>
                          <select class="form-select mk-edit-channel" name="channel" data-mk-scope="edit<?php echo (int)$m['id']; ?>">
                            <?php foreach (['upi','bank','wallet','cash','gateway','other'] as $c): ?>
                              <option value="<?php echo $c; ?>" <?php echo $ch === $c ? 'selected' : ''; ?>><?php echo strtoupper($c); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-12 col-md-2">
                          <label class="form-label">Sort</label>
                          <input class="form-control" type="number" name="sort_order" value="<?php echo (int)($m['sort_order'] ?? 0); ?>">
                        </div>
                        <div class="col-12 col-md-2 d-flex align-items-end">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enabled" id="mkEnabled<?php echo (int)$m['id']; ?>" <?php echo (int)($m['enabled'] ?? 0) === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="mkEnabled<?php echo (int)$m['id']; ?>">Enabled</label>
                          </div>
                        </div>

                        <div class="col-12 mk-edit-scope" data-mk-scope="edit<?php echo (int)$m['id']; ?>">
                          <div class="row g-3">
                            <div class="col-12 mk-edit-fields mk-edit-upi">
                              <label class="form-label">UPI ID</label>
                              <input class="form-control" name="upi_id" value="<?php echo htmlspecialchars((string)($ed['upi_id'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-upi">
                              <label class="form-label">Holder Name</label>
                              <input class="form-control" name="holder_name" value="<?php echo htmlspecialchars((string)($ed['holder_name'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-upi">
                              <label class="form-label">Bank Name (optional)</label>
                              <input class="form-control" name="upi_bank_name" value="<?php echo htmlspecialchars((string)($ch === 'upi' ? ($ed['bank_name'] ?? '') : '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-upi">
                              <label class="form-label">QR Code (optional)</label>
                              <?php if ($qr_href !== '' && $ch === 'upi'): ?>
                                <div class="mb-2">
                                  <a href="<?php echo htmlspecialchars($qr_href); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo htmlspecialchars($qr_href); ?>" alt="QR" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);background:#fff;">
                                  </a>
                                </div>
                              <?php endif; ?>
                              <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
                            </div>

                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">Bank Name</label>
                              <input class="form-control" name="bank_name" value="<?php echo htmlspecialchars((string)($ed['bank_name'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">Branch</label>
                              <input class="form-control" name="branch" value="<?php echo htmlspecialchars((string)($ed['branch'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">Account Name</label>
                              <input class="form-control" name="account_name" value="<?php echo htmlspecialchars((string)($ed['account_name'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">Account Number</label>
                              <input class="form-control" name="account_number" value="<?php echo htmlspecialchars((string)($ed['account_number'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">IFSC</label>
                              <input class="form-control" name="ifsc" value="<?php echo htmlspecialchars((string)($ed['ifsc'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">SWIFT (optional)</label>
                              <input class="form-control" name="swift" value="<?php echo htmlspecialchars((string)($ed['swift'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-bank">
                              <label class="form-label">IBAN (optional)</label>
                              <input class="form-control" name="iban" value="<?php echo htmlspecialchars((string)($ed['iban'] ?? '')); ?>">
                            </div>

                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">Wallet Type</label>
                              <select class="form-select" name="wallet_kind">
                                <option value="wallet" <?php echo (string)($ed['wallet_kind'] ?? 'wallet') === 'wallet' ? 'selected' : ''; ?>>Wallet</option>
                                <option value="usdt" <?php echo (string)($ed['wallet_kind'] ?? '') === 'usdt' ? 'selected' : ''; ?>>USDT</option>
                              </select>
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">USDT Network</label>
                              <select class="form-select" name="wallet_network">
                                <option value="TRC20" <?php echo (string)($ed['wallet_network'] ?? 'TRC20') === 'TRC20' ? 'selected' : ''; ?>>TRC20</option>
                                <option value="BEP20" <?php echo (string)($ed['wallet_network'] ?? '') === 'BEP20' ? 'selected' : ''; ?>>BEP20</option>
                              </select>
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">Wallet Name</label>
                              <input class="form-control" name="wallet_name" value="<?php echo htmlspecialchars((string)($ed['wallet_name'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">Wallet Address / Number</label>
                              <input class="form-control" name="wallet_number" value="<?php echo htmlspecialchars((string)($ed['wallet_number'] ?? '')); ?>">
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-wallet">
                              <label class="form-label">Wallet QR (optional)</label>
                              <?php if ($qr_href !== '' && $ch === 'wallet'): ?>
                                <div class="mb-2">
                                  <a href="<?php echo htmlspecialchars($qr_href); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo htmlspecialchars($qr_href); ?>" alt="QR" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);background:#fff;">
                                  </a>
                                </div>
                              <?php endif; ?>
                              <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
                            </div>

                            <div class="col-12 mk-edit-fields mk-edit-cash mk-edit-gateway mk-edit-other">
                              <label class="form-label">Instructions</label>
                              <textarea class="form-control" name="instructions" rows="2"><?php echo htmlspecialchars((string)($ed['instructions'] ?? '')); ?></textarea>
                            </div>
                            <div class="col-12 mk-edit-fields mk-edit-gateway mk-edit-other">
                              <label class="form-label">URL (optional)</label>
                              <input class="form-control" name="url" value="<?php echo htmlspecialchars((string)($ed['url'] ?? '')); ?>">
                            </div>
                            <div class="col-12">
                              <label class="form-label">Notes (optional)</label>
                              <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars((string)($ed['notes'] ?? '')); ?></textarea>
                            </div>
                          </div>
                        </div>

                        <div class="col-12 d-flex gap-2 flex-wrap">
                          <button class="btn btn-primary" type="submit" name="action" value="update">Update</button>
                          <button class="btn btn-outline-danger" type="submit" name="action" value="delete" onclick="return confirm('Delete this method?')">Delete</button>
                        </div>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($role === 'admin'): ?>
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-1">Auto Users Methods</h5>
        <div class="text-body-secondary">Configure methods for users without an agent</div>
      </div>
      <div class="card-body">
        <form method="GET" class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">User</label>
            <select class="form-select" name="user_id" onchange="this.form.submit()">
              <option value="0">Select auto-registered user</option>
              <?php foreach ($auto_users as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>" <?php echo $auto_user_id === (int)$u['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string)$u['username']); ?> (#<?php echo (int)$u['id']; ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <hr>

        <form method="POST" class="mb-3">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="action" value="user_copy_admin_defaults">
          <input type="hidden" name="user_id" value="<?php echo (int)$auto_user_id; ?>">
          <button class="btn btn-outline-dark" type="submit" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?> onclick="return confirm('Replace this user\\'s methods with admin defaults?')">
            Use Admin Default Methods
          </button>
        </form>

        <div class="row g-4">
          <div class="col-12 col-lg-6">
            <div class="border rounded p-3">
              <div class="fw-semibold mb-2">Add Method for User</div>
              <form method="POST" class="row g-3" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="user_create">
                <input type="hidden" name="user_id" value="<?php echo (int)$auto_user_id; ?>">

                <div class="col-12">
                  <label class="form-label">Label</label>
                  <input class="form-control" name="label" required <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Channel</label>
                  <select class="form-select" name="channel" id="mkUserDepositChannel" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                    <?php foreach (['upi','bank','wallet','cash','gateway','other'] as $ch): ?>
                      <option value="<?php echo $ch; ?>"><?php echo strtoupper($ch); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Sort Order</label>
                  <input class="form-control" type="number" name="sort_order" value="0" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="enabled" id="mkUserDepositEnabled" checked <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="mkUserDepositEnabled">Enabled</label>
                  </div>
                </div>

                <div class="col-12 mk-user-fields mk-user-upi">
                  <label class="form-label">UPI ID</label>
                  <input class="form-control" name="upi_id" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-upi">
                  <label class="form-label">Holder Name</label>
                  <input class="form-control" name="holder_name" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-upi">
                  <label class="form-label">Bank Name (optional)</label>
                  <input class="form-control" name="upi_bank_name" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-upi">
                  <label class="form-label">QR Code (optional)</label>
                  <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>

                <div class="col-12 mk-user-fields mk-user-bank">
                  <label class="form-label">Bank Name</label>
                  <input class="form-control" name="bank_name" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-bank">
                  <label class="form-label">Branch</label>
                  <input class="form-control" name="branch" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-bank">
                  <label class="form-label">Account Name</label>
                  <input class="form-control" name="account_name" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-bank">
                  <label class="form-label">Account Number</label>
                  <input class="form-control" name="account_number" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-bank">
                  <label class="form-label">IFSC</label>
                  <input class="form-control" name="ifsc" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>

                <div class="col-12 mk-user-fields mk-user-wallet">
                  <label class="form-label">Wallet Type</label>
                  <select class="form-select" name="wallet_kind" id="mkUserWalletKind" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                    <option value="wallet">Wallet</option>
                    <option value="usdt">USDT</option>
                  </select>
                </div>
                <div class="col-12 mk-user-fields mk-user-wallet" id="mkUserWalletNetworkWrap" style="display:none;">
                  <label class="form-label">USDT Network</label>
                  <select class="form-select" name="wallet_network" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                    <option value="TRC20">TRC20</option>
                    <option value="BEP20">BEP20</option>
                  </select>
                </div>
                <div class="col-12 mk-user-fields mk-user-wallet" id="mkUserWalletNameWrap">
                  <label class="form-label">Wallet Name</label>
                  <input class="form-control" name="wallet_name" placeholder="BTC / Skrill / ..." <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-wallet">
                  <label class="form-label">Wallet Address / Number</label>
                  <input class="form-control" name="wallet_number" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12 mk-user-fields mk-user-wallet">
                  <label class="form-label">Wallet QR (optional)</label>
                  <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>

                <div class="col-12 mk-user-fields mk-user-cash mk-user-gateway mk-user-other">
                  <label class="form-label">Instructions</label>
                  <textarea class="form-control" name="instructions" rows="2" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>></textarea>
                </div>
                <div class="col-12 mk-user-fields mk-user-gateway mk-user-other">
                  <label class="form-label">URL (optional)</label>
                  <input class="form-control" name="url" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>
                </div>
                <div class="col-12">
                  <label class="form-label">Notes (optional)</label>
                  <textarea class="form-control" name="notes" rows="2" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>></textarea>
                </div>

                <div class="col-12">
                  <button class="btn btn-primary" type="submit" <?php echo $auto_user_id <= 0 ? 'disabled' : ''; ?>>Add</button>
                </div>
              </form>
            </div>
          </div>

          <div class="col-12 col-lg-6">
            <div class="border rounded p-3">
              <div class="fw-semibold mb-2">User Methods</div>
              <?php if ($auto_user_id <= 0): ?>
                <div class="text-body-secondary">Select a user to view/edit methods.</div>
              <?php elseif (empty($auto_user_methods)): ?>
                <div class="text-body-secondary">No methods created yet.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table">
                    <thead>
                      <tr>
                        <th>Label</th>
                        <th>Channel</th>
                        <th>Enabled</th>
                        <th>Sort</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($auto_user_methods as $m): ?>
                        <?php $ed = json_assoc($m['details_json'] ?? ''); ?>
                        <tr>
                          <td class="fw-semibold"><?php echo htmlspecialchars((string)$m['label']); ?></td>
                          <td><?php echo htmlspecialchars(strtoupper((string)$m['channel'])); ?></td>
                          <td><?php echo (int)($m['enabled'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                          <td><?php echo (int)($m['sort_order'] ?? 0); ?></td>
                          <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editUserMethod<?php echo (int)$m['id']; ?>">Edit</button>
                          </td>
                        </tr>
                        <tr class="collapse" id="editUserMethod<?php echo (int)$m['id']; ?>">
                          <td colspan="5">
                            <?php
                              $ch = (string)($m['channel'] ?? '');
                              $qr = (string)($ed['qr_path'] ?? ($ed['qr_url'] ?? ''));
                              $qr_href = resolve_qr_href($admin_base, $qr);
                            ?>
                            <form method="POST" class="row g-3" enctype="multipart/form-data">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                              <input type="hidden" name="action" value="user_update">
                              <input type="hidden" name="user_id" value="<?php echo (int)$auto_user_id; ?>">
                              <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">

                              <div class="col-12 col-md-5">
                                <label class="form-label">Label</label>
                                <input class="form-control" name="label" value="<?php echo htmlspecialchars((string)$m['label']); ?>" required>
                              </div>
                              <div class="col-12 col-md-3">
                                <label class="form-label">Channel</label>
                                <select class="form-select mk-edit-channel" name="channel" data-mk-scope="u<?php echo (int)$m['id']; ?>">
                                  <?php foreach (['upi','bank','wallet','cash','gateway','other'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $ch === $c ? 'selected' : ''; ?>><?php echo strtoupper($c); ?></option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-12 col-md-2">
                                <label class="form-label">Sort</label>
                                <input class="form-control" type="number" name="sort_order" value="<?php echo (int)($m['sort_order'] ?? 0); ?>">
                              </div>
                              <div class="col-12 col-md-2 d-flex align-items-end">
                                <div class="form-check">
                                  <input class="form-check-input" type="checkbox" name="enabled" id="mkUserEnabled<?php echo (int)$m['id']; ?>" <?php echo (int)($m['enabled'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                  <label class="form-check-label" for="mkUserEnabled<?php echo (int)$m['id']; ?>">Enabled</label>
                                </div>
                              </div>

                              <div class="col-12 mk-edit-scope" data-mk-scope="u<?php echo (int)$m['id']; ?>">
                                <div class="row g-3">
                                  <div class="col-12 mk-edit-fields mk-edit-upi">
                                    <label class="form-label">UPI ID</label>
                                    <input class="form-control" name="upi_id" value="<?php echo htmlspecialchars((string)($ed['upi_id'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-upi">
                                    <label class="form-label">Holder Name</label>
                                    <input class="form-control" name="holder_name" value="<?php echo htmlspecialchars((string)($ed['holder_name'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-upi">
                                    <label class="form-label">Bank Name (optional)</label>
                                    <input class="form-control" name="upi_bank_name" value="<?php echo htmlspecialchars((string)($ch === 'upi' ? ($ed['bank_name'] ?? '') : '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-upi">
                                    <label class="form-label">QR Code (optional)</label>
                                    <?php if ($qr_href !== '' && $ch === 'upi'): ?>
                                      <div class="mb-2">
                                        <a href="<?php echo htmlspecialchars($qr_href); ?>" target="_blank" rel="noopener">
                                          <img src="<?php echo htmlspecialchars($qr_href); ?>" alt="QR" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);background:#fff;">
                                        </a>
                                      </div>
                                    <?php endif; ?>
                                    <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
                                  </div>

                                  <div class="col-12 mk-edit-fields mk-edit-bank">
                                    <label class="form-label">Bank Name</label>
                                    <input class="form-control" name="bank_name" value="<?php echo htmlspecialchars((string)($ed['bank_name'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-bank">
                                    <label class="form-label">Branch</label>
                                    <input class="form-control" name="branch" value="<?php echo htmlspecialchars((string)($ed['branch'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-bank">
                                    <label class="form-label">Account Name</label>
                                    <input class="form-control" name="account_name" value="<?php echo htmlspecialchars((string)($ed['account_name'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-bank">
                                    <label class="form-label">Account Number</label>
                                    <input class="form-control" name="account_number" value="<?php echo htmlspecialchars((string)($ed['account_number'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-bank">
                                    <label class="form-label">IFSC</label>
                                    <input class="form-control" name="ifsc" value="<?php echo htmlspecialchars((string)($ed['ifsc'] ?? '')); ?>">
                                  </div>

                                  <div class="col-12 mk-edit-fields mk-edit-wallet">
                                    <label class="form-label">Wallet Type</label>
                                    <select class="form-select" name="wallet_kind">
                                      <option value="wallet" <?php echo (string)($ed['wallet_kind'] ?? 'wallet') === 'wallet' ? 'selected' : ''; ?>>Wallet</option>
                                      <option value="usdt" <?php echo (string)($ed['wallet_kind'] ?? '') === 'usdt' ? 'selected' : ''; ?>>USDT</option>
                                    </select>
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-wallet">
                                    <label class="form-label">USDT Network</label>
                                    <select class="form-select" name="wallet_network">
                                      <option value="TRC20" <?php echo (string)($ed['wallet_network'] ?? 'TRC20') === 'TRC20' ? 'selected' : ''; ?>>TRC20</option>
                                      <option value="BEP20" <?php echo (string)($ed['wallet_network'] ?? '') === 'BEP20' ? 'selected' : ''; ?>>BEP20</option>
                                    </select>
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-wallet">
                                    <label class="form-label">Wallet Name</label>
                                    <input class="form-control" name="wallet_name" value="<?php echo htmlspecialchars((string)($ed['wallet_name'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-wallet">
                                    <label class="form-label">Wallet Address / Number</label>
                                    <input class="form-control" name="wallet_number" value="<?php echo htmlspecialchars((string)($ed['wallet_number'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-wallet">
                                    <label class="form-label">Wallet QR (optional)</label>
                                    <?php if ($qr_href !== '' && $ch === 'wallet'): ?>
                                      <div class="mb-2">
                                        <a href="<?php echo htmlspecialchars($qr_href); ?>" target="_blank" rel="noopener">
                                          <img src="<?php echo htmlspecialchars($qr_href); ?>" alt="QR" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);background:#fff;">
                                        </a>
                                      </div>
                                    <?php endif; ?>
                                    <input class="form-control" type="file" name="qr_file" accept="image/png,image/jpeg,image/webp">
                                  </div>

                                  <div class="col-12 mk-edit-fields mk-edit-cash mk-edit-gateway mk-edit-other">
                                    <label class="form-label">Instructions</label>
                                    <textarea class="form-control" name="instructions" rows="2"><?php echo htmlspecialchars((string)($ed['instructions'] ?? '')); ?></textarea>
                                  </div>
                                  <div class="col-12 mk-edit-fields mk-edit-gateway mk-edit-other">
                                    <label class="form-label">URL (optional)</label>
                                    <input class="form-control" name="url" value="<?php echo htmlspecialchars((string)($ed['url'] ?? '')); ?>">
                                  </div>
                                  <div class="col-12">
                                    <label class="form-label">Notes (optional)</label>
                                    <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars((string)($ed['notes'] ?? '')); ?></textarea>
                                  </div>
                                </div>
                              </div>

                              <div class="col-12 d-flex gap-2 flex-wrap">
                                <button class="btn btn-primary" type="submit">Update</button>
                                <button class="btn btn-outline-danger mk-confirm" type="submit" name="action" value="user_delete" data-confirm="Delete this method?">Delete</button>
                              </div>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('click', function(e) {
    var btn = e.target && e.target.closest ? e.target.closest('.mk-confirm') : null;
    if (!btn) return;
    var msg = btn.getAttribute('data-confirm') || 'Are you sure?';
    if (!window.confirm(msg)) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);

  var createSelect = document.getElementById('mkDepositChannel');
  var createFields = document.querySelectorAll('.mk-fields');
  function applyCreate() {
    if (!createSelect) return;
    var ch = createSelect.value || 'other';
    for (var i = 0; i < createFields.length; i++) createFields[i].style.display = 'none';
    var active = document.querySelectorAll('.mk-' + ch);
    for (var j = 0; j < active.length; j++) active[j].style.display = '';
    var kind = document.getElementById('mkWalletKind');
    var nw = document.getElementById('mkWalletNetworkWrap');
    var nameWrap = document.getElementById('mkWalletNameWrap');
    if (ch === 'wallet' && kind && nw && nameWrap) {
      if (kind.value === 'usdt') {
        nw.style.display = '';
        nameWrap.style.display = 'none';
      } else {
        nw.style.display = 'none';
        nameWrap.style.display = '';
      }
    }
  }
  if (createSelect) createSelect.addEventListener('change', applyCreate);
  var kind = document.getElementById('mkWalletKind');
  if (kind) kind.addEventListener('change', applyCreate);
  applyCreate();

  var userCreateSelect = document.getElementById('mkUserDepositChannel');
  var userCreateFields = document.querySelectorAll('.mk-user-fields');
  function applyUserCreate() {
    if (!userCreateSelect) return;
    var ch = userCreateSelect.value || 'other';
    for (var i = 0; i < userCreateFields.length; i++) userCreateFields[i].style.display = 'none';
    var active = document.querySelectorAll('.mk-user-' + ch);
    for (var j = 0; j < active.length; j++) active[j].style.display = '';
    var kind = document.getElementById('mkUserWalletKind');
    var nw = document.getElementById('mkUserWalletNetworkWrap');
    var nameWrap = document.getElementById('mkUserWalletNameWrap');
    if (ch === 'wallet' && kind && nw && nameWrap) {
      if (kind.value === 'usdt') {
        nw.style.display = '';
        nameWrap.style.display = 'none';
      } else {
        nw.style.display = 'none';
        nameWrap.style.display = '';
      }
    }
  }
  if (userCreateSelect) userCreateSelect.addEventListener('change', applyUserCreate);
  var userKind = document.getElementById('mkUserWalletKind');
  if (userKind) userKind.addEventListener('change', applyUserCreate);
  applyUserCreate();

  function applyScope(select, scope) {
    var wrap = document.querySelector('.mk-edit-scope[data-mk-scope="' + scope + '"]');
    if (!wrap) return;
    var ch = (select && select.value) ? select.value : 'other';
    var fields = wrap.querySelectorAll('.mk-edit-fields');
    for (var i = 0; i < fields.length; i++) fields[i].style.display = 'none';
    var active = wrap.querySelectorAll('.mk-edit-' + ch);
    for (var j = 0; j < active.length; j++) active[j].style.display = '';
  }

  var edits = document.querySelectorAll('.mk-edit-channel');
  for (var k = 0; k < edits.length; k++) {
    (function(sel) {
      var scope = sel.getAttribute('data-mk-scope');
      sel.addEventListener('change', function() { applyScope(sel, scope); });
      applyScope(sel, scope);
    })(edits[k]);
  }
});
</script>

<?php require '../includes/footer.php'; ?>
