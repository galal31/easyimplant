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
          AND TABLE_NAME IN ('surgical_guide_free_progress', 'surgical_guide_free_progress_ledger')")->fetchColumn();

    $requiredColumns = [
        'free_rule_cycle_id',
        'free_implant_every_used',
        'free_progress_before',
        'free_progress_after',
        'free_implant_every_after',
        'free_state_version_used',
        'free_rule_path',
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

    if ($tableCount !== 2 || (int) $columnStmt->fetchColumn() !== count($requiredColumns)) {
        throw new RuntimeException('Surgical guide pricing schema is out of date. Run the pricing migrations.');
    }

    $ensured = true;
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

    if ($clinicFirstEgp === null || $adminFirstEgp === null || $additionalPriceEgp === null || $freeEvery < 1) {
        throw new RuntimeException('Surgical guide pricing settings are incomplete.');
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
        'free_rule_cycle_id' => null,
        'admin_print_fee' => 0.0,
    ];
    $pricing['version'] = hash('sha256', implode('|', [
        number_format($pricing['clinic_print_first_implant_price'], 2, '.', ''),
        number_format($pricing['admin_print_first_implant_price'], 2, '.', ''),
        number_format($pricing['additional_implant_price'], 2, '.', ''),
        $pricing['free_implant_every'],
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

function getClinicFreeImplantState(PDO $pdo, int $clinicId, int $defaultFreeEvery, bool $forUpdate = false): array
{
    ensureSurgicalGuideFreeRuleSchema($pdo);
    if ($clinicId < 1 || $defaultFreeEvery < 1) {
        throw new InvalidArgumentException('A valid clinic and free-implant interval are required.');
    }

    $insert = $pdo->prepare("INSERT IGNORE INTO surgical_guide_free_progress
        (clinic_id, active_free_implant_every, progress_implants, reserved_implants, state_version)
        VALUES (:clinic_id, :free_every, 0, 0, 1)");
    $insert->execute([':clinic_id' => $clinicId, ':free_every' => $defaultFreeEvery]);

    $sql = "SELECT clinic_id, active_free_implant_every, progress_implants, reserved_implants, state_version, created_at, updated_at
        FROM surgical_guide_free_progress WHERE clinic_id = :clinic_id";
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':clinic_id' => $clinicId]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$state) {
        throw new RuntimeException('Could not initialize the clinic free-implant progress.');
    }

    if (
        (int) $state['active_free_implant_every'] !== $defaultFreeEvery
        && (int) $state['progress_implants'] === 0
        && (int) $state['reserved_implants'] === 0
    ) {
        $adoptDefault = $pdo->prepare("UPDATE surgical_guide_free_progress AS progress
            SET active_free_implant_every = :free_every,
                state_version = state_version + 1
            WHERE clinic_id = :clinic_id
              AND progress_implants = 0
              AND reserved_implants = 0
              AND NOT EXISTS (
                SELECT 1 FROM surgical_guide_free_progress_ledger AS ledger
                WHERE ledger.clinic_id = progress.clinic_id
                  AND ledger.status IN ('reserved', 'confirmed')
              )");
        $adoptDefault->execute([':free_every' => $defaultFreeEvery, ':clinic_id' => $clinicId]);
        if ($adoptDefault->rowCount() === 1) {
            $stmt->execute([':clinic_id' => $clinicId]);
            $state = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    foreach (['clinic_id', 'active_free_implant_every', 'progress_implants', 'reserved_implants', 'state_version'] as $key) {
        $state[$key] = (int) $state[$key];
    }
    if ($state['active_free_implant_every'] < 1 || $state['progress_implants'] >= $state['active_free_implant_every']) {
        throw new RuntimeException('The clinic free-implant progress is invalid.');
    }
    $state['next_default_free_implant_every'] = $defaultFreeEvery;
    return $state;
}

function getClinicSurgicalGuideQuote(PDO $pdo, int $clinicId, array $pricing, bool $forUpdate = false): array
{
    $state = getClinicFreeImplantState($pdo, $clinicId, (int) $pricing['free_implant_every'], $forUpdate);
    $quote = $pricing;
    $quote['global_pricing_version'] = $pricing['version'];
    $quote['clinic_free_implant_every'] = $state['active_free_implant_every'];
    $quote['clinic_free_progress'] = $state['progress_implants'];
    $quote['clinic_reserved_implants'] = $state['reserved_implants'];
    $quote['clinic_free_state_version'] = $state['state_version'];
    $quote['next_free_implant_every'] = (int) $pricing['free_implant_every'];
    $quote['version'] = hash('sha256', implode('|', [
        $pricing['version'],
        $clinicId,
        $state['active_free_implant_every'],
        $state['progress_implants'],
        $state['reserved_implants'],
        $state['state_version'],
        $pricing['free_implant_every'],
    ]));
    return $quote;
}

function calculateClinicFreeImplantAllocation(
    int $totalImplants,
    int $progressBefore,
    int $activeFreeEvery,
    int $nextDefaultFreeEvery
): array {
    if ($totalImplants < 0 || $activeFreeEvery < 1 || $nextDefaultFreeEvery < 1) {
        throw new InvalidArgumentException('Invalid free-implant allocation inputs.');
    }
    if ($progressBefore < 0 || $progressBefore >= $activeFreeEvery) {
        throw new InvalidArgumentException('Free-implant progress must be inside the active cycle.');
    }

    $remaining = $totalImplants;
    $progress = $progressBefore;
    $activeEvery = $activeFreeEvery;
    $freeImplants = 0;
    $path = [];

    while ($remaining > 0) {
        $segmentBefore = $progress;
        $segmentEvery = $activeEvery;
        $take = min($remaining, $segmentEvery - $segmentBefore);
        $progress += $take;
        $remaining -= $take;
        $earned = 0;

        if ($progress === $segmentEvery) {
            $earned = 1;
            $freeImplants++;
            $progress = 0;
            $activeEvery = $nextDefaultFreeEvery;
        }

        $path[] = [
            'free_implant_every' => $segmentEvery,
            'progress_before' => $segmentBefore,
            'implants' => $take,
            'progress_after' => $progress,
            'free_implants' => $earned,
        ];
    }

    return [
        'free_implants' => $freeImplants,
        'progress_before' => $progressBefore,
        'progress_after' => $progress,
        'active_free_implant_every_before' => $activeFreeEvery,
        'active_free_implant_every_after' => $activeEvery,
        'next_default_free_implant_every' => $nextDefaultFreeEvery,
        'rule_path' => $path,
    ];
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

    $activeFreeEvery = isset($pricing['clinic_free_implant_every'])
        ? (int) $pricing['clinic_free_implant_every']
        : $freeEvery;
    $progressBefore = isset($pricing['clinic_free_progress'])
        ? (int) $pricing['clinic_free_progress']
        : ($previousImplants % $activeFreeEvery);
    $nextDefaultFreeEvery = isset($pricing['next_free_implant_every'])
        ? (int) $pricing['next_free_implant_every']
        : $freeEvery;
    $allocation = calculateClinicFreeImplantAllocation(
        $totalImplants,
        $progressBefore,
        $activeFreeEvery,
        $nextDefaultFreeEvery
    );
    $freeImplants = $allocation['free_implants'];
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
        'free_progress_before' => $allocation['progress_before'],
        'free_progress_after' => $allocation['progress_after'],
        'free_implant_every_before' => $allocation['active_free_implant_every_before'],
        'free_implant_every_after' => $allocation['active_free_implant_every_after'],
        'next_default_free_implant_every' => $allocation['next_default_free_implant_every'],
        'free_rule_path' => $allocation['rule_path'],
    ];
}

function reserveClinicFreeImplantProgress(
    PDO $pdo,
    int $clinicId,
    int $requestId,
    array $lockedState,
    array $priceSummary
): int {
    $currentVersion = (int) $lockedState['state_version'];
    $nextVersion = $currentVersion + 1;
    $totalImplants = (int) $priceSummary['total_implants'];

    $update = $pdo->prepare("UPDATE surgical_guide_free_progress
        SET active_free_implant_every = :active_after,
            progress_implants = :progress_after,
            reserved_implants = reserved_implants + :total_implants,
            state_version = :next_version
        WHERE clinic_id = :clinic_id AND state_version = :current_version");
    $update->execute([
        ':active_after' => (int) $priceSummary['free_implant_every_after'],
        ':progress_after' => (int) $priceSummary['free_progress_after'],
        ':total_implants' => $totalImplants,
        ':next_version' => $nextVersion,
        ':clinic_id' => $clinicId,
        ':current_version' => $currentVersion,
    ]);
    if ($update->rowCount() !== 1) {
        throw new DomainException('Free-implant progress changed while this request was submitted. Please refresh and review the price.');
    }

    $ledger = $pdo->prepare("INSERT INTO surgical_guide_free_progress_ledger (
            clinic_id, request_id, status, total_implants, free_implants,
            active_free_implant_every_before, progress_before,
            active_free_implant_every_after, progress_after,
            next_default_free_implant_every, state_version_after, rule_path
        ) VALUES (
            :clinic_id, :request_id, 'reserved', :total_implants, :free_implants,
            :active_before, :progress_before, :active_after, :progress_after,
            :next_default, :state_version_after, :rule_path
        )");
    $ledger->execute([
        ':clinic_id' => $clinicId,
        ':request_id' => $requestId,
        ':total_implants' => $totalImplants,
        ':free_implants' => (int) $priceSummary['free_implants'],
        ':active_before' => (int) $priceSummary['free_implant_every_before'],
        ':progress_before' => (int) $priceSummary['free_progress_before'],
        ':active_after' => (int) $priceSummary['free_implant_every_after'],
        ':progress_after' => (int) $priceSummary['free_progress_after'],
        ':next_default' => (int) $priceSummary['next_default_free_implant_every'],
        ':state_version_after' => $nextVersion,
        ':rule_path' => json_encode($priceSummary['free_rule_path'], JSON_THROW_ON_ERROR),
    ]);
    return $nextVersion;
}

function confirmClinicFreeImplantReservation(PDO $pdo, int $requestId): bool
{
    ensureSurgicalGuideFreeRuleSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM surgical_guide_free_progress_ledger WHERE request_id = :request_id FOR UPDATE");
    $stmt->execute([':request_id' => $requestId]);
    $ledger = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ledger || $ledger['status'] === 'confirmed') return false;
    if ($ledger['status'] === 'released') {
        throw new DomainException('The free-implant reservation for this request was already released.');
    }

    $state = $pdo->prepare("SELECT state_version FROM surgical_guide_free_progress WHERE clinic_id = :clinic_id FOR UPDATE");
    $state->execute([':clinic_id' => (int) $ledger['clinic_id']]);
    if (!$state->fetchColumn()) {
        throw new RuntimeException('Clinic free-implant progress was not found.');
    }

    $updateState = $pdo->prepare("UPDATE surgical_guide_free_progress
        SET reserved_implants = GREATEST(0, reserved_implants - :implants),
            state_version = state_version + 1
        WHERE clinic_id = :clinic_id");
    $updateState->execute([
        ':implants' => (int) $ledger['total_implants'],
        ':clinic_id' => (int) $ledger['clinic_id'],
    ]);
    $updateLedger = $pdo->prepare("UPDATE surgical_guide_free_progress_ledger
        SET status = 'confirmed', resolved_at = NOW()
        WHERE id = :id AND status = 'reserved'");
    $updateLedger->execute([':id' => (int) $ledger['id']]);
    return $updateLedger->rowCount() === 1;
}

function releaseClinicFreeImplantReservation(PDO $pdo, int $requestId): bool
{
    ensureSurgicalGuideFreeRuleSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM surgical_guide_free_progress_ledger WHERE request_id = :request_id FOR UPDATE");
    $stmt->execute([':request_id' => $requestId]);
    $ledger = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ledger || $ledger['status'] === 'released') return false;
    if ($ledger['status'] === 'confirmed') {
        throw new DomainException('A confirmed free-implant reservation cannot be released.');
    }

    $stateStmt = $pdo->prepare("SELECT * FROM surgical_guide_free_progress WHERE clinic_id = :clinic_id FOR UPDATE");
    $stateStmt->execute([':clinic_id' => (int) $ledger['clinic_id']]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC);
    if (!$state) {
        throw new RuntimeException('Clinic free-implant progress was not found.');
    }

    $canRewind = (int) $state['state_version'] === (int) $ledger['state_version_after'];
    $sql = "UPDATE surgical_guide_free_progress
        SET reserved_implants = GREATEST(0, reserved_implants - :implants),
            state_version = state_version + 1";
    $params = [
        ':implants' => (int) $ledger['total_implants'],
        ':clinic_id' => (int) $ledger['clinic_id'],
    ];
    if ($canRewind) {
        $sql .= ", active_free_implant_every = :active_before, progress_implants = :progress_before";
        $params[':active_before'] = (int) $ledger['active_free_implant_every_before'];
        $params[':progress_before'] = (int) $ledger['progress_before'];
    }
    $sql .= ' WHERE clinic_id = :clinic_id';
    $updateState = $pdo->prepare($sql);
    $updateState->execute($params);

    $updateLedger = $pdo->prepare("UPDATE surgical_guide_free_progress_ledger
        SET status = 'released', resolved_at = NOW()
        WHERE id = :id AND status = 'reserved'");
    $updateLedger->execute([':id' => (int) $ledger['id']]);
    return $updateLedger->rowCount() === 1;
}

function getClinicFreeImplantLedgerRows(PDO $pdo, int $clinicId): array
{
    ensureSurgicalGuideFreeRuleSchema($pdo);
    $stmt = $pdo->prepare("SELECT l.*, r.status AS request_status, r.created_at
        FROM surgical_guide_free_progress_ledger l
        JOIN requests r ON r.id = l.request_id
        WHERE l.clinic_id = :clinic_id
        ORDER BY l.id DESC");
    $stmt->execute([':clinic_id' => $clinicId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            sgd.free_progress_before,
            sgd.free_progress_after,
            sgd.free_implant_every_after,
            sgd.free_state_version_used,
            sgd.free_rule_path,
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
    $pricing = getSurgicalGuidePricing($pdo);
    return [
        'clinic' => $clinic,
        'summary' => getClinicAccountSummaryFromRows($rows, $manualAdjustments),
        'rows' => $rows,
        'reward_state' => getClinicFreeImplantState($pdo, $clinicId, (int) $pricing['free_implant_every']),
        'reward_ledger' => getClinicFreeImplantLedgerRows($pdo, $clinicId),
        'next_free_implant_every' => (int) $pricing['free_implant_every'],
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
