<?php

define('EASYIMPLANT_SKIP_SESSION', true);
require_once 'includes/db_connect.php';
require_once 'includes/surgical_guide_pricing.php';

$token = trim((string) ($_GET['token'] ?? ''));
$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$returnState = trim((string) ($_GET['state'] ?? ''));
$checkout = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $sql = 'SELECT request_id, user_id, xpay_session_id, status, payment_status, amount_minor, currency
        FROM xpay_checkout_sessions WHERE return_token = :token';
    $params = [':token' => $token];
    if ($sessionId !== '') {
        $sql .= ' AND xpay_session_id = :session_id';
        $params[':session_id'] = $sessionId;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    $checkout = $stmt->fetch();
}

$isPaid = $checkout && $checkout['payment_status'] === 'paid';
$isFailed = !$isPaid && $returnState === 'failed';
$requestUrl = $checkout ? 'view_request.php?id=' . (int) $checkout['request_id'] : 'clinic_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Status | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#f4f8fb] font-['Outfit'] text-slate-800">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
        <section class="w-full rounded-3xl border border-slate-200 bg-white p-7 text-center shadow-xl shadow-slate-200/50 sm:p-10">
            <?php if (!$checkout): ?>
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-2xl text-red-600">!</div>
                <h1 class="text-2xl font-extrabold text-[#13324a]">Payment reference not found</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">Open your clinic dashboard to review the request and try again.</p>
            <?php elseif ($isPaid): ?>
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-2xl text-emerald-600">✓</div>
                <h1 class="text-2xl font-extrabold text-[#13324a]">Payment confirmed</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">XPay confirmed your payment for request #<?= (int) $checkout['request_id'] ?>. The request is now ready for production.</p>
            <?php elseif ($isFailed): ?>
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 text-2xl text-amber-600">×</div>
                <h1 class="text-2xl font-extrabold text-[#13324a]">Payment was not completed</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">No payment was confirmed. You can return to the request and start a new attempt.</p>
            <?php else: ?>
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 text-2xl text-blue-600">…</div>
                <h1 class="text-2xl font-extrabold text-[#13324a]">Payment is being confirmed</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">Your checkout has returned to Easy Implant. The request will update only after XPay sends the signed confirmation.</p>
            <?php endif; ?>

            <?php if ($checkout): ?>
                <div class="mt-6 rounded-2xl border border-slate-100 bg-slate-50 px-5 py-4 text-left">
                    <div class="flex items-center justify-between gap-4 text-sm">
                        <span class="font-semibold text-slate-500">Amount</span>
                        <span class="font-extrabold text-[#13324a]"><?= htmlspecialchars(formatCurrencyMoney(((int) $checkout['amount_minor']) / 100, (string) $checkout['currency'])) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($requestUrl) ?>" class="mt-7 inline-flex w-full items-center justify-center rounded-xl bg-[#1d5f8c] px-5 py-3.5 text-sm font-bold text-white transition hover:bg-[#13324a]">
                Return to request
            </a>
            <p class="mt-4 text-xs leading-5 text-slate-400">If your login is not restored automatically, sign in again and open the request from your dashboard.</p>
        </section>
    </main>
</body>
</html>
