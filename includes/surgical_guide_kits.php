<?php

function ensureSurgicalGuideKitsSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS surgical_guide_kit_options (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            rental_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_surgical_guide_kit_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        ALTER TABLE surgical_guide_details
          ADD COLUMN IF NOT EXISTS guided_kit_source ENUM('rental','owned') DEFAULT NULL AFTER implant_type_other,
          ADD COLUMN IF NOT EXISTS guided_kit_option_id INT(11) UNSIGNED DEFAULT NULL AFTER guided_kit_source,
          ADD COLUMN IF NOT EXISTS guided_kit_name VARCHAR(190) DEFAULT NULL AFTER guided_kit_option_id,
          ADD COLUMN IF NOT EXISTS guided_kit_type ENUM('sleeved','sleeveless') DEFAULT NULL AFTER guided_kit_name,
          ADD COLUMN IF NOT EXISTS guided_kit_rental_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER guided_kit_type,
          ADD INDEX IF NOT EXISTS idx_sgd_guided_kit_option (guided_kit_option_id)
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS surgical_guide_kit_files (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id INT(11) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            content_type VARCHAR(100) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sgkf_request (request_id),
            CONSTRAINT fk_sgkf_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ensured = true;
}

function getActiveSurgicalGuideKitOptions(PDO $pdo): array
{
    ensureSurgicalGuideKitsSchema($pdo);
    return $pdo->query("SELECT id, name, rental_price FROM surgical_guide_kit_options WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
}

function getAllSurgicalGuideKitOptions(PDO $pdo): array
{
    ensureSurgicalGuideKitsSchema($pdo);
    return $pdo->query("SELECT id, name, rental_price, is_active, created_at, updated_at FROM surgical_guide_kit_options ORDER BY is_active DESC, name ASC")->fetchAll();
}

function getSurgicalGuideKitOptionById(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    ensureSurgicalGuideKitsSchema($pdo);
    $sql = "SELECT id, name, rental_price, is_active FROM surgical_guide_kit_options WHERE id = :id" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getMinimumGuideOperationDate(): string
{
    $timezone = new DateTimeZone('Africa/Cairo');
    return (new DateTimeImmutable('today', $timezone))->modify('+2 days')->format('Y-m-d');
}

function isValidGuideOperationDate(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Africa/Cairo'));
    return $parsed !== false
        && $parsed->format('Y-m-d') === $date
        && $date >= getMinimumGuideOperationDate();
}

