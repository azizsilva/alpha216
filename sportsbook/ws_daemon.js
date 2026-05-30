'use strict';
/**
 * ─────────────────────────────────────────────────────────────────────────
 *  ws_daemon.js — Real-Time Odds WebSocket Daemon
 * ─────────────────────────────────────────────────────────────────────────
 * Connects to the odds-api.io WebSocket feed and keeps Redis stocked
 * with fresh live-match data so api.php can serve it in <10 ms per request.
 *
 * Replaces the old BetsAPI-based tick_live.php.
 *
 * START (once Node.js + Redis are installed on the VPS):
 *   cd /var/www/public_html/sportsbook
 *   npm install
 *   node ws_daemon.js
 *
 * Or as a systemd service — see ws_daemon.service (auto-created below).
 *
 * ENV OVERRIDES:
 *   ODDS_API_KEY=...    (default: key hard-coded below)
 *   REDIS_URL=redis://127.0.0.1:6379
 *   WS_SPORT=football,basketball,tennis   (comma-separated slugs)
 *   WS_MARKETS=ML,Spread,Totals,BTTS,Corners
 * ─────────────────────────────────────────────────────────────────────────
 */

const WebSocket = require('ws');
const { createClient } = require('redis');
const https = require('https');

// ── Configuration ────────────────────────────────────────────────────────
const API_KEY  = process.env.ODDS_API_KEY  || 'fbfb8d1a32e0f0a1b4dc55ef2b72abad19e86f1b9c37df1032464e25882e68f2';
const REDIS_URL = process.env.REDIS_URL    || 'redis://127.0.0.1:6379';
const REST_BASE = 'https://api.odds-api.io/v3';
const WS_URL    = 'wss://api.odds-api.io/v3/ws';

// Markets to subscribe to (ML=1x2, Spread=Asian HDP, Totals=O/U, BTTS, Corners)
const MARKETS   = process.env.WS_MARKETS  || 'ML,Spread,Totals,BTTS,Corners';

// Sports: { slug: odds-api slug, id: our numeric sport_id }
const SPORTS = [
  { slug: 'football',   id: 1  },
  { slug: 'basketball', id: 18 },
  { slug: 'tennis',     id: 13 },
  { slug: 'volleyball', id: 91 },
  { slug: 'ice-hockey', id: 17 },
  { slug: 'handball',   id: 78 },
];

// Redis key namespace
const KEY_EV      = id => `sb:ev:${id}`;          // JSON string per event
const KEY_SPORT   = sid => `sb:live:sport:${sid}`; // SET of event IDs
const KEY_ALL     = 'sb:live:all';                 // SET of all live event IDs
const KEY_UPDATED = 'sb:live:updated';             // timestamp of last WS tick

// Sequence number for zero-data-loss reconnect replay
let lastSeq = 0;

// In-memory mirror: eventId → full match object (so we merge meta + odds)
const store = {};

// ── Redis setup ──────────────────────────────────────────────────────────
const redis = createClient({ url: REDIS_URL });
redis.on('error', e => log('Redis error:', e.message));

// ── Helpers ──────────────────────────────────────────────────────────────
function log(...args) {
  process.stdout.write('[ws_daemon ' + ts() + '] ' + args.join(' ') + '\n');
}
function ts() {
  return new Date().toISOString().replace('T',' ').slice(0,19);
}

/** Simple HTTPS GET — returns parsed JSON or null */
function restGet(path) {
  return new Promise((resolve) => {
    const url = REST_BASE + path;
    const opts = {
      headers: { 'X-API-Key': API_KEY, 'Accept': 'application/json' },
      timeout: 10000,
    };
    https.get(url, opts, res => {
      let buf = '';
      res.on('data', d => buf += d);
      res.on('end', () => {
        try { resolve(JSON.parse(buf)); }
        catch(e) { log('JSON parse error', path, e.message); resolve(null); }
      });
    }).on('error', e => { log('REST error', path, e.message); resolve(null); });
  });
}

/** Map a period string ("1H","2H","HT","OT"…) to BetsAPI-style md */
function mapPeriod(period) {
  if (!period) return '1';
  const p = String(period).toUpperCase();
  if (p === 'HT' || p === 'HALF_TIME' || p === 'HALF-TIME') return 'HT';
  if (p === '1H' || p === '1'  || p === 'FIRST_HALF')  return '1';
  if (p === '2H' || p === '2'  || p === 'SECOND_HALF') return '2';
  if (p === 'OT' || p === 'ET' || p === 'EXTRA_TIME')  return 'OT';
  if (p === 'PEN'|| p === 'PENALTIES') return 'PEN';
  return '1';
}

/** Build a score string "H-A" from the event score object */
function mapScore(score) {
  if (!score) return '';
  if (typeof score === 'string') return score;
  const h = score.home ?? score.h ?? 0;
  const a = score.away ?? score.a ?? 0;
  return `${h}-${a}`;
}

