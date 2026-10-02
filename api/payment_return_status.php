<?php
define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/payment_return.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error'=>'Method not allowed.']);
    exit;
}
try {
    $token = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';
    $state = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
    echo json_encode(paymentReturnStatus($pdo, $token, $state), JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Payment return status unavailable: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error'=>'Could not check payment status. Please try again.']);
}
