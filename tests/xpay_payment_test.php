<?php

require_once __DIR__ . '/../includes/db_connect.php';
define('XPAY_LOCAL_CONFIG_PATH', __DIR__ . '/xpay.local.test.php');
require_once __DIR__ . '/../includes/xpay.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';

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

function createXpaySurgeonRequest(PDO $pdo, int $clinicId, string $status, ?string $total, bool $requiresQuote = false): int
{
    $stmt = $pdo->prepare("INSERT INTO requests (user_id, service_type, status)
        VALUES (:user_id, 'surgeon_request', :status)");
    $stmt->execute([':user_id' => $clinicId, ':status' => $status]);
    $requestId = (int) $pdo->lastInsertId();
    $details = $pdo->prepare("INSERT INTO surgeon_requests
        (request_id, service_kind, service_name_snapshot, estimated_total, total_price, currency,
         requires_quote, patient_name, proposed_date)
        VALUES (:request_id, :service_kind, :service_name, :estimated_total, :total_price, 'EGP',
                :requires_quote, 'Payment Test Patient', DATE_ADD(CURDATE(), INTERVAL 7 DAY))");
    $details->execute([
        ':request_id' => $requestId,
        ':service_kind' => $requiresQuote ? 'catalog_service' : 'dental_implant',
        ':service_name' => $requiresQuote ? 'Quote Test Service' : 'Implant Test Service',
        ':estimated_total' => $requiresQuote ? null : $total,
        ':total_price' => $total,
        ':requires_quote' => $requiresQuote ? 1 : 0,
    ]);
    return $requestId;
}

function paidXpayEvent(string $eventId, string $sessionId, int $requestId, int $clinicId, int $amountMinor, string $serviceType): array
{
    return [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $sessionId,
            'status' => 'complete',
            'paymentStatus' => 'paid',
            'amountTotal' => $amountMinor,
            'currency' => 'EGP',
            'livemode' => false,
            'metadata' => [
                'request_id' => (string) $requestId,
                'clinic_id' => (string) $clinicId,
                'service_type' => $serviceType,
            ],
            'paymentIntent' => ['id' => 'pi_' . $eventId],
            'customer' => ['id' => 'cus_' . $eventId],
        ]],
    ];
}

$suffix = bin2hex(random_bytes(5));
$clinicId = null;
$requestIds = [];
$sessionIds = [];
$eventIds = [];
$previousSecretKey = getenv('XPAY_SECRET_KEY');
$previousWebhookSecret = getenv('XPAY_WEBHOOK_SECRET');
$previousAppUrl = getenv('XPAY_APP_URL');
$testSecretKey = 'sk_test_' . bin2hex(random_bytes(16));
$testWebhookSecret = 'whsec_test_' . bin2hex(random_bytes(16));
putenv('XPAY_SECRET_KEY=' . $testSecretKey);
putenv('XPAY_WEBHOOK_SECRET=' . $testWebhookSecret);
putenv('XPAY_APP_URL=http://127.0.0.1/easyimplant');