/**
 * Convert odds-api.io markets array (from WS message) into
 * the live_odds + totals format the PHP/JS frontend understands:
 *
 *   live_odds: { h, x, a, ou_line, ou_over, ou_under }
 *   markets_raw: full array from API (for match detail / tabs)
 */
function parseMarkets(markets) {
  const live_odds = {};
  const markets_raw = markets || [];

  for (const mkt of markets_raw) {
    const name = (mkt.name || '').toUpperCase();
    const o    = (mkt.odds || [])[0] || {};

    if (name === 'ML') {
      if (o.home  != null) live_odds.h = parseFloat(o.home);
      if (o.draw  != null) live_odds.x = parseFloat(o.draw);
      if (o.away  != null) live_odds.a = parseFloat(o.away);
    }
    if (name === 'TOTALS') {
      if (o.hdp   != null) live_odds.ou_line  = parseFloat(o.hdp);
      if (o.over  != null) live_odds.ou_over  = parseFloat(o.over);
      if (o.under != null) live_odds.ou_under = parseFloat(o.under);
    }
    if (name === 'SPREAD') {
      live_odds.hdp_line = parseFloat(o.hdp  || 0);
      live_odds.hdp_h    = parseFloat(o.home || 0);
      live_odds.hdp_a    = parseFloat(o.away || 0);
    }
    if (name === 'BTTS') {
      live_odds.btts_yes = parseFloat(o.yes || o.home || 0);
      live_odds.btts_no  = parseFloat(o.no  || o.away || 0);
    }
    if (name === 'CORNERS') {
      if (o.hdp   != null) live_odds.corners_line  = parseFloat(o.hdp);
      if (o.over  != null) live_odds.corners_over  = parseFloat(o.over);
      if (o.under != null) live_odds.corners_under = parseFloat(o.under);
    }
  }

  return { live_odds, markets_raw };
}

/**
 * Convert odds-api.io markets_raw into md_markets array
 * that the match-detail tab renderer in api.php / app.js can use.
 * Each entry: { name: 'Over/Under 2.5', odds: [{name:'Over',odds:1.92},{name:'Under',odds:1.92}] }
 */
function buildMdMarkets(markets_raw) {
  const md = [];
  for (const mkt of markets_raw) {
    const name = (mkt.name || '').toUpperCase();
    const o    = (mkt.odds || [])[0] || {};

    if (name === 'ML') {
      md.push({
        name: '1X2',
        odds: [
          { name: '1', odds: String(o.home || ''), NA: '1' },
          { name: 'X', odds: String(o.draw || ''), NA: 'X' },
          { name: '2', odds: String(o.away || ''), NA: '2' },
        ].filter(x => parseFloat(x.odds) > 1.0)
      });
    }
    if (name === 'TOTALS') {
      const line = o.hdp ?? 2.5;
      md.push({
        name: `Over/Under ${line}`,
        odds: [
          { name: `Plus de ${line}`, odds: String(o.over  || ''), NA: `O ${line}` },
          { name: `Moins de ${line}`,odds: String(o.under || ''), NA: `U ${line}` },
        ].filter(x => parseFloat(x.odds) > 1.0)
      });
    }
    if (name === 'SPREAD') {
      md.push({
        name: 'Handicap Asiatique',
        odds: [
          { name: `1 (${o.hdp >= 0 ? '+' : ''}${o.hdp})`, odds: String(o.home || ''), NA: `H ${o.hdp}` },
          { name: `2 (${-(o.hdp||0) >= 0 ? '+' : ''}${-(o.hdp||0)})`, odds: String(o.away || ''), NA: `A ${-(o.hdp||0)}` },
        ].filter(x => parseFloat(x.odds) > 1.0)
      });
    }
    if (name === 'BTTS') {
      md.push({
        name: 'Les deux équipes qui marquent',
        odds: [
          { name: 'Oui', odds: String(o.yes  || o.home || ''), NA: 'Yes' },
          { name: 'Non', odds: String(o.no   || o.away || ''), NA: 'No'  },
        ].filter(x => parseFloat(x.odds) > 1.0)
      });
    }
    if (name === 'CORNERS') {
      const cline = o.hdp ?? 9.5;
      md.push({
        name: `Total des corners Plus/Moins ${cline}`,
        odds: [
          { name: `Plus de ${cline}`,  odds: String(o.over  || ''), NA: `CO ${cline}` },
          { name: `Moins de ${cline}`, odds: String(o.under || ''), NA: `CU ${cline}` },
        ].filter(x => parseFloat(x.odds) > 1.0)
      });
    }
  }
  return md;
}

