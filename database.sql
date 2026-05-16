-- Users Table for Hierarchy (Admin -> Master -> Agent -> Player)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','master','agent','player') NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `credit_ref` decimal(15,2) DEFAULT '0.00',
  `balance` decimal(15,2) DEFAULT '0.00',
  `exposure` decimal(15,2) DEFAULT '0.00',
  `rate` decimal(5,2) DEFAULT '100.00',
  `status` enum('active','locked','suspended') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transactions Table for Balance History
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `type` enum('deposit','withdrawal') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Admin User
-- Username: admin
-- Password: admin123
INSERT INTO `users` (`username`, `password`, `role`, `parent_id`, `credit_ref`, `balance`, `exposure`, `rate`, `status`) VALUES
('admin', '0192023a7bbd73250516f069df18b500', 'admin', NULL, 10000000.00, 10000000.00, 0.00, 100.00, 'active')
ON DUPLICATE KEY UPDATE `username`=`username`;
