<?php

function ensureImplantTypesSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS implant_types (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            brand VARCHAR(190) DEFAULT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            price_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            notes TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_implant_type_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        ALTER TABLE implant_types
          ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER brand,
          ADD COLUMN IF NOT EXISTS price_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price
    ");

    $pdo->exec("
        ALTER TABLE surgical_guide_details
          ADD COLUMN IF NOT EXISTS implant_type_id INT(11) DEFAULT NULL AFTER implant_type,
          ADD COLUMN IF NOT EXISTS implant_type_other VARCHAR(190) DEFAULT NULL AFTER implant_type_id
    ");

    $ensured = true;
}

function getActiveImplantTypes(PDO $pdo): array
{
    ensureImplantTypesSchema($pdo);
    $stmt = $pdo->query("
        SELECT id, name, brand, price, price_usd, notes
        FROM implant_types
        WHERE is_active = 1
        ORDER BY name ASC
    ");

    return $stmt->fetchAll();
}

function getAllImplantTypes(PDO $pdo): array
{
    ensureImplantTypesSchema($pdo);
    $stmt = $pdo->query("
        SELECT it.*,
            COUNT(sgd.id) AS request_count,
            COALESCE(SUM(sgd.total_implants), 0) AS implant_count
        FROM implant_types it
        LEFT JOIN surgical_guide_details sgd ON sgd.implant_type_id = it.id
        GROUP BY it.id
        ORDER BY it.is_active DESC, it.name ASC
    ");

    return $stmt->fetchAll();
}

function getImplantTypeById(PDO $pdo, int $implantTypeId): ?array
{
    ensureImplantTypesSchema($pdo);
    $stmt = $pdo->prepare("SELECT id, name, brand, price, price_usd, notes, is_active FROM implant_types WHERE id = :id");
    $stmt->execute([':id' => $implantTypeId]);
    $implantType = $stmt->fetch();

    return $implantType ?: null;
}

function getImplantTypeUsage(PDO $pdo, ?int $clinicId = null): array
{
    ensureImplantTypesSchema($pdo);

    $where = "r.service_type = 'surgical_guide'";
    $params = [];

    if ($clinicId !== null) {
        $where .= " AND r.user_id = :clinic_id";
        $params[':clinic_id'] = $clinicId;
    }

    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN sgd.implant_type_id IS NULL THEN CONCAT('Other: ', COALESCE(NULLIF(sgd.implant_type_other, ''), sgd.implant_type))
                ELSE COALESCE(it.name, sgd.implant_type)
            END AS implant_type_name,
            CASE WHEN sgd.implant_type_id IS NULL THEN 1 ELSE 0 END AS is_other,
            COUNT(*) AS request_count,
            COALESCE(SUM(sgd.total_implants), 0) AS implant_count,
            COALESCE(SUM(sgd.total_price), 0) AS revenue
        FROM surgical_guide_details sgd
        JOIN requests r ON r.id = sgd.request_id
        LEFT JOIN implant_types it ON it.id = sgd.implant_type_id
        WHERE {$where}
        GROUP BY implant_type_name, is_other
        ORDER BY implant_count DESC, request_count DESC, implant_type_name ASC
    ");
    $stmt->execute($params);

    return $stmt->fetchAll();
}
