<?php
// The external payment redirect must not create or replace the clinic session.
define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/payment_return.php';
require_once __DIR__ . '/includes/user_language.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$token = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';
$returnState = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
try {
    $paymentStatus = paymentReturnStatus($pdo, $token, $returnState);
} catch (Throwable $e) {
    error_log('Payment return unavailable: ' . $e->getMessage());
    $paymentStatus = ['state'=>'unavailable','terminal'=>true,'title'=>'Payment status is temporarily unavailable',
        'message'=>'Please check again shortly.','amount'=>null,'request_url'=>'clinic_dashboard.php','request_status'=>null];
}
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= userLanguageAttribute() ?>" dir="<?= userDirectionAttribute() ?>" data-i18n-title="payment_status_title">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/user-i18n.css">
</head>
<body class="min-h-screen bg-[#f4f8fb] font-['Outfit'] text-slate-800">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
        <section class="w-full rounded-3xl border border-slate-200 bg-white p-7 text-center shadow-xl shadow-slate-200/50 sm:p-10"
            id="payment-return" data-token="<?= $escape($token) ?>" data-return-state="<?= $escape($returnState) ?>" data-status="<?= $escape($paymentStatus['state']) ?>" data-terminal="<?= $paymentStatus['terminal'] ? 'true' : 'false' ?>">
            <div id="payment-icon" class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 text-2xl text-[#1d5f8c]" aria-hidden="true"><?= $paymentStatus['state'] === 'paid' ? '✓' : ($paymentStatus['state'] === 'pending' ? '…' : '!') ?></div>
            <div role="status" aria-live="polite" aria-atomic="true">
                <h1 id="payment-title" class="text-2xl font-extrabold text-[#13324a]"><?= $escape($paymentStatus['title']) ?></h1>
                <p id="payment-message" class="mt-3 text-sm leading-6 text-slate-500"><?= $escape($paymentStatus['message']) ?></p>
            </div>
            <div id="payment-amount-box" class="<?= $paymentStatus['amount'] === null ? 'hidden ' : '' ?>mt-6 rounded-2xl border border-slate-100 bg-slate-50 px-5 py-4 text-start">
                <div class="flex items-center justify-between gap-4 text-sm"><span class="font-semibold text-slate-500">Amount</span><span id="payment-amount" class="font-extrabold text-[#13324a]"><?= $escape($paymentStatus['amount']) ?></span></div>
            </div>
            <p id="payment-check-status" class="mt-4 text-xs leading-5 text-slate-500" role="status"></p>
            <?php if ($paymentStatus['state'] !== 'missing'): ?><button id="payment-check" type="button" class="mt-4 rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-[#1d5f8c] disabled:opacity-50">Check again</button><?php endif; ?>
            <a id="payment-return-link" href="<?= $escape($paymentStatus['request_url']) ?>" class="mt-7 inline-flex w-full items-center justify-center rounded-xl bg-[#1d5f8c] px-5 py-3.5 text-sm font-bold text-white transition hover:bg-[#13324a]">
                <?= $paymentStatus['state'] === 'missing' ? 'Open clinic dashboard' : 'Return to request' ?>
            </a>
            <p class="mt-4 text-xs leading-5 text-slate-400">If your session has expired, sign in to return to this request.</p>
            <noscript><p class="mt-4 text-sm text-slate-500">Reload this page to check the latest payment status.</p></noscript>
            <div class="mt-5 flex justify-center"><?php $userLanguageSwitcherCompact = false; require __DIR__ . '/includes/user_language_switcher.php'; ?></div>
        </section>
    </main>
    <script src="js/translations.js"></script>
    <script src="js/user-page-translations.js"></script>
    <script src="js/main.js"></script>
    <script src="js/payment-return.js"></script>
</body>
</html>
