CREATE TABLE IF NOT EXISTS `surgeon_services` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(190) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_surgeon_service_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `surgeon_requests`
  ADD COLUMN IF NOT EXISTS `service_kind` VARCHAR(30) DEFAULT NULL AFTER `request_id`,
  ADD COLUMN IF NOT EXISTS `surgeon_service_id` INT(11) DEFAULT NULL AFTER `service_kind`,
  ADD COLUMN IF NOT EXISTS `service_name_snapshot` VARCHAR(190) DEFAULT NULL AFTER `surgeon_service_id`,
  ADD COLUMN IF NOT EXISTS `implant_package` VARCHAR(50) DEFAULT NULL AFTER `service_name_snapshot`,
  ADD COLUMN IF NOT EXISTS `implant_count` INT(11) DEFAULT NULL AFTER `implant_package`,
  ADD COLUMN IF NOT EXISTS `implant_type_id` INT(11) DEFAULT NULL AFTER `implant_count`,
  ADD COLUMN IF NOT EXISTS `implant_type_name_snapshot` VARCHAR(190) DEFAULT NULL AFTER `implant_type_id`,
  ADD COLUMN IF NOT EXISTS `doctor_fee_per_implant` DECIMAL(10,2) DEFAULT NULL AFTER `implant_type_name_snapshot`,
  ADD COLUMN IF NOT EXISTS `doctor_fee_total` DECIMAL(10,2) DEFAULT NULL AFTER `doctor_fee_per_implant`,
  ADD COLUMN IF NOT EXISTS `implant_unit_price` DECIMAL(10,2) DEFAULT NULL AFTER `doctor_fee_total`,
  ADD COLUMN IF NOT EXISTS `implant_cost_total` DECIMAL(10,2) DEFAULT NULL AFTER `implant_unit_price`,
  ADD COLUMN IF NOT EXISTS `travel_price` DECIMAL(10,2) DEFAULT NULL AFTER `implant_cost_total`,
  ADD COLUMN IF NOT EXISTS `estimated_total` DECIMAL(10,2) DEFAULT NULL AFTER `travel_price`,
  ADD COLUMN IF NOT EXISTS `currency` VARCHAR(3) DEFAULT NULL AFTER `estimated_total`,
  ADD COLUMN IF NOT EXISTS `requires_quote` TINYINT(1) NOT NULL DEFAULT 1 AFTER `currency`,
  ADD INDEX IF NOT EXISTS `idx_surgeon_requests_service` (`surgeon_service_id`),
  ADD INDEX IF NOT EXISTS `idx_surgeon_requests_implant_type` (`implant_type_id`);

