const MATCHES = [
  { id: 1, sport: 'football', league: 'Premier League', flag: '🏴󠁧󠁢󠁥󠁮󠁧󠁿', time: '14:30', date: 'today', live: false, home: 'Arsenal', away: 'Chelsea', odds: { '1': '2.10', X: '3.40', '2': '3.20' }, popular: true },
  { id: 2, sport: 'football', league: 'Premier League', flag: '🏴󠁧󠁢󠁥󠁮󠁧󠁿', time: '16:00', date: 'today', live: true, home: 'Manchester City', away: 'Liverpool', odds: { '1': '1.55', X: '4.00', '2': '5.50' }, popular: true, liveMinute: 67 },
  { id: 3, sport: 'football', league: 'La Liga', flag: '🇪🇸', time: '18:30', date: 'today', live: false, home: 'Real Madrid', away: 'Barcelona', odds: { '1': '2.25', X: '3.30', '2': '3.10' }, popular: true },
  { id: 4, sport: 'football', league: 'Serie A', flag: '🇮🇹', time: '20:00', date: 'today', live: false, home: 'Juventus', away: 'AC Milan', odds: { '1': '2.00', X: '3.20', '2': '3.80' }, popular: false },
  { id: 5, sport: 'football', league: 'Bundesliga', flag: '🇩🇪', time: '15:30', date: 'today', live: true, home: 'Bayern Munich', away: 'Borussia Dortmund', odds: { '1': '1.40', X: '4.50', '2': '6.50' }, popular: true, liveMinute: 32 },
  { id: 6, sport: 'football', league: 'Ligue 1', flag: '🇫🇷', time: '21:00', date: 'today', live: false, home: 'PSG', away: 'Marseille', odds: { '1': '1.35', X: '4.80', '2': '7.00' }, popular: false },
  { id: 7, sport: 'basketball', league: 'NBA', flag: '🇺🇸', time: '02:00', date: 'tomorrow', live: false, home: 'LA Lakers', away: 'Boston Celtics', odds: { '1': '2.15', '2': '1.70' }, popular: true },
  { id: 8, sport: 'basketball', league: 'NBA', flag: '🇺🇸', time: '04:30', date: 'tomorrow', live: false, home: 'Golden State Warriors', away: 'Miami Heat', odds: { '1': '1.90', '2': '1.95' }, popular: false },
  { id: 9, sport: 'basketball', league: 'Euroleague', flag: '🇪🇺', time: '19:00', date: 'today', live: true, home: 'Real Madrid Basket', away: 'Fenerbahçe', odds: { '1': '1.65', '2': '2.25' }, popular: false },
  { id: 10, sport: 'tennis', league: 'ATP Masters', flag: '🌍', time: '11:00', date: 'today', live: true, home: 'Djokovic N.', away: 'Alcaraz C.', odds: { '1': '1.80', '2': '2.05' }, popular: true, liveMinute: 0 },
  { id: 11, sport: 'tennis', league: 'ATP Masters', flag: '🌍', time: '13:30', date: 'today', live: false, home: 'Sinner J.', away: 'Medvedev D.', odds: { '1': '1.95', '2': '1.90' }, popular: true },
  { id: 12, sport: 'handball', league: 'Champions League', flag: '🇪🇺', time: '17:00', date: 'today', live: false, home: 'Paris SG Handball', away: 'Barcelona Handball', odds: { '1': '1.85', '2': '1.95' }, popular: false },
  { id: 13, sport: 'rugby', league: 'Top 14', flag: '🇫🇷', time: '21:05', date: 'today', live: false, home: 'Stade Toulousain', away: 'Racing 92', odds: { '1': '1.50', '2': '2.60' }, popular: false },
  { id: 14, sport: 'esport', league: 'LCS', flag: '🌍', time: '22:00', date: 'today', live: false, home: 'T1', away: 'Gen.G', odds: { '1': '1.70', '2': '2.15' }, popular: true },
  { id: 15, sport: 'esport', league: 'LCS', flag: '🌍', time: '01:00', date: 'tomorrow', live: false, home: 'Fnatic', away: 'G2 Esports', odds: { '1': '2.35', '2': '1.60' }, popular: false },
  { id: 16, sport: 'golf', league: 'PGA Tour', flag: '🇺🇸', time: '15:00', date: 'today', live: true, home: 'Rory McIlroy', away: 'Scottie Scheffler', odds: { '1': '2.50', '2': '1.55' }, popular: false, liveMinute: 0 },
  { id: 17, sport: 'football', league: 'Premier League', flag: '🏴󠁧󠁢󠁥󠁮󠁧󠁿', time: '18:00', date: 'tomorrow', live: false, home: 'Tottenham', away: 'Manchester United', odds: { '1': '2.80', X: '3.50', '2': '2.40' }, popular: true },
  { id: 18, sport: 'football', league: 'La Liga', flag: '🇪🇸', time: '21:00', date: 'tomorrow', live: false, home: 'Atletico Madrid', away: 'Sevilla', odds: { '1': '1.70', X: '3.80', '2': '4.50' }, popular: false },
  { id: 19, sport: 'football', league: 'Premier League', flag: '🏴󠁧󠁢󠁥󠁮󠁧󠁿', time: '13:00', date: 'today', live: true, home: 'Aston Villa', away: 'Newcastle', odds: { '1': '2.20', X: '3.60', '2': '3.10' }, popular: false, liveMinute: 78 },
  { id: 20, sport: 'tennis', league: 'WTA', flag: '🌍', time: '09:00', date: 'today', live: true, home: 'Swiatek I.', away: 'Sabalenka A.', odds: { '1': '1.65', '2': '2.30' }, popular: false, liveMinute: 0 }
];

