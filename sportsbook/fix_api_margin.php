<?php
$file = 'c:/wamp64/www/public_html/sportsbook/api.php';
$code = file_get_contents($file);

// 1. Clean up bad insert
$bad_code = "    \$sports = [\n    '1' => 'football',\n    '18' => 'basketball',\n    '13' => 'tennis'\n];\n\n// --- PROVIDER ODDS ENGINE (MARGIN) ---\n\$global_margin_pct = 11.0;\ntry {\n    \$c_stmt = \$pdo->query(\"SELECT setting_value FROM provider_config WHERE setting_key='global_margin_percent'\");\n    if (\$c_row = \$c_stmt->fetch(PDO::FETCH_ASSOC)) {\n        \$global_margin_pct = (float)\$c_row['setting_value'];\n    }\n} catch (Exception \$e) {}\n\nfunction apply_margin(\$odds_decimal) {\n    global \$global_margin_pct;\n    \$odds = (float)\$odds_decimal;\n    if (\$odds <= 1.05) return \$odds; // Don't touch extreme favorites\n    \$prob = 1 / \$odds;\n    \$new_prob = \$prob * (1 + (\$global_margin_pct / 100));\n    if (\$new_prob >= 1) return 1.01;\n    return round(1 / \$new_prob, 2);\n}\n// -------------------------------------";
$code = str_replace($bad_code, "", $code);

// 2. Insert correct Provider Margin engine at the top
$insert_str = "
// --- PROVIDER ODDS ENGINE (MARGIN) ---
\$global_margin_pct = 11.0;
try {
    \$c_stmt = \$pdo->query(\"SELECT setting_value FROM provider_config WHERE setting_key='global_margin_percent'\");
    if (\$c_row = \$c_stmt->fetch(PDO::FETCH_ASSOC)) {
        \$global_margin_pct = (float)\$c_row['setting_value'];
    }
} catch (Exception \$e) {}

function apply_margin_to_odds(\$odds_decimal) {
    global \$global_margin_pct;
    \$odds = (float)\$odds_decimal;
    if (\$odds <= 1.05) return \$odds;
    \$prob = 1 / \$odds;
    \$new_prob = \$prob * (1 + (\$global_margin_pct / 100));
    if (\$new_prob >= 1) return 1.01;
    return round(1 / \$new_prob, 2);
}

function apply_margin_to_markets(&\$markets) {
    if (!is_array(\$markets)) return;
    foreach (\$markets as &\$m) {
        if (isset(\$m['selections']) && is_array(\$m['selections'])) {
            foreach (\$m['selections'] as &\$sel) {
                if (isset(\$sel['odds'])) {
                    \$sel['odds'] = apply_margin_to_odds(\$sel['odds']);
                }
            }
        }
        if (isset(\$m['h'])) \$m['h'] = apply_margin_to_odds(\$m['h']);
        if (isset(\$m['x'])) \$m['x'] = apply_margin_to_odds(\$m['x']);
        if (isset(\$m['a'])) \$m['a'] = apply_margin_to_odds(\$m['a']);
    }
}
// -------------------------------------
";

// Insert right after the DB include
$code = preg_replace("/(require_once __DIR__ \. '\/\.\.\/includes\/db\.php';)/", "$1\n" . $insert_str, $code, 1);

// 3. Inject apply_margin_to_markets before saving odds
$replacements = [
    '$pm = api_parse_prematch_odds($or);' => '$pm = api_parse_prematch_odds($or); if ($pm) apply_margin_to_markets($pm);',
    '$pm_bgu = api_parse_prematch_odds($or_bgu);' => '$pm_bgu = api_parse_prematch_odds($or_bgu); if ($pm_bgu) apply_margin_to_markets($pm_bgu);',
    '$feo_pm = api_parse_prematch_odds($feo_ev);' => '$feo_pm = api_parse_prematch_odds($feo_ev); if ($feo_pm) apply_margin_to_markets($feo_pm);',
    '$feo_pm = api_parse_prematch_odds($feo_pre);' => '$feo_pm = api_parse_prematch_odds($feo_pre); if ($feo_pm) apply_margin_to_markets($feo_pm);',
    '$pm = parse_event_stream_odds($or_ev[\'results\']);' => '$pm = parse_event_stream_odds($or_ev[\'results\']); if ($pm) apply_margin_to_markets($pm);',
    '$pm_bgu = parse_event_stream_odds($or_ev_bgu[\'results\']);' => '$pm_bgu = parse_event_stream_odds($or_ev_bgu[\'results\']); if ($pm_bgu) apply_margin_to_markets($pm_bgu);',
    '$feo_pm = parse_event_stream_odds($feo_ev[\'results\']);' => '$feo_pm = parse_event_stream_odds($feo_ev[\'results\']); if ($feo_pm) apply_margin_to_markets($feo_pm);',
    '$pm = parse_event_stream_odds($or[\'results\']);' => '$pm = parse_event_stream_odds($or[\'results\']); if ($pm) apply_margin_to_markets($pm);',
    '$pm_bgu = parse_event_stream_odds($or_bgu[\'results\']);' => '$pm_bgu = parse_event_stream_odds($or_bgu[\'results\']); if ($pm_bgu) apply_margin_to_markets($pm_bgu);',
    '$live_odds = $h_o ? [\'h\'=>$h_o,\'x\'=>$x_o,\'a\'=>$a_o,\'ou_line\'=>$ou_line,\'ou_over\'=>$ov_o,\'ou_under\'=>$un_o,\'ts\'=>time()] : null;' => '$live_odds = $h_o ? [\'h\'=>$h_o,\'x\'=>$x_o,\'a\'=>$a_o,\'ou_line\'=>$ou_line,\'ou_over\'=>$ov_o,\'ou_under\'=>$un_o,\'ts\'=>time()] : null; if ($live_odds) apply_margin_to_markets($live_odds);',
    'return $markets;' => 'apply_margin_to_markets($markets); return $markets;'
];

foreach ($replacements as $search => $replace) {
    $code = str_replace($search, $replace, $code);
}

file_put_contents($file, $code);
echo "Applied margin logic via PHP script.\n";
