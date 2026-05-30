'use strict';
/**
 * ws_daemon.js — alpina216 Real-Time Odds Daemon
 * Uses odds-api.io WebSocket feed (official SDK pattern)
 *
 * Based on: https://github.com/odds-api-io/odds-api-node/blob/main/examples/websocket-feed.ts
 *
 * Flow:
 *   1. Optional REST pre-fetch (--prefetch): load snapshot via REST
 *   2. WebSocket connection with lastSeq tracking for replay on reconnect
 *   3. All updates written to Redis for api.php to serve
 *
 * START:
 *   cd /var/www/public_html/sportsbook
 *   npm install
 *   node ws_daemon.js --prefetch      ← recommended (snapshot + live)
 *   node ws_daemon.js                 ← WebSocket only
 *
 * PROCESS MANAGER:
 *   pm2 start ws_daemon.js --name alpina216-odds -- --prefetch
 */

const WebSocket = require('ws');
const { createClient } = require('redis');

const API_KEY    = process.env.ODDS_API_KEY || '8957223a4359087972aee3d805832e0dd264bff0e3c78b7733e5f8cbd45f7b2e';
const REDIS_URL  = process.env.REDIS_URL    || 'redis://127.0.0.1:6379';
const PREFETCH   = process.argv.includes('--prefetch') || process.env.PREFETCH === '1';

// ── WebSocket config (matches official SDK example exactly) ───────────────────
const WS_URL      = 'wss://api.odds-api.io/v3/ws';
const WS_MARKETS  = 'ML,Spread,Totals,BTTS,DoubleChance,Corners,Cards,CorrectScore';
const WS_SPORT    = 'football,basketball,tennis,volleyball,ice-hockey,handball';
const WS_STATUS   = 'live';
// Bookmakers configured in your odds-api.io account dashboard.
// Leave empty to use whatever is configured there, or set explicitly e.g. 'Bet365'
const BOOKMAKER   = process.env.BOOKMAKER || '';

// Sport slug → sport_id mapping for Redis keys
const SPORT_IDS = {
  football: '1', basketball: '18', tennis: '13',
  volleyball: '91', 'ice-hockey': '17', handball: '78',
};
const ALL_SPORTS = ['football','basketball','tennis','volleyball','ice-hockey','handball'];

let OddsAPIClient = null;
try { OddsAPIClient = require('odds-api-io').OddsAPIClient; }
catch(e) { console.error('[ws_daemon] odds-api-io not found — run: npm install'); process.exit(1); }

// ── Redis keys ────────────────────────────────────────────────────────────────
const KEY_EV    = id  => `sb:ev:${id}`;
const KEY_SPORT = sid => `sb:live:sport:${sid}`;
const KEY_ALL   = 'sb:live:all';
const KEY_TS    = 'sb:live:updated';

// ── In-memory store ───────────────────────────────────────────────────────────
const store = {};   // eventId → { meta, markets_raw, bookie, sport }

const redis = createClient({ url: REDIS_URL });
redis.on('error', e => log('Redis error:', e.message));

