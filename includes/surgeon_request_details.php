<?php
if (!isset($details) || !is_array($details)) return;
$surgeonArches = $surgeonArches ?? [];
$surgeonFiles = $surgeonFiles ?? [];
$providerLabels = ['clinic' => 'Provided by clinic — not charged', 'easy_implant' => 'Provided by Easy Implant'];
$allOnPackages = getSurgeonAllOnPackages();
$fileGroups = ['cbct' => [], 'lab' => []];
foreach ($surgeonFiles as $file) {
    if (isset($fileGroups[$file['file_category']])) $fileGroups[$file['file_category']][] = $file;
}
?>
<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    <?php foreach (['patient_name' => 'Patient Name', 'patient_age' => 'Patient Age', 'service_name_snapshot' => 'Surgical Service', 'proposed_date' => 'Proposed Operation Date'] as $key => $label): ?>
        <?php if (isset($details[$key]) && $details[$key] !== ''): ?><div><h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400"><?= $label ?></h3><div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700"><?= htmlspecialchars((string) $details[$key]) ?></div></div><?php endif; ?>
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
    <?php elseif (!empty($details['requires_quote'])): ?>
        <div class="md:col-span-2 rounded-xl border border-orange-200 bg-orange-50 p-4 text-center font-bold text-orange-700" dir="rtl">سيتم الرد بعرض سعر</div>
    <?php endif; ?>

    <?php if (empty($details['requires_quote'])): ?>
        <div class="md:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="grid grid-cols-2 gap-3 p-4 text-sm sm:grid-cols-4">
                <div><p class="text-xs text-slate-500"><?= ($details['implant_package'] ?? '') === 'all_on_arches' ? 'Team work' : 'Surgeon work' ?></p><p class="mt-1 font-bold text-[#13324a]"><?= number_format((float) ((($details['implant_package'] ?? '') === 'all_on_arches') ? ($details['team_fee_total'] ?? 0) : ($details['doctor_fee_total'] ?? 0)), 2) ?> EGP</p></div>
                <div><p class="text-xs text-slate-500">Implants supplied by us</p><p class="mt-1 font-bold text-[#13324a]"><?= number_format((float) ($details['implant_cost_total'] ?? 0), 2) ?> EGP</p></div>
                <div><p class="text-xs text-slate-500">Travel</p><p class="mt-1 font-bold text-[#13324a]"><?= number_format((float) ($details['travel_price'] ?? 0), 2) ?> EGP</p></div>
                <div class="rounded-lg bg-[#13324a] px-3 py-2 text-white"><p class="text-xs text-slate-300">Estimated total</p><p class="mt-1 text-base font-extrabold"><?= number_format((float) ($details['estimated_total'] ?? 0), 2) ?> EGP</p></div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach (['medical_history' => 'Medical History & Considerations', 'notes' => 'Additional Notes'] as $key => $label): ?>
        <?php if (!empty($details[$key])): ?><div class="md:col-span-2"><h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400"><?= $label ?></h3><div class="whitespace-pre-wrap rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm leading-relaxed text-slate-700"><?= htmlspecialchars($details[$key]) ?></div></div><?php endif; ?>
    <?php endforeach; ?>

    <?php if ($surgeonFiles): ?>
        <div class="md:col-span-2 border-t border-slate-100 pt-6">
            <h3 class="mb-4 text-base font-bold text-[#13324a]">Clinical Files</h3>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <?php foreach (['cbct' => ['CBCT Files', 'fa-x-ray'], 'lab' => ['Lab Results', 'fa-flask-vial']] as $category => $meta): ?>
                    <?php if ($fileGroups[$category]): ?><div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><h4 class="mb-3 text-sm font-bold text-[#13324a]"><i class="fa-solid <?= $meta[1] ?> mr-2 text-[#1d5f8c]"></i><?= $meta[0] ?></h4><div class="space-y-2">
                        <?php foreach ($fileGroups[$category] as $file): ?><a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-[#1d5f8c]"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-700"><?= htmlspecialchars($file['original_name']) ?></p><p class="text-xs text-slate-400"><?= number_format((float) $file['file_size'] / 1024 / 1024, 2) ?> MB</p></div><i class="fa-solid fa-download shrink-0 text-[#1d5f8c]"></i></a><?php endforeach; ?>
                    </div></div><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
