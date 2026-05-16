<?php
// wipe_production.php
require 'includes/db.php';

// Security check
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'YES_WIPE') {
    die("<h1>Database Wipe Script</h1><p>Please navigate to <code>?confirm=YES_WIPE</code> to confirm wiping all data except the Owner account.</p>");
}

try {
    // 1. Delete all users EXCEPT the Owner account we seeded.
    // The user mentioned Owner@alpina365.com and Owner@alpina216.com
    $stmt = $pdo->prepare("DELETE FROM users WHERE email NOT LIKE 'Owner@%' AND username NOT LIKE 'Owner@%'");
    $stmt->execute();
    $deletedUsers = $stmt->rowCount();
    echo "Deleted $deletedUsers users.<br>";

    // 2. Truncate all related data tables
    $tablesToTruncate = [
        'game_callback_events',
        'game_round_exposures',
        'player_deposit_methods',
        'user_deposit_methods',
        'user_withdraw_banks',
        'crypto_addresses',
        'payments',
        'transactions',
        'recent_games',
        'bet_history',
        'sessions'
    ];

    // Disable foreign key checks to safely truncate
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tablesToTruncate as $table) {
        try {
            $pdo->exec("TRUNCATE TABLE `$table`");
            echo "Truncated table <strong>$table</strong>.<br>";
        } catch (Exception $e) {
            // Table might not exist, which is fine
            echo "<span style='color: gray'>Skipped table $table (may not exist).</span><br>";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "<h2 style='color: green'>Database wiped successfully!</h2>";
    echo "<p>The dashboard is now empty and like new. Your Owner admin account was preserved.</p>";
    echo "<p><strong>IMPORTANT:</strong> For security reasons, please delete this script (<code>wipe_production.php</code>) from the server now.</p>";

} catch (Exception $e) {
    die("<h2 style='color: red'>Error:</h2> " . htmlspecialchars($e->getMessage()));
}
