<?php
// includes/surgical_guide_pricing.php
// Central pricing, clinic account, and guide ledger helpers.

if (!defined('GUIDE_UPPER_REGIONS')) {
    define('GUIDE_UPPER_REGIONS', ['upper_anterior', 'upper_right_posterior', 'upper_left_posterior']);
}
if (!defined('GUIDE_LOWER_REGIONS')) {
    define('GUIDE_LOWER_REGIONS', ['lower_anterior', 'lower_right_posterior', 'lower_left_posterior']);
}
if (!defined('GUIDE_REGION_LABELS')) {
    define('GUIDE_REGION_LABELS', [
        'upper_anterior' => 'Upper Anterior',
        'upper_right_posterior' => 'Upper Right Posterior',
        'upper_left_posterior' => 'Upper Left Posterior',
        'lower_anterior' => 'Lower Anterior',
        'lower_right_posterior' => 'Lower Right Posterior',
        'lower_left_posterior' => 'Lower Left Posterior',
    ]);
}
if (!defined('GUIDE_DEFAULT_FREE_IMPLANT_EVERY')) {
    define('GUIDE_DEFAULT_FREE_IMPLANT_EVERY', 12);
}

function formatMoney($amount): string
{
    return number_format((float) $amount, 2) . ' EGP';
}

function formatCurrencyMoney($amount, string $currency): string
{
    return number_format((float) $amount, 2) . ' ' . strtoupper($currency);
}

function getPaymentStatusLabel($status): string
{
    return match ($status) {
        'pending_verification' => 'Pending Verification',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Not Uploaded',
    };
}

function ensureSurgicalGuidePricingSettings(PDO $pdo): void
{
    $defaults = [
        'clinic_print_first_implant_price' => 1300,
        'admin_print_first_implant_price' => 1700,
        'additional_implant_price' => 300,
        'clinic_print_first_implant_price_egp' => 1300,
        'clinic_print_first_implant_price_usd' => 0,
        'admin_print_first_implant_price_egp' => 1700,
        'admin_print_first_implant_price_usd' => 0,
        'additional_implant_price_egp' => 300,
        'additional_implant_price_usd' => 0,
        'free_implant_every' => GUIDE_DEFAULT_FREE_IMPLANT_EVERY,
        // Backward compatibility keys. They are no longer used as an extra print fee.
        'first_implant_price' => 1300,
        'admin_print_fee' => 0,
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO surgical_guide_pricing_settings (setting_key, setting_value) VALUES (:key, :value)");
    foreach ($defaults as $key => $value) {
        $stmt->execute([':key' => $key, ':value' => $value]);
    }
}

function ensureSurgicalGuideFreeRuleSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;

    $tableCount = (int) $pdo->query("SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'surgical_guide_free_rule_cycles'")->fetchColumn();

    $requiredColumns = [
        'free_rule_cycle_id',
        'free_implant_every_used',
        'first_implant_price_used',
        'additional_implant_price_used',
    ];
    $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
    $columnStmt = $pdo->prepare("SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'surgical_guide_details'
          AND COLUMN_NAME IN ({$placeholders})");
    $columnStmt->execute($requiredColumns);

    if ($tableCount !== 1 || (int) $columnStmt->fetchColumn() !== count($requiredColumns)) {
        throw new RuntimeException('Surgical guide pricing schema is out of date. Run the pricing migrations.');
    }

    $ensured = true;
}

function createSurgicalGuideFreeRuleCycle(PDO $pdo, int $freeEvery, ?int $adminId = null): int
{
    ensureSurgicalGuideFreeRuleSchema($pdo);
    $stmt = $pdo->prepare("INSERT INTO surgical_guide_free_rule_cycles (free_implant_every, created_by)
        VALUES (:free_every, :created_by)");
    $stmt->execute([
        ':free_every' => max(1, $freeEvery),
        ':created_by' => $adminId,
    ]);
    $cycleId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO surgical_guide_pricing_settings (setting_key, setting_value)
        VALUES ('active_free_rule_cycle_id', :cycle_id)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':cycle_id' => $cycleId]);
    return $cycleId;
}

