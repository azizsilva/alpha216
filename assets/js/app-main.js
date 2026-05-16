(function () {
  var app = document.getElementById('mkApp');
  if (!app) return;

  var base = (typeof window.SITE_BASE_URL === 'string' ? window.SITE_BASE_URL : '/');
  if (!base.endsWith('/')) base += '/';

  // Early URL normalize: collapse multiple slashes and drop trailing slash (except root)
  try {
    var __rawPath = window.location.pathname || '/';
    var __norm = __rawPath.replace(/\/{2,}/g, '/');
    if (__norm.length > 1 && __norm.endsWith('/')) __norm = __norm.slice(0, -1);
    if (__norm !== __rawPath) {
      history.replaceState({}, '', __norm + window.location.search + window.location.hash);
    }
  } catch (eNorm) {}

  var cache = Object.create(null);
  var navSeq = 0;

  var routes = {
    '/': base + 'index.php?mk_fragment=1',
    '/home': base + 'index.php?mk_fragment=1',
    '/deposit-withdraw': base + 'deposit-withdraw/?mk_fragment=1',
    '/account-details': base + 'account-details/?mk_fragment=1',
    '/account-statement': base + 'account-statement/?mk_fragment=1',
    '/profit-loss': base + 'profit-loss/?mk_fragment=1',
    '/bet-history': base + 'bet-history/?mk_fragment=1',
    '/activity-log': base + 'activity-log/?mk_fragment=1',
    '/pinned': base + 'pinned/?mk_fragment=1',
    '/sports': base + 'sports/?mk_fragment=1',
    '/sportsbook': base + 'sportsbook/?mk_fragment=1',
    '/live-football': base + 'live-football.php?mk_fragment=1',
    '/casino': base + 'casino-games/?mk_fragment=1',
    '/casino-games': base + 'casino-games/?mk_fragment=1',
    '/casino-games/slot-games': base + 'casino-games/slot-games/?mk_fragment=1',
    '/casino-games/live-casino': base + 'casino-games/live-casino/?mk_fragment=1',
    '/casino-games/virtual-sports': base + 'casino-games/virtual-sports/?mk_fragment=1',
    '/casino-games/slots': base + 'casino-games/slot-games/?mk_fragment=1',
    '/casino-games/live': base + 'casino-games/live-casino/?mk_fragment=1',
    '/casino-games/virtual': base + 'casino-games/virtual-sports/?mk_fragment=1',
    '/fantasy-games': base + 'fantasy-games/?mk_fragment=1',
    '/play': base + 'play/?mk_fragment=1'
  };

  var protectedRoutes = {
    '/deposit-withdraw': true,
    '/account-details': true,
    '/account-statement': true,
    '/profit-loss': true,
    '/bet-history': true,
    '/activity-log': true
  };

  function isLoggedIn() {
    return !!window.MK_IS_LOGGED_IN;
  }

  function isProtectedPath(path) {
    return !!protectedRoutes[canonicalPath(path)];
  }

  function openLoginModal() {
    try {
      if (typeof window.closeSidebar === 'function') window.closeSidebar();
      if (typeof window.toggleMobileMenu === 'function') {
        var sidebar = document.getElementById('mobileLeftSidebar');
        if (sidebar && sidebar.classList.contains('active')) window.toggleMobileMenu();
      }
    } catch (eClose) {}

    try {
      if (window.jQuery && jQuery.fn && jQuery.fn.modal && jQuery('#loginModal').length) {
        jQuery('#loginModal').modal('show');
        return;
      }
    } catch (eModal) {}

    var loginBtn = document.querySelector('[data-target="#loginModal"], .loginbtn');
    if (loginBtn) {
      try { loginBtn.click(); return; } catch (eClick) {}
    }
    window.location.href = base + 'login.php';
  }

  function guestFallbackPath() {
    var fallback = '/';
    try {
      var saved = sessionStorage.getItem('mk_last_non_profile_path') || '';
      if (saved) fallback = saved;
    } catch (eStore) {}
    var p = canonicalPath(fallback);
    if (protectedRoutes[p]) return '/';
    return fallback || '/';
  }

  function guardProtectedRoute(path, replaceUrl) {
    if (isLoggedIn() || !isProtectedPath(path)) return false;
    if (replaceUrl) {
      var fallback = guestFallbackPath();
      try { history.replaceState({}, '', fallback); } catch (eHist) {}
      syncShellForPath(canonicalPath(fallback));
      setTimeout(openLoginModal, 0);
      loadRoute();
    } else {
      openLoginModal();
    }
    return true;
  }

  function canonicalPath(path) {
    var p = normalizePath(path);
    if (p.indexOf('/casino-games/slots') === 0) return '/casino-games/slot-games';
    if (p.indexOf('/casino-games/live') === 0) return '/casino-games/live-casino';
    if (p.indexOf('/casino-games/virtual') === 0) return '/casino-games/virtual-sports';
    if (p === '/casino') return '/casino';
    return p;
  }

  function cleanSearch(search) {
    var q = String(search || '');
    if (!q) return '';
    if (q[0] === '?') q = q.slice(1);
    if (!q) return '';
    q = q.split('&').filter(function (kv) {
      if (!kv) return false;
      var k = kv.split('=')[0] || '';
      return k.toLowerCase() !== 'mk_fragment';
    }).join('&');
    return q;
  }

  function urlForPath(path, search) {
    var p = canonicalPath(path);
    var u = routes[p];
    if (!u) {
      if (p === '/' || p === '/home') u = routes['/'];
      else u = base + p.slice(1) + '/?mk_fragment=1';
    }
    var extra = cleanSearch(search);
    if (extra) u += (u.indexOf('?') >= 0 ? '&' : '?') + extra;
    return u;
  }

  function normalizePath(path) {
    path = String(path || '/');
    path = path.split('?')[0];
    if (!path.startsWith('/')) path = '/' + path;
    path = path.replace(/\/index\.php$/i, '');
    if (path === '') path = '/';
    if (path.length > 1 && path.endsWith('/')) path = path.slice(0, -1);
    try {
      // Collapse common duplicated route prefixes
      while (path.indexOf('/casino-games/casino-games') !== -1) path = path.replace('/casino-games/casino-games', '/casino-games');
      while (path.indexOf('/fantasy-games/fantasy-games') !== -1) path = path.replace('/fantasy-games/fantasy-games', '/fantasy-games');
      while (path.indexOf('/sportsbook/sportsbook') !== -1) path = path.replace('/sportsbook/sportsbook', '/sportsbook');
      while (path.indexOf('/sports/sports') !== -1) path = path.replace('/sports/sports', '/sports');

      var parts = path.split('/').filter(function (p) { return p !== ''; });
      var out = [];
      var seen = Object.create(null);
      for (var i = 0; i < parts.length; i++) {
        var seg = parts[i];
        if (i > 0 && seg === parts[i - 1]) continue;
        if (seg === 'casino-games' || seg === 'fantasy-games' || seg === 'pinned' || seg === 'sports' || seg === 'sportsbook') {
          if (seen[seg]) continue;
          seen[seg] = 1;
        }
        out.push(seg);
      }
      path = '/' + out.join('/');
      if (path === '') path = '/';
    } catch (e) {
    }
    return path;
  }

  function getCurrentPath() {
    // Prefer clean path. If legacy hash found, convert to clean URL once.
    var h = window.location.hash || '';
    if (h.indexOf('#/') === 0) {
      var hp = normalizePath(h.slice(1));
      try { history.replaceState({}, '', hp + window.location.search); } catch (e) {}
      return hp || '/';
    }
    var raw = window.location.pathname || '/';
    var p = normalizePath(raw);

    // Strip the SITE_BASE_URL prefix so routes like '/play' match even on subdirectory installs
    // e.g. '/public_html/play' → '/play' when base = '/public_html/'
    try {
      if (base && base.length > 1) {
        var bStrip = base.endsWith('/') ? base.slice(0, -1) : base;
        if (p.startsWith(bStrip + '/') || p === bStrip) {
          p = p.slice(bStrip.length) || '/';
        }
      }
    } catch (eBase) {}

    try {
      var rawNorm = String(raw || '/').split('?')[0];
      if (!rawNorm.startsWith('/')) rawNorm = '/' + rawNorm;
      if (rawNorm.length > 1 && rawNorm.endsWith('/')) rawNorm = rawNorm.slice(0, -1);
    } catch (e0) {}
    if (/\/index\.php$/i.test(window.location.pathname || '')) {
      try { history.replaceState({}, '', '/' + window.location.search); } catch (e) {}
      p = '/';
    }
    return p || '/';
  }

  function syncShellForPath(path) {
    try {
      if (!document.body) return;
      var p = canonicalPath(path || getCurrentPath());
      var inner = (
        p === '/pinned' ||
        p === '/play' ||
        p === '/sports' ||
        p === '/sportsbook' ||
        p.indexOf('/casino-games') === 0 ||
        p.indexOf('/fantasy-games') === 0 ||
        p === '/deposit-withdraw' ||
        p === '/account-details' ||
        p === '/account-statement' ||
        p === '/profit-loss' ||
        p === '/bet-history' ||
        p === '/activity-log'
      );

      var accountMode = (
        p === '/account-details' ||
        p === '/account-statement' ||
        p === '/deposit-withdraw' ||
        p === '/profit-loss' ||
        p === '/bet-history' ||
        p === '/activity-log'
      );

      document.body.classList.toggle('inner-page', !!inner);
      document.body.classList.toggle('mk-account-mode', !!accountMode);
      document.body.classList.toggle('mk-pinned-mode', p === '/pinned');
      document.body.classList.toggle('mk-game-no-chrome', p === '/sports' || p === '/sportsbook');
      if (p === '/play') {
        document.body.classList.add('mk-api-game-fullscreen');
        var qs = (window.location.search || '').toLowerCase();
        document.body.classList.toggle('mk-api-game-inplay', qs.indexOf('in-play') !== -1 || qs.indexOf('inplay') !== -1);
      } else {
        document.body.classList.remove('mk-api-game-fullscreen', 'mk-api-game-inplay');
      }

      var titleEl = document.getElementById('mobilePageTitle');
      if (titleEl) {
        var key = 'home';
        var text = 'HOME';
        if (p === '/pinned') { key = 'pinned'; text = 'PINNED'; }
        else if (p === '/sportsbook') { key = 'sports_book'; text = 'SPORTS BOOK'; }
        else if (p === '/sports') { key = 'sports'; text = 'SPORTS'; }
        else if (p === '/play') { key = 'game_play'; text = 'GAME PLAY'; }
        else if (p.indexOf('/casino-games/live-casino') === 0) { key = 'live_casino'; text = 'LIVE CASINO'; }
        else if (p.indexOf('/casino-games/virtual-sports') === 0) { key = 'virtual_sports'; text = 'VIRTUAL SPORTS'; }
        else if (p.indexOf('/casino-games/slot-games') === 0) { key = 'slot_games'; text = 'SLOT GAMES'; }
        else if (p.indexOf('/casino-games') === 0) { key = 'ace_casino'; text = 'ACE CASINO'; }
        else if (p.indexOf('/fantasy-games') === 0) { key = 'fantasy_games'; text = 'FANTASY GAMES'; }
        titleEl.setAttribute('data-translate', key);
        titleEl.innerText = text;
      }
    } catch (e) {}
  }

  function setPath(path) {
    var s = String(path || '');
    var q = '';
    var hash = '';
    try {
      var hi = s.indexOf('#');
      if (hi >= 0) { hash = s.slice(hi); s = s.slice(0, hi); }
      var qi = s.indexOf('?');
      if (qi >= 0) { q = s.slice(qi); s = s.slice(0, qi); }
    } catch (eP) {}

    var p = canonicalPath(s);
    if (guardProtectedRoute(p, false)) return;
    // Strip base from current URL for duplicate detection
    var curRaw = normalizePath(window.location.pathname || '/');
    var cur = curRaw;
    try {
      if (base && base.length > 1) {
        var bCur = base.endsWith('/') ? base.slice(0, -1) : base;
        if (cur.startsWith(bCur + '/') || cur === bCur) cur = cur.slice(bCur.length) || '/';
      }
    } catch (eBc) {}
    if (cur === '') cur = '/';
    if (cur === p && (window.location.search || '') === q) return;
    try {
      var accountPaths = {
        '/account-details': 1,
        '/account-statement': 1,
        '/profit-loss': 1,
        '/bet-history': 1,
        '/activity-log': 1
      };
      if (accountPaths[p] && !accountPaths[canonicalPath(cur)]) {
        sessionStorage.setItem('mk_last_non_profile_path', cur + (window.location.search || ''));
      }
    } catch (eStore) {}
    // Prepend base for the URL bar so refreshing at the URL works correctly
    var histPath = (base && base.length > 1) ? (base.replace(/\/$/, '') + p) : p;
    try { history.pushState({}, '', histPath + q + hash); } catch (e) { window.location.assign(histPath + q + hash); return; }
    loadRoute();
  }

  function showLoading() {
    if (app && app.childNodes && app.childNodes.length) return;
    app.innerHTML = '<div style="padding:18px 10px;text-align:center;color:#bbb;font-weight:700;">Loading<span class="mk-app-dots"><span>.</span><span>.</span><span>.</span></span></div>';
  }

  var warmOnce = false;
  function warmCache() {
    if (warmOnce) return;
    warmOnce = true;

    var paths = [];
    try {
      Object.keys(routes).forEach(function (p) { paths.push(p); });
    } catch (e) {
    }

    try {
      var links = document.querySelectorAll('a[href]');
      for (var i = 0; i < links.length; i++) {
        var href = links[i].getAttribute('href') || '';
        if (!href) continue;
        var h = '';
        var idx = href.indexOf('#/');
        if (idx >= 0) h = href.slice(idx + 1);
        else if (href.indexOf('#') === 0) h = href.slice(1);
        if (!h) continue;
        paths.push(h);
      }
    } catch (e) {
    }

    var uniq = Object.create(null);
    var list = [];
    for (var k = 0; k < paths.length; k++) {
      var pth = canonicalPath(paths[k]);
      if (!pth) continue;
      if (!isLoggedIn() && protectedRoutes[pth]) continue;
      if (uniq[pth]) continue;
      uniq[pth] = true;
      list.push(pth);
    }

    var current = canonicalPath(getRouteFromHash());
    list = list.filter(function (p) { return p !== current; });

    var queue = list.map(urlForPath);
    var uUniq = Object.create(null);
    var urls = [];
    for (var q = 0; q < queue.length; q++) {
      var u = queue[q];
      if (!u || uUniq[u]) continue;
      uUniq[u] = true;
      urls.push(u);
    }

    var concurrency = 2;
    var inFlight = 0;
    var idx2 = 0;
    function pump() {
      while (inFlight < concurrency && idx2 < urls.length) {
        var u = urls[idx2++];
        if (cache[u]) continue;
        inFlight++;
        fetchFragment(u).catch(function () {}).finally(function () {
          inFlight--;
          pump();
        });
      }
    }

    var start = function () { pump(); };
    if (window.requestIdleCallback) window.requestIdleCallback(start, { timeout: 1200 });
    else setTimeout(start, 300);
  }

  function runAfterLoad() {
    try {
      try {
        var imgs = app.querySelectorAll('img');
        imgs.forEach(function (img) {
          if (!img.getAttribute('loading')) img.setAttribute('loading', 'lazy');
          if (!img.getAttribute('decoding')) img.setAttribute('decoding', 'async');
        });
      } catch (eImg) {
      }

      // Execute any <script> tags inside the newly injected fragment
      var loaded = window.MK_LOADED_SCRIPTS = window.MK_LOADED_SCRIPTS || {};
      var nodes = app.querySelectorAll('script');
      var promises = [];
      nodes.forEach(function (s) {
        var src = s.getAttribute('src');
        if (src) {
          // Skip common libs already in shell
          var skip = /jquery|bootstrap|owl\.carousel/i.test(src);
          if (skip || loaded[src]) return;
          loaded[src] = true;
          promises.push(new Promise(function (resolve) {
            var el = document.createElement('script');
            el.src = src;
            el.async = false;
            el.onload = el.onerror = function () { resolve(); };
            document.body.appendChild(el);
          }));
        } else if (s.textContent && s.textContent.trim()) {
          try { (new Function(s.textContent))(); } catch (e) { /* ignore */ }
        }
      });
      Promise.all(promises).then(function () {
        if (typeof window.MK_INIT_UI === 'function') {
          try { window.MK_INIT_UI(); } catch (e) {}
        }
      });
    } catch (e) {
      if (typeof window.MK_INIT_UI === 'function') {
        try { window.MK_INIT_UI(); } catch (e2) {}
      }
    }
    if (typeof window.runTranslations === 'function') {
      try { window.runTranslations(); } catch (e) {}
    }
    warmCache();
  }

  function fetchFragment(url) {
    if (cache[url]) return Promise.resolve(cache[url]);
    if (window.__MK_PREFETCH_CACHE && window.__MK_PREFETCH_CACHE[url]) {
      var html = window.__MK_PREFETCH_CACHE[url];
      cache[url] = html;
      try { delete window.__MK_PREFETCH_CACHE[url]; } catch (e) {}
      return Promise.resolve(html);
    }
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(new Error('HTTP ' + r.status)); })
      .then(function (html) { cache[url] = html; return html; });
  }

  function loadRoute() {
    var path = canonicalPath(getCurrentPath());
    if (guardProtectedRoute(path, true)) return;
    var seq = ++navSeq;
    var search = window.location.search || '';
    syncShellForPath(path);
    try {
      if (typeof window.MK_BEFORE_ROUTE_CHANGE === 'function') {
        window.MK_BEFORE_ROUTE_CHANGE(window.__MK_LAST_PATH || null, path);
      }
    } catch (e) {}
    var url = urlForPath(path, search);
    showLoading();
    fetchFragment(url)
      .then(function (html) {
        if (seq !== navSeq) return;
        app.innerHTML = html;
        window.scrollTo(0, 0);
        runAfterLoad();
        try {
          if (typeof window.MK_AFTER_ROUTE_CHANGE === 'function') {
            window.MK_AFTER_ROUTE_CHANGE(path);
          }
        } catch (e2) {}
        window.__MK_LAST_PATH = path;
      })
      .catch(function () {
        if (seq !== navSeq) return;
        var tried = [];
        function add(u) { if (u && tried.indexOf(u) === -1) tried.push(u); }
        add(url);
        try {
          var p = canonicalPath(path);
          var p2 = p;
          if (p2.length > 1 && p2.endsWith('/')) p2 = p2.slice(0, -1);
          var p3 = p2 + '/';
          var b = (typeof window.SITE_BASE_URL === 'string' ? window.SITE_BASE_URL : '/');
          var extra = cleanSearch(search);
          function withExtra(u0) { return extra ? (u0 + (u0.indexOf('?') >= 0 ? '&' : '?') + extra) : u0; }
          add(withExtra(b + p2.slice(1) + '/?mk_fragment=1'));
          add(withExtra(b + p2.slice(1) + '?mk_fragment=1'));
          add(withExtra(b + p3.slice(1) + '?mk_fragment=1'));
          add(withExtra(b + p2.slice(1) + '/index.php?mk_fragment=1'));
        } catch (eT) {}

        function tryNext(i) {
          if (seq !== navSeq) return;
          if (i >= tried.length) {
            var home1 = urlForPath('/');
            fetchFragment(home1).then(function (html) {
              if (seq !== navSeq) return;
              syncShellForPath('/');
              app.innerHTML = html;
              window.scrollTo(0, 0);
              runAfterLoad();
              try {
                if (typeof window.MK_AFTER_ROUTE_CHANGE === 'function') {
                  window.MK_AFTER_ROUTE_CHANGE('/');
                }
              } catch (e3) {}
              window.__MK_LAST_PATH = '/';
            }).catch(function () {
              if (seq !== navSeq) return;
              app.innerHTML = '<div style="padding:24px 10px;text-align:center;color:#bbb;font-weight:700;">Page not found</div>';
            });
            return;
          }
          fetchFragment(tried[i]).then(function (html) {
            if (seq !== navSeq) return;
            app.innerHTML = html;
            window.scrollTo(0, 0);
            runAfterLoad();
            try {
              if (typeof window.MK_AFTER_ROUTE_CHANGE === 'function') {
                window.MK_AFTER_ROUTE_CHANGE(path);
              }
            } catch (e4) {}
            window.__MK_LAST_PATH = path;
          }).catch(function () {
            tryNext(i + 1);
          });
        }
        tryNext(0);
      });
  }

  document.addEventListener('click', function (e) {
    var a = e && e.target && e.target.closest ? e.target.closest('a') : null;
    if (!a) return;
    if (a.target === '_blank') return;
    if (a.hasAttribute('download')) return;
    var href = a.getAttribute('href') || '';
    if (!href) return;
    if (href.startsWith('http://') || href.startsWith('https://') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
    if (href.startsWith('javascript:')) return;
    if (href[0] === '#' && href.indexOf('#/') !== 0) return;

    // Convert any hash-route links to clean paths: "#/x" or "/#/x" or "something#/x"
    var hashIdx = href.indexOf('#/');
    if (hashIdx >= 0) {
      e.preventDefault();
      var hp = href.slice(hashIdx + 1);
      setPath(hp);
      return;
    }

    var clean = '';
    var search = '';
    try {
      var abs = new URL(href, window.location.href);
      if (abs && abs.origin === window.location.origin) { clean = abs.pathname || ''; search = abs.search || ''; }
    } catch (e1) {
      clean = href.split('?')[0].split('#')[0];
    }
    if (!clean) return;

    // Strip base prefix for subdirectory installs (e.g. /public_html/casino → /casino)
    try {
      if (base && base.length > 1) {
        var bS = base.endsWith('/') ? base.slice(0, -1) : base;
        if (clean.startsWith(bS + '/') || clean === bS) {
          clean = clean.slice(bS.length) || '/';
        }
      }
    } catch (eBs) {}

    var cp = canonicalPath(clean);
    var navTo = cp + (search || '');

    if (guardProtectedRoute(cp, false)) {
      e.preventDefault();
      return;
    }

    var can = true;
    if (cp === '/' || cp === '/home') can = true;
    else can = !!(routes[cp] || cp.length > 1);
    if (can) {
      e.preventDefault();
      setPath(navTo);
    }
  }, true);

  window.addEventListener('popstate', loadRoute);
  var booted = false;
  function boot() {
    if (booted) return;
    booted = true;
    if (app && app.children && app.children.length) {
      window.__MK_LAST_PATH = canonicalPath(getCurrentPath());
      syncShellForPath(window.__MK_LAST_PATH);
      runAfterLoad();
      return;
    }
    loadRoute();
  }
  window.addEventListener('DOMContentLoaded', boot);
  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    boot();
  }
})();