const selections = [];
const $ = id => document.getElementById(id);
const matchesContainer = $('matchesContainer');
const betslipItems = $('betslipItems');
const betslipEmpty = $('betslipEmpty');
const betslipFooter = $('betslipFooter');
const totalOddsEl = $('totalOdds');
const totalGainEl = $('totalGain');
const stakeInput = $('stake');
const betCount = $('betCount');
const mobileBetCount = $('mobileBetCount');
const searchInput = $('searchInput');
const sectionTitle = $('sectionTitle');
const sectionCount = $('sectionCount');
const sidebarList = $('sidebarList');
const filterBar = $('filterBar');
const backdrop = $('backdrop');
const burgerBtn = $('burgerBtn');
const betslipClose = $('betslipClose');
const mobileBetBtn = $('mobileBetBtn');
const clearAll = $('clearAll');
const placeBetBtn = $('placeBet');
const loadingOverlay = $('loadingOverlay');
const sidebar = $('sidebar');
const betslip = $('betslip');

const sportNames = {
  all: 'Tous les sports', football: 'Football', basketball: 'Basketball',
  tennis: 'Tennis', handball: 'Handball', rugby: 'Rugby', esport: 'E-Sport', golf: 'Golf'
};

const filterDateMap = {
  'all': null,
  'live': 'live',
  'today': 'today',
  'tomorrow': 'tomorrow',
  'popular': 'popular'
};

let liveTimers = {};

function filterMatches(sport, filterKey, query) {
  let list = MATCHES;

  if (sport && sport !== 'all') list = list.filter(m => m.sport === sport);

  if (filterKey === 'live') list = list.filter(m => m.live);
  else if (filterKey === 'today') list = list.filter(m => m.date === 'today');
  else if (filterKey === 'tomorrow') list = list.filter(m => m.date === 'tomorrow');
  else if (filterKey === 'popular') list = list.filter(m => m.popular);

  if (query) {
    const q = query.toLowerCase().trim();
    list = list.filter(m =>
      m.home.toLowerCase().includes(q) ||
      m.away.toLowerCase().includes(q) ||
      m.league.toLowerCase().includes(q)
    );
  }

  return list;
}

