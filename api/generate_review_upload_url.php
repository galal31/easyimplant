<?php
require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';
require_once '../includes/request_review.php';
require_once '../includes/r2_config.php';

use Aws\Exception\AwsException;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

$requestId = filter_var($data['request_id'] ?? null, FILTER_VALIDATE_INT);
$csrfToken = trim((string) ($data['csrf_token'] ?? ''));
if (!$requestId || !requestWorkflowCsrfIsValid($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

try {
    $file = normalizeRequestReviewUpload(
        (string) ($data['filename'] ?? ''),
        (string) ($data['content_type'] ?? ''),
        (int) ($data['file_size'] ?? 0)
    );

    $stmt = $pdo->prepare("SELECT id FROM requests
        WHERE id = :id AND service_type = 'surgical_guide'
          AND status IN ('pending_review', 'awaiting_clinic_approval')");
    $stmt->execute([':id' => $requestId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['error' => 'Review files can only be uploaded while the Surgical Guide is awaiting review or clinic approval.']);
        exit;
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['original_name']);
    $objectKey = 'uploads/admin_' . (int) $_SESSION['user_id'] . '/review_request_' . $requestId
        . '/review_' . bin2hex(random_bytes(8)) . '_' . $safeName;

    $pending = array_filter(
        $_SESSION['pending_review_uploads'] ?? [],
        static fn($metadata): bool => is_array($metadata) && (int) ($metadata['created_at'] ?? 0) >= time() - 3600
    );
    $pending[$objectKey] = $file + [
        'request_id' => (int) $requestId,
        'created_at' => time(),
    ];
    $_SESSION['pending_review_uploads'] = $pending;

    $command = $s3Client->getCommand('PutObject', [
        'Bucket' => $bucketName,
        'Key' => $objectKey,
        'ContentType' => $file['content_type'],
    ]);
    $request = $s3Client->createPresignedRequest($command, '+15 minutes');

    echo json_encode([
        'presigned_url' => (string) $request->getUri(),
        'object_key' => $objectKey,
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (AwsException $e) {
    error_log('Review presigned URL error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate a secure review upload URL.']);
}
