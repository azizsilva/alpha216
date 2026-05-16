<?php
// Set headers
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors to JSON response

// Log function
function logSearchError($message) {
    $logFile = __DIR__ . '/../error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [SEARCH API] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    // Get query
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';

    if (strlen($query) < 1) {
        // Log short query attempt? No, too noisy.
        echo json_encode([]);
        exit;
    }

    // Directory Setup
    $jsonDir = __DIR__ . '/../games-json';
    
    if (!is_dir($jsonDir)) {
        logSearchError("Directory not found: $jsonDir");
        echo json_encode([]);
        exit;
    }

    $results = [];
    $seen = [];
    $limit = 80;
    $queryLower = strtolower($query);

    // Scan directory
    $files = glob($jsonDir . '/*.json');

    if ($files === false) {
        logSearchError("glob() failed for pattern: $jsonDir/*.json");
        echo json_encode([]);
        exit;
    }

    if (empty($files)) {
        logSearchError("No JSON files found in: $jsonDir");
        echo json_encode([]);
        exit;
    }

    foreach ($files as $file) {
        $jsonContent = file_get_contents($file);
        if ($jsonContent === false) {
            logSearchError("Failed to read file: $file");
            continue;
        }

        $games = json_decode($jsonContent, true);
        if ($games === null && json_last_error() !== JSON_ERROR_NONE) {
            logSearchError("JSON Decode Error in file $file: " . json_last_error_msg());
            continue;
        }

        if (!is_array($games)) {
            // logSearchError("Invalid JSON structure in file $file (not an array)");
            continue;
        }

        foreach ($games as $game) {
            $gameName = isset($game['gamename']) ? trim((string)$game['gamename']) : '';
            $provider = isset($game['providerName']) ? trim((string)$game['providerName']) : '';
            $gameId = isset($game['gameid']) ? trim((string)$game['gameid']) : '';
            if ($gameName !== '' && $gameId !== '' && stripos($gameName, $query) !== false) {
                $dedupeKey = strtolower($gameId . '|' . $gameName);
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                
                // Image handling
                $img = !empty($game['image']) ? $game['image'] : 'https://moneyking365.com/assets/images/landing/b2c-b2c/ace-casino.png';
                
                // Link construction
                // Ensure URL is relative to the calling page (usually index.php in root)
                // If api/search.php is called directly, this path is wrong, but it's for AJAX consumption by index.php
                $url = "play/index.php?game_id=" . urlencode($gameId) . "&provider=" . urlencode($provider);

                $results[] = [
                    'name' => $gameName,
                    'img' => $img,
                    'url' => $url,
                    'provider' => $provider,
                    'game_id' => $gameId,
                    'score' => stripos($gameName, $query) === 0 ? 0 : 1
                ];
            }
        }
    }

    usort($results, function ($a, $b) use ($queryLower) {
        $scoreA = (int)($a['score'] ?? 1);
        $scoreB = (int)($b['score'] ?? 1);
        if ($scoreA !== $scoreB) return $scoreA <=> $scoreB;
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
    $results = array_slice(array_map(function ($item) {
        unset($item['score']);
        return $item;
    }, $results), 0, $limit);

    // Log empty results for valid queries to help debug "search not working"
    if (empty($results)) {
        logSearchError("No results found for query: '$query'");
    } else {
        // Optional: Log success (might be too spammy)
        // logSearchError("Found " . count($results) . " results for query: '$query'");
    }

    echo json_encode($results);

} catch (Exception $e) {
    logSearchError("Exception: " . $e->getMessage());
    echo json_encode(['error' => 'Internal Server Error']);
}
