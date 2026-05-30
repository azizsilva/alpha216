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

// ── WebSocket config ──────────────────────────────────────────────────────────
const WS_URL      = 'wss://api.odds-api.io/v3/ws';
// Market names MUST match odds-api.io's exact naming (see docs.odds-api.io/guides/fetching-odds).
// Confirmed names: ML, Spread, Totals, Both Teams to Score, Correct Score.
// Others requested speculatively — daemon logs welcome.market_filter to show which are accepted.
const WS_MARKETS_ARR = [
  'ML',
  'Spread',
  'Totals',
  'Both Teams to Score',
  'Correct Score',
  'Double Chance',
  'Asian Handicap',
  'Draw No Bet',
  'Odd/Even',
  'Corners',
  'Cards',
  'Team Totals',
  'Half Time Result',
];
const WS_MARKETS  = WS_MARKETS_ARR.join(',');
const WS_SPORT    = 'football,basketball,tennis,volleyball,ice-hockey,handball';
// status MUST be a single value per docs: 'live' or 'prematch' (NOT 'live,upcoming')
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
// UI (app.js) convention for the timer `md` flag:
//   md === '1' → "Mi-temps" (halftime BREAK)   md === '2' → "Pause"
//   md === '3' → "Prolongation" (extra time)   md === ''  → derive from minute (tm)
// So we only set md to a non-empty flag for break states; during active play
// we return '' and let the UI compute "45'" / "1ère mi-temps" from the minute.
function mapPeriod(p) {
  if (!p) return '';
  const s = String(p).toUpperCase().replace(/[-_ ]/g,'');
  if (s==='HT'||s==='HALFTIME'||s==='HALF-TIME'||s==='BREAK') return '1'; // halftime → Mi-temps
  if (s==='PAUSE')                return '2';
  if (s==='OT'||s==='ET'||s==='EXTRATIME'||s==='OVERTIME') return '3';
  return ''; // first/second half or unknown → derive from minute
}
// Extract a string name from a field that could be a string, number, or object
function extractName(...candidates) {
  for (const v of candidates) {
    if (!v && v !== 0) continue;
    if (typeof v === 'string' && v.trim()) return v.trim();
    if (typeof v === 'number') return String(v);
    if (typeof v === 'object') {
      const n = v.name || v.title || v.long_name || v.short_name || '';
      if (n) return String(n).trim();
    }
  }
  return '';
}

