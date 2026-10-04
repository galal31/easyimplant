<?php
/**
 * includes/surgeon_workspace.php
 * Clinic-facing redesigned workspace for surgeon_request.
 * Replaces surgeon_request_guidance + inline layout in view_request.php.
 * All backend logic (payment, chat, permissions) is unchanged.
 */
if (!isset($details) || !is_array($details)) return;

$swStatus   = $request['status'];
$swEsc      = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$swIsReady  = surgeonOperationIsReady($details);
$swHasPrice = (float)($details['total_price'] ?? 0) > 0;
$swCanPay   = $swStatus === 'pending_payment' && $swIsReady && $swHasPrice;

// ── Status badge meta ──────────────────────────────────────────────
$swBadgeMeta = [
    'pending_review'   => ['label'=>'Review & coordination',      'i18n'=>'status_review_coordination',      'color'=>'bg-amber-50 text-amber-700  border-amber-200'],
    'pending_payment'  => ['label'=>'Awaiting your payment',       'i18n'=>'status_pending_payment',           'color'=>'bg-orange-50 text-orange-700 border-orange-200'],
    'in_progress'      => ['label'=>'Paid — awaiting operation',   'i18n'=>'status_paid_waiting_operation',    'color'=>'bg-indigo-50 text-indigo-700 border-indigo-200'],
    'completed'        => ['label'=>'Operation performed',         'i18n'=>'status_operation_performed',       'color'=>'bg-emerald-50 text-emerald-700 border-emerald-200'],
    'rejected'         => ['label'=>'Rejected',                    'i18n'=>'status_rejected',                  'color'=>'bg-red-50 text-red-700 border-red-200'],
    'cancelled'        => ['label'=>'Cancelled — financial review','i18n'=>'status_cancelled_financial',       'color'=>'bg-red-50 text-red-700 border-red-200'],
    'contacted'        => ['label'=>'Previous coordination status','i18n'=>'status_previous_coordination',    'color'=>'bg-slate-100 text-slate-600 border-slate-300'],
];
$swBadge = $swBadgeMeta[$swStatus] ?? ['label'=>ucfirst($swStatus),'i18n'=>'status_unknown','color'=>'bg-slate-100 text-slate-600 border-slate-300'];

// ── Journey steps ──────────────────────────────────────────────────
$swJourneySteps = [
    ['key'=>'pending_review',  'label'=>'Review & coordination', 'i18n'=>'step_review_coordination'],
    ['key'=>'pending_payment', 'label'=>'Your payment',          'i18n'=>'step_your_payment'],
    ['key'=>'in_progress',     'label'=>'Awaiting operation',    'i18n'=>'step_awaiting_operation'],
    ['key'=>'completed',       'label'=>'Operation performed',   'i18n'=>'step_operation_performed'],
];
$swJourneyOrder = ['pending_review'=>0,'pending_payment'=>1,'in_progress'=>2,'completed'=>3];
$swCurrentStepIdx = $swJourneyOrder[$swStatus] ?? -1;
$swIsStopped = in_array($swStatus, ['rejected','cancelled'], true);

// ── Action card content ────────────────────────────────────────────
$swActionCards = [
    'pending_review' => [
        'icon'  => 'fa-magnifying-glass',
        'color' => 'bg-blue-50 border-blue-200',
        'head'  => 'bg-blue-100',
        'title_i18n' => 'action_reviewing_title',
        'title' => 'Under review — coordination in progress',
        'body_i18n'  => 'action_reviewing_body',
        'body'  => 'Easy Implant is reviewing the case and coordinating the surgeon, appointment and final price with your clinic. Follow the conversation and send any missing details or questions.',
    ],
    'pending_payment' => [
        'icon'  => 'fa-credit-card',
        'color' => 'bg-emerald-50 border-emerald-200',
        'head'  => 'bg-emerald-100',
        'title_i18n' => 'action_pay_title',
        'title' => 'Ready for payment',
        'body_i18n'  => 'action_pay_body',
        'body'  => 'The surgeon, appointment and final price are confirmed. Review the details and complete your payment.',
    ],
    'in_progress' => [
        'icon'  => 'fa-circle-check',
        'color' => 'bg-indigo-50 border-indigo-200',
        'head'  => 'bg-indigo-100',
        'title_i18n' => 'action_inprogress_title',
        'title' => 'Payment confirmed — awaiting operation',
        'body_i18n'  => 'action_inprogress_body',
        'body'  => 'Payment has been confirmed. Check the confirmed appointment and use the conversation for any remaining arrangements.',
    ],
    'completed' => [
        'icon'  => 'fa-flag-checkered',
        'color' => 'bg-emerald-50 border-emerald-200',
        'head'  => 'bg-emerald-100',
        'title_i18n' => 'action_completed_title',
        'title' => 'Operation completed',
        'body_i18n'  => 'action_completed_body',
        'body'  => 'Administration has marked this operation as completed. Review the recorded date and completion note below.',
    ],
    'rejected' => [
        'icon'  => 'fa-circle-xmark',
        'color' => 'bg-red-50 border-red-200',
        'head'  => 'bg-red-100',
        'title_i18n' => 'action_rejected_title',
        'title' => 'Request rejected',
        'body_i18n'  => 'action_rejected_body',
        'body'  => 'This request has been declined. Read the rejection reason below. The conversation is now read-only.',
    ],
    'cancelled' => [
        'icon'  => 'fa-ban',
        'color' => 'bg-red-50 border-red-200',
        'head'  => 'bg-red-100',
        'title_i18n' => 'action_cancelled_title',
        'title' => 'Request cancelled',
        'body_i18n'  => 'action_cancelled_body',
        'body'  => 'This booking has been cancelled. Cancellation does not issue an automatic refund. Read the cancellation reason and check the financial review notice below.',
    ],
];
$swActionCard = $swActionCards[$swStatus] ?? $swActionCards['pending_review'];

