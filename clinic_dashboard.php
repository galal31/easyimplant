<?php
// clinic_dashboard.php
require_once 'includes/db_connect.php';
require_once __DIR__ . '/includes/system_settings.php';

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

// Fetch recent requests for this clinic
try {
    $stmtClinic = $pdo->prepare("SELECT country FROM users WHERE id = :user_id AND role = 'clinic'");
    $stmtClinic->execute([':user_id' => $user_id]);
    $clinicCountry = $stmtClinic->fetchColumn();
    $clinicIsInEgypt = strtolower((string) $clinicCountry) === 'egypt';

    $stmt = $pdo->prepare("
        SELECT id, service_type, status, created_at 
        FROM requests 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $requests = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Dashboard DB Error: " . $e->getMessage());
    $requests = [];
}

// Function to format status text and badge color
function getStatusBadge($status) {
    $badges = [
        'pending_review' => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-600 border border-amber-200">Pending Review</span>',
        'rejected'       => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-600 border border-red-200">Rejected</span>',
        'pending_payment'=> '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-orange-50 text-orange-600 border border-orange-200">Pending Payment</span>',
        'in_progress'    => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200">In Progress</span>',
        'completed'      => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200">Completed</span>'
    ];
    return $badges[$status] ?? '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600">Unknown</span>';
}

function getServiceType($type) {
    return $type === 'surgical_guide' ? 'Surgical Guide' : 'Surgeon Request';
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Clinic Dashboard | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
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
                    <div class="hidden sm:block text-right">
                        <p class="text-sm font-bold text-[#13324a] leading-tight"><?= htmlspecialchars($full_name) ?></p>
                        <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($clinic_name) ?></p>
                    </div>
                    <a href="logout.php" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 hover:border-red-100">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header & Action Buttons -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-[#13324a]">Dashboard</h1>
                <p class="text-sm text-slate-500 mt-1">Manage your clinic's requests and track their status.</p>
            </div>
            <div class="flex gap-3">
                <a href="request_guide.php" class="inline-flex items-center justify-center rounded-xl bg-[#1d5f8c] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#13324a] shadow-sm">
                    <i class="fa-solid fa-layer-group mr-2"></i> Request Surgical Guide
                </a>
                <?php if ($clinicIsInEgypt): ?>
                    <a href="request_surgeon.php" class="inline-flex items-center justify-center rounded-xl bg-[#2b8a9e] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#1e697a] shadow-sm">
                        <i class="fa-solid fa-user-doctor mr-2"></i> Request Surgeon
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100">
                <h2 class="text-lg font-bold text-[#13324a]">Recent Requests</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-slate-50 text-slate-500 font-semibold text-[11px]">
                        <tr>
                            <th scope="col" class="px-6 py-4">Request ID</th>
                            <th scope="col" class="px-6 py-4">Service Type</th>
                            <th scope="col" class="px-6 py-4">Date Submitted</th>
                            <th scope="col" class="px-6 py-4">Status</th>
                            <th scope="col" class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                    <i class="fa-solid fa-folder-open text-xl"></i>
                                </div>
                                <p class="text-sm font-semibold">No requests found</p>
                                <p class="text-xs mt-1">Start by creating a new request using the buttons above.</p>
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
                                    <?= date('M d, Y', strtotime($req['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?= getStatusBadge($req['status']) ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="view_request.php?id=<?= $req['id'] ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-200 hover:text-[#13324a]">
                                        View Details
                                    </a>
                                    
                                    <?php if ($req['status'] === 'pending_payment'): ?>
                                    <a href="upload_receipt.php?id=<?= $req['id'] ?>" class="inline-flex items-center justify-center rounded-lg bg-orange-100 px-3 py-1.5 text-xs font-bold text-orange-700 transition hover:bg-orange-200 ml-2">
                                        <i class="fa-solid fa-upload mr-1.5"></i> Upload Receipt
                                    </a>
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

</body>
</html>
