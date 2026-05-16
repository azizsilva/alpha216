document.addEventListener('DOMContentLoaded', function () {
  initAdminSkeleton();
  initAdminShell();
  initMemberEditToggles();
  removeLegacyAdminSkin();

  document.addEventListener('click', function (e) {
    var closeBtn = e.target && e.target.closest ? e.target.closest('.close[data-dismiss="alert"]') : null;
    if (closeBtn) {
      var alertEl = closeBtn.closest('.alert');
      if (alertEl) alertEl.remove();
      e.preventDefault();
    }
  });

  document.querySelectorAll('table.custom-table').forEach(function (table) {
    if (!table.parentElement) return;
    if (table.parentElement.classList.contains('table-responsive')) return;
    var wrapper = document.createElement('div');
    wrapper.className = 'table-responsive';
    table.parentElement.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });

  document.querySelectorAll('.custom-table').forEach(function (table) {
    enhanceTable(table);
  });

  initHoverDropdowns();
});

function initAdminSkeleton() {
  var body = document.body;
  if (!body) return;

  shapeAdminSkeleton();

  var start = Date.now();
  var hidden = false;
  function hideSkeleton() {
    if (hidden) return;
    hidden = true;
    var delay = Math.max(0, 1000 - (Date.now() - start));
    setTimeout(function () {
      body.classList.remove('ta-loading');
      body.classList.add('ta-skeleton-hidden');
    }, delay);
  }

  if (document.readyState === 'complete') {
    hideSkeleton();
  } else {
    window.addEventListener('load', hideSkeleton, { once: true });
    setTimeout(hideSkeleton, 2000);
  }

  window.addEventListener('pageshow', hideSkeleton, { once: true });
}

function shapeAdminSkeleton() {
  var skel = document.getElementById('taSkeleton');
  var content = document.querySelector('.ta-content');
  if (!skel || !content) return;
  if (skel.getAttribute('data-ta-skeleton-profile')) return;

  var hasDashboard = !!content.querySelector('.ta-dashboard-showcase');
  var hasForm = !!content.querySelector('form, .form-control, .form-select, .mk-filterbar');
  var hasTable = !!content.querySelector('table, .custom-table, .table-responsive');
  var hasCards = !!content.querySelector('.card, .ta-panel-card, .ta-metric-card');

  skel.classList.toggle('ta-skeleton-dashboard-mode', hasDashboard);
  skel.classList.toggle('ta-skeleton-form-mode', !hasDashboard && hasForm);
  skel.classList.toggle('ta-skeleton-table-mode', !hasDashboard && hasTable);
  skel.classList.toggle('ta-skeleton-card-mode', !hasDashboard && hasCards && !hasForm && !hasTable);

  if (hasDashboard) return;

  var html = [
    '<div class="ta-skeleton-heading"><span></span><strong></strong></div>'
  ];

  if (hasForm) {
    html.push(
      '<div class="ta-skeleton-form-panel">',
      '<span class="ta-skeleton-line ta-skeleton-form-title"></span>',
      '<div class="ta-skeleton-fields">',
      '<i></i><i></i><i></i><i></i><i></i><i></i>',
      '</div>',
      '<span class="ta-skeleton-button"></span>',
      '</div>'
    );
  } else if (hasCards) {
    html.push(
      '<div class="ta-skeleton-grid">',
      '<div class="ta-skeleton-card"><i></i><span></span><strong></strong></div>',
      '<div class="ta-skeleton-card"><i></i><span></span><strong></strong></div>',
      '</div>'
    );
  }

  if (hasTable) {
    html.push(
      '<div class="ta-skeleton-table">',
      '<span></span><span></span><span></span><span></span><span></span><span></span><span></span>',
      '</div>'
    );
  } else if (!hasForm && !hasCards) {
    html.push(
      '<div class="ta-skeleton-panel">',
      '<span></span>',
      '<div class="ta-skeleton-bars"><i style="height:42%"></i><i style="height:68%"></i><i style="height:50%"></i><i style="height:76%"></i><i style="height:46%"></i><i style="height:60%"></i></div>',
      '</div>'
    );
  }

  skel.innerHTML = html.join('');
}