function mapScore(sc) {
  if (!sc && sc !== 0) return '';
  if (typeof sc === 'string') return sc;
  if (typeof sc === 'object') {
    const h = sc.home ?? sc.h ?? null;
    const a = sc.away ?? sc.a ?? null;
    if (h === null && a === null) return '';
    return `${h ?? 0}-${a ?? 0}`;
  }
  return '';
}
function normEv(ev, sportSlug) {
  const sid = SPORT_IDS[sportSlug] || '1';
  return {
    id:          String(ev.id),
    sport_id:    sid,
    time:        String(ev.starts_at||ev.start_time||ev.time||0),
    time_status: ev.status==='live'?'1': ev.status==='finished'?'3':'0',
    league:      { id: String(ev.league_id||''), name: extractName(ev.league_name, ev.competition, ev.league) },
    home:        { id: String(ev.home_id||''), name: extractName(ev.home_team, ev.home) },
    away:        { id: String(ev.away_id||''), name: extractName(ev.away_team, ev.away) },
    ss:          mapScore(ev.scores||ev.score||ev.ss),
    timer:       { tm: parseInt(ev.minute??ev.elapsed??ev.time_min??ev.clock??0)||0, ts: parseInt(ev.second??ev.seconds??0)||0, md: mapPeriod(ev.period||ev.half||ev.phase||ev.status_more) },
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
    const name = String(mkt.name||'').toUpperCase().replace(/[\s_\-\/]/g,'');
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
      // Build one Over/Under market per line (multiple lines in odds array)
      let firstLine = true;
      for (const entry of (mkt.odds||[])) {
        const line=+(entry.hdp??2.5),ov=+(entry.over||0),un=+(entry.under||0);
        if (ov<1.01&&un<1.01) continue;
        if (firstLine) { lo.ou_line=line; lo.ou_over=ov; lo.ou_under=un; firstLine=false; }
        const sel=[];
        if (ov>1) sel.push({name:`Plus de ${line}`,  odds:fmt(ov),NA:`O ${line}`});
        if (un>1) sel.push({name:`Moins de ${line}`, odds:fmt(un),NA:`U ${line}`});
        if (sel.length) md.push({name:`Over/Under ${line}`,selections:sel,is_open:true});
      }
    }
    if (['SPREAD','ASIANHANDICAP','HANDICAP'].includes(name)) {
      // Build ONE market per handicap line (multiple lines in odds array)
      for (const entry of (mkt.odds||[])) {
        const hdp=+(entry.hdp||0),hh=+(entry.home||0),ah=+(entry.away||0);
        if (hh<1.01&&ah<1.01) continue;
        const fh=hdp>=0?`+${hdp}`:`${hdp}`,fa=-hdp>=0?`+${-hdp}`:`${-hdp}`;
        const sel=[];
        if (hh>1) sel.push({name:`1 (${fh})`,odds:fmt(hh),NA:`H ${hdp}`});
        if (ah>1) sel.push({name:`2 (${fa})`,odds:fmt(ah),NA:`A ${-hdp}`});
        if (sel.length) md.push({name:`Handicap Asiatique ${fh}`,selections:sel,is_open:true});
      }
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
    // Team Totals (Total équipe 1 / 2)
    if (['TEAMTOTALS','HOMETOTALS','AWAYTOTALS'].includes(name)) {
      const line=+(o.hdp??1.5),ov=+(o.over||0),un=+(o.under||0);
      const sideLabel = name==='AWAYTOTALS' ? 'équipe 2' : 'équipe 1';
      const sel=[];
      if (ov>1) sel.push({name:`Plus de ${line}`,  odds:fmt(ov),NA:`TT O ${line}`});
      if (un>1) sel.push({name:`Moins de ${line}`, odds:fmt(un),NA:`TT U ${line}`});
      if (sel.length) md.push({name:`Total ${sideLabel} Plus/Moins ${line}`,selections:sel,is_open:true});
    }
    // Draw No Bet
    if (name==='DRAWNOBET') {
      const hh=+(o.home||0),ah=+(o.away||0);
      const sel=[];
      if (hh>1) sel.push({name:'1',odds:fmt(hh),NA:'DNB1'});
      if (ah>1) sel.push({name:'2',odds:fmt(ah),NA:'DNB2'});
      if (sel.length) md.push({name:'Remboursé si nul',selections:sel,is_open:true});
    }
    // Odd/Even
    if (['ODDEVEN','GOALSODDEVEN'].includes(name)) {
      const odd=+(o.odd||o.home||0),even=+(o.even||o.away||0);
      const sel=[];
      if (odd>1)  sel.push({name:'Impair',odds:fmt(odd),NA:'Odd'});
      if (even>1) sel.push({name:'Pair',  odds:fmt(even),NA:'Even'});
      if (sel.length) md.push({name:'Pair/Impair',selections:sel,is_open:true});
    }
    // Half Time Result (1ère mi-temps 1X2)
    if (['HALFTIMERESULT','HALFTIME1X2'].includes(name)) {
      const hh=+(o.home||0),hx=+(o.draw||0),ha=+(o.away||0);
      const sel=[];
      if (hh>1) sel.push({name:'1',odds:fmt(hh),NA:'HT1'});
      if (hx>1) sel.push({name:'X',odds:fmt(hx),NA:'HTX'});
      if (ha>1) sel.push({name:'2',odds:fmt(ha),NA:'HT2'});
      if (sel.length) md.push({name:'1ère mi-temps - 1x2',selections:sel,is_open:true});
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
  // Skip if we have neither meta nor markets — nothing useful to store
  if (!ev?.meta && !(ev?.markets_raw?.length)) return;

  // ALWAYS read existing Redis entry to preserve fields we don't have in memory.
  // Critical: refreshMeta has meta but no markets; WS push has markets but no meta.
  // We must merge both to avoid overwriting each other.
  let existing = null;
  try {
    const raw = await redis.get(KEY_EV(id));
    if (raw) existing = JSON.parse(raw);
  } catch(_){}

  const { live_odds, md_markets } = buildMarkets(ev.markets_raw || existing?.markets_raw_cache || []);

  // sport_id: prefer fresh meta, then existing, then default football
  const sid = ev.meta?.sport_id || existing?.sport_id || '1';

  const match = {
    // 1. start with existing Redis data (safe fallback for all fields)
    ...(existing || {}),
    // 2. overwrite with fresh meta if available (score, timer, home/away names)
    ...(ev.meta  || {}),
    // 3. always set computed fields
    live_odds:  Object.keys(live_odds).length ? live_odds  : (existing?.live_odds  || undefined),
    md_markets: md_markets.length             ? md_markets : (existing?.md_markets || undefined),
    _bookie:    ev.bookie || existing?._bookie,
    _updated:   Date.now(),
  };

  // Persist a lightweight markets cache so the next refreshMeta doesn't lose them
  if (ev.markets_raw?.length) match.markets_raw_cache = ev.markets_raw;

  // Ensure id is always present
  if (!match.id) match.id = id;

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
        if (!id || !(ev.home || ev.home_team)) continue;
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
  // Use URLSearchParams so market names with spaces (e.g. "Both Teams to Score")
  // are correctly percent-encoded — otherwise the server drops them silently.
  const params = new URLSearchParams();
  params.set('apiKey', API_KEY);
  params.set('markets', WS_MARKETS);
  params.set('sport', WS_SPORT);
  params.set('status', WS_STATUS);
  if (lastSeq > 0) params.set('lastSeq', String(lastSeq));
  return `${WS_URL}?${params.toString()}`;
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
      // CRITICAL: log which markets the server ACCEPTED vs what we requested.
      const accepted = (data.market_filter || []).map(m => String(m).toUpperCase());
      log('MARKETS requested:', WS_MARKETS_ARR.join(' | '));
      log('MARKETS accepted :', accepted.join(' | ') || '(none!)');
      const dropped = WS_MARKETS_ARR.filter(m => !accepted.includes(m.toUpperCase().replace(/\s+/g,' ')))
        .filter(m => !accepted.some(a => a.replace(/[\s/]/g,'') === m.toUpperCase().replace(/[\s/]/g,'')));
      if (dropped.length) log('MARKETS DROPPED  :', dropped.join(' | '), '(wrong name or not available for your bookmakers)');
      if (data.warning) log('⚠ Warning:', data.warning);
      if (bks.length === 0) {
        log('ACTION REQUIRED: Go to https://odds-api.io → Bookmakers tab → select your bookmakers → save');
      }
      if (lastSeq > 0) log(`Replay: lastSeq=${lastSeq} — catching up missed updates...`);
      // Trigger immediate meta refresh on first connect so Redis gets home/away right away
      if (wsReconnectAttempts <= 1) {
        log('WS connected — triggering immediate meta refresh to populate Redis...');
        setTimeout(() => refreshMeta().catch(e => log('Meta refresh error:', e.message)), 200);
      }
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

      // Populate meta from WS data (home/away/league/score/timer included in created events)
      if (data.home || data.home_team || data.away || data.away_team) {
        const sportSlug = data.sport || store[id]?.sport || 'football';
        store[id].meta  = normEv(data, sportSlug);
        store[id].sport = sportSlug;
      } else if (!store[id].meta && (data.league || data.competition)) {
        // Partial update: at least capture league
        if (!store[id].meta) store[id].meta = { id };
        if (data.league) store[id].meta.league = { id: '', name: extractName(data.league_name, data.competition, data.league) };
      }

      // Update odds/markets from WS
      if (data.markets && data.markets.length > 0) {
        store[id].markets_raw = data.markets;
        store[id].bookie      = data.bookie || BOOKMAKER || 'Bet365';
      }

      // Update live score/timer if WS sends them
      if (store[id].meta) {
        if (data.score || data.ss) store[id].meta.ss = mapScore(data.score || data.ss);
        if (data.minute !== undefined) {
          store[id].meta.timer = {
            tm: parseInt(data.minute || 0) || 0,
            ts: parseInt(data.second || 0) || 0,
            md: mapPeriod(data.period || data.half || data.phase),
          };
        }
        if (data.status) store[id].meta.time_status = data.status === 'live' ? '1' : data.status === 'finished' ? '3' : '0';
      }

      await writeToRedis(id);

      // Log a sample of updates to confirm data is flowing
      if (Math.random() < 0.05) {
        const mkts = (data.markets||[]).map(m=>m.name).join(',');
        const hn   = store[id]?.meta?.home?.name || '?';
        const an   = store[id]?.meta?.away?.name || '?';
        log(`WS ${data.type}: ${hn} v ${an} [${id}] | ${store[id].bookie} | markets: ${mkts||'none'}`);
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

// ── Live score/timer refresh for ONE sport (REST, cheap — no odds) ───────────
// Scores + timer are NOT delivered over WebSocket (it only carries odds), so
// we must poll /events/live to keep score/minute fresh. Football is polled
// frequently (see fast timer in main); other sports cycle slower.
async function refreshSportLive(sport) {
  if (rateLimited()) return;
  const client = new OddsAPIClient({ apiKey: API_KEY });
  const liveIds = new Set();
  try {
    const resp = await client.getLiveEvents(sport);
    const arr  = Array.isArray(resp)?resp:(Array.isArray(resp?.data)?resp.data:[]);
    onRLOk();
    for (const ev of arr) {
      const id = String(ev.id);
      if (!id || !(ev.home || ev.home_team)) continue;
      liveIds.add(id);
      if (!store[id]) store[id] = {};
      store[id].meta  = normEv(ev, sport);
      store[id].sport = sport;
      await writeToRedis(id);
    }
    if (arr.length) log(`Live refresh ${sport}: ${arr.length} events`);
    // Remove live events that vanished (kept upcoming untouched)
    for (const id of Object.keys(store)) {
      if (store[id]?.sport===sport && store[id]?.meta?.time_status==='1' && !liveIds.has(id)) {
        await removeFromRedis(id);
      }
    }
  } catch(e) {
    if (isRL(e)) onRL();
  } finally { try { client.close&&client.close(); } catch(_){} }
}

// ── Upcoming (prematch) refresh for ONE sport — runs in the slow cycle ───────
async function refreshSportUpcoming(sport) {
  if (rateLimited()) return;
  const client = new OddsAPIClient({ apiKey: API_KEY });
  try {
    const uResp = await client.getUpcomingEvents(sport);
    const uArr  = Array.isArray(uResp)?uResp:(Array.isArray(uResp?.data)?uResp.data:[]);
    onRLOk();
    let uCount = 0;
    for (const ev of uArr) {
      const id = String(ev.id);
      if (!id || !(ev.home || ev.home_team)) continue;
      if (store[id] && store[id]?.meta?.time_status === '1') continue; // already live
      if (!store[id]) store[id] = {};
      const meta = normEv(ev, sport);
      meta.time_status = '0'; // upcoming
      store[id].meta  = meta;
      store[id].sport = sport;
      await writeToRedis(id);
      uCount++;
      if (uCount >= 80) break;
    }
    if (uCount) log(`Upcoming refresh ${sport}: ${uCount} events`);
  } catch(eu) { /* upcoming may not exist on all plans — ignore */ }
}

// Fast football live loop (score/timer near real-time)
async function refreshFootballLive() { await refreshSportLive('football'); }

// Slow full cycle: all sports live + upcoming, one sport per tick
let metaSportCursor = 0;
async function refreshMeta() {
  if (rateLimited()) return;
  const sport = ALL_SPORTS[metaSportCursor % ALL_SPORTS.length];
  metaSportCursor++;
  await refreshSportLive(sport);
  await refreshSportUpcoming(sport);
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

  // Step 3a: Fast football live loop — score/timer near real-time (every 12s).
  // Football is the priority sport; scores/timers must feel live like fcbet216.
  refreshFootballLive().catch(e => log('Football live init error:', e.message));
  setInterval(() => refreshFootballLive().catch(e => log('Football live error:', e.message)), 12_000);

  // Step 3b: Full multi-sport cycle (live + upcoming), one sport per tick (every 20s).
  refreshMeta().catch(e => log('Meta init error:', e.message));
  setInterval(refreshMeta, 20_000);

  // Periodic stats log every 60s — shows how many events are in store + Redis
  setInterval(async () => {
    const total = Object.keys(store).length;
    const withMarkets = Object.values(store).filter(e => e.markets_raw?.length > 0).length;
    try {
      const redisCount = await redis.sCard('sb:live:all');
      log(`STATS: store=${total} events (${withMarkets} with markets) | Redis=${redisCount} events`);
    } catch(_) {
      log(`STATS: store=${total} events (${withMarkets} with markets)`);
    }
  }, 60_000);

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
