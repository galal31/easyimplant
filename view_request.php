<?php
// view_request.php
require_once 'includes/db_connect.php';
require_once 'includes/surgical_guide_pricing.php';
require_once 'includes/surgical_guide_kits.php';
require_once 'includes/surgeon_services.php';
require_once 'includes/r2_config.php';
require_once 'includes/request_file_metadata.php';
require_once 'includes/request_workflow.php';
require_once 'includes/request_review.php';

use Aws\Exception\AwsException;

// Check if user is logged in and is a clinic
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$clinic_name = $_SESSION['clinic_name'];

if (empty($_SESSION['request_workflow_csrf_token'])) {
    $_SESSION['request_workflow_csrf_token'] = bin2hex(random_bytes(32));
}
$request_workflow_csrf_token = $_SESSION['request_workflow_csrf_token'];

$request_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$request_id) {
    die("Invalid request ID.");
}

// دالة لتوليد رابط تحميل مؤقت صالح لمدة ساعة (60 دقيقة)
function getPresignedUrl($s3Client, $bucketName, $key) {
    if (empty($key)) return '#';
    try {
        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $bucketName,
            'Key'    => $key
        ]);
        // الرابط هيكون شغال لمدة ساعة واحدة بس للأمان
        $request = $s3Client->createPresignedRequest($cmd, '+60 minutes');
        return (string) $request->getUri();
    } catch (AwsException $e) {
        error_log("Presigned URL Error: " . $e->getMessage());
        return '#';
    }
}
// ---------------------------------------------------------