function removeLegacyAdminSkin() {
  document.querySelectorAll('.card-header[style], .modal-header[style], .btn[style]').forEach(function (el) {
    var style = String(el.getAttribute('style') || '').toLowerCase();
    if (style.indexOf('#003366') !== -1 || style.indexOf('#ffcc00') !== -1 || style.indexOf('background') !== -1 || style.indexOf('border-color') !== -1) {
      el.removeAttribute('style');
    }
  });
}

function initAdminShell() {
  var shell = document.getElementById('mkAdminShell');
  var sidebar = document.getElementById('mkAdminSidebar');
  if (!shell) return;

  initAdminSubmenus(shell);

  document.querySelectorAll('[data-mk-sidebar-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (window.matchMedia && window.matchMedia('(min-width: 992px)').matches) {
        shell.classList.remove('mk-sidebar-hover');
        shell.classList.toggle('mk-sidebar-collapsed');
      } else {
        shell.classList.toggle('mk-sidebar-open');
      }
    });
  });

  if (sidebar) {
    sidebar.addEventListener('mouseenter', function () {
      if (window.matchMedia && window.matchMedia('(min-width: 992px)').matches && shell.classList.contains('mk-sidebar-collapsed')) {
        shell.classList.add('mk-sidebar-hover');
        refreshOpenSubmenus();
      }
    });
    sidebar.addEventListener('mouseleave', function () {
      if (window.matchMedia && window.matchMedia('(min-width: 992px)').matches) {
        shell.classList.remove('mk-sidebar-hover');
      }
    });
  }

  document.querySelectorAll('.ta-menu-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (e.currentTarget && e.currentTarget.hasAttribute('data-ta-submenu-toggle')) return;
      if (window.matchMedia && window.matchMedia('(max-width: 991px)').matches) {
        shell.classList.remove('mk-sidebar-open');
      }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') shell.classList.remove('mk-sidebar-open');
  });

  window.addEventListener('resize', function () {
    if (window.matchMedia && window.matchMedia('(min-width: 992px)').matches) {
      shell.classList.remove('mk-sidebar-open');
    } else {
      shell.classList.remove('mk-sidebar-hover');
    }
  });
}

function initAdminSubmenus(shell) {
  var submenus = Array.prototype.slice.call(document.querySelectorAll('.ta-menu-subtree'));

  window.refreshOpenSubmenus = function () {
    submenus.forEach(function (tree) {
      if (tree.classList.contains('open')) setHeight(tree, true);
    });
  };

  function setHeight(tree, open) {
    var panel = tree.querySelector('.ta-submenu');
    if (!panel) return;
    if (open) {
      tree.classList.add('open');
      var h = panel.scrollHeight;
      panel.style.maxHeight = String(h) + 'px';
    } else {
      panel.style.maxHeight = String(panel.scrollHeight) + 'px';
      panel.offsetHeight;
      tree.classList.remove('open');
      panel.style.maxHeight = '0px';
    }
    var btn = tree.querySelector('[data-ta-submenu-toggle]');
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  submenus.forEach(function (tree) {
    setHeight(tree, tree.classList.contains('open'));
  });

  document.querySelectorAll('[data-ta-submenu-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var tree = btn.closest('.ta-menu-subtree');
      if (!tree) return;
      if (window.matchMedia && window.matchMedia('(min-width: 992px)').matches && shell.classList.contains('mk-sidebar-collapsed')) {
        shell.classList.remove('mk-sidebar-collapsed');
        setTimeout(function () { setHeight(tree, true); }, 120);
        return;
      }
      setHeight(tree, !tree.classList.contains('open'));
    });
  });

  window.addEventListener('resize', function () {
    window.refreshOpenSubmenus();
  });
}

function initHoverDropdowns() {
  if (!window.matchMedia || !window.matchMedia('(min-width: 992px)').matches) return;
  if (!window.bootstrap || !bootstrap.Dropdown) return;

  document.querySelectorAll('.mk-hover-dropdown').forEach(function (li) {
    var toggle = li.querySelector('[data-bs-toggle="dropdown"]');
    var menu = li.querySelector('.dropdown-menu');
    if (!toggle || !menu) return;

    var inst = bootstrap.Dropdown.getOrCreateInstance(toggle, { autoClose: true });
    var hideTimer = null;

    function show() {
      if (hideTimer) clearTimeout(hideTimer);
      inst.show();
    }

    function hideSoon() {
      if (hideTimer) clearTimeout(hideTimer);
      hideTimer = setTimeout(function () {
        inst.hide();
      }, 120);
    }

    li.addEventListener('mouseenter', show);
    li.addEventListener('mouseleave', hideSoon);
    menu.addEventListener('mouseenter', show);
    menu.addEventListener('mouseleave', hideSoon);
  });
}

