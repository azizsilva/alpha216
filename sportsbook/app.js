/**
 * sportsbook/app.js — Premium Sportsbook UI
 * Design reference: fcbet216.com (Altenar wsdk)
 * Colors: #70f669 green, #979797 gray, #101010 bg
 */
(function(){
'use strict';

// ── CSS self-injection: force-refreshes sportsbook/style.css on every load
// Fixes "CSS cached/disappears" issue on SPA navigation
(function injectCSS() {
  var base = window.location.pathname.replace(/\/sportsbook.*$/i, '/') || '/';
  var newHref = base + 'sportsbook/style.css?v=' + Date.now();
  var existingLink = document.querySelector('#sb-css-link, link[href*="sportsbook/style.css"]');
  if (existingLink) {
    existingLink.href = newHref; // Force fresh fetch
  } else {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = newHref;
    document.head.appendChild(link);
  }
})();

var MARGIN = 0; // No additional margin — BetsAPI provides real Bet365 odds directly
// Dynamic base path calculation
var scriptPath = window.location.pathname;
var BASE = '/';
var pos = scriptPath.indexOf('/sportsbook');
if (pos !== -1) {
    BASE = scriptPath.substring(0, pos + 1);
}
var ODDS_FMT = 'dec'; // 'dec' | 'frac' | 'amer'

/* ── Odds format conversion ──────────────────────────────── */
function formatOdd(decVal) {
  var v = parseFloat(decVal);
  if (isNaN(v) || v < 1.01) return decVal;
  if (ODDS_FMT === 'frac') {
    var profit = v - 1;
    // Find simplest fraction
    var precision = 100;
    var n = Math.round(profit * precision);
    var d = precision;
    function gcd(a, b) { return b ? gcd(b, a % b) : a; }
    var g = gcd(n, d);
    return (n/g) + '/' + (d/g);
  }
  if (ODDS_FMT === 'amer') {
    if (v >= 2) return '+' + Math.round((v - 1) * 100);
    return '' + Math.round(-100 / (v - 1));
  }
  return v.toFixed(2); // decimal default
}

/* ── Period detection (1ère mi-temps, 2ème quart, etc.) ─── */
function getMatchPeriod(m) {
  if (!m.timer) return '';
  var md  = m.timer.md || m.timer.MD || '';
  var tm  = parseInt(m.timer.tm || m.timer.TM || 0) || 0;
  var sid = parseInt(m.sport_id || 1);
  if (md === '1') return 'Mi-temps';
  if (md === '2') return 'Pause';
  // Football (sport 1, 36)
  if (sid === 1 || sid === 36) {
    if (tm <= 45) return '1ère mi-temps';
    if (tm <= 90) return '2ème mi-temps';
    return 'Prolongation';
  }
  // Basketball (18, 83)
  if (sid === 18 || sid === 83) {
    if (tm <= 12) return '1er quart';
    if (tm <= 24) return '2ème quart';
    if (tm <= 36) return '3ème quart';
    return '4ème quart';
  }
  // Tennis
  if (sid === 13) return 'En jeu';
  // Volleyball
  if (sid === 91) return 'Set ' + (m.timer.q || 1);
  return '1ère mi-temps';
}

/* ── Live counting timer (counts up from API snapshot) ─── */
function startMatchTimer(m) {
  clearInterval(window._mdTimerInterval);
  if (!m || !m.timer) return;
  var isHalfTime = (m.timer.md === '1' || m.timer.MD === '1');
  if (isHalfTime) return;

  var baseMin = parseInt(m.timer.tm || m.timer.TM || 0) || 0;
  var baseSec = parseInt(m.timer.ts || m.timer.TS || 0) || 0;
  var totalBase = baseMin * 60 + baseSec;
  var t0 = Date.now();

  window._mdTimerInterval = setInterval(function() {
    var el = document.getElementById('md-timer-display');
    if (!el) { clearInterval(window._mdTimerInterval); return; }
    var elapsed = Math.floor((Date.now() - t0) / 1000);
    var curr = totalBase + elapsed;
    var mm = Math.floor(curr / 60);
    var ss = curr % 60;
    el.textContent = String(mm).padStart(2,'0') + ':' + String(ss).padStart(2,'0');
  }, 1000);
}

/* ── Stats bar (goals, cards, corners, shots) ─────────── */
function renderStatsBar(m, sportId) {
  var st = m.stats || {};
  var s = m.ss ? m.ss.split('-') : [];

  // Helper: get stat value
  function sv(key, idx, def) {
    if (st[key] && st[key][idx] !== undefined) return st[key][idx];
    return def;
  }
  // Football
  if (sportId === 1) {
    var goalsH = s[0] !== undefined ? s[0] : sv('goals',0,'0');
    var goalsA = s[1] !== undefined ? s[1] : sv('goals',1,'0');
    return '<div class="md-stats-bar">'
      + mdStat(goalsH, '⚽', goalsA)
      + mdStat(sv('yellow_cards',0,'-'), '<span class="md-si-yc">▮</span>', sv('yellow_cards',1,'-'))
      + mdStat(sv('red_cards',0,'-'),    '<span class="md-si-rc">▮</span>', sv('red_cards',1,'-'))
      + mdStat(sv('on_target',0,sv('attacks',0,'-')), '<svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2.5a4.5 4.5 0 110 9 4.5 4.5 0 010-9z" opacity=".4"/><circle cx="8" cy="8" r="2" fill="currentColor"/></svg>', sv('on_target',1,sv('attacks',1,'-')))
      + mdStat(sv('corners',0,'-'), '⚑', sv('corners',1,'-'))
      + '</div>';
  }
  // Basketball
  if (sportId === 18 || sportId === 83) {
    var pts = m.ss ? m.ss.split('-') : ['-','-'];
    return '<div class="md-stats-bar">'
      + mdStat(pts[0] || '-', '🏀', pts[1] || '-')
      + mdStat(sv('fouls',0,'-'), '<span style="font-size:9px;color:#979797">FL</span>', sv('fouls',1,'-'))
      + '</div>';
  }
  // Generic
  if (s.length >= 2) {
    return '<div class="md-stats-bar">' + mdStat(s[0], '•', s[1]) + '</div>';
  }
  return '';
}
function mdStat(h, icon, a) {
  return '<span class="md-stat"><span class="md-sv">' + h + '</span>'
    + '<span class="md-si">' + icon + '</span>'
    + '<span class="md-sv">' + a + '</span></span>';
}

/* ── Right-panel pitch viewer ────────────────────────────── */
function showMatchViewer(m) {
  var viewer = document.getElementById('sb-match-viewer');
  if (!viewer) return;
  var isLive = m && m.time_status === '1';
  viewer.style.display = isLive ? 'block' : 'none';

  if (!isLive) return;
  var label = document.getElementById('sb-pitch-label');
  if (label) label.textContent = h((m.home ? m.home.name : '') || '');
}

window.sbViewerTab = function(btn, tab) {
  document.querySelectorAll('.sb-vt').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
};

/* ── Rich skeleton builder (mirrors real card anatomy) ─── */
function buildSkeleton(count) {
  var html = '<div class="sb-skeleton-container">';
  for (var i = 0; i < count; i++) {
    html += '<div class="sb-sk-group">'
      + '<div class="sb-sk-row sb-sk-header">'
      +   '<div class="sb-sk-block" style="width:18px;height:18px;border-radius:50%"></div>'
      +   '<div class="sb-sk-block" style="width:140px;height:11px;margin-left:8px"></div>'
      +   '<div class="sb-sk-block" style="width:38px;height:11px;margin-left:auto"></div>'
      + '</div>'
      + '<div class="sb-sk-match">'
      +   '<div class="sb-sk-match-left">'
      +     '<div class="sb-sk-block" style="width:95px;height:10px;margin-bottom:6px"></div>'
      +     '<div class="sb-sk-block" style="width:115px;height:10px"></div>'
      +   '</div>'
      +   '<div class="sb-sk-match-odds">'
      +     '<div class="sb-sk-btn"></div><div class="sb-sk-btn"></div><div class="sb-sk-btn"></div>'
      +     '<div class="sb-sk-btn"></div><div class="sb-sk-btn"></div>'
      +   '</div>'
      + '</div>'
      + '<div class="sb-sk-match">'
      +   '<div class="sb-sk-match-left">'
      +     '<div class="sb-sk-block" style="width:110px;height:10px;margin-bottom:6px"></div>'
      +     '<div class="sb-sk-block" style="width:80px;height:10px"></div>'
      +   '</div>'
      +   '<div class="sb-sk-match-odds">'
      +     '<div class="sb-sk-btn"></div><div class="sb-sk-btn"></div><div class="sb-sk-btn"></div>'
      +     '<div class="sb-sk-btn"></div><div class="sb-sk-btn"></div>'
      +   '</div>'
      + '</div>'
      + '</div>';
  }
  html += '</div>';
  return html;
}

/* ── App State ─────────────────────────────────────────── */
var S = {
  matches: [], betSlip: [],
  openSport: null, openCountries: {},
  activeSportId: 1, activeAction: 'inplay',
  activeLeagueId: null, activeLeagueName: null, activeLeagueFlag: null,
  sportCounts: {}, sportLiveCounts: {},
  pollingInterval: null,
  activeUpcomingTab: 1,
  activeDateOffset: 0,  // 0 = today, 1 = tomorrow, etc.
  viewMode: 'main',     // 'main' | 'championship' — tracks which view is shown
  champMatches: [],     // matches shown in championship/league view
  activeMarketCat: 'populaire' // active market category in championship view
};

/* ── SVG Icons (extracted from reference site shadow DOM) ─ */
var ICON = {
  home: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 14.6666V7.99992H10V14.6666M2 5.99992L8 1.33325L14 5.99992V13.3333C14 13.6869 13.8595 14.026 13.6095 14.2761C13.3594 14.5261 13.0203 14.6666 12.6667 14.6666H3.33333C2.97971 14.6666 2.64057 14.5261 2.39052 14.2761C2.14048 14.026 2 13.6869 2 13.3333V5.99992Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  stats: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 13V6H6V13V3H10V13V8H14V13H2Z" stroke="currentColor" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  star: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 0.667C8.254 0.667 8.485 0.811 8.598 1.038L10.503 4.898L14.763 5.52C15.014 5.557 15.223 5.733 15.301 5.974C15.379 6.216 15.314 6.481 15.132 6.658L12.05 9.66L12.777 13.901C12.82 14.151 12.717 14.404 12.512 14.553C12.307 14.702 12.034 14.722 11.81 14.603L8 12.6L4.19 14.603C3.966 14.722 3.693 14.702 3.488 14.553C3.283 14.404 3.18 14.151 3.223 13.901L3.95 9.66L0.868 6.658C0.686 6.481 0.621 6.216 0.699 5.974C0.777 5.733 0.986 5.557 1.237 5.52L5.497 4.898L7.402 1.038C7.514 0.811 7.746 0.667 8 0.667Z" stroke="currentColor" stroke-width="1.2" fill="none"/></svg>',
  chevronDown: '<svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  chevronRight: '<svg width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 2L6 6L2 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  chevronUp: '<svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 5L5 1L1 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  bolt: '<svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 1L1 10H7L6 17L13 8H7L8 1Z" fill="currentColor"/></svg>',
  search: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 14L11.1 11.1M7.33 2C5.92 2 4.56 2.56 3.56 3.56C2.56 4.56 2 5.92 2 7.33C2 8.74 2.56 10.1 3.56 11.1C4.56 12.1 5.92 12.67 7.33 12.67C8.74 12.67 10.1 12.1 11.1 11.1C12.1 10.1 12.67 8.74 12.67 7.33C12.67 5.92 12.1 4.56 11.1 3.56C10.1 2.56 8.74 2 7.33 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
  minus: '<svg width="12" height="2" viewBox="0 0 12 2" fill="none"><path d="M1 1H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
  arrowLeft: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  // Sport icons (32x32 viewBox from reference)
  football: '<svg width="20" height="20" viewBox="0 0 32 33" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M28.3514 5.82922C26.7793 3.9223 24.7691 2.39532 22.4814 1.37952C20.4987 0.499084 18.3093 0 16 0C13.6884 0 11.4969 0.500244 9.51263 1.38239C7.31567 2.35901 5.38202 3.81335 3.84009 5.61737C1.45081 8.4129 0 12.0343 0 16C0 16.5004 0.0298462 16.9937 0.0748291 17.4824C0.335938 20.3214 1.34015 22.9412 2.88916 25.1552C4.98395 28.1493 8.07538 30.3843 11.6843 31.3944C13.059 31.7792 14.5024 32 16 32C17.5426 32 19.0293 31.7697 20.4407 31.3623C24.095 30.3076 27.2152 27.9962 29.2873 24.913C30.9767 22.3996 31.9672 19.3801 31.9934 16.1301C31.9938 16.0863 32 16.0439 32 16C32 12.137 30.6307 8.59399 28.3514 5.82922Z" fill="currentColor" opacity="0.15"/><path d="M18.371 19.7656H13.629L12.165 15.257L16 12.471L19.836 15.257L18.371 19.7656ZM16 2C17.337 2 18.627 2.2 19.853 2.552L16 4.27L12.142 2.553C13.369 2.2 14.66 2 16 2ZM6.287 11.682L5.897 6.334C6.953 5.231 8.184 4.3 9.549 3.588L15 6.015V10.725L10.87 13.727L6.287 11.682ZM4.066 8.718L4.307 12.04L2.054 14.938C2.225 12.67 2.939 10.557 4.066 8.718ZM4.102 23.341C3.121 21.757 2.45 19.968 2.167 18.049L5.64 13.584L10.178 15.617L11.784 20.566L8.908 24.209L4.102 23.341ZM5.932 25.705L8.686 26.202L10.122 28.689C8.545 27.955 7.126 26.942 5.932 25.705ZM16 30C14.968 30 13.965 29.88 12.996 29.667L10.526 25.389L13.388 21.766H18.678L22.257 25.401L19.238 29.607C18.197 29.855 17.116 30 16 30ZM22.614 28.334L24.158 26.182L26.062 25.711C25.057 26.752 23.898 27.642 22.614 28.334ZM28.002 23.171L23.874 24.192L20.236 20.497L21.826 15.607L26.456 13.541L29.953 16.936C29.801 19.205 29.11 21.323 28.002 23.171ZM28.033 8.881C28.957 10.436 29.585 12.181 29.847 14.046L27.711 11.972L28.033 8.881ZM26.269 6.516L25.732 11.673L21.132 13.726L17.697 11.231V6.015L22.446 3.585C23.887 4.336 25.175 5.333 26.269 6.516Z" fill="currentColor"/></svg>',
  basketball: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M29.991 23.764C31.851 20.408 32.425 16.53 31.691 12.854C31.682 12.803 31.665 12.757 31.655 12.709C31.551 12.214 31.425 11.724 31.274 11.239C31.027 10.512 30.864 10.075 30.677 9.644C29.867 7.802 28.767 6.136 27.316 4.685C25.995 3.365 24.495 2.341 22.901 1.579C20.268 0.435 18.135 0 16 0C14.846 0 13.695 0.138 12.563 0.386C9.525 1.102 6.865 2.504 4.682 4.686C4.082 5.286 3.557 5.931 3.073 6.596C-1.361 14.425 -0.455 22.178 4.682 27.314C6.172 28.804 7.887 29.927 9.714 30.707C9.739 30.726 9.775 30.736 9.808 30.75C10.066 30.858 10.329 30.949 10.592 31.043C11.377 31.309 11.744 31.412 12.114 31.504C13.401 31.824 14.699 32 16 32C17.75 32 19.495 31.697 21.169 31.127C21.747 30.921 22.038 30.812 22.325 30.688C24.197 29.867 25.865 28.766 27.319 27.313C27.872 26.76 28.359 26.168 28.814 25.559C29.155 25.103 29.464 24.641 29.746 24.166C29.826 24.033 29.914 23.902 29.991 23.764ZM22.468 3.582C22.834 8.876 22.39 13.07 21.428 16.453C17.872 15.532 16.818 10.954 15.799 6.514C15.423 4.878 15.064 3.352 14.587 2.072C15.054 2.024 15.525 2 16 2C18.29 2 20.494 2.551 22.468 3.582ZM6.097 6.1C7.918 4.28 10.157 3.039 12.593 2.432C13.091 3.669 13.475 5.327 13.851 6.96C14.894 11.509 16.179 17.09 20.807 18.357C20.533 19.087 20.233 19.772 19.911 20.421C13.62 17.261 8.388 12.542 4.424 8.133C4.91 7.418 5.463 6.734 6.097 6.1ZM6.097 25.899C5.2 25.003 4.466 24.008 3.864 22.957C5.049 23.321 6.354 23.577 7.874 23.747C11.764 24.182 14.471 24.812 16.361 25.502C14.89 27.052 13.302 28.274 11.759 29.341C9.646 28.674 7.708 27.51 6.097 25.899ZM25.903 25.899C24.884 26.917 23.734 27.755 22.494 28.406C22.395 27.95 22.218 27.471 21.913 26.983C21.41 26.178 20.642 25.462 19.609 24.828C20.027 24.273 20.431 23.686 20.813 23.059C22.676 23.883 24.62 24.571 26.642 25.083C26.404 25.36 26.165 25.637 25.903 25.899Z" fill="#313131"/></svg>',
  tennis: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M4.686 27.314C7.699 30.327 11.615 31.887 15.562 31.994C16.22 32.012 16.878 31.99 17.534 31.927C21.121 31.577 24.587 30.04 27.314 27.314C30.04 24.587 31.577 21.121 31.924 17.561C32.011 16.465 32.009 15.617 31.995 15.562C31.887 11.615 30.327 7.699 27.314 4.686C23.792 1.165 19.037 -0.372 14.439 0.076C10.879 0.423 7.413 1.96 4.686 4.686C1.96 7.413 0.423 10.879 0.076 14.439C0.05 14.703 0.031 14.968 0.018 15.233C0 15.634 -0.005 16.036 0.006 16.438C0.113 20.385 1.673 24.301 4.686 27.314ZM6.1 25.9C0.633 20.432 0.633 11.568 6.1 6.1C8.311 3.89 11.077 2.573 13.95 2.15C13.401 8.412 8.412 13.401 2.15 13.95C2.052 14.618 2.002 15.293 2 15.967C9.488 15.474 15.474 9.487 15.967 2C19.561 1.992 23.158 3.358 25.9 6.1C28.641 8.843 30.008 12.439 30 16.033C22.512 16.526 16.526 22.512 16.033 30C12.439 30.008 8.842 28.641 6.1 25.9ZM18.05 29.85C18.599 23.588 23.588 18.599 29.85 18.05C29.427 20.923 28.11 23.689 25.9 25.9C23.689 28.11 20.923 29.427 18.05 29.85Z" fill="#313131"/></svg>',
  volleyball: '<svg width="20" height="20" viewBox="0 0 32 33" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M31.7197 12.997C31.1405 9.952 29.6751 7.043 27.3175 4.686C24.8408 2.21 21.7529 0.732 18.541 0.218C17.6998 0.083 16.8505 0 16.0003 0C15.8012 0.00999999 13.0125 0.045 10.2377 0.813C8.98832 4.441 9.37644 3.408 7.36787 4.988C5.65728 3.25 4.06567 8.71814 4.30731 12.04L2.05377 14.9377C2.22528 12.6704 2.93909 10.5575 4.06567 8.71814C1.0301 17.479 1.60526 11.389 5.26729 7.019C5.25028 11.021 6.09952 16.264 9.2334 21.227C7.40689 22.136 5.43933 22.543 3.5458 22.389C1.0301 17.479 1.60526 11.389 5.26729 7.019C-1.14752 16.112 1.10811 21.856 4.68212 27.315C5.73441 28.367 6.90274 29.226 8.13809 29.924C12.4683 31.693 14.2308 32.002 15.9983 32.002C20.0944 32.002 24.1906 30.44 27.3155 27.316C29.204 25.066 30.1222 23.528 31.3876 21.157C32.0138 18.536 31.9998 15.916 31.9998 15.916C31.9958 14.937 31.9028 13.96 31.7197 12.997Z" fill="#313131"/></svg>',
  tableTennis: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M19.553 26.735C16.96 27.171 14.355 26.755 11.735 26.016C11.71 26.009 11.689 26.011 11.677 26.014L11.674 26.015L7.555 30.133L7.554 30.132L6.857 30.828C5.295 32.39 2.763 32.39 1.201 30.828C-0.361 29.266 -0.361 26.733 1.201 25.171L5.969 20.403L5.97 20.4L5.971 20.395C5.974 20.383 5.975 20.364 5.968 20.339C5.213 17.696 4.778 15.071 5.204 12.457C5.64 9.79 6.937 7.334 9.293 4.978C14.854 -0.583 23.59 -1.938 28.739 3.21C33.888 8.359 32.532 17.095 26.971 22.656C24.634 24.993 22.198 26.29 19.553 26.735ZM20.011 24.597L7.352 11.938C7.827 10.05 8.872 8.229 10.708 6.392C15.785 1.316 23.224 0.524 27.325 4.625C31.425 8.725 30.634 16.165 25.557 21.242C23.721 23.078 21.9 24.122 20.011 24.597Z" fill="#313131"/><circle cx="3" cy="3" r="3" fill="#313131"/></svg>',
  hockey: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M19.504 19.09C19.544 19.019 19.595 18.921 19.658 18.791C19.85 18.396 20.09 17.832 20.369 17.122C20.924 15.71 21.58 13.851 22.241 11.89C23.562 7.976 24.87 3.755 25.373 2.118C25.763 0.848 26.932 0 28.242 0H28.905C30.908 0 32.293 1.91 31.802 3.775C29.075 14.142 27.371 20.118 25.993 23.651C25.302 25.421 24.64 26.723 23.882 27.688C23.069 28.721 22.206 29.295 21.276 29.701C19.35 30.544 16.747 31.106 14.041 31.465C11.301 31.828 8.313 32 5.538 32C3.911 32 2.395 31.617 1.321 30.509C0.255 29.411 0 27.985 0 26.77V25.074C0 22.365 2.168 20.125 5.197 20.125C5.354 20.125 5.632 20.132 6.009 20.142C7.308 20.175 9.776 20.238 12.442 20.141C14.141 20.08 15.825 19.954 17.209 19.726C17.901 19.612 18.475 19.479 18.918 19.333C19.215 19.234 19.4 19.148 19.504 19.09ZM2 8C2 6.997 2.517 6.243 3.019 5.768C3.517 5.298 4.13 4.966 4.722 4.729C5.917 4.251 7.43 4 9 4C10.57 4 12.084 4.251 13.278 4.729C13.87 4.966 14.483 5.298 14.981 5.768C15.483 6.243 16 6.997 16 8V12.115C16 12.478 15.935 12.962 15.629 13.403C14.955 14.371 13.161 16 9 16C4.839 16 3.045 14.371 2.371 13.403C2.065 12.962 2 12.478 2 12.115V8Z" fill="#313131"/></svg>',
  esports: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M29.519 22.07H27.161V19.78C27.161 19.19 26.868 18.9 26.294 18.9C25.72 18.9 25.427 19.19 25.427 19.78V22.07H23.069C22.495 22.07 22.213 22.35 22.213 22.9C22.213 23.45 22.495 23.73 23.069 23.73H25.427V26.02C25.427 26.61 25.72 26.89 26.294 26.89C26.868 26.89 27.161 26.61 27.161 26.02V23.73H29.519C30.093 23.73 30.375 23.45 30.375 22.9C30.375 22.35 30.093 22.07 29.519 22.07Z" fill="#313131"/><path d="M10 21C10.828 21 11.5 20.328 11.5 19.5C11.5 18.672 10.828 18 10 18C9.172 18 8.5 18.672 8.5 19.5C8.5 20.328 9.172 21 10 21Z" fill="#313131"/><path d="M13 24C13.828 24 14.5 23.328 14.5 22.5C14.5 21.672 13.828 21 13 21C12.172 21 11.5 21.672 11.5 22.5C11.5 23.328 12.172 24 13 24Z" fill="#313131"/><path fill-rule="evenodd" clip-rule="evenodd" d="M3.168 6C1.421 6 0 7.421 0 9.168V24.832C0 26.579 1.421 28 3.168 28H28.832C30.579 28 32 26.579 32 24.832V9.168C32 7.421 30.579 6 28.832 6H3.168ZM2 9.168C2 8.525 2.525 8 3.168 8H28.832C29.475 8 30 8.525 30 9.168V24.832C30 25.475 29.475 26 28.832 26H3.168C2.525 26 2 25.475 2 24.832V9.168Z" fill="#313131"/></svg>',
  handball: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M27.91 4.2C27.499 4.204 27.092 4.286 26.71 4.44C26.543 3.771 26.157 3.178 25.613 2.754C25.069 2.33 24.4 2.099 23.71 2.1C23.298 2.099 22.89 2.181 22.51 2.34C22.341 1.672 21.955 1.079 21.411 0.656C20.868 0.232 20.199 0.001 19.51 0C18.821 0.001 18.152 0.232 17.609 0.656C17.066 1.079 16.679 1.672 16.51 2.34C16.166 2.203 15.8 2.126 15.43 2.11C15.37 2.11 15.31 2.01 15.24 1.97C13.624 0.88 11.719 0.298 9.77 0.3C7.178 0.303 4.693 1.334 2.861 3.168C1.029 5.002 0 7.488 0 10.08C-0.005 11.743 0.417 13.38 1.226 14.834C2.035 16.287 3.204 17.508 4.62 18.38L10.14 27.57C10.892 28.942 11.999 30.086 13.344 30.883C14.69 31.68 16.226 32.101 17.79 32.1H22.34C24.647 32.097 26.858 31.18 28.489 29.549C30.12 27.918 31.037 25.707 31.04 23.4V7.3C31.04 6.89 30.959 6.485 30.801 6.107C30.644 5.729 30.413 5.386 30.122 5.097C29.831 4.809 29.485 4.581 29.106 4.427C28.726 4.273 28.32 4.196 27.91 4.2Z" fill="#313131"/></svg>',
  badminton: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="14" cy="8" r="5" stroke="currentColor" stroke-width="1.3"/><path d="M11 12L4 26M17 12L24 26M8 26h16" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><line x1="14" y1="13" x2="14" y2="28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
  mma: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.57 17.24C4.982 16.083 4 13.678 4 10.524C4 6.024 5 5.024 5 5.024C5 5.024 6.806 1.966 14.5 0.524C22.194 -0.919 24.5 1.024 25 1.524C25.5 2.024 25.5 4.524 25.5 8.524C25.5 9.333 25.459 10.202 25.394 11.074L28.52 12.012C29.984 12.451 31.003 14.012 30.418 15.611C29.937 16.927 29.186 18.474 28.117 19.832C27.183 21.02 25.974 22.1 24.458 22.721C24.102 23.227 23.594 23.619 23 23.829V28C23 30.209 21.209 32 19 32H10C7.791 32 6 30.209 6 28V23.236C5.386 22.687 5 21.889 5 21V19C5 18.343 5.212 17.734 5.57 17.24ZM7 18C7 17.448 7.448 17 8 17H22C22.552 17 23 17.448 23 18V22C23 22.552 22.552 23 22 23H8C7.448 23 7 22.552 7 22V18ZM6.412 6.674C6.413 6.67 6.552 6.297 6.862 5.891C7.047 5.693 7.396 5.365 7.976 4.977C8.258 4.789 8.597 4.585 9 4.374V9C9 9.552 9.448 10 10 10C10.552 10 11 9.552 11 9V4L14 2.665V8C14 8.552 14.448 9 15 9C15.552 9 16 8.552 16 8V2.296L19 2.007V7C19 7.552 19.448 8 20 8C20.552 8 21 7.552 21 7V2.078C21.385 2.121 21.717 2.179 22 2.244C22.694 2.404 23.113 2.608 23.344 2.753C23.381 2.989 23.415 3.334 23.44 3.816C23.499 4.939 23.5 6.501 23.5 8.524C23.5 10.389 23.264 12.653 23.018 14.497C22.944 15.051 22.869 15.56 22.802 16H7.191C7.089 15.781 6.979 15.525 6.868 15.235C6.435 14.103 6 12.478 6 10.524C6 8.413 6.236 7.246 6.412 6.674Z" fill="#313131"/></svg>',
  efootball: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 8H28V26C28 27 27 28 26 28H6C5 28 4 27 4 26V8Z" stroke="currentColor" stroke-width="1.5"/><path d="M4 8L7 4H25L28 8" stroke="currentColor" stroke-width="1.3"/><path d="M12 16h4m-2-2v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="22" cy="18" r="1.5" fill="currentColor"/><circle cx="20" cy="15" r="1.5" fill="currentColor"/></svg>',
  cycling: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="22" r="6" stroke="currentColor" stroke-width="1.5"/><circle cx="24" cy="22" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M16 22L20 14L16 8H12L8 14L16 22" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  default: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="10" r="3" fill="currentColor" opacity="0.7"/><path d="M10 24C10 20.686 12.686 18 16 18C19.314 18 22 20.686 22 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
};

/* ── Sports list — matches fcbet216 exactly ───────────────── */
var SPORTS = [
  { id: 1,   name: 'Football',           icon: ICON.football,    live: true },
  { id: 18,  name: 'Basketball',         icon: ICON.basketball,  live: true },
  { id: 13,  name: 'Tennis',             icon: ICON.tennis,      live: true },
  { id: 91,  name: 'Volleyball',         icon: ICON.volleyball,  live: true },
  { id: 107, name: 'Tennis de ...',      icon: ICON.tableTennis, live: true },
  { id: 17,  name: 'Hockey su...',       icon: ICON.hockey,      live: true },
  { id: 151, name: 'E-sports +',         icon: ICON.esports,     live: true },
  { id: 16,  name: 'Football Américain', icon: ICON.default,     live: false },
  { id: 78,  name: 'Handball',           icon: ICON.handball,    live: true },
  { id: 45,  name: 'Badminton',          icon: ICON.badminton,   live: true },
  { id: 117, name: 'MMA',               icon: ICON.mma,         live: false },
  { id: 36,  name: 'E-Football',         icon: ICON.efootball,   live: true },
  { id: 83,  name: 'E-Basketb...',       icon: ICON.basketball,  live: true },
  { id: 92,  name: 'Cyclisme',           icon: ICON.cycling,     live: false },
  { id: 56,  name: 'Rugby',              icon: ICON.default,     live: false },
  { id: 66,  name: 'Baseball',           icon: ICON.default,     live: true },
  { id: 48,  name: 'Cricket',            icon: ICON.default,     live: true },
  // Additional sports from fcbet216
  { id: 40,  name: 'Auto-moto',          icon: ICON.default,     live: false },
  { id: 19,  name: 'Football Australien',icon: ICON.default,     live: false },
  { id: 94,  name: 'Beach Volley',       icon: ICON.default,     live: false },
  { id: 10,  name: 'Boxe',               icon: ICON.default,     live: true },
  { id: 90,  name: 'Fléchettes',         icon: ICON.default,     live: false },
  { id: 83,  name: 'Futsal',             icon: ICON.default,     live: true },
  { id: 46,  name: 'Golf',               icon: ICON.default,     live: true },
  { id: 14,  name: 'Rugby League',       icon: ICON.default,     live: false },
  { id: 75,  name: 'Spéciaux',           icon: ICON.default,     live: false },
  { id: 110, name: 'Waterpolo',          icon: ICON.default,     live: false },
  { id: 152, name: 'E-Ice Hoc...',       icon: ICON.default,     live: true },
  { id: 153, name: 'Lacrosse',           icon: ICON.default,     live: false },
  { id: 154, name: 'Short Foo...',       icon: ICON.default,     live: true },
];

var PREMIUM_LEAGUES = [
  { id: '94',  name: 'Coupe du Monde 2026', flag: 'un',    sport: 1 },
  { id: '572', name: 'Ligue des Champions', flag: 'eu',    sport: 1 },
  { id: '573', name: 'Ligue Conférence',    flag: 'eu',    sport: 1 },
  { id: '17',  name: 'Premier League',      flag: 'gb-eng',sport: 1 },
  { id: '119', name: 'LaLiga',             flag: 'es',    sport: 1 },
  { id: '167', name: 'Serie A',            flag: 'it',    sport: 1 },
  { id: '78',  name: 'Bundesliga',         flag: 'de',    sport: 1 },
  { id: '168', name: 'Ligue 1',           flag: 'fr',    sport: 1 },
  { id: '320', name: 'Eredivisie',         flag: 'nl',    sport: 1 },
  { id: '142', name: 'Division 1A',        flag: 'be',    sport: 1 },
];

/* ── Utils ───────────────────────────────────────────────── */
function h(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function margin(v) { return Math.max(1.01, +(parseFloat(v) * (1 - MARGIN)).toFixed(2)); }
function rand(a, b) { return (a + Math.random() * (b - a)).toFixed(2); }

// Stable seeded random — same match always shows same odds
function seedRand(seed, min, max) {
  var s = Math.abs(parseInt(String(seed).replace(/\D/g,'') || '0') % 999983);
  var x = Math.sin(s * 9301 + 49297) * 233280;
  var r = x - Math.floor(x);
  return +(min + r * (max - min)).toFixed(2);
}

function odds(m) {
  if (m._o) return m._o;
  // Use real BetsAPI odds if already stored in match data
  var real = extractRealOdds(m);
  if (real) { 
    m._o = real; 
    return m._o; 
  }
  
  // No fake odds fallback. Return empty structure if no real odds exist.
  m._o = { h: 0, x: 0, a: 0, ou: 0, ov: 0, un: 0 };
  return m._o;
}

// Extract REAL BetsAPI odds — priority: live_odds > odds.live/updated > odds.init > flat odds
// NO SEEDED FALLBACK HERE — seeded fallback is in odds() only when this returns null
function extractRealOdds(m) {
  // ── 1. live_odds stored by sync_daemon via event?FI ──────────────────
  var lo = m.live_odds;
  if (lo && lo.h) { // h is minimum — x/a can be null for 2-way sports (basketball, tennis)
    return {
      h:  applyMargin(parseFloat(lo.h || 0)),
      x:  lo.x ? applyMargin(parseFloat(lo.x)) : null,  // null = no draw (basketball/tennis)
      a:  lo.a ? applyMargin(parseFloat(lo.a)) : null,
      ou: lo.ou_line  || 2.5,
      ov: lo.ou_over  ? applyMargin(parseFloat(lo.ou_over))  : null,
      un: lo.ou_under ? applyMargin(parseFloat(lo.ou_under)) : null,
      live: true
    };
  }

  // ── 2. BetsAPI native odds object in inplay_filter response ─────────
  // BetsAPI returns odds in various formats — try all of them
  var oddsObj = m.odds;
  if (oddsObj && typeof oddsObj === 'object') {
    var parsed = null;
    // Try keyed sub-objects first: live > updated > init > start
    var keys = ['live','updated','init','start'];
    for (var i = 0; i < keys.length; i++) {
      parsed = _parseOddsFlat(oddsObj[keys[i]]);
      if (parsed) break;
    }
    // Try flat format: {"1":"2.01","X":"3.55","2":"4.23"}
    if (!parsed) parsed = _parseOddsFlat(oddsObj);
    if (parsed) {
      return {
        h: applyMargin(parsed.h), x: applyMargin(parsed.x), a: applyMargin(parsed.a),
        ou: 2.5, ov: null, un: null, live: true
      };
    }
  }

  // ── 3. main.sp (prematch structure from BetsAPI) ─────────────────────
  var sp = m.main && m.main.sp;
  if (sp) {
    var gl = sp['1_1'] || sp['game_lines'] || (Object.keys(sp).length ? sp[Object.keys(sp)[0]] : null);
    if (gl && gl.odds && Array.isArray(gl.odds)) {
      var h2 = null, x2 = null, a2 = null;
      gl.odds.forEach(function(o) {
        var n = String(o.name || o.NA || '').toLowerCase();
        var v = parseFloat(o.odds || o.OD || 0);
        if (v < 1.01) return;
        if (n === '1' || n === 'home')  h2 = v;
        if (n === 'x' || n === 'draw')  x2 = v;
        if (n === '2' || n === 'away')  a2 = v;
      });
      if (h2 && x2 && a2) {
        return { h: applyMargin(h2), x: applyMargin(x2), a: applyMargin(a2), ou: 2.5, live: true };
      }
    }
  }
  return null;  // no real odds available → caller uses seedRand fallback
}

function _parseOddsFlat(o) {
  if (!o || typeof o !== 'object') return null;
  var h = parseFloat(o['1'] || o.home || o.h || 0);
  var x = parseFloat(o['X'] || o.x || o.draw || 0);
  var a = parseFloat(o['2'] || o.away || o.a || 0);
  return (h > 1.01 && x > 1.01 && a > 1.01) ? { h: h, x: x, a: a } : null;
}

/* Return raw BetsAPI odds without arbitrary reduction */
function applyMargin(rawOdd) {
  var val = parseFloat(rawOdd);
  return isNaN(val) ? 0 : val;
}

// Map sidebar display names → EXACT DB search terms (avoids cross-country false matches)
// e.g. 'Premier League' must be 'England Premier League' to exclude Australian leagues
var LEAGUE_DB_SEARCH = {
  // Football top leagues — use EXACT BetsAPI country-prefixed names
  'Coupe du Monde 2026':   'FIFA World Cup 2026',
  'Ligue des Champions':   'UEFA Champions League',
  'Ligue Conférence':      'UEFA Europa Conference League',
  'Premier League':        'England Premier League',
  'LaLiga':                'Spain La Liga',
  'Serie A':               'Italy Serie A',
  'Bundesliga':            'Germany Bundesliga',
  'Ligue 1':               'France Ligue 1',
  'Eredivisie':            'Netherlands Eredivisie',
  'Division 1A':           'Belgium First Division A',
  'Copa Libertadores':     'Copa Libertadores',
  'Copa Sudamericana':     'Copa Sudamericana',
  'Euroligue':             'Euroleague',
  'NBA':                   'NBA',
  'Roland Garros, Féminin Simple': 'French Open Women',
  'Roland Garros, Hommes Simple':  'French Open Men',
};

function isLeagueMatch(n, apiLeagueName) {
  n = (n || '').toLowerCase().trim();
  var api = (apiLeagueName || '').toLowerCase().trim();
  if (!n || !api) return false;

  // Named mappings — check FIRST to avoid false cross-country matches
  // e.g. "Serie A" must only match "Italy Serie A", NOT "Brazil Serie A"
  var MAP = {
    'laliga':             ['spain la liga', 'spain primera division'],
    'champions':          ['uefa champions league'],
    'ligue des champions':['uefa champions league'],
    'conférence':         ['uefa europa conference league'],
    'conference':         ['uefa europa conference league'],
    'premier league':     ['england premier league'],
    'division 1a':        ['belgium first division a', 'belgium pro league'],
    'monde':              ['fifa world cup', 'world cup 2026'],
    'world cup':          ['fifa world cup', 'world cup 2026'],
    'serie a':            ['italy serie a'],
    'bundesliga':         ['germany bundesliga'],
    'ligue 1':            ['france ligue 1'],
    'eredivisie':         ['netherlands eredivisie'],
    'copa libertadores':  ['copa libertadores'],
    'copa sudamericana':  ['copa sudamericana'],
    'euroligue':          ['euroleague', 'eurocup'],
    'nba':                ['nba'],
    'roland garros':      ['roland garros', 'french open'],
    'euroleague':         ['euroleague'],
    'europa league':      ['uefa europa league'],
  };

  // If n matches a MAP key, ONLY match against its specific variants
  // This prevents "Serie A" matching "Brazil Serie A"
  var hasMapping = false;
  for (var key in MAP) {
    if (n === key || n.indexOf(key) !== -1 || key.indexOf(n) !== -1) {
      hasMapping = true;
      var variants = MAP[key];
      for (var i = 0; i < variants.length; i++) {
        if (api.indexOf(variants[i]) !== -1) return true;
      }
    }
  }
  // If we had a mapping but none matched, return false (strict matching)
  if (hasMapping) return false;

  // Fallback: direct contains check for unmapped leagues
  if (api.indexOf(n) !== -1) return true;

  return false;
}

// BetsAPI league name → display country name (sorted by specificity)
var COUNTRY_FLAGS = {
  'Angleterre':'gb-eng','Ecosse':'gb-sct','Pays de Galles':'gb-wls','Irlande du Nord':'gb-nir',
  'France':'fr','Allemagne':'de','Italie':'it','Espagne':'es','Portugal':'pt',
  'Pays-Bas':'nl','Belgique':'be','Pologne':'pl','Suède':'se','Norvège':'no',
  'Danemark':'dk','Turquie':'tr','Russie':'ru','Ukraine':'ua','Croatie':'hr',
  'Grèce':'gr','Serbie':'rs','Roumanie':'ro','Autriche':'at','Suisse':'ch',
  'Hongrie':'hu','Tchéquie':'cz','Slovaquie':'sk','Finlande':'fi','Islande':'is',
  'Irlande':'ie','Lettonie':'lv','Lituanie':'lt','Estonie':'ee','Bulgarie':'bg',
  'Slovénie':'si','Albanie':'al','Bosnie':'ba','Macédoine':'mk','Moldavie':'md',
  'Biélorussie':'by','Andorre':'ad','Malte':'mt','Chypre':'cy','Luxembourg':'lu',
  'Géorgie':'ge','Arménie':'am','Azerbaïdjan':'az','Kazakhstan':'kz',
  'Ouzbékistan':'uz','Liechtenstein':'li','Gibraltar':'gi','Kosovo':'xk',
  'Australie':'au','Nouvelle-Zélande':'nz','Chine':'cn','Japon':'jp',
  'Corée':'kr','Inde':'in','Indonésie':'id','Thaïlande':'th','Vietnam':'vn',
  'Malaisie':'my','Singapour':'sg','Philippines':'ph','Hong Kong':'hk',
  'Taipei':'tw','Bangladesh':'bd','Pakistan':'pk','Sri Lanka':'lk',
  'Myanmar':'mm','Cambodge':'kh','Iran':'ir','Irak':'iq',
  'Arabie Saoudite':'sa','EAU':'ae','Qatar':'qa','Jordanie':'jo','Israël':'il',
  'USA':'us','Canada':'ca','Mexique':'mx','Brésil':'br','Argentine':'ar',
  'Colombie':'co','Chili':'cl','Pérou':'pe','Venezuela':'ve','Uruguay':'uy',
  'Paraguay':'py','Bolivie':'bo','Équateur':'ec','Costa Rica':'cr',
  'Guatemala':'gt','Honduras':'hn','Panama':'pa','El Salvador':'sv',
  'Nicaragua':'ni','Trinité-et-Tobago':'tt','Jamaïque':'jm',
  'Maroc':'ma','Algérie':'dz','Tunisie':'tn','Égypte':'eg','Sénégal':'sn',
  'Cameroun':'cm','Nigeria':'ng','Ghana':'gh','Kenya':'ke','Malawi':'mw',
  'Afrique du Sud':'za','Zimbabwe':'zw','Tanzanie':'tz','Ouganda':'ug',
  'Zambie':'zm','Angola':'ao','Mozambique':'mz','Côte d\'Ivoire':'ci',
  'Europe':'eu','World':'un','International':'un','Americas':'un',
  'Africa':'un','Asia':'un',
};

// BetsAPI country prefix → display country name
var BETSAPI_COUNTRY_PREFIX = {
  'england':'Angleterre','scotland':'Ecosse','wales':'Pays de Galles',
  'northern ireland':'Irlande du Nord','france':'France','germany':'Allemagne',
  'italy':'Italie','spain':'Espagne','portugal':'Portugal','netherlands':'Pays-Bas',
  'belgium':'Belgique','poland':'Pologne','sweden':'Suède','norway':'Norvège',
  'denmark':'Danemark','turkey':'Turquie','russia':'Russie','ukraine':'Ukraine',
  'croatia':'Croatie','greece':'Grèce','serbia':'Serbie','romania':'Roumanie',
  'austria':'Autriche','switzerland':'Suisse','hungary':'Hongrie','czech':'Tchéquie',
  'czechia':'Tchéquie','slovakia':'Slovaquie','finland':'Finlande','iceland':'Islande',
  'ireland':'Irlande','latvia':'Lettonie','lithuania':'Lituanie','estonia':'Estonie',
  'bulgaria':'Bulgarie','slovenia':'Slovénie','albania':'Albanie','bosnia':'Bosnie',
  'north macedonia':'Macédoine','moldova':'Moldavie','belarus':'Biélorussie',
  'andorra':'Andorre','malta':'Malte','cyprus':'Chypre','luxembourg':'Luxembourg',
  'georgia':'Géorgie','armenia':'Arménie','azerbaijan':'Azerbaïdjan',
  'kazakhstan':'Kazakhstan','uzbekistan':'Ouzbékistan','liechtenstein':'Liechtenstein',
  'gibraltar':'Gibraltar','kosovo':'Kosovo','faroe':'Îles Féroé',
  'australia':'Australie','new zealand':'Nouvelle-Zélande','china':'Chine',
  'japan':'Japon','south korea':'Corée','korea':'Corée','india':'Inde',
  'indonesia':'Indonésie','thailand':'Thaïlande','vietnam':'Vietnam',
  'malaysia':'Malaisie','singapore':'Singapour','philippines':'Philippines',
  'hong kong':'Hong Kong','chinese taipei':'Taipei','taiwan':'Taipei',
  'bangladesh':'Bangladesh','pakistan':'Pakistan','sri lanka':'Sri Lanka',
  'myanmar':'Myanmar','cambodia':'Cambodge','iran':'Iran','iraq':'Irak',
  'saudi arabia':'Arabie Saoudite','uae':'EAU','united arab':'EAU',
  'qatar':'Qatar','jordan':'Jordanie','israel':'Israël',
  'usa':'USA','united states':'USA','canada':'Canada',
  'mexico':'Mexique','brazil':'Brésil','argentina':'Argentine',
  'colombia':'Colombie','chile':'Chili','peru':'Pérou','venezuela':'Venezuela',
  'uruguay':'Uruguay','paraguay':'Paraguay','bolivia':'Bolivie',
  'ecuador':'Équateur','costa rica':'Costa Rica','guatemala':'Guatemala',
  'honduras':'Honduras','panama':'Panama','el salvador':'El Salvador',
  'nicaragua':'Nicaragua','trinidad':'Trinité-et-Tobago','jamaica':'Jamaïque',
  'morocco':'Maroc','algeria':'Algérie','tunisia':'Tunisie','egypt':'Égypte',
  'senegal':'Sénégal','cameroon':'Cameroun','nigeria':'Nigeria','ghana':'Ghana',
  'kenya':'Kenya','malawi':'Malawi','south africa':'Afrique du Sud',
  'zimbabwe':'Zimbabwe','tanzania':'Tanzanie','uganda':'Ouganda',
  'zambia':'Zambie','angola':'Angola','mozambique':'Mozambique',
  'ivory coast':'Côte d\'Ivoire','cote d':'Côte d\'Ivoire',
  'oman':'Oman','kuwait':'Koweït','bahrain':'Bahreïn','lebanon':'Liban',
  'myanmar':'Myanmar','nepal':'Népal','mongolia':'Mongolie',
  'international':'International',
};

function getFlag(c) {
  var f = COUNTRY_FLAGS[c] || 'un';
  return 'https://flagcdn.com/w20/' + f + '.png';
}

// Strip the country prefix from a league name for display
// e.g. "England Premier League" → "Premier League"
//      "Spain La Liga" → "La Liga"
function stripCountryPrefix(leagueName) {
  if (!leagueName) return leagueName;
  var n = leagueName;
  // Try 2-word prefix first (e.g. "South Africa ...")
  var words = n.split(' ');
  var twoWord = (words[0] + ' ' + words[1]).toLowerCase();
  if (BETSAPI_COUNTRY_PREFIX[twoWord]) return words.slice(2).join(' ') || n;
  // Try 1-word prefix
  var oneWord = words[0].toLowerCase();
  if (BETSAPI_COUNTRY_PREFIX[oneWord]) return words.slice(1).join(' ') || n;
  return n;
}

// Accurate country detection using BetsAPI prefix convention
function guessCountry(l) {
  if (!l) return 'International';
  var n = l.toLowerCase().trim();
  var words = n.split(/\s+/);

  // Check 2-word prefix first (e.g. "south africa", "new zealand", "north macedonia")
  if (words.length >= 2) {
    var two = words[0] + ' ' + words[1];
    if (BETSAPI_COUNTRY_PREFIX[two]) return BETSAPI_COUNTRY_PREFIX[two];
  }
  // Check 1-word prefix
  if (words.length >= 1) {
    var one = words[0];
    if (BETSAPI_COUNTRY_PREFIX[one]) return BETSAPI_COUNTRY_PREFIX[one];
  }

  // Well-known leagues without country prefix
  if (n === 'ligue 1' || n === 'ligue 2') return 'France';
  if (n === 'eredivisie' || n === 'tweede divisie' || n === 'derde divisie') return 'Pays-Bas';
  if (n === 'serie a' || n === 'serie b' || n === 'serie c') return 'Italie';
  if (n === 'bundesliga' || n === 'bundesliga ii') return 'Allemagne';
  if (n === 'la liga' || n === 'primera division') return 'Espagne';
  if (n === 'superettan') return 'Suède';

  // Continental / international
  if (n.indexOf('champions league') !== -1 || n.indexOf('europa league') !== -1 ||
      n.indexOf('conference league') !== -1 || n.indexOf('euro') !== -1) return 'Europe';
  if (n.indexOf('copa libertadores') !== -1 || n.indexOf('copa sudamericana') !== -1 ||
      n.indexOf('conmebol') !== -1) return 'Americas';
  if (n.indexOf('caf ') !== -1 || n.indexOf('africa cup') !== -1) return 'Africa';
  if (n.indexOf('world cup') !== -1 || n.indexOf('fifa') !== -1 || n.indexOf('mondial') !== -1) return 'World';
  if (n.indexOf('international') !== -1) return 'International';

  return 'International';
}

/* ── Data Fetching ───────────────────────────────────────── */
function loadCounts() {
  fetch(BASE + 'sportsbook/api.php?action=counts')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d && d.counts) {
        Object.keys(d.counts).forEach(function(sid) {
          var val = d.counts[sid];
          // New format: {total: N, live: N} — old format: just a number
          if (typeof val === 'object' && val !== null) {
            S.sportCounts[parseInt(sid)] = val.total || 0;
            S.sportLiveCounts[parseInt(sid)] = val.live || 0;
          } else {
            S.sportCounts[parseInt(sid)] = val;
            S.sportLiveCounts[parseInt(sid)] = val; // legacy: assume all are live
          }
        });
        renderSidebar();
        renderSportNav();
      }
    }).catch(function() {});
}

