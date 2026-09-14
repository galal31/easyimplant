<?php
// api/generate_presigned_url.php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/r2_config.php';

use Aws\Exception\AwsException;

header('Content-Type: application/json');

// التأكد من صلاحيات المستخدم
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['clinic', 'admin'])) {
    echo json_encode(['error' => 'Unauthorized access. Please login.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $filename = trim((string) ($data['filename'] ?? ''));
    $contentType = trim((string) ($data['contentType'] ?? 'application/octet-stream'));
    $fileSize = filter_var($data['fileSize'] ?? null, FILTER_VALIDATE_INT);

    if ($filename === '' || mb_strlen($filename) > 255 || $fileSize === false || $fileSize < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid filename and file size are required.']);
        exit;
    }

    if ($fileSize > 5 * 1024 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'The selected file is too large.']);
        exit;
    }

    if ($contentType === '' || strlen($contentType) > 100) {
        $contentType = 'application/octet-stream';
    }

    $originalName = basename(str_replace('\\', '/', $filename));

    // توليد مسار فريد مرتبط بصاحب الجلسة حتى يمكن تنظيف الرفع غير المكتمل بأمان.
    $ownerPrefix = $_SESSION['role'] . '_' . (int) $_SESSION['user_id'];
    $uniqueFileName = 'uploads/' . $ownerPrefix . '/guide_' . bin2hex(random_bytes(8)) . '_'
        . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($filename));
    $pendingUploads = $_SESSION['pending_upload_keys'] ?? [];
    $pendingUploads = array_filter(
        $pendingUploads,
        static fn($metadata) => (int) (is_array($metadata) ? ($metadata['created_at'] ?? 0) : $metadata) >= time() - 3600
    );
    $pendingUploads[$uniqueFileName] = [
        'created_at' => time(),
        'original_name' => $originalName,
        'content_type' => $contentType,
        'file_size' => (int) $fileSize,
    ];
    $_SESSION['pending_upload_keys'] = $pendingUploads;

    try {
        // إعداد أمر الرفع (PutObject)
        $cmd = $s3Client->getCommand('PutObject', [
            'Bucket'      => $bucketName,
            'Key'         => $uniqueFileName,
            'ContentType' => $contentType
        ]);

        // توليد الرابط الموقع مسبقاً (صالح لمدة 15 دقيقة)
        $request = $s3Client->createPresignedRequest($cmd, '+15 minutes');

        echo json_encode([
            'presigned_url' => (string)$request->getUri(),
            'object_key'    => $uniqueFileName
        ]);
    } catch (AwsException $e) {
        error_log("Presigned URL Error: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to generate upload URL.']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method.']);
}
?>