function groupByLeague(list) {
  const groups = {};
  list.forEach(m => {
    if (!groups[m.league]) groups[m.league] = [];
    groups[m.league].push(m);
  });
  return groups;
}

function updateSectionTitle(sport, count) {
  const name = sportNames[sport] || sport;
  const icon = sport === 'football'
    ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c3.314 0 6-2.686 6-6 0-3.314-6-10-6-10S6 12.686 6 16c0 3.314 2.686 6 6 6z"/><path d="M12 2v4"/></svg>'
    : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>';
  sectionTitle.innerHTML = `${icon} ${name} <span class="section-header__count" id="sectionCount">${count} match${count > 1 ? 's' : ''}</span>`;
}

function renderMatches(sport, filterKey, query) {
  const list = filterMatches(sport, filterKey, query);
  const groups = groupByLeague(list);
  const entries = Object.entries(groups);

  updateSectionTitle(sport, list.length);

  if (entries.length === 0) {
    matchesContainer.innerHTML = `
      <div class="league-group" style="text-align:center;padding:60px 20px;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" opacity="0.2" style="margin:0 auto 16px"><circle cx="12" cy="12" r="10"/><path d="M16 8l-8 8M8 8l8 8"/></svg>
        <p style="color:var(--text-muted)">Aucun match trouvé</p>
      </div>`;
    return;
  }

  const totalCount = list.length;
  sectionCount.textContent = `${totalCount} match${totalCount > 1 ? 's' : ''}`;

  let html = '';
  entries.forEach(([league, matches]) => {
    const flag = matches[0].flag || '🏆';
    const leagueId = `league-${league.replace(/\s+/g, '-').toLowerCase()}`;
    html += `
      <div class="league-group">
        <div class="league-header" data-target="${leagueId}">
          <span class="league-header__flag">${flag}</span>
          <span class="league-header__name">${league}</span>
          <span class="league-header__count">${matches.length}</span>
          <span class="league-header__arrow">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"/></svg>
          </span>
        </div>
        <div class="league-body" id="${leagueId}">
          ${matches.map(m => renderMatchCard(m)).join('')}
        </div>
      </div>`;
  });

  matchesContainer.innerHTML = html;
  attachListeners();
  startLiveTimers();
}

function renderMatchCard(m) {
  const keys = Object.keys(m.odds);
  const oddsHtml = keys.map(k => {
    const selIdx = selections.findIndex(s => s.matchId === m.id && s.key === k);
    const selected = selIdx !== -1;
    const label = k === '1' ? m.home : k === '2' ? m.away : 'N';
    return `
      <button class="odd-btn ${selected ? 'selected' : ''}"
        data-match-id="${m.id}" data-key="${k}" data-odds="${m.odds[k]}" data-label="${label}">
        <span class="odd-btn__label">${k}</span>
        <span class="odd-btn__value">${m.odds[k]}</span>
      </button>`;
  }).join('');

  const liveHtml = m.live
    ? `<span class="match-card__live">Live${m.liveMinute != null ? ' ' + m.liveMinute + '\'' : ''}</span>`
    : '';

  const timerHtml = m.live && m.liveMinute != null
    ? `<span class="match-card__timer" data-minute="${m.liveMinute}" data-match-id="${m.id}">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span class="timer-value">${m.liveMinute}\'</span>
      </span>`
    : '';

  return `
    <div class="match-card" data-match-id="${m.id}">
      <div class="match-card__info">
        <div class="match-card__time">
          ${liveHtml}
          ${timerHtml}
          <span>${m.time}</span>
        </div>
        <div class="match-card__teams">
          <span class="match-card__team">${m.home}</span>
          <span class="match-card__team">${m.away}</span>
        </div>
      </div>
      <div class="match-card__odds">${oddsHtml}</div>
    </div>`;
}