/* ── Real-time polling: updates scores, odds, timer, time_status ───────────
   Polls every 20s. Works for both main view (sport list) and championship view.
   Updates ALL live match fields — not just score.
   ─────────────────────────────────────────────────────────────────────────── */
function startPolling() {
  if (S.pollingInterval) clearInterval(S.pollingInterval);

  function doPoll() {
    // Skip re-rendering when viewing match detail — just update data silently
    if (S.viewMode === 'matchDetail') return;

    var url, targetList, isChamp;

    if (S.viewMode === 'championship' && S.activeLeagueName) {
      // Championship view: poll league_matches endpoint for this league
      var searchTerm = LEAGUE_DB_SEARCH[S.activeLeagueName] || S.activeLeagueName;
      url = BASE + 'sportsbook/api.php?action=league_matches&sport_id=' + S.activeSportId
          + '&league=' + encodeURIComponent(searchTerm);
      targetList = S.champMatches;
      isChamp = true;

      // For live matches in championship view, also trigger a direct BetsAPI refresh
      // so scores/odds update even between sync_daemon runs (max 5 live matches)
      var liveIds = S.champMatches
        .filter(function(m) { return m.time_status === '1'; })
        .map(function(m) { return m.id; })
        .slice(0, 5);
      if (liveIds.length > 0) {
        fetch(BASE + 'sportsbook/api.php?action=live_refresh&ids=' + liveIds.join(','))
          .catch(function() {}); // fire-and-forget; DB update picked up by next league_matches poll
      }
    } else {
      // Main view: poll inplay or upcoming based on date
      var apiAction = S.activeDateOffset > 0 ? 'upcoming' : 'inplay';
      url = BASE + 'sportsbook/api.php?action=' + apiAction + '&sport_id=' + S.activeSportId;
      targetList = S.matches;
      isChamp = false;
    }

    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d || !d.results) return;
        // Always filter out finished matches from fresh API response
        var newResults = d.results.filter(function(m) { return m.time_status !== '3'; });
        var updated = false;

        // If list count changed significantly, do a full refresh (new matches went live, etc.)
        if (!isChamp && Math.abs(newResults.length - targetList.length) >= 3) {
          S.matches = newResults;
          S.matches.forEach(function(m) { m._o = null; });
          renderMatches(S.matches);
          markLiveSidebarLeagues(S.matches);
          return;
        }

        newResults.forEach(function(nm) {
          var m = targetList.find(function(xm) { return xm.id == nm.id; });
          if (!m) {
            // New match appeared — add to list and mark for re-render
            if (!isChamp) { S.matches.push(nm); updated = true; }
            return;
          }

          // ── Score update ──────────────────────────────────────────
          if (m.ss !== nm.ss) { m.ss = nm.ss; updated = true; }

          // ── Live odds update (always accept newer timestamp) ──────
          var newOdds = nm.live_odds;
          if (newOdds && newOdds.h) {
            var newTs = parseInt(newOdds.ts || 0);
            var oldTs = parseInt((m.live_odds && m.live_odds.ts) || 0);
            if (newTs >= oldTs) {
              m.live_odds = newOdds;
              m._o = null; // Clear cached computed odds
              updated = true;
            }
          }

          // ── Timer update ─────────────────────────────────────────
          if (nm.timer && JSON.stringify(m.timer) !== JSON.stringify(nm.timer)) {
            m.timer = nm.timer;
            updated = true;
          }

          // ── Status update (match going live) ──────────────────────
          if (m.time_status !== nm.time_status) {
            m.time_status = nm.time_status;
            updated = true;
          }
        });

        // ── Remove finished matches (time_status === '3') immediately ──
        var beforeLen = isChamp ? S.champMatches.length : S.matches.length;
        if (isChamp) {
          S.champMatches = S.champMatches.filter(function(m) { return m.time_status !== '3'; });
          if (S.champMatches.length !== beforeLen) updated = true;
        } else {
          S.matches = S.matches.filter(function(m) { return m.time_status !== '3'; });
          if (S.matches.length !== beforeLen) updated = true;
        }

        if (!updated) return;

        // Re-render the correct view
        if (isChamp) {
          renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.champMatches);
        } else {
          renderMatches(S.matches);
          // Also refresh sidebar badges and counts
          markLiveSidebarLeagues(S.matches);
        }
      })
      .catch(function() {});
  }

  S.pollingInterval = setInterval(doPoll, 15000); // 15s for responsive real-time
}

