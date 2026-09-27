<?php

require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'clinic') {
    header('Location: login.php');
    exit;
}

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId) {
    http_response_code(400);
    exit('Invalid request ID.');
}

$stmt = $pdo->prepare("SELECT id FROM requests
    WHERE id = :id AND user_id = :user_id
      AND service_type IN ('surgical_guide', 'surgeon_request')");
$stmt->execute([':id' => $requestId, ':user_id' => $_SESSION['user_id']]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    exit('Request not found.');
}

http_response_code(409);
exit('New manual payment receipts are disabled. Return to the request page to complete payment online.');
