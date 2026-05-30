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

const API_KEY    = process.env.ODDS_API_KEY || '06eff561d8a52e749f38d64f95f4c22bc7504bc16c7122d849eabc9f97908d91';
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
  'ML HT',
  'Spread HT',
  'Totals HT',
  'Both Teams to Score',
  'Correct Score',
  'Double Chance',
  'Asian Handicap',
  'Draw No Bet',
  'Odd/Even',
  'Corners',
  'Cards',
  'Team Totals',
];
const WS_MARKETS  = WS_MARKETS_ARR.join(',');
const WS_SPORT    = 'football,basketball,tennis,volleyball,ice-hockey,handball';
// status MUST be a single value per docs: 'live' or 'prematch' (NOT 'live,upcoming')
const WS_STATUS   = 'live';
// Bookmakers configured in your odds-api.io account dashboard.
// Leave empty to use whatever is configured there, or set explicitly e.g. 'Bet365'
// Bet365 carries the richest market set on the Pro plan (30+ markets incl.
// Corners, Correct Score, Team Totals, HT/2H). Default to it.
const BOOKMAKER   = process.env.BOOKMAKER || 'Bet365';
// Bookmakers to request for /odds (must also be SELECTED in the account).
// Bet365 first (primary), then good-coverage fallbacks. Per event we keep
// whichever returns the most markets.
// Multiple books are queried and MERGED per event (see mergeBookmakerMarkets).
// 1xbet/22Bet carry corners & cards; Bet365 carries half-time/correct score;
// the rest fill gaps. odds-api allows up to 30 bookmakers per request.
const ODDS_BOOKMAKERS = process.env.ODDS_BOOKMAKERS || 'Bet365,1xbet,22Bet,888Sport,Betano,Unibet,10BET,12bet,18bet';

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

// ── Direct REST helper (https) — used for /odds/multi batch odds polling ──────
const https = require('https');
function httpGetJson(path, params = {}) {
  return new Promise((resolve, reject) => {
    const qs = new URLSearchParams({ ...params, apiKey: API_KEY }).toString();
    const url = `https://api.odds-api.io/v3${path}?${qs}`;
    const req = https.get(url, { timeout: 12000, headers: { 'Accept': 'application/json' } }, res => {
      let body = '';
      res.on('data', c => body += c);
      res.on('end', () => {
        if (res.statusCode === 429) return reject(new Error('rate-limit 429'));
        try { resolve(JSON.parse(body)); }
        catch(e) { reject(new Error('bad json: ' + body.slice(0,120))); }
      });
    });
    req.on('error', reject);
    req.on('timeout', () => { req.destroy(); reject(new Error('timeout')); });
  });
}

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

// odds-api.io gives NO minute/period field, but scores.periods tells us which
// halves have data. Derive the active half (1 or 2) so the UI can show the
// correct period and STOP falsely flipping to "Mi-temps" during the 2nd half.
function derivePeriod(scores) {
  if (!scores || typeof scores !== 'object') return 0;
  const p = scores.periods;
  if (!p || typeof p !== 'object') return 0;
  const has = (...keys) => keys.some(k => p[k] && typeof p[k] === 'object');
  // 2nd half / later
  if (has('p2','secondhalf','second_half','ht2','h2','p3','p4','et1','et2','overtime')) return 2;
  // 1st half
  if (has('p1','firsthalf','first_half','ht1','h1')) return 1;
  return 0;
}
// Parse kickoff to a unix-seconds string (frontend estimates the live minute
// from this, since odds-api.io provides NO live clock field).
function parseKickoff(ev) {
  const raw = ev.date || ev.starts_at || ev.start_time || ev.time || 0;
  if (!raw) return '0';
  if (typeof raw === 'number') return String(raw > 1e12 ? Math.floor(raw/1000) : raw);
  const t = Date.parse(raw);
  return isNaN(t) ? '0' : String(Math.floor(t / 1000));
}

function normEv(ev, sportSlug) {
  const slug = (ev.sport && ev.sport.slug) || sportSlug;
  const sid  = SPORT_IDS[slug] || SPORT_IDS[sportSlug] || '1';
  // odds-api.io /events/live has NO minute/clock field — only score + period
  // scores. We store kickoff so the UI can estimate the running minute.
  return {
    id:          String(ev.id),
    sport_id:    sid,
    time:        parseKickoff(ev),
    time_status: ev.status==='live'?'1': (ev.status==='finished'||ev.status==='ended')?'3':'0',
    league:      { id: String(ev.league_id||ev.leagueId||''), name: extractName(ev.league_name, ev.competition, ev.league) },
    home:        { id: String(ev.homeId||ev.home_id||''), name: extractName(ev.home_team, ev.home) },
    away:        { id: String(ev.awayId||ev.away_id||''), name: extractName(ev.away_team, ev.away) },
    ss:          mapScore(ev.scores||ev.score||ev.ss),
    timer:       { tm: parseInt(ev.minute??ev.elapsed??ev.time_min??ev.clock??0)||0, ts: parseInt(ev.second??ev.seconds??0)||0, md: mapPeriod(ev.period||ev.half||ev.phase||ev.status_more), half: derivePeriod(ev.scores||ev.score) },
    _source:     'oddsapi',
    _ts:         Date.now(),
  };
}

// ── Market builder ────────────────────────────────────────────────────────────
function fmt(v) { return (Math.round(parseFloat(v)*100)/100).toFixed(2); }
function r2(v)  { return Math.round(v*100)/100; }

