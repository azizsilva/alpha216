<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';

require_admin_login($admin_base);
require_admin_role(['admin','master','agent'], $admin_base);

$page_title = 'Deposit History';
require '../includes/header.php';

$role = (string)current_admin_role();
$my_id = (int)current_admin_id();

$filters = [
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'q' => trim((string)($_GET['q'] ?? ''))
];

$where = "WHERE p.type='deposit'";
$params = [];

if ($role === 'agent') {
    $where .= " AND (p.payee_id = ? OR p.payer_id IN (SELECT id FROM users WHERE role='player' AND parent_id=?))";
    $params[] = $my_id;
    $params[] = $my_id;
} elseif ($role === 'master') {
    $where .= " AND (p.payee_id IN (SELECT id FROM users WHERE role='agent' AND parent_id=?) OR p.payer_id IN (
        SELECT id FROM users WHERE role='player' AND parent_id IN (SELECT id FROM users WHERE role='agent' AND parent_id=?)
    ))";
    $params[] = $my_id;
    $params[] = $my_id;
} elseif ($role !== 'admin') {
    $where .= " AND (p.payer_id = ? OR p.payee_id = ?)";
    $params[] = $my_id;
    $params[] = $my_id;
}

if ($filters['status'] !== '' && in_array($filters['status'], ['pending','completed','failed','reversed'], true)) {
    $where .= " AND p.status = ?";
    $params[] = $filters['status'];
}

