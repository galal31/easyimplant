<?php
// admin_requests.php
require_once 'includes/admin_header.php';

try {
    // Get All Requests
    $stmt_requests = $pdo->query("
        SELECT r.id, r.service_type, r.status, r.created_at, u.id AS clinic_id, u.clinic_name 
        FROM requests r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
    ");
    $recent_requests = $stmt_requests->fetchAll();
} catch (\PDOException $e) {
    error_log("Admin Requests DB Error: " . $e->getMessage());
    $recent_requests = [];
}

$requestsTable = adminTableState($recent_requests, ['id', 'clinic_name', 'service_type', 'status', 'created_at'], 'requests');
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h2 class="text-2xl font-bold text-[#13324a]">Service Requests</h2>
        <p class="text-slate-500 text-sm mt-1">Manage and track all incoming requests from clinics.</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
        <h3 class="text-lg font-bold text-[#13324a]">All Requests</h3>
    </div>
    <?php adminTableToolbar('requests', $requestsTable, 'Search by clinic, request, service or status...'); ?>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-white text-slate-500 font-semibold text-[11px]">
                <tr>
                    <th scope="col" class="px-6 py-4">ID</th>
                    <th scope="col" class="px-6 py-4">Clinic</th>
                    <th scope="col" class="px-6 py-4">Service</th>
                    <th scope="col" class="px-6 py-4">Status</th>
                    <th scope="col" class="px-6 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700 font-medium bg-white" data-admin-table-body="requests">
                <?php if (empty($requestsTable['rows'])): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                        <p class="text-sm font-semibold">No requests match your search.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($requestsTable['rows'] as $req): ?>
                    <tr id="request-row-<?= (int) $req['id'] ?>" class="hover:bg-slate-50/50 transition">
                        <td class="px-6 py-4 font-bold text-[#13324a]">#<?= str_pad($req['id'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td class="px-6 py-4">
                            <a href="admin_clinic_account.php?id=<?= (int) $req['clinic_id'] ?>" class="text-[#1d5f8c] font-bold hover:underline"><?= htmlspecialchars($req['clinic_name']) ?></a>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <?= $req['service_type'] == 'surgical_guide' ? '<i class="fa-solid fa-layer-group text-blue-400"></i>' : '<i class="fa-solid fa-user-doctor text-teal-400"></i>' ?>
                                <?= getServiceType($req['service_type']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4" id="status-cell-<?= $req['id'] ?>"><?= getStatusBadge($req['status']) ?></td>
                        <td class="px-6 py-4 text-right flex justify-end gap-2">
                            <?php if ($req['service_type'] !== 'surgical_guide'): ?>
                                <select onchange="updateStatus(<?= $req['id'] ?>, this.value)" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 focus:ring-[#1d5f8c] bg-slate-50 hover:bg-white cursor-pointer transition">
                                    <option value="" disabled selected>Change Status</option>
                                    <option value="pending_review" <?= $req['status'] == 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                                    <option value="rejected" <?= $req['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    <option value="pending_payment" <?= $req['status'] == 'pending_payment' ? 'selected' : '' ?>>Pending Payment</option>
                                    <option value="in_progress" <?= $req['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $req['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            <?php endif; ?>
                            <a href="admin_view_request.php?id=<?= $req['id'] ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-[#13324a] hover:text-white">View</a>
                            <button onclick="deleteRequest(<?= $req['id'] ?>)" class="inline-flex items-center justify-center rounded-lg bg-red-50 px-3 py-1.5 text-xs font-bold text-red-600 transition hover:bg-red-500 hover:text-white" title="Delete Request">
        <i class="fa-solid fa-trash"></i>
    </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php adminTablePagination('requests', $requestsTable); ?>
</div>
<script>
// 1. الدالة اللي بتعمل الـ Toast الكيوت اللي بيظهر على الجنب
function showToast(message, type = 'success') {
    const toastId = 'toast-' + Date.now();
    const isSuccess = type === 'success';

    // تحديد الألوان بناءً على نوع الرسالة عشان تليق مع الـ Theme بتاعك
    const bgColor = isSuccess ? 'bg-emerald-50' : 'bg-red-50';
    const textColor = isSuccess ? 'text-emerald-600' : 'text-red-600';
    const borderColor = isSuccess ? 'border-emerald-200' : 'border-red-200';
    const icon = isSuccess ? '<i class="fa-solid fa-circle-check text-lg"></i>' : '<i class="fa-solid fa-circle-exclamation text-lg"></i>';

    const toastHTML = `
        <div id="${toastId}" class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-lg border ${bgColor} ${textColor} ${borderColor} transition-all duration-500 transform translate-y-10 opacity-0 font-bold text-sm">
            ${icon}
            <span>${message}</span>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);

    // تأثير الدخول (بيطلع لفوق ويظهر)
    setTimeout(() => {
        toastElement.classList.remove('translate-y-10', 'opacity-0');
    }, 10);

    // تأثير الخروج (بينزل لتحت ويختفي بعد 3 ثواني)
    setTimeout(() => {
        toastElement.classList.add('translate-y-10', 'opacity-0');
        setTimeout(() => toastElement.remove(), 500);
    }, 3000);
}

// 2. دالة المسح اللي بتستخدم الـ Toast
async function deleteRequest(requestId) {
    // رسالة التأكيد العادية عشان دي عملية مسح نهائية للملفات
    if (!confirm('Are you absolutely sure you want to delete this request? This action cannot be undone.')) {
        return;
    }

    const btn = document.querySelector(`#request-row-${requestId} button[title="Delete Request"]`);
    const originalIcon = btn ? btn.innerHTML : '';
    if(btn) btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>'; // علامة تحميل مكان أيقونة المسح

    try {
        const response = await fetch('../api/delete_request.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `request_id=${requestId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            // إظهار رسالة النجاح الكيوت
            showToast(data.message, 'success');
            
            // إخفاء الصف بانسيابية
            const row = document.getElementById(`request-row-${requestId}`);
            if (row) {
                row.style.transition = "all 0.5s ease";
                row.style.opacity = "0";
                row.style.transform = "translateX(20px)";
                setTimeout(() => row.remove(), 500);
            } else {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showToast(data.message, 'error');
            if(btn) btn.innerHTML = originalIcon;
        }
    } catch (e) {
        showToast('A network error occurred.', 'error');
        if(btn) btn.innerHTML = originalIcon;
    }
}
</script>
<?php require_once 'includes/admin_footer.php'; ?>
