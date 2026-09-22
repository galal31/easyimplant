<?php

require_once __DIR__ . '/request_workflow.php';

const XPAY_API_BASE_URL = 'https://api.xpay.app';
const XPAY_WEBHOOK_TOLERANCE_SECONDS = 300;

function xpayResolveConfig(array $localConfig): array
{
    $value = static function (string $key) use ($localConfig): string {
        $localValue = trim((string) ($localConfig[$key] ?? ''));

        return $localValue !== ''
            ? $localValue
            : trim((string) (getenv($key) ?: ''));
    };

    return [
        'secret_key' => $value('XPAY_SECRET_KEY'),
        'webhook_secret' => $value('XPAY_WEBHOOK_SECRET'),
        'app_url' => rtrim($value('XPAY_APP_URL'), '/'),
    ];
}

function xpayConfig(): array
{
    $localConfigPath = defined('XPAY_LOCAL_CONFIG_PATH')
        ? (string) constant('XPAY_LOCAL_CONFIG_PATH')
        : dirname(__DIR__) . '/config/xpay.local.php';
    $localConfig = [];

    if (is_file($localConfigPath)) {
        $loadedConfig = require $localConfigPath;
        if (is_array($loadedConfig)) {
            $localConfig = $loadedConfig;
        }
    }

    return xpayResolveConfig($localConfig);
}

function xpayIsConfigured(): bool
{
    $config = xpayConfig();
    if ($config['secret_key'] === '' || $config['webhook_secret'] === '' || $config['app_url'] === '') {
        return false;
    }
    if (!str_starts_with($config['secret_key'], 'sk_test_') && !str_starts_with($config['secret_key'], 'sk_live_')) {
        return false;
    }

    $url = parse_url($config['app_url']);
    if (!is_array($url) || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host'])) {
        return false;
    }

    return !str_starts_with($config['secret_key'], 'sk_live_') || ($url['scheme'] ?? '') === 'https';
}

function xpayUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-'
        . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-'
        . substr($hex, 20);
}

function xpayDecimalToMinor(string $amount): int
{
    $amount = trim($amount);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
        throw new InvalidArgumentException('Invalid payment amount.');
    }

    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    if ($minor < 1) {
        throw new DomainException('This request has no payable balance. Please contact support.');
    }

    return $minor;
}

function xpayIsTrustedCheckoutUrl(string $url): bool
{
    $parts = parse_url($url);

    return is_array($parts)
        && ($parts['scheme'] ?? '') === 'https'
        && strtolower((string) ($parts['host'] ?? '')) === 'checkout.xpay.app';
}

function xpayBuildReturnUrl(string $returnToken): string
{
    $baseUrl = xpayConfig()['app_url'];

    return $baseUrl . '/xpay_return?token=' . rawurlencode($returnToken)
        . '&session_id={CHECKOUT_SESSION_ID}';
}

function xpayBuildCancelUrl(string $returnToken): string
{
    $baseUrl = xpayConfig()['app_url'];

    return $baseUrl . '/xpay_return?token=' . rawurlencode($returnToken) . '&state=failed';
}

function xpayCreateCheckoutPayload(array $request, array $clinic, int $amountMinor, string $returnToken): array
{
    return [
        'mode' => 'payment',
        'uiMode' => 'hosted',
        'submitType' => 'pay',
        'afterCompletion' => [
            'type' => 'redirect',
            'redirect' => ['url' => xpayBuildReturnUrl($returnToken)],
        ],
        'cancelUrl' => xpayBuildCancelUrl($returnToken),
        'customerCreation' => 'always',
        'customerDetails' => [
            'name' => (string) $clinic['full_name'],
            'email' => (string) $clinic['email'],
        ],
        'lineItems' => [[
            'priceData' => [
                'currency' => 'EGP',
                'unitAmount' => $amountMinor,
                'productData' => [
                    'name' => 'Surgical Guide Request #' . (int) $request['id'],
                    'description' => 'Easy Implant surgical guide service',
                ],
            ],
            'quantity' => 1,
        ]],
        'metadata' => [
            'request_id' => (string) (int) $request['id'],
            'clinic_id' => (string) (int) $request['user_id'],
        ],
        'expiresAfterMinutes' => 30,
    ];
}

