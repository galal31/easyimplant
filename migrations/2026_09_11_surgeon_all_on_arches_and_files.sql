CREATE TABLE IF NOT EXISTS `surgeon_all_on_prices` (
  `package_code` VARCHAR(30) NOT NULL PRIMARY KEY,
  `team_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `surgeon_all_on_prices` (`package_code`, `team_fee`) VALUES
  ('all_on_4', 0.00),
  ('all_on_6', 0.00);

ALTER TABLE `surgeon_requests`
  ADD COLUMN IF NOT EXISTS `implant_provider` VARCHAR(30) DEFAULT NULL AFTER `implant_type_name_snapshot`,
  ADD COLUMN IF NOT EXISTS `team_fee_total` DECIMAL(10,2) DEFAULT NULL AFTER `doctor_fee_total`;

CREATE TABLE IF NOT EXISTS `surgeon_request_arches` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT(11) NOT NULL,
  `arch_position` ENUM('upper', 'lower') NOT NULL,
  `package_code` VARCHAR(30) NOT NULL,
  `implant_count` INT(11) NOT NULL,
  `implant_type_id` INT(11) DEFAULT NULL,
  `implant_type_name_snapshot` VARCHAR(190) NOT NULL,
  `implant_provider` ENUM('clinic', 'easy_implant') NOT NULL,
  `implant_unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `implant_cost_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `team_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_surgeon_request_arch` (`request_id`, `arch_position`),
  KEY `idx_surgeon_arch_implant_type` (`implant_type_id`),
  CONSTRAINT `fk_surgeon_arch_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surgeon_request_files` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT(11) NOT NULL,
  `file_category` ENUM('cbct', 'lab') NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `content_type` VARCHAR(190) NOT NULL DEFAULT 'application/octet-stream',
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_surgeon_request_files_request` (`request_id`),
  CONSTRAINT `fk_surgeon_file_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
