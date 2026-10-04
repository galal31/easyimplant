<?php
$operationAdmin=($_SESSION['role'] ?? '')==='admin';
$operationReady=surgeonOperationIsReady($details);
$operationEditable=$operationAdmin && ($request['status']==='pending_review' || (in_array($request['status'],['pending_payment','in_progress'],true) && !$operationReady));
$operationEsc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<div class="case-card">
<div class="case-card-header"><div><h2 class="text-base font-bold text-[#13324a]">Operation coordination</h2><?php if (!$operationAdmin): ?><p class="mt-1 text-xs leading-5 text-slate-500">The surgeon and appointment confirmed by Easy Implant for this request.</p><?php endif; ?></div></div>
<div class="case-card-body space-y-4">
<dl class="grid gap-4 sm:grid-cols-2 text-sm">
<div><dt class="font-bold text-slate-500">Assigned surgeon</dt><dd class="mt-1"><?= $operationEsc($details['surgeon_name_snapshot'] ?? 'Not assigned') ?></dd></div>
<div><dt class="font-bold text-slate-500">Confirmed appointment (Cairo time)</dt><dd class="mt-1"><?= $operationEsc($details['confirmed_operation_at'] ?? 'Not confirmed') ?></dd></div>
</dl>
<?php if (!$operationAdmin): ?><p class="text-xs leading-5 text-slate-500">Administration assigns the surgeon and confirms the appointment after coordinating with your clinic. The proposed date in your case details is your preference; the confirmed appointment here is the agreed date and time.</p><?php endif; ?>
<?php if (!$operationReady): ?>
    <?php if ($operationAdmin): ?><p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">The surgeon and appointment need confirmation by administration. Existing request prices and payments are preserved.</p>
    <?php elseif (in_array($request['status'], ['pending_review','pending_payment'], true)): ?><p class="rounded-xl bg-amber-50 p-3 text-sm leading-6 text-amber-800">The surgeon or appointment has not been confirmed yet. Easy Implant needs to complete these details before payment can become available. Use the conversation for questions or changes.</p>
    <?php elseif ($request['status']==='in_progress'): ?><p class="rounded-xl bg-amber-50 p-3 text-sm leading-6 text-amber-800">The request is marked paid, but the surgeon or confirmed appointment is missing. Ask Easy Implant to complete the coordination details.</p><?php endif; ?>
<?php endif; ?>
<?php if ($request['status']==='pending_review'): ?><p class="text-sm leading-6 text-slate-600">Discuss the case, appointment and price in the conversation. Administration confirms the agreed details before requesting payment.</p><?php endif; ?>
<?php if ($request['status']==='pending_payment'): ?><p class="text-sm text-slate-600">Review the surgeon, confirmed appointment and final price before payment. Ask for any changes in the conversation before paying. Full payment confirms your agreement to these details.</p><?php endif; ?>
<?php if ($request['status']==='in_progress'): ?><p class="rounded-xl bg-blue-50 p-3 text-sm text-blue-800">Payment confirmed — awaiting operation. Administration records completion after the procedure.</p><?php endif; ?>
<?php if (!empty($details['performed_at'])): ?><p class="font-bold text-emerald-700">Operation performed: <?= $operationEsc($details['performed_at']) ?> (Cairo)</p><p class="whitespace-pre-wrap text-sm"><?= $operationEsc($details['completion_note']) ?></p><?php elseif ($request['status']==='completed'): ?><p class="text-sm text-amber-700">Historical completed request: operation date and completion note were not recorded.</p><?php endif; ?>
<?php if ($request['status']==='cancelled'): ?><p class="rounded-xl bg-red-50 p-3 text-sm text-red-800">Cancelled after payment — financial review required. No automatic refund has been made.</p><p class="whitespace-pre-wrap text-sm"><?= $operationEsc($request['rejection_reason']) ?></p><?php endif; ?>
<?php if (!empty($details['financial_review_required']) && $request['status']!=='cancelled'): ?><p class="text-sm text-red-700">Financial review required. No automatic refund has been made.</p><?php endif; ?>
<?php if ($operationEditable): ?>
<form id="surgeonAppointmentForm" class="space-y-3 border-t pt-4">
<label for="assignedSurgeon" class="block text-sm font-bold">Surgeon</label><select id="assignedSurgeon" name="surgeon_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><option value="">Select available surgeon</option><?php foreach($surgeons as $surgeon): ?><option value="<?= (int)$surgeon['id'] ?>" <?= (int)($details['surgeon_id'] ?? 0)===(int)$surgeon['id']?'selected':'' ?>><?= $operationEsc($surgeon['full_name']) ?> — <?= $operationEsc($surgeon['specialty']) ?></option><?php endforeach; ?></select>
<label for="confirmedOperationAt" class="block text-sm font-bold">Confirmed appointment (Cairo time)</label><input id="confirmedOperationAt" name="confirmed_operation_at" type="datetime-local" required value="<?= $operationEsc(!empty($details['confirmed_operation_at'])?str_replace(' ','T',substr($details['confirmed_operation_at'],0,16)):'') ?>" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
<button class="w-full rounded-xl bg-[#13324a] py-3 font-bold text-white">Save surgeon and appointment</button>
</form>
<?php endif; ?>
</div></div>
