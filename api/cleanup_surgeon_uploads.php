<?php
require_once '../includes/db_connect.php';
require_once '../includes/r2_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized request.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$csrfToken = (string) ($data['csrf_token'] ?? '');
if (empty($_SESSION['surgeon_request_csrf_token']) || !hash_equals($_SESSION['surgeon_request_csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid form token.']);
    exit;
}

$keys = is_array($data['keys'] ?? null) ? $data['keys'] : [];
$prefix = 'uploads/clinic_' . (int) $_SESSION['user_id'] . '/surgeon_';
$objects = [];
foreach ($keys as $key) {
    $key = trim((string) $key);
    if ($key !== '' && str_starts_with($key, $prefix) && isset($_SESSION['pending_surgeon_uploads'][$key])) {
        $objects[] = ['Key' => $key];
    }
}

if ($objects) {
    try {
        $s3Client->deleteObjects([
            'Bucket' => $bucketName,
            'Delete' => ['Objects' => $objects, 'Quiet' => true],
        ]);
        foreach ($objects as $object) {
            unset($_SESSION['pending_surgeon_uploads'][$object['Key']]);
        }
    } catch (Throwable $e) {
        error_log('Surgeon upload cleanup error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Could not clean up uploaded files.']);
        exit;
    }
}

echo json_encode(['success' => true, 'deleted' => count($objects)]);

