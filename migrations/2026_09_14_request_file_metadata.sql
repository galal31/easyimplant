ALTER TABLE `surgical_guide_details`
  ADD COLUMN IF NOT EXISTS `cbct_original_name` VARCHAR(255) NULL AFTER `cbct_file_path`,
  ADD COLUMN IF NOT EXISTS `cbct_content_type` VARCHAR(100) NULL AFTER `cbct_original_name`,
  ADD COLUMN IF NOT EXISTS `cbct_file_size` BIGINT UNSIGNED NULL AFTER `cbct_content_type`,
  ADD COLUMN IF NOT EXISTS `stl_original_name` VARCHAR(255) NULL AFTER `stl_file_path`,
  ADD COLUMN IF NOT EXISTS `stl_content_type` VARCHAR(100) NULL AFTER `stl_original_name`,
  ADD COLUMN IF NOT EXISTS `stl_file_size` BIGINT UNSIGNED NULL AFTER `stl_content_type`;

ALTER TABLE `request_deliverables`
  ADD COLUMN IF NOT EXISTS `original_name` VARCHAR(255) NULL AFTER `file_path`,
  ADD COLUMN IF NOT EXISTS `content_type` VARCHAR(100) NULL AFTER `original_name`,
  ADD COLUMN IF NOT EXISTS `file_size` BIGINT UNSIGNED NULL AFTER `content_type`;

ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `receipt_original_name` VARCHAR(255) NULL AFTER `receipt_file_path`,
  ADD COLUMN IF NOT EXISTS `receipt_content_type` VARCHAR(100) NULL AFTER `receipt_original_name`,
  ADD COLUMN IF NOT EXISTS `receipt_file_size` BIGINT UNSIGNED NULL AFTER `receipt_content_type`;
