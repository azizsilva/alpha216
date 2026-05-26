<?php
require_once __DIR__ . '/includes/db.php';

try {
    if (!$pdo) {
        die("No database connection.\n");
    }

    // 1. Modify users table to accept 'provider' role if it's an ENUM
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $row = $stmt->fetch();
    if ($row) {
        $type = $row['Type'];
        if (strpos($type, 'enum') !== false && strpos($type, "'provider'") === false) {
            // It's an enum and missing 'provider'
            $newType = str_replace(")", ",'provider')", $type);
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role $newType");
            echo "Added 'provider' to role ENUM.\n";
        }
    }

    // 2. Create provider_config table
    $pdo->exec("CREATE TABLE IF NOT EXISTS provider_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Created provider_config table.\n";

    // 3. Insert default config
    $pdo->exec("INSERT IGNORE INTO provider_config (setting_key, setting_value) VALUES ('global_margin_percent', '11')");
    $pdo->exec("INSERT IGNORE INTO provider_config (setting_key, setting_value) VALUES ('max_bet_liability', '5000')");
    echo "Inserted default provider configs.\n";

    // 4. Create market_exposure table
    $pdo->exec("CREATE TABLE IF NOT EXISTS market_exposure (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        match_id VARCHAR(50) NOT NULL,
        market_id VARCHAR(50) NOT NULL,
        selection_id VARCHAR(100) NOT NULL,
        total_staked DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        liability DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_exposure (match_id, market_id, selection_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Created market_exposure table.\n";

    // 5. Seed Provider User
    $email = 'provider@gmail.com';
    $password = 'provider@gmail.com';
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'provider', password_text = ?, status = 'active' WHERE id = ?");
        $stmt->execute([$hashed, $password, $user['id']]);
        echo "Updated provider user.\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, password_text, role, status, balance, mobile) VALUES (?, ?, ?, ?, 'provider', 'active', 0, '00000000')");
        // Username can be Provider
        $stmt->execute(['Provider', $email, $hashed, $password]);
        echo "Inserted new provider user.\n";
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
