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
        <li class="active"><a href="<?php echo htmlspecialchars($base_url); ?>profit-loss/"><i class="fa fa-line-chart"></i> <span data-translate="profit_loss">PROFIT AND LOSS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>bet-history/"><i class="fa fa-history"></i> <span data-translate="bet_history">BET HISTORY</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>activity-log/"><i class="fa fa-list"></i> <span data-translate="activity_log">ACTIVITY LOG</span></a></li>
      </ul>
    </aside>

    <main class="mk-account-content">
      <div class="mk-account-inner">
        <div class="mk-bet-card">
          <div class="mk-bet-head">
            <div data-translate="profit_loss">Profit And Loss</div>
            <div class="mk-bet-filters">
              <input id="plGame" type="text" class="form-control form-control-sm" placeholder="Game UID">
              <input id="plFrom" type="date" class="form-control form-control-sm">
              <input id="plTo" type="date" class="form-control form-control-sm">
              <button id="plRefresh" class="btn btn-sm mk-bet-refresh" type="button" data-translate="apply">Apply</button>
            </div>
          </div>
          <div class="mk-pl-summary" id="plSummary">
            <div><span>Total Bet</span><strong>0.00</strong></div>
            <div><span>Total Win</span><strong>0.00</strong></div>
            <div><span>Refund</span><strong>0.00</strong></div>
            <div><span>Net P/L</span><strong>0.00</strong></div>
          </div>
          <div class="mk-bet-body">
            <div class="table-responsive mk-bet-table-wrap">
              <table class="table table-sm table-striped mk-bet-table" id="plTable">
                <thead>
                  <tr>
                    <th data-translate="date">Date</th>
                    <th data-translate="game">Game</th>
                    <th>Total Bet</th>
                    <th>Total Win</th>
                    <th>Refund</th>
                    <th>Net P/L</th>
                    <th>Events</th>
                  </tr>
                </thead>
                <tbody><tr><td colspan="7" class="text-center" data-translate="loading">Loading...</td></tr></tbody>
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
.mk-pl-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;background:rgba(148,163,184,.16);border-bottom:1px solid rgba(148,163,184,.16)}
.mk-pl-summary div{background:#111827;padding:12px 14px;display:flex;flex-direction:column;gap:5px}
.mk-pl-summary span{color:#94a3b8;font-size:12px;font-weight:800;text-transform:uppercase}
.mk-pl-summary strong{color:#fff;font-size:18px}
.mk-bet-body{padding:14px}
.mk-bet-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
.mk-bet-table{min-width:820px;color:#e5e7eb;margin-bottom:0}
.mk-bet-table th,.mk-bet-table td{border-color:rgba(148,163,184,.18)!important;white-space:nowrap}
.mk-bet-table th{background:#111827;color:#f8fafc}
.mk-bet-table.table-striped>tbody>tr:nth-of-type(odd),.mk-bet-table.table-striped>tbody>tr:nth-of-type(even),.mk-bet-table tbody tr{background:#0f172a!important}
.mk-pl-pos{color:#10b981;font-weight:900}.mk-pl-neg{color:#ef4444;font-weight:900}
@media(max-width:767px){.mk-bet-card{border-left:0;border-right:0;border-radius:6px}.mk-bet-head{align-items:stretch;flex-direction:column}.mk-bet-filters{display:grid;grid-template-columns:1fr}.mk-bet-filters .form-control,.mk-bet-refresh{width:100%;min-width:0}.mk-pl-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.mk-bet-body{padding:10px}}
</style>

<script>
(function(){
  var body = document.querySelector('#plTable tbody');
  var summary = document.getElementById('plSummary');
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});}
  function num(v){var n=Number(v);return isFinite(n)?n:0;}
  function fmt(v){return num(v).toFixed(2);}
  function netClass(v){return num(v)>=0?'mk-pl-pos':'mk-pl-neg';}
  function load(){
    var qs = new URLSearchParams();
    var g = document.getElementById('plGame').value.trim();
    var f = document.getElementById('plFrom').value;
    var t = document.getElementById('plTo').value;
    if(g) qs.set('game_uid', g);
    if(f) qs.set('from', f);
    if(t) qs.set('to', t);
    body.innerHTML = '<tr><td colspan="7" class="text-center">Loading...</td></tr>';
    fetch('../api/profit_loss.php?' + qs.toString(), {credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(json){
        if(!json || !json.success){ body.innerHTML = '<tr><td colspan="7" class="text-center">No data</td></tr>'; return; }
        var totals = json.totals || {};
        var cells = summary.querySelectorAll('strong');
        cells[0].textContent = fmt(totals.total_bet);
        cells[1].textContent = fmt(totals.total_win);
        cells[2].textContent = fmt(totals.total_refund);
        cells[3].textContent = fmt(totals.net_pl);
        cells[3].className = netClass(totals.net_pl);
        var rows = json.rows || [];
        if(!rows.length){ body.innerHTML = '<tr><td colspan="7" class="text-center">No records found</td></tr>'; return; }
        body.innerHTML = rows.map(function(r){
          return '<tr><td>'+esc(r.play_date)+'</td><td>'+esc(r.game_name || r.game_uid)+'</td><td>'+esc(fmt(r.total_bet))+'</td><td>'+esc(fmt(r.total_win))+'</td><td>'+esc(fmt(r.total_refund))+'</td><td class="'+netClass(r.net_pl)+'">'+esc(fmt(r.net_pl))+'</td><td>'+esc(r.events)+'</td></tr>';
        }).join('');
      })
      .catch(function(){ body.innerHTML = '<tr><td colspan="7" class="text-center">Error loading</td></tr>'; });
  }
  document.getElementById('plRefresh').addEventListener('click', load);
  load();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
