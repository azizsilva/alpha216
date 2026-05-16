<?php
$mk_fragment = isset($_GET['mk_fragment']);
if (!$mk_fragment) {
    require_once __DIR__ . '/../app/index.php';
    exit;
}
require_once '../includes/db.php';
require_once '../includes/header.php';
require_once '../includes/profile-header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'player') {
    header("Location: ../login.php");
    exit;
}
?>

<script>
  document.body.classList.add('mk-account-mode');
</script>

<div class="mk-account-page">
  <div class="mk-account-layout">
    <aside class="mk-account-sidebar">
      <div class="mk-side-title" data-translate="profile">PROFILE</div>
      <ul class="mk-side-menu">
        <li><a href="<?php echo htmlspecialchars($base_url); ?>account-details/"><i class="fa fa-user"></i> <span data-translate="account_detail">ACCOUNT DETAILS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>account-statement/"><i class="fa fa-file-text-o"></i> <span data-translate="account_statement">ACCOUNT STATEMENT</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>profit-loss/"><i class="fa fa-line-chart"></i> <span data-translate="profit_loss">PROFIT AND LOSS</span></a></li>
        <li class="active"><a href="<?php echo htmlspecialchars($base_url); ?>bet-history/"><i class="fa fa-history"></i> <span data-translate="bet_history">BET HISTORY</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>activity-log/"><i class="fa fa-list"></i> <span data-translate="activity_log">ACTIVITY LOG</span></a></li>
      </ul>
    </aside>

    <main class="mk-account-content">
      <div class="mk-account-inner">
        <div class="mk-bet-card">
          <div class="mk-bet-head">
            <div data-translate="bet_history">Bet History</div>
            <div class="mk-bet-filters">
              <select id="bhAction" class="form-select form-select-sm">
                <option value="" data-translate="all">All</option>
                <option value="bet" data-translate="bet">Bet</option>
                <option value="win" data-translate="win">Win</option>
                <option value="refund" data-translate="refund">Refund</option>
              </select>
              <input id="bhFrom" type="date" class="form-control form-control-sm" />
              <input id="bhTo" type="date" class="form-control form-control-sm" />
              <button id="bhRefresh" class="btn btn-sm mk-bet-refresh" data-translate="refresh">Refresh</button>
            </div>
          </div>
          <div class="mk-bet-body">
            <div class="table-responsive mk-bet-table-wrap">
              <table class="table table-sm table-striped mk-bet-table" id="bhTable">
                <thead>
                  <tr>
                    <th data-translate="date">Date</th>
                    <th data-translate="action">Action</th>
                    <th data-translate="game">Game</th>
                    <th data-translate="txn">Txn</th>
                    <th data-translate="bet">Bet</th>
                    <th data-translate="win">Win</th>
                    <th data-translate="delta">Delta</th>
                    <th data-translate="before">Before</th>
                    <th data-translate="after">After</th>
                    <th data-translate="status">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="10" class="text-center" data-translate="loading">Loading...</td></tr>
                </tbody>
              </table>
            </div>
            <div class="mk-bet-pager">
              <div id="bhMeta" class="text-muted small"></div>
              <div class="d-flex gap-2">
                <button id="bhPrev" class="btn btn-outline-secondary btn-sm" data-translate="prev">Prev</button>
                <button id="bhNext" class="btn btn-outline-secondary btn-sm" data-translate="next">Next</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<style>
