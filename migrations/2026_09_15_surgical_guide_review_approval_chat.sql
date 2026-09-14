-- Surgical Guide review packages, clinic approval, and manual request chat.
-- This migration does not update existing request rows or payment records.

ALTER TABLE `requests`
  MODIFY COLUMN `status` enum(
    'pending_review',
    'awaiting_clinic_approval',
    'pending_payment',
    'in_progress',
    'completed',
    'rejected'
  ) DEFAULT 'pending_review';

CREATE TABLE IF NOT EXISTS `request_review_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_review_packages_request_latest` (`request_id`,`id`),
  KEY `idx_review_packages_admin` (`admin_id`),
  KEY `idx_review_packages_approved_by` (`approved_by`),
  CONSTRAINT `fk_review_packages_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_packages_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_review_packages_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_review_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `content_type` varchar(100) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_review_files_package` (`package_id`),
  CONSTRAINT `fk_review_files_package` FOREIGN KEY (`package_id`) REFERENCES `request_review_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `sender_role` enum('admin','clinic') NOT NULL,
  `message_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_request_messages_request` (`request_id`,`id`),
  KEY `idx_request_messages_sender` (`sender_id`),
  CONSTRAINT `fk_request_messages_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_request_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