// Override action for pending_payment when not ready
if ($swStatus === 'pending_payment' && !$swCanPay) {
    $swActionCard['body_i18n'] = 'action_pay_not_ready_body';
    $swActionCard['body'] = 'Payment is not yet available because the surgeon, confirmed appointment or final price is not complete. Use the conversation to ask Easy Implant to provide the missing details.';
}

// ── Summary card helpers ───────────────────────────────────────────
$swSurgeon    = !empty($details['surgeon_name_snapshot']) ? $details['surgeon_name_snapshot'] : null;
$swAppt       = !empty($details['confirmed_operation_at']) ? $details['confirmed_operation_at'] : null;
$swTotal      = ($swHasPrice || (float)($details['estimated_total'] ?? 0) > 0)
                ? ($swHasPrice ? $details['total_price'] : $details['estimated_total'])
                : null;
$swIsEstimate = !$swHasPrice && $swTotal !== null;

// Provider card value
if (($details['implant_package'] ?? '') === 'all_on_arches') {
    $allOnArches = $surgeonArches ?? [];
    $providers = array_unique(array_map(fn($a) => $a['implant_provider'], $allOnArches));
    $swProvider = count($providers) === 1
        ? ($providers[0] === 'clinic' ? 'Clinic' : 'Easy Implant')
        : 'Mixed by arch';
    $swProviderI18n = count($providers) === 1
        ? ($providers[0] === 'clinic' ? 'provider_clinic_short' : 'provider_easy_implant_short')
        : 'provider_mixed_by_arch';
} else {
    $swProvider = ($details['implant_provider'] ?? '') === 'easy_implant' ? 'Easy Implant' : (($details['implant_provider'] ?? '') === 'clinic' ? 'Clinic' : null);
    $swProviderI18n = ($details['implant_provider'] ?? '') === 'easy_implant' ? 'provider_easy_implant_short' : 'provider_clinic_short';
}

$swAllOnPackages = getSurgeonAllOnPackages();
$swFileGroups = ['cbct'=>[],'lab'=>[]];
foreach ($surgeonFiles ?? [] as $file) {
    if (isset($swFileGroups[$file['file_category']])) $swFileGroups[$file['file_category']][] = $file;
}
$swProviderLabels = [
    'clinic'       => 'Clinic — you provide the implants',
    'easy_implant' => 'Easy Implant — we provide the implants',
];
?>

<!-- ════════════════════════════════════════════
     SURGEON WORKSPACE — clinic view
     ════════════════════════════════════════════ -->

