<?php
$local_env = dirname(__DIR__) . '/local.env.php';
if (file_exists($local_env)) {
    require_once $local_env;
}

$host = getenv('DB_HOST') ?: 'localhost';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Try multiple databases, users and passwords to auto-connect on any environment
$databases_to_try = array_unique(array_filter([
    getenv('DB_NAME') ?: null,
    'alpina216_db',   // production domain
    'alpha216_db',    // legacy name
    'forza_db',
    'xbet_db',
    'u842075676_tanichub'
]));
$users_to_try = array_unique(array_filter([
    getenv('DB_USER') ?: null,
    'root',
    'admin',
    'alpina216_usr',
]));
$passwords_to_try = array_unique([
    getenv('DB_PASS') ?: 'Alpina@2026',
    'Alpina@2026',
    '',
    'root'
]);

$pdo = null;
$cache_file = dirname(__DIR__) . '/.db_auth_cache.php';

// First, try the cached configuration if it exists to avoid trying wrong passwords (which blocks the host in MySQL)
if (file_exists($cache_file)) {
    include $cache_file;
    if (isset($cached_db, $cached_user, $cached_pass)) {
        try {
            $dsn = "mysql:host=$host;dbname=$cached_db;charset=utf8mb4";
            $pdo = new PDO($dsn, $cached_user, $cached_pass, $options);
        } catch (\PDOException $e) {
            $pdo = null;
        }
    }
} // end function 

if (!$pdo) {
    foreach ($databases_to_try as $db) {
        if (!$db) continue;
        foreach ($users_to_try as $user) {
            foreach ($passwords_to_try as $pass) {
                try {
                    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
                    $pdo = new PDO($dsn, $user, $pass, $options);
                    $cache_content = "<?php\n\$cached_db = " . var_export($db, true) . ";\n\$cached_user = " . var_export($user, true) . ";\n\$cached_pass = " . var_export($pass, true) . ";\n";
                    @file_put_contents($cache_file, $cache_content);
                    break 3; // Success
                } catch (\PDOException $e) {
                }
            }
        }
    }
}



if (!$pdo) {
    if (isset($_SERVER["HTTP_ACCEPT"]) && strpos($_SERVER["HTTP_ACCEPT"], "application/json") !== false) {
        http_response_code(503);
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "message" => "Database connection not established"]);
        exit;
    }
    $error_msg = "Database connection not established. Auto-detection failed.";
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Maintenance - Alpha216</title>';
    echo '<style>body{background:#000;color:#fff;font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;flex-direction:column;}';
    echo 'h1{color:#39FF14;font-size:40px;margin-bottom:10px;}p{font-size:18px;color:#ccc;max-width:600px;line-height:1.5;}';
    echo '.loader{border:4px solid #333;border-top:4px solid #39FF14;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin:20px auto;}';
    echo '@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}</style></head>';
    echo '<body><div class="loader"></div><h1>System Maintenance</h1>';
    echo '<p>We are currently performing essential maintenance on our servers. Please check back in a few minutes.</p>';
    echo '<p style="font-size:12px;color:#555;margin-top:40px;">' . $error_msg . '</p>';
    echo '</body></html>';
    exit;
}
try {
    $schema_marker = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.mk_schema_ready';
    if (!file_exists($schema_marker)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS recent_games (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            game_id VARCHAR(50) NOT NULL,
            played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_game (user_id, game_id)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS game_callback_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(191) NOT NULL,
            action ENUM('bet','win','refund') NOT NULL,
            game_uid VARCHAR(100) NULL,
            txn_id VARCHAR(128) NOT NULL,
            game_round VARCHAR(128) NULL,
            provider_ts DATETIME NULL,
            bet_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            win_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            amount_delta DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            balance_before DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            balance_after DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            result_status TINYINT(1) NOT NULL DEFAULT 1,
            result_message VARCHAR(255) NULL,
            request_ip VARCHAR(64) NULL,
            request_ua VARCHAR(255) NULL,
            raw_json MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_txn_action (txn_id, action),
            KEY idx_user_time (user_id, created_at),
            KEY idx_game_time (game_uid, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS game_round_exposures (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            game_round VARCHAR(128) NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_round (user_id, game_round),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS player_deposit_methods (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            agent_id INT NOT NULL,
            label VARCHAR(100) NOT NULL,
            channel ENUM('upi','bank','wallet','cash','gateway','other') NOT NULL DEFAULT 'other',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            details_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_player_deposit_agent_label (agent_id, label),
            KEY idx_player_deposit_agent (agent_id, enabled, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_deposit_methods (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            label VARCHAR(100) NOT NULL,
            channel ENUM('upi','bank','wallet','cash','gateway','other') NOT NULL DEFAULT 'other',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            details_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_deposit_user_label (user_id, label),
            KEY idx_user_deposit_user (user_id, enabled, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_withdraw_banks (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bank_slot TINYINT NOT NULL,
            bank_name VARCHAR(120) NOT NULL,
            ifsc_swift VARCHAR(32) NOT NULL,
            account_no VARCHAR(40) NOT NULL,
            account_holder VARCHAR(120) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_bank_slot (user_id, bank_slot),
            KEY idx_user_banks_user (user_id, enabled, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        @file_put_contents($schema_marker, (string)time());
    }
    
} catch (\PDOException $e) {
    if (strpos($e->getMessage(), "Unknown database") !== false) {
        // Instead of die, we can log and continue with null pdo if needed, 
        // but for this app we'll just throw so the caller can catch.
        error_log("Database 'moneyking' not found.");
        // We'll just set pdo to null and let the application handle it.
        $pdo = null;
    }
} // end method testing 
?>
