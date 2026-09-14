<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/r2_config.php';

use Aws\Exception\AwsException;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access. Please login.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$filename = trim((string) ($data['filename'] ?? ''));
$contentType = strtolower(trim((string) ($data['contentType'] ?? '')));
$fileSize = filter_var($data['fileSize'] ?? null, FILTER_VALIDATE_INT);
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

if ($filename === '' || !in_array($contentType, $allowedTypes, true) || $fileSize === false || $fileSize < 1 || $fileSize > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Kit images must be JPG, PNG, or WebP and no larger than 10 MB each.']);
    exit;
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid kit image extension.']);
    exit;
}

$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));
$objectKey = 'uploads/clinic_' . (int) $_SESSION['user_id'] . '/guide_kit_' . bin2hex(random_bytes(8)) . '_' . $safeName;

try {
    $cmd = $s3Client->getCommand('PutObject', [
        'Bucket' => $bucketName,
        'Key' => $objectKey,
        'ContentType' => $contentType,
    ]);
    $request = $s3Client->createPresignedRequest($cmd, '+15 minutes');

    $_SESSION['pending_guide_kit_uploads'][$objectKey] = [
        'created_at' => time(),
        'original_name' => basename($filename),
        'content_type' => $contentType,
        'file_size' => (int) $fileSize,
    ];

    echo json_encode([
        'presigned_url' => (string) $request->getUri(),
        'object_key' => $objectKey,
    ]);
} catch (AwsException $e) {
    error_log('Guide kit presigned URL error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate a secure upload URL.']);
}