<!-- ── Compact Page Header ── -->
<div class="mb-5 flex flex-wrap items-start gap-3">
    <a href="clinic_dashboard.php"
       class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-[#13324a] hover:bg-slate-50 transition shadow-sm"
       aria-label="Back to dashboard">
        <i class="fa-solid fa-arrow-left rtl-flip"></i>
    </a>
    <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold text-[#13324a]">
                <span data-i18n="request_id">Request</span> #<?= str_pad($request['id'], 5, '0', STR_PAD_LEFT) ?>
            </h1>
            <span class="rounded-full border px-3 py-0.5 text-xs font-bold <?= $swEsc($swBadge['color']) ?>" data-i18n="<?= $swEsc($swBadge['i18n']) ?>"><?= $swEsc($swBadge['label']) ?></span>
        </div>
        <p class="mt-0.5 text-xs text-slate-500">
            <span data-i18n="submitted_on">Submitted on</span>
            <time datetime="<?= $swEsc(date('c', strtotime($request['created_at']))) ?>" data-localized-date><?= date('F j, Y', strtotime($request['created_at'])) ?></time>
            · <span data-i18n="surgeon_request">Surgeon Request</span>
        </p>
    </div>
</div>

<!-- ── Action Card ── -->
<section class="mb-5 rounded-2xl border <?= $swEsc($swActionCard['color']) ?> overflow-hidden" aria-label="Current action">
    <div class="flex items-center gap-3 px-4 py-3 <?= $swEsc($swActionCard['head']) ?>">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/70 text-slate-700">
            <i class="fa-solid <?= $swEsc($swActionCard['icon']) ?> text-sm"></i>
        </div>
        <h2 class="text-sm font-bold text-slate-800" data-i18n="<?= $swEsc($swActionCard['title_i18n']) ?>"><?= $swEsc($swActionCard['title']) ?></h2>
    </div>
    <div class="px-4 py-3">
        <p class="text-sm leading-6 text-slate-700" data-i18n="<?= $swEsc($swActionCard['body_i18n']) ?>"><?= $swEsc($swActionCard['body']) ?></p>

        <?php if ($swStatus === 'rejected' && !empty($request['rejection_reason'])): ?>
            <div class="mt-3 rounded-xl bg-white/70 border border-red-200 px-3 py-2 text-sm text-red-800" dir="auto"><?= nl2br($swEsc($request['rejection_reason'])) ?></div>
        <?php endif; ?>

        <?php if ($swStatus === 'cancelled' && !empty($request['rejection_reason'])): ?>
            <div class="mt-3 rounded-xl bg-white/70 border border-red-200 px-3 py-2 text-sm text-red-800" dir="auto"><?= nl2br($swEsc($request['rejection_reason'])) ?></div>
            <p class="mt-2 text-xs font-semibold text-red-700" data-i18n="cancel_no_auto_refund">Cancellation does not issue an automatic refund. Contact Easy Implant for the financial review.</p>
        <?php endif; ?>

        <?php if ($swStatus === 'in_progress' && !empty($details['confirmed_operation_at'])): ?>
            <p class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white/70 border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-800">
                <i class="fa-solid fa-calendar-check"></i>
                <span data-i18n="confirmed_appt_label">Confirmed appointment:</span>
                <time dir="ltr"><?= $swEsc($details['confirmed_operation_at']) ?></time>
            </p>
        <?php endif; ?>

        <?php if ($swStatus === 'completed'): ?>
            <?php if (!empty($details['performed_at'])): ?>
                <p class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white/70 border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-800">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span data-i18n="operation_date_label">Operation date:</span>
                    <time dir="ltr"><?= $swEsc($details['performed_at']) ?></time>
                </p>
            <?php endif; ?>
            <?php if (!empty($details['completion_note'])): ?>
                <div class="mt-3 rounded-xl bg-white/70 border border-emerald-200 px-3 py-2 text-sm text-emerald-800 whitespace-pre-wrap" dir="auto"><?= $swEsc($details['completion_note']) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($swStatus === 'pending_payment'): ?>
            <?php if ($swCanPay): ?>
                <?php if ($hasPaymentError): ?>
                    <p class="mt-3 rounded-xl border border-red-200 bg-white/70 px-3 py-2 text-sm font-semibold text-red-700"><?= (($_GET['payment_error'] ?? '') === 'operation_changed') ? 'The operation details changed. Review the updated surgeon, appointment and final price before paying.' : 'Payment could not be started. Please try again or contact support.' ?></p>
                <?php endif; ?>
                <?php if (xpayIsConfigured()): ?>
                    <form method="post" action="api/create_xpay_checkout.php" class="mt-4">
                        <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>" />
                        <input type="hidden" name="csrf_token" value="<?= $swEsc($request_workflow_csrf_token) ?>" />
                        <input type="hidden" name="operation_fingerprint" value="<?= $swEsc(surgeonOperationFingerprint($details)) ?>" />
                        <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1d5f8c] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#13324a] focus-visible:outline-2 focus-visible:outline-[#0891b2]">
                            <i class="fa-solid fa-lock"></i>
                            <span data-i18n="pay_now">Pay now</span>
                            · <?= $swEsc(formatMoney($details['total_price'])) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <p class="mt-3 rounded-xl border border-amber-200 bg-white/70 px-3 py-2 text-sm font-semibold text-amber-800" data-i18n="payment_gateway_unavailable">Online payment is temporarily unavailable.</p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ── 4 Quick Summary Cards ── -->
<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <!-- Assigned Surgeon -->
    <div class="rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm">
        <div class="flex items-center gap-2 mb-1.5">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 text-xs">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" data-i18n="assigned_surgeon">Assigned Surgeon</p>
        </div>
        <?php if ($swSurgeon): ?>
            <p class="text-sm font-bold text-[#13324a] leading-tight" dir="auto"><?= $swEsc($swSurgeon) ?></p>
        <?php else: ?>
            <p class="text-sm text-slate-400 italic" data-i18n="not_assigned">Not assigned</p>
        <?php endif; ?>
    </div>
    <!-- Confirmed Appointment -->
    <div class="rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm">
        <div class="flex items-center gap-2 mb-1.5">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-xs">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" data-i18n="confirmed_appointment">Confirmed Appointment</p>
        </div>
        <?php if ($swAppt): ?>
            <p class="text-sm font-bold text-[#13324a] leading-tight" dir="ltr"><?= $swEsc($swAppt) ?></p>
        <?php else: ?>
            <p class="text-sm text-slate-400 italic" data-i18n="not_confirmed">Not confirmed</p>
        <?php endif; ?>
    </div>
    <!-- Final Total -->
    <div class="rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm">
        <div class="flex items-center gap-2 mb-1.5">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-xs">
                <i class="fa-solid fa-circle-dollar-to-slot"></i>
            </div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" data-i18n="<?= $swIsEstimate ? 'estimated_total' : 'final_total' ?>"><?= $swIsEstimate ? 'Estimated Total' : 'Final Total' ?></p>
        </div>
        <?php if ($swTotal !== null): ?>
            <p class="text-sm font-bold text-[#13324a]" dir="ltr"><?= $swEsc(formatMoney($swTotal)) ?></p>
        <?php elseif (!empty($details['requires_quote'])): ?>
            <p class="text-sm text-slate-400 italic" data-i18n="quote_pending">Quote pending</p>
        <?php else: ?>
            <p class="text-sm text-slate-400 italic" data-i18n="not_set">Not set</p>
        <?php endif; ?>
    </div>
    <!-- Implant Provider -->
    <div class="rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm">
        <div class="flex items-center gap-2 mb-1.5">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600 text-xs">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" data-i18n="implant_provider">Implant Provider</p>
        </div>
        <?php if ($swProvider): ?>
            <p class="text-sm font-bold text-[#13324a] leading-tight" data-i18n="<?= $swEsc($swProviderI18n) ?>"><?= $swEsc($swProvider) ?></p>
        <?php else: ?>
            <p class="text-sm text-slate-400 italic" data-i18n="not_set">Not set</p>
        <?php endif; ?>
    </div>
</div>

<!-- ── Progress Strip ── -->
<section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Request stages">
    <?php if ($swIsStopped): ?>
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-circle-stop text-red-400"></i>
            <span class="text-sm font-bold text-red-700" data-i18n="request_stopped"><?= $swStatus === 'rejected' ? 'Request rejected — booking stopped' : 'Request cancelled — booking stopped' ?></span>
        </div>
    <?php else: ?>
        <ol class="flex items-start" role="list" aria-label="Request progress">
            <?php foreach ($swJourneySteps as $si => $step):
                $isDone    = $swCurrentStepIdx > $si;
                $isCurrent = $swCurrentStepIdx === $si;
                $lineColor = ($isDone || $isCurrent) ? 'bg-[#1d5f8c]' : 'bg-slate-200';
                $dotColor  = $isDone ? 'bg-[#1d5f8c] text-white' : ($isCurrent ? 'bg-[#1d5f8c] text-white ring-4 ring-blue-100' : 'bg-slate-200 text-slate-400');
                $textColor = ($isDone || $isCurrent) ? 'text-[#1d5f8c]' : 'text-slate-400';
            ?>
                <li class="flex-1 flex flex-col items-center" aria-current="<?= $isCurrent ? 'step' : 'false' ?>">
                    <div class="flex w-full items-center">
                        <?php if ($si > 0): ?><div class="flex-1 h-0.5 <?= $lineColor ?>" aria-hidden="true"></div><?php endif; ?>
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold <?= $dotColor ?>" aria-hidden="true">
                            <?= $isDone ? '<i class="fa-solid fa-check text-[9px]"></i>' : ($si + 1) ?>
                        </div>
                        <?php if ($si < count($swJourneySteps) - 1): ?><div class="flex-1 h-0.5 <?= $isDone ? 'bg-[#1d5f8c]' : 'bg-slate-200' ?>" aria-hidden="true"></div><?php endif; ?>
                    </div>
                    <p class="mt-1.5 text-center text-[10px] font-bold leading-tight <?= $textColor ?>" data-i18n="<?= $swEsc($step['i18n']) ?>"><?= $swEsc($step['label']) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<!-- ══════════════════════════════════════════
     TABBED CONTENT — Desktop | Accordion — Mobile
     ══════════════════════════════════════════ -->
<div id="swTabsRoot">

    <!-- Desktop Tab Navigation -->
    <div class="hidden lg:block mb-4" role="tablist" aria-label="Request sections">
        <div class="flex gap-1 rounded-2xl bg-slate-100 p-1">
            <?php
            $swTabs = [
                ['id'=>'sw-tab-overview',  'panel'=>'sw-panel-overview',  'label'=>'Overview',              'i18n'=>'tab_overview'],
                ['id'=>'sw-tab-case',      'panel'=>'sw-panel-case',      'label'=>'Case & Clinical Files', 'i18n'=>'tab_case_clinical'],
                ['id'=>'sw-tab-payment',   'panel'=>'sw-panel-payment',   'label'=>'Payment & Status',      'i18n'=>'tab_payment_status'],
            ];
            foreach ($swTabs as $ti => $tab): ?>
                <button
                    id="<?= $tab['id'] ?>"
                    role="tab"
                    aria-selected="<?= $ti === 0 ? 'true' : 'false' ?>"
                    aria-controls="<?= $tab['panel'] ?>"
                    tabindex="<?= $ti === 0 ? '0' : '-1' ?>"
                    class="sw-tab-btn flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-[#0891b2]
                           <?= $ti === 0 ? 'bg-white text-[#13324a] shadow-sm' : 'text-slate-500 hover:text-[#13324a]' ?>"
                    data-i18n="<?= $tab['i18n'] ?>">
                    <?= $tab['label'] ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Panel: Overview (Tab on desktop / Accordion on mobile) -->
    <div class="sw-accordion-item mb-3 rounded-2xl border border-slate-200 bg-white overflow-hidden">
        <!-- Mobile accordion header -->
        <button
            type="button"
            class="sw-acc-btn lg:hidden w-full flex items-center justify-between gap-3 px-4 py-3 text-start font-bold text-[#13324a] bg-slate-50 hover:bg-slate-100 transition focus-visible:outline-2 focus-visible:outline-[#0891b2]"
            aria-expanded="true"
            aria-controls="sw-panel-overview">
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-[#1d5f8c] text-sm"></i>
                <span data-i18n="tab_overview">Overview</span>
            </span>
            <i class="fa-solid fa-chevron-up sw-acc-chevron text-slate-400 text-xs transition-transform"></i>
        </button>
        <!-- Panel content -->
        <div
            id="sw-panel-overview"
            role="tabpanel"
            aria-labelledby="sw-tab-overview"
            class="sw-panel p-5 space-y-5">

            <!-- Operation format / service -->
            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1" data-i18n="surgical_service">Surgical Service</p>
                <p class="text-sm font-semibold text-[#13324a]" dir="auto"><?= $swEsc($details['service_name_snapshot'] ?? '—') ?></p>
            </div>

            <!-- Surgeon & appointment mini-table -->
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1" data-i18n="assigned_surgeon">Assigned Surgeon</dt>
                    <dd class="font-semibold text-[#13324a]" dir="auto"><?= $swSurgeon ? $swEsc($swSurgeon) : '<span class="text-slate-400 italic" data-i18n="not_assigned">Not assigned</span>' ?></dd>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1" data-i18n="confirmed_appointment">Confirmed Appointment</dt>
                    <dd class="font-semibold text-[#13324a]" dir="ltr"><?= $swAppt ? $swEsc($swAppt) : '<span class="text-slate-400 italic" data-i18n="not_confirmed">Not confirmed</span>' ?></dd>
                </div>
            </dl>

            <!-- Price summary (compact) -->
            <?php if (empty($details['requires_quote'])): ?>
                <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2 border-b border-slate-100 bg-slate-50">
                        <p class="text-xs font-bold text-[#13324a]" data-i18n="price_breakdown">Price breakdown</p>
                        <p class="text-sm font-extrabold text-[#13324a]" dir="ltr"><?= $swEsc(formatMoney($swTotal ?? 0)) ?> <?= ($swIsEstimate) ? '<span class="text-xs font-normal text-slate-400" data-i18n="estimated_label">(estimated)</span>' : '' ?></p>
                    </div>
                    <div class="grid grid-cols-3 gap-px bg-slate-100">
                        <div class="bg-white px-3 py-2 text-center">
                            <p class="text-[10px] text-slate-500" data-i18n="<?= ($details['implant_package'] ?? '') === 'all_on_arches' ? 'team_work_label' : 'surgeon_work_label' ?>"><?= ($details['implant_package'] ?? '') === 'all_on_arches' ? 'Team work' : 'Surgeon work' ?></p>
                            <p class="text-xs font-bold text-[#13324a]" dir="ltr"><?= number_format((float)(($details['implant_package'] ?? '') === 'all_on_arches' ? ($details['team_fee_total'] ?? 0) : ($details['doctor_fee_total'] ?? 0)), 2) ?> EGP</p>
                        </div>
                        <div class="bg-white px-3 py-2 text-center">
                            <p class="text-[10px] text-slate-500" data-i18n="implants_we_supply">Implants we supply</p>
                            <p class="text-xs font-bold text-[#13324a]" dir="ltr"><?= number_format((float)($details['implant_cost_total'] ?? 0), 2) ?> EGP</p>
                        </div>
                        <div class="bg-white px-3 py-2 text-center">
                            <p class="text-[10px] text-slate-500" data-i18n="travel_label">Travel</p>
                            <p class="text-xs font-bold text-[#13324a]" dir="ltr"><?= number_format((float)($details['travel_price'] ?? 0), 2) ?> EGP</p>
                        </div>
                    </div>
                </div>
            <?php elseif (!empty($details['requires_quote']) && (float)($details['total_price'] ?? 0) > 0): ?>
                <div class="rounded-xl bg-[#13324a] px-4 py-3 text-white">
                    <p class="text-xs text-blue-200 font-bold uppercase tracking-wider" data-i18n="final_total">Final Total</p>
                    <p class="text-xl font-extrabold" dir="ltr"><?= number_format((float)$details['total_price'], 2) ?> EGP</p>
                </div>
            <?php else: ?>
                <p class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm font-bold text-orange-700" data-i18n="quotation_notice">You will receive a quotation after review.</p>
            <?php endif; ?>

            <!-- Status-specific alert -->
            <?php if ($swStatus === 'pending_payment' && !$swIsReady): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="font-bold" data-i18n="incomplete_details_warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Missing details</p>
                    <p class="mt-1 text-xs" data-i18n="incomplete_details_body">The surgeon, confirmed appointment or final price is not complete. Use the conversation to ask Easy Implant to complete these details.</p>
                </div>
            <?php endif; ?>
            <?php if (!empty($details['financial_review_required']) && $swStatus !== 'cancelled'): ?>
                <p class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700" data-i18n="financial_review_notice"><i class="fa-solid fa-triangle-exclamation me-2"></i>Financial review required. No automatic refund has been made.</p>
            <?php endif; ?>

        </div>
    </div>

    <!-- Panel: Case & Clinical Files -->
    <div class="sw-accordion-item mb-3 rounded-2xl border border-slate-200 bg-white overflow-hidden">
        <button
            type="button"
            class="sw-acc-btn lg:hidden w-full flex items-center justify-between gap-3 px-4 py-3 text-start font-bold text-[#13324a] bg-slate-50 hover:bg-slate-100 transition focus-visible:outline-2 focus-visible:outline-[#0891b2]"
            aria-expanded="true"
            aria-controls="sw-panel-case">
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-notes-medical text-[#1d5f8c] text-sm"></i>
                <span data-i18n="tab_case_clinical">Case & Clinical Files</span>
            </span>
            <i class="fa-solid fa-chevron-up sw-acc-chevron text-slate-400 text-xs transition-transform"></i>
        </button>
        <div
            id="sw-panel-case"
            role="tabpanel"
            aria-labelledby="sw-tab-case"
            class="sw-panel p-5">

            <!-- Patient -->
            <div class="mb-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3" data-i18n="section_patient">Patient</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <?php foreach (['patient_name'=>'Patient Name','patient_age'=>'Patient Age'] as $k=>$l): ?>
                        <?php if (!empty($details[$k])): ?>
                            <div class="flex items-baseline gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0" data-i18n="<?= $k ?>"><?= $l ?></dt>
                                <dd class="font-semibold text-[#13324a] truncate" dir="auto"><?= $swEsc($details[$k]) ?></dd>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            </div>

            <!-- Treatment -->
            <div class="mb-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3" data-i18n="section_treatment">Treatment</h3>
                <dl class="space-y-2 text-sm">
                    <?php if (!empty($details['service_name_snapshot'])): ?>
                        <div class="flex items-start gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0 mt-0.5" data-i18n="surgical_service">Surgical Service</dt>
                            <dd class="font-semibold text-[#13324a]" dir="auto"><?= $swEsc($details['service_name_snapshot']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($details['proposed_date'])): ?>
                        <div class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0" data-i18n="proposed_operation_date">Proposed Operation Date</dt>
                            <dd class="font-semibold text-[#13324a]" dir="auto"><?= $swEsc($details['proposed_date']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Implants -->
            <?php if (($details['service_kind'] ?? '') === 'dental_implant'): ?>
                <div class="mb-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3" data-i18n="section_implants">Implants</h3>
                    <?php if (($details['implant_package'] ?? '') === 'all_on_arches'): ?>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <?php foreach ($surgeonArches ?? [] as $arch): ?>
                                <div class="rounded-xl border border-teal-100 bg-teal-50/40 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="font-bold text-[#13324a] text-sm" data-i18n="<?= $arch['arch_position'] === 'upper' ? 'upper_arch' : 'lower_arch' ?>"><?= $arch['arch_position'] === 'upper' ? 'Upper Arch' : 'Lower Arch' ?></h4>
                                        <span class="rounded-full bg-white px-2.5 py-0.5 text-xs font-bold text-[#1d5f8c] shadow-sm"><?= $swEsc($swAllOnPackages[$arch['package_code']]['label'] ?? $arch['package_code']) ?></span>
                                    </div>
                                    <dl class="space-y-1.5 text-xs">
                                        <div class="flex justify-between gap-2"><dt class="text-slate-500" data-i18n="implant_type">Implant type</dt><dd class="font-semibold text-[#13324a] text-end" dir="auto"><?= $swEsc($arch['implant_type_name_snapshot']) ?></dd></div>
                                        <div class="flex justify-between gap-2"><dt class="text-slate-500" data-i18n="number_of_implants">Implants</dt><dd class="font-semibold"><?= (int)$arch['implant_count'] ?></dd></div>
                                        <div class="flex justify-between gap-2"><dt class="text-slate-500" data-i18n="implant_provider">Provider</dt><dd class="font-semibold text-end"><?= $swEsc($arch['implant_provider'] === 'easy_implant' ? 'Easy Implant' : 'Clinic') ?></dd></div>
                                    </dl>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                            <?php
                            $swImplantRows = [
                                ['key'=>'implant_package','label'=>'Implant Package','i18n'=>'implant_package','val'=> getSurgeonImplantPackageLabel($details['implant_package'] ?? null),'dir'=>'auto'],
                                ['key'=>'implant_type_name_snapshot','label'=>'Implant Type','i18n'=>'implant_type','val'=>$details['implant_type_name_snapshot'] ?? null,'dir'=>'auto'],
                                ['key'=>'implant_count','label'=>'Number of Implants','i18n'=>'number_of_implants','val'=>isset($details['implant_count']) ? (int)$details['implant_count'] : null,'dir'=>'ltr'],
                            ];
                            foreach ($swImplantRows as $row):
                                if (!isset($row['val']) || $row['val'] === null || $row['val'] === '') continue;
                            ?>
                                <div class="flex items-baseline gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0" data-i18n="<?= $row['i18n'] ?>"><?= $row['label'] ?></dt>
                                    <dd class="font-semibold text-[#13324a]" dir="<?= $row['dir'] ?>"><?= $swEsc((string)$row['val']) ?></dd>
                                </div>
                            <?php endforeach; ?>
                            <!-- Implant Provider -->
                            <?php if (!empty($details['implant_provider'])): ?>
                                <div class="flex items-start gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 sm:col-span-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0 mt-0.5" data-i18n="implant_provider">Implant Provider</dt>
                                    <dd class="font-semibold text-[#13324a]"><?= $swEsc($swProviderLabels[$details['implant_provider']] ?? $details['implant_provider']) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Clinical Notes -->
            <?php if (!empty($details['medical_history']) || !empty($details['notes'])): ?>
                <div class="mb-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3" data-i18n="section_clinical_notes">Clinical Notes</h3>
                    <div class="space-y-3">
                        <?php if (!empty($details['medical_history'])): ?>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1" data-i18n="medical_history">Medical History & Considerations</p>
                                <div class="whitespace-pre-wrap rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-700" dir="auto"><?= $swEsc($details['medical_history']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($details['notes'])): ?>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1" data-i18n="additional_notes">Additional Notes</p>
                                <div class="whitespace-pre-wrap rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-700" dir="auto"><?= $swEsc($details['notes']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Clinical Files -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3" data-i18n="section_clinical_files">Clinical Files</h3>
                <?php $swHasFiles = !empty($swFileGroups['cbct']) || !empty($swFileGroups['lab']); ?>
                <?php if ($swHasFiles): ?>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <?php foreach (['cbct'=>['CBCT Files','fa-x-ray'],'lab'=>['Lab Results','fa-flask-vial']] as $cat=>[$catLabel,$catIcon]): ?>
                            <?php if ($swFileGroups[$cat]): ?>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <h4 class="mb-3 text-sm font-bold text-[#13324a]"><i class="fa-solid <?= $catIcon ?> me-2 text-[#1d5f8c]"></i><?= $catLabel ?></h4>
                                    <div class="space-y-2">
                                        <?php foreach ($swFileGroups[$cat] as $file): ?>
                                            <a href="<?= $swEsc(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank"
                                               class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-[#1d5f8c]">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-slate-700"><?= $swEsc($file['original_name']) ?></p>
                                                    <p class="text-xs text-slate-400"><?= number_format((float)$file['file_size'] / 1024 / 1024, 2) ?> MB</p>
                                                </div>
                                                <i class="fa-solid fa-download shrink-0 text-[#1d5f8c]"></i>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500" data-i18n="no_clinical_files">No clinical files were attached to this request. Files are optional.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Panel: Payment & Status -->
    <div class="sw-accordion-item mb-3 rounded-2xl border border-slate-200 bg-white overflow-hidden">
        <button
            type="button"
            class="sw-acc-btn lg:hidden w-full flex items-center justify-between gap-3 px-4 py-3 text-start font-bold text-[#13324a] bg-slate-50 hover:bg-slate-100 transition focus-visible:outline-2 focus-visible:outline-[#0891b2]"
            aria-expanded="true"
            aria-controls="sw-panel-payment">
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-receipt text-[#1d5f8c] text-sm"></i>
                <span data-i18n="tab_payment_status">Payment & Status</span>
            </span>
            <i class="fa-solid fa-chevron-up sw-acc-chevron text-slate-400 text-xs transition-transform"></i>
        </button>
        <div
            id="sw-panel-payment"
            role="tabpanel"
            aria-labelledby="sw-tab-payment"
            class="sw-panel p-5 space-y-5">

            <!-- Price summary box -->
            <?php if (empty($details['requires_quote']) || (float)($details['total_price'] ?? 0) > 0): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 overflow-hidden">
                    <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
                        <div class="bg-white px-4 py-3">
                            <p class="text-[10px] text-slate-500" data-i18n="<?= ($details['implant_package'] ?? '') === 'all_on_arches' ? 'team_work_label' : 'surgeon_work_label' ?>"><?= ($details['implant_package'] ?? '') === 'all_on_arches' ? 'Team work' : 'Surgeon work' ?></p>
                            <p class="mt-1 font-bold text-[#13324a] text-sm" dir="ltr"><?= number_format((float)(($details['implant_package'] ?? '') === 'all_on_arches' ? ($details['team_fee_total'] ?? 0) : ($details['doctor_fee_total'] ?? 0)), 2) ?> EGP</p>
                        </div>
                        <div class="bg-white px-4 py-3">
                            <p class="text-[10px] text-slate-500" data-i18n="implants_we_supply">Implants we supply</p>
                            <p class="mt-1 font-bold text-[#13324a] text-sm" dir="ltr"><?= number_format((float)($details['implant_cost_total'] ?? 0), 2) ?> EGP</p>
                        </div>
                        <div class="bg-white px-4 py-3">
                            <p class="text-[10px] text-slate-500" data-i18n="travel_label">Travel</p>
                            <p class="mt-1 font-bold text-[#13324a] text-sm" dir="ltr"><?= number_format((float)($details['travel_price'] ?? 0), 2) ?> EGP</p>
                        </div>
                        <div class="bg-[#13324a] px-4 py-3">
                            <p class="text-[10px] text-blue-200" data-i18n="<?= $swIsEstimate ? 'estimated_total' : 'final_total' ?>"><?= $swIsEstimate ? 'Estimated Total' : 'Final Total' ?></p>
                            <p class="mt-1 font-extrabold text-white text-sm" dir="ltr"><?= $swEsc(formatMoney($swTotal ?? 0)) ?></p>
                        </div>
                    </div>
                    <p class="px-4 py-2 text-xs text-slate-500 border-t border-slate-100" data-i18n="<?= !empty($details['price_confirmed_at']) ? 'price_confirmed_note' : 'price_estimate_note' ?>"><?= !empty($details['price_confirmed_at']) ? 'Administration has confirmed this final total.' : 'This is the calculated estimate. Administration must confirm it before payment.' ?></p>
                </div>
            <?php elseif (!empty($details['requires_quote'])): ?>
                <p class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm font-bold text-orange-700" data-i18n="quotation_notice">You will receive a quotation after review.</p>
            <?php endif; ?>

            <!-- Payment record -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3" data-i18n="payment_record">Payment Record</h3>
                <?php if ($payment): ?>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" data-i18n="payment_status">Payment Status</p>
                                <p class="mt-0.5 text-sm font-bold <?= $payment['status'] === 'approved' ? 'text-emerald-600' : ($payment['status'] === 'rejected' ? 'text-red-600' : 'text-orange-600') ?>"><?= ucfirst(str_replace('_',' ',$payment['status'])) ?></p>
                            </div>
                            <div class="text-end">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400" data-i18n="confirmed_on">Confirmed On</p>
                                <time class="text-sm font-semibold text-slate-700" datetime="<?= $swEsc(date('c', strtotime($payment['approved_at'] ?? $payment['uploaded_at']))) ?>" data-localized-date><?= date('M d, Y', strtotime($payment['approved_at'] ?? $payment['uploaded_at'])) ?></time>
                            </div>
                        </div>
                        <?php if (($payment['payment_source'] ?? 'manual_receipt') === 'xpay'): ?>
                            <div class="flex w-full items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-emerald-600 shadow-sm"><i class="fa-solid fa-shield-halved"></i></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-emerald-800" data-i18n="paid_online">Paid online</span>
                                </span>
                                <span class="shrink-0 text-sm font-extrabold text-emerald-800"><?= $swEsc(formatCurrencyMoney($payment['amount'], $payment['currency'] ?? 'EGP')) ?></span>
                            </div>
                        <?php else: ?>
                            <?php
                                $swReceiptUrl  = getPresignedUrl($s3Client, $bucketName, $payment['receipt_file_path'] ?? '');
                                $swReceiptName = uploadedFileDisplayName($payment['receipt_original_name'] ?? null, $payment['receipt_file_path'] ?? '');
                            ?>
                            <a href="<?= $swEsc($swReceiptUrl) ?>" target="_blank"
                               class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 transition hover:border-[#1d5f8c] hover:bg-white">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-[#1d5f8c] shadow-sm"><i class="fa-solid fa-receipt"></i></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-700"><?= $swEsc($swReceiptName) ?></span>
                                </span>
                                <span class="shrink-0 text-xs font-bold text-[#1d5f8c]" data-i18n="view_receipt">View receipt <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></span>
                            </a>
                            <p class="text-xs text-slate-500 italic" data-i18n="legacy_receipt_note">Legacy receipt shown for historical reference only. New manual receipts are disabled.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-slate-500">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 mb-3"><i class="fa-solid fa-file-invoice text-xl"></i></div>
                        <p class="text-sm font-semibold text-[#13324a]" data-i18n="no_confirmed_payment">No confirmed payment yet.</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            <?php if ($swStatus === 'pending_review'): ?>
                                <span data-i18n="no_payment_review_stage">No payment is required at the review stage. After the surgeon, appointment and final price are confirmed, the Pay Now button will appear above.</span>
                            <?php elseif ($swStatus === 'pending_payment'): ?>
                                <span data-i18n="no_payment_pending_payment">Use the Pay Now button above when the confirmed details are ready. Your payment record will appear here after confirmation.</span>
                            <?php elseif ($swStatus === 'in_progress'): ?>
                                <span data-i18n="no_payment_in_progress">No confirmed payment is recorded here. If you have already paid, contact Easy Implant to check the payment status before paying again.</span>
                            <?php else: ?>
                                <span data-i18n="no_payment_other">No confirmed payment is recorded for this request. Contact Easy Implant support if you need clarification.</span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Financial review notice -->
            <?php if (!empty($details['financial_review_required'])): ?>
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm">
                    <p class="font-bold text-red-700" data-i18n="financial_review_required_title"><i class="fa-solid fa-triangle-exclamation me-2"></i>Financial review required</p>
                    <p class="mt-1 text-xs text-red-600" data-i18n="financial_review_required_body">No automatic refund has been made. Contact Easy Implant to clarify the financial status of this request.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div><!-- end #swTabsRoot -->

<script>
(function () {
    'use strict';

    /* ── Tab behaviour (desktop ≥1024px) ── */
    const tabBtns  = document.querySelectorAll('.sw-tab-btn');
    const panels   = { 'sw-panel-overview': null, 'sw-panel-case': null, 'sw-panel-payment': null };
    Object.keys(panels).forEach(id => { panels[id] = document.getElementById(id); });

    function activateTab(btn) {
        tabBtns.forEach(b => {
            const isActive = b === btn;
            b.setAttribute('aria-selected', isActive ? 'true' : 'false');
            b.setAttribute('tabindex', isActive ? '0' : '-1');
            b.classList.toggle('bg-white', isActive);
            b.classList.toggle('text-[#13324a]', isActive);
            b.classList.toggle('shadow-sm', isActive);
            b.classList.toggle('text-slate-500', !isActive);
        });
        const targetId = btn.getAttribute('aria-controls');
        Object.entries(panels).forEach(([id, el]) => {
            if (!el) return;
            if (id === targetId) {
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', '');
            }
        });
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => activateTab(btn));
        btn.addEventListener('keydown', e => {
            const all = [...tabBtns];
            const idx = all.indexOf(btn);
            if (e.key === 'ArrowRight') { e.preventDefault(); all[(idx + 1) % all.length].focus(); activateTab(all[(idx + 1) % all.length]); }
            if (e.key === 'ArrowLeft')  { e.preventDefault(); all[(idx - 1 + all.length) % all.length].focus(); activateTab(all[(idx - 1 + all.length) % all.length]); }
            if (e.key === 'Home')       { e.preventDefault(); all[0].focus(); activateTab(all[0]); }
            if (e.key === 'End')        { e.preventDefault(); all[all.length - 1].focus(); activateTab(all[all.length - 1]); }
        });
    });

    /* On desktop: hide non-active panels (first tab active by default) */
    function applyDesktopLayout() {
        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        if (isDesktop) {
            const activeTab = document.querySelector('.sw-tab-btn[aria-selected="true"]');
            if (activeTab) activateTab(activeTab);
        } else {
            // On mobile: all panels shown (accordion controls visibility)
            Object.values(panels).forEach(el => { if (el) el.removeAttribute('hidden'); });
        }
    }
    applyDesktopLayout();
    const mq = window.matchMedia('(min-width: 1024px)');
    mq.addEventListener ? mq.addEventListener('change', applyDesktopLayout) : mq.addListener(applyDesktopLayout);

    /* ── Accordion behaviour (mobile <1024px) ── */
    document.querySelectorAll('.sw-acc-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            const panelId  = btn.getAttribute('aria-controls');
            const panel    = document.getElementById(panelId);
            const chevron  = btn.querySelector('.sw-acc-chevron');
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            if (panel) {
                if (expanded) {
                    panel.style.maxHeight = panel.scrollHeight + 'px';
                    requestAnimationFrame(() => { panel.style.maxHeight = '0'; panel.style.overflow = 'hidden'; });
                    panel.addEventListener('transitionend', () => { panel.setAttribute('hidden', ''); panel.style.maxHeight = ''; panel.style.overflow = ''; }, { once: true });
                } else {
                    panel.removeAttribute('hidden');
                    panel.style.overflow = 'hidden';
                    panel.style.maxHeight = '0';
                    requestAnimationFrame(() => { panel.style.maxHeight = panel.scrollHeight + 'px'; });
                    panel.addEventListener('transitionend', () => { panel.style.maxHeight = ''; panel.style.overflow = ''; }, { once: true });
                }
            }
            if (chevron) chevron.style.transform = expanded ? 'rotate(180deg)' : '';
        });
    });
})();
</script>
