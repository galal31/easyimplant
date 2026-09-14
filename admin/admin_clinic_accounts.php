<?php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';
require_once __DIR__ . '/../includes/locations.php';

try {
    $accounts = getAllClinicAccountSummaries($pdo);
} catch (PDOException $e) {
    error_log('Clinic Accounts Error: ' . $e->getMessage());
    $accounts = [];
}

$totals = [
    'clinics' => count($accounts),
    'guide_requests' => 0,
    'total_implants' => 0,
    'free_implants' => 0,
    'total_price' => 0,
    'approved_paid' => 0,
    'balance_due' => 0,
    'discount_amount' => 0,
    'guided_kit_rental_requests' => 0,
    'guided_kit_rental_total' => 0,
    'manual_adjustments' => 0,
];

foreach ($accounts as $account) {
    foreach (['guide_requests', 'total_implants', 'free_implants', 'total_price', 'approved_paid', 'balance_due', 'discount_amount', 'guided_kit_rental_requests', 'guided_kit_rental_total', 'manual_adjustments'] as $key) {
        $totals[$key] += $account['summary'][$key];
    }
}

$accountsTable = adminTableState($accounts, [
    'clinic.clinic_name', 'clinic.full_name', 'clinic.email', 'clinic.phone', 'clinic.country', 'clinic.governorate', 'clinic.status',
    'summary.guide_requests', 'summary.total_implants', 'summary.total_price', 'summary.guided_kit_rental_total', 'summary.approved_paid', 'summary.balance_due'
], 'clinic_accounts');
?>

<div class="mb-6 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
    <div>
        <p class="mb-1 text-xs font-bold uppercase tracking-[0.18em] text-[#1d5f8c]">Clinics</p>
        <h2 class="text-2xl font-bold text-[#13324a]">Clinic Accounts</h2>
        <p class="text-slate-500 text-sm mt-1">Financial and implant history for every clinic.</p>
    </div>
    <a href="admin_guide_pricing.php" class="inline-flex items-center justify-center rounded-xl bg-[#13324a] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#1d5f8c]">
        <i class="fa-solid fa-tags mr-2"></i> Pricing Settings
    </a>
</div>

<?php
$clinicSection = 'accounts';
require 'includes/clinic_tabs.php';
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Clinics</p>
        <p class="text-3xl font-extrabold text-[#13324a] mt-1"><?= (int) $totals['clinics'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Implants</p>
        <p class="text-3xl font-extrabold text-[#13324a] mt-1"><?= (int) $totals['total_implants'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Kit Rentals</p>
        <p class="text-2xl font-extrabold text-[#1d5f8c] mt-1"><?= formatMoney($totals['guided_kit_rental_total']) ?></p>
        <p class="mt-1 text-xs text-slate-500"><?= (int) $totals['guided_kit_rental_requests'] ?> rented kit request(s)</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Approved Paid</p>
        <p class="text-2xl font-extrabold text-emerald-600 mt-1"><?= formatMoney($totals['approved_paid']) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Manual Adjustments</p>
        <p class="text-2xl font-extrabold <?= $totals['manual_adjustments'] >= 0 ? 'text-orange-600' : 'text-emerald-600' ?> mt-1"><?= formatMoney($totals['manual_adjustments']) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Balance Due</p>
        <p class="text-2xl font-extrabold text-orange-600 mt-1"><?= formatMoney($totals['balance_due']) ?></p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50">
        <h3 class="text-lg font-bold text-[#13324a]">All Clinic Accounts</h3>
    </div>
    <?php adminTableToolbar('clinic_accounts', $accountsTable, 'Search clinic accounts...'); ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-white text-slate-500 font-semibold text-[11px]">
                <tr>
                    <th class="px-6 py-4">Clinic</th>
                    <th class="px-6 py-4">Requests</th>
                    <th class="px-6 py-4">Implants</th>
                    <th class="px-6 py-4">Free</th>
                    <th class="px-6 py-4">Orders Value</th>
                    <th class="px-6 py-4">Kit Rentals</th>
                    <th class="px-6 py-4">Paid</th>
                    <th class="px-6 py-4">Adjustments</th>
                    <th class="px-6 py-4">Balance</th>
                    <th class="px-6 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700 font-medium" data-admin-table-body="clinic_accounts">
                <?php if (!$accountsTable['rows']): ?>
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-slate-500">No clinic accounts match your search.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($accountsTable['rows'] as $account): ?>
                    <?php $clinic = $account['clinic']; $summary = $account['summary']; ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <div class="font-bold text-[#13324a]"><?= htmlspecialchars($clinic['clinic_name']) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($clinic['full_name']) ?></div>
                            <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars(formatClinicLocation($clinic['country'], $clinic['governorate'])) ?></div>
                        </td>
                        <td class="px-6 py-4"><?= (int) $summary['guide_requests'] ?></td>
                        <td class="px-6 py-4">
                            <span class="font-bold"><?= (int) $summary['total_implants'] ?></span>
                            <span class="text-xs text-slate-400 ml-1">total</span>
                        </td>
                        <td class="px-6 py-4 text-emerald-600 font-bold"><?= (int) $summary['free_implants'] ?></td>
                        <td class="px-6 py-4"><?= formatMoney($summary['total_price']) ?></td>
                        <td class="px-6 py-4"><div class="font-bold text-[#1d5f8c]"><?= formatMoney($summary['guided_kit_rental_total']) ?></div><div class="text-xs text-slate-400"><?= (int) $summary['guided_kit_rental_requests'] ?> request(s)</div></td>
                        <td class="px-6 py-4 text-emerald-600 font-bold"><?= formatMoney($summary['approved_paid']) ?></td>
                        <td class="px-6 py-4 <?= ($summary['manual_adjustments'] ?? 0) >= 0 ? 'text-orange-600' : 'text-emerald-600' ?> font-bold"><?= formatMoney($summary['manual_adjustments'] ?? 0) ?></td>
                        <td class="px-6 py-4 <?= $summary['balance_due'] > 0 ? 'text-orange-600' : 'text-slate-500' ?> font-bold"><?= formatMoney($summary['balance_due']) ?></td>
                        <td class="px-6 py-4 text-right">
                            <a href="admin_clinic_account.php?id=<?= (int) $clinic['id'] ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-[#13324a] hover:text-white">
                                View Account
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php adminTablePagination('clinic_accounts', $accountsTable); ?>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
