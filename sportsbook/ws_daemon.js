'use strict';
/**
 * ─────────────────────────────────────────────────────────────────────────
 *  ws_daemon.js — Real-Time Odds Daemon (odds-api.io)
 * ─────────────────────────────────────────────────────────────────────────
 * Two-mode operation:
 *  1. WebSocket mode (preferred): zero-latency push updates
 *  2. REST polling mode (fallback): if WS returns 403/401, falls back to
 *     polling the REST API every POLL_INTERVAL_MS (default 3s) — still
 *     dramatically faster and more reliable than the old BetsAPI setup.
 *
 * Uses the official odds-api-io Node SDK for all REST calls (handles auth).
 *
 * START:
 *   cd /var/www/public_html/sportsbook
 *   npm install
 *   node ws_daemon.js
 *
 * ENV OVERRIDES:
 *   ODDS_API_KEY=...
 *   REDIS_URL=redis://127.0.0.1:6379
 *   POLL_INTERVAL_MS=3000        (REST poll interval in ms, default 3000)
 *   WS_DISABLE=1                 (force REST-only mode for debugging)
 * ─────────────────────────────────────────────────────────────────────────
 */

const WebSocket = require('ws');
const { createClient } = require('redis');

// ── Configuration ────────────────────────────────────────────────────────
const API_KEY         = process.env.ODDS_API_KEY       || 'fbfb8d1a32e0f0a1b4dc55ef2b72abad19e86f1b9c37df1032464e25882e68f2';
const REDIS_URL       = process.env.REDIS_URL           || 'redis://127.0.0.1:6379';
// Poll one sport per cycle, staggered — so 6 sports at 15s each = 1 call/2.5s ≈ 1440 req/hr (within 5000 limit)
const POLL_INTERVAL   = parseInt(process.env.POLL_INTERVAL_MS || '15000', 10);
const WS_DISABLE      = process.env.WS_DISABLE === '1';
const WS_URL          = 'wss://api.odds-api.io/v3/ws';
const MARKETS_WS      = 'ML,Spread,Totals,BTTS,Corners';

// SDK client (lazy-loaded so we can catch missing module gracefully)
let OddsAPIClient = null;
try { OddsAPIClient = require('odds-api-io').OddsAPIClient; } catch(e) {
  console.error('[ws_daemon] WARN: odds-api-io SDK not found. Run: npm install');
}

// Sports table
const SPORTS = [
  { slug: 'football',   id: '1'  },
  { slug: 'basketball', id: '18' },
  { slug: 'tennis',     id: '13' },
  { slug: 'volleyball', id: '91' },
  { slug: 'ice-hockey', id: '17' },
  { slug: 'handball',   id: '78' },
];

// Redis key helpers
const KEY_EV      = id  => `sb:ev:${id}`;
const KEY_SPORT   = sid => `sb:live:sport:${sid}`;
const KEY_ALL     = 'sb:live:all';
const KEY_UPDATED = 'sb:live:updated';

// In-memory mirror
const store = {};

// Sequence tracking for WS replay
let lastSeq = 0;

// Mode flag
let wsMode = !WS_DISABLE;
let wsAuthFailed = false;

// ── Logging ──────────────────────────────────────────────────────────────
function log(...args) {
  const t = new Date().toISOString().replace('T',' ').slice(0,19);
  process.stdout.write(`[ws_daemon ${t}] ${args.join(' ')}\n`);
}

// ── Redis ────────────────────────────────────────────────────────────────
const redis = createClient({ url: REDIS_URL });
redis.on('error', e => log('Redis error:', e.message));

// ── Map period string to our md format ───────────────────────────────────
function mapPeriod(period) {
  if (!period) return '1';
  const p = String(period).toUpperCase().replace(/[-_]/g, '');
  if (p === 'HT' || p === 'HALFTIME') return 'HT';
  if (p === '1H' || p === '1' || p === 'FIRSTHALF') return '1';
  if (p === '2H' || p === '2' || p === 'SECONDHALF') return '2';
  if (p === 'OT' || p === 'ET' || p === 'EXTRATIME') return 'OT';
  return '1';
}

