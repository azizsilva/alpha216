<?php
require_once dirname(__DIR__, 2) . '/forza/includes/db.php';

if (!$pdo) {
    die("Database connection failed.");
}

echo "Setting up Sportsbook tables in xbet_db...<br>\n";

// Leagues table
$pdo->exec("CREATE TABLE IF NOT EXISTS sb_leagues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_league_id VARCHAR(50) NULL,
    name VARCHAR(150) NOT NULL,
    country VARCHAR(100) NULL,
    sport_id INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    UNIQUE KEY uniq_league_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "sb_leagues checked.<br>\n";

// Matches table
$pdo->exec("CREATE TABLE IF NOT EXISTS sb_matches (
    id VARCHAR(50) PRIMARY KEY,
    sport_id INT NOT NULL DEFAULT 1,
    league_name VARCHAR(150) NOT NULL,
    home_team VARCHAR(100) NOT NULL,
    away_team VARCHAR(100) NOT NULL,
    start_time INT NOT NULL,
    status ENUM('upcoming', 'inplay', 'ended') NOT NULL DEFAULT 'upcoming',
    score VARCHAR(20) NULL,
    raw_json MEDIUMTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_match_time (start_time),
    KEY idx_match_league (league_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "sb_matches checked.<br>\n";

// Odds table
$pdo->exec("CREATE TABLE IF NOT EXISTS sb_odds (
    match_id VARCHAR(50) NOT NULL,
    market_key VARCHAR(50) NOT NULL,
    selection VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (match_id, market_key, selection)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "sb_odds checked.<br>\n";

// Bets table
$pdo->exec("CREATE TABLE IF NOT EXISTS sb_bets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    match_id VARCHAR(50) NOT NULL,
    selection VARCHAR(50) NOT NULL,
    odds_taken DECIMAL(10,2) NOT NULL,
    stake DECIMAL(15,2) NOT NULL,
    potential_return DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'won', 'lost', 'refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bet_user (user_id),
    KEY idx_bet_match (match_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "sb_bets checked.<br>\n";

echo "<b>Setup Complete!</b><br>\n";
?>
