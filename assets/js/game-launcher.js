function launchGame(gameId, gameName) {
  if (!gameId) return;

  var apiUrl = (typeof SITE_API_URL !== 'undefined') ? SITE_API_URL + 'launch_game.php' : 'api/launch_game.php';
  var name = (typeof gameName === 'string') ? gameName : '';

  window.MK_GAME_CACHE = window.MK_GAME_CACHE || {};
  var cacheHit = window.MK_GAME_CACHE[gameId];
  var now = Date.now();
  if (cacheHit && (now - cacheHit.ts) < 5 * 60 * 1000 && cacheHit.url) {
    try {
      var u0 = new URL(String(cacheHit.url));
      MK_preconnectOrigin(u0.origin);
    } catch (e0) {}
  }

  var overlay = null;
  try {
    overlay = document.getElementById('mkGameLaunchingOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'mkGameLaunchingOverlay';
      overlay.style.position = 'fixed';
      overlay.style.left = '0';
      overlay.style.top = '0';
      overlay.style.right = '0';
      overlay.style.bottom = '0';
      overlay.style.zIndex = '10090';
      overlay.style.background = 'rgba(0,0,0,0.92)';
      overlay.style.display = 'none';
      overlay.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center"><div style="width:42px;height:42px;border-radius:50%;border:4px solid rgba(255,255,255,.18);border-top-color:#c37601;animation:mkspin 900ms linear infinite"></div></div><style>@keyframes mkspin{to{transform:rotate(360deg)}}</style>';
      document.body.appendChild(overlay);
    }
    overlay.style.display = 'block';
  } catch (e1) {}

  $.ajax({
    url: apiUrl,
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ game_id: gameId, game_name: name, home_url: window.location.href }),
    timeout: 12000,
    success: function (response) {
      var res = null;
      try {
        res = (typeof response === 'string') ? JSON.parse(response) : response;
      } catch (eJson) {
        res = null;
      }
      if (res && res.success) {
        var target = '';
        if (res.redirect_url) {
          try {
            var u1 = new URL(String(res.redirect_url), window.location.origin);
            target = (u1.pathname || '') + (u1.search || '');
          } catch (e0) {
            target = String(res.redirect_url);
          }
        } else if (res.game_url) {
          target = res.game_url;
        }

        if (target) {
          if (res.game_url) window.MK_GAME_CACHE[gameId] = { url: res.game_url, ts: Date.now(), tag: String(res.tag || '') };
          try {
            var u = new URL(target, window.location.origin);
            MK_preconnectOrigin(u.origin);
          } catch (e) {}
          try {
            if (typeof history.pushState === 'function' && typeof window.dispatchEvent === 'function' && target[0] === '/') {
              history.pushState({}, '', target);
              try {
                window.dispatchEvent(new PopStateEvent('popstate'));
              } catch (eEvt) {
                try {
                  var ev = document.createEvent('Event');
                  ev.initEvent('popstate', true, true);
                  window.dispatchEvent(ev);
                } catch (eEvt2) {}
              }
            } else {
              window.location.assign(target);
            }
          } catch (e2) {
            window.location.assign(target);
          }
          try { if (overlay) overlay.style.display = 'none'; } catch (eH) {}
          return;
        }
      }

      try { if (overlay) overlay.style.display = 'none'; } catch (e3) {}
      if (res && res.message === 'Please login to play.') {
        if (typeof $('#loginModal').modal === 'function') $('#loginModal').modal('show'); else alert('Please login to play.');
      } else {
        alert((res && res.message) ? res.message : 'Failed to launch game.');
      }
    },
    error: function () {
      try { if (overlay) overlay.style.display = 'none'; } catch (e2) {}
      alert('Error launching game. Please try again.');
    },
    complete: function () {
      try { if (overlay) overlay.style.display = 'none'; } catch (e4) {}
    }
  });
}

