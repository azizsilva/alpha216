<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';

require_admin_login($admin_base);
require_admin_role(['admin','agent'], $admin_base);

$page_title = 'Withdraw Requests';
require '../includes/header.php';

$role = (string)current_admin_role();
$my_id = (int)current_admin_id();

$where = "WHERE p.type='withdrawal' AND p.status IN ('pending','completed','failed','reversed')";
$params = [];

if ($role === 'agent') {
    $where .= " AND p.payee_id = ?";
    $params[] = $my_id;
} elseif ($role === 'admin') {
    $where .= " AND (p.payee_id IS NULL OR p.payee_id = 0)";
}

$sql = "SELECT p.*, u1.username AS payer_name, u2.username AS payee_name
        FROM payments p
        LEFT JOIN users u1 ON u1.id = p.payer_id
        LEFT JOIN users u2 ON u2.id = p.payee_id
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
      <h5 class="mb-1">Withdraw Requests</h5>
      <div class="text-body-secondary"><?php echo $role === 'agent' ? 'Requests from your players' : 'Requests from admin-side users'; ?></div>
    </div>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle" id="mkWdReqTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>User</th>
            <th>Payee</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th>Bank</th>
            <th>Account</th>
            <th>IFSC</th>
            <th>Holder</th>
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
                $st = (string)($r['status'] ?? '');
                $can_act = $st === 'pending' && (($role === 'agent' && (int)($r['payee_id'] ?? 0) === $my_id) || ($role === 'admin' && (int)($r['payee_id'] ?? 0) === 0));
                $acc = (string)($meta['account_no'] ?? '');
                $last4 = $acc !== '' ? substr($acc, max(0, strlen($acc) - 4)) : '';
              ?>
              <tr data-id="<?php echo (int)$r['id']; ?>">
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars((string)($r['created_at'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string)($r['payer_name'] ?? '-')); ?></td>
                <td><?php echo htmlspecialchars((string)($r['payee_name'] ?? ($role === 'admin' ? 'Admin' : '-'))); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['amount'] ?? 0), 2); ?></td>
                <td><span class="badge bg-<?php echo $st === 'completed' ? 'success' : ($st === 'failed' ? 'danger' : 'warning'); ?>"><?php echo htmlspecialchars($st); ?></span></td>
                <td><?php echo htmlspecialchars((string)($meta['bank_name'] ?? '')); ?></td>
                <td><?php echo $last4 !== '' ? 'XXXX' . htmlspecialchars($last4) : htmlspecialchars($acc); ?></td>
                <td><?php echo htmlspecialchars((string)($meta['ifsc_swift'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string)($meta['account_holder'] ?? '')); ?></td>
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
            <tr><td colspan="11" class="text-center text-body-secondary py-4">No withdraw requests found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function() {
  var csrf = '<?php echo htmlspecialchars(csrf_token()); ?>';
  var btns = document.querySelectorAll('.mk-pay-act');
  for (var i = 0; i < btns.length; i++) {
    btns[i].addEventListener('click', function() {
      var id = this.getAttribute('data-id');
      var act = this.getAttribute('data-action');
      if (!id || !act) return;
      if (act === 'reject' && !confirm('Reject this withdraw request?')) return;
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
        var actionsTd = tr.querySelector('td:nth-child(11)');
        if (actionsTd) actionsTd.innerHTML = '<span class="text-body-secondary">-</span>';
      }).catch(function(){ alert('Network error'); });
    });
  }
})();
</script>

<?php require '../includes/footer.php'; ?>

