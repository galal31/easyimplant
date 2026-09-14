ALTER TABLE `surgeon_governorate_prices`
  ADD COLUMN IF NOT EXISTS `is_available` TINYINT(1) NOT NULL DEFAULT 1 AFTER `price`;

