-- Table structure for table `web_settings`
CREATE TABLE IF NOT EXISTS `web_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_logo` varchar(255) DEFAULT 'https://tanitbet216.com/tanitbet.png',
  `telegram_link` varchar(255) DEFAULT '#',
  `instagram_link` varchar(255) DEFAULT '#',
  `facebook_link` varchar(255) DEFAULT '#',
  `telegram_icon` varchar(255) DEFAULT 'fa fa-paper-plane',
  `instagram_icon` varchar(255) DEFAULT 'fa fa-instagram',
  `facebook_icon` varchar(255) DEFAULT 'fa fa-facebook',
  `country_code` varchar(10) DEFAULT '+91',
  `country_name` varchar(50) DEFAULT 'IN',
  `is_login_on` tinyint(1) DEFAULT 1,
  `is_signup_on` tinyint(1) DEFAULT 1,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `web_settings`
INSERT INTO `web_settings` (`id`, `site_logo`, `telegram_link`, `instagram_link`, `facebook_link`, `telegram_icon`, `instagram_icon`, `facebook_icon`, `country_code`, `country_name`, `is_login_on`, `is_signup_on`) VALUES
(1, 'https://tanitbet216.com/tanitbet.png', '#', '#', '#', 'fa fa-paper-plane', 'fa fa-instagram', 'fa fa-facebook', '+91', 'IN', 1, 1);
