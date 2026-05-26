import re

with open('c:/wamp64/www/public_html/sportsbook/api.php', 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Clean up my bad insert at line 449
bad_code = """    $sports = [
    '1' => 'football',
    '18' => 'basketball',
    '13' => 'tennis'
];

// --- PROVIDER ODDS ENGINE (MARGIN) ---
$global_margin_pct = 11.0;
try {
    $c_stmt = $pdo->query("SELECT setting_value FROM provider_config WHERE setting_key='global_margin_percent'");
    if ($c_row = $c_stmt->fetch(PDO::FETCH_ASSOC)) {
        $global_margin_pct = (float)$c_row['setting_value'];
    }
} catch (Exception $e) {}

function apply_margin($odds_decimal) {
    global $global_margin_pct;
    $odds = (float)$odds_decimal;
    if ($odds <= 1.05) return $odds; // Don't touch extreme favorites
    $prob = 1 / $odds;
    $new_prob = $prob * (1 + ($global_margin_pct / 100));
    if ($new_prob >= 1) return 1.01;
    return round(1 / $new_prob, 2);
}
// -------------------------------------"""
code = code.replace(bad_code, "")

# 2. Insert it at the top, right after includes
insert_str = """
// --- PROVIDER ODDS ENGINE (MARGIN) ---
$global_margin_pct = 11.0;
try {
    $c_stmt = $pdo->query("SELECT setting_value FROM provider_config WHERE setting_key='global_margin_percent'");
    if ($c_row = $c_stmt->fetch(PDO::FETCH_ASSOC)) {
        $global_margin_pct = (float)$c_row['setting_value'];
    }
} catch (Exception $e) {}

function apply_margin_to_odds($odds_decimal) {
    global $global_margin_pct;
    $odds = (float)$odds_decimal;
    if ($odds <= 1.05) return $odds; // Don't touch extreme favorites
    $prob = 1 / $odds;
    $new_prob = $prob * (1 + ($global_margin_pct / 100));
    if ($new_prob >= 1) return 1.01;
    return round(1 / $new_prob, 2);
}

function apply_margin_to_markets(&$markets) {
    if (!is_array($markets)) return;
    // Iterate over markets
    foreach ($markets as &$m) {
        if (isset($m['selections']) && is_array($m['selections'])) {
            foreach ($m['selections'] as &$sel) {
                if (isset($sel['odds'])) {
                    $sel['odds'] = apply_margin_to_odds($sel['odds']);
                }
            }
        }
        // Also check if it's the old live_odds structure: ['h'=>2.0, 'x'=>3.0, 'a'=>4.0]
        if (isset($m['h'])) $m['h'] = apply_margin_to_odds($m['h']);
        if (isset($m['x'])) $m['x'] = apply_margin_to_odds($m['x']);
        if (isset($m['a'])) $m['a'] = apply_margin_to_odds($m['a']);
    }
}
// -------------------------------------
"""

# Find the first require_once and insert after it
code = re.sub(r"(require_once __DIR__ \. '/\.\./includes/db\.php';)", r"\1" + "\n" + insert_str, code)

# 3. Apply the margin to all places where we output odds to JSON or Cache
# Look for places where $pm is assigned and then stored
# Example: $pm = api_parse_prematch_odds(...)
code = re.sub(r"(\$pm = api_parse_prematch_odds\([^)]+\);)", r"\1 if ($pm) apply_margin_to_markets($pm);", code)
code = re.sub(r"(\$pm_bgu = api_parse_prematch_odds\([^)]+\);)", r"\1 if ($pm_bgu) apply_margin_to_markets($pm_bgu);", code)
code = re.sub(r"(\$feo_pm = api_parse_prematch_odds\([^)]+\);)", r"\1 if ($feo_pm) apply_margin_to_markets($feo_pm);", code)
code = re.sub(r"(\$pm = parse_event_stream_odds\([^)]+\);)", r"\1 if ($pm) apply_margin_to_markets($pm);", code)
code = re.sub(r"(\$pm_bgu = parse_event_stream_odds\([^)]+\);)", r"\1 if ($pm_bgu) apply_margin_to_markets($pm_bgu);", code)
code = re.sub(r"(\$feo_pm = parse_event_stream_odds\([^)]+\);)", r"\1 if ($feo_pm) apply_margin_to_markets($feo_pm);", code)

# Also for $live_odds = ['h'=>$h_o, ...]
code = re.sub(r"(\$live_odds = \[.*?\] : null;)", r"\1 if ($live_odds) apply_margin_to_markets($live_odds);", code)

# Also synthetic markets generator: function api_build_synthetic_markets
# Before return $markets;
code = re.sub(r"(return \$markets;)", r"apply_margin_to_markets($markets); \1", code)

with open('c:/wamp64/www/public_html/sportsbook/api.php', 'w', encoding='utf-8') as f:
    f.write(code)

print("Applied margin logic via Python script.")
