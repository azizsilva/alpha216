<?php
$local_env = dirname(__DIR__) . '/local.env.php';
if (file_exists($local_env)) {
    require_once $local_env;
}

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'alpha216_db';
$user = getenv('DB_USER') ?: 'admin'; // Default to admin for production
$pass = getenv('DB_PASS') ?: 'Alpina@2026'; // Default to the password we set
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
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
