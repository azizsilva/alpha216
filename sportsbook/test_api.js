/**
 * test_api.js — Quick test: is odds-api.io key alive?
 *
 * Usage:  node test_api.js
 */
'use strict';
const API_KEY = 'fbfb8d1a32e0f0a1b4dc55ef2b72abad19e86f1b9c37df1032464e25882e68f2';

let OddsAPIClient;
try { OddsAPIClient = require('odds-api-io').OddsAPIClient; }
catch(e) { console.error('odds-api-io not installed. Run: npm install'); process.exit(1); }

async function main() {
  const client = new OddsAPIClient({ apiKey: API_KEY });
  console.log('Testing odds-api.io key:', API_KEY.slice(0,8) + '...' + API_KEY.slice(-4));

  // Test 1: fetch live football events
  console.log('\n[1] getLiveEvents("football")...');
  try {
    const resp = await client.getLiveEvents('football');
    const arr  = Array.isArray(resp) ? resp : (resp?.data || resp?.events || []);
    if (arr.length > 0) {
      console.log('✓ SUCCESS — football events:', arr.length);
      const ev = arr[0];
      console.log('  Sample event:', ev.home, 'vs', ev.away, '|', ev.league, '| score:', ev.score?.home + '-' + ev.score?.away);

      // Test 2: fetch odds for first event
      console.log('\n[2] getEventOdds for event', ev.id, '...');
      const odds = await client.getEventOdds({ eventId: String(ev.id), bookmakers: 'Bet365' });
      const bk   = odds?.bookmakers || odds?.data?.bookmakers || {};
      const mkts  = Object.values(bk)[0] || [];
      if (mkts.length > 0) {
        console.log('✓ SUCCESS — markets for this event:', mkts.map(m=>m.name).join(', '));
      } else {
        console.log('⚠ Event found but no odds/markets returned for Bet365');
        console.log('  Raw odds response keys:', Object.keys(odds||{}));
      }
    } else {
      console.log('⚠ API responded OK but returned 0 events.');
      console.log('  (No live football right now, or events filtered out)');
      console.log('  Raw response type:', typeof resp, Array.isArray(resp)?'array':'object');
    }
  } catch(e) {
    if (/429|rate.limit|too many/i.test(e.message)) {
      console.error('✗ RATE LIMITED (429) — API quota not yet reset. Wait a few minutes and try again.');
    } else if (/403|401|unauthorized/i.test(e.message)) {
      console.error('✗ AUTH ERROR (403/401) — API key invalid or not activated.');
    } else {
      console.error('✗ ERROR:', e.message);
    }
  }

  process.exit(0);
}

main().catch(e => { console.error('Fatal:', e.message); process.exit(1); });
