<?php

require_once '../includes/db_connect.php';
require_once '../includes/xpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'clinic') {
    header('Location: ../login.php', true, 303);
    exit;
}

$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
$fallbackUrl = '../clinic_dashboard.php';
if ($requestId) {
    $fallbackUrl = '../view_request.php?id=' . (int) $requestId;
}
$fallbackSeparator = str_contains($fallbackUrl, '?') ? '&' : '?';

if (!$requestId || !requestWorkflowCsrfIsValid($csrfToken)) {
    header('Location: ' . $fallbackUrl . $fallbackSeparator . 'xpay_error=session', true, 303);
    exit;
}
if (!xpayIsConfigured()) {
    header('Location: ' . $fallbackUrl . $fallbackSeparator . 'xpay_error=configuration', true, 303);
    exit;
}

$checkoutRecordId = null;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT r.id, r.user_id, r.service_type, r.status, sgd.total_price,
            u.full_name, u.email
        FROM requests r
        JOIN surgical_guide_details sgd ON sgd.request_id = r.id
        JOIN users u ON u.id = r.user_id
        WHERE r.id = :request_id AND r.user_id = :user_id
        FOR UPDATE");
    $stmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $_SESSION['user_id'],
    ]);
    $request = $stmt->fetch();
    if (!$request || $request['service_type'] !== 'surgical_guide') {
        throw new DomainException('This payment request is not available.');
    }
    if ($request['status'] !== 'pending_payment') {
        throw new DomainException('This request is no longer waiting for payment.');
    }

    $amountMinor = xpayDecimalToMinor((string) $request['total_price']);
    $existingStmt = $pdo->prepare("SELECT *,
            (expires_at IS NOT NULL AND expires_at > UTC_TIMESTAMP()) AS is_unexpired,
            (created_at > NOW() - INTERVAL 24 HOUR) AS is_recent
        FROM xpay_checkout_sessions
        WHERE request_id = :request_id AND payment_status <> 'paid'
        ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $existingStmt->execute([':request_id' => $requestId]);
    $checkout = $existingStmt->fetch();

    if ($checkout
        && $checkout['status'] === 'open'
        && !empty($checkout['checkout_url'])
        && (int) $checkout['is_unexpired'] === 1
        && xpayIsTrustedCheckoutUrl((string) $checkout['checkout_url'])) {
        $pdo->commit();
        header('Location: ' . $checkout['checkout_url'], true, 303);
        exit;
    }

    $canRetryExisting = $checkout
        && in_array($checkout['status'], ['creating', 'failed'], true)
        && (int) $checkout['amount_minor'] === $amountMinor
        && strtoupper((string) $checkout['currency']) === 'EGP'
        && (int) $checkout['is_recent'] === 1;

    if (!$canRetryExisting) {
        $insert = $pdo->prepare("INSERT INTO xpay_checkout_sessions
            (request_id, user_id, idempotency_key, return_token, status, payment_status,
             amount_minor, currency, livemode)
            VALUES (:request_id, :user_id, :idempotency_key, :return_token, 'creating', 'unpaid',
                    :amount_minor, 'EGP', :livemode)");
        $insert->execute([
            ':request_id' => $requestId,
            ':user_id' => $_SESSION['user_id'],
            ':idempotency_key' => xpayUuidV4(),
            ':return_token' => bin2hex(random_bytes(32)),
            ':amount_minor' => $amountMinor,
            ':livemode' => str_starts_with(xpayConfig()['secret_key'], 'sk_live_') ? 1 : 0,
        ]);
        $checkoutRecordId = (int) $pdo->lastInsertId();
        $checkoutStmt = $pdo->prepare('SELECT * FROM xpay_checkout_sessions WHERE id = :id');
        $checkoutStmt->execute([':id' => $checkoutRecordId]);
        $checkout = $checkoutStmt->fetch();
    } else {
        $checkoutRecordId = (int) $checkout['id'];
        $pdo->prepare("UPDATE xpay_checkout_sessions SET status = 'creating', last_error = NULL, updated_at = NOW() WHERE id = :id")
            ->execute([':id' => $checkoutRecordId]);
    }
    $pdo->commit();

    $payload = xpayCreateCheckoutPayload(
        ['id' => $request['id'], 'user_id' => $request['user_id']],
        ['full_name' => $request['full_name'], 'email' => $request['email']],
        $amountMinor,
        (string) $checkout['return_token']
    );
    $xpaySession = xpayCreateCheckoutSession($payload, (string) $checkout['idempotency_key']);

    $xpaySessionId = trim((string) ($xpaySession['id'] ?? ''));
    $checkoutUrl = trim((string) ($xpaySession['url'] ?? ''));
    $responseAmount = filter_var($xpaySession['amountTotal'] ?? null, FILTER_VALIDATE_INT);
    $responseCurrency = strtoupper(trim((string) ($xpaySession['currency'] ?? '')));
    $responseLivemode = !empty($xpaySession['livemode']) ? 1 : 0;
    if ($xpaySessionId === '' || !str_starts_with($xpaySessionId, 'cs_') || !xpayIsTrustedCheckoutUrl($checkoutUrl)) {
        throw new RuntimeException('XPay returned an invalid checkout session.');
    }
    if ($responseAmount === false || (int) $responseAmount !== $amountMinor || $responseCurrency !== 'EGP') {
        throw new RuntimeException('XPay returned a checkout total that does not match this request.');
    }
    if ($responseLivemode !== (int) $checkout['livemode']) {
        throw new RuntimeException('XPay returned a checkout session in the wrong mode.');
    }

    $expiresAt = gmdate('Y-m-d H:i:s', time() + 1800);
    if (!empty($xpaySession['expiresAt'])) {
        try {
            $expiresAt = (new DateTimeImmutable((string) $xpaySession['expiresAt']))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            // The requested XPay expiry is 30 minutes; retain the safe local fallback.
        }
    }
    $update = $pdo->prepare("UPDATE xpay_checkout_sessions
        SET xpay_session_id = :xpay_session_id, checkout_url = :checkout_url,
            status = :status, payment_status = :payment_status, expires_at = :expires_at,
            last_error = NULL, updated_at = NOW()
        WHERE id = :id");
    $update->execute([
        ':xpay_session_id' => $xpaySessionId,
        ':checkout_url' => $checkoutUrl,
        ':status' => trim((string) ($xpaySession['status'] ?? 'open')) ?: 'open',
        ':payment_status' => trim((string) ($xpaySession['paymentStatus'] ?? 'unpaid')) ?: 'unpaid',
        ':expires_at' => $expiresAt,
        ':id' => $checkoutRecordId,
    ]);

    header('Location: ' . $checkoutUrl, true, 303);
    exit;
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . $fallbackUrl . $fallbackSeparator . 'xpay_error=not_payable', true, 303);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($checkoutRecordId) {
        try {
            $pdo->prepare("UPDATE xpay_checkout_sessions SET status = 'failed', last_error = :error, updated_at = NOW() WHERE id = :id")
                ->execute([
                    ':error' => substr($e->getMessage(), 0, 500),
                    ':id' => $checkoutRecordId,
                ]);
        } catch (Throwable) {
            // Preserve the original checkout failure.
        }
    }
    error_log('Create XPay checkout error: ' . $e->getMessage());
    header('Location: ' . $fallbackUrl . $fallbackSeparator . 'xpay_error=unavailable', true, 303);
    exit;
}