/* ── Sidebar Rendering ───────────────────────────────────── */
function renderSidebar() {
  var el = document.getElementById('sb-sports-list');
  if (!el) return;
  // Remove initial skeleton placeholder
  var sk = document.getElementById('sb-sports-skeleton');
  if (sk) sk.parentNode.removeChild(sk);
  var out = '';

  SPORTS.forEach(function(sp) {
    var isOpen = (S.openSport === sp.id);
    var cnt = S.sportCounts[sp.id] || '';
    var liveCnt = S.sportLiveCounts[sp.id] || 0;
    var isLive = liveCnt > 0;
    // Format count: 999+ for large numbers
    if (cnt > 999) cnt = '999+';

    // When open, show minus icon; otherwise show chevron
    out += '<div class="sb-sport-row' + (isOpen ? ' open' : '') + '" onclick="window.sbToggleSport(' + sp.id + ')">';
    out += '<span class="sb-sport-icon">' + sp.icon + '</span>';
    out += '<span class="sb-sport-name">' + h(sp.name) + '</span>';
    if (isLive) out += '<span class="sb-en-direct-badge">EN DIRECT</span>';
    if (cnt) out += '<span class="sb-sport-cnt">' + cnt + '</span>';
    if (isOpen) {
      out += '<span class="sb-chevron" style="transform:none">' + ICON.minus + '</span>';
    } else {
      out += '<span class="sb-chevron">' + ICON.chevronDown + '</span>';
    }
    out += '</div>';

    if (isOpen) {
      out += '<div class="sb-countries-sub">';
      var countries = S['countries_' + sp.id] || [];
      countries.forEach(function(c) {
        var isOpenC = S.openCountries[sp.id + '_' + c.name];
        var isLiveCountry = c.live || (c.count > 0);
        // For competition groups (ATP/WTA etc) use globe icon, otherwise country flag
        var COMPETITION_GROUPS = ['ATP','WTA','Challenger','ITF (M)','ITF (F)','UTR (M)','UTR (W)',
          'US Open','French Open','Australian Open','Wimbledon','Davis Cup',
          'World','Europe','TT Cup','BWF Super Series','Premier',
          'CS2/CSGO','Dota 2','League of Legends','Valorant','E-Soccer','E-Basketball','Other'];
        var isCompGroup = COMPETITION_GROUPS.indexOf(c.name) !== -1;
        var flagOrGlobe = isCompGroup
          ? '<span class="sb-country-globe"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#4a9fd4" stroke-width="1.5"/><path d="M12 3c-2 2-3 5-3 9s1 7 3 9M12 3c2 2 3 5 3 9s-1 7-3 9M3 12h18" stroke="#4a9fd4" stroke-width="1.2" stroke-linecap="round"/></svg></span>'
          : '<img src="' + getFlag(c.name) + '" class="sb-country-flag" onerror="this.style.display=\'none\'">';

        out += '<div class="sb-country-row' + (isOpenC ? ' open' : '') + '" onclick="window.sbToggleCountry(' + sp.id + ',\'' + h(c.name) + '\')">';
        out += '<span class="sb-country-cb" onclick="event.stopPropagation()"></span>';
        out += flagOrGlobe;
        out += '<span class="sb-country-name">' + h(c.name) + '</span>';
        if (isLiveCountry) out += '<span class="sb-en-direct-badge" style="font-size:7px;padding:1px 4px">EN DIRECT</span>';
        out += '<span class="sb-country-chevron">' + ICON.chevronDown + '</span>';
        out += '</div>';
        if (isOpenC && c.leagues && c.leagues.length > 0) {
          out += '<div class="sb-league-list">';
          // Premium/featured leagues get [EP] badge (like fcbet216)
          var EP_LEAGUES = [
            'england premier league','spain la liga','italy serie a',
            'germany bundesliga','france ligue 1','netherlands eredivisie',
            'portugal primeira liga','belgium first division a',
            'turkey super lig','russia premier league',
            'uefa champions league','copa sudamericana',
            'nba','euroleague','copa libertadores',
          ];
          c.leagues.forEach(function(lg) {
            var lgName  = (typeof lg === 'string') ? lg : (lg.name || '');
            var lgLive  = (typeof lg === 'object') ? lg.live : false;
            var display = stripCountryPrefix(lgName) || lgName;
            var isEP    = EP_LEAGUES.indexOf(lgName.toLowerCase()) !== -1;
            out += '<div class="sb-league-item' + (lgLive ? ' live' : '') + '" onclick="window.sbSelectLeague(\'' + h(lgName) + '\',' + sp.id + ')">';
            out += '<span class="sb-league-item-dot' + (lgLive ? ' live' : '') + '"></span>';
            out += '<span class="sb-li-name">' + h(display) + '</span>';
            if (isEP)   out += '<span class="sb-ep-badge">EP</span>';
            if (lgLive && !isEP) out += '<span class="sb-en-direct-badge" style="font-size:7px;padding:1px 4px;margin-left:auto">EN DIRECT</span>';
            out += '</div>';
          });
          out += '</div>';
        }
      });
      if (!countries.length) {
        out += '<div class="sb-loader" style="padding:12px 16px;font-size:11px">Chargement...</div>';
      }
      out += '</div>';
    }
  });

  el.innerHTML = out;
}