try {
    // Fetch request details
    $stmt = $pdo->prepare("
        SELECT * FROM requests 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([':id' => $request_id, ':user_id' => $user_id]);
    $request = $stmt->fetch();

    if (!$request) {
        die("Request not found or you do not have permission to view it.");
    }

    // Fetch specific details based on type
    $deliverables = [];
    $surgeonArches = [];
    $surgeonFiles = [];
    $guideKitFiles = [];
    $reviewPackages = [];
    $requestMessages = [];
    if ($request['service_type'] === 'surgical_guide') {
        ensureSurgicalGuideKitsSchema($pdo);
        $stmt_details = $pdo->prepare("SELECT * FROM surgical_guide_details WHERE request_id = :id");
        $stmt_details->execute([':id' => $request_id]);
        $details = $stmt_details->fetch();

        $stmt_kit_files = $pdo->prepare("SELECT file_path, original_name, content_type, file_size FROM surgical_guide_kit_files WHERE request_id = :id ORDER BY created_at, id");
        $stmt_kit_files->execute([':id' => $request_id]);
        $guideKitFiles = $stmt_kit_files->fetchAll();

        $stmt_arches = $pdo->prepare("SELECT * FROM surgeon_request_arches WHERE request_id = :id ORDER BY FIELD(arch_position, 'upper', 'lower')");
        $stmt_arches->execute([':id' => $request_id]);
        $surgeonArches = $stmt_arches->fetchAll();

        $stmt_files = $pdo->prepare("SELECT * FROM surgeon_request_files WHERE request_id = :id ORDER BY file_category, created_at, id");
        $stmt_files->execute([':id' => $request_id]);
        $surgeonFiles = $stmt_files->fetchAll();

        // If the request is completed, fetch all deliverables from the new table
        if ($request['status'] === 'completed') {
            $stmt_deliverables = $pdo->prepare("SELECT file_type, file_path, original_name, content_type, file_size FROM request_deliverables WHERE request_id = :id ORDER BY created_at, id");
            $stmt_deliverables->execute([':id' => $request_id]);
            $fetched_deliverables = $stmt_deliverables->fetchAll();
            // Group deliverables by their type
            foreach ($fetched_deliverables as $file) {
                $deliverables[$file['file_type']][] = $file;
            }
        }
        $reviewPackages = fetchRequestReviewPackages($pdo, (int) $request_id);
        $requestMessages = fetchRequestMessages($pdo, (int) $request_id);
    } else {
        $stmt_details = $pdo->prepare("SELECT sr.*, u.full_name as surgeon_name 
                                       FROM surgeon_requests sr 
                                       LEFT JOIN users u ON sr.assigned_surgeon_id = u.id 
                                       WHERE sr.request_id = :id");
        $stmt_details->execute([':id' => $request_id]);
        $details = $stmt_details->fetch();

    }

    // Fetch payment details if they exist
    $stmt_pay = $pdo->prepare("SELECT * FROM payments WHERE request_id = :id ORDER BY uploaded_at DESC LIMIT 1");
    $stmt_pay->execute([':id' => $request_id]);
    $payment = $stmt_pay->fetch();

} catch (\PDOException $e) {
    error_log("View Request DB Error: " . $e->getMessage());
    die("Database error occurred.");
}

function getStatusBadge($status) {
    $badges = [
        'pending_review' => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-amber-50 text-amber-600 border border-amber-200">Pending Review</span>',
        'awaiting_clinic_approval' => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200">Awaiting Your Approval</span>',
        'rejected'       => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-50 text-red-600 border border-red-200">Rejected</span>',
        'pending_payment'=> '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-orange-50 text-orange-600 border border-orange-200">Pending Payment</span>',
        'in_progress'    => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200">In Progress</span>',
        'completed'      => '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200">Completed</span>'
    ];
    return $badges[$status] ?? '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-slate-100 text-slate-600">Unknown</span>';
}

function formatLabel($key) {
    return ucwords(str_replace('_', ' ', $key));
}

$guideDisplayFields = [
    'operation_date',
    'cbct_file_path',
    'stl_file_path',
    'implant_type',
    'guided_kit_source',
    'guided_kit_name',
    'guided_kit_type',
    'guided_kit_rental_price',
    'delivery_method',
    'notes',
];

$isSurgicalGuide = $request['service_type'] === 'surgical_guide';
$isChatWritable  = $isSurgicalGuide && surgicalGuideChatIsWritable($request['status']);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Request Details | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }

        /* ── Chat Panel sticky ── */
        #chatColumnWrapper {
            position: sticky;
            top: 73px; /* nav height */
            height: calc(100dvh - 73px - 1.5rem);
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

        /* ── Page layout grid ── */
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

        /* ── Chat column: hide on mobile, show on desktop ── */
        .chat-column { display: none; }
        @media (min-width: 1024px) {
            .chat-column { display: block; }
        }

        /* ── Mobile chat FAB ── */
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
            box-shadow: 0 6px 24px rgba(29,95,140,0.55), 0 0 0 0 rgba(8,145,178,0.3);
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
        #chatAttentionBubble.visible {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }
        @media (min-width: 1024px) { #chatAttentionBubble { display: none; } }

        /* ── Mobile Chat Drawer ── */
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
            #mobileChatDrawer { transition: none; }
            #mobileChatBackdrop { transition: none; }
        }
        @media (min-width: 1024px) {
            #mobileChatDrawer, #mobileChatBackdrop { display: none !important; }
        }

        /* ── Inside drawer: messages flex ── */
        #mobileChatDrawer .drawer-messages-wrap {
            flex: 1 1 auto;
            overflow-y: auto;
            min-height: 0;
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

        /* ── Info grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        @media (max-width: 639px) { .info-grid { grid-template-columns: 1fr; } }
        .info-item { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 0.75rem; padding: 0.75rem 1rem; }
        .info-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.25rem; }
        .info-value { font-size: 0.875rem; font-weight: 600; color: #1e293b; }

        /* Truncate long filenames safely */
        .filename-cell { min-width: 0; overflow: hidden; }
        .filename-cell span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        video, img { max-width: 100%; }
    </style>
</head>
<body class="bg-[#f4f8fb] text-slate-800 antialiased">

    <!-- ══ Navigation ══ -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30" style="height:73px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full">
            <div class="flex justify-between items-center h-full">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#13324a] text-white">
                        <i class="fa-solid fa-tooth text-sm"></i>
                    </div>
                    <span class="font-bold text-[#13324a] text-lg">Easy Implant</span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="hidden sm:block text-right">
                        <p class="text-sm font-bold text-[#13324a] leading-tight"><?= htmlspecialchars($full_name) ?></p>
                        <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($clinic_name) ?></p>
                    </div>
                    <a href="clinic_dashboard.php" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-[#13324a]">
                        Dashboard
                    </a>
                    <a href="logout.php" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 hover:border-red-100">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ══ Page Container ══ -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- ── Request header ── -->
        <div class="flex items-center gap-4 mb-4">
            <a href="clinic_dashboard.php" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-[#13324a] hover:bg-slate-50 transition shadow-sm" aria-label="Back to dashboard">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold text-[#13324a]">Request #<?= str_pad($request['id'], 5, '0', STR_PAD_LEFT) ?></h1>
                <p class="text-sm text-slate-500 mt-0.5">Submitted on <?= date('F j, Y', strtotime($request['created_at'])) ?> · <?= $isSurgicalGuide ? 'Surgical Guide' : 'Surgeon Request' ?></p>
            </div>
            <div class="shrink-0"><?= getStatusBadge($request['status']) ?></div>
        </div>

        <?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
            <div class="mb-4 rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-extrabold mb-1"><i class="fa-solid fa-circle-exclamation mr-2"></i>Request Rejected</p>
                <p><?= nl2br(htmlspecialchars($request['rejection_reason'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($isSurgicalGuide): ?>
            <?php
                $guideSteps = ['pending_review', 'awaiting_clinic_approval', 'pending_payment', 'in_progress', 'completed'];
                $guideStepIndex = array_search($request['status'], $guideSteps, true);
                $guideStepIndex = $guideStepIndex === false ? -1 : $guideStepIndex;
            ?>
            <section class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Surgical Guide progress">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-[#13324a]">Request progress</h2>
                    <span class="text-xs text-slate-400">Five protected stages</span>
                </div>
                <div class="grid grid-cols-5 gap-1 text-center">
                    <?php foreach (['Admin review', 'Your approval', 'Payment', 'Production', 'Complete'] as $index => $label): ?>
                        <div>
                            <div class="h-2 rounded-full <?= $request['status'] !== 'rejected' && $index <= $guideStepIndex ? 'bg-[#1d5f8c]' : 'bg-slate-200' ?>"></div>
                            <span class="mt-1.5 block text-[10px] font-bold leading-tight <?= $request['status'] !== 'rejected' && $index <= $guideStepIndex ? 'text-[#1d5f8c]' : 'text-slate-400' ?>"><?= $label ?></span>
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
                    <?php if ($request['service_type'] === 'surgeon_request'): ?>
                        <!-- Surgeon request details (unchanged) -->
                        <div class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-500"><i class="fa-solid fa-user-doctor"></i></div>
                                <h2 class="text-lg font-bold text-[#13324a]">Surgeon Request Details</h2>
                                <?php if ($request['status'] === 'pending_payment'): ?>
                                    <a href="upload_receipt.php?id=<?= $request['id'] ?>" class="ml-auto inline-flex items-center justify-center rounded-lg bg-orange-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-orange-600 shadow-sm">
                                        <i class="fa-solid fa-upload mr-2"></i> Upload Receipt
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="case-card-body">
                                <?php require __DIR__ . '/includes/surgeon_request_details.php'; ?>
                                <?php if (!empty($details['surgeon_name'])): ?>
                                    <div class="mt-6 p-4 rounded-xl bg-teal-50 border border-teal-100 flex items-start gap-4">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white shadow-sm text-teal-600 text-lg"><i class="fa-solid fa-user-md"></i></div>
                                        <div>
                                            <h3 class="text-sm font-bold text-[#13324a]">Assigned Surgeon</h3>
                                            <p class="text-sm text-slate-600 mt-1">Dr. <?= htmlspecialchars($details['surgeon_name']) ?> has been assigned to this case.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- ── Surgical Guide: Key info cards ── -->
                        <div class="case-card">
                            <div class="case-card-header">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-500"><i class="fa-solid fa-layer-group"></i></div>
                                <h2 class="text-lg font-bold text-[#13324a]">Surgical Guide Details</h2>
                            </div>
                            <div class="case-card-body space-y-5">

                                <!-- Key stats in a compact grid -->
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">Operation Date</div>
                                        <div class="info-value"><?= date('F j, Y', strtotime($details['operation_date'] ?? 'now')) ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Implant Type</div>
                                        <div class="info-value"><?= htmlspecialchars($details['implant_type'] ?: 'Not specified') ?></div>
                                    </div>
                                    <div class="info-item" style="grid-column: 1 / -1;">
                                        <div class="info-label">Delivery Method</div>
                                        <div class="info-value flex items-center gap-2">
                                            <i class="fa-solid <?= ($details['delivery_method'] ?? '') === 'clinic_print' ? 'fa-print text-[#1d5f8c]' : 'fa-truck text-purple-600' ?>"></i>
                                            <?= ($details['delivery_method'] ?? '') === 'clinic_print' ? 'I will print it at my clinic' : 'Admin will print and deliver physical guide' ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- CBCT File -->
                                <?php if (!empty($details['cbct_file_path'])): ?>
                                    <?php
                                        $cbctName = uploadedFileDisplayName($details['cbct_original_name'] ?? null, $details['cbct_file_path']);
                                        $cbctUrl  = getPresignedUrl($s3Client, $bucketName, $details['cbct_file_path']);
                                    ?>
                                    <div>
                                        <h3 class="info-label mb-2">CBCT Scan</h3>
                                        <div class="flex items-center gap-3 bg-slate-50 border border-slate-100 rounded-xl p-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white shadow-sm text-slate-400 shrink-0"><i class="fa-solid fa-x-ray text-lg"></i></div>
                                            <div class="filename-cell flex-1">
                                                <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($cbctName) ?>"><?= htmlspecialchars($cbctName) ?></span>
                                                <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($details['cbct_content_type'] ?? null, $cbctName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($details['cbct_file_size'] ?? null)) ?></span>
                                            </div>
                                            <a href="<?= htmlspecialchars($cbctUrl) ?>" target="_blank" class="shrink-0 text-xs font-bold text-[#1d5f8c] hover:underline">Download / View <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- STL File -->
                                <?php if (!empty($details['stl_file_path'])): ?>
                                    <?php
                                        $stlName = uploadedFileDisplayName($details['stl_original_name'] ?? null, $details['stl_file_path']);
                                        $stlUrl  = getPresignedUrl($s3Client, $bucketName, $details['stl_file_path']);
                                    ?>
                                    <div>
                                        <h3 class="info-label mb-2">Intraoral Scan (STL)</h3>
                                        <div class="flex items-center gap-3 bg-slate-50 border border-slate-100 rounded-xl p-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white shadow-sm text-slate-400 shrink-0"><i class="fa-solid fa-tooth text-lg"></i></div>
                                            <div class="filename-cell flex-1">
                                                <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($stlName) ?>"><?= htmlspecialchars($stlName) ?></span>
                                                <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($details['stl_content_type'] ?? null, $stlName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($details['stl_file_size'] ?? null)) ?></span>
                                            </div>
                                            <a href="<?= htmlspecialchars($stlUrl) ?>" target="_blank" class="shrink-0 text-xs font-bold text-[#1d5f8c] hover:underline">Download / View <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Guided Kit -->
                                <?php if (!empty($details['guided_kit_source'])): ?>
                                    <div>
                                        <h3 class="info-label mb-2">Guided Kit</h3>
                                        <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <div class="info-label">Source</div>
                                                    <div class="info-value"><?= ($details['guided_kit_source'] ?? '') === 'rental' ? 'Rental from Easy Implant' : 'Clinic-owned kit' ?></div>
                                                </div>
                                                <div class="info-item">
                                                    <div class="info-label">Kit Name</div>
                                                    <div class="info-value" title="<?= htmlspecialchars($details['guided_kit_name'] ?? '') ?>"><?= htmlspecialchars($details['guided_kit_name'] ?: 'Not specified') ?></div>
                                                </div>
                                                <?php if (($details['guided_kit_source'] ?? '') === 'owned'): ?>
                                                    <div class="info-item">
                                                        <div class="info-label">Kit Type</div>
                                                        <div class="info-value"><?= !empty($details['guided_kit_type']) ? ucfirst($details['guided_kit_type']) : 'Not applicable' ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (($details['guided_kit_source'] ?? '') === 'rental' && !empty($details['guided_kit_rental_price'])): ?>
                                                    <div class="info-item">
                                                        <div class="info-label">Rental Price</div>
                                                        <div class="info-value"><?= formatMoney($details['guided_kit_rental_price']) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($guideKitFiles): ?>
                                                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                                    <?php foreach ($guideKitFiles as $file): ?>
                                                        <?php $kitFileName = uploadedFileDisplayName($file['original_name'] ?? null, $file['file_path']); ?>
                                                        <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="flex items-center justify-between gap-3 rounded-xl border border-blue-100 bg-white p-3 text-[#13324a] transition hover:border-[#1d5f8c]">
                                                            <span class="filename-cell min-w-0"><span class="text-sm font-semibold" title="<?= htmlspecialchars($kitFileName) ?>"><i class="fa-regular fa-image mr-2 text-[#1d5f8c]"></i><?= htmlspecialchars($kitFileName) ?></span><span class="text-xs font-normal text-slate-400 mt-0.5"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'] ?? null, $kitFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'] ?? null)) ?></span></span>
                                                            <span class="shrink-0 text-xs font-bold text-[#1d5f8c]">View photo <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php elseif (($details['guided_kit_source'] ?? '') === 'owned'): ?>
                                                <div class="mt-3 rounded-xl border border-dashed border-blue-200 bg-white/70 p-3 text-sm text-slate-500">
                                                    <i class="fa-regular fa-images mr-2 text-[#1d5f8c]"></i>No kit photos were attached. Photos are optional.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Notes -->
                                <?php if (!empty($details['notes'])): ?>
                                    <div>
                                        <h3 class="info-label mb-2">Notes</h3>
                                        <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-100 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($details['notes']) ?></div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>

                        <!-- ── Case Review Packages ── -->
                        <section class="case-card" aria-labelledby="clinicReviewTitle">
                            <div class="case-card-header">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"><i class="fa-solid fa-file-circle-check"></i></div>
                                <div>
                                    <h2 id="clinicReviewTitle" class="text-base font-bold text-[#13324a]">Case Review from Easy Implant</h2>
                                    <p class="text-xs text-slate-500">Review the explanation and every file in the latest round before approving the plan.</p>
                                </div>
                            </div>
                            <div class="case-card-body">

                                <?php if (!$reviewPackages): ?>
                                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center">
                                        <i class="fa-regular fa-folder-open mb-3 text-2xl text-slate-300 block"></i>
                                        <p class="text-sm font-semibold text-slate-600">No review has been sent yet.</p>
                                        <p class="mt-1 text-xs text-slate-400">The administration is still reviewing your case and files.</p>
                                    </div>
                                <?php else: ?>
                                    <?php if ($request['status'] === 'awaiting_clinic_approval'): ?>
                                        <div class="mb-5 rounded-2xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900">
                                            <p class="font-bold"><i class="fa-solid fa-circle-info mr-2"></i>Your decision is needed on the latest review round.</p>
                                            <p class="mt-1 text-xs leading-5 text-cyan-800">If you need changes, send your notes in the conversation. The request remains in this stage until you approve.</p>
                                        </div>
                                    <?php elseif ($request['status'] === 'pending_payment'): ?>
                                        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                                            <p class="font-bold"><i class="fa-solid fa-circle-check mr-2"></i>The plan is approved.</p>
                                            <p class="mt-1 text-xs leading-5">Online payment will become available after the payment gateway is connected. No receipt or payment is required here now.</p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="space-y-5">
                                        <?php foreach ($reviewPackages as $reviewIndex => $package): ?>
                                            <article class="overflow-hidden rounded-2xl border <?= $reviewIndex === 0 ? 'border-cyan-200 shadow-sm' : 'border-slate-200' ?> bg-white">
                                                <header class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between <?= $reviewIndex === 0 ? 'bg-cyan-50/60' : 'bg-slate-50' ?>">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h3 class="font-bold text-[#13324a]">Review round #<?= (int) $package['id'] ?></h3>
                                                        <?php if ($reviewIndex === 0): ?><span class="rounded-full bg-cyan-100 px-2.5 py-1 text-[11px] font-bold text-cyan-800">Latest review</span><?php endif; ?>
                                                        <?php if (!empty($package['approved_at'])): ?><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Approved</span><?php endif; ?>
                                                    </div>
                                                    <time class="text-xs text-slate-500"><?= date('M d, Y, H:i', strtotime($package['sent_at'])) ?></time>
                                                </header>
                                                <div class="p-5">
                                                    <?php if (!empty($package['summary'])): ?>
                                                        <div class="mb-5 whitespace-pre-wrap rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm leading-7 text-slate-700"><?= htmlspecialchars($package['summary']) ?></div>
                                                    <?php else: ?>
                                                        <p class="mb-5 text-sm text-slate-500">This round contains files without an additional written explanation.</p>
                                                    <?php endif; ?>

                                                    <div class="grid gap-4 sm:grid-cols-2">
                                                        <?php foreach ($package['files'] as $file): ?>
                                                            <?php
                                                                $reviewFileName = uploadedFileDisplayName($file['original_name'], $file['file_path']);
                                                                $reviewFileUrl  = getPresignedUrl($s3Client, $bucketName, $file['file_path']);
                                                                $reviewContentType = strtolower((string) $file['content_type']);
                                                            ?>
                                                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                                                <?php if (str_starts_with($reviewContentType, 'image/')): ?>
                                                                    <a href="<?= htmlspecialchars($reviewFileUrl) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars($reviewFileUrl) ?>" alt="<?= htmlspecialchars($reviewFileName) ?>" class="h-44 w-full bg-white object-contain" loading="lazy"></a>
                                                                <?php elseif (str_starts_with($reviewContentType, 'video/')): ?>
                                                                    <video controls preload="metadata" class="h-44 w-full bg-slate-950 object-contain"><source src="<?= htmlspecialchars($reviewFileUrl) ?>" type="<?= htmlspecialchars($reviewContentType) ?>">Your browser cannot preview this video.</video>
                                                                <?php endif; ?>
                                                                <div class="flex min-w-0 items-center gap-3 p-3">
                                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-cyan-700 shadow-sm"><i class="fa-solid fa-paperclip"></i></span>
                                                                    <span class="filename-cell min-w-0 flex-1">
                                                                        <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($reviewFileName) ?>"><?= htmlspecialchars($reviewFileName) ?></span>
                                                                        <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'], $reviewFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'])) ?></span>
                                                                    </span>
                                                                    <a href="<?= htmlspecialchars($reviewFileUrl) ?>" target="_blank" rel="noopener" class="shrink-0 rounded-lg bg-white px-3 py-2 text-xs font-bold text-[#1d5f8c] shadow-sm hover:bg-blue-50">Open</a>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <?php if ($reviewIndex === 0 && $request['status'] === 'awaiting_clinic_approval' && empty($package['approved_at'])): ?>
                                                        <div class="mt-5 border-t border-slate-100 pt-5">
                                                            <button type="button" id="approveReviewButton" data-package-id="<?= (int) $package['id'] ?>" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60">
                                                                <i class="fa-solid fa-check-double mr-2"></i>Approve the latest plan
                                                            </button>
                                                            <p class="mt-2 text-center text-xs text-slate-500">You will confirm this decision before it is saved.</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div id="reviewApprovalStatus" class="mt-4 hidden rounded-xl border p-3 text-sm font-semibold" role="status"></div>
                            </div>
                        </section>

                        <!-- ── Delivery Package ── -->
                        <?php if (!empty($deliverables)): ?>
                            <section class="case-card">
                                <div class="case-card-header">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600"><i class="fa-solid fa-box-open"></i></div>
                                    <h2 class="text-lg font-bold text-[#13324a]">Delivery Package</h2>
                                </div>
                                <div class="case-card-body space-y-6">
                                    <?php
                                    $deliverable_meta = [
                                        'video'       => ['title' => 'Plan Videos', 'icon' => 'fa-solid fa-video'],
                                        'instruction' => ['title' => 'Instructions & Sheets', 'icon' => 'fa-regular fa-file-lines'],
                                        'guide'       => ['title' => 'Guide Files (STL)', 'icon' => 'fa-solid fa-cube'],
                                        'optional'    => ['title' => 'Optional Files', 'icon' => 'fa-solid fa-paperclip'],
                                    ];
                                    ?>
                                    <?php foreach ($deliverable_meta as $type => $meta): ?>
                                        <?php if (!empty($deliverables[$type])): ?>
                                            <div>
                                                <h3 class="text-base font-bold text-[#13324a] mb-3"><?= $meta['title'] ?></h3>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <?php foreach ($deliverables[$type] as $file): ?>
                                                        <?php $deliveryFileName = uploadedFileDisplayName($file['original_name'] ?? null, $file['file_path']); ?>
                                                        <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-3 transition hover:border-[#1d5f8c] hover:shadow-sm group">
                                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 group-hover:bg-blue-50 group-hover:text-[#1d5f8c] transition shrink-0"><i class="<?= $meta['icon'] ?> text-lg"></i></div>
                                                            <div class="filename-cell flex-1">
                                                                <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($deliveryFileName) ?>"><?= htmlspecialchars($deliveryFileName) ?></span>
                                                                <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'] ?? null, $deliveryFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'] ?? null)) ?></span>
                                                            </div>
                                                            <span class="text-xs font-bold text-[#1d5f8c] shrink-0">Download</span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php elseif ($isSurgicalGuide && $request['status'] === 'completed'): ?>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                <p class="font-bold"><i class="fa-solid fa-circle-exclamation mr-2"></i>The delivery package is not available.</p>
                                <p class="mt-1 text-xs text-amber-700">Please contact the administration and mention request #<?= (int) $request['id'] ?>.</p>
                            </div>
                        <?php endif; ?>

                    <?php endif; /* end surgical_guide content */ ?>

                <?php else: ?>
                    <div class="case-card">
                        <div class="case-card-body text-center py-8 text-slate-500 text-sm">No specific details found for this request.</div>
                    </div>
                <?php endif; ?>

                <!-- ── Payment Receipt (read-only for surgical guide) ── -->
                <div class="case-card">
                    <div class="case-card-header">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-400"><i class="fa-solid fa-receipt"></i></div>
                        <h2 class="text-base font-bold text-[#13324a]">Payment Receipt</h2>
                    </div>
                    <div class="case-card-body">
                        <?php if ($payment): ?>
                            <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                                <div>
                                    <p class="info-label">Status</p>
                                    <p class="text-sm font-bold <?= $payment['status'] === 'approved' ? 'text-emerald-600' : ($payment['status'] === 'rejected' ? 'text-red-600' : 'text-orange-600') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $payment['status'])) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="info-label">Uploaded On</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= date('M d, Y', strtotime($payment['uploaded_at'])) ?></p>
                                </div>
                            </div>
                            <?php
                                $receiptUrl  = getPresignedUrl($s3Client, $bucketName, $payment['receipt_file_path']);
                                $receiptName = uploadedFileDisplayName($payment['receipt_original_name'] ?? null, $payment['receipt_file_path']);
                            ?>
                            <a href="<?= htmlspecialchars($receiptUrl) ?>" target="_blank" class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left transition hover:border-[#1d5f8c] hover:bg-white">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-[#1d5f8c] shadow-sm"><i class="fa-solid fa-receipt"></i></span>
                                <span class="filename-cell min-w-0 flex-1">
                                    <span class="text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($receiptName) ?>"><?= htmlspecialchars($receiptName) ?></span>
                                    <span class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($payment['receipt_content_type'] ?? null, $receiptName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($payment['receipt_file_size'] ?? null)) ?></span>
                                </span>
                                <span class="shrink-0 text-xs font-bold text-[#1d5f8c]">View receipt <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></span>
                            </a>
                            <?php if ($isSurgicalGuide): ?>
                                <p class="mt-3 text-xs text-slate-500 italic">This receipt is shown for historical reference only. Manual receipts are disabled for Surgical Guide requests.</p>
                            <?php endif; ?>
                            <?php if ($payment['status'] === 'rejected' && $request['status'] === 'pending_payment' && !$isSurgicalGuide): ?>
                                <div class="mt-4 text-center">
                                    <p class="text-xs text-red-500 mb-2">Your previous receipt was rejected. Please upload a new one.</p>
                                    <a href="upload_receipt.php?id=<?= $request['id'] ?>" class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-orange-600 shadow-sm">Upload New Receipt</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-6 text-slate-500">
                                <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 mb-3"><i class="fa-solid fa-file-invoice text-xl"></i></div>
                                <?php if ($isSurgicalGuide): ?>
                                    <p class="text-sm font-semibold text-[#13324a]">Manual receipts are not used for this request.</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">Online payment will appear here after the payment gateway is connected.</p>
                                <?php else: ?>
                                    <p class="text-sm font-medium">No payment receipt uploaded yet.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- end main column -->

            <!-- ── Chat column (desktop sticky) ── -->
            <?php if ($isSurgicalGuide): ?>
            <div class="chat-column">
                <div id="chatColumnWrapper">
                    <?php include __DIR__ . '/includes/request_chat_panel.php'; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- end workspace-grid -->
    </div><!-- end page container -->

    <?php if ($isSurgicalGuide): ?>
    <!-- ══ Mobile Chat: FAB + Attention Bubble + Drawer ══ -->
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

    <!-- Backdrop -->
    <div id="mobileChatBackdrop" aria-hidden="true"></div>

    <!-- Drawer -->
    <div
        id="mobileChatDrawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mobileChatDrawerTitle"
        tabindex="-1">
        <!-- Drawer header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 shrink-0" style="border-radius:1.25rem 1.25rem 0 0;">
            <div class="flex items-center gap-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#1d5f8c] text-white"><i class="fa-regular fa-comments text-sm"></i></div>
                <div>
                    <h2 id="mobileChatDrawerTitle" class="text-sm font-bold text-[#13324a]">Conversation</h2>
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
        <!-- Chat panel moves here on mobile via JS -->
        <div id="mobileChatContent" class="flex flex-col flex-1 min-h-0 overflow-hidden"></div>
    </div>
    <?php endif; ?>

<script>
const requestWorkflowCsrfToken = <?= json_encode($request_workflow_csrf_token) ?>;
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
    article.className = `flex ${message.sender_role === 'clinic' ? 'justify-end' : 'justify-start'}`;
    const bubble = document.createElement('div');
    bubble.className = `max-w-[88%] rounded-2xl border px-4 py-3 ${message.sender_role === 'clinic' ? 'border-[#1d5f8c] bg-[#13324a] text-white' : 'border-slate-200 bg-white text-slate-700'}`;
    const meta = document.createElement('div');
    meta.className = `flex flex-wrap items-center gap-x-3 gap-y-1 text-xs ${message.sender_role === 'clinic' ? 'text-blue-100' : 'text-slate-400'}`;
    const sender = document.createElement('span');
    sender.className = 'font-bold';
    sender.textContent = `${message.sender_name} · ${message.sender_role === 'clinic' ? 'Clinic' : 'Admin'}`;
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
        const response = await fetch(`api/request_messages.php?request_id=<?= (int) $request['id'] ?>&after_id=${latestMessageId()}&csrf_token=${encodeURIComponent(requestWorkflowCsrfToken)}`, {headers: {'Accept': 'application/json'}});
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
        const response = await fetch('api/send_request_message.php', {
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

document.getElementById('approveReviewButton')?.addEventListener('click', async event => {
    if (!confirm('Approve the latest review and move this request to the payment stage? This confirms that you reviewed the latest files.')) return;
    const button = event.currentTarget;
    const statusBox = document.getElementById('reviewApprovalStatus');
    button.disabled = true;
    try {
        const response = await fetch('api/approve_review.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                request_id: <?= (int) $request['id'] ?>,
                package_id: Number(button.dataset.packageId),
                csrf_token: requestWorkflowCsrfToken
            })
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'The plan approval could not be saved.');
        statusBox.textContent = data.message;
        statusBox.className = 'mt-4 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800';
        statusBox.classList.remove('hidden');
        window.location.reload();
    } catch (error) {
        statusBox.textContent = error.message || 'The plan approval could not be saved.';
        statusBox.className = 'mt-4 rounded-xl border border-red-100 bg-red-50 p-3 text-sm font-semibold text-red-700';
        statusBox.classList.remove('hidden');
        button.disabled = false;
    }
});

/* ════════════════════════════════════
   Mobile Chat: FAB + Drawer logic
   Single chat panel element moves between desktop column and mobile drawer.
   No duplicate IDs or event listeners.
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

    /* ── Media query ── */
    const mq = window.matchMedia('(min-width: 1024px)');

    function placePanel() {
        if (mq.matches) {
            // Desktop: put panel back in desktop column
            if (desktopCol && chatPanel.parentElement !== desktopCol) {
                desktopCol.appendChild(chatPanel);
            }
        } else {
            // Mobile: move panel into drawer slot (only if drawer is open)
            if (drawerOpen && mobileSlot && chatPanel.parentElement !== mobileSlot) {
                mobileSlot.appendChild(chatPanel);
            }
        }
    }

    mq.addEventListener('change', () => {
        if (mq.matches && drawerOpen) closeDrawer(false);
        placePanel();
    });

    /* ── Open / Close drawer ── */
    function openDrawer() {
        if (drawerOpen) return;
        drawerOpen = true;

        // Move panel into drawer
        if (mobileSlot) mobileSlot.appendChild(chatPanel);

        // Show backdrop
        backdrop.classList.add('active');
        requestAnimationFrame(() => backdrop.classList.add('visible'));

        // Prevent background scroll
        document.body.style.overflow = 'hidden';

        // Open drawer
        drawer.classList.add('open');
        fab.setAttribute('aria-expanded', 'true');

        // Scroll to latest message
        setTimeout(() => {
            if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;
            drawer.focus();
        }, 350);

        // Stop attention bubble timers
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
            // Move panel back to desktop column (if desktop) or just leave in drawer slot
            if (desktopCol && mq.matches) desktopCol.appendChild(chatPanel);
        }, 350);

        if (returnFocus) fab.focus();
    }

    fab.addEventListener('click', openDrawer);
    closeBtn?.addEventListener('click', () => closeDrawer());
    backdrop.addEventListener('click', () => closeDrawer());

    // Escape key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && drawerOpen) closeDrawer();
    });

    /* ── Attention Bubble ── */
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
        // First show: 5-6 seconds
        const firstT = setTimeout(() => {
            showBubble();
            // Repeat every 25-30 seconds
            function repeat() {
                if (sessionStorage.getItem(SESSION_KEY)) return;
                const rptT = setTimeout(() => {
                    showBubble();
                    repeat();
                }, 25000 + Math.random() * 5000);
                bubbleTimers.push(rptT);
            }
            repeat();
        }, 5000 + Math.random() * 1000);
        bubbleTimers.push(firstT);
    }

    // Only on mobile
    if (!mq.matches) scheduleBubble();
    mq.addEventListener('change', () => {
        if (mq.matches) { stopBubbleTimers(); hideBubble(); }
        else if (!sessionStorage.getItem(SESSION_KEY)) scheduleBubble();
    });

    // Clean up on page leave
    window.addEventListener('pagehide', stopBubbleTimers);
    window.addEventListener('beforeunload', stopBubbleTimers);
})();
</script>
</body>
</html>