function getSurgicalGuidePricing(PDO $pdo): array
{
    ensureSurgicalGuideFreeRuleSchema($pdo);
    $rows = $pdo->query("SELECT setting_key, setting_value FROM surgical_guide_pricing_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

    $clinicFirstEgp = $rows['clinic_print_first_implant_price_egp'] ?? $rows['clinic_print_first_implant_price'] ?? $rows['first_implant_price'] ?? null;
    $adminFirstEgp = $rows['admin_print_first_implant_price_egp'] ?? $rows['admin_print_first_implant_price'] ?? null;
    $additionalPriceEgp = $rows['additional_implant_price_egp'] ?? $rows['additional_implant_price'] ?? null;
    $clinicFirstUsd = $rows['clinic_print_first_implant_price_usd'] ?? 0;
    $adminFirstUsd = $rows['admin_print_first_implant_price_usd'] ?? 0;
    $additionalPriceUsd = $rows['additional_implant_price_usd'] ?? 0;
    $freeEvery = isset($rows['free_implant_every']) ? (int) $rows['free_implant_every'] : 0;
    $activeCycleId = isset($rows['active_free_rule_cycle_id']) ? (int) $rows['active_free_rule_cycle_id'] : 0;

    if ($clinicFirstEgp === null || $adminFirstEgp === null || $additionalPriceEgp === null || $freeEvery < 1 || $activeCycleId < 1) {
        throw new RuntimeException('Surgical guide pricing settings are incomplete.');
    }

    $cycleStmt = $pdo->prepare("SELECT free_implant_every FROM surgical_guide_free_rule_cycles WHERE id = :id");
    $cycleStmt->execute([':id' => $activeCycleId]);
    $cycleFreeEvery = $cycleStmt->fetchColumn();
    if ($cycleFreeEvery === false || (int) $cycleFreeEvery !== $freeEvery) {
        throw new RuntimeException('The active free-implant cycle does not match the current pricing rule.');
    }

    $pricing = [
        'clinic_print_first_implant_price_egp' => (float) $clinicFirstEgp,
        'clinic_print_first_implant_price_usd' => (float) $clinicFirstUsd,
        'admin_print_first_implant_price_egp' => (float) $adminFirstEgp,
        'admin_print_first_implant_price_usd' => (float) $adminFirstUsd,
        'additional_implant_price_egp' => (float) $additionalPriceEgp,
        'additional_implant_price_usd' => (float) $additionalPriceUsd,
        // Until location-based selection is approved, current calculations keep using Egypt prices.
        'clinic_print_first_implant_price' => (float) $clinicFirstEgp,
        'admin_print_first_implant_price' => (float) $adminFirstEgp,
        'additional_implant_price' => (float) $additionalPriceEgp,
        'free_implant_every' => $freeEvery,
        'free_rule_cycle_id' => $activeCycleId,
        'admin_print_fee' => 0.0,
    ];
    $pricing['version'] = hash('sha256', implode('|', [
        number_format($pricing['clinic_print_first_implant_price'], 2, '.', ''),
        number_format($pricing['admin_print_first_implant_price'], 2, '.', ''),
        number_format($pricing['additional_implant_price'], 2, '.', ''),
        $pricing['free_implant_every'],
        $pricing['free_rule_cycle_id'],
    ]));
    // Backward compatibility for old templates, if any remain.
    $pricing['first_implant_price'] = $pricing['clinic_print_first_implant_price'];
    return $pricing;
}

function normalizeGuideImplantCounts(array $source): array
{
    $result = [];
    foreach (array_merge(GUIDE_UPPER_REGIONS, GUIDE_LOWER_REGIONS) as $region) {
        $value = filter_var($source[$region] ?? 0, FILTER_VALIDATE_INT);
        $result[$region] = max(0, (int) ($value ?: 0));
    }
    return $result;
}

function getClinicCompletedGuideImplants(PDO $pdo, int $clinicId, int $freeRuleCycleId): int
{
    if ($freeRuleCycleId < 1) {
        throw new InvalidArgumentException('A valid free-implant cycle is required.');
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(sgd.total_implants), 0)
        FROM requests r
        JOIN surgical_guide_details sgd ON sgd.request_id = r.id
        WHERE r.user_id = :clinic_id
          AND r.service_type = 'surgical_guide'
          AND r.status = 'completed'
          AND sgd.free_rule_cycle_id = :cycle_id");
    $stmt->execute([
        ':clinic_id' => $clinicId,
        ':cycle_id' => $freeRuleCycleId,
    ]);
    return (int) $stmt->fetchColumn();
}

function calculateArchSubtotal(int $count, float $firstPrice, float $additionalPrice): float
{
    if ($count <= 0) return 0.0;
    return $firstPrice + max(0, $count - 1) * $additionalPrice;
}

function calculateSurgicalGuidePrice(array $implantCounts, string $deliveryMethod, int $previousImplants, array $pricing): array
{
    $upper = 0;
    foreach (GUIDE_UPPER_REGIONS as $region) $upper += (int) ($implantCounts[$region] ?? 0);
    $lower = 0;
    foreach (GUIDE_LOWER_REGIONS as $region) $lower += (int) ($implantCounts[$region] ?? 0);

    $firstPriceKey = $deliveryMethod === 'admin_print'
        ? 'admin_print_first_implant_price'
        : 'clinic_print_first_implant_price';
    if (!isset($pricing[$firstPriceKey], $pricing['additional_implant_price'], $pricing['free_implant_every'])) {
        throw new InvalidArgumentException('Complete pricing settings are required.');
    }
    $firstPrice = (float) $pricing[$firstPriceKey];
    $additionalPrice = (float) $pricing['additional_implant_price'];
    $freeEvery = (int) $pricing['free_implant_every'];
    if ($firstPrice < 0 || $additionalPrice < 0 || $freeEvery < 1) {
        throw new InvalidArgumentException('Pricing settings contain invalid values.');
    }

    $totalImplants = $upper + $lower;
    $upperSubtotal = calculateArchSubtotal($upper, $firstPrice, $additionalPrice);
    $lowerSubtotal = calculateArchSubtotal($lower, $firstPrice, $additionalPrice);

    $freeImplants = max(0, intdiv($previousImplants + $totalImplants, $freeEvery) - intdiv($previousImplants, $freeEvery));
    $discount = $freeImplants * $additionalPrice;
    $totalPrice = max(0, $upperSubtotal + $lowerSubtotal - $discount);

    return [
        'first_implant_price_used' => $firstPrice,
        'additional_implant_price_used' => $additionalPrice,
        'upper_implants' => $upper,
        'lower_implants' => $lower,
        'total_implants' => $totalImplants,
        'free_implants' => $freeImplants,
        'paid_implants' => max(0, $totalImplants - $freeImplants),
        'upper_subtotal' => $upperSubtotal,
        'lower_subtotal' => $lowerSubtotal,
        'print_fee' => 0,
        'discount_amount' => $discount,
        'total_price' => $totalPrice,
    ];
}

function sumApprovedPaymentsByRequest(PDO $pdo): array
{
    $rows = $pdo->query("SELECT request_id, COALESCE(SUM(amount), 0) AS approved_amount FROM payments WHERE status = 'approved' GROUP BY request_id")->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) $map[(int) $row['request_id']] = (float) $row['approved_amount'];
    return $map;
}

