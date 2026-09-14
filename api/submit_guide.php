<?php
// api/submit_guide.php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/surgical_guide_pricing.php';
require_once '../includes/implant_types.php';
require_once '../includes/surgical_guide_kits.php';
require_once '../includes/r2_config.php';

header('Content-Type: application/json');

function guideRequestError(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function cleanupUnstoredGuideUploads($s3Client, string $bucketName, int $clinicId, array $keys): void
{
    $prefix = 'uploads/clinic_' . $clinicId . '/';
    $objects = [];
    foreach ($keys as $key) {
        $key = trim((string) $key);
        if (
            $key !== ''
            && str_starts_with($key, $prefix)
            && isset($_SESSION['pending_upload_keys'][$key])
        ) {
            $objects[] = ['Key' => $key];
            unset($_SESSION['pending_upload_keys'][$key]);
        }
    }
    if (!$objects) return;

    try {
        $s3Client->deleteObjects([
            'Bucket' => $bucketName,
            'Delete' => ['Objects' => $objects, 'Quiet' => true],
        ]);
    } catch (Throwable $cleanupError) {
        error_log('Guide upload cleanup failed: ' . $cleanupError->getMessage());
    }
}

function markGuideUploadsStored(array $keys): void
{
    foreach ($keys as $key) {
        unset($_SESSION['pending_upload_keys'][(string) $key]);
    }
}

function cleanupUnstoredGuideKitUploads($s3Client, string $bucketName, int $clinicId, array $keys): void
{
    $prefix = 'uploads/clinic_' . $clinicId . '/guide_kit_';
    $objects = [];
    foreach ($keys as $key) {
        $key = trim((string) $key);
        if ($key !== '' && str_starts_with($key, $prefix) && isset($_SESSION['pending_guide_kit_uploads'][$key])) {
            $objects[] = ['Key' => $key];
            unset($_SESSION['pending_guide_kit_uploads'][$key]);
        }
    }
    if (!$objects) return;

    try {
        $s3Client->deleteObjects([
            'Bucket' => $bucketName,
            'Delete' => ['Objects' => $objects, 'Quiet' => true],
        ]);
    } catch (Throwable $cleanupError) {
        error_log('Guide kit upload cleanup failed: ' . $cleanupError->getMessage());
    }
}

function markGuideKitUploadsStored(array $keys): void
{
    foreach ($keys as $key) {
        unset($_SESSION['pending_guide_kit_uploads'][(string) $key]);
    }
}

// التأكد من صلاحيات المستخدم
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    guideRequestError('Unauthorized access. Please login.', 401);
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operation_date  = trim($_POST['operation_date'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');
    $cbct_file_path  = trim($_POST['cbct_file_path'] ?? '');
    $stl_file_path   = trim($_POST['stl_file_path'] ?? '');
    $implant_type_id_raw = trim($_POST['implant_type_id'] ?? '');
    $implant_type_other = trim($_POST['implant_type_other'] ?? '');
    $implant_type_id = null;
    $implant_type = '';
    $delivery_method = trim($_POST['delivery_method'] ?? '');
    $pricing_version = trim($_POST['pricing_version'] ?? '');
    $guided_kit_source = trim($_POST['guided_kit_source'] ?? '');
    $guided_kit_option_id = null;
    $guided_kit_name = null;
    $guided_kit_type = null;
    $guided_kit_rental_price = 0.0;
    $guided_kit_price_seen = filter_var($_POST['guided_kit_price_seen'] ?? null, FILTER_VALIDATE_FLOAT);
    $guided_kit_uploads_raw = json_decode((string) ($_POST['guided_kit_uploads'] ?? '[]'), true);
    $guided_kit_uploads = is_array($guided_kit_uploads_raw) ? $guided_kit_uploads_raw : [];
    $guided_kit_upload_keys = [];
    $guided_kit_uploads_verified = [];
    ensureSurgicalGuideKitsSchema($pdo);

    foreach (array_merge(GUIDE_UPPER_REGIONS, GUIDE_LOWER_REGIONS) as $region) {
        $count = filter_var($_POST[$region] ?? 0, FILTER_VALIDATE_INT);
        if ($count === false || $count < 0 || $count > 32) {
            guideRequestError('Each implant location must be a whole number between 0 and 32.');
        }
    }
    $implant_counts  = normalizeGuideImplantCounts($_POST);

    if (empty($implant_type_id_raw) || empty($delivery_method) || empty($operation_date) || $pricing_version === '') {
        guideRequestError('Please fill all required fields and refresh the page if pricing is missing.');
    }

    if (!isValidGuideOperationDate($operation_date)) {
        guideRequestError('The operation date must be at least two days after submitting the request.');
    }

    ensureImplantTypesSchema($pdo);
    if ($implant_type_id_raw === 'other') {
        if ($implant_type_other === '') {
            guideRequestError('Please write the implant type name.');
        }
        $implant_type = $implant_type_other;
    } else {
        $implant_type_id = filter_var($implant_type_id_raw, FILTER_VALIDATE_INT);
        if (!$implant_type_id) {
            guideRequestError('Invalid implant type.');
        }

        $selected_implant_type = getImplantTypeById($pdo, (int) $implant_type_id);
        if (!$selected_implant_type || (int) $selected_implant_type['is_active'] !== 1) {
            guideRequestError('Selected implant type is not available.');
        }
        $implant_type = $selected_implant_type['name'];
        $implant_type_other = null;
    }

    if (!in_array($delivery_method, ['clinic_print', 'admin_print'], true)) {
        guideRequestError('Invalid delivery method.');
    }

    if ($guided_kit_source === 'rental') {
        $guided_kit_option_id = filter_var($_POST['guided_kit_option_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$guided_kit_option_id) {
            guideRequestError('Please select an available guided kit to rent.');
        }
        if ($guided_kit_price_seen === false || $guided_kit_price_seen === null || $guided_kit_price_seen < 0) {
            guideRequestError('Invalid guided kit rental price. Please refresh the page.');
        }
        if ($guided_kit_uploads) {
            guideRequestError('Kit photos can only be attached when using your own kit.');
        }
    } elseif ($guided_kit_source === 'owned') {
        $guided_kit_name = trim((string) ($_POST['guided_kit_name'] ?? ''));
        $guided_kit_type = trim((string) ($_POST['guided_kit_type'] ?? ''));
        if ($guided_kit_name === '' || mb_strlen($guided_kit_name) > 190 || !in_array($guided_kit_type, ['sleeved', 'sleeveless'], true)) {
            guideRequestError('Please enter the kit name and select Sleeved or Sleeveless.');
        }
    } else {
        guideRequestError('Please choose whether you will rent a guided kit or use your own kit.');
    }

    $total_requested_implants = array_sum($implant_counts);
    if ($total_requested_implants < 1) {
        guideRequestError('Please enter at least one implant location.');
    }

    if (empty($cbct_file_path) || empty($stl_file_path)) {
        guideRequestError('Both CBCT and STL scan files are required.');
    }

    $verified_scan_uploads = [];
    foreach (['cbct' => $cbct_file_path, 'stl' => $stl_file_path] as $scanType => $key) {
        $pending = $_SESSION['pending_upload_keys'][$key] ?? null;
        if (
            !is_array($pending)
            || !str_starts_with($key, 'uploads/clinic_' . (int) $user_id . '/guide_')
            || (int) ($pending['created_at'] ?? 0) < time() - 3600
        ) {
            cleanupUnstoredGuideUploads($s3Client, $bucketName, (int) $user_id, [$cbct_file_path, $stl_file_path]);
            guideRequestError('One or more scan files are invalid or expired. Please upload them again.');
        }

        try {
            $head = $s3Client->headObject(['Bucket' => $bucketName, 'Key' => $key]);
            $actualSize = (int) ($head['ContentLength'] ?? 0);
            $actualType = strtolower((string) ($head['ContentType'] ?? 'application/octet-stream'));
            if ($actualSize < 1 || $actualSize !== (int) ($pending['file_size'] ?? 0)) {
                throw new RuntimeException('Uploaded scan size mismatch.');
            }
        } catch (Throwable $uploadError) {
            cleanupUnstoredGuideUploads($s3Client, $bucketName, (int) $user_id, [$cbct_file_path, $stl_file_path]);
            guideRequestError('Could not verify one or more scan files. Please upload them again.');
        }

        $verified_scan_uploads[$scanType] = [
            'original_name' => (string) ($pending['original_name'] ?? ''),
            'content_type' => $actualType,
            'file_size' => $actualSize,
        ];
    }

    if (count($guided_kit_uploads) > 8) {
        guideRequestError('You can upload up to 8 kit images.');
    }

    foreach ($guided_kit_uploads as $upload) {
        $key = trim((string) ($upload['key'] ?? ''));
        $metadata = $_SESSION['pending_guide_kit_uploads'][$key] ?? null;
        if (
            $key === ''
            || !is_array($metadata)
            || !str_starts_with($key, 'uploads/clinic_' . (int) $user_id . '/guide_kit_')
            || (int) ($metadata['created_at'] ?? 0) < time() - 3600
        ) {
            cleanupUnstoredGuideKitUploads($s3Client, $bucketName, (int) $user_id, $guided_kit_upload_keys);
            guideRequestError('One or more kit images are invalid or expired. Please upload them again.');
        }

        try {
            $head = $s3Client->headObject(['Bucket' => $bucketName, 'Key' => $key]);
            $actualSize = (int) ($head['ContentLength'] ?? 0);
            $actualType = strtolower((string) ($head['ContentType'] ?? ''));
            if ($actualSize < 1 || $actualSize > 10 * 1024 * 1024 || !in_array($actualType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw new RuntimeException('Invalid uploaded kit image.');
            }
        } catch (Throwable $uploadError) {
            cleanupUnstoredGuideKitUploads($s3Client, $bucketName, (int) $user_id, array_merge($guided_kit_upload_keys, [$key]));
            guideRequestError('Could not verify one of the kit images. Please upload it again.');
        }

        $guided_kit_upload_keys[] = $key;
        $guided_kit_uploads_verified[] = [
            'key' => $key,
            'original_name' => (string) $metadata['original_name'],
            'content_type' => $actualType,
            'file_size' => $actualSize,
        ];
    }

    try {
        $pdo->beginTransaction();

        $clinicLock = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'clinic' FOR UPDATE");
        $clinicLock->execute([':id' => $user_id]);
        if (!$clinicLock->fetchColumn()) {
            throw new RuntimeException('Clinic account not found.');
        }

        $pdo->query("SELECT setting_key
            FROM surgical_guide_pricing_settings
            WHERE setting_key IN (
                'clinic_print_first_implant_price',
                'admin_print_first_implant_price',
                'additional_implant_price',
                'free_implant_every',
                'active_free_rule_cycle_id'
            )
            FOR UPDATE")->fetchAll();

        $pricing_settings = getSurgicalGuidePricing($pdo);
        if (!hash_equals($pricing_settings['version'], $pricing_version)) {
            $pdo->rollBack();
            cleanupUnstoredGuideUploads($s3Client, $bucketName, (int) $user_id, [$cbct_file_path, $stl_file_path]);
            cleanupUnstoredGuideKitUploads($s3Client, $bucketName, (int) $user_id, $guided_kit_upload_keys);
            guideRequestError('Pricing changed while this request was being submitted. Please refresh and review the new price.', 409);
        }
        $previous_implants = getClinicCompletedGuideImplants($pdo, (int) $user_id, (int) $pricing_settings['free_rule_cycle_id']);
        $price_summary = calculateSurgicalGuidePrice($implant_counts, $delivery_method, $previous_implants, $pricing_settings);

        if ($guided_kit_source === 'rental') {
            $guided_kit_option = getSurgicalGuideKitOptionById($pdo, (int) $guided_kit_option_id, true);
            if (!$guided_kit_option || (int) $guided_kit_option['is_active'] !== 1) {
                throw new DomainException('Selected guided kit is no longer available.');
            }
            if (abs((float) $guided_kit_option['rental_price'] - (float) $guided_kit_price_seen) > 0.00001) {
                throw new DomainException('The selected guided kit price changed. Please refresh and review the new price.');
            }
            $guided_kit_name = $guided_kit_option['name'];
            $guided_kit_rental_price = (float) $guided_kit_option['rental_price'];
        }
        $request_total_price = round((float) $price_summary['total_price'] + $guided_kit_rental_price, 2);

        $stmtRequest = $pdo->prepare("INSERT INTO requests (user_id, service_type, status) VALUES (:user_id, 'surgical_guide', 'pending_review')");
        $stmtRequest->execute([':user_id' => $user_id]);
        $request_id = $pdo->lastInsertId();

        $stmtDetails = $pdo->prepare("
            INSERT INTO surgical_guide_details (
                request_id,
                operation_date,
                cbct_file_path,
                cbct_original_name,
                cbct_content_type,
                cbct_file_size,
                stl_file_path,
                stl_original_name,
                stl_content_type,
                stl_file_size,
                implant_type,
                implant_type_id,
                implant_type_other,
                guided_kit_source,
                guided_kit_option_id,
                guided_kit_name,
                guided_kit_type,
                guided_kit_rental_price,
                delivery_method,
                upper_anterior_implants,
                upper_right_posterior_implants,
                upper_left_posterior_implants,
                lower_anterior_implants,
                lower_right_posterior_implants,
                lower_left_posterior_implants,
                upper_implants,
                lower_implants,
                total_implants,
                free_implants,
                paid_implants,
                free_rule_cycle_id,
                free_implant_every_used,
                first_implant_price_used,
                additional_implant_price_used,
                upper_subtotal,
                lower_subtotal,
                print_fee,
                discount_amount,
                total_price,
                notes
            ) VALUES (
                :request_id,
                :operation_date,
                :cbct_file_path,
                :cbct_original_name,
                :cbct_content_type,
                :cbct_file_size,
                :stl_file_path,
                :stl_original_name,
                :stl_content_type,
                :stl_file_size,
                :implant_type,
                :implant_type_id,
                :implant_type_other,
                :guided_kit_source,
                :guided_kit_option_id,
                :guided_kit_name,
                :guided_kit_type,
                :guided_kit_rental_price,
                :delivery_method,
                :upper_anterior_implants,
                :upper_right_posterior_implants,
                :upper_left_posterior_implants,
                :lower_anterior_implants,
                :lower_right_posterior_implants,
                :lower_left_posterior_implants,
                :upper_implants,
                :lower_implants,
                :total_implants,
                :free_implants,
                :paid_implants,
                :free_rule_cycle_id,
                :free_implant_every_used,
                :first_implant_price_used,
                :additional_implant_price_used,
                :upper_subtotal,
                :lower_subtotal,
                :print_fee,
                :discount_amount,
                :total_price,
                :notes
            )
        ");
        $stmtDetails->execute([
            ':request_id'      => $request_id,
            ':operation_date'  => $operation_date,
            ':cbct_file_path'  => $cbct_file_path,
            ':cbct_original_name' => $verified_scan_uploads['cbct']['original_name'],
            ':cbct_content_type' => $verified_scan_uploads['cbct']['content_type'],
            ':cbct_file_size' => $verified_scan_uploads['cbct']['file_size'],
            ':stl_file_path'   => $stl_file_path,
            ':stl_original_name' => $verified_scan_uploads['stl']['original_name'],
            ':stl_content_type' => $verified_scan_uploads['stl']['content_type'],
            ':stl_file_size' => $verified_scan_uploads['stl']['file_size'],
            ':implant_type'    => $implant_type,
            ':implant_type_id' => $implant_type_id,
            ':implant_type_other' => $implant_type_other,
            ':guided_kit_source' => $guided_kit_source,
            ':guided_kit_option_id' => $guided_kit_option_id,
            ':guided_kit_name' => $guided_kit_name,
            ':guided_kit_type' => $guided_kit_type,
            ':guided_kit_rental_price' => $guided_kit_rental_price,
            ':delivery_method' => $delivery_method,
            ':upper_anterior_implants' => $implant_counts['upper_anterior'],
            ':upper_right_posterior_implants' => $implant_counts['upper_right_posterior'],
            ':upper_left_posterior_implants' => $implant_counts['upper_left_posterior'],
            ':lower_anterior_implants' => $implant_counts['lower_anterior'],
            ':lower_right_posterior_implants' => $implant_counts['lower_right_posterior'],
            ':lower_left_posterior_implants' => $implant_counts['lower_left_posterior'],
            ':upper_implants' => $price_summary['upper_implants'],
            ':lower_implants' => $price_summary['lower_implants'],
            ':total_implants' => $price_summary['total_implants'],
            ':free_implants' => $price_summary['free_implants'],
            ':paid_implants' => $price_summary['paid_implants'],
            ':free_rule_cycle_id' => (int) $pricing_settings['free_rule_cycle_id'],
            ':free_implant_every_used' => (int) $pricing_settings['free_implant_every'],
            ':first_implant_price_used' => $price_summary['first_implant_price_used'],
            ':additional_implant_price_used' => $price_summary['additional_implant_price_used'],
            ':upper_subtotal' => $price_summary['upper_subtotal'],
            ':lower_subtotal' => $price_summary['lower_subtotal'],
            ':print_fee' => $price_summary['print_fee'],
            ':discount_amount' => $price_summary['discount_amount'],
            ':total_price' => $request_total_price,
            ':notes'           => $notes
        ]);

        if (!empty($guided_kit_uploads_verified)) {
            $stmtKitFile = $pdo->prepare("INSERT INTO surgical_guide_kit_files
                (request_id, file_path, original_name, content_type, file_size)
                VALUES (:request_id, :file_path, :original_name, :content_type, :file_size)");
            foreach ($guided_kit_uploads_verified as $file) {
                $stmtKitFile->execute([
                    ':request_id' => $request_id,
                    ':file_path' => $file['key'],
                    ':original_name' => $file['original_name'],
                    ':content_type' => $file['content_type'],
                    ':file_size' => $file['file_size'],
                ]);
            }
        }

        $stmtLog = $pdo->prepare("INSERT INTO request_activity_logs (request_id, actor_id, actor_role, action, new_value, note)
            VALUES (:request_id, :actor_id, 'clinic', 'request_created', 'pending_review', :note)");
        $stmtLog->execute([
            ':request_id' => $request_id,
            ':actor_id' => $user_id,
            ':note' => 'Surgical guide request submitted.'
        ]);

        $pdo->commit();
        markGuideUploadsStored([$cbct_file_path, $stl_file_path]);
        markGuideKitUploadsStored($guided_kit_upload_keys);
        http_response_code(201);
        echo json_encode([
            'success' => 'Request submitted and scans uploaded successfully!',
            'total_price' => $request_total_price,
            'free_implants' => $price_summary['free_implants']
        ]);

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        cleanupUnstoredGuideUploads($s3Client, $bucketName, (int) $user_id, [$cbct_file_path, $stl_file_path]);
        cleanupUnstoredGuideKitUploads($s3Client, $bucketName, (int) $user_id, $guided_kit_upload_keys);
        error_log("DB Error in submit_guide: " . $e->getMessage());
        if ($e instanceof DomainException) {
            http_response_code(409);
            echo json_encode(['error' => $e->getMessage()]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'A database error occurred.']);
        }
    }
} else {
    guideRequestError('Invalid request method.', 405);
}
?>
