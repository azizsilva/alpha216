<?php
/**
 * Odds Sync Service (cron)
 * 
 * This script runs continuously or periodically to fetch odds from the odds provider
 * and store them into Redis (via Predis) or file cache so the React UI can fetch them instantly.
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/odds-api.php';

// Enable error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting Odds Sync Service...\n";

// Function to store in cache (Redis if available, fallback to file)
function sync_cache_set($key, $data) {
    global $redis;
    if (isset($redis)) {
        $redis->set('odds_' . $key, json_encode($data));
        // Odds change fast, 20 second TTL
        $redis->expire('odds_' . $key, 20); 
    } else {
        odds_api_cache_set($key, $data);
    }
}

// 1. Fetch live events
echo "Fetching live soccer events...\n";
$live_events = odds_api_get('/events/live', ['sport' => 'soccer'], 20);
if (isset($live_events['__error'])) {
    echo "Error fetching live events: " . $live_events['message'] . "\n";
} else {
    // Overwrite the specific cache key that React fetches
    $cache_key = hash('sha256', '/events/live?sport=soccer');
    sync_cache_set($cache_key, $live_events);
    echo "Live events synced successfully. Found: " . count($live_events) . " events.\n";
}

// In a real sportsbook architecture, we'd loop over all sports and leagues here
// and fetch their odds, caching each endpoint the frontend needs.

echo "Odds Sync completed at " . date('Y-m-d H:i:s') . "\n";
