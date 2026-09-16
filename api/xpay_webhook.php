<?php

require_once '../includes/db_connect.php';
require_once '../includes/xpay.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$rawBody = (string) file_get_contents('php://input');
$signature = trim((string) ($_SERVER['HTTP_XPAY_SIGNATURE'] ?? ''));

try {
    $event = xpayVerifyWebhook($rawBody, $signature);
    xpayProcessWebhookEvent($pdo, $event);
    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (UnexpectedValueException|JsonException $e) {
    error_log('Invalid XPay webhook: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook.']);
} catch (Throwable $e) {
    error_log('XPay webhook processing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed.']);
}
