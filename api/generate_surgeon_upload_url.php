<?php
require_once '../includes/db_connect.php';
require_once '../includes/r2_config.php';

header('Content-Type: application/json; charset=utf-8');

function surgeonUploadError(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    surgeonUploadError('Invalid request method.', 405);
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    surgeonUploadError('Unauthorized access. Please login.', 401);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    surgeonUploadError('Invalid upload request.');
}

$csrfToken = (string) ($data['csrf_token'] ?? '');
if (empty($_SESSION['surgeon_request_csrf_token']) || !hash_equals($_SESSION['surgeon_request_csrf_token'], $csrfToken)) {
    surgeonUploadError('Your form session expired. Refresh the page and try again.', 403);
}

$category = (string) ($data['category'] ?? '');
$filename = trim((string) ($data['filename'] ?? ''));
$contentType = trim((string) ($data['content_type'] ?? 'application/octet-stream'));
$fileSize = filter_var($data['file_size'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($category, ['cbct', 'lab'], true)) {
    surgeonUploadError('Invalid file category.');
}
if ($filename === '' || mb_strlen($filename) > 255 || $fileSize === false || $fileSize < 1) {
    surgeonUploadError('Invalid file information.');
}
if ($fileSize > 5 * 1024 * 1024 * 1024) {
    surgeonUploadError('Each file must be no larger than 5 GB.');
}
if ($contentType === '' || mb_strlen($contentType) > 190) {
    $contentType = 'application/octet-stream';
}

$safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));
$objectKey = 'uploads/clinic_' . (int) $_SESSION['user_id'] . '/surgeon_' . $category . '_'
    . bin2hex(random_bytes(12)) . '_' . $safeFilename;

$pendingUploads = $_SESSION['pending_surgeon_uploads'] ?? [];
$pendingUploads = array_filter($pendingUploads, static function ($upload): bool {
    return is_array($upload) && (int) ($upload['created_at'] ?? 0) >= time() - 3600;
});
$pendingUploads[$objectKey] = [
    'created_at' => time(),
    'category' => $category,
    'original_name' => $filename,
    'content_type' => $contentType,
    'file_size' => (int) $fileSize,
];
$_SESSION['pending_surgeon_uploads'] = $pendingUploads;

try {
    $command = $s3Client->getCommand('PutObject', [
        'Bucket' => $bucketName,
        'Key' => $objectKey,
        'ContentType' => $contentType,
    ]);
    $request = $s3Client->createPresignedRequest($command, '+30 minutes');

    echo json_encode([
        'presigned_url' => (string) $request->getUri(),
        'object_key' => $objectKey,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    unset($_SESSION['pending_surgeon_uploads'][$objectKey]);
    error_log('Surgeon presigned URL error: ' . $e->getMessage());
    surgeonUploadError('Failed to generate a secure upload URL.', 500);
}
