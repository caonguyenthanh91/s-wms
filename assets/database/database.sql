-- s-WMS schema
-- This file contains the full database structure required by the current app flows.
-- Import into an empty database or run it after selecting the target database in your SQL client.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `export_temp`;
DROP TABLE IF EXISTS `export_log`;
DROP TABLE IF EXISTS `import_temp`;
DROP TABLE IF EXISTS `shelves`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `log_users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `log_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('Admin','Leader','Manager','Staff') NOT NULL DEFAULT 'Staff',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_log_users_username` (`username`),
  KEY `idx_log_users_role` (`role`),
  KEY `idx_log_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` varchar(50) NOT NULL,
  `product_name` varchar(255) DEFAULT 'noname',
  `unit` varchar(20) DEFAULT 'pcs',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_product_id` (`product_id`),
  KEY `idx_products_product_name` (`product_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shelves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shelf_id` varchar(50) NOT NULL,
  `shelf_name` varchar(50) DEFAULT NULL,
  `level0_val` varchar(20) DEFAULT NULL,
  `level1_val` varchar(20) DEFAULT NULL,
  `level2_val` varchar(20) DEFAULT NULL,
  `level3_val` varchar(20) DEFAULT NULL,
  `level4_val` varchar(20) DEFAULT NULL,
  `status` varchar(10) DEFAULT 'Active',
  `capacity` int(11) NOT NULL DEFAULT '100',
  `current_usage` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shelves_shelf_id` (`shelf_id`),
  KEY `idx_shelves_status` (`status`),
  KEY `idx_shelves_current_usage` (`current_usage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shelf_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inventory_shelf_product` (`shelf_id`,`product_id`),
  KEY `idx_inventory_shelf_id` (`shelf_id`),
  KEY `idx_inventory_product_id` (`product_id`),
  CONSTRAINT `fk_inventory_shelf` FOREIGN KEY (`shelf_id`) REFERENCES `shelves` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `shelf_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `type` varchar(10) NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transactions_product_id` (`product_id`),
  KEY `idx_transactions_shelf_id` (`shelf_id`),
  KEY `idx_transactions_type` (`type`),
  KEY `idx_transactions_created_by` (`created_by`),
  KEY `idx_transactions_created_at` (`created_at`),
  CONSTRAINT `fk_transactions_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_shelf` FOREIGN KEY (`shelf_id`) REFERENCES `shelves` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `import_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pallet_id` varchar(50) NOT NULL,
  `part_no` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_import_temp_pallet_id` (`pallet_id`),
  KEY `idx_import_temp_part_no` (`part_no`),
  KEY `idx_import_temp_status` (`status`),
  KEY `idx_import_temp_created_by` (`created_by`),
  KEY `idx_import_temp_pallet_status` (`pallet_id`,`status`),
  KEY `idx_import_temp_pallet_part` (`pallet_id`,`part_no`),
  KEY `idx_import_temp_status_part` (`status`,`part_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `export_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `command` varchar(50) DEFAULT NULL,
  `case_no` varchar(50) DEFAULT '001',
  `transport_type` varchar(50) DEFAULT 'SEA',
  `for_product` varchar(50) DEFAULT NULL,
  `product_id` varchar(50) NOT NULL,
  `total_qty` int(11) NOT NULL,
  `bucket_qty` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `order_code` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_export_temp_command` (`command`),
  KEY `idx_export_temp_product_id` (`product_id`),
  KEY `idx_export_temp_command_product_id` (`command`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `export_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `command` varchar(50) NOT NULL,
  `case_no` varchar(50) NOT NULL,
  `product_id` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `status` enum('picking','packing','pickup') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_export_log_command_case` (`command`,`case_no`),
  KEY `idx_export_log_product` (`product_id`),
  KEY `idx_export_log_status` (`status`),
  KEY `idx_export_log_created_by` (`created_by`),
  KEY `idx_export_log_created_at` (`created_at`),
  KEY `idx_export_log_command_case_status` (`command`,`case_no`,`status`),
  KEY `idx_export_log_command_case_product_status` (`command`,`case_no`,`product_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `log_users` (`username`, `password`, `full_name`, `role`, `status`) VALUES
('admin', MD5('admin123'), 'Quản Trị Viên', 'Admin', 1),
('manager', MD5('manager123'), 'Trưởng Phòng', 'Manager', 1),
('leader', MD5('leader123'), 'Trưởng Nhóm', 'Leader', 1),
('staff', MD5('staff123'), 'Nhân Viên', 'Staff', 1);

SET FOREIGN_KEY_CHECKS = 1;
