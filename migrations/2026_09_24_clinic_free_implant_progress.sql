CREATE TABLE IF NOT EXISTS `surgical_guide_free_progress` (
  `clinic_id` INT(11) NOT NULL,
  `active_free_implant_every` INT(11) UNSIGNED NOT NULL,
  `progress_implants` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `reserved_implants` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `state_version` BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clinic_id`),
  CONSTRAINT `fk_sg_free_progress_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surgical_guide_free_progress_ledger` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `clinic_id` INT(11) NOT NULL,
  `request_id` INT(11) NOT NULL,
  `status` ENUM('reserved','confirmed','released') NOT NULL DEFAULT 'reserved',
  `total_implants` INT(11) UNSIGNED NOT NULL,
  `free_implants` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `active_free_implant_every_before` INT(11) UNSIGNED NOT NULL,
  `progress_before` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `active_free_implant_every_after` INT(11) UNSIGNED NOT NULL,
  `progress_after` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `next_default_free_implant_every` INT(11) UNSIGNED NOT NULL,
  `state_version_after` BIGINT(20) UNSIGNED NOT NULL,
  `rule_path` JSON DEFAULT NULL,
  `reserved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sg_free_ledger_request` (`request_id`),
  KEY `idx_sg_free_ledger_clinic_status` (`clinic_id`, `status`),
  CONSTRAINT `fk_sg_free_ledger_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sg_free_ledger_request`
    FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `surgical_guide_details`
  ADD COLUMN IF NOT EXISTS `free_progress_before` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `free_implant_every_used`,
  ADD COLUMN IF NOT EXISTS `free_progress_after` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `free_progress_before`,
  ADD COLUMN IF NOT EXISTS `free_implant_every_after` INT(11) UNSIGNED DEFAULT NULL AFTER `free_progress_after`,
  ADD COLUMN IF NOT EXISTS `free_state_version_used` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `free_implant_every_after`,
  ADD COLUMN IF NOT EXISTS `free_rule_path` JSON DEFAULT NULL AFTER `free_state_version_used`;
