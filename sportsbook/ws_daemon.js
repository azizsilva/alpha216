'use strict';
/**
 * ws_daemon.js — alpina216 Real-Time Odds Daemon (odds-api.io)
 *
 * Strategy (budget-safe):
 *   A) WebSocket mode (if provider enables it): zero-cost push, instant.
 *   B) REST fallback (if WS 403):
 *      - Every EVENT_MS (60s): one sport's event list (scores/timers/teams)
 *        6 sports × 1 call = 6 calls/cycle = ~360/hr ← very cheap
 *      - Odds queue: 1 event every ODDS_INTERVAL_MS (8s)
 *        = 450/hr for odds, shared across all events
 *        147 events → full rotation every ~20 minutes
 *      Total: ~810/hr  (well under 5000/hr free limit)
 *
 *   C) on-demand odds: api.php fetches individual event odds when user opens
 *      match detail and Redis has no markets yet (instant for user, 1 call).
 *
 * ENV overrides:
 *   ODDS_API_KEY   WS_DISABLE=1   EVENT_MS=60000   ODDS_INTERVAL_MS=8000
 *
 * START:
 *   cd /var/www/public_html/sportsbook && npm install && node ws_daemon.js
 *   # or:  pm2 start ws_daemon.js --name alpina216-odds
 */

const WebSocket = require('ws');
const { createClient } = require('redis');

const API_KEY          = process.env.ODDS_API_KEY    || 'fbfb8d1a32e0f0a1b4dc55ef2b72abad19e86f1b9c37df1032464e25882e68f2';
const REDIS_URL        = process.env.REDIS_URL       || 'redis://127.0.0.1:6379';
const EVENT_MS         = parseInt(process.env.EVENT_MS        || '60000',  10); // event list cycle
const ODDS_INTERVAL_MS = parseInt(process.env.ODDS_INTERVAL_MS|| '8000',   10); // per-event odds gap
const WS_DISABLE       = process.env.WS_DISABLE === '1';
const BOOKMAKER        = 'Bet365';
const WS_URL           = 'wss://api.odds-api.io/v3/ws';
const WS_MARKETS       = 'ML,Spread,Totals,BTTS,DoubleChance,Corners,Cards,CorrectScore';

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

// In-memory store: { [id]: { meta, markets_raw, bookie } }
const store = {};

// Odds queue: array of event IDs waiting for odds fetch (background)
const oddsQueue = [];
let   oddsQueueSet = new Set(); // dedup
let   lastSeq = 0;
let   wsAuthFailed = false;