function getManualAdjustmentSum(PDO $pdo, int $clinicId): float
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM clinic_account_adjustments WHERE clinic_id = :clinic_id");
    $stmt->execute([':clinic_id' => $clinicId]);
    return (float) $stmt->fetchColumn();
}

function getClinicAccountSummaryFromRows(array $rows, float $manualAdjustments = 0): array
{
    $summary = [
        'guide_requests' => count($rows),
        'total_implants' => 0,
        'eligible_implants' => 0,
        'free_implants' => 0,
        'total_price' => 0.0,
        'approved_paid' => 0.0,
        'balance_due' => 0.0,
        'discount_amount' => 0.0,
        'print_fees' => 0.0,
        'guided_kit_rental_requests' => 0,
        'guided_kit_rental_total' => 0.0,
        'manual_adjustments' => $manualAdjustments,
        'average_implants' => 0.0,
    ];

    foreach ($rows as $row) {
        $summary['total_implants'] += (int) $row['total_implants'];
        if (($row['request_status'] ?? '') === 'completed') {
            $summary['eligible_implants'] += (int) $row['total_implants'];
        }
        $summary['free_implants'] += (int) $row['free_implants'];
        $summary['total_price'] += (float) $row['total_price'];
        $summary['approved_paid'] += (float) ($row['approved_amount'] ?? 0);
        $summary['discount_amount'] += (float) $row['discount_amount'];
        $summary['print_fees'] += (float) ($row['print_fee'] ?? 0);
        if (($row['guided_kit_source'] ?? '') === 'rental') {
            $summary['guided_kit_rental_requests']++;
            $summary['guided_kit_rental_total'] += (float) ($row['guided_kit_rental_price'] ?? 0);
        }
    }

    $summary['balance_due'] = $summary['total_price'] - $summary['approved_paid'] + $manualAdjustments;
    $summary['average_implants'] = $summary['guide_requests'] ? $summary['total_implants'] / $summary['guide_requests'] : 0;

    return $summary;
}