function initMemberEditToggles() {
  function getTargetFromButton(btn) {
    if (!btn) return null;
    var sel = btn.getAttribute('data-bs-target') || btn.getAttribute('data-target') || btn.getAttribute('href') || '';
    if (!sel || sel.charAt(0) !== '#') return null;
    if (!/^#edit(Master|Agent|Player)\d+$/i.test(sel)) return null;
    try {
      return document.querySelector(sel);
    } catch (e) {
      return null;
    }
  }

  function closePanel(panel) {
    if (!panel) return;
    panel.classList.remove('show');
    panel.setAttribute('aria-hidden', 'true');
    var row = panel.closest('.ta-edit-row');
    if (row) {
      row.classList.remove('ta-edit-row-open');
      row.style.display = 'none';
    }
    document.querySelectorAll('[data-bs-target="#' + panel.id + '"], [data-target="#' + panel.id + '"]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
  }

  function openPanel(panel) {
    if (!panel) return;
    var table = panel.closest('table');
    if (table) {
      table.querySelectorAll('.ta-edit-row .collapse.show').forEach(function (open) {
        if (open !== panel) closePanel(open);
      });
    }
    var row = panel.closest('.ta-edit-row');
    if (row) {
      row.classList.add('ta-edit-row-open');
      row.style.display = '';
    }
    panel.classList.add('show');
    panel.setAttribute('aria-hidden', 'false');
    document.querySelectorAll('[data-bs-target="#' + panel.id + '"], [data-target="#' + panel.id + '"]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', 'true');
    });
  }

  document.querySelectorAll('.ta-edit-row .collapse').forEach(function (panel) {
    if (!panel.classList.contains('show')) {
      panel.setAttribute('aria-hidden', 'true');
    }
  });

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-bs-toggle="collapse"], [data-toggle="collapse"]') : null;
    var panel = getTargetFromButton(btn);
    if (!panel) return;

    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();

    if (panel.classList.contains('show')) {
      closePanel(panel);
    } else {
      openPanel(panel);
      var firstInput = panel.querySelector('input[name="username"]');
      if (firstInput && window.matchMedia && window.matchMedia('(min-width: 768px)').matches) {
        setTimeout(function () { firstInput.focus(); firstInput.select(); }, 80);
      }
    }
  }, true);
}

function enhanceTable(table) {
  if (table.dataset.enhanced === '1') return;
  var tbody = table.querySelector('tbody');
  var thead = table.querySelector('thead');
  if (!tbody || !thead) return;

  var groups = parseGroups(tbody);
  if (!groups.length) return;

  var state = {
    groups: groups,
    query: '',
    perPage: 25,
    page: 1,
    sortIndex: -1,
    sortDir: 'asc'
  };

  table.dataset.enhanced = '1';

  var tools = buildTools(state, table);
  var respWrap = table.parentElement && table.parentElement.classList.contains('table-responsive') ? table.parentElement : table;
  respWrap.parentNode.insertBefore(tools, respWrap);

  var pager = buildPager(state, table);
  respWrap.parentNode.insertBefore(pager, respWrap.nextSibling);

  bindSorting(state, table);
  applyEmptyState(table);
  attachHeaderExport(state, table);
  applyState(state, table);
}

function applyEmptyState(table) {
  var tbody = table.querySelector('tbody');
  var thead = table.querySelector('thead');
  if (!tbody || !thead) return;
  var tr = tbody.querySelector('tr');
  if (!tr) return;
  var tds = tr.querySelectorAll('td');
  if (tds.length !== 1) return;
  var text = (tds[0].innerText || '').trim().toLowerCase();
  if (text.indexOf('no') === -1) return;
  tds[0].innerHTML = '<div class="mk-empty-state text-center"><img src="https://moneyking365.com/assets/images/norecode.png" alt="No records"></div>';
}

function parseGroups(tbody) {
  var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
  var groups = [];
  var current = null;
  rows.forEach(function (row) {
    if (row.classList.contains('ta-edit-row') || row.querySelector('.collapse')) {
      if (current) current.extra.push(row);
    } else if (!row.classList.contains('collapse')) {
      current = { main: row, extra: [] };
      groups.push(current);
    } else if (current) {
      current.extra.push(row);
    }
  });
  return groups;
}

