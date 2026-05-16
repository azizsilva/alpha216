CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    password_text VARCHAR(255) DEFAULT NULL,
    role ENUM('admin', 'partner', 'super_master', 'master', 'agent', 'player') NOT NULL,
    parent_id INT DEFAULT NULL,
    credit_ref DECIMAL(15, 2) DEFAULT 0.00,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    exposure DECIMAL(15, 2) DEFAULT 0.00,
    rate DECIMAL(5, 2) DEFAULT 100.00,
    status ENUM('active', 'locked', 'suspended') DEFAULT 'active',
    coins DECIMAL(20, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES users(id)
);

-- Default Admin (Password: admin123)
INSERT INTO users (username, password, password_text, role, credit_ref, balance, coins) VALUES
('admin', '0192023a7bbd73250516f069df18b500', 'admin123', 'admin', 10000000.00, 10000000.00, 1000000.00);
