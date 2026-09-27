<?php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';
require_once __DIR__ . '/../includes/implant_types.php';
require_once __DIR__ . '/../includes/locations.php';

$clinicId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$clinicId) die('Invalid clinic ID.');

try {
    $account = getClinicAccount($pdo, $clinicId);
} catch (Throwable $e) {
    error_log('Clinic Account Detail Error: ' . $e->getMessage());
    $account = null;
}
if (!$account) die('Clinic not found.');

$clinic = $account['clinic'];
$summary = $account['summary'];
$rows = $account['rows'];
$onlinePayments = $account['online_payments'] ?? [];
$adjustments = $account['adjustments'] ?? [];
$freeHistory = $account['reward_ledger'] ?? [];
$rewardState = $account['reward_state'] ?? [];
$nextFreeImplantEvery = (int) ($account['next_free_implant_every'] ?? 0);
$implantUsage = getImplantTypeUsage($pdo, (int) $clinic['id']);
$completedImplants = (int) $summary['eligible_implants'];
$confirmedRewardRows = array_values(array_filter($freeHistory, static fn(array $entry): bool => ($entry['status'] ?? '') === 'confirmed'));
$completedFreeImplants = array_sum(array_map(static fn(array $entry): int => (int) $entry['free_implants'], $confirmedRewardRows));

$historyTable = adminTableState($rows, ['request_id', 'request_status', 'implant_type_name', 'delivery_method', 'guided_kit_source', 'guided_kit_name', 'guided_kit_type', 'guided_kit_rental_price', 'latest_payment_status', 'created_at'], 'guide_history');
$onlinePaymentsTable = adminTableState($onlinePayments, ['request_id', 'service_type', 'amount', 'currency', 'approved_at'], 'online_payments');
$adjustmentsTable = adminTableState($adjustments, ['adjustment_type', 'amount', 'reason', 'admin_name', 'created_at'], 'adjustments');
$freeHistoryTable = adminTableState($freeHistory, ['request_id', 'created_at', 'status', 'active_free_implant_every_before', 'progress_before', 'progress_after', 'active_free_implant_every_after', 'total_implants', 'free_implants'], 'free_history');
$clinicUsageTable = adminTableState($implantUsage, ['implant_type_name', 'request_count', 'implant_count', 'revenue', 'is_other'], 'clinic_implant_usage');
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
    <div>
        <a href="admin_clinic_accounts.php" class="mb-3 inline-flex items-center text-sm font-bold text-[#1d5f8c] hover:underline"><i class="fa-solid fa-arrow-left mr-2"></i> Clinic Accounts</a>
        <h2 class="text-2xl font-bold text-[#13324a]"><?= htmlspecialchars($clinic['clinic_name']) ?></h2>
        <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($clinic['full_name']) ?> / <?= htmlspecialchars($clinic['email']) ?> / <?= htmlspecialchars($clinic['phone']) ?> / <?= htmlspecialchars(formatClinicLocation($clinic['country'], $clinic['governorate'])) ?></p>
    </div>
    <a href="admin_edit_clinic.php?id=<?= (int) $clinic['id'] ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-[#13324a] hover:text-white"><i class="fa-solid fa-pen mr-2"></i> Edit Clinic</a>
</div>

