<?php
// admin_clinics.php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/locations.php';

try {
    // Get Pending Clinics
    $stmt_pending = $pdo->query("
        SELECT id, full_name, clinic_name, email, phone, country, governorate, created_at
        FROM users 
        WHERE role = 'clinic' AND status = 'pending' 
        ORDER BY created_at ASC
    ");
    $pending_clinics = $stmt_pending->fetchAll();

    // Get Registered Clinics
    $stmt_registered = $pdo->query("
        SELECT id, full_name, clinic_name, email, phone, country, governorate, status, created_at
        FROM users 
        WHERE role = 'clinic' AND status IN ('approved', 'paused') 
        ORDER BY created_at DESC
    ");
    $registered_clinics = $stmt_registered->fetchAll();
} catch (\PDOException $e) {
    error_log("Admin Clinics DB Error: " . $e->getMessage());
    $pending_clinics = [];
    $registered_clinics = [];
}

$pendingTable = adminTableState($pending_clinics, ['clinic_name', 'full_name', 'email', 'phone', 'country', 'governorate', 'created_at'], 'pending_clinics');
$registeredTable = adminTableState($registered_clinics, ['clinic_name', 'full_name', 'email', 'phone', 'country', 'governorate', 'status', 'created_at'], 'registered_clinics');
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <p class="mb-1 text-xs font-bold uppercase tracking-[0.18em] text-[#1d5f8c]">Clinics</p>
        <h2 class="text-2xl font-bold text-[#13324a]">Clinic Management</h2>
        <p class="text-slate-500 text-sm mt-1">Review pending requests and manage registered clinics.</p>
    </div>
    <a href="admin_edit_clinic.php" class="bg-[#13324a] hover:bg-[#1d5f8c] text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition flex items-center gap-2">
        <i class="fa-solid fa-plus"></i> Add New Clinic
    </a>
</div>

<?php
$clinicSection = 'management';
require 'includes/clinic_tabs.php';
?>

<!-- Pending Clinics Section -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
        <h3 class="text-lg font-bold text-[#13324a]">Pending Clinics</h3>
        <span class="bg-[#1d5f8c] text-white text-xs font-bold px-2.5 py-1 rounded-full"><?= count($pending_clinics) ?></span>
    </div>
    
    <?php adminTableToolbar('pending_clinics', $pendingTable, 'Search pending clinics...'); ?>
    <div class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-white text-slate-500 font-semibold text-[11px]">
                        <tr>
                            <th scope="col" class="px-6 py-4">Clinic</th>
                            <th scope="col" class="px-6 py-4">Contact</th>
                            <th scope="col" class="px-6 py-4">Location</th>
                            <th scope="col" class="px-6 py-4">Registered</th>
                            <th scope="col" class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 bg-white" data-admin-table-body="pending_clinics">
                        <?php if (!$pendingTable['rows']): ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No pending clinics match your search.</td></tr>
                        <?php endif; ?>
                        <?php foreach($pendingTable['rows'] as $clinic): ?>
                        <tr class="hover:bg-slate-50/50 transition" id="clinic-row-<?= (int) $clinic['id'] ?>">
                            <td class="px-6 py-4">
                                <p class="font-bold text-[#13324a]"><?= htmlspecialchars($clinic['clinic_name']) ?></p>
                                <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($clinic['full_name']) ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p><?= htmlspecialchars($clinic['phone']) ?></p>
                                <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($clinic['email']) ?></p>
                            </td>
                            <td class="px-6 py-4"><?= htmlspecialchars(formatClinicLocation($clinic['country'], $clinic['governorate'])) ?></td>
                            <td class="px-6 py-4 text-slate-500"><?= date('M j, Y', strtotime($clinic['created_at'])) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <button onclick="handleClinic(<?= (int) $clinic['id'] ?>, 'approved')" class="inline-flex items-center justify-center bg-emerald-50 hover:bg-emerald-500 hover:text-white text-emerald-600 border border-emerald-200 transition px-3 py-2 rounded-lg text-xs font-bold"><i class="fa-solid fa-check mr-1"></i> Approve</button>
                                    <button onclick="handleClinic(<?= (int) $clinic['id'] ?>, 'rejected')" class="inline-flex items-center justify-center bg-red-50 hover:bg-red-500 hover:text-white text-red-600 border border-red-200 transition px-3 py-2 rounded-lg text-xs font-bold"><i class="fa-solid fa-xmark mr-1"></i> Reject</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </div>
    <?php adminTablePagination('pending_clinics', $pendingTable); ?>
</div>

<!-- Registered Clinics Section -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
        <h3 class="text-lg font-bold text-[#13324a]">Registered Clinics</h3>
        <span class="bg-[#13324a] text-white text-xs font-bold px-2.5 py-1 rounded-full"><?= count($registered_clinics) ?></span>
    </div>
    
    <?php adminTableToolbar('registered_clinics', $registeredTable, 'Search registered clinics...'); ?>
    <div class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-white text-slate-500 font-semibold text-[11px]">
                        <tr>
                            <th scope="col" class="px-6 py-4">Clinic</th>
                            <th scope="col" class="px-6 py-4">Contact</th>
                            <th scope="col" class="px-6 py-4">Location</th>
                            <th scope="col" class="px-6 py-4">Status</th>
                            <th scope="col" class="px-6 py-4">Registered</th>
                            <th scope="col" class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 bg-white" data-admin-table-body="registered_clinics">
                        <?php if (!$registeredTable['rows']): ?>
                            <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">No registered clinics match your search.</td></tr>
                        <?php endif; ?>
                        <?php foreach($registeredTable['rows'] as $clinic): ?>
                        <tr class="hover:bg-slate-50/50 transition" id="registered-clinic-row-<?= (int) $clinic['id'] ?>">
                            <td class="px-6 py-4">
                                <p class="font-bold text-[#13324a]"><?= htmlspecialchars($clinic['clinic_name']) ?></p>
                                <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($clinic['full_name']) ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p><?= htmlspecialchars($clinic['phone']) ?></p>
                                <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($clinic['email']) ?></p>
                            </td>
                            <td class="px-6 py-4"><?= htmlspecialchars(formatClinicLocation($clinic['country'], $clinic['governorate'])) ?></td>
                            <td class="px-6 py-4">
                                <?php if($clinic['status'] === 'approved'): ?>
                                    <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-1 rounded-md">Active</span>
                                <?php else: ?>
                                    <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded-md">Paused</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-slate-500"><?= date('M j, Y', strtotime($clinic['created_at'])) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <?php if($clinic['status'] === 'approved'): ?>
                                        <button onclick="handleClinic(<?= (int) $clinic['id'] ?>, 'paused', true)" class="inline-flex items-center justify-center bg-orange-50 hover:bg-orange-500 hover:text-white text-orange-600 border border-orange-200 transition h-8 w-8 rounded-lg text-xs font-bold" title="Pause"><i class="fa-solid fa-pause"></i></button>
                                    <?php else: ?>
                                        <button onclick="handleClinic(<?= (int) $clinic['id'] ?>, 'approved', true)" class="inline-flex items-center justify-center bg-emerald-50 hover:bg-emerald-500 hover:text-white text-emerald-600 border border-emerald-200 transition h-8 w-8 rounded-lg text-xs font-bold" title="Activate"><i class="fa-solid fa-play"></i></button>
                                    <?php endif; ?>
                                    <a href="admin_clinic_account.php?id=<?= (int) $clinic['id'] ?>" class="inline-flex items-center justify-center bg-purple-50 hover:bg-purple-500 hover:text-white text-purple-600 border border-purple-200 transition h-8 w-8 rounded-lg text-xs font-bold" title="Account"><i class="fa-solid fa-wallet"></i></a>
                                    <a href="admin_edit_clinic.php?id=<?= (int) $clinic['id'] ?>" class="inline-flex items-center justify-center bg-blue-50 hover:bg-blue-500 hover:text-white text-blue-600 border border-blue-200 transition h-8 w-8 rounded-lg text-xs font-bold" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <button onclick="deleteClinic(<?= (int) $clinic['id'] ?>)" class="inline-flex items-center justify-center bg-red-50 hover:bg-red-500 hover:text-white text-red-600 border border-red-200 transition h-8 w-8 rounded-lg text-xs font-bold" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </div>
    <?php adminTablePagination('registered_clinics', $registeredTable); ?>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
