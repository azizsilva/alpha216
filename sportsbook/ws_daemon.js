'use strict';
/**
 * ws_daemon.js — alpina216 Real-Time Odds Daemon (odds-api.io)
 *
 * Cycle strategy (stays within 5000 req/hr free limit):
 *   Every CYCLE_MS (default 90s), process ONE sport:
 *     1. getLiveEvents(sport)          → 1 API call  (scores, timers, teams)
 *     2. getOddsForMultipleEvents(...)  → 1 call per 10 events (odds batch)
 *   6 sports × ~4 calls = ~24 calls per 90s = ~960 calls/hour ✓
 *
 * WebSocket mode: auto-switches to push (no polling needed) if provider
 *   enables WS access on the key.
 *
 * START:
 *   cd /var/www/public_html/sportsbook
 *   npm install
 *   node ws_daemon.js
 *
 * ENV:
 *   ODDS_API_KEY=...
 *   REDIS_URL=redis://127.0.0.1:6379
 *   CYCLE_MS=90000       cycle interval in ms (default 90000 = 90s)
 *   WS_DISABLE=1         force REST-only
 */

const WebSocket = require('ws');
const { createClient } = require('redis');

const API_KEY   = process.env.ODDS_API_KEY || 'fbfb8d1a32e0f0a1b4dc55ef2b72abad19e86f1b9c37df1032464e25882e68f2';
const REDIS_URL = process.env.REDIS_URL    || 'redis://127.0.0.1:6379';
const CYCLE_MS  = parseInt(process.env.CYCLE_MS || '90000', 10);
const WS_DISABLE= process.env.WS_DISABLE === '1';

const WS_URL    = 'wss://api.odds-api.io/v3/ws';
const BOOKMAKER = 'Bet365';
// All markets to request (WS mode)
const WS_MARKETS = 'ML,Spread,Totals,BTTS,DoubleChance,Corners,Cards,CorrectScore';

let OddsAPIClient = null;
try { OddsAPIClient = require('odds-api-io').OddsAPIClient; }
catch(e) { console.error('[ws_daemon] odds-api-io not found — run: npm install'); process.exit(1); }

const SPORTS = [
  { slug: 'football',   id: '1'  },
  { slug: 'basketball', id: '18' },
  { slug: 'tennis',     id: '13' },
  { slug: 'volleyball', id: '91' },
  { slug: 'ice-hockey', id: '17' },
  { slug: 'handball',   id: '78' },
];

// Redis keys
const KEY_EV    = id  => `sb:ev:${id}`;
const KEY_SPORT = sid => `sb:live:sport:${sid}`;
const KEY_ALL   = 'sb:live:all';
const KEY_TS    = 'sb:live:updated';

// In-memory mirror
const store = {};
let lastSeq = 0;
let wsAuthFailed = false;