// Names we map explicitly (normalized: UPPER, no spaces/_/-//).
const RECOGNIZED_MKT = new Set([
  'ML','1X2','MONEYLINE',
  'TOTALS','OVERUNDER','GOALS','TOTALGOALS','GOALSOVERUNDER','ALTERNATIVETOTALGOALS','ALTERNATIVEGOALLINE',
  'SPREAD','ASIANHANDICAP','HANDICAP','ALTERNATIVEASIANHANDICAP',
  'DOUBLECHANCE','BTTS','BOTHTEAMSTOSCORE',
  'CORNERS','TOTALCORNERS','CORNERSOVERUNDER','CORNERSTOTALS','CORNERSSPREAD','CORNERSTOTALSHOME','CORNERSTOTALSAWAY',
  'CARDS','TOTALCARDS','YELLOWCARDS','CARDSOVERUNDER','BOOKINGPOINTS','NUMBEROFCARDSINMATCH',
  'BOOKINGS','BOOKINGSSPREAD','BOOKINGSTOTALS','BOOKINGSTOTALSHOME','BOOKINGSTOTALSAWAY',
  'CORRECTSCORE','EXACTSCORE',
  'TEAMTOTALS','HOMETOTALS','AWAYTOTALS','HOMETEAMTOTAL','AWAYTEAMTOTAL',
  'TEAMTOTALHOME','TEAMTOTALAWAY','TEAMTOTALGOALSHOME','TEAMTOTALGOALSAWAY',
  'DRAWNOBET','ODDEVEN','GOALSODDEVEN','HALFTIMERESULT','HALFTIME1X2',
  'MLHT','1X2HT','FIRSTHALF1X2','SPREADHT','HANDICAPHT','ASIANHANDICAPHT',
  'TOTALSHT','OVERUNDERHT','GOALSHT',
  'ML2H','1X22H','SECONDHALF1X2','TOTALS2H','OVERUNDER2H','GOALS2H','SPREAD2H','HANDICAP2H',
]);

// Generic builder for ANY market we don't map explicitly — guarantees the
// market still appears (Corners, Mi-temps variants, player props, etc.) so
// no tab is ever empty just because of a naming difference.
function buildGenericMarket(mkt) {
  const sel = [];
  for (const e of (mkt.odds || [])) {
    if (e == null) continue;
    const line = e.hdp ?? e.max ?? e.points ?? e.line ?? null;
    const suffix = (line !== null && line !== undefined && line !== '') ? ` ${line}` : '';
    // 1X2-shaped
    if (e.home !== undefined || e.draw !== undefined || e.away !== undefined) {
      const hh=+(e.home||0), hx=+(e.draw||0), ha=+(e.away||0);
      if (hh>1) sel.push({name:`1${suffix}`,odds:fmt(hh),NA:`1${suffix}`});
      if (hx>1) sel.push({name:`X${suffix}`,odds:fmt(hx),NA:`X${suffix}`});
      if (ha>1) sel.push({name:`2${suffix}`,odds:fmt(ha),NA:`2${suffix}`});
      continue;
    }
    // Over/Under-shaped (both sides, or single-sided with a line)
    if ((e.over !== undefined || e.under !== undefined) && (e.under !== undefined || e.hdp !== undefined || e.line !== undefined)) {
      const ov=+(e.over||0), un=+(e.under||0);
      if (ov>1) sel.push({name:`Plus de ${line??''}`.trim(),odds:fmt(ov),NA:`O ${line??''}`,handicap:line});
      if (un>1) sel.push({name:`Moins de ${line??''}`.trim(),odds:fmt(un),NA:`U ${line??''}`,handicap:line});
      continue;
    }
    // Player prop: label + over only (e.g. "Player Cards")
    const nmEarly = e.name ?? e.label ?? e.selection ?? e.score ?? '';
    if (nmEarly && e.over !== undefined && +(e.over||0)>1 && e.under === undefined) {
      sel.push({name:String(nmEarly),odds:fmt(e.over),NA:String(nmEarly)});
      continue;
    }
    // Yes/No-shaped
    if (e.yes !== undefined || e.no !== undefined) {
      const y=+(e.yes||0), n=+(e.no||0);
      if (y>1) sel.push({name:'Oui',odds:fmt(y),NA:'Yes'});
      if (n>1) sel.push({name:'Non',odds:fmt(n),NA:'No'});
      continue;
    }
    // Odd/Even-shaped
    if (e.odd !== undefined || e.even !== undefined) {
      const od=+(e.odd||0), ev2=+(e.even||0);
      if (od>1)  sel.push({name:'Impair',odds:fmt(od),NA:'Odd'});
      if (ev2>1) sel.push({name:'Pair',odds:fmt(ev2),NA:'Even'});
      continue;
    }
    // {name, odds}-shaped (correct score, player props, etc.)
    const nm = e.name ?? e.label ?? e.selection ?? e.score ?? '';
    const v  = +(e.odds ?? e.price ?? e.value ?? 0);
    if (nm && v>1) sel.push({name:String(nm),odds:fmt(v),NA:String(nm)});
  }
  if (!sel.length) return null;
  return { name: String(mkt.name||'Marché'), selections: sel, is_open: true };
}

// Odds value can live under several keys depending on the market shape:
// {odds}, or list-style {label, under}, etc. Read the first that is > 1.01.
function oddsVal(e) {
  for (const k of ['odds','price','value','under','over','home','away']) {
    const v = +(e?.[k] || 0);
    if (v > 1.01) return v;
  }
  return 0;
}

