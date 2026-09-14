<?php
require_once '../includes/db_connect.php';
require_once __DIR__ . '/../includes/implant_types.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/surgeon_services.php';
require_once __DIR__ . '/../includes/r2_config.php';

header('Content-Type: application/json; charset=utf-8');

function surgeonRequestResponseError(string $message, int $status): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function cleanupPendingSurgeonUploads($s3Client, string $bucketName, int $clinicId, array $keys): void
{
    $prefix = 'uploads/clinic_' . $clinicId . '/surgeon_';
    $objects = [];
    foreach ($keys as $key) {
        $key = trim((string) $key);
        if ($key !== '' && str_starts_with($key, $prefix) && isset($_SESSION['pending_surgeon_uploads'][$key])) {
            $objects[] = ['Key' => $key];
        }
    }
    if (!$objects) return;

    try {
        $s3Client->deleteObjects([
            'Bucket' => $bucketName,
            'Delete' => ['Objects' => $objects, 'Quiet' => true],
        ]);
        foreach ($objects as $object) {
            unset($_SESSION['pending_surgeon_uploads'][$object['Key']]);
        }
    } catch (Throwable $e) {
        error_log('Surgeon request upload cleanup failed: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    surgeonRequestResponseError('Method not allowed.', 405);
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    surgeonRequestResponseError('Unauthorized. Please log in as a clinic.', 401);
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['surgeon_request_csrf_token']) || !hash_equals($_SESSION['surgeon_request_csrf_token'], $csrfToken)) {
    surgeonRequestResponseError('Your form session expired. Refresh the page and try again.', 403);
}

$userId = (int) $_SESSION['user_id'];
$patientName = trim((string) ($_POST['patient_name'] ?? ''));
$patientAge = filter_var($_POST['patient_age'] ?? null, FILTER_VALIDATE_INT);
$medicalHistory = trim((string) ($_POST['medical_history'] ?? ''));
$proposedDate = trim((string) ($_POST['proposed_date'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));
$surgicalService = trim((string) ($_POST['surgical_service'] ?? ''));
$submittedFiles = json_decode((string) ($_POST['surgeon_files'] ?? '[]'), true);
$uploadedKeys = [];

if ($patientName === '' || mb_strlen($patientName) > 100 || $patientAge === false || $medicalHistory === '' || $proposedDate === '' || $surgicalService === '') {
    surgeonRequestResponseError('Please fill in all required fields correctly.', 400);
}
if ($patientAge < 1 || $patientAge > 120) {
    surgeonRequestResponseError('Please enter a valid patient age.', 400);
}
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $proposedDate);
if (!$date || $date->format('Y-m-d') !== $proposedDate || $proposedDate < date('Y-m-d')) {
    surgeonRequestResponseError('Please choose a valid proposed date that is not in the past.', 400);
}
if (!is_array($submittedFiles) || count($submittedFiles) > 40) {
    surgeonRequestResponseError('Invalid uploaded file list.', 400);
}

try {
    ensureImplantTypesSchema($pdo);
    ensureSurgeonGovernoratePricingSchema($pdo);
    ensureSurgeonServicesSchema($pdo);

    $validatedFiles = [];
    $prefix = 'uploads/clinic_' . $userId . '/surgeon_';
    foreach ($submittedFiles as $submittedFile) {
        $key = trim((string) ($submittedFile['file_path'] ?? ''));
        $pending = $_SESSION['pending_surgeon_uploads'][$key] ?? null;
        if ($key === '' || !str_starts_with($key, $prefix) || !is_array($pending)) {
            throw new InvalidArgumentException('One or more uploaded file paths are invalid.');
        }
        $uploadedKeys[] = $key;
        $category = (string) ($pending['category'] ?? '');
        if (!in_array($category, ['cbct', 'lab'], true)) {
            throw new InvalidArgumentException('Invalid uploaded file category.');
        }

        try {
            $head = $s3Client->headObject(['Bucket' => $bucketName, 'Key' => $key]);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('A file upload is incomplete. Please upload it again.');
        }
        if ((int) ($head['ContentLength'] ?? -1) !== (int) ($pending['file_size'] ?? -2)) {
            throw new InvalidArgumentException('An uploaded file size could not be verified.');
        }

        $validatedFiles[] = [
            'file_path' => $key,
            'file_category' => $category,
            'original_name' => (string) $pending['original_name'],
            'content_type' => (string) $pending['content_type'],
            'file_size' => (int) $pending['file_size'],
        ];
    }

    $regularPackages = getSurgeonImplantPackages();
    $allOnPackages = getSurgeonAllOnPackages();
    $implantPackage = trim((string) ($_POST['implant_package'] ?? ''));
    $requestData = [
        'service_kind' => null,
        'surgeon_service_id' => null,
        'service_name_snapshot' => null,
        'implant_package' => null,
        'implant_count' => null,
        'implant_type_id' => null,
        'implant_type_name_snapshot' => null,
        'implant_provider' => null,
        'doctor_fee_per_implant' => null,
        'doctor_fee_total' => null,
        'team_fee_total' => null,
        'implant_unit_price' => null,
        'implant_cost_total' => null,
        'travel_price' => null,
        'estimated_total' => null,
        'currency' => null,
        'requires_quote' => 1,
    ];
    $arches = [];

    $pdo->beginTransaction();
    $clinicStmt = $pdo->prepare("SELECT country, governorate FROM users WHERE id = :id AND role = 'clinic' FOR UPDATE");
    $clinicStmt->execute([':id' => $userId]);
    $clinic = $clinicStmt->fetch();
    if (!$clinic || strtolower((string) $clinic['country']) !== 'egypt') {
        throw new DomainException('Surgeon requests are available to clinics inside Egypt only.');
    }

    $travelStmt = $pdo->prepare("SELECT price, is_available FROM surgeon_governorate_prices WHERE governorate_code = :code FOR UPDATE");
    $travelStmt->execute([':code' => $clinic['governorate'] ?? '']);
    $travelSetting = $travelStmt->fetch();
    if (!$travelSetting || !(int) $travelSetting['is_available']) {
        throw new DomainException('Surgeon service is not currently available for your clinic governorate.');
    }
    $travelPrice = (float) $travelSetting['price'];

    if ($surgicalService === 'dental_implant' && isset($regularPackages[$implantPackage])) {
        $implantCount = filter_var($_POST['implant_count'] ?? null, FILTER_VALIDATE_INT);
        $implantTypeId = filter_var($_POST['implant_type_id'] ?? null, FILTER_VALIDATE_INT);
        $implantProvider = trim((string) ($_POST['implant_provider'] ?? ''));
        if ($implantCount === false || $implantCount < 1 || $implantCount > 32 || !$implantTypeId || !in_array($implantProvider, ['clinic', 'easy_implant'], true)) {
            throw new InvalidArgumentException('Please choose valid implant details.');
        }
        $implantStmt = $pdo->prepare("SELECT id, name, price, is_active FROM implant_types WHERE id = :id FOR UPDATE");
        $implantStmt->execute([':id' => $implantTypeId]);
        $implantType = $implantStmt->fetch();
        if (!$implantType || !(int) $implantType['is_active']) {
            throw new InvalidArgumentException('The selected implant type is no longer available.');
        }

        $doctorFee = (float) $regularPackages[$implantPackage]['doctor_fee_per_implant'];
        $unitPrice = (float) $implantType['price'];
        $calculation = calculateSurgeonImplantPrice((int) $implantCount, $doctorFee, $unitPrice, $travelPrice, $implantProvider === 'easy_implant');
        $requestData = [
            'service_kind' => 'dental_implant', 'surgeon_service_id' => null, 'service_name_snapshot' => 'Dental Implant',
            'implant_package' => $implantPackage, 'implant_count' => (int) $implantCount,
            'implant_type_id' => (int) $implantType['id'], 'implant_type_name_snapshot' => $implantType['name'],
            'implant_provider' => $implantProvider, 'doctor_fee_per_implant' => $doctorFee,
            'doctor_fee_total' => $calculation['doctor_fee_total'], 'team_fee_total' => 0,
            'implant_unit_price' => $unitPrice, 'implant_cost_total' => $calculation['implant_cost_total'],
            'travel_price' => $travelPrice, 'estimated_total' => $calculation['estimated_total'],
            'currency' => 'EGP', 'requires_quote' => 0,
        ];
    } elseif ($surgicalService === 'dental_implant' && $implantPackage === 'all_on_arches') {
        $priceStmt = $pdo->query("SELECT package_code, team_fee FROM surgeon_all_on_prices WHERE package_code IN ('all_on_4', 'all_on_6') FOR UPDATE");
        $teamPrices = $priceStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $totalImplants = 0;
        $teamFeeTotal = 0.00;
        $implantCostTotal = 0.00;

        foreach (['upper', 'lower'] as $archPosition) {
            $packageCode = trim((string) ($_POST[$archPosition . '_package'] ?? ''));
            if ($packageCode === '') continue;
            if (!isset($allOnPackages[$packageCode]) || !array_key_exists($packageCode, $teamPrices)) {
                throw new InvalidArgumentException('Please choose a valid All-on package.');
            }
            $implantTypeId = filter_var($_POST[$archPosition . '_implant_type_id'] ?? null, FILTER_VALIDATE_INT);
            $implantProvider = trim((string) ($_POST[$archPosition . '_implant_provider'] ?? ''));
            if (!$implantTypeId || !in_array($implantProvider, ['clinic', 'easy_implant'], true)) {
                throw new InvalidArgumentException('Complete the implant type and provider for each selected arch.');
            }
            $implantStmt = $pdo->prepare("SELECT id, name, price, is_active FROM implant_types WHERE id = :id FOR UPDATE");
            $implantStmt->execute([':id' => $implantTypeId]);
            $implantType = $implantStmt->fetch();
            if (!$implantType || !(int) $implantType['is_active']) {
                throw new InvalidArgumentException('An implant type selected for an arch is no longer available.');
            }
            $teamFee = (float) $teamPrices[$packageCode];
            $unitPrice = (float) $implantType['price'];
            $calculation = calculateSurgeonAllOnArchPrice($packageCode, $teamFee, $unitPrice, $implantProvider === 'easy_implant');
            $arches[] = [
                'arch_position' => $archPosition, 'package_code' => $packageCode,
                'implant_count' => $calculation['implant_count'], 'implant_type_id' => (int) $implantType['id'],
                'implant_type_name_snapshot' => $implantType['name'], 'implant_provider' => $implantProvider,
                'implant_unit_price' => $unitPrice, 'implant_cost_total' => $calculation['implant_cost_total'],
                'team_fee' => $teamFee, 'subtotal' => $calculation['subtotal'],
            ];
            $totalImplants += $calculation['implant_count'];
            $teamFeeTotal += $teamFee;
            $implantCostTotal += $calculation['implant_cost_total'];
        }
        if (!$arches) {
            throw new InvalidArgumentException('Choose at least one arch for the All-on treatment.');
        }
        $requestData = [
            'service_kind' => 'dental_implant', 'surgeon_service_id' => null, 'service_name_snapshot' => 'Dental Implant',
            'implant_package' => 'all_on_arches', 'implant_count' => $totalImplants,
            'implant_type_id' => null, 'implant_type_name_snapshot' => null, 'implant_provider' => null,
            'doctor_fee_per_implant' => null, 'doctor_fee_total' => 0, 'team_fee_total' => round($teamFeeTotal, 2),
            'implant_unit_price' => null, 'implant_cost_total' => round($implantCostTotal, 2),
            'travel_price' => $travelPrice, 'estimated_total' => round($teamFeeTotal + $implantCostTotal + $travelPrice, 2),
            'currency' => 'EGP', 'requires_quote' => 0,
        ];
    } elseif (preg_match('/^custom:(\d+)$/', $surgicalService, $matches)) {
        $serviceStmt = $pdo->prepare("SELECT id, name, is_active FROM surgeon_services WHERE id = :id FOR UPDATE");
        $serviceStmt->execute([':id' => (int) $matches[1]]);
        $service = $serviceStmt->fetch();
        if (!$service || !(int) $service['is_active']) {
            throw new InvalidArgumentException('The selected surgical service is no longer available.');
        }
        $requestData['service_kind'] = 'custom_quote';
        $requestData['surgeon_service_id'] = (int) $service['id'];
        $requestData['service_name_snapshot'] = $service['name'];
    } else {
        throw new InvalidArgumentException('Please choose a valid surgical service.');
    }

    $requestStmt = $pdo->prepare("INSERT INTO requests (user_id, service_type, status) VALUES (:user_id, 'surgeon_request', 'pending_review')");
    $requestStmt->execute([':user_id' => $userId]);
    $requestId = (int) $pdo->lastInsertId();

    $detailsStmt = $pdo->prepare("
        INSERT INTO surgeon_requests (
            request_id, service_kind, surgeon_service_id, service_name_snapshot, implant_package,
            implant_count, implant_type_id, implant_type_name_snapshot, implant_provider,
            doctor_fee_per_implant, doctor_fee_total, team_fee_total, implant_unit_price,
            implant_cost_total, travel_price, estimated_total, currency, requires_quote,
            patient_name, patient_age, medical_history, proposed_date, notes
        ) VALUES (
            :request_id, :service_kind, :surgeon_service_id, :service_name_snapshot, :implant_package,
            :implant_count, :implant_type_id, :implant_type_name_snapshot, :implant_provider,
            :doctor_fee_per_implant, :doctor_fee_total, :team_fee_total, :implant_unit_price,
            :implant_cost_total, :travel_price, :estimated_total, :currency, :requires_quote,
            :patient_name, :patient_age, :medical_history, :proposed_date, :notes
        )
    ");
    $detailsStmt->execute(array_merge($requestData, [
        'request_id' => $requestId, 'patient_name' => $patientName, 'patient_age' => $patientAge,
        'medical_history' => $medicalHistory, 'proposed_date' => $proposedDate,
        'notes' => $notes !== '' ? $notes : null,
    ]));

    if ($arches) {
        $archStmt = $pdo->prepare("
            INSERT INTO surgeon_request_arches (
                request_id, arch_position, package_code, implant_count, implant_type_id,
                implant_type_name_snapshot, implant_provider, implant_unit_price,
                implant_cost_total, team_fee, subtotal
            ) VALUES (
                :request_id, :arch_position, :package_code, :implant_count, :implant_type_id,
                :implant_type_name_snapshot, :implant_provider, :implant_unit_price,
                :implant_cost_total, :team_fee, :subtotal
            )
        ");
        foreach ($arches as $arch) $archStmt->execute(array_merge(['request_id' => $requestId], $arch));
    }

    if ($validatedFiles) {
        $fileStmt = $pdo->prepare("
            INSERT INTO surgeon_request_files (request_id, file_category, file_path, original_name, content_type, file_size)
            VALUES (:request_id, :file_category, :file_path, :original_name, :content_type, :file_size)
        ");
        foreach ($validatedFiles as $file) $fileStmt->execute(array_merge(['request_id' => $requestId], $file));
    }

    $pdo->commit();
    foreach ($uploadedKeys as $key) unset($_SESSION['pending_surgeon_uploads'][$key]);
    $_SESSION['surgeon_request_csrf_token'] = bin2hex(random_bytes(32));
    http_response_code(201);
    echo json_encode([
        'success' => $requestData['requires_quote']
            ? 'Surgeon request submitted successfully. سيتم الرد بعرض سعر'
            : 'Surgeon request submitted successfully. Estimated total: ' . number_format((float) $requestData['estimated_total'], 2) . ' EGP.',
        'request_id' => $requestId,
        'estimated_total' => $requestData['estimated_total'],
        'currency' => $requestData['currency'],
        'requires_quote' => (bool) $requestData['requires_quote'],
    ], JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cleanupPendingSurgeonUploads($s3Client, $bucketName, $userId, $uploadedKeys);
    surgeonRequestResponseError($e->getMessage(), 403);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cleanupPendingSurgeonUploads($s3Client, $bucketName, $userId, $uploadedKeys);
    surgeonRequestResponseError($e->getMessage(), 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cleanupPendingSurgeonUploads($s3Client, $bucketName, $userId, $uploadedKeys);
    error_log('Surgeon Request Error: ' . $e->getMessage());
    surgeonRequestResponseError('Failed to submit request due to a server error.', 500);
}
