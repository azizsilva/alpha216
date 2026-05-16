<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/profile-header.php';
?>

<script>document.body.classList.add('mk-account-mode');</script>

<div class="mk-account-page">
  <div class="mk-account-layout">
    <aside class="mk-account-sidebar">
      <div class="mk-side-title" data-translate="profile">PROFILE</div>
      <ul class="mk-side-menu">
        <li><a href="<?php echo htmlspecialchars($base_url); ?>account-details/"><i class="fa fa-user"></i> <span data-translate="account_detail">ACCOUNT DETAILS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>account-statement/"><i class="fa fa-file-text-o"></i> <span data-translate="account_statement">ACCOUNT STATEMENT</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>profit-loss/"><i class="fa fa-line-chart"></i> <span data-translate="profit_loss">PROFIT AND LOSS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>bet-history/"><i class="fa fa-history"></i> <span data-translate="bet_history">BET HISTORY</span></a></li>
        <li class="active"><a href="<?php echo htmlspecialchars($base_url); ?>activity-log/"><i class="fa fa-list"></i> <span data-translate="activity_log">ACTIVITY LOG</span></a></li>
      </ul>
    </aside>

    <main class="mk-account-content">
      <div class="mk-account-inner">
        <div class="mk-bet-card">
          <div class="mk-bet-head">
            <div data-translate="activity_log">Activity Log</div>
            <div class="mk-bet-filters">
              <select id="alKind" class="form-control form-control-sm">
                <option value="all" data-translate="all">All</option>
                <option value="account">Account</option>
                <option value="game">Game</option>
                <option value="profile">Profile</option>
              </select>
              <input id="alFrom" type="date" class="form-control form-control-sm">
              <input id="alTo" type="date" class="form-control form-control-sm">
              <button id="alRefresh" class="btn btn-sm mk-bet-refresh" type="button" data-translate="apply">Apply</button>
            </div>
          </div>
          <div class="mk-bet-body">
            <div class="table-responsive mk-bet-table-wrap">
              <table class="table table-sm table-striped mk-bet-table" id="alTable">
                <thead>
                  <tr>
                    <th data-translate="date_time">Date/Time</th>
                    <th>Type</th>
                    <th data-translate="action">Action</th>
                    <th data-translate="game">Game</th>
                    <th>Amount</th>
                    <th>IP</th>
                  </tr>
                </thead>
                <tbody><tr><td colspan="6" class="text-center" data-translate="loading">Loading...</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<style>
.mk-bet-card{background:#0f172a;border:1px solid rgba(148,163,184,.22);border-radius:10px;color:#e5e7eb;overflow:hidden;box-shadow:0 18px 45px rgba(0,0,0,.22)}
.mk-bet-head{min-height:58px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;background:#000;border-bottom:1px solid rgba(195,118,1,.45);color:#fff;font-weight:900}
.mk-bet-filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.mk-bet-filters .form-control{min-width:140px;height:36px;border:1px solid rgba(148,163,184,.34);background:#111827;color:#f8fafc}
.mk-bet-refresh{height:36px;background:#c37601;border:1px solid #c37601;color:#000;font-weight:900}
.mk-bet-body{padding:14px}.mk-bet-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
.mk-bet-table{min-width:860px;color:#e5e7eb;margin-bottom:0}.mk-bet-table th,.mk-bet-table td{border-color:rgba(148,163,184,.18)!important;white-space:nowrap}.mk-bet-table th{background:#111827;color:#f8fafc}.mk-bet-table.table-striped>tbody>tr:nth-of-type(odd),.mk-bet-table.table-striped>tbody>tr:nth-of-type(even),.mk-bet-table tbody tr{background:#0f172a!important}
@media(max-width:767px){.mk-bet-card{border-left:0;border-right:0;border-radius:6px}.mk-bet-head{align-items:stretch;flex-direction:column}.mk-bet-filters{display:grid;grid-template-columns:1fr}.mk-bet-filters .form-control,.mk-bet-refresh{width:100%;min-width:0}.mk-bet-body{padding:10px}}
</style>

<script>
(function(){
  var body = document.querySelector('#alTable tbody');
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});}
  function load(){
    var qs = new URLSearchParams();
    qs.set('kind', document.getElementById('alKind').value || 'all');
    var f = document.getElementById('alFrom').value;
    var t = document.getElementById('alTo').value;
    if(f) qs.set('from', f);
    if(t) qs.set('to', t);
    body.innerHTML = '<tr><td colspan="6" class="text-center">Loading...</td></tr>';
    fetch('../api/activity_log.php?' + qs.toString(), {credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(json){
        if(!json || !json.success){ body.innerHTML = '<tr><td colspan="6" class="text-center">No data</td></tr>'; return; }
        var rows = json.rows || [];
        if(!rows.length){ body.innerHTML = '<tr><td colspan="6" class="text-center">No records found</td></tr>'; return; }
        body.innerHTML = rows.map(function(r){
          return '<tr><td>'+esc(r.created_at)+'</td><td>'+esc(r.kind)+'</td><td>'+esc(r.action)+'</td><td>'+esc(r.detail || '-')+'</td><td>'+esc(r.amount || '-')+'</td><td>'+esc(r.ip || '-')+'</td></tr>';
        }).join('');
      })
      .catch(function(){ body.innerHTML = '<tr><td colspan="6" class="text-center">Error loading</td></tr>'; });
  }
  document.getElementById('alRefresh').addEventListener('click', load);
  load();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