function MK_preconnectOrigin(origin) {
  try {
    if (!origin) return;
    window.__MK_PRECON = window.__MK_PRECON || {};
    if (window.__MK_PRECON[origin]) return;
    window.__MK_PRECON[origin] = 1;
    var link = document.createElement('link');
    link.rel = 'preconnect';
    link.href = origin;
    link.crossOrigin = 'anonymous';
    document.head.appendChild(link);
  } catch (e) {}
}

function MK_prefetchGame(gameId) {
  if (!gameId) return;
  window.MK_GAME_CACHE = window.MK_GAME_CACHE || {};
  if (window.MK_GAME_CACHE[gameId] && (Date.now() - window.MK_GAME_CACHE[gameId].ts) < 5 * 60 * 1000) return;
  window.__MK_PREFETCHED_GAME_IDS = window.__MK_PREFETCHED_GAME_IDS || {};
  if (window.__MK_PREFETCHED_GAME_IDS[gameId]) return;
  var cnt = window.__MK_PREFETCHED_GAME_IDS.__count || 0;
  if (cnt >= 10) return;
  window.__MK_PREFETCHED_GAME_IDS[gameId] = 1;
  window.__MK_PREFETCHED_GAME_IDS.__count = cnt + 1;
  var apiUrl = (typeof SITE_API_URL !== 'undefined') ? SITE_API_URL + 'launch_game.php' : 'api/launch_game.php';
  var baseUrl = (typeof SITE_BASE_URL !== 'undefined') ? SITE_BASE_URL : '';
  $.ajax({
    url: apiUrl,
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ game_id: gameId, home_url: window.location.href, prefetch: 1 }),
    timeout: 2800
  }).done(function (response) {
    try {
      var res = (typeof response === 'string') ? JSON.parse(response) : response;
      var target = '';
      if (res && res.success) {
        if (res.game_url) target = res.game_url;
      }
      if (target) {
        window.MK_GAME_CACHE[gameId] = { url: target, ts: Date.now() };
        try {
          var u = new URL(target, window.location.origin);
          MK_preconnectOrigin(u.origin);
        } catch (e) {}
      }
    } catch (e) {}
  });
}

function MK_bindGamePrefetch() {
  function extractId(el) {
    try {
      if (!el) return '';
      var id = el.getAttribute && el.getAttribute('data-game-id');
      if (id) return String(id);
      var oc = el.getAttribute && el.getAttribute('onclick');
      if (!oc) return '';
      var m = oc.match(/launchGame\(\s*['"]?([^'",)]+)['"]?/i);
      return m && m[1] ? String(m[1]) : '';
    } catch (e) {
      return '';
    }
  }

  var delegate = function (root) {
    var lastHoverId = null, hoverTimer = null;
    $(root).on('mouseenter touchstart', '[data-game-id], [onclick*="launchGame"]', function () {
      var id = $(this).attr('data-game-id') || '';
      if (!id) id = extractId(this);
      if (!id) return;
      lastHoverId = id;
      clearTimeout(hoverTimer);
      hoverTimer = setTimeout(function () {
        if (lastHoverId === id) MK_prefetchGame(id);
      }, 50);
    });
  };
  delegate(document);
  try {
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            var el = e.target;
            var id = el.getAttribute('data-game-id') || '';
            if (!id) id = extractId(el);
            if (id) MK_prefetchGame(id);
            io.unobserve(el);
          }
        });
      }, { rootMargin: '200px 0px' });
      Array.prototype.slice.call(document.querySelectorAll('[data-game-id], [onclick*="launchGame"]')).forEach(function (n, i) {
        if (i < 12) io.observe(n);
      });
    }
  } catch (e) {}
}

if (typeof window.MK_INIT_UI === 'function') {
  var oldInit = window.MK_INIT_UI;
  window.MK_INIT_UI = function () { try { oldInit(); } catch (e) {} try { MK_bindGamePrefetch(); } catch (e) {} };
} else {
  $(document).ready(function () { try { MK_bindGamePrefetch(); } catch (e) {} });
}
