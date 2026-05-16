<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';

require_admin_login($admin_base);
require_admin_role(['admin','master','agent'], $admin_base);

$page_title = 'Balance Requests';
require '../includes/header.php';

$role = (string)current_admin_role();
$my_id = (int)current_admin_id();

$where = "WHERE p.type='adjustment' AND p.status IN ('pending','completed','failed','reversed') AND p.meta_json LIKE '%\"kind\":\"balance_request\"%'";
$params = [];

if ($role === 'agent') {
    $where .= " AND (p.payee_id = ? OR p.payer_id = ?)";
    $params[] = $my_id;
    $params[] = $my_id;
} elseif ($role === 'master') {
    $where .= " AND (p.payer_id = ? OR p.payee_id = ? OR p.payee_id IN (SELECT id FROM users WHERE role='agent' AND parent_id=?))";
    $params[] = $my_id;
    $params[] = $my_id;
    $params[] = $my_id;
}

$sql = "SELECT p.*, u1.username AS payer_name, u2.username AS payee_name, c.username AS creator_name
        FROM payments p
        LEFT JOIN users u1 ON u1.id = p.payer_id
        LEFT JOIN users u2 ON u2.id = p.payee_id
        LEFT JOIN users c ON c.id = p.created_by
        $where
        ORDER BY p.id DESC
        LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Balance Requests</h5>
      <div class="text-body-secondary">Agent → Master, Master → Admin</div>
    </div>

    <?php if (in_array($role, ['agent','master'], true)): ?>
      <form class="d-flex align-items-center gap-2 flex-wrap" id="mkReqForm">
        <input type="number" step="0.01" min="1" class="form-control" id="mkReqAmount" placeholder="Amount" style="max-width:160px;">
        <input type="text" class="form-control" id="mkReqNote" placeholder="Note (optional)" style="max-width:260px;">
        <button type="button" class="btn btn-primary" id="mkReqSubmit">Request</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle" id="mkReqTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>From</th>
            <th>To</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th>Note</th>
            <th>Requested By</th>
            <th class="text-end">Actions</th>
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
                $can_act = ((string)($r['status'] ?? '') === 'pending') && (int)($r['payer_id'] ?? 0) === $my_id && in_array($role, ['master','admin'], true);
              ?>
              <tr data-id="<?php echo (int)$r['id']; ?>">
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars((string)($r['created_at'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string)($r['payer_name'] ?? '-')); ?></td>
                <td><?php echo htmlspecialchars((string)($r['payee_name'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['amount'] ?? 0), 2); ?></td>
                <td><span class="badge bg-<?php echo (string)($r['status'] ?? '') === 'completed' ? 'success' : ((string)($r['status'] ?? '') === 'failed' ? 'danger' : 'warning'); ?>"><?php echo htmlspecialchars((string)($r['status'] ?? '')); ?></span></td>
                <td><?php echo htmlspecialchars((string)($r['note'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string)($r['creator_name'] ?? '')); ?></td>
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
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="9" class="text-center text-body-secondary py-4">No requests found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  var csrf = '<?php echo htmlspecialchars(csrf_token()); ?>';

  function attachActionHandlers() {
    var btns = document.querySelectorAll('.mk-pay-act');
    for (var i = 0; i < btns.length; i++) {
      btns[i].addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var act = this.getAttribute('data-action');
        if (!id || !act) return;
        if (act === 'reject' && !confirm('Reject this request?')) return;
        var btn = this;
        var fd = new FormData();
        fd.append('action', act);
        fd.append('payment_id', String(id));
        fetch('<?php echo $admin_base; ?>api/payment_action.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': csrf }
        }).then(function(r){ return r.json(); })
        .then(function(json){
          if (!json || !json.success) { alert((json && json.message) ? json.message : 'Failed'); return; }
          var tr = btn.closest('tr');
          if (!tr) return;
          var status = json.status || '';
          var badge = tr.querySelector('td:nth-child(6) .badge');
          if (badge) {
            badge.textContent = status;
            badge.className = 'badge ' + (status === 'completed' ? 'bg-success' : (status === 'failed' ? 'bg-danger' : 'bg-warning'));
          }
          var actionsTd = tr.querySelector('td:nth-child(9)');
          if (actionsTd) actionsTd.innerHTML = '<span class="text-body-secondary">-</span>';
        }).catch(function(){ alert('Network error'); });
      });
    }
  }

  attachActionHandlers();

  var btn = document.getElementById('mkReqSubmit');
  if (!btn) return;
  btn.addEventListener('click', function() {
    var amtEl = document.getElementById('mkReqAmount');
    var noteEl = document.getElementById('mkReqNote');
    var amt = Number(amtEl && amtEl.value ? amtEl.value : 0);
    var note = noteEl && noteEl.value ? noteEl.value : '';
    if (!isFinite(amt) || amt <= 0) return alert('Enter valid amount.');
    btn.disabled = true;
    var fd = new FormData();
    fd.append('amount', String(amt));
    fd.append('note', note);
    fetch('<?php echo $admin_base; ?>api/balance_request_create.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf }
    }).then(function(r){ return r.json(); })
    .then(function(json){
      if (!json || !json.success) { alert((json && json.message) ? json.message : 'Failed'); return; }
      alert('Request submitted.');
      location.reload();
    }).catch(function(){ alert('Network error'); })
    .finally(function(){ btn.disabled = false; });
  });
})();
</script>

<?php require '../includes/footer.php'; ?>

