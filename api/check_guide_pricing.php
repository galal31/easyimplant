<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';
require_once __DIR__ . '/../includes/implant_types.php';
require_once __DIR__ . '/../includes/surgical_guide_kits.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$diagnosticStage = 'validate_request';

try {
    $operationDate = trim((string) ($_POST['operation_date'] ?? ''));
    $implantTypeIdRaw = trim((string) ($_POST['implant_type_id'] ?? ''));
    $implantTypeOther = trim((string) ($_POST['implant_type_other'] ?? ''));
    $deliveryMethod = trim((string) ($_POST['delivery_method'] ?? ''));
    $guidedKitSource = trim((string) ($_POST['guided_kit_source'] ?? ''));
    if ($operationDate === '' || $implantTypeIdRaw === '' || !in_array($deliveryMethod, ['clinic_print', 'admin_print'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Please complete the required guide details.']);
        exit;
    }

    if (!isValidGuideOperationDate($operationDate)) {
        http_response_code(400);
        echo json_encode(['error' => 'The operation date must be at least two days after submitting the request.']);
        exit;
    }

    $implantTotal = 0;
    foreach (array_merge(GUIDE_UPPER_REGIONS, GUIDE_LOWER_REGIONS) as $region) {
        $count = filter_var($_POST[$region] ?? 0, FILTER_VALIDATE_INT);
        if ($count === false || $count < 0 || $count > 32) {
            http_response_code(400);
            echo json_encode(['error' => 'Each implant location must be a whole number between 0 and 32.']);
            exit;
        }
        $implantTotal += $count;
    }

    $diagnosticStage = 'verify_guided_kit';
    if ($guidedKitSource === 'rental') {
        $guidedKitOptionId = filter_var($_POST['guided_kit_option_id'] ?? null, FILTER_VALIDATE_INT);
        $guidedKitOption = $guidedKitOptionId ? getSurgicalGuideKitOptionById($pdo, (int) $guidedKitOptionId) : null;
        if (!$guidedKitOption || (int) $guidedKitOption['is_active'] !== 1) {
            http_response_code(400);
            echo json_encode(['error' => 'Please select an available guided kit to rent.']);
            exit;
        }
        $guidedKitPriceSeen = filter_var($_POST['guided_kit_price_seen'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($guidedKitPriceSeen === false || $guidedKitPriceSeen === null || abs((float) $guidedKitOption['rental_price'] - (float) $guidedKitPriceSeen) > 0.00001) {
            http_response_code(409);
            echo json_encode(['error' => 'The selected guided kit price changed. Please refresh and review the new price.']);
            exit;
        }
    } elseif ($guidedKitSource === 'owned') {
        $guidedKitName = trim((string) ($_POST['guided_kit_name'] ?? ''));
        $guidedKitType = trim((string) ($_POST['guided_kit_type'] ?? ''));
        if ($guidedKitName === '' || mb_strlen($guidedKitName) > 190 || !in_array($guidedKitType, ['sleeved', 'sleeveless'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Please enter the kit name and select Sleeved or Sleeveless.']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Please choose whether you will rent a guided kit or use your own kit.']);
        exit;
    }
    if ($implantTotal < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Please enter at least one implant location.']);
        exit;
    }

    $diagnosticStage = 'verify_implant_type_schema';
    ensureImplantTypesSchema($pdo);
    if ($implantTypeIdRaw === 'other') {
        if ($implantTypeOther === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Please write the implant type name.']);
            exit;
        }
    } else {
        $diagnosticStage = 'verify_implant_type';
        $implantTypeId = filter_var($implantTypeIdRaw, FILTER_VALIDATE_INT);
        $implantType = $implantTypeId ? getImplantTypeById($pdo, (int) $implantTypeId) : null;
        if (!$implantType || (int) $implantType['is_active'] !== 1) {
            http_response_code(400);
            echo json_encode(['error' => 'Selected implant type is not available.']);
            exit;
        }
    }

    $diagnosticStage = 'load_pricing_and_cycle';
    $pricing = getSurgicalGuidePricing($pdo);
    $loadedVersion = trim((string) ($_POST['pricing_version'] ?? ''));

    if ($loadedVersion === '' || !hash_equals($pricing['version'], $loadedVersion)) {
        http_response_code(409);
        echo json_encode([
            'error' => 'Pricing changed while this page was open. Please refresh the page and review the new price before submitting.',
            'pricing_changed' => true,
        ]);
        exit;
    }

    $diagnosticStage = 'complete';
    echo json_encode([
        'success' => true,
        'pricing_version' => $pricing['version'],
        'free_implant_every' => $pricing['free_implant_every'],
        'free_rule_cycle_id' => $pricing['free_rule_cycle_id'],
    ]);
} catch (Throwable $e) {
    error_log('Guide Pricing Check Error [' . $diagnosticStage . ']: ' . $e->getMessage());
    http_response_code(500);

    $safePricingErrors = [
        'Surgical guide pricing schema is out of date. Run the pricing migrations.',
        'Surgical guide pricing settings are incomplete.',
        'The active free-implant cycle does not match the current pricing rule.',
    ];

    $response = [
        'error' => 'Could not verify the current pricing.',
        'stage' => $diagnosticStage,
    ];
    if (in_array($e->getMessage(), $safePricingErrors, true)) {
        $response['details'] = $e->getMessage();
    }

    echo json_encode($response);
}
