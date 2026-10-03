-- 发卡网数据库初始化文件
-- 导入前请先选择目标数据库，例如：USE your_database_name;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `products` (
  `id` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `description` text NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` varchar(64) NOT NULL,
  `card_code` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unused',
  `order_id` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `sold_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cards_product_status` (`product_id`,`status`),
  KEY `idx_cards_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(64) NOT NULL,
  `pay_id` varchar(64) NOT NULL,
  `product_id` varchar(64) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `type` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'created',
  `card` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_order_id` (`order_id`),
  UNIQUE KEY `uk_orders_pay_id` (`pay_id`),
  KEY `idx_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `closed_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(64) NOT NULL,
  `closed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_closed_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 默认系统配置（可后续在后台修改）
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('site_name', '简单发卡网', NOW()),
('site_url', 'http://localhost', NOW()),
('vpay_base_url', '', NOW()),
('vpay_key', '', NOW()),
('site_announcement', '', NOW())
ON DUPLICATE KEY UPDATE
`setting_value` = VALUES(`setting_value`),
`updated_at` = VALUES(`updated_at`);

-- 默认管理员：admin / admin123
-- 如需更换密码，请登录后台后修改
INSERT INTO `admins` (`id`, `username`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92g4lX1vYV0YjWnC8t0SYen8Pz0vA6d1n1E8l2YjJ0h5M6x8z5IuK', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
`username` = VALUES(`username`),
`password_hash` = VALUES(`password_hash`),
`status` = 1,
`updated_at` = VALUES(`updated_at`);

SET FOREIGN_KEY_CHECKS = 1;
