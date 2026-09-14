ALTER TABLE `users`
  MODIFY COLUMN `country` VARCHAR(100) NOT NULL,
  ADD COLUMN IF NOT EXISTS `governorate` VARCHAR(50) DEFAULT NULL AFTER `country`;

ALTER TABLE `implant_types`
  ADD COLUMN IF NOT EXISTS `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `brand`;

CREATE TABLE IF NOT EXISTS `surgeon_governorate_prices` (
  `governorate_code` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`governorate_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `surgeon_governorate_prices` (`governorate_code`, `price`) VALUES
('cairo', 0.00),
('giza', 0.00),
('alexandria', 0.00),
('dakahlia', 0.00),
('red-sea', 0.00),
('beheira', 0.00),
('fayoum', 0.00),
('gharbia', 0.00),
('ismailia', 0.00),
('menofia', 0.00),
('minya', 0.00),
('qalyubia', 0.00),
('new-valley', 0.00),
('suez', 0.00),
('aswan', 0.00),
('assiut', 0.00),
('beni-suef', 0.00),
('port-said', 0.00),
('damietta', 0.00),
('sharqia', 0.00),
('south-sinai', 0.00),
('kafr-el-sheikh', 0.00),
('matrouh', 0.00),
('luxor', 0.00),
('qena', 0.00),
('north-sinai', 0.00),
('sohag', 0.00);
