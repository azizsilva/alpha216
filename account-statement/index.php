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

$user_id = (int)($_SESSION['user_id'] ?? 0);
$username = (string)($_SESSION['username'] ?? '');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/profile-header.php';
?>

<script>
  document.body.classList.add('mk-account-mode');
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('.mk-side-menu a') : null;
    if (!a) return;
    a.classList.remove('mk-shine');
    void a.offsetWidth;
    a.classList.add('mk-shine');
  });
</script>

<div class="mk-account-page">
  <div class="mk-account-layout">
    <aside class="mk-account-sidebar">
      <div class="mk-side-title" data-translate="profile">PROFILE</div>
      <ul class="mk-side-menu">
        <li><a href="<?php echo htmlspecialchars($base_url); ?>account-details/"><i class="fa fa-user"></i> <span data-translate="account_detail">ACCOUNT DETAILS</span></a></li>
        <li class="active"><a href="<?php echo htmlspecialchars($base_url); ?>account-statement/"><i class="fa fa-file-text-o"></i> <span data-translate="account_statement">ACCOUNT STATEMENT</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>profit-loss/"><i class="fa fa-line-chart"></i> <span data-translate="profit_loss">PROFIT AND LOSS</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>bet-history/"><i class="fa fa-history"></i> <span data-translate="bet_history">BET HISTORY</span></a></li>
        <li><a href="<?php echo htmlspecialchars($base_url); ?>activity-log/"><i class="fa fa-list"></i> <span data-translate="activity_log">ACTIVITY LOG</span></a></li>
      </ul>
    </aside>

    <main class="mk-account-content">
      <div class="mk-account-inner">
        <div class="mk-statement-card">
          <div class="mk-statement-filters">
            <div class="mk-filter-row">
              <div class="mk-filter">
                <span data-translate="wallet_type">Wallet Type:</span>
                <select id="stWallet" class="form-control input-sm">
                  <option value="all" data-translate="all">All</option>
                  <option value="main" data-translate="main_wallet">Main Wallet</option>
                  <option value="casino" data-translate="casino_wallet">Casino Wallet</option>
                </select>
              </div>

              <div class="mk-filter">
                <span data-translate="txn_type">Txn Type:</span>
                <select id="stTxnType" class="form-control input-sm">
                  <option value="all" data-translate="all">All</option>
                  <option value="deposit" data-translate="credit">Credit</option>
                  <option value="withdrawal" data-translate="balance_txn">Balance</option>
                </select>
              </div>

              <div class="mk-filter mk-date-filter">
                <span data-translate="date">Date:</span>
                <div class="mk-date-range">
                  <input id="stFrom" type="date" class="form-control input-sm" />
                  <span class="mk-date-sep">-</span>
                  <input id="stTo" type="date" class="form-control input-sm" />
                </div>
              </div>

              <button id="stApply" class="btn mk-apply-btn" type="button" data-translate="apply">Apply</button>
            </div>

            <div class="mk-export-row">
              <button id="stPdf" class="btn mk-export-btn" type="button" aria-label="PDF">
                <img class="mk-export-img" src="https://moneyking365.com/assets/images/pdf-icon.png" alt="PDF">
              </button>
              <button id="stXls" class="btn mk-export-btn" type="button" aria-label="Excel">
                <img class="mk-export-img" src="https://moneyking365.com/assets/images/excel-icon.png" alt="Excel">
              </button>
            </div>
          </div>

          <div class="mk-statement-table-wrap">
            <table class="table table-bordered table-striped mk-statement-table" id="stTable">
              <thead>
                <tr>
                  <th class="mk-sortable" data-col="0" data-type="number"><span class="mk-th-label" data-translate="deposit">Deposit</span><span class="mk-sort" aria-hidden="true"></span></th>
                  <th class="mk-sortable" data-col="1" data-type="number"><span class="mk-th-label" data-translate="withdraw">Withdraw</span><span class="mk-sort" aria-hidden="true"></span></th>
                  <th class="mk-sortable" data-col="2" data-type="number"><span class="mk-th-label" data-translate="balance">Balance</span><span class="mk-sort" aria-hidden="true"></span></th>
                  <th class="mk-sortable" data-col="3" data-type="text"><span class="mk-th-label" data-translate="remark">Remark</span><span class="mk-sort" aria-hidden="true"></span></th>
                  <th class="mk-sortable" data-col="4" data-type="date"><span class="mk-th-label" data-translate="date_time">Date/Time</span><span class="mk-sort" aria-hidden="true"></span></th>
                  <th class="mk-sortable" data-col="5" data-type="number"><span class="mk-th-label" data-translate="old_balance">Old Balance</span><span class="mk-sort" aria-hidden="true"></span></th>
                  <th class="mk-sortable" data-col="6" data-type="number"><span class="mk-th-label" data-translate="credit_reference">Credit Reference</span><span class="mk-sort" aria-hidden="true"></span></th>
                  <th class="mk-sortable" data-col="7" data-type="number"><span class="mk-th-label" data-translate="old_credit_reference">Old Credit Reference</span><span class="mk-sort" aria-hidden="true"></span></th>
                  <th class="mk-sortable" data-col="8" data-type="number"><span class="mk-th-label" data-translate="ref_pl">Ref.P/L</span><span class="mk-sort" aria-hidden="true"></span></th>
                </tr>
              </thead>
              <tbody id="stBody">
              </tbody>
            </table>

            <div class="mk-empty" id="stEmpty" style="display:none;">
              <img class="mk-empty-img" src="https://moneyking365.com/assets/images/norecode.png" alt="No record found">
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<style>
.mk-statement-card {
  background: #0f172a;
  border: 1px solid rgba(148,163,184,0.22);
  border-radius: 10px;
  box-shadow: 0 18px 45px rgba(0,0,0,0.22);
  color: #e5e7eb;
  overflow: hidden;
}
.mk-statement-filters {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 14px 10px;
}
.mk-filter-row {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  flex: 1 1 auto;
}
.mk-filter {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.mk-filter > span {
  color: #f8fafc;
  font-weight: 700;
}
.mk-filter select.form-control,
.mk-date-range input.form-control {
  height: 36px;
  border-radius: 6px;
  border: 1px solid rgba(148,163,184,0.34);
  background: #111827;
  color: #f8fafc;
  min-width: 140px;
}
.mk-date-filter { flex: 1 1 auto; }
.mk-date-range {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.mk-date-range input { min-width: 170px; }
.mk-date-sep { color: #94a3b8; font-weight: 800; }
.mk-apply-btn {
  height: 36px;
  background: #c37601;
  border: 1px solid #c37601;
  color: #000;
  font-weight: 800;
  border-radius: 6px;
  padding: 0 16px;
}
.mk-apply-btn:hover { color: #000; filter: brightness(1.05); }
.mk-export-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 0 0 auto;
}
.mk-export-btn {
  width: 38px;
  height: 38px;
  padding: 0;
  border-radius: 8px;
  border: 0;
  background: transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.mk-export-img {
  width: 28px;
  height: 28px;
  object-fit: contain;
  display: block;
  transition: transform 160ms ease, filter 160ms ease, opacity 160ms ease;
}
.mk-export-btn:hover .mk-export-img {
  transform: translateY(-1px) scale(1.04);
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.18));
}
.mk-export-btn:active .mk-export-img {
  transform: translateY(0) scale(0.98);
  opacity: 0.9;
}
.mk-statement-table-wrap {
  width: 100%;
  padding: 10px 14px 16px;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.mk-statement-table {
  min-width: 980px;
  margin: 0;
  color: #e5e7eb;
  background: #0f172a;
}
.mk-statement-table th,
.mk-statement-table td {
  border-color: rgba(148,163,184,0.18) !important;
  white-space: nowrap;
}
.mk-statement-table.table-striped > tbody > tr:nth-of-type(odd),
.mk-statement-table.table-striped > tbody > tr:nth-of-type(even),
.mk-statement-table tbody tr {
  background: #0f172a !important;
}
.mk-statement-table tbody tr:hover {
  background: #162033 !important;
}
.mk-statement-table th {
  background: #111827;
  color: #f8fafc;
  font-weight: 800;
}
.mk-statement-table th.mk-sortable {
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}
.mk-statement-table th.mk-sortable:hover { background: #182235; }
.mk-statement-table th.mk-sortable .mk-sort {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 10px;
  height: 12px;
  margin-left: 6px;
  vertical-align: middle;
}
.mk-statement-table th.mk-sortable .mk-sort::before,
.mk-statement-table th.mk-sortable .mk-sort::after {
  content: "";
  width: 0;
  height: 0;
  border-left: 4px solid transparent;
  border-right: 4px solid transparent;
  opacity: 0.6;
  transition: transform 160ms ease, opacity 160ms ease, border-color 160ms ease;
}
.mk-statement-table th.mk-sortable .mk-sort::before {
  border-bottom: 5px solid rgba(248,250,252,0.60);
  margin-bottom: 2px;
}
.mk-statement-table th.mk-sortable .mk-sort::after {
  border-top: 5px solid rgba(248,250,252,0.60);
}
.mk-statement-table th.mk-sorted-asc .mk-sort::before {
  opacity: 1;
  transform: translateY(-1px) scale(1.06);
  border-bottom-color: rgba(248,250,252,0.95);
}
.mk-statement-table th.mk-sorted-asc .mk-sort::after { opacity: 0.25; }
.mk-statement-table th.mk-sorted-desc .mk-sort::after {
  opacity: 1;
  transform: translateY(1px) scale(1.06);
  border-top-color: rgba(248,250,252,0.95);
}
.mk-statement-table th.mk-sorted-desc .mk-sort::before { opacity: 0.25; }
.mk-empty {
  padding: 38px 0 44px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.mk-empty-img {
  width: min(280px, 60%);
  max-width: 280px;
  height: auto;
  display: block;
  user-select: none;
  pointer-events: none;
}
@media (max-width: 991px) {
  .mk-statement-filters { flex-direction: column; align-items: stretch; }
  .mk-export-row { justify-content: flex-end; }
  .mk-date-range input { min-width: 140px; }
}
@media (max-width: 767px) {
  .mk-statement-card {
    border-radius: 6px;
    border-left: 0;
    border-right: 0;
  }
  .mk-statement-filters {
    padding: 12px;
    gap: 10px;
  }
  .mk-filter-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
  }
  .mk-filter {
    width: 100%;
    display: grid;
    grid-template-columns: 110px minmax(0, 1fr);
    justify-content: stretch;
    gap: 8px;
  }
  .mk-filter select.form-control { min-width: 0; width: 100%; }
  .mk-date-filter {
    grid-template-columns: 1fr;
  }
  .mk-date-range {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 8px;
  }
  .mk-date-range input { width: 100%; min-width: 0; }
  .mk-apply-btn { width: 100%; }
  .mk-export-row { justify-content: flex-start; }
  .mk-statement-table-wrap { padding: 8px 10px 14px; }
  .mk-statement-table { min-width: 900px; }
  .mk-empty { padding: 28px 0 34px; }
  .mk-empty-img { width: min(220px, 72%); max-width: 220px; }
}
</style>

<script>
(function() {
  var stBody = document.getElementById('stBody');
  var stEmpty = document.getElementById('stEmpty');
  var stTable = document.getElementById('stTable');
  var btnApply = document.getElementById('stApply');
  var btnPdf = document.getElementById('stPdf');
  var btnXls = document.getElementById('stXls');
  var fromEl = document.getElementById('stFrom');
  var toEl = document.getElementById('stTo');
  var walletEl = document.getElementById('stWallet');
  var typeEl = document.getElementById('stTxnType');
  var currentSort = { col: null, dir: 'asc', type: 'text' };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
    });
  }

  function parseVal(val, type) {
    var v = String(val == null ? '' : val).trim();
    if (type === 'number') {
      var n = Number(v.replace(/,/g, '').replace(/[^\d.-]/g, ''));
      return isFinite(n) ? n : 0;
    }
    if (type === 'date') {
      var ts = Date.parse(v.replace(' ', 'T'));
      return isFinite(ts) ? ts : 0;
    }
    return v.toLowerCase();
  }

  function applySort() {
    if (!stTable || currentSort.col == null) return;
    var tbody = stTable.tBodies && stTable.tBodies[0];
    if (!tbody) return;
    var rows = Array.prototype.slice.call(tbody.rows || []);
    if (!rows.length) return;

    var col = currentSort.col;
    var dir = currentSort.dir === 'desc' ? -1 : 1;
    var type = currentSort.type || 'text';

    rows = rows.map(function(tr, idx) { return { tr: tr, idx: idx }; });
    rows.sort(function(a, b) {
      var av = a.tr.cells[col] ? a.tr.cells[col].innerText : '';
      var bv = b.tr.cells[col] ? b.tr.cells[col].innerText : '';
      var pa = parseVal(av, type);
      var pb = parseVal(bv, type);
      var cmp = 0;
      if (typeof pa === 'number' && typeof pb === 'number') {
        cmp = pa === pb ? 0 : (pa < pb ? -1 : 1);
      } else {
        cmp = String(pa).localeCompare(String(pb));
      }
      if (cmp === 0) return a.idx - b.idx;
      return cmp * dir;
    });

    rows.forEach(function(r) { tbody.appendChild(r.tr); });
  }

  function setHeaderSortUI(th) {
    if (!stTable) return;
    var headers = stTable.querySelectorAll('thead th.mk-sortable');
    Array.prototype.forEach.call(headers, function(h) {
      h.classList.remove('mk-sorted-asc');
      h.classList.remove('mk-sorted-desc');
    });
    if (!th) return;
    th.classList.add(currentSort.dir === 'desc' ? 'mk-sorted-desc' : 'mk-sorted-asc');
  }

  if (stTable) {
    var headers = stTable.querySelectorAll('thead th.mk-sortable');
    Array.prototype.forEach.call(headers, function(th) {
      th.addEventListener('click', function() {
        var col = Number(th.getAttribute('data-col'));
        var type = th.getAttribute('data-type') || 'text';
        if (currentSort.col === col) {
          currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
          currentSort.col = col;
          currentSort.dir = 'asc';
          currentSort.type = type;
        }
        setHeaderSortUI(th);
        applySort();
      });
    });
  }

  function fetchRows() {
    var qs = new URLSearchParams();
    qs.set('wallet', walletEl.value || 'all');
    qs.set('type', typeEl.value || 'all');
    if (fromEl.value) qs.set('from', fromEl.value);
    if (toEl.value) qs.set('to', toEl.value);

    stBody.innerHTML = '';
    stEmpty.style.display = 'none';

    fetch('../api/account_statement.php?' + qs.toString(), {credentials: 'same-origin'})
      .then(function(r) { return r.json(); })
      .then(function(json) {
        var rows = (json && json.success && Array.isArray(json.rows)) ? json.rows : [];
        if (!rows.length) {
          stEmpty.style.display = 'flex';
          return;
        }
        stBody.innerHTML = rows.map(function(r) {
          return '<tr>' +
            '<td>' + esc(r.deposit) + '</td>' +
            '<td>' + esc(r.withdraw) + '</td>' +
            '<td>' + esc(r.balance) + '</td>' +
            '<td>' + esc(r.remark) + '</td>' +
            '<td>' + esc(r.date_time) + '</td>' +
            '<td>' + esc(r.old_balance) + '</td>' +
            '<td>' + esc(r.credit_reference) + '</td>' +
            '<td>' + esc(r.old_credit_reference) + '</td>' +
            '<td>' + esc(r.ref_pl) + '</td>' +
          '</tr>';
        }).join('');
        applySort();
      })
      .catch(function() {
        stEmpty.style.display = 'flex';
      });
  }

  function downloadCsv() {
    var rows = [];
    var ths = document.querySelectorAll('#stTable thead th');
    rows.push(Array.prototype.map.call(ths, function(th) { return '"' + (th.innerText || '').replace(/"/g,'""') + '"'; }).join(','));
    var trs = document.querySelectorAll('#stTable tbody tr');
    Array.prototype.forEach.call(trs, function(tr) {
      var tds = tr.querySelectorAll('td');
      rows.push(Array.prototype.map.call(tds, function(td) { return '"' + (td.innerText || '').replace(/"/g,'""') + '"'; }).join(','));
    });
    var blob = new Blob([rows.join('\n')], {type: 'text/csv;charset=utf-8;'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'account-statement.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  function printTable() {
    var win = window.open('', '_blank');
    if (!win) return;
    win.document.write('<html><head><title>Account Statement</title>');
    win.document.write('<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">');
    win.document.write('</head><body style="padding:15px;">');
    win.document.write('<h3>Account Statement</h3>');
    win.document.write(document.getElementById('stTable').outerHTML);
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    win.print();
  }

  btnApply.addEventListener('click', fetchRows);
  btnXls.addEventListener('click', downloadCsv);
  btnPdf.addEventListener('click', printTable);

  var now = new Date();
  var yyyy = now.getFullYear();
  var mm = String(now.getMonth() + 1).padStart(2, '0');
  var dd = String(now.getDate()).padStart(2, '0');
  toEl.value = yyyy + '-' + mm + '-' + dd;

  fetchRows();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