function mapScore(score) {
  if (!score) return '';
  if (typeof score === 'string') return score;
  const h = score.home ?? score.h ?? 0;
  const a = score.away ?? score.a ?? 0;
  return `${h}-${a}`;
}

// ── Convert WS/SDK market data → live_odds + md_markets ─────────────────
function parseMarkets(markets) {
  const lo = {};
  const md = [];
  for (const mkt of (markets || [])) {
    const name = String(mkt.name || '').toUpperCase();
    const o    = (mkt.odds || [])[0] || {};

    if (name === 'ML') {
      if (o.home != null) lo.h = +o.home;
      if (o.draw != null) lo.x = +o.draw;
      if (o.away != null) lo.a = +o.away;
      const sel = [];
      if (lo.h > 1) sel.push({ name:'1', odds: String(lo.h), NA:'1' });
      if (lo.x > 1) sel.push({ name:'X', odds: String(lo.x), NA:'X' });
      if (lo.a > 1) sel.push({ name:'2', odds: String(lo.a), NA:'2' });
      if (sel.length) md.push({ name:'1X2', selections: sel, is_open: true });
    }
    if (name === 'TOTALS') {
      const line = o.hdp ?? 2.5;
      if (o.over  != null) lo.ou_over  = +o.over;
      if (o.under != null) lo.ou_under = +o.under;
      if (line    != null) lo.ou_line  = +line;
      const sel = [];
      if (lo.ou_over  > 1) sel.push({ name:`Plus de ${line}`,  odds: String(lo.ou_over),  NA:`O ${line}` });
      if (lo.ou_under > 1) sel.push({ name:`Moins de ${line}`, odds: String(lo.ou_under), NA:`U ${line}` });
      if (sel.length) md.push({ name:`Over/Under ${line}`, selections: sel, is_open: true });
    }
    if (name === 'SPREAD') {
      lo.hdp_line = +(o.hdp  ?? 0);
      lo.hdp_h    = +(o.home ?? 0);
      lo.hdp_a    = +(o.away ?? 0);
      const sel = [];
      const hdp  = o.hdp ?? 0;
      if (lo.hdp_h > 1) sel.push({ name:`1 (${hdp >= 0 ? '+' : ''}${hdp})`, odds: String(lo.hdp_h), NA:`H ${hdp}` });
      if (lo.hdp_a > 1) sel.push({ name:`2 (${-hdp >= 0 ? '+' : ''}${-hdp})`, odds: String(lo.hdp_a), NA:`A ${-hdp}` });
      if (sel.length) md.push({ name:'Handicap Asiatique', selections: sel, is_open: true });
    }
    if (name === 'BTTS') {
      lo.btts_yes = +(o.yes || o.home || 0);
      lo.btts_no  = +(o.no  || o.away || 0);
      const sel = [];
      if (lo.btts_yes > 1) sel.push({ name:'Oui', odds: String(lo.btts_yes), NA:'Yes' });
      if (lo.btts_no  > 1) sel.push({ name:'Non', odds: String(lo.btts_no),  NA:'No'  });
      if (sel.length) md.push({ name:'Les deux équipes qui marquent', selections: sel, is_open: true });
    }
    if (name === 'CORNERS') {
      const cl = o.hdp ?? 9.5;
      lo.corners_line  = +cl;
      lo.corners_over  = +(o.over  ?? 0);
      lo.corners_under = +(o.under ?? 0);
      const sel = [];
      if (lo.corners_over  > 1) sel.push({ name:`Plus de ${cl}`,  odds: String(lo.corners_over),  NA:`CO ${cl}` });
      if (lo.corners_under > 1) sel.push({ name:`Moins de ${cl}`, odds: String(lo.corners_under), NA:`CU ${cl}` });
      if (sel.length) md.push({ name:`Total des corners Plus/Moins ${cl}`, selections: sel, is_open: true });
    }
  }
  return { live_odds: lo, md_markets: md };
}