function log(...a) {
  process.stdout.write(`[ws_daemon ${new Date().toISOString().slice(0,19).replace('T',' ')}] ${a.join(' ')}\n`);
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

// ── Rate-limit guard ─────────────────────────────────────────────────────────
let rlUntil = 0, rlBackoff = 60;
function rateLimited()  { return Date.now() < rlUntil; }
function onRL(secs)     { rlBackoff = Math.min(rlBackoff * 2, 600); rlUntil = Date.now() + (secs || rlBackoff) * 1000; log(`Rate-limited — pause ${secs||rlBackoff}s`); }
function onRLOk()       { rlBackoff = Math.max(30, Math.floor(rlBackoff / 2)); }
function isRL(e)        { return /rate.limit|429|too many/i.test(String(e?.message || e)); }

// ── Redis ─────────────────────────────────────────────────────────────────────
const redis = createClient({ url: REDIS_URL });
redis.on('error', e => log('Redis error:', e.message));

// ── SDK factory ───────────────────────────────────────────────────────────────
function mkClient() { return new OddsAPIClient({ apiKey: API_KEY }); }
function closeClient(c) { try { c && c.close && c.close(); } catch(_){} }

// ── Score/period helpers ──────────────────────────────────────────────────────
function mapPeriod(p) {
  if (!p) return '1';
  const s = String(p).toUpperCase().replace(/[-_ ]/g,'');
  if (s === 'HT' || s === 'HALFTIME')    return 'HT';
  if (s === '1' || s === '1H' || s === 'FIRSTHALF')  return '1';
  if (s === '2' || s === '2H' || s === 'SECONDHALF') return '2';
  if (s === 'OT' || s === 'ET')           return 'OT';
  return '1';
}
function mapScore(sc) {
  if (!sc) return '';
  if (typeof sc === 'string') return sc;
  return `${sc.home ?? sc.h ?? 0}-${sc.away ?? sc.a ?? 0}`;
}
function normEv(ev, sportId) {
  return {
    id:          String(ev.id),
    sport_id:    sportId,
    time:        String(ev.starts_at || ev.start_time || ev.time || 0),
    time_status: ev.status === 'live' ? '1' : ev.status === 'finished' ? '3' : '0',
    league:      { id: String(ev.league_id||''), name: ev.league||ev.competition||ev.league_name||'' },
    home:        { id: String(ev.home_id  ||''), name: ev.home||ev.home_team||'' },
    away:        { id: String(ev.away_id  ||''), name: ev.away||ev.away_team||'' },
    ss:          mapScore(ev.score||ev.ss),
    timer: {
      tm: parseInt(ev.minute||ev.elapsed||0)||0,
      ts: parseInt(ev.second||0)||0,
      md: mapPeriod(ev.period||ev.half||ev.phase),
    },
    _source: 'oddsapi',
    _meta_ts: Date.now(),
  };
}

// ── Market builder (mirrors api.php oddsapi_build_markets) ───────────────────
function fmt(v) { return (Math.round(parseFloat(v)*100)/100).toFixed(2); }
function round2(v) { return Math.round(v*100)/100; }

function buildMarkets(markets) {
  const lo  = {};
  const md  = [];
  let ml_h=0, ml_x=0, ml_a=0;

  for (const mkt of (markets||[])) {
    const name = String(mkt.name||'').toUpperCase().replace(/[\s_\-]/g,'');
    const o    = (mkt.odds||[])[0]||{};

    if (['ML','1X2','MONEYLINE'].includes(name)) {
      ml_h = +(o.home||0); ml_x = +(o.draw||0); ml_a = +(o.away||0);
      if (ml_h>1) lo.h = ml_h;
      if (ml_x>1) lo.x = ml_x;
      if (ml_a>1) lo.a = ml_a;
      const sel = [];
      if (ml_h>1) sel.push({name:'1',   odds:fmt(ml_h), NA:'1'});
      if (ml_x>1) sel.push({name:'X',   odds:fmt(ml_x), NA:'X'});
      if (ml_a>1) sel.push({name:'2',   odds:fmt(ml_a), NA:'2'});
      if (sel.length) md.push({name:'1X2', selections:sel, is_open:true});
    }
    if (['TOTALS','OVERUNDER','GOALS','TOTALGOALS'].includes(name)) {
      const line = +(o.hdp??2.5), ov = +(o.over||0), un = +(o.under||0);
      lo.ou_line=line; lo.ou_over=ov; lo.ou_under=un;
      const sel = [];
      if (ov>1) sel.push({name:`Plus de ${line}`,  odds:fmt(ov), NA:`O ${line}`});
      if (un>1) sel.push({name:`Moins de ${line}`, odds:fmt(un), NA:`U ${line}`});
      if (sel.length) md.push({name:`Over/Under ${line}`, selections:sel, is_open:true});
    }
    if (['SPREAD','ASIANHANDICAP','HANDICAP'].includes(name)) {
      const hdp=+(o.hdp||0), hh=+(o.home||0), ah=+(o.away||0);
      const fh = hdp>=0?`+${hdp}`:`${hdp}`, fa = -hdp>=0?`+${-hdp}`:`${-hdp}`;
      const sel = [];
      if (hh>1) sel.push({name:`1 (${fh})`, odds:fmt(hh), NA:`H ${hdp}`});
      if (ah>1) sel.push({name:`2 (${fa})`, odds:fmt(ah), NA:`A ${-hdp}`});
      if (sel.length) md.push({name:'Handicap Asiatique', selections:sel, is_open:true});
    }
    if (name === 'DOUBLECHANCE') {
      const dc = {};
      for (const entry of (mkt.odds||[])) {
        const en = String(entry.name||'').toUpperCase().replace(/[\s_]/g,'');
        if (en==='1X'||en==='HOMEORDRAW') dc['1X'] = +(entry.odds||0);
        if (en==='12'||en==='HOMEORAWAY') dc['12'] = +(entry.odds||0);
        if (en==='X2'||en==='DRAWORAWAY')dc['X2'] = +(entry.odds||0);
      }
      const sel = [];
      if ((dc['1X']||0)>1) sel.push({name:'1X', odds:fmt(dc['1X']), NA:'1X'});
      if ((dc['12']||0)>1) sel.push({name:'12', odds:fmt(dc['12']), NA:'12'});
      if ((dc['X2']||0)>1) sel.push({name:'X2', odds:fmt(dc['X2']), NA:'X2'});
      if (sel.length) md.push({name:'Double chance', selections:sel, is_open:true});
    }
    if (['BTTS','BOTHTEAMSTOSCORE'].includes(name)) {
      const y=+(o.yes||o.home||0), n=+(o.no||o.away||0);
      const sel = [];
      if (y>1) sel.push({name:'Oui', odds:fmt(y), NA:'Yes'});
      if (n>1) sel.push({name:'Non', odds:fmt(n), NA:'No'});
      if (sel.length) md.push({name:'Les deux équipes qui marquent', selections:sel, is_open:true});
    }
    if (['CORNERS','TOTALCORNERS'].includes(name)) {
      const cl=+(o.hdp??9.5), co=+(o.over||0), cu=+(o.under||0);
      const sel = [];
      if (co>1) sel.push({name:`Plus de ${cl}`,  odds:fmt(co), NA:`CO ${cl}`});
      if (cu>1) sel.push({name:`Moins de ${cl}`, odds:fmt(cu), NA:`CU ${cl}`});
      if (sel.length) md.push({name:`Total des corners Plus/Moins ${cl}`, selections:sel, is_open:true});
    }
    if (['CARDS','TOTALCARDS','YELLOWCARDS'].includes(name)) {
      const yl=+(o.hdp??3.5), yo=+(o.over||0), yu=+(o.under||0);
      const sel = [];
      if (yo>1) sel.push({name:`Plus de ${yl}`,  odds:fmt(yo), NA:`YC ${yl}`});
      if (yu>1) sel.push({name:`Moins de ${yl}`, odds:fmt(yu), NA:`YC- ${yl}`});
      if (sel.length) md.push({name:`Cartons Plus/Moins ${yl}`, selections:sel, is_open:true});
    }
    if (['CORRECTSCORE','EXACTSCORE'].includes(name)) {
      const sel = [];
      for (const entry of (mkt.odds||[])) {
        const sc = String(entry.name||entry.score||'');
        const v  = +(entry.odds||0);
        if (v>1 && sc) sel.push({name:sc, odds:fmt(v), NA:sc});
      }
      if (sel.length) md.push({name:'Score exact', selections:sel, is_open:true});
    }
  }

  // Compute Double Chance from ML if not in raw data
  if (ml_h>1 && ml_x>1 && ml_a>1 && !md.some(m=>m.name==='Double chance')) {
    const p1=1/ml_h, px=1/ml_x, p2=1/ml_a;
    const sel = [];
    const dc1x=round2((1/(p1+px))*0.95), dc12=round2((1/(p1+p2))*0.95), dcx2=round2((1/(px+p2))*0.95);
    if (dc1x>1) sel.push({name:'1X', odds:fmt(dc1x), NA:'1X'});
    if (dc12>1) sel.push({name:'12', odds:fmt(dc12), NA:'12'});
    if (dcx2>1) sel.push({name:'X2', odds:fmt(dcx2), NA:'X2'});
    if (sel.length) {
      const pos = (md.findIndex(m=>m.name==='1X2')+1) || 0;
      md.splice(pos, 0, {name:'Double chance', selections:sel, is_open:true});
    }
  }

  return { live_odds: lo, md_markets: md };
}

// ── Redis write/remove ────────────────────────────────────────────────────────
async function writeToRedis(id) {
  const ev = store[id];
  if (!ev?.meta) return;
  const { live_odds, md_markets } = buildMarkets(ev.markets_raw || []);
  const match = {
    ...ev.meta,
    live_odds:  Object.keys(live_odds).length ? live_odds  : undefined,
    md_markets: md_markets.length             ? md_markets : undefined,
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

// ── STEP 1: Fetch event metadata for ONE sport ────────────────────────────────
// Only event list calls — 1 API call. Very cheap.
let sportCursor = 0;
async function fetchEventMetadata() {
  if (rateLimited()) { log(`Rate-limited (${Math.ceil((rlUntil-Date.now())/1000)}s left), skip event fetch`); return; }
  const sport = SPORTS[sportCursor % SPORTS.length];
  sportCursor++;
  const c = mkClient();
  try {
    const resp = await c.getLiveEvents(sport.slug);
    const arr  = Array.isArray(resp)       ? resp
                : Array.isArray(resp?.data) ? resp.data
                : Array.isArray(resp?.events)? resp.events : [];
    onRLOk();
    let newCount = 0, updCount = 0;
    for (const ev of arr) {
      const id = String(ev.id);
      if (!id || !ev.home) continue;
      const isNew = !store[id];
      if (!store[id]) store[id] = {};
      store[id].meta = normEv(ev, sport.id);
      await writeToRedis(id); // writes meta (md_markets will be empty until odds fetched)
      if (isNew) {
        // New event → add to odds queue for background processing
        if (!oddsQueueSet.has(id)) { oddsQueue.push(id); oddsQueueSet.add(id); newCount++; }
      } else { updCount++; }
    }
    log(`${sport.slug}: ${arr.length} events (${newCount} new queued for odds)`);
    // Remove events that disappeared from this sport
    const liveIds = new Set(arr.map(e => String(e.id)));
    for (const id of Object.keys(store)) {
      if (store[id]?.meta?.sport_id === sport.id && !liveIds.has(id)) {
        await removeFromRedis(id);
      }
    }
  } catch(e) {
    if (isRL(e)) { onRL(); return; }
    log(`Event fetch error (${sport.slug}):`, e.message);
  } finally { closeClient(c); }
}

// ── STEP 2: Background odds queue — 1 event every ODDS_INTERVAL_MS ───────────
// Processes events in rotation. api.php handles on-demand for the active event.
async function processOddsQueue() {
  if (rateLimited()) return;
  if (wsAuthFailed === false) return; // WS mode → odds come from WS push
  if (oddsQueue.length === 0) {
    // Re-queue all known events for next rotation
    for (const id of Object.keys(store)) {
      if (!oddsQueueSet.has(id)) { oddsQueue.push(id); oddsQueueSet.add(id); }
    }
    return;
  }
  const id = oddsQueue.shift();
  oddsQueueSet.delete(id);
  if (!store[id]?.meta) return; // event gone

  const c = mkClient();
  try {
    const resp = await c.getEventOdds({ eventId: id, bookmakers: BOOKMAKER });
    onRLOk();
    // Normalise response to market array
    let markets = [];
    if (Array.isArray(resp?.bookmakers?.[BOOKMAKER])) markets = resp.bookmakers[BOOKMAKER];
    else if (Array.isArray(resp)) markets = resp;
    else if (Array.isArray(resp?.data?.bookmakers?.[BOOKMAKER])) markets = resp.data.bookmakers[BOOKMAKER];
    if (markets.length) {
      store[id].markets_raw = markets;
      store[id].bookie      = BOOKMAKER;
      await writeToRedis(id);
    }
  } catch(e) {
    if (isRL(e)) { onRL(); oddsQueue.unshift(id); oddsQueueSet.add(id); return; }
    // Silently skip — api.php on-demand will fill the gap
  } finally { closeClient(c); }
}

// ── WebSocket mode ────────────────────────────────────────────────────────────
let wsInstance = null, wsReconn = null, wsAttempts = 0;

function buildWsUrl() {
  const slugs = SPORTS.map(s=>s.slug).join(',');
  let url = `${WS_URL}?apiKey=${API_KEY}`;
  url += `&markets=${encodeURIComponent(WS_MARKETS)}`;
  url += `&sport=${encodeURIComponent(slugs)}&status=live`;
  if (lastSeq > 0) url += `&lastSeq=${lastSeq}`;
  return url;
}

function startWebSocket() {
  if (wsReconn) { clearTimeout(wsReconn); wsReconn = null; }
  log(`Connecting WS${lastSeq>0?' lastSeq='+lastSeq:''}...`);
  const ws = new WebSocket(buildWsUrl());
  wsInstance = ws;
  let ping = null;

  ws.on('open', () => {
    log('WebSocket connected — push mode active (no REST odds polling needed).');
    wsAttempts = 0;
    ping = setInterval(() => { if (ws.readyState === WebSocket.OPEN) ws.ping(); }, 30000);
  });

  ws.on('message', async (raw) => {
    for (const line of raw.toString().trim().split('\n')) {
      if (!line.trim()) continue;
      try { await handleWsMsg(JSON.parse(line)); } catch(_){}
    }
  });

  ws.on('unexpected-response', (req, res) => {
    if (res.statusCode === 403 || res.statusCode === 401) {
      wsAuthFailed = true;
      log('WS 403 — WebSocket not enabled on this key. REST polling mode active.');
      ws.terminate();
    }
  });

  ws.on('error', e => { if (!wsAuthFailed) log('WS error:', e.message); });

  ws.on('close', () => {
    if (ping) { clearInterval(ping); ping = null; }
    if (wsAuthFailed) return;
    wsAttempts++;
    const delay = Math.min(Math.pow(2, wsAttempts-1)*1000, 30000);
    if (wsAttempts > 20) { wsAuthFailed = true; log('WS: too many failures, REST-only.'); return; }
    wsReconn = setTimeout(startWebSocket, delay);
  });
}

async function handleWsMsg(msg) {
  const { type, id, bookie, markets, seq } = msg;
  if (seq && seq > lastSeq) lastSeq = seq;
  switch (type) {
    case 'welcome':          log('WS welcome. Bookmakers:', (msg.bookmakers||[]).join(',')); break;
    case 'resync_required':  log('WS resync required'); lastSeq = 0; break;
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

// ── Prune stale entries ───────────────────────────────────────────────────────
async function pruneStale() {
  try {
    for (const id of await redis.sMembers(KEY_ALL)) {
      if (!await redis.exists(KEY_EV(id))) {
        await redis.sRem(KEY_SPORT(store[id]?.meta?.sport_id||'1'), id);
        await redis.sRem(KEY_ALL, id);
        delete store[id];
      }
    }
  } catch(_){}
}

// ── Main ──────────────────────────────────────────────────────────────────────
async function main() {
  log('='.repeat(60));
  log('alpina216 ws_daemon — odds-api.io');
  log(`Key: ${API_KEY.slice(0,8)}...${API_KEY.slice(-4)}`);
  log(`Events: every ${EVENT_MS/1000}s per sport | Odds queue: 1 event every ${ODDS_INTERVAL_MS/1000}s`);
  log(`Budget: ~${Math.round(SPORTS.length * 3600 / (EVENT_MS/1000))} event calls/hr + ~${Math.round(3600000/ODDS_INTERVAL_MS)} odds calls/hr`);
  log('='.repeat(60));

  await redis.connect();
  log('Redis connected.');

  // Initial scan — staggered 3s between sports to avoid burst
  log('Initial scan (staggered)...');
  for (let i = 0; i < SPORTS.length; i++) {
    await fetchEventMetadata();
    if (i < SPORTS.length - 1) await sleep(rateLimited() ? Math.max(0, rlUntil - Date.now()) + 1000 : 3000);
  }

  // Event metadata cycle (one sport per EVENT_MS)
  setInterval(fetchEventMetadata, EVENT_MS);

  // Odds background queue (only active in REST mode — WS mode doesn't need it)
  setInterval(processOddsQueue, ODDS_INTERVAL_MS);

  // Prune stale Redis keys every 5 minutes
  setInterval(pruneStale, 5 * 60_000);

  // Start WebSocket (auto-fallbacks to REST on 403)
  if (!WS_DISABLE) startWebSocket();
  else { wsAuthFailed = true; log('WS disabled by env — REST-only mode.'); }

  process.on('SIGINT',  shutdown);
  process.on('SIGTERM', shutdown);
}

async function shutdown() {
  log('Shutting down...');
  if (wsInstance) wsInstance.close();
  try { await redis.quit(); } catch(_){}
  process.exit(0);
}

main().catch(e => { log('Fatal:', e.message, e.stack); process.exit(1); });
