-- Run on a backed-up database before deploying the surgeon workflow.
CREATE TABLE IF NOT EXISTS surgeons (
 id INT(11) AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(190) NOT NULL,
 phone VARCHAR(40) NOT NULL,
 specialty VARCHAR(190) NOT NULL DEFAULT '',
 notes TEXT NULL,
 is_available TINYINT(1) NOT NULL DEFAULT 1,
 legacy_user_id INT(11) NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE surgeon_requests
 ADD COLUMN IF NOT EXISTS surgeon_id INT(11) NULL,
 ADD COLUMN IF NOT EXISTS surgeon_name_snapshot VARCHAR(190) NULL,
 ADD COLUMN IF NOT EXISTS confirmed_operation_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS performed_at DATETIME NULL,
 ADD COLUMN IF NOT EXISTS completion_note TEXT NULL,
 ADD COLUMN IF NOT EXISTS financial_review_required TINYINT(1) NOT NULL DEFAULT 0,
 ADD INDEX IF NOT EXISTS idx_surgeon_operation (surgeon_id);
ALTER TABLE requests MODIFY status ENUM('pending_review','contacted','awaiting_clinic_approval','pending_payment','in_progress','completed','rejected','cancelled') DEFAULT 'pending_review';
CREATE TABLE IF NOT EXISTS surgeon_assignment_history (
 id INT(11) AUTO_INCREMENT PRIMARY KEY,
 request_id INT(11) NOT NULL,
 surgeon_id INT(11) NOT NULL,
 surgeon_name_snapshot VARCHAR(190) NOT NULL,
 operation_at DATETIME NULL,
 assigned_at DATETIME NULL,
 assigned_by INT(11) NULL,
 INDEX (surgeon_id,request_id),
 FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE RESTRICT,
 FOREIGN KEY (surgeon_id) REFERENCES surgeons(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO surgeons (full_name, phone, is_available, legacy_user_id)
 SELECT full_name, COALESCE(phone,''), status = 'approved', id FROM users WHERE role = 'surgeon';
UPDATE surgeon_requests sr JOIN surgeons s ON s.legacy_user_id = sr.assigned_surgeon_id
 SET sr.surgeon_id = s.id, sr.surgeon_name_snapshot = s.full_name
 WHERE sr.surgeon_id IS NULL;
INSERT INTO surgeon_assignment_history (request_id,surgeon_id,surgeon_name_snapshot)
 SELECT sr.request_id,sr.surgeon_id,sr.surgeon_name_snapshot FROM surgeon_requests sr
 WHERE sr.surgeon_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM surgeon_assignment_history h WHERE h.request_id=sr.request_id);
