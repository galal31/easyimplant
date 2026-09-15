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

$isSurgicalGuide = $request['service_type'] === 'surgical_guide';
$isChatWritable  = $isSurgicalGuide && surgicalGuideChatIsWritable($request['status']);
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
    <style>
        body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }

        /* ── Chat panel sticky ── */
        #chatColumnWrapper {
            position: sticky;
            top: 64px; /* nav height */
            height: calc(100dvh - 64px - 1.5rem);
            display: flex;
            flex-direction: column;
        }
        #requestChatPanel {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
        }
        #requestMessages {
            flex: 1 1 auto;
            overflow-y: auto;
            min-height: 0;
        }

        /* ── Page layout ── */
        .workspace-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
        }
        @media (min-width: 1024px) {
            .workspace-grid {
                grid-template-columns: minmax(0, 1fr) 380px;
            }
        }
        .chat-column { display: none; }
        @media (min-width: 1024px) { .chat-column { display: block; } }

        /* ── Mobile FAB ── */
        #chatFabButton {
            position: fixed;
            bottom: 1.5rem;
            right: 1.25rem;
            z-index: 40;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1d5f8c 0%, #0891b2 100%);
            color: #fff;
            font-size: 1.5rem;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(29,95,140,0.45), 0 0 0 0 rgba(8,145,178,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        #chatFabButton:hover, #chatFabButton:focus-visible {
            transform: scale(1.08);
            outline: 2px solid #0891b2;
            outline-offset: 3px;
        }
        @media (min-width: 1024px) { #chatFabButton { display: none; } }
        @keyframes fabPulse {
            0%   { box-shadow: 0 4px 20px rgba(29,95,140,0.45), 0 0 0 0 rgba(8,145,178,0.4); }
            60%  { box-shadow: 0 4px 20px rgba(29,95,140,0.45), 0 0 0 14px rgba(8,145,178,0); }
            100% { box-shadow: 0 4px 20px rgba(29,95,140,0.45), 0 0 0 0 rgba(8,145,178,0); }
        }
        #chatFabButton.pulsing { animation: fabPulse 2s ease-out infinite; }
        @media (prefers-reduced-motion: reduce) { #chatFabButton.pulsing { animation: none; } }

        /* ── Attention bubble ── */
        #chatAttentionBubble {
            position: fixed;
            bottom: 5.5rem;
            right: 1.25rem;
            z-index: 41;
            background: #13324a;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.45rem 0.85rem;
            border-radius: 1rem 1rem 0.25rem 1rem;
            white-space: nowrap;
            box-shadow: 0 4px 14px rgba(19,50,74,0.25);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.35s ease, transform 0.35s ease;
            transform: translateY(8px);
        }
        #chatAttentionBubble.visible { opacity: 1; pointer-events: auto; transform: translateY(0); }
        @media (min-width: 1024px) { #chatAttentionBubble { display: none; } }

        /* ── Drawer ── */
        #mobileChatBackdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 48;
            background: rgba(19,50,74,0.45);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        #mobileChatBackdrop.active { display: block; }
        #mobileChatBackdrop.visible { opacity: 1; }
        #mobileChatDrawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 49;
            max-height: 90dvh;
            background: #fff;
            border-radius: 1.25rem 1.25rem 0 0;
            box-shadow: 0 -8px 40px rgba(19,50,74,0.2);
            display: flex;
            flex-direction: column;
            transform: translateY(100%);
            transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
        }
        #mobileChatDrawer.open { transform: translateY(0); }
        @media (prefers-reduced-motion: reduce) {
            #mobileChatDrawer, #mobileChatBackdrop { transition: none; }
        }
        @media (min-width: 1024px) {
            #mobileChatDrawer, #mobileChatBackdrop { display: none !important; }
        }

        /* ── Section cards ── */
        .case-card {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 4px rgba(19,50,74,0.05);
            overflow: hidden;
        }
        .case-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
        }
        .case-card-body { padding: 1.25rem; }
        .info-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.25rem; }
        .info-value { font-size: 0.875rem; font-weight: 600; color: #1e293b; }
        .filename-cell { min-width: 0; overflow: hidden; }
        .filename-cell span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        video, img { max-width: 100%; }
    </style>
</head>
<body class="bg-[#f4f8fb] text-slate-800 antialiased">

    <!-- ══ Admin Navigation ══ -->
    <nav class="bg-[#13324a] text-white border-b border-[#0f2233] sticky top-0 z-30 shadow-md" style="height:64px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full">
            <div class="flex items-center gap-3 h-full">
                <a href="admin_dashboard.php" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-white" aria-label="Back to dashboard">
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </a>
                <span class="font-bold text-white text-lg">Request Details</span>
                <div class="ml-auto"><?= getStatusBadge($request['status']) ?></div>
            </div>
        </div>
    </nav>

    <!-- ══ Page Container ══ -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- ── Request header ── -->
        <div class="flex flex-wrap items-start gap-4 mb-4">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-extrabold text-[#13324a]">Request #<?= str_pad($request['id'], 5, '0', STR_PAD_LEFT) ?></h1>
                <p class="text-sm text-slate-500 mt-0.5">Submitted <?= date('F j, Y, g:i a', strtotime($request['created_at'])) ?> · <?= $isSurgicalGuide ? 'Surgical Guide' : 'Surgeon Request' ?></p>
            </div>
        </div>

        <?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
            <div class="mb-4 rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-extrabold mb-1"><i class="fa-solid fa-circle-exclamation mr-2"></i>Rejection Reason</p>
                <p><?= nl2br(htmlspecialchars($request['rejection_reason'])) ?></p>
            </div>
        <?php endif; ?>

        <!-- ── Summary Cards: Clinic Info + Workflow Status ── -->
        <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Clinic name + doctor -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="info-label">Clinic</p>
                <p class="info-value truncate" title="<?= htmlspecialchars($request['clinic_name']) ?>"><?= htmlspecialchars($request['clinic_name']) ?></p>
                <p class="text-xs text-slate-500 mt-1 truncate">Dr. <?= htmlspecialchars($request['doctor_name']) ?></p>
            </div>
            <!-- Country + Contact -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="info-label">Contact</p>
                <a href="tel:<?= htmlspecialchars($request['phone']) ?>" class="text-sm font-semibold text-[#1d5f8c] hover:underline flex items-center gap-1 truncate"><i class="fa-solid fa-phone w-4 shrink-0"></i><?= htmlspecialchars($request['phone']) ?></a>
                <a href="mailto:<?= htmlspecialchars($request['email']) ?>" class="text-xs text-[#1d5f8c] hover:underline flex items-center gap-1 mt-1 truncate"><i class="fa-solid fa-envelope w-4 shrink-0"></i><?= htmlspecialchars($request['email']) ?></a>
            </div>
            <!-- Country -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="info-label">Location</p>
                <p class="info-value uppercase"><?= htmlspecialchars($request['country']) ?></p>
                <a href="admin_clinic_account.php?id=<?= (int) $request['user_id'] ?>" class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-[#1d5f8c] hover:underline"><i class="fa-solid fa-wallet"></i> View Account</a>
            </div>
            <!-- Workflow status note -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="info-label">Stage</p>
                <?php if ($isSurgicalGuide): ?>
                    <?php if ($request['status'] === 'pending_review'): ?>
                        <p class="text-xs text-slate-600 leading-relaxed">Review the case, then send at least one file below.</p>
                    <?php elseif ($request['status'] === 'awaiting_clinic_approval'): ?>
                        <p class="text-xs text-cyan-700 leading-relaxed">Clinic is reviewing the latest package.</p>
                    <?php elseif ($request['status'] === 'pending_payment'): ?>
                        <p class="text-xs text-orange-600 leading-relaxed">Clinic approved. Payment gateway not yet connected.</p>
                    <?php elseif ($request['status'] === 'in_progress'): ?>
                        <p class="text-xs text-blue-700 leading-relaxed">Upload the final delivery package below.</p>
                    <?php elseif ($request['status'] === 'completed'): ?>
                        <p class="text-xs font-semibold text-emerald-700">Request complete and locked.</p>
                    <?php elseif ($request['status'] === 'rejected'): ?>
                        <p class="text-xs font-semibold text-red-700">Request rejected and locked.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-xs text-slate-600">Surgeon request — use status selector below.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isSurgicalGuide): ?>
        <!-- ── Progress bar ── -->
        <?php
            $guide_steps = ['pending_review', 'awaiting_clinic_approval', 'pending_payment', 'in_progress', 'completed'];
            $current_step = array_search($request['status'], $guide_steps, true);
            $current_step = $current_step === false ? -1 : $current_step;
        ?>
        <section class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Request workflow">
            <div class="grid grid-cols-5 gap-1 text-center">
                <?php foreach (['Admin review', 'Clinic approval', 'Payment', 'Production', 'Complete'] as $index => $step_label): ?>
                    <div>
                        <div class="h-2 rounded-full <?= $request['status'] !== 'rejected' && $index <= $current_step ? 'bg-[#1d5f8c]' : 'bg-slate-200' ?>"></div>
                        <span class="mt-1.5 block text-[10px] font-bold <?= $request['status'] !== 'rejected' && $index <= $current_step ? 'text-[#1d5f8c]' : 'text-slate-400' ?>"><?= $step_label ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ══ Two-column workspace grid ══ -->
        <div class="workspace-grid">

            <!-- ── Main content column ── -->
            <div class="space-y-5">

                <?php if ($details): ?>
                    <?php if (!$isSurgicalGuide): ?>
                        <!-- Surgeon request details (unchanged) -->
                        <div class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-500"><i class="fa-solid fa-user-doctor"></i></div>
                                <h2 class="text-lg font-bold text-[#13324a]">Surgeon Request</h2>
                            </div>
                            <div class="case-card-body">
                                <?php require __DIR__ . '/../includes/surgeon_request_details.php'; ?>
                            </div>
                        </div>
                        <!-- Status select for surgeon requests -->
                        <div class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><i class="fa-solid fa-pen-to-square"></i></div>
                                <h2 class="text-base font-bold text-[#13324a]">Update Status</h2>
                            </div>
                            <div class="case-card-body">
                                <select id="statusSelect" class="w-full text-sm border border-slate-300 rounded-xl px-3 py-2.5 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-white mb-3">
                                    <option value="pending_review" <?= $request['status'] === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                                    <option value="rejected" <?= $request['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    <option value="pending_payment" <?= $request['status'] === 'pending_payment' ? 'selected' : '' ?>>Pending Payment</option>
                                    <option value="in_progress" <?= $request['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $request['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                                <textarea id="rejectionReason" rows="3" class="hidden w-full text-sm border border-red-200 rounded-xl px-3 py-2.5 focus:ring-red-400 focus:border-red-400 bg-red-50 mb-3" placeholder="Required reason if rejected"><?= htmlspecialchars($request['rejection_reason'] ?? '') ?></textarea>
                                <button onclick="updateStatus(<?= $request['id'] ?>)" class="w-full bg-[#13324a] hover:bg-[#1d5f8c] text-white font-bold py-2.5 rounded-xl transition text-sm">Save Status</button>
                            </div>
                        </div>

                    <?php else: /* Surgical Guide content */ ?>

                        <!-- ── Price Summary cards ── -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="sm:col-span-2 rounded-2xl bg-[#13324a] text-white p-5">
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

                        <!-- ── Implant breakdown ── -->
                        <div class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-[#1d5f8c]"><i class="fa-solid fa-tooth"></i></div>
                                <div class="flex-1">
                                    <h2 class="text-sm font-bold text-[#13324a]">Implant Locations & Price Breakdown</h2>
                                </div>
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
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-4 bg-slate-50 border-t border-slate-100 text-sm">
                                <div><span class="text-slate-500">Print fee:</span> <span class="font-bold"><?= formatMoney($details['print_fee'] ?? 0) ?></span></div>
                                <div><span class="text-slate-500">Paid implants:</span> <span class="font-bold"><?= (int) ($details['paid_implants'] ?? 0) ?></span></div>
                                <div><span class="text-slate-500">Free rule used:</span> <span class="font-bold">Every <?= (int) ($details['free_implant_every_used'] ?? 0) ?></span></div>
                                <div><span class="text-slate-500">Completed this cycle:</span> <span class="font-bold"><?= $clinic_cycle_completed_implants ?></span></div>
                            </div>
                            <?php if ((float) ($details['guided_kit_rental_price'] ?? 0) > 0): ?>
                                <div class="flex items-center justify-between border-t border-cyan-100 bg-cyan-50 px-4 py-3 text-sm">
                                    <span class="font-semibold text-cyan-800">Guided kit rental</span>
                                    <span class="font-bold text-cyan-900"><?= formatMoney($details['guided_kit_rental_price']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- ── Request Details ── -->
                        <div class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-500"><i class="fa-solid fa-layer-group"></i></div>
                                <h2 class="text-lg font-bold text-[#13324a]">Surgical Guide Details</h2>
                            </div>
                            <div class="case-card-body space-y-5">

                                <!-- Delivery + Implant + Date -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                        <p class="info-label">Delivery Method</p>
                                        <div class="flex items-center gap-2 text-sm font-bold <?= $details['delivery_method'] === 'clinic_print' ? 'text-[#1d5f8c]' : 'text-purple-700' ?>">
                                            <i class="fa-solid <?= $details['delivery_method'] === 'clinic_print' ? 'fa-print' : 'fa-truck' ?> text-base"></i>
                                            <?= $details['delivery_method'] === 'clinic_print' ? 'Clinic will print it' : 'Admin will print & deliver physical guide' ?>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                        <p class="info-label">Implant Type</p>
                                        <p class="info-value"><?= htmlspecialchars($details['implant_type'] ?: 'Not specified') ?></p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                        <p class="info-label">Operation Date</p>
                                        <p class="info-value"><?= date('F j, Y', strtotime($details['operation_date'])) ?></p>
                                    </div>
                                </div>

                                <!-- Guided Kit -->
                                <?php if (!empty($details['guided_kit_source'])): ?>
                                    <div>
                                        <h3 class="info-label mb-2">Guided Kit</h3>
                                        <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4">
                                            <div class="grid gap-3 sm:grid-cols-3">
                                                <div><p class="info-label">Source</p><p class="info-value"><?= ($details['guided_kit_source'] ?? '') === 'rental' ? 'Rental from Easy Implant' : 'Clinic-owned kit' ?></p></div>
                                                <div><p class="info-label">Kit name</p><p class="info-value truncate" title="<?= htmlspecialchars($details['guided_kit_name'] ?? '') ?>"><?= htmlspecialchars($details['guided_kit_name'] ?: 'Not specified') ?></p></div>
                                                <div><p class="info-label">Kit type</p><p class="info-value"><?= !empty($details['guided_kit_type']) ? htmlspecialchars(ucfirst($details['guided_kit_type'])) : 'Not applicable' ?></p></div>
                                            </div>
                                            <?php if (!empty($guideKitFiles)): ?>
                                                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                                    <?php foreach ($guideKitFiles as $file): ?>
                                                        <?php $kitFileName = uploadedFileDisplayName($file['original_name'] ?? null, $file['file_path']); ?>
                                                        <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="flex items-center justify-between gap-3 rounded-xl border border-blue-100 bg-white p-3 text-[#13324a] transition hover:border-[#1d5f8c] hover:shadow-sm">
                                                            <span class="filename-cell min-w-0"><span class="text-sm font-semibold" title="<?= htmlspecialchars($kitFileName) ?>"><i class="fa-regular fa-image mr-2 text-[#1d5f8c]"></i><?= htmlspecialchars($kitFileName) ?></span><span class="text-xs font-normal text-slate-400 mt-0.5"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'] ?? null, $kitFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'] ?? null)) ?></span></span>
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
                                    </div>
                                <?php endif; ?>

                                <!-- CBCT -->
                                <div>
                                    <h3 class="info-label mb-2">CBCT Scan</h3>
                                    <?php $cbctName = uploadedFileDisplayName($details['cbct_original_name'] ?? null, $details['cbct_file_path']); ?>
                                    <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl p-3">
                                        <div class="flex items-center gap-3 overflow-hidden">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm text-slate-400"><i class="fa-solid fa-x-ray text-lg"></i></div>
                                            <div class="filename-cell min-w-0">
                                                <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($cbctName) ?>"><?= htmlspecialchars($cbctName) ?></span>
                                                <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($details['cbct_content_type'] ?? null, $cbctName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($details['cbct_file_size'] ?? null)) ?></span>
                                            </div>
                                        </div>
                                        <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $details['cbct_file_path'])) ?>" target="_blank" class="shrink-0 ml-3 inline-flex items-center justify-center rounded-lg bg-[#1d5f8c] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#13324a] shadow-sm">
                                            <i class="fa-solid fa-download mr-1.5"></i> Download
                                        </a>
                                    </div>
                                </div>

                                <!-- STL -->
                                <div>
                                    <h3 class="info-label mb-2">Intraoral Scan (STL)</h3>
                                    <?php $stlName = uploadedFileDisplayName($details['stl_original_name'] ?? null, $details['stl_file_path']); ?>
                                    <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl p-3">
                                        <div class="flex items-center gap-3 overflow-hidden">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm text-slate-400"><i class="fa-solid fa-tooth text-lg"></i></div>
                                            <div class="filename-cell min-w-0">
                                                <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($stlName) ?>"><?= htmlspecialchars($stlName) ?></span>
                                                <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($details['stl_content_type'] ?? null, $stlName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($details['stl_file_size'] ?? null)) ?></span>
                                            </div>
                                        </div>
                                        <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $details['stl_file_path'])) ?>" target="_blank" class="shrink-0 ml-3 inline-flex items-center justify-center rounded-lg bg-[#1d5f8c] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#13324a] shadow-sm">
                                            <i class="fa-solid fa-download mr-1.5"></i> Download
                                        </a>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <?php if (!empty($details['notes'])): ?>
                                    <div>
                                        <h3 class="info-label mb-2">Notes</h3>
                                        <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-100 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($details['notes']) ?></div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>

                        <!-- ── Review Packages ── -->
                        <section class="case-card" aria-labelledby="reviewPackagesTitle">
                            <div class="case-card-header">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"><i class="fa-solid fa-file-waveform"></i></div>
                                <div>
                                    <h2 id="reviewPackagesTitle" class="text-base font-bold text-[#13324a]">Case Review & Explanation for Clinic</h2>
                                    <p class="text-xs text-slate-500">Each send creates a permanent review round. Separate from the final delivery package.</p>
                                </div>
                            </div>
                            <div class="case-card-body">

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
                                                        <span class="filename-cell min-w-0 flex-1"><span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($reviewFileName) ?>"><?= htmlspecialchars($reviewFileName) ?></span><span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'], $reviewFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'])) ?></span></span>
                                                        <i class="fa-solid fa-arrow-up-right-from-square shrink-0 text-xs text-[#1d5f8c]"></i>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>

                        <!-- ── Saved Delivery Package (view) ── -->
                        <?php
                        $deliverableMeta = [
                            'video'       => ['title' => 'Plan Videos', 'icon' => 'fa-solid fa-video', 'tone' => 'bg-purple-50 text-purple-600'],
                            'instruction' => ['title' => 'Instructions & Sheets', 'icon' => 'fa-regular fa-file-lines', 'tone' => 'bg-amber-50 text-amber-600'],
                            'guide'       => ['title' => 'Final Guide Files (STL)', 'icon' => 'fa-solid fa-cube', 'tone' => 'bg-blue-50 text-[#1d5f8c]'],
                            'optional'    => ['title' => 'Optional Files', 'icon' => 'fa-solid fa-paperclip', 'tone' => 'bg-slate-100 text-slate-500'],
                        ];
                        ?>
                        <?php if (!empty($deliverables)): ?>
                        <section class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fa-solid fa-box-open"></i></div>
                                <div>
                                    <h2 class="text-base font-bold text-[#13324a]">Saved Delivery Package</h2>
                                    <p class="text-xs text-slate-500">Files already sent to the clinic, grouped by purpose.</p>
                                </div>
                            </div>
                            <div class="case-card-body space-y-6">
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
                                                    <span class="filename-cell min-w-0 flex-1">
                                                        <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($deliveryFileName) ?>"><?= htmlspecialchars($deliveryFileName) ?></span>
                                                        <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'] ?? null, $deliveryFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'] ?? null)) ?></span>
                                                    </span>
                                                    <span class="shrink-0 text-xs font-bold text-[#1d5f8c]">View <i class="fa-solid fa-arrow-up-right-from-square ml-0.5"></i></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php elseif ($request['status'] === 'completed'): ?>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            <p class="font-bold"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Completed request has no saved delivery files.</p>
                            <p class="mt-1 text-xs text-amber-700">Review the case history before contacting the clinic.</p>
                        </div>
                        <?php endif; ?>

                        <!-- ── Upload Delivery Package (in_progress only) ── -->
                        <?php if ($request['status'] === 'in_progress'): ?>
                        <div class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#1d5f8c] text-white shadow-sm"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                <div>
                                    <h2 class="text-lg font-bold text-[#13324a]">Upload Delivery Package</h2>
                                    <p class="text-xs text-slate-500">Provide the final deliverables to the clinic to complete this request.</p>
                                </div>
                            </div>
                            <div class="case-card-body">
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
                                        <!-- Final Guide (Conditional) -->
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
                        </div>
                        <?php elseif (!in_array($request['status'], ['completed', 'rejected'], true)): ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            <i class="fa-solid fa-lock mr-2 text-slate-400"></i>
                            Delivery uploads become available after payment is approved and the request enters production.
                        </div>
                        <?php endif; ?>

                        <!-- ── Reject button for guide ── -->
                        <?php if (in_array($request['status'], ['pending_review', 'awaiting_clinic_approval', 'pending_payment', 'in_progress'], true)): ?>
                        <div class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-500"><i class="fa-solid fa-ban"></i></div>
                                <h2 class="text-sm font-bold text-red-700">Reject Request</h2>
                            </div>
                            <div class="case-card-body">
                                <textarea id="guideRejectionReason" rows="3" class="w-full text-sm border border-red-200 rounded-xl px-3 py-2.5 focus:ring-red-400 focus:border-red-400 bg-red-50 mb-3" placeholder="Rejection reason (optional)"></textarea>
                                <button onclick="updateGuideStatus(<?= $request['id'] ?>, 'rejected')" class="w-full bg-red-50 hover:bg-red-500 text-red-600 hover:text-white border border-red-200 font-bold py-2.5 rounded-xl transition text-sm">
                                    Reject Request
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php endif; /* end surgical_guide */ ?>

                <?php endif; /* end $details */ ?>

                <!-- ── Payment Receipt ── -->
                <?php if ($payment): ?>
                <div class="case-card">
                    <div class="case-card-header">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-400"><i class="fa-solid fa-receipt"></i></div>
                        <h2 class="text-base font-bold text-[#13324a]">Payment Receipt</h2>
                    </div>
                    <div class="case-card-body">
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                            <div class="flex items-center justify-between gap-4 mb-4">
                                <div>
                                    <p class="text-sm font-bold text-[#13324a]">Amount: <?= $payment['amount'] ? number_format($payment['amount'], 2) : 'Not specified' ?></p>
                                    <p class="text-xs text-slate-500">Uploaded: <?= date('M d, Y, H:i', strtotime($payment['uploaded_at'])) ?></p>
                                </div>
                            </div>
                            <?php
                                $receiptUrl  = getPresignedUrl($s3Client, $bucketName, $payment['receipt_file_path']);
                                $receiptName = uploadedFileDisplayName($payment['receipt_original_name'] ?? null, $payment['receipt_file_path']);
                            ?>
                            <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#1d5f8c]"><i class="fa-solid fa-receipt"></i></span>
                                <span class="filename-cell min-w-0 flex-1">
                                    <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($receiptName) ?>"><?= htmlspecialchars($receiptName) ?></span>
                                    <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($payment['receipt_content_type'] ?? null, $receiptName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($payment['receipt_file_size'] ?? null)) ?></span>
                                </span>
                                <a href="<?= htmlspecialchars($receiptUrl) ?>" target="_blank" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#1d5f8c] px-3 py-2 text-xs font-bold text-white hover:bg-[#13324a] transition">
                                    <i class="fa-solid fa-eye mr-1.5"></i> View receipt
                                </a>
                            </div>
                            <?php if ($payment['status'] === 'pending_verification' && !$isSurgicalGuide): ?>
                                <div class="flex gap-3 mt-4 border-t border-slate-200 pt-4">
                                    <button onclick="verifyPayment(<?= $payment['id'] ?>, 'approved')" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 rounded-lg text-sm transition">Approve</button>
                                    <button onclick="verifyPayment(<?= $payment['id'] ?>, 'rejected')" class="bg-red-50 hover:bg-red-500 text-red-600 hover:text-white border border-red-200 font-bold py-2 px-4 rounded-lg text-sm transition">Reject</button>
                                </div>
                            <?php elseif ($payment['status'] === 'pending_verification' && $isSurgicalGuide): ?>
                                <div class="mt-4 border-t border-slate-200 pt-4 text-sm text-red-600">
                                    Historical receipt only. Manual receipt review is disabled for Surgical Guide requests.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── Activity Log ── -->
                <div class="case-card">
                    <div class="case-card-header">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <h2 class="text-base font-bold text-[#13324a]">Request Activity</h2>
                    </div>
                    <div class="case-card-body">
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

            </div><!-- end main column -->

            <!-- ── Chat column (desktop sticky) ── -->
            <?php if ($isSurgicalGuide): ?>
            <div class="chat-column">
                <div id="chatColumnWrapper">
                    <?php include __DIR__ . '/../includes/request_chat_panel.php'; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- end workspace-grid -->
    </div><!-- end page container -->

    <?php if ($isSurgicalGuide): ?>
    <!-- ══ Mobile Chat: FAB + Bubble + Drawer ══ -->
    <button
        id="chatFabButton"
        type="button"
        aria-label="Open conversation"
        aria-expanded="false"
        aria-controls="mobileChatDrawer"
        class="pulsing">
        <i class="fa-solid fa-comment-dots"></i>
    </button>

    <div id="chatAttentionBubble" aria-hidden="true">Click here to view chat</div>

    <div id="mobileChatBackdrop" aria-hidden="true"></div>

    <div
        id="mobileChatDrawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mobileChatDrawerTitle"
        tabindex="-1">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 shrink-0" style="border-radius:1.25rem 1.25rem 0 0;">
            <div class="flex items-center gap-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#1d5f8c] text-white"><i class="fa-regular fa-comments text-sm"></i></div>
                <div>
                    <h2 id="mobileChatDrawerTitle" class="text-sm font-bold text-[#13324a]">Request Conversation</h2>
                    <p class="text-[11px] text-slate-400">Refresh to see new replies</p>
                </div>
            </div>
            <button
                id="closeChatDrawerButton"
                type="button"
                aria-label="Close conversation"
                class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-[#13324a] hover:bg-slate-50 transition focus-visible:outline-2 focus-visible:outline-[#0891b2]">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="mobileChatContent" class="flex flex-col flex-1 min-h-0 overflow-hidden"></div>
    </div>
    <?php endif; ?>

<script>
    const requestWorkflowCsrfToken = <?= json_encode($request_workflow_csrf_token) ?>;

    const reviewFileInput = document.getElementById('reviewFiles');
    const reviewFileList = document.getElementById('reviewSelectedFiles');
    const reviewForm = document.getElementById('reviewPackageForm');

    function compactFileSize(bytes) {
        if (!Number.isFinite(bytes) || bytes < 1) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        let value = bytes, index = 0;
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

    /* ════ Chat functions (same IDs as before) ════ */
    const messagesContainer = document.getElementById('requestMessages');
    const chatStatus = document.getElementById('requestChatStatus');

    function latestMessageId() {
        const items = messagesContainer ? messagesContainer.querySelectorAll('[data-message-id]') : [];
        return items.length ? Number(items[items.length - 1].dataset.messageId) : 0;
    }

    function setChatStatus(message, isError = false) {
        if (!chatStatus) return;
        chatStatus.textContent = message;
        chatStatus.className = `mb-2 rounded-xl border p-2.5 text-sm font-semibold ${isError ? 'border-red-100 bg-red-50 text-red-700' : 'border-cyan-100 bg-cyan-50 text-cyan-800'}`;
        chatStatus.classList.remove('hidden');
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

    const chatRefreshCooldownSeconds = <?= (int) REQUEST_MESSAGE_REFRESH_COOLDOWN_SECONDS ?>;
    const chatRefreshDefaultMarkup = '<i class="fa-solid fa-rotate mr-1.5"></i>Refresh';
    let chatRefreshCooldownUntil = 0;
    let chatRefreshCooldownTimer = null;

    function renderChatRefreshCooldown(button) {
        if (!button) return;
        if (chatRefreshCooldownTimer) clearTimeout(chatRefreshCooldownTimer);
        const remaining = Math.ceil((chatRefreshCooldownUntil - Date.now()) / 1000);
        if (remaining > 0) {
            button.disabled = true;
            button.innerHTML = `<i class="fa-regular fa-clock mr-1.5"></i>Refresh in ${remaining}s`;
            chatRefreshCooldownTimer = setTimeout(() => renderChatRefreshCooldown(button), 250);
            return;
        }
        chatRefreshCooldownUntil = 0;
        chatRefreshCooldownTimer = null;
        button.disabled = false;
        button.innerHTML = chatRefreshDefaultMarkup;
    }

    function startChatRefreshCooldown(button, seconds = chatRefreshCooldownSeconds) {
        const safeSeconds = Math.max(1, Math.min(60, Math.ceil(Number(seconds) || chatRefreshCooldownSeconds)));
        chatRefreshCooldownUntil = Date.now() + (safeSeconds * 1000);
        renderChatRefreshCooldown(button);
    }

    document.getElementById('refreshMessagesButton')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1.5"></i>Refreshing…';
        try {
            const response = await fetch(`../api/request_messages.php?request_id=<?= (int) $request['id'] ?>&after_id=${latestMessageId()}&csrf_token=${encodeURIComponent(requestWorkflowCsrfToken)}`, {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (response.status === 429) {
                startChatRefreshCooldown(button, data.retry_after);
                throw new Error(data.message || 'Please wait before refreshing messages again.');
            }
            if (!response.ok || !data.success) throw new Error(data.message || 'Messages could not be loaded.');
            data.messages.forEach(appendChatMessage);
            setChatStatus(data.messages.length ? `${data.messages.length} new message(s) loaded.` : 'No new messages.');
            startChatRefreshCooldown(button);
        } catch (error) {
            setChatStatus(error.message || 'Messages could not be loaded.', true);
        } finally {
            if (!chatRefreshCooldownUntil) {
                button.disabled = false;
                button.innerHTML = chatRefreshDefaultMarkup;
            }
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

    /* ════ Status / Reject functions ════ */
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
        if (newStatus === 'rejected' && !reason) { alert('Rejection reason is required.'); return; }
        try {
            const body = new URLSearchParams({request_id: requestId, status: newStatus, reason});
            const response = await fetch('../api/update_request_status.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body.toString()});
            const data = await response.json();
            if (response.ok) { alert(data.success || 'Updated successfully'); location.reload(); }
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
            const body = new URLSearchParams({request_id: requestId, status: newStatus, reason, csrf_token: requestWorkflowCsrfToken});
            const response = await fetch('../api/update_request_status.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body.toString()});
            const data = await response.json();
            if (response.ok) { alert(data.success || 'Request step updated successfully.'); location.reload(); }
            else { alert(data.error || 'Could not update the request step.'); }
        } catch (e) { alert('Network error occurred.'); }
    }

    async function verifyPayment(paymentId, action) {
        if (!confirm(`Are you sure you want to ${action} this payment receipt?`)) return;
        try {
            const body = new URLSearchParams({payment_id: paymentId, action, csrf_token: requestWorkflowCsrfToken});
            const response = await fetch('../api/verify_receipt.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body.toString()});
            const data = await response.json();
            if (response.ok) { alert(data.success || 'Verified successfully'); location.reload(); }
            else { alert(data.error || 'Could not verify this receipt.'); }
        } catch(e) { alert('Network error occurred.'); }
    }

    /* ════ Deliverables upload ════ */
    let uploadedDeliverables = { video: [], instruction: [], guide: [], optional: [] };

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
        for (let i = 0; i < files.length; i++) { totalSize += files[i].size; }
        const uploadPromises = Array.from(files).map((file, index) => {
            return new Promise(async (resolve, reject) => {
                try {
                    const response = await fetch('../api/generate_presigned_url.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({filename: file.name, contentType: file.type || 'application/octet-stream', fileSize: file.size})
                    });
                    const data = await response.json();
                    if (!response.ok || !data.presigned_url || data.error) {
                        throw new Error(data.error || `Failed to get secure upload URL for ${file.name}.`);
                    }
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', data.presigned_url, true);
                    xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
                    xhr.upload.onprogress = function(e) {
                        if (e.lengthComputable) {
                            loadedSizes[index] = e.loaded;
                            const totalLoaded = loadedSizes.reduce((a, b) => a + b, 0);
                            const pct = Math.round((totalLoaded / totalSize) * 100);
                            if (progressBar) progressBar.style.width = pct + '%';
                            if (progressText) progressText.textContent = pct + '%';
                        }
                    };
                    xhr.onload = function() {
                        if (xhr.status === 200) { uploadedDeliverables[category].push(data.object_key); resolve(); }
                        else { reject(new Error(`Upload failed for ${file.name} (Status: ${xhr.status})`)); }
                    };
                    xhr.onerror = () => reject(new Error(`Network error during upload for ${file.name}`));
                    xhr.send(file);
                } catch (error) { reject(error); }
            });
        });
        await Promise.all(uploadPromises);
    }

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
            const uploadTasks = [
                uploadAdminFilesToCloud('admin_plan_video', 'video', 'videoProgressContainer', 'videoProgressBar', 'videoProgressText'),
                uploadAdminFilesToCloud('admin_instruction_file', 'instruction', 'instructionProgressContainer', 'instructionProgressBar', 'instructionProgressText'),
                uploadAdminFilesToCloud('admin_optional_file', 'optional', 'optionalProgressContainer', 'optionalProgressBar', 'optionalProgressText')
            ];
            if (deliveryMethod === 'clinic_print') {
                uploadTasks.push(uploadAdminFilesToCloud('admin_guide_file', 'guide', 'guideProgressContainer', 'guideProgressBar', 'guideProgressText'));
            }
            await Promise.all(uploadTasks);
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Saving to Database...';
            const response = await fetch('../api/admin_save_deliverables.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({request_id: requestId, files: uploadedDeliverables, csrf_token: requestWorkflowCsrfToken})
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

    /* ════════════════════════════════════
       Mobile Chat: FAB + Drawer (same logic)
       ════════════════════════════════════ */
    (function() {
        const fab        = document.getElementById('chatFabButton');
        const backdrop   = document.getElementById('mobileChatBackdrop');
        const drawer     = document.getElementById('mobileChatDrawer');
        const closeBtn   = document.getElementById('closeChatDrawerButton');
        const bubble     = document.getElementById('chatAttentionBubble');
        const chatPanel  = document.getElementById('requestChatPanel');
        const mobileSlot = document.getElementById('mobileChatContent');
        const desktopCol = document.getElementById('chatColumnWrapper');

        if (!fab || !drawer || !chatPanel) return;

        let drawerOpen = false;
        let bubbleTimers = [];
        const SESSION_KEY = 'chatOpenedReq<?= (int) $request['id'] ?>';

        const mq = window.matchMedia('(min-width: 1024px)');

        function placePanel() {
            if (mq.matches) {
                if (desktopCol && chatPanel.parentElement !== desktopCol) desktopCol.appendChild(chatPanel);
            } else {
                if (drawerOpen && mobileSlot && chatPanel.parentElement !== mobileSlot) mobileSlot.appendChild(chatPanel);
            }
        }

        mq.addEventListener('change', () => {
            if (mq.matches && drawerOpen) closeDrawer(false);
            placePanel();
        });

        function openDrawer() {
            if (drawerOpen) return;
            drawerOpen = true;
            if (mobileSlot) mobileSlot.appendChild(chatPanel);
            backdrop.classList.add('active');
            requestAnimationFrame(() => backdrop.classList.add('visible'));
            document.body.style.overflow = 'hidden';
            drawer.classList.add('open');
            fab.setAttribute('aria-expanded', 'true');
            setTimeout(() => {
                if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;
                drawer.focus();
            }, 350);
            sessionStorage.setItem(SESSION_KEY, '1');
            hideBubble();
            stopBubbleTimers();
            fab.classList.remove('pulsing');
        }

        function closeDrawer(returnFocus = true) {
            if (!drawerOpen) return;
            drawerOpen = false;
            drawer.classList.remove('open');
            backdrop.classList.remove('visible');
            document.body.style.overflow = '';
            fab.setAttribute('aria-expanded', 'false');
            setTimeout(() => {
                backdrop.classList.remove('active');
                if (desktopCol && mq.matches) desktopCol.appendChild(chatPanel);
            }, 350);
            if (returnFocus) fab.focus();
        }

        fab.addEventListener('click', openDrawer);
        closeBtn?.addEventListener('click', () => closeDrawer());
        backdrop.addEventListener('click', () => closeDrawer());
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && drawerOpen) closeDrawer(); });

        function showBubble() {
            if (sessionStorage.getItem(SESSION_KEY)) return;
            bubble.classList.add('visible');
            bubble.setAttribute('aria-hidden', 'false');
            const hideT = setTimeout(() => hideBubble(), 4000);
            bubbleTimers.push(hideT);
        }
        function hideBubble() {
            bubble.classList.remove('visible');
            bubble.setAttribute('aria-hidden', 'true');
        }
        function stopBubbleTimers() {
            bubbleTimers.forEach(t => clearTimeout(t));
            bubbleTimers = [];
        }
        function scheduleBubble() {
            if (sessionStorage.getItem(SESSION_KEY)) return;
            const firstT = setTimeout(() => {
                showBubble();
                function repeat() {
                    if (sessionStorage.getItem(SESSION_KEY)) return;
                    const rptT = setTimeout(() => { showBubble(); repeat(); }, 25000 + Math.random() * 5000);
                    bubbleTimers.push(rptT);
                }
                repeat();
            }, 5000 + Math.random() * 1000);
            bubbleTimers.push(firstT);
        }

        if (!mq.matches) scheduleBubble();
        mq.addEventListener('change', () => {
            if (mq.matches) { stopBubbleTimers(); hideBubble(); }
            else if (!sessionStorage.getItem(SESSION_KEY)) scheduleBubble();
        });
        window.addEventListener('pagehide', stopBubbleTimers);
        window.addEventListener('beforeunload', stopBubbleTimers);
    })();
</script>
</body>
</html>