function renderSportNav() {
  var el = document.getElementById('sb-sport-nav-list');
  if (!el) return;
  var out = '';
  SPORTS.forEach(function(sp) {
    var active = (S.activeSportId === sp.id && !S.activeLeagueId && S.activeDateOffset === 0);
    out += '<button class="sb-sport-item' + (active ? ' active' : '') + '" data-sid="' + sp.id + '" onclick="window.sbSwitchTab(this,\'inplay\',' + sp.id + ')">';
    out += '<div class="sb-sport-icon">' + sp.icon + '</div>';
    out += '<span class="sb-sport-lbl">' + h(sp.name) + '</span>';
    out += '</button>';
  });
  el.innerHTML = out;

  // Mark Streaming/EnDirect buttons inactive since sports are now selected
  document.querySelectorAll('.sb-nav-streaming,.sb-nav-endirect,.sb-nav-all').forEach(function(b) {
    b.classList.remove('active');
  });
}

/* ── Match Rendering ─────────────────────────────────────── */
function renderMatches(results) {
  var el = document.getElementById('sb-matches-body');
  if (!el) return;

  if (!results || !results.length) {
    el.innerHTML = '<div class="sb-loader">Aucun match disponible.</div>';
    return;
  }

  if (S.activeAction === 'inplay' && !S.activeLeagueId) {
    renderEnDirectCards(results.slice(0, 6));   // horizontal EN DIRECT cards
    renderBoosted(results.slice(6, 10));        // COTES BOOSTÉES promotional cards
  }

  // Group by league
  var groups = {}, order = [];
  results.forEach(function(m) {
    var k = (m.league && m.league.name) ? m.league.name : 'Autre championnat';
    if (!groups[k]) { groups[k] = []; order.push(k); }
    groups[k].push(m);
  });

  var out = '';

  // Section title — "En direct maintenant" for live, "Prochainement" for upcoming
  var sectionLabel = (S.activeAction === 'inplay') ? 'En direct maintenant' : 'Prochainement';
  out += '<div class="sb-section-title">';
  out += '<span>' + sectionLabel + '</span>';
  out += '<div class="sb-section-icon">' + ICON.football + '</div>';
  out += '</div>';

  // Sport tabs for Prochainement
  out += '<div class="sb-upcoming-tabs">';
  SPORTS.slice(0, 8).forEach(function(sp, i) {
    var isActive = (sp.id === S.activeUpcomingTab);
    out += '<button class="sb-upcoming-tab' + (isActive ? ' active' : '') + '" onclick="window.sbSetUpcomingTab(' + sp.id + ',this)">';
    out += '<div class="sb-tab-icon">' + sp.icon + '</div>';
    out += h(sp.name);
    out += '</button>';
  });
  out += '</div>';

  // Market selectors
  out += '<div class="sb-market-row">';
  out += '<select class="sb-market-sel"><option>1x2</option><option>Handicap</option><option>Double Chance</option></select>';
  out += '<select class="sb-market-sel"><option>Total</option><option>Corners</option><option>Cartons</option></select>';
  out += '</div>';

  // Match groups
  order.forEach(function(lg) {
    var country = guessCountry(lg);
    var flag = getFlag(country);
    var countryLabel = (country && country !== 'International') ? (' · ' + h(country)) : '';
    // Section header: ☆ [flag] League · Country   [BB] —  (matching reference exactly)
    out += '<div class="sb-league-section-hdr">';
    out += '<span class="sb-lh-star" onclick="event.stopPropagation()">' + ICON.star + '</span>';
    out += '<img src="' + flag + '" class="sb-league-f" onerror="this.src=\'https://flagcdn.com/w20/un.png\'">';
    out += '<span class="sb-league-n">' + h(lg) + countryLabel + '</span>';
    out += '<span class="sb-lh-bb">BB</span>';
    out += '<div class="sb-lh-icons">' + ICON.minus + '</div>';
    out += '</div>';
    groups[lg].forEach(function(m) { out += matchCard(m); });
  });

  el.innerHTML = out;
}

function matchCard(m) {
  var o = odds(m);
  var isLive = m.time_status === '1';
  var hn = h(m.home ? m.home.name : '');
  var an = h(m.away ? m.away.name : '');
  var mid = h(m.id);

  // Format date/time for upcoming matches — safe parsing
  var ts = parseInt(m.time || m.kickoff || m.start_time || 0) || 0;
  var dateD = ts > 0 ? new Date(ts * 1000) : new Date();
  var dateStr = String(dateD.getDate()).padStart(2,'0') + '/' + String(dateD.getMonth()+1).padStart(2,'0');
  var timeStr = String(dateD.getHours()).padStart(2,'0') + ':' + String(dateD.getMinutes()).padStart(2,'0');

  // Live timer — "Mi-temps" for half-time, otherwise "45'"
  var liveMin = '';
  if (isLive && m.timer) {
    var tm = m.timer.tm || m.timer.TM || '';
    var ts = m.timer.ts || m.timer.TS || '';
    // md=1 means half-time pause
    if (m.timer.md === '1' || m.timer.MD === '1') liveMin = 'Mi-temps';
    else liveMin = tm ? (tm + '\'') : '';
  }

  // Team logos
  var FALLBACK = BASE + 'assets/images/logo.png';
  var hl = m.home ? m.home.image_id : null;
  var al = m.away ? m.away.image_id : null;
  var hlogo = hl ? 'https://assets.b365api.com/images/team/m/' + hl + '.png' : FALLBACK;
  var alogo = al ? 'https://assets.b365api.com/images/team/m/' + al + '.png' : FALLBACK;

  // League / country info — strip country prefix for display (matches reference)
  var rawLeagueName  = m.league ? (m.league.name || '') : '';
  var leagueName     = h(stripCountryPrefix(rawLeagueName)); // "England Premier League" → "Premier League"
  var country        = guessCountry(rawLeagueName);
  var flagUrl        = getFlag(country);
  var countryLabel   = (country && country !== 'International') ? h(country) : '';

  // Scores
  var scores = m.ss ? m.ss.split('-') : ['', ''];
  var hasScore = isLive && (scores[0] !== '' || scores[1] !== '');

  // Over/Under — null when not available (shows '-' in button)
  var ouLine  = o.ou || 2.5;
  var ouOver  = (o.ov && parseFloat(o.ov) >= 1.01) ? o.ov : null;
  var ouUnder = (o.un && parseFloat(o.un) >= 1.01) ? o.un : null;

  var mname = (m.home ? m.home.name : '') + ' v ' + (m.away ? m.away.name : '');
  var out = '<div class="mc" onclick="window.sbOpenMatch(\'' + mid + '\')">';

  /* ── Row 1: BB | EN DIRECT | time/min | spacer | ☆ star (matches fcbet216 exactly) ── */
  out += '<div class="mc-info-row">';
  out += '<span class="mc-badge-bb">BB</span>';
  if (isLive) {
    out += '<span class="mc-live-badge">EN DIRECT</span>';
    if (liveMin) out += '<span class="mc-live-min">' + h(liveMin) + '</span>';
  } else {
    out += '<span class="mc-date">' + h(timeStr) + ' ' + h(dateStr) + '</span>';
  }
  out += '<span class="mc-row-spacer"></span>';
  out += '<button class="mc-star" onclick="event.stopPropagation()" aria-label="Favori">' + ICON.star + '</button>';
  out += '</div>';

  /* ── Row 2: flag | league · country | stats/EN DIRECT badge ── */
  out += '<div class="mc-league-row">';
  out += '<img src="' + flagUrl + '" class="mc-league-flag" onerror="this.style.display=\'none\'">';
  out += '<span class="mc-league-name">' + leagueName + (countryLabel ? ' · ' + countryLabel : '') + '</span>';
  if (!isLive) {
    out += '<span class="mc-live-badge outlined" style="margin-left:auto;flex-shrink:0">EN DIRECT</span>';
  } else {
    out += '<button class="mc-stats-icon" onclick="event.stopPropagation()" style="margin-left:auto" aria-label="Statistiques">' + ICON.stats + '</button>';
  }
  out += '</div>';

  /* ── Body row: teams/scores | odds | chevron ── */
  out += '<div class="mc-body-row" onclick="event.stopPropagation()">';

  // Teams column — with jersey/badge images matching fcbet216
  out += '<div class="mc-teams">';
  out += '<div class="mc-team">';
  out += '<img src="' + hlogo + '" class="mc-jersey" onerror="this.style.opacity=\'0\'">';
  out += '<span class="mc-t-name">' + hn + '</span>';
  if (hasScore) out += '<span class="mc-t-score">' + h(scores[0]) + '</span>';
  out += '</div>';
  out += '<div class="mc-team">';
  out += '<img src="' + alogo + '" class="mc-jersey" onerror="this.style.opacity=\'0\'">';
  out += '<span class="mc-t-name">' + an + '</span>';
  if (hasScore) out += '<span class="mc-t-score">' + h(scores[1]) + '</span>';
  out += '</div>';
  out += '</div>';

  // Odds buttons — market-specific rendering based on activeMarketCat
  out += '<div class="mc-odds-all">';
  var cat = (S.viewMode === 'championship') ? (S.activeMarketCat || 'populaire') : 'populaire';
  var hVal = parseFloat(o.h) || 0;
  var aVal = parseFloat(o.a) || 0;
  var xVal = parseFloat(o.x) || 0;

  if (cat === 'populaire') {
    // Default: 1x2 + O/U
    out += oddBtn(mid, mname, '1', o.h);
    out += oddBtn(mid, mname, 'X', o.x);
    out += oddBtn(mid, mname, '2', o.a);
    out += ouBtn(mid, mname, 'Plus de ' + ouLine, ouOver, 'ou_over');
    out += ouBtn(mid, mname, 'Moins de ' + ouLine, ouUnder, 'ou_under');
  } else if (cat === '1x2') {
    out += oddBtn(mid, mname, '1', o.h);
    out += oddBtn(mid, mname, 'X', o.x);
    out += oddBtn(mid, mname, '2', o.a);
  } else if (cat === 'total') {
    out += ouBtn(mid, mname, 'Plus de ' + ouLine, ouOver, 'ou_over');
    out += ouBtn(mid, mname, 'Moins de ' + ouLine, ouUnder, 'ou_under');
  } else if (cat === 'double_chance') {
    var dc1x = (hVal >= 1.01 && xVal >= 1.01) ? Math.max(1.01, +(1 / (1/hVal + 1/xVal)).toFixed(2)) : null;
    var dc12 = (hVal >= 1.01 && aVal >= 1.01) ? Math.max(1.01, +(1 / (1/hVal + 1/aVal)).toFixed(2)) : null;
    var dcx2 = (xVal >= 1.01 && aVal >= 1.01) ? Math.max(1.01, +(1 / (1/xVal + 1/aVal)).toFixed(2)) : null;
    out += oddBtn(mid, mname, '1X', dc1x);
    out += oddBtn(mid, mname, '12', dc12);
    out += oddBtn(mid, mname, 'X2', dcx2);
  } else if (cat === 'btts') {
    // Both Teams To Score — derived from O/U
    var bttsYes = ouOver ? Math.max(1.01, +(parseFloat(ouOver) * 0.82).toFixed(2)) : null;
    var bttsNo = ouUnder ? Math.max(1.01, +(parseFloat(ouUnder) * 1.10).toFixed(2)) : null;
    out += oddBtn(mid, mname, 'Oui', bttsYes);
    out += oddBtn(mid, mname, 'Non', bttsNo);
  } else if (cat === 'odd_even') {
    out += oddBtn(mid, mname, 'Impair', Math.max(1.01, +(1.94).toFixed(2)));
    out += oddBtn(mid, mname, 'Pair', Math.max(1.01, +(1.85).toFixed(2)));
  } else if (cat === 'handicap') {
    var hc1 = hVal >= 1.01 ? Math.max(1.01, +(hVal * 0.92).toFixed(2)) : null;
    var hc2 = aVal >= 1.01 ? Math.max(1.01, +(aVal * 0.88).toFixed(2)) : null;
    out += ouBtn(mid, mname, '1 (-0.5)', hc1, 'hc_1');
    out += ouBtn(mid, mname, '2 (+0.5)', hc2, 'hc_2');
  } else if (cat === 'goal_range') {
    var gr01 = hVal >= 1.01 ? Math.max(1.01, +(hVal * 2.5).toFixed(2)) : null;
    var gr23 = xVal >= 1.01 ? Math.max(1.01, +(xVal * 0.55).toFixed(2)) : null;
    var gr46 = aVal >= 1.01 ? Math.max(1.01, +(aVal * 0.65).toFixed(2)) : null;
    var gr7  = hVal >= 1.01 ? Math.max(1.01, +(hVal * 8).toFixed(2)) : null;
    out += oddBtn(mid, mname, '0-1', gr01);
    out += oddBtn(mid, mname, '2-3', gr23);
    out += oddBtn(mid, mname, '4-6', gr46);
    out += oddBtn(mid, mname, '7+', gr7);
  } else if (cat === 'dnb') {
    // Draw No Bet — remove draw probability
    var dnb1 = hVal >= 1.01 ? Math.max(1.01, +(1 / (1/hVal / (1/hVal + 1/aVal))).toFixed(2)) : null;
    var dnb2 = aVal >= 1.01 ? Math.max(1.01, +(1 / (1/aVal / (1/hVal + 1/aVal))).toFixed(2)) : null;
    out += oddBtn(mid, mname, '1', dnb1);
    out += oddBtn(mid, mname, '2', dnb2);
  } else if (cat === 'handicap_3way') {
    var h3_1 = hVal >= 1.01 ? Math.max(1.01, +(hVal * 1.5).toFixed(2)) : null;
    var h3_x = xVal >= 1.01 ? Math.max(1.01, +(xVal * 1.0).toFixed(2)) : null;
    var h3_2 = aVal >= 1.01 ? Math.max(1.01, +(aVal * 0.55).toFixed(2)) : null;
    out += ouBtn(mid, mname, '1 (0:1)', h3_1, 'h3w_1');
    out += ouBtn(mid, mname, 'Match nul (0:1)', h3_x, 'h3w_x');
    out += ouBtn(mid, mname, '2 (0:1)', h3_2, 'h3w_2');
  } else if (cat === 'next_goal') {
    var ng1 = hVal >= 1.01 ? Math.max(1.01, +(hVal * 0.85).toFixed(2)) : null;
    var ng0 = hVal >= 1.01 ? Math.max(1.01, +(15 + hVal * 2).toFixed(2)) : null;
    var ng2 = aVal >= 1.01 ? Math.max(1.01, +(aVal * 0.95).toFixed(2)) : null;
    out += oddBtn(mid, mname, '1', ng1);
    out += oddBtn(mid, mname, 'Aucun', ng0);
    out += oddBtn(mid, mname, '2', ng2);
  } else {
    // Fallback: populaire
    out += oddBtn(mid, mname, '1', o.h);
    out += oddBtn(mid, mname, 'X', o.x);
    out += oddBtn(mid, mname, '2', o.a);
  }
  out += '</div>';

  out += '<button class="mc-more-btn" onclick="event.stopPropagation();window.sbOpenMatch(\'' + mid + '\')" aria-label="Voir marchés">' + ICON.chevronDown + '</button>';
  out += '</div>'; // body row
  out += '</div>'; // mc
  return out;
}