// ── Normalise SDK event → our frontend format ────────────────────────────
function normEvent(ev, sportId) {
  const id = String(ev.id);
  return {
    id,
    sport_id:    sportId,
    time:        String(ev.starts_at || ev.start_time || ev.time || 0),
    time_status: ev.status === 'live' ? '1' : (ev.status === 'finished' ? '3' : '0'),
    league: { id: String(ev.league_id || ''), name: ev.league || ev.competition || ev.league_name || '' },
    home:   { id: String(ev.home_id   || ''), name: ev.home   || ev.home_team   || '' },
    away:   { id: String(ev.away_id   || ''), name: ev.away   || ev.away_team   || '' },
    ss:      mapScore(ev.score || ev.ss),
    timer: {
      tm: parseInt(ev.minute || ev.elapsed || 0) || 0,
      ts: parseInt(ev.second || 0) || 0,
      md: mapPeriod(ev.period || ev.half || ev.phase),
    },
    stats: buildStats(ev),
    _source: 'oddsapi',
  };
}

function buildStats(ev) {
  const s = ev.stats || ev.statistics || {};
  const out = {};
  const tryPair = (key, src) => {
    const hk = `home_${src}`, ak = `away_${src}`;
    if (s[hk] != null && s[ak] != null)
      out[key] = [parseInt(s[hk])||0, parseInt(s[ak])||0];
  };
  tryPair('corners',       'corners');
  tryPair('yellow_cards',  'yellow_cards');
  tryPair('red_cards',     'red_cards');
  return Object.keys(out).length ? out : undefined;
}

// ── Write merged event to Redis ──────────────────────────────────────────
async function writeToRedis(id) {
  const ev = store[id];
  if (!ev || !ev.meta) return;

  const parsed = parseMarkets(ev.markets_raw || []);
  const match  = {
    ...ev.meta,
    live_odds:  Object.keys(parsed.live_odds).length ? parsed.live_odds : undefined,
    md_markets: parsed.md_markets.length            ? parsed.md_markets : undefined,
    _bookie:    ev.bookie,
    _seq:       ev.seq,
    _updated:   Date.now(),
  };

  const sid = ev.meta.sport_id || '1';
  await redis.set(KEY_EV(id), JSON.stringify(match), { EX: 600 });
  await redis.sAdd(KEY_SPORT(sid), id);
  await redis.sAdd(KEY_ALL, id);
  await redis.set(KEY_UPDATED, String(Date.now()));
}

async function removeFromRedis(id) {
  const sid = (store[id]?.meta?.sport_id) || '1';
  await redis.del(KEY_EV(id));
  await redis.sRem(KEY_SPORT(sid), id);
  await redis.sRem(KEY_ALL, id);
  delete store[id];
}

// ── Rate-limit state ─────────────────────────────────────────────────────
let rateLimitUntil  = 0;   // epoch ms — don't call REST before this time
let rateLimitBackoff = 30; // seconds — doubles on each hit, resets on success

function isRateLimited() {
  return Date.now() < rateLimitUntil;
}
function onRateLimited() {
  rateLimitBackoff = Math.min(rateLimitBackoff * 2, 300); // max 5 min
  rateLimitUntil   = Date.now() + rateLimitBackoff * 1000;
  log(`Rate limited — backing off ${rateLimitBackoff}s (until ${new Date(rateLimitUntil).toISOString().slice(11,19)})`);
}
function onRateLimitSuccess() {
  rateLimitBackoff = 30; // reset
}

// ── REST: fetch ONE sport per call (stagger to stay within rate limit) ───
// We cycle through sports one at a time, so each full cycle = SPORTS.length calls.
// At POLL_INTERVAL=15s per sport cycle iteration → 1 call every 2.5s → ~1440/hr.
let sportCursor = 0;

async function restFetchNext() {
  if (isRateLimited()) {
    const wait = Math.ceil((rateLimitUntil - Date.now()) / 1000);
    log(`Rate-limited, skipping (${wait}s remaining)`);
    return;
  }
  if (!OddsAPIClient) return;

  const sport  = SPORTS[sportCursor % SPORTS.length];
  sportCursor++;

  const client = new OddsAPIClient({ apiKey: API_KEY });
  try {
    const events = await client.getLiveEvents(sport.slug);
    const arr = Array.isArray(events) ? events
      : (Array.isArray(events?.data)   ? events.data
      : (Array.isArray(events?.events) ? events.events : []));

    for (const ev of arr) {
      const id = String(ev.id);
      if (!id || !ev.home) continue;
      if (!store[id]) store[id] = {};
      store[id].meta = normEvent(ev, sport.id);
      await writeToRedis(id);
    }

    onRateLimitSuccess();
    if (arr.length > 0) log(`REST ${sport.slug}: ${arr.length} events → Redis`);

  } catch(e) {
    const msg = String(e.message || e);
    if (/rate.limit|429|too many/i.test(msg)) {
      onRateLimited();
    } else {
      log(`REST error (${sport.slug}):`, msg);
    }
  } finally {
    try { client.close && client.close(); } catch(_) {}
  }
}

