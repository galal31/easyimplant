<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/xpay.php';

function assertXpayTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function createXpayTestRequest(PDO $pdo, int $clinicId, string $status, string $total): int
{
    $stmt = $pdo->prepare("INSERT INTO requests (user_id, service_type, status)
        VALUES (:user_id, 'surgical_guide', :status)");
    $stmt->execute([':user_id' => $clinicId, ':status' => $status]);
    $requestId = (int) $pdo->lastInsertId();

    $details = $pdo->prepare("INSERT INTO surgical_guide_details
        (request_id, operation_date, cbct_file_path, stl_file_path, implant_type,
         delivery_method, upper_implants, total_implants, paid_implants,
         first_implant_price_used, upper_subtotal, total_price)
        VALUES (:request_id, DATE_ADD(CURDATE(), INTERVAL 7 DAY), :cbct, :stl, 'XPay Test Implant',
                'clinic_print', 1, 1, 1, :first_price, :upper_subtotal, :total_price)");
    $details->execute([
        ':request_id' => $requestId,
        ':cbct' => 'tests/xpay-cbct-' . $requestId . '.dcm',
        ':stl' => 'tests/xpay-stl-' . $requestId . '.stl',
        ':first_price' => $total,
        ':upper_subtotal' => $total,
        ':total_price' => $total,
    ]);

    return $requestId;
}

function createXpayTestSession(PDO $pdo, int $requestId, int $clinicId, string $sessionId, int $amountMinor): void
{
    $stmt = $pdo->prepare("INSERT INTO xpay_checkout_sessions
        (request_id, user_id, idempotency_key, return_token, xpay_session_id, checkout_url,
         status, payment_status, amount_minor, currency, livemode, expires_at)
        VALUES (:request_id, :user_id, :idempotency_key, :return_token, :session_id,
                :checkout_url, 'open', 'unpaid', :amount_minor, 'EGP', 0,
                DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))");
    $stmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $clinicId,
        ':idempotency_key' => xpayUuidV4(),
        ':return_token' => bin2hex(random_bytes(32)),
        ':session_id' => $sessionId,
        ':checkout_url' => 'https://checkout.xpay.app/c/' . $sessionId,
        ':amount_minor' => $amountMinor,
    ]);
}

$suffix = bin2hex(random_bytes(5));
$clinicId = null;
$requestIds = [];
$sessionIds = [];
$eventIds = [];
$previousWebhookSecret = getenv('XPAY_WEBHOOK_SECRET');
$previousAppUrl = getenv('XPAY_APP_URL');
$testWebhookSecret = 'whsec_test_' . bin2hex(random_bytes(16));
putenv('XPAY_WEBHOOK_SECRET=' . $testWebhookSecret);
putenv('XPAY_APP_URL=http://127.0.0.1/easyimplant');

