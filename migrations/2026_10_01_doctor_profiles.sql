CREATE TABLE IF NOT EXISTS doctors (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    display_name VARCHAR(190) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    specialty VARCHAR(190) NOT NULL DEFAULT '',
    short_bio TEXT DEFAULT NULL,
    qualifications TEXT DEFAULT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_doctor_slug (slug),
    UNIQUE KEY uq_doctor_user (user_id),
    KEY idx_doctor_published (is_published, display_name),
    CONSTRAINT fk_doctor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS video_doctors (
    video_id INT NOT NULL,
    doctor_id INT NOT NULL,
    contribution VARCHAR(190) NOT NULL DEFAULT '',
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (video_id, doctor_id),
    KEY idx_video_doctor (doctor_id, video_id),
    CONSTRAINT fk_video_doctor_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    CONSTRAINT fk_video_doctor_profile FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