// Full scan of all sports — used only at startup and on WS resync
async function restFetchAll() {
  if (isRateLimited()) { log('Rate-limited, skipping full scan'); return; }
  if (!OddsAPIClient)  return;

  let total = 0;
  for (const sport of SPORTS) {
    if (isRateLimited()) break;
    const client = new OddsAPIClient({ apiKey: API_KEY });
    try {
      const events = await client.getLiveEvents(sport.slug);
      const arr = Array.isArray(events) ? events
        : (Array.isArray(events?.data)   ? events.data
        : (Array.isArray(events?.events) ? events.events : []));

      for (const ev of arr) {
        const id = String(ev.id);
        if (!id || !ev.home) continue;
        if (!store[id]) store[id] = {};
        store[id].meta = normEvent(ev, sport.id);
        await writeToRedis(id);
        total++;
      }
      onRateLimitSuccess();
      // Small gap between sports during bulk fetch to avoid burst rate-limit
      await new Promise(r => setTimeout(r, 500));
    } catch(e) {
      const msg = String(e.message || e);
      if (/rate.limit|429|too many/i.test(msg)) { onRateLimited(); break; }
      log(`REST error (${sport.slug}):`, msg);
    } finally {
      try { client.close && client.close(); } catch(_) {}
    }
  }
  if (total > 0) log(`REST full scan: ${total} events → Redis`);
  else if (!isRateLimited()) log('REST: 0 live events (off-peak or auth issue)');
}

// ── Prune stale events from Redis ────────────────────────────────────────
async function pruneStale() {
  try {
    const ids = await redis.sMembers(KEY_ALL);
    for (const id of ids) {
      const exists = await redis.exists(KEY_EV(id));
      if (!exists) {
        const sid = store[id]?.meta?.sport_id || '1';
        await redis.sRem(KEY_SPORT(sid), id);
        await redis.sRem(KEY_ALL, id);
        delete store[id];
      }
    }
  } catch(e) { log('Prune error:', e.message); }
}

// ── WebSocket mode ───────────────────────────────────────────────────────
let wsInstance    = null;
let wsReconnTimer = null;
let wsAttempts    = 0;

function buildWsUrl() {
  let url = `${WS_URL}?apiKey=${API_KEY}&markets=${encodeURIComponent(MARKETS_WS)}&status=live`;
  const slugs = SPORTS.map(s => s.slug).join(',');
  url += `&sport=${encodeURIComponent(slugs)}`;
  if (lastSeq > 0) url += `&lastSeq=${lastSeq}`;
  return url;
}

