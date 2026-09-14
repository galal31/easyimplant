<?php
// admin_dashboard.php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';

try {
    // Quick Stats
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM requests WHERE status = 'pending_review') as pending_requests,
            (SELECT COUNT(*) FROM users WHERE role = 'clinic' AND status = 'approved') as active_clinics,
            (SELECT COUNT(*) FROM requests WHERE status = 'completed') as completed_requests
    ")->fetch();
    $clinic_accounts = getAllClinicAccountSummaries($pdo);
    $account_stats = [
        'total_implants' => 0,
        'free_implants' => 0,
        'guided_kit_rental_total' => 0,
        'approved_paid' => 0,
        'balance_due' => 0,
    ];

    foreach ($clinic_accounts as $account) {
        $account_stats['total_implants'] += $account['summary']['total_implants'];
        $account_stats['free_implants'] += $account['summary']['free_implants'];
        $account_stats['guided_kit_rental_total'] += $account['summary']['guided_kit_rental_total'];
        $account_stats['approved_paid'] += $account['summary']['approved_paid'];
        $account_stats['balance_due'] += $account['summary']['balance_due'];
    }
} catch (\PDOException $e) {
    error_log("Admin Dashboard DB Error: " . $e->getMessage());
    $stats = ['pending_requests' => 0, 'active_clinics' => 0, 'completed_requests' => 0];
    $account_stats = ['total_implants' => 0, 'free_implants' => 0, 'guided_kit_rental_total' => 0, 'approved_paid' => 0, 'balance_due' => 0];
}
?>

<h2 class="text-2xl font-bold text-[#13324a] mb-6">Dashboard Overview</h2>