function xpayApiRequest(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
{
    $secretKey = xpayConfig()['secret_key'];
    if ($secretKey === '') {
        throw new RuntimeException('XPay is not configured.');
    }

    $body = $payload === null
        ? null
        : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $headers = array_merge([
        'Authorization: Bearer ' . $secretKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ], $extraHeaders);

    $curl = curl_init(XPAY_API_BASE_URL . $path);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the XPay connection.');
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($responseBody === false) {
        throw new RuntimeException('XPay connection failed: ' . $curlError);
    }

    try {
        $response = json_decode((string) $responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('XPay returned an unreadable response.');
    }

    if ($statusCode < 200 || $statusCode >= 300 || !is_array($response)) {
        $errorCode = is_array($response) ? trim((string) ($response['error']['code'] ?? '')) : '';
        throw new RuntimeException('XPay request failed with HTTP ' . $statusCode . ($errorCode !== '' ? ' (' . $errorCode . ')' : '') . '.');
    }

    return $response;
}

function xpayCreateCheckoutSession(array $payload, string $idempotencyKey): array
{
    return xpayApiRequest('POST', '/checkout/sessions', $payload, [
        'Idempotency-Key: ' . $idempotencyKey,
    ]);
}

function xpayVerifyWebhook(string $rawBody, string $signatureHeader, ?int $now = null): array
{
    $secret = xpayConfig()['webhook_secret'];
    if ($secret === '' || $rawBody === '' || $signatureHeader === '') {
        throw new UnexpectedValueException('Missing XPay webhook signature data.');
    }

    $parts = [];
    foreach (explode(',', $signatureHeader) as $piece) {
        [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
        if ($key !== '' && !array_key_exists($key, $parts)) {
            $parts[$key] = $value;
        }
    }

    if (!isset($parts['t'], $parts['v1']) || !ctype_digit($parts['t']) || !preg_match('/^[a-f0-9]{64}$/i', $parts['v1'])) {
        throw new UnexpectedValueException('Malformed XPay webhook signature.');
    }

    $timestamp = (int) $parts['t'];
    $now ??= time();
    if (abs($now - $timestamp) > XPAY_WEBHOOK_TOLERANCE_SECONDS) {
        throw new UnexpectedValueException('XPay webhook timestamp is outside the allowed window.');
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    if (!hash_equals($expected, strtolower($parts['v1']))) {
        throw new UnexpectedValueException('Invalid XPay webhook signature.');
    }

    $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($event)) {
        throw new UnexpectedValueException('Invalid XPay webhook payload.');
    }

    return $event;
}

function xpayObjectId(array|string|null $value): ?string
{
    if (is_string($value)) {
        return $value !== '' ? $value : null;
    }
    if (is_array($value)) {
        $id = trim((string) ($value['id'] ?? ''));
        return $id !== '' ? $id : null;
    }

    return null;
}

function xpayProcessWebhookEvent(PDO $pdo, array $event): array
{
    $eventId = trim((string) ($event['id'] ?? ''));
    $eventType = trim((string) ($event['type'] ?? ''));
    $sessionObject = $event['data']['object'] ?? null;
    if ($eventId === '' || $eventType === '' || !is_array($sessionObject)) {
        throw new UnexpectedValueException('Incomplete XPay event.');
    }

    $xpaySessionId = trim((string) ($sessionObject['id'] ?? ''));
    if (!str_starts_with($eventType, 'checkout.session.') || $xpaySessionId === '') {
        return ['status' => 'ignored'];
    }

    $payloadHash = hash('sha256', json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $successEvents = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    $pdo->beginTransaction();
    try {
        $insertEvent = $pdo->prepare("INSERT IGNORE INTO xpay_webhook_events
            (event_id, event_type, xpay_session_id, payload_sha256, processing_status)
            VALUES (:event_id, :event_type, :session_id, :payload_hash, 'received')");
        $insertEvent->execute([
            ':event_id' => $eventId,
            ':event_type' => $eventType,
            ':session_id' => $xpaySessionId,
            ':payload_hash' => $payloadHash,
        ]);
        if ($insertEvent->rowCount() === 0) {
            $pdo->commit();
            return ['status' => 'duplicate'];
        }

        $sessionStmt = $pdo->prepare('SELECT * FROM xpay_checkout_sessions WHERE xpay_session_id = :session_id FOR UPDATE');
        $sessionStmt->execute([':session_id' => $xpaySessionId]);
        $storedSession = $sessionStmt->fetch();
        if (!$storedSession) {
            $pdo->prepare("UPDATE xpay_webhook_events SET processing_status = 'ignored', processed_at = NOW(), error_message = :error WHERE event_id = :event_id")
                ->execute([':error' => 'Unknown checkout session.', ':event_id' => $eventId]);
            $pdo->commit();
            return ['status' => 'unknown_session'];
        }

        $sessionStatus = trim((string) ($sessionObject['status'] ?? $storedSession['status']));
        $paymentStatus = trim((string) ($sessionObject['paymentStatus'] ?? $storedSession['payment_status']));
        $paymentIntentId = xpayObjectId($sessionObject['paymentIntent'] ?? null);
        $customerId = xpayObjectId($sessionObject['customer'] ?? null);

        $updateSession = $pdo->prepare('UPDATE xpay_checkout_sessions
            SET status = :status,
                payment_status = :payment_status,
                xpay_payment_intent_id = COALESCE(:payment_intent_id, xpay_payment_intent_id),
                xpay_customer_id = COALESCE(:customer_id, xpay_customer_id),
                updated_at = NOW()
            WHERE id = :id');
        $updateSession->execute([
            ':status' => $sessionStatus !== '' ? $sessionStatus : $storedSession['status'],
            ':payment_status' => $paymentStatus !== '' ? $paymentStatus : $storedSession['payment_status'],
            ':payment_intent_id' => $paymentIntentId,
            ':customer_id' => $customerId,
            ':id' => $storedSession['id'],
        ]);

        if (!in_array($eventType, $successEvents, true) || $paymentStatus !== 'paid') {
            $pdo->prepare("UPDATE xpay_webhook_events SET processing_status = 'processed', processed_at = NOW() WHERE event_id = :event_id")
                ->execute([':event_id' => $eventId]);
            $pdo->commit();
            return ['status' => 'recorded'];
        }

        $amountTotal = filter_var($sessionObject['amountTotal'] ?? null, FILTER_VALIDATE_INT);
        $currency = strtoupper(trim((string) ($sessionObject['currency'] ?? '')));
        $metadata = is_array($sessionObject['metadata'] ?? null) ? $sessionObject['metadata'] : [];
        $livemode = !empty($sessionObject['livemode']) ? 1 : 0;
        $validationError = null;

        if ($amountTotal === false || (int) $amountTotal !== (int) $storedSession['amount_minor']) {
            $validationError = 'Paid amount does not match the stored checkout amount.';
        } elseif ($currency !== strtoupper((string) $storedSession['currency'])) {
            $validationError = 'Paid currency does not match the stored checkout currency.';
        } elseif ((int) ($metadata['request_id'] ?? 0) !== (int) $storedSession['request_id']
            || (int) ($metadata['clinic_id'] ?? 0) !== (int) $storedSession['user_id']) {
            $validationError = 'Checkout metadata does not match the stored request.';
        } elseif ($livemode !== (int) $storedSession['livemode']) {
            $validationError = 'Checkout mode does not match the stored session mode.';
        } elseif ($paymentIntentId === null) {
            $validationError = 'The paid checkout has no Payment Intent ID.';
        }

        $requestStmt = $pdo->prepare("SELECT r.status, r.service_type, sgd.total_price
            FROM requests r
            JOIN surgical_guide_details sgd ON sgd.request_id = r.id
            WHERE r.id = :request_id AND r.user_id = :user_id
            FOR UPDATE");
        $requestStmt->execute([
            ':request_id' => $storedSession['request_id'],
            ':user_id' => $storedSession['user_id'],
        ]);
        $request = $requestStmt->fetch();
        if (!$request || $request['service_type'] !== 'surgical_guide') {
            $validationError = 'The related Surgical Guide request was not found.';
        } elseif (xpayDecimalToMinor((string) $request['total_price']) !== (int) $storedSession['amount_minor']) {
            $validationError = 'The request total no longer matches the stored checkout amount.';
        }

        if ($validationError !== null) {
            $pdo->prepare("UPDATE xpay_webhook_events SET processing_status = 'rejected', processed_at = NOW(), error_message = :error WHERE event_id = :event_id")
                ->execute([':error' => $validationError, ':event_id' => $eventId]);
            $pdo->commit();
            error_log('XPay webhook rejected for session ' . $xpaySessionId . ': ' . $validationError);
            return ['status' => 'rejected'];
        }

        $amount = number_format(((int) $storedSession['amount_minor']) / 100, 2, '.', '');
        $insertPayment = $pdo->prepare("INSERT IGNORE INTO payments
            (request_id, user_id, receipt_file_path, amount, status, payment_source, currency,
             provider_session_id, provider_payment_intent_id, approved_at)
            VALUES (:request_id, :user_id, NULL, :amount, 'approved', 'xpay', :currency,
                    :provider_session_id, :provider_payment_intent_id, NOW())");
        $insertPayment->execute([
            ':request_id' => $storedSession['request_id'],
            ':user_id' => $storedSession['user_id'],
            ':amount' => $amount,
            ':currency' => $currency,
            ':provider_session_id' => $xpaySessionId,
            ':provider_payment_intent_id' => $paymentIntentId,
        ]);
        if ($insertPayment->rowCount() === 0) {
            $existingPaymentStmt = $pdo->prepare("SELECT request_id, user_id, amount, currency, status, payment_source
                FROM payments WHERE provider_session_id = :provider_session_id LIMIT 1");
            $existingPaymentStmt->execute([':provider_session_id' => $xpaySessionId]);
            $existingPayment = $existingPaymentStmt->fetch();
            $sameApprovedPayment = $existingPayment
                && (int) $existingPayment['request_id'] === (int) $storedSession['request_id']
                && (int) $existingPayment['user_id'] === (int) $storedSession['user_id']
                && xpayDecimalToMinor((string) $existingPayment['amount']) === (int) $storedSession['amount_minor']
                && strtoupper((string) $existingPayment['currency']) === $currency
                && $existingPayment['status'] === 'approved'
                && $existingPayment['payment_source'] === 'xpay';
            if (!$sameApprovedPayment) {
                $validationError = 'The provider identifiers conflict with an existing payment.';
                $pdo->prepare("UPDATE xpay_webhook_events SET processing_status = 'rejected', processed_at = NOW(), error_message = :error WHERE event_id = :event_id")
                    ->execute([':error' => $validationError, ':event_id' => $eventId]);
                $pdo->commit();
                error_log('XPay webhook rejected for session ' . $xpaySessionId . ': ' . $validationError);
                return ['status' => 'rejected'];
            }
        }

        if ($request['status'] === 'pending_payment'
            && surgicalGuideTransitionIsAllowed('pending_payment', 'in_progress', 'gateway_payment_confirmation')) {
            $pdo->prepare("UPDATE requests SET status = 'in_progress' WHERE id = :request_id AND status = 'pending_payment'")
                ->execute([':request_id' => $storedSession['request_id']]);
            $pdo->prepare("INSERT INTO request_activity_logs
                (request_id, actor_id, actor_role, action, old_value, new_value, note)
                VALUES (:request_id, NULL, 'gateway', 'xpay_payment_confirmed', 'pending_payment', 'in_progress', :note)")
                ->execute([
                    ':request_id' => $storedSession['request_id'],
                    ':note' => 'XPay confirmed payment for checkout session ' . $xpaySessionId . '.',
                ]);
        } elseif (!in_array($request['status'], ['in_progress', 'completed'], true)) {
            $pdo->prepare("INSERT INTO request_activity_logs
                (request_id, actor_id, actor_role, action, old_value, new_value, note)
                VALUES (:request_id, NULL, 'gateway', 'xpay_payment_requires_review', :status, :status, :note)")
                ->execute([
                    ':request_id' => $storedSession['request_id'],
                    ':status' => $request['status'],
                    ':note' => 'XPay confirmed payment after the request left the payable stage. Manual review is required.',
                ]);
        }

        $pdo->prepare('UPDATE xpay_checkout_sessions
            SET status = :status, payment_status = \'paid\', xpay_payment_intent_id = :payment_intent_id,
                xpay_customer_id = COALESCE(:customer_id, xpay_customer_id), paid_at = COALESCE(paid_at, NOW()), updated_at = NOW()
            WHERE id = :id')
            ->execute([
                ':status' => $sessionStatus !== '' ? $sessionStatus : 'complete',
                ':payment_intent_id' => $paymentIntentId,
                ':customer_id' => $customerId,
                ':id' => $storedSession['id'],
            ]);
        $pdo->prepare("UPDATE xpay_webhook_events SET processing_status = 'processed', processed_at = NOW() WHERE event_id = :event_id")
            ->execute([':event_id' => $eventId]);

        $pdo->commit();
        return ['status' => 'paid'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