function startWebSocket() {
  if (wsReconnTimer) { clearTimeout(wsReconnTimer); wsReconnTimer = null; }

  log(`Connecting WS${lastSeq > 0 ? ' lastSeq='+lastSeq : ' (fresh)'}...`);
  const ws = new WebSocket(buildWsUrl());
  wsInstance = ws;
  let ping = null;

  ws.on('open', () => {
    log('WebSocket connected OK.');
    wsAttempts = 0;
    wsAuthFailed = false;
    ping = setInterval(() => { if (ws.readyState === WebSocket.OPEN) ws.ping(); }, 30000);
  });

  ws.on('message', async (raw) => {
    for (const line of raw.toString().trim().split('\n')) {
      if (!line.trim()) continue;
      try { await handleWsMessage(JSON.parse(line)); }
      catch(e) { log('WS parse error:', e.message); }
    }
  });

  ws.on('unexpected-response', (req, res) => {
    log(`WS auth error: HTTP ${res.statusCode} — WebSocket access not enabled for this key.`);
    if (res.statusCode === 403 || res.statusCode === 401) {
      wsAuthFailed = true;
      log('Switching to REST polling mode (faster than BetsAPI was).');
      ws.terminate();
      // Don't reconnect WS — start REST polling
      startRestPolling();
      return;
    }
  });

  ws.on('error', (e) => log('WS error:', e.message));

  ws.on('close', (code) => {
    if (ping) { clearInterval(ping); ping = null; }
    if (wsAuthFailed) return; // Already switched to REST mode
    log(`WS closed code=${code}.`);
    wsAttempts++;
    if (wsAttempts > 20) {
      log('Too many WS failures — switching to REST polling mode.');
      wsAuthFailed = true;
      startRestPolling();
      return;
    }
    const delay = Math.min(Math.pow(2, wsAttempts - 1) * 1000, 30000);
    log(`Reconnect in ${delay/1000}s (attempt ${wsAttempts}/20)`);
    wsReconnTimer = setTimeout(startWebSocket, delay);
  });
}

async function handleWsMessage(msg) {
  const { type, id, bookie, markets, seq, reason } = msg;
  if (seq && seq > lastSeq) lastSeq = seq;

  switch (type) {
    case 'welcome':
      log('WS Welcome. Bookmakers:', (msg.bookmakers || []).join(', '));
      if (msg.warning) log('WS Warning:', msg.warning);
      break;
    case 'resync_required':
      log('WS Resync required:', reason);
      lastSeq = 0;
      await restFetchAll();
      break;
    case 'created':
    case 'updated': {
      const eid = String(id);
      if (!store[eid]) store[eid] = {};
      store[eid].markets_raw = markets || [];
      store[eid].bookie = bookie;
      store[eid].seq    = seq;
      await writeToRedis(eid);
      if (seq % 10 === 0) log(`WS ${type.toUpperCase()} ev=${eid} bk=${bookie} seq=${seq}`);
      break;
    }
    case 'deleted':
      log(`WS DELETED ev=${id}`);
      await removeFromRedis(String(id));
      break;
    case 'no_markets':
      if (id && store[String(id)]) {
        store[String(id)].markets_raw = [];
        await writeToRedis(String(id));
      }
      break;
  }
}

// ── REST polling mode (fallback when WS is 403) ──────────────────────────
// Stagger: one sport per interval tick so we don't burst all 6 at once.
// 6 sports × 15s = full cycle in 90s = ~24 req/hour/sport (144 total/hr)
let pollTimer = null;
function startRestPolling() {
  if (pollTimer) return; // already running
  log(`Starting staggered REST polling (one sport per ${POLL_INTERVAL}ms tick)...`);
  restFetchNext(); // immediate first tick
  pollTimer = setInterval(restFetchNext, POLL_INTERVAL);
}

// ── Main ─────────────────────────────────────────────────────────────────
async function main() {
  log('='.repeat(60));
  log('alpina216 ws_daemon — odds-api.io Real-Time Feed');
  log('API Key:', API_KEY.slice(0,8) + '...' + API_KEY.slice(-4));
  log('Redis:', REDIS_URL);
  log('='.repeat(60));

  if (!OddsAPIClient) {
    log('ERROR: odds-api-io SDK not installed. Run: npm install');
    process.exit(1);
  }

  await redis.connect();
  log('Redis connected OK.');

  // Initial REST snapshot
  log('Initial REST fetch...');
  await restFetchAll();

  // Background staggered polling for score/timer refresh (one sport per cycle)
  setInterval(restFetchNext, POLL_INTERVAL);
  setInterval(pruneStale,    5 * 60_000);

  if (!WS_DISABLE && !wsAuthFailed) {
    startWebSocket();
  } else {
    log('WS disabled — REST polling only.');
    startRestPolling();
  }

  process.on('SIGINT',  shutdown);
  process.on('SIGTERM', shutdown);
}

async function shutdown() {
  log('Shutting down...');
  if (wsInstance) wsInstance.close();
  if (pollTimer)  clearInterval(pollTimer);
  await redis.quit();
  process.exit(0);
}

main().catch(e => { log('Fatal:', e.message); process.exit(1); });
