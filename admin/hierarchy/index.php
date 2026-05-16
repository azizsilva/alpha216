<?php
$admin_base = '../';
require '../includes/db.php';
require '../includes/auth.php';

require_admin_login($admin_base);
require_admin_role(['admin', 'master', 'agent'], $admin_base);

$page_title = 'Hierarchy';
require '../includes/header.php';

$role = current_admin_role();
$my_id = (int)current_admin_id();
?>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h5 class="mb-1">Hierarchy</h5>
      <div class="text-body-secondary">Expandable user tree</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <input id="treeSearch" type="text" class="form-control" placeholder="Search by ID / Username" style="min-width: 240px;">
      <button id="treeRefresh" class="btn btn-outline-primary" type="button">Refresh</button>
    </div>
  </div>

  <div class="card-body">
    <div id="treeMeta" class="text-body-secondary mb-3"></div>
    <div id="treeRoot"></div>
  </div>
</div>

<script>
  (function () {
    var role = <?php echo json_encode($role, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var myId = <?php echo json_encode($my_id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var root = document.getElementById('treeRoot');
    var meta = document.getElementById('treeMeta');
    var searchInput = document.getElementById('treeSearch');
    var refreshBtn = document.getElementById('treeRefresh');

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
      });
    }

    function nextRole(r) {
      if (r === 'admin') return 'master';
      if (r === 'master') return 'agent';
      if (r === 'agent') return 'player';
      return '';
    }

    function canExpand(nodeRole) {
      return nodeRole === 'admin' || nodeRole === 'master' || nodeRole === 'agent';
    }

    function badge(role) {
      if (role === 'master') return '<span class="badge bg-label-warning text-dark">MASTER</span>';
      if (role === 'agent') return '<span class="badge bg-label-primary">AGENT</span>';
      if (role === 'player') return '<span class="badge bg-label-secondary">PLAYER</span>';
      return '<span class="badge bg-label-success">ADMIN</span>';
    }

    function statusBadge(st) {
      var u = String(st || 'active').toUpperCase();
      if (u === 'ACTIVE') return '<span class="badge bg-label-success">ACTIVE</span>';
      if (u === 'LOCKED') return '<span class="badge bg-label-danger">LOCKED</span>';
      if (u === 'SUSPENDED') return '<span class="badge bg-label-warning text-dark">SUSPENDED</span>';
      return '<span class="badge bg-label-secondary">' + esc(u) + '</span>';
    }

    function nodeRow(n, parentRole) {
      var hasChildren = canExpand(n.role) && Number(n.children_count || 0) > 0;
      var btn = hasChildren ? '<button type="button" class="btn btn-sm btn-outline-primary mk-node-toggle" data-role="' + esc(n.role) + '" data-id="' + esc(n.id) + '">Expand</button>' : '';
      var bal = (n.balance != null) ? Number(n.balance).toFixed(2) : '0.00';
      var exp = (n.exposure != null) ? Number(n.exposure).toFixed(2) : '0.00';
      var childLabel = nextRole(n.role);
      var childCount = Number(n.children_count || 0);

      return '' +
        '<div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">' +
          '<div class="d-flex align-items-center gap-2 flex-wrap">' +
            '<div class="fw-semibold">#' + esc(n.id) + ' ' + esc(n.username) + '</div>' +
            badge(n.role) +
            statusBadge(n.status) +
            (childLabel && childCount ? ('<span class="text-body-secondary">(' + childCount + ' ' + esc(childLabel.toUpperCase()) + (childCount === 1 ? '' : 'S') + ')</span>') : '') +
          '</div>' +
          '<div class="d-flex align-items-center gap-2 flex-wrap">' +
            '<span class="text-body-secondary">Bal:</span><span class="fw-semibold">' + esc(bal) + '</span>' +
            '<span class="text-body-secondary">Exp:</span><span class="fw-semibold">' + esc(exp) + '</span>' +
            btn +
          '</div>' +
        '</div>' +
        '<div class="mk-node-children ps-3 mt-2 mb-2" data-parent-role="' + esc(n.role) + '" data-parent-id="' + esc(n.id) + '" style="display:none;"></div>';
    }

    function fetchNodes(nodeRole, nodeId, q) {
      var params = new URLSearchParams();
      params.set('role', nodeRole);
      params.set('id', String(nodeId));
      if (q) params.set('q', q);
      return fetch('./tree.php?' + params.toString(), { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.json(); });
    }

    function renderRoot() {
      var q = searchInput.value.trim();
      root.innerHTML = '<div class="text-body-secondary">Loading...</div>';
      meta.textContent = '';
      var baseRole = role === 'admin' ? 'admin' : role;
      var baseId = role === 'admin' ? 0 : myId;

      fetchNodes(baseRole, baseId, q)
        .then(function (json) {
          if (!json || !json.success) {
            root.innerHTML = '<div class="text-danger">Failed to load</div>';
            return;
          }
          var nodes = json.nodes || [];
          meta.textContent = 'Showing ' + nodes.length + ' nodes';
          if (!nodes.length) {
            root.innerHTML = '<div class="text-body-secondary">No users found.</div>';
            return;
          }
          root.innerHTML = nodes.map(function (n) { return nodeRow(n, baseRole); }).join('');
        })
        .catch(function () {
          root.innerHTML = '<div class="text-danger">Error loading</div>';
        });
    }

    root.addEventListener('click', function (e) {
      var btn = e.target.closest('.mk-node-toggle');
      if (!btn) return;
      var pr = btn.getAttribute('data-role') || '';
      var pid = btn.getAttribute('data-id') || '';
      var container = root.querySelector('.mk-node-children[data-parent-role="' + pr + '"][data-parent-id="' + pid + '"]');
      if (!container) return;

      var open = container.style.display !== 'none';
      if (open) {
        container.style.display = 'none';
        btn.textContent = 'Expand';
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Loading...';
      container.style.display = 'block';
      container.innerHTML = '<div class="text-body-secondary">Loading...</div>';

      fetchNodes(pr, pid, '')
        .then(function (json) {
          if (!json || !json.success) {
            container.innerHTML = '<div class="text-danger">Failed</div>';
            return;
          }
          var nodes = json.nodes || [];
          if (!nodes.length) {
            container.innerHTML = '<div class="text-body-secondary">No children</div>';
            return;
          }
          container.innerHTML = nodes.map(function (n) { return nodeRow(n, pr); }).join('');
        })
        .catch(function () {
          container.innerHTML = '<div class="text-danger">Error</div>';
        })
        .finally(function () {
          btn.disabled = false;
          btn.textContent = 'Collapse';
        });
    });

    refreshBtn.addEventListener('click', renderRoot);
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') renderRoot();
    });

    renderRoot();
  })();
</script>

<?php require '../includes/footer.php'; ?>

