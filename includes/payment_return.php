<?php

require_once __DIR__ . '/surgical_guide_pricing.php';

function paymentReturnStatus(PDO $pdo, string $token, string $returnState = ''): array
{
    $result = ['state'=>'missing', 'terminal'=>true, 'title'=>'Payment reference not found',
        'message'=>'Open your clinic dashboard to review your requests.', 'amount'=>null,
        'request_url'=>'clinic_dashboard.php', 'request_status'=>null];
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return $result;
    $stmt = $pdo->prepare("SELECT c.request_id,c.status,c.payment_status,c.amount_minor,c.currency,
        r.status AS request_status,
        (c.expires_at IS NOT NULL AND c.expires_at <= UTC_TIMESTAMP()) AS is_expired,
        EXISTS(SELECT 1 FROM payments p WHERE p.request_id=c.request_id AND p.user_id=c.user_id
            AND p.status='approved' AND p.payment_source='xpay' AND p.provider_session_id=c.xpay_session_id
            AND p.amount=c.amount_minor/100 AND p.currency=c.currency) AS confirmed,
        (SELECT e.event_type FROM xpay_webhook_events e WHERE e.xpay_session_id=c.xpay_session_id
            AND e.processing_status='processed' ORDER BY e.received_at DESC,e.event_id DESC LIMIT 1) AS last_event,
        EXISTS(SELECT 1 FROM xpay_webhook_events e WHERE e.xpay_session_id=c.xpay_session_id
            AND e.processing_status='rejected') AS needs_review
        FROM xpay_checkout_sessions c JOIN requests r ON r.id=c.request_id AND r.user_id=c.user_id
        WHERE c.return_token=? LIMIT 1");
    $stmt->execute([$token]);
    $checkout = $stmt->fetch();
    if (!$checkout) return $result;
    $result['amount'] = formatCurrencyMoney(((int) $checkout['amount_minor']) / 100, $checkout['currency']);
    $result['request_url'] = 'view_request.php?id=' . (int) $checkout['request_id'];
    $result['request_status'] = $checkout['request_status'];
    if ($checkout['confirmed']) {
        $result['state'] = 'paid';
        $result['title'] = 'Payment confirmed';
        $result['message'] = match ($checkout['request_status']) {
            'in_progress'=>'Payment completed. Your request is in progress.',
            'completed'=>'Payment completed. Your request is completed.',
            default=>'Payment completed. Return to the request to review its current status.',
        };
    } elseif ($checkout['needs_review']) {
        $result['state'] = 'review';
        $result['title'] = 'Payment confirmation needs review';
        $result['message'] = 'We could not verify the payment details. Contact support before making another payment.';
    } elseif ($checkout['last_event'] === 'checkout.session.async_payment_failed' || $checkout['status'] === 'failed') {
        $result['state'] = 'failed';
        $result['title'] = 'Payment was not completed';
        $result['message'] = 'This attempt was not completed. Return to your request to review the payment options.';
    } elseif ($checkout['status'] === 'expired' || ($checkout['is_expired'] && $checkout['status'] !== 'complete')) {
        $result['state'] = 'expired';
        $result['title'] = 'Payment session expired';
        $result['message'] = 'No payment has been confirmed for this session. Return to your request to review the payment options.';
    } elseif ($returnState === 'failed') {
        $result['state'] = 'cancelled';
        $result['terminal'] = false;
        $result['title'] = 'You returned before payment confirmation';
        $result['message'] = 'No payment has been confirmed yet. Use Check again if you completed a payment before returning.';
    } else {
        $result['state'] = 'pending';
        $result['terminal'] = false;
        $result['title'] = 'Payment is being verified';
        $result['message'] = 'We are checking for payment confirmation. This page updates automatically.';
    }
    return $result;
}
