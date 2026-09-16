-- XPay Hosted Checkout for Surgical Guide requests.
-- Existing manual-receipt records remain unchanged and continue to use payment_source = manual_receipt.

ALTER TABLE `payments`
  MODIFY COLUMN `receipt_file_path` varchar(255) NULL COMMENT 'Path to uploaded receipt image/pdf; null for gateway payments',
  ADD COLUMN IF NOT EXISTS `payment_source` enum('manual_receipt','xpay') NOT NULL DEFAULT 'manual_receipt' AFTER `receipt_file_size`,
  ADD COLUMN IF NOT EXISTS `currency` char(3) NOT NULL DEFAULT 'EGP' AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `provider_session_id` varchar(100) DEFAULT NULL AFTER `currency`,
  ADD COLUMN IF NOT EXISTS `provider_payment_intent_id` varchar(100) DEFAULT NULL AFTER `provider_session_id`,
  ADD COLUMN IF NOT EXISTS `approved_at` datetime DEFAULT NULL AFTER `status`;

CREATE UNIQUE INDEX IF NOT EXISTS `uq_payments_provider_session`
  ON `payments` (`provider_session_id`);

CREATE UNIQUE INDEX IF NOT EXISTS `uq_payments_provider_intent`
  ON `payments` (`provider_payment_intent_id`);

CREATE TABLE IF NOT EXISTS `xpay_checkout_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `idempotency_key` varchar(255) NOT NULL,
  `return_token` char(64) NOT NULL,
  `xpay_session_id` varchar(100) DEFAULT NULL,
  `xpay_payment_intent_id` varchar(100) DEFAULT NULL,
  `xpay_customer_id` varchar(100) DEFAULT NULL,
  `checkout_url` varchar(1000) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'creating',
  `payment_status` varchar(30) NOT NULL DEFAULT 'unpaid',
  `amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'EGP',
  `livemode` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_xpay_idempotency_key` (`idempotency_key`),
  UNIQUE KEY `uq_xpay_return_token` (`return_token`),
  UNIQUE KEY `uq_xpay_session_id` (`xpay_session_id`),
  KEY `idx_xpay_request_latest` (`request_id`,`id`),
  KEY `idx_xpay_user` (`user_id`),
  CONSTRAINT `fk_xpay_sessions_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_xpay_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `xpay_webhook_events` (
  `event_id` varchar(100) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `xpay_session_id` varchar(100) NOT NULL,
  `payload_sha256` char(64) NOT NULL,
  `processing_status` varchar(30) NOT NULL DEFAULT 'received',
  `error_message` varchar(500) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `idx_xpay_events_session` (`xpay_session_id`),
  KEY `idx_xpay_events_status` (`processing_status`,`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
