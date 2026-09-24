<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';

function assertFreeProgress(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$suffix = bin2hex(random_bytes(5));
$pdo->beginTransaction();

try {
    $insertUser = $pdo->prepare("INSERT INTO users
        (full_name, clinic_name, email, password, phone, country, role, status)
        VALUES ('Reward Test', 'Reward Test', :email, :password, '0000000000', 'egypt', 'clinic', 'approved')");
    $insertUser->execute([
        ':email' => "free-progress-$suffix@example.test",
        ':password' => password_hash('test-only', PASSWORD_DEFAULT),
    ]);
    $clinicId = (int) $pdo->lastInsertId();

    $pricing = [
        'clinic_print_first_implant_price' => 1300.0,
        'admin_print_first_implant_price' => 1700.0,
        'additional_implant_price' => 300.0,
        'free_implant_every' => 5,
        'version' => hash('sha256', 'test-pricing'),
    ];
    $counts = array_fill_keys(array_merge(GUIDE_UPPER_REGIONS, GUIDE_LOWER_REGIONS), 0);

    $state = getClinicFreeImplantState($pdo, $clinicId, 5, true);
    assertFreeProgress($state['active_free_implant_every'] === 5 && $state['progress_implants'] === 0, 'A new clinic must start on the current default rule.');
    $unstartedState = getClinicFreeImplantState($pdo, $clinicId, 7, true);
    assertFreeProgress($unstartedState['active_free_implant_every'] === 7, 'A clinic with no reward activity must adopt the latest default.');
    getClinicFreeImplantState($pdo, $clinicId, 5, true);

    $insertRequest = $pdo->prepare("INSERT INTO requests (user_id, service_type, status)
        VALUES (:clinic_id, 'surgical_guide', 'pending_review')");

    $insertRequest->execute([':clinic_id' => $clinicId]);
    $requestOne = (int) $pdo->lastInsertId();
    $quoteOne = getClinicSurgicalGuideQuote($pdo, $clinicId, $pricing, true);
    $summaryOne = calculateSurgicalGuidePrice(array_merge($counts, ['upper_anterior' => 3]), 'clinic_print', 0, $quoteOne);
    reserveClinicFreeImplantProgress($pdo, $clinicId, $requestOne, ['state_version' => $quoteOne['clinic_free_state_version']], $summaryOne);

    $insertRequest->execute([':clinic_id' => $clinicId]);
    $requestTwo = (int) $pdo->lastInsertId();
    $quoteTwo = getClinicSurgicalGuideQuote($pdo, $clinicId, $pricing, true);
    assertFreeProgress($quoteTwo['clinic_free_progress'] === 3, 'A second request must see the first open reservation.');
    assertFreeProgress(!hash_equals($quoteOne['version'], $quoteTwo['version']), 'A reservation must invalidate an older quote version.');
    $summaryTwo = calculateSurgicalGuidePrice(array_merge($counts, ['upper_anterior' => 2]), 'clinic_print', 3, $quoteTwo);
    assertFreeProgress($summaryTwo['free_implants'] === 1, 'Only the second reservation may close the shared reward boundary.');
    reserveClinicFreeImplantProgress($pdo, $clinicId, $requestTwo, ['state_version' => $quoteTwo['clinic_free_state_version']], $summaryTwo);

    $afterTwo = getClinicFreeImplantState($pdo, $clinicId, 5, true);
    assertFreeProgress($afterTwo['progress_implants'] === 0 && $afterTwo['reserved_implants'] === 5, 'Open requests must be reserved atomically in clinic progress.');

    assertFreeProgress(confirmClinicFreeImplantReservation($pdo, $requestOne), 'The first completion must confirm its reservation.');
    assertFreeProgress(!confirmClinicFreeImplantReservation($pdo, $requestOne), 'Repeated completion must be idempotent.');
    assertFreeProgress(releaseClinicFreeImplantReservation($pdo, $requestTwo), 'Rejecting an open request must release its reservation.');
    assertFreeProgress(!releaseClinicFreeImplantReservation($pdo, $requestTwo), 'Repeated rejection must be idempotent.');

    $afterRelease = getClinicFreeImplantState($pdo, $clinicId, 5, true);
    assertFreeProgress($afterRelease['reserved_implants'] === 0, 'Resolved requests must leave no open reserved implants.');
    assertFreeProgress($afterRelease['progress_implants'] === 0, 'Releasing an older reservation must not reprice later request snapshots.');

    $insertRequest->execute([':clinic_id' => $clinicId]);
    $requestThree = (int) $pdo->lastInsertId();
    $quoteThree = getClinicSurgicalGuideQuote($pdo, $clinicId, $pricing, true);
    $summaryThree = calculateSurgicalGuidePrice(array_merge($counts, ['upper_anterior' => 1]), 'clinic_print', 0, $quoteThree);
    reserveClinicFreeImplantProgress($pdo, $clinicId, $requestThree, ['state_version' => $quoteThree['clinic_free_state_version']], $summaryThree);
    assertFreeProgress(releaseClinicFreeImplantReservation($pdo, $requestThree), 'The latest open reservation must be releasable.');
    $afterLatestRelease = getClinicFreeImplantState($pdo, $clinicId, 5, true);
    assertFreeProgress($afterLatestRelease['progress_implants'] === 0, 'Releasing the latest request must rewind its unused progress.');

    $pdo->prepare("UPDATE surgical_guide_free_progress
        SET active_free_implant_every = 5, progress_implants = 3, reserved_implants = 0, state_version = state_version + 1
        WHERE clinic_id = :clinic_id")->execute([':clinic_id' => $clinicId]);
    $newDefaultPricing = $pricing;
    $newDefaultPricing['free_implant_every'] = 7;
    $newDefaultPricing['version'] = hash('sha256', 'new-default');
    $protectedQuote = getClinicSurgicalGuideQuote($pdo, $clinicId, $newDefaultPricing, true);
    $protectedSummary = calculateSurgicalGuidePrice(array_merge($counts, ['upper_anterior' => 3]), 'clinic_print', 3, $protectedQuote);
    assertFreeProgress($protectedSummary['free_implants'] === 1, 'The clinic must finish its protected rule after an admin change.');
    assertFreeProgress($protectedSummary['free_progress_after'] === 1 && $protectedSummary['free_implant_every_after'] === 7, 'Remaining implants must start the latest next-cycle rule.');

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

echo "Surgical guide free-progress tests passed.\n";