function getClinicGuideRows(PDO $pdo, int $clinicId): array
{
    $stmt = $pdo->prepare("SELECT
            r.id AS request_id,
            r.status AS request_status,
            r.created_at,
            sgd.implant_type AS implant_type_name,
            CASE WHEN sgd.implant_type_id IS NULL AND sgd.implant_type_other IS NOT NULL THEN 1 ELSE 0 END AS implant_type_is_other,
            sgd.delivery_method,
            sgd.guided_kit_source,
            sgd.guided_kit_name,
            sgd.guided_kit_type,
            sgd.guided_kit_rental_price,
            sgd.upper_implants,
            sgd.lower_implants,
            sgd.total_implants,
            sgd.free_implants,
            sgd.free_rule_cycle_id,
            sgd.free_implant_every_used,
            sgd.first_implant_price_used,
            sgd.additional_implant_price_used,
            sgd.discount_amount,
            sgd.print_fee,
            sgd.total_price,
            COALESCE(pay.approved_amount, 0) AS approved_amount,
            latest.status AS latest_payment_status
        FROM requests r
        JOIN surgical_guide_details sgd ON sgd.request_id = r.id
        LEFT JOIN (
            SELECT request_id, SUM(amount) AS approved_amount
            FROM payments
            WHERE status = 'approved'
            GROUP BY request_id
        ) pay ON pay.request_id = r.id
        LEFT JOIN payments latest ON latest.id = (
            SELECT p2.id FROM payments p2 WHERE p2.request_id = r.id ORDER BY p2.uploaded_at DESC, p2.id DESC LIMIT 1
        )
        WHERE r.user_id = :clinic_id
          AND r.service_type = 'surgical_guide'
          AND r.status <> 'rejected'
        ORDER BY r.created_at DESC");
    $stmt->execute([':clinic_id' => $clinicId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFreeImplantLedger(array $rows): array
{
    $completed = array_values(array_filter($rows, fn($r) => ($r['request_status'] ?? '') === 'completed'));
    usort($completed, fn($a, $b) => strtotime($a['created_at']) <=> strtotime($b['created_at']));

    $ledger = [];
    $cumulativeImplants = 0;
    $cumulativeFreeImplants = 0;
    $cycleTotals = [];
    foreach ($completed as $row) {
        $cumulativeImplants += (int) $row['total_implants'];
        $cumulativeFreeImplants += (int) $row['free_implants'];
        $cycleId = (int) ($row['free_rule_cycle_id'] ?? 0);
        if (!isset($cycleTotals[$cycleId])) {
            $cycleTotals[$cycleId] = ['implants' => 0, 'free_implants' => 0];
        }
        $cycleTotals[$cycleId]['implants'] += (int) $row['total_implants'];
        $cycleTotals[$cycleId]['free_implants'] += (int) $row['free_implants'];
        $entry = $row;
        $entry['cumulative_implants'] = $cumulativeImplants;
        $entry['cumulative_free_implants'] = $cumulativeFreeImplants;
        $entry['cycle_cumulative_implants'] = $cycleTotals[$cycleId]['implants'];
        $entry['cycle_cumulative_free_implants'] = $cycleTotals[$cycleId]['free_implants'];
        $ledger[] = $entry;
    }
    return array_reverse($ledger);
}

function getClinicAdjustments(PDO $pdo, int $clinicId): array
{
    $stmt = $pdo->prepare("SELECT a.*, u.full_name AS admin_name
        FROM clinic_account_adjustments a
        LEFT JOIN users u ON u.id = a.admin_id
        WHERE a.clinic_id = :clinic_id
        ORDER BY a.created_at DESC, a.id DESC");
    $stmt->execute([':clinic_id' => $clinicId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClinicAccount(PDO $pdo, int $clinicId): ?array
{
    $stmt = $pdo->prepare("SELECT id, full_name, clinic_name, email, phone, country, governorate, status, created_at FROM users WHERE id = :id AND role = 'clinic'");
    $stmt->execute([':id' => $clinicId]);
    $clinic = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$clinic) return null;

    $rows = getClinicGuideRows($pdo, $clinicId);
    $manualAdjustments = getManualAdjustmentSum($pdo, $clinicId);
    return [
        'clinic' => $clinic,
        'summary' => getClinicAccountSummaryFromRows($rows, $manualAdjustments),
        'rows' => $rows,
        'ledger' => getFreeImplantLedger($rows),
        'adjustments' => getClinicAdjustments($pdo, $clinicId),
    ];
}

function getAllClinicAccountSummaries(PDO $pdo): array
{
    $clinics = $pdo->query("SELECT id, full_name, clinic_name, email, phone, country, governorate, status, created_at FROM users WHERE role = 'clinic' ORDER BY clinic_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $accounts = [];
    foreach ($clinics as $clinic) {
        $rows = getClinicGuideRows($pdo, (int) $clinic['id']);
        $manualAdjustments = getManualAdjustmentSum($pdo, (int) $clinic['id']);
        $accounts[] = [
            'clinic' => $clinic,
            'summary' => getClinicAccountSummaryFromRows($rows, $manualAdjustments),
        ];
    }
    return $accounts;
}
?>