function attachListeners() {
  document.querySelectorAll('.odd-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      toggleSelection(this);
    });
  });

  document.querySelectorAll('.league-header').forEach(header => {
    header.addEventListener('click', function() {
      const targetId = this.dataset.target;
      const body = document.getElementById(targetId);
      const arrow = this.querySelector('.league-header__arrow');
      if (body && arrow) {
        body.classList.toggle('collapsed');
        arrow.classList.toggle('collapsed');
      }
    });
  });
}

function toggleSelection(btn) {
  const matchId = parseInt(btn.dataset.matchId);
  const key = btn.dataset.key;
  const odds = btn.dataset.odds;
  const label = btn.dataset.label;
  const match = MATCHES.find(m => m.id === matchId);

  const existingIdx = selections.findIndex(s => s.matchId === matchId && s.key === key);
  if (existingIdx !== -1) {
    selections.splice(existingIdx, 1);
  } else {
    selections.push({
      matchId, key, odds, label,
      matchName: `${match.home} vs ${match.away}`,
      matchLeague: match.league
    });
  }

  updateAll();
}

function getActiveSport() {
  const active = sidebarList.querySelector('.sidebar__item.active');
  return active ? active.dataset.sport : 'all';
}

function getActiveFilter() {
  const active = filterBar.querySelector('.filter-btn.active');
  const key = active ? active.dataset.filter : 'all';
  return key;
}

function updateAll() {
  const sport = getActiveSport();
  const filterKey = getActiveFilter();
  const query = searchInput.value;
  renderMatches(sport, filterKey, query);
  updateBetslip();
}

function updateBetslip() {
  const count = selections.length;
  betCount.textContent = count;
  if (mobileBetCount) mobileBetCount.textContent = count;

  if (count === 0) {
    betslipEmpty.classList.remove('hidden');
    betslipItems.innerHTML = '';
    betslipFooter.classList.add('hidden');
    return;
  }

  betslipEmpty.classList.add('hidden');
  betslipFooter.classList.remove('hidden');

  let html = '';
  selections.forEach((s, i) => {
    html += `
      <div class="betslip__item" style="animation-delay:${i * 0.05}s">
        <div class="betslip__item-info">
          <div class="betslip__item-match">${s.matchName}</div>
          <div class="betslip__item-selection">${s.label} @ ${s.odds}</div>
        </div>
        <span class="betslip__item-odds">${s.odds}</span>
        <button class="betslip__item-remove" data-index="${i}">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>`;
  });

  betslipItems.innerHTML = html;

  betslipItems.querySelectorAll('.betslip__item-remove').forEach(btn => {
    btn.addEventListener('click', function() {
      const idx = parseInt(this.dataset.index);
      selections.splice(idx, 1);
      updateAll();
    });
  });

  calculateTotal();
}

function calculateTotal() {
  const total = selections.reduce((acc, s) => acc * parseFloat(s.odds), 1);
  totalOddsEl.textContent = total.toFixed(2);

  const stake = Math.max(1, parseFloat(stakeInput.value) || 0);
  const gain = stake * total;
  totalGainEl.textContent = gain.toFixed(2) + ' DT';
}

function startLiveTimers() {
  Object.values(liveTimers).forEach(t => clearInterval(t));
  liveTimers = {};

  document.querySelectorAll('.match-card__timer').forEach(el => {
    const matchId = parseInt(el.dataset.matchId);
    const match = MATCHES.find(m => m.id === matchId);
    if (!match || !match.live || match.liveMinute == null) return;

    const valueEl = el.querySelector('.timer-value');
    if (!valueEl) return;

    liveTimers[matchId] = setInterval(() => {
      match.liveMinute++;
      valueEl.textContent = match.liveMinute + '\'';
      if (match.liveMinute > 90) match.liveMinute = 90;
    }, 60000);
  });
}

sidebarList.addEventListener('click', function(e) {
  const item = e.target.closest('.sidebar__item');
  if (!item) return;
  sidebarList.querySelectorAll('.sidebar__item').forEach(el => el.classList.remove('active'));
  item.classList.add('active');
  selections.length = 0;
  updateAll();
  closeSidebar();
});

