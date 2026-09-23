<?php
require_once '../includes/db_connect.php';
require_once '../includes/r2_config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo 'Method not allowed.';
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'clinic') {
    http_response_code(401);
    echo 'Please sign in to open this review file.';
    exit;
}

$fileId = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if (!$fileId) {
    http_response_code(400);
    echo 'Invalid review file.';
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT rf.file_path
        FROM request_review_files rf
        INNER JOIN request_review_packages rp ON rp.id = rf.package_id
        INNER JOIN requests r ON r.id = rp.request_id
        WHERE rf.id = :file_id
          AND r.user_id = :user_id
          AND r.service_type = 'surgical_guide'
        LIMIT 1");
    $stmt->execute([
        ':file_id' => (int) $fileId,
        ':user_id' => (int) $_SESSION['user_id'],
    ]);
    $filePath = trim((string) $stmt->fetchColumn());

    if ($filePath === '') {
        http_response_code(404);
        echo 'Review file not found or unavailable to this account.';
        exit;
    }

    $command = $s3Client->getCommand('GetObject', [
        'Bucket' => $bucketName,
        'Key' => $filePath,
    ]);
    $request = $s3Client->createPresignedRequest($command, '+5 minutes');

    header('Location: ' . (string) $request->getUri(), true, 302);
    exit;
} catch (Throwable $e) {
    error_log('Open review file error for file #' . (int) $fileId . ': ' . $e->getMessage());
    http_response_code(500);
    echo 'The review file could not be opened. Please refresh the page and try again.';
}