/* Lock icon SVG — shown when odds are suspended/unavailable */
var LOCK_ICON = '<svg class="mc-lock-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/></svg>';

/* Odds cell pair: [label-cell] [value-cell] — two separate bordered cells
   Matching fcbet216 Altenar OddBox: label and value are in separate bordered boxes */
function oddBtn(mid, match, sel, val) {
  var v = parseFloat(val);
  var hasOdds = !isNaN(v) && v >= 1.01;
  var bid = mid + '_' + sel;
  var isSel = S.betSlip.some(function(b) { return b.id === bid; });
  var valCls = 'mc-o-val-cell' + (isSel ? ' sel' : '') + (hasOdds ? '' : ' no-odds');
  var onclick = hasOdds
    ? ' onclick="event.stopPropagation();window.sbAddBet(\'' + h(bid) + '\',\'' + h(match) + '\',\'' + h(sel) + '\',' + v + ')"'
    : '';
  return '<span class="mc-o-lbl-cell">' + sel + '</span>'
    + '<button class="' + valCls + '"' + onclick + '>'
    + (hasOdds ? h(formatOdd(v)) : LOCK_ICON)
    + '</button>';
}

/* Over/Under cell pair: [label-cell] [value-cell]
   Shows lock icon when val is null or < 1.01 */
function ouBtn(mid, match, label, val, key) {
  var v = parseFloat(val);
  var hasOdds = val !== null && !isNaN(v) && v >= 1.01;
  var bid = mid + '_' + key;
  var isSel = S.betSlip.some(function(b) { return b.id === bid; });
  var valCls = 'mc-o-val-cell' + (isSel ? ' sel' : '') + (hasOdds ? '' : ' no-odds');
  var onclick = hasOdds
    ? ' onclick="event.stopPropagation();window.sbAddBet(\'' + h(bid) + '\',\'' + h(match) + '\',\'' + h(label) + '\',' + v + ')"'
    : '';
  return '<span class="mc-o-lbl-cell ou">' + h(label) + '</span>'
    + '<button class="' + valCls + '"' + onclick + '>'
    + (hasOdds ? h(formatOdd(v)) : LOCK_ICON)
    + '</button>';
}

/* Odds format switcher */
window.sbSetOddsFormat = function(fmt, el) {
  ODDS_FMT = fmt;
  document.querySelectorAll('.sb-cotes-opt').forEach(function(b) {
    b.classList.remove('active');
    var check = b.querySelector('.sb-cotes-check');
    if (check) check.textContent = '—';
  });
  if (el) {
    el.classList.add('active');
    var c = el.querySelector('.sb-cotes-check');
    if (!c) {
      c = document.createElement('span');
      c.className = 'sb-cotes-check';
      el.appendChild(c);
    }
    c.textContent = '✓';
  }
  // Re-render all visible odds with new format
  renderMatches(S.matches);
};

/* ── EN DIRECT Live Cards (horizontal carousel above Cotes boostées) ──────── */
function renderEnDirectCards(matches) {
  var el = document.getElementById('sb-en-direct-cards');
  if (!el) return;
  el.querySelectorAll('.sb-sk-boost-card').forEach(function(n){ n.parentNode.removeChild(n); });

  var out = '';
  var shown = matches.slice(0, 6); // max 6 live cards

  shown.forEach(function(m) {
    var o   = odds(m);
    var mid = h(m.id);
    var hn  = h(m.home ? m.home.name : '');
    var an  = h(m.away ? m.away.name : '');
    var ts2 = parseInt(m.time || 0) || 0;
    var dObj = ts2 > 0 ? new Date(ts2 * 1000) : new Date();
    var timeStr = String(dObj.getHours()).padStart(2,'0') + ':' + String(dObj.getMinutes()).padStart(2,'0');
    var dateStr = String(dObj.getDate()).padStart(2,'0') + '/' + String(dObj.getMonth()+1).padStart(2,'0');

    var lgName  = h(m.league ? m.league.name : '');
    var country = guessCountry(m.league ? m.league.name : '');
    var flagUrl = getFlag(country);
    var isLive  = m.time_status === '1';

    var FALLBACK = BASE + 'assets/images/logo.png';
    var hl  = m.home ? m.home.image_id : null;
    var al  = m.away ? m.away.image_id : null;
    var hlogo = hl ? 'https://assets.b365api.com/images/team/m/' + hl + '.png' : FALLBACK;
    var alogo = al ? 'https://assets.b365api.com/images/team/m/' + al + '.png' : FALLBACK;

    var scores = m.ss ? m.ss.split('-') : ['',''];
    var timerMin = '';
    if (isLive && m.timer) {
      var tm = m.timer.tm || ''; var tmd = m.timer.md || '';
      timerMin = tmd === '1' ? 'Mi-temps' : (tm ? tm + '\'' : '');
    }

    out += '<div class="slc" onclick="window.sbOpenMatch(\'' + mid + '\')">';

    // Header row: for live → BB · EN DIRECT · minute; for upcoming → BB · time date
    out += '<div class="slc-hdr">';
    out += '<span class="slc-bb">BB</span>';
    if (isLive) {
      out += '<span class="slc-live-badge">EN DIRECT</span>';
      if (timerMin) out += '<span class="slc-time">' + h(timerMin) + '</span>';
    } else {
      out += '<span class="slc-time">' + timeStr + ' ' + dateStr + '</span>';
    }
    out += '<span class="slc-sep">·</span>';
    out += '<img src="' + flagUrl + '" class="slc-flag" onerror="this.style.display=\'none\'">';
    out += '<span class="slc-lg">' + lgName + '</span>';
    out += '</div>';

    // Middle: jerseys + names | EN DIRECT + stats
    out += '<div class="slc-mid">';
    out += '<div class="slc-teams">';
    out += '<div class="slc-team-row"><img src="' + hlogo + '" class="slc-jersey" onerror="this.src=\'' + FALLBACK + '\'"><span class="slc-tname">' + hn + '</span>';
    if (isLive && scores[0] !== '') out += '<span class="slc-score">' + h(scores[0]) + '</span>';
    out += '</div>';
    out += '<div class="slc-team-row"><img src="' + alogo + '" class="slc-jersey" onerror="this.src=\'' + FALLBACK + '\'"><span class="slc-tname">' + an + '</span>';
    if (isLive && scores[1] !== '') out += '<span class="slc-score">' + h(scores[1]) + '</span>';
    out += '</div>';
    out += '</div>';
    out += '<div class="slc-right">';
    out += '<span class="slc-stats">' + ICON.stats + '</span>';
    out += '</div>';
    out += '</div>';

    // Odds row: 1 / X / 2
    out += '<div class="slc-odds">';
    ['1','X','2'].forEach(function(lbl, i) {
      var val = [o.h, o.x, o.a][i];
      if (!val) return;
      var bid = mid + '_slc_' + lbl;
      var isSel = S.betSlip.some(function(b){ return b.id === bid; });
      out += '<button class="slc-odd-btn' + (isSel ? ' sel' : '') + '" onclick="event.stopPropagation();window.sbAddBet(\'' + h(bid) + '\',\'' + hn + ' v ' + an + '\',\'' + lbl + '\',' + parseFloat(val) + ')">'
        + '<span class="slc-odd-lbl">' + lbl + '</span>'
        + '<span class="slc-odd-val">' + parseFloat(val).toFixed(2) + '</span>'
        + '</button>';
    });
    out += '</div>';

    out += '</div>'; // slc
  });

  el.innerHTML = out;
}

/* ── Cotes Boostées (old promotional style with boost arrow) ─────────────── */
function renderBoosted(matches) {
  var el = document.getElementById('sb-boosted-odds');
  if (!el) return;
  el.querySelectorAll('.sb-sk-boost-card').forEach(function(n){ n.parentNode.removeChild(n); });

  var boostLines = [
    ['2 - 1ère mi-temps - 1x2', 'Plus de 0.5 - 1ère mi-temps - total', 'Plus de 10.5 - Total des corners'],
    ['1x2 & GG/NG', '2 & non'],
    ['Plus de 2.5 - Total', "N'importe quand - Buteur", 'Plus de 1.5 - Tirs'],
    ['1ère mi-temps - 1x2', 'Plus de 1.5 - total', 'Carton jaune - 1ère mi-temps'],
  ];
  var out = '';
  matches.forEach(function(m, idx) {
    var o = odds(m);
    var oldOdd = Math.max(1.01, +(parseFloat(o.h) * 1.09 + 0.21)).toFixed(2);
    var newOdd = (parseFloat(o.h) + 0.1).toFixed(2);
    var ts2 = parseInt(m.time || m.kickoff || 0) || 0;
    var dObj = ts2 > 0 ? new Date(ts2 * 1000) : new Date();
    var dateStr = String(dObj.getDate()).padStart(2,'0') + '/' + String(dObj.getMonth()+1).padStart(2,'0');
    var timeStr = String(dObj.getHours()).padStart(2,'0') + ':' + String(dObj.getMinutes()).padStart(2,'0');
    var lines = boostLines[idx % boostLines.length];

    out += '<div class="sb-boost-card" onclick="window.sbOpenMatch(\'' + h(m.id) + '\')">';
    out += '<div class="sb-boost-card-top">';
    out += '<span class="sb-boost-sport-icon">' + ICON.football + '</span>';
    out += '<span class="sb-badge-bb">BB</span>';
    out += '<span class="sb-badge-blue">COTES BOOSTÉES</span>';
    out += '</div>';
    out += '<div class="sb-meta-text">' + h(m.league ? m.league.name : '') + '</div>';
    out += '<div class="sb-meta-text">' + dateStr + ' · ' + timeStr + '</div>';
    out += '<div class="sb-teams-text"><strong>' + h(m.home ? m.home.name : '') + ' vs. ' + h(m.away ? m.away.name : '') + '</strong></div>';
    lines.forEach(function(line) {
      out += '<div class="sb-boost-line"><span class="sb-boost-dot"></span>' + h(line) + '</div>';
    });
    out += '<div class="sb-odds-row">';
    out += '<span class="sb-old-val">' + oldOdd + '</span>';
    out += '<svg width="18" height="10" viewBox="0 0 18 10" fill="none"><path d="M1 5H17M13 1L17 5L13 9" stroke="var(--sb-green)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    out += '<span class="sb-new-val">' + newOdd + '</span>';
    out += '</div></div>';
  });
  el.innerHTML = out || '<div style="padding:10px;color:var(--sb-text-2);font-size:12px">Aucune cote boostée disponible</div>';
}

/* ── Bet Slip — fcbet216-style with Simple/Combiné/Système ────────────── */
var SLIP_MODE = 'simple';
var SLIP_STAKE = 5; // default stake per bet
var SLIP_COMBI_STAKE = 10;
var SLIP_SYS_SINGLES_STAKE = 0;
var SLIP_SYS_COMBO_STAKE = 0;

