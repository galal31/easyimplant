<?php

require_once '../includes/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'clinic') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
if (!$requestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request ID.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM requests
    WHERE id = :id AND user_id = :user_id
      AND service_type IN ('surgical_guide', 'surgeon_request')");
$stmt->execute([':id' => $requestId, ':user_id' => $_SESSION['user_id']]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'Request not found.']);
    exit;
}

http_response_code(409);
echo json_encode(['error' => 'New manual payment receipts are disabled. Please use online payment from the request page.']);
