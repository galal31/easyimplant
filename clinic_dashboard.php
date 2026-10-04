<?php
// clinic_dashboard.php
require_once 'includes/db_connect.php';
require_once __DIR__ . '/includes/system_settings.php';
require_once __DIR__ . '/includes/xpay.php';
require_once __DIR__ . '/includes/user_language.php';

// Check if user is logged in and is a clinic
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$clinic_name = $_SESSION['clinic_name'];
$clinicSystemIconUrl = getSystemIconUrl($pdo);
$clinicIsInEgypt = false;
if (empty($_SESSION['request_workflow_csrf_token'])) {
    $_SESSION['request_workflow_csrf_token'] = bin2hex(random_bytes(32));
}
$requestWorkflowCsrfToken = $_SESSION['request_workflow_csrf_token'];

// Fetch recent requests for this clinic
try {
    $stmtClinic = $pdo->prepare("SELECT country FROM users WHERE id = :user_id AND role = 'clinic'");
    $stmtClinic->execute([':user_id' => $user_id]);
    $clinicCountry = $stmtClinic->fetchColumn();
    $clinicIsInEgypt = strtolower((string) $clinicCountry) === 'egypt';

    $stmt = $pdo->prepare("
        SELECT r.id, r.service_type, r.status, r.created_at,
            CASE
                WHEN r.service_type = 'surgical_guide' THEN sgd.total_price
                WHEN r.service_type = 'surgeon_request' THEN sr.total_price
                ELSE NULL
            END AS payable_total
        FROM requests r
        LEFT JOIN surgical_guide_details sgd ON sgd.request_id = r.id
        LEFT JOIN surgeon_requests sr ON sr.request_id = r.id
        WHERE r.user_id = :user_id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $requests = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Dashboard DB Error: " . $e->getMessage());
    $requests = [];
}

// Function to format status text and badge color
function getStatusBadge($status, $serviceType = null) {
    if ($status === 'cancelled') return '<span class="rounded-full bg-red-50 px-3 py-1 text-sm font-semibold text-red-700" data-i18n="status_cancelled_financial">Cancelled — financial review</span>';
    if ($serviceType === 'surgeon_request') {
        $labels = ['pending_review'=>['status_review_coordination','Review & coordination'],'in_progress'=>['status_paid_waiting_operation','Paid — awaiting operation'],'completed'=>['status_operation_performed','Operation performed']];
        if (isset($labels[$status])) return '<span class="rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700" data-i18n="'.$labels[$status][0].'">'.$labels[$status][1].'</span>';
    }
    $badges = [
        'pending_review' => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-600 border border-amber-200" data-i18n="status_pending_review">Pending Review</span>',
        'awaiting_clinic_approval' => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200" data-i18n="status_awaiting_approval">Awaiting Your Approval</span>',
        'rejected'       => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-600 border border-red-200" data-i18n="status_rejected">Rejected</span>',
        'pending_payment'=> '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-orange-50 text-orange-600 border border-orange-200" data-i18n="status_pending_payment">Pending Payment</span>',
        'in_progress'    => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200" data-i18n="status_in_progress">In Progress</span>',
        'completed'      => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200" data-i18n="status_completed">Completed</span>'
    ];
    return $badges[$status] ?? '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600" data-i18n="status_unknown">Unknown</span>';
}

function getServiceType($type) {
    return $type === 'surgical_guide'
        ? '<span data-i18n="surgical_guide">Surgical Guide</span>'
        : '<span data-i18n="surgeon_request">Surgeon Request</span>';
}
?>
<!DOCTYPE html>
<html lang="<?= userLanguageAttribute() ?>" dir="<?= userDirectionAttribute() ?>" data-i18n-title="dashboard_title">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Clinic Dashboard | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="css/user-i18n.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <!-- Top Navigation -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#13324a] text-white">
                        <?php if ($clinicSystemIconUrl): ?>
                            <img src="<?= htmlspecialchars($clinicSystemIconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Easy Implant" class="h-7 w-7 object-contain">
                        <?php else: ?>
                            <i class="fa-solid fa-tooth text-sm"></i>
                        <?php endif; ?>
                    </div>
                    <span class="font-bold text-[#13324a] text-lg">Easy Implant</span>
                </div>
                <div class="flex items-center gap-4">
                    <?php $userLanguageSwitcherCompact = true; include __DIR__ . '/includes/user_language_switcher.php'; ?>
                    <div class="hidden sm:block text-right">
                        <p class="text-sm font-bold text-[#13324a] leading-tight"><?= htmlspecialchars($full_name) ?></p>
                        <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($clinic_name) ?></p>
                    </div>
                    <a href="logout.php" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 hover:border-red-100">
                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> <span data-i18n="logout">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header & Action Buttons -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-[#13324a]" data-i18n="dashboard_heading">Dashboard</h1>
                <p class="text-sm text-slate-500 mt-1" data-i18n="dashboard_intro">Manage your clinic's requests and track their status.</p>
            </div>
            <div class="flex gap-3">
                <a href="request_guide.php" class="inline-flex items-center justify-center rounded-xl bg-[#1d5f8c] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#13324a] shadow-sm">
                    <i class="fa-solid fa-layer-group me-2"></i> <span data-i18n="request_guide">Request Surgical Guide</span>
                </a>
                <?php if ($clinicIsInEgypt): ?>
                    <a href="request_surgeon.php" class="inline-flex items-center justify-center rounded-xl bg-[#2b8a9e] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#1e697a] shadow-sm">
                        <i class="fa-solid fa-user-doctor me-2"></i> <span data-i18n="request_surgeon">Request Surgeon</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100">
                <h2 class="text-lg font-bold text-[#13324a]" data-i18n="recent_requests">Recent Requests</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm whitespace-nowrap">
                    <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-slate-50 text-slate-500 font-semibold text-[11px]">
                        <tr>
                            <th scope="col" class="px-6 py-4" data-i18n="request_id">Request ID</th>
                            <th scope="col" class="px-6 py-4" data-i18n="service_type">Service Type</th>
                            <th scope="col" class="px-6 py-4" data-i18n="date_submitted">Date Submitted</th>
                            <th scope="col" class="px-6 py-4" data-i18n="request_status_label">Status</th>
                            <th scope="col" class="px-6 py-4 text-end" data-i18n="action">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                    <i class="fa-solid fa-folder-open text-xl"></i>
                                </div>
                                <p class="text-sm font-semibold" data-i18n="no_requests">No requests found</p>
                                <p class="text-xs mt-1" data-i18n="no_requests_help">Start by creating a new request using the buttons above.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-[#13324a]">#REQ-<?= str_pad($req['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td class="px-6 py-4 flex items-center gap-2">
                                    <?php if($req['service_type'] == 'surgical_guide'): ?>
                                        <i class="fa-solid fa-layer-group text-blue-500"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-user-doctor text-teal-500"></i>
                                    <?php endif; ?>
                                    <?= getServiceType($req['service_type']) ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <time datetime="<?= htmlspecialchars(date('c', strtotime($req['created_at']))) ?>" data-localized-date><?= date('M d, Y', strtotime($req['created_at'])) ?></time>
                                </td>
                                <td class="px-6 py-4">
                                    <?= getStatusBadge($req['status'], $req['service_type']) ?>
                                </td>
                                <td class="px-6 py-4 text-end">
                                    <a href="view_request.php?id=<?= $req['id'] ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-200 hover:text-[#13324a]">
                                        <span data-i18n="view_details">View Details</span>
                                    </a>
                                    <?php if ($req['service_type']==='surgical_guide' && $req['status'] === 'pending_payment' && (float) ($req['payable_total'] ?? 0) > 0 && xpayIsConfigured()): ?>
                                    <form method="post" action="api/create_xpay_checkout.php" class="ms-2 inline-block">
                                        <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>" />
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($requestWorkflowCsrfToken) ?>" />
                                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#1d5f8c] px-3 py-1.5 text-xs font-bold text-white transition hover:bg-[#13324a]">
                                            <i class="fa-solid fa-lock me-1.5"></i> <span data-i18n="pay_now">Pay now</span>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="js/translations.js"></script>
    <script src="js/user-page-translations.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