window.sbAddBet = function(id, match, sel, val) {
  id = String(id).replace(/'/g, '');
  var idx = S.betSlip.findIndex(function(b) { return b.id === id; });
  if (idx !== -1) S.betSlip.splice(idx, 1);
  else S.betSlip.push({ id: id, match: match, sel: sel, val: parseFloat(val), isLive: false, market: '1x2', stake: SLIP_STAKE });
  renderBetSlip();
  updateFloatingBetBadge();
  // Update button highlights
  document.querySelectorAll('.md-odd-btn, .mc-odd-btn, .mc-o-val-cell, .slc-odd-btn').forEach(function(btn) {
    var oc = btn.getAttribute('onclick') || '';
    btn.classList.toggle('sel', S.betSlip.some(function(b) { return oc.indexOf(b.id) !== -1; }));
  });
  // On mobile, auto-open right panel if bet added
  if (S.betSlip.length > 0 && window.innerWidth <= 1100) {
    var right = document.getElementById('sb-right');
    if (right && !right.classList.contains('open')) {
      right.classList.add('open');
    }
  }
};

/* Floating bet badge — shows count when bets exist */
function updateFloatingBetBadge() {
  var badge = document.getElementById('sb-floating-bet-badge');
  if (!badge) {
    badge = document.createElement('div');
    badge.id = 'sb-floating-bet-badge';
    badge.className = 'sb-floating-bet-badge';
    badge.onclick = function() { window.sbToggleRight(); };
    document.querySelector('.sb-root').appendChild(badge);
  }
  var n = S.betSlip.length;
  if (n > 0) {
    badge.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="4" y="2" width="16" height="20" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 10h8M8 14h8M8 18h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>'
      + '<span>Fiche de pari</span>'
      + '<span class="sb-fb-count">' + n + '</span>';
    badge.style.display = 'flex';
  } else {
    badge.style.display = 'none';
  }
}

window.sbSlipMode = function(mode) { SLIP_MODE = mode; renderBetSlip(); };
window.sbClearSlip = function() { S.betSlip = []; renderBetSlip(); updateFloatingBetBadge(); };

window.sbUpdateBetStake = function(idx, val) {
  if (S.betSlip[idx]) S.betSlip[idx].stake = Math.max(0, parseFloat(val) || 0);
  renderBetSlip();
};
window.sbUpdateCombiStake = function(val) { SLIP_COMBI_STAKE = Math.max(0, parseFloat(val) || 0); renderBetSlip(); };
window.sbUpdateSysStake = function(type, val) {
  if (type === 'singles') SLIP_SYS_SINGLES_STAKE = Math.max(0, parseFloat(val) || 0);
  else SLIP_SYS_COMBO_STAKE = Math.max(0, parseFloat(val) || 0);
  renderBetSlip();
};

function renderBetSlip() {
  var el = document.getElementById('sb-slip-body');
  var cntEl = document.getElementById('sb-slip-count');
  if (!el) return;

  if (cntEl) {
    cntEl.innerText = S.betSlip.length || '';
    cntEl.style.display = S.betSlip.length ? 'inline-block' : 'none';
  }

  var out = '';
  var n = S.betSlip.length;

  // ── Mode tabs ──────────────────────────────────────────────
  out += '<div class="slip-tabs">';
  ['simple','combi','system'].forEach(function(m) {
    var lbl = m === 'simple' ? 'Simple' : m === 'combi' ? 'Combiné' : 'Système';
    out += '<button class="slip-tab' + (SLIP_MODE === m ? ' active' : '') + '" onclick="window.sbSlipMode(\'' + m + '\')">' + lbl + '</button>';
  });
  out += '</div>';

  // ── Empty state ────────────────────────────────────────────
  if (!n) {
    out += '<div class="sb-slip-empty">'
      + '<svg viewBox="0 0 40 40" fill="none"><rect x="8" y="4" width="24" height="32" rx="3" stroke="currentColor" stroke-width="1.5"/><path d="M14 14h12M14 20h12M14 26h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
      + '<p>Pas de sélections sur la fiche de pari</p></div>';
    el.innerHTML = out;
    return;
  }

  // ── Selections ─────────────────────────────────────────────
  var totalOdds = 1;
  S.betSlip.forEach(function(b, i) {
    totalOdds *= b.val;

    out += '<div class="slip-item">';
    out += '<div class="slip-item-hdr">';
    out += '<span class="slip-sport-icon">' + ICON.football + '</span>';
    out += '<span class="slip-match-nm">' + h(b.match) + '</span>';
    // System mode: banker toggle
    if (SLIP_MODE === 'system') {
      out += '<span class="slip-banker-toggle" title="Banker">🅱</span>';
    }
    out += '<button class="slip-remove-btn" onclick="window.sbAddBet(\'' + h(b.id) + '\',\'\',\'\',0)">×</button>';
    out += '</div>';

    // Market name
    out += '<div class="slip-market-nm">Vainqueur (prolongations incluses)</div>';

    // Selection row: label + odds (highlighted box)
    out += '<div class="slip-sel-row">';
    if (b.isLive) out += '<span class="slip-live-badge">EN DIRECT</span>';
    out += '<span class="slip-sel-lbl">' + h(b.sel) + '</span>';
    out += '<span class="slip-sel-odd">' + b.val.toFixed(2) + '</span>';
    out += '</div>';

    // Stake + gain (Simple mode only — per-bet stake)
    if (SLIP_MODE === 'simple') {
      var gain = +(b.stake * b.val).toFixed(2);
      out += '<div class="slip-stake-row">';
      out += '<input type="number" class="slip-stake-inp" value="' + (b.stake || SLIP_STAKE) + '" min="0" step="1" onchange="window.sbUpdateBetStake(' + i + ',this.value)">';
      out += '<span class="slip-gain-lbl">Gagner:</span>';
      out += '<span class="slip-gain-val">' + gain.toFixed(2) + '</span>';
      out += '</div>';
    }
    out += '</div>';
  });

  // ── Promo hint ─────────────────────────────────────────────
  if (SLIP_MODE === 'combi' && n >= 1) {
    var bonusPct = n >= 4 ? 10 : n >= 3 ? 7 : 5;
    out += '<div class="slip-promo">'
      + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z" fill="var(--sb-green)"/></svg>'
      + ' Ajoutez 1 événement avec une cote de 1.20 ou plus pour augmenter vos gains de <strong>' + bonusPct + ' %</strong>'
      + '</div>';
  }

  // ── Tout effacer ───────────────────────────────────────────
  out += '<button class="slip-clear-btn" onclick="window.sbClearSlip()">Tout effacer <i class="fa fa-trash"></i></button>';

  // ── Odds changed warning ───────────────────────────────────
  out += '<div class="slip-odds-warning"><i class="fa fa-exclamation-triangle"></i> Certaines cotes ont changées, veuillez accepter.</div>';

  // ═══ MODE-SPECIFIC SUMMARY ═════════════════════════════════
  if (SLIP_MODE === 'simple') {
    // ── Simple: sum of individual bets ───────────────────────
    var totalMise = 0, totalGain = 0;
    S.betSlip.forEach(function(b) {
      var st = b.stake || SLIP_STAKE;
      totalMise += st;
      totalGain += st * b.val;
    });
    out += '<div class="slip-summary">';
    out += '<div class="slip-sum-row"><span>Mise totale</span><span>' + totalMise.toFixed(2) + '</span></div>';
    out += '<div class="slip-sum-row"><span>Gain total</span><span class="green">' + totalGain.toFixed(2) + '</span></div>';
    out += '</div>';

  } else if (SLIP_MODE === 'combi') {
    // ── Combiné: product of odds × stake + bonus ─────────────
    var bonusPct2 = n >= 4 ? 10 : n >= 3 ? 7 : 5;
    var baseGain = SLIP_COMBI_STAKE * totalOdds;
    var bonus = baseGain * bonusPct2 / 100;
    var combiGain = baseGain + bonus;

    out += '<div class="slip-combo-row">';
    out += '<span class="slip-combo-lbl">Combo</span>';
    out += '<span class="slip-combo-bonus">🎁 ' + bonusPct2 + '%</span>';
    out += '<span class="slip-combo-mult">' + n + ' x</span>';
    out += '<input type="number" class="slip-stake-inp" value="' + SLIP_COMBI_STAKE + '" min="0" step="1" onchange="window.sbUpdateCombiStake(this.value)">';
    out += '</div>';

    out += '<div class="slip-summary">';
    out += '<div class="slip-sum-row"><span>Cotes totales</span><span>' + totalOdds.toFixed(2) + '</span></div>';
    out += '<div class="slip-sum-row"><span>Mise totale</span><span>' + SLIP_COMBI_STAKE.toFixed(2) + '</span></div>';
    out += '<div class="slip-sum-row"><span>Gains supplémentaires</span><span class="green">🎁 ' + bonus.toFixed(2) + '</span></div>';
    out += '<div class="slip-sum-row slip-sum-total"><span>Gain total</span><span class="green">' + combiGain.toFixed(2) + '</span></div>';
    out += '</div>';

  } else if (SLIP_MODE === 'system') {
    // ── Système: singles + combo ─────────────────────────────
    out += '<div class="slip-sys-row">';
    out += '<span class="slip-sys-lbl">Seuls</span>';
    out += '<span class="slip-sys-cnt">' + n + ' x</span>';
    out += '<input type="number" class="slip-stake-inp" value="' + SLIP_SYS_SINGLES_STAKE + '" min="0" step="1" placeholder="Fixer la mise" onchange="window.sbUpdateSysStake(\'singles\',this.value)">';
    out += '</div>';
    out += '<div class="slip-sys-row">';
    out += '<span class="slip-sys-lbl">Combo</span>';
    out += '<span class="slip-sys-cnt">1 x</span>';
    out += '<input type="number" class="slip-stake-inp" value="' + SLIP_SYS_COMBO_STAKE + '" min="0" step="1" placeholder="Fixer la mise" onchange="window.sbUpdateSysStake(\'combo\',this.value)">';
    out += '</div>';

    var sysMise = (SLIP_SYS_SINGLES_STAKE * n) + SLIP_SYS_COMBO_STAKE;
    var sysNbParis = (SLIP_SYS_SINGLES_STAKE > 0 ? n : 0) + (SLIP_SYS_COMBO_STAKE > 0 ? 1 : 0);

    out += '<div class="slip-summary">';
    out += '<div class="slip-sum-row"><span>Nombre de paris</span><span>' + sysNbParis + '</span></div>';
    out += '<div class="slip-sum-row"><span>Mise totale</span><span>' + sysMise.toFixed(2) + '</span></div>';
    out += '</div>';
  }

  // ── Place bet button ───────────────────────────────────────
  out += '<div class="slip-place-wrap">';
  out += '<span class="slip-bookmark" title="Sauvegarder">🔖</span>';
  out += '<button class="slip-place-btn" onclick="window.sbPlaceBet()">Connectez-vous pour placer des paris</button>';
  out += '</div>';

  el.innerHTML = out;
}

window.sbPlaceBet = function() {
  if (!S.betSlip.length) return;
  var confirmed = confirm('Confirmer votre pari ?\n\nMise: ' + SLIP_STAKE + '\nCotes: ' + S.betSlip.map(function(b){ return b.sel + ' @ ' + b.val.toFixed(2); }).join(', '));
  if (confirmed) {
    // Calculate total odds
    var totalOdds = SLIP_MODE === 'combi' ? 1 : S.betSlip[0].val;
    if (SLIP_MODE === 'combi') {
      S.betSlip.forEach(function(b) { totalOdds *= b.val; });
    }

    var payload = {
      amount: SLIP_STAKE,
      total_odds: totalOdds,
      slip: S.betSlip
    };

    fetch('place_bet.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        S.betSlip = [];
        renderBetSlip();
        // optionally update balance if needed
      } else {
        alert('Erreur: ' + data.message);
      }
    })
    .catch(err => alert('Erreur de connexion.'));
  }
};

/* ── Popular Bets ─────────────────────────────────────────── */
function renderPopularBets() {
  var el = document.getElementById('sb-popular-bets');
  if (!el) return;
  var items = [
    { match: 'Real Madrid vs. Athletic Club', market: '1x2', sel: '1', val: 1.46 },
    { match: 'Majorque vs. Real Oviedo',     market: '1x2', sel: '1', val: 1.48 },
    { match: 'SS Lazio vs. Pise',            market: '1x2', sel: '1', val: 1.72 },
  ];
  var out = '<div class="sb-widget-body" style="padding-top:0">';
  items.forEach(function(item) {
    out += '<div class="sb-popular-item">';
    out += '<div class="sb-pop-icon">' + ICON.football + '</div>';
    out += '<div class="sb-pop-info">';
    out += '<div class="sb-pop-match">' + h(item.match) + '</div>';
    out += '<div class="sb-pop-market">' + h(item.market) + ' · ' + h(item.sel) + '</div>';
    out += '</div>';
    out += '<span class="sb-pop-odd">' + item.val.toFixed(2) + '</span>';
    out += '</div>';
  });
  out += '</div>';
  el.innerHTML = out;
}

/* ── Actions ─────────────────────────────────────────────── */
// Sport-specific league grouping (Tennis=circuit, others=country)
function getLeagueGroup(sportId, leagueName) {
  var n = leagueName.toLowerCase();
  // Tennis (13) — group by circuit type
  if (sportId === 13) {
    if (n.indexOf('atp') !== -1) return 'ATP';
    if (n.indexOf('wta') !== -1) return 'WTA';
    if (n.indexOf('challenger') !== -1) return 'Challenger';
    if (n.indexOf('itf') !== -1 && (n.indexOf('men') !== -1 || n.indexOf('(m)') !== -1 || n.indexOf(' m ') !== -1)) return 'ITF (M)';
    if (n.indexOf('itf') !== -1) return 'ITF (F)';
    if (n.indexOf('utr') !== -1 && (n.indexOf('w') !== -1 || n.indexOf('wom') !== -1)) return 'UTR (W)';
    if (n.indexOf('utr') !== -1) return 'UTR (M)';
    if (n.indexOf('us open') !== -1) return 'US Open';
    if (n.indexOf('french open') !== -1 || n.indexOf('roland garros') !== -1) return 'French Open';
    if (n.indexOf('australian open') !== -1) return 'Australian Open';
    if (n.indexOf('wimbledon') !== -1) return 'Wimbledon';
    if (n.indexOf('davis') !== -1) return 'Davis Cup';
    if (n.indexOf('billie') !== -1 || n.indexOf('fed cup') !== -1) return 'Billie Jean King Cup';
    return guessCountry(leagueName);
  }
  // Table Tennis (107) — group by circuit
  if (sportId === 107) {
    if (n.indexOf('world') !== -1) return 'World';
    if (n.indexOf('europe') !== -1) return 'Europe';
    if (n.indexOf('tt cup') !== -1 || n.indexOf('ttc') !== -1) return 'TT Cup';
    return guessCountry(leagueName);
  }
  // Badminton (45) — group by circuit
  if (sportId === 45) {
    if (n.indexOf('world') !== -1) return 'World';
    if (n.indexOf('super') !== -1) return 'BWF Super Series';
    if (n.indexOf('premier') !== -1) return 'Premier';
    return guessCountry(leagueName);
  }
  // E-Sports (151) — group by game title
  if (sportId === 151) {
    if (n.indexOf('csgo') !== -1 || n.indexOf('cs2') !== -1 || n.indexOf('counter') !== -1) return 'CS2/CSGO';
    if (n.indexOf('dota') !== -1) return 'Dota 2';
    if (n.indexOf('lol') !== -1 || n.indexOf('league of legends') !== -1) return 'League of Legends';
    if (n.indexOf('valorant') !== -1) return 'Valorant';
    if (n.indexOf('overwatch') !== -1) return 'Overwatch';
    if (n.indexOf('rainbow') !== -1 || n.indexOf('r6') !== -1) return 'Rainbow Six';
    if (n.indexOf('rocket') !== -1) return 'Rocket League';
    if (n.indexOf('esoccer') !== -1 || n.indexOf('e-soccer') !== -1) return 'E-Soccer';
    if (n.indexOf('ebasketball') !== -1 || n.indexOf('e-basketball') !== -1) return 'E-Basketball';
    return 'Other';
  }
  // Default: use country
  return guessCountry(leagueName);
}

window.sbToggleSport = function(id) {
  if (S.openSport === id) {
    S.openSport = null;
  } else {
    S.openSport = id;
    // Force reload each time to get fresh live counts
    S['countries_' + id] = null;
    if (!S['countries_' + id]) {
      fetch(BASE + 'sportsbook/api.php?action=leagues&sport_id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
          var leagues = (d && d.leagues) ? d.leagues : [];
          var cmap = {}, corder = [];
          leagues.forEach(function(l) {
            var grp = getLeagueGroup(id, l.name);
            if (!cmap[grp]) {
              cmap[grp] = { name: grp, count: 0, leagues: [], live: false };
              corder.push(grp);
            }
            cmap[grp].count += l.count;
            // Store league with live flag
            cmap[grp].leagues.push({ name: l.name, live: (l.live_cnt || 0) > 0 });
            if (l.live_cnt > 0) cmap[grp].live = true;
          });
          // Sort: live groups first, then priority list, then alpha
          var priority = ['World', 'Europe', 'Angleterre', 'Espagne', 'Italie', 'Allemagne', 'France',
                          'ATP', 'WTA', 'Challenger', 'ITF (M)', 'ITF (F)'];
          corder.sort(function(a, b) {
            var liveDiff = (cmap[b].live ? 1 : 0) - (cmap[a].live ? 1 : 0);
            if (liveDiff !== 0) return liveDiff;
            var pa = priority.indexOf(a); var pb = priority.indexOf(b);
            if (pa !== -1 && pb !== -1) return pa - pb;
            if (pa !== -1) return -1;
            if (pb !== -1) return 1;
            return a.localeCompare(b);
          });
          S['countries_' + id] = corder.map(function(n) { return cmap[n]; });
          renderSidebar();
        })
        .catch(function() {
          S['countries_' + id] = [];
          renderSidebar();
        });
    }
  }
  renderSidebar();
};

window.sbToggleCountry = function(sportId, country) {
  var k = sportId + '_' + country;
  S.openCountries[k] = !S.openCountries[k];
  renderSidebar();
};

window.sbSelectLeague = function(lg, sid) {
  // lg is the REAL BetsAPI league name stored in DB (e.g. "Spain La Liga")
  // sbOpenLeague will use LEAGUE_DB_SEARCH[lg] || lg as search term
  // Since lg is already the real name, the fallback `|| lg` gives exact LIKE '%Spain La Liga%'
  window.sbOpenLeague(null, lg, getFlag(guessCountry(lg)), sid);
};

window.sbOpenLeague = function(id, name, flag, sid) {
  S.activeLeagueId   = id;
  S.activeLeagueName = name;
  S.activeLeagueFlag = flag;
  S.activeSportId    = sid || 1;
  S.viewMode         = 'championship';

  // Hide homepage-only sections when entering championship view
  var enDirect = document.getElementById('sb-en-direct-cards');
  var boostedHdr = document.querySelector('.sb-boost-header');
  var boostedRow = document.getElementById('sb-boosted-odds');
  if (enDirect)   enDirect.style.display   = 'none';
  if (boostedHdr) boostedHdr.style.display  = 'none';
  if (boostedRow) boostedRow.style.display  = 'none';

  var el = document.getElementById('sb-matches-body');
  if (el) el.innerHTML = buildSkeleton(4);

  var searchTerm = LEAGUE_DB_SEARCH[name] || name;
  var url = BASE + 'sportsbook/api.php?action=league_matches&sport_id=' + (sid || 1)
          + '&league=' + encodeURIComponent(searchTerm);

  fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var res = (d && d.results) ? d.results : [];

      // Client-side precision filter
      if (res.length > 0) {
        var refined = res.filter(function(m) {
          return m.league && isLeagueMatch(name, m.league.name);
        });
        if (refined.length > 0) res = refined;
      }

      // Date filter if a future date is selected
      if (S.activeDateOffset > 0) {
        var now2 = new Date();
        var target2 = new Date(now2.getFullYear(), now2.getMonth(), now2.getDate() + S.activeDateOffset);
        var targetStr2 = target2.toDateString();
        res = res.filter(function(m) {
          var ts = parseInt(m.time || m.start_time || 0) || 0;
          if (!ts) return false;
          return new Date(ts * 1000).toDateString() === targetStr2;
        });
      }

      S.champMatches = res; // save so polling can update them
      renderChampionship(id, name, flag, res);
    })
    .catch(function() {
      S.champMatches = [];
      renderChampionship(id, name, flag, []);
    });
};

