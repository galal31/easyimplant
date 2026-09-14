CREATE TABLE IF NOT EXISTS `surgical_guide_free_rule_cycles` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `free_implant_every` INT(11) UNSIGNED NOT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `surgical_guide_pricing_settings` (`setting_key`, `setting_value`) VALUES
('clinic_print_first_implant_price', 1300.00),
('admin_print_first_implant_price', 1700.00),
('additional_implant_price', 300.00),
('free_implant_every', 12.00),
('first_implant_price', 1300.00),
('admin_print_fee', 0.00)
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

ALTER TABLE `surgical_guide_details`
  ADD COLUMN IF NOT EXISTS `free_rule_cycle_id` INT(11) UNSIGNED DEFAULT NULL AFTER `paid_implants`,
  ADD COLUMN IF NOT EXISTS `free_implant_every_used` INT(11) UNSIGNED DEFAULT NULL AFTER `free_rule_cycle_id`,
  ADD COLUMN IF NOT EXISTS `first_implant_price_used` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `free_implant_every_used`,
  ADD COLUMN IF NOT EXISTS `additional_implant_price_used` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `first_implant_price_used`;

INSERT INTO `surgical_guide_free_rule_cycles` (`free_implant_every`)
SELECT CAST(COALESCE(MAX(`setting_value`), 12) AS UNSIGNED)
FROM `surgical_guide_pricing_settings`
WHERE `setting_key` = 'free_implant_every'
HAVING NOT EXISTS (SELECT 1 FROM `surgical_guide_free_rule_cycles`);

INSERT INTO `surgical_guide_pricing_settings` (`setting_key`, `setting_value`)
SELECT 'active_free_rule_cycle_id', MAX(`id`)
FROM `surgical_guide_free_rule_cycles`
HAVING MAX(`id`) IS NOT NULL
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

UPDATE `surgical_guide_pricing_settings` AS `setting`
SET `setting`.`setting_value` = (SELECT MAX(`id`) FROM `surgical_guide_free_rule_cycles`)
WHERE `setting`.`setting_key` = 'active_free_rule_cycle_id'
  AND NOT EXISTS (
    SELECT 1
    FROM `surgical_guide_free_rule_cycles` AS `cycle`
    WHERE `cycle`.`id` = CAST(`setting`.`setting_value` AS UNSIGNED)
  );

UPDATE `surgical_guide_details` AS `sgd`
JOIN `surgical_guide_pricing_settings` AS `setting`
  ON `setting`.`setting_key` = 'active_free_rule_cycle_id'
JOIN `surgical_guide_free_rule_cycles` AS `cycle`
  ON `cycle`.`id` = CAST(`setting`.`setting_value` AS UNSIGNED)
SET `sgd`.`free_rule_cycle_id` = `cycle`.`id`,
    `sgd`.`free_implant_every_used` = COALESCE(`sgd`.`free_implant_every_used`, `cycle`.`free_implant_every`)
WHERE `sgd`.`free_rule_cycle_id` IS NULL;

UPDATE `surgical_guide_details` AS `sgd`
JOIN `surgical_guide_pricing_settings` AS `clinic_price`
  ON `clinic_price`.`setting_key` = 'clinic_print_first_implant_price'
JOIN `surgical_guide_pricing_settings` AS `admin_price`
  ON `admin_price`.`setting_key` = 'admin_print_first_implant_price'
JOIN `surgical_guide_pricing_settings` AS `additional_price`
  ON `additional_price`.`setting_key` = 'additional_implant_price'
SET `sgd`.`first_implant_price_used` = CASE
      WHEN `sgd`.`delivery_method` = 'admin_print' THEN `admin_price`.`setting_value`
      ELSE `clinic_price`.`setting_value`
    END,
    `sgd`.`additional_implant_price_used` = `additional_price`.`setting_value`
WHERE `sgd`.`first_implant_price_used` = 0
   OR `sgd`.`additional_implant_price_used` = 0;

CREATE TABLE IF NOT EXISTS `surgical_guide_pricing_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) DEFAULT NULL,
  `setting_key` VARCHAR(100) NOT NULL,
  `old_value` DECIMAL(10,2) DEFAULT NULL,
  `new_value` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pricing_log_admin` (`admin_id`),
  KEY `idx_pricing_log_setting` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