try {
    $userStmt = $pdo->prepare("INSERT INTO users
        (full_name, clinic_name, email, password, phone, country, role, status)
        VALUES ('XPay Test Clinic', 'XPay Test Clinic', :email, :password, '201000000000', 'egypt', 'clinic', 'approved')");
    $userStmt->execute([
        ':email' => 'xpay-test-' . $suffix . '@example.test',
        ':password' => password_hash('test-only', PASSWORD_DEFAULT),
    ]);
    $clinicId = (int) $pdo->lastInsertId();

    $requestId = createXpayTestRequest($pdo, $clinicId, 'pending_payment', '1499.00');
    $requestIds[] = $requestId;
    $sessionId = 'cs_test_' . $suffix;
    $sessionIds[] = $sessionId;
    createXpayTestSession($pdo, $requestId, $clinicId, $sessionId, 149900);

    $payload = xpayCreateCheckoutPayload(
        ['id' => $requestId, 'user_id' => $clinicId],
        ['full_name' => 'XPay Test Clinic', 'email' => 'xpay@example.test'],
        149900,
        str_repeat('a', 64)
    );
    assertXpayTest($payload['lineItems'][0]['priceData']['unitAmount'] === 149900, 'Checkout payload must use the server-side amount in minor units.');
    assertXpayTest($payload['metadata']['request_id'] === (string) $requestId, 'Checkout payload must carry the request ID in metadata.');
    assertXpayTest(str_contains($payload['afterCompletion']['redirect']['url'], '{CHECKOUT_SESSION_ID}'), 'Checkout return URL must include the XPay session template.');
    assertXpayTest(xpayIsTrustedCheckoutUrl('https://checkout.xpay.app/c/' . $sessionId), 'The official XPay checkout host must be accepted.');
    assertXpayTest(!xpayIsTrustedCheckoutUrl('https://checkout.xpay.app.example/c/' . $sessionId), 'A lookalike checkout host must be rejected.');

    $eventId = 'evt_test_' . $suffix;
    $eventIds[] = $eventId;
    $event = [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $sessionId,
            'status' => 'complete',
            'paymentStatus' => 'paid',
            'amountTotal' => 149900,
            'currency' => 'EGP',
            'livemode' => false,
            'metadata' => ['request_id' => (string) $requestId, 'clinic_id' => (string) $clinicId],
            'paymentIntent' => ['id' => 'pi_test_' . $suffix],
            'customer' => ['id' => 'cus_test_' . $suffix],
        ]],
    ];

    $rawBody = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $testWebhookSecret);
    $verified = xpayVerifyWebhook($rawBody, 't=' . $timestamp . ',v1=' . $signature, $timestamp);
    assertXpayTest($verified['id'] === $eventId, 'A valid signed webhook must be accepted.');
    $invalidSignatureRejected = false;
    try {
        xpayVerifyWebhook($rawBody, 't=' . $timestamp . ',v1=' . str_repeat('0', 64), $timestamp);
    } catch (UnexpectedValueException) {
        $invalidSignatureRejected = true;
    }
    assertXpayTest($invalidSignatureRejected, 'An invalid webhook signature must be rejected.');

    $result = xpayProcessWebhookEvent($pdo, $verified);
    assertXpayTest($result['status'] === 'paid', 'A matching paid checkout must be processed.');
    assertXpayTest($pdo->query("SELECT status FROM requests WHERE id = $requestId")->fetchColumn() === 'in_progress', 'A confirmed payment must move the request to in_progress.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $requestId AND payment_source = 'xpay' AND status = 'approved'")->fetchColumn() === 1, 'A confirmed payment must create one approved XPay payment.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM request_activity_logs WHERE request_id = $requestId AND action = 'xpay_payment_confirmed'")->fetchColumn() === 1, 'A confirmed payment must create one transition log.');

    $duplicate = xpayProcessWebhookEvent($pdo, $verified);
    assertXpayTest($duplicate['status'] === 'duplicate', 'The same webhook event must be ignored on replay.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $requestId")->fetchColumn() === 1, 'A replay must not create a second payment.');

    $secondEventId = 'evt_test_second_' . $suffix;
    $eventIds[] = $secondEventId;
    $secondEvent = $event;
    $secondEvent['id'] = $secondEventId;
    $secondResult = xpayProcessWebhookEvent($pdo, $secondEvent);
    assertXpayTest($secondResult['status'] === 'paid', 'A second paid event for the same checkout must be handled idempotently.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $requestId")->fetchColumn() === 1, 'A second paid event must not create a second payment.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM request_activity_logs WHERE request_id = $requestId AND action = 'xpay_payment_confirmed'")->fetchColumn() === 1, 'A second paid event must not repeat the request transition.');

    $mismatchRequestId = createXpayTestRequest($pdo, $clinicId, 'pending_payment', '100.00');
    $requestIds[] = $mismatchRequestId;
    $mismatchSessionId = 'cs_test_mismatch_' . $suffix;
    $sessionIds[] = $mismatchSessionId;
    createXpayTestSession($pdo, $mismatchRequestId, $clinicId, $mismatchSessionId, 10000);
    $mismatchEventId = 'evt_test_mismatch_' . $suffix;
    $eventIds[] = $mismatchEventId;
    $mismatchEvent = [
        'id' => $mismatchEventId,
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $mismatchSessionId,
            'status' => 'complete',
            'paymentStatus' => 'paid',
            'amountTotal' => 9999,
            'currency' => 'EGP',
            'livemode' => false,
            'metadata' => ['request_id' => (string) $mismatchRequestId, 'clinic_id' => (string) $clinicId],
            'paymentIntent' => ['id' => 'pi_test_mismatch_' . $suffix],
        ]],
    ];
    $mismatchResult = xpayProcessWebhookEvent($pdo, $mismatchEvent);
    assertXpayTest($mismatchResult['status'] === 'rejected', 'A mismatched paid amount must be rejected.');
    assertXpayTest($pdo->query("SELECT status FROM requests WHERE id = $mismatchRequestId")->fetchColumn() === 'pending_payment', 'A mismatched payment must not advance the request.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $mismatchRequestId")->fetchColumn() === 0, 'A mismatched payment must not create an approved payment.');

    echo "XPay payment tests passed.\n";
} finally {
    if ($eventIds) {
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $pdo->prepare("DELETE FROM xpay_webhook_events WHERE event_id IN ($placeholders)")->execute($eventIds);
    }
    if ($requestIds) {
        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $pdo->prepare("DELETE FROM requests WHERE id IN ($placeholders)")->execute($requestIds);
    }
    if ($clinicId) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$clinicId]);
    }
    if ($previousWebhookSecret === false) {
        putenv('XPAY_WEBHOOK_SECRET');
    } else {
        putenv('XPAY_WEBHOOK_SECRET=' . $previousWebhookSecret);
    }
    if ($previousAppUrl === false) {
        putenv('XPAY_APP_URL');
    } else {
        putenv('XPAY_APP_URL=' . $previousAppUrl);
    }
}