function log(...a) {
  process.stdout.write(`[ws_daemon ${new Date().toISOString().slice(0,19).replace('T',' ')}] ${a.join(' ')}\n`);
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

// ── Rate-limit guard (REST pre-fetch only) ────────────────────────────────────
let rlUntil = 0, rlBackoff = 30;
function rateLimited() { return Date.now() < rlUntil; }
function onRL()  { rlBackoff = Math.min(rlBackoff * 2, 120); rlUntil = Date.now() + rlBackoff * 1000; log(`Rate-limited — pause ${rlBackoff}s`); }
function onRLOk(){ rlBackoff = 30; }
function isRL(e) { return /rate.limit|429|too many/i.test(String(e?.message||e)); }

// ── Period helpers ────────────────────────────────────────────────────────────
function mapPeriod(p) {
  if (!p) return '1';
  const s = String(p).toUpperCase().replace(/[-_ ]/g,'');
  if (s==='HT'||s==='HALFTIME')   return 'HT';
  if (s==='1'||s==='1H'||s==='FIRSTHALF')  return '1';
  if (s==='2'||s==='2H'||s==='SECONDHALF') return '2';
  if (s==='OT'||s==='ET')         return 'OT';
  return '1';
}
function mapScore(sc) {
  if (!sc) return '';
  if (typeof sc === 'string') return sc;
  return `${sc.home??sc.h??0}-${sc.away??sc.a??0}`;
}
function normEv(ev, sportSlug) {
  const sid = SPORT_IDS[sportSlug] || '1';
  return {
    id:          String(ev.id),
    sport_id:    sid,
    time:        String(ev.starts_at||ev.start_time||ev.time||0),
    time_status: ev.status==='live'?'1': ev.status==='finished'?'3':'0',
    league:      { id: String(ev.league_id||''), name: ev.league||ev.competition||ev.league_name||'' },
    home:        { id: String(ev.home_id||''), name: ev.home||ev.home_team||'' },
    away:        { id: String(ev.away_id||''), name: ev.away||ev.away_team||'' },
    ss:          mapScore(ev.score||ev.ss),
    timer:       { tm: parseInt(ev.minute||ev.elapsed||0)||0, ts: parseInt(ev.second||0)||0, md: mapPeriod(ev.period||ev.half||ev.phase) },
    _source:     'oddsapi',
    _ts:         Date.now(),
  };
}

// ── Market builder ────────────────────────────────────────────────────────────
function fmt(v) { return (Math.round(parseFloat(v)*100)/100).toFixed(2); }
function r2(v)  { return Math.round(v*100)/100; }

function buildMarkets(markets) {
  const lo = {}, md = [];
  let ml_h=0, ml_x=0, ml_a=0;

  for (const mkt of (markets||[])) {
    const name = String(mkt.name||'').toUpperCase().replace(/[\s_\-]/g,'');
    const o    = (mkt.odds||[])[0]||{};

    if (['ML','1X2','MONEYLINE'].includes(name)) {
      ml_h=+(o.home||0); ml_x=+(o.draw||0); ml_a=+(o.away||0);
      if (ml_h>1) lo.h=ml_h; if (ml_x>1) lo.x=ml_x; if (ml_a>1) lo.a=ml_a;
      const sel=[];
      if (ml_h>1) sel.push({name:'1',   odds:fmt(ml_h),NA:'1'});
      if (ml_x>1) sel.push({name:'X',   odds:fmt(ml_x),NA:'X'});
      if (ml_a>1) sel.push({name:'2',   odds:fmt(ml_a),NA:'2'});
      if (sel.length) md.push({name:'1X2',selections:sel,is_open:true});
    }
    if (['TOTALS','OVERUNDER','GOALS','TOTALGOALS'].includes(name)) {
      const line=+(o.hdp??2.5),ov=+(o.over||0),un=+(o.under||0);
      lo.ou_line=line; lo.ou_over=ov; lo.ou_under=un;
      const sel=[];
      if (ov>1) sel.push({name:`Plus de ${line}`,  odds:fmt(ov),NA:`O ${line}`});
      if (un>1) sel.push({name:`Moins de ${line}`, odds:fmt(un),NA:`U ${line}`});
      if (sel.length) md.push({name:`Over/Under ${line}`,selections:sel,is_open:true});
    }
    if (['SPREAD','ASIANHANDICAP','HANDICAP'].includes(name)) {
      const hdp=+(o.hdp||0),hh=+(o.home||0),ah=+(o.away||0);
      const fh=hdp>=0?`+${hdp}`:`${hdp}`,fa=-hdp>=0?`+${-hdp}`:`${-hdp}`;
      const sel=[];
      if (hh>1) sel.push({name:`1 (${fh})`,odds:fmt(hh),NA:`H ${hdp}`});
      if (ah>1) sel.push({name:`2 (${fa})`,odds:fmt(ah),NA:`A ${-hdp}`});
      if (sel.length) md.push({name:'Handicap Asiatique',selections:sel,is_open:true});
    }
    if (name==='DOUBLECHANCE') {
      const dc={};
      for (const e of (mkt.odds||[])) {
        const en=String(e.name||'').toUpperCase().replace(/[\s_]/g,'');
        if (en==='1X'||en==='HOMEORDRAW')  dc['1X']=+(e.odds||0);
        if (en==='12'||en==='HOMEORAWAY')  dc['12']=+(e.odds||0);
        if (en==='X2'||en==='DRAWORAWAY') dc['X2']=+(e.odds||0);
      }
      const sel=[];
      if ((dc['1X']||0)>1) sel.push({name:'1X',odds:fmt(dc['1X']),NA:'1X'});
      if ((dc['12']||0)>1) sel.push({name:'12',odds:fmt(dc['12']),NA:'12'});
      if ((dc['X2']||0)>1) sel.push({name:'X2',odds:fmt(dc['X2']),NA:'X2'});
      if (sel.length) md.push({name:'Double chance',selections:sel,is_open:true});
    }
    if (['BTTS','BOTHTEAMSTOSCORE'].includes(name)) {
      const y=+(o.yes||o.home||0),n=+(o.no||o.away||0);
      const sel=[];
      if (y>1) sel.push({name:'Oui',odds:fmt(y),NA:'Yes'});
      if (n>1) sel.push({name:'Non',odds:fmt(n),NA:'No'});
      if (sel.length) md.push({name:'Les deux équipes qui marquent',selections:sel,is_open:true});
    }
    if (['CORNERS','TOTALCORNERS'].includes(name)) {
      const cl=+(o.hdp??9.5),co=+(o.over||0),cu=+(o.under||0);
      const sel=[];
      if (co>1) sel.push({name:`Plus de ${cl}`,  odds:fmt(co),NA:`CO ${cl}`});
      if (cu>1) sel.push({name:`Moins de ${cl}`, odds:fmt(cu),NA:`CU ${cl}`});
      if (sel.length) md.push({name:`Total des corners Plus/Moins ${cl}`,selections:sel,is_open:true});
    }
    if (['CARDS','TOTALCARDS','YELLOWCARDS'].includes(name)) {
      const yl=+(o.hdp??3.5),yo=+(o.over||0),yu=+(o.under||0);
      const sel=[];
      if (yo>1) sel.push({name:`Plus de ${yl}`,  odds:fmt(yo),NA:`YC ${yl}`});
      if (yu>1) sel.push({name:`Moins de ${yl}`, odds:fmt(yu),NA:`YC- ${yl}`});
      if (sel.length) md.push({name:`Cartons Plus/Moins ${yl}`,selections:sel,is_open:true});
    }
    if (['CORRECTSCORE','EXACTSCORE'].includes(name)) {
      const sel=[];
      for (const e of (mkt.odds||[])) {
        const sc=String(e.name||e.score||''),v=+(e.odds||0);
        if (v>1&&sc) sel.push({name:sc,odds:fmt(v),NA:sc});
      }
      if (sel.length) md.push({name:'Score exact',selections:sel,is_open:true});
    }
  }

  // Compute Double Chance from ML if not in data
  if (ml_h>1&&ml_x>1&&ml_a>1&&!md.some(m=>m.name==='Double chance')) {
    const p1=1/ml_h,px=1/ml_x,p2=1/ml_a;
    const sel=[];
    const dc1x=r2((1/(p1+px))*0.95),dc12=r2((1/(p1+p2))*0.95),dcx2=r2((1/(px+p2))*0.95);
    if (dc1x>1) sel.push({name:'1X',odds:fmt(dc1x),NA:'1X'});
    if (dc12>1) sel.push({name:'12',odds:fmt(dc12),NA:'12'});
    if (dcx2>1) sel.push({name:'X2',odds:fmt(dcx2),NA:'X2'});
    if (sel.length) {
      const pos=(md.findIndex(m=>m.name==='1X2')+1)||0;
      md.splice(pos,0,{name:'Double chance',selections:sel,is_open:true});
    }
  }
  return { live_odds: lo, md_markets: md };
}

// ── Redis write/remove ────────────────────────────────────────────────────────
async function writeToRedis(id) {
  const ev = store[id];
  if (!ev?.meta) return;
  const { live_odds, md_markets } = buildMarkets(ev.markets_raw||[]);
  const match = {
    ...ev.meta,
    live_odds:  Object.keys(live_odds).length ? live_odds  : undefined,
    md_markets: md_markets.length             ? md_markets : undefined,
    _bookie:    ev.bookie,
    _updated:   Date.now(),
  };
  const sid = ev.meta.sport_id||'1';
  try {
    await redis.set(KEY_EV(id), JSON.stringify(match), { EX: 600 });
    await redis.sAdd(KEY_SPORT(sid), id);
    await redis.sAdd(KEY_ALL, id);
    await redis.set(KEY_TS, String(Date.now()));
  } catch(e) { log('Redis write error:', e.message); }
}

async function removeFromRedis(id) {
  const sid = store[id]?.meta?.sport_id||'1';
  try {
    await redis.del(KEY_EV(id));
    await redis.sRem(KEY_SPORT(sid), id);
    await redis.sRem(KEY_ALL, id);
  } catch(_){}
  delete store[id];
}

// ── REST pre-fetch (initial snapshot) ────────────────────────────────────────
// Mirrors the official SDK example's initialFetch() exactly.
async function restPrefetch() {
  log('='.repeat(55));
  log('REST pre-fetch: loading current live events + odds...');
  log('='.repeat(55));
  const client = new OddsAPIClient({ apiKey: API_KEY });
  let totalEvents = 0, totalWithOdds = 0;

  for (const sport of ALL_SPORTS) {
    if (rateLimited()) {
      const wait = rlUntil - Date.now() + 500;
      log(`Waiting ${Math.ceil(wait/1000)}s (rate-limit) before ${sport}...`);
      await sleep(wait);
    }
    try {
      const resp = await client.getLiveEvents(sport);
      const arr  = Array.isArray(resp)?resp:(Array.isArray(resp?.data)?resp.data:(Array.isArray(resp?.events)?resp.events:[]));
      onRLOk();
      if (!arr.length) { log(`${sport}: 0 live events`); continue; }
      totalEvents += arr.length;
      log(`${sport}: ${arr.length} events — fetching odds...`);

      // Fetch odds for each event (1 per event, staggered 1s)
      for (const ev of arr) {
        const id = String(ev.id);
        if (!id || !ev.home) continue;
        if (!store[id]) store[id] = {};
        store[id].meta  = normEv(ev, sport);
        store[id].sport = sport;

        if (rateLimited()) {
          log('Rate-limited mid-fetch, will resume after backoff...');
          await sleep(rlUntil - Date.now() + 500);
        }
        try {
          const oddsParams = { eventId: id };
          if (BOOKMAKER) oddsParams.bookmakers = BOOKMAKER;
          const oddsResp = await client.getEventOdds(oddsParams);
          onRLOk();
          const bkData  = oddsResp?.bookmakers || oddsResp?.data?.bookmakers || {};
          const markets  = bkData[BOOKMAKER] || Object.values(bkData)[0] || [];
          if (markets.length) {
            store[id].markets_raw = markets;
            store[id].bookie      = BOOKMAKER;
            totalWithOdds++;
          }
          await writeToRedis(id);
        } catch(e) {
          if (isRL(e)) { onRL(); await sleep(rlUntil - Date.now() + 200); }
          else { await writeToRedis(id); } // write meta even without odds
        }
        await sleep(1200); // 1.2s gap between odds calls ≈ 50 calls/min ≈ 3000/hr
      }
    } catch(e) {
      if (isRL(e)) { onRL(); continue; }
      log(`Error fetching ${sport}:`, e.message);
    }
    await sleep(3000); // 3s gap between sports
  }
  log(`Pre-fetch done: ${totalEvents} events, ${totalWithOdds} with odds in Redis.`);
}

// ── WebSocket client (mirrors official SDK OddsWebSocketClient exactly) ───────
let ws = null;
let lastSeq = 0;
let wsReconnectAttempts = 0;
let wsMaxAttempts = 20;
let wsShouldReconnect = true;
let wsReconnTimeout = null;
let wsPingInterval = null;

function buildWsUrl() {
  // Exact format from official SDK example — no encodeURIComponent on comma lists
  let url = `${WS_URL}?apiKey=${API_KEY}`;
  url += `&markets=${WS_MARKETS}`;
  url += `&sport=${WS_SPORT}`;
  url += `&status=${WS_STATUS}`;
  if (lastSeq > 0) url += `&lastSeq=${lastSeq}`;
  return url;
}

function startWs() {
  if (wsReconnTimeout) { clearTimeout(wsReconnTimeout); wsReconnTimeout = null; }
  const url = buildWsUrl();
  log(`Connecting WS${lastSeq>0?' lastSeq='+lastSeq:''}...`);
  ws = new WebSocket(url);

  ws.on('open', () => {
    log('WebSocket CONNECTED — real-time push active.');
    wsReconnectAttempts = 0;
    wsPingInterval = setInterval(() => {
      if (ws?.readyState === WebSocket.OPEN) ws.ping();
    }, 30000);
  });

  ws.on('message', async (raw) => {
    const lines = raw.toString().trim().split('\n');
    for (const line of lines) {
      if (!line.trim()) continue;
      try { await handleWsMessage(JSON.parse(line)); } catch(_){}
    }
  });

  ws.on('error', e => log('WS error:', e.message));

  ws.on('unexpected-response', (req, res) => {
    log(`WS HTTP ${res.statusCode} — `, res.statusCode===403
      ? 'WebSocket access not enabled for this key (ask provider to activate).'
      : 'unexpected response');
    if (res.statusCode===403||res.statusCode===401) {
      wsShouldReconnect = false; // Don't retry auth errors
    }
  });

  ws.on('close', (code) => {
    if (wsPingInterval) { clearInterval(wsPingInterval); wsPingInterval=null; }
    if (!wsShouldReconnect) {
      log('WS permanently closed (auth error). Daemon continues in metadata-only mode.');
      log('→ api.php on-demand odds fetch will serve markets to users.');
      return;
    }
    wsReconnectAttempts++;
    if (wsReconnectAttempts > wsMaxAttempts) {
      log('Max WS reconnects reached. Metadata-only mode.');
      return;
    }
    // Exponential backoff: 1s, 2s, 4s… cap 30s (from official SDK)
    const delay = Math.min(Math.pow(2, wsReconnectAttempts-1)*1000, 30000);
    log(`WS closed (${code}). Reconnect in ${delay/1000}s (attempt ${wsReconnectAttempts}, lastSeq=${lastSeq})`);
    wsReconnTimeout = setTimeout(startWs, delay);
  });
}

async function handleWsMessage(data) {
  if (data.seq && data.seq > lastSeq) lastSeq = data.seq;

  switch (data.type) {
    case 'welcome': {
      const bks = data.bookmakers || [];
      log('WS welcome. Bookmakers:', bks.length ? bks.join(',') : '(none configured — go to odds-api.io → Bookmakers tab)');
      log('Sports:', (data.sport_filter||[]).join(','), '| Status:', data.status_filter||'live');
      if (data.warning) log('⚠ Warning:', data.warning);
      if (bks.length === 0) {
        log('ACTION REQUIRED: Go to https://odds-api.io → Bookmakers tab → select your bookmakers → save');
      }
      if (lastSeq > 0) log(`Replay: lastSeq=${lastSeq} — catching up missed updates...`);
      break;
    }

    case 'resync_required':
      log(`WS resync required: ${data.reason}. Rebuilding from REST...`);
      lastSeq = 0;
      if (PREFETCH) await restPrefetch();
      break;

    case 'created':
    case 'updated': {
      const id = String(data.id);
      if (!store[id]) store[id] = {};
      store[id].markets_raw = data.markets || [];
      store[id].bookie      = data.bookie || BOOKMAKER || 'unknown';
      await writeToRedis(id);
      // Log first few updates so user can confirm data is flowing
      if (Math.random() < 0.05) {
        const mkts = (data.markets||[]).map(m=>m.name).join(',');
        log(`WS ${data.type}: event ${id} | ${store[id].bookie} | markets: ${mkts||'none'}`);
      }
      break;
    }

    case 'deleted':
      await removeFromRedis(String(data.id));
      break;

    case 'no_markets':
      // Event exists but no markets available — keep meta, clear odds
      break;
  }
}

// ── Periodic score/meta refresh (REST, cheap — no odds) ──────────────────────
// Even in WS mode: scores/timers come from REST getLiveEvents (WS gives odds only).
let metaSportCursor = 0;
async function refreshMeta() {
  if (rateLimited()) return;
  const sport = ALL_SPORTS[metaSportCursor % ALL_SPORTS.length];
  metaSportCursor++;
  const client = new OddsAPIClient({ apiKey: API_KEY });
  try {
    const resp = await client.getLiveEvents(sport);
    const arr  = Array.isArray(resp)?resp:(Array.isArray(resp?.data)?resp.data:[]);
    onRLOk();
    for (const ev of arr) {
      const id = String(ev.id);
      if (!id||!ev.home) continue;
      if (!store[id]) store[id] = {};
      store[id].meta  = normEv(ev, sport);
      store[id].sport = sport;
      // Write to Redis immediately (updates score/timer; keeps existing markets)
      await writeToRedis(id);
    }
    // Remove events that vanished from this sport
    const liveIds = new Set(arr.map(e=>String(e.id)));
    for (const id of Object.keys(store)) {
      if (store[id]?.sport===sport && !liveIds.has(id)) await removeFromRedis(id);
    }
    if (arr.length) log(`Meta refresh ${sport}: ${arr.length} events`);
  } catch(e) {
    if (isRL(e)) onRL();
  } finally { try { client.close&&client.close(); } catch(_){} }
}

// ── Prune stale Redis entries ─────────────────────────────────────────────────
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
  log('='.repeat(55));
  log('alpina216 ws_daemon — odds-api.io');
  log('Key:', API_KEY.slice(0,8)+'...'+API_KEY.slice(-4));
  log('Mode:', PREFETCH ? 'REST snapshot + WebSocket' : 'WebSocket only');
  log('='.repeat(55));

  await redis.connect();
  log('Redis connected.');

  // Step 1: Start WebSocket IMMEDIATELY — no REST quota consumed
  // WebSocket push gives us real-time odds without any API rate limits
  log('Starting WebSocket...');
  startWs();

  // Step 2: REST snapshot runs in background (non-blocking)
  // If rate-limited it will wait and retry, but WebSocket is already live
  if (PREFETCH) {
    setTimeout(() => {
      restPrefetch().catch(e => log('Pre-fetch error:', e.message));
    }, 5000); // 5s delay so WS can connect first
  }

  // Step 3: Score/meta refresh every 90s cycling through sports
  // (WS gives odds but not always live scores; REST fills the gap)
  setInterval(refreshMeta, 90_000);

  // Step 4: Prune expired entries every 5 minutes
  setInterval(pruneStale, 5 * 60_000);

  process.on('SIGINT',  shutdown);
  process.on('SIGTERM', shutdown);
}

async function shutdown() {
  log('Shutting down...');
  wsShouldReconnect = false;
  if (ws) ws.close();
  try { await redis.quit(); } catch(_){}
  process.exit(0);
}

main().catch(e => { log('Fatal:', e.message); process.exit(1); });