filterBar.addEventListener('click', function(e) {
  const btn = e.target.closest('.filter-btn');
  if (!btn) return;
  filterBar.querySelectorAll('.filter-btn').forEach(el => el.classList.remove('active'));
  btn.classList.add('active');
  updateAll();
});

searchInput.addEventListener('input', function() {
  updateAll();
});

burgerBtn.addEventListener('click', function() {
  sidebar.classList.toggle('open');
  backdrop.classList.toggle('hidden');
  backdrop.classList.toggle('visible');
  burgerBtn.classList.toggle('active');
});

betslipClose.addEventListener('click', closeBetslip);
mobileBetBtn.addEventListener('click', openBetslip);
clearAll.addEventListener('click', function() {
  selections.length = 0;
  updateAll();
});

backdrop.addEventListener('click', function() {
  closeSidebar();
  closeBetslip();
});

stakeInput.addEventListener('input', calculateTotal);

document.querySelectorAll('.betslip__stake-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const dir = this.dataset.dir;
    let val = parseInt(stakeInput.value) || 1;
    val = dir === 'up' ? val + 1 : Math.max(1, val - 1);
    stakeInput.value = val;
    calculateTotal();
  });
});

placeBetBtn.addEventListener('click', function() {
  if (selections.length === 0) return;
  const stake = Math.max(1, parseFloat(stakeInput.value) || 0);
  const total = selections.reduce((acc, s) => acc * parseFloat(s.odds), 1);
  const gain = stake * total;

  this.innerHTML = '<div class="loading-spinner" style="width:20px;height:20px;border-width:2px;margin:0 auto"></div>';
  this.disabled = true;

  setTimeout(() => {
    alert(`✅ Pari placé avec succès !\n\nMise: ${stake} DT\nCote totale: ${total.toFixed(2)}\nGain potentiel: ${gain.toFixed(2)} DT\n\nSélections: ${selections.length}`);
    selections.length = 0;
    this.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> Placer le pari';
    this.disabled = false;
    updateAll();
  }, 800);
});

function closeSidebar() {
  sidebar.classList.remove('open');
  backdrop.classList.add('hidden');
  backdrop.classList.remove('visible');
  burgerBtn.classList.remove('active');
}

function openBetslip() {
  betslip.classList.add('open');
  backdrop.classList.remove('hidden');
  backdrop.classList.add('visible');
}

function closeBetslip() {
  betslip.classList.remove('open');
  backdrop.classList.add('hidden');
  backdrop.classList.remove('visible');
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeSidebar();
    closeBetslip();
  }
});

window.addEventListener('resize', function() {
  if (window.innerWidth > 768) closeSidebar();
  if (window.innerWidth > 1100) closeBetslip();
});

function init() {
  setTimeout(() => {
    loadingOverlay.classList.add('fade-out');
    setTimeout(() => loadingOverlay.style.display = 'none', 500);
  }, 600);

  renderMatches('all', 'all', '');
  updateBetslip();

  setInterval(() => {
    document.querySelectorAll('.odd-btn:not(.selected)').forEach(btn => {
      if (Math.random() > 0.85) {
        btn.classList.remove('flash-up', 'flash-down');
        void btn.offsetWidth;
        btn.classList.add(Math.random() > 0.5 ? 'flash-up' : 'flash-down');
        const valEl = btn.querySelector('.odd-btn__value');
        if (valEl) {
          const current = parseFloat(valEl.textContent);
          const delta = (Math.random() * 0.2 - 0.1);
          const newVal = Math.max(1.01, current + delta);
          const matchId = parseInt(btn.dataset.matchId);
          const key = btn.dataset.key;
          const match = MATCHES.find(m => m.id === matchId);
          if (match && match.odds[key]) {
            match.odds[key] = newVal.toFixed(2);
          }
        }
      }
    });
  }, 5000);
}

init();