.mk-bet-card {
  background: #0f172a;
  border: 1px solid rgba(148,163,184,0.22);
  border-radius: 10px;
  color: #e5e7eb;
  overflow: hidden;
  box-shadow: 0 18px 45px rgba(0,0,0,0.22);
}
.mk-bet-head {
  min-height: 58px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  background: #000;
  border-bottom: 1px solid rgba(195,118,1,0.45);
  color: #fff;
  font-weight: 900;
}
.mk-bet-filters {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.mk-bet-filters .form-control,
.mk-bet-filters .form-select {
  min-width: 140px;
  height: 36px;
  border: 1px solid rgba(148,163,184,0.34);
  background: #111827;
  color: #f8fafc;
}
.mk-bet-refresh {
  height: 36px;
  background: #c37601;
  border: 1px solid #c37601;
  color: #000;
  font-weight: 900;
}
.mk-bet-body { padding: 14px; }
.mk-bet-table-wrap {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.mk-bet-table {
  min-width: 980px;
  color: #e5e7eb;
  margin-bottom: 0;
}
.mk-bet-table th,
.mk-bet-table td {
  border-color: rgba(148,163,184,0.18) !important;
  white-space: nowrap;
}
.mk-bet-table th {
  background: #111827;
  color: #f8fafc;
}
.mk-bet-table.table-striped > tbody > tr:nth-of-type(odd),
.mk-bet-table.table-striped > tbody > tr:nth-of-type(even),
.mk-bet-table tbody tr {
  background: #0f172a !important;
}
.mk-bet-pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding-top: 14px;
}
@media (max-width: 767px) {
  .mk-bet-card {
    border-left: 0;
    border-right: 0;
    border-radius: 6px;
  }
  .mk-bet-head {
    align-items: stretch;
    flex-direction: column;
  }
  .mk-bet-filters {
    display: grid;
    grid-template-columns: 1fr;
  }
  .mk-bet-filters .form-control,
  .mk-bet-filters .form-select,
  .mk-bet-refresh {
    width: 100%;
    min-width: 0;
  }
  .mk-bet-body { padding: 10px; }
  .mk-bet-table { min-width: 900px; }
}
</style>

<script>
  (function () {
    var limit = 50;
    var offset = 0;
    var tableBody = document.querySelector('#bhTable tbody');
    var meta = document.getElementById('bhMeta');
    var btnPrev = document.getElementById('bhPrev');
    var btnNext = document.getElementById('bhNext');
    var btnRefresh = document.getElementById('bhRefresh');

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
      });
    }

    function getLang() {
      if (typeof isLoggedIn !== 'undefined' && isLoggedIn && typeof sessionLang !== 'undefined' && sessionLang) return sessionLang;
      return localStorage.getItem('selected_language') || 'en';
    }

    function t(key, fallback) {
      var lang = getLang();
      if (typeof translations !== 'undefined' && translations[lang] && translations[lang][key]) return translations[lang][key];
      return fallback;
    }

    function load() {
      var action = document.getElementById('bhAction').value;
      var from = document.getElementById('bhFrom').value;
      var to = document.getElementById('bhTo').value;
      var qs = new URLSearchParams({limit: String(limit), offset: String(offset)});
      if (action) qs.set('action', action);
      if (from) qs.set('from', from);
      if (to) qs.set('to', to);

      tableBody.innerHTML = '<tr><td colspan="10" class="text-center">' + esc(t('loading', 'Loading...')) + '</td></tr>';
      fetch('../api/bet_history.php?' + qs.toString(), {credentials: 'same-origin'})
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json || !json.success) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center">' + esc(t('no_data', 'No data')) + '</td></tr>';
            meta.textContent = '';
            return;
          }
          var rows = json.rows || [];
          if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center">' + esc(t('no_records_found', 'No records found')) + '</td></tr>';
          } else {
            tableBody.innerHTML = rows.map(function (r) {
              var st = (r.result_status == 1) ? t('ok', 'OK') : t('fail', 'FAIL');
              return '<tr>' +
                '<td>' + esc(r.created_at) + '</td>' +
                '<td>' + esc(String(r.action || '').toUpperCase()) + '</td>' +
                '<td>' + esc(r.game_name || r.game_uid || '-') + '</td>' +
                '<td>' + esc(r.txn_id || '-') + '</td>' +
                '<td>' + esc(r.bet_amount) + '</td>' +
                '<td>' + esc(r.win_amount) + '</td>' +
                '<td>' + esc(r.amount_delta) + '</td>' +
                '<td>' + esc(r.balance_before) + '</td>' +
                '<td>' + esc(r.balance_after) + '</td>' +
                '<td>' + esc(st) + '</td>' +
              '</tr>';
            }).join('');
          }
          var start = json.total ? (json.offset + 1) : 0;
          var end = Math.min(json.offset + json.limit, json.total || 0);
          meta.textContent = t('showing', 'Showing') + ' ' + start + '-' + end + ' ' + t('of', 'of') + ' ' + (json.total || 0);
          btnPrev.disabled = offset <= 0;
          btnNext.disabled = (offset + limit) >= (json.total || 0);
        })
        .catch(function () {
          tableBody.innerHTML = '<tr><td colspan="10" class="text-center">' + esc(t('error_loading', 'Error loading')) + '</td></tr>';
        });
    }

    btnPrev.addEventListener('click', function () {
      offset = Math.max(0, offset - limit);
      load();
    });
    btnNext.addEventListener('click', function () {
      offset = offset + limit;
      load();
    });
    btnRefresh.addEventListener('click', function () {
      offset = 0;
      load();
    });
    load();
  })();
</script>

<?php require_once '../includes/footer.php'; ?>
