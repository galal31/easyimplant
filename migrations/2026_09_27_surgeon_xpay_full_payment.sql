-- Full online payment for surgeon requests.
-- Existing request statuses, payments, receipts, and account adjustments remain unchanged.

ALTER TABLE `surgeon_requests`
  ADD COLUMN IF NOT EXISTS `total_price` decimal(10,2) DEFAULT NULL AFTER `estimated_total`,
  ADD COLUMN IF NOT EXISTS `price_confirmed_at` datetime DEFAULT NULL AFTER `total_price`,
  ADD COLUMN IF NOT EXISTS `price_confirmed_by` int(11) DEFAULT NULL AFTER `price_confirmed_at`,
  ADD INDEX IF NOT EXISTS `idx_surgeon_requests_price_confirmed_by` (`price_confirmed_by`);

UPDATE `surgeon_requests`
SET `total_price` = `estimated_total`
WHERE `requires_quote` = 0
  AND `estimated_total` > 0
  AND `total_price` IS NULL;

ALTER TABLE `surgeon_requests`
  ADD CONSTRAINT `fk_surgeon_requests_price_confirmed_by`
    FOREIGN KEY (`price_confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