<div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl bg-[#13324a] p-5 text-white shadow-sm">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold uppercase tracking-wider text-blue-100">Orders Value</p>
            <button type="button" data-account-card-help="orders_value" data-account-card-value="<?= htmlspecialchars(formatMoney($summary['total_price']), ENT_QUOTES, 'UTF-8') ?>" aria-label="شرح قيمة الطلبات" class="flex h-6 w-6 items-center justify-center rounded-full border border-white/30 bg-white/10 text-xs font-bold text-blue-100 transition hover:border-white hover:bg-white hover:text-[#13324a]">?</button>
        </div>
        <p class="mt-1 text-2xl font-extrabold"><?= formatMoney($summary['total_price']) ?></p>
        <p class="mt-2 text-xs text-blue-100">Non-rejected surgical guide requests</p>
    </div>
    <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-5 shadow-sm">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold uppercase tracking-wider text-[#1d5f8c]">Kit Rentals</p>
            <button type="button" data-account-card-help="kit_rentals" data-account-card-value="<?= htmlspecialchars(formatMoney($summary['guided_kit_rental_total']), ENT_QUOTES, 'UTF-8') ?>" aria-label="شرح إيجارات الكيت" class="flex h-6 w-6 items-center justify-center rounded-full border border-blue-200 bg-white text-xs font-bold text-[#1d5f8c] transition hover:bg-[#1d5f8c] hover:text-white">?</button>
        </div>
        <p class="mt-1 text-2xl font-extrabold text-[#1d5f8c]"><?= formatMoney($summary['guided_kit_rental_total']) ?></p>
        <p class="mt-2 text-xs text-slate-500"><?= (int) $summary['guided_kit_rental_requests'] ?> non-rejected request(s) with a rented kit</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Approved Paid</p>
            <button type="button" data-account-card-help="approved_paid" data-account-card-value="<?= htmlspecialchars(formatMoney($summary['approved_paid']), ENT_QUOTES, 'UTF-8') ?>" aria-label="شرح المدفوع المعتمد" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
        </div>
        <p class="mt-1 text-2xl font-extrabold text-emerald-600"><?= formatMoney($summary['approved_paid']) ?></p>
        <p class="mt-2 text-xs text-slate-500">Approved online payments for both services</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Awaiting Payment</p>
            <button type="button" data-account-card-help="awaiting_payment" data-account-card-value="<?= htmlspecialchars(formatMoney($summary['awaiting_payment']), ENT_QUOTES, 'UTF-8') ?>" aria-label="شرح المبالغ المنتظرة للدفع" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
        </div>
        <p class="mt-1 text-2xl font-extrabold <?= $summary['awaiting_payment'] > 0 ? 'text-orange-600' : 'text-emerald-600' ?>"><?= formatMoney($summary['awaiting_payment']) ?></p>
        <p class="mt-2 text-xs text-slate-500">Pending-payment requests without an approved payment</p>
    </div>
</div>

<div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 bg-slate-50 px-6 py-5"><h3 class="text-lg font-bold text-[#13324a]">Approved Online Payments</h3><p class="mt-1 text-sm text-slate-500">Completed payments for Surgical Guide and Surgeon Request services.</p></div>
    <?php adminTableToolbar('online_payments', $onlinePaymentsTable, 'Search online payments...'); ?>
    <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="border-b-2 border-slate-100 bg-white text-[11px] font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Request</th><th class="px-6 py-4">Service</th><th class="px-6 py-4">Amount</th><th class="px-6 py-4">Completed</th></tr></thead>
        <tbody class="divide-y divide-slate-100 text-slate-700" data-admin-table-body="online_payments">
            <?php if (!$onlinePaymentsTable['rows']): ?><tr><td colspan="4" class="px-6 py-10 text-center text-slate-500">No approved online payments yet.</td></tr><?php endif; ?>
            <?php foreach ($onlinePaymentsTable['rows'] as $onlinePayment): ?><tr><td class="px-6 py-4"><a href="admin_view_request.php?id=<?= (int) $onlinePayment['request_id'] ?>" class="font-bold text-[#1d5f8c] hover:underline">#<?= str_pad($onlinePayment['request_id'], 5, '0', STR_PAD_LEFT) ?></a></td><td class="px-6 py-4 font-semibold"><?= $onlinePayment['service_type'] === 'surgeon_request' ? 'Surgeon Request' : 'Surgical Guide' ?></td><td class="px-6 py-4 font-extrabold text-emerald-600"><?= formatCurrencyMoney($onlinePayment['amount'], $onlinePayment['currency']) ?></td><td class="px-6 py-4 text-slate-500"><?= date('M d, Y H:i', strtotime($onlinePayment['approved_at'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php adminTablePagination('online_payments', $onlinePaymentsTable); ?>
</div>

<div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 bg-slate-50 px-6 py-5"><h3 class="text-lg font-bold text-[#13324a]">Surgical Guide Requests</h3><p class="mt-1 text-sm text-slate-500">Prices, guided kits, implants and approved payments for this clinic.</p></div>
    <?php adminTableToolbar('guide_history', $historyTable, 'Search guide requests...'); ?>
    <div class="overflow-x-auto">
        <table class="w-full whitespace-nowrap text-left text-sm">
            <thead class="border-b-2 border-slate-100 bg-white text-[11px] font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Request</th><th class="px-6 py-4">Implant Type</th><th class="px-6 py-4">Guided Kit</th><th class="px-6 py-4">Upper</th><th class="px-6 py-4">Lower</th><th class="px-6 py-4">Total</th><th class="px-6 py-4">Free</th><th class="px-6 py-4">Price</th><th class="px-6 py-4">Paid</th><th class="px-6 py-4">Payment</th></tr></thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700" data-admin-table-body="guide_history">
                <?php if (!$historyTable['rows']): ?><tr><td colspan="10" class="px-6 py-12 text-center text-slate-500">No guide requests match your search.</td></tr><?php endif; ?>
                <?php foreach ($historyTable['rows'] as $row): ?>
                    <tr class="transition hover:bg-slate-50/60">
                        <td class="px-6 py-4"><a href="admin_view_request.php?id=<?= (int) $row['request_id'] ?>" class="font-bold text-[#1d5f8c] hover:underline">#<?= str_pad($row['request_id'], 5, '0', STR_PAD_LEFT) ?></a><div class="text-xs text-slate-500"><?= date('M d, Y', strtotime($row['created_at'])) ?></div></td>
                        <td class="px-6 py-4"><div class="font-bold text-[#13324a]"><?= htmlspecialchars($row['implant_type_name'] ?: 'Not specified') ?></div><?php if ((int) ($row['implant_type_is_other'] ?? 0)): ?><div class="text-xs font-bold text-orange-600">Other</div><?php endif; ?></td>
                        <td class="px-6 py-4">
                            <?php if (($row['guided_kit_source'] ?? '') === 'rental'): ?>
                                <div class="font-bold text-[#13324a]"><?= htmlspecialchars($row['guided_kit_name'] ?: 'Rental kit') ?></div>
                                <div class="text-xs font-bold text-[#1d5f8c]">Rental · <?= formatMoney($row['guided_kit_rental_price']) ?></div>
                            <?php elseif (($row['guided_kit_source'] ?? '') === 'owned'): ?>
                                <div class="font-bold text-[#13324a]"><?= htmlspecialchars($row['guided_kit_name'] ?: 'Clinic-owned kit') ?></div>
                                <div class="text-xs text-slate-500">Owned · <?= htmlspecialchars(ucfirst((string) ($row['guided_kit_type'] ?? ''))) ?></div>
                            <?php else: ?>
                                <span class="text-slate-400">Not recorded</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4"><?= (int) $row['upper_implants'] ?></td><td class="px-6 py-4"><?= (int) $row['lower_implants'] ?></td><td class="px-6 py-4 font-bold"><?= (int) $row['total_implants'] ?></td><td class="px-6 py-4 font-bold text-emerald-600"><?= (int) $row['free_implants'] ?></td><td class="px-6 py-4"><div class="font-bold"><?= formatMoney($row['total_price']) ?></div><?php if ((float) ($row['guided_kit_rental_price'] ?? 0) > 0): ?><div class="text-xs text-slate-400">Guide <?= formatMoney((float) $row['total_price'] - (float) $row['guided_kit_rental_price']) ?> + kit <?= formatMoney($row['guided_kit_rental_price']) ?></div><?php endif; ?></td><td class="px-6 py-4 font-bold text-emerald-600"><?= formatMoney($row['approved_amount']) ?></td><td class="px-6 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600"><?= htmlspecialchars(getPaymentStatusLabel($row['latest_payment_status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php adminTablePagination('guide_history', $historyTable); ?>
</div>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex gap-2 overflow-x-auto border-b border-slate-100 bg-slate-50 px-4 pt-4">
        <button type="button" data-account-tab="adjustments" class="account-tab whitespace-nowrap rounded-t-xl bg-[#13324a] px-5 py-3 text-sm font-bold text-white">Legacy Adjustments</button>
        <button type="button" data-account-tab="free-history" class="account-tab whitespace-nowrap rounded-t-xl px-5 py-3 text-sm font-bold text-slate-500 transition hover:bg-white hover:text-[#13324a]">Implant & Free History</button>
        <button type="button" data-account-tab="implant-usage" class="account-tab whitespace-nowrap rounded-t-xl px-5 py-3 text-sm font-bold text-slate-500 transition hover:bg-white hover:text-[#13324a]">Implant Type Usage</button>
    </div>

    <section data-account-panel="adjustments">
        <div class="border-b border-slate-100 px-6 py-5"><h3 class="text-lg font-bold text-[#13324a]">Legacy Adjustments</h3><p class="mt-1 text-sm text-slate-500">Historical manual discounts and charges. Read-only and excluded from current payment totals.</p></div>
        <?php adminTableToolbar('adjustments', $adjustmentsTable, 'Search adjustments...'); ?>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b-2 border-slate-100 bg-white text-[11px] font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Date</th><th class="px-6 py-4">Type</th><th class="px-6 py-4">Amount</th><th class="px-6 py-4">Reason</th><th class="px-6 py-4">Admin</th></tr></thead>
            <tbody class="divide-y divide-slate-100 text-slate-700" data-admin-table-body="adjustments">
                <?php if (!$adjustmentsTable['rows']): ?><tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No adjustments match your search.</td></tr><?php endif; ?>
                <?php foreach ($adjustmentsTable['rows'] as $adjustment): ?><tr class="transition hover:bg-slate-50/60"><td class="whitespace-nowrap px-6 py-4 text-slate-500"><?= date('M d, Y H:i', strtotime($adjustment['created_at'])) ?></td><td class="px-6 py-4 font-bold text-[#13324a]"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $adjustment['adjustment_type']))) ?></td><td class="whitespace-nowrap px-6 py-4 font-extrabold <?= (float) $adjustment['amount'] >= 0 ? 'text-orange-600' : 'text-emerald-600' ?>"><?= formatMoney($adjustment['amount']) ?></td><td class="min-w-64 whitespace-normal px-6 py-4"><?= nl2br(htmlspecialchars($adjustment['reason'])) ?></td><td class="whitespace-nowrap px-6 py-4"><?= htmlspecialchars($adjustment['admin_name'] ?: 'Admin') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php adminTablePagination('adjustments', $adjustmentsTable); ?>
    </section>

    <section data-account-panel="free-history" class="hidden">
        <div class="border-b border-slate-100 px-6 py-5">
            <h3 class="text-lg font-bold text-[#13324a]">Implant & Free History</h3>
            <p class="mt-1 text-sm text-slate-500">Clinic-specific protected progress, open reservations, and the immutable reward snapshot stored for every guide request.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 border-b border-slate-100 bg-blue-50/40 p-6 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-blue-100 bg-white p-4"><p class="text-xs font-bold uppercase text-slate-400">Protected Rule</p><p class="mt-1 text-2xl font-extrabold text-[#13324a]">Every <?= (int) ($rewardState['active_free_implant_every'] ?? 0) ?></p></div>
            <div class="rounded-xl border border-blue-100 bg-white p-4"><p class="text-xs font-bold uppercase text-slate-400">Current Progress</p><p class="mt-1 text-2xl font-extrabold text-[#13324a]"><?= (int) ($rewardState['progress_implants'] ?? 0) ?> / <?= (int) ($rewardState['active_free_implant_every'] ?? 0) ?></p></div>
            <div class="rounded-xl border border-blue-100 bg-white p-4"><p class="text-xs font-bold uppercase text-slate-400">Reserved Implants</p><p class="mt-1 text-2xl font-extrabold text-orange-600"><?= (int) ($rewardState['reserved_implants'] ?? 0) ?></p></div>
            <div class="rounded-xl border border-blue-100 bg-white p-4"><p class="text-xs font-bold uppercase text-slate-400">Next-Cycle Rule</p><p class="mt-1 text-2xl font-extrabold text-[#13324a]">Every <?= $nextFreeImplantEvery ?></p></div>
        </div>
        <div class="grid grid-cols-1 gap-4 border-b border-slate-100 bg-slate-50/60 p-6 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center gap-2"><p class="text-xs font-bold uppercase text-slate-400">Completed Orders</p><button type="button" data-account-card-help="completed_orders" data-account-card-value="<?= count($confirmedRewardRows) ?>" aria-label="شرح الطلبات المكتملة" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button></div><p class="mt-1 text-2xl font-extrabold text-[#13324a]"><?= count($confirmedRewardRows) ?></p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center gap-2"><p class="text-xs font-bold uppercase text-slate-400">Completed Implants</p><button type="button" data-account-card-help="completed_implants" data-account-card-value="<?= $completedImplants ?>" aria-label="شرح زرعات الطلبات المكتملة" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button></div><p class="mt-1 text-2xl font-extrabold text-[#13324a]"><?= $completedImplants ?></p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center gap-2"><p class="text-xs font-bold uppercase text-slate-400">Free Implants Given</p><button type="button" data-account-card-help="free_implants_given" data-account-card-value="<?= $completedFreeImplants ?>" aria-label="شرح الزرعات المجانية الممنوحة" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button></div><p class="mt-1 text-2xl font-extrabold text-emerald-600"><?= $completedFreeImplants ?></p></div>
        </div>
        <?php adminTableToolbar('free_history', $freeHistoryTable, 'Search implant and free history...'); ?>
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="border-b-2 border-slate-100 bg-white text-[11px] font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Request / Date</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Protected Rule</th><th class="px-6 py-4">Progress</th><th class="px-6 py-4">Implants</th><th class="px-6 py-4">Free Given</th><th class="px-6 py-4">Rule After</th></tr></thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700" data-admin-table-body="free_history">
                    <?php if (!$freeHistoryTable['rows']): ?><tr><td colspan="7" class="px-6 py-12 text-center text-slate-500">No reward progress history matches your search.</td></tr><?php endif; ?>
                    <?php foreach ($freeHistoryTable['rows'] as $entry): ?>
                        <tr class="transition hover:bg-slate-50/60"><td class="px-6 py-4"><a href="admin_view_request.php?id=<?= (int) $entry['request_id'] ?>" class="font-bold text-[#1d5f8c] hover:underline">#<?= str_pad($entry['request_id'], 5, '0', STR_PAD_LEFT) ?></a><div class="text-xs text-slate-500"><?= date('M d, Y', strtotime($entry['created_at'])) ?></div></td><td class="px-6 py-4 font-bold"><?= htmlspecialchars(ucfirst((string) $entry['status'])) ?></td><td class="px-6 py-4">Every <?= (int) $entry['active_free_implant_every_before'] ?></td><td class="px-6 py-4"><?= (int) $entry['progress_before'] ?> → <?= (int) $entry['progress_after'] ?></td><td class="px-6 py-4 font-bold"><?= (int) $entry['total_implants'] ?></td><td class="px-6 py-4 font-bold text-emerald-600"><?= (int) $entry['free_implants'] ?></td><td class="px-6 py-4">Every <?= (int) $entry['active_free_implant_every_after'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php adminTablePagination('free_history', $freeHistoryTable); ?>
    </section>

    <section data-account-panel="implant-usage" class="hidden">
        <div class="border-b border-slate-100 px-6 py-5"><h3 class="text-lg font-bold text-[#13324a]">Implant Type Usage</h3><p class="mt-1 text-sm text-slate-500">The implant types most frequently requested by this clinic.</p></div>
        <?php adminTableToolbar('clinic_implant_usage', $clinicUsageTable, 'Search implant usage...'); ?>
        <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="border-b-2 border-slate-100 bg-white text-[11px] font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Type</th><th class="px-6 py-4">Requests</th><th class="px-6 py-4">Implants</th><th class="px-6 py-4">Orders Value</th><th class="px-6 py-4">Source</th></tr></thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700" data-admin-table-body="clinic_implant_usage">
                <?php if (!$clinicUsageTable['rows']): ?><tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No implant usage matches your search.</td></tr><?php endif; ?>
                <?php foreach ($clinicUsageTable['rows'] as $usage): ?><tr class="transition hover:bg-slate-50/60"><td class="px-6 py-4 font-bold text-[#13324a]"><?= htmlspecialchars($usage['implant_type_name'] ?: 'Unknown') ?></td><td class="px-6 py-4"><?= (int) $usage['request_count'] ?></td><td class="px-6 py-4"><?= (int) $usage['implant_count'] ?></td><td class="px-6 py-4"><?= formatMoney($usage['revenue']) ?></td><td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold <?= (int) $usage['is_other'] ? 'border border-orange-100 bg-orange-50 text-orange-600' : 'border border-blue-100 bg-blue-50 text-[#1d5f8c]' ?>"><?= (int) $usage['is_other'] ? 'Other' : 'Catalog' ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php adminTablePagination('clinic_implant_usage', $clinicUsageTable); ?>
    </section>
</div>

<div id="clinicAccountCardHelpModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="clinicAccountCardHelpTitle">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" dir="rtl">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#1d5f8c] text-lg font-bold text-white">?</span>
                <h2 id="clinicAccountCardHelpTitle" class="text-lg font-bold text-[#13324a]"></h2>
            </div>
            <button type="button" data-account-help-close aria-label="إغلاق الشرح" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-200 hover:text-slate-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="px-6 py-5 text-right">
            <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                <p class="text-xs font-bold text-blue-500">القيمة الحالية</p>
                <p id="clinicAccountCardHelpValue" class="mt-1 text-2xl font-extrabold text-[#13324a]"></p>
            </div>
            <p id="clinicAccountCardHelpBody" class="whitespace-pre-line text-sm leading-8 text-slate-600"></p>
        </div>
        <div class="border-t border-slate-100 px-6 py-4 text-left">
            <button type="button" data-account-help-close class="rounded-xl bg-[#13324a] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#1d5f8c]">فهمت</button>
        </div>
    </div>
</div>

<script>
const clinicAccountCardHelp = {
    orders_value: {
        title: 'قيمة الطلبات',
        body: 'هذا الرقم هو مجموع الأسعار النهائية لطلبات الأدلة الجراحية غير المرفوضة الخاصة بهذه العيادة، ويشمل سعر إيجار الـ Guided Kit عندما تختار العيادة الإيجار. قد يشمل طلبات ما زالت قيد المراجعة أو الدفع أو التنفيذ، وليس الطلبات المكتملة فقط.'
    },
    kit_rentals: {
        title: 'إيجارات الـ Guided Kit',
        body: 'هذا الرقم هو مجموع أسعار إيجار الـ Guided Kit المحفوظة داخل طلبات الأدلة الجراحية غير المرفوضة لهذه العيادة. هو جزء من قيمة طلب الدليل وليس رسومًا مضافة مرة ثانية.'
    },
    approved_paid: {
        title: 'المدفوع المعتمد',
        body: 'هذا الرقم هو مجموع المدفوعات الإلكترونية المعتمدة لطلبات الدليل الجراحي وطلبات الجرّاح الخاصة بهذه العيادة.'
    },
    awaiting_payment: {
        title: 'بانتظار الدفع',
        body: 'هذا الرقم هو مجموع الأسعار النهائية لطلبات الدليل الجراحي وطلبات الجرّاح الموجودة في انتظار الدفع ولا تملك دفعة معتمدة. التسويات القديمة لا تدخل في الحساب.'
    },
    completed_orders: {
        title: 'الطلبات المكتملة',
        body: 'هذا الرقم هو عدد طلبات الأدلة الجراحية المكتملة لهذه العيادة والمسجلة في تاريخ الزرعات والمجاني. لا يشمل الطلبات غير المكتملة أو المرفوضة.'
    },
    completed_implants: {
        title: 'زرعات الطلبات المكتملة',
        body: 'هذا الرقم هو مجموع الزرعات الموجودة في طلبات الأدلة الجراحية المكتملة لهذه العيادة. لا يشمل زرعات الطلبات التي لم تكتمل بعد.'
    },
    free_implants_given: {
        title: 'الزرعات المجانية الممنوحة',
        body: 'هذا الرقم هو مجموع الزرعات المجانية المحفوظة فعليًا على طلبات الأدلة المكتملة لهذه العيادة وقت إنشاء كل طلب. لا يحسب زرعة مجانية مستقبلية ولا يعيد الحساب باستخدام القاعدة الحالية.'
    }
};

function openClinicAccountCardHelp(key, value) {
    const content = clinicAccountCardHelp[key];
    const modal = document.getElementById('clinicAccountCardHelpModal');
    if (!content || !modal) return;
    document.getElementById('clinicAccountCardHelpTitle').textContent = content.title;
    document.getElementById('clinicAccountCardHelpValue').textContent = value;
    document.getElementById('clinicAccountCardHelpBody').textContent = content.body;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

function closeClinicAccountCardHelp() {
    const modal = document.getElementById('clinicAccountCardHelpModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
}

const accountTabs = document.querySelectorAll('[data-account-tab]');
const accountPanels = document.querySelectorAll('[data-account-panel]');
accountTabs.forEach((button) => button.addEventListener('click', () => {
    accountTabs.forEach((tab) => {
        const active = tab === button;
        tab.classList.toggle('bg-[#13324a]', active);
        tab.classList.toggle('text-white', active);
        tab.classList.toggle('text-slate-500', !active);
    });
    accountPanels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.accountPanel !== button.dataset.accountTab));
}));

document.addEventListener('click', (event) => {
    const helpButton = event.target.closest('[data-account-card-help]');
    if (helpButton) {
        openClinicAccountCardHelp(helpButton.dataset.accountCardHelp, helpButton.dataset.accountCardValue);
        return;
    }
    if (event.target.closest('[data-account-help-close]') || event.target.id === 'clinicAccountCardHelpModal') {
        closeClinicAccountCardHelp();
        return;
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeClinicAccountCardHelp();
    }
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