<!-- Stats Row -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider">Pending Requests</p>
                <button type="button" data-dashboard-card-help="pending_requests" data-dashboard-card-value="<?= (int) $stats['pending_requests'] ?>" aria-label="شرح الطلبات المعلقة" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
            </div>
            <p class="text-3xl font-extrabold text-[#13324a] mt-1"><?= $stats['pending_requests'] ?></p>
        </div>
        <div class="h-14 w-14 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 text-xl">
            <i class="fa-solid fa-bell"></i>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider">Active Clinics</p>
                <button type="button" data-dashboard-card-help="active_clinics" data-dashboard-card-value="<?= (int) $stats['active_clinics'] ?>" aria-label="شرح العيادات النشطة" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
            </div>
            <p class="text-3xl font-extrabold text-[#13324a] mt-1"><?= $stats['active_clinics'] ?></p>
        </div>
        <div class="h-14 w-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-xl">
            <i class="fa-solid fa-hospital-user"></i>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider">Completed Cases</p>
                <button type="button" data-dashboard-card-help="completed_cases" data-dashboard-card-value="<?= (int) $stats['completed_requests'] ?>" aria-label="شرح الحالات المكتملة" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
            </div>
            <p class="text-3xl font-extrabold text-[#13324a] mt-1"><?= $stats['completed_requests'] ?></p>
        </div>
        <div class="h-14 w-14 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-xl">
            <i class="fa-solid fa-check-double"></i>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-6 mb-8">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Guide Implants</p>
            <button type="button" data-dashboard-card-help="guide_implants" data-dashboard-card-value="<?= (int) $account_stats['total_implants'] ?>" aria-label="شرح زرعات الأدلة" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
        </div>
        <p class="text-2xl font-extrabold text-[#13324a] mt-1"><?= (int) $account_stats['total_implants'] ?></p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Free Implants</p>
            <button type="button" data-dashboard-card-help="free_implants" data-dashboard-card-value="<?= (int) $account_stats['free_implants'] ?>" aria-label="شرح الزرعات المجانية" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
        </div>
        <p class="text-2xl font-extrabold text-emerald-600 mt-1"><?= (int) $account_stats['free_implants'] ?></p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Kit Rentals</p>
            <button type="button" data-dashboard-card-help="kit_rentals" data-dashboard-card-value="<?= htmlspecialchars(formatMoney($account_stats['guided_kit_rental_total']), ENT_QUOTES, 'UTF-8') ?>" aria-label="شرح إيجارات الكيت" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
        </div>
        <p class="text-xl font-extrabold text-[#1d5f8c] mt-1"><?= formatMoney($account_stats['guided_kit_rental_total']) ?></p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Approved Paid</p>
            <button type="button" data-dashboard-card-help="approved_paid" data-dashboard-card-value="<?= htmlspecialchars(formatMoney($account_stats['approved_paid']), ENT_QUOTES, 'UTF-8') ?>" aria-label="شرح المدفوع المعتمد" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
        </div>
        <p class="text-xl font-extrabold text-emerald-600 mt-1"><?= formatMoney($account_stats['approved_paid']) ?></p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center gap-2">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Balance Due</p>
            <button type="button" data-dashboard-card-help="balance_due" data-dashboard-card-value="<?= htmlspecialchars(formatMoney($account_stats['balance_due']), ENT_QUOTES, 'UTF-8') ?>" aria-label="شرح الرصيد المستحق" class="flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 transition hover:border-[#1d5f8c] hover:bg-[#1d5f8c] hover:text-white">?</button>
        </div>
        <p class="text-xl font-extrabold text-orange-600 mt-1"><?= formatMoney($account_stats['balance_due']) ?></p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center mt-6">
    <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-slate-50 text-[#1d5f8c] mb-4">
        <i class="fa-solid fa-compass text-2xl"></i>
    </div>
    <h3 class="text-lg font-bold text-[#13324a] mb-2">Welcome to the Admin Panel</h3>
    <p class="text-slate-500 max-w-md mx-auto mb-6">Use the navigation bar above to manage pending clinic approvals and review incoming service requests.</p>
    <div class="flex justify-center gap-4">
        <a href="admin_clinics.php" class="bg-[#13324a] hover:bg-[#1d5f8c] text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition">Manage Clinics</a>
        <a href="admin_requests.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-lg text-sm font-semibold transition">View Requests</a>
        <a href="admin_clinic_accounts.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-lg text-sm font-semibold transition">Clinic Accounts</a>
    </div>
</div>

<div id="dashboardCardHelpModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="dashboardCardHelpTitle">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" dir="rtl">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-6 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#1d5f8c] text-lg font-bold text-white">?</span>
                <h2 id="dashboardCardHelpTitle" class="text-lg font-bold text-[#13324a]"></h2>
            </div>
            <button type="button" data-dashboard-help-close aria-label="إغلاق الشرح" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-200 hover:text-slate-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="px-6 py-5 text-right">
            <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                <p class="text-xs font-bold text-blue-500">القيمة الحالية</p>
                <p id="dashboardCardHelpValue" class="mt-1 text-2xl font-extrabold text-[#13324a]"></p>
            </div>
            <p id="dashboardCardHelpBody" class="whitespace-pre-line text-sm leading-8 text-slate-600"></p>
        </div>
        <div class="border-t border-slate-100 px-6 py-4 text-left">
            <button type="button" data-dashboard-help-close class="rounded-xl bg-[#13324a] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#1d5f8c]">فهمت</button>
        </div>
    </div>
</div>

<script>
const dashboardCardHelp = {
    pending_requests: {
        title: 'الطلبات المعلقة',
        body: 'هذا الرقم هو عدد الطلبات الجديدة التي أرسلتها العيادات وما زالت تنتظر مراجعة الإدارة. يشمل طلبات الأدلة الجراحية وطلبات الجرّاحين.'
    },
    active_clinics: {
        title: 'العيادات النشطة',
        body: 'هذا الرقم هو عدد حسابات العيادات التي وافقت عليها الإدارة وتستطيع تسجيل الدخول واستخدام النظام. لا يشمل العيادات المنتظرة أو الموقوفة أو المرفوضة.'
    },
    completed_cases: {
        title: 'الحالات المكتملة',
        body: 'هذا الرقم هو عدد الطلبات التي انتهى تنفيذها بالكامل. يشمل طلبات الأدلة الجراحية وطلبات الجرّاحين التي حالتها مكتمل.'
    },
    guide_implants: {
        title: 'زرعات الأدلة',
        body: 'هذا الرقم هو مجموع عدد الزرعات المكتوبة داخل طلبات الأدلة الجراحية غير المرفوضة لكل العيادات. قد يشمل طلبات لم يكتمل تنفيذها بعد.'
    },
    free_implants: {
        title: 'الزرعات المجانية',
        body: 'هذا الرقم هو مجموع الزرعات المجانية المسجلة داخل طلبات الأدلة غير المرفوضة وفق نظام العرض. القاعدة الحالية تمنح زرعة مجانية عند الوصول إلى العدد المحدد في إعدادات التسعير.'
    },
    kit_rentals: {
        title: 'إيجارات الـ Guided Kit',
        body: 'هذا الرقم هو مجموع أسعار إيجار الـ Guided Kit داخل طلبات الأدلة الجراحية غير المرفوضة لكل العيادات. هذه الأسعار موجودة بالفعل ضمن قيمة الطلبات والرصيد المستحق وليست إضافة منفصلة عليهما.'
    },
    approved_paid: {
        title: 'المدفوع المعتمد',
        body: 'هذا الرقم هو مجموع الأموال الموجودة في إيصالات الدفع التي راجعتها الإدارة ووافقت عليها. الإيصالات المنتظرة أو المرفوضة لا تدخل في هذا الرقم.'
    },
    balance_due: {
        title: 'الرصيد المستحق',
        body: 'هذا الرقم هو إجمالي الأموال المتبقية على جميع العيادات. يحسب من قيمة طلبات الأدلة غير المرفوضة بما فيها إيجارات الـ Guided Kit، ثم يطرح المدفوعات المعتمدة، ويطبق أي خصم أو رسوم يدوية مسجلة داخل حساب العيادة.'
    }
};

function openDashboardCardHelp(key, value) {
    const content = dashboardCardHelp[key];
    const modal = document.getElementById('dashboardCardHelpModal');
    if (!content || !modal) return;
    document.getElementById('dashboardCardHelpTitle').textContent = content.title;
    document.getElementById('dashboardCardHelpValue').textContent = value;
    document.getElementById('dashboardCardHelpBody').textContent = content.body;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

function closeDashboardCardHelp() {
    const modal = document.getElementById('dashboardCardHelpModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
}

document.addEventListener('click', function(event) {
    const helpButton = event.target.closest('[data-dashboard-card-help]');
    if (helpButton) {
        openDashboardCardHelp(helpButton.dataset.dashboardCardHelp, helpButton.dataset.dashboardCardValue);
        return;
    }

    if (event.target.closest('[data-dashboard-help-close]') || event.target.id === 'dashboardCardHelpModal') {
        closeDashboardCardHelp();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeDashboardCardHelp();
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