function buildTools(state, table) {
  var wrap = document.createElement('div');
  wrap.className = 'mk-table-tools d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3';

  var left = document.createElement('div');
  left.className = 'd-flex align-items-center gap-2 flex-wrap';

  var search = document.createElement('input');
  search.type = 'search';
  search.className = 'form-control form-control-sm';
  search.placeholder = 'Search...';
  search.style.minWidth = '200px';
  search.addEventListener('input', function () {
    state.query = (search.value || '').trim().toLowerCase();
    state.page = 1;
    applyState(state, table);
  });
  left.appendChild(search);

  var per = document.createElement('select');
  per.className = 'form-select form-select-sm';
  [10, 25, 50, 100, 200].forEach(function (n) {
    var opt = document.createElement('option');
    opt.value = String(n);
    opt.textContent = String(n) + ' rows';
    if (n === state.perPage) opt.selected = true;
    per.appendChild(opt);
  });
  per.addEventListener('change', function () {
    state.perPage = Number(per.value) || 25;
    state.page = 1;
    applyState(state, table);
  });
  left.appendChild(per);

  wrap.appendChild(left);
  return wrap;
}

function attachHeaderExport(state, table) {
  var card = table.closest ? table.closest('.card') : null;
  if (!card) return;
  var header = card.querySelector('.card-header');
  if (!header) return;
  if (header.querySelector('.mk-export-excel')) return;

  var actions = header.querySelector('.mk-card-actions');
  if (!actions) {
    actions = document.createElement('div');
    actions.className = 'mk-card-actions';
    header.appendChild(actions);
  }

  var a = document.createElement('a');
  a.href = 'javascript:void(0)';
  a.className = 'mk-export-excel';
  var img = document.createElement('img');
  img.src = 'https://moneyking365.com/assets/images/excel-icon.png';
  img.alt = 'Excel';
  img.className = 'mk-export-excel-icon';
  a.appendChild(img);
  a.addEventListener('click', function () {
    exportTable(table, state, 'excel');
  });
  actions.appendChild(a);
}

function buildPager(state, table) {
  var wrap = document.createElement('div');
  wrap.className = 'mk-table-pager d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3';

  var left = document.createElement('div');
  left.className = 'text-body-secondary mk-pager-meta';
  wrap.appendChild(left);

  var right = document.createElement('div');
  right.className = 'd-flex gap-2 align-items-center';

  var prev = document.createElement('button');
  prev.type = 'button';
  prev.className = 'btn btn-sm btn-outline-secondary';
  prev.textContent = 'Prev';
  prev.addEventListener('click', function () {
    state.page = Math.max(1, state.page - 1);
    applyState(state, table);
  });

  var mid = document.createElement('div');
  mid.className = 'text-body-secondary mk-pager-page';

  var next = document.createElement('button');
  next.type = 'button';
  next.className = 'btn btn-sm btn-outline-secondary';
  next.textContent = 'Next';
  next.addEventListener('click', function () {
    state.page = state.page + 1;
    applyState(state, table);
  });

  right.appendChild(prev);
  right.appendChild(mid);
  right.appendChild(next);
  wrap.appendChild(right);

  wrap._mkPrev = prev;
  wrap._mkNext = next;
  wrap._mkMeta = left;
  wrap._mkPage = mid;
  table._mkPager = wrap;
  return wrap;
}

function bindSorting(state, table) {
  var headers = Array.prototype.slice.call(table.querySelectorAll('thead th'));
  headers.forEach(function (th, idx) {
    if (th.querySelector('a')) return;
    th.style.cursor = 'pointer';
    th.addEventListener('click', function () {
      if (state.sortIndex === idx) {
        state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
      } else {
        state.sortIndex = idx;
        state.sortDir = 'asc';
      }
      headers.forEach(function (x) { x.removeAttribute('data-sort-dir'); });
      th.setAttribute('data-sort-dir', state.sortDir);
      sortGroups(state);
      state.page = 1;
      applyState(state, table);
    });
  });
}

