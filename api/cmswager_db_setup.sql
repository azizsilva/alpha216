-- CMS Wager Integration Tables
-- Run once on your MySQL database

-- Session tokens (created at launch, used by wallet callback)
CREATE TABLE IF NOT EXISTS cmswager_sessions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token       VARCHAR(64)  NOT NULL UNIQUE,
    cw_username VARCHAR(64)  NOT NULL,
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL,
    INDEX idx_token   (token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Wallet transactions (idempotency + audit)
CREATE TABLE IF NOT EXISTS cmswager_transactions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    external_txn_id VARCHAR(128) NOT NULL,
    action          ENUM('debit','credit','rollback') NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    balance_before  DECIMAL(15,2) NOT NULL,
    balance_after   DECIMAL(15,2) NOT NULL,
    created_at      DATETIME NOT NULL,
    UNIQUE  KEY uk_txn   (external_txn_id),
    INDEX   idx_user_id  (user_id),
    INDEX   idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