// ── REST: fetch all live events for a sport ──────────────────────────────
async function fetchLiveEvents(sport) {
  const data = await restGet(`/events?sport=${sport.slug}&status=live`);
  // odds-api.io returns an array of events (or {data:[...]} wrapper)
  const events = Array.isArray(data) ? data
    : (Array.isArray(data?.data) ? data.data
    : (Array.isArray(data?.events) ? data.events : []));

  let count = 0;
  for (const ev of events) {
    const id = String(ev.id);
    if (!id) continue;
    if (!store[id]) store[id] = {};

    // Build match metadata in the format api.php / frontend expects
    store[id].meta = {
      id,
      sport_id: String(sport.id),
      time:        String(ev.starts_at || ev.start_time || ev.time || 0),
      time_status: ev.status === 'live' ? '1' : (ev.status === 'finished' ? '3' : '0'),
      league: { id: String(ev.league_id || ''), name: ev.league || ev.competition || ev.league_name || '' },
      home:   { id: String(ev.home_id   || ''), name: ev.home   || ev.home_team   || '' },
      away:   { id: String(ev.away_id   || ''), name: ev.away   || ev.away_team   || '' },
      ss:      mapScore(ev.score || ev.ss),
      timer: {
        tm: parseInt(ev.minute || ev.timer_minutes || ev.elapsed || 0),
        ts: parseInt(ev.second || ev.timer_seconds || 0),
        md: mapPeriod(ev.period || ev.half || ev.phase),
      },
      stats: buildStats(ev),
      _source: 'oddsapi',
    };
    count++;
    await writeEventToRedis(id);
  }
  return count;
}

/** Extract stats (corners, cards) from event if provided by the REST API */
function buildStats(ev) {
  const s = ev.stats || ev.statistics || {};
  const out = {};
  const tryPair = (key, src) => {
    const hk = `home_${src}`, ak = `away_${src}`;
    if (s[hk] != null && s[ak] != null) out[key] = [parseInt(s[hk]), parseInt(s[ak])];
  };
  tryPair('corners',       'corners');
  tryPair('yellow_cards',  'yellow_cards');
  tryPair('red_cards',     'red_cards');
  tryPair('attacks',       'attacks');
  tryPair('shots_on_target','shots_on_target');
  return Object.keys(out).length ? out : undefined;
}

/** Write the merged match object to Redis */
async function writeEventToRedis(id) {
  const ev = store[id];
  if (!ev) return;
  const meta  = ev.meta  || {};
  const parsed = parseMarkets(ev.markets_raw || []);

  const match = {
    ...meta,
    live_odds:   parsed.live_odds,
    md_markets:  buildMdMarkets(ev.markets_raw || []),
    _bookie:     ev.bookie,
    _seq:        ev.seq,
    _updated:    Date.now(),
  };

  const sportId = meta.sport_id || '1';

  // Store per-event JSON string (TTL 10 min — cleaned up if event disappears)
  await redis.set(KEY_EV(id), JSON.stringify(match), { EX: 600 });

  // Track in sport-specific set and global set
  await redis.sAdd(KEY_SPORT(sportId), id);
  await redis.sAdd(KEY_ALL, id);

  // Timestamp for SSE/polling freshness
  await redis.set(KEY_UPDATED, String(Date.now()));
}

/** Remove an event that was deleted/finished from Redis */
async function removeEventFromRedis(id) {
  const ev = store[id] || {};
  const sportId = (ev.meta || {}).sport_id || '1';
  await redis.del(KEY_EV(id));
  await redis.sRem(KEY_SPORT(sportId), id);
  await redis.sRem(KEY_ALL, id);
  delete store[id];
}

// ── WebSocket ────────────────────────────────────────────────────────────
let wsInstance = null;
let reconnectTimer = null;
let reconnectAttempts = 0;
const MAX_RECONNECT = 20;

function buildWsUrl() {
  const sportSlugs = SPORTS.map(s => s.slug).join(',');
  let url = `${WS_URL}?apiKey=${API_KEY}&markets=${encodeURIComponent(MARKETS)}&sport=${encodeURIComponent(sportSlugs)}&status=live`;
  if (lastSeq > 0) url += `&lastSeq=${lastSeq}`;
  return url;
}

function startWebSocket() {
  if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }

  const url = buildWsUrl();
  log(`Connecting WS${lastSeq > 0 ? ' lastSeq=' + lastSeq : ' (fresh)'}...`);
  const ws = new WebSocket(url);
  wsInstance = ws;

  // Ping every 30s to keep connection alive
  let pingTimer = null;

  ws.on('open', () => {
    log('WebSocket connected.');
    reconnectAttempts = 0;
    pingTimer = setInterval(() => {
      if (ws.readyState === WebSocket.OPEN) ws.ping();
    }, 30000);
  });

  ws.on('message', async (raw) => {
    const lines = raw.toString().trim().split('\n');
    for (const line of lines) {
      if (!line.trim()) continue;
      try {
        await handleMessage(JSON.parse(line));
      } catch(e) {
        log('Parse error:', e.message, '|', line.slice(0, 80));
      }
    }
  });

  ws.on('error', (e) => log('WS error:', e.message));

  ws.on('close', (code) => {
    if (pingTimer) { clearInterval(pingTimer); pingTimer = null; }
    log(`WS closed code=${code}.`);
    scheduleReconnect();
  });
}