function sortGroups(state) {
  if (state.sortIndex < 0) return;
  var idx = state.sortIndex;
  var dir = state.sortDir;
  state.groups.sort(function (ga, gb) {
    var a = cellText(ga.main, idx);
    var b = cellText(gb.main, idx);
    var cmp = compareValues(a, b);
    return dir === 'asc' ? cmp : -cmp;
  });
  var tbody = state.groups[0].main.parentNode;
  state.groups.forEach(function (g) {
    tbody.appendChild(g.main);
    g.extra.forEach(function (r) { tbody.appendChild(r); });
  });
}

function compareValues(a, b) {
  var na = parseFloat(String(a).replace(/[^0-9.\-]/g, ''));
  var nb = parseFloat(String(b).replace(/[^0-9.\-]/g, ''));
  var bothNumeric = !isNaN(na) && !isNaN(nb);
  if (bothNumeric) return na === nb ? 0 : na < nb ? -1 : 1;
  var ta = String(a || '').trim().toLowerCase();
  var tb = String(b || '').trim().toLowerCase();
  return ta === tb ? 0 : ta < tb ? -1 : 1;
}

function cellText(row, idx) {
  var cells = row.children;
  if (!cells || idx < 0 || idx >= cells.length) return '';
  return (cells[idx].innerText || '').trim();
}

function applyState(state, table) {
  var visible = filterGroups(state);
  var total = visible.length;
  var pages = Math.max(1, Math.ceil(total / state.perPage));
  state.page = Math.min(state.page, pages);
  var start = (state.page - 1) * state.perPage;
  var end = Math.min(total, start + state.perPage);

  state.groups.forEach(function (g) {
    g.main.style.display = 'none';
    g.extra.forEach(function (r) { r.style.display = 'none'; });
  });

  visible.slice(start, end).forEach(function (g) {
    g.main.style.display = '';
    g.extra.forEach(function (r) {
      var open = r.classList.contains('show') || r.classList.contains('ta-edit-row-open') || !!r.querySelector('.collapse.show, .collapsing');
      if (open) r.style.display = '';
    });
  });

  syncEmptyRow(table, total);

  var pager = table._mkPager;
  if (pager) {
    pager._mkPrev.disabled = state.page <= 1;
    pager._mkNext.disabled = state.page >= pages;
    pager._mkMeta.textContent = 'Total: ' + total;
    pager._mkPage.textContent = 'Page ' + state.page + ' / ' + pages;
  }
}

function syncEmptyRow(table, total) {
  var tbody = table.querySelector('tbody');
  var thead = table.querySelector('thead');
  if (!tbody || !thead) return;
  var colCount = thead.querySelectorAll('th').length || 1;
  var empty = tbody.querySelector('tr.mk-empty-row');

  if (total > 0) {
    if (empty) empty.remove();
    return;
  }

  if (!empty) {
    empty = document.createElement('tr');
    empty.className = 'mk-empty-row';
    var td = document.createElement('td');
    td.colSpan = colCount;
    td.className = 'text-center';
    td.innerHTML = '<div class="mk-empty-state"><img src="https://moneyking365.com/assets/images/norecode.png" alt="No records"></div>';
    empty.appendChild(td);
    tbody.appendChild(empty);
  }
  empty.style.display = '';
}

function filterGroups(state) {
  if (!state.query) return state.groups.slice();
  var q = state.query;
  return state.groups.filter(function (g) {
    var text = (g.main.innerText || '').toLowerCase();
    return text.indexOf(q) !== -1;
  });
}

function exportTable(table, state, kind) {
  var headers = Array.prototype.slice.call(table.querySelectorAll('thead th')).map(function (th) {
    return (th.innerText || '').trim();
  });
  var visible = filterGroups(state);
  var rows = visible.map(function (g) {
    return Array.prototype.slice.call(g.main.querySelectorAll('td')).map(function (td) {
      return (td.innerText || '').replace(/\s+/g, ' ').trim();
    });
  });

  var csv = [];
  csv.push(headers.map(csvCell).join(','));
  rows.forEach(function (r) {
    csv.push(r.map(csvCell).join(','));
  });
  var blob = new Blob([csv.join('\n')], { type: 'application/vnd.ms-excel;charset=utf-8;' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = 'export.xls';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

function csvCell(v) {
  var s = String(v == null ? '' : v);
  if (s.indexOf('"') !== -1) s = s.replace(/"/g, '""');
  if (/[",\n]/.test(s)) s = '"' + s + '"';
  return s;
}

function escHtml(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
  });
}
