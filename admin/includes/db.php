<?php
require_once __DIR__ . '/../../includes/db.php';

if (isset($pdo)) {
    try {
        try {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','partner','super_master','master','agent','player') NOT NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN password_text VARCHAR(255) NULL AFTER password");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("UPDATE users SET password_text = 'admin123' WHERE username = 'admin' AND password = '0192023a7bbd73250516f069df18b500' AND (password_text IS NULL OR password_text = '')");
        } catch (Exception $e) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_modes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            channel ENUM('upi','bank','wallet','cash','gateway','other') NOT NULL DEFAULT 'other',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            fee_percent DECIMAL(6,3) NOT NULL DEFAULT 0.000,
            fee_flat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            allowed_roles VARCHAR(100) NOT NULL DEFAULT 'admin,partner,super_master,master,agent',
            config_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_payment_mode_name (name),
            KEY idx_payment_modes_enabled (enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            payer_id INT NULL,
            payee_id INT NULL,
            created_by INT NOT NULL,
            mode_id INT NULL,
            type ENUM('deposit','withdrawal','adjustment','refund') NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            fee_percent DECIMAL(6,3) NOT NULL DEFAULT 0.000,
            fee_flat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'pending',
            reference VARCHAR(100) NULL,
            note VARCHAR(255) NULL,
            meta_json TEXT NULL,
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_payments_status_created (status, created_at),
            KEY idx_payments_payer (payer_id, created_at),
            KEY idx_payments_payee (payee_id, created_at),
            KEY idx_payments_created_by (created_by, created_at),
            CONSTRAINT fk_payments_mode FOREIGN KEY (mode_id) REFERENCES payment_modes(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT NOT NULL,
            actor_role VARCHAR(20) NOT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(50) NULL,
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            old_json TEXT NULL,
            new_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_audit_actor (actor_id, created_at),
            KEY idx_audit_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_by INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS bonus_ledger (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            note VARCHAR(255) NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_bonus_user_time (user_id, created_at),
            KEY idx_bonus_created_by (created_by, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS loyalty_ledger (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            note VARCHAR(255) NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_loyalty_user_time (user_id, created_at),
            KEY idx_loyalty_created_by (created_by, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settlements (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT NOT NULL,
            to_user_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
            note VARCHAR(255) NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_settle_from_time (from_user_id, created_at),
            KEY idx_settle_to_time (to_user_id, created_at),
            KEY idx_settle_status_time (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS deposit_methods (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            owner_id INT NOT NULL,
            target_role ENUM('partner','super_master','master','agent','player') NOT NULL,
            label VARCHAR(100) NOT NULL,
            channel ENUM('upi','bank','wallet','cash','gateway','other') NOT NULL DEFAULT 'other',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            details_json TEXT NULL,
            source_method_id BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_owner_target_label (owner_id, target_role, label),
            KEY idx_deposit_methods_owner_target (owner_id, target_role, enabled, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        try {
            $pdo->exec("ALTER TABLE deposit_methods MODIFY target_role ENUM('partner','super_master','master','agent','player') NOT NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE deposit_methods ADD COLUMN source_method_id BIGINT NULL");
        } catch (Exception $e) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS player_deposit_methods (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            agent_id INT NOT NULL,
            label VARCHAR(100) NOT NULL,
            channel ENUM('upi','bank','wallet','cash','gateway','other') NOT NULL DEFAULT 'other',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            details_json TEXT NULL,
            source_method_id BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_player_deposit_agent_label (agent_id, label),
            KEY idx_player_deposit_agent (agent_id, enabled, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        try {
            $pdo->exec("ALTER TABLE player_deposit_methods ADD COLUMN source_method_id BIGINT NULL");
        } catch (Exception $e) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_deposit_methods (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            label VARCHAR(100) NOT NULL,
            channel ENUM('upi','bank','wallet','cash','gateway','other') NOT NULL DEFAULT 'other',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            details_json TEXT NULL,
            source_method_id BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_deposit_user_label (user_id, label),
            KEY idx_user_deposit_user (user_id, enabled, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        try {
            $pdo->exec("ALTER TABLE user_deposit_methods ADD COLUMN source_method_id BIGINT NULL");
        } catch (Exception $e) {
        }

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

        try {
            $pdo->exec("INSERT IGNORE INTO player_deposit_methods (agent_id, label, channel, enabled, sort_order, details_json, created_at, updated_at)
                SELECT owner_id, label, channel, enabled, sort_order, details_json, created_at, updated_at
                FROM deposit_methods
                WHERE target_role = 'player'");
        } catch (Exception $e) {
        }

        $stmt = $pdo->query("SELECT COUNT(*) FROM payment_modes");
        $count = (int)$stmt->fetchColumn();
        if ($count === 0) {
            $pdo->exec("INSERT INTO payment_modes (name, channel, enabled, fee_percent, fee_flat, allowed_roles) VALUES
                ('UPI', 'upi', 1, 0.000, 0.00, 'admin,partner,super_master,master,agent'),
                ('Bank Transfer', 'bank', 1, 0.000, 0.00, 'admin,partner,super_master,master,agent'),
                ('Cash', 'cash', 1, 0.000, 0.00, 'admin,partner,super_master,master,agent'),
                ('Wallet', 'wallet', 1, 0.000, 0.00, 'admin,partner,super_master,master,agent');");
        }
    } catch (Exception $e) {
    }
}
?>
