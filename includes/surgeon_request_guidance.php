<?php
// Clinic-facing explanations only; request transitions remain server-controlled.
$surgeonStages = [
    'pending_review' => [
        'title' => 'Review & coordination',
        'meaning' => 'Your request has been submitted. Easy Implant is reviewing the case and coordinating the surgeon, appointment and final price with your clinic.',
        'action' => 'Follow the conversation with Easy Implant and send any missing details, questions or requested changes. Payment becomes available after administration confirms the agreed details.',
    ],
    'pending_payment' => [
        'title' => 'Awaiting your payment',
        'meaning' => 'Your request is at the payment stage. Check the assigned surgeon, confirmed appointment and final total below before making payment.',
        'action' => 'If the details are correct, use Pay now in the Online payment section. If you need a change, send a message to Easy Implant before paying. Full payment confirms your agreement to the displayed details.',
    ],
    'in_progress' => [
        'title' => 'Paid — awaiting operation',
        'meaning' => 'Payment has been confirmed. The request is awaiting the operation; this status does not mean the procedure has already taken place.',
        'action' => 'Check the confirmed appointment below and use the conversation for any remaining arrangements. Administration records completion after the procedure.',
    ],
    'completed' => [
        'title' => 'Operation performed',
        'meaning' => 'Administration has marked this operation as completed. The request remains available as a record of the case, coordination and payment.',
        'action' => 'Review the recorded operation date and completion note below, where available. The conversation is now read-only. Contact Easy Implant support if you need further assistance.',
    ],
    'rejected' => [
        'title' => 'Request rejected',
        'meaning' => 'Administration has declined this request. It will not continue through the booking steps.',
        'action' => 'Read the rejection reason shown on this page. The conversation is read-only; contact Easy Implant support if you need clarification.',
    ],
    'cancelled' => [
        'title' => 'Request cancelled',
        'meaning' => 'This booking has been cancelled. The request and payment records are retained for reference.',
        'action' => 'Read the cancellation reason and financial review notice below. Cancellation does not issue an automatic refund. The conversation is read-only; contact Easy Implant support about the financial review.',
    ],
];
$surgeonStages['contacted'] = [
    'title' => 'Previous coordination status',
    'meaning' => 'This request was saved with a coordination status from the previous workflow. The case details and conversation are retained for reference.',
    'action' => 'Review the saved details below and contact Easy Implant support to confirm the next step. This historical conversation is read-only.',
];
$surgeonGuidance = $surgeonStages[$request['status']] ?? [
    'title' => 'Request being followed up',
    'meaning' => 'This request uses a previous or unrecognized status. Review the saved case and coordination details below.',
    'action' => 'Contact Easy Implant to confirm the next step for this request.',
];
if ($request['status'] === 'pending_payment' && (!$details || !surgeonOperationIsReady($details) || (float) ($details['total_price'] ?? 0) <= 0)) {
    $surgeonGuidance['action'] = 'Payment is not ready yet because the surgeon, confirmed appointment or final price is missing. Use the conversation to ask Easy Implant to complete these details before you pay.';
}
$surgeonJourney = [
    'pending_review' => ['Review & coordination', 'We review your case and agree the surgeon, appointment and price with you.'],
    'pending_payment' => ['Your payment', 'You review the confirmed details and pay the full final amount online.'],
    'in_progress' => ['Awaiting operation', 'Payment is confirmed and the operation is arranged for the confirmed appointment.'],
    'completed' => ['Operation performed', 'Administration records the procedure date and completion note.'],
];
$surgeonGuidanceEsc = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section id="surgeonRequestGuidance" class="case-card" aria-labelledby="surgeonGuidanceTitle">
    <div class="case-card-header">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#1d5f8c]"><i class="fa-solid fa-circle-info"></i></div>
        <div><p class="text-xs font-semibold text-slate-500">What your request status means</p><h2 id="surgeonGuidanceTitle" class="text-lg font-bold text-[#13324a]"><?= $surgeonGuidanceEsc($surgeonGuidance['title']) ?></h2></div>
    </div>
    <div class="case-card-body space-y-4">
        <p class="text-sm leading-6 text-slate-600"><?= $surgeonGuidanceEsc($surgeonGuidance['meaning']) ?></p>
        <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4"><h3 class="text-sm font-bold text-[#13324a]">What you need to do now</h3><p class="mt-1 text-sm leading-6 text-slate-700"><?= $surgeonGuidanceEsc($surgeonGuidance['action']) ?></p></div>
        <details class="border-t border-slate-100 pt-4">
            <summary class="cursor-pointer text-sm font-bold text-[#1d5f8c]">How your surgeon request works — view all 4 steps</summary>
            <ol class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php $surgeonStepNumber = 0; foreach ($surgeonJourney as $stage => [$title, $explanation]): $surgeonStepNumber++; $isCurrentStage = $request['status'] === $stage; ?>
                    <li class="rounded-xl border p-3 <?= $isCurrentStage ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-slate-50' ?>" <?= $isCurrentStage ? 'aria-current="step"' : '' ?>>
                        <p class="text-sm font-bold text-[#13324a]"><?= $surgeonStepNumber ?>. <span><?= $surgeonGuidanceEsc($title) ?></span><?php if ($isCurrentStage): ?> · <span>Current step</span><?php endif; ?></p>
                        <p class="mt-1 text-xs leading-5 text-slate-600"><?= $surgeonGuidanceEsc($explanation) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </details>
    </div>
</section>
