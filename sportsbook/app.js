/**
 * sportsbook/app.js — Premium Sportsbook UI
 * Design reference: fcbet216.com (Altenar wsdk)
 * Colors: #70f669 green, #979797 gray, #101010 bg
 */
(function(){
'use strict';

// ── CSS safety-net: ensure sportsbook/style.css is in the DOM (does NOT
// re-fetch when the file is already linked via PHP filemtime cache-bust).
// The previous version forced a re-download on every page load by setting
// `existingLink.href = newHref` — that defeated browser caching and slowed
// every navigation. Now we only inject if the link is missing.
(function injectCSS() {
  if (document.querySelector('#sb-css-link, link[href*="sportsbook/style.css"]')) return;
  var base = window.location.pathname.replace(/\/sportsbook.*$/i, '/') || '/';
    var link = document.createElement('link');
    link.rel = 'stylesheet';
  link.href = base + 'sportsbook/style.css';
    document.head.appendChild(link);
})();

var MARGIN = 0; // BetsAPI provides real Bet365 odds directly; margin is in Bet365's overround
// Bet Builder correlation factor per number of legs (matches fcbet/Altenar model)
var BB_CORR = { 1: 1.0, 2: 0.90, 3: 0.85, 4: 0.80 }; // 4+ uses 0.80
// Dynamic base path calculation
var scriptPath = window.location.pathname;
var BASE = '/';

// ── Shared jersey SVG builder ─────────────────────────────────────────────
// Supports patterns: solid, stripes (vertical), hoops (horizontal),
// halves (left/right), sash (diagonal stripe), quarters
var _shirtId = 0;
function shirtSVG(tName, cssClass, size) {
  var kit = KITS[tName]
    || KITS[tName.replace(/ FC$| CF$| SC$| AC$| BC$| IF$| FK$| BK$| SK$/, '').trim()]
    || KITS[tName.replace(/^FC |^AC |^AS |^RC |^SC |^SS |^CS |^CF |^SL |^FK |^NK |^SK |^BK /, '').trim()]
    || null;
  var main, sec, pat;
  if (kit) {
    main = kit.m; sec = kit.s; pat = kit.p || 'solid';
  } else {
    var c1 = ['#e02424','#2563eb','#16a34a','#ca8a04','#7c3aed','#db2777','#C8102E','#000000','#f97316','#0ea5e9'];
    var c2 = ['#ffffff','#ffffff','#ffffff','#F5C400','#ffffff','#ffffff','#ffffff','#ffffff','#ffffff','#ffffff'];
    var seed = 0;
    for (var i = 0; i < tName.length; i++) seed += tName.charCodeAt(i);
    main = c1[seed % c1.length]; sec = c2[seed % c2.length]; pat = 'solid';
  }
  // STROKE = pure white for every shirt. Inline SVG attribute (not CSS) so
  // it always wins regardless of cache or browser specificity quirks.
  // This is the "border 1 white" the user has been asking for.
  var stroke = '#ffffff';
  var sz = size || 24;
  var uid = 'k' + (++_shirtId);
  var defs = '';
  var bodyFill = 'fill="' + main + '"';
  if (pat === 'stripes') {
    defs = '<defs><pattern id="' + uid + '" x="0" y="0" width="8" height="32" patternUnits="userSpaceOnUse"><rect width="4" height="32" fill="' + main + '"/><rect x="4" width="4" height="32" fill="' + sec + '"/></pattern></defs>';
    bodyFill = 'fill="url(#' + uid + ')"';
  } else if (pat === 'hoops') {
    defs = '<defs><pattern id="' + uid + '" x="0" y="0" width="32" height="8" patternUnits="userSpaceOnUse"><rect width="32" height="4" fill="' + main + '"/><rect y="4" width="32" height="4" fill="' + sec + '"/></pattern></defs>';
    bodyFill = 'fill="url(#' + uid + ')"';
  } else if (pat === 'halves') {
    defs = '<defs><linearGradient id="' + uid + '" x1="0" y1="0" x2="1" y2="0"><stop offset="50%" stop-color="' + main + '"/><stop offset="50%" stop-color="' + sec + '"/></linearGradient></defs>';
    bodyFill = 'fill="url(#' + uid + ')"';
  } else if (pat === 'sash') {
    defs = '<defs><linearGradient id="' + uid + '" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="' + main + '"/><stop offset="40%" stop-color="' + main + '"/><stop offset="40%" stop-color="' + sec + '"/><stop offset="60%" stop-color="' + sec + '"/><stop offset="60%" stop-color="' + main + '"/><stop offset="100%" stop-color="' + main + '"/></linearGradient></defs>';
    bodyFill = 'fill="url(#' + uid + ')"';
  } else if (pat === 'quarters') {
    defs = '<defs><pattern id="' + uid + '" x="0" y="0" width="16" height="16" patternUnits="userSpaceOnUse"><rect width="8" height="8" fill="' + main + '"/><rect x="8" width="8" height="8" fill="' + sec + '"/><rect y="8" width="8" height="8" fill="' + sec + '"/><rect x="8" y="8" width="8" height="8" fill="' + main + '"/></pattern></defs>';
    bodyFill = 'fill="url(#' + uid + ')"';
  }
  var collarFill = 'fill="' + sec + '" stroke="' + stroke + '"';
  return '<svg viewBox="0 0 32 32" class="' + cssClass + '" width="' + sz + '" height="' + sz + '" style="flex-shrink:0" stroke="' + stroke + '" stroke-width="1" stroke-linejoin="round">'
    + defs
    + '<path ' + bodyFill + ' d="M11.6,2.2c0,0-1-1.3-4.5-0.1L2,5l2,6.5c0,0,1.3,0.3,3.5-1.5c0,0,1,19.3,1,19.9h15.2c0-0.6,0.9-19.9,0.9-19.9 c2.2,1.8,3.5,1.5,3.5,1.5L30,5L24.8,2.1c-3.4-1.2-4.5,0.1-4.5,0.1c-1.3,1.4-2.3,1.6-4.3,1.6C13.9,3.8,12.8,3.6,11.6,2.2z"/>'
    + '<path d="M12.9,2.8c1,0.9,1.8,1.2,3.1,1.2c1.3,0,2.1-0.3,3.1-1.2l-1.4,1.8h-3.4L12.9,2.8z" ' + collarFill + '/>'
    + '</svg>';
}

// ── Real team kit colors — INLINED for zero-latency (no fetch needed) ─────
// Format: {m: mainColor, s: secondaryColor, p: pattern}
// Patterns: solid | stripes | hoops | halves | sash | quarters
var KITS = {
  "Arsenal":{"m":"#EF0107","s":"#FFFFFF","p":"solid"},"Chelsea":{"m":"#034694","s":"#FFFFFF","p":"solid"},
  "Man City":{"m":"#6CABDD","s":"#FFFFFF","p":"solid"},"Manchester City":{"m":"#6CABDD","s":"#FFFFFF","p":"solid"},
  "Man Utd":{"m":"#DA291C","s":"#FFFFFF","p":"solid"},"Manchester Utd":{"m":"#DA291C","s":"#FFFFFF","p":"solid"},"Manchester United":{"m":"#DA291C","s":"#FFFFFF","p":"solid"},
  "Liverpool":{"m":"#C8102E","s":"#FFFFFF","p":"solid"},"Tottenham":{"m":"#FFFFFF","s":"#132257","p":"solid"},"Tottenham Hotspur":{"m":"#FFFFFF","s":"#132257","p":"solid"},"Spurs":{"m":"#FFFFFF","s":"#132257","p":"solid"},
  "West Ham":{"m":"#7A263A","s":"#1BB1E7","p":"solid"},"West Ham United":{"m":"#7A263A","s":"#1BB1E7","p":"solid"},
  "Everton":{"m":"#003399","s":"#FFFFFF","p":"solid"},"Leicester":{"m":"#003090","s":"#FDBE11","p":"solid"},"Leicester City":{"m":"#003090","s":"#FDBE11","p":"solid"},
  "Newcastle":{"m":"#241F20","s":"#FFFFFF","p":"stripes"},"Newcastle United":{"m":"#241F20","s":"#FFFFFF","p":"stripes"},"Newcastle Utd":{"m":"#241F20","s":"#FFFFFF","p":"stripes"},
  "Brighton":{"m":"#0057B8","s":"#FFFFFF","p":"stripes"},"Brighton & Hove Albion":{"m":"#0057B8","s":"#FFFFFF","p":"stripes"},
  "Aston Villa":{"m":"#95BFE5","s":"#670E36","p":"stripes"},"Wolves":{"m":"#FDB913","s":"#231F20","p":"solid"},"Wolverhampton":{"m":"#FDB913","s":"#231F20","p":"solid"},
  "Crystal Palace":{"m":"#1B458F","s":"#C4122E","p":"halves"},"Brentford":{"m":"#E30613","s":"#FFFFFF","p":"stripes"},
  "Fulham":{"m":"#FFFFFF","s":"#000000","p":"solid"},"Burnley":{"m":"#6C1D45","s":"#99D6EA","p":"solid"},
  "Nottingham Forest":{"m":"#DD0000","s":"#FFFFFF","p":"solid"},"Nott'm Forest":{"m":"#DD0000","s":"#FFFFFF","p":"solid"},"Nottm Forest":{"m":"#DD0000","s":"#FFFFFF","p":"solid"},
  "Southampton":{"m":"#D71920","s":"#FFFFFF","p":"stripes"},"Leeds United":{"m":"#FFFFFF","s":"#1D428A","p":"solid"},"Leeds":{"m":"#FFFFFF","s":"#1D428A","p":"solid"},
  "Bournemouth":{"m":"#DA291C","s":"#000000","p":"stripes"},"AFC Bournemouth":{"m":"#DA291C","s":"#000000","p":"stripes"},
  "Ipswich":{"m":"#0033A0","s":"#FFFFFF","p":"solid"},"Ipswich Town":{"m":"#0033A0","s":"#FFFFFF","p":"solid"},
  "Sunderland":{"m":"#EB172B","s":"#FFFFFF","p":"stripes"},"Sheffield United":{"m":"#EE2737","s":"#FFFFFF","p":"stripes"},"Sheff Utd":{"m":"#EE2737","s":"#FFFFFF","p":"stripes"},
  "Norwich City":{"m":"#00A650","s":"#FFF200","p":"halves"},"Norwich":{"m":"#00A650","s":"#FFF200","p":"halves"},
  "Middlesbrough":{"m":"#DD0000","s":"#FFFFFF","p":"solid"},"Boro":{"m":"#DD0000","s":"#FFFFFF","p":"solid"},
  "Barcelona":{"m":"#004D98","s":"#A50044","p":"stripes"},"Real Madrid":{"m":"#FFFFFF","s":"#00529F","p":"solid"},
  "Atletico Madrid":{"m":"#CB3524","s":"#FFFFFF","p":"stripes"},"Atlético Madrid":{"m":"#CB3524","s":"#FFFFFF","p":"stripes"},"Atl. Madrid":{"m":"#CB3524","s":"#FFFFFF","p":"stripes"},
  "Valencia":{"m":"#FFFFFF","s":"#FF7F00","p":"solid"},"Villarreal":{"m":"#FFD900","s":"#005689","p":"solid"},"Villarreal CF":{"m":"#FFD900","s":"#005689","p":"solid"},
  "Sevilla":{"m":"#FFFFFF","s":"#D91A21","p":"solid"},"Sevilla FC":{"m":"#FFFFFF","s":"#D91A21","p":"solid"},
  "Real Betis":{"m":"#00954C","s":"#FFFFFF","p":"stripes"},"Betis":{"m":"#00954C","s":"#FFFFFF","p":"stripes"},
  "Athletic Bilbao":{"m":"#EE2523","s":"#FFFFFF","p":"stripes"},"Athletic Club":{"m":"#EE2523","s":"#FFFFFF","p":"stripes"},"Athletic":{"m":"#EE2523","s":"#FFFFFF","p":"stripes"},
  "Real Sociedad":{"m":"#003DA5","s":"#FFFFFF","p":"stripes"},"Osasuna":{"m":"#C8102E","s":"#003DA5","p":"solid"},
  "Celta Vigo":{"m":"#8CBFE8","s":"#FFFFFF","p":"stripes"},"Celta de Vigo":{"m":"#8CBFE8","s":"#FFFFFF","p":"stripes"},
  "Girona":{"m":"#CD1E2C","s":"#FFFFFF","p":"stripes"},"Girona FC":{"m":"#CD1E2C","s":"#FFFFFF","p":"stripes"},
  "Getafe":{"m":"#005FA8","s":"#FFFFFF","p":"solid"},"Rayo Vallecano":{"m":"#FFFFFF","s":"#D01111","p":"sash"},
  "Mallorca":{"m":"#E4001B","s":"#000000","p":"stripes"},"Cadiz":{"m":"#FFE000","s":"#003DA5","p":"solid"},"Cádiz":{"m":"#FFE000","s":"#003DA5","p":"solid"},
  "Alaves":{"m":"#0047AB","s":"#FFFFFF","p":"solid"},"Leganes":{"m":"#003DA5","s":"#FFFFFF","p":"solid"},"Leganés":{"m":"#003DA5","s":"#FFFFFF","p":"solid"},
  "Juventus":{"m":"#000000","s":"#FFFFFF","p":"stripes"},"Juve":{"m":"#000000","s":"#FFFFFF","p":"stripes"},
  "Inter":{"m":"#010E80","s":"#000000","p":"stripes"},"Inter Milan":{"m":"#010E80","s":"#000000","p":"stripes"},"Internazionale":{"m":"#010E80","s":"#000000","p":"stripes"},
  "AC Milan":{"m":"#FB090B","s":"#000000","p":"stripes"},"Milan":{"m":"#FB090B","s":"#000000","p":"stripes"},
  "Napoli":{"m":"#12A0C3","s":"#FFFFFF","p":"solid"},"SSC Napoli":{"m":"#12A0C3","s":"#FFFFFF","p":"solid"},
  "Roma":{"m":"#A6192E","s":"#F5C400","p":"solid"},"AS Roma":{"m":"#A6192E","s":"#F5C400","p":"solid"},
  "Lazio":{"m":"#87CEEB","s":"#FFFFFF","p":"solid"},"SS Lazio":{"m":"#87CEEB","s":"#FFFFFF","p":"solid"},
  "Fiorentina":{"m":"#6C1F7B","s":"#FFFFFF","p":"solid"},"ACF Fiorentina":{"m":"#6C1F7B","s":"#FFFFFF","p":"solid"},
  "Atalanta":{"m":"#1C4E9D","s":"#000000","p":"stripes"},"Bologna":{"m":"#C8102E","s":"#003DA5","p":"halves"},"FC Bologna":{"m":"#C8102E","s":"#003DA5","p":"halves"},
  "Torino":{"m":"#7A1621","s":"#FFFFFF","p":"solid"},"Genoa":{"m":"#C8102E","s":"#003DA5","p":"stripes"},
  "Udinese":{"m":"#000000","s":"#FFFFFF","p":"stripes"},"Cagliari":{"m":"#003DA5","s":"#CC0000","p":"solid"},
  "Lecce":{"m":"#F5C400","s":"#CC0000","p":"solid"},"Empoli":{"m":"#1B9CD0","s":"#FFFFFF","p":"solid"},
  "Monza":{"m":"#FF0000","s":"#FFFFFF","p":"solid"},"AC Monza":{"m":"#FF0000","s":"#FFFFFF","p":"solid"},
  "Bayern Munich":{"m":"#DC052D","s":"#FFFFFF","p":"solid"},"FC Bayern":{"m":"#DC052D","s":"#FFFFFF","p":"solid"},"Bayern":{"m":"#DC052D","s":"#FFFFFF","p":"solid"},
  "Dortmund":{"m":"#FDE100","s":"#000000","p":"solid"},"Borussia Dortmund":{"m":"#FDE100","s":"#000000","p":"solid"},"BVB":{"m":"#FDE100","s":"#000000","p":"solid"},"B. Dortmund":{"m":"#FDE100","s":"#000000","p":"solid"},
  "Bayer Leverkusen":{"m":"#E32221","s":"#000000","p":"solid"},"Leverkusen":{"m":"#E32221","s":"#000000","p":"solid"},"B. Leverkusen":{"m":"#E32221","s":"#000000","p":"solid"},
  "RB Leipzig":{"m":"#FFFFFF","s":"#CC0000","p":"solid"},"Schalke":{"m":"#004D9E","s":"#FFFFFF","p":"solid"},
  "Werder Bremen":{"m":"#1D6034","s":"#FFFFFF","p":"solid"},"Eintracht Frankfurt":{"m":"#E1000F","s":"#000000","p":"solid"},"Frankfurt":{"m":"#E1000F","s":"#000000","p":"solid"},"Ein Frankfurt":{"m":"#E1000F","s":"#000000","p":"solid"},
  "Stuttgart":{"m":"#E32221","s":"#FFFFFF","p":"solid"},"VfB Stuttgart":{"m":"#E32221","s":"#FFFFFF","p":"solid"},
  "Wolfsburg":{"m":"#65B32E","s":"#FFFFFF","p":"solid"},"Freiburg":{"m":"#C8102E","s":"#FFFFFF","p":"stripes"},
  "Hoffenheim":{"m":"#1863A1","s":"#FFFFFF","p":"solid"},"Mainz":{"m":"#C8102E","s":"#FFFFFF","p":"solid"},"Mainz 05":{"m":"#C8102E","s":"#FFFFFF","p":"solid"},
  "Union Berlin":{"m":"#CF2218","s":"#FFFFFF","p":"solid"},"Augsburg":{"m":"#007A33","s":"#FFFFFF","p":"stripes"},
  "Heidenheim":{"m":"#C8102E","s":"#FFFFFF","p":"solid"},"Bochum":{"m":"#003DA5","s":"#FFFFFF","p":"solid"},"VfL Bochum":{"m":"#003DA5","s":"#FFFFFF","p":"solid"},
  "PSG":{"m":"#003F8A","s":"#DA291C","p":"solid"},"Paris Saint-Germain":{"m":"#003F8A","s":"#DA291C","p":"solid"},"Paris SG":{"m":"#003F8A","s":"#DA291C","p":"solid"},
  "Marseille":{"m":"#009FE3","s":"#FFFFFF","p":"solid"},"Olympique Marseille":{"m":"#009FE3","s":"#FFFFFF","p":"solid"},
  "Lyon":{"m":"#FFFFFF","s":"#0D1E4C","p":"solid"},"Monaco":{"m":"#D01027","s":"#FFFFFF","p":"halves"},"AS Monaco":{"m":"#D01027","s":"#FFFFFF","p":"halves"},
  "Rennes":{"m":"#C8102E","s":"#000000","p":"solid"},"Nice":{"m":"#C8102E","s":"#000000","p":"stripes"},
  "Lens":{"m":"#CF2118","s":"#F5C400","p":"solid"},"RC Lens":{"m":"#CF2118","s":"#F5C400","p":"solid"},
  "Lille":{"m":"#CF0B28","s":"#003D8F","p":"halves"},"Toulouse":{"m":"#6B2C85","s":"#FFFFFF","p":"solid"},
  "Brest":{"m":"#DA291C","s":"#FFFFFF","p":"solid"},"Nantes":{"m":"#F5C400","s":"#009550","p":"stripes"},
  "Reims":{"m":"#DA291C","s":"#FFFFFF","p":"solid"},"Saint-Etienne":{"m":"#007A33","s":"#FFFFFF","p":"solid"},
  "Ajax":{"m":"#CC0000","s":"#FFFFFF","p":"stripes"},"PSV":{"m":"#DA291C","s":"#FFFFFF","p":"stripes"},"PSV Eindhoven":{"m":"#DA291C","s":"#FFFFFF","p":"stripes"},
  "Feyenoord":{"m":"#CC0000","s":"#FFFFFF","p":"halves"},"AZ":{"m":"#CC0000","s":"#FFFFFF","p":"solid"},
  "Porto":{"m":"#003DA5","s":"#FFFFFF","p":"stripes"},"FC Porto":{"m":"#003DA5","s":"#FFFFFF","p":"stripes"},
  "Benfica":{"m":"#CC0000","s":"#FFFFFF","p":"solid"},"SL Benfica":{"m":"#CC0000","s":"#FFFFFF","p":"solid"},
  "Sporting CP":{"m":"#009550","s":"#FFFFFF","p":"solid"},"Sporting":{"m":"#009550","s":"#FFFFFF","p":"solid"},
  "Celtic":{"m":"#009550","s":"#FFFFFF","p":"hoops"},"Rangers":{"m":"#003DA5","s":"#FFFFFF","p":"solid"},
  "Anderlecht":{"m":"#6B2C85","s":"#FFFFFF","p":"solid"},"Club Brugge":{"m":"#003DA5","s":"#000000","p":"stripes"},
  "Shakhtar Donetsk":{"m":"#E87722","s":"#000000","p":"stripes"},"Shakhtar":{"m":"#E87722","s":"#000000","p":"stripes"},
  "Galatasaray":{"m":"#CC0000","s":"#F5C400","p":"stripes"},"Fenerbahce":{"m":"#F5C400","s":"#003DA5","p":"stripes"},"Fenerbahçe":{"m":"#F5C400","s":"#003DA5","p":"stripes"},
  "Besiktas":{"m":"#000000","s":"#FFFFFF","p":"stripes"},"Beşiktaş":{"m":"#000000","s":"#FFFFFF","p":"stripes"},
  "Olympiakos":{"m":"#CC0000","s":"#FFFFFF","p":"stripes"},"Panathinaikos":{"m":"#009550","s":"#FFFFFF","p":"solid"},
  "Salzburg":{"m":"#CC0000","s":"#FFFFFF","p":"solid"},"RB Salzburg":{"m":"#CC0000","s":"#FFFFFF","p":"solid"},
  "Young Boys":{"m":"#F5C400","s":"#000000","p":"solid"},"FC Zurich":{"m":"#003DA5","s":"#FFFFFF","p":"solid"},
  "Flamengo":{"m":"#CC0000","s":"#000000","p":"stripes"},"Palmeiras":{"m":"#006E51","s":"#FFFFFF","p":"solid"},
  "Corinthians":{"m":"#000000","s":"#FFFFFF","p":"stripes"},"Boca Juniors":{"m":"#003DA5","s":"#F5C400","p":"stripes"},
  "River Plate":{"m":"#FFFFFF","s":"#CC0000","p":"sash"},"Fluminense":{"m":"#CC0000","s":"#FFFFFF","p":"stripes"}
};
// Async-extend with full kits.json (extra teams not in the inline set)
(function() {
  var base2 = window.location.pathname.replace(/\/sportsbook.*$/i, '/') || '/';
  fetch(base2 + 'sportsbook/kits.json?v=2')
    .then(function(r) { return r.json(); })
    .then(function(d) { Object.assign(KITS, d.teams || {}); })
    .catch(function() {});
})();
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

/* ── computeFallbackTimer: kickoff-derived LAST-RESORT estimate ──
 * For low-coverage leagues (Serie C Play-Offs, women's, lower regional)
 * BetsAPI does NOT push a real m.timer at all. Showing 00:00 / En cours
 * for a 12-minute-old match is wrong. We derive an estimate from m.time
 * (kickoff Unix ts), tagged with .estimated so the UI can prefix "~".
 * effectiveTimer() prefers real timer first — this is fallback ONLY.
 *
 * KICKOFF DELAY OFFSET: real matches start 2-4 minutes after the
 * scheduled kickoff (referees, anthems, late teams). Subtract a
 * 2-minute offset so our estimate roughly matches FlashScore (which
 * counts from actual play, not scheduled time). */
// 3 min average start delay (referees, anthems, late teams, VAR checks).
// Matches the typical FlashScore offset against scheduled kickoff.
var KICKOFF_DELAY_SEC = 180;
function computeFallbackTimer(m) {
  if (!m) return null;
  if (isMatchEnded(m)) return null;
  var kickoff = parseInt(m.time || m.kickoff || 0, 10) || 0;
  if (kickoff <= 0) return null;
  var nowSec = Math.floor(Date.now() / 1000);
  var elapsed = nowSec - kickoff - KICKOFF_DELAY_SEC;
  if (elapsed < 0) return null;  // match hasn't 'started' for our purposes
  var sid = parseInt(m.sport_id || 1, 10);
  if (sid === 1 || sid === 36) {
    var totalMin = Math.floor(elapsed / 60);
    var sec = elapsed % 60;
    if (totalMin <= 45) return { tm: totalMin, ts: sec, md: '0', estimated: true };
    if (totalMin < 60)  return { tm: 45,       ts: 0,   md: '1', estimated: true };
    var matchMin = 46 + (totalMin - 60);
    if (matchMin > 125) return { tm: 90, ts: 0, md: '0', estimated: true };
    return { tm: matchMin, ts: sec, md: '0', estimated: true };
  }
  return { tm: Math.floor(elapsed / 60), ts: elapsed % 60, md: '0', estimated: true };
}

/* ── Score regression guard. Goals don't un-happen for EITHER team.
 * Sometimes a stale BetsAPI snapshot (v3 / inplay_filter) arrives
 * AFTER the fresher /live_refresh result and would otherwise revert
 * the score (e.g. 1-1 → 0-1 → 1-1, or 1-1 → 1-0).
 *
 * Rule: BOTH sides must be ≥ the old value for us to accept the
 * new score. This kills flicker like 1-1 → 1-0 → 1-1.
 * Tolerant of "1:1" / "1 - 1" formatting from various BetsAPI endpoints. */
function _parseScore(s) {
  if (!s) return null;
  var m = String(s).replace(/\s+/g, '').split(/[-:]/);
  if (m.length < 2) return null;
  var h = parseInt(m[0], 10);
  var a = parseInt(m[1], 10);
  if (isNaN(h) || isNaN(a)) return null;
  return [h, a];
}
function _acceptScoreUpdate(oldSs, newSs) {
  if (newSs == null || newSs === '') return false;
  if (newSs === oldSs) return false;
  // ── Per-side regression guard. A real goal is never un-scored on
  //    a refresh — the snapshot endpoint sometimes lags one cycle
  //    behind the EV stream, so it can briefly send "1-0" right
  //    after we've already moved to "1-1". Reject any update that
  //    DECREASES either home or away. (VAR cancellations are rare
  //    enough that flicker is a worse UX than the 30s delay.)
  var oldP = _parseScore(oldSs);
  var newP = _parseScore(newSs);
  if (oldP && newP) {
    if (newP[0] < oldP[0]) return false;
    if (newP[1] < oldP[1]) return false;
  }
  return true;
}

/* ── Timer regression guard. Sometimes BetsAPI / v3 momentarily
 * returns timer.tm = 0 while the match is well past minute 0 — we
 * never want to "reset" the live clock back to zero because that
 * causes the dreaded "42' → 0' → 42'" flicker on cards. Returns
 * TRUE iff the new timer is a legit forward update.                */
function _acceptTimerUpdate(oldT, newT) {
  if (!newT) return false;
  if (!oldT) return true;
  var oldMd = String(oldT.md || oldT.MD || '');
  var newMd = String(newT.md || newT.MD || '');
  // Period change always wins (e.g. 1st-half → halftime → 2nd-half).
  if (oldMd !== newMd) return true;
  var oldTm = parseInt(oldT.tm || oldT.TM || 0, 10) || 0;
  var newTm = parseInt(newT.tm || newT.TM || 0, 10) || 0;
  // Reset-to-zero glitch inside an active period — almost certainly noise.
  if (newTm === 0 && oldTm > 3) return false;
  // Kickoff-derived fallback clocks often run 2-4 min AHEAD of the real
  // Bet365 stream timer. Accept any ≥2 min correction so we snap to
  // BetsAPI truth instead of staying stuck on a wrong estimate.
  if (newTm > 0 && oldTm - newTm >= 2) return true;
  // SMALL backwards drift (1 min) inside the same period is the classic
  // v3-snapshot-vs-EV-stream race — reject it to avoid flicker.
  if (newTm > 0 && oldTm - newTm === 1) return false;
  return true;
}

/* True when the timer object carries a real Bet365 minute/period
 * (not our kickoff-derived fallback estimate). */
function _timerIsReal(t) {
  if (!t) return false;
  var md = String(t.md || t.MD || '');
  if (md === '1' || md === '3' || md === '2') return true;
  var tm = parseInt(t.tm || t.TM || 0, 10) || 0;
  return tm > 0;
}
/* Helper: in-place merge of m.timer with newTimer, respecting the
 * regression guard. Returns true if any field was updated.
 *
 * In addition to the standard guard, we also accept the new timer
 * if it's a large drop (≥ 4 min) within the same period — that
 * means our previous cached value was a bad spike and we should
 * snap back to the API's truth. */
function _mergeTimer(m, newTimer) {
  if (!m || !newTimer) return false;
  if (!_acceptTimerUpdate(m.timer, newTimer)) {
    // Large-drop snap-back: same period, new tm well below cached tm.
    if (m.timer) {
      var sameMd = String(m.timer.md || m.timer.MD || '') ===
                   String(newTimer.md || newTimer.MD || '');
      var oTm = parseInt(m.timer.tm || m.timer.TM || 0, 10) || 0;
      var nTm = parseInt(newTimer.tm || newTimer.TM || 0, 10) || 0;
      if (!(sameMd && nTm > 0 && oTm - nTm >= 4)) return false;
    } else {
      return false;
    }
  }
  if (JSON.stringify(m.timer) === JSON.stringify(newTimer)) return false;
  m.timer = newTimer;
  return true;
}

/* ── Merge real m.timer with kickoff fallback. Returns the timer
 * object the rest of the code should use — real data wins, but
 * we never leave the user staring at 00:00 for a live match.
 *
 * KEY FIX (b20260525bs): BetsAPI sometimes returns `tm = 0` for a
 * live match (its "I don't know" sentinel). We previously accepted
 * that as "real" data, which is why the match-detail clock kept
 * ticking from 00:00 even though the match was in HT or 60'+ already.
 * Now we treat tm=0 as missing UNLESS we can confirm via kickoff that
 * the match really is in its first minute. */
function effectiveTimer(m) {
  if (isMatchEnded(m)) return null;
  // ── 1) Real Bet365 timer always wins (Volume Plan: major leagues)
  if (m && m.timer) {
    var tmV = m.timer.tm;
    if (tmV === undefined || tmV === null) tmV = m.timer.TM;
    var tmn = parseInt(tmV, 10);
    var md  = String(m.timer.md || m.timer.MD || '');
    if (md === '1' || md === '2' || md === '3') return m.timer;
    if (!isNaN(tmn) && tmn > 0 && tmn < 130) return m.timer;
  }
  // ── 2) Last cached REAL timer from the live poll (same match)
  if (window._mdLastTimer && _timerIsReal(window._mdLastTimer)
      && window._mdMatch && String(window._mdMatch.id) === String(m.id || '')) {
    return window._mdLastTimer;
  }
  // ── 3) Kickoff-derived LAST-RESORT estimate (tagged with ~)
  //    Used only for low-coverage leagues where BetsAPI sends no timer.
  return computeFallbackTimer(m);
}

/* ── Build the inner HTML for the match-detail period+timer block.
 * Used both by initial render (renderMatchDetail) and by the live
 * 3s poll (patchMatchDetailLive). Lets the period text appear as
 * soon as we have a timer (even if the first match_detail payload
 * was timer-less). Format mirrors fcbet216 image 3:
 *   "1ère mi-temps | 43:42"   when running
 *   "Mi-temps"                 when halftime  */
function buildMdPeriodBlock(period, isHT, timerStr) {
  var out = '';
  if (isHT) {
    out += '<span class="md-card-timer md-card-timer--ht">Mi-temps</span>';
    return out;
  }
  if (period) {
    out += '<span class="md-card-period-txt">' + h(period) + '</span>';
    out += '<span class="md-card-period-sep">|</span>';
  }
  out += '<span class="md-card-timer" id="md-timer-display">' + h(timerStr || '00:00') + '</span>';
  return out;
}

/* ── Period detection (1ère mi-temps, 2ème mi-temps, etc.) ──
 * Volume Plan: strictly from the real Bet365 timer payload.
 * Returns '' when no real timer exists so the UI shows nothing
 * rather than a wrong period label. */
function getMatchPeriod(m) {
  if (!m) return '';
  var tmr = effectiveTimer(m);
  if (!tmr) return '';
  var md  = String(tmr.md || tmr.MD || '');
  var tm  = parseInt(tmr.tm || tmr.TM || 0) || 0;
  var sid = parseInt(m.sport_id || 1);
  if (md === '1') return 'Mi-temps';
  if (md === '2') return 'Pause';
  if (md === '3') return 'Prolongation';
  // Football (sport 1, 36) — derived from real tm value
  if (sid === 1 || sid === 36) {
    if (tm >= 0 && tm <= 45) return '1ère mi-temps';
    if (tm > 45 && tm <= 90) return '2ème mi-temps';
    if (tm > 90)             return 'Prolongation';
    return '';
  }
  // Basketball (18, 83)
  if (sid === 18 || sid === 83) {
    if (tm <= 12) return '1er quart';
    if (tm <= 24) return '2ème quart';
    if (tm <= 36) return '3ème quart';
    return '4ème quart';
  }
  if (sid === 13) return 'En jeu';
  if (sid === 91 && tmr.q) return 'Set ' + tmr.q;
  return '';
}

/* ── Live minute label for match cards (e.g. "45'" or "Mi-temps")
 * Prefers real Bet365 timer; falls back to kickoff-derived estimate
 * with "~" prefix for low-coverage leagues. */
function formatLiveMinute(m) {
  if (!m) return '';
  var t = effectiveTimer(m);
  if (!t) return isMatchLive(m) ? 'En cours' : '';
  var md = String(t.md || t.MD || '');
  if (md === '1') return 'Mi-temps';
  if (md === '2') return 'Pause';
  if (md === '3') return 'Prolong.';
  var tm = parseInt(t.tm || t.TM || 0, 10);
  if (!isNaN(tm) && tm >= 0 && tm < 130) {
    var prefix = t.estimated ? '~' : '';
    return prefix + tm + "'";
  }
  return isMatchLive(m) ? 'En cours' : '';
}

/* ── Live counting timer — ticks every 1s from the latest snapshot.
 * Prefers the real Bet365 timer; for low-coverage leagues where the API
 * never sends a timer, falls back to the kickoff-derived estimate and
 * tags the display with "~" so the user knows it's approximate. */
function startMatchTimer(m) {
  clearInterval(window._mdTimerInterval);
  if (!m || isMatchEnded(m)) return;
  var tmr = effectiveTimer(m);
  if (!tmr) return;
  var mdRaw = String(tmr.md || tmr.MD || '');
  if (mdRaw === '1' || mdRaw === '2') return;  // Mi-temps / Pause — no ticking

  var baseMin = parseInt(tmr.tm || tmr.TM || 0) || 0;
  var baseSec = parseInt(tmr.ts || tmr.TS || 0) || 0;
  // Don't tick from a completely empty (0,0) REAL snapshot — wait for
  // the next poll to bring real data. For estimated fallback we DO
  // count from 0 because that's the whole point of the fallback.
  var isEstimate = !!tmr.estimated;
  if (!isEstimate && baseMin === 0 && baseSec === 0) return;

  var totalBase = baseMin * 60 + baseSec;
  var t0 = Date.now();

  window._mdTimerInterval = setInterval(function() {
    var el = document.getElementById('md-timer-display');
    if (!el) { clearInterval(window._mdTimerInterval); return; }
    if (window._mdMatch && isMatchEnded(window._mdMatch)) {
      clearInterval(window._mdTimerInterval);
      el.textContent = 'Terminé';
      return;
    }
    var elapsed = Math.floor((Date.now() - t0) / 1000);
    var curr    = totalBase + elapsed;
    var mm      = Math.floor(curr / 60);
    var ss      = curr % 60;
    var clk = String(mm).padStart(2,'0') + ':' + String(ss).padStart(2,'0');
    el.textContent = isEstimate ? ('~' + clk) : clk;
  }, 1000);
}

/* ── Match detail live poll (score + timer + odds every 3s) ──
 * Fires one immediate fetch so the period name + minute appear
 * straight after click, then continues polling every 3s. */
function _mdPollOnce(mid) {
  if (S.viewMode !== 'matchDetail' || String(S.activeMatchId) !== String(mid)) return;
  // Force-bypass any browser / CDN / proxy cache — without this a
  // stale "0-1" response can be served for many seconds while
  // BetsAPI has already pushed the new "1-1" upstream.
  var url = BASE + 'sportsbook/api.php?action=match_live&match_id='
          + encodeURIComponent(mid) + '&_t=' + Date.now();
  fetch(url, { cache: 'no-store', headers: { 'Cache-Control': 'no-cache' } })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d || !d.success || !d.match) return;
      patchMatchDetailLive(d.match, d.markets || []);
    })
    .catch(function() {});
}
function startMatchDetailPoll(mid) {
  if (S._mdPollInterval) clearInterval(S._mdPollInterval);
  // Reset per-match high-water-marks so a different match's last
  // known timer/stats can't leak into this one.
  window._mdLastTimer = null;
  window._mdLastStats = null;
  window._mdLastTimerWasReal = false;
  window._mdHasRealMarkets = false;
  // Forget any previously expanded markets when switching match.
  S._mdMktState = {};
  // Fire one cycle right away so the timer/period populate without
  // a 3s blank-screen wait.
  _mdPollOnce(mid);
  // Fast detail polling for football (top European leagues), slower
  // for other sports to stay within BetsAPI rate limits. Backend is
  // pre-warmed by tick_live.php so 1s is safe for football detail.
  var detailMs = 1500;
  S._mdPollInterval = setInterval(function() {
    if (S.viewMode !== 'matchDetail' || String(S.activeMatchId) !== String(mid)) {
      clearInterval(S._mdPollInterval);
      S._mdPollInterval = null;
      return;
    }
    _mdPollOnce(mid);
  }, detailMs);
}

function patchMatchDetailLive(m, markets) {
  // ── Sticky stats: BetsAPI sometimes drops stats during the
  //    half-time transition or for a few seconds around a goal/card
  //    event. Counters never go backwards (5 yellow cards do not
  //    become 0), so we keep the highest per-key value we've seen.
  if (m) {
    var prevStats = window._mdLastStats || {};
    var newStats  = m.stats || {};
    var merged = {};
    Object.keys(prevStats).forEach(function(k) { merged[k] = prevStats[k]; });
    Object.keys(newStats).forEach(function(k) {
      var pv = prevStats[k], nv = newStats[k];
      function pairTot(v) {
        if (!v) return -1;
        var arr = Array.isArray(v) ? v : (typeof v === 'string' ? v.split(',') : [v[0], v[1]]);
        var a = parseInt(arr[0], 10) || 0, b = parseInt(arr[1], 10) || 0;
        return a + b;
      }
      // Take the side with the higher total, OR the new value if
      // it's the only one present.
      if (!pv) merged[k] = nv;
      else if (!nv) merged[k] = pv;
      else if (pairTot(nv) >= pairTot(pv)) merged[k] = nv;
      else merged[k] = pv;
    });
    if (Object.keys(merged).length) {
      m.stats = merged;
      window._mdLastStats = merged;
    } else if (window._mdLastStats) {
      m.stats = window._mdLastStats;
    }
  }
  window._mdMatch = m;

  // Ended match — freeze clock, keep score/stats fresh
  if (isMatchEnded(m)) {
    clearInterval(window._mdTimerInterval);
    var pEnd = document.getElementById('md-period-block');
    if (pEnd) {
      pEnd.innerHTML = '<span class="md-card-timer md-card-timer--ended">Terminé</span>';
    }
  }

  // Score — use sticky cache so a momentary empty m.ss during a poll
  // never flashes the score back to "0 : 0" on screen.
  var scoreEl = document.getElementById('md-score-display') || document.querySelector('.md-score-live');
  if (scoreEl) {
    var ssc = _sbReadScore(m);
    if (ssc[2]) { // we have a known score (current poll or sticky)
      scoreEl.textContent = ssc[0] + ' : ' + ssc[1];
    }
  }

  // Timer + period — skip ticking when match ended.
  // We apply the regression guard against the previously displayed
  // timer (window._mdLastTimer) so a momentary "tm:0" from upstream
  // never resets the on-screen clock to zero.
  if (!isMatchEnded(m)) {
    if (m.timer && _timerIsReal(m.timer)) {
      var acceptT = !window._mdLastTimer
        || _acceptTimerUpdate(window._mdLastTimer, m.timer)
        || !window._mdLastTimerWasReal; // real Bet365 timer replaces kickoff estimate
      if (acceptT) {
        window._mdLastTimer = m.timer;
        window._mdLastTimerWasReal = true;
      } else if (window._mdLastTimer) {
        m.timer = window._mdLastTimer;
      }
    } else if (window._mdLastTimer && _timerIsReal(window._mdLastTimer)) {
      m.timer = window._mdLastTimer;
    }
  }
  var effPatch = isMatchEnded(m) ? null : effectiveTimer(m);
  if (effPatch) {
    var mdNow   = String(effPatch.md || effPatch.MD || '');
    var isHTnow = (mdNow === '1');
    var pname  = getMatchPeriod(m);
    var tmStr  = String(effPatch.tm || effPatch.TM || '0').padStart(2, '0');
    var tsStr  = String(effPatch.ts || effPatch.TS || '0').padStart(2, '0');
    var clock  = tmStr + ':' + tsStr;
    // Mark estimated timers with "~" so the user knows it's not the
    // exact Bet365 clock (low-coverage leagues without a real timer).
    if (effPatch.estimated && !isHTnow) clock = '~' + clock;
    var pblock = document.getElementById('md-period-block');
    if (pblock) {
      pblock.innerHTML = buildMdPeriodBlock(pname, isHTnow, clock);
    } else {
  var timerEl = document.getElementById('md-timer-display');
      if (timerEl) timerEl.textContent = isHTnow ? 'Mi-temps' : clock;
    }
    if (isHTnow) {
      clearInterval(window._mdTimerInterval);
    } else {
      startMatchTimer(m);   // keep ticking, even past 90 (stoppage / ET)
    }
  }

  // Sync into every cached match list so carousel / cards stay fresh.
  sbSyncMatchCache(m);

  // Refresh stats bar (corners, yellow/red cards, attacks, shots on target)
  if (isMatchLive(m) || isMatchEnded(m)) {
    var sportId = parseInt(m.sport_id || 1);
    var newBar  = renderStatsBar(m, sportId);
    var oldBar  = document.querySelector('.md-card--compact .md-stats-bar') || document.querySelector('.md-stats-bar');
    if (oldBar && newBar) {
      var tmp = document.createElement('div');
      tmp.innerHTML = newBar;
      var fresh = tmp.firstElementChild;
      if (fresh) oldBar.replaceWith(fresh);
    }
  }

  // Refresh market odds. Two cases:
  //   1) API returned real markets → replace whole list.
  //   2) API returned no markets but live_odds changed → rebuild
  //      fallback markets from the updated match so the user sees
  //      the new odds (1x2 etc.) without reloading.
  var needsRender = false;
  var prevMarkets = window._mdMarkets || [];
  var nextMarkets = null;
  if (markets && markets.length) {
    // Merge separate "Goals Over/Under X" market groups into a single
    // normalised "Total" ladder and update m.live_odds.ou_line so the
    // fallback always anchors on the correct current Bet365 main line.
    nextMarkets = mergeOuMarkets(markets, m);
    window._mdHasRealMarkets = true;
    // ── Sticky markets: merge new with previous so a market that's
    // momentarily absent from one poll doesn't flicker out. We keep
    // any previously-seen market that isn't in the new tree.
    if (prevMarkets.length) {
      var newIds = {};
      nextMarkets.forEach(function(mk){
        var key = (mk.id || mk.name || '').toString().toLowerCase();
        newIds[key] = true;
      });
      prevMarkets.forEach(function(pm){
        var key = (pm.id || pm.name || '').toString().toLowerCase();
        if (!newIds[key]) nextMarkets.push(pm);  // preserve stale market
      });
    }
  } else if (window._mdHasRealMarkets && prevMarkets.length) {
    // Empty markets[] on one poll must NOT revert to static fallback —
    // keep the last real Bet365 market tree on screen.
    nextMarkets = null;
  } else if (m && m.live_odds) {
  }
  if (nextMarkets) {
    sbClearMdTabCache();
    // Diff: annotate each selection with _change ('up'|'down'|null)
    // and a flash class so renderMktBtn can show the ▲ / ▼ arrow.
    var prevIdx = {};
    prevMarkets.forEach(function(pm){
      (pm.selections || []).forEach(function(ps){
        var key = (pm.id || pm.name || '') + '|' + (ps.id || ps.name || '');
        var pv = parseFloat(ps.odds);
        if (!isNaN(pv) && pv > 1.01) prevIdx[key] = pv;
      });
    });
    nextMarkets.forEach(function(nm){
      (nm.selections || []).forEach(function(ns){
        var key = (nm.id || nm.name || '') + '|' + (ns.id || ns.name || '');
        var nv = parseFloat(ns.odds);
        var pv = prevIdx[key];
        if (pv && !isNaN(nv) && nv > 1.01 && Math.abs(nv - pv) >= 0.01) {
          ns._change = (nv > pv) ? 'up' : 'down';
        } else {
          ns._change = null;
        }
      });
    });
    window._mdMarkets = nextMarkets;
    needsRender = true;
    // ── Refresh bet slip BB legs with live odds ─────────────────
    // When polling brings new odds, every BB leg in the slip must
    // pick up the new value so the combined Bet Builder odds stay
    // accurate. Single bets are handled by their own updater.
    try { refreshBetSlipLegOdds(nextMarkets, m); } catch (e) {}
    // Markets list changed — re-prune tab visibility
    try { if (typeof sbMdPruneEmptyTabs === 'function') sbMdPruneEmptyTabs(); } catch(e) {}
  }
  if (needsRender) {
    // If the user is searching, preserve the search filter so live
    // odds refresh doesn't kick them back to "Tout".
    var searchPanel = document.getElementById('md-search-panel');
    var searchInput = document.getElementById('md-search-input');
    if (searchPanel && searchPanel.style.display !== 'none' && searchInput) {
      if (typeof window.sbMdSearch === 'function') window.sbMdSearch(searchInput.value);
      return;
    }
    var activeTab = document.querySelector('.md-tab.active');
    if (activeTab && typeof window.sbMdTab === 'function') {
      var tabName = activeTab.getAttribute('data-tab') || activeTab.textContent.trim();
      window.sbMdTab(activeTab, tabName);
    }
  }
}

/* ── Stats bar (goals, cards, corners, shots) ─────────── */
function renderStatsBar(m, sportId) {
  var st = m.stats || {};
  var s = m.ss ? m.ss.split('-') : [];

  // Validate one stat cell — must look like a small non-negative integer.
  // BetsAPI occasionally leaks event timestamps (e.g. "20260525143000") into
  // red_cards / yellow_cards arrays; this filter strips them client-side as
  // a defense-in-depth in case the server response slips one through.
  function _validCell(x) {
    if (x === null || x === undefined) return false;
    var s = String(x).trim();
    if (s === '') return false;
    if (!/^-?\d+$/.test(s)) return false;
    var n = parseInt(s, 10);
    return n >= 0 && n <= 999;
  }
  // Helper: get stat value — tries several API key variants because
  // BetsAPI / Bet365 feeds use inconsistent naming across leagues.
  function sv(key, idx, def) {
    if (!st) return def;
    var aliases = {
      yellow_cards: ['yellow_cards','yellowcard','yellow card','yellowcards','yellowcards'],
      red_cards:    ['red_cards','redcard','red card','redcards'],
      corners:      ['corners','corner','corner_kicks','corner kicks'],
      on_target:    ['on_target','on target','shots_on_target','shots on target','goal','goals','goal attempts'],
      dangerous_attacks: ['dangerous_attacks','dangerous attacks','attacks','attack'],
      attacks:      ['attacks','attack','dangerous_attacks']
    };
    var keys = aliases[key] || [key];
    for (var ki = 0; ki < keys.length; ki++) {
      var k = keys[ki];
      if (st[k] === undefined || st[k] === null || st[k] === '') continue;
      var v = st[k], pick;
      if (typeof v === 'string' && v.indexOf(',') !== -1) {
        var parts = v.split(',');
        pick = parts[idx] !== undefined ? String(parts[idx]).trim() : null;
      } else if (Array.isArray(v) && v[idx] !== undefined && v[idx] !== null) {
        pick = v[idx];
      } else if (v && typeof v === 'object' && v[idx] !== undefined && v[idx] !== null) {
        pick = v[idx];
      }
      if (pick !== undefined && pick !== null && pick !== '' && _validCell(pick)) {
        return pick;
      }
    }
    return def;
  }
  // Football — fcbet216 stat row reference (image 1):
  //   attacks ⚡ | yellow ▮ | red ▮ | corner flag | shots on target ◎
  // Falls back to '0' (not '-') when the API confirms the match is
  // live but the stat hasn't been seen yet — this matches fcbet216
  // which always shows numeric counters during a live match.
  if (sportId === 1) {
    var isLiveNow = m && (m.time_status === '1');
    var defaultVal = isLiveNow ? '0' : '-';
    function svL(k, i) {
      var v = sv(k, i, null);
      if (v === null || v === undefined || v === '') return defaultVal;
      return v;
    }
    // Stat icons — SVGs matching reference site exactly:
    //   ⚽ Goals | 🟨 Yellow | 🟥 Red | ⚑ Corner | 👟 Shots on target
    var icBall = '<svg class="md-si-ic md-si-ic--ball" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><polygon points="12,7 16,10 14.5,15 9.5,15 8,10" fill="currentColor" stroke="none"/></svg>';
    var icYC   = '<svg class="md-si-ic md-si-ic--yc"  width="9"  height="12" viewBox="0 0 9 12"><rect x="0" y="0" width="9" height="12" rx="1.6" ry="1.6" fill="#FACC15"/></svg>';
    var icRC   = '<svg class="md-si-ic md-si-ic--rc"  width="9"  height="12" viewBox="0 0 9 12"><rect x="0" y="0" width="9" height="12" rx="1.6" ry="1.6" fill="#EF4444"/></svg>';
    var icCor  = '<svg class="md-si-ic md-si-ic--cor" width="11" height="12" viewBox="0 0 16 16" fill="none"><line x1="3" y1="1" x2="3" y2="15" stroke="#ffffff" stroke-width="1.5"/><path d="M3 2 L13 4.5 L3 7 Z" fill="#22C55E"/></svg>';
    var icShot = '<svg class="md-si-ic md-si-ic--shot" width="11" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="8" r="6"/><line x1="8" y1="4" x2="8" y2="12"/><line x1="4" y1="8" x2="12" y2="8"/></svg>';
    // Goals derived from live score (m.ss) — always accurate
    var goalParts = m.ss ? String(m.ss).replace(/\s+/g,'').split(/[-:]/) : [];
    var goalH = goalParts[0] !== undefined && goalParts[0] !== '' ? goalParts[0] : (isLiveNow ? '0' : '-');
    var goalA = goalParts[1] !== undefined && goalParts[1] !== '' ? goalParts[1] : (isLiveNow ? '0' : '-');
    // shots_on_target with fallback to shots_total
    function svBest(primary, fallback, i) {
      var v = sv(primary, i, null);
      if (v !== null && v !== undefined && v !== '') return v;
      var v2 = sv(fallback, i, null);
      if (v2 !== null && v2 !== undefined && v2 !== '') return v2;
      return defaultVal;
    }
    return '<div class="md-stats-bar">'
      + mdStat(goalH,                                              icBall, goalA)
      + mdStat(svL('yellow_cards',0),                             icYC,   svL('yellow_cards',1))
      + mdStat(svL('red_cards',0),                                icRC,   svL('red_cards',1))
      + mdStat(svL('corners',0),                                  icCor,  svL('corners',1))
      + mdStat(svBest('shots_on_target','shots_total',0),         icShot, svBest('shots_on_target','shots_total',1))
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
  var isLive = m && isMatchLive(m);
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
  // userPickedSport tracks whether the user has actually clicked a sport tile.
  // On the HOME landing the nav strip shows NO active tile (image 2 spec) —
  // even though we fetch football matches for the initial list, the tile
  // only turns green after a real user click.
  userPickedSport: false,
  activeSportId: 1, activeAction: 'inplay',
  activeLeagueId: null, activeLeagueName: null, activeLeagueFlag: null,
  sportCounts: {}, sportLiveCounts: {},
  pollingInterval: null,
  activeUpcomingTab: 1,
  activeDateOffset: 0,  // 0 = today, 1 = tomorrow, etc.
  viewMode: 'main',     // 'main' | 'championship' — tracks which view is shown
  champMatches: [],     // matches shown in championship/league view
  activeMarketCat: 'populaire', // active market category in championship view
  mobLeagueTab: 'best',         // 'best' | 'my' — mobile inline league tabs
  homeLeagueFilter: null,       // filter "En direct maintenant" after league pick
  activeLiveCat: 'populaire',   // active market category for live section dropdown
  liveSearchQ: ''               // debounced EN DIRECT search filter
  ,allLiveLeagueNames: []       // multi-sport live leagues for top-league badges
  ,champGroupMode: 'league'     // 'league' | 'hour' — Par Ligue / Par Heure
  ,champMktAccOpen: false       // dropdown starts collapsed (fcbet216 UX)
  ,champLeagueCollapsed: {}     // per-league collapsed map in championship view
};

/* ── Navigation helpers — keep every view switch instant and race-free.
 * Problem: a home-list fetch started before the user clicked a match
 * could resolve AFTER navigation and repaint the home grid on top of
 * match detail (the "bounce back" bug). We fix that with:
 *   1) AbortController — cancel in-flight list/detail fetches on nav
 *   2) navGen token — late callbacks bail if a newer nav happened
 *   3) sbFindMatch — look up cached match data from every list source
 *   4) view guards on every render* entry point
 * ─────────────────────────────────────────────────────────────────── */
S.navGen = 0;
S._listAbort = null;
S._mdAbort   = null;

function sbAbortListFetches() {
  if (S._listAbort) { try { S._listAbort.abort(); } catch (e) {} }
  S._listAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
}
function sbAbortMdFetches() {
  if (S._mdAbort) { try { S._mdAbort.abort(); } catch (e) {} }
  S._mdAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
}
function sbNextNav() {
  S.navGen++;
  return S.navGen;
}
function sbNavAlive(gen) {
  return gen === S.navGen;
}
function sbFindMatch(mid) {
  if (!mid) return null;
  var id = String(mid);
  var lists = [S.matches, S.champMatches, S.sportPageLive, S.sportPageUpcoming];
  for (var i = 0; i < lists.length; i++) {
    if (!lists[i] || !lists[i].length) continue;
    for (var j = 0; j < lists[i].length; j++) {
      if (String(lists[i][j].id) === id) return lists[i][j];
    }
  }
  return null;
}
function sbSyncMatchCache(m) {
  if (!m || m.id == null) return;
  var id = String(m.id);
  [S.matches, S.champMatches, S.sportPageLive, S.sportPageUpcoming].forEach(function(list) {
    if (!list) return;
    var lm = list.find(function(x) { return String(x.id) === id; });
    if (!lm) return;
    if (m.ss !== undefined) lm.ss = m.ss;
    if (m.timer) lm.timer = m.timer;
    if (m.live_odds) { lm.live_odds = m.live_odds; lm._o = null; }
    if (m.time_status) lm.time_status = m.time_status;
    if (m.stats) lm.stats = m.stats;
  });
}
function sbClearMdTabCache() {
  window._mdTabCache = null;
}

/* ── SVG Icons (extracted from reference site shadow DOM) ─ */
var ICON = {
  lock: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
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
  football: '<svg width="20" height="20" viewBox="0 0 32 33" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M28.3514 5.82922C26.7793 3.9223 24.7691 2.39532 22.4814 1.37952C20.4987 0.499084 18.3093 0 16 0C13.6884 0 11.4969 0.500244 9.51263 1.38239C7.31567 2.35901 5.38202 3.81335 3.84009 5.61737C1.45081 8.4129 0 12.0343 0 16C0 16.5004 0.0298462 16.9937 0.0748291 17.4824C0.335938 20.3214 1.34015 22.9412 2.88916 25.1552C4.98395 28.1493 8.07538 30.3843 11.6843 31.3944C13.059 31.7792 14.5024 32 16 32C17.5426 32 19.0293 31.7697 20.4407 31.3623C24.095 30.3076 27.2152 27.9962 29.2873 24.913C30.9767 22.3996 31.9672 19.3801 31.9934 16.1301C31.9938 16.0863 32 16.0439 32 16C32 12.137 30.6307 8.59399 28.3514 5.82922ZM28.0334 8.88104C28.957 10.4362 29.585 12.181 29.8473 14.046L27.7114 11.9718L28.0334 8.88104ZM26.2687 6.51642L25.7321 11.6733L25.7321 11.6738L21.1324 13.7266L21.1319 13.7262L21.1316 13.7263L17.6975 11.2315L16.9996 10.7246V10.7245V6.01465L22.4458 3.58508C23.8868 4.33636 25.1751 5.33313 26.2687 6.51642ZM18.371 19.7656H18.3707H13.631H13.6295L12.1648 15.2572L12.1647 15.2568L12.1658 15.256L15.9996 12.4707L16.0002 12.4711L16.0004 12.4709L19.8366 15.2568L18.371 19.7656ZM16 2C17.3374 2 18.627 2.19989 19.8528 2.5517L15.9988 4.27026L12.142 2.55304C13.3693 2.20026 14.6607 2 16 2ZM9.54932 3.58752L14.9996 6.01465V10.7247V10.7256L10.8698 13.7266L10.8688 13.7261L6.28674 11.6816L5.89728 6.3338C6.95319 5.23071 8.18378 4.30011 9.54932 3.58752ZM4.06567 8.71814L4.30731 12.0401L2.05377 14.9377C2.22528 12.6704 2.93909 10.5575 4.06567 8.71814ZM4.10193 23.3414C3.12085 21.7574 2.4505 19.9675 2.16718 18.0488L5.64026 13.584L10.1783 15.6168L10.1813 15.6182L11.7872 20.5656L11.7906 20.5762L11.7901 20.5769L8.90881 24.209L8.90771 24.2088L4.10193 23.3414ZM5.93231 25.7047L8.68652 26.202L10.1224 28.689C8.54498 27.9553 7.12585 26.9425 5.93231 25.7047ZM16 30C14.9681 30 13.9649 29.88 12.996 29.6671L10.5258 25.389L10.525 25.3877L13.3883 21.7656H13.4005H18.6783L22.2565 25.4014L19.2382 29.6068C18.1968 29.8547 17.1162 30 16 30ZM22.6138 28.3339L24.158 26.182L26.0621 25.7109C25.057 26.752 23.8981 27.6424 22.6138 28.3339ZM28.0019 23.1707L23.8735 24.1921L23.8727 24.1924L20.2361 20.4972L20.236 20.4971L20.236 20.4969L20.4579 19.8145L21.8258 15.6074L26.4557 13.541L29.9526 16.9363C29.8013 19.2053 29.11 21.3233 28.0019 23.1707Z" fill="currentColor"/></svg>',
  basketball: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M29.991 23.764C31.851 20.408 32.425 16.53 31.691 12.854C31.682 12.803 31.665 12.757 31.655 12.709C31.551 12.214 31.425 11.724 31.274 11.239C31.027 10.512 30.864 10.075 30.677 9.644C29.867 7.802 28.767 6.136 27.316 4.685C25.995 3.365 24.495 2.341 22.901 1.579C20.268 0.435 18.135 0 16 0C14.846 0 13.695 0.138 12.563 0.386C9.525 1.102 6.865 2.504 4.682 4.686C4.082 5.286 3.557 5.931 3.073 6.596C-1.361 14.425 -0.455 22.178 4.682 27.314C6.172 28.804 7.887 29.927 9.714 30.707C9.739 30.726 9.775 30.736 9.808 30.75C10.066 30.858 10.329 30.949 10.592 31.043C11.377 31.309 11.744 31.412 12.114 31.504C13.401 31.824 14.699 32 16 32C17.75 32 19.495 31.697 21.169 31.127C21.747 30.921 22.038 30.812 22.325 30.688C24.197 29.867 25.865 28.766 27.319 27.313C27.872 26.76 28.359 26.168 28.814 25.559C29.155 25.103 29.464 24.641 29.746 24.166C29.826 24.033 29.914 23.902 29.991 23.764ZM22.468 3.582C22.834 8.876 22.39 13.07 21.428 16.453C17.872 15.532 16.818 10.954 15.799 6.514C15.423 4.878 15.064 3.352 14.587 2.072C15.054 2.024 15.525 2 16 2C18.29 2 20.494 2.551 22.468 3.582ZM6.097 6.1C7.918 4.28 10.157 3.039 12.593 2.432C13.091 3.669 13.475 5.327 13.851 6.96C14.894 11.509 16.179 17.09 20.807 18.357C20.533 19.087 20.233 19.772 19.911 20.421C13.62 17.261 8.388 12.542 4.424 8.133C4.91 7.418 5.463 6.734 6.097 6.1ZM6.097 25.899C5.2 25.003 4.466 24.008 3.864 22.957C5.049 23.321 6.354 23.577 7.874 23.747C11.764 24.182 14.471 24.812 16.361 25.502C14.89 27.052 13.302 28.274 11.759 29.341C9.646 28.674 7.708 27.51 6.097 25.899ZM25.903 25.899C24.884 26.917 23.734 27.755 22.494 28.406C22.395 27.95 22.218 27.471 21.913 26.983C21.41 26.178 20.642 25.462 19.609 24.828C20.027 24.273 20.431 23.686 20.813 23.059C22.676 23.883 24.62 24.571 26.642 25.083C26.404 25.36 26.165 25.637 25.903 25.899Z" fill="currentColor"/></svg>',
  tennis: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M4.686 27.314C7.699 30.327 11.615 31.887 15.562 31.994C16.22 32.012 16.878 31.99 17.534 31.927C21.121 31.577 24.587 30.04 27.314 27.314C30.04 24.587 31.577 21.121 31.924 17.561C32.011 16.465 32.009 15.617 31.995 15.562C31.887 11.615 30.327 7.699 27.314 4.686C23.792 1.165 19.037 -0.372 14.439 0.076C10.879 0.423 7.413 1.96 4.686 4.686C1.96 7.413 0.423 10.879 0.076 14.439C0.05 14.703 0.031 14.968 0.018 15.233C0 15.634 -0.005 16.036 0.006 16.438C0.113 20.385 1.673 24.301 4.686 27.314ZM6.1 25.9C0.633 20.432 0.633 11.568 6.1 6.1C8.311 3.89 11.077 2.573 13.95 2.15C13.401 8.412 8.412 13.401 2.15 13.95C2.052 14.618 2.002 15.293 2 15.967C9.488 15.474 15.474 9.487 15.967 2C19.561 1.992 23.158 3.358 25.9 6.1C28.641 8.843 30.008 12.439 30 16.033C22.512 16.526 16.526 22.512 16.033 30C12.439 30.008 8.842 28.641 6.1 25.9ZM18.05 29.85C18.599 23.588 23.588 18.599 29.85 18.05C29.427 20.923 28.11 23.689 25.9 25.9C23.689 28.11 20.923 29.427 18.05 29.85Z" fill="currentColor"/></svg>',
  volleyball: '<svg width="20" height="20" viewBox="0 0 32 33" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M31.7197 12.997C31.1405 9.952 29.6751 7.043 27.3175 4.686C24.8408 2.21 21.7529 0.732 18.541 0.218C17.6998 0.083 16.8505 0 16.0003 0C15.9343 0 15.8673 0.00899999 15.8012 0.00999999C13.0125 0.045 10.2377 0.813 7.76898 2.297C6.95775 2.785 6.18254 3.35 5.44933 3.994C5.19126 4.221 4.92919 4.441 4.68212 4.687C0.038814 9.329 -1.14752 16.112 1.10811 21.856C1.43121 22.679 1.83632 23.476 2.30145 24.247C2.95963 25.338 3.74085 26.374 4.68212 27.315C5.73441 28.367 6.90274 29.226 8.13809 29.924C8.98832 30.404 9.87157 30.798 10.7788 31.111C12.4683 31.693 14.2308 32.002 15.9983 32.002C20.0944 32.002 24.1906 30.44 27.3155 27.316C27.8006 26.831 28.2347 26.316 28.6448 25.787C29.204 25.066 29.7031 24.313 30.1222 23.528C31.3876 21.157 32.0138 18.536 31.9998 15.916C31.9958 14.937 31.9028 13.96 31.7197 12.997Z" fill="currentColor"/></svg>',
  tableTennis: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.7036 4.94642L15.5979 5.58787C15.8227 4.92406 16.0026 4.24842 16.1378 3.56566L14.1759 3.17725C14.0576 3.77482 13.9001 4.36591 13.7036 4.94642Z" fill="currentColor"/><path d="M12.0882 8.22114L13.75 9.33401C14.1421 8.74861 14.4913 8.14305 14.7977 7.52174L13.0041 6.63704C12.7362 7.18004 12.431 7.70933 12.0882 8.22114Z" fill="currentColor"/><path d="M10.9928 12.4742L9.67277 10.9717C9.90114 10.771 10.1247 10.5615 10.3431 10.3431C10.5615 10.1247 10.771 9.90114 10.9717 9.67277L12.4742 10.9928C12.2451 11.2535 12.0062 11.5085 11.7574 11.7574C11.5085 12.0062 11.2535 12.2451 10.9928 12.4742Z" fill="currentColor"/><path d="M3.56567 16.1378L3.17725 14.1759C3.77482 14.0576 4.36591 13.9001 4.94642 13.7036L5.58787 15.5979C4.92406 15.8227 4.24842 16.0026 3.56567 16.1378Z" fill="currentColor"/><path d="M7.52173 14.7977L6.63704 13.0041C7.18004 12.7362 7.70933 12.431 8.22114 12.0882L9.33401 13.75C8.74861 14.1421 8.14305 14.4913 7.52173 14.7977Z" fill="currentColor"/><path d="M28.4343 15.8622L28.8227 17.8241C28.2252 17.9424 27.6341 18.0999 27.0536 18.2964L26.4121 16.4021C27.0759 16.1773 27.7516 15.9974 28.4343 15.8622Z" fill="currentColor"/><path d="M24.4783 17.2023L25.363 18.9959C24.82 19.2638 24.2907 19.569 23.7789 19.9118L22.666 18.25C23.2514 17.8579 23.8569 17.5087 24.4783 17.2023Z" fill="currentColor"/><path d="M21.0072 19.5258C20.7465 19.7549 20.4915 19.9938 20.2426 20.2426C19.9938 20.4915 19.7549 20.7465 19.5258 21.0072L21.0283 22.3272C21.229 22.0989 21.4385 21.8753 21.6569 21.6569C21.8752 21.4385 22.0989 21.229 22.3272 21.0283L21.0072 19.5258Z" fill="currentColor"/><path d="M18.25 22.666L19.9118 23.7789C19.569 24.2907 19.2638 24.82 18.9959 25.363L17.2023 24.4783C17.5087 23.8569 17.8579 23.2514 18.25 22.666Z" fill="currentColor"/><path d="M16.4021 26.4121L18.2964 27.0536C18.0999 27.6341 17.9424 28.2252 17.8241 28.8227L15.8622 28.4343C15.9974 27.7516 16.1773 27.0759 16.4021 26.4121Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M15.5623 31.994C11.6146 31.8867 7.69916 30.3266 4.68629 27.3137C1.67343 24.3008 0.113313 20.3854 0.0059507 16.4377C-0.00497431 16.036 -0.000856392 15.6339 0.0183057 15.2325C0.0309485 14.9676 0.0501402 14.703 0.0758803 14.4389C0.42284 10.8789 1.95964 7.41294 4.68629 4.68629C7.41294 1.95964 10.8789 0.422841 14.4389 0.0758806C19.0369 -0.372253 23.792 1.16455 27.3137 4.68629C30.3266 7.69916 31.8867 11.6146 31.994 15.5623C31.9945 15.5802 31.995 15.5982 31.9954 15.6161C32.0109 16.265 31.9871 16.9146 31.9241 17.5611C31.5772 21.1211 30.0404 24.5871 27.3137 27.3137C24.5871 30.0404 21.1211 31.5772 17.5611 31.9241C16.8783 31.9895 16.2199 32.0119 15.5623 31.994ZM25.8995 25.8995C20.4322 31.3668 11.5678 31.3668 6.1005 25.8995C0.633165 20.4322 0.633164 11.5678 6.1005 6.1005C11.5678 0.633163 20.4322 0.633163 25.8995 6.1005C31.3668 11.5678 31.3668 20.4322 25.8995 25.8995Z" fill="currentColor"/></svg>',
  hockey: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M19.504 19.09C19.544 19.019 19.595 18.921 19.658 18.791C19.85 18.396 20.09 17.832 20.369 17.122C20.924 15.71 21.58 13.851 22.241 11.89C23.562 7.976 24.87 3.755 25.373 2.118C25.763 0.848 26.932 0 28.242 0H28.905C30.908 0 32.293 1.91 31.802 3.775C29.075 14.142 27.371 20.118 25.993 23.651C25.302 25.421 24.64 26.723 23.882 27.688C23.069 28.721 22.206 29.295 21.276 29.701C19.35 30.544 16.747 31.106 14.041 31.465C11.301 31.828 8.313 32 5.538 32C3.911 32 2.395 31.617 1.321 30.509C0.255 29.411 0 27.985 0 26.77V25.074C0 22.365 2.168 20.125 5.197 20.125C5.354 20.125 5.632 20.132 6.009 20.142C7.308 20.175 9.776 20.238 12.442 20.141C14.141 20.08 15.825 19.954 17.209 19.726C17.901 19.612 18.475 19.479 18.918 19.333C19.215 19.234 19.4 19.148 19.504 19.09ZM2 8C2 6.997 2.517 6.243 3.019 5.768C3.517 5.298 4.13 4.966 4.722 4.729C5.917 4.251 7.43 4 9 4C10.57 4 12.084 4.251 13.278 4.729C13.87 4.966 14.483 5.298 14.981 5.768C15.483 6.243 16 6.997 16 8V12.115C16 12.478 15.935 12.962 15.629 13.403C14.955 14.371 13.161 16 9 16C4.839 16 3.045 14.371 2.371 13.403C2.065 12.962 2 12.478 2 12.115V8Z" fill="currentColor"/></svg>',
  esports: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M29.5187 22.07H27.1606V19.78C27.1606 19.19 26.8684 18.9 26.294 18.9C25.7196 18.9 25.4274 19.19 25.4274 19.78V22.07H23.0693C22.4949 22.07 22.2128 22.35 22.2128 22.9C22.2128 23.45 22.4949 23.73 23.0693 23.73H25.4274V26.11C25.4274 26.7 25.7196 26.99 26.294 26.99C26.8684 26.99 27.1606 26.7 27.1606 26.11V23.73H29.5187C30.093 23.73 30.3752 23.45 30.3752 22.9C30.3752 22.35 30.093 22.07 29.5187 22.07Z" fill="currentColor"/><path d="M13.8791 7H17.9099C18.4642 7 18.9176 6.55 18.9176 6C18.9176 5.45 18.4642 5 17.9099 5H13.8791C13.3249 5 12.8714 5.45 12.8714 6C12.8714 6.55 13.3249 7 13.8791 7Z" fill="currentColor"/><path d="M7.83285 18C9.50247 18 10.856 16.6569 10.856 15C10.856 13.3431 9.50247 12 7.83285 12C6.16323 12 4.80973 13.3431 4.80973 15C4.80973 16.6569 6.16323 18 7.83285 18Z" fill="currentColor"/><path d="M23.9562 14C24.5127 14 24.9639 13.5523 24.9639 13C24.9639 12.4477 24.5127 12 23.9562 12C23.3996 12 22.9485 12.4477 22.9485 13C22.9485 13.5523 23.3996 14 23.9562 14Z" fill="currentColor"/><path d="M25.9716 16C26.5281 16 26.9793 15.5523 26.9793 15C26.9793 14.4477 26.5281 14 25.9716 14C25.415 14 24.9639 14.4477 24.9639 15C24.9639 15.5523 25.415 16 25.9716 16Z" fill="currentColor"/><path d="M21.9407 16C22.4973 16 22.9485 15.5523 22.9485 15C22.9485 14.4477 22.4973 14 21.9407 14C21.3842 14 20.933 14.4477 20.933 15C20.933 15.5523 21.3842 16 21.9407 16Z" fill="currentColor"/><path d="M30.2946 10.94C30.0628 9.32001 29.0854 7.90001 27.6544 7.09001L25.246 5.72001C23.2709 4.60001 20.7617 5.72001 20.3183 7.94001C20.1974 8.55001 19.6532 8.99001 19.0284 8.99001H12.7706C12.1458 8.99001 11.6016 8.55001 11.4807 7.94001C11.0273 5.73001 8.52814 4.60001 6.55304 5.72001L4.14462 7.09001C2.71368 7.90001 1.74628 9.32001 1.50443 10.94L0.0533321 20.98C-0.158286 22.46 0.264951 23.96 1.23235 25.11C3.55007 27.87 7.91344 27.58 9.82808 24.53L10.2715 23.83C10.9869 22.7 12.2365 22.01 13.5868 22.01H18.6657C19.2199 22.01 19.6734 21.56 19.6734 21.01C19.6734 20.46 19.2501 20.05 18.7261 20.02H13.5868C11.5412 20.01 9.6467 21.05 8.56845 22.77L8.12506 23.47C6.93597 25.36 4.22524 25.55 2.78422 23.83C2.18967 23.12 1.91759 22.19 2.04859 21.27L3.49969 11.22C3.64077 10.21 4.24539 9.33001 5.14225 8.82001L7.55067 7.45001C8.33668 7.01001 9.32423 7.45001 9.49554 8.33001C9.80793 9.88001 11.1784 10.99 12.7706 10.99H19.0284C20.6206 10.99 21.9911 9.88001 22.3035 8.33001C22.4849 7.45001 23.4724 7.01001 24.2484 7.45001L26.6568 8.82001C27.5436 9.32001 28.1482 10.21 28.2993 11.22L29.0249 16.16C29.1156 16.62 29.5086 16.98 29.9923 16.98C30.5465 16.98 31 16.53 31 15.98L30.2845 10.94H30.2946Z" fill="currentColor"/></svg>',
  handball: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M27.9101 4.2C27.4986 4.20425 27.0915 4.28566 26.7101 4.44C26.5427 3.77119 26.1565 3.17755 25.6129 2.75353C25.0693 2.32951 24.3995 2.09946 23.7101 2.1C23.2981 2.09947 22.8901 2.18106 22.5101 2.34C22.3411 1.67204 21.9546 1.07939 21.4113 0.655639C20.868 0.231891 20.199 0.00119935 19.5101 0C18.8211 0.00119935 18.1521 0.231891 17.6089 0.655639C17.0656 1.07939 16.679 1.67204 16.5101 2.34C16.1655 2.2034 15.8004 2.12565 15.4301 2.11C15.3701 2.11 15.3101 2.01 15.2401 1.97C13.6244 0.879611 11.7193 0.297967 9.77007 0.300001C7.17799 0.302652 4.69296 1.33421 2.86101 3.16803C1.02907 5.00185 4.81123e-05 7.48791 4.94673e-05 10.08C-0.00525944 11.7433 0.416836 13.3802 1.22582 14.8335C2.0348 16.2869 3.2036 17.5081 4.62011 18.38C4.85011 18.53 5.1101 18.64 5.3601 18.77L10.1401 27.57C10.8917 28.9418 11.9985 30.0862 13.3444 30.8832C14.6903 31.6802 16.2259 32.1005 17.7901 32.1H22.3401C24.6466 32.0974 26.858 31.1799 28.489 29.5489C30.12 27.9179 31.0374 25.7066 31.0401 23.4V7.3C31.0401 6.89037 30.9589 6.48479 30.8013 6.10671C30.6436 5.72863 30.4126 5.38555 30.1215 5.09729C29.8305 4.80904 29.4852 4.58132 29.1056 4.42731C28.7261 4.27329 28.3197 4.19604 27.9101 4.2ZM6.64006 16.88V16.69C6.62036 16.4045 6.7097 16.1222 6.89006 15.9C6.93738 15.8474 6.98747 15.7973 7.04009 15.75C7.09445 15.7021 7.15499 15.6618 7.22008 15.63C7.37974 15.5437 7.5586 15.499 7.7401 15.5C7.94419 15.4944 8.14583 15.5456 8.32244 15.648C8.49905 15.7505 8.64366 15.9 8.7401 16.08L12.3401 22.97C12.3728 23.0315 12.4131 23.0886 12.4601 23.14L12.5301 23.21C12.5891 23.2622 12.6527 23.3091 12.7201 23.35C12.7954 23.3936 12.8761 23.4273 12.9601 23.45C13.0864 23.4678 13.2148 23.4644 13.3401 23.44H13.4401H13.5701H13.6601L13.7601 23.39L13.8801 23.28L14.1001 22.95C14.1254 22.8858 14.1422 22.8186 14.1501 22.75C14.1549 22.6934 14.1549 22.6366 14.1501 22.58V5.2C14.1527 4.90907 14.2694 4.63079 14.4751 4.42507C14.6809 4.21934 14.9591 4.10261 15.25 4.1C15.5418 4.1 15.8216 4.21589 16.0279 4.42218C16.2342 4.62847 16.3501 4.90826 16.3501 5.2V14.3C16.3501 14.5652 16.4555 14.8196 16.643 15.0071C16.8305 15.1946 17.0849 15.3 17.3501 15.3C17.6153 15.3 17.8696 15.1946 18.0572 15.0071C18.2447 14.8196 18.3501 14.5652 18.3501 14.3V3.1C18.3501 2.80826 18.4659 2.52847 18.6722 2.32218C18.8785 2.11589 19.1583 2 19.4501 2C19.7418 2 20.0216 2.11589 20.2279 2.32218C20.4342 2.52847 20.5501 2.80826 20.5501 3.1V14.3C20.5501 14.5652 20.6554 14.8196 20.8429 15.0071C21.0305 15.1946 21.2849 15.3 21.5501 15.3C21.8153 15.3 22.0697 15.1946 22.2572 15.0071C22.4447 14.8196 22.5501 14.5652 22.5501 14.3V5.2C22.5527 4.90907 22.6694 4.63079 22.8752 4.42507C23.0809 4.21934 23.3591 4.10261 23.6501 4.1C23.9418 4.1 24.2216 4.21589 24.4279 4.42218C24.6342 4.62847 24.75 4.90826 24.75 5.2V14.3C24.75 14.5652 24.8554 14.8196 25.043 15.0071C25.2305 15.1946 25.4848 15.3 25.75 15.3C26.0153 15.3 26.2697 15.1946 26.4572 15.0071C26.6447 14.8196 26.75 14.5652 26.75 14.3V7.3C26.7527 7.00907 26.8694 6.73079 27.0751 6.52507C27.2808 6.31934 27.5592 6.20261 27.8501 6.2C28.1418 6.2 28.4216 6.31589 28.6279 6.52218C28.8342 6.72847 28.9501 7.00826 28.9501 7.3V23.44C28.9474 25.2161 28.2407 26.9188 26.9848 28.1747C25.7289 29.4306 24.0262 30.1374 22.25 30.14H17.7201C16.5159 30.1384 15.3341 29.8138 14.2981 29.1999C13.2621 28.5861 12.4098 27.7055 11.8301 26.65L6.73009 17.12C6.69181 17.0434 6.66162 16.9629 6.64006 16.88ZM8.00005 2.52C8.01527 2.79646 8.01527 3.07354 8.00005 3.35C8.00369 5.10218 7.41197 6.80362 6.32183 8.17539C5.23168 9.54716 3.70779 10.5079 2.00005 10.9C1.96845 10.6278 1.95177 10.354 1.95006 10.08C1.95491 8.32889 2.54964 6.63048 3.63823 5.25885C4.72683 3.88722 6.2458 2.92237 7.95006 2.52H8.00005ZM2.50005 12.85C4.64023 12.3479 6.54708 11.1352 7.90923 9.40984C9.27138 7.68445 10.0084 5.54827 10 3.35C10 3 10.0001 2.66 9.94005 2.35C11.0292 2.37355 12.1016 2.62209 13.0901 3.08C12.5231 3.65559 12.2067 4.43206 12.2101 5.24V18.44L10.4401 15.15C10.0538 14.425 9.39553 13.883 8.60991 13.643C7.8243 13.403 6.97552 13.4847 6.25005 13.87C6.16286 13.9133 6.07926 13.9634 6.00005 14.02H5.94005C5.86028 14.0678 5.78646 14.1249 5.72008 14.19C5.24588 14.5677 4.89723 15.0802 4.72008 15.66C4.72008 15.75 4.72008 15.83 4.72008 15.92C3.73564 15.0978 2.97245 14.0425 2.50005 12.85Z" fill="currentColor"/></svg>',
  badminton: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M31.5191 14.2128C31.499 13.9893 31.4043 13.779 31.2501 13.6159C31.0959 13.4527 30.8912 13.3462 30.6691 13.3134L25.8891 12.604L25.1391 7.44767C25.1088 7.22539 25.0045 7.01975 24.8431 6.86387C24.6816 6.70798 24.4724 6.61092 24.2491 6.58829L19.1091 6.11863L18.4091 1.33208C18.3767 1.11161 18.2716 0.908268 18.1103 0.754369C17.949 0.60047 17.7409 0.504846 17.5191 0.482692L12.2891 0.00303772C12.0794 -0.0133331 11.8699 0.0367588 11.6904 0.146192C11.5109 0.255625 11.3704 0.418829 11.2891 0.612599L3.58906 19.3891C3.10208 19.8151 2.65367 20.2832 2.24906 20.7881L1.82906 21.2577C0.657149 22.4435 0 24.0429 0 25.7095C0 27.3761 0.657149 28.9755 1.82906 30.1613C2.40453 30.7458 3.09107 31.2096 3.84838 31.5254C4.60568 31.8411 5.41848 32.0025 6.23906 32C7.05964 32.0025 7.87244 31.8411 8.62974 31.5254C9.38705 31.2096 10.0736 30.7458 10.6491 30.1613C10.7591 30.0514 10.9491 29.8915 11.1791 29.6916C11.6581 29.2934 12.1092 28.8627 12.5291 28.4026L19.6891 25.4047L31.3591 20.5382C31.5548 20.4585 31.7203 20.3189 31.8317 20.1394C31.9431 19.9598 31.9947 19.7496 31.9791 19.539L31.5191 14.2128ZM11.5191 17.9901L13.9991 20.4483L9.48906 23.7859L8.23906 22.5168L11.5191 17.9901ZM10.9191 25.1949L15.3791 21.8873L17.5691 24.0957L12.1191 26.374L10.9191 25.1949ZM23.9191 13.0336L15.5591 19.2492L12.6891 16.3513L18.6891 8.0972L23.2691 8.5169L23.9191 13.0336ZM12.8191 2.04157L16.5391 2.38132L17.1991 6.79814L11.2891 14.9423L8.65906 12.2842L12.8191 2.04157ZM7.81906 14.2428L10.0791 16.5311L8.99906 18.05L6.79906 21.0479L5.56906 19.7888L7.81906 14.2428ZM9.22906 28.6824C8.83527 29.0764 8.36762 29.3889 7.85286 29.6022C7.33809 29.8154 6.78631 29.9252 6.22906 29.9252C5.67181 29.9252 5.12003 29.8154 4.60526 29.6022C4.0905 29.3889 3.62285 29.0764 3.22906 28.6824C2.43566 27.8719 1.99138 26.7833 1.99138 25.6495C1.99138 24.5158 2.43566 23.4272 3.22906 22.6167C3.35906 22.4868 3.53906 22.287 3.73906 22.0671C3.93906 21.8473 4.11906 21.6275 4.33906 21.3976L6.22906 23.2962L8.67906 25.7845L10.4991 27.5832L9.86906 28.1428C9.59906 28.3826 9.36906 28.5824 9.24906 28.7123L9.22906 28.6824ZM19.5391 23.2363L16.9991 20.6881L25.2691 14.5426L29.6091 15.1821L29.9991 18.9394L19.5391 23.2363Z" fill="currentColor"/></svg>',
  mma: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.57024 17.24C4.98249 16.0832 4 13.6777 4 10.5237C4 6.02369 5 5.02368 5 5.02368C5 5.02368 6.80569 1.96639 14.5 0.523648C22.1943 -0.919098 24.5 1.02367 25 1.52368C25.5 2.02369 25.5 4.52368 25.5 8.52369C25.5 9.33263 25.4591 10.2019 25.3938 11.0741L28.5198 12.0119C29.9843 12.4513 31.0027 14.0118 30.418 15.6111C29.9369 16.9271 29.1857 18.4744 28.1171 19.8322C27.1826 21.0195 25.9744 22.1 24.4579 22.7206C24.1024 23.2274 23.5942 23.6193 23 23.8293V28C23 30.2091 21.2091 32 19 32H10C7.79086 32 6 30.2091 6 28V23.2361C5.38625 22.6868 5 21.8885 5 21V19C5 18.3425 5.21152 17.7344 5.57024 17.24ZM6.41156 6.67436C6.46926 6.48683 6.51922 6.36754 6.54992 6.30219L6.59329 6.25882L6.69583 6.08519C6.71636 6.05766 6.76947 5.98953 6.86222 5.89048C7.04692 5.69324 7.39611 5.36511 7.97609 4.97742C8.25797 4.789 8.59662 4.58503 9 4.37446V9C9 9.55228 9.44772 10 10 10C10.5523 10 11 9.55228 11 9V4C11 3.8377 10.9613 3.68443 10.8927 3.5489C11.7556 3.2339 12.7827 2.9296 14 2.66498V8C14 8.55228 14.4477 9 15 9C15.5523 9 16 8.55228 16 8V2.29613C17.1603 2.11826 18.1534 2.03169 19 2.00736V7C19 7.55228 19.4477 8 20 8C20.5523 8 21 7.55228 21 7V2.07784C21.3853 2.12107 21.7167 2.17924 21.9999 2.24432C22.6939 2.40381 23.1126 2.60752 23.3439 2.75282C23.3811 2.9885 23.4149 3.33426 23.4403 3.8163C23.4993 4.93855 23.5 6.50056 23.5 8.52369C23.5 10.3888 23.2642 12.6531 23.0177 14.4967C22.9436 15.0507 22.8694 15.5598 22.8022 16H7.19105C7.08932 15.781 6.97898 15.5248 6.8679 15.2345C6.43473 14.1026 6 12.4779 6 10.5237C6 8.41271 6.23563 7.24613 6.41156 6.67436ZM8 22H22C22.5523 22 23 21.5523 23 21V19C23 18.4477 22.5523 18 22 18H8C7.44772 18 7 18.4477 7 19V21C7 21.5523 7.44772 22 8 22ZM12 24H8V28C8 29.1046 8.89543 30 10 30H12C13.6569 30 15 28.6569 15 27C15 25.3431 13.6569 24 12 24ZM16.0004 30C16.6281 29.1643 17 28.1256 17 27C17 25.8744 16.6281 24.8357 16.0004 24H21V28C21 29.1046 20.1046 30 19 30H16.0004ZM24.5896 17.4844C24.7264 16.6734 24.9935 14.9959 25.2032 13.105L27.9451 13.9276C28.4773 14.0872 28.6706 14.5659 28.5396 14.9245C28.1093 16.1015 27.4492 17.4469 26.5455 18.5952C26.0838 19.1818 25.5691 19.704 25 20.1274V19C25 18.4471 24.8504 17.9292 24.5896 17.4844Z" fill="currentColor"/></svg>',
  trophy: '<svg width="20" height="20" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.4755 5.46353C16.3259 5.00287 15.6741 5.00287 15.5245 5.46353L14.7652 7.80041C14.6982 8.00642 14.5063 8.1459 14.2896 8.1459H11.8325C11.3481 8.1459 11.1468 8.76571 11.5386 9.05041L13.5265 10.4947C13.7017 10.622 13.7751 10.8477 13.7081 11.0537L12.9488 13.3906C12.7991 13.8512 13.3264 14.2343 13.7182 13.9496L15.7061 12.5053C15.8814 12.378 16.1186 12.378 16.2939 12.5053L18.2818 13.9496C18.6736 14.2343 19.2009 13.8512 19.0512 13.3906L18.2919 11.0537C18.2249 10.8477 18.2983 10.622 18.4735 10.4947L20.4614 9.05041C20.8532 8.76571 20.6519 8.1459 20.1675 8.1459H17.7104C17.4937 8.1459 17.3018 8.00642 17.2348 7.80041L16.4755 5.46353Z" fill="currentColor"/><path fill-rule="evenodd" clip-rule="evenodd" d="M5 3.29178C3.89589 3.621 2.87971 4.2208 2.05025 5.05025C0.737498 6.36301 0 8.14348 0 10C0 11.8565 0.737497 13.637 2.05025 14.9497C3.31026 16.2098 5.00116 16.9398 6.77661 16.9964C8.21065 19.1977 10.4114 20.8538 13 21.5859V26H8.33333C6.49238 26 5 27.4924 5 29.3333C5 30.8061 6.19391 32 7.66667 32H24.3333C25.8061 32 27 30.8061 27 29.3333C27 27.4924 25.5076 26 23.6667 26H19V21.5859C21.5886 20.8538 23.7894 19.1977 25.2234 16.9964C26.9988 16.9398 28.6897 16.2098 29.9497 14.9497C31.2625 13.637 32 11.8565 32 10C32 8.14349 31.2625 6.36301 29.9497 5.05025C29.1203 4.2208 28.1041 3.621 27 3.29178V0H5V3.29178ZM25 2H7V11C7 15.9706 11.0294 20 16 20C20.9706 20 25 15.9706 25 11V2ZM3.43438 6.43438C3.88887 5.97989 4.42118 5.62112 5 5.37104V11C5 12.3629 5.24786 13.6679 5.70095 14.8724C4.85091 14.6457 4.06721 14.1984 3.43438 13.5656C2.48872 12.62 1.95745 11.3374 1.95745 10C1.95745 8.66263 2.48872 7.38004 3.43438 6.43438ZM27 5.37104V11C27 12.3629 26.7521 13.6679 26.299 14.8724C27.1491 14.6457 27.9328 14.1984 28.5656 13.5656C29.5113 12.62 30.0425 11.3374 30.0425 10C30.0425 8.66263 29.5113 7.38004 28.5656 6.43438C28.1111 5.97989 27.5788 5.62112 27 5.37104ZM15 22H17V26H15V22ZM8.33333 28C8.21823 28 8.10654 28.0146 8 28.042C7.88174 28.0724 7.76983 28.1187 7.66667 28.1784C7.26813 28.4089 7 28.8398 7 29.3333C7 29.7015 7.29848 30 7.66667 30H24.3333C24.7015 30 25 29.7015 25 29.3333C25 28.8398 24.7319 28.4089 24.3333 28.1784C24.2302 28.1187 24.1183 28.0724 24 28.042C23.8935 28.0146 23.7818 28 23.6667 28H8.33333Z" fill="currentColor"/></svg>',
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

/** Live match — BetsAPI uses string or number time_status.
 *  Volume Plan: trust ONLY BetsAPI's explicit ended signals. The old
 *  elapsed-time heuristic (>=105 min + locked odds) was firing on
 *  matches that fcbet216 still showed as live (stoppage time / ET).
 *  We only fall back to a hard 4-hour staleness cutoff for matches
 *  BetsAPI literally forgets to flip — by then they're definitely done. */
function isMatchEnded(m) {
  if (!m) return false;
  if (String(m.time_status) === '3') return true;
  if (m.status === 'ended' || m.status === 'finished') return true;

  var kickoff = parseInt(m.time || 0, 10) || 0;
  if (kickoff > 0) {
    var elapsed = Date.now() / 1000 - kickoff;
    var sid     = parseInt(m.sport_id || 1, 10);

    // ONLY use a generous wall-clock fallback — never flag a match ended
    // based on elapsed minutes alone. Halftime pauses, extra time, and
    // delayed kicks all cause false positives. Trust BetsAPI's explicit
    // time_status=3 signal (above) as the primary source of truth.
    // Football absolute fallback: 5 hours from scheduled kickoff
    if ((sid === 1 || sid === 36) && elapsed >= 18000) return true;
    // Any other sport: 8 hours absolute max
    if (elapsed >= 28800) return true;
  }
  return false;
}
function isMatchLive(m) {
  if (!m || isMatchEnded(m)) return false;
  // Primary: API says live — always trust this
  if (String(m.time_status) === '1' || m.status === 'inplay') return true;
  // Secondary: start time has passed by more than 5 minutes — match has definitely started
  // We use a 5-minute grace window so a match at 20:00 doesn't show EN DIRECT at 19:59
  var startTs = parseInt(m.time || 0);
  if (startTs > 1000000000 && (Date.now() / 1000) >= (startTs + 300)) return true;
  return false;
}
function margin(v) { return Math.max(1.01, +(parseFloat(v) * (1 - MARGIN)).toFixed(2)); }
function rand(a, b) { return (a + Math.random() * (b - a)).toFixed(2); }

// Bet Builder combined odds with correlation discount (matches fcbet/Altenar)
function bbCombinedOdds(sels) {
  if (!sels || !sels.length) return 1.0;
  var raw = sels.reduce(function(acc, s) { return acc * (parseFloat(s.odds) || 1.0); }, 1.0);
  var corr = BB_CORR[sels.length] !== undefined ? BB_CORR[sels.length] : BB_CORR[4];
  return Math.max(1.01, parseFloat((raw * corr).toFixed(2)));
}

/* ── refreshBetSlipLegOdds ────────────────────────────────────────
 * Called from patchMatchDetailLive when new markets arrive. Walks the
 * bet slip, finds every leg whose selection ID matches one in the new
 * market tree, copies the fresh odds across, and recomputes the BB
 * combined value. Also tags each leg with _change ('up'|'down') so the
 * slip can flash the same way the market buttons do. */
function refreshBetSlipLegOdds(markets, m) {
  if (!Array.isArray(S.betSlip) || !S.betSlip.length || !markets || !markets.length) return;
  // Build a quick lookup: btnId -> { odds, name }
  // btnId format matches renderMktBtn: '<matchId>_md_<selId>'
  var matchIdStr = String((m && m.id) || '');
  var lookup = {};
  markets.forEach(function(mk) {
    (mk.selections || []).forEach(function(s) {
      var selKey = String(s.id != null ? s.id : (s.name != null ? s.name : ''));
      if (!selKey) return;
      var bid = matchIdStr + '_md_' + selKey;
      var v   = applyMargin(parseFloat(s.odds) || 0);
      if (v >= 1.01) lookup[bid] = { odds: v, name: s.name, market: mk.name || '' };
    });
  });
  var slipDirty = false;
  S.betSlip.forEach(function(b) {
    if (b.isBB && Array.isArray(b.legs)) {
      var legChanged = false;
      b.legs.forEach(function(leg) {
        var fresh = lookup[leg.id];
        if (!fresh) return;
        var prev = parseFloat(leg.odds);
        var nv   = parseFloat(fresh.odds);
        if (!isNaN(nv) && nv >= 1.01 && Math.abs(nv - prev) >= 0.01) {
          leg._change = nv > prev ? 'up' : 'down';
          leg.odds = nv;
          legChanged = true;
        }
      });
      if (legChanged) {
        var prevCombined = parseFloat(b.val);
        var newCombined  = parseFloat(bbCombinedOdds(b.legs).toFixed(2));
        if (!isNaN(prevCombined) && Math.abs(newCombined - prevCombined) >= 0.01) {
          b._change = newCombined > prevCombined ? 'up' : 'down';
        }
        b.val = newCombined;
        b._origVal = newCombined;
        slipDirty = true;
      }
    } else if (b.matchId && String(b.matchId) === matchIdStr) {
      // Single bet on a market in this match — keep its odds fresh too.
      var fresh2 = lookup[b.id];
      if (fresh2) {
        var pv2 = parseFloat(b.val);
        var nv2 = parseFloat(fresh2.odds);
        if (!isNaN(nv2) && nv2 >= 1.01 && Math.abs(nv2 - pv2) >= 0.01) {
          b._change = nv2 > pv2 ? 'up' : 'down';
          b.val = nv2;
          b._origVal = nv2;
          slipDirty = true;
        }
      }
    }
  });
  if (slipDirty) {
    try { renderBetSlip(); } catch (e) {}
    try { updateFloatingBetBadge(); } catch (e) {}
  }
}

// Stable seeded random — same match always shows same odds
/* seedRand removed — Volume Plan uses 100% real Bet365 API odds.
 * No fake/seeded values anywhere in the codebase. */

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
    var keys = ['live','updated','init','start','end'];
    for (var i = 0; i < keys.length; i++) {
      parsed = _parseOddsFlat(oddsObj[keys[i]]);
      if (parsed) break;
    }
    // Try flat format: {"1":"2.01","X":"3.55","2":"4.23"} and "end" key
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
  return null;  // no real odds available from Bet365 stream yet
}

function _parseOddsFlat(o) {
  if (!o || typeof o !== 'object') return null;
  var h = parseFloat(o['1'] || o.home || o.h || 0);
  var x = parseFloat(o['X'] || o.x || o.draw || 0);
  var a = parseFloat(o['2'] || o.away || o.a || 0);
  // Only require h (home odds) — x can be null for 2-way sports (basketball, tennis)
  if (!(h > 1.01)) return null;
  return { h: h, x: (x > 1.01 ? x : null), a: (a > 1.01 ? a : null) };
}

/* Return raw BetsAPI odds without arbitrary reduction */
function applyMargin(rawOdd) {
  var val = parseFloat(rawOdd);
  return isNaN(val) ? 0 : val;
}

// Map sidebar display names → EXACT DB search terms (avoids cross-country false matches)
// e.g. 'Premier League' must be 'England Premier League' to exclude Australian leagues
var LEAGUE_DB_SEARCH = {
  // Football top leagues — MUST match EXACT league_name stored in DB
  'Coupe du Monde 2026':   'World Cup 2026',          // DB stores "World Cup 2026"
  'Ligue des Champions':   'UEFA Champions League',
  'Ligue Conférence':      'UEFA Conference League',  // DB: "UEFA Conference League"
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
    'monde':              ['fifa world cup', 'world cup 2026', 'world cup'],
    'world cup':          ['fifa world cup', 'world cup 2026', 'world cup'],
    'coupe du monde':     ['fifa world cup', 'world cup 2026', 'world cup'],
    'serie a':            ['italy serie a'],
    'bundesliga':         ['germany bundesliga'],
    'ligue 1':            ['france ligue 1'],
    'eredivisie':         ['netherlands eredivisie'],
    'copa libertadores':  ['copa libertadores'],
    'copa sudamericana':  ['copa sudamericana'],
    'euroligue':          ['euroleague', 'eurocup'],
    'nba':                ['nba'],
    'roland garros':      ['roland garros', 'french open'],
    'féminin':            ['french open women', 'roland garros women', 'wta french open', 'roland garros, féminin'],
    'hommes':             ['french open men', 'roland garros men', 'atp french open', 'roland garros, hommes'],
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
// Coerce league/home/away name fields to strings (odds-api.io can return IDs as numbers or objects)
function _safeNameStr(v) {
  if (!v && v !== 0) return '';
  if (typeof v === 'string') return v;
  if (typeof v === 'number') return String(v);
  if (typeof v === 'object') return String(v.name || v.title || v.long_name || '') || '';
  return String(v);
}
function normalizeMatch(m) {
  if (!m) return m;
  if (m.league) m.league.name = _safeNameStr(m.league.name);
  if (m.home)   m.home.name   = _safeNameStr(m.home.name);
  if (m.away)   m.away.name   = _safeNameStr(m.away.name);
  return m;
}

function stripCountryPrefix(leagueName) {
  if (!leagueName) return '';
  var n = typeof leagueName === 'string' ? leagueName : String(leagueName);
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
  var n = (typeof l === 'string' ? l : String(l)).toLowerCase().trim();
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
  var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
  var tid = ctrl ? setTimeout(function() { ctrl.abort(); }, 8000) : null;
  var opts = ctrl ? { signal: ctrl.signal } : {};
  fetch(BASE + 'sportsbook/api.php?action=counts', opts)
    .then(function(r) { if (!r.ok) throw new Error('counts'); return r.json(); })
    .then(function(d) {
      if (d && d.counts) {
        Object.keys(d.counts).forEach(function(sid) {
          var val = d.counts[sid];
          if (typeof val === 'object' && val !== null) {
            S.sportCounts[parseInt(sid)] = val.total || 0;
            S.sportLiveCounts[parseInt(sid)] = val.live || 0;
          } else {
            S.sportCounts[parseInt(sid)] = val;
            S.sportLiveCounts[parseInt(sid)] = val;
          }
        });
        renderSidebar();
        renderSportNav();
        refreshLiveTopLeagues();
      }
    })
    .catch(function() {
      SPORTS.forEach(function(sp) {
        if (!S.sportCounts[sp.id]) S.sportCounts[sp.id] = 0;
      });
      renderSidebar();
      renderSportNav();
      refreshLiveTopLeagues();
    })
    .finally(function() { if (tid) clearTimeout(tid); });
}

/* ── Apply live_refresh payload to in-memory match list ── */
function applyLiveRefresh(ids, targetList) {
  if (!ids || !ids.length || !targetList) return Promise.resolve(false);
  return fetch(BASE + 'sportsbook/api.php?action=live_refresh&ids=' + ids.join(',') + '&_t=' + Date.now(),
               { cache: 'no-store', headers: { 'Cache-Control': 'no-cache' } })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d || !d.refreshed) return false;
      var updated = false;
      Object.keys(d.refreshed).forEach(function(mid) {
        var upd = d.refreshed[mid];
        var m = targetList.find(function(x) { return String(x.id) === String(mid); });
        if (!m) return;
        if (upd.ss !== undefined && upd.ss !== null && m.ss !== upd.ss
            && _acceptScoreUpdate(m.ss, upd.ss)) {
          m.ss = upd.ss;
          updated = true;
        }
        if (upd.timer && _mergeTimer(m, upd.timer)) {
          updated = true;
        }
        if (upd.live_odds && upd.live_odds.h) {
          m.live_odds = upd.live_odds;
          m._o = null;
          updated = true;
          // Propagate fresh odds into any active bet slip leg pointing at
          // this match — sets _change so the slip can show up/down arrows
          // and the "cotes ont changées" banner stays accurate.
          if (typeof syncSlipOddsFromMatch === 'function') {
            syncSlipOddsFromMatch(m);
          }
        }
        if (upd.time_status && m.time_status !== upd.time_status) {
          m.time_status = upd.time_status;
          updated = true;
        }
        if (upd.stats) {
          m.stats = upd.stats;
          updated = true;
        }
      });
      return updated;
    })
    .catch(function() { return false; });
}

/* ── Update odds in the bet slip when a match's live_odds changes.
   We look at each bet that references this match (b.matchId) and try
   to re-resolve its odds value from match.live_odds. Sets b._change
   to 'up' or 'down' so the UI can show coloured arrows + flash. ── */
function syncSlipOddsFromMatch(match) {
  if (!match || !match.live_odds || !S.betSlip || !S.betSlip.length) return;
  var lo = match.live_odds;
  var changed = false;
  S.betSlip.forEach(function(b) {
    if (!b || !b.matchId) return;
    if (String(b.matchId) !== String(match.id)) return;
    var newVal = null;
    var mkt = (b.market || '1x2').toLowerCase();
    var sel = String(b.sel || '').trim();
    if (mkt === '1x2' || mkt === '' || mkt === '1 x 2') {
      if (sel === '1' || /home/i.test(sel)) newVal = parseFloat(lo.h);
      else if (sel === 'x' || sel === 'X' || /draw/i.test(sel)) newVal = parseFloat(lo.x);
      else if (sel === '2' || /away/i.test(sel)) newVal = parseFloat(lo.a);
    } else if (/total|over|under|o\/u/i.test(mkt)) {
      if (/over|plus|\+/i.test(sel)) newVal = parseFloat(lo.ou_over);
      else if (/under|moins|\-/i.test(sel)) newVal = parseFloat(lo.ou_under);
    }
    if (newVal && !isNaN(newVal) && Math.abs(newVal - b.val) > 0.001) {
      b._change = newVal > b.val ? 'up' : 'down';
      b._prevVal = b.val;
      b.val = newVal;
      b.isLive = true;
      changed = true;
    }
  });
  if (changed) {
    // Re-render slip with new odds + arrows
    try { renderBetSlip(); } catch (e) {}
  }
}

/* ── Real-time polling: updates scores, odds, timer, time_status ───────────
   Polls every 20s. Works for both main view (sport list) and championship view.
   Updates ALL live match fields — not just score.
   ─────────────────────────────────────────────────────────────────────────── */
function startPolling() {
  if (S.pollingInterval) clearInterval(S.pollingInterval);

  function doPoll() {
    // Match detail page uses dedicated faster poll (startMatchDetailPoll)
    if (S.viewMode === 'matchDetail') return;
    // Sport page has its own light refresh path (sbPollSportPage) that
    // only re-renders the matches list, not the four category cards.
    if (S.viewMode === 'sportPage') {
      if (typeof sbPollSportPage === 'function') sbPollSportPage();
      return;
    }
    // Period page is for future dates — no live polling needed.
    if (S.viewMode === 'periodPage') return;

    var url, targetList, isChamp, liveRefreshP = Promise.resolve(false);
    // Snapshot view mode at poll start so we can DROP late responses that
    // arrive after the user navigated away. This stops the bug where
    // clicking a league briefly opens its page, then the in-flight main
    // poll response paints renderMatches() back over it.
    var startView   = S.viewMode;
    var startLeague = S.activeLeagueId;
    var startSport  = S.activeSportId;
    var startTab    = S.activeTab;
    var startAction = S.activeAction;
    var startDate   = S.activeDateOffset;

    if (S.viewMode === 'championship' && S.activeLeagueName) {
      // Championship view: poll league_matches endpoint for this league
      var searchTerm = LEAGUE_DB_SEARCH[S.activeLeagueName] || S.activeLeagueName;
      url = BASE + 'sportsbook/api.php?action=league_matches&sport_id=' + S.activeSportId
          + '&league=' + encodeURIComponent(searchTerm)
          + (S.activeLeagueId ? '&league_id=' + encodeURIComponent(S.activeLeagueId) : '');
      targetList = S.champMatches;
      isChamp = true;

      // For live matches in championship view, trigger direct BetsAPI refresh
      var liveCap = (parseInt(S.activeSportId || 0, 10) === 1) ? 24 : 12;
      var liveIds = S.champMatches
        .filter(isMatchLive)
        .map(function(m) { return m.id; })
        .slice(0, liveCap);
      if (liveIds.length) {
        liveRefreshP = applyLiveRefresh(liveIds, S.champMatches);
      }
    } else {
      // Main inplay view — refresh visible live matches every poll cycle
      if (S.activeAction === 'inplay' || S.activeDateOffset === 0) {
        var mainCap = (parseInt(S.activeSportId || 0, 10) === 1) ? 24 : 12;
        var liveIdsMain = S.matches
          .filter(isMatchLive)
          .map(function(m) { return m.id; })
          .slice(0, mainCap);
        if (liveIdsMain.length > 0) {
          liveRefreshP = applyLiveRefresh(liveIdsMain, S.matches);
        }
      }
      // Main view: poll inplay or upcoming based on USER-PICKED date.
      // IMPORTANT: never silently flip activeDateOffset back to 0 — the
      // user explicitly chose that date and the previous heuristic
      // ("a match's start time has passed") was firing on day-N matches
      // that had been loaded into S.matches, causing the date row to
      // snap back to TODAY mid-poll. We respect the user's selection;
      // if a match goes live on a future date, the user can re-pick
      // "today" or "EN DIRECT" themselves.
      var apiAction = (S.activeDateOffset > 0) ? 'upcoming' : 'inplay';
      url = BASE + 'sportsbook/api.php?action=' + apiAction + '&sport_id=' + S.activeSportId;
      targetList = S.matches;
      isChamp = false;
    }
    // Force-bypass browser / CDN caches for live data — append a
    // millisecond timestamp so every poll hits PHP fresh.
    url += (url.indexOf('?') === -1 ? '?' : '&') + '_t=' + Date.now();

    // Stale-response guard: if any of the navigation-defining keys
    // changed between poll start and now, the user navigated and we
    // must NOT touch the DOM. This is what previously caused the
    // "click league -> bounce back to live" flash.
    function navChanged() {
      return S.viewMode      !== startView
          || S.activeLeagueId !== startLeague
          || S.activeSportId !== startSport
          || S.activeTab     !== startTab
          || S.activeAction  !== startAction
          || S.activeDateOffset !== startDate;
    }

    liveRefreshP.then(function(refreshedEarly) {
    if (navChanged()) return;
    // Render IMMEDIATELY after live_refresh — scores / odds / timers
    // update in real time instead of waiting for the slower inplay fetch
    // (which can take 1–2s). This keeps the live cards in sync with
    // BetsAPI within the 3s poll cadence.
    if (refreshedEarly) {
      try {
        if (isChamp) {
          renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.champMatches);
        } else {
          renderMatches(S.matches);
          markLiveSidebarLeagues(S.matches);
        }
      } catch (e) {}
    }
    fetch(url, { cache: 'no-store', headers: { 'Cache-Control': 'no-cache' } })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        // CRITICAL: If the user navigated away while this poll fetch
        // was in flight, do NOT render — that paints the wrong view
        // on top and makes the page look like it "redirected back".
        if (S.viewMode === 'matchDetail' || S.viewMode === 'sportPage') return;
        if (navChanged()) return;
        if (!d || !d.results) {
          if (refreshedEarly && !navChanged()) {
            if (isChamp) {
              renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.champMatches);
            } else {
              renderMatches(S.matches);
              markLiveSidebarLeagues(S.matches);
            }
          }
          return;
        }
        // Always filter out finished matches from fresh API response
        var newResults = d.results.filter(function(m) { return m.time_status !== '3'; }).map(normalizeMatch);
        var updated = false;

        // If list count changed significantly, do a full refresh (new matches went live, etc.)
        if (!isChamp && Math.abs(newResults.length - targetList.length) >= 3) {
          if (navChanged()) return;
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

          // ── Score update (regression-guarded) ─────────────────────
          // Goals don't "un-happen". The slower /inplay snapshot can
          // arrive with a stale score AFTER /live_refresh already wrote
          // the fresher one (e.g. 1-1 → 0-1). Only accept a new score
          // if it has the same OR more total goals than what we have.
          if (m.ss !== nm.ss && _acceptScoreUpdate(m.ss, nm.ss)) {
            m.ss = nm.ss; updated = true;
          }

          // ── Live odds update (accept when values or timestamp changed) ──
          var newOdds = nm.live_odds;
          if (newOdds && newOdds.h) {
            var oldOdds = m.live_odds || {};
            var newTs = parseInt(newOdds.ts || 0);
            var oldTs = parseInt(oldOdds.ts || 0);
            var oddsChanged = oldOdds.h !== newOdds.h || oldOdds.x !== newOdds.x || oldOdds.a !== newOdds.a
              || oldOdds.ou_over !== newOdds.ou_over || oldOdds.ou_under !== newOdds.ou_under
              || oldOdds.ou_line !== newOdds.ou_line;
            if (oddsChanged || newTs >= oldTs) {
              m.live_odds = newOdds;
              m._o = null;
              updated = true;
            }
          }

          // ── Timer update (regression-guarded) ─────────────────────
          if (nm.timer && _mergeTimer(m, nm.timer)) {
            updated = true;
          }

          // ── Status update (match going live) ──────────────────────
          if (m.time_status !== nm.time_status) {
            m.time_status = nm.time_status;
            m._o = null;
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

        // Guard one more time before re-rendering — in case the user
        // clicked a match / switched view between the score update
        // step above and now.
        if (navChanged()) return;

        if (isChamp) {
          renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.champMatches);
        } else {
          renderMatches(S.matches);
          markLiveSidebarLeagues(S.matches);
        }
      })
      .catch(function() {
        if (navChanged()) return;
        if (refreshedEarly) {
          if (isChamp) {
            renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.champMatches);
          } else {
            renderMatches(S.matches);
            markLiveSidebarLeagues(S.matches);
          }
        }
      });
    });
  }

  // Faster live updates for minimal delay (football stricter). The
  // tick_live.php daemon already pre-warms our local cache every 2s,
  // so polling our own backend at 1.5s for football is fine and gives
  // the user a sub-2-second perceived delay end-to-end.
  var isFootball = (parseInt(S.activeSportId || 0, 10) === 1);
  var isLiveView = (S.activeAction === 'inplay') || (S.viewMode === 'championship' && S.champMatches && S.champMatches.some(function(m) { return m.time_status === '1'; }));
  var pollMs = isLiveView ? (isFootball ? 1500 : 2500) : 10000;
  S.pollingInterval = setInterval(function() {
    doPoll();
    refreshLiveTopLeagues();
  }, pollMs);
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
    var liveCnt = S.sportLiveCounts[sp.id] || 0;
    var isLive = liveCnt > 0;
    // Show live count when live; otherwise show total. Cap at 999+.
    var cnt = isLive ? liveCnt : (S.sportCounts[sp.id] || '');
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
  // fcbet216 nav only shows sports that have a proper icon — the generic
  // "person silhouette" placeholder (ICON.default) is filtered out so the
  // strip never renders the unknown-sport tiles (Baseball, Auto-moto,
  // Cricket, etc.) the user flagged in image 3.
  SPORTS.filter(function(sp){ return sp.icon && sp.icon !== ICON.default; }).forEach(function(sp) {
    // ONLY mark a tile active once the user has actually clicked something —
    // home page lands with no tile highlighted, matching the fcbet216 reference.
    var active = S.userPickedSport && (S.activeSportId === sp.id && !S.activeLeagueId && S.activeDateOffset === 0);
    out += '<button class="sb-sport-item' + (active ? ' active' : '') + '" data-sid="' + sp.id + '" onclick="window.sbOpenSportPage(' + sp.id + ')">';
    out += '<div class="sb-sport-icon">' + sp.icon + '</div>';
    out += '<span class="sb-sport-lbl">' + h(sp.name) + '</span>';
    out += '</button>';
  });
  el.innerHTML = out;

  // Mark Streaming/EnDirect buttons inactive since sports are now selected
  document.querySelectorAll('.sb-nav-streaming,.sb-nav-endirect,.sb-nav-all').forEach(function(b) {
    b.classList.remove('active');
  });
  // Refresh chevron arrow visibility now that the tiles are in place
  if (typeof _sbUpdateNavArrows === 'function') {
    setTimeout(_sbUpdateNavArrows, 0);
    setTimeout(_sbUpdateNavArrows, 200);
  }
}

/* ── Match Rendering ─────────────────────────────────────── */
function renderMatchGroups(matches, out, opts) {
  opts = opts || {};
  var showLeagueHeader = (opts.showLeagueHeader !== false);
  var collapsible     = !!opts.collapsible;
  var defaultOpenN    = (typeof opts.defaultOpenCount === 'number') ? opts.defaultOpenCount : 999;
  // Strip ended matches before rendering — they must never appear in
  // any live or upcoming list regardless of what the API reports.
  matches = (matches || []).filter(function(m) { return !isMatchEnded(m); });
  var groups = {}, order = [];
  matches.forEach(function(m) {
    var k = (m.league && m.league.name) ? m.league.name : 'Autre championnat';
    if (!groups[k]) { groups[k] = []; order.push(k); }
    groups[k].push(m);
  });
  order.forEach(function(lg, lgIdx) {
    if (showLeagueHeader) {
      var country = guessCountry(lg);
      var flag = getFlag(country);
      var countryLabel = (country && country !== 'International') ? (' · ' + h(country)) : '';
      var isCollapsed = collapsible && (lgIdx >= defaultOpenN);
      var headerOnclick = collapsible ? ' onclick="window.sbToggleLeagueAcc(this)"' : '';
      out += '<div class="sb-league-block' + (isCollapsed ? ' collapsed' : '') + '">';
      out += '<div class="sb-league-section-hdr' + (collapsible ? ' sb-league-section-hdr--toggle' : '') + '"' + headerOnclick + '>';
      out += '<span class="sb-lh-star" onclick="event.stopPropagation()">' + ICON.star + '</span>';
      out += '<img src="' + flag + '" class="sb-league-f" onerror="this.src=\'https://flagcdn.com/w20/un.png\'">';
      out += '<span class="sb-league-n">' + h(stripCountryPrefix(lg) || lg) + countryLabel + '</span>';
      out += '<span class="sb-lh-bb">BB</span>';
      out += '<div class="sb-lh-icons">' + (isCollapsed ? ICON.chevronDown : ICON.minus) + '</div>';
      out += '</div>';
      out += '<div class="sb-league-matches"' + (isCollapsed ? ' style="display:none"' : '') + '>';
    }
    groups[lg].forEach(function(m) { out += matchCard(m); });
    if (showLeagueHeader) out += '</div></div>';
  });
  return out;
}

/* Click handler for collapsible league accordions on the EN DIRECT tab. */
window.sbToggleLeagueAcc = function(headerEl) {
  if (!headerEl) return;
  var block = headerEl.parentElement; // .sb-league-block
  if (!block) return;
  var body = block.querySelector('.sb-league-matches');
  var icon = headerEl.querySelector('.sb-lh-icons');
  var nowCollapsed = !block.classList.contains('collapsed');
  block.classList.toggle('collapsed', nowCollapsed);
  if (body) body.style.display = nowCollapsed ? 'none' : '';
  if (icon) icon.innerHTML = nowCollapsed ? ICON.chevronDown : ICON.minus;
};

// showMarkets=false for live section (pills only), true for upcoming (pills + market selectors)
/* ── Live section market-type dropdown (matches fcbet "En direct maintenant") ── */
var LIVE_MKT_OPTIONS = [
  {key: 'populaire',    label: '1x2'},
  {key: 'total',        label: 'Total'},
  {key: 'double_chance',label: 'Double chance'},
  {key: 'btts',         label: 'Les deux équipes qui marquent'},
  {key: 'handicap',     label: 'Handicap'},
  {key: '1x2_ht',       label: '1ère mi-temps - 1x2'},
  {key: 'total_ht',     label: '1ère mi-temps - total'}
];

function renderLiveMarketDropdown() {
  var active = LIVE_MKT_OPTIONS.find(function(o){ return o.key === (S.activeLiveCat||'populaire'); });
  var label  = active ? active.label : '1x2';
  // Honor persisted open state so the 1.5-3s poll re-render never
  // collapses a dropdown the user has just opened (this is the
  // "ferme automatiquement" bug the client kept hitting).
  var isOpen = !!S.liveMktDropOpen;
  var arrowHtml = isOpen
    ? '&minus;'
    : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
  var out = '<div class="sb-live-mkt-wrap' + (isOpen ? ' sb-lmdt-open' : '') + '" id="sb-live-mkt-wrap">';
  out += '<div class="sb-live-mkt-panel">';
  out += '<button type="button" class="sb-live-mkt-btn' + (isOpen ? ' open' : '') + '" id="sb-live-mkt-btn" onclick="window.sbToggleLiveMktDrop()">';
  out += '<span id="sb-live-mkt-lbl">' + h(label) + '</span>';
  out += '<span class="sb-lmb-arrow" id="sb-lmb-arrow">' + arrowHtml + '</span>';
  out += '</button>';
  out += '<div class="sb-live-mkt-drop" id="sb-live-mkt-drop">';
  LIVE_MKT_OPTIONS.forEach(function(o) {
    var isCur = (o.key === (S.activeLiveCat || 'populaire'));
    if (isCur) return;
    out += '<button type="button" class="sb-lmk-item" onclick="window.sbSetLiveCat(\'' + o.key + '\',\'' + h(o.label) + '\')">';
    out += h(o.label);
    out += '</button>';
  });
  out += '</div>';
  out += '</div>';
  out += '</div>';
  return out;
}

window.sbToggleLiveMktDrop = function() {
  var wrap  = document.getElementById('sb-live-mkt-wrap');
  var arrow = document.getElementById('sb-lmb-arrow');
  if (!wrap) return;
  var isOpen = wrap.classList.contains('sb-lmdt-open');
  var nextOpen = !isOpen;
  wrap.classList.toggle('sb-lmdt-open', nextOpen);
  S.liveMktDropOpen = nextOpen;        // persist so poll re-render keeps state
  if (arrow) {
    arrow.innerHTML = nextOpen
      ? '&minus;'
      : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
  }
  var btn = document.getElementById('sb-live-mkt-btn');
  if (btn) btn.classList.toggle('open', nextOpen);
};

window.sbSetLiveCat = function(key, label) {
  S.activeLiveCat = key;
  // Close dropdown after picking — fcbet UX. Update persisted state
  // BEFORE re-rendering so the rebuilt dropdown is in the closed state.
  S.liveMktDropOpen = false;
  var wrap = document.getElementById('sb-live-mkt-wrap');
  if (wrap) {
    wrap.outerHTML = renderLiveMarketDropdown();
  }
  var lbl = document.getElementById('sb-live-mkt-lbl');
  if (lbl) lbl.textContent = label;
  wrap = document.getElementById('sb-live-mkt-wrap');
  if (wrap) wrap.classList.remove('sb-lmdt-open');
  var arrow = document.getElementById('sb-lmb-arrow');
  if (arrow) {
    arrow.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
  }
  var btn = document.getElementById('sb-live-mkt-btn');
  if (btn) btn.classList.remove('open');
  // Re-render only the live match groups (fast — no API call)
  var liveList = (S.matches || []).filter(isMatchLive);
  liveList = sortLiveMatches(liveList);
  var liveBody = document.getElementById('sb-live-groups-body');
  if (liveBody) {
    var html = '';
    html = renderMatchGroups(liveList, html, { showLeagueHeader: false });
    liveBody.innerHTML = html;
  }
};

/* European countries (incl. UEFA continental comps) — used to surface
   European football leagues FIRST inside each sport on the home page
   and the EN DIRECT page. Country names match the values returned by
   guessCountry() (French labels). */
var EUROPEAN_COUNTRIES = {
  'France':1,'Allemagne':1,'Italie':1,'Espagne':1,'Angleterre':1,
  'Royaume-Uni':1,'Pays-Bas':1,'Portugal':1,'Belgique':1,'Suisse':1,
  'Autriche':1,'Pologne':1,'Suède':1,'Norvège':1,'Danemark':1,
  'Finlande':1,'Russie':1,'Turquie':1,'Grèce':1,'Roumanie':1,
  'Hongrie':1,'Tchéquie':1,'République Tchèque':1,'Slovaquie':1,
  'Slovénie':1,'Croatie':1,'Serbie':1,'Ukraine':1,'Bulgarie':1,
  'Bosnie-Herzégovine':1,'Albanie':1,'Macédoine':1,'Monténégro':1,
  'Estonie':1,'Lettonie':1,'Lituanie':1,'Écosse':1,'Pays de Galles':1,
  'Irlande':1,'Irlande du Nord':1,'Islande':1,'Géorgie':1,'Arménie':1,
  'Azerbaïdjan':1,'Kazakhstan':1,'Israël':1,'Chypre':1,'Malte':1,
  'Luxembourg':1,'Andorre':1,'Saint-Marin':1,'Liechtenstein':1,
  'Europe':1   // UEFA continental tournaments
};
function isEuropeanLeague(league) {
  if (!league) return false;
  var n = typeof league === 'string' ? league : String(league.name || '');
  if (!n) return false;
  var c = guessCountry(n);
  return !!EUROPEAN_COUNTRIES[c];
}

/* Sort live matches: Football first (sport_id=1), then by league priority.
   Lower number = higher priority. Exact match beats substring. */
var LEAGUE_PRIORITY_MAP = {
  // Tier 0 — Global
  'FIFA World Cup': 0, 'World Cup': 0,
  // Tier 1 — UEFA Club Competitions
  'UEFA Champions League': 1, 'Champions League': 1,
  'UEFA Europa League': 2, 'Europa League': 2,
  'UEFA Conference League': 3, 'Conference League': 3,
  // Tier 2 — Big 5 European Leagues
  'England Premier League': 4, 'English Premier League': 4,
  'Spain LaLiga': 5, 'Spain La Liga': 5, 'LaLiga': 5, 'La Liga': 5, 'LaLiga Santander': 5,
  'Germany Bundesliga': 6, 'Bundesliga': 6,
  'Italy Serie A': 7, 'Serie A': 7,
  'France Ligue 1': 8, 'Ligue 1': 8,
  // Tier 3 — Second tier European
  'Netherlands Eredivisie': 10, 'Eredivisie': 10,
  'Portugal Primeira Liga': 11, 'Primeira Liga': 11, 'Portugal Liga': 11,
  'Belgium First Division A': 12, 'Belgium Pro League': 12,
  'Turkey Super Lig': 13, 'Turkey Süper Lig': 13,
  'Scotland Premiership': 14, 'Scotland Premier': 14,
  'Russia Premier League': 15,
  // Tier 4 — Other European
  'Austria Bundesliga': 16,
  'Switzerland Super League': 17,
  'Greece Super League': 18,
  'Czech Republic First League': 19,
  'Poland Ekstraklasa': 20,
  'Ukraine Premier League': 21,
  'Denmark Superliga': 22,
  'Sweden Allsvenskan': 23,
  'Norway Eliteserien': 24,
  'Germany Bundesliga II': 25, 'Bundesliga II': 25, '2. Bundesliga': 25,
  'Spain Segunda': 26, 'LaLiga 2': 26,
  'Italy Serie B': 27,
  'France Ligue 2': 28,
  'England Championship': 29,
  // Tier 5 — Cup competitions
  'England FA Cup': 32, 'EFL Cup': 33,
  'Spain Copa del Rey': 34, 'Italy Coppa Italia': 35,
  'Germany DFB Pokal': 36, 'France Coupe de France': 37,
  // Tier 6 — Americas & Global
  'Brazil Brasileiro Serie A': 40, 'Brasileiro Serie A': 40,
  'Argentina Liga Profesional': 41,
  'USA MLS': 45, 'Major League Soccer': 45,
  'Saudi Professional League': 50, 'Saudi Pro League': 50
};

/* Penalty terms — these leagues go to the BOTTOM regardless of name. */
var LEAGUE_PENALTY_TERMS = [
  'esoccer','e-soccer','virtual','simulated',
  'u17','u19','u20','u21','u23','youth','reserve',
  'friendly','women','feminin','féminin','frauen','femme',
  'regional','amateur',
  // Small nations whose "Premier League" name should NOT rank with England's
  'iceland','faroe','malta','cyprus','gibraltar',
  'liechtenstein','andorra','san marino','kosovo',
  'moldova','armenia','georgia','azerbaijan','belarus',
  'relegation','promotion','qualifier'
];

/* Exact country prefixes that indicate a top-tier league match.
   Used to confirm "Premier League" or "Bundesliga" is from the right country. */
var ELITE_LEAGUE_PREFIXES = [
  'England','English','Spain','Spain ','Germany','Italy','France',
  'Netherlands','Portugal','Belgium','Turkey','Scotland',
  'Russia','Austria','Switzerland','Greece','Czech','Poland',
  'Ukraine','Denmark','Sweden','Norway'
];

function getLeaguePriority(league) {
  if (!league) return 900;
  var n = typeof league === 'string' ? league : String(league.name || '');
  if (!n) return 900;
  var low = n.toLowerCase();

  // Penalty check FIRST — small nations and non-competitive leagues go to bottom
  for (var pi = 0; pi < LEAGUE_PENALTY_TERMS.length; pi++) {
    if (low.indexOf(LEAGUE_PENALTY_TERMS[pi]) >= 0) return 850;
  }

  // Exact match
  if (LEAGUE_PRIORITY_MAP.hasOwnProperty(n)) return LEAGUE_PRIORITY_MAP[n];

  // Substring match — key inside name OR name inside key
  var best = 500;
  for (var k in LEAGUE_PRIORITY_MAP) {
    if (n.indexOf(k) >= 0 || k.indexOf(n) >= 0) {
      var rank = LEAGUE_PRIORITY_MAP[k];
      // For generic terms like "Premier League", "Bundesliga" — only accept
      // if the league name starts with a known elite country prefix
      var isGenericTerm = (k === 'Premier League' || k === 'Bundesliga' ||
                           k === 'Serie A' || k === 'Ligue 1' || k === 'Eredivisie' ||
                           k === 'Champions League' || k === 'Europa League');
      if (isGenericTerm) {
        var hasElitePrefix = ELITE_LEAGUE_PREFIXES.some(function(pfx) {
          return n.indexOf(pfx) === 0 || n.indexOf(pfx + ' ') >= 0;
        });
        if (!hasElitePrefix) continue; // skip — e.g. "Iceland Premier League"
      }
      if (rank < best) best = rank;
    }
  }
  return best;
}

function sortLiveMatches(list) {
  return list.slice().sort(function(a, b) {
    var sa = parseInt(a.sport_id || 1);
    var sb2 = parseInt(b.sport_id || 1);
    if (sa !== sb2) {
      if (sa === 1) return -1;
      if (sb2 === 1) return 1;
      return sa - sb2;
    }
    // ─ TIER 1 ── Big-5 European top divisions and UEFA club competitions
    //   ALWAYS lead the live carousel, regardless of kickoff. This is what
    //   fcbet216 does (PL, La Liga, Serie A, Bundesliga, Ligue 1 front).
    var pa = getLeaguePriority(a.league);
    var pb = getLeaguePriority(b.league);
    var topA = pa <= 10 ? 0 : 1;  // priority 0-10 = Big 5 + UEFA + World Cup
    var topB = pb <= 10 ? 0 : 1;
    if (topA !== topB) return topA - topB;
    // ─ TIER 2 ── European leagues before rest of world
    var ea = isEuropeanLeague(a.league) ? 0 : 1;
    var eb = isEuropeanLeague(b.league) ? 0 : 1;
    if (ea !== eb) return ea - eb;
    return pa - pb;
  });
}
/* Sort UPCOMING matches: Football first, EUROPE first, popular leagues
   first, earliest kickoff first. Ensures Premier League / La Liga /
   Serie A / Bundesliga / Ligue 1 / Champions League etc. surface at the
   top of "Prochainement" before any non-European league. */
function sortUpcomingMatches(list) {
  return list.slice().sort(function(a, b) {
    var sa = parseInt(a.sport_id || 1);
    var sb2 = parseInt(b.sport_id || 1);
    if (sa !== sb2) {
      if (sa === 1) return -1;
      if (sb2 === 1) return 1;
      return sa - sb2;
    }
    var ea = isEuropeanLeague(a.league) ? 0 : 1;
    var eb = isEuropeanLeague(b.league) ? 0 : 1;
    if (ea !== eb) return ea - eb;
    var pa = getLeaguePriority(a.league);
    var pb = getLeaguePriority(b.league);
    if (pa !== pb) return pa - pb;
    var ta = parseInt(a.time || a.kickoff || 0, 10) || 0;
    var tb = parseInt(b.time || b.kickoff || 0, 10) || 0;
    return ta - tb;                  // earliest kickoff first within same league
  });
}

function renderSportFilterRow(showMarkets) {
  var liveSports = SPORTS.filter(function(sp) { return sp.live !== false; }).slice(0, 8);
  var isLiveView = (S.activeTab === 'live');
  var out = '<div class="sb-upcoming-tabs">';
  liveSports.forEach(function(sp) {
    var isActive = showMarkets ? (sp.id === S.activeUpcomingTab) : (sp.id === S.activeSportId);
    var onClick = showMarkets
      ? 'window.sbSetUpcomingTab(' + sp.id + ',this)'
      : (isLiveView
          ? 'window.sbSwitchLiveSport(' + sp.id + ',this)'
          : 'window.sbSwitchTab(null,\'inplay\',' + sp.id + ')');
    out += '<button type="button" class="sb-upcoming-tab' + (isActive ? ' active' : '') + '" onclick="' + onClick + '">';
    out += '<div class="sb-tab-icon">' + sp.icon + '</div>';
    out += '<span class="sb-tab-name">' + h(sp.name) + '</span>';
    out += '</button>';
  });
  out += '</div>';
  if (showMarkets) {
    out += '<div class="sb-market-row">';
    out += '<select class="sb-market-sel" autocomplete="off" onchange="event.stopPropagation()"><option>1x2</option><option>Handicap</option><option>Double chance</option></select>';
    out += '<select class="sb-market-sel" autocomplete="off" onchange="event.stopPropagation()"><option>Total</option><option>Corners</option><option>Cartons</option></select>';
    out += '</div>';
  }
  return out;
}

function renderMatches(results) {
  var el = document.getElementById('sb-matches-body');
  if (!el) return;
  // Never paint the home grid over a dedicated sub-view.
  if (S.viewMode === 'matchDetail' || S.viewMode === 'sportPage') return;

  results = results || [];

  // EN DIRECT search filter — survives poll re-renders
  if (S.liveSearchQ && S.activeTab === 'live') {
    var sq = S.liveSearchQ;
    results = results.filter(function(m) {
      var home   = ((m.home   && m.home.name)   || '').toLowerCase();
      var away   = ((m.away   && m.away.name)   || '').toLowerCase();
      var league = ((m.league && m.league.name) || '').toLowerCase();
      return home.indexOf(sq) !== -1 || away.indexOf(sq) !== -1 || league.indexOf(sq) !== -1;
    });
  }

  // Home league filter (after returning from a league or picking from list)
  if (S.homeLeagueFilter && S.viewMode === 'main' && !S.activeLeagueId) {
    results = results.filter(function(m) {
      return m.league && isLeagueMatch(S.homeLeagueFilter, m.league.name);
    });
  }

  // Continental region filter (set by sbBcCountry for "Europe", "Africa",
  // "Americas", "World", "International"). Matches via guessCountry() so
  // every UEFA / CAF / CONMEBOL competition appears, not just leagues
  // whose names literally contain the region word.
  if (S.homeRegionFilter && S.viewMode === 'main' && !S.activeLeagueId) {
    var region = S.homeRegionFilter;
    results = results.filter(function(m) {
      if (!m.league || !m.league.name) return false;
      return guessCountry(m.league.name) === region;
    });
  }

  var liveList = results.filter(isMatchLive);
  var upcomingList = results.filter(function(m) { return !isMatchLive(m); });
  var isTodayInplay = (S.activeAction === 'inplay' && S.activeDateOffset === 0);
  var liveOnlyTab = (S.activeTab === 'live');

  // Carousel + boosted — home page only (NOT on EN DIRECT tab).
  // Europe-priority sort so the featured "before Cotes boostees" card
  // and the live carousel surface PL / La Liga / Serie A / etc. first.
  if (!S.activeLeagueId && S.viewMode === 'main' && !liveOnlyTab) {
    var carouselRaw = liveList.length ? liveList : results;
    var carouselSrc = sortLiveMatches(carouselRaw);
    renderEnDirectCards(carouselSrc.slice(0, 6));
    // Show up to 10 important upcoming matches in the Cotes boostées
    // carousel (fcbet216 carousel typically shows 5-10). The
    // sortUpcomingMatches function already prioritises top European
    // and continental cup matches.
    renderBoosted(sortUpcomingMatches(results).slice(0, 10));
    sbSetHomepanelVisible(true);
  } else if (liveOnlyTab) {
    // EN DIRECT tab: hide carousel / boost / sport nav — show matches only.
    sbSetHomepanelVisible(false);
  }

  var out = '';

  // ── EN DIRECT MAINTENANT (live cards with jerseys, scores, green odds) ──
  // On the EN DIRECT tab we render PER-LEAGUE collapsible accordions
  // (fcbet216 image ref): each league header has star + flag + name +
  // BB pill + minus/chevron, click toggles the body. On the home page
  // the same rows render WITHOUT league headers (flat list under
  // "En direct maintenant").
  if (isTodayInplay && (liveList.length || liveOnlyTab)) {
    var showLive = liveOnlyTab ? liveList : liveList;
    var sortedLive = sortLiveMatches(showLive);
    out += '<div id="sb-live-now-block" class="sb-live-now-block">';
    out += '<div class="sb-section-title"><span>En direct maintenant</span><div class="sb-section-icon">' + ICON.football + '</div></div>';
    out += renderSportFilterRow(false); // live: sport pills only
    out += renderLiveMarketDropdown();  // market-type selector (1x2, Total, etc.)
    if (!sortedLive.length) {
      out += '<div class="sb-loader">Aucun match en direct pour le moment.</div>';
    } else {
      out += '<div id="sb-live-groups-body">';
      out = renderMatchGroups(sortedLive, out, {
        showLeagueHeader: liveOnlyTab,
        collapsible:      liveOnlyTab,
        defaultOpenCount: 2  // first 2 leagues open by default, rest collapsed
      });
      out += '</div>';
    }
    out += '</div>';
  }

  // ── PROCHAINEMENT (upcoming — date/time header style)
  //  Sort so famous leagues (PL, La Liga, Serie A, Bundesliga, Ligue 1,
  //  Champions League, ...) appear first, then earliest kickoff. ──
  var showUpcoming = upcomingList;
  if (!isTodayInplay) showUpcoming = results;
  else if (liveOnlyTab) showUpcoming = [];

  if (showUpcoming.length) {
    showUpcoming = sortUpcomingMatches(showUpcoming);
    out += '<div class="sb-upcoming-block">';
    out += '<div class="sb-section-title"><span>Prochainement</span><div class="sb-section-icon">' + ICON.football + '</div></div>';
    if (!isTodayInplay || !liveList.length) out += renderSportFilterRow(true); // upcoming: pills + market selects
    out = renderMatchGroups(showUpcoming, out);
    out += '</div>';
  }

  // ── POINTS FORTS — home page only, not EN DIRECT tab ──
  if (!S.activeLeagueId && S.viewMode === 'main' && !liveOnlyTab) {
    var TOP_LEAGUE_KEYWORDS = [
      'Champions League','Europa League','Conference League',
      'Premier League','LaLiga','La Liga','Serie A','Bundesliga','Ligue 1',
      'Eredivisie','Primera Division','Primeira Liga','FIFA World Cup',
      'NBA','Euroligue'
    ];
    var featuredMatches = results.filter(function(m) {
      var lg = (m.league && m.league.name) ? m.league.name : '';
      return TOP_LEAGUE_KEYWORDS.some(function(kw) { return lg.indexOf(kw) >= 0; });
    });
    if (featuredMatches.length >= 2) {
      out += '<div class="sb-points-forts-block">';
      out += '<div class="sb-section-title"><span>Points forts</span><div class="sb-section-icon">' + ICON.football + '</div></div>';
      out += renderSportFilterRow(true);
      out = renderMatchGroups(featuredMatches, out);
      out += '</div>';
    }
  }

  if (!out) {
    el.innerHTML = '<div class="sb-loader">Aucun match disponible.</div>';
    return;
  }

  el.innerHTML = out;
  highlightHomeLeagueFilter();
  startLiveMinuteTicker();
  // Re-apply inline expansion to any card the user had open before
  // this re-render (polling, sport switch, etc.). Skip the very first
  // call when no card is yet expanded — Object.keys check is cheap.
  if (typeof window.sbRestoreExpandedCards === 'function') {
    window.sbRestoreExpandedCards();
  }
}

/* ── Live minute ticker — bumps the displayed minute between API polls so
   "41'" naturally advances to "42'", "43'" etc. even if the next poll is
   delayed. Freezes when the API reports half-time (md=1 or md=3).
   Tick every 1s so the displayed minute is never more than 1s stale. ── */
function startLiveMinuteTicker() {
  if (window._mcLiveMinTicker) return;
  window._mcLiveMinTicker = setInterval(function() {
    var allM = (S.matches || []).concat(S.champMatches || []).concat(S.sportPageLive || []);
    function patch(sel) {
      document.querySelectorAll(sel).forEach(function(el) {
        var mid = el.getAttribute('data-mid');
        if (!mid) return;
        var m = allM.find(function(x) { return String(x.id) === String(mid); });
        if (m && m.time_status === '1') {
          var str = formatLiveMinute(m);
          if (str && el.textContent !== str) el.textContent = str;
        }
      });
    }
    patch('.mc-live-min');
    patch('.slc-time');
  }, 1000);
}

function matchCard(m) {
  // Ended matches must never appear in live lists.
  // (Callers already filter, but this is a belt-and-suspenders guard
  // for any path that renders a card without pre-filtering.)
  if (isMatchEnded(m)) return '';
  var o = odds(m);
  var isLive = isMatchLive(m);
  var hn = h(m.home ? String(m.home.name || '') : '');
  var an = h(m.away ? String(m.away.name || '') : '');
  var mid = h(m.id);

  // Format date/time for upcoming matches — safe parsing
  var ts = parseInt(m.time || m.kickoff || m.start_time || 0) || 0;
  var dateD = ts > 0 ? new Date(ts * 1000) : new Date();
  var dateStr = String(dateD.getDate()).padStart(2,'0') + '/' + String(dateD.getMonth()+1).padStart(2,'0');
  var timeStr = String(dateD.getHours()).padStart(2,'0') + ':' + String(dateD.getMinutes()).padStart(2,'0');
  var dateTimeLabel = dateStr + ' \u2022 ' + timeStr;

  // Live timer — "Mi-temps" for half-time, otherwise "45'"
  var liveMin = isLive ? formatLiveMinute(m) : '';

  // Team logos
  var FALLBACK = BASE + 'assets/images/logo.png';
  var hl = m.home ? m.home.image_id : null;
  var al = m.away ? m.away.image_id : null;
  var hlogo = hl ? 'https://assets.b365api.com/images/team/m/' + hl + '.png' : FALLBACK;
  var alogo = al ? 'https://assets.b365api.com/images/team/m/' + al + '.png' : FALLBACK;

  // League / country info — strip country prefix for display (matches reference)
  var rawLeagueName  = m.league ? String(m.league.name || '') : '';
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
  // Detect 2-way sports (basketball, tennis, volleyball, etc.) — no draw column
  var sportId = parseInt(m.sport_id || (S.activeSportId) || 0);
  var isTwoWay = (sportId !== 0 && sportId !== 1); // sport_id 1 = football/soccer
  var spObj = SPORTS.filter(function(s){ return s.id === sportId; })[0];
  var spIcon = spObj ? spObj.icon : ICON.football;
  var spIconSm = spIcon.replace(/width="20" height="20"/g, 'width="13" height="13"');
  var out = '<div class="mc' + (isLive ? ' mc-live-on' : '') + '" id="mc-' + mid + '" onclick="window.sbOpenMatch(\'' + mid + '\')">';

  /* ── Row 1: BB + EN DIRECT + timer ........ ☆ (NO flag/league here) ── */
  out += '<div class="mc-hdr-live">';
  out += '<div class="mc-hl-left">';
  if (isLive) {
    // Resolve base minute for the ticker (prefer API timer, fall back to kickoff)
    var _tmRaw = (m.timer && (m.timer.tm || m.timer.TM)) || 0;
    var _tmBase = parseInt(_tmRaw, 10) || 0;
    var _mdRaw = String((m.timer && (m.timer.md || m.timer.MD)) || '');
    // ── Kickoff fallback (football only): used when BetsAPI returned
    //    tm=0 (its "unknown" sentinel) OR when the elapsed kickoff
    //    minute is clearly AHEAD of the API timer (stale data — e.g.
    //    API says 12' but kickoff was 40 min ago → trust kickoff).
    if (parseInt(m.sport_id || 1, 10) === 1) {
      var _kickoff = parseInt(m.time || m.kickoff || 0, 10) || 0;
      if (_kickoff > 0) {
        var _elapsed = Math.floor((Date.now() / 1000 - _kickoff) / 60);
        var _shouldFallback = (_tmBase === 0) || (_elapsed > _tmBase + 5 && _elapsed <= 105);
        if (_shouldFallback) {
          if (_elapsed > 105) { _tmBase = 90; }
          else if (_elapsed > 60) _tmBase = _elapsed - 15;
          else if (_elapsed > 45) { _tmBase = 45; _mdRaw = '1'; }
          else if (_elapsed >= 0) _tmBase = Math.max(1, _elapsed);
        }
      }
    }
    // fcbet216 Type A (En direct maintenant list) — NO sport icon in header.
    // Just: BB · EN DIRECT · timer
    out += '<span class="mc-badge-bb">BB</span>';
    out += '<span class="mc-live-badge">EN DIRECT</span>';
    if (!liveMin) liveMin = 'En cours';
    out += '<span class="mc-live-min" data-mid="' + mid + '">' + h(liveMin) + '</span>';
  } else {
    // UPCOMING — inline header that matches fcbet216:
    //   [sport-icon] [BB] 19:30 25/05 • [flag] Bundesliga
    out += '<span class="mc-sport-badge-inline">' + spIconSm + '</span>';
    out += '<span class="mc-badge-bb">BB</span>';
    out += '<span class="mc-date mc-date--inline">' + h(dateTimeLabel) + '</span>';
    out += '<span class="mc-live-sep">&bull;</span>';
    out += '<img src="' + flagUrl + '" class="mc-league-flag mc-league-flag--inline" onerror="this.style.display=\'none\'">';
    out += '<span class="mc-league-name mc-league-name--inline">' + leagueName + '</span>';
  }
    out += '</div>';
    out += '<div class="mc-hl-right">';
    out += '<button class="mc-star" onclick="event.stopPropagation()">' + ICON.star + '</button>';
    out += '</div>';
    out += '</div>';

  out += '<div class="mc-body-col">';

  /* ── Row 2: SEPARATE league row — flag · league · country
        (fcbet216 "En direct maintenant" spec — image 2 Notts ref) ── */
  if (isLive) {
    out += '<div class="mc-league-row">';
    out += '<img src="' + flagUrl + '" class="mc-league-flag mc-league-flag--inline" onerror="this.style.display=\'none\'">';
    out += '<span class="mc-league-name mc-league-name--inline">' + leagueName + '</span>';
    if (countryLabel) {
      out += '<span class="mc-live-sep">&bull;</span>';
      out += '<span class="mc-league-country">' + countryLabel + '</span>';
    }
  out += '</div>';
  }

  /* ── Teams layout — fcbet216 image 2 spec:
        per-team rows = [shirt-inline] [team name ........... score]
        No big jersey circles, no side-by-side stack. ──────────────────── */
  if (isLive) {
    out += '<div class="mc-teams-wrap mc-teams-wrap--live mc-teams-wrap--rows" onclick="event.stopPropagation();window.sbOpenMatch(\'' + mid + '\')">';
    out += '<div class="mc-team-row mc-team-row--live">';
    out += '<span class="mc-shirt-cell">' + shirtSVG(m.home ? m.home.name : '', 'mc-jersey-svg', 16) + '</span>';
  out += '<span class="mc-t-name">' + hn + '</span>';
    out += '<span class="mc-t-score">' + h(scores[0] !== '' ? scores[0] : '0') + '</span>';
  out += '</div>';
    out += '<div class="mc-team-row mc-team-row--live">';
    out += '<span class="mc-shirt-cell">' + shirtSVG(m.away ? m.away.name : '', 'mc-jersey-svg', 16) + '</span>';
  out += '<span class="mc-t-name">' + an + '</span>';
    out += '<span class="mc-t-score">' + h(scores[1] !== '' ? scores[1] : '0') + '</span>';
  out += '</div>';
    out += '</div>';
  } else {
    // UPCOMING — fcbet216 reference image 3 (Paderborn vs VfL Wolfsburg):
    //   [shirt][shirt]   Team A      [EN DIRECT]  [signal]
    //                    Team B
    // Two PLAIN shirts side-by-side on the LEFT (no circle wrapper, the
    // shirt SVG itself carries the 1px white stroke). Team names stacked
    // in the middle. EN DIRECT pill + signal icon pinned far right.
    out += '<div class="mc-teams-wrap mc-teams-wrap--upcoming" onclick="event.stopPropagation();window.sbOpenMatch(\'' + mid + '\')">';
    out += '<div class="mc-jerseys-side">';
    out += '<span class="mc-jersey-cell">' + shirtSVG(m.home ? m.home.name : '', 'mc-jersey-svg', 26) + '</span>';
    out += '<span class="mc-jersey-cell">' + shirtSVG(m.away ? m.away.name : '', 'mc-jersey-svg', 26) + '</span>';
    out += '</div>';
    out += '<div class="mc-teams-stacked">';
    out += '<div class="mc-team-row mc-team-row--upcoming"><span class="mc-t-name">' + hn + '</span></div>';
    out += '<div class="mc-team-row mc-team-row--upcoming"><span class="mc-t-name">' + an + '</span></div>';
    out += '</div>';
    out += '<div class="mc-upcoming-actions">';
    out += '<span class="mc-ed-pill">EN DIRECT</span>';
    out += '<span class="mc-signal-ico" aria-label="Statistiques">';
    out += '<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 14V11M6 14V8M10 14V5M14 14V2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    out += '</span>';
    out += '</div>';
    out += '</div>';
  }

  // Odds buttons — full width bottom row (stopPropagation here so clicking odds ≠ opening match)
  out += '<div class="mc-odds-bot" onclick="event.stopPropagation()">';

  // When the match is over show a "Terminé" bar instead of odds
  if (isMatchEnded(m)) {
    out += '<div class="mc-ended-bar">Terminé</div>';
    out += '</div></div></div>';
    return out;
  }

  var cat = (S.viewMode === 'championship')
    ? (S.activeMarketCat || 'populaire')
    : (S.activeLiveCat || 'populaire');
  // Check if we have ANY real odds for this card — if not, show "market not available" message
  var hasAnyRealOdds = (parseFloat(o.h) >= 1.01 || parseFloat(o.a) >= 1.01 || parseFloat(o.x) >= 1.01 ||
                        parseFloat(o.ov) >= 1.01 || parseFloat(o.un) >= 1.01);
  // For non-default categories (not populaire), also check if the specific market has odds
  var catHasOdds = hasAnyRealOdds;
  if (cat === 'total' || cat === 'handicap') {
    catHasOdds = (parseFloat(o.ov) >= 1.01 || parseFloat(o.un) >= 1.01);
  }
  if (!catHasOdds && cat !== 'populaire') {
    out += '<div class="mc-no-market">Le marché que vous avez sélectionné n\'est pas disponible</div>';
    out += '<button class="mc-chevron-btn" onclick="window.sbToggleMc(\'' + mid + '\',event)" aria-label="Voir tous les marchés">' + ICON.chevronDown + '</button>';
    out += '</div>';
    out += '</div>';
    out += '</div>';
    return out;
  }
  var hVal = parseFloat(o.h) || 0;
  var aVal = parseFloat(o.a) || 0;
  var xVal = parseFloat(o.x) || 0;

  if (cat === 'populaire') {
    out += oddBtn(mid, mname, '1', o.h);
    if (!isTwoWay) out += oddBtn(mid, mname, 'X', o.x);
    out += oddBtn(mid, mname, '2', o.a);
  } else if (cat === '1x2') {
    out += oddBtn(mid, mname, '1', o.h);
    if (!isTwoWay) out += oddBtn(mid, mname, 'X', o.x);
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
    out += oddBtn(mid, mname, '1', o.h);
    if (!isTwoWay) out += oddBtn(mid, mname, 'X', o.x);
    out += oddBtn(mid, mname, '2', o.a);
  }
  
  // Chevron expands the full markets list INLINE (fcbet216 UX) — does
  // NOT navigate to the match-detail page. Card body still navigates.
  out += '<button class="mc-chevron-btn" onclick="window.sbToggleMc(\'' + mid + '\',event)" aria-label="Voir tous les marchés">' + ICON.chevronDown + '</button>';

  out += '</div>'; // close mc-odds-bot
  // Inline markets container — populated on chevron click via sbToggleMc.
  // onclick stopPropagation is critical: every click inside this box
  // must NOT bubble up to the card .mc onclick (which routes to detail).
  out += '<div class="mc-inline-md" id="mc-md-' + h(String(mid)) + '" style="display:none" onclick="event.stopPropagation()"></div>';
  out += '</div>'; // close body col
  out += '</div>'; // mc
  return out;
}

/* Lock icon SVG — shown when odds are suspended/unavailable */
var LOCK_ICON = '<svg class="mc-lock-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/></svg>';

/* Combined odds button: label left, odd right (matches fcbet216) */
function oddBtn(mid, match, sel, val) {
  var v = parseFloat(val);
  var hasOdds = !isNaN(v) && v >= 1.01;
  var bid = mid + '_' + sel;
  var isSel = S.betSlip.some(function(b) { return b.id === bid; });
  var valCls = 'mc-odd-btn' + (isSel ? ' sel' : '') + (hasOdds ? '' : ' no-odds');
  var onclick = hasOdds
    ? ' onclick="event.stopPropagation();window.sbAddBet(\'' + h(bid) + '\',\'' + h(match) + '\',\'' + h(sel) + '\',' + v + ')"'
    : '';
  return '<button type="button" class="' + valCls + '"' + onclick + '>'
    + '<span class="mc-odd-lbl">' + sel + '</span>'
    + '<span class="mc-odd-val">' + (hasOdds ? h(formatOdd(v)) : LOCK_ICON) + '</span>'
    + '</button>';
}

function slcOddBtn(mid, match, sel, val) {
  var v = parseFloat(val);
  var hasOdds = !isNaN(v) && v >= 1.01;
  var bid = mid + '_slc_' + sel;
  var isSel = S.betSlip.some(function(b) { return b.id === bid; });
  var valCls = 'slc-odd-btn' + (isSel ? ' sel' : '') + (hasOdds ? '' : ' no-odds');
  var onclick = hasOdds
    ? ' onclick="event.stopPropagation();window.sbAddBet(\'' + h(bid) + '\',\'' + h(match) + '\',\'' + h(sel) + '\',' + v + ')"'
    : '';
  return '<button type="button" class="' + valCls + '"' + onclick + '>'
    + '<span class="slc-odd-lbl">' + sel + '</span>'
    + '<span class="slc-odd-val">' + (hasOdds ? h(formatOdd(v)) : LOCK_ICON) + '</span>'
    + '</button>';
}

function ouBtn(mid, match, label, val, key) {
  var v = parseFloat(val);
  var hasOdds = val !== null && !isNaN(v) && v >= 1.01;
  var bid = mid + '_' + key;
  var isSel = S.betSlip.some(function(b) { return b.id === bid; });
  var valCls = 'mc-odd-btn' + (isSel ? ' sel' : '') + (hasOdds ? '' : ' no-odds');
  var onclick = hasOdds
    ? ' onclick="event.stopPropagation();window.sbAddBet(\'' + h(bid) + '\',\'' + h(match) + '\',\'' + h(label) + '\',' + v + ')"'
    : '';
  return '<button type="button" class="' + valCls + '"' + onclick + '>'
    + '<span class="mc-odd-lbl">' + h(label) + '</span>'
    + '<span class="mc-odd-val">' + (hasOdds ? h(formatOdd(v)) : LOCK_ICON) + '</span>'
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

  // Safety filter: drop anything that has effectively ended even if
  // the caller forgot. Covers stale Bet365 time_status=1 on lower
  // leagues where the API takes minutes to push status=3.
  matches = (matches || []).filter(function(m){ return !isMatchEnded(m); });

  var out = '';
  var shown = matches.slice(0, 6); // max 6 live cards

  // SVG Shirt helper — round circle container, same style as main match cards
  function getShirtSVG(tName) {
    return '<div class="mc-jersey-wrap slc-jersey-wrap">' + shirtSVG(tName, 'mc-jersey-svg', 18) + '</div>';
  }

  shown.forEach(function(m) {
    var o   = odds(m);
    var mid = h(m.id);
    var hn  = h(m.home ? m.home.name : '');
    var an  = h(m.away ? m.away.name : '');
    var ts2 = parseInt(m.time || 0) || 0;
    var dObj = ts2 > 0 ? new Date(ts2 * 1000) : new Date();
    var timeStr = String(dObj.getHours()).padStart(2,'0') + ':' + String(dObj.getMinutes()).padStart(2,'0');

    var rawLg   = m.league ? (m.league.name || '') : '';
    var lgName  = h(stripCountryPrefix(rawLg) || rawLg);
    var isLive  = isMatchLive(m);

    var scores = m.ss ? m.ss.split('-') : ['',''];
    var timerMin = isLive ? formatLiveMinute(m) : '';

    // Pick sport icon (small 14px)
    var sid = parseInt(m.sport_id || 1);
    var spObj = SPORTS.filter(function(s){ return s.id === sid; })[0];
    var spIcon = spObj ? spObj.icon : ICON.football;
    // Swap 20→14 for small icon
    var spIconSm = spIcon.replace(/width="20" height="20"/g, 'width="14" height="14"');

    // League flag (derived from league name)
    var lgCountry  = guessCountry(rawLg);
    var lgFlagUrl  = getFlag(lgCountry);

    out += '<div class="slc" onclick="window.sbOpenMatch(\'' + mid + '\')">';

    // ── Header: [⚽] [BB] [EN DIRECT] [timer] · [flag] [league]
    //    fcbet216 Type B (before Cotes boostées) — image 3 ref ──
    out += '<div class="slc-hdr">';
    out += '<span class="slc-sport-icon">' + spIconSm + '</span>';
    out += '<span class="slc-bb">BB</span>';
    if (isLive) {
      out += '<span class="slc-live-badge">EN DIRECT</span>';
      if (timerMin) {
        out += '<span class="slc-time" data-mid="' + mid + '">' + h(timerMin) + '</span>';
      }
    } else {
      out += '<span class="slc-time">' + timeStr + '</span>';
    }
    out += '<span class="slc-sep">&bull;</span>';
    out += '<img src="' + lgFlagUrl + '" class="slc-flag" onerror="this.style.display=\'none\'">';
    out += '<span class="slc-lg">' + lgName + '</span>';
    out += '</div>';

    // ── Teams: overlapping jerseys LEFT + stacked names/scores RIGHT (fcbet reference) ──
    out += '<div class="slc-mid">';

    // Left: two overlapping jersey circles
    out += '<div class="slc-jerseys-overlap">';
    out += getShirtSVG(hn);
    out += getShirtSVG(an);
    out += '</div>';

    // Right: stacked team rows (name + score)
    out += '<div class="slc-teams">';

    out += '<div class="slc-team-row">';
    out += '<span class="slc-tname">' + hn + '</span>';
    out += '<span class="slc-score">' + h(isLive && scores[0] !== '' ? scores[0] : (isLive ? '0' : '')) + '</span>';
    out += '</div>';

    out += '<div class="slc-team-row">';
    out += '<span class="slc-tname">' + an + '</span>';
    out += '<span class="slc-score">' + h(isLive && scores[1] !== '' ? scores[1] : (isLive ? '0' : '')) + '</span>';
    out += '</div>';

    out += '</div>';  // slc-teams
    out += '</div>';  // slc-mid

    // ── Odds row ──
    var slcMatch = (m.home ? m.home.name : '') + ' v ' + (m.away ? m.away.name : '');
    out += '<div class="slc-odds">';
    out += slcOddBtn(mid, slcMatch, '1', o.h);
    out += slcOddBtn(mid, slcMatch, 'X', o.x);
    out += slcOddBtn(mid, slcMatch, '2', o.a);
    out += '</div>';

    out += '</div>'; // slc
  });

  el.innerHTML = out;
}

/* ── Boost-card maths (fcbet216 / Altenar-style)
 *
 * Each card shows a SAME-GAME-MULTI promo with a "boosted" price.
 * We derive both prices from the match's real 1x2 odds:
 *   1. Normalise h/x/a into a true probability (strip the bookmaker margin).
 *   2. Estimate the conditional probability of each combo leg from the
 *      relative strength of the two teams (asymmetry).
 *   3. Multiply legs with a correlation discount (matches BB_CORR).
 *   4. Apply a small house margin to get the "real" (struck-out) price.
 *   5. Add a 10–15 % boost — deterministic per match so cards stay stable.
 * The result feels real because it's anchored on actual Bet365 prices
 * (which themselves reflect each team's classement / form). */
function computeBoostOdds(m, pickKey) {
  var o = odds(m);
  var h = parseFloat(o.h);
  var x = parseFloat(o.x);
  var a = parseFloat(o.a);
  if (!(h >= 1.01) || !(a >= 1.01)) return null;
  if (!(x >= 1.01)) x = 3.2;

  // Strip bookmaker margin to recover the "true" probabilities
  var ph = 1 / h, px = 1 / x, pa = 1 / a;
  var sumP = ph + px + pa;
  ph /= sumP; px /= sumP; pa /= sumP;

  // Asymmetry drives how lopsided the match is. 0 = even, 0.6 = mismatch
  var asym = Math.abs(ph - pa);
  // Higher asymmetry → fewer BTTS / Over 2.5 (one-sided games)
  var pBtts   = Math.max(0.32, Math.min(0.70, 0.62 - asym * 0.45));
  var pOver25 = Math.max(0.36, Math.min(0.66, 0.55 - asym * 0.25));
  var pOver05 = 0.92, pOver15 = 0.78;
  var pCorrect1H = 0.18; // 1H scorer market avg
  var pOver10HCorners = 0.55;
  var pYellow1H = 0.78;

  // Correlation factor used when multiplying correlated legs
  var CORR = 0.88;

  // ── 1) Compute the FAIR probability for the requested pick
  var pFair;
  switch (pickKey) {
    case '1_oui':  pFair = ph * pBtts          * CORR; break;
    case '2_oui':  pFair = pa * pBtts          * CORR; break;
    case '1_non':  pFair = ph * (1 - pBtts)    * CORR; break;
    case '2_non':  pFair = pa * (1 - pBtts)    * CORR; break;
    case 'over25': pFair = pOver25; break;
    case 'under25':pFair = 1 - pOver25; break;
    case '1':      pFair = ph; break;
    case '2':      pFair = pa; break;
    case 'X':      pFair = px; break;
    case 'oui':    pFair = pBtts; break;
    case 'non':    pFair = 1 - pBtts; break;
    case '1h_over05_corners10_2':
      // 3-leg: away win 1H + over 0.5 1H + over 10.5 corners
      pFair = (pa * 0.55) * pOver05 * pOver10HCorners * Math.pow(CORR, 2);
      break;
    case 'over25_anytime_shots15':
      pFair = pOver25 * 0.75 * 0.70 * Math.pow(CORR, 2);
      break;
    case '1h_winner_over15_yellow1h':
      pFair = (Math.max(ph, pa) * 0.50) * pOver15 * pYellow1H * Math.pow(CORR, 2);
      break;
    default:       pFair = ph * pBtts * CORR;
  }
  if (pFair <= 0.005) pFair = 0.005;
  if (pFair >= 0.95)  pFair = 0.95;

  // House margin recovers a "real" book price (~7 % overround)
  var realOdd = (1 / pFair) * 0.93;
  // Deterministic boost 10–15 % depending on match id
  var idn = parseInt(String(m.id || '0').replace(/[^0-9]/g, '')) || 0;
  var boostPct = 0.10 + ((idn % 6) / 100); // 10 % … 15 %
  var boostedOdd = realOdd * (1 + boostPct);

  return {
    real:    Math.max(1.05, +realOdd.toFixed(2)),
    boosted: Math.max(1.06, +boostedOdd.toFixed(2))
  };
}

/* ── Cotes Boostées (old promotional style with boost arrow) ─────────────── */
function renderBoosted(matches) {
  var el = document.getElementById('sb-boosted-odds');
  if (!el) return;
  el.querySelectorAll('.sb-sk-boost-card').forEach(function(n){ n.parentNode.removeChild(n); });

  // Each combo: (display lines, pickKey, header pill text)
  var COMBOS = [
    { lines: ['1x2 & GG/NG', '1 & oui'],                                                  key: '1_oui'  },
    { lines: ['1x2 & GG/NG', '2 & non'],                                                  key: '2_non'  },
    { lines: ['Plus de 2.5 - Total', "N'importe quand - Buteur", 'Plus de 1.5 - Tirs'],   key: 'over25_anytime_shots15' },
    { lines: ['1ère mi-temps - 1x2', 'Plus de 1.5 - total', 'Carton jaune - 1ère mi-temps'], key: '1h_winner_over15_yellow1h' },
    { lines: ['2 - 1ère mi-temps - 1x2', 'Plus de 0.5 - 1ère mi-temps - total', 'Plus de 10.5 - Total des corners'], key: '1h_over05_corners10_2' }
  ];
  // Stash boost params per match-id so the click handler stays simple
  // (no fragile string-escaping in the onclick attribute).
  window._sbBoostCards = {};

  var out = '';
  matches.forEach(function(m, idx) {
    var combo = COMBOS[idx % COMBOS.length];
    // Choose the home/away variant of the favourite when the combo allows
    var pick = combo.key;
    var oReal = odds(m);
    if (pick === '1_oui' && parseFloat(oReal.a) < parseFloat(oReal.h)) pick = '2_oui';
    if (pick === '2_non' && parseFloat(oReal.h) < parseFloat(oReal.a)) pick = '1_non';
    var pricing = computeBoostOdds(m, pick);
    if (!pricing) return; // skip matches with no real odds
    var oldOdd = pricing.real.toFixed(2);
    var newOdd = pricing.boosted.toFixed(2);
    // Patch the display label so it reflects the chosen side
    var legs = combo.lines.slice();
    if (pick === '1_oui' && legs[1])  legs[1] = '1 & oui';
    if (pick === '2_oui' && legs[1])  legs[1] = '2 & oui';
    if (pick === '1_non' && legs[1])  legs[1] = '1 & non';
    if (pick === '2_non' && legs[1])  legs[1] = '2 & non';
    var ts2 = parseInt(m.time || m.kickoff || 0) || 0;
    var dObj = ts2 > 0 ? new Date(ts2 * 1000) : new Date();
    var dateStr = String(dObj.getDate()).padStart(2,'0') + '/' + String(dObj.getMonth()+1).padStart(2,'0');
    var timeStr = String(dObj.getHours()).padStart(2,'0') + ':' + String(dObj.getMinutes()).padStart(2,'0');
    var lines = legs;

    // Save the boost card payload so sbAddBoostBet can pick it up by id
    window._sbBoostCards[String(m.id)] = {
      matchId: String(m.id),
      matchName: (m.home ? m.home.name : '') + ' vs. ' + (m.away ? m.away.name : ''),
      league: m.league ? m.league.name : '',
      lines: legs,
      real: pricing.real,
      boosted: pricing.boosted,
      pick: pick,
      isLive: isMatchLive(m)
    };

    var boostBetId  = 'boost_' + m.id;
    var isSelected  = S.betSlip.some(function(b){ return b.id === boostBetId; });
    var selCls      = isSelected ? ' is-selected' : '';

    out += '<div class="sb-boost-card' + selCls + '" data-bid="' + h(boostBetId) + '"'
         + ' onclick="event.stopPropagation();window.sbAddBoostBet(\'' + h(m.id) + '\')">';
    out += '<div class="sb-boost-card-top">';
    out += '<span class="sb-boost-sport-icon"><svg width="16" height="16" viewBox="0 0 32 33" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M28.3514 5.82922C26.7793 3.9223 24.7691 2.39532 22.4814 1.37952C20.4987 0.499084 18.3093 0 16 0C13.6884 0 11.4969 0.500244 9.51263 1.38239C7.31567 2.35901 5.38202 3.81335 3.84009 5.61737C1.45081 8.4129 0 12.0343 0 16C0 16.5004 0.0298462 16.9937 0.0748291 17.4824C0.335938 20.3214 1.34015 22.9412 2.88916 25.1552C4.98395 28.1493 8.07538 30.3843 11.6843 31.3944C13.059 31.7792 14.5024 32 16 32C17.5426 32 19.0293 31.7697 20.4407 31.3623C24.095 30.3076 27.2152 27.9962 29.2873 24.913C30.9767 22.3996 31.9672 19.3801 31.9934 16.1301C31.9938 16.0863 32 16.0439 32 16C32 12.137 30.6307 8.59399 28.3514 5.82922ZM28.0334 8.88104C28.957 10.4362 29.585 12.181 29.8473 14.046L27.7114 11.9718L28.0334 8.88104ZM26.2687 6.51642L25.7321 11.6733L25.7321 11.6738L21.1324 13.7266L21.1319 13.7262L21.1316 13.7263L17.6975 11.2315L16.9996 10.7246V10.7245V6.01465L22.4458 3.58508C23.8868 4.33636 25.1751 5.33313 26.2687 6.51642ZM18.371 19.7656H18.3707H13.631H13.6295L12.1648 15.2572L12.1647 15.2568L12.1658 15.256L15.9996 12.4707L16.0002 12.4711L16.0004 12.4709L19.8366 15.2568L18.371 19.7656ZM16 2C17.3374 2 18.627 2.19989 19.8528 2.5517L15.9988 4.27026L12.142 2.55304C13.3693 2.20026 14.6607 2 16 2ZM9.54932 3.58752L14.9996 6.01465V10.7247V10.7256L10.8698 13.7266L10.8688 13.7261L6.28674 11.6816L5.89728 6.3338C6.95319 5.23071 8.18378 4.30011 9.54932 3.58752ZM4.06567 8.71814L4.30731 12.0401L2.05377 14.9377C2.22528 12.6704 2.93909 10.5575 4.06567 8.71814ZM4.10193 23.3414C3.12085 21.7574 2.4505 19.9675 2.16718 18.0488L5.64026 13.584L10.1783 15.6168L10.1813 15.6182L11.7872 20.5656L11.7906 20.5762L11.7901 20.5769L8.90881 24.209L8.90771 24.2088L4.10193 23.3414ZM5.93231 25.7047L8.68652 26.202L10.1224 28.689C8.54498 27.9553 7.12585 26.9425 5.93231 25.7047ZM16 30C14.9681 30 13.9649 29.88 12.996 29.6671L10.5258 25.389L10.525 25.3877L13.3883 21.7656H13.4005H18.6783L22.2565 25.4014L19.2382 29.6068C18.1968 29.8547 17.1162 30 16 30ZM22.6138 28.3339L24.158 26.182L26.0621 25.7109C25.057 26.752 23.8981 27.6424 22.6138 28.3339ZM28.0019 23.1707L23.8735 24.1921L23.8727 24.1924L20.2361 20.4972L20.236 20.4971L20.236 20.4969L20.4579 19.8145L21.8258 15.6074L26.4557 13.541L29.9526 16.9363C29.8013 19.2053 29.11 21.3233 28.0019 23.1707Z" fill="rgba(255,255,255,0.75)"></path></svg></span>';
    out += '<span class="sb-badge-bb">BB</span>';
    out += '<span class="sb-badge-blue">COTES BOOSTÉES</span>';
    out += '</div>';
    out += '<div class="sb-boost-meta-row">';
    out += '<span class="sb-boost-league">' + h(m.league ? m.league.name : '') + '</span>';
    out += '<span class="sb-boost-date">' + dateStr + ' • ' + timeStr + '</span>';
    out += '</div>';
    out += '<div class="sb-teams-text"><strong>' + h(m.home ? m.home.name : '') + ' vs. ' + h(m.away ? m.away.name : '') + '</strong></div>';
    // Picks list wrapped so the green vertical connector between dots
    // (fcbet216 timeline style) can target adjacent siblings cleanly.
    out += '<div class="sb-boost-picks">';
    lines.forEach(function(line) {
      out += '<div class="sb-boost-line"><span class="sb-boost-dot"></span><span class="sb-boost-line-txt">' + h(line) + '</span></div>';
    });
    out += '</div>';
    out += '<div class="sb-odds-row">';
    out += '<span class="sb-old-val">' + oldOdd + '</span>';
    // EXACT fcbet216 BoostIcon SVG (user-supplied DevTools markup):
    //   path d="M4 5L7.11111 8L4 11M8.88889 5L12 8L8.88889 11"
    //   stroke-width 1.5, stroke-linecap/linejoin round. currentColor
    //   inherits the green sb-boost-arrow text color.
    out += '<span class="sb-boost-arrow" aria-hidden="true">';
    out += '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5L7.11111 8L4 11M8.88889 5L12 8L8.88889 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    out += '</span>';
    out += '<span class="sb-new-val">' + h(formatOdd(parseFloat(newOdd))) + '</span>';
    out += '</div></div>';
  });
  el.innerHTML = out || '<div style="padding:10px;color:var(--sb-text-2);font-size:12px">Aucune cote boostée disponible</div>';

  // Build pagination dots and wire scroll-snap to highlight the active card
  // with the blue left border (matches fcbet216 reference image 2).
  sbBuildBoostDots(matches.length);
  sbUpdateBoostActive();
}

/* ── Boost row: pagination dots + active-card tracker ─────────────────
   Matches fcbet216 image 2: ●●○○○ dots under the carousel; the snapped
   card gets a 1px blue left border (.is-active). */
function sbBuildBoostDots(count) {
  var holder = document.getElementById('sb-boost-dots');
  if (!holder) {
    var row = document.getElementById('sb-boosted-odds');
    if (!row || !row.parentNode) return;
    holder = document.createElement('div');
    holder.id = 'sb-boost-dots';
    holder.className = 'sb-boost-dots';
    row.parentNode.insertBefore(holder, row.nextSibling);
    // Attach scroll listener once
    row.addEventListener('scroll', function() {
      if (sbBuildBoostDots._raf) return;
      sbBuildBoostDots._raf = requestAnimationFrame(function() {
        sbBuildBoostDots._raf = null;
        sbUpdateBoostActive();
      });
    }, { passive: true });
  }
  var dots = '';
  for (var i = 0; i < count; i++) dots += '<span class="sb-boost-dot-pip" data-i="' + i + '"></span>';
  holder.innerHTML = dots;
}
function sbUpdateBoostActive() {
  var row = document.getElementById('sb-boosted-odds');
  if (!row) return;
  var cards = row.querySelectorAll('.sb-boost-card');
  if (!cards.length) return;
  var rowRect = row.getBoundingClientRect();
  var rowCenter = rowRect.left + rowRect.width / 2;
  var activeIdx = 0;
  var bestDist = Infinity;
  cards.forEach(function(c, i) {
    var r = c.getBoundingClientRect();
    var center = r.left + r.width / 2;
    var d = Math.abs(center - rowCenter);
    if (d < bestDist) { bestDist = d; activeIdx = i; }
  });
  cards.forEach(function(c, i) { c.classList.toggle('is-active', i === activeIdx); });
  var dots = document.querySelectorAll('#sb-boost-dots .sb-boost-dot-pip');
  dots.forEach(function(d, i) { d.classList.toggle('is-active', i === activeIdx); });
}

/* ── Bet Slip — fcbet216-style with Simple/Combiné/Système ────────────── */
var SLIP_MODE = 'simple';
var SLIP_STAKE = 5; // default stake per bet
var SLIP_COMBI_STAKE = 10;
var SLIP_SYS_SINGLES_STAKE = 0;
var SLIP_SYS_COMBO_STAKE = 0;

// fcbet216 / Altenar behaviour: multiple selections from the SAME
// match auto-group into a single Same-Game-Multi ticket in the slip
// (Simple mode keeps working; Combiné only triggers when the user
// adds bets from a DIFFERENT match). One leg per market group; click
// a same-market different-line button replaces the previous leg.
window.sbAddBet = function(id, match, sel, val, market) {
  id = String(id).replace(/'/g, '');
  var matchId = String(id).split('_')[0];

  // 1) Toggling OFF an existing single bet?
  var sIdx = S.betSlip.findIndex(function(b) { return b.id === id && !b.isBB; });
  if (sIdx !== -1) {
    S.betSlip.splice(sIdx, 1);
    _afterSlipChange();
    return;
  }
  // 2) Toggling OFF a leg already inside the per-match SGM ticket?
  var bbBetId = 'bb_' + matchId;
  var bbIdx   = S.betSlip.findIndex(function(b) { return b.id === bbBetId; });
  if (bbIdx !== -1) {
    var bb = S.betSlip[bbIdx];
    var legIdx = (bb.legs || []).findIndex(function(l){ return l.id === id; });
    if (legIdx !== -1) {
      bb.legs.splice(legIdx, 1);
      // 1 leg left → collapse back to a regular single bet
      if (bb.legs.length === 0) {
        S.betSlip.splice(bbIdx, 1);
      } else if (bb.legs.length === 1) {
        var only = bb.legs[0];
        S.betSlip[bbIdx] = {
          id: only.id, match: bb.match, sel: only.name,
          val: parseFloat(only.odds), _origVal: parseFloat(only.odds),
          _change: null, isLive: bb.isLive, matchId: matchId,
          market: only.market || '1x2', stake: bb.stake || SLIP_STAKE
        };
      } else {
        bb.val = parseFloat(bbCombinedOdds(bb.legs).toFixed(2));
        bb._origVal = bb.val;
        bb.sel = bb.legs.map(function(l){ return l.name; }).join(' + ');
      }
      _afterSlipChange();
      return;
    }
  }

  // 3) ADDING a new selection
  var live = false;
  var pool = (S.matches || []).concat(S.champMatches || []);
  for (var p = 0; p < pool.length; p++) {
    if (String(pool[p].id) === matchId) { live = isMatchLive(pool[p]); break; }
  }

  // Find ALL existing single bets for this match — they ALL need to be
  // pulled into the SGM ticket (covers stale slips from older builds
  // that allowed multiple singles per match).
  var singleIdxs = [];
  S.betSlip.forEach(function(b, ii){
    if (!b.isBB && b.matchId === matchId) singleIdxs.push(ii);
  });

  if (bbIdx !== -1 || singleIdxs.length > 0) {
    // Promote/extend a per-match SGM ticket
    var bet;
    if (bbIdx !== -1) {
      bet = S.betSlip[bbIdx];
    } else {
      // Convert the FIRST single into the SGM ticket header
      var ex0 = S.betSlip[singleIdxs[0]];
      bet = {
        id: bbBetId, match: ex0.match, sel: '',
        val: 1.00, _origVal: 1.00, _change: null,
        isLive: live, isBB: true, matchId: matchId,
        legs: [],
        stake: ex0.stake || SLIP_STAKE
      };
    }

    // Pull every leftover single bet for this match into the ticket.
    // Splice from the highest index down so earlier indices stay valid.
    for (var k = singleIdxs.length - 1; k >= 0; k--) {
      var idx = singleIdxs[k];
      var ex  = S.betSlip[idx];
      bet.legs.unshift({
        id: ex.id, name: ex.sel,
        odds: parseFloat(ex.val) || 1.0,
        market: ex.market || '',
        handicap: ''
      });
      S.betSlip.splice(idx, 1);
    }
    if (bbIdx === -1) S.betSlip.push(bet);

    // Mutual exclusion within the same market group
    var mktKey = (market || '').toLowerCase();
    if (mktKey) {
      var sameMktIdx = bet.legs.findIndex(function(l){
        return (l.market || '').toLowerCase() === mktKey;
      });
      if (sameMktIdx !== -1) bet.legs.splice(sameMktIdx, 1);
    }
    // Also drop any duplicate of THIS exact bid before re-adding
    var dupIdx = bet.legs.findIndex(function(l){ return l.id === id; });
    if (dupIdx !== -1) bet.legs.splice(dupIdx, 1);

    bet.legs.push({
      id: id, name: sel,
      odds: parseFloat(val) || 1.0,
      market: market || '',
      handicap: ''
    });

    // 1 leg only after the dust settles → render as a plain single bet
    if (bet.legs.length === 1) {
      var only2 = bet.legs[0];
      var bIdx2 = S.betSlip.findIndex(function(b){ return b.id === bbBetId; });
      if (bIdx2 !== -1) {
        S.betSlip[bIdx2] = {
          id: only2.id, match: bet.match, sel: only2.name,
          val: parseFloat(only2.odds), _origVal: parseFloat(only2.odds),
          _change: null, isLive: bet.isLive, matchId: matchId,
          market: only2.market || '1x2', stake: bet.stake || SLIP_STAKE
        };
      }
    } else {
      bet.val      = parseFloat(bbCombinedOdds(bet.legs).toFixed(2));
      bet._origVal = bet.val;
      bet.sel      = bet.legs.map(function(l){ return l.name; }).join(' + ');
    }
  } else {
    // No existing entry for this match → plain single bet
    S.betSlip.push({
      id: id, match: match, sel: sel,
      val: parseFloat(val),
      _origVal: parseFloat(val),
      _change: null,
      isLive: live,
      matchId: matchId,
      market: market || '1x2',
      stake: SLIP_STAKE
    });
  }

  _afterSlipChange();
};

// Add a "Cotes boostées" promo bet to the slip. The card data was
// stashed on window._sbBoostCards by renderBoosted. Clicking again
// toggles the boost off (consistent with every other slip button).
window.sbAddBoostBet = function(matchId) {
  var data = window._sbBoostCards && window._sbBoostCards[String(matchId)];
  if (!data) return;
  var boostBetId = 'boost_' + matchId;
  var idx = S.betSlip.findIndex(function(b){ return b.id === boostBetId; });
  if (idx !== -1) {
    S.betSlip.splice(idx, 1);
  } else {
    S.betSlip.push({
      id: boostBetId,
      match: data.matchName,
      sel: data.lines.length >= 2 ? data.lines[data.lines.length - 1] : (data.lines[0] || ''),
      val: parseFloat(data.boosted),
      _origVal: parseFloat(data.boosted),
      _change: null,
      isLive: !!data.isLive,
      isBoost: true,
      boostReal: parseFloat(data.real),
      boostLines: data.lines.slice(),
      matchId: String(matchId),
      market: data.lines[0] || 'Cotes boostées',
      stake: SLIP_STAKE
    });
  }
  _afterSlipChange();
  // Highlight every boost card matching this id (carousel + match-detail copy)
  document.querySelectorAll('.sb-boost-card[data-bid="' + boostBetId + '"]').forEach(function(c){
    c.classList.toggle('is-selected', S.betSlip.some(function(b){ return b.id === boostBetId; }));
  });
  // Open the slip drawer on mobile so the user sees the bet they just added
  if (window.innerWidth < 1101) {
    var right = document.getElementById('sb-right');
    if (right && !right.classList.contains('open')) right.classList.add('open');
    updateFloatingBetBadge();
  }
};

// Centralised post-slip-change side-effects: pick the right mode,
// re-render, sync the badge and refresh button highlights.
function _afterSlipChange() {
  // Boost (promotional) bets and Bet Builder tickets are always Simple
  // mode — each one has its own combined price and can't be folded
  // into an accumulator with the others.
  var hasBoost = S.betSlip.some(function(b){ return b && b.isBoost; });
  var hasBB    = S.betSlip.some(function(b){ return b && b.isBB; });
  var matchIds = {};
  S.betSlip.forEach(function(b){ if (b && b.matchId) matchIds[b.matchId] = true; });
  var distinctMatches = Object.keys(matchIds).length;
  if (hasBoost || hasBB || distinctMatches <= 1) {
    SLIP_MODE = 'simple';
  } else if (SLIP_MODE === 'simple' || !SLIP_MODE) {
    SLIP_MODE = 'combi';
  }

  renderBetSlip();
  updateFloatingBetBadge();

  // Refresh selection highlights everywhere
  document.querySelectorAll('.md-odd-btn, .md-bb-btn, .mc-odd-btn, .mc-o-val-cell, .slc-odd-btn').forEach(function(btn){
    var dbid = btn.getAttribute('data-bid') || '';
    var oc   = btn.getAttribute('onclick') || '';
    var selectedIds = [];
    S.betSlip.forEach(function(b){
      if (b.isBB && b.legs) b.legs.forEach(function(l){ selectedIds.push(l.id); });
      else if (b.id) selectedIds.push(b.id);
    });
    var hit = selectedIds.some(function(sid){
      if (dbid) return sid === dbid;
      return oc.indexOf(sid) !== -1;
    });
    btn.classList.toggle('sel', hit);
  });
}

/* Floating bet badge — shows count when bets exist.
   Style matches fcbet216 reference image: circular dark FAB with the
   document icon, green pill count badge at top-right and a small
   "Fiche de pari" label below the circle. Tapping it opens the slip. */
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
  // Is the slip drawer currently open? If yes, keep the FAB hidden
  // (it would overlap the open slip on mobile). If no, show whenever
  // there's at least one bet — and IMPORTANTLY reset any stale
  // visibility:hidden left over from a previous open/close cycle.
  var right     = document.getElementById('sb-right');
  var drawerOpen = right && right.classList.contains('open');
  if (n > 0) {
    badge.innerHTML =
        '<div class="sb-fb-circle">'
      +   '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>'
      +   '<span class="sb-fb-count">' + n + '</span>'
      + '</div>'
      + '<span class="sb-fb-label">Fiche de pari</span>';
    badge.style.display    = 'flex';
    badge.style.visibility = drawerOpen ? 'hidden' : '';
    badge.style.opacity    = drawerOpen ? '' : '1';
    badge.style.pointerEvents = drawerOpen ? '' : 'auto';
  } else {
    badge.style.display    = 'none';
    badge.style.visibility = '';
  }
}

window.sbSlipMode = function(mode) {
  if ((mode === 'combi' || mode === 'system') && S.betSlip.length < 2) return;
  if ((mode === 'combi' || mode === 'system') && S.betSlip.some(function(b){ return b && (b.isBB || b.isBoost); })) return;
  SLIP_MODE = mode;
  renderBetSlip();
};
window.sbClearSlip = function() {
  S.betSlip = [];
  if (typeof _afterSlipChange === 'function') _afterSlipChange();
  else { renderBetSlip(); updateFloatingBetBadge(); }
  var right = document.getElementById('sb-right');
  if (right && right.classList.contains('open')) window.sbToggleRight();
};
window.sbRemoveBet = function(id) {
  // Find the bet — could be a regular single OR a per-match SGM ticket
  var idx = S.betSlip.findIndex(function(b){ return b.id === id; });
  if (idx === -1) return;
  S.betSlip.splice(idx, 1);
  if (typeof _afterSlipChange === 'function') _afterSlipChange();
  else { renderBetSlip(); updateFloatingBetBadge(); }
};
window.sbToggleExclude = function(idx) {
  if (S.betSlip[idx]) { S.betSlip[idx].excluded = !S.betSlip[idx].excluded; renderBetSlip(); }
};
window.sbToggleBanker = function(idx) {
  if (S.betSlip[idx]) { S.betSlip[idx].banker = !S.betSlip[idx].banker; renderBetSlip(); }
};
window.sbRemoveBBLeg = function(betId, legIdx) {
  var bIdx = S.betSlip.findIndex(function(b){ return b.id === betId; });
  if (bIdx < 0) return;
  var bet = S.betSlip[bIdx];
  if (!bet.legs) return;
  bet.legs.splice(legIdx, 1);
  if (!bet.legs.length) {
    S.betSlip.splice(bIdx, 1);
  } else if (bet.legs.length === 1) {
    // Collapse back to a regular single bet so the slip / button
    // highlights match fcbet216 (1-leg = single, not SGM).
    var only = bet.legs[0];
    S.betSlip[bIdx] = {
      id: only.id, match: bet.match, sel: only.name,
      val: parseFloat(only.odds), _origVal: parseFloat(only.odds),
      _change: null, isLive: bet.isLive, matchId: bet.matchId,
      market: only.market || '1x2', stake: bet.stake || SLIP_STAKE
    };
  } else {
    bet.val = parseFloat(bbCombinedOdds(bet.legs).toFixed(2));
    bet._origVal = bet.val;
    bet.sel = bet.legs.map(function(l){ return l.name; }).join(' + ');
  }
  if (typeof _afterSlipChange === 'function') _afterSlipChange();
  else { renderBetSlip(); updateFloatingBetBadge(); }
};

/* ── Slip financial helpers — update totals WITHOUT re-rendering the
   whole slip (re-render destroys focused inputs and breaks typing). ── */
function slipParseStake(val) {
  var v = parseFloat(String(val == null ? '' : val).replace(',', '.'));
  return isNaN(v) ? 0 : Math.max(0, v);
}
function slipCombiMeta() {
  var combiLegs = S.betSlip.filter(function(b) { return !b.excluded; });
  var seen = {};
  var valid = combiLegs.filter(function(b) {
    var mid = String(b.matchId || b.id);
    if (seen[mid]) return false;
    seen[mid] = true;
    return true;
  });
  var odds = valid.reduce(function(acc, l) { return acc * (parseFloat(l.val) || 1); }, 1);
  var cnt = valid.length;
  var bonusPct = cnt >= 4 ? 10 : cnt >= 3 ? 7 : cnt >= 2 ? 5 : 0;
  var stake = parseFloat(SLIP_COMBI_STAKE) || 0;
  var baseGain = stake * odds;
  var bonus = baseGain * bonusPct / 100;
  return { odds: odds, cnt: cnt, bonusPct: bonusPct, stake: stake, gain: baseGain + bonus, bonus: bonus };
}
function slipSystemMeta() {
  var sysLegs = S.betSlip.filter(function(b) { return !b.excluded; });
  var groups = {};
  sysLegs.forEach(function(b) {
    var key = String(b.matchId || b.id);
    (groups[key] = groups[key] || []).push(b);
  });
  var groupCounts = Object.keys(groups).map(function(k) { return groups[k].length; });
  function countCombos(k) {
    var poly = [1];
    groupCounts.forEach(function(g) {
      var next = poly.slice();
      for (var i = 0; i < poly.length; i++) next[i + 1] = (next[i + 1] || 0) + poly[i] * g;
      poly = next;
    });
    return poly[k] || 0;
  }
  var levels = [
    { k: 1, id: 'k1' }, { k: 2, id: 'k2' }, { k: 3, id: 'k3' },
    { k: 4, id: 'k4' }, { k: 5, id: 'k5' }, { k: 6, id: 'k6' },
    { k: 7, id: 'k7' }, { k: 8, id: 'k8' }
  ];
  S._sysStakes = S._sysStakes || {};
  var totalBets = 0, totalMise = 0;
  levels.forEach(function(lvl) {
    if (lvl.k > sysLegs.length) return;
    var combos = countCombos(lvl.k);
    if (!combos) return;
    var stake = parseFloat(S._sysStakes[lvl.id] || 0) || 0;
    if (stake > 0) totalBets += combos;
    totalMise += stake * combos;
  });
  return { totalBets: totalBets, totalMise: totalMise };
}
function _syncStakeInput(mode, idx, str) {
  var sel = mode === 'combi' ? '#slip-combi-stake'
    : '.slip-item[data-slip-idx="' + idx + '"] .slip-stake-inp';
  var inp = document.querySelector(sel);
  if (inp && document.activeElement !== inp) inp.value = str;
}
function patchSlipFinancials() {
  if (SLIP_MODE === 'simple') {
    var totalMise = 0, totalGain = 0;
    S.betSlip.forEach(function(b, i) {
      var st = slipParseStake(b.stake != null ? b.stake : SLIP_STAKE);
      var gain = (st * (parseFloat(b.val) || 1)).toFixed(2);
      totalMise += st;
      totalGain += st * (parseFloat(b.val) || 1);
      var card = document.querySelector('.slip-item[data-slip-idx="' + i + '"]');
      if (!card) return;
      var gag = card.querySelector('.slip-gagner strong');
      if (gag) gag.textContent = gain;
      _syncStakeInput('simple', i, String(st || ''));
    });
    var miseEl = document.getElementById('slip-sum-mise');
    var gainEl = document.getElementById('slip-sum-gain');
    if (miseEl) miseEl.textContent = totalMise.toFixed(2);
    if (gainEl) gainEl.textContent = totalGain.toFixed(2);
  } else if (SLIP_MODE === 'combi') {
    var cm = slipCombiMeta();
    _syncStakeInput('combi', 0, String(cm.stake || ''));
    var oEl = document.getElementById('slip-combi-odds');
    var mEl = document.getElementById('slip-combi-mise');
    var bEl = document.getElementById('slip-combi-bonus');
    var gEl = document.getElementById('slip-combi-gain');
    if (oEl) oEl.textContent = cm.odds.toFixed(2);
    if (mEl) mEl.textContent = cm.stake.toFixed(2);
    if (bEl) {
      bEl.style.display = cm.bonusPct > 0 ? '' : 'none';
      if (cm.bonusPct > 0) bEl.textContent = '+' + cm.bonus.toFixed(2);
    }
    if (gEl) gEl.textContent = cm.gain.toFixed(2);
  } else if (SLIP_MODE === 'system') {
    var sm = slipSystemMeta();
    var cEl = document.getElementById('slip-sys-count');
    var mEl2 = document.getElementById('slip-sys-mise');
    if (cEl) cEl.textContent = String(sm.totalBets);
    if (mEl2) mEl2.textContent = sm.totalMise.toFixed(2);
  }
}
function slipMarkActiveStake(idx) {
  document.querySelectorAll('.slip-stake-inp').forEach(function(inp) {
    var card = inp.closest('.slip-item');
    var ci = card ? parseInt(card.getAttribute('data-slip-idx'), 10) : -1;
    inp.classList.toggle('active', ci === idx);
  });
}

window.sbUpdateBetStake = function(idx, val) {
  if (!S.betSlip[idx]) return;
  S.betSlip[idx].stake = slipParseStake(val);
  patchSlipFinancials();
};
window.sbUpdateCombiStake = function(val) {
  SLIP_COMBI_STAKE = slipParseStake(val);
  patchSlipFinancials();
};
window.sbCombiQuickStake = function(delta) {
  SLIP_COMBI_STAKE = slipParseStake((parseFloat(SLIP_COMBI_STAKE) || 0) + (parseFloat(delta) || 0));
  _syncStakeInput('combi', 0, String(SLIP_COMBI_STAKE || ''));
  patchSlipFinancials();
};
window.sbSimpleQuickStake = function(delta, idx) {
  if (!S.betSlip[idx]) return;
  S.betSlip[idx].stake = slipParseStake((parseFloat(S.betSlip[idx].stake) || 0) + (parseFloat(delta) || 0));
  S._activeStakeIdx = idx;
  _syncStakeInput('simple', idx, String(S.betSlip[idx].stake || ''));
  slipMarkActiveStake(idx);
  patchSlipFinancials();
};
window.sbActivateStake = function(idx) {
  S._activeStakeIdx = idx;
  slipMarkActiveStake(idx);
  var editor = document.getElementById('slip-stake-editor');
  if (editor) editor.classList.add('slip-stake-editor--open');
};

/* ── Stake numpad — 1..9, 0, "." , ⌫, OK ──
   Mode = 'simple'  → operates on S.betSlip[idx].stake
   Mode = 'combi'   → operates on SLIP_COMBI_STAKE              */
function renderStakeNumpad(mode, idx) {
  var out = '<div class="slip-numpad">';
  var keys = ['1','2','3','4','5','.','6','7','8','9','0','del'];
  keys.forEach(function(k) {
    if (k === 'del') {
      out += '<button class="slip-np-key slip-np-del" onclick="window.sbNumpadDel(\'' + mode + '\',' + (idx || 0) + ')" aria-label="Effacer">'
        + '<svg width="18" height="14" viewBox="0 0 24 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H7L1 9l6 6h15a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/><line x1="18" y1="7" x2="12" y2="13"/><line x1="12" y1="7" x2="18" y2="13"/></svg>'
        + '</button>';
    } else {
      out += '<button class="slip-np-key" onclick="window.sbNumpadKey(\'' + k + '\',\'' + mode + '\',' + (idx || 0) + ')">' + k + '</button>';
    }
  });
  out += '<button class="slip-np-ok" onclick="window.sbNumpadOk(\'' + mode + '\',' + (idx || 0) + ')">OK</button>';
  out += '</div>';
  return out;
}
function _readStake(mode, idx) {
  if (mode === 'combi') return String(SLIP_COMBI_STAKE || '');
  if (mode === 'simple' && S.betSlip[idx]) return String(S.betSlip[idx].stake || '');
  return '';
}
function _writeStake(mode, idx, str) {
  var v = parseFloat(str);
  if (isNaN(v) || v < 0) v = 0;
  if (mode === 'combi') SLIP_COMBI_STAKE = v;
  else if (mode === 'simple' && S.betSlip[idx]) {
    S.betSlip[idx].stake = v;
    S._activeStakeIdx = idx;
  }
}
window.sbNumpadKey = function(key, mode, idx) {
  var cur = _readStake(mode, idx);
  if (key === '.' && cur.indexOf('.') !== -1) return;
  if (cur === '0' && key !== '.') cur = '';
  cur = cur + key;
  if (cur.indexOf('.') !== -1) {
    var parts = cur.split('.');
    cur = parts[0] + '.' + (parts[1] || '').slice(0, 2);
  }
  _writeStake(mode, idx, cur);
  _syncStakeInput(mode, idx, cur);
  patchSlipFinancials();
};
window.sbNumpadDel = function(mode, idx) {
  var cur = _readStake(mode, idx);
  cur = cur.slice(0, -1);
  _writeStake(mode, idx, cur);
  _syncStakeInput(mode, idx, cur);
  patchSlipFinancials();
};
window.sbNumpadOk = function(mode, idx) {
  if (mode === 'simple') {
    S._activeStakeIdx = idx;
    slipMarkActiveStake(idx);
  }
  var editor = document.getElementById('slip-stake-editor');
  if (editor) editor.classList.remove('slip-stake-editor--open');
};
window.sbUpdateSysStake = function(type, val) {
  if (type === 'singles') SLIP_SYS_SINGLES_STAKE = slipParseStake(val);
  else SLIP_SYS_COMBO_STAKE = slipParseStake(val);
  patchSlipFinancials();
};
window.sbUpdateSysLevelStake = function(levelId, val) {
  S._sysStakes = S._sysStakes || {};
  S._sysStakes[levelId] = slipParseStake(val);
  patchSlipFinancials();
};
// Accept all pending odds changes — clears the "cotes ont changées" banner.
window.sbAcceptOddsChanges = function() {
  if (!S.betSlip) return;
  S.betSlip.forEach(function(b) { if (b) b._change = null; });
  renderBetSlip();
};

function renderBetSlip() {
  var el = document.getElementById('sb-slip-body');
  var cntEl = document.getElementById('sb-slip-count');
  if (!el) return;

  var n = S.betSlip.length;

  // Bet Builder bets cannot be combined/systemised — force Simple
  var hasBB = S.betSlip.some(function(b) { return b && b.isBB; });
  // Force back to Simple when < 2 selections OR when a BB bet is present
  if ((n < 2 || hasBB) && SLIP_MODE !== 'simple') {
    SLIP_MODE = 'simple';
  }

  if (cntEl) {
    cntEl.innerText = n || '';
    cntEl.style.display = n ? 'inline-flex' : 'none';
  }

  // Hide / show the secondary widgets (CODE RAPIDE / RECHERCHER DES PARIS
  // / PARIS POPULAIRES) — they act as the empty-state UI and must hide
  // the moment any bet is added (matches fcbet216 reference image).
  var secondary = document.querySelector('.sb-right-secondary');
  if (secondary) secondary.style.display = n ? 'none' : '';

  var out = '';

  // ── Mode tabs — disabled when < 2 selections OR Bet Builder is present ──────
  var tabsLocked = n < 2 || hasBB;
  out += '<div class="slip-tabs"><div class="slip-tabs-inner">';
  ['simple','combi','system'].forEach(function(m) {
    var lbl = m === 'simple' ? 'Simple' : m === 'combi' ? 'Combiné' : 'Système';
    var isActive = SLIP_MODE === m;
    var isDisabled = tabsLocked && m !== 'simple';
    out += '<button class="slip-tab'
      + (isActive ? ' active' : '')
      + (isDisabled ? ' tab-disabled' : '')
      + '"'
      + (isDisabled ? ' disabled' : ' onclick="window.sbSlipMode(\'' + m + '\')"')
      + '>' + lbl + '</button>';
  });
  out += '</div></div>';

  // ── Empty state ────────────────────────────────────────────
  // Just the centered document icon — the CODE RAPIDE / RECHERCHER /
  // PARIS POPULAIRES widgets below act as the rest of the empty state,
  // exactly like fcbet216 reference (image 2).
  if (!n) {
    out += '<div class="sb-slip-empty-icon">'
      + '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">'
      +   '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>'
      +   '<polyline points="14 2 14 8 20 8"/>'
      +   '<line x1="8" y1="13" x2="16" y2="13"/>'
      +   '<line x1="8" y1="17" x2="13" y2="17"/>'
      + '</svg>'
      + '</div>';
    el.innerHTML = out;
    return;
  }

  // ── Bet cards ──────────────────────────────────────────────
  var totalOdds = 1;
  S.betSlip.forEach(function(b, i) {
    if (!b.excluded) totalOdds *= b.val;

    var isExcl   = !!b.excluded;
    var isBanker = !!b.banker;

    out += '<div class="slip-item'
      + (isExcl ? ' excluded' : '')
      + (SLIP_MODE === 'combi' ? ' combi' : '')
      + '" data-slip-idx="' + i + '">';

    // ── Header row: ⚽ | match name | [−][B][×]
    out += '<div class="slip-item-hdr">';
    out += '<span class="slip-sport-icon">' + ICON.football + '</span>';
    out += '<span class="slip-match-nm">' + h(b.match) + '</span>';
    out += '<div class="slip-item-btns">';
    // Exclude (−) only in Combiné, Banker (B) only in Système/Combiné — not in Simple
    if (SLIP_MODE === 'combi' && !b.isBB) {
      out += '<button type="button" class="slip-excl-btn' + (isExcl ? ' excluded' : '') + '" title="Exclure/Inclure" onclick="window.sbToggleExclude(' + i + ')">&#8722;</button>';
    }
    if ((SLIP_MODE === 'combi' || SLIP_MODE === 'system') && !b.isBB) {
      out += '<button type="button" class="slip-banker-btn' + (isBanker ? ' active' : '') + '" title="Banker" onclick="window.sbToggleBanker(' + i + ')">B</button>';
    }
    out += '<button type="button" class="slip-remove-btn" onclick="window.sbRemoveBet(\'' + h(b.id) + '\')">&#215;</button>';
    out += '</div>';
    out += '</div>';

    out += '<div class="slip-item-body">';

    // ── Market name (single bets only; BB tickets show each leg's market inline)
    if (b.isBoost) {
      out += '<div class="slip-market-nm slip-market-nm--boost">'
           + '<span class="sb-badge-blue" style="font-size:10px;padding:2px 6px;border-radius:3px;background:#3a6fff;color:#fff;font-weight:700;letter-spacing:0.4px">COTES BOOSTÉES</span>'
           + '<span style="margin-left:6px;color:rgba(255,255,255,0.7);font-size:12px">' + h(b.market || '') + '</span>'
           + '</div>';
    } else if (!b.isBB) {
      out += '<div class="slip-market-nm">' + h(b.market || '1x2') + '</div>';
    }

    // Helper that renders the odds pill — adds up/down arrow + colored
    // frame when live polling shifted the value (b._change).
    function renderOddsPill(val, change) {
      var boxCls = 'slip-odds-box';
      if (change === 'up')   boxCls += ' up';
      if (change === 'down') boxCls += ' down';
      var indicator = '';
      if (change === 'up') {
        indicator = '<svg class="slip-odds-arrow slip-odds-arrow--up" width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><polygon points="12,4 22,18 2,18"/></svg>';
      } else if (change === 'down') {
        indicator = '<svg class="slip-odds-arrow slip-odds-arrow--down" width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><polygon points="12,20 22,6 2,6"/></svg>';
      }
      // No minus filler when stable — fcbet216 shows just the number.
      return '<span class="' + boxCls + '">' + indicator + '<span class="slip-odds-num">' + (parseFloat(val) || 0).toFixed(2) + '</span></span>';
    }

    // ── Selection row: EN DIRECT badge + selection + odds
    if (b.isBoost && b.boostLines && b.boostLines.length) {
      // Render each promo leg with the green timeline dot, then the
      // strike-through "real" odd next to the boosted price.
      b.boostLines.forEach(function(line) {
        out += '<div class="slip-leg-row">';
        out += '<span class="slip-leg-dot"></span>';
        out += '<div class="slip-leg-info"><span class="slip-leg-sel">' + h(line) + '</span></div>';
        out += '</div>';
      });
      out += '<div class="slip-sel-row">';
      if (b.isLive) out += '<span class="slip-live-badge">EN DIRECT</span>';
      out += '<span class="slip-sel-lbl" style="color:rgba(255,255,255,0.5);font-size:11px">Cote boostée</span>';
      if (b.boostReal) {
        out += '<span class="slip-old-odd" style="text-decoration:line-through;color:#f04a4a;margin-right:6px;font-size:12px">' + parseFloat(b.boostReal).toFixed(2) + '</span>';
      }
      out += renderOddsPill(b.val, b._change);
      out += '</div>';
    } else if (b.isBB && b.legs && b.legs.length) {
      b.legs.forEach(function(leg, li) {
        out += '<div class="slip-leg-row">';
        out += '<span class="slip-leg-dot"></span>';
        out += '<div class="slip-leg-info"><span class="slip-leg-mkt">' + h(leg.market || '') + '</span><span class="slip-leg-sep"> — </span><span class="slip-leg-sel">' + h(leg.name) + '</span></div>';
        out += '<button class="slip-leg-del" onclick="window.sbRemoveBBLeg(\'' + h(b.id) + '\',' + li + ')">&#215;</button>';
        out += '</div>';
      });
      // Combined odds row
      out += '<div class="slip-sel-row">';
      if (b.isLive) out += '<span class="slip-live-badge">EN DIRECT</span>';
      out += '<span class="slip-sel-lbl" style="color:rgba(255,255,255,0.5);font-size:11px">Cote combinée</span>';
      out += renderOddsPill(b.val, b._change);
      out += '</div>';
    } else {
      out += '<div class="slip-sel-row">';
      if (b.isLive) out += '<span class="slip-live-badge">EN DIRECT</span>';
      out += '<span class="slip-sel-lbl">' + h(b.sel) + '</span>';
      out += renderOddsPill(b.val, b._change);
      out += '</div>';
    }

    // ── Stake + Gagner (Simple mode only)
    if (SLIP_MODE === 'simple') {
      var gain = +((b.stake || SLIP_STAKE) * b.val).toFixed(2);
      var activeCls = (i === (S._activeStakeIdx || 0)) ? ' active' : '';
      out += '<div class="slip-stake-row">';
      out += '<input type="text" inputmode="decimal" class="slip-stake-inp' + activeCls + '" value="' + (b.stake || SLIP_STAKE) + '" autocomplete="off" oninput="window.sbUpdateBetStake(' + i + ',this.value)" onfocus="window.sbActivateStake(' + i + ')">';
      out += '<span class="slip-gagner">Gagner: <strong>' + gain.toFixed(2) + '</strong></span>';
      out += '</div>';
    }

    out += '</div>'; // slip-item-body
    out += '</div>'; // slip-item
  });

  // ── Simple-mode stake editor (quick chips + numpad) ──
  if (SLIP_MODE === 'simple') {
    var editorOpen = !!document.getElementById('slip-stake-editor') &&
      document.getElementById('slip-stake-editor').classList.contains('slip-stake-editor--open');
    out += '<div class="slip-stake-editor' + (editorOpen ? ' slip-stake-editor--open' : '') + '" id="slip-stake-editor">';
    var simpleActiveIdxA = (typeof S._activeStakeIdx === 'number')
      ? Math.min(S._activeStakeIdx, S.betSlip.length - 1)
      : 0;
    if (simpleActiveIdxA < 0) simpleActiveIdxA = 0;
    out += '<div class="slip-quick-stakes">';
    [5, 10, 20, 50].forEach(function(v) {
      out += '<button type="button" class="slip-quick-stake" onclick="window.sbSimpleQuickStake(' + v + ',' + simpleActiveIdxA + ')">+' + v + '</button>';
    });
    out += '</div>';
    out += renderStakeNumpad('simple', simpleActiveIdxA);
    out += '</div>';
  }

  // ── Promo hint — shown for Simple and Système only; Combiné has its own ──
  if (SLIP_MODE !== 'combi') {
  out += '<div class="slip-promo">'
    + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="6" width="22" height="14" rx="2"/><path d="M16 6V4a2 2 0 00-4 0v2M8 6V4a2 2 0 00-4 0v2"/></svg>'
      + ' Ajoutez 1 événement avec une cote de 1.20 ou plus pour augmenter vos gains de <strong>5&nbsp;%</strong>'
    + '</div>';
  }

  // ── Tout effacer ───────────────────────────────────────────
  out += '<button class="slip-clear-btn" onclick="window.sbClearSlip()">'
    + 'Tout effacer '
    + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>'
    + '</button>';

  // ── Odds changed warning (only when at least one leg actually moved) ──
  var hasOddsChange = S.betSlip.some(function(b) { return b && b._change; });
  if (hasOddsChange) {
    out += '<div class="slip-odds-warning" onclick="window.sbAcceptOddsChanges()" role="button">'
    + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
    + ' Certaines cotes ont changées, veuillez accepter.'
    + '</div>';
  }

  // ═══ MODE-SPECIFIC SUMMARY ═════════════════════════════════
  if (SLIP_MODE === 'simple') {
    var totalMise = 0, totalGain = 0;
    S.betSlip.forEach(function(b) {
      var st = b.stake || SLIP_STAKE;
      totalMise += st;
      totalGain += st * b.val;
    });
    out += '<div class="slip-summary">';
    out += '<div class="slip-sum-row"><span>Mise totale</span><span id="slip-sum-mise">' + totalMise.toFixed(2) + '</span></div>';
    out += '<div class="slip-sum-row"><span>Gain total</span><span class="slip-sum-green" id="slip-sum-gain">' + totalGain.toFixed(2) + '</span></div>';
    out += '</div>';

  } else if (SLIP_MODE === 'combi') {
    // Combo is built ONLY from non-excluded legs whose match isn't
    // already represented by another leg (you can't combo two 1x2
    // selections of the same match). When that happens we still show
    // the duplicate in the list but it's silently dropped from the
    // combined odds — matches fcbet216 behaviour.
    var combiLegs = S.betSlip.filter(function(b) { return !b.excluded; });
    var seenMatches = {};
    var validCombiLegs = combiLegs.filter(function(b) {
      var mid = String(b.matchId || b.id);
      if (seenMatches[mid]) return false;
      seenMatches[mid] = true;
      return true;
    });
    var combiOdds = validCombiLegs.reduce(function(acc, l){ return acc * (parseFloat(l.val) || 1); }, 1);
    var combiCount = validCombiLegs.length;

    // Progressive bonus, fcbet216-style
    var bonusPct = combiCount >= 4 ? 10 : combiCount >= 3 ? 7 : combiCount >= 2 ? 5 : 0;

    // Promo banner (one only — universal promo is suppressed in combi mode above)
    if (combiCount >= 1 && combiCount < 4) {
      var nextBonus = combiCount < 2 ? 5 : combiCount < 3 ? 7 : 10;
      out += '<div class="slip-promo">'
        + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="6" width="22" height="14" rx="2"/><path d="M16 6V4a2 2 0 00-4 0v2M8 6V4a2 2 0 00-4 0v2"/></svg>'
        + ' Ajoutez 1 événement avec une cote de 1.20 ou plus pour augmenter vos gains de <strong>' + nextBonus + '\u00A0%</strong>'
        + '</div>';
    }

    // "Can't combine" warning when same match has duplicate selections
    if (combiLegs.length !== validCombiLegs.length) {
      out += '<div class="slip-odds-warning" role="alert">'
        + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
        + ' Certaines de vos sélections ne peuvent pas être combinées.'
        + '</div>';
    }

    // Combo row
    var stake = SLIP_COMBI_STAKE || 0;
    out += '<div class="slip-combo-row">';
    out += '<span class="slip-combo-lbl">Combo</span>';
    if (bonusPct > 0) {
      out += '<span class="slip-combo-bonus">'
        + '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>'
        + ' ' + bonusPct + '%</span>';
    }
    out += '<span class="slip-combo-mult">' + combiCount + ' x</span>';
    out += '<input type="text" inputmode="decimal" class="slip-stake-inp slip-combi-stake" id="slip-combi-stake" value="' + stake + '" autocomplete="off" oninput="window.sbUpdateCombiStake(this.value)" onfocus="document.getElementById(\'slip-stake-editor\')&&document.getElementById(\'slip-stake-editor\').classList.add(\'slip-stake-editor--open\')">';
    out += '</div>';

    out += '<div class="slip-stake-editor" id="slip-stake-editor">';
    out += '<div class="slip-quick-stakes">';
    [5, 10, 20, 50].forEach(function(v) {
      out += '<button type="button" class="slip-quick-stake" onclick="window.sbCombiQuickStake(' + v + ')">+' + v + '</button>';
    });
    out += '</div>';
    out += renderStakeNumpad('combi');
    out += '</div>'; // slip-stake-editor

    // Summary (cotes / mise / bonus / gain total)
    var baseGain = stake * combiOdds;
    var bonus = baseGain * bonusPct / 100;
    var combiGain = baseGain + bonus;
    out += '<div class="slip-summary">';
    out += '<div class="slip-sum-row"><span>Cotes totales</span><span id="slip-combi-odds">' + combiOdds.toFixed(2) + '</span></div>';
    out += '<div class="slip-sum-row"><span>Mise totale</span><span id="slip-combi-mise">' + stake.toFixed(2) + '</span></div>';
    if (bonusPct > 0) {
      out += '<div class="slip-sum-row" id="slip-combi-bonus-row"><span>Gains supplémentaires</span><span class="slip-sum-green" id="slip-combi-bonus">+' + bonus.toFixed(2) + '</span></div>';
    }
    out += '<div class="slip-sum-row slip-sum-total"><span>Gain total</span><span class="slip-sum-green" id="slip-combi-gain">' + combiGain.toFixed(2) + '</span></div>';
    out += '</div>';

  } else if (SLIP_MODE === 'system') {
    // ── Système mode — fcbet216 parity. Each leg is a "Seul", and
    // every k-combo is a Doublé / Triplé / ... but we must EXCLUDE
    // combinations whose legs share the same match (you can't combo
    // 1+X+2 of the same match — they're mutually exclusive). The same
    // logic powers fcbet216's counts (e.g. "Doublé 9 x" for a 6-leg
    // slip made of 3 selections × 2 matches). ───────────────────────
    var sysLegs = S.betSlip.filter(function(b) { return !b.excluded; });

    // Group legs by match — combos must take 0/1 leg per match.
    var groups = {};
    sysLegs.forEach(function(b) {
      var key = String(b.matchId || b.id);
      (groups[key] = groups[key] || []).push(b);
    });
    var groupCounts = Object.keys(groups).map(function(k){ return groups[k].length; });
    // valid k-combos = coefficient of x^k in prod((1 + g*x) for each group)
    // where g = group size (any 1 of g legs).
    function countCombos(k) {
      var poly = [1];
      groupCounts.forEach(function(g) {
        var next = poly.slice();
        for (var i = 0; i < poly.length; i++) {
          next[i + 1] = (next[i + 1] || 0) + poly[i] * g;
        }
        poly = next;
      });
      return poly[k] || 0;
    }

    var sysLevels = [
      { k: 1, lbl: 'Seuls',     id: 'k1' },
      { k: 2, lbl: 'Doublé',    id: 'k2' },
      { k: 3, lbl: 'Triplé',    id: 'k3' },
      { k: 4, lbl: 'Quartetté', id: 'k4' },
      { k: 5, lbl: 'Quintuplé', id: 'k5' },
      { k: 6, lbl: 'Sextuplé',  id: 'k6' },
      { k: 7, lbl: 'Septuplé',  id: 'k7' },
      { k: 8, lbl: 'Octuplé',   id: 'k8' }
    ];
    S._sysStakes = S._sysStakes || {};
    var totalBetsCount = 0, totalSysMise = 0;
    sysLevels.forEach(function(lvl) {
      if (lvl.k > sysLegs.length) return;
      var combos = countCombos(lvl.k);
      if (!combos) return;
      var stake = parseFloat(S._sysStakes[lvl.id] || 0) || 0;
      totalBetsCount += stake > 0 ? combos : 0;
      totalSysMise   += stake * combos;
    out += '<div class="slip-sys-row">';
      out += '<span class="slip-sys-lbl">' + lvl.lbl + '</span>';
      out += '<span class="slip-sys-cnt">' + combos + ' x</span>';
      out += '<input type="text" inputmode="decimal" class="slip-stake-inp" value="' + (stake || '') + '" autocomplete="off" placeholder="Fixer la mise" oninput="window.sbUpdateSysLevelStake(\'' + lvl.id + '\',this.value)">';
    out += '</div>';
    });

    // Summary
    out += '<div class="slip-summary">';
    out += '<div class="slip-sum-row"><span>Nombre de paris</span><span id="slip-sys-count">' + totalBetsCount + '</span></div>';
    out += '<div class="slip-sum-row"><span>Mise totale</span><span id="slip-sys-mise">' + totalSysMise.toFixed(2) + '</span></div>';
    out += '</div>';
  }

  // ── Place bet button ───────────────────────────────────────
  out += '<div class="slip-place-wrap">';
  out += '<button class="slip-bookmark-btn" title="Sauvegarder">'
    + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>'
    + '</button>';
  // Determine the total stake for the button label
  var _btnStake = 0;
  if (SLIP_MODE === 'combi') {
    _btnStake = SLIP_COMBI_STAKE || 0;
  } else if (SLIP_MODE === 'simple') {
    S.betSlip.forEach(function(b) { _btnStake += (b.stake || SLIP_STAKE || 0); });
  } else {
    // system mode — sum all level stakes * combos
    _btnStake = 0; // already shown in summary
  }
  var _isLoggedIn = (typeof isLoggedIn !== 'undefined') ? isLoggedIn : true;
  var _btnLbl, _btnClick;
  if (!_isLoggedIn) {
    _btnLbl = 'Connectez-vous pour placer des paris';
    _btnClick = 'window.location.href="/"';
  } else if (_btnStake > 0) {
    _btnLbl = 'Placer votre pari (' + _btnStake.toFixed(2) + ' TND)';
    _btnClick = 'window.sbPlaceBet()';
  } else {
    _btnLbl = 'Placer votre pari';
    _btnClick = 'window.sbPlaceBet()';
  }
  out += '<button class="slip-place-btn" onclick="' + _btnClick + '">' + _btnLbl + '</button>';
  out += '</div>';

  el.innerHTML = out;
}

window.sbPlaceBet = function() {
  if (!S.betSlip.length) return;

  // Calculate total amount and odds based on mode
  var totalAmount = 0;
  var totalOdds = 1;

    if (SLIP_MODE === 'combi') {
    totalAmount = SLIP_COMBI_STAKE || 0;
    S.betSlip.filter(function(b) { return !b.excluded; }).forEach(function(b) {
      totalOdds *= (parseFloat(b.val) || 1);
    });
  } else if (SLIP_MODE === 'simple') {
    S.betSlip.forEach(function(b) { totalAmount += (b.stake || SLIP_STAKE || 0); });
    // Simple: each bet is independent — sum potential returns
    totalOdds = S.betSlip.length ? (S.betSlip.reduce(function(acc, b) {
      return acc + (b.stake || SLIP_STAKE || 0) * (parseFloat(b.val) || 1);
    }, 0) / totalAmount) : 1;
  } else {
    // System mode
    S.betSlip.filter(function(b) { return !b.excluded; }).forEach(function(b) {
      totalOdds *= (parseFloat(b.val) || 1);
    });
    totalAmount = SLIP_STAKE || 0;
  }

  if (totalAmount <= 0) {
    alert('Veuillez entrer une mise valide.');
    return;
  }

  var confirmMsg = 'Confirmer votre pari ?\n\nMise: ' + totalAmount.toFixed(2) + ' TND\n';
  confirmMsg += 'Sélections: ' + S.betSlip.map(function(b) {
    return b.sel + ' @ ' + (parseFloat(b.val) || 0).toFixed(2);
  }).join(', ');
  confirmMsg += '\nGain potentiel: ' + (totalAmount * totalOdds).toFixed(2) + ' TND';

  var confirmed = confirm(confirmMsg);
  if (confirmed) {
    var payload = {
      mode: SLIP_MODE,
      amount: totalAmount,
      total_odds: totalOdds,
      slip: S.betSlip
    };

    fetch(BASE + 'sportsbook/place_bet.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        // Update the balance display in the header if present
        if (typeof data.new_balance !== 'undefined') {
          var balEls = document.querySelectorAll('.sb-balance-val, .user-balance, [data-balance]');
          balEls.forEach(function(el) {
            el.textContent = parseFloat(data.new_balance).toFixed(2) + ' TND';
          });
        }
        var placedBetId   = data.bet_id || 0;
        var placedAmount  = totalAmount;
        var placedReturns = totalAmount * totalOdds;
        var placedMode    = SLIP_MODE === 'combine' ? 'Combiné' : (SLIP_MODE === 'system' ? 'Système' : 'Simple');
        // Clear slip
        S.betSlip = [];
        SLIP_COMBI_STAKE = 0;
        SLIP_STAKE = 0;
        renderBetSlip();
        // Replace slip body with success card (image 2 reference) — but keep
        // the drawer open so the user can immediately access "Mes paris" or
        // print the ticket.
        var bodyEl = document.querySelector('.sb-right .sb-slip-body, .sb-slip-body');
        if (bodyEl && placedBetId) {
          bodyEl.innerHTML =
            '<div class="sb-bet-card" style="margin:8px 0;">' +
              '<div class="sb-bet-card-body" style="display:block;padding:14px;border-top:0">' +
                '<div class="sb-bet-card-row" style="border-bottom:1px dashed rgba(255,255,255,0.06)"><span>Numéro d\'identification</span><span class="v">' + placedBetId + '</span></div>' +
                '<div class="sb-bet-card-row"><span>Mise totale</span><span class="v">TND ' + placedAmount.toFixed(2) + '</span></div>' +
                '<div class="sb-bet-card-row"><span>Gagner</span><span class="v green">TND ' + placedReturns.toFixed(2) + '</span></div>' +
                '<div class="sb-bet-card-row" style="border-bottom:0"><span>Type</span><span class="v">' + placedMode + '</span></div>' +
              '</div>' +
              '<div class="sb-bet-card-foot" style="justify-content:space-between">' +
                '<button class="btn-icon" title="Imprimer" onclick="window.sbPrintBet(' + placedBetId + ',\'copy\')">' +
                  '<svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4V1.5h8V4M4 12H2.5V6.5h11V12H12M4 9.5h8V15H4V9.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>' +
                '</button>' +
                '<div style="display:flex;gap:8px;flex:1;justify-content:flex-end">' +
                  '<button class="sb-slip-btn sb-slip-btn-green" style="padding:7px 14px;border-radius:6px;border:0;background:#2bcd62;color:#000;font-weight:700;cursor:pointer" onclick="window.sbOpenMyBets()">Mes paris</button>' +
                '</div>' +
              '</div>' +
            '</div>';
        }
        // Update the floating bet badge — will hide cleanly since the
        // slip is empty, and will properly reset visibility so it can
        // reappear on the next bet click.
        updateFloatingBetBadge();
      } else {
        alert('❌ Erreur: ' + data.message);
      }
    })
    .catch(function(err) {
      alert('❌ Erreur de connexion. Vérifiez votre connexion et réessayez.');
      console.error('[place_bet]', err);
    });
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

window.sbOpenLeague = function(id, name, flag, sid, _skipPush) {
  S.homeLeagueFilter = name;
  S.activeLeagueId   = id;
  S.activeLeagueName = name;
  S.activeLeagueFlag = flag;
  S.activeSportId    = sid || 1;
  S.viewMode         = 'championship';
  S.mobLeagueTab     = 'best';
  if (!_skipPush) {
    // flag is NOT stored in URL (it's derived from the name on restore)
    sbPushUrl('championship', {championshipIds: id||'', sportId: sid||1, name: name||''});
  }

  // Hide homepage-only sections when entering championship view
  sbSetHomepanelVisible(false);

  var el = document.getElementById('sb-matches-body');

  // Instantly pre-populate with any matching live/upcoming matches already
  // in memory so the user sees content immediately rather than a blank screen
  // while we wait for the API round-trip.
  var preMatches = (S.matches || []).filter(function(m) {
    if (!m.league) return false;
    if (sid && parseInt(m.sport_id || 1) !== parseInt(sid || 1)) return false;
    return isLeagueMatch(name, m.league.name);
  });
  if (preMatches.length) {
    S.champMatches = preMatches;
    if (el) el.innerHTML = '';     // suppress skeleton — we already have data
    renderChampionship(id, name, flag, preMatches);
  } else {
  if (el) el.innerHTML = buildSkeleton(4);
  }

  // Token so a late response from a previous league click can't overwrite
  // the championship view if the user has since navigated to a different league.
  var token = (S._champFetchToken = (S._champFetchToken || 0) + 1);

  var searchTerm = LEAGUE_DB_SEARCH[name] || name;
  var url = BASE + 'sportsbook/api.php?action=league_matches&sport_id=' + (sid || 1)
          + '&league=' + encodeURIComponent(searchTerm)
          + (id ? '&league_id=' + encodeURIComponent(id) : '');

  fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      // Drop late response if the user navigated to a different
      // championship / left championship view entirely.
      if (token !== S._champFetchToken) return;
      if (S.viewMode !== 'championship') return;
      var res = (d && d.results) ? d.results : [];

      // Client-side precision filter — ALWAYS apply, return empty if none match
      // This prevents showing all-sport fallback matches when a specific league has no games today
      var refined = res.filter(function(m) {
        return m.league && isLeagueMatch(name, m.league.name);
      });
      // Fallback: if precision filter removes all matches, but the server sent matches
      // specifically for this league query, show the server's matches instead of nothing.
      if (refined.length > 0) res = refined;

      // Initialize date offset if entering from period page
      if (typeof S.champDateOffset === 'undefined') {
        S.champDateOffset = (S.activeDateOffset > 0) ? S.activeDateOffset : 'tout';
      }

      S.champMatches = res; // save so polling can update them
      renderChampionship(id, name, flag, window.sbGetFilteredChampMatches());

      // ── Odds re-poll: api.php fills prematch odds asynchronously in the background.
      // If matches have no odds yet (odds_pending > 0), re-fetch after 2.5s so the
      // user sees real odds instead of lock icons. Retry up to 3 times.
      var missingOdds = res.filter(function(m) {
        var lo = m.live_odds;
        return !(lo && lo.h && parseFloat(lo.h) >= 1.01);
      });
      if (missingOdds.length > 0 || (d && d.odds_pending > 0)) {
        var retries = 0;
        var maxRetries = 3;
        var champToken = token;
        var repoll = function() {
          if (champToken !== S._champFetchToken) return;
          if (S.viewMode !== 'championship') return;
          if (retries >= maxRetries) return;
          retries++;
          fetch(url + '&_r=' + Date.now())
            .then(function(r2) { return r2.json(); })
            .then(function(d2) {
              if (champToken !== S._champFetchToken) return;
              if (S.viewMode !== 'championship') return;
              var res2 = (d2 && d2.results) ? d2.results : [];
              var refined2 = res2.filter(function(m) { return m.league && isLeagueMatch(name, m.league.name); });
              if (refined2.length > 0) res2 = refined2;
              // Check if we got new odds
              var gotOdds = res2.some(function(m) { var lo = m.live_odds; return lo && lo.h && parseFloat(lo.h) >= 1.01; });
              S.champMatches = res2;
              renderChampionship(id, name, flag, window.sbGetFilteredChampMatches());
              // Keep retrying if still missing odds
              var stillMissing = res2.filter(function(m) { var lo = m.live_odds; return !(lo && lo.h && parseFloat(lo.h) >= 1.01); });
              if (stillMissing.length > 0 && retries < maxRetries) {
                setTimeout(repoll, 3000);
              }
            })
            .catch(function() {});
        };
        setTimeout(repoll, 2500);
      }
    })
    .catch(function() {
      if (token !== S._champFetchToken) return;
      if (S.viewMode !== 'championship') return;
      S.champMatches = [];
      renderChampionship(id, name, flag, []);
    });
};

window.sbGetFilteredChampMatches = function() {
  var res = S.champMatches || [];
  var offset = typeof S.champDateOffset !== 'undefined' ? S.champDateOffset : 'tout';
  if (offset !== 'tout') {
    var now = new Date(); now.setHours(0,0,0,0);
    var target = new Date(now); target.setDate(target.getDate() + offset);
    res = res.filter(function(m) {
      var ts = parseInt(m.time || m.start_time || 0) || 0;
      if (!ts) return false;
      var md = new Date(ts * 1000); md.setHours(0,0,0,0);
      return md.getTime() === target.getTime();
    });
  }
  return res;
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

  // ── Breadcrumb: [<] [Football] [Angleterre] [Premier League] — bordered pills (all clickable)
  out += '<div class="sb-champ-breadcrumb">';
  out += '<button class="sb-bc-pill sb-champ-back-btn" onclick="window.sbBackToMain()" aria-label="Retour">' + ICON.arrowLeft + '</button>';
  out += '<button class="sb-bc-pill" onclick="window.sbBcSport(' + sport.id + ')">' + h(sport.name) + '</button>';
  if (country && country !== 'International') {
    out += '<button class="sb-bc-pill" onclick="window.sbBcCountry(\'' + country.replace(/'/g,"\\'") + '\',' + (sport.id||1) + ')">' + h(country) + '</button>';
  }
  out += '<button class="sb-bc-pill sb-bc-active">' + h(displayName) + '</button>';
  out += '</div>';

  // ── Top tabs: Cotes de match | Victoire finale | Cotes boostées ─────────────
  var topTabs = ['Cotes de match', 'Victoire finale', 'Cotes boostées'];
  S.champTopTab = (typeof S.champTopTab !== 'undefined') ? S.champTopTab : 0;
  out += '<div class="sb-champ-top-tabs">';
  topTabs.forEach(function(t, i) {
    out += '<button type="button" class="sb-ctt' + (S.champTopTab === i ? ' active' : '') + '" onclick="window.sbChampTopTab(' + i + ')">' + t + '</button>';
  });
  out += '<span class="sb-ctt-more">&rsaquo;</span>';
  out += '</div>';

  // ── Sub-nav: Par Ligue | Par Heure ────────────────────────────────────
  var groupMode = S.champGroupMode || 'league';
  out += '<div class="sb-champ-subnav">';
  out += '<button type="button" class="sb-subnav-btn' + (groupMode === 'league' ? ' active' : '') + '" onclick="window.sbChampGroupMode(\'league\')">Par Ligue</button>';
  out += '<button type="button" class="sb-subnav-btn' + (groupMode === 'hour' ? ' active' : '') + '" onclick="window.sbChampGroupMode(\'hour\')">Par Heure</button>';
  out += '</div>';

  // ── Date filter: Tout + upcoming dates ────────────────────────────────
  out += '<div class="sb-champ-date-row">';
  var activeDate = (typeof S.champDateOffset !== 'undefined') ? S.champDateOffset : 'tout';
  out += '<button class="sb-champ-date' + (activeDate === 'tout' ? ' active' : '') + '" onclick="window.sbChampDateFilter(\'tout\')">Tout</button>';
  var fr_days_short = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
  for (var di = 0; di < 7; di++) {
    var dts = new Date(); dts.setDate(dts.getDate() + di);
    var dlbl = fr_days_short[dts.getDay()] + ', ' + String(dts.getDate()).padStart(2,'0') + '/' + String(dts.getMonth()+1).padStart(2,'0');
    out += '<button class="sb-champ-date' + (activeDate === di ? ' active' : '') + '" onclick="window.sbChampDateFilter(' + di + ')">' + dlbl + '</button>';
  }
  out += '</div>';

  // ── Market type tabs (horizontal scroll) ──────────────────────────────
  var mktTypeTabs = ['Tout','Principaux','Spéciale joueurs','1 minute','Mi-temps 1','Mi-temps 2','Teams H2H','Correct Score','Corners','Cartes','Multi Chance','Multigoals','Combo'];
  S.champMktTab = (typeof S.champMktTab !== 'undefined') ? S.champMktTab : 1;
  out += '<div class="sb-champ-mkt-tabs">';
  mktTypeTabs.forEach(function(t, i) {
    out += '<button class="sb-cmt' + (S.champMktTab === i ? ' active' : '') + '" onclick="window.sbChampMktTab(' + i + ', \'' + t.replace(/'/g, "\\'") + '\')">' + t + '</button>';
  });
  out += '</div>';

  // ── Market category dropdown (fcbet216 .HeaderMarketsSelectorContainer)
  //    A single card: green trigger button on top with the active market
  //    label + chevron toggle. Expanded body shows the OTHER markets as a
  //    vertical list of rows (.SelectMenuOptionContainer pattern).
  //    Selecting one switches the active market and collapses the menu.
  var activeCat = S.activeMarketCat || '1x2';
  var marketOpts = [
    {key:'1x2',           label:'1x2'},
    {key:'total',         label:'Total'},
    {key:'double_chance', label:'Double chance'},
    {key:'btts',          label:'Les deux équipes qui marquent'},
    {key:'handicap',      label:'Handicap'},
    {key:'ht_1x2',        label:'1ère mi-temps - 1x2'},
    {key:'ht_total',      label:'1ère mi-temps - total'}
  ];
  var activeOpt = marketOpts.find(function(o){ return o.key === activeCat; }) || marketOpts[0];
  var accOpen   = !!S.champMktAccOpen; // default collapsed (fcbet216 UX)
  out += '<div class="sb-champ-mkt-acc' + (accOpen ? ' open' : ' collapsed') + '" id="sb-champ-mkt-acc">';
  out += '<button type="button" class="sb-champ-mkt-acc-hdr" onclick="window.sbToggleChampMktAcc()">'
       +   '<span class="sb-champ-mkt-acc-lbl">' + h(activeOpt.label) + '</span>'
       +   '<span class="sb-champ-mkt-acc-tgl" aria-hidden="true">' + (accOpen ? '&minus;' : '&#9662;') + '</span>'
       + '</button>';
  out += '<div class="sb-champ-mkt-acc-body"' + (accOpen ? '' : ' style="display:none"') + '>';
  // EXACT fcbet216 .SelectMenuOptionContainer / .hMbNfr spec (user-provided):
  //   width:100%; display:grid; grid-template-columns:1fr 20px;
  //   padding:12px 10px; border-radius:4px; background:rgb(74,74,74);
  //   color:#fff; font Roboto 14px / 500 / 16px line-height
  // Inline styles so neither Bootstrap nor a cached style.css can win.
  var optStyle =
      'display:grid !important;grid-template-columns:1fr 20px !important;'
    + 'place-items:center !important;width:100% !important;'
    + 'padding:12px 10px !important;margin:0 !important;'
    + 'background:rgb(74,74,74) !important;border:0 !important;border-radius:4px !important;'
    + 'color:rgb(255,255,255) !important;font-family:Roboto,sans-serif !important;'
    + 'font-size:14px !important;font-weight:500 !important;line-height:16px !important;'
    + 'letter-spacing:0 !important;text-transform:none !important;'
    + 'cursor:pointer !important;outline:none !important;'
    + 'box-shadow:rgba(13,13,13,0) 0 0 0 0, rgba(13,13,13,0) 0 0 0 0 inset !important;'
    + 'text-shadow:rgba(13,13,13,0) 0 0 0 !important;'
    + '-webkit-appearance:none !important;-moz-appearance:none !important;appearance:none !important;';
  marketOpts.forEach(function(o) {
    if (o.key === activeOpt.key) return;
    out += '<button type="button" class="sb-champ-mkt-opt" '
         + 'style="' + optStyle + '" '
         + 'onclick="window.sbSwitchMarketCat(\'' + o.key + '\')">'
         +    h(o.label)
         + '</button>';
  });
  out += '</div>'; // sb-champ-mkt-acc-body
  out += '</div>'; // sb-champ-mkt-acc

  // ── Matches list (always visible, OUTSIDE the dropdown) ───────────────
  out += '<div class="sb-champ-matches">';

  if (!matches.length) {
    out += '<div class="sb-loader" style="margin-top:16px">Aucun match disponible pour cette ligue.</div>';
  } else {
    var groups = {}, order = [];
    var byHour = (S.champGroupMode === 'hour');
    matches.forEach(function(m) {
      var k;
      if (byHour) {
        var ts = parseInt(m.time || m.start_time || 0) || 0;
        if (ts) {
          var d = new Date(ts * 1000);
          var hh = String(d.getHours()).padStart(2,'0');
          var mm = String(d.getMinutes()).padStart(2,'0');
          var dnames = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
          k = dnames[d.getDay()] + ' ' + String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + ' · ' + hh + 'h' + mm;
        } else {
          k = 'En direct';
        }
      } else {
        k = (m.league && m.league.name) ? m.league.name : name;
      }
      if (!groups[k]) { groups[k] = []; order.push(k); }
      groups[k].push(m);
    });
    S.champLeagueCollapsed = S.champLeagueCollapsed || {};
    order.forEach(function(lg, idx) {
      var lc = guessCountry(lg);
      var lf = getFlag(lc);
      var lcl = (lc && lc !== 'International') ? (' · ' + h(lc)) : '';
      var lgKey = encodeURIComponent(lg);
      var isCollapsed = !!S.champLeagueCollapsed[lg];
      var hasLive = groups[lg].some(function(m){ return m.time_status === '1'; });
      out += '<div class="sb-league-acc' + (isCollapsed ? ' collapsed' : ' open') + '" data-lg="' + h(lg) + '">';
      out += '<button type="button" class="sb-league-acc-hdr" onclick="window.sbToggleChampLeague(\'' + lgKey + '\')">';
      out += '<span class="sb-lh-star" onclick="event.stopPropagation()">' + ICON.star + '</span>';
      out += '<img src="' + lf + '" class="sb-league-f" onerror="this.src=\'https://flagcdn.com/w20/un.png\'">';
      out += '<span class="sb-league-n">' + h(stripCountryPrefix(lg)||lg) + lcl + '</span>';
      if (hasLive) out += '<span class="mc-live-badge" style="margin-left:auto;font-size:10px;padding:2px 5px;line-height:12px;height:16px;">EN DIRECT</span>';
      out += '<span class="sb-league-acc-tgl" aria-hidden="true">' + (isCollapsed ? '&#9662;' : '&minus;') + '</span>';
      out += '</button>';
      out += '<div class="sb-league-acc-body"' + (isCollapsed ? ' style="display:none"' : '') + '>';
      groups[lg].forEach(function(m) { out += matchCard(m); });
      out += '</div>';
      out += '</div>';
    });
  }

  out += '</div>'; // sb-champ-matches

  out += '</div>'; // sb-champ-view
  el.innerHTML = out;
  if (typeof window.sbRestoreExpandedCards === 'function') {
    window.sbRestoreExpandedCards();
  }
}

/* Championship date filter helper */
/* ── Market category switcher — re-renders championship with new market ── */
window.sbSwitchMarketCat = function(cat) {
  if (cat === 'all_markets') return; // TODO: open full markets view
  S.activeMarketCat = cat;
  S.champMktAccOpen = false; // collapse dropdown after selection (fcbet UX)
  renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.champMatches);
};

/* ── Toggle the overall market accordion (e.g. "Vainqueur") open/close ── */
window.sbToggleChampMktAcc = function() {
  var acc = document.getElementById('sb-champ-mkt-acc');
  if (!acc) return;
  var open = acc.classList.contains('open');
  acc.classList.toggle('open', !open);
  acc.classList.toggle('collapsed', open);
  var body = acc.querySelector('.sb-champ-mkt-acc-body');
  var tgl  = acc.querySelector('.sb-champ-mkt-acc-tgl');
  if (body) body.style.display = open ? 'none' : '';
  if (tgl)  tgl.innerHTML = open ? '&#9662;' : '&minus;';
  S.champMktAccOpen = !open;
};

/* ── Switch championship grouping: "league" or "hour" ────────────────── */
window.sbChampGroupMode = function(mode) {
  S.champGroupMode = (mode === 'hour') ? 'hour' : 'league';
  S.champLeagueCollapsed = {}; // reset accordion state on regroup
  renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, window.sbGetFilteredChampMatches());
};

/* ── Toggle a single league section open/close (chevron / minus) ─────── */
window.sbToggleChampLeague = function(lgKey) {
  var lg = decodeURIComponent(lgKey);
  S.champLeagueCollapsed = S.champLeagueCollapsed || {};
  var nextCollapsed = !S.champLeagueCollapsed[lg];
  S.champLeagueCollapsed[lg] = nextCollapsed;
  // Update DOM directly (no full re-render — keeps scroll position).
  var sel = '.sb-league-acc[data-lg="' + lg.replace(/"/g,'\\"') + '"]';
  var node = document.querySelector(sel);
  if (!node) return;
  node.classList.toggle('collapsed', nextCollapsed);
  node.classList.toggle('open', !nextCollapsed);
  var body = node.querySelector('.sb-league-acc-body');
  var tgl  = node.querySelector('.sb-league-acc-tgl');
  if (body) body.style.display = nextCollapsed ? 'none' : '';
  if (tgl)  tgl.innerHTML = nextCollapsed ? '&#9662;' : '&minus;';
};

window.sbChampDateFilter = function(offset) {
  S.champDateOffset = offset;
  renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, window.sbGetFilteredChampMatches());
};

window.sbChampTopTab = function(index) {
  S.champTopTab = index;
  renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, window.sbGetFilteredChampMatches());
};

window.sbChampMktTab = function(index, label) {
  S.champMktTab = index;
  var l = (label || '').toLowerCase();
  if (l.indexOf('mi-temps') !== -1) S.activeMarketCat = 'ht_1x2';
  else if (l.indexOf('principaux') !== -1 || l.indexOf('tout') !== -1) S.activeMarketCat = '1x2';
  else if (l.indexOf('total') !== -1 || l.indexOf('buts') !== -1 || l.indexOf('multigoals') !== -1) S.activeMarketCat = 'total';
  
  renderChampionship(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, window.sbGetFilteredChampMatches());
};

/* ── Breadcrumb navigation helpers ─────────────────────────────────────────
   Sport pill  → go back to sport live view
   Country pill → filter league list to show that country's leagues
   League pill  → open championship page for that league              */
window.sbBcSport = function(sportId) {
  // Reset back to main live view for the sport (clears every filter)
  S.activeLeagueId   = null;
  S.activeLeagueName = null;
  S.activeLeagueFlag = null;
  S.activeMatchId    = null;
  S.champMatches     = [];
  S.viewMode         = 'main';
  S.homeLeagueFilter = null;
  S.homeRegionFilter = null;
  S.activeCountryFilter = null;
  clearInterval(window._mdTimerInterval);
  if (S._mdPollInterval) { clearInterval(S._mdPollInterval); S._mdPollInterval = null; }
  var viewer = document.getElementById('sb-match-viewer');
  if (viewer) viewer.style.display = 'none';
  sbSetHomepanelVisible(true);
  var root = document.querySelector('.sb-root');
  if (root) root.removeAttribute('data-view');
  S.activeSportId = sportId || 1;
  S.activeAction  = 'inplay';
  sbPushUrl('main');
  loadAndFilter('inplay', S.activeSportId, null);
  try { window.scrollTo({ top: 0, behavior: 'instant' }); } catch(e) { window.scrollTo(0,0); }
};

window.sbBcCountry = function(country, sportId) {
  // Filter: show all matches whose league's guessed country matches.
  // For continental regions ("Europe", "Americas", "Africa", "World",
  // "International") we use a region filter so every UEFA / CAF / CONMEBOL
  // competition shows up — not just leagues with the literal word in the name.
  S.activeLeagueId   = null;
  S.activeLeagueName = null;
  S.activeLeagueFlag = null;
  S.activeMatchId    = null;
  S.champMatches     = [];
  S.viewMode         = 'main';
  S.activeCountryFilter = country;
  clearInterval(window._mdTimerInterval);
  if (S._mdPollInterval) { clearInterval(S._mdPollInterval); S._mdPollInterval = null; }
  var viewer = document.getElementById('sb-match-viewer');
  if (viewer) viewer.style.display = 'none';
  sbSetHomepanelVisible(true);
  var root = document.querySelector('.sb-root');
  if (root) root.removeAttribute('data-view');
  S.activeSportId = sportId || 1;
  S.activeAction  = 'inplay';
  sbPushUrl('main');

  var REGIONS = ['Europe','Americas','Africa','World','International'];
  if (REGIONS.indexOf(country) !== -1) {
    S.homeLeagueFilter = null;
    S.homeRegionFilter = country;
  } else {
    S.homeLeagueFilter = country;
    S.homeRegionFilter = null;
  }
  loadAndFilter('inplay', S.activeSportId, null);
  try { window.scrollTo({ top: 0, behavior: 'instant' }); } catch(e) { window.scrollTo(0,0); }
};

window.sbBcLeague = function(leagueName, leagueId, sportId, flag) {
  // Navigate to championship page for the league
  window.sbGoChampionship(leagueId, leagueName, flag || '', sportId || S.activeSportId || 1);
};

window.sbGoChampionship = function(id, name, flag, sid) {
  // Delegate to the actual championship / league loader
  window.sbOpenLeague(id || null, name, flag || getFlag(guessCountry(name)), sid || S.activeSportId || 1);
};


window.sbBackToMain = function() {
  var prevLeague = S.activeLeagueName;
  sbAbortMdFetches();
  sbNextNav();
  S.activeLeagueId   = null;
  S.activeLeagueName = null;
  S.activeLeagueFlag = null;
  S.activeMatchId    = null;
  S.viewMode         = 'main';
  S.champMatches     = [];
  // Restore the previous league filter if we came from a league view.
  // Region filter is always cleared so the user sees the full live feed.
  S.homeRegionFilter = null;
  if (prevLeague) S.homeLeagueFilter = prevLeague;
  clearInterval(window._mdTimerInterval);
  if (S._mdPollInterval) { clearInterval(S._mdPollInterval); S._mdPollInterval = null; }
  var viewer = document.getElementById('sb-match-viewer');
  if (viewer) viewer.style.display = 'none';

  // Clear URL back to base path
  sbPushUrl('main');

  // Restore all homepage panels and exit any data-view mode so the
  // date row, favoris, sport nav, live carousel and boost section all
  // come back.
  sbSetHomepanelVisible(true);
  var rootB = document.querySelector('.sb-root');
  if (rootB) rootB.removeAttribute('data-view');

  loadAndFilter(S.activeAction, S.activeSportId, null);
  try { window.scrollTo({ top: 0, behavior: 'instant' }); } catch (e) { window.scrollTo(0, 0); }
};

/* Mobile league tab switcher — syncs BOTH sidebar + inline league sections */
function highlightHomeLeagueFilter() {
  if (!S.homeLeagueFilter) return;
  document.querySelectorAll('.sb-tl-item').forEach(function(el) {
    var nameEl = el.querySelector('.sb-league-name');
    if (!nameEl) return;
    var match = isLeagueMatch(S.homeLeagueFilter, nameEl.textContent.trim());
    el.classList.toggle('sb-tl-selected', match);
  });
}

window.sbMobLeagueTab = function(el, tab) {
  S.mobLeagueTab = tab;
  document.querySelectorAll('.sb-mob-tab[data-tab]').forEach(function(t) {
    t.classList.toggle('active', t.getAttribute('data-tab') === tab);
  });

  var sidebarBest = document.getElementById('sb-mob-best-leagues');
  var sidebarMy   = document.getElementById('sb-mob-my-leagues');
  var inlineBest  = document.getElementById('sb-inline-best-leagues');
  var inlineMy    = document.getElementById('sb-inline-my-leagues');

  [sidebarBest, inlineBest].forEach(function(e) {
    if (e) e.style.display = (tab === 'best') ? '' : 'none';
  });
  [sidebarMy, inlineMy].forEach(function(e) {
    if (e) e.style.display = (tab === 'my') ? '' : 'none';
  });

  // Returning to "LES MEILLEURES LIGUES" — show matches filtered by last league if any
  if (tab === 'best' && S.homeLeagueFilter && S.viewMode === 'main') {
    renderMatches(S.matches);
    var liveBlock = document.getElementById('sb-live-now-block');
    if (liveBlock) liveBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    S.liveSearchQ = q;

    // Filter top-league sidebar items
    document.querySelectorAll('.sb-tl-item').forEach(function(el) {
      var name = el.querySelector('.sb-league-name');
      if (name) el.style.display = (!q || name.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
    });

    // EN DIRECT live page — fast client-side filter only
    if (S.activeTab === 'live' && S.viewMode === 'main') {
      if (!q) {
        S.liveSearchQ = '';
        renderMatches(S.matches);
        return;
      }
      var liveFiltered = S.matches.filter(isMatchLive).filter(function(m) {
        var home   = ((m.home   && m.home.name)   || '').toLowerCase();
        var away   = ((m.away   && m.away.name)   || '').toLowerCase();
        var league = ((m.league && m.league.name) || '').toLowerCase();
        return home.indexOf(q) !== -1 || away.indexOf(q) !== -1 || league.indexOf(q) !== -1;
      });
      renderMatches(liveFiltered);
      return;
    }

    // Clear → restore full match list
    if (!q) {
      S.liveSearchQ = '';
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
  }, 180);
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
    if (!ts) return isMatchLive(m);
    var inRange = (!minTs || ts >= minTs) && (!maxTs || ts <= maxTs);
    return inRange || isMatchLive(m);
  });
  renderMatches(filtered);
};

window.sbCloseLeft = function() {
  var sidebar  = document.getElementById('sb-left');
  var backdrop = document.getElementById('sb-left-backdrop');
  var root     = document.querySelector('.sb-root');
  if (sidebar)  sidebar.classList.remove('open');
  if (backdrop) backdrop.classList.remove('open');
  if (root)     root.classList.remove('sb-sidebar-open');
  document.body.style.overflow = '';
};
window.sbToggleLeft = function() {
  var sidebar  = document.getElementById('sb-left');
  var backdrop = document.getElementById('sb-left-backdrop');
  var root     = document.querySelector('.sb-root');
  if (!sidebar) return;
  var isOpen = sidebar.classList.toggle('open');
  if (backdrop) backdrop.classList.toggle('open', isOpen);
  if (root)     root.classList.toggle('sb-sidebar-open', isOpen);
  // Prevent body scroll while drawer is open on mobile
  document.body.style.overflow = isOpen ? 'hidden' : '';
};
window.sbToggleRight = function() {
  var right = document.getElementById('sb-right');
  if (!right) return;
  right.classList.toggle('open');
  // Defer to updateFloatingBetBadge which handles the open/closed drawer
  // case AND resets any stale visibility so the FAB reliably reappears
  // when the user closes the drawer and then adds more bets.
  updateFloatingBetBadge();
};

/* Collapse / expand just the slip body (the body, tabs, numpad…).
 * On mobile this also closes the right drawer so the user sees the
 * rest of the page. Triggered by the "—" button in the FICHE DE PARI
 * header. */
window.sbCollapseSlip = function() {
  var panel = document.getElementById('sb-slip-panel');
  if (panel) panel.classList.toggle('slip-collapsed');
  if (window.innerWidth < 1101) {
    // Mobile / tablet: also close the drawer so the page is usable.
    var right = document.getElementById('sb-right');
    if (right) right.classList.remove('open');
    updateFloatingBetBadge();
  }
};

window.sbFilterByDate = function(btn, dayOffset) {
  document.querySelectorAll('.sb-date-item').forEach(function(b) { b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  S.activeDateOffset = dayOffset;
  S.activeAction = (dayOffset === 0) ? 'inplay' : 'upcoming';
  if (dayOffset > 0) S.activeTab = 'home';

  // If currently in a league view, stay in that league but re-filter by date.
  if (S.activeLeagueName) {
    window.sbOpenLeague(S.activeLeagueId, S.activeLeagueName, S.activeLeagueFlag, S.activeSportId);
    return;
  }

  // Aujourd'hui (offset 0) — restore the full home page.
  if (dayOffset === 0) {
    S.viewMode = 'main';
    var root = document.querySelector('.sb-root');
    if (root) root.setAttribute('data-view', '');
    try {
      var u = new URL(window.location.href);
      u.searchParams.delete('page');
      u.searchParams.delete('date');
      history.pushState({ sbView:'main' }, '', u.toString());
    } catch (e) {}
    sbSetHomepanelVisible(true);
    loadAndFilter('inplay', S.activeSportId, null);
    startPolling();
    return;
  }

  // Future date — open the fcbet-style period page (hides home chrome).
  window.sbOpenPeriodPage(dayOffset);
};

/* ════════════════════════════════════════════════════════════════════════
   SPORT PAGE — fcbet216 reference (URL: /sportsbook/prelive?page=sport&sportId=X)
   Tapping a sport tile opens an intermediate "sport page" with four
   category cards (Les matches du jour / demain / prochains / meilleurs
   ligues). Tapping a category card drills into the matches list with
   the 1x2 dropdown + tabs the user showed in image 5.
   ════════════════════════════════════════════════════════════════════════ */
window.sbOpenSportPage = function(sportId) {
  sbAbortListFetches();
  sbAbortMdFetches();
  sbNextNav();
  var sp = null;
  for (var i = 0; i < SPORTS.length; i++) { if (SPORTS[i].id === sportId) { sp = SPORTS[i]; break; } }
  var sportName = sp ? sp.name : 'Sport';

  S.userPickedSport = true;
  S.activeSportId   = sportId;
  S.activeAction    = 'inplay';
  S.activeLeagueId  = null;
  S.activeLeagueName = null;
  S.activeDateOffset = 0;
  S.viewMode        = 'sportPage';
  S.sportPageName   = sportName;

  // Reset poll tick so the first sbPollSportPage cycle re-fetches
  // upcoming as well (catches odds that the async fill just wrote).
  if (typeof sbPollSportPage !== 'undefined') sbPollSportPage._tick = 0;

  // Switch the page into "sport page" mode — CSS hides the home-only
  // sections (date row, favoris, sport nav, EN DIRECT carousel, Cotes
  // boostées, mobile leagues panel) so the user gets a dedicated
  // sport-detail view (matches fcbet216 ?page=sport&sportId=X layout).
  var root = document.querySelector('.sb-root');
  if (root) root.setAttribute('data-view', 'sportpage');

  // Highlight the clicked tile in the nav (even though the nav is
  // hidden in sport-page mode, we still mark it so the state is
  // correct when the user returns home).
  document.querySelectorAll('.sb-sport-item').forEach(function(b) { b.classList.remove('active'); });
  var navBtn = document.querySelector('.sb-sport-item[data-sid="' + sportId + '"]');
  if (navBtn) navBtn.classList.add('active');

  renderSportPage(sportId, sportName);
  if (typeof sbPushUrl === 'function') {
    try { sbPushUrl('sport', { sportId: sportId }); } catch (e) {}
  }
  window.scrollTo({ top: 0, behavior: 'instant' in window.scrollTo ? 'instant' : 'auto' });
};

function renderSportPage(sportId, sportName) {
  var el = document.getElementById('sb-matches-body');
  if (!el) return;

  var hdr =
    '<div class="sb-sport-page-header">' +
      '<button class="sb-sp-back" onclick="window.sbBackToHome()" aria-label="Retour">' +
        '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
      '</button>' +
      '<span class="sb-sp-pill">' + h(sportName) + '</span>' +
    '</div>';

  // Show skeleton immediately so the page never flashes empty
  el.innerHTML =
    '<div class="sb-sport-page sb-sport-page--loading">' +
      hdr +
      '<div class="sb-sk-cat-card"></div><div class="sb-sk-cat-card"></div><div class="sb-sk-cat-card"></div><div class="sb-sk-cat-card"></div>' +
    '</div>' +
    '<div class="sb-sport-page-matches" id="sb-sport-matches">' +
      '<div class="sb-loader">Chargement des matchs…</div>' +
    '</div>';

  // Fetch live + upcoming in parallel. Live = ALL inplay matches for the
  // sport, Upcoming = the next 48h of non-ended matches. Both are used to
  // compute the four category-card counts AND to populate the matches list
  // that lives BELOW the cards on the sport page (matches fcbet216 layout).
  var liveUrl = BASE + 'sportsbook/api.php?action=inplay&sport_id=' + sportId + '&_t=' + Date.now();
  var upUrl   = BASE + 'sportsbook/api.php?action=upcoming&sport_id=' + sportId + '&_t=' + Date.now();

  Promise.all([
    fetch(liveUrl).then(function(r){ return r.json(); }).catch(function(){ return {results: []}; }),
    fetch(upUrl).then(function(r){ return r.json(); }).catch(function(){ return {results: []}; })
  ]).then(function(both) {
    var live     = (both[0] && both[0].results) ? both[0].results : [];
    var upcoming = (both[1] && both[1].results) ? both[1].results : [];

    // Stash so polling can refresh the matches without re-fetching counts
    S.sportPageLive     = live;
    S.sportPageUpcoming = upcoming;

    var now         = Math.floor(Date.now() / 1000);
    var endToday    = now + (24 * 3600);
    var endTomorrow = now + (48 * 3600);
    var jour = 0, demain = 0, prochain = 0, leagues = {};
    upcoming.forEach(function(m) {
      if (!m || !m.id || m.time_status === '3') return;
      var ts = parseInt(m.time || m.start_time || 0) || 0;
      if (ts > 0 && ts <= endToday)               jour++;
      else if (ts > endToday && ts <= endTomorrow) demain++;
      else                                         prochain++;
      var ln = (m.league && m.league.name) ? m.league.name : '';
      if (ln) leagues[ln] = 1;
    });
    // Live matches always count toward "matches du jour" too
    live.forEach(function(m) {
      if (!m || !m.id) return;
      jour++;
      var ln = (m.league && m.league.name) ? m.league.name : '';
      if (ln) leagues[ln] = 1;
    });
    var leaguesCount = Object.keys(leagues).length;

    el.innerHTML =
      '<div class="sb-sport-page">' +
        hdr +
        sbCatCard('today',    'Les matches du jour',    jour,        catIconCalendar()) +
        sbCatCard('tomorrow', 'Les matches de demain',  demain,      catIconArrowIn()) +
        sbCatCard('soon',     'Les prochains matchs',   prochain,    catIconClock()) +
        sbCatCard('leagues',  'Les meilleurs ligues',   leaguesCount,catIconTrophy()) +
      '</div>' +
      '<div class="sb-sport-page-matches" id="sb-sport-matches"></div>';

    renderSportPageMatches(live, upcoming);
  }).catch(function(){
    el.innerHTML =
      '<div class="sb-sport-page">' +
        hdr +
        '<div class="sb-cat-error">Impossible de charger les matches.</div>' +
      '</div>';
  });
}

// Light poll for the sport page — refreshes the live matches every poll
// cycle so timers tick and scores update, AND re-fetches upcoming every
// few cycles so prematch odds that the server-side async fill writes to
// the DB (lock icons -> real values) show up automatically. Counts on
// the four category cards stay stable (computed once on open).
function sbPollSportPage() {
  var sid = S.activeSportId;
  if (!sid) return;

  var t = Date.now();
  var liveUrl = BASE + 'sportsbook/api.php?action=inplay&sport_id=' + sid + '&_t=' + t;
  var upUrl   = BASE + 'sportsbook/api.php?action=upcoming&sport_id=' + sid + '&_t=' + t;

  // Tick counter — refresh upcoming every 3rd cycle (~15s at 5s poll).
  // First call also refreshes upcoming (handles missed odds on initial load).
  sbPollSportPage._tick = (sbPollSportPage._tick || 0) + 1;
  var refreshUpcoming = (sbPollSportPage._tick === 1) || (sbPollSportPage._tick % 3 === 0);

  var fopts = { cache: 'no-store', headers: { 'Cache-Control': 'no-cache' } };
  var liveP = fetch(liveUrl, fopts).then(function(r){ return r.json(); }).catch(function(){ return null; });
  var upP   = refreshUpcoming
    ? fetch(upUrl, fopts).then(function(r){ return r.json(); }).catch(function(){ return null; })
    : Promise.resolve(null);

  Promise.all([liveP, upP]).then(function(both) {
    var liveData = both[0], upData = both[1];
    if (liveData && liveData.results) S.sportPageLive = liveData.results;
    if (upData   && upData.results)   S.sportPageUpcoming = upData.results;
    // Guard: if user clicked into a match between request and now,
    // don't repaint the sport-page on top of the match detail view.
    if (S.viewMode === 'matchDetail' || S.viewMode === 'sportPage') return;
    renderSportPageMatches(S.sportPageLive || [], S.sportPageUpcoming || []);
  });
}

// Render live + upcoming matches BELOW the four category cards. Each match
// uses the standard matchCard() helper so the row layout (BB + EN DIRECT +
// timer + flag + league + teams + odds) is identical to the home view.
function renderSportPageMatches(live, upcoming) {
  var holder = document.getElementById('sb-sport-matches');
  if (!holder) return;

  var sortedLive = (typeof sortLiveMatches === 'function') ? sortLiveMatches(live) : (live || []);
  var sortedUp   = (typeof sortUpcomingMatches === 'function') ? sortUpcomingMatches(upcoming) : (upcoming || []);

  var out = '';

  if (sortedLive.length) {
    out += '<div class="sb-section-title sb-sport-section-title"><span>En direct maintenant</span></div>';
    sortedLive.slice(0, 30).forEach(function(m) {
      try { out += matchCard(m); } catch (e) {}
    });
  }

  if (sortedUp.length) {
    out += '<div class="sb-section-title sb-sport-section-title"><span>Prochainement</span></div>';
    sortedUp.slice(0, 50).forEach(function(m) {
      try { out += matchCard(m); } catch (e) {}
    });
  }

  if (!out) {
    out = '<div class="sb-loader">Aucun match disponible pour ce sport.</div>';
  }

  holder.innerHTML = out;
  // Kick the live-minute ticker so timers on live cards update immediately
  if (typeof startLiveMinuteTicker === 'function') {
    try { startLiveMinuteTicker(); } catch (e) {}
  }
}

function sbCatCard(kind, label, count, iconSvg) {
  return '<button type="button" class="sb-cat-card" onclick="window.sbOpenCategory(\'' + kind + '\')">' +
    '<span class="sb-cat-ico">' + iconSvg + '</span>' +
    '<span class="sb-cat-label">' + h(label) + '</span>' +
    '<span class="sb-cat-count">' + count + '</span>' +
  '</button>';
}
function catIconCalendar() {
  return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 9h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
}
function catIconArrowIn() {
  return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 12h6M12 9l3 3-3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}
function catIconClock() {
  return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.5V12L15 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}
function catIconTrophy() {
  return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 4h10v4a5 5 0 01-10 0V4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M5 6H3v2a3 3 0 003 3M19 6h2v2a3 3 0 01-3 3M10 13h4M12 13v4M9 19h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

window.sbOpenCategory = function(kind) {
  S.viewMode       = 'sportCategory';
  S.sportCategory  = kind;
  // Stay in the sport-page mode (still hide home sections) but flip
  // the content to the matches list. We use a sibling data-view value
  // so CSS can keep the home siblings hidden but allow the back
  // button + matches body.
  var root = document.querySelector('.sb-root');
  if (root) root.setAttribute('data-view', 'sportpage');

  // Map kind -> date offset / live filter
  if (kind === 'tomorrow') { S.activeDateOffset = 1; S.activeAction = 'upcoming'; }
  else if (kind === 'soon'){ S.activeDateOffset = 0; S.activeAction = 'upcoming'; }
  else                     { S.activeDateOffset = 0; S.activeAction = 'inplay'; }
  loadAndFilter(S.activeAction, S.activeSportId, null);
};

window.sbBackToHome = function() {
  S.userPickedSport  = false;
  S.viewMode         = 'main';
  S.activeSportId    = 1;
  S.activeAction     = 'inplay';
  S.activeDateOffset = 0;
  S.activeLeagueId   = null;
  S.activeLeagueName = null;
  // Exit sport-page mode so home siblings (live cards, boost section,
  // mobile leagues panel, sport nav, date row, favoris) reappear.
  var root = document.querySelector('.sb-root');
  if (root) root.removeAttribute('data-view');
  document.querySelectorAll('.sb-sport-item').forEach(function(b) { b.classList.remove('active'); });
  loadAndFilter('inplay', 1, null);
  if (typeof sbPushUrl === 'function') { try { sbPushUrl('main'); } catch (e) {} }
  window.scrollTo({ top: 0, behavior: 'instant' in window.scrollTo ? 'instant' : 'auto' });
};

// Switch sport while staying on EN DIRECT tab (Football / Basketball / …)
window.sbSwitchLiveSport = function(sportId, btn) {
  S.activeTab = 'live';
  S.activeSportId = sportId || 1;
  S.activeAction = 'inplay';
  S.activeDateOffset = 0;
  S.activeLeagueId = null;
  S.viewMode = 'main';
  var root = document.querySelector('.sb-root');
  if (root) root.setAttribute('data-view', 'livepage');
  document.querySelectorAll('.sb-upcoming-tab').forEach(function(b) { b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  sbSetHomepanelVisible(false);
  loadAndFilter('inplay', sportId, null);
  startPolling();
};

// Switch to live view (En direct button)
// Sync EN DIRECT / Home active state on desktop sidebar + mobile topbar
function sbSetTopbarActive(mode) {
  document.querySelectorAll('.sb-top-bar, .sb-mobile-topbar').forEach(function(bar) {
    var homeBtn = bar.querySelector('.sb-btn-home');
    var liveBtn = bar.querySelector('.sb-btn-live');
    bar.querySelectorAll('.sb-btn-home, .sb-btn-live').forEach(function(b) { b.classList.remove('active'); });
    if (mode === 'live') {
      if (liveBtn) liveBtn.classList.add('active');
    } else if (homeBtn) {
      homeBtn.classList.add('active');
    }
  });
}

window.sbSwitchLive = function(btn) {
  document.querySelectorAll('.sb-sport-item').forEach(function(b) { b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  S.activeTab = 'live';
  S.activeSportId = 1; S.activeAction = 'inplay'; S.activeLeagueId = null; S.activeDateOffset = 0;
  S.viewMode = 'main';
  S.userPickedSport = false;
  var rootLive = document.querySelector('.sb-root');
  if (rootLive) rootLive.setAttribute('data-view', 'livepage');
  sbSetTopbarActive('live');
  sbSetHomepanelVisible(false);
  loadAndFilter('inplay', 1, null);
  startPolling();
};

window.sbSwitchTab = function(btn, action, sportId) {
  // Close Mes Paris page if it was open
  var _mb = document.getElementById('sb-mybets-page');
  if (_mb && _mb.style.display !== 'none') {
    _mb.style.display = 'none';
    var _mbody = document.getElementById('sb-matches-body');
    if (_mbody) _mbody.style.display = '';
    document.querySelectorAll('.sb-btn-mybets').forEach(function(b){ b.classList.remove('active'); });
  }

  sbSetTopbarActive(action === 'live' ? 'live' : 'home');

  S.activeTab = (action === 'live') ? 'live' : 'home';
  if (action !== 'live') S.liveSearchQ = '';
  S.activeSportId = sportId || 1;
  S.activeAction = (action === 'live' || action === 'home') ? 'inplay' : action;
  S.activeLeagueId = null;
  S.activeDateOffset = (action === 'upcoming') ? 1 : 0;
  S.activeLeagueName = null; S.activeLeagueFlag = null;
  S.activeMatchId    = null; S.viewMode         = 'main';
  S.champMatches     = [];
  S.userPickedSport  = false;
  var rootHL = document.querySelector('.sb-root');
  if (rootHL) {
    if (action === 'live') {
      rootHL.setAttribute('data-view', 'livepage');
      sbSetHomepanelVisible(false);
    } else {
      rootHL.removeAttribute('data-view');
      sbSetHomepanelVisible(true);
    }
  }
  clearInterval(window._mdTimerInterval);
  var _v = document.getElementById('sb-match-viewer');
  if (_v) _v.style.display = 'none';
  sbPushUrl('main');

  if (btn && btn.classList.contains('sb-sport-item')) {
     document.querySelectorAll('.sb-sport-item').forEach(function(b) { b.classList.remove('active'); });
     btn.classList.add('active');
  } else if (action !== 'live') {
     renderSportNav();
  }

  loadAndFilter(S.activeAction, S.activeSportId, null);
  startPolling();
};

// Scroll the sport nav (whole inner row now scrolls natively)
window.sbScrollNav = function() {
  var inner = document.querySelector('.sb-sport-nav-inner') || document.querySelector('.sb-sport-nav');
  if (inner) inner.scrollBy({ left: 200, behavior: 'smooth' });
};

/* ══════════════════════════════════════════════════════════
   MATCH DETAIL VIEW — Images 3 & 4
   ══════════════════════════════════════════════════════════ */
/* Toggle inline markets view on a match card (fcbet216 UX).
   Click chevron → expands ALL markets (tabs + accordions) right inside
   the card, no navigation. Click again → collapses. Markets are fetched
   on first expand and cached on window._mcMktCache so subsequent
   open/close is instant. Expanded state is preserved across polling
   re-renders via window._mcExpandedSet + sbRestoreExpandedCards(). */
window._mcMktCache    = window._mcMktCache || {};
window._mcExpandedSet = window._mcExpandedSet || {};

function _mcSkeletonHTML() {
  // Skeleton matches the real inline UI: a tabs row + 4 accordion bars.
  var out = '<div class="mc-md-inner mc-md-skeleton">';
  out += '<div class="mc-md-tabs">';
  out += '<span class="mc-sk mc-sk-tab" style="width:90px"></span>';
  out += '<span class="mc-sk mc-sk-tab" style="width:90px"></span>';
  out += '<span class="mc-sk mc-sk-tab" style="width:90px"></span>';
  out += '<span class="mc-sk mc-sk-tab" style="width:70px"></span>';
  out += '</div>';
  out += '<div class="mc-md-markets">';
  for (var i = 0; i < 4; i++) {
    out += '<div class="mc-sk mc-sk-row"></div>';
  }
  out += '</div>';
  out += '</div>';
  return out;
}

window.sbToggleMc = function(mid, ev) {
  // Always cancel propagation — clicking the chevron must NEVER trigger
  // the parent .mc onclick that routes to the match-detail page.
  if (ev && typeof ev.stopPropagation === 'function') ev.stopPropagation();
  var card = document.getElementById('mc-' + mid);
  var box  = document.getElementById('mc-md-' + mid);
  if (!card || !box) return;
  var isOpen = (box.getAttribute('data-open') === '1');

  var chev = card.querySelector('.mc-chevron-btn');
  if (isOpen) {
    box.style.display = 'none';
    box.setAttribute('data-open', '0');
    card.classList.remove('mc-inline-open');
    if (chev) chev.classList.remove('mc-chevron-up');
    delete window._mcExpandedSet[String(mid)];
    return;
  }

  // Opening — render from cache instantly if we have it; else fetch.
  box.style.display = '';
  box.setAttribute('data-open', '1');
  card.classList.add('mc-inline-open');
  if (chev) chev.classList.add('mc-chevron-up');
  window._mcExpandedSet[String(mid)] = true;

  var cached = window._mcMktCache[mid];
  if (cached && cached.match && cached.markets) {
    box.innerHTML = renderInlineMatchMarkets(mid, cached.match, cached.markets, cached.tab || 'Principaux');
    return;
  }

  // Render INSTANTLY from local match data (no skeleton delay).
  // If we have the match in memory, build fallback markets and paint
  // right away — the user sees real markets in < 1 frame. Then fire
  // the API fetch in background and upgrade when it resolves.
  var localMatch = (typeof sbFindMatch === 'function') ? sbFindMatch(mid) : null;
  if (localMatch) {
    var fb = [];
    window._mcMktCache[mid] = { match: localMatch, markets: fb, tab: 'Principaux' };
    box.innerHTML = renderInlineMatchMarkets(mid, localMatch, fb, 'Principaux');
  } else {
    box.innerHTML = _mcSkeletonHTML();
  }

  // Background fetch — upgrades to real API markets (more selections,
  // real handicap/total lines) without re-collapsing the card.
  fetch(BASE + 'sportsbook/api.php?action=match_detail&match_id=' + encodeURIComponent(mid))
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (box.getAttribute('data-open') !== '1') return;
      var m    = (d && d.match) ? d.match : localMatch;
      var mkts = (d && d.markets && d.markets.length) ? d.markets : null;
      if (!m) return;
      if (!mkts || !mkts.length) { mkts = []; }
      var activeTab = ((window._mcMktCache[mid] || {}).tab) || 'Principaux';
      window._mcMktCache[mid] = { match: m, markets: mkts, tab: activeTab };
      box.innerHTML = renderInlineMatchMarkets(mid, m, mkts, activeTab);
    })
    .catch(function() { /* fallback already rendered — nothing to do */ });
};

/* Diff previous markets vs next markets and annotate each new selection
   with _change = 'up' | 'down' | null so renderMktBtn() shows the ▲/▼
   arrow + flash animation. Mirrors the diff inside patchMatchDetailLive(). */
function _mcDiffMarkets(prevMarkets, nextMarkets) {
  var prevIdx = {};
  (prevMarkets || []).forEach(function(pm) {
    (pm.selections || []).forEach(function(ps) {
      var key = (pm.id || pm.name || '') + '|' + (ps.id || ps.name || '');
      var pv = parseFloat(ps.odds);
      if (!isNaN(pv) && pv > 1.01) prevIdx[key] = pv;
    });
  });
  (nextMarkets || []).forEach(function(nm) {
    (nm.selections || []).forEach(function(ns) {
      var key = (nm.id || nm.name || '') + '|' + (ns.id || ns.name || '');
      var nv  = parseFloat(ns.odds);
      var pv  = prevIdx[key];
      if (pv && !isNaN(nv) && nv > 1.01 && Math.abs(nv - pv) >= 0.01) {
        ns._change = (nv > pv) ? 'up' : 'down';
      } else {
        ns._change = null;
      }
    });
  });
}

/* Re-apply inline expansion to every card the user had previously opened.
   Called after every match-list re-render (renderMatches, renderMatchGroups,
   period page, championship view) so polling refreshes don't silently
   collapse the user's open cards. Also re-builds the inline markets from
   the fresh match data so live odds stay accurate (green ▲ / red ▼
   arrows on changes). */
window.sbRestoreExpandedCards = function() {
  var set = window._mcExpandedSet || {};
  Object.keys(set).forEach(function(mid) {
    if (!set[mid]) return;
    var card = document.getElementById('mc-' + mid);
    var box  = document.getElementById('mc-md-' + mid);
    if (!card || !box) {
      // Card no longer in the DOM — drop from set so it doesn't accumulate.
      delete window._mcExpandedSet[mid];
      return;
    }
    var cached = (window._mcMktCache || {})[mid];
    box.style.display = '';
    box.setAttribute('data-open', '1');
    card.classList.add('mc-inline-open');
    var chev = card.querySelector('.mc-chevron-btn');
    if (chev) chev.classList.add('mc-chevron-up');
    if (!cached || !cached.markets) return;

    // Pull the FRESH match data from the in-memory list. If odds changed,
    // rebuild fallback markets, annotate _change for the arrows, and
    // re-paint. Keeps the inline view truly live across poll cycles.
    var fresh = (typeof sbFindMatch === 'function') ? sbFindMatch(mid) : null;
    if (fresh) {
      var prevOdds = cached.match && cached.match.live_odds ? JSON.stringify(cached.match.live_odds) : '';
      var nextOdds = fresh.live_odds ? JSON.stringify(fresh.live_odds) : '';
      if (prevOdds !== nextOdds) {
        var nextMkts = null;
        if (nextMkts && nextMkts.length) {
          _mcDiffMarkets(cached.markets, nextMkts);
          cached.match   = fresh;
          cached.markets = nextMkts;
        }
      }
    }

    box.innerHTML = renderInlineMatchMarkets(mid, cached.match, cached.markets, cached.tab || 'Principaux');
  });
};

/* Render the inline markets view (tabs + accordions) inside a card.
   Uses the same renderMarketGroup() the full match-detail page uses
   so the look-and-feel is identical (images 7 / 2-6 in the spec). */
function renderInlineMatchMarkets(mid, m, markets, activeTab) {
  activeTab = activeTab || 'Principaux';
  var TABS = ['Principaux','Bet Builder','Teams H2H','1 minute'];

  var out = '<div class="mc-md-inner" data-mid="' + h(String(mid)) + '">';

  // Tabs row (mirrors the full match-detail tabs, compact). Each tab
  // explicitly stopPropagation()s so the card-level onclick (which
  // routes to the dedicated detail page) never fires.
  out += '<div class="mc-md-tabs" onclick="event.stopPropagation()">';
  TABS.forEach(function(t) {
    var isAct = (t === activeTab);
    out += '<button type="button" class="mc-md-tab' + (isAct?' active':'') + '"'
        +  ' data-tab="' + h(t) + '"'
        +  ' onclick="event.stopPropagation();window.sbInlineMcTab(this,\'' + String(mid) + '\',\'' + t.replace(/'/g,"\\'") + '\')">' + h(t) + '</button>';
  });
  out += '<button type="button" class="mc-md-info" aria-label="Légende" onclick="event.stopPropagation()">'
      + '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#f04a4a" stroke-width="2.2"><circle cx="12" cy="12" r="10"/></svg>'
      + '</button>';
  out += '</div>';

  // Filter markets by active tab (same logic as match-detail page)
  var isBB = (activeTab === 'Bet Builder');
  var filter = null;
  if (!isBB && activeTab !== 'Tout') {
    var kw = activeTab.toLowerCase();
    if (activeTab === 'Principaux') {
      filter = function(mk){
        var nm = (mk.name||'').toLowerCase();
        if (nm.indexOf('2ème mi-temps') !== -1) return false;
        return (typeof MD_FLASH_MARKETS !== 'undefined')
          ? MD_FLASH_MARKETS.some(function(k){ return nm.indexOf(k) !== -1; })
          : true;
      };
    } else if (activeTab === '1 minute') {
      filter = function(mk){
        var nm = (mk.name||'').toLowerCase();
        return (typeof MD_1MIN_MARKETS !== 'undefined')
          ? MD_1MIN_MARKETS.some(function(k){ return nm.indexOf(k) !== -1; })
          : false;
      };
    } else if (activeTab === 'Teams H2H') {
      filter = function(mk){
        var nm = (mk.name||'').toLowerCase();
        return nm.indexOf('h2h') !== -1 || nm.indexOf('head to head') !== -1
            || nm.indexOf('face') !== -1 || nm === '1x2' || nm.indexOf('1 x 2') !== -1;
      };
    } else {
      filter = function(mk){ return (mk.name||'').toLowerCase().indexOf(kw) !== -1; };
    }
  }
  var shown = filter ? markets.filter(filter) : markets;
  if (!shown.length) shown = markets;
  if (activeTab === 'Principaux' && typeof sortMarketsForPrincipaux === 'function') {
    shown = sortMarketsForPrincipaux(shown);
  }

  out += '<div class="mc-md-markets">';
  if (!shown.length) {
    out += '<div class="mc-md-empty">Aucun marché disponible.</div>';
  } else {
    shown.forEach(function(mk, i) {
      out += renderMarketGroup(mk, m, i < 4, isBB);
    });
  }
  out += '</div>';

  out += '</div>';
  return out;
}

/* Tab click inside an inline expanded card — re-renders the markets list
   for the new tab without collapsing the card or fetching again. */
window.sbInlineMcTab = function(btn, mid, tabName) {
  var box = document.getElementById('mc-md-' + mid);
  if (!box) return;
  var cache = (window._mcMktCache || {})[mid];
  if (!cache || !cache.match || !cache.markets) return;
  cache.tab = tabName;
  box.innerHTML = renderInlineMatchMarkets(mid, cache.match, cache.markets, tabName);
};

window.sbOpenMatch = function(mid, _skipPush) {
  if (!mid) return;
  // Already on this match — ignore duplicate clicks (prevents double-fetch).
  if (S.viewMode === 'matchDetail' && String(S.activeMatchId) === String(mid)) return;

  sbAbortListFetches();   // cancel any in-flight home list fetch
  sbAbortMdFetches();
  var navGen = sbNextNav();

  S.activeMatchId = mid;
  S.viewMode = 'matchDetail';
  if (!_skipPush) {
    sbPushUrl('liveEvent', {eventId: mid, sportId: S.activeSportId || 1});
  }
  sbSetHomepanelVisible(false);
  var rootMd = document.querySelector('.sb-root');
  if (rootMd) rootMd.setAttribute('data-view', 'matchdetail');
  try { window.scrollTo({ top: 0, behavior: 'instant' }); } catch (e) { window.scrollTo(0, 0); }

  var el = document.getElementById('sb-matches-body');
  // Look in every cached list — home, league, sport page — so we can
  // paint instantly even when the user clicked from a sub-view.
  var cached = sbFindMatch(mid);
  if (cached && el) {
    renderMatchDetail(cached, []);
    startMatchDetailPoll(mid);
  } else if (el) {
    el.innerHTML = buildMatchDetailSkeleton();
  }

  var fetchOpts = {};
  if (S._mdAbort) fetchOpts.signal = S._mdAbort.signal;
  fetch(BASE + 'sportsbook/api.php?action=match_detail&match_id=' + encodeURIComponent(mid), fetchOpts)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!sbNavAlive(navGen)) return;
      if (S.viewMode !== 'matchDetail' || String(S.activeMatchId) !== String(mid)) return;
      var m = (d && d.match) ? d.match : cached;
      var mkts = (d && d.markets) ? d.markets : [];
      if (!m) return;
      sbSyncMatchCache(m);
      if (cached || document.getElementById('md-period-block')) {
        patchMatchDetailLive(m, mkts);
        var body = document.getElementById('md-markets-body');
        if (body && mkts && mkts.length) {
          var bbActive = document.querySelector('.md-tab.md-tab--active');
          var bbMode = !!(bbActive && /bet builder/i.test(bbActive.textContent || ''));
          window._mdMarkets = mkts;
          sbClearMdTabCache();
          body.innerHTML = mkts.map(function(mk, i){ return renderMarketGroup(mk, m, i < 6, bbMode); }).join('');
          try { if (typeof sbMdPruneEmptyTabs === 'function') sbMdPruneEmptyTabs(); } catch(e) {}
        }
      } else {
        renderMatchDetail(m, mkts);
        startMatchDetailPoll(mid);
      }
    })
    .catch(function(err) {
      if (err && err.name === 'AbortError') return;
      if (!sbNavAlive(navGen)) return;
      if (S.viewMode !== 'matchDetail') return;
      if (!cached) {
        var m = sbFindMatch(mid);
      if (m) renderMatchDetail(m, []);
      }
    });
};

/* Cold-start skeleton — only shown when the clicked match isn't
 * in S.matches yet (deep-link or browser refresh). Lays out the
 * exact same boxes as renderMatchDetail so there's zero layout
 * shift when the real data lands. Shimmer animation is provided
 * by the .sb-sk-block class (already in style.css). */
function buildMatchDetailSkeleton() {
  var out = '<div class="md-view md-view--compact">';
  // Breadcrumb chip row
  out += '<div class="sb-champ-breadcrumb md-bc-row">'
    +   '<div class="sb-sk-block" style="width:36px;height:32px;border-radius:8px"></div>'
    +   '<div class="sb-sk-block" style="width:72px;height:32px;border-radius:8px"></div>'
    +   '<div class="sb-sk-block" style="width:100px;height:32px;border-radius:8px"></div>'
    +   '<div class="sb-sk-block" style="width:140px;height:32px;border-radius:8px"></div>'
    + '</div>';
  // Compact match card
  out += '<div class="md-card md-card--compact">'
    +   '<div class="md-card-top">'
    +     '<div class="sb-sk-block" style="width:18px;height:13px;border-radius:2px"></div>'
    +     '<div class="sb-sk-block" style="width:160px;height:12px;flex:1"></div>'
    +     '<div class="sb-sk-block" style="width:96px;height:12px"></div>'
    +   '</div>'
    +   '<div class="md-card-period">'
    +     '<div class="sb-sk-block" style="width:22px;height:22px;border-radius:50%"></div>'
    +     '<div class="sb-sk-block" style="width:140px;height:14px"></div>'
    +   '</div>'
    +   '<div class="md-card-teams" style="grid-template-columns:1fr auto auto auto 1fr">'
    +     '<div class="sb-sk-block" style="width:48px;height:14px;justify-self:end"></div>'
    +     '<div class="sb-sk-block" style="width:28px;height:28px;border-radius:50%"></div>'
    +     '<div class="sb-sk-block" style="width:56px;height:18px"></div>'
    +     '<div class="sb-sk-block" style="width:28px;height:28px;border-radius:50%"></div>'
    +     '<div class="sb-sk-block" style="width:48px;height:14px;justify-self:start"></div>'
    +   '</div>'
    +   '<div class="md-stats-bar">'
    +     [1,2,3,4,5].map(function(){ return '<div class="md-stat"><div class="sb-sk-block" style="width:48px;height:12px"></div></div>'; }).join('')
    +   '</div>'
    + '</div>';
  // Tabs row
  out += '<div class="md-tabs-row">'
    + [40,72,90,80,110,90].map(function(w){ return '<div class="sb-sk-block" style="width:'+w+'px;height:36px;border-radius:8px;flex-shrink:0"></div>'; }).join('')
    + '</div>';
  // Market group placeholders
  out += '<div class="md-markets">' + [1,2,3,4].map(function(i){
    var rows = i === 2 ? 2 : 1;  // Total card gets a second row
    var btnHtml = '<div class="md-mkt-row">'
      + '<div class="sb-sk-block" style="height:44px;flex:1;border-radius:6px"></div>'
      + '<div class="sb-sk-block" style="height:44px;flex:1;border-radius:6px"></div>'
      + (i === 1 ? '<div class="sb-sk-block" style="height:44px;flex:1;border-radius:6px"></div>' : '')
      + '</div>';
    var body = '';
    for (var r = 0; r < rows; r++) body += btnHtml;
    return '<div class="md-market-group">'
      +    '<div class="md-mkt-hdr">'
      +      '<div class="sb-sk-block" style="width:14px;height:14px;border-radius:50%"></div>'
      +      '<div class="sb-sk-block" style="width:100px;height:14px;flex:1"></div>'
      +      '<div class="sb-sk-block" style="width:32px;height:18px;border-radius:3px"></div>'
      +    '</div>'
      +    '<div class="md-mkt-body">' + body + '</div>'
      +  '</div>';
  }).join('') + '</div>';
  out += '</div>';
  return out;
}

/* Toggle a single market accordion (open/close) — mirrors fcbet216 UX.
   Click the header to flip .collapsed and swap the minus/chevron icon. */
window.sbToggleMdMarket = function(hdr) {
  if (!hdr) return;
  var grp = hdr.parentNode;
  if (!grp || !grp.classList) return;
  var nowCollapsed = !grp.classList.contains('collapsed');
  grp.classList.toggle('collapsed', nowCollapsed);
  var openIc   = hdr.querySelector('.md-mkt-ctrl--open');
  var closedIc = hdr.querySelector('.md-mkt-ctrl--closed');
  if (openIc)   openIc.style.display   = nowCollapsed ? 'none' : '';
  if (closedIc) closedIc.style.display = nowCollapsed ? '' : 'none';
  // Persist per-group expanded state so the 1.5s match-detail poll
  // does not re-collapse a market the user just opened.
  if (!S._mdMktState) S._mdMktState = {};
  if (grp.id) S._mdMktState[grp.id] = !nowCollapsed;
};

function renderMatchDetail(m, markets) {
  var el = document.getElementById('sb-matches-body');
  if (!el || !m) return;

  var isLive   = isMatchLive(m);
  var isEnded  = isMatchEnded(m);
  var hn       = m.home ? m.home.name : '';
  var an       = m.away ? m.away.name : '';
  // Full names for display; short only for the breadcrumb pill (3 chars)
  var hShort   = hn.replace(/^(FC|AC|AS|RC|SC|SS|CS|CF|SL|FK|NK|SK|BK)\s+/i,'').substring(0,3).toUpperCase();
  var aShort   = an.replace(/^(FC|AC|AS|RC|SC|SS|CS|CF|SL|FK|NK|SK|BK)\s+/i,'').substring(0,3).toUpperCase();
  // Shorten only if name > 14 chars (to avoid overflow in the compact card)
  var hDisplay = hn.length > 14 ? hn.replace(/^(FC|AC|AS|RC|SC|SS|CS|CF|SL|FK|NK|SK|BK)\s+/i,'') : hn;
  var aDisplay = an.length > 14 ? an.replace(/^(FC|AC|AS|RC|SC|SS|CS|CF|SL|FK|NK|SK|BK)\s+/i,'') : an;
  // Read score with sticky-cache fallback so the score never visually
  // disappears on a reload race or a momentary empty poll.
  var _msc = (typeof _sbReadScore === 'function') ? _sbReadScore(m) : [0, 0, !!m.ss];
  var scoreH   = _msc[2] ? String(_msc[0]) : '';
  var scoreA   = _msc[2] ? String(_msc[1]) : '';
  var sportId  = parseInt(m.sport_id || 1);
  var lg       = m.league ? m.league.name : '';
  var country  = guessCountry(lg);
  var flagUrl  = getFlag(country);
  var period   = isLive ? getMatchPeriod(m) : '';
  var effTmr   = isLive ? effectiveTimer(m) : null;
  var baseMins = effTmr ? (parseInt(effTmr.tm || 0) || 0) : 0;
  var baseSecs = effTmr ? (parseInt(effTmr.ts || 0) || 0) : 0;
  var isHT     = effTmr && (String(effTmr.md||effTmr.MD||'') === '1');
  var timerInit = isHT ? 'Mi-temps' : (String(baseMins).padStart(2,'0') + ':' + String(baseSecs).padStart(2,'0'));

  var ts2 = parseInt(m.time || 0);
  var dObj = ts2 > 0 ? new Date(ts2 * 1000) : new Date();
  var dateStr = String(dObj.getDate()).padStart(2,'0') + '/' + String(dObj.getMonth()+1).padStart(2,'0') + '/' + dObj.getFullYear()
              + ' ' + String(dObj.getHours()).padStart(2,'0') + ':' + String(dObj.getMinutes()).padStart(2,'0');

  // HT score from scores object
  var htScoreH = '', htScoreA = '';
  if (m.scores && m.scores['1']) {
    var hts = m.scores['1'];
    htScoreH = hts.home !== undefined ? hts.home : '';
    htScoreA = hts.away !== undefined ? hts.away : '';
  }

  // Country / sport strings for the breadcrumb (fcbet216 layout).
  var sportObj  = SPORTS.find(function(s) { return s.id === sportId; }) || SPORTS[0];
  var sportName = sportObj ? sportObj.name : 'Football';
  var leagueDisplay = stripCountryPrefix(lg) || lg;
  var countryDisplay = country || '';

  var out = '<div class="md-view md-view--compact">';

  // ── Breadcrumb pill row: [<] Football | Country | League | TeamShort (all clickable)
  out += '<div class="sb-champ-breadcrumb md-bc-row">';
  out += '<button class="sb-bc-pill sb-champ-back-btn" onclick="window.sbBackToMain()" aria-label="Retour">' + ICON.arrowLeft + '</button>';
  out += '<button class="sb-bc-pill" onclick="window.sbBcSport(' + sportId + ')">' + h(sportName) + '</button>';
  if (countryDisplay && countryDisplay !== 'International') {
    out += '<button class="sb-bc-pill" onclick="window.sbBcCountry(\'' + countryDisplay.replace(/'/g,"\\'") + '\',' + sportId + ')">' + h(countryDisplay) + '</button>';
  }
  if (leagueDisplay) {
    var _lgId  = h(String(m.league && m.league.id ? m.league.id : ''));
    var _lgNm  = leagueDisplay.replace(/'/g,"\\'");
    var _lgFlg = flagUrl.replace(/'/g,"\\'");
    out += '<button class="sb-bc-pill" onclick="window.sbBcLeague(\'' + _lgNm + '\',\'' + _lgId + '\',' + sportId + ',\'' + _lgFlg + '\')">' + h(leagueDisplay) + '</button>';
  }
  out += '<button class="sb-bc-pill sb-bc-active">' + h(hShort) + '</button>';
  out += '</div>';

  // ── Compact match-detail card (image 2 / image 5)
  out += '<div class="md-card md-card--compact">';

  // Row 1: flag + league + date
  out += '<div class="md-card-top">';
  out += '<img src="' + flagUrl + '" class="md-card-flag" onerror="this.style.display=\'none\'">';
  out += '<span class="md-card-league">' + h(lg) + '</span>';
  out += '<span class="md-card-date">' + h(dateStr) + '</span>';
  out += '</div>';

  // Row 2 (live only): info dot + period + green timer.
  // We wrap the inner content in #md-period-block so
  // patchMatchDetailLive can refresh ALL of it in one go when the
  // 3s poll lands fresh timer/period data (period text, | separator,
  // timer or Mi-temps label).
  if (isLive && !isEnded) {
    out += '<div class="md-card-period" id="md-period-row">';
    out += '<span class="md-card-info"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="8" cy="12" r="1.2" fill="currentColor"/><circle cx="12" cy="12" r="1.2" fill="currentColor"/><circle cx="16" cy="12" r="1.2" fill="currentColor"/></svg></span>';
    out += '<span class="md-card-period-block" id="md-period-block">';
    out +=   buildMdPeriodBlock(period, isHT, timerInit);
    out += '</span>';
    out += '</div>';
  } else if (isEnded) {
    out += '<div class="md-card-period" id="md-period-row">';
    out += '<span class="md-card-info"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="8" cy="12" r="1.2" fill="currentColor"/><circle cx="12" cy="12" r="1.2" fill="currentColor"/><circle cx="16" cy="12" r="1.2" fill="currentColor"/></svg></span>';
    out += '<span class="md-card-period-block" id="md-period-block">';
    out += '<span class="md-card-timer md-card-timer--ended">Terminé</span>';
    out += '</span>';
    out += '</div>';
  }

  // Row 3: team1 [jersey] score [jersey] team2 — compact single line
  out += '<div class="md-card-teams">';
  out += '<span class="md-card-team-abbr md-card-team-home">' + h(hDisplay) + '</span>';
  out += shirtSVG(hn, 'md-card-jersey', 28);
  out += '<div class="md-card-score-col">';
  if (scoreH !== '' && scoreA !== '') {
    out += '<div class="md-card-score" id="md-score-display">' + h(scoreH) + ' : ' + h(scoreA) + '</div>';
    if (isLive && htScoreH !== '' && htScoreA !== '') {
      out += '<div class="md-card-ht">' + h(htScoreH) + ' : ' + h(htScoreA) + '</div>';
    }
  } else {
    out += '<div class="md-card-score md-card-score--vs">vs</div>';
  }
  out += '</div>';
  out += shirtSVG(an, 'md-card-jersey', 28);
  out += '<span class="md-card-team-abbr md-card-team-away">' + h(aDisplay) + '</span>';
  out += '</div>';

  // Row 4: stats bar (corners, cards, attacks, shots)
  var statsHtml = (isLive || isEnded) ? renderStatsBar(m, sportId) : '';
  if (statsHtml) out += statsHtml;

  out += '</div>'; // md-card

  // ── Main market category tabs — fcbet216 style.
  // Single horizontal row: [search] Tout Principaux Bet Builder Teams H2H
  //   1 minute  2ème mi-temps  Correct Score  Corners  Multigoals  [ⓘ alert]
  // The search button toggles an inline "Search market" input. The
  // red info button reveals a tooltip with the market lock legend.
  var TABS = ['Tout','Principaux','Bet Builder','Teams H2H','1 minute','2ème mi-temps','Correct Score','Corners','Cartes','Multigoals'];
  out += '<div class="md-tabs-wrap" id="md-tabs-wrap">';

  // Search trigger (expands to input when clicked)
  out += '<button type="button" class="md-tab-search-btn" id="md-search-btn"'
       + ' onclick="window.sbMdSearchToggle(true)" title="Rechercher">'
       + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.5" y2="16.5"/></svg>'
    + '</button>';

  // Inline search panel. Hidden by default via CSS rule
  //   .md-search-panel { display:none !important }
  // and revealed via the wrapper class .md-tabs-wrap--search.
  // Do NOT use inline style="display:none" — that fought the
  // class toggle on some browsers.
  out += '<div class="md-search-panel" id="md-search-panel">';
  out += '<button type="button" class="md-tab-search-close" onclick="window.sbMdSearchToggle(false)" title="Fermer">'
       + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>'
       + '</button>';
  out += '<input type="text" class="md-search-input" id="md-search-input" placeholder="Search market" oninput="window.sbMdSearch(this.value)" />';
  out += '</div>';

  // Tabs scroll container (hidden when search active)
  out += '<div class="md-tabs-row" id="md-tabs-inner">';
  TABS.forEach(function(t, i) {
    out += '<button type="button" class="md-tab' + (i === 1 ? ' active' : '') + '"'
         + ' data-tab="' + h(t) + '"'
         + ' onclick="window.sbMdTab(this,\'' + t.replace(/'/g,"\\'") + '\')">' + h(t) + '</button>';
  });
  out += '</div>';

  // Info / alert button at the end
  out += '<button type="button" class="md-tab-info-btn" id="md-tab-info-btn"'
       + ' onclick="window.sbMdToggleInfo()" title="Légende des marchés">'
       + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="13"/><circle cx="12" cy="16" r="0.6" fill="currentColor"/></svg>'
    + '</button>';

  out += '</div>';

  // ── Markets list (always visible; markets switch to BB-mode when tab is active)
  if (!markets.length) markets = [];
  // Merge separate "Goals Over/Under X" market groups from the Bet365 event
  // stream into a single normalised "Total" ladder.
  markets = mergeOuMarkets(markets, m);
  window._mdMarkets = markets;
  window._mdMatch   = m;

  out += '<div class="md-markets" id="md-markets-body">';
  markets.forEach(function(mkt, i) { out += renderMarketGroup(mkt, m, i < 6, false); });
  out += '</div>';

  // Bet Builder sticky footer removed to add bets directly to the betslip (fcbet216 parity).

  out += '</div>'; // md-view

  el.innerHTML = out;

  // ── Post-render
  if (isLive && !isHT) startMatchTimer(m);
  else clearInterval(window._mdTimerInterval);
  showMatchViewer(m);
  // Hide tabs that have zero markets for this match (fcbet216 parity)
  try { sbMdPruneEmptyTabs(); } catch(e) {}
}

/* ── Filter market selections that are already settled by the current
 * score so we render only meaningful bets (matches fcbet216 behavior).
 *
 * For Total (Over/Under) and similar count-based markets, a line where
 * the handicap is ≤ current_total is either certain to win or certain
 * to lose. fcbet216 hides those — only lines where line > current_total
 * are still active bets.
 *
 * Example with current_goals=2:
 *   "Plus de 1"   → CERTAIN WIN (drop)
 *   "Plus de 1.5" → CERTAIN WIN (drop)
 *   "Plus de 2"   → still tied (drop)
 *   "Plus de 2.5" → meaningful (keep)
 *   "Plus de 3.5" → meaningful (keep)
 *
 * We also prefer .5 lines (no push possible) over integer lines if
 * both are present.
 */
/* Sticky cache for the last known live score per match.
 * The match-detail poll can momentarily return an empty m.ss
 * (during the half-time transition, a goal event, or a v3 snapshot
 * race) and we don't want the totals filter to reset to current=0
 * during that flicker. We remember the last NON-ZERO score we saw
 * for each match and use it when m.ss is missing or zero. */
window._sbScoreCache = window._sbScoreCache || {};

function _sbReadScore(m) {
  // Returns [home_g, away_g, totalSeen] where totalSeen is true if
  // we have an actual score (current poll OR sticky cache).
  var mid = m && m.id ? String(m.id) : '';
  // Parse the current poll's m.ss first
  if (m && m.ss) {
    var p = String(m.ss).replace(/\s+/g, '').split(/[-:]/);
    if (p.length >= 2) {
      var h = parseInt(p[0], 10);
      var a = parseInt(p[1], 10);
      if (!isNaN(h) && !isNaN(a)) {
        // Update sticky cache only when it's a forward-progressing score
        // (the cache should never decrease).
        var prev = mid ? window._sbScoreCache[mid] : null;
        if (!prev || h + a >= prev[0] + prev[1]) {
          if (mid) window._sbScoreCache[mid] = [h, a];
        }
        return [Math.max(h, prev ? prev[0] : 0),
                Math.max(a, prev ? prev[1] : 0),
                true];
      }
    }
  }
  // No m.ss this cycle → fall back to sticky cache.
  if (mid && window._sbScoreCache[mid]) {
    var sc = window._sbScoreCache[mid];
    return [sc[0], sc[1], true];
  }
  return [0, 0, false];
}

function filterMarketByScore(mkt, m) {
  if (!mkt || !mkt.selections) return mkt;
  var nm = String(mkt.name || '').toLowerCase().trim();
  var isTotalAll  = /\btotal\b|plus\/moins|over\/under|goals\b/i.test(nm);
  var isTotalHome = /^1\s*total/i.test(nm);
  var isTotalAway = /^2\s*total/i.test(nm);
  var isCorners   = /corner/i.test(nm);
  var isCards     = /carton|card/i.test(nm);
  if (!(isTotalAll || isTotalHome || isTotalAway || isCorners || isCards)) return mkt;

  // Read score with sticky-cache fallback so a momentarily-empty m.ss
  // never resets the filter to current=0.
  var sc = _sbReadScore(m);
  var home_g = sc[0], away_g = sc[1], hasScore = sc[2];
  var current = 0;
  if (isTotalHome) {
    current = home_g;
  } else if (isTotalAway) {
    current = away_g;
  } else if (isTotalAll) {
    current = home_g + away_g;
  } else if (isCorners) {
    if (m && m.stats && m.stats.corners) {
      var c = m.stats.corners;
      var ca = Array.isArray(c) ? c : String(c).split(',');
      current = (parseInt(ca[0], 10) || 0) + (parseInt(ca[1], 10) || 0);
    }
  } else if (isCards) {
    if (m && m.stats && m.stats.yellow_cards) {
      var yc = m.stats.yellow_cards;
      var yca = Array.isArray(yc) ? yc : String(yc).split(',');
      current = (parseInt(yca[0], 10) || 0) + (parseInt(yca[1], 10) || 0);
    }
  }
  // Diagnostic: log when filtering for debugging. Remove later.
  if (window._sbDebugFilter) {
    console.log('[filterMarketByScore]', mkt.name, 'current=', current, 'sels=', mkt.selections.length);
  }

  // Filter selections — drop anything where the line is already settled
  var keptSels = mkt.selections.filter(function(s) {
    var hcStr = String(s.handicap != null ? s.handicap : '');
    // If no numeric handicap, keep (might be score-correct, btts, etc.)
    var hcNum = parseFloat(hcStr);
    if (isNaN(hcNum)) {
      // Try to parse line from name ("Plus de 2.5" → 2.5)
      var fromName = String(s.name || '').match(/(\d+(?:\.\d+)?)/);
      if (!fromName) return true;
      hcNum = parseFloat(fromName[1]);
    }
    var nameLow = String(s.name || '').toLowerCase();
    var isOver  = /plus|over/.test(nameLow);
    var isUnder = /moins|under/.test(nameLow);
    if (isOver) {
      // Over X is still active only when X >= current (a line of X.5
      // with current X is still active; line X with current X pushes).
      // We require the line to be STRICTLY greater than current so we
      // never show a pushed bet.
      return hcNum > current;
    }
    if (isUnder) {
      // Under X is impossible to win once current >= X — drop those.
      // We keep lines where there's still room: line > current.
      return hcNum > current;
    }
    return true;
  });

  if (keptSels.length === 0) return mkt; // never empty out the market
  // If we filtered, return a shallow clone with the new selections so we
  // don't mutate the source market object (preserves prev odds cache).
  if (keptSels.length === mkt.selections.length) return mkt;
  return {
    id: mkt.id, name: mkt.name, handicap: mkt.handicap,
    selections: keptSels
  };
}

function _parseSelectionLine(s) {
  if (!s) return NaN;
  var hc = parseFloat(s.handicap);
  if (!isNaN(hc)) return hc;
  var m = String(s.name || '').match(/(\d+(?:\.\d+)?)/);
  return m ? parseFloat(m[1]) : NaN;
}
function _isOverSelName(n) { return /plus|over/i.test(String(n || '')); }
function _isUnderSelName(n) { return /moins|under/i.test(String(n || '')); }

/* Regex that identifies any Total / Over-Under / Goals market whether it
 * comes from our synthetic fallback ("Total") or directly from the Bet365
 * event stream ("Goals Over/Under 3.5", "Total Goals", "Under/Over" ...). */
var _ouRx = /^total$|total goals|over.under|goals over|under.over/i;

/* Merge multiple separate "Goals Over/Under X" markets that Bet365 returns
 * as individual market-groups into a single normalised "Total" market whose
 * selections are properly labelled ("Plus de X.5" / "Moins de X.5") with
 * the line stored in s.handicap. The resulting market passes cleanly through
 * filterMarketByScore → trimTotalMarketWindow → renderMarketGroup.
 *
 * Also updates m.live_odds.ou_line to the lowest ACTIVE line (the one that
 * keeps buildFallbackMarkets anchored on the correct Bet365 main line). */
function mergeOuMarkets(markets, m) {
  if (!markets || !markets.length) return markets;
  var ouMkts = [], others = [];
  markets.forEach(function(mkt) {
    if (_ouRx.test(mkt.name || '')) ouMkts.push(mkt);
    else others.push(mkt);
  });
  if (!ouMkts.length) return markets;

  // Collect all (line → {over,under}) pairs across all OU markets
  var pairMap = {};
  ouMkts.forEach(function(mkt) {
    // Try to get the line from the market name ("Goals Over/Under 3.5" → 3.5)
    var lineFromName = null;
    var lm = String(mkt.name || '').match(/(\d+(?:\.\d+)?)/);
    if (lm) lineFromName = parseFloat(lm[1]);

    (mkt.selections || []).forEach(function(s) {
      var line = _parseSelectionLine(s);
      if (isNaN(line) && lineFromName !== null) line = lineFromName;
      if (isNaN(line)) return;
      var key = String(line);
      if (!pairMap[key]) pairMap[key] = { line: line, over: null, under: null };
      if (_isOverSelName(s.name)) pairMap[key].over = s;
      else if (_isUnderSelName(s.name)) pairMap[key].under = s;
    });
  });

  // Sort pairs by line ascending; build normalised selections list
  var lineNums = Object.keys(pairMap).map(parseFloat).sort(function(a, b) { return a - b; });
  var mergedSels = [];
  lineNums.forEach(function(line) {
    var p = pairMap[String(line)];
    var overOdds  = p.over  ? parseFloat(p.over.odds)  : 0;
    var underOdds = p.under ? parseFloat(p.under.odds) : 0;
    if (overOdds < 1.01 && underOdds < 1.01) return; // skip locked pair
    // Normalise label and set handicap
    if (p.over) {
      mergedSels.push({
        id: (p.over.id  || 'ov_' + line),
        name: 'Plus de ' + line,
        odds: overOdds,
        handicap: line,
        _change: p.over._change || null
      });
    }
    if (p.under) {
      mergedSels.push({
        id: (p.under.id || 'un_' + line),
        name: 'Moins de ' + line,
        odds: underOdds,
        handicap: line,
        _change: p.under._change || null
      });
    }
  });

  if (!mergedSels.length) return markets; // nothing to merge

  var totalMkt = { id: 'total', name: 'Total', selections: mergedSels };

  // Update m.live_odds.ou_line to the lowest ACTIVE OU line so that
  // buildFallbackMarkets uses the correct anchor on the next poll.
  if (m) {
    var sc = _sbReadScore(m);
    var cur = sc[0] + sc[1];
    var firstActive = lineNums.find(function(ln) { return ln > cur; });
    if (firstActive !== undefined) {
      if (!m.live_odds) m.live_odds = {};
      if (!m.live_odds.ou_line || m.live_odds.ou_line < firstActive) {
        m.live_odds.ou_line = firstActive;
        // Cache reset so odds() picks fresh value
        m._o = null;
      }
    }
  }

  // Replace all OU markets with the single merged "Total" — keep insertion order
  var result = [];
  var inserted = false;
  markets.forEach(function(mkt) {
    if (_ouRx.test(mkt.name || '')) {
      if (!inserted) { result.push(totalMkt); inserted = true; }
    } else {
      result.push(mkt);
    }
  });
  return result;
}

/* fcbet216 Principaux / Bet Builder show 2 active O/U line pairs
 * anchored on the Bet365 main line — NOT the full 0.5→6.5 ladder.
 * Example 0-0 @ 3': Plus de 3.5 / 4.5 (not 0.5 / 1.5 / 2.5). */
function trimTotalMarketWindow(mkt, m, maxPairs) {
  if (!mkt || !mkt.selections) return mkt;
  maxPairs = maxPairs || 2;
  var nm = String(mkt.name || '').toLowerCase().trim();
  // Accept "Total", "Goals Over/Under 3.5", "Total Goals", etc.
  var isTotalAll  = /^total$|total goals|over.under|goals over|under.over/i.test(mkt.name || '');
  var isTotalHome = /^1\s*total/i.test(nm);
  var isTotalAway = /^2\s*total/i.test(nm);
  var isCorners   = /corner/i.test(nm);
  var isCards     = /carton|card/i.test(nm);
  if (!(isTotalAll || isTotalHome || isTotalAway || isCorners || isCards)) return mkt;

  var pairs = {};
  mkt.selections.forEach(function(s) {
    var line = _parseSelectionLine(s);
    if (isNaN(line)) return;
    var key = String(line);
    if (!pairs[key]) pairs[key] = { line: line, over: null, under: null, other: [] };
    if (_isOverSelName(s.name)) pairs[key].over = s;
    else if (_isUnderSelName(s.name)) pairs[key].under = s;
    else pairs[key].other.push(s);
  });

  var lineList = Object.keys(pairs).map(function(k) { return pairs[k]; })
    .filter(function(p) { return p.over || p.under; })
    .sort(function(a, b) { return a.line - b.line; });
  if (lineList.length <= maxPairs) return mkt;

  // Drop trivial overs (Plus de 0.5 @ 1.01) — fcbet hides these.
  lineList = lineList.filter(function(p) {
    var ov = p.over ? parseFloat(p.over.odds) : 0;
    return !(ov > 0 && ov < 1.12);
  });
  if (lineList.length <= maxPairs) {
    var quick = [];
    lineList.forEach(function(p) {
      if (p.over) quick.push(p.over);
      if (p.under) quick.push(p.under);
      quick = quick.concat(p.other);
    });
    if (!quick.length) return mkt;
    return { id: mkt.id, name: mkt.name, handicap: mkt.handicap, selections: quick };
  }

  var anchor = null;
  if (isTotalAll) {
    var lo = odds(m);
    anchor = parseFloat(lo.ou);
    if (isNaN(anchor)) anchor = null;
  }
  if (anchor == null) {
    var bestDist = 999;
    lineList.forEach(function(p) {
      var ov = p.over ? parseFloat(p.over.odds) : 0;
      if (ov < 1.05) return;
      var dist = Math.abs(ov - 1.85);
      if (dist < bestDist) { bestDist = dist; anchor = p.line; }
    });
  }

  var sc = _sbReadScore(m);
  var floor = 0;
  if (isTotalHome) floor = sc[0] + 0.5;
  else if (isTotalAway) floor = sc[1] + 0.5;
  else if (isTotalAll) floor = sc[0] + sc[1] + 0.5;
  else if (isCorners && m && m.stats && m.stats.corners) {
    var ca = Array.isArray(m.stats.corners) ? m.stats.corners : String(m.stats.corners).split(',');
    floor = (parseInt(ca[0], 10) || 0) + (parseInt(ca[1], 10) || 0) + 0.5;
  } else if (isCards && m && m.stats && m.stats.yellow_cards) {
    var yca = Array.isArray(m.stats.yellow_cards) ? m.stats.yellow_cards : String(m.stats.yellow_cards).split(',');
    floor = (parseInt(yca[0], 10) || 0) + (parseInt(yca[1], 10) || 0) + 0.5;
  }

  var eligible = lineList.filter(function(p) { return p.line >= floor - 0.001; });
  if (!eligible.length) eligible = lineList;

  var startIdx = 0;
  if (anchor != null) {
    for (var ai = 0; ai < eligible.length; ai++) {
      if (eligible[ai].line >= anchor - 0.001) { startIdx = ai; break; }
    }
  } else if (floor > 0) {
    for (var fi = 0; fi < eligible.length; fi++) {
      if (eligible[fi].line >= floor - 0.001) { startIdx = fi; break; }
    }
  }

  var picked = eligible.slice(startIdx, startIdx + maxPairs);
  if (picked.length < maxPairs && eligible.length > maxPairs) {
    picked = eligible.slice(Math.max(0, eligible.length - maxPairs));
  }

  var newSels = [];
  picked.forEach(function(p) {
    if (p.over) newSels.push(p.over);
    if (p.under) newSels.push(p.under);
    newSels = newSels.concat(p.other);
  });
  if (!newSels.length) return mkt;
  return { id: mkt.id, name: mkt.name, handicap: mkt.handicap, selections: newSels };
}

function renderMarketGroup(mkt, m, expanded, bbMode) {
  // Filter out already-settled lines so e.g. a 2:0 match no longer
  // shows "Plus de 1 / 1.5 / 2" — only "Plus de 2.5 / 3.5 …" (fcbet216 behavior).
  mkt = filterMarketByScore(mkt, m);
  // Principaux trims to 2 active pairs (compact view).
  // Bet Builder / Tout / Total tab show ALL active lines so users can mix legs.
  var trimMax = bbMode ? 99 : 2;
  mkt = trimTotalMarketWindow(mkt, m, trimMax);
  // Stable id so the toggle handler can target this exact group.
  var grpId = 'md-mkt-' + (mkt.id || (mkt.name||'mkt').replace(/[^a-z0-9]/gi,'_'));
  // Honor any user-driven expand/collapse override stored in S._mdMktState.
  // Without this, the 1.5s match-detail poll re-renders the markets and
  // any group the user just opened immediately collapses back to its
  // initial "expanded by index" state.
  if (S && S._mdMktState && S._mdMktState.hasOwnProperty(grpId)) {
    expanded = !!S._mdMktState[grpId];
  }
  var out = '<div class="md-market-group' + (expanded ? '' : ' collapsed') + (bbMode ? ' bb-mode' : '') + '" id="' + h(grpId) + '">';
  out += '<div class="md-mkt-hdr" onclick="window.sbToggleMdMarket(this)">';
  out += '<span class="md-mkt-star" onclick="event.stopPropagation()">' + ICON.star + '</span>';
  out += '<span class="md-mkt-name">' + h(mkt.name) + '</span>';
  // Grid icons only for Total / Handicap in normal mode
  if (!bbMode && /^(total|handicap)$/i.test(mkt.name)) {
    out += '<span class="md-mkt-grid-icons" onclick="event.stopPropagation()">'
      + '<svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="1" width="6" height="6"/><rect x="9" y="1" width="6" height="6"/><rect x="1" y="9" width="6" height="6"/><rect x="9" y="9" width="6" height="6"/></svg>'
      + '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="1" y1="4" x2="15" y2="4"/><line x1="1" y1="8" x2="15" y2="8"/><line x1="1" y1="12" x2="15" y2="12"/></svg>'
      + '</span>';
  }
  out += '<span class="md-mkt-bb' + (bbMode ? ' md-mkt-bb-active' : '') + '">BB</span>';
  // Swap icon: minus when open, chevron-down when collapsed — fcbet216 UX
  out += '<span class="md-mkt-ctrl md-mkt-ctrl--open" aria-hidden="true"'
       + (expanded ? '' : ' style="display:none"') + '>' + ICON.minus + '</span>';
  out += '<span class="md-mkt-ctrl md-mkt-ctrl--closed" aria-hidden="true"'
       + (expanded ? ' style="display:none"' : '') + '>' + ICON.chevronDown + '</span>';
  out += '</div>';
  out += '<div class="md-mkt-body">';

  // Give renderMktBtn access to the current market name for BB leg labeling
  renderMktBtn._curMkt = mkt.name || '';

  var sels  = mkt.selections || [];
  var n     = sels.length;
  var isTotal = /^total$/i.test(mkt.name);
  var isHandicap = /^handicap$/i.test(mkt.name);
  var hasRange = isTotal || isHandicap;
  var isHc1x2  = /handicap 1x2/i.test(mkt.name);
  var is3x3    = /mi-temps.*fin|marge|dc.*mi-temps/i.test(mkt.name);

  if (hasRange) {
    var groups = {}, gord = [];
    sels.forEach(function(s) {
      var hc = parseFloat(s.handicap);
      if (isNaN(hc)) hc = 0;
      if (!groups[hc]) { groups[hc] = []; gord.push(hc); }
      groups[hc].push(s);
    });
    gord.sort(function(a, b) { return a - b; });

    var uid = (mkt.id || 'rng') + '_' + (m.id || 'm');
    var activeIdx = Math.floor(gord.length / 2);
    // For Over/Under we prefer a line close to current score if possible
    if (isTotal && m.live_odds && m.live_odds.ou_line) {
      var closest = -1, minDist = 999;
      gord.forEach(function(hc, idx) {
        var d = Math.abs(hc - m.live_odds.ou_line);
        if (d < minDist) { minDist = d; closest = idx; }
      });
      if (closest !== -1) activeIdx = closest;
    }
    var activeHc = gord[activeIdx];

    if (gord.length > 0) {
      out += '<div id="slider-body-' + uid + '">';
      gord.forEach(function(hc, idx) {
        out += '<div class="md-mkt-row md-slider-group" data-hc-idx="' + idx + '" ' + (idx === activeIdx ? '' : 'style="display:none"') + '>';
        groups[hc].forEach(function(s) { out += renderMktBtn(s, m, bbMode); });
        out += '</div>';
      });
      out += '</div>';

      if (gord.length > 1) {
        var arrStr = JSON.stringify(gord);
        out += '<div class="md-slider-wrap">';
        out += '<span class="md-slider-val">' + activeHc + '</span>';
        out += '<input type="range" class="md-slider" min="0" max="' + (gord.length - 1) + '" step="1" value="' + activeIdx + '" ' +
               'oninput="window.sbUpdateSlider(this, \'' + uid + '\', ' + arrStr.replace(/"/g, '&quot;') + ')">';
        out += '</div>';
      } else {
        out += '<div class="md-slider-wrap"><span class="md-slider-val">' + activeHc + '</span></div>';
      }
    }
  } else if (isHc1x2) {
    var groups = {}, gord = [];
    sels.forEach(function(s) {
      var hc = s.handicap || '';
      if (!groups[hc]) { groups[hc] = []; gord.push(hc); }
      groups[hc].push(s);
    });
    gord.forEach(function(hc) {
      if (hc) out += '<div class="md-hc-label">' + h(hc) + '</div>';
      out += '<div class="md-mkt-row md-row-3">';
      groups[hc].forEach(function(s) { out += renderMktBtn(s, m, bbMode); });
      out += '</div>';
    });
  } else if (is3x3 && n === 9) {
    for (var i = 0; i < n; i += 3) {
      out += '<div class="md-mkt-row md-row-3">';
      out += renderMktBtn(sels[i], m, bbMode);
      if (sels[i+1]) out += renderMktBtn(sels[i+1], m, bbMode);
      if (sels[i+2]) out += renderMktBtn(sels[i+2], m, bbMode);
      out += '</div>';
    }
  } else if (n <= 3) {
    out += '<div class="md-mkt-row">';
    sels.forEach(function(s) { out += renderMktBtn(s, m, bbMode); });
    out += '</div>';
  } else {
    for (var i = 0; i < n; i += 2) {
      out += '<div class="md-mkt-row">';
      out += renderMktBtn(sels[i], m, bbMode);
      if (sels[i+1]) out += renderMktBtn(sels[i+1], m, bbMode);
      out += '</div>';
    }
  }

  out += '</div></div>';
  return out;
}

window.sbUpdateSlider = function(input, uid, lines) {
  var idx = parseInt(input.value, 10);
  var val = lines[idx];
  input.previousElementSibling.textContent = val;
  var body = document.getElementById('slider-body-' + uid);
  if (body) {
    var groups = body.querySelectorAll('.md-slider-group');
    groups.forEach(function(g) {
      g.style.display = (parseInt(g.getAttribute('data-hc-idx'), 10) === idx) ? '' : 'none';
    });
  }
};

function renderMktBtn(sel, m, bbMode) {
  // Guard against malformed selections from the API (saw "X2 undefined"
  // when a Double Chance entry came through with .odds === undefined).
  if (!sel) return '';
  var rawOdd = parseFloat(sel.odds);
  if (isNaN(rawOdd) || rawOdd < 1.01) rawOdd = 0;
  var val    = applyMargin(rawOdd);
  // Extra hard guard — applyMargin should never return undefined, but
  // if anything ever leaks through we render a stable 0 so .toFixed()
  // can't throw and produce the "undefined" string in the DOM.
  if (typeof val !== 'number' || isNaN(val)) val = 0;
  var matchEnded = isMatchEnded(m);
  var hasOdd = !matchEnded && (val >= 1.01);
  var safeName = (sel.name != null && sel.name !== '' && sel.name !== 'undefined') ? String(sel.name) : '-';
  // Strip any literal "undefined" leak from the name (saw "Plus de undefined"
  // when a market's line value was lost during merge).
  if (safeName.indexOf('undefined') !== -1) safeName = safeName.replace(/\s*undefined\s*/g, '').trim() || '-';
  // Suppress the trailing handicap badge when the selection name already
  // contains the line value (e.g. "Plus de 4.5"). fcbet216 never shows
  // the line twice — we previously rendered "Plus de 2.5 2.5".
  var hcStr = '';
  if (sel.handicap != null) {
    var hcn = parseFloat(sel.handicap);
    if (!isNaN(hcn)) hcStr = String(sel.handicap);
  }
  var nameHasHc = hcStr && safeName.indexOf(hcStr) !== -1;
  var lbl = h(safeName) + (hcStr && !nameHasHc ? ' <span class="md-hc">' + h(hcStr) + '</span>' : '');
  var bid    = h(String(m.id || '')) + '_md_' + h(String(sel.id || safeName));
  // Direction arrow — set by buildOddsDiff() when live odds move.
  var arrowHtml = '';
  var flashCls  = '';
  if (sel._change === 'up') {
    arrowHtml = '<span class="md-o-arrow md-o-arrow--up" aria-hidden="true">▲</span>';
    flashCls  = ' md-odd-flash--up';
  } else if (sel._change === 'down') {
    arrowHtml = '<span class="md-o-arrow md-o-arrow--down" aria-hidden="true">▼</span>';
    flashCls  = ' md-odd-flash--down';
  }
  var isSel  = S.betSlip.some(function(b){ return b.id === bid; });
  var bbBet  = S.betSlip.find(function(b){ return b.id === 'bb_' + String(m.id || ''); });
  var isBB   = bbBet && bbBet.legs && bbBet.legs.some(function(l){ return l.id === bid; });
  // Market name for BB leg display — stored in the button's data-mkt attr if available
  var mktName = (renderMktBtn._curMkt || '');
  if (bbMode) {
    var hcArg = (sel.handicap != null) ? String(sel.handicap) : '';
    return '<button type="button" data-bid="' + bid + '" class="md-odd-btn md-bb-btn' + (isBB ? ' sel' : '') + (hasOdd ? '' : ' md-odd-btn--locked') + flashCls + '"'
      + (hasOdd ? ' onclick="window.sbBBToggle(\'' + bid + '\',\'' + h(String(safeName)) + '\',' + val + ',\'' + h(mktName) + '\',\'' + h(hcArg) + '\')"' : ' disabled')
      + '>'
      + '<span class="md-o-name">' + lbl + '</span>'
      + (hasOdd ? '<span class="md-o-val">' + arrowHtml + val.toFixed(2) + '</span>' : '<span class="md-o-lock">' + ICON.lock + '</span>')
      + '</button>';
  }
  var matchStr = h((m.home ? m.home.name : '') + ' v ' + (m.away ? m.away.name : ''));
  return '<button type="button" data-bid="' + bid + '" class="md-odd-btn' + (isSel ? ' sel' : '') + (hasOdd ? '' : ' md-odd-btn--locked') + flashCls + '"'
    + (hasOdd ? ' onclick="window.sbAddBet(\'' + bid + '\',\'' + matchStr + '\',\'' + h(String(safeName)) + '\',' + val + ',\'' + h(mktName) + '\')"' : ' disabled')
    + '>'
    + '<span class="md-o-name">' + lbl + '</span>'
    + (hasOdd ? '<span class="md-o-val">' + arrowHtml + val.toFixed(2) + '</span>' : '<span class="md-o-lock">' + ICON.lock + '</span>')
    + '</button>';
}



// ── Flash keywords: markets shown in "Flash" quick view (Principaux)
// Must match ALL market names produced by ws_daemon.js buildMarkets()
var MD_FLASH_MARKETS = [
  '1x2','1 x 2','match winner','résultat du match',
  'double chance',
  'over/under','total','les deux équipes','btts','both teams',
  'handicap','handicap asiatique',
  'score exact','correct score','exact score',
  'corner','corners','total des corners',
  'carton','carte','cards','yellow','jaune','cartons plus',
  'pair','impair','odd','even',
  'plage de buts','goal range','multigoal','multigoals',
];
var MD_1MIN_MARKETS  = ['1 minute','1-minute','minute 1','minute bets','prochain but','next goal','1 minute bets'];

// Canonical display order for "Principaux" tab (fcbet216 parity):
// 1x2, Double Chance, Over/Under, Handicap, BTTS, Score exact, Corners, Cards, rest
var PRINCIPAUX_ORDER = [
  '1x2','1 x 2','match winner',
  'double chance',
  'over/under','total des buts','total goals','over','total',
  'handicap asiatique','handicap',
  'les deux équipes','btts','both teams',
  'score exact','correct score',
  'total des corners','corner',
  'carton','cartons','cards','yellow','jaune',
];
function sortMarketsForPrincipaux(mkts) {
  return mkts.slice().sort(function(a, b) {
    var na = (a.name||'').toLowerCase(), nb = (b.name||'').toLowerCase();
    function rank(n) {
      for (var i = 0; i < PRINCIPAUX_ORDER.length; i++) {
        if (n.indexOf(PRINCIPAUX_ORDER[i]) !== -1) return i;
      }
      return PRINCIPAUX_ORDER.length;
    }
    return rank(na) - rank(nb);
  });
}

window._bbModeActive = false;

window.sbTabsScroll = function(dir) {
  var row = document.getElementById('md-tabs-inner');
  if (!row) return;
  // If direction is -1/+1, try to activate the adjacent tab
  var tabs = Array.from(row.querySelectorAll('.md-tab'));
  var activeIdx = tabs.findIndex(function(t){ return t.classList.contains('active'); });
  var next = tabs[activeIdx + dir];
  if (next) {
    next.click();
    next.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
  } else {
    // Just scroll the row
    row.scrollBy({ left: dir * 120, behavior: 'smooth' });
  }
};

// Build the filter function for a given tab name. Centralised so both
// the renderer (sbMdTab) and the tab visibility prune (sbMdPruneEmptyTabs)
// can reuse exactly the same rules.
function getTabFilter(tabName) {
  var isBB = (tabName === 'Bet Builder');
  if (isBB) {
    var BB_COMBINABLE = [
      '1x2', '1 x 2', 'match winner', 'résultat',
      'double chance',
      'total',
      'handicap',
      'les deux équipes', 'btts', 'both teams to score',
      'pair', 'impair', 'odd', 'even',
      'mi-temps', 'half-time', 'first half', '1ère mi-temps', '1ere mi-temps',
      '2ème mi-temps', '2eme mi-temps', 'second half',
      'ht/ft', 'mi-temps/fin', 'half-time/full-time',
      'correct score', 'score exact',
      'corner', 'corners',
      'card', 'cartons', 'cartes',
      'plage de buts', 'goal range',
      'next goal', 'prochain but',
      'player', 'joueur', 'buteur',
      'multigoal', 'multigoals'
    ];
    return function(mkt) {
      var nm = (mkt.name || '').toLowerCase();
      var combinable = BB_COMBINABLE.some(function(k){ return nm.indexOf(k) !== -1; });
      if (!combinable) return false;
      var sels = mkt.selections || [];
      return sels.some(function(s){ var v = parseFloat(s.odds); return v >= 1.01; });
    };
  }
  if (tabName === 'Principaux') {
    return function(mkt) {
      var nm = (mkt.name || '').toLowerCase();
      if (nm.indexOf('2ème mi-temps') !== -1 || nm.indexOf('2eme mi-temps') !== -1) return false;
      if (nm.indexOf('1 minute') !== -1) return false;
      return MD_FLASH_MARKETS.some(function(k){ return nm.indexOf(k) !== -1; });
    };
  }
  if (tabName === '2ème mi-temps' || tabName === '2eme mi-temps') {
    return function(mkt){
      var nm = (mkt.name||'').toLowerCase();
      return nm.indexOf('2ème mi-temps') !== -1 || nm.indexOf('2eme mi-temps') !== -1 || nm.indexOf('second half') !== -1 || nm.indexOf('2h') === 0;
    };
  }
  if (tabName === '1 minute') {
    return function(mkt){
      var nm = (mkt.name||'').toLowerCase();
      return MD_1MIN_MARKETS.some(function(k){ return nm.indexOf(k) !== -1; });
    };
  }
  if (tabName === 'Correct Score') {
    return function(mkt){
      var nm = (mkt.name||'').toLowerCase();
      // Exact-match correct score variants
      return nm.indexOf('correct score') !== -1
          || nm.indexOf('score exact') !== -1
          || nm.indexOf('exact score') !== -1
          || nm.indexOf('score correct') !== -1
          || nm.indexOf('résultat exact') !== -1
          || nm.indexOf('resultat exact') !== -1
          // Bet365 prematch fallback: per-team / total exact goals markets
          || nm.indexOf('exact goals') !== -1
          || nm.indexOf('nombre exact de buts') !== -1
          || nm.indexOf('nombre exact') !== -1
          || nm.indexOf('cs ') === 0
          || nm === 'cs';
    };
  }
  if (tabName === 'Corners') {
    return function(mkt){
      var nm = (mkt.name||'').toLowerCase();
      return nm.indexOf('corner') !== -1
          || nm.indexOf('corners') !== -1
          || nm.indexOf('coup de coin') !== -1
          || nm.indexOf('coups de coin') !== -1;
    };
  }
  if (tabName === 'Multigoals') {
    return function(mkt){
      var nm = (mkt.name||'').toLowerCase();
      return nm.indexOf('multigoal') !== -1 || nm.indexOf('multi-goal') !== -1 || nm.indexOf('multigoals') !== -1;
    };
  }
  if (tabName === 'Cartes' || tabName === 'Cards' || tabName === 'Cartons') {
    return function(mkt){
      var nm = (mkt.name||'').toLowerCase();
      return nm.indexOf('carton') !== -1 || nm.indexOf('carte') !== -1
          || nm.indexOf('cards') !== -1 || nm.indexOf('yellow') !== -1
          || nm.indexOf('jaune') !== -1 || nm.indexOf('red card') !== -1;
    };
  }
  if (tabName === 'Teams H2H' || tabName === 'Teams H2h' || tabName === 'teams h2h') {
    return function(mkt){
      var nm = (mkt.name||'').toLowerCase();
      return nm.indexOf('h2h') !== -1
          || nm.indexOf('head to head') !== -1
          || nm.indexOf('face-à-face') !== -1
          || nm.indexOf('face a face') !== -1
          || nm === '1x2'
          || nm.indexOf('1 x 2') !== -1;
    };
  }
  if (tabName !== 'Tout') {
    var kw = tabName.toLowerCase();
    return function(mkt){ return (mkt.name||'').toLowerCase().indexOf(kw) !== -1; };
  }
  return null;
}

// Hide tabs that produce zero matching markets, like fcbet216. Pre-match
// matches in particular don't expose 1-minute, Corners, Multigoals so we
// suppress their tabs entirely instead of greeting the user with empty
// "Aucun marché" panes.
function sbMdPruneEmptyTabs() {
  if (!window._mdMarkets) return;
  document.querySelectorAll('.md-tab[data-tab]').forEach(function(btn){
    var tn = btn.getAttribute('data-tab');
    if (tn === 'Tout' || tn === 'Principaux' || tn === 'Bet Builder') {
      btn.style.display = '';
      return;
    }
    // Cartes, Corners, Correct Score — only show if data exists (handled below)
    var f = getTabFilter(tn);
    var any = false;
    if (f) {
      for (var i = 0; i < window._mdMarkets.length; i++) {
        if (f(window._mdMarkets[i])) {
          var sels = window._mdMarkets[i].selections || [];
          if (sels.some(function(s){ var v = parseFloat(s.odds); return v >= 1.01; })) {
            any = true;
            break;
          }
        }
      }
    }
    btn.style.display = any ? '' : 'none';
  });
}
window.sbMdPruneEmptyTabs = sbMdPruneEmptyTabs;

window.sbMdTab = function(btn, tabName) {
  document.querySelectorAll('.md-tab').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  var mktBody = document.getElementById('md-markets-body');
  if (!mktBody || !window._mdMarkets || !window._mdMatch) return;

  var isBB = (tabName === 'Bet Builder');
  window._bbModeActive = isBB;
  var cacheKey = tabName + (isBB ? '_bb' : '');
  if (!window._mdTabCache) window._mdTabCache = {};

  // Instant tab switch when we've already built this filter once.
  if (window._mdTabCache[cacheKey]) {
    mktBody.innerHTML = window._mdTabCache[cacheKey];
    var bbStickyC = document.getElementById('md-bb-sticky');
    if (isBB) {
      if (bbStickyC && window._bbSelections && window._bbSelections.length > 0) bbStickyC.style.display = '';
      mktBody.style.paddingBottom = '120px';
    } else {
      mktBody.style.paddingBottom = '';
    }
    return;
  }

  // Re-render markets — filter is built by the shared helper above
  var filter = getTabFilter(tabName);
  var shown = filter ? window._mdMarkets.filter(filter) : window._mdMarkets;
  // Principaux: enforce canonical order (1X2 → DC → Over → Handicap → BTTS → CS → Corners → Cards)
  if (tabName === 'Principaux') shown = sortMarketsForPrincipaux(shown);
  if (!shown.length && tabName !== 'Tout') {
    // More helpful empty state: distinguish "pre-match" (will appear at kickoff)
    // from "live but missing" (Bet365 didn't expose this market for this game).
    var msg;
    var mdMatch = window._mdMatch;
    var isLiveMatch = mdMatch && (typeof isMatchLive === 'function') && isMatchLive(mdMatch);
    var liveOnlyTabs = ['1 minute', '1 minutes', 'minute', 'corners', 'corner'];
    var lowerTab = tabName.toLowerCase();
    if (!isLiveMatch && liveOnlyTabs.indexOf(lowerTab) !== -1) {
      msg = '<b>' + h(tabName) + '</b> sera disponible après le coup d\'envoi.';
    } else {
      msg = 'Aucun marché disponible pour <b>' + h(tabName) + '</b> pour le moment.';
    }
    var emptyHtml = '<div class="md-empty-tab">' + msg + '</div>';
    window._mdTabCache[cacheKey] = emptyHtml;
    mktBody.innerHTML = emptyHtml;
    var bbSticky0 = document.getElementById('md-bb-sticky');
    if (bbSticky0) bbSticky0.style.display = 'none';
    mktBody.style.paddingBottom = '';
    return;
  }
  if (!shown.length) shown = window._mdMarkets;

  // Paint on next frame so the active tab highlight lands first (feels instant).
  mktBody.classList.add('md-markets--loading');
  requestAnimationFrame(function() {
  var out = '';
  shown.forEach(function(mkt, i) { out += renderMarketGroup(mkt, window._mdMatch, i < 6, isBB); });
    window._mdTabCache[cacheKey] = out;
  mktBody.innerHTML = out;
    mktBody.classList.remove('md-markets--loading');

  var bbSticky = document.getElementById('md-bb-sticky');
  if (isBB) {
    if (bbSticky && window._bbSelections && window._bbSelections.length > 0) bbSticky.style.display = '';
    mktBody.style.paddingBottom = '120px';
  } else {
    mktBody.style.paddingBottom = '';
  }
  });
};

window.sbMdQuickFilter = function(btn, filter) {
  document.querySelectorAll('.md-qt').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  var mktBody = document.getElementById('md-markets-body');
  if (!mktBody || !window._mdMarkets || !window._mdMatch) return;
  var shown = window._mdMarkets;
  if (filter === 'flash') {
    shown = window._mdMarkets.filter(function(mkt){
      var nm = (mkt.name || '').toLowerCase();
      return MD_FLASH_MARKETS.some(function(k){ return nm.indexOf(k) !== -1; });
    });
    if (!shown.length) shown = window._mdMarkets.slice(0, 5);
  } else if (filter === '1min') {
    shown = window._mdMarkets.filter(function(mkt){
      var nm = (mkt.name || '').toLowerCase();
      return MD_1MIN_MARKETS.some(function(k){ return nm.indexOf(k) !== -1; });
    });
    if (!shown.length) shown = window._mdMarkets.slice(0, 8);
  }
  var out = '';
  shown.forEach(function(mkt, i) { out += renderMarketGroup(mkt, window._mdMatch, true, false); });
  mktBody.innerHTML = out;
};

// ── Inline market search (fcbet216 parity) ────────────────────
// Click the search icon → tabs row hides, an input shows ("Search market").
// Typing filters markets by name. Click the X → restores tabs row.
window.sbMdSearchToggle = function(open) {
  // Use a class on the wrapper instead of inline display:none, because
  // the critical inline CSS forces `.md-tabs-row { display: flex !important }`
  // and that beats JS-set inline style. With the class on the wrapper
  // we win specificity (.md-tabs-wrap.md-tabs-wrap--search .md-tabs-row).
  var wrap   = document.getElementById('md-tabs-wrap');
  var input  = document.getElementById('md-search-input');
  if (!wrap) return;
  if (open) {
    wrap.classList.add('md-tabs-wrap--search');
    setTimeout(function(){ if (input) input.focus(); }, 0);
  } else {
    wrap.classList.remove('md-tabs-wrap--search');
    if (input) input.value = '';
    var activeTab = document.querySelector('.md-tab.active');
    if (activeTab) {
      var nm = activeTab.getAttribute('data-tab') || activeTab.textContent.trim();
      window.sbMdTab(activeTab, nm);
    }
  }
};

window.sbMdSearch = function(query) {
    var mktBody = document.getElementById('md-markets-body');
  if (!mktBody || !window._mdMarkets || !window._mdMatch) return;
  var q = String(query || '').toLowerCase().trim();
  var shown;
  if (!q) {
    shown = window._mdMarkets;
  } else {
    shown = window._mdMarkets.filter(function(mkt){
      var nm  = (mkt.name || '').toLowerCase();
      if (nm.indexOf(q) !== -1) return true;
      // Also match by selection name (e.g. typing "BTTS" finds 'Oui'/'Non' inside).
      if (mkt.selections && mkt.selections.some(function(sel){
        return String(sel.name || '').toLowerCase().indexOf(q) !== -1;
      })) return true;
      return false;
    });
  }
  if (!shown.length) {
    mktBody.innerHTML = '<div class="md-empty-tab">Aucun marché ne correspond à <b>' + h(q) + '</b>.</div>';
    return;
  }
  var out = '';
  shown.forEach(function(mkt, i){ out += renderMarketGroup(mkt, window._mdMatch, i < 6, false); });
  mktBody.innerHTML = out;
};

window.sbMdToggleInfo = function() {
  var existing = document.getElementById('md-tab-info-pop');
  if (existing) { existing.remove(); return; }
  var btn = document.getElementById('md-tab-info-btn');
  if (!btn) return;
  var pop = document.createElement('div');
  pop.id = 'md-tab-info-pop';
  pop.className = 'md-tab-info-pop';
  pop.innerHTML = '<div class="md-tab-info-title">Légende des marchés</div>'
    + '<div class="md-tab-info-row"><span class="md-tab-info-icon md-tab-info-icon--bb">BB</span> Bet Builder éligible</div>'
    + '<div class="md-tab-info-row"><span class="md-tab-info-icon md-tab-info-icon--lock">' + ICON.lock + '</span> Marché temporairement suspendu</div>'
    + '<div class="md-tab-info-row"><span class="md-tab-info-icon md-tab-info-icon--star">★</span> Ajouter aux favoris</div>';
  document.body.appendChild(pop);
  var r = btn.getBoundingClientRect();
  pop.style.top  = (r.bottom + 6) + 'px';
  pop.style.left = Math.max(8, r.right - 240) + 'px';
  // Close on outside click
  setTimeout(function(){
    document.addEventListener('click', function _cl(e){
      if (!pop.contains(e.target) && e.target !== btn) {
        pop.remove();
        document.removeEventListener('click', _cl);
      }
    });
  }, 0);
};

// ── Bet Builder ──────────────────────────────────────────────
// BB tab clicks delegate to the unified sbAddBet so the same
// auto-grouping rules (per-match SGM, one leg per market group)
// apply regardless of which tab the user is on.
window.sbBBToggle = function(id, name, odds, market, handicap) {
  var match = window._mdMatch;
  if (!match) return;
  var matchName = (match.home ? match.home.name : '') + ' vs. ' + (match.away ? match.away.name : '');
  return window.sbAddBet(id, matchName, name, odds, market);
};

// Removed duplicate sbSwitchTab

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

/* ════════════════════════════════════════════════════════════════════════
   PERIOD PAGE — fcbet216 reference (URL: /sportsbook/prelive?page=period&date=...)
   Tapping a future date pill opens this view: all leagues with that date's
   fixtures, every accordion CLOSED by default, user can expand any.
   Hides Favoris / sport tiles / SLC carousel / Cotes boostees / search /
   leagues panel — see CSS [data-view="periodpage"].
   ════════════════════════════════════════════════════════════════════════ */
window.sbOpenPeriodPage = function(dayOffset, _skipPush) {
  sbAbortListFetches();
  sbAbortMdFetches();
  var navGen = sbNextNav();

  S.viewMode         = 'periodPage';
  S.activeDateOffset = dayOffset;
  S.activeAction     = 'upcoming';
  S.activeTab        = 'home';
  S.activeLeagueId   = null;
  S.userPickedSport  = false;
  if (!S.periodLeagueExpanded) S.periodLeagueExpanded = {};

  // Update URL with fcbet-like format ?page=period&date=ISO
  try {
    var dt = new Date();
    dt.setDate(dt.getDate() + dayOffset);
    dt.setHours(23, 0, 0, 0);
    var dateStr = dt.toISOString();
    if (!_skipPush) {
      var u = new URL(window.location.href);
      u.searchParams.set('page', 'period');
      u.searchParams.set('date', dateStr);
      history.pushState({ sbView:'period', date:dateStr, dayOffset:dayOffset }, '', u.toString());
    }
  } catch (e) {}

  var root = document.querySelector('.sb-root');
  if (root) root.setAttribute('data-view', 'periodpage');

  var el = document.getElementById('sb-matches-body');
  if (el) el.innerHTML = buildSkeleton(5);

  // Stop home polling while on period page (future-date fixtures won't go live)
  if (window._sbPollTid) { clearInterval(window._sbPollTid); window._sbPollTid = null; }

  var apiUrl = BASE + 'sportsbook/api.php?action=upcoming&sport_id=' + (S.activeSportId || 1);
  fetch(apiUrl)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!sbNavAlive(navGen)) return;
      if (S.viewMode !== 'periodPage') return;
      var res = (d && d.results) ? d.results : [];
      var now = new Date();
      var target = new Date(now.getFullYear(), now.getMonth(), now.getDate() + dayOffset);
      var targetStr = target.toDateString();
      res = res.filter(function(m) {
        if (!m || !m.id || !m.home || !m.home.name || !m.away || !m.away.name) return false;
        if (m.time_status === '3') return false;
        var ts = parseInt(m.time || m.start_time || 0) || 0;
        if (!ts) return false;
        return new Date(ts * 1000).toDateString() === targetStr;
      });
      S.periodMatches = res;
      renderPeriodPage(dayOffset, res);
    })
    .catch(function() {
      if (S.viewMode !== 'periodPage') return;
      S.periodMatches = [];
      renderPeriodPage(dayOffset, []);
    });
};

function renderPeriodPage(dayOffset, matches) {
  var el = document.getElementById('sb-matches-body');
  if (!el) return;
  if (S.viewMode !== 'periodPage') return;

  matches = sortUpcomingMatches(matches || []);

  var groupMode = S.periodGroupMode || 'league';
  var activeCat = S.activeMarketCat || '1x2';
  var marketOpts = [
    {key:'1x2',           label:'1x2'},
    {key:'total',         label:'Total'},
    {key:'double_chance', label:'Double chance'},
    {key:'btts',          label:'Les deux équipes qui marquent'},
    {key:'handicap',      label:'Handicap'},
    {key:'ht_1x2',        label:'1ère mi-temps - 1x2'},
    {key:'ht_total',      label:'1ère mi-temps - total'}
  ];
  var activeOpt = marketOpts.find(function(o){ return o.key === activeCat; }) || marketOpts[0];
  var accOpen = !!S.periodMktAccOpen;

  var out = '<div class="sb-period-view sb-champ-view">';

  // Sport pills (Football / Basketball / Tennis / ...) — switches the
  // active sport and reloads the period for the current dayOffset.
  out += '<div class="sb-upcoming-tabs sb-period-sport-tabs">';
  SPORTS.filter(function(sp){ return sp.live !== false; }).slice(0, 8).forEach(function(sp) {
    var isActive = (sp.id === S.activeSportId);
    out += '<button type="button" class="sb-upcoming-tab' + (isActive?' active':'') + '" onclick="window.sbPeriodSwitchSport(' + sp.id + ')">';
    out += '<div class="sb-tab-icon">' + sp.icon + '</div>';
    out += '<span class="sb-tab-name">' + h(sp.name) + '</span>';
    out += '</button>';
  });
  out += '</div>';

  // Cotes de match / Cotes boostees top tabs
  out += '<div class="sb-champ-top-tabs">';
  out += '<button type="button" class="sb-ctt active">Cotes de match</button>';
  out += '<button type="button" class="sb-ctt">Cotes boostées</button>';
  out += '</div>';

  // Par Ligue / Par Heure sub-nav
  out += '<div class="sb-champ-subnav">';
  out += '<button type="button" class="sb-subnav-btn' + (groupMode==='league'?' active':'') + '" onclick="window.sbPeriodGroupMode(\'league\')">Par Ligue</button>';
  out += '<button type="button" class="sb-subnav-btn' + (groupMode==='hour'?' active':'') + '" onclick="window.sbPeriodGroupMode(\'hour\')">Par Heure</button>';
  out += '</div>';

  // Market type tabs (horizontal scroll)
  var pMktTypeTabs = ['Tout','Principaux','Spéciale joueurs','1 minute','Mi-temps 1','Mi-temps 2','Teams H2H','Correct Score','Corners','Cartes','Combo'];
  S.periodMktTab = (typeof S.periodMktTab !== 'undefined') ? S.periodMktTab : 1;
  out += '<div class="sb-champ-mkt-tabs">';
  pMktTypeTabs.forEach(function(t, i) {
    out += '<button class="sb-cmt' + (S.periodMktTab === i ? ' active' : '') + '" onclick="window.sbPeriodMktTab(' + i + ', \'' + t.replace(/'/g, "\\'") + '\')">' + t + '</button>';
  });
  out += '</div>';

  // Market category shortcut grid — fcbet216 image-2 reference:
  //   Populaire | 1x2
  //   Total     | Double chance
  //   Les deux équipes qui marquent | Voir tous les marchés
  // Two cells per row, green = active. Tapping a cell sets the
  // market category (same data as the dropdown below).
  var activeShortcut = S.periodShortcut || 'popular';
  var shortcuts = [
    {k:'popular',       label:'Populaire'},
    {k:'1x2',           label:'1x2'},
    {k:'total',         label:'Total'},
    {k:'double_chance', label:'Double chance'},
    {k:'btts',          label:'Les deux équipes qui marquent'},
    {k:'all_markets',   label:'Voir tous les marchés'}
  ];
  out += '<div class="sb-period-shortcuts">';
  shortcuts.forEach(function(s) {
    var isAct = s.k === activeShortcut;
    var isCta = s.k === 'all_markets';
    out += '<button type="button" class="sb-period-shortcut'
      + (isCta ? ' sb-period-shortcut--cta' : '')
      + (isAct ? ' active' : '')
      + '" onclick="window.sbPeriodSetShortcut(\'' + s.k + '\')">'
      + h(s.label) + '</button>';
  });
  out += '</div>';

  // Market category dropdown (1x2 default)
  out += '<div class="sb-champ-mkt-acc' + (accOpen?' open':' collapsed') + '" id="sb-period-mkt-acc">';
  out += '<button type="button" class="sb-champ-mkt-acc-hdr" onclick="window.sbTogglePeriodMktAcc()">';
  out += '<span class="sb-champ-mkt-acc-lbl">' + h(activeOpt.label) + '</span>';
  out += '<span class="sb-champ-mkt-acc-tgl" aria-hidden="true">' + (accOpen?'&minus;':'&#9662;') + '</span>';
  out += '</button>';
  out += '<div class="sb-champ-mkt-acc-body"' + (accOpen?'':' style="display:none"') + '>';
  // EXACT fcbet216 .hMbNfr spec — bg rgb(74,74,74), grid 1fr 20px, padding 12px 10px.
  var optStyle = 'display:grid !important;grid-template-columns:1fr 20px !important;place-items:center !important;width:100% !important;padding:12px 10px !important;margin:0 !important;background:rgb(74,74,74) !important;border:0 !important;border-radius:4px !important;color:rgb(255,255,255) !important;font-family:Roboto,sans-serif !important;font-size:14px !important;font-weight:500 !important;line-height:16px !important;cursor:pointer !important;outline:none !important;box-shadow:rgba(13,13,13,0) 0 0 0 0, rgba(13,13,13,0) 0 0 0 0 inset !important;-webkit-appearance:none !important;-moz-appearance:none !important;appearance:none !important;';
  marketOpts.forEach(function(o) {
    if (o.key === activeOpt.key) return;
    out += '<button type="button" class="sb-champ-mkt-opt" style="' + optStyle + '" onclick="window.sbSetPeriodMarketCat(\'' + o.key + '\')">' + h(o.label) + '</button>';
  });
  out += '</div></div>';

  // League / hour accordions — ALL CLOSED BY DEFAULT
  out += '<div class="sb-period-matches sb-champ-matches">';
  if (!matches.length) {
    out += '<div class="sb-loader" style="margin-top:16px">Aucun match disponible pour cette date.</div>';
  } else {
    var groups = {}, order = [];
    var byHour = (groupMode === 'hour');
    matches.forEach(function(m) {
      var k;
      if (byHour) {
        var ts = parseInt(m.time||m.start_time||0)||0;
        if (ts) {
          var dx = new Date(ts*1000);
          var dn = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
          k = dn[dx.getDay()] + ' ' + String(dx.getDate()).padStart(2,'0') + '/' + String(dx.getMonth()+1).padStart(2,'0') + ' · ' + String(dx.getHours()).padStart(2,'0') + 'h' + String(dx.getMinutes()).padStart(2,'0');
        } else { k = 'À venir'; }
      } else {
        k = (m.league && m.league.name) ? m.league.name : 'Autre championnat';
      }
      if (!groups[k]) { groups[k]=[]; order.push(k); }
      groups[k].push(m);
    });
    S.periodLeagueExpanded = S.periodLeagueExpanded || {};
    // Default: open the first 3 leagues, collapse the rest (fcbet216 UX).
    var defaultOpenN = 3;
    order.forEach(function(lg, lgIdx) {
      var lc = guessCountry(lg);
      var lf = getFlag(lc);
      var lcl = (lc && lc !== 'International') ? (' · ' + h(lc)) : '';
      // Honor user clicks first; otherwise auto-open the first N.
      var hasUserState = Object.prototype.hasOwnProperty.call(S.periodLeagueExpanded, lg);
      var isExpanded   = hasUserState ? !!S.periodLeagueExpanded[lg] : (lgIdx < defaultOpenN);
      var isCollapsed  = !isExpanded;
      var lgKey = encodeURIComponent(lg);
      out += '<div class="sb-league-acc' + (isCollapsed?' collapsed':' open') + '" data-lg-period="' + h(lg) + '">';
      out += '<button type="button" class="sb-league-acc-hdr" onclick="window.sbTogglePeriodLeague(\'' + lgKey + '\')">';
      out += '<span class="sb-lh-star" onclick="event.stopPropagation()">' + ICON.star + '</span>';
      out += '<img src="' + lf + '" class="sb-league-f" onerror="this.src=\'https://flagcdn.com/w20/un.png\'">';
      out += '<span class="sb-league-n">' + h(stripCountryPrefix(lg)||lg) + lcl + '</span>';
      out += '<span class="sb-lh-bb">BB</span>';
      out += '<span class="sb-league-acc-tgl" aria-hidden="true">' + (isCollapsed?'&#9662;':'&minus;') + '</span>';
      out += '</button>';
      out += '<div class="sb-league-acc-body"' + (isCollapsed?' style="display:none"':'') + '>';
      groups[lg].forEach(function(m) { out += matchCard(m); });
      out += '</div>';
      out += '</div>';
    });
  }
  out += '</div>'; // sb-period-matches
  out += '</div>'; // sb-period-view
  el.innerHTML = out;
  if (typeof window.sbRestoreExpandedCards === 'function') {
    window.sbRestoreExpandedCards();
  }
}

window.sbTogglePeriodLeague = function(lgKey) {
  var lg = decodeURIComponent(lgKey);
  S.periodLeagueExpanded = S.periodLeagueExpanded || {};
  var nextExpanded = !S.periodLeagueExpanded[lg];
  S.periodLeagueExpanded[lg] = nextExpanded;
  var sel = '.sb-league-acc[data-lg-period="' + lg.replace(/"/g,'\\"') + '"]';
  var node = document.querySelector(sel);
  if (!node) return;
  node.classList.toggle('collapsed', !nextExpanded);
  node.classList.toggle('open', nextExpanded);
  var body = node.querySelector('.sb-league-acc-body');
  var tgl  = node.querySelector('.sb-league-acc-tgl');
  if (body) body.style.display = nextExpanded ? '' : 'none';
  if (tgl)  tgl.innerHTML = nextExpanded ? '&minus;' : '&#9662;';
};

window.sbPeriodGroupMode = function(mode) {
  S.periodGroupMode = (mode === 'hour') ? 'hour' : 'league';
  S.periodLeagueExpanded = {}; // reset accordion state on regroup
  renderPeriodPage(S.activeDateOffset, S.periodMatches || []);
};

window.sbTogglePeriodMktAcc = function() {
  var acc = document.getElementById('sb-period-mkt-acc');
  if (!acc) return;
  var open = acc.classList.contains('open');
  acc.classList.toggle('open', !open);
  acc.classList.toggle('collapsed', open);
  var body = acc.querySelector('.sb-champ-mkt-acc-body');
  var tgl  = acc.querySelector('.sb-champ-mkt-acc-tgl');
  if (body) body.style.display = open ? 'none' : '';
  if (tgl)  tgl.innerHTML = open ? '&#9662;' : '&minus;';
  S.periodMktAccOpen = !open;
};

window.sbSetPeriodMarketCat = function(cat) {
  S.activeMarketCat = cat;
  S.periodMktAccOpen = false;
  renderPeriodPage(S.activeDateOffset, S.periodMatches || []);
};

/* Switch sport on the period page — reloads fixtures for the same date. */
window.sbPeriodSwitchSport = function(sportId) {
  S.activeSportId = sportId || 1;
  S.periodLeagueExpanded = {};   // reset accordion state on sport change
  window.sbOpenPeriodPage(S.activeDateOffset, true /* don't push duplicate URL */);
};

/* Shortcut grid (Populaire / 1x2 / Total / ... ) sets the market category
   then re-renders. fcbet216 .HeaderMarketsButtonsContainer behaviour. */
window.sbPeriodSetShortcut = function(key) {
  S.periodShortcut = key;
  if (key && key !== 'popular' && key !== 'all_markets') {
    S.activeMarketCat = key;
  }
  renderPeriodPage(S.activeDateOffset, S.periodMatches || []);
};

window.sbPeriodMktTab = function(index, label) {
  S.periodMktTab = index;
  var l = (label || '').toLowerCase();
  if (l.indexOf('mi-temps') !== -1) S.activeMarketCat = 'ht_1x2';
  else if (l.indexOf('principaux') !== -1 || l.indexOf('tout') !== -1) S.activeMarketCat = '1x2';
  else if (l.indexOf('total') !== -1 || l.indexOf('buts') !== -1 || l.indexOf('multigoals') !== -1) S.activeMarketCat = 'total';
  
  renderPeriodPage(S.activeDateOffset, S.periodMatches || []);
};

function loadAndFilter(action, sid, lid) {
  // Never clobber a dedicated sub-view with a list skeleton.
  if (S.viewMode === 'matchDetail' || S.viewMode === 'sportPage' || S.viewMode === 'periodPage') return;

  sbAbortListFetches();
  var navGen = sbNextNav();
  var el = document.getElementById('sb-matches-body');
  if (el) el.innerHTML = buildSkeleton(5);

  // Use `upcoming` action for future dates, `inplay` for today
  var apiAction = (S.activeDateOffset > 0) ? 'upcoming' : 'inplay';
  var url = BASE + 'sportsbook/api.php?action=' + apiAction + '&sport_id=' + (sid || 1);
  var fetchOpts = {};
  if (S._listAbort) fetchOpts.signal = S._listAbort.signal;

  fetch(url, fetchOpts)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!sbNavAlive(navGen)) return;
      if (S.viewMode === 'matchDetail' || S.viewMode === 'sportPage') return;
      var res = (d && d.results) ? d.results : [];

      // Filter out invalid and ended matches
      res = res.filter(function(m) {
        if (S.activeTab === 'live' && !isMatchLive(m)) return false;
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

      res = res.map(normalizeMatch);
      res.forEach(function(m) { m._o = null; });
      S.matches = res;
      if (!sbNavAlive(navGen)) return;
      if (S.viewMode === 'matchDetail' || S.viewMode === 'sportPage') return;
      renderMatches(S.matches);
      markLiveSidebarLeagues(res);
    })
    .catch(function(err) {
      if (err && err.name === 'AbortError') return;
      if (!sbNavAlive(navGen)) return;
      if (S.viewMode === 'matchDetail' || S.viewMode === 'sportPage') return;
      renderMatches([]);
    });
}

/* ── Multi-sport live league names for top-league EN DIRECT badges ── */
function refreshLiveTopLeagues() {
  fetch(BASE + 'sportsbook/api.php?action=top_leagues_live')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d) return;
      // Prefer {name, sport_id} pairs so we never badge the wrong sport
      // (e.g. Handball Bundesliga lighting up Football Bundesliga).
      if (Array.isArray(d.live_pairs)) {
        S.allLiveLeaguePairs = d.live_pairs;
      } else if (Array.isArray(d.live_leagues)) {
        S.allLiveLeaguePairs = d.live_leagues.map(function(n){ return { name: n, sport_id: 1 }; });
      }
      S.allLiveLeagueNames = (S.allLiveLeaguePairs || []).map(function(p){ return p.name; });
      markLiveSidebarLeagues(S.matches);
    })
    .catch(function() {});
}

/* Refresh EN DIRECT badges on sidebar leagues every 15s independently
   of which sport the user is browsing, so Tennis/Basketball/etc. rows
   get their badge even when the home view shows football. */
(function _startLiveLeaguePoll() {
  // First run happens at boot (refreshLiveTopLeagues() called below).
  // Subsequent runs every 15s — cheap PHP endpoint reads local cache files.
  setInterval(refreshLiveTopLeagues, 15000);
})();

/* ── Mark sidebar top-league items with EN DIRECT badge when live matches exist ── */
function markLiveSidebarLeagues(matches) {
  // Use {name, sport_id} pairs so we badge ONLY the correct sport's row.
  // (Previously a handball "1. Bundesliga" would badge the football row.)
  var livePairs = (S.allLiveLeaguePairs || []).slice();
  (matches || []).forEach(function(m) {
    if (isMatchLive(m) && m.league && m.league.name) {
      livePairs.push({ name: m.league.name, sport_id: parseInt(m.sport_id || 1, 10) });
    }
  });

  document.querySelectorAll('.sb-tl-item').forEach(function(el) {
    var nameEl = el.querySelector('.sb-league-name');
    if (!nameEl) return;
    var displayName = (el.getAttribute('data-league-label') || nameEl.textContent || '').trim();
    // Each sidebar item carries its expected sport via the onclick (..., sport)
    // OR a data-sport attribute. Fall back to 1 (football) when missing.
    var itemSport = parseInt(el.getAttribute('data-sport') || '1', 10);
    // Best-effort sport extraction from onclick handler text
    var oc = el.getAttribute('onclick') || '';
    var mSport = oc.match(/sbOpenLeague\([^)]*?,\s*(\d+)\)/);
    if (mSport) itemSport = parseInt(mSport[1], 10) || itemSport;

    var isLive = livePairs.some(function(p) {
      if (parseInt(p.sport_id || 1, 10) !== itemSport) return false;
      return isLeagueMatch(displayName, p.name);
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

/* ── Hide/show the mobile leagues panel + search bar ───────────
   On fcbet216, entering a championship or match detail page hides
   the left leagues panel completely so the full viewport is used.
─────────────────────────────────────────────────────────────── */
function sbSetHomepanelVisible(show) {
  // Must use setProperty with 'important' priority to override
  // the media-query rule that sets display:flex !important
  var selectors = [
    '.sb-mob-inline-leagues',
    '.sb-mob-leagues-panel',
    '.sb-sport-nav',
    '#sb-en-direct-cards',   // carousel
    '#sb-boost-section'      // cotes boostées
  ];
  selectors.forEach(function(sel) {
    var el = document.querySelector(sel);
    if (!el) return;
    if (show) {
      el.style.removeProperty('display');
    } else {
      el.style.setProperty('display', 'none', 'important');
    }
  });
}

/* ── URL-based routing ────────────────────────────────────────
   Mirrors fcbet216 URL scheme:
   - Main:         /sportsbook/
   - Championship: /sportsbook/?page=championship&championshipIds=ID&sportId=SID&name=NAME
   - Match detail: /sportsbook/?page=liveEvent&eventId=ID&sportId=SID
   This means refreshing the page keeps you on the correct view.
────────────────────────────────────────────────────────────── */
function sbPushUrl(pageType, params) {
  var base = window.location.pathname;
  if (!pageType || pageType === 'main') {
    history.pushState({page: 'main'}, '', base);
    return;
  }
  var qs = '?page=' + encodeURIComponent(pageType);
  Object.keys(params || {}).forEach(function(k) {
    if (params[k] != null && params[k] !== '') {
      qs += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
    }
  });
  var state = {page: pageType};
  Object.keys(params || {}).forEach(function(k) { state[k] = params[k]; });
  history.pushState(state, '', base + qs);
}

function sbRestoreFromUrl() {
  var sp = new URLSearchParams(window.location.search);
  var page = sp.get('page');
  if (page === 'championship') {
    var id   = sp.get('championshipIds') || null;
    var name = sp.get('name') ? decodeURIComponent(sp.get('name')) : '';
    var sid  = parseInt(sp.get('sportId') || '1') || 1;
    if (id || name) {
      var flag = getFlag(guessCountry(name));
      window.sbOpenLeague(id, name, flag, sid, true /* skipPush */);
      return true;
    }
  } else if (page === 'liveEvent') {
    var eventId  = sp.get('eventId');
    var sportId2 = parseInt(sp.get('sportId') || '1') || 1;
    if (eventId) {
      S.activeSportId = sportId2;
      window.sbOpenMatch(eventId, true /* skipPush */);
      return true;
    }
  } else if (page === 'sport') {
    // /sportsbook?page=sport&sportId=N — restore the sport-detail view
    var sportId3 = parseInt(sp.get('sportId') || '0') || 0;
    if (sportId3) {
      window.sbOpenSportPage(sportId3);
      return true;
    }
  } else if (page === 'period') {
    // /sportsbook?page=period&date=ISO — restore the prelive period page
    var iso = sp.get('date');
    if (iso) {
      try {
        var d   = new Date(iso);
        var now = new Date();
        var ms  = d.getTime() - new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
        var dOff = Math.max(0, Math.round(ms / 86400000));
        // Activate the matching date pill if present
        var items = document.querySelectorAll('.sb-date-item');
        if (items[dOff]) {
          items.forEach(function(b){ b.classList.remove('active'); });
          items[dOff].classList.add('active');
        }
        window.sbOpenPeriodPage(dOff, true /* skipPush */);
        return true;
      } catch (e) {}
    }
  }
  return false;
}

window.addEventListener('popstate', function(e) {
  var st = e.state || {};
  if (st.page === 'championship') {
    var _f = st.flag || getFlag(guessCountry(st.name || ''));
    window.sbOpenLeague(st.championshipIds || null, st.name || '', _f, parseInt(st.sportId||1)||1, true);
  } else if (st.page === 'liveEvent') {
    if (st.sportId) S.activeSportId = parseInt(st.sportId) || 1;
    window.sbOpenMatch(st.eventId || '', true);
  } else if (st.page === 'sport') {
    var sid = parseInt(st.sportId || 0) || 0;
    if (sid) window.sbOpenSportPage(sid);
  } else if (st.sbView === 'period') {
    // Restore period page from back/forward navigation
    var dOff = parseInt(st.dayOffset || 0, 10) || 0;
    window.sbOpenPeriodPage(dOff, true /* skipPush */);
  } else {
    // main — restore without pushing another entry
    S.activeLeagueId = null; S.activeLeagueName = null; S.activeLeagueFlag = null;
    S.activeMatchId  = null; S.viewMode = 'main'; S.champMatches = [];
    S.userPickedSport = false;
    var rootPop = document.querySelector('.sb-root');
    if (rootPop) rootPop.removeAttribute('data-view');
    clearInterval(window._mdTimerInterval);
    var viewer = document.getElementById('sb-match-viewer');
    if (viewer) viewer.style.display = 'none';
    sbSetHomepanelVisible(true);
    loadAndFilter(S.activeAction || 'inplay', S.activeSportId || 1, null);
  }
});

/* ── Sport nav scroll arrows — show/hide based on scroll position ── */
window.sbScrollSportNav = function(dir) {
  var inner = document.getElementById('sb-sport-nav-inner');
  if (!inner) return;
  inner.scrollBy({ left: dir * Math.max(120, inner.clientWidth * 0.6), behavior: 'smooth' });
};
function _sbUpdateNavArrows() {
  var inner = document.getElementById('sb-sport-nav-inner');
  var l = document.getElementById('sb-nav-arrow-left');
  var r = document.getElementById('sb-nav-arrow-right');
  if (!inner || !l || !r) return;
  var atStart = inner.scrollLeft <= 2;
  var atEnd   = (inner.scrollLeft + inner.clientWidth) >= (inner.scrollWidth - 2);
  l.classList.toggle('is-hidden', atStart);
  r.classList.toggle('is-hidden', atEnd);
}
(function _sbInitNavArrows() {
  var inner = document.getElementById('sb-sport-nav-inner');
  if (!inner) return;
  inner.addEventListener('scroll', _sbUpdateNavArrows, { passive: true });
  window.addEventListener('resize', _sbUpdateNavArrows);
  setTimeout(_sbUpdateNavArrows, 80);
  setTimeout(_sbUpdateNavArrows, 400);
  setTimeout(_sbUpdateNavArrows, 1500); // after sport tiles render
})();

/* ── Boot splash control — hide the loading ring once we've painted ── */
function sbHideBootSplash() {
  var el = document.getElementById('sb-boot-splash');
  if (!el || el.classList.contains('hide')) return;
  el.classList.add('hide');
  setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 350);
}
// Safety net: never let the splash stick around longer than 4s
setTimeout(sbHideBootSplash, 4000);
// Hide as soon as the first real render lands in the DOM
window.addEventListener('load', function() { setTimeout(sbHideBootSplash, 150); });
document.addEventListener('DOMContentLoaded', function() { setTimeout(sbHideBootSplash, 800); });

/* ── Init ─────────────────────────────────────────────────── */
loadCounts();
refreshLiveTopLeagues();
startPolling();
renderSidebar();
renderBetSlip();
renderPopularBets();
// Restore from URL params on load, else start fresh
if (!sbRestoreFromUrl()) {
  loadAndFilter('inplay', 1, null);
}
// Hide splash now that the page chrome is rendered (data fills in async)
sbHideBootSplash();

/* ══════════════════════════════════════════════════════════
   MES PARIS (My Bets) — fcbet216 image 3 reference.
   Replaces the home stream with a list of the logged-in user's
   bets, scoped by status (Ouvrir/Calculé/Gagné/Perdu/Retirer)
   and date range. Print actions open bet_print.php.
   ══════════════════════════════════════════════════════════ */
window._mbState = window._mbState || { status: 'open', q: '', from: '', to: '', bets: [] };

function _mbFmtAmount(n) {
  var v = (parseFloat(n) || 0).toFixed(2);
  return 'TND ' + v;
}
function _mbFmtDateShort(iso) {
  if (!iso) return '';
  var d = new Date(iso.replace(' ', 'T'));
  if (isNaN(d.getTime())) return iso;
  var p = function(x){ return x < 10 ? '0' + x : ('' + x); };
  return p(d.getDate()) + '/' + p(d.getMonth() + 1) + ' • ' + p(d.getHours()) + ':' + p(d.getMinutes());
}
function _mbDefaultFrom() {
  var d = new Date(); d.setDate(d.getDate() - 10);
  return d.toISOString().slice(0, 10);
}
function _mbDefaultTo() {
  return new Date().toISOString().slice(0, 10);
}
function _mbPillForStatus(s) {
  if (s === 'pending')  return '<span class="sb-bet-card-pill is-open">OUVRIR</span>';
  if (s === 'won')      return '<span class="sb-bet-card-pill is-won">GAGNÉ</span>';
  if (s === 'lost')     return '<span class="sb-bet-card-pill is-lost">PERDU</span>';
  if (s === 'refunded') return '<span class="sb-bet-card-pill is-ref">RETIRÉ</span>';
  return '<span class="sb-bet-card-pill">' + String(s || '').toUpperCase() + '</span>';
}
function _mbRenderLegInline(leg) {
  // Slip schema (from app.js): match (string), sel, market, isBB, legs[]{name,market,odds}
  var teams = (leg.match || '').trim();
  if (!teams) teams = (leg.home || '') + (leg.away ? ' vs. ' + leg.away : '');
  var pick, market;
  if (leg.isBB && Array.isArray(leg.legs)) {
    market = 'BetBuilder';
    pick = leg.legs.map(function(L){
      var mk = (L.market || '').trim();
      var pk = (L.name || L.pick || '').trim();
      return (mk ? mk + ': ' : '') + pk;
    }).join(' | ');
  } else {
    market = (leg.market || '').trim();
    pick   = (leg.sel || leg.pick || '').trim();
  }
  return '<div class="sb-bet-leg">' +
           '<div class="lg-mk">' + _escHtml(teams) + (market ? ' — ' + _escHtml(market) : '') + '</div>' +
           '<div class="lg-pk">' + _escHtml(pick) + '</div>' +
         '</div>';
}
function _escHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function _mbBetMatchesQuery(bet, q) {
  if (!q) return true;
  q = q.toLowerCase();
  var slip = Array.isArray(bet.slip) ? bet.slip : [];
  for (var i = 0; i < slip.length; i++) {
    var L = slip[i];
    var hay = ((L.match || '') + ' ' + (L.home || '') + ' ' + (L.away || '') + ' ' + (L.league || '') + ' ' + (L.sel || '')).toLowerCase();
    if (hay.indexOf(q) !== -1) return true;
  }
  return false;
}

function _mbRenderCard(bet) {
  var slip = Array.isArray(bet.slip) ? bet.slip : [];
  var first = slip[0] || {};
  // Title: first match's teams, or "Combiné (N)" if multiple matches
  var title;
  if (slip.length > 1) {
    title = 'Combiné (' + slip.length + ' sélections)';
  } else {
    title = (first.match || '').trim();
    if (!title) title = (first.home || '') + (first.away ? ' vs. ' + first.away : '');
    if (!title) title = '#' + bet.id;
  }
  var statusPill = _mbPillForStatus(bet.status);
  var oddsLabel  = (parseFloat(bet.total_odds) || 1).toFixed(2);

  var body = '';
  body += '<div class="sb-bet-card-row"><span>Cotes totales</span><span class="v green">' + oddsLabel + '</span></div>';
  body += '<div class="sb-bet-card-row"><span>Mise totale</span><span class="v">' + _mbFmtAmount(bet.amount) + '</span></div>';
  body += '<div class="sb-bet-card-row"><span>Gain total</span><span class="v">' + _mbFmtAmount(bet.potential_returns) + '</span></div>';
  // Expanded selections
  if (slip.length) {
    body += '<div class="sb-bet-card-row" style="border-bottom:0;padding-bottom:2px"><span style="font-size:11px;color:#888">SÉLECTIONS</span></div>';
    body += slip.map(_mbRenderLegInline).join('');
  }

  return '<div class="sb-bet-card" id="sb-bet-card-' + bet.id + '">' +
           '<div class="sb-bet-card-hdr" onclick="window.sbMyBetsToggle(' + bet.id + ')">' +
             '<svg class="sb-bet-card-hdr-chev" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
             '<span class="sb-bet-card-title">' + _escHtml(title) + '</span>' +
             statusPill +
           '</div>' +
           '<div class="sb-bet-card-body" style="display:none">' + body + '</div>' +
           '<div class="sb-bet-card-foot">' +
             '<span class="meta">' + _mbFmtDateShort(bet.created_at) + '  Numéro d\'identification: ' + bet.id + '</span>' +
             '<button class="btn-icon" title="Imprimer" onclick="event.stopPropagation();window.sbPrintBet(' + bet.id + ',\'copy\')">' +
               '<svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4V1.5h8V4M4 12H2.5V6.5h11V12H12M4 9.5h8V15H4V9.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>' +
             '</button>' +
             '<button class="btn-icon" title="Plus d\'options" onclick="event.stopPropagation();window.sbToggleMyBetsMenu(' + bet.id + ', event)">' +
               '<svg viewBox="0 0 16 16" fill="currentColor"><circle cx="3" cy="8" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="13" cy="8" r="1.4"/></svg>' +
             '</button>' +
           '</div>' +
         '</div>';
}

window.sbMyBetsToggle = function(id) {
  var card = document.getElementById('sb-bet-card-' + id);
  if (!card) return;
  var body = card.querySelector('.sb-bet-card-body');
  if (!body) return;
  var open = card.classList.toggle('is-open');
  body.style.display = open ? 'block' : 'none';
};

window.sbToggleMyBetsMenu = function(id, ev) {
  // Close any open menu first
  document.querySelectorAll('.sb-bet-print-menu').forEach(function(m){ m.parentNode.removeChild(m); });
  if (ev && ev.stopPropagation) ev.stopPropagation();
  var btn = ev && ev.currentTarget;
  if (!btn) return;
  var menu = document.createElement('div');
  menu.className = 'sb-bet-print-menu';
  menu.innerHTML =
    '<div class="item" onclick="window.sbPrintBet(' + id + ',\'copy\')">Imprimer</div>' +
    '<div class="item" onclick="window.sbPrintBet(' + id + ',\'combinations\')">Imprimer les combinaisons</div>';
  btn.appendChild(menu);
  // Click-outside to close
  setTimeout(function() {
    var off = function(e) {
      if (!menu.contains(e.target)) {
        if (menu.parentNode) menu.parentNode.removeChild(menu);
        document.removeEventListener('click', off, true);
      }
    };
    document.addEventListener('click', off, true);
  }, 0);
};

window.sbPrintBet = function(id, mode) {
  var url = 'bet_print.php?id=' + encodeURIComponent(id) + '&mode=' + encodeURIComponent(mode || 'copy');
  window.open(url, '_blank', 'width=720,height=900,scrollbars=1,resizable=1');
};

window.sbOpenMyBets = function() {
  // Toggle: if already open, go back to main
  var page = document.getElementById('sb-mybets-page');
  if (!page) return;
  if (page.style.display !== 'none') {
    window.sbCloseMyBets();
    return;
  }
  // Hide all home panels + match listings
  sbSetHomepanelVisible(false);
  var body = document.getElementById('sb-matches-body');
  if (body) body.style.display = 'none';
  page.style.display = 'block';
  // Default date range (last 10 days → today)
  var fromEl = document.getElementById('sb-mybets-from');
  var toEl   = document.getElementById('sb-mybets-to');
  if (fromEl && !fromEl.value) fromEl.value = _mbDefaultFrom();
  if (toEl   && !toEl.value)   toEl.value   = _mbDefaultTo();
  window._mbState.from = fromEl ? fromEl.value : '';
  window._mbState.to   = toEl   ? toEl.value   : '';
  // Highlight the mybets button
  document.querySelectorAll('.sb-btn-home,.sb-btn-live,.sb-btn-mybets').forEach(function(b){ b.classList.remove('active'); });
  document.querySelectorAll('.sb-btn-mybets').forEach(function(b){ b.classList.add('active'); });
  window.sbMyBetsReload();
  try { window.scrollTo({ top: 0, behavior: 'instant' }); } catch (e) { window.scrollTo(0, 0); }
};

window.sbCloseMyBets = function() {
  var page = document.getElementById('sb-mybets-page');
  if (page) page.style.display = 'none';
  var body = document.getElementById('sb-matches-body');
  if (body) body.style.display = '';
  sbSetHomepanelVisible(true);
  // Re-activate the right tab (default home/inplay)
  document.querySelectorAll('.sb-btn-mybets').forEach(function(b){ b.classList.remove('active'); });
  sbSetTopbarActive(S.activeTab === 'live' ? 'live' : 'home');
};

window.sbMyBetsFilter = function(btn, status) {
  document.querySelectorAll('#sb-mybets-filters .sb-mybets-filter').forEach(function(b){ b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  window._mbState.status = status || 'open';
  window.sbMyBetsReload();
};

window.sbMyBetsSearch = function(v) {
  window._mbState.q = (v || '').trim().toLowerCase();
  _mbRenderList();
};

window.sbMyBetsReload = function() {
  var fromEl = document.getElementById('sb-mybets-from');
  var toEl   = document.getElementById('sb-mybets-to');
  window._mbState.from = fromEl ? fromEl.value : '';
  window._mbState.to   = toEl   ? toEl.value   : '';
  var listEl = document.getElementById('sb-mybets-list');
  if (listEl) listEl.innerHTML = '<div class="sb-mybets-loading">Chargement…</div>';
  var url = 'api.php?action=my_bets'
          + '&status=' + encodeURIComponent(window._mbState.status || 'open')
          + '&from='   + encodeURIComponent(window._mbState.from   || '')
          + '&to='     + encodeURIComponent(window._mbState.to     || '')
          + '&_t='     + Date.now();
  fetch(url, { credentials: 'same-origin', cache: 'no-store' })
    .then(function(r){ return r.json(); })
    .then(function(d){
      window._mbState.bets = (d && d.bets) ? d.bets : [];
      _mbRenderList();
    })
    .catch(function(){
      if (listEl) listEl.innerHTML = '<div class="sb-mybets-empty"><div class="sb-mybets-empty-txt">Erreur de chargement</div></div>';
    });
};

function _mbRenderList() {
  var listEl = document.getElementById('sb-mybets-list');
  if (!listEl) return;
  var st = window._mbState.status || 'open';
  var bets = (window._mbState.bets || []).filter(function(b){
    return _mbBetMatchesQuery(b, window._mbState.q);
  });
  if (!bets.length) {
    var label = 'Vous n\'avez pas de paris ouverts';
    if (st === 'settled')  label = 'Aucun pari calculé';
    if (st === 'won')      label = 'Aucun pari gagné';
    if (st === 'lost')     label = 'Aucun pari perdu';
    if (st === 'refunded') label = 'Aucun pari retiré';
    listEl.innerHTML =
      '<div class="sb-mybets-empty">' +
        '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">' +
          '<circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2.5"/>' +
          '<path d="M24 14v10l7 4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>' +
        '</svg>' +
        '<div class="sb-mybets-empty-txt">' + label + '</div>' +
      '</div>';
    return;
  }
  listEl.innerHTML = bets.map(_mbRenderCard).join('');
}

})();