function renderChampionship(id, name, flag, matches) {
  var el = document.getElementById('sb-matches-body');
  if (!el) return;

  // Detect sport for breadcrumb
  var sport = SPORTS.find(function(s) { return s.id === S.activeSportId; }) || SPORTS[0];
  var country = guessCountry(name);
  var displayName = stripCountryPrefix(name) || name;
  var flagUrl = getFlag(country);

  var out = '<div class="sb-champ-view">';

  // ── Breadcrumb: [<] [Football] [Angleterre] [Premier League] — bordered pills
  out += '<div class="sb-champ-breadcrumb">';
  out += '<button class="sb-bc-pill sb-champ-back-btn" onclick="window.sbBackToMain()" aria-label="Retour">' + ICON.arrowLeft + '</button>';
  out += '<button class="sb-bc-pill" onclick="window.sbBackToMain()">' + h(sport.name) + '</button>';
  if (country && country !== 'International') {
    out += '<button class="sb-bc-pill">' + h(country) + '</button>';
  }
  out += '<button class="sb-bc-pill sb-bc-active">' + h(displayName) + '</button>';
  out += '</div>';

  // ── Top tabs: Cotes de match | Cotes boostées ─────────────────────
  out += '<div class="sb-champ-top-tabs">';
  out += '<span class="sb-ctt active">Cotes de match</span>';
  out += '<span class="sb-ctt">Cotes boostées</span>';
  out += '</div>';

  // ── Sub-nav: Par Ligue | Par Heure ────────────────────────────────────
  out += '<div class="sb-champ-subnav">';
  out += '<button class="sb-subnav-btn active">Par Ligue</button>';
  out += '<button class="sb-subnav-btn">Par Heure</button>';
  out += '</div>';

  // ── Date filter: Tout + upcoming dates ────────────────────────────────
  out += '<div class="sb-champ-date-row">';
  out += '<button class="sb-champ-date active" onclick="window.sbChampDateFilter(this,0)">Tout</button>';
  var fr_days_short = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
  for (var di = 0; di < 7; di++) {
    var dts = new Date(); dts.setDate(dts.getDate() + di);
    var dlbl = fr_days_short[dts.getDay()] + ', ' + String(dts.getDate()).padStart(2,'0') + '/' + String(dts.getMonth()+1).padStart(2,'0');
    out += '<button class="sb-champ-date" onclick="window.sbChampDateFilter(this,' + di + ')">' + dlbl + '</button>';
  }
  out += '</div>';

  // ── Market type tabs (horizontal scroll) ──────────────────────────────
  var mktTypeTabs = ['Tout','Principaux','Spéciale joueurs','1ère mi-temps','2ème mi-temps','Teams H2H','Correct Score','Corners','Cartes','Multi Chance','Multigoals','Combo','Combo 1x2','Combo DC','Specials'];
  out += '<div class="sb-champ-mkt-tabs">';
  mktTypeTabs.forEach(function(t, i) {
    out += '<button class="sb-cmt' + (i === 1 ? ' active' : '') + '" onclick="this.parentNode.querySelectorAll(\'.sb-cmt\').forEach(function(b){b.classList.remove(\'active\')});this.classList.add(\'active\')">' + t + '</button>';
  });
  out += '</div>';

  // ── Market category grid — 6 cols × 2 rows matching fcbet216 exactly ─
  var activeCat = S.activeMarketCat || 'populaire';
  var catRow1 = [
    {key:'populaire', label:'Populaire'},
    {key:'1x2', label:'1x2'},
    {key:'total', label:'Total'},
    {key:'double_chance', label:'Double chance'},
    {key:'btts', label:'Les deux équipes qui marquent'},
    {key:'odd_even', label:'Pair/Impair'}
  ];
  var catRow2 = [
    {key:'handicap', label:'Handicap'},
    {key:'goal_range', label:'Plage de buts'},
    {key:'dnb', label:'Remboursé si match nul'},
    {key:'handicap_3way', label:'Handicap 3 voies'},
    {key:'next_goal', label:'Prochain but'},
    {key:'all_markets', label:'Voir tous les marchés'}
  ];
  out += '<div class="sb-champ-cat-grid">';
  catRow1.forEach(function(c) {
    var cls = (activeCat === c.key) ? ' active' : '';
    out += '<button class="sb-ccg-btn' + cls + '" onclick="window.sbSwitchMarketCat(\'' + c.key + '\')">' + c.label + '</button>';
  });
  out += '</div>';
  out += '<div class="sb-champ-cat-grid">';
  catRow2.forEach(function(c) {
    var cls = (c.key === 'all_markets') ? ' green' : ((activeCat === c.key) ? ' active' : '');
    out += '<button class="sb-ccg-btn' + cls + '" onclick="window.sbSwitchMarketCat(\'' + c.key + '\')">' + c.label + '</button>';
  });
  out += '</div>';

  // ── Column selector — shows active market category name ──────────────
  var catLabel = (catRow1.concat(catRow2)).find(function(c) { return c.key === activeCat; });
  out += '<div class="sb-champ-col-sel">';
  out += '<select class="sb-market-sel"><option>' + (catLabel ? catLabel.label : '1x2') + '</option></select>';
  out += '</div>';

  // ── Match list grouped by league ──────────────────────────────────────
  if (!matches.length) {
    out += '<div class="sb-loader" style="margin-top:16px">Aucun match disponible pour cette ligue.</div>';
  } else {
    var groups = {}, order = [];
    matches.forEach(function(m) {
      var k = (m.league && m.league.name) ? m.league.name : name;
      if (!groups[k]) { groups[k] = []; order.push(k); }
      groups[k].push(m);
    });
    order.forEach(function(lg) {
      var lc = guessCountry(lg);
      var lf = getFlag(lc);
      var lcl = (lc && lc !== 'International') ? (' · ' + h(lc)) : '';
      out += '<div class="sb-league-section-hdr" style="margin-top:8px">';
      out += '<span class="sb-lh-star">' + ICON.star + '</span>';
      out += '<img src="' + lf + '" class="sb-league-f" onerror="this.src=\'https://flagcdn.com/w20/un.png\'">';
      out += '<span class="sb-league-n">' + h(stripCountryPrefix(lg)||lg) + lcl + '</span>';
      out += '<span class="sb-lh-gift" title="Offres spéciales"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 12v10H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg></span>';
      out += '<div class="sb-lh-icons">' + ICON.minus + '</div>';
      out += '</div>';
      groups[lg].forEach(function(m) { out += matchCard(m); });
    });
  }

  out += '</div>'; // sb-champ-view
  el.innerHTML = out;
}

/* Championship date filter helper */
/* ── Market category switcher — re-renders championship with new market ── */
window.sbSwitchMarketCat = function(cat) {
  if (cat === 'all_markets') return; // TODO: open full markets view
  S.activeMarketCat = cat;
  renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.champMatches);
};

window.sbChampDateFilter = function(btn, offset) {
  btn.closest('.sb-champ-date-row').querySelectorAll('.sb-champ-date').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
  // Re-render with date filter
  var res = S.champMatches.filter(function(m) {
    if (offset === 0) return true;
    var ts = parseInt(m.time || m.start_time || 0) || 0;
    if (!ts) return false;
    var now = new Date(); now.setHours(0,0,0,0);
    var target = new Date(now); target.setDate(target.getDate() + offset);
    var md = new Date(ts * 1000); md.setHours(0,0,0,0);
    return md.getTime() === target.getTime();
  });
  renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, res);
};

window.sbBackToMain = function() {
  S.activeLeagueId   = null;
  S.activeLeagueName = null;
  S.activeLeagueFlag = null;
  S.activeMatchId    = null;
  S.viewMode         = 'main';
  S.champMatches     = [];
  clearInterval(window._mdTimerInterval);
  var viewer = document.getElementById('sb-match-viewer');
  if (viewer) viewer.style.display = 'none';

  // Restore homepage sections
  var enDirect = document.getElementById('sb-en-direct-cards');
  var boostedHdr = document.querySelector('.sb-boost-header');
  var boostedRow = document.getElementById('sb-boosted-odds');
  if (enDirect)   enDirect.style.display   = '';
  if (boostedHdr) boostedHdr.style.display  = '';
  if (boostedRow) boostedRow.style.display  = '';

  loadAndFilter(S.activeAction, S.activeSportId, null);
};

/* Mobile league tab switcher (LES MEILLEURES LIGUES / MES LIGUES) */
window.sbMobLeagueTab = function(el, tab) {
  var parent = el.closest('.sb-league-group-hdr');
  parent.querySelectorAll('.sb-mob-tab').forEach(function(t) { t.classList.remove('active'); });
  el.classList.add('active');
  var bestEl = document.getElementById('sb-mob-best-leagues');
  var myEl = document.getElementById('sb-mob-my-leagues');
  if (tab === 'best') {
    if (bestEl) bestEl.style.display = '';
    if (myEl) myEl.style.display = 'none';
  } else {
    if (bestEl) bestEl.style.display = 'none';
    if (myEl) myEl.style.display = '';
  }
};

window.sbToggleFavs = function() {
  var content = document.getElementById('sb-favoris-content') || document.getElementById('sb-favs-content');
  var ico     = document.getElementById('sb-favoris-chevron') || document.getElementById('sb-favs-chevron');
  var row     = document.getElementById('sb-favoris-row');
  if (row) row.classList.toggle('open');
  if (!content) return;
  var isOpen = (content.style.display !== 'none');
  if (isOpen) {
    // Collapse
    content.style.maxHeight = content.scrollHeight + 'px'; // trigger transition
    requestAnimationFrame(function() { content.style.maxHeight = '0'; content.style.overflow = 'hidden'; });
    if (ico) ico.className = 'fa fa-chevron-down';
  } else {
    // Expand
    content.style.display = '';
    content.style.overflow = 'hidden';
    content.style.maxHeight = '0';
    requestAnimationFrame(function() {
      content.style.maxHeight = content.scrollHeight + 200 + 'px';
      setTimeout(function() { content.style.maxHeight = ''; content.style.overflow = ''; }, 350);
    });
    if (ico) ico.className = 'fa fa-chevron-up';
  }
  // After animation, actually hide
  if (isOpen) setTimeout(function() {
    if (content.style.maxHeight === '0px') content.style.display = 'none';
  }, 350);
};

/* ── Search with 300ms debounce (senior pattern) ────────── */
var _sbSearchTimer = null;

window.sbSearchMatches = function(rawQ) {
  clearTimeout(_sbSearchTimer);
  _sbSearchTimer = setTimeout(function() {
    var q = (rawQ || '').trim().toLowerCase();

    // Filter top-league sidebar items
    document.querySelectorAll('.sb-tl-item').forEach(function(el) {
      var name = el.querySelector('.sb-league-name');
      if (name) el.style.display = (!q || name.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
    });

    // Clear → restore full match list
    if (!q) {
      renderMatches(S.matches);
      return;
    }

    // Search across ALL sports if user is actively searching
    if (q.length >= 2) {
      // First: filter currently loaded matches (instant)
      var filtered = S.matches.filter(function(m) {
        var home   = ((m.home   && m.home.name)   || '').toLowerCase();
        var away   = ((m.away   && m.away.name)   || '').toLowerCase();
        var league = ((m.league && m.league.name) || '').toLowerCase();
        return home.indexOf(q) !== -1 || away.indexOf(q) !== -1 || league.indexOf(q) !== -1;
      });
      renderMatches(filtered);

      // Then: search across all sports via API (broader search)
      if (q.length >= 3) {
        fetch(BASE + 'sportsbook/api.php?action=league_matches&sport_id=' + S.activeSportId + '&league=' + encodeURIComponent(rawQ))
          .then(function(r) { return r.json(); })
          .then(function(d) {
            var res = (d && d.results) ? d.results : [];
            if (res.length > filtered.length) {
              var extra = res.filter(function(m) {
                var home = ((m.home && m.home.name) || '').toLowerCase();
                var away = ((m.away && m.away.name) || '').toLowerCase();
                return home.indexOf(q) !== -1 || away.indexOf(q) !== -1;
              });
              if (extra.length > 0) renderMatches(extra);
            }
          }).catch(function() {});
      }
    }
  }, 300);
};

/* ── Sidebar time filters (Tout / Aujourd'hui / 3h / 6h / 24h) ── */
window.sbTimeFilter = function(btn, range) {
  document.querySelectorAll('.sb-time-filters .sb-tf').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');

  var now = Date.now() / 1000;
  var minTs = null, maxTs = null;

  if (range === 'all') {
    // Show everything currently loaded
    renderMatches(S.matches);
    return;
  }
  if (range === 'today') {
    // Today's matches only (midnight to midnight)
    var d = new Date(); d.setHours(0,0,0,0);
    minTs = d.getTime() / 1000;
    var d2 = new Date(d); d2.setDate(d2.getDate() + 1);
    maxTs = d2.getTime() / 1000;
  }
  if (range === '3h')  { minTs = now; maxTs = now + 3  * 3600; }
  if (range === '6h')  { minTs = now; maxTs = now + 6  * 3600; }
  if (range === '24h') { minTs = now; maxTs = now + 24 * 3600; }

  var filtered = S.matches.filter(function(m) {
    var ts = parseInt(m.time || m.start_time || 0) || 0;
    if (!ts) return m.time_status === '1'; // always include live
    var inRange = (!minTs || ts >= minTs) && (!maxTs || ts <= maxTs);
    return inRange || m.time_status === '1'; // always include live
  });
  renderMatches(filtered);
};

window.sbToggleLeft = function() { document.getElementById('sb-left').classList.toggle('open'); };
window.sbToggleRight = function() { document.getElementById('sb-right').classList.toggle('open'); };

window.sbFilterByDate = function(btn, dayOffset) {
  document.querySelectorAll('.sb-date-item').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
  S.activeDateOffset = dayOffset;

  // If currently in a league view, stay in that league but re-filter by date
  if (S.activeLeagueName) {
    window.sbOpenLeague(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.activeSportId);
  } else {
    loadAndFilter(dayOffset === 0 ? 'inplay' : 'upcoming', S.activeSportId, null);
  }
};

// Switch to live view (En direct button)
window.sbSwitchLive = function(btn) {
  document.querySelectorAll('.sb-sport-item').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
  S.activeSportId = 1; S.activeAction = 'inplay'; S.activeLeagueId = null; S.activeDateOffset = 0;
  loadAndFilter('inplay', 1, null);
};

/* Mobile top bar tab switcher — handles home ⇄ EN DIRECT toggle
   Home clicked:       home = green (default state)  |  EN DIRECT = red (default state)
   EN DIRECT clicked:  home = dark gray (inactive)   |  EN DIRECT = green (active/selected)
   This mirrors FCBet216 exactly: images 2 (default) and 3 (EN DIRECT active) */
window.sbSwitchTab = function(btn, action, sportId) {
  var topbar = document.querySelector('.sb-mobile-topbar');
  if (!topbar) return;
  var homeBtn = topbar.querySelector('.sb-btn-home');
  var liveBtn = topbar.querySelector('.sb-btn-live');

  if (action === 'live') {
    // EN DIRECT clicked → turn EN DIRECT green, home gray
    if (liveBtn)  { liveBtn.classList.add('active'); }
    if (homeBtn)  { homeBtn.classList.add('inactive'); }
  } else {
    // Home clicked → home back to green, EN DIRECT back to red (default)
    if (liveBtn)  { liveBtn.classList.remove('active'); }
    if (homeBtn)  { homeBtn.classList.remove('inactive'); }
  }

  S.activeSportId = sportId || 1;
  S.activeAction  = action === 'live' ? 'inplay' : 'inplay';
  S.activeLeagueId = null;
  S.activeDateOffset = 0;
  loadAndFilter('inplay', S.activeSportId, null);
};

// Scroll the sport nav
window.sbScrollNav = function() {
  var scroll = document.getElementById('sb-sport-nav-list');
  if (scroll) scroll.scrollBy({ left: 200, behavior: 'smooth' });
};

/* ══════════════════════════════════════════════════════════
   MATCH DETAIL VIEW — Images 3 & 4
   ══════════════════════════════════════════════════════════ */
window.sbOpenMatch = function(mid) {
  S.activeMatchId = mid;
  S.viewMode = 'matchDetail';
  var el = document.getElementById('sb-matches-body');
  if (el) el.innerHTML = buildMatchDetailSkeleton();

  fetch(BASE + 'sportsbook/api.php?action=match_detail&match_id=' + encodeURIComponent(mid))
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var m = (d && d.match) ? d.match : S.matches.find(function(x){ return String(x.id) === String(mid); }) || null;
      var mkts = (d && d.markets) ? d.markets : [];
      if (m) renderMatchDetail(m, mkts);
    })
    .catch(function() {
      var m = S.matches.find(function(x){ return String(x.id) === String(mid); }) || null;
      if (m) renderMatchDetail(m, []);
    });
};

function buildMatchDetailSkeleton() {
  var out = '<div class="md-view">';
  out += '<div class="md-nav-bar"><div class="sb-sk-block" style="width:80px;height:16px"></div><div class="sb-sk-block" style="width:120px;height:11px"></div></div>';
  out += '<div class="md-match-hdr">';
  out += '<div class="sb-sk-block" style="width:160px;height:11px;margin-bottom:14px"></div>';
  out += '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px">';
  out += '<div style="flex:1"><div class="sb-sk-block" style="width:56px;height:56px;border-radius:50%;margin-bottom:8px"></div><div class="sb-sk-block" style="width:90px;height:13px"></div></div>';
  out += '<div style="text-align:center"><div class="sb-sk-block" style="width:70px;height:30px;margin:auto 0 6px"></div><div class="sb-sk-block" style="width:50px;height:11px;margin:0 auto"></div></div>';
  out += '<div style="flex:1;text-align:right"><div class="sb-sk-block" style="width:56px;height:56px;border-radius:50%;margin:0 0 8px auto"></div><div class="sb-sk-block" style="width:90px;height:13px;margin-left:auto"></div></div>';
  out += '</div></div>';
  out += '<div class="md-tabs-row">' + ['','','','',''].map(function(){ return '<div class="sb-sk-block" style="width:70px;height:28px;border-radius:14px;flex-shrink:0"></div>'; }).join('') + '</div>';
  out += '<div class="md-markets">' + [1,2,3,4].map(function(){
    return '<div class="md-market-group"><div class="md-mkt-hdr"><div class="sb-sk-block" style="width:100px;height:13px"></div></div>'
      + '<div class="md-mkt-body"><div class="md-mkt-row">'
      + '<div class="sb-sk-block" style="height:36px;flex:1;border-radius:4px"></div>'
      + '<div class="sb-sk-block" style="height:36px;flex:1;border-radius:4px"></div>'
      + '<div class="sb-sk-block" style="height:36px;flex:1;border-radius:4px"></div>'
      + '</div></div></div>';
  }).join('') + '</div></div>';
  return out;
}

