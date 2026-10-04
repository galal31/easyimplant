<?php
if (!isset($details) || !is_array($details)) return;
$surgeonArches = $surgeonArches ?? [];
$surgeonFiles = $surgeonFiles ?? [];
$surgeonClinicView = ($_SESSION['role'] ?? '') === 'clinic';
$providerLabels = $surgeonClinicView
    ? ['clinic' => 'Clinic — you provide the implants; their cost is excluded from this total', 'easy_implant' => 'Easy Implant — we provide the implants; their cost is included in this total']
    : ['clinic' => 'Provided by clinic — not charged', 'easy_implant' => 'Provided by Easy Implant'];
$surgeonFieldHelp = [
    'patient_name' => 'The patient this surgical request is for.',
    'patient_age' => 'The patient age in years, as entered when you submitted the request.',
    'service_name_snapshot' => 'The surgical service you requested for this case.',
    'proposed_date' => 'Your preferred date when submitting the request. Check Operation coordination below for the appointment confirmed by administration.',
    'medical_history' => 'The medical information you submitted for administration to review this case.',
    'notes' => 'The extra instructions or information you included with your request.',
];
$allOnPackages = getSurgeonAllOnPackages();
$fileGroups = ['cbct' => [], 'lab' => []];
foreach ($surgeonFiles as $file) {
    if (isset($fileGroups[$file['file_category']])) $fileGroups[$file['file_category']][] = $file;
}
?>
<?php if ($surgeonClinicView): ?><p class="mb-5 text-sm leading-6 text-slate-600">These are the case details you submitted. Review them and use the conversation with Easy Implant to request any changes or clarify the treatment before payment.</p><?php endif; ?>
<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    <?php foreach (['patient_name' => 'Patient Name', 'patient_age' => 'Patient Age', 'service_name_snapshot' => 'Surgical Service', 'proposed_date' => 'Proposed Operation Date'] as $key => $label): ?>
        <?php if (isset($details[$key]) && $details[$key] !== ''): ?><div><h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400"><?= $label ?></h3><div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700" dir="auto"><?= htmlspecialchars((string) $details[$key]) ?></div><?php if ($surgeonClinicView): ?><p class="mt-2 text-xs leading-5 text-slate-500"><?= htmlspecialchars($surgeonFieldHelp[$key]) ?></p><?php endif; ?></div><?php endif; ?>
    <?php endforeach; ?>

    <?php if (($details['service_kind'] ?? null) === 'dental_implant' && ($details['implant_package'] ?? null) !== 'all_on_arches'): ?>
        <div><h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Implant Package</h3><div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700"><?= htmlspecialchars(getSurgeonImplantPackageLabel($details['implant_package'] ?? null)) ?></div></div>
        <div><h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Implant Type</h3><div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700"><?= htmlspecialchars($details['implant_type_name_snapshot'] ?? 'Not specified') ?></div></div>
        <div><h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Number of Implants</h3><div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700"><?= (int) ($details['implant_count'] ?? 0) ?></div></div>
        <div><h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Implant Provider</h3><div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700"><?= htmlspecialchars($providerLabels[$details['implant_provider'] ?? ''] ?? 'Not specified') ?></div></div>
    <?php elseif (($details['implant_package'] ?? null) === 'all_on_arches'): ?>
        <div class="md:col-span-2 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <?php foreach ($surgeonArches as $arch): ?>
                <div class="rounded-2xl border border-teal-100 bg-teal-50/40 p-5">
                    <div class="mb-4 flex items-center justify-between"><h3 class="font-bold text-[#13324a]"><?= $arch['arch_position'] === 'upper' ? 'Upper Arch' : 'Lower Arch' ?></h3><span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-[#1d5f8c] shadow-sm"><?= htmlspecialchars($allOnPackages[$arch['package_code']]['label'] ?? $arch['package_code']) ?></span></div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Implant type</dt><dd class="text-right font-semibold text-[#13324a]"><?= htmlspecialchars($arch['implant_type_name_snapshot']) ?></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Implants</dt><dd class="font-semibold"><?= (int) $arch['implant_count'] ?></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Provider</dt><dd class="text-right font-semibold"><?= htmlspecialchars($providerLabels[$arch['implant_provider']] ?? $arch['implant_provider']) ?></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Team work</dt><dd class="font-semibold"><?= number_format((float) $arch['team_fee'], 2) ?> EGP</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Implant cost</dt><dd class="font-semibold"><?= number_format((float) $arch['implant_cost_total'], 2) ?> EGP</dd></div>
                        <div class="flex justify-between gap-4 border-t border-teal-100 pt-2"><dt class="font-bold text-[#13324a]">Arch subtotal</dt><dd class="font-extrabold text-[#13324a]"><?= number_format((float) $arch['subtotal'], 2) ?> EGP</dd></div>
                    </dl>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif (!empty($details['requires_quote']) && empty($details['total_price'])): ?>
        <div data-i18n="quotation_notice" class="md:col-span-2 rounded-xl border border-orange-200 bg-orange-50 p-4 text-center font-bold text-orange-700">You will receive a quotation after review.</div>
    <?php endif; ?>

    <?php if (empty($details['requires_quote'])): ?>
        <div class="md:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <?php if ($surgeonClinicView): ?><div class="border-b border-slate-100 px-4 py-3"><h3 class="text-sm font-bold text-[#13324a]">Price breakdown</h3><p class="mt-1 text-xs leading-5 text-slate-500">Professional fees are for your selected treatment. Implant costs are added only for implants supplied by Easy Implant. Travel covers the visit to your clinic governorate and is charged once for the request.</p></div><?php endif; ?>
            <div class="grid grid-cols-2 gap-3 p-4 text-sm sm:grid-cols-4">
                <div><p class="text-xs text-slate-500"><?= ($details['implant_package'] ?? '') === 'all_on_arches' ? 'Team work' : 'Surgeon work' ?></p><p class="mt-1 font-bold text-[#13324a]"><?= number_format((float) ((($details['implant_package'] ?? '') === 'all_on_arches') ? ($details['team_fee_total'] ?? 0) : ($details['doctor_fee_total'] ?? 0)), 2) ?> EGP</p></div>
                <div><p class="text-xs text-slate-500">Implants supplied by us</p><p class="mt-1 font-bold text-[#13324a]"><?= number_format((float) ($details['implant_cost_total'] ?? 0), 2) ?> EGP</p></div>
                <div><p class="text-xs text-slate-500">Travel</p><p class="mt-1 font-bold text-[#13324a]"><?= number_format((float) ($details['travel_price'] ?? 0), 2) ?> EGP</p></div>
                <div class="rounded-lg bg-[#13324a] px-3 py-2 text-white"><p class="text-xs text-slate-300"><?= !empty($details['price_confirmed_at']) ? 'Final total' : 'Estimated total' ?></p><p class="mt-1 text-base font-extrabold"><?= number_format((float) ($details['total_price'] ?? $details['estimated_total'] ?? 0), 2) ?> EGP</p></div>
            </div>
            <?php if ($surgeonClinicView): ?><p class="border-t border-slate-100 px-4 py-3 text-xs leading-5 text-slate-500"><?= !empty($details['price_confirmed_at']) ? 'Administration has approved this final total. Payment is available at the payment stage after the surgeon and appointment are confirmed.' : 'This is the calculated estimate saved with your request. Administration must confirm the surgeon, appointment and final price before asking you to pay.' ?></p><?php endif; ?>
        </div>
    <?php elseif (!empty($details['requires_quote']) && (float) ($details['total_price'] ?? 0) > 0): ?>
        <div class="md:col-span-2 rounded-xl bg-[#13324a] p-5 text-white"><p class="text-xs font-bold uppercase tracking-wider text-blue-100">Final total</p><p class="mt-1 text-2xl font-extrabold"><?= number_format((float) $details['total_price'], 2) ?> EGP</p></div>
    <?php endif; ?>

    <?php if ($surgeonClinicView && !empty($details['requires_quote'])): ?><p class="md:col-span-2 text-sm leading-6 text-slate-600"><?= (float) ($details['total_price'] ?? 0) > 0 ? 'This service uses an individual quotation. Review the quoted total with Easy Implant and ask what it covers before paying.' : 'This service has no automatic price. Administration reviews the case and provides an individual quotation; no payment is required while the quote is being prepared.' ?></p><?php endif; ?>

    <?php if ($surgeonClinicView && ($details['service_kind'] ?? '') === 'dental_implant'): ?><p class="md:col-span-2 text-xs leading-5 text-slate-500"><?= ($details['implant_package'] ?? '') === 'all_on_arches' ? 'Each arch has its own treatment package, implant type and supplier. The arch subtotal combines team fees and any implants we supply for that arch; travel is added once to the whole request.' : 'The package, implant type and number of implants describe the treatment you selected. The implant provider identifies who supplies the implants, separately from the surgeon professional fees.' ?></p><?php endif; ?>

    <?php foreach (['medical_history' => 'Medical History & Considerations', 'notes' => 'Additional Notes'] as $key => $label): ?>
        <?php if (!empty($details[$key])): ?><div class="md:col-span-2"><h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400"><?= $label ?></h3><div class="whitespace-pre-wrap rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm leading-relaxed text-slate-700" dir="auto"><?= htmlspecialchars($details[$key]) ?></div><?php if ($surgeonClinicView): ?><p class="mt-2 text-xs leading-5 text-slate-500"><?= htmlspecialchars($surgeonFieldHelp[$key]) ?></p><?php endif; ?></div><?php endif; ?>
    <?php endforeach; ?>

    <?php if ($surgeonFiles): ?>
        <div class="md:col-span-2 border-t border-slate-100 pt-6">
            <h3 class="mb-4 text-base font-bold text-[#13324a]">Clinical Files</h3>
            <?php if ($surgeonClinicView): ?><p class="mb-4 text-sm leading-6 text-slate-600">The CBCT scans and lab results attached to this request help administration review the case. Select a file to open or download it.</p><?php endif; ?>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <?php foreach (['cbct' => ['CBCT Files', 'fa-x-ray'], 'lab' => ['Lab Results', 'fa-flask-vial']] as $category => $meta): ?>
                    <?php if ($fileGroups[$category]): ?><div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><h4 class="mb-3 text-sm font-bold text-[#13324a]"><i class="fa-solid <?= $meta[1] ?> mr-2 text-[#1d5f8c]"></i><?= $meta[0] ?></h4><div class="space-y-2">
                        <?php foreach ($fileGroups[$category] as $file): ?><a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-[#1d5f8c]"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-700"><?= htmlspecialchars($file['original_name']) ?></p><p class="text-xs text-slate-400"><?= number_format((float) $file['file_size'] / 1024 / 1024, 2) ?> MB</p></div><i class="fa-solid fa-download shrink-0 text-[#1d5f8c]"></i></a><?php endforeach; ?>
                    </div></div><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($surgeonClinicView): ?>
        <div class="md:col-span-2 border-t border-slate-100 pt-5"><h3 class="text-sm font-bold text-[#13324a]">Clinical Files</h3><p class="mt-2 text-sm leading-6 text-slate-500">No clinical files were attached to this request. Files are optional at submission; use the conversation to coordinate if administration needs more information.</p></div>
    <?php endif; ?>
</div>
