CREATE TABLE IF NOT EXISTS `surgical_guide_pricing_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` DECIMAL(10,2) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `surgical_guide_pricing_settings` (`setting_key`, `setting_value`) VALUES
('first_implant_price', 1300.00),
('additional_implant_price', 300.00),
('admin_print_fee', 1700.00),
('free_implant_every', 12.00)
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

ALTER TABLE `surgical_guide_details`
  ADD COLUMN IF NOT EXISTS `upper_anterior_implants` INT(11) NOT NULL DEFAULT 0 AFTER `delivery_method`,
  ADD COLUMN IF NOT EXISTS `upper_right_posterior_implants` INT(11) NOT NULL DEFAULT 0 AFTER `upper_anterior_implants`,
  ADD COLUMN IF NOT EXISTS `upper_left_posterior_implants` INT(11) NOT NULL DEFAULT 0 AFTER `upper_right_posterior_implants`,
  ADD COLUMN IF NOT EXISTS `lower_anterior_implants` INT(11) NOT NULL DEFAULT 0 AFTER `upper_left_posterior_implants`,
  ADD COLUMN IF NOT EXISTS `lower_right_posterior_implants` INT(11) NOT NULL DEFAULT 0 AFTER `lower_anterior_implants`,
  ADD COLUMN IF NOT EXISTS `lower_left_posterior_implants` INT(11) NOT NULL DEFAULT 0 AFTER `lower_right_posterior_implants`,
  ADD COLUMN IF NOT EXISTS `upper_implants` INT(11) NOT NULL DEFAULT 0 AFTER `lower_left_posterior_implants`,
  ADD COLUMN IF NOT EXISTS `lower_implants` INT(11) NOT NULL DEFAULT 0 AFTER `upper_implants`,
  ADD COLUMN IF NOT EXISTS `total_implants` INT(11) NOT NULL DEFAULT 0 AFTER `lower_implants`,
  ADD COLUMN IF NOT EXISTS `free_implants` INT(11) NOT NULL DEFAULT 0 AFTER `total_implants`,
  ADD COLUMN IF NOT EXISTS `paid_implants` INT(11) NOT NULL DEFAULT 0 AFTER `free_implants`,
  ADD COLUMN IF NOT EXISTS `upper_subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `paid_implants`,
  ADD COLUMN IF NOT EXISTS `lower_subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `upper_subtotal`,
  ADD COLUMN IF NOT EXISTS `print_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `lower_subtotal`,
  ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `print_fee`,
  ADD COLUMN IF NOT EXISTS `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `discount_amount`;