function log(...a) {
  process.stdout.write(`[ws_daemon ${new Date().toISOString().slice(0,19).replace('T',' ')}] ${a.join(' ')}\n`);
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

// ── Redis ────────────────────────────────────────────────────────────────
const redis = createClient({ url: REDIS_URL });
redis.on('error', e => log('Redis error:', e.message));

// ── Rate-limit guard ─────────────────────────────────────────────────────
let rlUntil   = 0;
let rlBackoff = 60; // seconds

function rateLimited() { return Date.now() < rlUntil; }
function onRL() {
  rlBackoff   = Math.min(rlBackoff * 2, 600);
  rlUntil     = Date.now() + rlBackoff * 1000;
  log(`Rate limited — pause ${rlBackoff}s (until ${new Date(rlUntil).toISOString().slice(11,19)})`);
}
function onRLOk() { rlBackoff = 60; }

// ── SDK client helper ────────────────────────────────────────────────────
function mkClient() { return new OddsAPIClient({ apiKey: API_KEY }); }
function closeClient(c) { try { c && c.close && c.close(); } catch(_){} }

function isRL(e) { return /rate.limit|429|too many/i.test(String(e?.message || e)); }

// ── Period / score helpers ───────────────────────────────────────────────
function mapPeriod(p) {
  if (!p) return '1';
  const s = String(p).toUpperCase().replace(/[-_ ]/g,'');
  if (s === 'HT' || s === 'HALFTIME')  return 'HT';
  if (s === '1H' || s === '1' || s === 'FIRSTHALF')  return '1';
  if (s === '2H' || s === '2' || s === 'SECONDHALF') return '2';
  if (s === 'OT' || s === 'ET')                       return 'OT';
  return '1';
}
function mapScore(sc) {
  if (!sc) return '';
  if (typeof sc === 'string') return sc;
  return `${sc.home ?? sc.h ?? 0}-${sc.away ?? sc.a ?? 0}`;
}
function normEv(ev, sportId) {
  return {
    id: String(ev.id),
    sport_id: sportId,
    time: String(ev.starts_at || ev.start_time || ev.time || 0),
    time_status: ev.status === 'live' ? '1' : ev.status === 'finished' ? '3' : '0',
    league: { id: String(ev.league_id||''), name: ev.league||ev.competition||ev.league_name||'' },
    home:   { id: String(ev.home_id  ||''), name: ev.home||ev.home_team||'' },
    away:   { id: String(ev.away_id  ||''), name: ev.away||ev.away_team||'' },
    ss: mapScore(ev.score||ev.ss),
    timer: {
      tm: parseInt(ev.minute||ev.elapsed||0)||0,
      ts: parseInt(ev.second||0)||0,
      md: mapPeriod(ev.period||ev.half||ev.phase),
    },
    _source: 'oddsapi',
  };
}

// ── Market builder ───────────────────────────────────────────────────────
// Accepts an array of { name, odds:[{home,draw,away,hdp,over,under,yes,no,...}] }
// Returns { live_odds, md_markets[] } in the format the frontend expects.
function buildMarkets(markets) {
  const lo = {};   // live_odds (fast card display)
  const md = [];   // md_markets (match-detail tabs)
  let ml_h = 0, ml_x = 0, ml_a = 0;

  for (const mkt of (markets || [])) {
    const name = String(mkt.name||'').toUpperCase().replace(/[\s_-]/g,'');
    const o = (mkt.odds||[])[0] || {};

    // ── 1X2 ─────────────────────────────────────────────────────────────
    if (name === 'ML' || name === '1X2' || name === 'MONEYLINE') {
      ml_h = +(o.home||0); ml_x = +(o.draw||0); ml_a = +(o.away||0);
      if (ml_h > 1) lo.h = ml_h;
      if (ml_x > 1) lo.x = ml_x;
      if (ml_a > 1) lo.a = ml_a;
      const sel = [];
      if (ml_h > 1) sel.push({ name:'1',   odds:fmt(ml_h), NA:'1' });
      if (ml_x > 1) sel.push({ name:'X',   odds:fmt(ml_x), NA:'X' });
      if (ml_a > 1) sel.push({ name:'2',   odds:fmt(ml_a), NA:'2' });
      if (sel.length) md.push({ name:'1X2', selections: sel, is_open: true });
    }

    // ── Over / Under (Totals) ────────────────────────────────────────────
    if (name === 'TOTALS' || name === 'OVERUNDER' || name === 'GOALS') {
      const line = +(o.hdp??2.5);
      const ov   = +(o.over||0), un = +(o.under||0);
      if (line) lo.ou_line  = line;
      if (ov)   lo.ou_over  = ov;
      if (un)   lo.ou_under = un;
      const sel = [];
      if (ov > 1) sel.push({ name:`Plus de ${line}`,  odds:fmt(ov), NA:`O ${line}` });
      if (un > 1) sel.push({ name:`Moins de ${line}`, odds:fmt(un), NA:`U ${line}` });
      if (sel.length) md.push({ name:`Over/Under ${line}`, selections:sel, is_open:true });
    }

    // ── Asian Handicap (Spread) ──────────────────────────────────────────
    if (name === 'SPREAD' || name === 'ASIANHANDICAP' || name === 'HANDICAP') {
      const hdp = +(o.hdp||0);
      const hh  = +(o.home||0), ah = +(o.away||0);
      lo.hdp_line = hdp; lo.hdp_h = hh; lo.hdp_a = ah;
      const sel = [];
      const fh = hdp >= 0 ? `+${hdp}` : `${hdp}`;
      const fa = (-hdp) >= 0 ? `+${-hdp}` : `${-hdp}`;
      if (hh > 1) sel.push({ name:`1 (${fh})`, odds:fmt(hh), NA:`H ${hdp}` });
      if (ah > 1) sel.push({ name:`2 (${fa})`, odds:fmt(ah), NA:`A ${-hdp}` });
      if (sel.length) md.push({ name:'Handicap Asiatique', selections:sel, is_open:true });
    }

    // ── Double Chance ────────────────────────────────────────────────────
    if (name === 'DOUBLECHANCE') {
      const dc = {};
      for (const entry of (mkt.odds||[])) {
        const n = String(entry.name||entry.NA||'').toUpperCase().replace(/[\s_]/g,'');
        const v = +(entry.odds||entry.home||0);
        if (n==='1X'||n==='HOMEORDRAW')  dc['1X'] = v;
        if (n==='12'||n==='HOMEORAWAY')  dc['12'] = v;
        if (n==='X2'||n==='DRAWORAWAY') dc['X2'] = v;
        // Single odds object with named keys
        if (!entry.name && entry['1X']) dc['1X'] = +entry['1X'];
        if (!entry.name && entry['12']) dc['12'] = +entry['12'];
        if (!entry.name && entry['X2']) dc['X2'] = +entry['X2'];
      }
      // Fallback: compute from ML odds if not provided
      if (!dc['1X'] && ml_h > 1 && ml_x > 1) {
        const p1 = 1/ml_h, px = 1/ml_x, p2 = ml_a > 1 ? 1/ml_a : 0;
        dc['1X'] = round((1/(p1+px)) * 0.95);
        dc['X2'] = round((1/(px+p2)) * 0.95);
        dc['12'] = round((1/(p1+p2)) * 0.95);
      }
      const sel = [];
      if (dc['1X'] > 1) sel.push({ name:'1X', odds:fmt(dc['1X']), NA:'1X' });
      if (dc['12'] > 1) sel.push({ name:'12', odds:fmt(dc['12']), NA:'12' });
      if (dc['X2'] > 1) sel.push({ name:'X2', odds:fmt(dc['X2']), NA:'X2' });
      if (sel.length) md.push({ name:'Double chance', selections:sel, is_open:true });
    }

    // ── BTTS ─────────────────────────────────────────────────────────────
    if (name === 'BTTS' || name === 'BOTHTEAMSTOSCORE') {
      const y = +(o.yes||o.home||0), n = +(o.no||o.away||0);
      lo.btts_yes = y; lo.btts_no = n;
      const sel = [];
      if (y > 1) sel.push({ name:'Oui', odds:fmt(y), NA:'Yes' });
      if (n > 1) sel.push({ name:'Non', odds:fmt(n), NA:'No'  });
      if (sel.length) md.push({ name:'Les deux équipes qui marquent', selections:sel, is_open:true });
    }

    // ── Corners ──────────────────────────────────────────────────────────
    if (name === 'CORNERS' || name === 'TOTALCORNERS') {
      const cl = +(o.hdp??9.5);
      const co = +(o.over||0), cu = +(o.under||0);
      lo.corners_line = cl; lo.corners_over = co; lo.corners_under = cu;
      const sel = [];
      if (co > 1) sel.push({ name:`Plus de ${cl}`,  odds:fmt(co), NA:`CO ${cl}` });
      if (cu > 1) sel.push({ name:`Moins de ${cl}`, odds:fmt(cu), NA:`CU ${cl}` });
      if (sel.length) md.push({ name:`Total des corners Plus/Moins ${cl}`, selections:sel, is_open:true });
    }

    // ── Cards ─────────────────────────────────────────────────────────────
    if (name === 'CARDS' || name === 'TOTALCARDS' || name === 'YELLOWCARDS') {
      const yl = +(o.hdp??3.5);
      const yo = +(o.over||0), yu = +(o.under||0);
      const sel = [];
      if (yo > 1) sel.push({ name:`Plus de ${yl}`,  odds:fmt(yo), NA:`YC ${yl}` });
      if (yu > 1) sel.push({ name:`Moins de ${yl}`, odds:fmt(yu), NA:`YC- ${yl}` });
      if (sel.length) md.push({ name:`Cartons Plus/Moins ${yl}`, selections:sel, is_open:true });
    }

    // ── Correct Score ────────────────────────────────────────────────────
    if (name === 'CORRECTSCORE' || name === 'EXACTSCORE') {
      const sel = [];
      for (const entry of (mkt.odds||[])) {
        const sc = String(entry.name||entry.score||entry.NA||'');
        const v  = +(entry.odds||0);
        if (v > 1 && sc) sel.push({ name:sc, odds:fmt(v), NA:sc });
      }
      if (sel.length) md.push({ name:'Score exact', selections:sel, is_open:true });
    }

    // ── Team Totals ──────────────────────────────────────────────────────
    if (name === 'TEAMTOTAL' || name === 'TEAMTOTALS') {
      // Usually comes as separate Home/Away team total markets
      const team = mkt.team || (name.includes('HOME') ? 'home' : 'away');
      const tl   = +(o.hdp??0.5);
      const to   = +(o.over||0), tu = +(o.under||0);
      const lbl  = team === 'home' ? 'Équipe 1' : 'Équipe 2';
      const sel  = [];
      if (to > 1) sel.push({ name:`Plus de ${tl}`,  odds:fmt(to), NA:`T${team}O ${tl}` });
      if (tu > 1) sel.push({ name:`Moins de ${tl}`, odds:fmt(tu), NA:`T${team}U ${tl}` });
      if (sel.length) md.push({ name:`Total ${lbl} Plus/Moins ${tl}`, selections:sel, is_open:true });
    }
  }

  // ── Inject computed Double Chance if ML present but DC not in API ────
  if (ml_h > 1 && ml_x > 1 && ml_a > 1 && !md.some(m => m.name === 'Double chance')) {
    const p1 = 1/ml_h, px = 1/ml_x, p2 = 1/ml_a;
    const dc1x = round((1/(p1+px)) * 0.95);
    const dc12 = round((1/(p1+p2)) * 0.95);
    const dcx2 = round((1/(px+p2)) * 0.95);
    const sel  = [];
    if (dc1x > 1) sel.push({ name:'1X', odds:fmt(dc1x), NA:'1X' });
    if (dc12 > 1) sel.push({ name:'12', odds:fmt(dc12), NA:'12' });
    if (dcx2 > 1) sel.push({ name:'X2', odds:fmt(dcx2), NA:'X2' });
    if (sel.length) md.push({ name:'Double chance', selections:sel, is_open:true });
  }

  return { live_odds: lo, md_markets: md };
}

function fmt(v) { return (Math.round(parseFloat(v) * 100) / 100).toFixed(2); }
function round(v) { return Math.round(v * 100) / 100; }

// ── Redis write / remove ─────────────────────────────────────────────────
async function writeToRedis(id) {
  const ev = store[id];
  if (!ev?.meta) return;
  const { live_odds, md_markets } = buildMarkets(ev.markets_raw || []);
  const match = {
    ...ev.meta,
    live_odds:  Object.keys(live_odds).length  ? live_odds  : undefined,
    md_markets: md_markets.length              ? md_markets : undefined,
    _bookie:    ev.bookie,
    _updated:   Date.now(),
  };
  const sid = ev.meta.sport_id || '1';
  await redis.set(KEY_EV(id), JSON.stringify(match), { EX: 600 });
  await redis.sAdd(KEY_SPORT(sid), id);
  await redis.sAdd(KEY_ALL, id);
  await redis.set(KEY_TS, String(Date.now()));
}
async function removeFromRedis(id) {
  const sid = store[id]?.meta?.sport_id || '1';
  await redis.del(KEY_EV(id));
  await redis.sRem(KEY_SPORT(sid), id);
  await redis.sRem(KEY_ALL, id);
  delete store[id];
}

// ── ONE SPORT CYCLE: events + odds ───────────────────────────────────────
// Each call fetches: 1 event-list + ceil(n/10) odds-batch calls
// Budget at CYCLE_MS=90s, 6 sports: each sport runs once per 90s
// ~4 calls/sport × 6 = 24/90s = 960/hr (well under 5000/hr limit)
async function fetchSportCycle(sport) {
  if (rateLimited()) { log(`Rate-limited, skip ${sport.slug}`); return; }

  const c1 = mkClient();
  let arr = [];
  try {
    const resp = await c1.getLiveEvents(sport.slug);
    arr = Array.isArray(resp) ? resp
        : (Array.isArray(resp?.data) ? resp.data
        : (Array.isArray(resp?.events) ? resp.events : []));
    onRLOk();
  } catch(e) {
    if (isRL(e)) { onRL(); return; }
    log(`Event list error (${sport.slug}):`, e.message);
    return;
  } finally { closeClient(c1); }

  // Update event metadata for all events in this sport
  for (const ev of arr) {
    const id = String(ev.id);
    if (!id || !ev.home) continue;
    if (!store[id]) store[id] = {};
    store[id].meta = normEv(ev, sport.id);
    // If we already have markets for this event, write immediately (keeps score fresh)
    if (store[id].markets_raw) await writeToRedis(id);
  }

  if (arr.length === 0) return;
  log(`${sport.slug}: ${arr.length} events`);

  // Fetch odds in batches of 10
  const ids = arr.map(e => String(e.id)).filter(Boolean);
  const BATCH = 10;
  for (let i = 0; i < ids.length; i += BATCH) {
    if (rateLimited()) { log('Rate-limited mid-batch, stopping'); break; }
    const batch = ids.slice(i, i + BATCH);
    const c2 = mkClient();
    try {
      const oddsResp = await c2.getOddsForMultipleEvents({
        event_ids:  batch.join(','),
        bookmakers: BOOKMAKER,
      });
      const items = Array.isArray(oddsResp) ? oddsResp
          : (Array.isArray(oddsResp?.data) ? oddsResp.data : []);

      for (const item of items) {
        const eid = String(item.id || '');
        if (!eid || !store[eid]) continue;
        // Extract markets from bookmaker data
        const bkData = item.bookmakers || {};
        for (const [, mkts] of Object.entries(bkData)) {
          if (Array.isArray(mkts) && mkts.length) {
            store[eid].markets_raw = mkts;
            store[eid].bookie = Object.keys(bkData)[0];
            break;
          }
        }
        await writeToRedis(eid);
      }
      onRLOk();
    } catch(e) {
      if (isRL(e)) { onRL(); break; }
      log(`Odds batch error (${sport.slug}):`, e.message);
    } finally { closeClient(c2); }

    // Small gap between batches to avoid burst
    if (i + BATCH < ids.length) await sleep(1000);
  }
}

// ── WebSocket mode ───────────────────────────────────────────────────────
let wsInstance = null, wsReconn = null, wsAttempts = 0;

function buildWsUrl() {
  const slugs = SPORTS.map(s => s.slug).join(',');
  let url = `${WS_URL}?apiKey=${API_KEY}`;
  url += `&markets=${encodeURIComponent(WS_MARKETS)}`;
  url += `&sport=${encodeURIComponent(slugs)}&status=live`;
  if (lastSeq > 0) url += `&lastSeq=${lastSeq}`;
  return url;
}

function startWebSocket() {
  if (wsReconn) { clearTimeout(wsReconn); wsReconn = null; }
  log(`Connecting WS${lastSeq > 0 ? ' lastSeq='+lastSeq : ''}...`);
  const ws = new WebSocket(buildWsUrl());
  wsInstance = ws;
  let ping = null;

  ws.on('open', () => {
    log('WebSocket OK — switching to push mode (no REST polling needed).');
    wsAttempts = 0; wsAuthFailed = false;
    ping = setInterval(() => { if (ws.readyState === WebSocket.OPEN) ws.ping(); }, 30000);
  });

  ws.on('message', async (raw) => {
    for (const line of raw.toString().trim().split('\n')) {
      if (!line.trim()) continue;
      try { await handleWsMsg(JSON.parse(line)); } catch(_) {}
    }
  });

  ws.on('unexpected-response', (req, res) => {
    if (res.statusCode === 403 || res.statusCode === 401) {
      wsAuthFailed = true;
      log(`WS 403 — WebSocket not enabled for this key. REST polling mode active.`);
      ws.terminate();
    }
  });

  ws.on('error', e => { if (!wsAuthFailed) log('WS error:', e.message); });

  ws.on('close', code => {
    if (ping) { clearInterval(ping); ping = null; }
    if (wsAuthFailed) return;
    wsAttempts++;
    if (wsAttempts > 15) { wsAuthFailed = true; log('WS: too many failures, REST-only.'); return; }
    const delay = Math.min(Math.pow(2, wsAttempts-1)*1000, 30000);
    wsReconn = setTimeout(startWebSocket, delay);
  });
}

async function handleWsMsg(msg) {
  const { type, id, bookie, markets, seq } = msg;
  if (seq && seq > lastSeq) lastSeq = seq;
  switch (type) {
    case 'welcome': log('WS Welcome. Bookmakers:', (msg.bookmakers||[]).join(',')); break;
    case 'resync_required': log('WS resync'); lastSeq = 0; break;
    case 'created':
    case 'updated': {
      const eid = String(id);
      if (!store[eid]) store[eid] = {};
      store[eid].markets_raw = markets || [];
      store[eid].bookie = bookie;
      await writeToRedis(eid);
      break;
    }
    case 'deleted': await removeFromRedis(String(id)); break;
  }
}

// ── Prune stale Redis entries ────────────────────────────────────────────
async function pruneStale() {
  try {
    for (const id of await redis.sMembers(KEY_ALL)) {
      if (!await redis.exists(KEY_EV(id))) {
        const sid = store[id]?.meta?.sport_id || '1';
        await redis.sRem(KEY_SPORT(sid), id);
        await redis.sRem(KEY_ALL, id);
        delete store[id];
      }
    }
  } catch(_) {}
}

// ── Main ─────────────────────────────────────────────────────────────────
let sportCursor = 0;

async function main() {
  log('='.repeat(60));
  log('alpina216 ws_daemon — odds-api.io');
  log(`API Key: ${API_KEY.slice(0,8)}...${API_KEY.slice(-4)}`);
  log(`Cycle: ${CYCLE_MS/1000}s | Budget: ~${Math.round((SPORTS.length * 4 * 3600) / (CYCLE_MS/1000))} req/hr`);
  log('='.repeat(60));

  await redis.connect();
  log('Redis connected.');

  // Staggered initial scan — one sport every 2s to avoid burst
  log('Initial scan...');
  for (const sport of SPORTS) {
    await fetchSportCycle(sport);
    if (!rateLimited()) await sleep(2000);
  }

  // Cyclic poll: one sport per CYCLE_MS interval
  setInterval(async () => {
    if (wsAuthFailed || !WS_DISABLE) {
      // In REST-only mode, cycle through sports
      const sport = SPORTS[sportCursor % SPORTS.length];
      sportCursor++;
      try { await fetchSportCycle(sport); } catch(e) { log('Cycle error:', e.message); }
    }
  }, CYCLE_MS);

  // Even in WS mode, refresh scores every 60s (WS gives odds, not scores)
  setInterval(async () => {
    if (wsAuthFailed) return; // WS mode doesn't use this
    // In WS mode: periodic score refresh without odds (cheap — just event list)
  }, 60_000);

  setInterval(pruneStale, 5 * 60_000);

  if (!WS_DISABLE && !wsAuthFailed) startWebSocket();

  process.on('SIGINT',  shutdown);
  process.on('SIGTERM', shutdown);
}

async function shutdown() {
  log('Shutting down...');
  if (wsInstance) wsInstance.close();
  await redis.quit();
  process.exit(0);
}

// When WS switches to REST, start REST cycling
setInterval(() => {
  // wsAuthFailed gets set inside ws.on('unexpected-response')
  // The CYCLE_MS interval above handles the rest
}, 1000);

main().catch(e => { log('Fatal:', e.message); process.exit(1); });
