ALTER TABLE `implant_types`
  ADD COLUMN IF NOT EXISTS `price_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `price`;

INSERT IGNORE INTO `surgical_guide_pricing_settings` (`setting_key`, `setting_value`)
SELECT 'clinic_print_first_implant_price_egp', `setting_value`
FROM `surgical_guide_pricing_settings`
WHERE `setting_key` = 'clinic_print_first_implant_price';

INSERT IGNORE INTO `surgical_guide_pricing_settings` (`setting_key`, `setting_value`)
SELECT 'admin_print_first_implant_price_egp', `setting_value`
FROM `surgical_guide_pricing_settings`
WHERE `setting_key` = 'admin_print_first_implant_price';

INSERT IGNORE INTO `surgical_guide_pricing_settings` (`setting_key`, `setting_value`)
SELECT 'additional_implant_price_egp', `setting_value`
FROM `surgical_guide_pricing_settings`
WHERE `setting_key` = 'additional_implant_price';

INSERT IGNORE INTO `surgical_guide_pricing_settings` (`setting_key`, `setting_value`) VALUES
('clinic_print_first_implant_price_usd', 0.00),
('admin_print_first_implant_price_usd', 0.00),
('additional_implant_price_usd', 0.00);

