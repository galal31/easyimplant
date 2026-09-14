<?php
// admin_view_request.php
require_once '../includes/db_connect.php';
require_once '../includes/surgical_guide_pricing.php';
require_once '../includes/surgical_guide_kits.php';
require_once '../includes/surgeon_services.php';
require_once '../includes/r2_config.php';
require_once '../includes/request_file_metadata.php';
require_once '../includes/request_review.php';

use Aws\Exception\AwsException;

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if (empty($_SESSION['request_workflow_csrf_token'])) {
    $_SESSION['request_workflow_csrf_token'] = bin2hex(random_bytes(32));
}
$request_workflow_csrf_token = $_SESSION['request_workflow_csrf_token'];

$request_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$request_id) {
    die("Invalid request ID.");
}

function getPresignedUrl($s3Client, $bucketName, $key) {
    if (empty($key)) return '#';
    try {
        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $bucketName,
            'Key'    => $key
        ]);
        $request = $s3Client->createPresignedRequest($cmd, '+60 minutes');
        return (string) $request->getUri();
    } catch (AwsException $e) {
        error_log("Presigned URL Error: " . $e->getMessage());
        return '#';
    }
}

try {
    $guideKitFiles = [];
    $deliverables = [];
    $reviewPackages = [];
    $requestMessages = [];
    // Get Request Basic Info
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as doctor_name, u.clinic_name, u.phone, u.email, u.country FROM requests r JOIN users u ON r.user_id = u.id WHERE r.id = :id");
    $stmt->execute([':id' => $request_id]);
    $request = $stmt->fetch();

    if (!$request) die("Request not found.");

    if ($request['service_type'] === 'surgical_guide') {
        ensureSurgicalGuideKitsSchema($pdo);
        $stmt_details = $pdo->prepare("SELECT * FROM surgical_guide_details WHERE request_id = :id");
        $stmt_details->execute([':id' => $request_id]);
        $details = $stmt_details->fetch();

        $stmt_kit_files = $pdo->prepare("SELECT file_path, original_name, content_type, file_size FROM surgical_guide_kit_files WHERE request_id = :id ORDER BY created_at, id");
        $stmt_kit_files->execute([':id' => $request_id]);
        $guideKitFiles = $stmt_kit_files->fetchAll();

        $stmt_deliverables = $pdo->prepare("SELECT file_type, file_path, original_name, content_type, file_size, created_at
            FROM request_deliverables WHERE request_id = :id ORDER BY created_at, id");
        $stmt_deliverables->execute([':id' => $request_id]);
        foreach ($stmt_deliverables->fetchAll() as $file) {
            $deliverables[$file['file_type']][] = $file;
        }
        $reviewPackages = fetchRequestReviewPackages($pdo, (int) $request_id);
        $requestMessages = fetchRequestMessages($pdo, (int) $request_id);
    } else {
        $stmt_details = $pdo->prepare("SELECT * FROM surgeon_requests WHERE request_id = :id");
        $stmt_details->execute([':id' => $request_id]);
        $details = $stmt_details->fetch();

        $stmt_arches = $pdo->prepare("SELECT * FROM surgeon_request_arches WHERE request_id = :id ORDER BY FIELD(arch_position, 'upper', 'lower')");
        $stmt_arches->execute([':id' => $request_id]);
        $surgeonArches = $stmt_arches->fetchAll();

        $stmt_files = $pdo->prepare("SELECT * FROM surgeon_request_files WHERE request_id = :id ORDER BY file_category, created_at, id");
        $stmt_files->execute([':id' => $request_id]);
        $surgeonFiles = $stmt_files->fetchAll();
    }

    $stmt_pay = $pdo->prepare("SELECT * FROM payments WHERE request_id = :id ORDER BY uploaded_at DESC LIMIT 1");
    $stmt_pay->execute([':id' => $request_id]);
    $payment = $stmt_pay->fetch();

    $clinic_account = null;
    $clinic_cycle_completed_implants = 0;
    if ($request['service_type'] === 'surgical_guide') {
        $clinic_account = getClinicAccount($pdo, (int) $request['user_id']);
        if (!empty($details['free_rule_cycle_id'])) {
            $clinic_cycle_completed_implants = getClinicCompletedGuideImplants(
                $pdo,
                (int) $request['user_id'],
                (int) $details['free_rule_cycle_id']
            );
        }
    }

    $stmt_surgeons = $pdo->query("SELECT id, full_name FROM users WHERE role = 'surgeon' AND status = 'approved'");
    $surgeons = $stmt_surgeons->fetchAll();

    $stmt_logs = $pdo->prepare("SELECT l.*, u.full_name AS actor_name
        FROM request_activity_logs l
        LEFT JOIN users u ON u.id = l.actor_id
        WHERE l.request_id = :id
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT 50");
    $stmt_logs->execute([':id' => $request_id]);
    $activity_logs = $stmt_logs->fetchAll();

} catch (\PDOException $e) {
    die("Database error occurred.");
}

function getStatusBadge($status) {
    $badges = [
        'pending_review' => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-amber-50 text-amber-600 border border-amber-200">Pending Review</span>',
        'awaiting_clinic_approval' => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200">Awaiting Clinic Approval</span>',
        'rejected'       => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-50 text-red-600 border border-red-200">Rejected</span>',
        'pending_payment'=> '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-orange-50 text-orange-600 border border-orange-200">Pending Payment</span>',
        'in_progress'    => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200">In Progress</span>',
        'completed'      => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200">Completed</span>'
    ];
    return $badges[$status] ?? '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-slate-100 text-slate-600">Unknown</span>';
}

function formatLabel($key) { return ucwords(str_replace('_', ' ', $key)); }
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View Request #<?= $request['id'] ?> | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
    <nav class="bg-[#13324a] text-white border-b border-[#0f2233] sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <a href="admin_dashboard.php" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20"><i class="fa-solid fa-arrow-left text-sm"></i></a>
                    <span class="font-bold text-white text-lg">Request Details</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-extrabold text-[#13324a]">Request #<?= str_pad($request['id'], 5, '0', STR_PAD_LEFT) ?></h1>
                <p class="text-sm text-slate-500 mt-1">Submitted on <?= date('F j, Y, g:i a', strtotime($request['created_at'])) ?></p>
            </div>
            <div><?= getStatusBadge($request['status']) ?></div>
        </div>

        <?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
            <div class="mb-6 rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-extrabold mb-1"><i class="fa-solid fa-circle-exclamation mr-2"></i>Rejection Reason</p>
                <p><?= nl2br(htmlspecialchars($request['rejection_reason'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-[#13324a] mb-4 border-b border-slate-100 pb-3">Clinic Information</h2>
                    <ul class="space-y-3 text-sm text-slate-600">
                        <li><span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Clinic Name</span><span class="font-semibold text-slate-800"><?= htmlspecialchars($request['clinic_name']) ?></span></li>
                        <li><span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Doctor</span><span class="font-semibold text-slate-800"><?= htmlspecialchars($request['doctor_name']) ?></span></li>
                        <li><span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Country</span><span class="font-semibold text-slate-800 uppercase"><?= htmlspecialchars($request['country']) ?></span></li>
                        <li class="pt-2 border-t border-slate-100">
                            <a href="tel:<?= htmlspecialchars($request['phone']) ?>" class="flex items-center text-[#1d5f8c] hover:underline mb-1"><i class="fa-solid fa-phone w-5"></i> <?= htmlspecialchars($request['phone']) ?></a>
                            <a href="mailto:<?= htmlspecialchars($request['email']) ?>" class="flex items-center text-[#1d5f8c] hover:underline"><i class="fa-solid fa-envelope w-5"></i> <?= htmlspecialchars($request['email']) ?></a>
                        </li>
                    </ul>
                    <a href="admin_clinic_account.php?id=<?= (int) $request['user_id'] ?>" class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-[#13324a] hover:text-white">
                        <i class="fa-solid fa-wallet mr-2"></i> View Clinic Account
                    </a>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <?php if ($request['service_type'] === 'surgical_guide'): ?>
                        <?php
                            $guide_steps = ['pending_review', 'awaiting_clinic_approval', 'pending_payment', 'in_progress', 'completed'];
                            $current_step = array_search($request['status'], $guide_steps, true);
                            $current_step = $current_step === false ? -1 : $current_step;
                        ?>
                        <h2 class="text-lg font-bold text-[#13324a] mb-4 border-b border-slate-100 pb-3">Request Workflow</h2>
                        <div class="grid grid-cols-5 gap-1 mb-5 text-center">
                            <?php foreach (['Admin review', 'Clinic approval', 'Payment', 'Production', 'Complete'] as $index => $step_label): ?>
                                <div>
                                    <div class="h-2 rounded-full <?= $request['status'] !== 'rejected' && $index <= $current_step ? 'bg-[#1d5f8c]' : 'bg-slate-200' ?>"></div>
                                    <span class="mt-1.5 block text-[10px] font-bold <?= $request['status'] !== 'rejected' && $index <= $current_step ? 'text-[#1d5f8c]' : 'text-slate-400' ?>"><?= $step_label ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($request['status'] === 'pending_review'): ?>
                            <p class="mb-4 text-sm text-slate-600">Review the case, then send at least one explanatory file below. Payment cannot be requested before the clinic approves the latest review.</p>
                        <?php elseif ($request['status'] === 'awaiting_clinic_approval'): ?>
                            <div class="mb-4 rounded-xl border border-cyan-100 bg-cyan-50 p-3 text-sm text-cyan-800">
                                The clinic is reviewing the latest package. You can send a revised package without deleting the earlier rounds.
                            </div>
                        <?php elseif ($request['status'] === 'pending_payment'): ?>
                            <div class="mb-4 rounded-xl border border-orange-100 bg-orange-50 p-3 text-sm text-orange-700">
                                The clinic approved the plan. Online payment is not connected yet; manual receipts are disabled for Surgical Guides.
                            </div>
                        <?php elseif ($request['status'] === 'in_progress'): ?>
                            <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-700">
                                Complete this request by saving the required delivery package below.
                            </div>
                        <?php elseif ($request['status'] === 'completed'): ?>
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">
                                This request is complete and locked.
                            </div>
                        <?php elseif ($request['status'] === 'rejected'): ?>
                            <div class="rounded-xl border border-red-100 bg-red-50 p-3 text-sm font-semibold text-red-700">
                                This request is rejected and locked.
                            </div>
                        <?php endif; ?>

                        <?php if (in_array($request['status'], ['pending_review', 'awaiting_clinic_approval', 'pending_payment', 'in_progress'], true)): ?>
                            <div class="mt-5 border-t border-slate-100 pt-4">
                                <textarea id="guideRejectionReason" rows="3" class="w-full text-sm border border-red-200 rounded-xl px-3 py-2.5 focus:ring-red-400 focus:border-red-400 bg-red-50 mb-3" placeholder="Rejection reason (optional)"></textarea>
                                <button onclick="updateGuideStatus(<?= $request['id'] ?>, 'rejected')" class="w-full bg-red-50 hover:bg-red-500 text-red-600 hover:text-white border border-red-200 font-bold py-2.5 rounded-xl transition text-sm">
                                    Reject Request
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <h2 class="text-lg font-bold text-[#13324a] mb-4 border-b border-slate-100 pb-3">Update Status</h2>
                        <select id="statusSelect" class="w-full text-sm border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-white mb-3">
                            <option value="pending_review" <?= $request['status'] === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="rejected" <?= $request['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="pending_payment" <?= $request['status'] === 'pending_payment' ? 'selected' : '' ?>>Pending Payment</option>
                            <option value="in_progress" <?= $request['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $request['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                        <textarea id="rejectionReason" rows="3" class="hidden w-full text-sm border border-red-200 rounded-xl px-3 py-2.5 focus:ring-red-400 focus:border-red-400 bg-red-50 mb-3" placeholder="Required reason if rejected"><?= htmlspecialchars($request['rejection_reason'] ?? '') ?></textarea>
                        <button onclick="updateStatus(<?= $request['id'] ?>)" class="w-full bg-[#13324a] hover:bg-[#1d5f8c] text-white font-bold py-2.5 rounded-xl transition text-sm">Save Status</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                        <?php if($request['service_type'] == 'surgical_guide'): ?>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-500 text-xl"><i class="fa-solid fa-layer-group"></i></div><h2 class="text-xl font-bold text-[#13324a]">Surgical Guide Request</h2>
                        <?php else: ?>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-500 text-xl"><i class="fa-solid fa-user-doctor"></i></div><h2 class="text-xl font-bold text-[#13324a]">Surgeon Request</h2>
                        <?php endif; ?>
                    </div>

                    <?php if ($details): ?>
                        <?php if ($request['service_type'] === 'surgical_guide'): ?>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <div class="md:col-span-2 rounded-2xl bg-[#13324a] text-white p-5">
                                    <p class="text-xs font-bold uppercase tracking-wider text-blue-100">Final Price</p>
                                    <p class="text-3xl font-extrabold mt-1"><?= formatMoney($details['total_price'] ?? 0) ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 border border-slate-100 p-5">
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Implants</p>
                                    <p class="text-xl font-extrabold text-[#13324a] mt-1"><?= (int) ($details['total_implants'] ?? 0) ?></p>
                                </div>
                                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-5">
                                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Free</p>
                                    <p class="text-xl font-extrabold text-emerald-700 mt-1"><?= (int) ($details['free_implants'] ?? 0) ?></p>
                                </div>
                            </div>

                            <div class="mb-6 rounded-2xl border border-slate-200 overflow-hidden">
                                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-[#13324a]">Implant Locations & Price Breakdown</h3>
                                    <span class="text-xs font-bold text-emerald-600">Discount: -<?= formatMoney($details['discount_amount'] ?? 0) ?></span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100">
                                    <div class="p-4">
                                        <div class="flex justify-between mb-3">
                                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Upper Arch</p>
                                            <p class="text-xs font-bold text-[#13324a]"><?= formatMoney($details['upper_subtotal'] ?? 0) ?></p>
                                        </div>
                                        <?php foreach (GUIDE_UPPER_REGIONS as $region): ?>
                                            <div class="flex justify-between text-sm py-1.5">
                                                <span class="text-slate-600"><?= GUIDE_REGION_LABELS[$region] ?></span>
                                                <span class="font-bold text-[#13324a]"><?= (int) ($details[$region . '_implants'] ?? 0) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="p-4">
                                        <div class="flex justify-between mb-3">
                                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Lower Arch</p>
                                            <p class="text-xs font-bold text-[#13324a]"><?= formatMoney($details['lower_subtotal'] ?? 0) ?></p>
                                        </div>
                                        <?php foreach (GUIDE_LOWER_REGIONS as $region): ?>
                                            <div class="flex justify-between text-sm py-1.5">
                                                <span class="text-slate-600"><?= GUIDE_REGION_LABELS[$region] ?></span>
                                                <span class="font-bold text-[#13324a]"><?= (int) ($details[$region . '_implants'] ?? 0) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 p-4 bg-slate-50 border-t border-slate-100 text-sm">
                                    <div><span class="text-slate-500">Print fee:</span> <span class="font-bold"><?= formatMoney($details['print_fee'] ?? 0) ?></span></div>
                                    <div><span class="text-slate-500">Paid implants:</span> <span class="font-bold"><?= (int) ($details['paid_implants'] ?? 0) ?></span></div>
                                    <div><span class="text-slate-500">Free rule used:</span> <span class="font-bold">Every <?= (int) ($details['free_implant_every_used'] ?? 0) ?></span></div>
                                    <div><span class="text-slate-500">Completed in this cycle:</span> <span class="font-bold"><?= $clinic_cycle_completed_implants ?></span></div>
                                </div>
                                <?php if ((float) ($details['guided_kit_rental_price'] ?? 0) > 0): ?>
                                    <div class="flex items-center justify-between border-t border-cyan-100 bg-cyan-50 px-4 py-3 text-sm">
                                        <span class="font-semibold text-cyan-800">Guided kit rental</span>
                                        <span class="font-bold text-cyan-900"><?= formatMoney($details['guided_kit_rental_price']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Delivery Method -->
                                <div class="col-span-1 md:col-span-2">
                                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Delivery Method</h3>
                                    <div class="flex items-center gap-3 text-sm font-bold <?= $details['delivery_method'] === 'clinic_print' ? 'text-[#1d5f8c] bg-blue-50 border-blue-100' : 'text-purple-700 bg-purple-50 border-purple-100' ?> p-4 rounded-xl border">
                                        <i class="fa-solid <?= $details['delivery_method'] === 'clinic_print' ? 'fa-print' : 'fa-truck' ?> text-lg"></i>
                                        <?= $details['delivery_method'] === 'clinic_print' ? 'Clinic will print it' : 'Admin will print & deliver physical guide' ?>
                                    </div>
                                </div>

                                <!-- Implant Type -->
                                <div>
                                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Implant Type</h3>
                                    <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-100 font-semibold">
                                        <?= htmlspecialchars($details['implant_type'] ?: 'Not specified') ?>
                                    </div>
                                </div>

                                <!-- Operation Date -->
                                <div>
                                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Operation Date</h3>
                                    <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-100 font-semibold">
                                        <?= date('F j, Y', strtotime($details['operation_date'])) ?>
                                    </div>
                                </div>

                                <?php if (!empty($details['guided_kit_source'])): ?>
                                <div class="col-span-1 md:col-span-2 rounded-2xl border border-blue-100 bg-blue-50/50 p-4">
                                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-[#1d5f8c]">Guided Kit</h3>
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div><p class="text-xs text-slate-500">Source</p><p class="mt-1 text-sm font-bold text-[#13324a]"><?= ($details['guided_kit_source'] ?? '') === 'rental' ? 'Rental from Easy Implant' : 'Clinic-owned kit' ?></p></div>
                                        <div><p class="text-xs text-slate-500">Kit name</p><p class="mt-1 text-sm font-bold text-[#13324a]"><?= htmlspecialchars($details['guided_kit_name'] ?: 'Not specified') ?></p></div>
                                        <div><p class="text-xs text-slate-500">Kit type</p><p class="mt-1 text-sm font-bold text-[#13324a]"><?= !empty($details['guided_kit_type']) ? htmlspecialchars(ucfirst($details['guided_kit_type'])) : 'Not applicable' ?></p></div>
                                    </div>
                                    <?php if (!empty($guideKitFiles)): ?>
                                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                            <?php foreach ($guideKitFiles as $file): ?>
                                                <?php $kitFileName = uploadedFileDisplayName($file['original_name'] ?? null, $file['file_path']); ?>
                                                <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="flex items-center justify-between gap-3 rounded-xl border border-blue-100 bg-white p-3 text-[#13324a] transition hover:border-[#1d5f8c] hover:shadow-sm">
                                                    <span class="min-w-0"><span class="block truncate text-sm font-semibold" title="<?= htmlspecialchars($kitFileName) ?>"><i class="fa-regular fa-image mr-2 text-[#1d5f8c]"></i><?= htmlspecialchars($kitFileName) ?></span><span class="mt-1 block text-xs font-normal text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'] ?? null, $kitFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'] ?? null)) ?></span></span>
                                                    <span class="shrink-0 text-xs font-bold text-[#1d5f8c]">View photo <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif (($details['guided_kit_source'] ?? '') === 'owned'): ?>
                                        <div class="mt-4 rounded-xl border border-dashed border-blue-200 bg-white/70 p-3 text-sm text-slate-500">
                                            <i class="fa-regular fa-images mr-2 text-[#1d5f8c]"></i>No kit photos were attached. Photos are optional.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <!-- CBCT File -->
                                <div>
                                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">CBCT Scan</h3>
                                    <?php $cbctName = uploadedFileDisplayName($details['cbct_original_name'] ?? null, $details['cbct_file_path']); ?>
                                    <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl p-3">
                                        <div class="flex items-center gap-3 overflow-hidden">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm text-slate-400">
                                                <i class="fa-solid fa-x-ray text-lg"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-700 truncate" title="<?= htmlspecialchars($cbctName) ?>"><?= htmlspecialchars($cbctName) ?></p>
                                                <p class="mt-0.5 text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($details['cbct_content_type'] ?? null, $cbctName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($details['cbct_file_size'] ?? null)) ?></p>
                                            </div>
                                        </div>
                                        <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $details['cbct_file_path'])) ?>" target="_blank" class="shrink-0 ml-3 inline-flex items-center justify-center rounded-lg bg-[#1d5f8c] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#13324a] shadow-sm">
                                            <i class="fa-solid fa-download mr-1.5"></i> Download
                                        </a>
                                    </div>
                                </div>

                                <!-- STL File -->
                                <div>
                                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Intraoral Scan (STL)</h3>
                                    <?php $stlName = uploadedFileDisplayName($details['stl_original_name'] ?? null, $details['stl_file_path']); ?>
                                    <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl p-3">
                                        <div class="flex items-center gap-3 overflow-hidden">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm text-slate-400">
                                                <i class="fa-solid fa-tooth text-lg"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-700 truncate" title="<?= htmlspecialchars($stlName) ?>"><?= htmlspecialchars($stlName) ?></p>
                                                <p class="mt-0.5 text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($details['stl_content_type'] ?? null, $stlName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($details['stl_file_size'] ?? null)) ?></p>
                                            </div>
                                        </div>
                                        <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $details['stl_file_path'])) ?>" target="_blank" class="shrink-0 ml-3 inline-flex items-center justify-center rounded-lg bg-[#1d5f8c] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#13324a] shadow-sm">
                                            <i class="fa-solid fa-download mr-1.5"></i> Download
                                        </a>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <?php if (!empty($details['notes'])): ?>
                                <div class="col-span-1 md:col-span-2">
                                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Notes</h3>
                                    <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-100 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($details['notes']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <section class="mt-8 border-t border-slate-200 pt-8" aria-labelledby="reviewPackagesTitle">
                                <div class="mb-6 flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"><i class="fa-solid fa-file-waveform"></i></div>
                                    <div>
                                        <h2 id="reviewPackagesTitle" class="text-lg font-bold text-[#13324a]">Case review & explanation for the clinic</h2>
                                        <p class="mt-1 text-sm text-slate-500">Each send creates a permanent review round. These files are separate from the final delivery package.</p>
                                    </div>
                                </div>

                                <?php if (in_array($request['status'], ['pending_review', 'awaiting_clinic_approval'], true)): ?>
                                <form id="reviewPackageForm" class="mb-7 rounded-2xl border border-cyan-100 bg-cyan-50/40 p-5">
                                    <div id="reviewPackageError" class="mb-4 hidden rounded-xl border border-red-100 bg-red-50 p-3 text-sm font-semibold text-red-700"></div>
                                    <div id="reviewPackageSuccess" class="mb-4 hidden rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700"></div>
                                    <label for="reviewSummary" class="mb-2 block text-sm font-bold text-[#13324a]">Explanation <span class="font-medium text-slate-400">(optional)</span></label>
                                    <textarea id="reviewSummary" maxlength="5000" rows="4" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 focus:border-[#1d5f8c] focus:ring-[#1d5f8c]" placeholder="Explain the plan, important findings, or what changed in this review round."></textarea>

                                    <div class="mt-5">
                                        <label for="reviewFiles" class="mb-2 block text-sm font-bold text-[#13324a]">Review files <span class="text-red-500">*</span></label>
                                        <input id="reviewFiles" type="file" multiple required
                                            accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.webm,.mov,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                            class="block w-full cursor-pointer rounded-xl border border-slate-200 bg-white text-sm text-slate-500 file:mr-4 file:border-0 file:bg-[#13324a] file:px-4 file:py-3 file:text-sm file:font-bold file:text-white hover:file:bg-[#1d5f8c]">
                                        <p class="mt-2 text-xs leading-5 text-slate-500">Select 1–20 images, videos, PDFs, Word, Excel, or PowerPoint files. Maximum 250 MB per file.</p>
                                    </div>
                                    <div id="reviewSelectedFiles" class="mt-4 space-y-2" aria-live="polite">
                                        <div class="rounded-xl border border-dashed border-slate-200 bg-white p-4 text-sm text-slate-500">No review files selected yet.</div>
                                    </div>
                                    <div class="mt-5 flex justify-end">
                                        <button id="sendReviewPackageButton" type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#13324a] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#1d5f8c] disabled:cursor-not-allowed disabled:opacity-60">
                                            <i class="fa-solid fa-paper-plane mr-2"></i> Send review to clinic
                                        </button>
                                    </div>
                                </form>
                                <?php elseif (!$reviewPackages): ?>
                                <div class="mb-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">No review package was sent before this request left the review stage.</div>
                                <?php endif; ?>

                                <div class="space-y-4">
                                    <?php if (!$reviewPackages): ?>
                                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500">No review rounds have been sent yet.</div>
                                    <?php endif; ?>
                                    <?php foreach ($reviewPackages as $reviewIndex => $package): ?>
                                        <article class="rounded-2xl border <?= $reviewIndex === 0 ? 'border-cyan-200 bg-cyan-50/30' : 'border-slate-200 bg-white' ?> p-5">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h3 class="font-bold text-[#13324a]">Review round #<?= (int) $package['id'] ?></h3>
                                                        <?php if ($reviewIndex === 0): ?><span class="rounded-full bg-cyan-100 px-2.5 py-1 text-[11px] font-bold text-cyan-800">Latest</span><?php endif; ?>
                                                        <?php if (!empty($package['approved_at'])): ?><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Approved</span><?php endif; ?>
                                                    </div>
                                                    <p class="mt-1 text-xs text-slate-500">Sent <?= date('M d, Y, H:i', strtotime($package['sent_at'])) ?> by <?= htmlspecialchars($package['admin_name'] ?: 'Admin') ?></p>
                                                </div>
                                                <?php if (!empty($package['approved_at'])): ?><p class="text-xs font-semibold text-emerald-700">Approved <?= date('M d, Y, H:i', strtotime($package['approved_at'])) ?></p><?php endif; ?>
                                            </div>
                                            <?php if (!empty($package['summary'])): ?><div class="mt-4 whitespace-pre-wrap rounded-xl border border-slate-100 bg-white p-4 text-sm leading-6 text-slate-700"><?= htmlspecialchars($package['summary']) ?></div><?php endif; ?>
                                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                                <?php foreach ($package['files'] as $file): ?>
                                                    <?php $reviewFileName = uploadedFileDisplayName($file['original_name'], $file['file_path']); ?>
                                                    <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-[#1d5f8c]">
                                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700"><i class="fa-solid fa-paperclip"></i></span>
                                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($reviewFileName) ?>"><?= htmlspecialchars($reviewFileName) ?></span><span class="block text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'], $reviewFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'])) ?></span></span>
                                                        <i class="fa-solid fa-arrow-up-right-from-square shrink-0 text-xs text-[#1d5f8c]"></i>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <?php
                            $deliverableMeta = [
                                'video' => ['title' => 'Plan Videos', 'icon' => 'fa-solid fa-video', 'tone' => 'bg-purple-50 text-purple-600'],
                                'instruction' => ['title' => 'Instructions & Sheets', 'icon' => 'fa-regular fa-file-lines', 'tone' => 'bg-amber-50 text-amber-600'],
                                'guide' => ['title' => 'Final Guide Files (STL)', 'icon' => 'fa-solid fa-cube', 'tone' => 'bg-blue-50 text-[#1d5f8c]'],
                                'optional' => ['title' => 'Optional Files', 'icon' => 'fa-solid fa-paperclip', 'tone' => 'bg-slate-100 text-slate-500'],
                            ];
                            ?>
                            <?php if (!empty($deliverables)): ?>
                            <div class="mt-8 border-t border-slate-200 pt-8">
                                <div class="mb-6 flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fa-solid fa-box-open"></i></div>
                                    <div>
                                        <h2 class="text-lg font-bold text-[#13324a]">Saved Delivery Package</h2>
                                        <p class="text-sm text-slate-500">Files already sent to the clinic, grouped by purpose.</p>
                                    </div>
                                </div>
                                <div class="space-y-6">
                                    <?php foreach ($deliverableMeta as $type => $meta): ?>
                                        <?php if (!empty($deliverables[$type])): ?>
                                        <section>
                                            <div class="mb-3 flex items-center justify-between gap-3">
                                                <h3 class="text-sm font-bold text-[#13324a]"><?= htmlspecialchars($meta['title']) ?></h3>
                                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500"><?= count($deliverables[$type]) ?> file<?= count($deliverables[$type]) === 1 ? '' : 's' ?></span>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <?php foreach ($deliverables[$type] as $file): ?>
                                                    <?php $deliveryFileName = uploadedFileDisplayName($file['original_name'] ?? null, $file['file_path']); ?>
                                                    <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-[#1d5f8c] hover:shadow-sm">
                                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg <?= $meta['tone'] ?>"><i class="<?= $meta['icon'] ?>"></i></span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="block truncate text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($deliveryFileName) ?>"><?= htmlspecialchars($deliveryFileName) ?></span>
                                                            <span class="mt-0.5 block text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'] ?? null, $deliveryFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'] ?? null)) ?></span>
                                                        </span>
                                                        <span class="shrink-0 text-xs font-bold text-[#1d5f8c]">View <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </section>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php elseif ($request['status'] === 'completed'): ?>
                            <div class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                <p class="font-bold"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Completed request has no saved delivery files.</p>
                                <p class="mt-1 text-xs text-amber-700">Review the case history before contacting the clinic.</p>
                            </div>
                            <?php endif; ?>

                            <!-- Upload Deliverables Form (Only while in production) -->
                            <?php if ($request['status'] === 'in_progress'): ?>
                            <div class="mt-8 pt-8 border-t border-slate-200">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#1d5f8c] text-white shadow-sm">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-bold text-[#13324a]">Upload Delivery Package</h2>
                                        <p class="text-sm text-slate-500">Provide the final deliverables to the clinic to complete this request.</p>
                                    </div>
                                </div>

                                <form id="deliverablesForm" class="space-y-6">
                                    <input type="hidden" name="request_id" value="<?= htmlspecialchars($request['id']) ?>">

                                    <div id="delivErrorMsg" class="hidden bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100"></div>
                                    <div id="delivSuccessMsg" class="hidden bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm font-medium border border-emerald-100"></div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Plan Video -->
                                        <div>
                                            <label class="block text-sm font-semibold text-[#13324a] mb-2">Plan Videos (MP4)</label>
                                            <input type="file" id="admin_plan_video" name="admin_plan_video[]" accept="video/mp4" multiple class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#1d5f8c] hover:file:bg-blue-100 transition cursor-pointer border border-slate-200 rounded-xl bg-slate-50">
                                            <div id="videoProgressContainer" class="hidden mt-3">
                                                <div class="flex justify-between text-xs font-bold text-[#13324a] mb-1"><span id="videoProgressText">0%</span></div>
                                                <div class="w-full bg-slate-200 rounded-full h-2"><div id="videoProgressBar" class="bg-[#1d5f8c] h-2 rounded-full transition-all duration-300" style="width: 0%"></div></div>
                                            </div>
                                        </div>

                                        <!-- Instructions -->
                                        <div>
                                            <label class="block text-sm font-semibold text-[#13324a] mb-2">Instruction Sheets (PDF/Images)</label>
                                            <input type="file" id="admin_instruction_file" name="admin_instruction_file[]" accept="application/pdf,image/*" multiple class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#1d5f8c] hover:file:bg-blue-100 transition cursor-pointer border border-slate-200 rounded-xl bg-slate-50">
                                            <div id="instructionProgressContainer" class="hidden mt-3">
                                                <div class="flex justify-between text-xs font-bold text-[#13324a] mb-1"><span id="instructionProgressText">0%</span></div>
                                                <div class="w-full bg-slate-200 rounded-full h-2"><div id="instructionProgressBar" class="bg-[#1d5f8c] h-2 rounded-full transition-all duration-300" style="width: 0%"></div></div>
                                            </div>
                                        </div>

                                        <!-- Optional Files -->
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-semibold text-[#13324a] mb-2">Optional Files</label>
                                            <input type="file" id="admin_optional_file" name="admin_optional_file[]" multiple class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#1d5f8c] hover:file:bg-blue-100 transition cursor-pointer border border-slate-200 rounded-xl bg-slate-50">
                                            <div id="optionalProgressContainer" class="hidden mt-3">
                                                <div class="flex justify-between text-xs font-bold text-[#13324a] mb-1"><span id="optionalProgressText">0%</span></div>
                                                <div class="w-full bg-slate-200 rounded-full h-2"><div id="optionalProgressBar" class="bg-[#1d5f8c] h-2 rounded-full transition-all duration-300" style="width: 0%"></div></div>
                                            </div>
                                        </div>

                                        <!-- Final Guide (Conditional Display based on delivery_method) -->
                                        <?php if ($details['delivery_method'] === 'clinic_print'): ?>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-semibold text-[#13324a] mb-2">Guide Files (STL) <span class="text-red-500 font-bold">*</span></label>
                                            <p class="text-xs text-slate-500 mb-2 -mt-1">Required for 'Clinic Print' delivery method.</p>
                                            <input type="file" id="admin_guide_file" name="admin_guide_file[]" accept=".stl" multiple class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#1d5f8c] hover:file:bg-blue-100 transition cursor-pointer border border-slate-200 rounded-xl bg-slate-50">
                                            <div id="guideProgressContainer" class="hidden mt-3">
                                                <div class="flex justify-between text-xs font-bold text-[#13324a] mb-1"><span id="guideProgressText">0%</span></div>
                                                <div class="w-full bg-slate-200 rounded-full h-2"><div id="guideProgressBar" class="bg-[#1d5f8c] h-2 rounded-full transition-all duration-300" style="width: 0%"></div></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pt-4 flex justify-end">
                                        <button type="submit" id="submitDeliverablesBtn" class="inline-flex items-center justify-center rounded-xl bg-[#1d5f8c] px-6 py-2.5 text-sm font-bold text-white transition hover:bg-[#13324a] shadow-sm disabled:opacity-70">
                                            <i class="fa-solid fa-paper-plane mr-2"></i> Finalize & Send Package
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php elseif (!in_array($request['status'], ['completed', 'rejected'], true)): ?>
                            <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                <i class="fa-solid fa-lock mr-2 text-slate-400"></i>
                                Delivery uploads become available after payment is approved and the request enters production.
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php require __DIR__ . '/../includes/surgeon_request_details.php'; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($payment): ?>
                        <div class="mt-8 pt-6 border-t border-slate-100">
                            <h3 class="text-lg font-bold text-[#13324a] mb-4">Payment Receipt</h3>
                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                                <div class="flex items-center justify-between gap-4 mb-4">
                                    <div>
                                        <p class="text-sm font-bold text-[#13324a]">Amount: <?= $payment['amount'] ? number_format($payment['amount'], 2) : 'Not specified' ?></p>
                                        <p class="text-xs text-slate-500">Uploaded: <?= date('M d, Y, H:i', strtotime($payment['uploaded_at'])) ?></p>
                                    </div>
                                </div>
                                <?php
                                    $receiptUrl = getPresignedUrl($s3Client, $bucketName, $payment['receipt_file_path']);
                                    $receiptName = uploadedFileDisplayName($payment['receipt_original_name'] ?? null, $payment['receipt_file_path']);
                                ?>
                                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#1d5f8c]"><i class="fa-solid fa-receipt"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($receiptName) ?>"><?= htmlspecialchars($receiptName) ?></span>
                                        <span class="mt-0.5 block text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($payment['receipt_content_type'] ?? null, $receiptName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($payment['receipt_file_size'] ?? null)) ?></span>
                                    </span>
                                    <a href="<?= htmlspecialchars($receiptUrl) ?>" target="_blank" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#1d5f8c] px-3 py-2 text-xs font-bold text-white hover:bg-[#13324a] transition">
                                        <i class="fa-solid fa-eye mr-1.5"></i> View receipt
                                    </a>
                                </div>
                                <?php if ($payment['status'] === 'pending_verification' && $request['service_type'] !== 'surgical_guide'): ?>
                                    <div class="flex gap-3 mt-4 border-t border-slate-200 pt-4">
                                        <button onclick="verifyPayment(<?= $payment['id'] ?>, 'approved')" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 rounded-lg text-sm transition">Approve</button>
                                        <button onclick="verifyPayment(<?= $payment['id'] ?>, 'rejected')" class="bg-red-50 hover:bg-red-500 text-red-600 hover:text-white border border-red-200 font-bold py-2 px-4 rounded-lg text-sm transition">Reject</button>
                                    </div>
                                <?php elseif ($payment['status'] === 'pending_verification' && $request['service_type'] === 'surgical_guide'): ?>
                                    <div class="mt-4 border-t border-slate-200 pt-4 text-sm text-red-600">
                                        Historical receipt only. Manual receipt review is disabled for Surgical Guide requests.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($request['service_type'] === 'surgical_guide'): ?>
                    <section class="mt-8 border-t border-slate-100 pt-6" aria-labelledby="requestChatTitle">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 id="requestChatTitle" class="text-lg font-bold text-[#13324a]"><i class="fa-regular fa-comments mr-2 text-cyan-700"></i>Request conversation</h3>
                                <p class="mt-1 text-xs text-slate-500">Messages do not update automatically. Select “Refresh messages” to see new replies.</p>
                            </div>
                            <button type="button" id="refreshMessagesButton" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 transition hover:border-[#1d5f8c] hover:text-[#1d5f8c]">
                                <i class="fa-solid fa-rotate mr-2"></i> Refresh messages
                            </button>
                        </div>
                        <div id="requestChatStatus" class="mb-3 hidden rounded-xl p-3 text-sm font-semibold" role="status"></div>
                        <div id="requestMessages" class="max-h-[32rem] space-y-3 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-4" aria-live="polite">
                            <?php if (!$requestMessages): ?>
                                <div id="requestMessagesEmpty" class="py-8 text-center text-sm text-slate-500"><i class="fa-regular fa-comment-dots mb-3 block text-2xl text-slate-300"></i>No messages yet. Start the conversation about this case.</div>
                            <?php endif; ?>
                            <?php foreach ($requestMessages as $message): ?>
                                <article data-message-id="<?= (int) $message['id'] ?>" class="flex <?= $message['sender_role'] === 'admin' ? 'justify-end' : 'justify-start' ?>">
                                    <div class="max-w-[88%] rounded-2xl border px-4 py-3 <?= $message['sender_role'] === 'admin' ? 'border-[#1d5f8c] bg-[#13324a] text-white' : 'border-slate-200 bg-white text-slate-700' ?>">
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs <?= $message['sender_role'] === 'admin' ? 'text-blue-100' : 'text-slate-400' ?>"><span class="font-bold"><?= htmlspecialchars($message['sender_name']) ?> · <?= $message['sender_role'] === 'admin' ? 'Admin' : 'Clinic' ?></span><time><?= date('M d, Y, H:i', strtotime($message['created_at'])) ?></time></div>
                                        <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6"><?= htmlspecialchars($message['message_text']) ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php if (surgicalGuideChatIsWritable($request['status'])): ?>
                        <form id="requestMessageForm" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1"><label for="requestMessageText" class="mb-2 block text-sm font-bold text-[#13324a]">Write a message</label><textarea id="requestMessageText" maxlength="<?= REQUEST_MESSAGE_MAX_LENGTH ?>" rows="3" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-[#1d5f8c] focus:ring-[#1d5f8c]" placeholder="Write a clear note about the case or review package."></textarea></div>
                            <button id="sendMessageButton" type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#1d5f8c] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#13324a] disabled:opacity-60"><i class="fa-solid fa-paper-plane mr-2"></i>Send message</button>
                        </form>
                        <?php else: ?>
                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600"><i class="fa-solid fa-lock mr-2 text-slate-400"></i>This conversation is read-only because the request is completed or rejected.</div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <div class="mt-8 pt-6 border-t border-slate-100">
                        <h3 class="text-lg font-bold text-[#13324a] mb-4">Request Activity</h3>
                        <div class="space-y-3">
                            <?php if (empty($activity_logs)): ?>
                                <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 text-sm text-slate-500">No activity logged yet.</div>
                            <?php endif; ?>
                            <?php foreach ($activity_logs as $log): ?>
                                <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 text-sm">
                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1">
                                        <p class="font-bold text-[#13324a]"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))) ?></p>
                                        <p class="text-xs text-slate-400"><?= date('M d, Y, H:i', strtotime($log['created_at'])) ?></p>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">By <?= htmlspecialchars($log['actor_name'] ?: $log['actor_role']) ?><?php if ($log['old_value'] || $log['new_value']): ?> — <?= htmlspecialchars((string) $log['old_value']) ?> → <?= htmlspecialchars((string) $log['new_value']) ?><?php endif; ?></p>
                                    <?php if (!empty($log['note'])): ?><p class="mt-2 text-slate-700"><?= nl2br(htmlspecialchars($log['note'])) ?></p><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const requestWorkflowCsrfToken = <?= json_encode($request_workflow_csrf_token) ?>;

        const reviewFileInput = document.getElementById('reviewFiles');
        const reviewFileList = document.getElementById('reviewSelectedFiles');
        const reviewForm = document.getElementById('reviewPackageForm');

        function compactFileSize(bytes) {
            if (!Number.isFinite(bytes) || bytes < 1) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let value = bytes;
            let index = 0;
            while (value >= 1024 && index < units.length - 1) { value /= 1024; index++; }
            return `${value.toFixed(index === 0 ? 0 : 2)} ${units[index]}`;
        }

        function renderSelectedReviewFiles() {
            if (!reviewFileInput || !reviewFileList) return;
            reviewFileList.replaceChildren();
            const files = Array.from(reviewFileInput.files);
            if (!files.length) {
                const empty = document.createElement('div');
                empty.className = 'rounded-xl border border-dashed border-slate-200 bg-white p-4 text-sm text-slate-500';
                empty.textContent = 'No review files selected yet.';
                reviewFileList.appendChild(empty);
                return;
            }
            files.forEach((file, index) => {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3';
                row.dataset.reviewFileIndex = String(index);
                const icon = document.createElement('span');
                icon.className = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700';
                icon.innerHTML = '<i class="fa-solid fa-file"></i>';
                const details = document.createElement('span');
                details.className = 'min-w-0 flex-1';
                const name = document.createElement('span');
                name.className = 'block truncate text-sm font-semibold text-slate-700';
                name.textContent = file.name;
                const meta = document.createElement('span');
                meta.className = 'block text-xs text-slate-400';
                meta.textContent = `${file.type || 'Unknown type'} · ${compactFileSize(file.size)}`;
                details.append(name, meta);
                const state = document.createElement('span');
                state.className = 'review-upload-state shrink-0 text-xs font-bold text-slate-400';
                state.textContent = 'Ready';
                row.append(icon, details, state);
                reviewFileList.appendChild(row);
            });
        }

        async function uploadReviewFile(file, row) {
            const state = row.querySelector('.review-upload-state');
            state.textContent = 'Preparing…';
            state.className = 'review-upload-state shrink-0 text-xs font-bold text-cyan-700';
            const response = await fetch('../api/generate_review_upload_url.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    request_id: <?= (int) $request['id'] ?>,
                    csrf_token: requestWorkflowCsrfToken,
                    filename: file.name,
                    content_type: file.type || 'application/octet-stream',
                    file_size: file.size
                })
            });
            const data = await response.json();
            if (!response.ok || !data.presigned_url || !data.object_key) throw new Error(data.error || `Could not prepare ${file.name}.`);

            state.textContent = 'Uploading 0%';
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', data.presigned_url, true);
                xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
                xhr.upload.onprogress = event => {
                    if (event.lengthComputable) state.textContent = `Uploading ${Math.round((event.loaded / event.total) * 100)}%`;
                };
                xhr.onload = () => xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`Upload failed for ${file.name}.`));
                xhr.onerror = () => reject(new Error(`Network error while uploading ${file.name}.`));
                xhr.send(file);
            });
            state.textContent = 'Uploaded';
            state.className = 'review-upload-state shrink-0 text-xs font-bold text-emerald-600';
            return data.object_key;
        }

        if (reviewFileInput) {
            reviewFileInput.addEventListener('change', renderSelectedReviewFiles);
        }
        if (reviewForm) {
            reviewForm.addEventListener('submit', async event => {
                event.preventDefault();
                const files = Array.from(reviewFileInput.files);
                const button = document.getElementById('sendReviewPackageButton');
                const errorBox = document.getElementById('reviewPackageError');
                const successBox = document.getElementById('reviewPackageSuccess');
                errorBox.classList.add('hidden');
                successBox.classList.add('hidden');
                if (!files.length || files.length > 20) {
                    errorBox.textContent = 'Select between 1 and 20 review files.';
                    errorBox.classList.remove('hidden');
                    return;
                }
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i>Uploading review…';
                try {
                    const rows = Array.from(reviewFileList.querySelectorAll('[data-review-file-index]'));
                    const uploadedKeys = await Promise.all(files.map((file, index) => uploadReviewFile(file, rows[index])));
                    button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i>Saving review round…';
                    const response = await fetch('../api/admin_send_review.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            request_id: <?= (int) $request['id'] ?>,
                            csrf_token: requestWorkflowCsrfToken,
                            summary: document.getElementById('reviewSummary').value.trim(),
                            files: uploadedKeys
                        })
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || 'The review package could not be saved.');
                    successBox.textContent = data.message;
                    successBox.classList.remove('hidden');
                    window.location.reload();
                } catch (error) {
                    errorBox.textContent = error.message;
                    errorBox.classList.remove('hidden');
                    reviewFileList.querySelectorAll('.review-upload-state').forEach(state => {
                        if (state.textContent !== 'Uploaded') {
                            state.textContent = 'Failed';
                            state.className = 'review-upload-state shrink-0 text-xs font-bold text-red-600';
                        }
                    });
                } finally {
                    button.disabled = false;
                    button.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i>Send review to clinic';
                }
            });
        }

        const messagesContainer = document.getElementById('requestMessages');
        const chatStatus = document.getElementById('requestChatStatus');

        function latestMessageId() {
            const items = messagesContainer ? messagesContainer.querySelectorAll('[data-message-id]') : [];
            return items.length ? Number(items[items.length - 1].dataset.messageId) : 0;
        }

        function setChatStatus(message, isError = false) {
            if (!chatStatus) return;
            chatStatus.textContent = message;
            chatStatus.className = `mb-3 rounded-xl border p-3 text-sm font-semibold ${isError ? 'border-red-100 bg-red-50 text-red-700' : 'border-cyan-100 bg-cyan-50 text-cyan-800'}`;
        }

        function appendChatMessage(message) {
            if (!messagesContainer || messagesContainer.querySelector(`[data-message-id="${message.id}"]`)) return;
            document.getElementById('requestMessagesEmpty')?.remove();
            const article = document.createElement('article');
            article.dataset.messageId = String(message.id);
            article.className = `flex ${message.sender_role === 'admin' ? 'justify-end' : 'justify-start'}`;
            const bubble = document.createElement('div');
            bubble.className = `max-w-[88%] rounded-2xl border px-4 py-3 ${message.sender_role === 'admin' ? 'border-[#1d5f8c] bg-[#13324a] text-white' : 'border-slate-200 bg-white text-slate-700'}`;
            const meta = document.createElement('div');
            meta.className = `flex flex-wrap items-center gap-x-3 gap-y-1 text-xs ${message.sender_role === 'admin' ? 'text-blue-100' : 'text-slate-400'}`;
            const sender = document.createElement('span');
            sender.className = 'font-bold';
            sender.textContent = `${message.sender_name} · ${message.sender_role === 'admin' ? 'Admin' : 'Clinic'}`;
            const time = document.createElement('time');
            time.textContent = message.created_label;
            const body = document.createElement('p');
            body.className = 'mt-2 whitespace-pre-wrap break-words text-sm leading-6';
            body.textContent = message.message_text;
            meta.append(sender, time);
            bubble.append(meta, body);
            article.appendChild(bubble);
            messagesContainer.appendChild(article);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        document.getElementById('refreshMessagesButton')?.addEventListener('click', async event => {
            const button = event.currentTarget;
            button.disabled = true;
            try {
                const response = await fetch(`../api/request_messages.php?request_id=<?= (int) $request['id'] ?>&after_id=${latestMessageId()}&csrf_token=${encodeURIComponent(requestWorkflowCsrfToken)}`, {headers: {'Accept': 'application/json'}});
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Messages could not be loaded.');
                data.messages.forEach(appendChatMessage);
                setChatStatus(data.messages.length ? `${data.messages.length} new message(s) loaded.` : 'No new messages.');
            } catch (error) {
                setChatStatus(error.message || 'Messages could not be loaded.', true);
            } finally {
                button.disabled = false;
            }
        });

        document.getElementById('requestMessageForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const input = document.getElementById('requestMessageText');
            const button = document.getElementById('sendMessageButton');
            const messageText = input.value.trim();
            if (!messageText) { setChatStatus('Write a message before sending.', true); return; }
            button.disabled = true;
            try {
                const response = await fetch('../api/send_request_message.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({request_id: <?= (int) $request['id'] ?>, csrf_token: requestWorkflowCsrfToken, message_text: messageText})
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'The message could not be sent.');
                appendChatMessage(data.message);
                input.value = '';
                setChatStatus('Message sent.');
            } catch (error) {
                setChatStatus(error.message || 'The message could not be sent.', true);
            } finally {
                button.disabled = false;
            }
        });

        function toggleRejectionReason() {
            const statusSelect = document.getElementById('statusSelect');
            const reasonBox = document.getElementById('rejectionReason');
            if (!statusSelect || !reasonBox) return;
            const status = statusSelect.value;
            reasonBox.classList.toggle('hidden', status !== 'rejected');
            reasonBox.required = status === 'rejected';
        }
        const statusSelect = document.getElementById('statusSelect');
        if (statusSelect) {
            statusSelect.addEventListener('change', toggleRejectionReason);
            toggleRejectionReason();
        }

        async function updateStatus(requestId) {
            const newStatus = document.getElementById('statusSelect').value;
            const reason = document.getElementById('rejectionReason').value.trim();
            if (newStatus === 'rejected' && !reason) {
                alert('Rejection reason is required.');
                return;
            }
            try {
                const body = new URLSearchParams({request_id: requestId, status: newStatus, reason});
                const response = await fetch('../api/update_request_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body.toString()
                });
                const data = await response.json();
                if(response.ok) { alert(data.success || 'Updated successfully'); location.reload(); }
                else { alert(data.error || 'Could not update status.'); }
            } catch(e) { alert('Network error occurred.'); }
        }

        async function updateGuideStatus(requestId, newStatus) {
            const reasonBox = document.getElementById('guideRejectionReason');
            const reason = newStatus === 'rejected' && reasonBox ? reasonBox.value.trim() : '';
            const confirmation = newStatus === 'pending_payment'
                ? 'Approve this request and ask the clinic for payment?'
                : 'Reject this request? The rejection reason is optional.';

            if (!confirm(confirmation)) return;

            try {
                const body = new URLSearchParams({
                    request_id: requestId,
                    status: newStatus,
                    reason,
                    csrf_token: requestWorkflowCsrfToken
                });
                const response = await fetch('../api/update_request_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body.toString()
                });
                const data = await response.json();
                if (response.ok) {
                    alert(data.success || 'Request step updated successfully.');
                    location.reload();
                } else {
                    alert(data.error || 'Could not update the request step.');
                }
            } catch (e) {
                alert('Network error occurred.');
            }
        }

        async function verifyPayment(paymentId, action) {
            if(!confirm(`Are you sure you want to ${action} this payment receipt?`)) return;
            try {
                const body = new URLSearchParams({
                    payment_id: paymentId,
                    action,
                    csrf_token: requestWorkflowCsrfToken
                });
                const response = await fetch('../api/verify_receipt.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body.toString()
                });
                const data = await response.json();
                if(response.ok) {
                    alert(data.success || 'Verified successfully');
                    location.reload();
                } else {
                    alert(data.error || 'Could not verify this receipt.');
                }
            } catch(e) { alert('Network error occurred.'); }
        }

        // State to hold the final uploaded object keys for each category
        let uploadedDeliverables = {
            video: [],
            instruction: [],
            guide: [],
            optional: []
        };

        /**
         * Uploads multiple files for a specific category and tracks overall progress
         */
        async function uploadAdminFilesToCloud(fileInputId, category, progressContainerId, progressBarId, progressTextId) {
            const fileInput = document.getElementById(fileInputId);
            if (!fileInput) return;
            const files = fileInput.files;
            if (files.length === 0) return;

            const progressContainer = document.getElementById(progressContainerId);
            const progressBar = document.getElementById(progressBarId);
            const progressText = document.getElementById(progressTextId);
            
            progressContainer.classList.remove('hidden');

            let totalSize = 0;
            let loadedSizes = new Array(files.length).fill(0);

            // Calculate total size for the progress bar
            for (let i = 0; i < files.length; i++) {
                totalSize += files[i].size;
            }

            // Map each file to an upload Promise
            const uploadPromises = Array.from(files).map((file, index) => {
                return new Promise(async (resolve, reject) => {
                    try {
                        // 1. Fetch Presigned URL
                        const response = await fetch('../api/generate_presigned_url.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                filename: file.name,
                                contentType: file.type || 'application/octet-stream',
                                fileSize: file.size
                            })
                        });
                        const data = await response.json();
                        
                        if (!response.ok || !data.presigned_url || data.error) {
                            throw new Error(data.error || `Failed to get secure upload URL for ${file.name}.`);
                        }

                        // 2. Upload file via XMLHttpRequest
                        const xhr = new XMLHttpRequest();
                        xhr.open('PUT', data.presigned_url, true);
                        xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');

                        xhr.upload.onprogress = function(e) {
                            if (e.lengthComputable) {
                                loadedSizes[index] = e.loaded;
                                const totalLoaded = loadedSizes.reduce((a, b) => a + b, 0);
                                const percentComplete = Math.round((totalLoaded / totalSize) * 100);
                                
                                if (progressBar) progressBar.style.width = percentComplete + '%';
                                if (progressText) progressText.textContent = percentComplete + '%';
                            }
                        };

                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                uploadedDeliverables[category].push(data.object_key);
                                resolve();
                            } else {
                                reject(new Error(`Upload failed for ${file.name} (Status: ${xhr.status})`));
                            }
                        };

                        xhr.onerror = () => reject(new Error(`Network error during upload for ${file.name}`));
                        xhr.send(file);
                    } catch (error) {
                        reject(error);
                    }
                });
            });

            // Wait for all files in this category to finish uploading
            await Promise.all(uploadPromises);
        }

        /**
         * Main Save Function Triggered by the "Save Deliverables" button
         */
        async function submitDeliverables(event, requestId, deliveryMethod) {
            event.preventDefault();
            const btn = document.getElementById('submitDeliverablesBtn');
            const errorMsg = document.getElementById('delivErrorMsg');
            const successMsg = document.getElementById('delivSuccessMsg');

            errorMsg.classList.add('hidden');
            successMsg.classList.add('hidden');

            const videoInput = document.getElementById('admin_plan_video');
            const instructionInput = document.getElementById('admin_instruction_file');
            const optionalInput = document.getElementById('admin_optional_file');
            const guideInput = document.getElementById('admin_guide_file');

            const hasVideo = videoInput && videoInput.files.length > 0;
            const hasInstruction = instructionInput && instructionInput.files.length > 0;
            const hasOptional = optionalInput && optionalInput.files.length > 0;
            const hasGuide = guideInput && guideInput.files.length > 0;

            if (!hasVideo && !hasInstruction && !hasOptional && !hasGuide) {
                errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> Please select at least one file to upload.';
                errorMsg.classList.remove('hidden');
                return;
            }

            if (deliveryMethod === 'clinic_print' && !hasGuide) {
                errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> Guide files (STL) are required when delivery method is \'Clinic Print\'.';
                errorMsg.classList.remove('hidden');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Processing Uploads...';

            try {
                // Define upload tasks
                const uploadTasks = [
                    uploadAdminFilesToCloud('admin_plan_video', 'video', 'videoProgressContainer', 'videoProgressBar', 'videoProgressText'),
                    uploadAdminFilesToCloud('admin_instruction_file', 'instruction', 'instructionProgressContainer', 'instructionProgressBar', 'instructionProgressText'),
                    uploadAdminFilesToCloud('admin_optional_file', 'optional', 'optionalProgressContainer', 'optionalProgressBar', 'optionalProgressText')
                ];

                if (deliveryMethod === 'clinic_print') {
                    uploadTasks.push(uploadAdminFilesToCloud('admin_guide_file', 'guide', 'guideProgressContainer', 'guideProgressBar', 'guideProgressText'));
                }

                // Upload all categories concurrently
                await Promise.all(uploadTasks);

                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Saving to Database...';

                // Send keys to backend
                const response = await fetch('../api/admin_save_deliverables.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: requestId,
                        files: uploadedDeliverables,
                        csrf_token: requestWorkflowCsrfToken
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    successMsg.innerHTML = '<i class="fa-solid fa-check-circle mr-1"></i> ' + result.message;
                    successMsg.classList.remove('hidden');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    throw new Error(result.message || 'An unknown error occurred while saving deliverables.');
                }

            } catch (error) {
                errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> ' + error.message;
                errorMsg.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i> Finalize & Send Package';
            }
        }

        const delivForm = document.getElementById('deliverablesForm');
        if (delivForm) {
            delivForm.addEventListener('submit', (e) => submitDeliverables(
                e,
                <?= (int) $request['id'] ?>,
                <?= json_encode($details['delivery_method'] ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
            ));
        }
    </script>
</body>
</html>