function scheduleReconnect() {
  reconnectAttempts++;
  if (reconnectAttempts > MAX_RECONNECT) {
    log('Max reconnect attempts reached. Exiting.');
    process.exit(1);
  }
  const delay = Math.min(Math.pow(2, reconnectAttempts - 1) * 1000, 30000);
  log(`Reconnect in ${delay / 1000}s (attempt ${reconnectAttempts}/${MAX_RECONNECT})`);
  reconnectTimer = setTimeout(startWebSocket, delay);
}

async function handleMessage(msg) {
  const { type, id, bookie, markets, seq, reason } = msg;

  // Track sequence for zero-data-loss replay
  if (seq && seq > lastSeq) lastSeq = seq;

  switch (type) {
    case 'welcome':
      log('Welcome. Bookmakers:', (msg.bookmakers || []).join(', '));
      if (lastSeq > 0) log('Replaying missed updates since seq', lastSeq);
      break;

    case 'resync_required':
      log('Resync required:', reason, '— rebuilding from REST...');
      lastSeq = 0;
      for (const sport of SPORTS) await fetchLiveEvents(sport);
      break;

    case 'created':
    case 'updated': {
      const eid = String(id);
      if (!store[eid]) store[eid] = {};
      store[eid].markets_raw = markets || [];
      store[eid].bookie      = bookie;
      store[eid].seq         = seq;
      await writeEventToRedis(eid);
      // Log every 5th update to avoid flooding stdout
      if (seq % 5 === 0) log(`${type.toUpperCase()} ev=${eid} bk=${bookie} seq=${seq}`);
      break;
    }

    case 'deleted': {
      const eid = String(id);
      log(`DELETED ev=${eid}`);
      await removeEventFromRedis(eid);
      break;
    }

    case 'no_markets':
      // Event exists but has no markets right now — keep meta, clear odds
      if (id) {
        const eid = String(id);
        if (store[eid]) {
          store[eid].markets_raw = [];
          await writeEventToRedis(eid);
        }
      }
      break;
  }
}

// ── Periodic REST refresh for scores/timers ──────────────────────────────
// The WS gives us real-time odds but NOT live scores/timers.
// We poll the REST API every 10s to keep scores/timers fresh.
async function refreshAllScores() {
  for (const sport of SPORTS) {
    try {
      await fetchLiveEvents(sport);
    } catch(e) {
      log('REST refresh error:', sport.slug, e.message);
    }
  }
}

// ── Cleanup: purge stale events from sets ────────────────────────────────
// Any event in the Redis sets that no longer has a key gets pruned.
async function pruneStaleEvents() {
  try {
    const ids = await redis.sMembers(KEY_ALL);
    for (const id of ids) {
      const exists = await redis.exists(KEY_EV(id));
      if (!exists) {
        // Key expired or was deleted — remove from sets
        const sportId = (store[id]?.meta?.sport_id) || '1';
        await redis.sRem(KEY_SPORT(sportId), id);
        await redis.sRem(KEY_ALL, id);
        delete store[id];
      }
    }
  } catch(e) {
    log('Prune error:', e.message);
  }
}

// ── Main ─────────────────────────────────────────────────────────────────
async function main() {
  log('='.repeat(60));
  log('alpina216 ws_daemon — odds-api.io Real-Time Feed');
  log('Markets:', MARKETS);
  log('Sports:', SPORTS.map(s => s.slug).join(', '));
  log('='.repeat(60));

  await redis.connect();
  log('Redis connected:', REDIS_URL);

  // Initial REST snapshot so we have team names before WS lands
  log('Fetching initial live events...');
  for (const sport of SPORTS) {
    const n = await fetchLiveEvents(sport);
    log(`  ${sport.slug}: ${n} events`);
  }

  // Start the WebSocket
  startWebSocket();

  // Refresh scores/timers from REST every 10s
  setInterval(refreshAllScores, 10_000);

  // Prune stale Redis keys every 5 min
  setInterval(pruneStaleEvents, 5 * 60_000);

  // Graceful shutdown
  process.on('SIGINT',  shutdown);
  process.on('SIGTERM', shutdown);
}

async function shutdown() {
  log('Shutting down...');
  if (wsInstance) wsInstance.close();
  await redis.quit();
  process.exit(0);
}

main().catch(e => { log('Fatal:', e.message); process.exit(1); });