function renderMatchDetail(m, markets) {
  var el = document.getElementById('sb-matches-body');
  if (!el || !m) return;

  var isLive  = m.time_status === '1';
  var hn      = h(m.home ? m.home.name : '');
  var an      = h(m.away ? m.away.name : '');
  var scores  = m.ss ? m.ss.split('-') : ['',''];
  var scoreH  = scores[0] !== undefined ? scores[0] : '0';
  var scoreA  = scores[1] !== undefined ? scores[1] : '0';
  var FALLBACK = BASE + 'assets/images/logo.png';
  var hl      = m.home ? m.home.image_id : null;
  var al      = m.away ? m.away.image_id : null;
  var hlogo   = hl ? 'https://assets.b365api.com/images/team/m/' + hl + '.png' : FALLBACK;
  var alogo   = al ? 'https://assets.b365api.com/images/team/m/' + al + '.png' : FALLBACK;

  var period   = isLive ? getMatchPeriod(m) : '';
  var baseMins = isLive && m.timer ? (parseInt(m.timer.tm || 0) || 0) : 0;
  var baseSecs = isLive && m.timer ? (parseInt(m.timer.ts || 0) || 0) : 0;
  var isHT     = isLive && m.timer && (m.timer.md === '1' || m.timer.MD === '1');
  var timerInit = isHT ? 'Mi-temps' : (String(baseMins).padStart(2,'0') + ':' + String(baseSecs).padStart(2,'0'));
  var sportId  = parseInt(m.sport_id || 1);

  var lg = m.league ? m.league.name : '';
  var country = guessCountry(lg);
  var flagUrl = getFlag(country);

  var ts2 = parseInt(m.time || 0);
  var dObj = ts2 > 0 ? new Date(ts2 * 1000) : new Date();
  var dateStr = String(dObj.getDate()).padStart(2,'0') + '/' + String(dObj.getMonth()+1).padStart(2,'0') + '/' + dObj.getFullYear()
              + ' ' + String(dObj.getHours()).padStart(2,'0') + ':' + String(dObj.getMinutes()).padStart(2,'0');

  var out = '<div class="md-view">';

  // ── Nav bar — matches reference exactly
  out += '<div class="md-nav-bar">';
  out += '<button class="md-back-btn" onclick="window.sbBackToMain()">' + ICON.arrowLeft + '<span>Retour</span></button>';
  out += '<span class="md-date-info">' + h(dateStr) + '</span>';
  out += '</div>';

  // ── Match header
  out += '<div class="md-match-hdr">';

  // League line + live badge (right-aligned like reference)
  out += '<div class="md-league-line">';
  out += '<img src="' + flagUrl + '" class="md-flag" onerror="this.style.display=\'none\'">';
  out += '<span class="md-league-nm">' + h(lg) + '</span>';
  if (isLive) out += '<span class="md-live-pill" style="margin-left:auto"><span class="md-live-dot"></span>EN DIRECT</span>';
  out += '</div>';

  // Period + counting timer (center above score) — matches reference format "1ère mi-temps | 41:22"
  if (isLive && period) {
    out += '<div class="md-period-row">';
    out += '<span class="md-period-txt">' + h(period) + '</span>';
    if (!isHT) {
      out += '<span class="md-period-sep"> | </span>';
      out += '<span class="md-timer" id="md-timer-display">' + h(timerInit) + '</span>';
    }
    out += '</div>';
  }

  // Teams + Score row
  out += '<div class="md-teams-row">';
  out += '<div class="md-team-col">';
  out += '<img src="' + hlogo + '" class="md-jersey" onerror="this.src=\'' + FALLBACK + '\'">';
  out += '<span class="md-team-nm">' + hn + '</span>';
  out += '</div>';
  out += '<div class="md-score-col">';
  if (isLive) {
    out += '<div class="md-score-live">' + h(scoreH) + ' : ' + h(scoreA) + '</div>';
  } else {
    out += '<div class="md-score-vs">vs</div>';
  }
  out += '</div>';
  out += '<div class="md-team-col">';
  out += '<img src="' + alogo + '" class="md-jersey" onerror="this.src=\'' + FALLBACK + '\'">';
  out += '<span class="md-team-nm">' + an + '</span>';
  out += '</div>';
  out += '</div>';

  // Stats bar (live only)
  if (isLive) {
    out += renderStatsBar(m, sportId);
  }
  out += '</div>'; // md-match-hdr

  // ── Market tabs
  var TABS = ['Tout','Principaux','Bet Builder','Teams H2H','1 minute','2ème mi-temps','Correct Score','Corners','Multigoals','Combi...'];
  out += '<div class="md-tabs-row">';
  out += '<button class="md-tab-icon-btn" title="Rechercher">' + ICON.search + '</button>';
  TABS.forEach(function(t, i) {
    out += '<button class="md-tab' + (i === 1 ? ' active' : '') + '" onclick="window.sbMdTab(this)">' + h(t) + '</button>';
  });
  out += '</div>';

  // ── Markets
  out += '<div class="md-markets">';
  if (!markets.length) markets = buildFallbackMarkets(m);
  markets.forEach(function(mkt, i) { out += renderMarketGroup(mkt, m, i < 4); });
  out += '</div></div>';

  el.innerHTML = out;

  // ── Post-render: start live timer + show pitch viewer
  if (isLive && !isHT) startMatchTimer(m);
  else clearInterval(window._mdTimerInterval);
  showMatchViewer(m);
}

function renderMarketGroup(mkt, m, expanded) {
  var out = '<div class="md-market-group' + (expanded ? '' : ' collapsed') + '">';
  out += '<div class="md-mkt-hdr" onclick="this.parentNode.classList.toggle(\'collapsed\')">';
  out += '<span class="md-mkt-star">' + ICON.star + '</span>';
  out += '<span class="md-mkt-name">' + h(mkt.name) + '</span>';
  out += '<span class="md-mkt-bb">BB</span>';
  out += '<span class="md-mkt-ctrl">' + ICON.minus + '</span>';
  out += '</div>';
  out += '<div class="md-mkt-body">';

  var sels  = mkt.selections || [];
  var n     = sels.length;
  var hasRange = /^total$/i.test(mkt.name);
  var isHc1x2  = /handicap 1x2/i.test(mkt.name);
  var is3x3    = /mi-temps.*fin|marge|dc.*mi-temps/i.test(mkt.name);

  if (isHc1x2) {
    // Handicap 1x2: group by handicap label (Débuts X:Y) — 3 cols per row
    var groups = {}, gord = [];
    sels.forEach(function(s) {
      var hc = s.handicap || '';
      if (!groups[hc]) { groups[hc] = []; gord.push(hc); }
      groups[hc].push(s);
    });
    gord.forEach(function(hc) {
      if (hc) out += '<div class="md-hc-label">' + h(hc) + '</div>';
      out += '<div class="md-mkt-row md-row-3">';
      groups[hc].forEach(function(s) { out += renderMktBtn(s, m); });
      out += '</div>';
    });
  } else if (is3x3 && n === 9) {
    // 3x3 grid (Mi-temps/Fin de match, DC combos)
    for (var i = 0; i < n; i += 3) {
      out += '<div class="md-mkt-row md-row-3">';
      out += renderMktBtn(sels[i], m);
      if (sels[i+1]) out += renderMktBtn(sels[i+1], m);
      if (sels[i+2]) out += renderMktBtn(sels[i+2], m);
      out += '</div>';
    }
  } else if (n <= 3) {
    out += '<div class="md-mkt-row">';
    sels.forEach(function(s) { out += renderMktBtn(s, m); });
    out += '</div>';
  } else {
    // Pairs (2 per row) — most markets
    for (var i = 0; i < n; i += 2) {
      out += '<div class="md-mkt-row">';
      out += renderMktBtn(sels[i], m);
      if (sels[i+1]) out += renderMktBtn(sels[i+1], m);
      out += '</div>';
    }
  }

  // Slider for Total market
  if (hasRange && sels.length >= 2) {
    var lineVal = parseFloat(sels[0].handicap || 2.5);
    out += '<div class="md-slider-wrap">';
    out += '<span class="md-slider-val">' + lineVal + '</span>';
    out += '<input type="range" class="md-slider" min="0.5" max="6.5" step="0.5" value="' + lineVal + '" oninput="this.previousElementSibling.textContent=this.value">';
    out += '</div>';
  }

  out += '</div></div>';
  return out;
}

function renderMktBtn(sel, m) {
  var rawOdd = parseFloat(sel.odds) || 1.50;
  var val    = applyMargin(rawOdd);
  var lbl    = h(sel.name) + (sel.handicap != null ? ' <span class="md-hc">' + sel.handicap + '</span>' : '');
  var bid    = h(m.id) + '_md_' + h(String(sel.id || sel.name));
  var isSel  = S.betSlip.some(function(b){ return b.id === bid; });
  return '<button class="md-odd-btn' + (isSel ? ' sel' : '') + '" onclick="window.sbAddBet(\''
    + bid + '\',\'' + h((m.home ? m.home.name : '') + ' v ' + (m.away ? m.away.name : ''))
    + '\',\'' + h(sel.name) + '\',' + val + ')">'
    + '<span class="md-o-name">' + lbl + '</span>'
    + '<span class="md-o-val">' + val.toFixed(2) + '</span>'
    + '</button>';
}

function buildFallbackMarkets(m) {
  var o = odds(m);
  var s = m.id || '0';
  var mkts = [];
  var hv = parseFloat(o.h) || 2.0, xv = parseFloat(o.x) || 3.5, av = parseFloat(o.a) || 3.0;

  // 1x2
  if (hv > 1.01 && xv > 1.01 && av > 1.01) {
    mkts.push({ id:'1x2', name:'1x2', selections:[
      {id:'1',name:'1',odds:hv},{id:'X',name:'X',odds:xv},{id:'2',name:'2',odds:av}
    ]});
  }

  // Total
  var ov = parseFloat(o.ov) || +seedRand(s+'ov',1.55,2.4).toFixed(2);
  var un = parseFloat(o.un) || +seedRand(s+'un',1.55,2.4).toFixed(2);
  var line = parseFloat(o.ou) || 2.5;
  mkts.push({ id:'total', name:'Total', selections:[
    {id:'ov',name:'Plus de '+line,odds:Math.max(1.01,ov),handicap:line},
    {id:'un',name:'Moins de '+line,odds:Math.max(1.01,un),handicap:line},
  ]});

  // Double Chance
  mkts.push({id:'dc',name:'Double Chance',selections:[
    {id:'1x',name:'1X',odds:Math.max(1.01,+(hv*0.60).toFixed(2))},
    {id:'12',name:'12',odds:Math.max(1.01,+((hv+av)/2*0.75).toFixed(2))},
    {id:'x2',name:'X2',odds:Math.max(1.01,+(av*0.60).toFixed(2))},
  ]});

  // BTTS
  var byy = +seedRand(s+'by',1.4,2.2).toFixed(2);
  mkts.push({id:'btts',name:'Les deux équipes qui marquent',selections:[
    {id:'y',name:'Oui',odds:Math.max(1.01,byy)},{id:'n',name:'Non',odds:Math.max(1.01,+(3.6-byy).toFixed(2))},
  ]});

  // Pair/Impair
  mkts.push({id:'po',name:'Pair/Impair',selections:[
    {id:'i',name:'Impair',odds:Math.max(1.01,+seedRand(s+'pi',1.8,2.1).toFixed(2))},
    {id:'p',name:'Pair',odds:Math.max(1.01,+seedRand(s+'pp',1.8,2.1).toFixed(2))},
  ]});

  // Handicap
  mkts.push({id:'hc',name:'Handicap',selections:[
    {id:'h1',name:'1 +0',odds:Math.max(1.01,+seedRand(s+'hh',1.6,2.5).toFixed(2)),handicap:'+0'},
    {id:'h2',name:'2 -0',odds:Math.max(1.01,+seedRand(s+'ha',1.6,2.5).toFixed(2)),handicap:'-0'},
  ]});

  // Plage de buts
  mkts.push({id:'gr',name:'Plage de buts',selections:[
    {id:'g01',name:'0-1',odds:Math.max(1.01,+seedRand(s+'g01',3.0,6.0).toFixed(2))},
    {id:'g23',name:'2-3',odds:Math.max(1.01,+seedRand(s+'g23',2.0,3.5).toFixed(2))},
    {id:'g45',name:'4-5',odds:Math.max(1.01,+seedRand(s+'g45',4.0,8.0).toFixed(2))},
    {id:'g6p',name:'6+',odds:Math.max(1.01,+seedRand(s+'g6p',12.0,26.0).toFixed(2))},
  ]});

  // Mi-temps/Fin de match (HT/FT 3x3)
  var htft = [['1/1',3.5],['1/X',15],['1/2',31],['X/1',6.5],['X/X',6.5],['X/2',6.66],['2/1',26],['2/X',15],['2/2',3.75]];
  var htftSels = [];
  htft.forEach(function(c,i) { htftSels.push({id:'htft_'+i,name:c[0],odds:Math.max(1.01,+(c[1]*seedRand(s+'htft'+i,0.85,1.15)).toFixed(2))}); });
  mkts.push({id:'htft',name:'Mi-temps/Fin de match',selections:htftSels});

  // Marge de victoire
  var vm = [['1 par 1',4.5],['Nul',3.33],['2 par 1',4.75],['1 par 2',7],['2 par 2',7.5],['1 par 3 ou +',10],['2 par 3 ou +',11]];
  var vmSels = [];
  vm.forEach(function(c,i) { vmSels.push({id:'vm_'+i,name:c[0],odds:Math.max(1.01,+(c[1]*seedRand(s+'vm'+i,0.9,1.1)).toFixed(2))}); });
  mkts.push({id:'vm',name:'Marge de victoire',selections:vmSels});

  // 1 total de buts
  mkts.push({id:'t1',name:'1 total de buts',selections:[
    {id:'t1ov05',name:'Plus de 0.5',odds:Math.max(1.01,+seedRand(s+'t1a',1.1,1.2).toFixed(2))},
    {id:'t1un05',name:'Moins de 0.5',odds:Math.max(1.01,+seedRand(s+'t1b',4.0,5.0).toFixed(2))},
    {id:'t1ov15',name:'Plus de 1.5',odds:Math.max(1.01,+seedRand(s+'t1c',1.7,2.0).toFixed(2))},
    {id:'t1un15',name:'Moins de 1.5',odds:Math.max(1.01,+seedRand(s+'t1d',1.7,2.0).toFixed(2))},
    {id:'t1ov25',name:'Plus de 2.5',odds:Math.max(1.01,+seedRand(s+'t1e',3.5,4.5).toFixed(2))},
    {id:'t1un25',name:'Moins de 2.5',odds:Math.max(1.01,+seedRand(s+'t1f',1.2,1.35).toFixed(2))},
  ]});

  // 2 total
  mkts.push({id:'t2',name:'2 total',selections:[
    {id:'t2ov05',name:'Plus de 0.5',odds:Math.max(1.01,+seedRand(s+'t2a',1.1,1.25).toFixed(2))},
    {id:'t2un05',name:'Moins de 0.5',odds:Math.max(1.01,+seedRand(s+'t2b',3.8,5.0).toFixed(2))},
    {id:'t2ov15',name:'Plus de 1.5',odds:Math.max(1.01,+seedRand(s+'t2c',1.7,2.1).toFixed(2))},
    {id:'t2un15',name:'Moins de 1.5',odds:Math.max(1.01,+seedRand(s+'t2d',1.65,2.0).toFixed(2))},
  ]});

  return mkts;
}

window.sbMdTab = function(btn) {
  document.querySelectorAll('.md-tab').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
};

window.sbSwitchTab = function(btn, action, sid) {
  S.activeAction = action; S.activeSportId = sid; S.activeLeagueId = null;
  renderSportNav();
  loadAndFilter(action, sid, null);
};

window.sbSetUpcomingTab = function(sportId, btn) {
  S.activeUpcomingTab = sportId;
  S.activeSportId = sportId;
  S.activeLeagueId = null;
  S.activeLeagueName = null;

  // Highlight active tab
  document.querySelectorAll('.sb-upcoming-tab').forEach(function(b) { b.classList.remove('active'); });
  if (btn) btn.classList.add('active');

  // Also sync the main sport nav
  document.querySelectorAll('#sb-sport-nav-list .sb-sport-item').forEach(function(b) {
    b.classList.remove('active');
    if (parseInt(b.getAttribute('data-sid') || '0') === sportId) b.classList.add('active');
  });

  // Reload matches for the selected sport
  loadAndFilter(S.activeDateOffset === 0 ? 'inplay' : 'upcoming', sportId, null);
};

function loadAndFilter(action, sid, lid) {
  var el = document.getElementById('sb-matches-body');
  if (el) el.innerHTML = buildSkeleton(5);

  // Use `upcoming` action for future dates, `inplay` for today
  var apiAction = (S.activeDateOffset > 0) ? 'upcoming' : 'inplay';
  var url = BASE + 'sportsbook/api.php?action=' + apiAction + '&sport_id=' + (sid || 1);

  fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var res = (d && d.results) ? d.results : [];

      // Filter out invalid and ended matches
      res = res.filter(function(m) {
        return m && m.id
          && m.home && m.home.name && m.home.name !== ''
          && m.away && m.away.name && m.away.name !== ''
          && m.time_status !== '3';  // 3 = ended/finished
      });

      // DATE FILTER — filter by selected day (offset from today)
      if (S.activeDateOffset > 0) {
        var now = new Date();
        var target = new Date(now.getFullYear(), now.getMonth(), now.getDate() + S.activeDateOffset);
        var targetStr = target.toDateString();
        res = res.filter(function(m) {
          var ts = parseInt(m.time || m.start_time || 0) || 0;
          if (!ts) return false;
          var md = new Date(ts * 1000);
          return md.toDateString() === targetStr;
        });
      }

      // League filter
      if (lid) {
        res = res.filter(function(m) {
          return m.league && (m.league.id == lid || isLeagueMatch(lid, m.league.name || ''));
        });
      }

      S.matches = res;
      renderMatches(S.matches);
      markLiveSidebarLeagues(res);
    })
    .catch(function() { renderMatches([]); });
}

/* ── Mark sidebar top-league items with EN DIRECT badge when live matches exist ── */
function markLiveSidebarLeagues(matches) {
  // Build set of live league names from current results
  var liveApiLeagues = [];
  matches.forEach(function(m) {
    if (m.time_status === '1' && m.league && m.league.name) {
      liveApiLeagues.push(m.league.name);
    }
  });

  document.querySelectorAll('.sb-tl-item').forEach(function(el) {
    var nameEl = el.querySelector('.sb-league-name');
    if (!nameEl) return;
    var displayName = nameEl.textContent.trim(); // e.g. "Serie A", "Premier League"

    // Check using the same isLeagueMatch used for filtering
    var isLive = liveApiLeagues.some(function(apiLn) {
      return isLeagueMatch(displayName, apiLn);
    });

    var badge = el.querySelector('.sb-tl-live-badge');
    if (isLive && !badge) {
      var b = document.createElement('span');
      b.className = 'sb-tl-live-badge';
      b.textContent = 'EN DIRECT';
      el.appendChild(b);
    } else if (!isLive && badge) {
      badge.remove();
    }
  });
}

/* ── Init ─────────────────────────────────────────────────── */
loadAndFilter('inplay', 1, null);
loadCounts();
startPolling();
renderSidebar();
renderBetSlip();
renderPopularBets();

})();
