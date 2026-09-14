<?php
require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';
require_once '../includes/request_review.php';
require_once '../includes/r2_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$requestId = filter_var($data['request_id'] ?? null, FILTER_VALIDATE_INT);
$summary = trim((string) ($data['summary'] ?? ''));
$filePaths = array_values(array_unique(array_filter(array_map(
    static fn($path): string => trim((string) $path),
    is_array($data['files'] ?? null) ? $data['files'] : []
))));

if (!$requestId || !requestWorkflowCsrfIsValid(trim((string) ($data['csrf_token'] ?? '')))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}
if (mb_strlen($summary) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The review explanation cannot exceed 5,000 characters.']);
    exit;
}
if (!$filePaths || count($filePaths) > 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Select between 1 and 20 review files.']);
    exit;
}

$verifiedFiles = [];
$prefix = 'uploads/admin_' . (int) $_SESSION['user_id'] . '/review_request_' . $requestId . '/';
foreach ($filePaths as $path) {
    $pending = $_SESSION['pending_review_uploads'][$path] ?? null;
    if (
        !str_starts_with($path, $prefix)
        || !is_array($pending)
        || (int) ($pending['request_id'] ?? 0) !== (int) $requestId
        || (int) ($pending['created_at'] ?? 0) < time() - 3600
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'One or more review uploads expired or do not belong to this request. Upload them again.']);
        exit;
    }

    try {
        $head = $s3Client->headObject(['Bucket' => $bucketName, 'Key' => $path]);
        $actualSize = (int) ($head['ContentLength'] ?? 0);
        $object = $s3Client->getObject([
            'Bucket' => $bucketName,
            'Key' => $path,
            'Range' => 'bytes=0-8191',
        ]);
        $detectedType = detectRequestReviewFileType((string) $object['Body']);
        if (
            $actualSize < 1
            || $actualSize !== (int) ($pending['file_size'] ?? 0)
            || !$detectedType
            || !requestReviewDetectedTypeMatches((string) $pending['extension'], $detectedType)
        ) {
            throw new RuntimeException('Review file verification failed.');
        }
    } catch (Throwable $e) {
        error_log('Review upload verification error: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'One or more review files could not be verified by type and size. Upload them again.']);
        exit;
    }

    $verifiedFiles[] = [
        'path' => $path,
        'original_name' => $pending['original_name'],
        'content_type' => $pending['content_type'],
        'file_size' => $actualSize,
    ];
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT status, service_type FROM requests WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch();
    if (!$request || $request['service_type'] !== 'surgical_guide') {
        throw new DomainException('Review packages are only available for Surgical Guide requests.');
    }
    if (!surgicalGuideTransitionIsAllowed($request['status'], 'awaiting_clinic_approval', 'review_package')) {
        throw new DomainException('The request is no longer waiting for an admin review package. Refresh the page.');
    }

    $packageStmt = $pdo->prepare("INSERT INTO request_review_packages (request_id, admin_id, summary)
        VALUES (:request_id, :admin_id, :summary)");
    $packageStmt->execute([
        ':request_id' => $requestId,
        ':admin_id' => $_SESSION['user_id'],
        ':summary' => $summary !== '' ? $summary : null,
    ]);
    $packageId = (int) $pdo->lastInsertId();

    $fileStmt = $pdo->prepare("INSERT INTO request_review_files
        (package_id, file_path, original_name, content_type, file_size)
        VALUES (:package_id, :file_path, :original_name, :content_type, :file_size)");
    foreach ($verifiedFiles as $file) {
        $fileStmt->execute([
            ':package_id' => $packageId,
            ':file_path' => $file['path'],
            ':original_name' => $file['original_name'],
            ':content_type' => $file['content_type'],
            ':file_size' => $file['file_size'],
        ]);
    }

    $pdo->prepare("UPDATE requests SET status = 'awaiting_clinic_approval' WHERE id = :id")
        ->execute([':id' => $requestId]);
    $logStmt = $pdo->prepare("INSERT INTO request_activity_logs
        (request_id, actor_id, actor_role, action, old_value, new_value, note)
        VALUES (:request_id, :actor_id, 'admin', 'review_package_sent', :old_value, 'awaiting_clinic_approval', :note)");
    $logStmt->execute([
        ':request_id' => $requestId,
        ':actor_id' => $_SESSION['user_id'],
        ':old_value' => $request['status'],
        ':note' => 'Review package #' . $packageId . ' sent with ' . count($verifiedFiles) . ' file(s).',
    ]);
    $pdo->commit();

    foreach ($verifiedFiles as $file) unset($_SESSION['pending_review_uploads'][$file['path']]);
    echo json_encode(['success' => true, 'message' => 'The review package was sent to the clinic.', 'package_id' => $packageId]);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Send review package error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The review package could not be saved. No request status was changed.']);
}