function buildMarkets(markets, homeName, awayName) {
  const lo = {}, md = [];
  let ml_h=0, ml_x=0, ml_a=0;
  const _hl = String(homeName||'').toLowerCase().trim();
  const _al = String(awayName||'').toLowerCase().trim();

  for (const mkt of (markets||[])) {
    const name = String(mkt.name||'').toUpperCase().replace(/[\s_\-\/]/g,'');
    const o    = (mkt.odds||[])[0]||{};

    // Unrecognized market → generic passthrough so it still shows in the UI.
    if (!RECOGNIZED_MKT.has(name)) {
      const gm = buildGenericMarket(mkt);
      if (gm) md.push(gm);
      continue;
    }

    if (['ML','1X2','MONEYLINE'].includes(name)) {
      ml_h=+(o.home||0); ml_x=+(o.draw||0); ml_a=+(o.away||0);
      if (ml_h>1) lo.h=ml_h; if (ml_x>1) lo.x=ml_x; if (ml_a>1) lo.a=ml_a;
      const sel=[];
      if (ml_h>1) sel.push({name:'1',   odds:fmt(ml_h),NA:'1'});
      if (ml_x>1) sel.push({name:'X',   odds:fmt(ml_x),NA:'X'});
      if (ml_a>1) sel.push({name:'2',   odds:fmt(ml_a),NA:'2'});
      if (sel.length) md.push({name:'1X2',selections:sel,is_open:true});
    }
    if (['TOTALS','OVERUNDER','GOALS','TOTALGOALS','OVER/UNDER','GOALSOVERUNDER','ALTERNATIVETOTALGOALS','ALTERNATIVEGOALLINE'].includes(name)) {
      // ONE "Total" family with all O/U lines grouped (line in selection.handicap).
      // The UI groups by `handicap` → slider + grid toggle (fcbet216 parity).
      const tsel=[]; let firstLine=true;
      for (const entry of (mkt.odds||[])) {
        const line=+(entry.hdp??entry.max??entry.points??entry.line??entry.total??2.5);
        const ov=+(entry.over||0),un=+(entry.under||0);
        if (ov<1.01&&un<1.01) continue;
        if (firstLine) { lo.ou_line=line; lo.ou_over=ov; lo.ou_under=un; firstLine=false; }
        if (ov>1) tsel.push({name:`Plus de ${line}`,  odds:fmt(ov),NA:`O ${line}`,handicap:line});
        if (un>1) tsel.push({name:`Moins de ${line}`, odds:fmt(un),NA:`U ${line}`,handicap:line});
      }
      if (tsel.length) md.push({name:'Total',selections:tsel,is_open:true});
    }
    if (['SPREAD','ASIANHANDICAP','HANDICAP','ALTERNATIVEASIANHANDICAP'].includes(name)) {
      // ONE "Handicap" family with all lines grouped (line in selection.handicap).
      // odds-api.io "Spread" uses over=home-side, under=away-side.
      const hsel=[];
      for (const entry of (mkt.odds||[])) {
        const hdp=+(entry.hdp??entry.points??entry.line??entry.handicap??0);
        const hh=+(entry.home??entry.over??0),ah=+(entry.away??entry.under??0);
        if (hh<1.01&&ah<1.01) continue;
        const fh=hdp>=0?`+${hdp}`:`${hdp}`,fa=-hdp>=0?`+${-hdp}`:`${-hdp}`;
        if (hh>1) hsel.push({name:`1 ${fh}`,odds:fmt(hh),NA:`H ${hdp}`,handicap:hdp});
        if (ah>1) hsel.push({name:`2 ${fa}`,odds:fmt(ah),NA:`A ${-hdp}`,handicap:hdp});
      }
      if (hsel.length) md.push({name:'Handicap',selections:hsel,is_open:true});
    }
    if (name==='DOUBLECHANCE') {
      const dc={};
      for (const e of (mkt.odds||[])) {
        const val = oddsVal(e);
        if (val<1.01) continue;
        const en=String(e.name||'').toUpperCase().replace(/[\s_]/g,'');
        if (en==='1X'||en==='HOMEORDRAW')  { dc['1X']=val; continue; }
        if (en==='12'||en==='HOMEORAWAY')  { dc['12']=val; continue; }
        if (en==='X2'||en==='DRAWORAWAY')  { dc['X2']=val; continue; }
        // Label-based ("Mexico or Draw", "Mexico or South Africa", "Draw or South Africa")
        const lbl=String(e.label||e.name||'').toLowerCase();
        const hasDraw=/\bdraw\b|\bnul\b|\bx\b/.test(lbl);
        const hasHome=_hl && lbl.indexOf(_hl)!==-1;
        const hasAway=_al && lbl.indexOf(_al)!==-1;
        if (hasDraw && hasHome) dc['1X']=val;
        else if (hasDraw && hasAway) dc['X2']=val;
        else if (hasHome && hasAway) dc['12']=val;
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
    if (['CORNERS','TOTALCORNERS','CORNERSOVERUNDER','CORNERSTOTALS','CORNERSTOTALSHOME','CORNERSTOTALSAWAY'].includes(name)) {
      const cTitle = name==='CORNERSTOTALSHOME' ? 'Corners équipe 1'
                   : name==='CORNERSTOTALSAWAY' ? 'Corners équipe 2'
                   : 'Total des corners';
      const csel=[];
      for (const entry of (mkt.odds||[])) {
        const cl=+(entry.hdp??entry.max??entry.points??entry.line??9.5);
        const co=+(entry.over||0),cu=+(entry.under||0);
        if (co<1.01&&cu<1.01) continue;
        if (co>1) csel.push({name:`Plus de ${cl}`,  odds:fmt(co),NA:`CO ${cl}`,handicap:cl});
        if (cu>1) csel.push({name:`Moins de ${cl}`, odds:fmt(cu),NA:`CU ${cl}`,handicap:cl});
      }
      if (csel.length) md.push({name:cTitle,selections:csel,is_open:true});
    }
    // Corners Spread / Handicap (home vs away)
    if (name==='CORNERSSPREAD') {
      const csel=[];
      for (const entry of (mkt.odds||[])) {
        const hdp=+(entry.hdp??entry.points??entry.line??0);
        const hh=+(entry.home??entry.over??0),ah=+(entry.away??entry.under??0);
        if (hh<1.01&&ah<1.01) continue;
        const fh=hdp>=0?`+${hdp}`:`${hdp}`,fa=-hdp>=0?`+${-hdp}`:`${-hdp}`;
        if (hh>1) csel.push({name:`1 ${fh}`,odds:fmt(hh),NA:`CH ${hdp}`,handicap:hdp});
        if (ah>1) csel.push({name:`2 ${fa}`,odds:fmt(ah),NA:`CA ${-hdp}`,handicap:hdp});
      }
      if (csel.length) md.push({name:'Handicap corners',selections:csel,is_open:true});
    }
    if (['CARDS','TOTALCARDS','YELLOWCARDS','CARDSOVERUNDER','BOOKINGPOINTS','NUMBEROFCARDSINMATCH','BOOKINGS','BOOKINGSTOTALS','BOOKINGSTOTALSHOME','BOOKINGSTOTALSAWAY'].includes(name)) {
      const yTitle = name==='BOOKINGSTOTALSHOME' ? 'Cartons équipe 1'
                   : name==='BOOKINGSTOTALSAWAY' ? 'Cartons équipe 2'
                   : 'Cartons';
      const ysel=[];
      for (const entry of (mkt.odds||[])) {
        const yl=+(entry.hdp??entry.max??entry.points??entry.line??3.5);
        const yo=+(entry.over||0),yu=+(entry.under||0);
        if (yo<1.01&&yu<1.01) continue;
        if (yo>1) ysel.push({name:`Plus de ${yl}`,  odds:fmt(yo),NA:`YC ${yl}`,handicap:yl});
        if (yu>1) ysel.push({name:`Moins de ${yl}`, odds:fmt(yu),NA:`YC- ${yl}`,handicap:yl});
      }
      if (ysel.length) md.push({name:yTitle,selections:ysel,is_open:true});
    }
    // Bookings Spread / Cards Handicap (home vs away)
    if (name==='BOOKINGSSPREAD') {
      const ysel=[];
      for (const entry of (mkt.odds||[])) {
        const hdp=+(entry.hdp??entry.points??entry.line??0);
        const hh=+(entry.home??entry.over??0),ah=+(entry.away??entry.under??0);
        if (hh<1.01&&ah<1.01) continue;
        const fh=hdp>=0?`+${hdp}`:`${hdp}`,fa=-hdp>=0?`+${-hdp}`:`${-hdp}`;
        if (hh>1) ysel.push({name:`1 ${fh}`,odds:fmt(hh),NA:`BH ${hdp}`,handicap:hdp});
        if (ah>1) ysel.push({name:`2 ${fa}`,odds:fmt(ah),NA:`BA ${-hdp}`,handicap:hdp});
      }
      if (ysel.length) md.push({name:'Handicap cartons',selections:ysel,is_open:true});
    }
    if (['CORRECTSCORE','EXACTSCORE'].includes(name)) {
      const sel=[];
      for (const e of (mkt.odds||[])) {
        const sc=String(e.label||e.name||e.score||''),v=+(e.odds||e.price||0);
        if (v>1&&sc) sel.push({name:sc,odds:fmt(v),NA:sc});
      }
      if (sel.length) md.push({name:'Score exact',selections:sel,is_open:true});
    }
    // Team Totals (Total équipe 1 / 2) — grouped family
    if (['TEAMTOTALS','HOMETOTALS','AWAYTOTALS','HOMETEAMTOTAL','AWAYTEAMTOTAL','TEAMTOTALHOME','TEAMTOTALAWAY','TEAMTOTALGOALSHOME','TEAMTOTALGOALSAWAY'].includes(name)) {
      const sideLabel = /AWAY/.test(name) ? 'équipe 2' : 'équipe 1';
      const ttsel=[];
      for (const entry of (mkt.odds||[])) {
        const line=+(entry.hdp??entry.max??entry.points??entry.line??1.5);
        const ov=+(entry.over||0),un=+(entry.under||0);
        if (ov<1.01&&un<1.01) continue;
        if (ov>1) ttsel.push({name:`Plus de ${line}`,  odds:fmt(ov),NA:`TT O ${line}`,handicap:line});
        if (un>1) ttsel.push({name:`Moins de ${line}`, odds:fmt(un),NA:`TT U ${line}`,handicap:line});
      }
      if (ttsel.length) md.push({name:`Total ${sideLabel}`,selections:ttsel,is_open:true});
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
    // Half Time Result (1ère mi-temps 1X2) — odds-api.io sends this as "ML HT".
    // Two shapes: (a) odds[0] = {home,draw,away}; (b) list of {label:'1'|'2'|'Draw', under:odds}.
    if (['HALFTIMERESULT','HALFTIME1X2','MLHT','1X2HT','FIRSTHALF1X2'].includes(name)) {
      let hh=+(o.home||0),hx=+(o.draw||0),ha=+(o.away||0);
      if (hh<1.01&&ha<1.01) {
        for (const e of (mkt.odds||[])) {
          const lbl=String(e.label||e.name||'').toLowerCase().trim();
          const val=oddsVal(e);
          if (val<1.01) continue;
          if (lbl==='1'||lbl==='home'||(_hl&&lbl===_hl)) hh=val;
          else if (lbl==='2'||lbl==='away'||(_al&&lbl===_al)) ha=val;
          else if (lbl==='draw'||lbl==='x'||lbl==='nul'||lbl==='tie') hx=val;
        }
      }
      const sel=[];
      if (hh>1) sel.push({name:'1',odds:fmt(hh),NA:'HT1'});
      if (hx>1) sel.push({name:'X',odds:fmt(hx),NA:'HTX'});
      if (ha>1) sel.push({name:'2',odds:fmt(ha),NA:'HT2'});
      if (sel.length) md.push({name:'1ère mi-temps - 1x2',selections:sel,is_open:true});
    }
    // Half Time Handicap ("Spread HT") — grouped family
    if (['SPREADHT','HANDICAPHT','ASIANHANDICAPHT'].includes(name)) {
      const hhsel=[];
      for (const entry of (mkt.odds||[])) {
        const hdp=+(entry.hdp??entry.points??entry.line??0);
        const hh=+(entry.home??entry.over??0),ah=+(entry.away??entry.under??0);
        if (hh<1.01&&ah<1.01) continue;
        const fh=hdp>=0?`+${hdp}`:`${hdp}`,fa=-hdp>=0?`+${-hdp}`:`${-hdp}`;
        if (hh>1) hhsel.push({name:`1 ${fh}`,odds:fmt(hh),NA:`HTH ${hdp}`,handicap:hdp});
        if (ah>1) hhsel.push({name:`2 ${fa}`,odds:fmt(ah),NA:`HTA ${-hdp}`,handicap:hdp});
      }
      if (hhsel.length) md.push({name:'1ère mi-temps - Handicap',selections:hhsel,is_open:true});
    }
    // Half Time Totals ("Totals HT") — grouped family
    if (['TOTALSHT','OVERUNDERHT','GOALSHT'].includes(name)) {
      const htsel=[];
      for (const entry of (mkt.odds||[])) {
        const line=+(entry.hdp??entry.max??entry.points??entry.line??1.5);
        const ov=+(entry.over||0),un=+(entry.under||0);
        if (ov<1.01&&un<1.01) continue;
        if (ov>1) htsel.push({name:`Plus de ${line}`,  odds:fmt(ov),NA:`HTO ${line}`,handicap:line});
        if (un>1) htsel.push({name:`Moins de ${line}`, odds:fmt(un),NA:`HTU ${line}`,handicap:line});
      }
      if (htsel.length) md.push({name:'1ère mi-temps - Total',selections:htsel,is_open:true});
    }
    // 2nd Half 1X2 ("ML 2H")
    if (['ML2H','1X22H','SECONDHALF1X2'].includes(name)) {
      let hh=+(o.home||0),hx=+(o.draw||0),ha=+(o.away||0);
      if (hh<1.01&&ha<1.01) {
        for (const e of (mkt.odds||[])) {
          const lbl=String(e.label||e.name||'').toLowerCase().trim();
          const val=oddsVal(e);
          if (val<1.01) continue;
          if (lbl==='1'||lbl==='home'||(_hl&&lbl===_hl)) hh=val;
          else if (lbl==='2'||lbl==='away'||(_al&&lbl===_al)) ha=val;
          else if (lbl==='draw'||lbl==='x'||lbl==='nul'||lbl==='tie') hx=val;
        }
      }
      const sel=[];
      if (hh>1) sel.push({name:'1',odds:fmt(hh),NA:'2H1'});
      if (hx>1) sel.push({name:'X',odds:fmt(hx),NA:'2HX'});
      if (ha>1) sel.push({name:'2',odds:fmt(ha),NA:'2H2'});
      if (sel.length) md.push({name:'2ème mi-temps - 1x2',selections:sel,is_open:true});
    }
    // 2nd Half Totals ("Totals 2H")
    if (['TOTALS2H','OVERUNDER2H','GOALS2H'].includes(name)) {
      const s2=[];
      for (const entry of (mkt.odds||[])) {
        const line=+(entry.hdp??entry.max??entry.points??entry.line??1.5);
        const ov=+(entry.over||0),un=+(entry.under||0);
        if (ov<1.01&&un<1.01) continue;
        if (ov>1) s2.push({name:`Plus de ${line}`,  odds:fmt(ov),NA:`2HO ${line}`,handicap:line});
        if (un>1) s2.push({name:`Moins de ${line}`, odds:fmt(un),NA:`2HU ${line}`,handicap:line});
      }
      if (s2.length) md.push({name:'2ème mi-temps - Total',selections:s2,is_open:true});
    }
    // 2nd Half Handicap ("Spread 2H")
    if (['SPREAD2H','HANDICAP2H'].includes(name)) {
      const s2=[];
      for (const entry of (mkt.odds||[])) {
        const hdp=+(entry.hdp??entry.points??entry.line??0);
        const hh=+(entry.home??entry.over??0),ah=+(entry.away??entry.under??0);
        if (hh<1.01&&ah<1.01) continue;
        const fh=hdp>=0?`+${hdp}`:`${hdp}`,fa=-hdp>=0?`+${-hdp}`:`${-hdp}`;
        if (hh>1) s2.push({name:`1 ${fh}`,odds:fmt(hh),NA:`2HH ${hdp}`,handicap:hdp});
        if (ah>1) s2.push({name:`2 ${fa}`,odds:fmt(ah),NA:`2HA ${-hdp}`,handicap:hdp});
      }
      if (s2.length) md.push({name:'2ème mi-temps - Handicap',selections:s2,is_open:true});
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

  // ── Merge same-named market families into ONE market with all lines ──────────
  // The provider can return several total-type markets ("Totals",
  // "Alternative Total Goals", "Goals Over/Under") and several spread-type
  // markets, each as a separate block. Merging them into a single "Total" /
  // "Handicap" family gives the UI the WIDEST possible Over/Under ladder
  // (e.g. 0.5 → 4.5 when the books collectively offer it) instead of just the
  // narrow live window from one block. Dedupe by selection key (NA) keeping the
  // best/last price, then sort by the handicap line so the slider is ordered.
  const mergedOrder = [];
  const mergedByName = new Map();
  for (const mk of md) {
    if (!mergedByName.has(mk.name)) {
      mergedByName.set(mk.name, mk);
      mergedOrder.push(mk.name);
    } else {
      const tgt = mergedByName.get(mk.name);
      const seen = new Set(tgt.selections.map(s => s.NA || s.name));
      for (const s of (mk.selections || [])) {
        const k = s.NA || s.name;
        if (!seen.has(k)) { tgt.selections.push(s); seen.add(k); }
      }
    }
  }
  const merged = mergedOrder.map(n => mergedByName.get(n));
  for (const mk of merged) {
    if (mk.selections && mk.selections.length && mk.selections.some(s => s.handicap !== undefined)) {
      mk.selections.sort((a, b) => {
        const ha = a.handicap === undefined ? 1e9 : +a.handicap;
        const hb = b.handicap === undefined ? 1e9 : +b.handicap;
        return ha - hb;
      });
    }
  }
  // Canonical display order: 1X2 → Total → Double chance → Handicap → Pair/Impair → BTTS → others.
  merged.sort((a, b) => marketRank(a.name) - marketRank(b.name));
  return { live_odds: lo, md_markets: merged };
}

// Canonical market ordering shared by the daemon (matches frontend PRINCIPAUX_ORDER).
const MARKET_ORDER = [
  '1x2','total équipe','total','double chance','handicap','pair/impair','impair','pair',
  'les deux équipes','remboursé si nul',
  '1ère mi-temps','2ème mi-temps','total des corners','cartons','score exact',
];
function marketRank(name) {
  const n = String(name || '').toLowerCase();
  // "Total équipe" must rank after the main "Total"; check specific before generic.
  if (n === '1x2') return 0;
  if (n.indexOf('total équipe') !== -1 || n.indexOf('total equipe') !== -1) return 6;
  if (n === 'total' || n.indexOf('total des buts') !== -1) return 1;
  if (n.indexOf('double chance') !== -1) return 2;
  if (n === 'handicap' || n.indexOf('handicap asiatique') !== -1) return 3;
  if (n.indexOf('pair') !== -1 || n.indexOf('impair') !== -1) return 4;
  if (n.indexOf('deux équipes') !== -1 || n.indexOf('marquent') !== -1) return 5;
  if (n.indexOf('remboursé') !== -1) return 7;
  if (n.indexOf('1ère mi-temps') !== -1 || n.indexOf('1ere mi-temps') !== -1) return 20;
  if (n.indexOf('2ème mi-temps') !== -1 || n.indexOf('2eme mi-temps') !== -1) return 21;
  if (n.indexOf('corner') !== -1) return 30;
  if (n.indexOf('carton') !== -1) return 31;
  if (n.indexOf('score exact') !== -1) return 32;
  return 15;
}

// Merge markets from ALL bookmakers into one list. Different books cover
// different markets for the same fixture (e.g. corners live on 1xbet, while
// Half Time Result / Correct Score live on Bet365). Picking a single "best"
// bookmaker silently drops whole categories, which is why clicking "Corners"
// showed nothing. We dedupe by market name and, when two books expose the
// same market, keep whichever has the most odds lines (richest ladder).
function mergeBookmakerMarkets(bk) {
  if (!bk || typeof bk !== 'object') return [];
  const byName = {};
  // Process books in priority order so ties favour the more reliable feed.
  const order = ODDS_BOOKMAKERS.split(',').map(s => s.trim());
  const books = Object.keys(bk).sort((a, b) => {
    const ia = order.indexOf(a); const ib = order.indexOf(b);
    return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib);
  });
  for (const book of books) {
    const raw = bk[book];
    const list = Array.isArray(raw) ? raw : (raw?.odds || raw?.markets || []);
    if (!Array.isArray(list)) continue;
    for (const m of list) {
      const name = m && (m.name || m.market);
      if (!name) continue;
      const cnt = Array.isArray(m.odds) ? m.odds.length : 0;
      const prev = byName[name];
      const prevCnt = prev ? (Array.isArray(prev.odds) ? prev.odds.length : 0) : -1;
      if (!prev || cnt > prevCnt) byName[name] = m;
    }
  }
  return Object.values(byName);
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

  const _hn = ev.meta?.home?.name || existing?.home?.name || '';
  const _an = ev.meta?.away?.name || existing?.away?.name || '';
  const { live_odds, md_markets } = buildMarkets(ev.markets_raw || existing?.markets_raw_cache || [], _hn, _an);

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
    _source:    'oddsapi',   // stamp source so api.php can drop legacy leftovers
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
          const oddsParams = { eventId: id, bookmakers: ODDS_BOOKMAKERS };
          const oddsResp = await client.getEventOdds(oddsParams);
          onRLOk();
          const bkData  = oddsResp?.bookmakers || oddsResp?.data?.bookmakers || {};
          const markets  = mergeBookmakerMarkets(bkData);
          if (markets.length) {
            store[id].markets_raw = markets;
            store[id].bookie      = Object.keys(bkData).join('+') || 'odds-api';
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

      // Update odds/markets from WS — MERGE into existing rather than replace.
      // WS pushes are single-bookmaker (e.g. Bet365 only carries ML/Spread/Totals/HT).
      // REST batch polling (/odds/multi) already merged Corners/Cards from 1xbet/22Bet.
      // Overwriting kills those markets on every Bet365 push. We keep existing markets
      // not present in this WS push (Corners, Cards, etc.) and update those that are.
      if (data.markets && data.markets.length > 0) {
        const prevRaw = store[id].markets_raw || [];
        if (prevRaw.length) {
          const byName = {};
          for (const m of prevRaw) {
            const key = m?.name ? m.name.toUpperCase().replace(/[\s_\-\/]/g,'') : null;
            if (key) byName[key] = m;
          }
          for (const m of data.markets) {
            const key = m?.name ? m.name.toUpperCase().replace(/[\s_\-\/]/g,'') : null;
            if (!key) continue;
            const existing = byName[key];
            const prevCnt = existing ? (Array.isArray(existing.odds) ? existing.odds.length : 0) : -1;
            const cnt = Array.isArray(m.odds) ? m.odds.length : 0;
            if (!existing || cnt >= prevCnt) byName[key] = m;
          }
          store[id].markets_raw = Object.values(byName);
        } else {
          store[id].markets_raw = data.markets;
        }
        store[id].bookie = data.bookie || BOOKMAKER || 'Bet365';
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
  try {
    // NOTE: there is NO /events/upcoming endpoint (it 400s). The correct source
    // is /events?sport=X which returns ALL events; we keep the ones that are
    // upcoming (status pending/not-started) with a future kickoff so the
    // "browse by date" and league pages are fully populated across days.
    const uResp = await httpGetJson('/events', { sport });
    const rawArr = Array.isArray(uResp) ? uResp : (Array.isArray(uResp?.events) ? uResp.events : (Array.isArray(uResp?.data) ? uResp.data : []));
    const nowMs = Date.now() - 2 * 3600 * 1000;       // keep just-started (<2h) too
    const maxMs = Date.now() + 8 * 24 * 3600 * 1000;  // cover the next ~8 days of dates
    const uArr = rawArr.filter(e => {
      const st = String(e.status || '').toLowerCase();
      if (st === 'settled' || st === 'cancelled' || st === 'finished' || st === 'ended' || st === 'live') return false;
      const ms = Date.parse(e.date || e.starts_at || e.start_time || 0) || 0;
      return ms === 0 || (ms >= nowMs && ms <= maxMs);
    });
    onRLOk();
    // Sort by KICKOFF first so the next several days are fully covered (the
    // "browse by date" page needs every date populated). Priority European
    // leagues only break ties within the same kickoff time.
    const PRIORITY = /world cup|champions league|premier league|la liga|serie a|bundesliga|ligue 1|europa league|conference league/i;
    uArr.sort((a, b) => {
      const da = Date.parse(a.date || a.starts_at || a.start_time || 0) || 0;
      const db = Date.parse(b.date || b.starts_at || b.start_time || 0) || 0;
      if (da !== db) return da - db;
      const la = extractName(a.league_name, a.competition, a.league);
      const lb = extractName(b.league_name, b.competition, b.league);
      return (PRIORITY.test(la) ? 0 : 1) - (PRIORITY.test(lb) ? 0 : 1);
    });
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
      if (uCount >= 5000) break;
    }
    if (uCount) log(`Upcoming refresh ${sport}: ${uCount} events`);
  } catch(eu) { /* upcoming may not exist on all plans — ignore */ }
}

// Fast football live loop (score/timer near real-time)
async function refreshFootballLive() { await refreshSportLive('football'); }

// ── Continuous REST odds polling ──────────────────────────────────────────────
// TWO separate batches:
//   refreshOddsBatch()        — LIVE only, every 3s, 10 events/tick (fast real-time)
//   refreshUpcomingOddsBatch()— UPCOMING only, every 30s, 10 events/tick (prematch)
// Keeping them separate means 2551 upcoming events never delay live odds updates.

let oddsCursor = 0;
async function refreshOddsBatch() {
  if (rateLimited()) return;
  // LIVE events only — football first, then other sports
  const liveIds = Object.keys(store).filter(id => store[id]?.meta?.time_status === '1')
    .sort((a,b) => (store[a]?.sport==='football'?0:1) - (store[b]?.sport==='football'?0:1));
  if (!liveIds.length) return;

  // Round-robin through all live events, 10 per tick (1 request per tick).
  const BATCH = 10;
  if (oddsCursor >= liveIds.length) oddsCursor = 0;
  const batch = liveIds.slice(oddsCursor, oddsCursor + BATCH);
  oddsCursor += BATCH;

  try {
    const params = { eventIds: batch.join(','), bookmakers: ODDS_BOOKMAKERS };
    const resp = await httpGetJson('/odds/multi', params);
    onRLOk();

    // /odds/multi can return: array of events OR {data:[...]} OR {events:{id:{bookmakers:{...}}}}
    let events = [];
    if (Array.isArray(resp)) events = resp;
    else if (Array.isArray(resp?.data)) events = resp.data;
    else if (resp && typeof resp === 'object') {
      // Some API versions return {events: {id: {bookmakers: {...}}}}
      const evMap = resp.events || resp.odds || null;
      if (evMap && typeof evMap === 'object') {
        events = Object.entries(evMap).map(([id, val]) => ({ id, ...val }));
      }
    }

    // Log first response shape for debugging (once per daemon run)
    if (!refreshOddsBatch._logged && resp) {
      refreshOddsBatch._logged = true;
      const sample = JSON.stringify(resp).slice(0, 400);
      log(`Odds/multi response shape (first 400 chars): ${sample}`);
    }

    let withOdds = 0;
    for (const ev of events) {
      const id = String(ev.id || ev.eventId || '');
      if (!id) continue;
      if (!store[id]) store[id] = {};

      // Try ev.bookmakers (standard), ev.odds (some versions), ev itself if keyed by book name
      let bk = ev.bookmakers || ev.odds || {};
      // If bk is an array, it's a flat market list from one bookmaker — wrap it
      if (Array.isArray(bk)) bk = { 'unknown': bk };

      const markets = mergeBookmakerMarkets(bk);
      if (markets && markets.length) {
        store[id].markets_raw = markets;
        store[id].bookie = Object.keys(bk).join('+') || 'odds-api';
        withOdds++;
        await writeToRedis(id);
      }
    }
    if (withOdds) log(`Odds batch: ${withOdds}/${batch.length} events got markets (cursor ${oddsCursor}/${liveIds.length})`);
    else {
      // Zero markets from /odds/multi — log raw shape to diagnose
      if (!refreshOddsBatch._warnedEmpty) {
        refreshOddsBatch._warnedEmpty = true;
        const shape = JSON.stringify(resp).slice(0, 600);
        log(`Odds batch: 0 markets. Raw: ${shape}`);
      } else {
        log(`Odds batch: 0/${batch.length} got markets`);
      }
    }
  } catch(e) {
    if (isRL(e)) onRL();
    else log('Odds batch error:', e.message);
  }
}

// ── Upcoming/prematch odds batch (slow) ──────────────────────────────────────
// Fetches /odds/multi for UPCOMING events every 30s, 10 per tick.
// Keeping this separate from the live batch ensures live odds never get delayed
// by cycling through 2500+ prematch events.
let upcomingOddsCursor = 0;
async function refreshUpcomingOddsBatch() {
  if (rateLimited()) return;
  // Upcoming events only (ts=0), football first
  const upcomingIds = Object.keys(store)
    .filter(id => store[id]?.meta?.time_status === '0')
    .sort((a,b) => (store[a]?.sport==='football'?0:1) - (store[b]?.sport==='football'?0:1));
  if (!upcomingIds.length) return;

  const BATCH = 10;
  if (upcomingOddsCursor >= upcomingIds.length) upcomingOddsCursor = 0;
  const batch = upcomingIds.slice(upcomingOddsCursor, upcomingOddsCursor + BATCH);
  upcomingOddsCursor += BATCH;

  try {
    const params = { eventIds: batch.join(','), bookmakers: ODDS_BOOKMAKERS };
    const resp = await httpGetJson('/odds/multi', params);
    onRLOk();

    let events = [];
    if (Array.isArray(resp)) events = resp;
    else if (Array.isArray(resp?.data)) events = resp.data;
    else if (resp && typeof resp === 'object') {
      const evMap = resp.events || resp.odds || null;
      if (evMap && typeof evMap === 'object') {
        events = Object.entries(evMap).map(([id, val]) => ({ id, ...val }));
      }
    }

    let withOdds = 0;
    for (const ev of events) {
      const id = String(ev.id || ev.eventId || '');
      if (!id) continue;
      if (!store[id]) store[id] = {};

      let bk = ev.bookmakers || ev.odds || {};
      if (Array.isArray(bk)) bk = { 'unknown': bk };

      const markets = mergeBookmakerMarkets(bk);
      if (markets && markets.length) {
        store[id].markets_raw = markets;
        store[id].bookie = Object.keys(bk).join('+') || 'odds-api';
        withOdds++;
        await writeToRedis(id);
      }
    }
    if (withOdds) log(`Upcoming odds batch: ${withOdds}/${batch.length} events got markets (cursor ${upcomingOddsCursor}/${upcomingIds.length})`);
  } catch(e) {
    if (isRL(e)) onRL();
    else log('Upcoming odds batch error:', e.message);
  }
}

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

  // Step 3a': Dedicated football UPCOMING loop. The slow multi-sport cycle only
  // touches one sport per tick, so football's "browse by date" / league pages
  // could sit empty for minutes after a flush. Populate football upcoming
  // immediately and refresh it every 45s so every near date is covered fast.
  refreshSportUpcoming('football').catch(e => log('Football upcoming init error:', e.message));
  setInterval(() => refreshSportUpcoming('football').catch(e => log('Football upcoming error:', e.message)), 45_000);

  // Step 3b: Full multi-sport cycle (live + upcoming), one sport per tick (every 20s).
  refreshMeta().catch(e => log('Meta init error:', e.message));
  setInterval(refreshMeta, 20_000);

  // Step 3c: Continuous REST odds polling — fetches full markets via /odds/multi.
  // LIVE batch: 10 events every 3s → full cycle of ~95 live events in ~30s.
  // UPCOMING batch: 10 events every 30s → covers prematch odds without starving live.
  setTimeout(() => {
    refreshOddsBatch().catch(e => log('Odds init error:', e.message));
    setInterval(() => refreshOddsBatch().catch(e => log('Odds loop error:', e.message)), 3_000);

    // Start upcoming batch 15s after live batch to stagger API calls
    setTimeout(() => {
      refreshUpcomingOddsBatch().catch(e => log('Upcoming odds init error:', e.message));
      setInterval(() => refreshUpcomingOddsBatch().catch(e => log('Upcoming odds loop error:', e.message)), 30_000);
    }, 15_000);
  }, 8000); // wait for first meta refresh to populate event store

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