try {
    $resolvedConfig = xpayResolveConfig([
        'XPAY_SECRET_KEY' => 'sk_test_local_value',
        'XPAY_WEBHOOK_SECRET' => '',
        'XPAY_APP_URL' => 'http://localhost/easyimplant/',
    ]);
    assertXpayTest($resolvedConfig['secret_key'] === 'sk_test_local_value', 'Local XPay values must take priority over environment variables.');
    assertXpayTest($resolvedConfig['webhook_secret'] === $testWebhookSecret, 'Empty local XPay values must fall back to environment variables.');
    assertXpayTest($resolvedConfig['app_url'] === 'http://localhost/easyimplant', 'The resolved XPay app URL must not retain a trailing slash.');

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
        ['id' => $requestId, 'user_id' => $clinicId, 'service_type' => 'surgical_guide'],
        ['full_name' => 'XPay Test Clinic', 'email' => 'xpay@example.test'],
        149900,
        str_repeat('a', 64)
    );
    assertXpayTest($payload['submitType'] === 'PAY', 'Checkout submitType must use the uppercase XPay API enum.');
    assertXpayTest($payload['lineItems'][0]['priceData']['unitAmount'] === 149900, 'Checkout payload must use the server-side amount in minor units.');
    assertXpayTest($payload['metadata']['request_id'] === (string) $requestId, 'Checkout payload must carry the request ID in metadata.');
    assertXpayTest($payload['metadata']['service_type'] === 'surgical_guide', 'Checkout payload must carry the service type in metadata.');
    assertXpayTest(!str_contains($payload['afterCompletion']['redirect']['url'], 'session_id'), 'The customer return URL must not expose a checkout session identifier.');
    assertXpayTest(xpayIsTrustedCheckoutUrl('https://checkout.xpay.app/c/' . $sessionId), 'The official XPay checkout host must be accepted.');
    assertXpayTest(!xpayIsTrustedCheckoutUrl('https://checkout.xpay.app.example/c/' . $sessionId), 'A lookalike checkout host must be rejected.');
    $surgeonPayload = xpayCreateCheckoutPayload(
        ['id' => 999, 'user_id' => $clinicId, 'service_type' => 'surgeon_request'],
        ['full_name' => 'XPay Test Clinic', 'email' => 'xpay@example.test'],
        10000,
        str_repeat('b', 64)
    );
    assertXpayTest($surgeonPayload['metadata']['service_type'] === 'surgeon_request', 'Surgeon checkout metadata must identify the service type.');
    assertXpayTest(str_starts_with($surgeonPayload['lineItems'][0]['priceData']['productData']['name'], 'Implant Surgeon Request'), 'Surgeon checkout must use the surgeon product name.');

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
            'metadata' => ['request_id' => (string) $requestId, 'clinic_id' => (string) $clinicId, 'service_type' => 'surgical_guide'],
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
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM request_activity_logs WHERE request_id = $requestId AND action = 'online_payment_confirmed' AND actor_role = 'system'")->fetchColumn() === 1, 'A confirmed payment must create one neutral system transition log.');

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
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM request_activity_logs WHERE request_id = $requestId AND action = 'online_payment_confirmed'")->fetchColumn() === 1, 'A second paid event must not repeat the request transition.');

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
            'metadata' => ['request_id' => (string) $mismatchRequestId, 'clinic_id' => (string) $clinicId, 'service_type' => 'surgical_guide'],
            'paymentIntent' => ['id' => 'pi_test_mismatch_' . $suffix],
        ]],
    ];
    $mismatchResult = xpayProcessWebhookEvent($pdo, $mismatchEvent);
    assertXpayTest($mismatchResult['status'] === 'rejected', 'A mismatched paid amount must be rejected.');
    assertXpayTest($pdo->query("SELECT status FROM requests WHERE id = $mismatchRequestId")->fetchColumn() === 'pending_payment', 'A mismatched payment must not advance the request.');
    assertXpayTest($pdo->query("SELECT payment_status FROM xpay_checkout_sessions WHERE xpay_session_id = " . $pdo->quote($mismatchSessionId))->fetchColumn() === 'unpaid', 'A rejected paid event must not mark the stored checkout as paid.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $mismatchRequestId")->fetchColumn() === 0, 'A mismatched payment must not create an approved payment.');

    $surgeonRequestId = createXpaySurgeonRequest($pdo, $clinicId, 'pending_payment', '2750.00');
    $requestIds[] = $surgeonRequestId;
    $pdo->prepare("UPDATE surgeon_requests SET estimated_total = '9999.00' WHERE request_id = :id")->execute([':id' => $surgeonRequestId]);
    $surgeonSessionId = 'cs_test_surgeon_' . $suffix;
    createXpayTestSession($pdo, $surgeonRequestId, $clinicId, $surgeonSessionId, 275000);
    $surgeonEventId = 'evt_test_surgeon_' . $suffix;
    $eventIds[] = $surgeonEventId;
    $surgeonResult = xpayProcessWebhookEvent($pdo, paidXpayEvent($surgeonEventId, $surgeonSessionId, $surgeonRequestId, $clinicId, 275000, 'surgeon_request'));
    assertXpayTest($surgeonResult['status'] === 'paid', 'A matching surgeon-request payment must be processed.');
    assertXpayTest((float) $pdo->query("SELECT amount FROM payments WHERE request_id = $surgeonRequestId")->fetchColumn() === 2750.00, 'Surgeon payment must use the stored final price, not a changed estimate.');
    assertXpayTest($pdo->query("SELECT status FROM requests WHERE id = $surgeonRequestId")->fetchColumn() === 'in_progress', 'A paid surgeon request must move to in_progress.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $surgeonRequestId AND status = 'approved'")->fetchColumn() === 1, 'A surgeon-request payment must be stored once.');

    $quoteRequestId = createXpaySurgeonRequest($pdo, $clinicId, 'pending_review', null, true);
    $requestIds[] = $quoteRequestId;
    $quoteCannotPay = false;
    try { xpayDecimalToMinor((string) $pdo->query("SELECT total_price FROM surgeon_requests WHERE request_id = $quoteRequestId")->fetchColumn()); }
    catch (InvalidArgumentException|DomainException) { $quoteCannotPay = true; }
    assertXpayTest($quoteCannotPay, 'A quote-only service must not have a payable amount before price approval.');
    $pdo->prepare("UPDATE surgeon_requests SET total_price = '900.00', price_confirmed_at = NOW() WHERE request_id = :id")->execute([':id' => $quoteRequestId]);
    $pdo->prepare("UPDATE requests SET status = 'pending_payment' WHERE id = :id")->execute([':id' => $quoteRequestId]);
    assertXpayTest(xpayDecimalToMinor((string) $pdo->query("SELECT total_price FROM surgeon_requests WHERE request_id = $quoteRequestId")->fetchColumn()) === 90000, 'An approved quote price must become the stored full payment amount.');

    $currencyRequestId = createXpaySurgeonRequest($pdo, $clinicId, 'pending_payment', '300.00');
    $requestIds[] = $currencyRequestId;
    $currencySessionId = 'cs_test_currency_' . $suffix;
    createXpayTestSession($pdo, $currencyRequestId, $clinicId, $currencySessionId, 30000);
    $currencyEventId = 'evt_test_currency_' . $suffix;
    $eventIds[] = $currencyEventId;
    $currencyEvent = paidXpayEvent($currencyEventId, $currencySessionId, $currencyRequestId, $clinicId, 30000, 'surgeon_request');
    $currencyEvent['data']['object']['currency'] = 'USD';
    assertXpayTest(xpayProcessWebhookEvent($pdo, $currencyEvent)['status'] === 'rejected', 'A currency mismatch must be rejected.');

    $serviceRequestId = createXpaySurgeonRequest($pdo, $clinicId, 'pending_payment', '400.00');
    $requestIds[] = $serviceRequestId;
    $serviceSessionId = 'cs_test_service_' . $suffix;
    createXpayTestSession($pdo, $serviceRequestId, $clinicId, $serviceSessionId, 40000);
    $serviceEventId = 'evt_test_service_' . $suffix;
    $eventIds[] = $serviceEventId;
    assertXpayTest(xpayProcessWebhookEvent($pdo, paidXpayEvent($serviceEventId, $serviceSessionId, $serviceRequestId, $clinicId, 40000, 'surgical_guide'))['status'] === 'rejected', 'A service-type metadata mismatch must be rejected.');

    $lateRequestId = createXpaySurgeonRequest($pdo, $clinicId, 'rejected', '500.00');
    $requestIds[] = $lateRequestId;
    $lateSessionId = 'cs_test_late_' . $suffix;
    createXpayTestSession($pdo, $lateRequestId, $clinicId, $lateSessionId, 50000);
    $lateEventId = 'evt_test_late_' . $suffix;
    $eventIds[] = $lateEventId;
    assertXpayTest(xpayProcessWebhookEvent($pdo, paidXpayEvent($lateEventId, $lateSessionId, $lateRequestId, $clinicId, 50000, 'surgeon_request'))['status'] === 'paid', 'A valid late payment must still be recorded.');
    assertXpayTest($pdo->query("SELECT status FROM requests WHERE id = $lateRequestId")->fetchColumn() === 'rejected', 'A late payment must not reopen a rejected request.');
    assertXpayTest((int)$pdo->query("SELECT financial_review_required FROM surgeon_requests WHERE request_id = $lateRequestId")->fetchColumn() === 1, 'A late surgeon payment must flag financial review without refunding or reopening.');
    assertXpayTest((int) $pdo->query("SELECT COUNT(*) FROM request_activity_logs WHERE request_id = $lateRequestId AND action = 'online_payment_requires_review'")->fetchColumn() === 1, 'A late payment must create a neutral review activity.');

    $awaitingBefore = getClinicAwaitingPaymentAmount($pdo, $clinicId);
    $awaitingGuideId = createXpayTestRequest($pdo, $clinicId, 'pending_payment', '125.00');
    $requestIds[] = $awaitingGuideId;
    $awaitingSurgeonId = createXpaySurgeonRequest($pdo, $clinicId, 'pending_payment', '225.00');
    $requestIds[] = $awaitingSurgeonId;
    $pdo->prepare("INSERT INTO clinic_account_adjustments (clinic_id, adjustment_type, amount, reason) VALUES (:clinic, 'debit', 999.00, 'Legacy test adjustment')")->execute([':clinic' => $clinicId]);
    assertXpayTest(abs(getClinicAwaitingPaymentAmount($pdo, $clinicId) - $awaitingBefore - 350.00) < 0.001, 'Awaiting Payment must include both services and ignore legacy adjustments.');

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
    if ($previousSecretKey === false) {
        putenv('XPAY_SECRET_KEY');
    } else {
        putenv('XPAY_SECRET_KEY=' . $previousSecretKey);
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
