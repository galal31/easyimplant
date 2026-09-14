<?php

function getSurgeonImplantPackages(): array
{
    return [
        'implant_without_prosthetics' => [
            'label' => 'Implant without prosthetics — زراعة بدون تركيبات',
            'doctor_fee_per_implant' => 1500.00,
        ],
        'implant_a_to_z' => [
            'label' => 'Implant from A to Z — زرع من أ إلى ي',
            'doctor_fee_per_implant' => 3300.00,
        ],
    ];
}

function getSurgeonAllOnPackages(): array
{
    return [
        'all_on_4' => [
            'label' => 'All-on-4 A to Z',
            'implant_count' => 4,
        ],
        'all_on_6' => [
            'label' => 'All-on-6 A to Z',
            'implant_count' => 6,
        ],
    ];
}

function getSurgeonImplantPackageLabel(?string $code): string
{
    $packages = getSurgeonImplantPackages();
    return $packages[$code ?? '']['label'] ?? ($code ?: 'Not specified');
}

function ensureSurgeonServicesSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS surgeon_services (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            notes TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_surgeon_service_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS surgeon_all_on_prices (
            package_code VARCHAR(30) NOT NULL PRIMARY KEY,
            team_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO surgeon_all_on_prices (package_code, team_fee)
        VALUES (:package_code, 0.00)
    ");
    foreach (array_keys(getSurgeonAllOnPackages()) as $packageCode) {
        $stmt->execute([':package_code' => $packageCode]);
    }

    $ensured = true;
}

function getSurgeonAllOnPrices(PDO $pdo): array
{
    ensureSurgeonServicesSchema($pdo);
    $stored = $pdo->query("SELECT package_code, team_fee FROM surgeon_all_on_prices")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $prices = [];
    foreach (getSurgeonAllOnPackages() as $code => $package) {
        $prices[$code] = (float) ($stored[$code] ?? 0);
    }
    return $prices;
}

function getActiveSurgeonServices(PDO $pdo): array
{
    ensureSurgeonServicesSchema($pdo);
    return $pdo->query("
        SELECT id, name, notes
        FROM surgeon_services
        WHERE is_active = 1
        ORDER BY name ASC
    ")->fetchAll();
}

function getAllSurgeonServices(PDO $pdo): array
{
    ensureSurgeonServicesSchema($pdo);
    return $pdo->query("
        SELECT ss.*, COUNT(sr.id) AS request_count
        FROM surgeon_services ss
        LEFT JOIN surgeon_requests sr ON sr.surgeon_service_id = ss.id
        GROUP BY ss.id
        ORDER BY ss.is_active DESC, ss.name ASC
    ")->fetchAll();
}

function getSurgeonServiceById(PDO $pdo, int $serviceId): ?array
{
    ensureSurgeonServicesSchema($pdo);
    $stmt = $pdo->prepare("SELECT id, name, notes, is_active FROM surgeon_services WHERE id = :id");
    $stmt->execute([':id' => $serviceId]);
    $service = $stmt->fetch();

    return $service ?: null;
}

function calculateSurgeonImplantPrice(
    int $implantCount,
    float $doctorFeePerImplant,
    float $implantUnitPrice,
    float $travelPrice,
    bool $includeImplantCost = true
): array {
    $doctorFeeTotal = round($implantCount * $doctorFeePerImplant, 2);
    $implantCostTotal = $includeImplantCost
        ? round($implantCount * $implantUnitPrice, 2)
        : 0.00;

    return [
        'doctor_fee_total' => $doctorFeeTotal,
        'implant_cost_total' => $implantCostTotal,
        'estimated_total' => round($doctorFeeTotal + $implantCostTotal + $travelPrice, 2),
    ];
}

function calculateSurgeonAllOnArchPrice(
    string $packageCode,
    float $teamFee,
    float $implantUnitPrice,
    bool $includeImplantCost
): array {
    $packages = getSurgeonAllOnPackages();
    if (!isset($packages[$packageCode])) {
        throw new InvalidArgumentException('Invalid All-on package.');
    }

    $implantCount = (int) $packages[$packageCode]['implant_count'];
    $implantCostTotal = $includeImplantCost
        ? round($implantCount * $implantUnitPrice, 2)
        : 0.00;

    return [
        'implant_count' => $implantCount,
        'implant_cost_total' => $implantCostTotal,
        'subtotal' => round($teamFee + $implantCostTotal, 2),
    ];
}
