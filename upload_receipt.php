<?php

require_once 'includes/db_connect.php';
require_once 'includes/user_language.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'clinic') {
    header('Location: login.php');
    exit;
}

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId) {
    http_response_code(400);
    exit(userLocalized('Invalid request ID.', 'رقم الطلب غير صحيح.'));
}

$stmt = $pdo->prepare("SELECT id FROM requests
    WHERE id = :id AND user_id = :user_id
      AND service_type IN ('surgical_guide', 'surgeon_request')");
$stmt->execute([':id' => $requestId, ':user_id' => $_SESSION['user_id']]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    exit(userLocalized('Request not found.', 'الطلب غير موجود.'));
}

http_response_code(409);
exit(userLocalized('New manual payment receipts are disabled. Return to the request page to complete payment online.', 'تم إيقاف رفع إيصالات الدفع اليدوي الجديدة. ارجع إلى صفحة الطلب لإتمام الدفع الإلكتروني.'));