if ($filters['from'] !== '') {
    $where .= " AND p.created_at >= ?";
    $params[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to'] !== '') {
    $where .= " AND p.created_at <= ?";
    $params[] = $filters['to'] . ' 23:59:59';
}

if ($filters['q'] !== '') {
    $where .= " AND (p.reference LIKE ? OR p.note LIKE ? OR m.name LIKE ? OR u1.username LIKE ? OR u2.username LIKE ? OR c.username LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
    $like = '%' . $filters['q'] . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT p.*, m.name AS mode_name,
            u1.username AS payer_name, u2.username AS payee_name, c.username AS creator_name
        FROM payments p
        LEFT JOIN payment_modes m ON m.id = p.mode_id
        LEFT JOIN users u1 ON u1.id = p.payer_id
        LEFT JOIN users u2 ON u2.id = p.payee_id
        LEFT JOIN users c ON c.id = p.created_by
        $where
        ORDER BY p.id DESC
        LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Deposit History</h5>
      <div class="text-body-secondary">
        <?php if ($role === 'admin'): ?>
          All deposits (Admin → Master, Master → Agent, Agent → Player)
        <?php elseif ($role === 'master'): ?>
          Deposits received from admin and sent to agents
        <?php else: ?>
          Deposits received from master and sent to players
        <?php endif; ?>
      </div>
    </div>
    <form method="GET" class="mk-filterbar">
      <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>" />
      <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>" />
      <select class="form-select" name="status">
        <option value="">All Status</option>
        <?php foreach (['pending','completed','failed','reversed'] as $st): ?>
          <option value="<?php echo $st; ?>" <?php echo $filters['status'] === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
        <?php endforeach; ?>
      </select>
      <input class="form-control" name="q" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="ID / Ref / User / Mode" />
      <button class="btn btn-outline-primary" type="submit">Apply</button>
      <a class="btn btn-outline-secondary" href="./">Reset</a>
    </form>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Payer</th>
            <th>Payee</th>
            <th>Mode</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th>Reference</th>
            <th>Proof</th>
            <th class="text-end">Actions</th>
            <th>Note</th>
            <th>Created By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $meta = [];
                if (!empty($r['meta_json'])) {
                    $tmp = json_decode((string)$r['meta_json'], true);
                    if (is_array($tmp)) $meta = $tmp;
                }
                $proof = (string)($meta['proof_path'] ?? '');
                $proof_href = '';
                if ($proof !== '') {
                    if (preg_match('#^https?://#i', $proof)) $proof_href = $proof;
                    elseif ($proof[0] === '/') $proof_href = $proof;
                    else $proof_href = $admin_base . $proof;
                }
                $mode = (string)($r['mode_name'] ?? '');
                if ($mode === '' && !empty($meta['method_label'])) $mode = (string)$meta['method_label'];
                $can_act = false;
                if ((string)($r['status'] ?? '') === 'pending') {
                    if ($role === 'agent' && (int)($r['payee_id'] ?? 0) === $my_id) $can_act = true;
                    if ($role === 'admin' && empty($r['payee_id'])) $can_act = true;
                }
              ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars((string)($r['created_at'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string)($r['payer_name'] ?? ($role === 'admin' ? 'ADMIN' : '-'))); ?></td>
                <td><?php echo htmlspecialchars((string)($r['payee_name'] ?? (($role === 'admin' && empty($r['payee_id'])) ? 'ADMIN' : '-'))); ?></td>
                <td><?php echo htmlspecialchars($mode !== '' ? $mode : '-'); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['amount'] ?? 0), 2); ?></td>
                <td><span class="badge bg-<?php echo (string)($r['status'] ?? '') === 'completed' ? 'success' : ((string)($r['status'] ?? '') === 'failed' ? 'danger' : 'warning'); ?>"><?php echo htmlspecialchars((string)($r['status'] ?? '')); ?></span></td>
                <td><?php echo htmlspecialchars((string)($r['reference'] ?? '')); ?></td>
                <td>
                  <?php if ($proof_href !== ''): ?>
                    <a href="<?php echo htmlspecialchars($proof_href); ?>" target="_blank" rel="noopener">
                      <img src="<?php echo htmlspecialchars($proof_href); ?>" alt="Proof" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid rgba(0,0,0,0.12);background:#fff;">
                    </a>
                  <?php else: ?>
                    <span class="text-body-secondary">-</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if ($can_act): ?>
                    <div class="btn-group btn-group-sm" role="group">
                      <button type="button" class="btn btn-success mk-pay-act" data-action="approve" data-id="<?php echo (int)$r['id']; ?>">Approve</button>
                      <button type="button" class="btn btn-outline-danger mk-pay-act" data-action="reject" data-id="<?php echo (int)$r['id']; ?>">Reject</button>
                    </div>
                  <?php else: ?>
                    <span class="text-body-secondary">-</span>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars((string)($r['note'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string)($r['creator_name'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="12" class="text-center text-body-secondary py-4">No deposits found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  var btns = document.querySelectorAll('.mk-pay-act');
  if (!btns.length) return;
  var csrf = '<?php echo htmlspecialchars(csrf_token()); ?>';
  function post(action, id, btn) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('payment_id', String(id));
    fetch('<?php echo $admin_base; ?>api/payment_action.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf }
    }).then(function(r){ return r.json(); })
    .then(function(json){
      if (!json || !json.success) {
        alert((json && json.message) ? json.message : 'Failed');
        return;
      }
      var tr = btn.closest('tr');
      if (!tr) return;
      var status = json.status || '';
      var badge = tr.querySelector('td:nth-child(7) .badge');
      if (badge) {
        badge.textContent = status;
        badge.className = 'badge ' + (status === 'completed' ? 'bg-success' : (status === 'failed' ? 'bg-danger' : 'bg-warning'));
      }
      var actionsTd = tr.querySelector('td:nth-child(10)');
      if (actionsTd) actionsTd.innerHTML = '<span class="text-body-secondary">-</span>';
    }).catch(function(){
      alert('Network error');
    });
  }
  for (var i = 0; i < btns.length; i++) {
    btns[i].addEventListener('click', function() {
      var id = this.getAttribute('data-id');
      var act = this.getAttribute('data-action');
      if (!id || !act) return;
      if (act === 'reject' && !confirm('Reject this deposit?')) return;
      post(act, id, this);
    });
  }
})();
</script>

<?php require '../includes/footer.php'; ?>
