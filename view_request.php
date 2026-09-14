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
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
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

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="flex items-center gap-4 mb-6">
            <a href="clinic_dashboard.php" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-[#13324a] hover:bg-slate-50 transition shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-[#13324a]">Request #<?= str_pad($request['id'], 5, '0', STR_PAD_LEFT) ?></h1>
                <p class="text-sm text-slate-500 mt-1">Submitted on <?= date('F j, Y', strtotime($request['created_at'])) ?></p>
            </div>
            <div class="ml-auto">
                <?= getStatusBadge($request['status']) ?>
            </div>
        </div>

        <?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
            <div class="mb-6 rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-extrabold mb-1"><i class="fa-solid fa-circle-exclamation mr-2"></i>Request Rejected</p>
                <p><?= nl2br(htmlspecialchars($request['rejection_reason'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($request['service_type'] === 'surgical_guide'): ?>
            <?php
                $guideSteps = ['pending_review', 'awaiting_clinic_approval', 'pending_payment', 'in_progress', 'completed'];
                $guideStepIndex = array_search($request['status'], $guideSteps, true);
                $guideStepIndex = $guideStepIndex === false ? -1 : $guideStepIndex;
            ?>
            <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Surgical Guide progress">
                <div class="mb-3 flex items-center justify-between gap-3"><h2 class="text-sm font-bold text-[#13324a]">Request progress</h2><span class="text-xs text-slate-400">Five protected stages</span></div>
                <div class="grid grid-cols-5 gap-1 text-center">
                    <?php foreach (['Admin review', 'Your approval', 'Payment', 'Production', 'Complete'] as $index => $label): ?>
                        <div><div class="h-2 rounded-full <?= $request['status'] !== 'rejected' && $index <= $guideStepIndex ? 'bg-[#1d5f8c]' : 'bg-slate-200' ?>"></div><span class="mt-1.5 block text-[10px] font-bold leading-tight <?= $request['status'] !== 'rejected' && $index <= $guideStepIndex ? 'text-[#1d5f8c]' : 'text-slate-400' ?>"><?= $label ?></span></div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <?php if($request['service_type'] == 'surgical_guide'): ?>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-500"><i class="fa-solid fa-layer-group"></i></div>
                            <h2 class="text-lg font-bold text-[#13324a]">Surgical Guide Details</h2>
                        <?php else: ?>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-500"><i class="fa-solid fa-user-doctor"></i></div>
                            <h2 class="text-lg font-bold text-[#13324a]">Surgeon Request Details</h2>
                        <?php endif; ?>
                    </div>
                    <?php if ($request['status'] === 'pending_payment' && $request['service_type'] !== 'surgical_guide'): ?>
                        <a href="upload_receipt.php?id=<?= $request['id'] ?>" class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-orange-600 shadow-sm">
                            <i class="fa-solid fa-upload mr-2"></i> Upload Receipt
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="p-6">
                    <?php if ($details): ?>
                        <?php if ($request['service_type'] === 'surgeon_request'): ?>
                            <?php require __DIR__ . '/includes/surgeon_request_details.php'; ?>
                        <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($details as $key => $value): ?>
                                <?php if (in_array($key, ['id', 'request_id', 'assigned_surgeon_id', 'surgeon_name'])) continue; ?>
                                <?php if ($request['service_type'] === 'surgical_guide' && !in_array($key, $guideDisplayFields, true)) continue; ?>
                                <?php if (str_starts_with($key, 'guided_kit_') && empty($details['guided_kit_source'])) continue; ?>
                                <?php if ($key === 'guided_kit_type' && ($details['guided_kit_source'] ?? '') !== 'owned') continue; ?>
                                <?php if ($key === 'guided_kit_rental_price' && ($details['guided_kit_source'] ?? '') !== 'rental') continue; ?>
                                
                                <div class="<?= in_array($key, ['notes', 'medical_history']) ? 'md:col-span-2' : '' ?>">
                                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2"><?= formatLabel($key) ?></h3>
                                    
                                    <?php if (in_array($key, ['cbct_file_path', 'stl_file_path'])): ?>
                                        <?php 
                                            // توليد رابط التحميل الآمن لملف الأشعة
                                            $downloadUrl = getPresignedUrl($s3Client, $bucketName, $value);
                                            $filePrefix = $key === 'cbct_file_path' ? 'cbct' : 'stl';
                                            $sourceFileName = uploadedFileDisplayName($details[$filePrefix . '_original_name'] ?? null, $value);
                                        ?>
                                        <div class="flex items-center gap-3 bg-slate-50 border border-slate-100 rounded-xl p-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white shadow-sm text-slate-400">
                                                <i class="fa-solid <?= $key === 'cbct_file_path' ? 'fa-x-ray' : 'fa-tooth' ?> text-lg"></i>
                                            </div>
                                            <div class="flex-1 overflow-hidden text-ellipsis whitespace-nowrap">
                                                <p class="text-sm font-semibold text-slate-700 truncate" title="<?= htmlspecialchars($sourceFileName) ?>"><?= htmlspecialchars($sourceFileName) ?></p>
                                                <p class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($details[$filePrefix . '_content_type'] ?? null, $sourceFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($details[$filePrefix . '_file_size'] ?? null)) ?></p>
                                                <a href="<?= htmlspecialchars($downloadUrl) ?>" target="_blank" class="text-xs font-bold text-[#1d5f8c] hover:underline">Download / View File</a>
                                            </div>
                                        </div>
                                    <?php elseif ($key === 'delivery_method'): ?>
                                        <div class="text-sm font-semibold text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-100">
                                            <?= $value === 'clinic_print' ? 'I will print it at my clinic' : 'Admin will print and deliver physical guide' ?>
                                        </div>
                                    <?php else: ?>
                                        <?php
                                            $displayValue = $value;
                                            if ($key === 'guided_kit_source') $displayValue = $value === 'rental' ? 'Rental from Easy Implant' : ($value === 'owned' ? 'Clinic-owned kit' : 'Not specified');
                                            if ($key === 'guided_kit_type') $displayValue = $value ? ucfirst($value) : 'Not applicable';
                                            if ($key === 'guided_kit_rental_price') $displayValue = formatMoney($value ?? 0);
                                        ?>
                                        <div class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-100 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars((string) ($displayValue ?: 'Not provided')) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($guideKitFiles): ?>
                                <div class="md:col-span-2">
                                    <h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Guided Kit Photos</h3>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <?php foreach ($guideKitFiles as $file): ?>
                                            <?php $kitFileName = uploadedFileDisplayName($file['original_name'] ?? null, $file['file_path']); ?>
                                            <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-[#13324a] transition hover:border-[#1d5f8c]">
                                                <span class="min-w-0"><span class="block truncate text-sm font-semibold" title="<?= htmlspecialchars($kitFileName) ?>"><i class="fa-regular fa-image mr-2 text-[#1d5f8c]"></i><?= htmlspecialchars($kitFileName) ?></span><span class="mt-1 block text-xs font-normal text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'] ?? null, $kitFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'] ?? null)) ?></span></span>
                                                <span class="shrink-0 text-xs font-bold text-[#1d5f8c]">View photo <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php elseif (($details['guided_kit_source'] ?? '') === 'owned'): ?>
                                <div class="md:col-span-2 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-3 text-sm text-slate-500">
                                    <i class="fa-regular fa-images mr-2 text-[#1d5f8c]"></i>No kit photos were attached. Photos are optional.
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($request['service_type'] === 'surgical_guide'): ?>
                        <section class="mt-8 border-t border-slate-100 pt-8" aria-labelledby="clinicReviewTitle">
                            <div class="mb-5 flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"><i class="fa-solid fa-file-circle-check"></i></div>
                                <div><h2 id="clinicReviewTitle" class="text-lg font-bold text-[#13324a]">Case review from Easy Implant</h2><p class="mt-1 text-sm text-slate-500">Review the explanation and every file in the latest round before approving the plan.</p></div>
                            </div>

                            <?php if (!$reviewPackages): ?>
                                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center"><i class="fa-regular fa-folder-open mb-3 text-2xl text-slate-300"></i><p class="text-sm font-semibold text-slate-600">No review has been sent yet.</p><p class="mt-1 text-xs text-slate-400">The administration is still reviewing your case and files.</p></div>
                            <?php else: ?>
                                <?php if ($request['status'] === 'awaiting_clinic_approval'): ?>
                                    <div class="mb-5 rounded-2xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900"><p class="font-bold"><i class="fa-solid fa-circle-info mr-2"></i>Your decision is needed on the latest review round.</p><p class="mt-1 text-xs leading-5 text-cyan-800">If you need changes, send your notes in the conversation below. The request remains in this stage until you approve.</p></div>
                                <?php elseif ($request['status'] === 'pending_payment'): ?>
                                    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"><p class="font-bold"><i class="fa-solid fa-circle-check mr-2"></i>The plan is approved.</p><p class="mt-1 text-xs leading-5">Online payment will become available after the payment gateway is connected. No receipt or payment is required here now.</p></div>
                                <?php endif; ?>

                                <div class="space-y-5">
                                    <?php foreach ($reviewPackages as $reviewIndex => $package): ?>
                                        <article class="overflow-hidden rounded-2xl border <?= $reviewIndex === 0 ? 'border-cyan-200 shadow-sm' : 'border-slate-200' ?> bg-white">
                                            <header class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between <?= $reviewIndex === 0 ? 'bg-cyan-50/60' : 'bg-slate-50' ?>">
                                                <div class="flex flex-wrap items-center gap-2"><h3 class="font-bold text-[#13324a]">Review round #<?= (int) $package['id'] ?></h3><?php if ($reviewIndex === 0): ?><span class="rounded-full bg-cyan-100 px-2.5 py-1 text-[11px] font-bold text-cyan-800">Latest review</span><?php endif; ?><?php if (!empty($package['approved_at'])): ?><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Approved</span><?php endif; ?></div>
                                                <time class="text-xs text-slate-500"><?= date('M d, Y, H:i', strtotime($package['sent_at'])) ?></time>
                                            </header>
                                            <div class="p-5">
                                                <?php if (!empty($package['summary'])): ?><div class="mb-5 whitespace-pre-wrap rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm leading-7 text-slate-700"><?= htmlspecialchars($package['summary']) ?></div><?php else: ?><p class="mb-5 text-sm text-slate-500">This round contains files without an additional written explanation.</p><?php endif; ?>
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <?php foreach ($package['files'] as $file): ?>
                                                        <?php
                                                            $reviewFileName = uploadedFileDisplayName($file['original_name'], $file['file_path']);
                                                            $reviewFileUrl = getPresignedUrl($s3Client, $bucketName, $file['file_path']);
                                                            $reviewContentType = strtolower((string) $file['content_type']);
                                                        ?>
                                                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                                            <?php if (str_starts_with($reviewContentType, 'image/')): ?>
                                                                <a href="<?= htmlspecialchars($reviewFileUrl) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars($reviewFileUrl) ?>" alt="<?= htmlspecialchars($reviewFileName) ?>" class="h-44 w-full bg-white object-contain" loading="lazy"></a>
                                                            <?php elseif (str_starts_with($reviewContentType, 'video/')): ?>
                                                                <video controls preload="metadata" class="h-44 w-full bg-slate-950 object-contain"><source src="<?= htmlspecialchars($reviewFileUrl) ?>" type="<?= htmlspecialchars($reviewContentType) ?>">Your browser cannot preview this video.</video>
                                                            <?php endif; ?>
                                                            <div class="flex min-w-0 items-center gap-3 p-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-cyan-700 shadow-sm"><i class="fa-solid fa-paperclip"></i></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($reviewFileName) ?>"><?= htmlspecialchars($reviewFileName) ?></span><span class="block text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'], $reviewFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'])) ?></span></span><a href="<?= htmlspecialchars($reviewFileUrl) ?>" target="_blank" rel="noopener" class="shrink-0 rounded-lg bg-white px-3 py-2 text-xs font-bold text-[#1d5f8c] shadow-sm hover:bg-blue-50">Open</a></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <?php if ($reviewIndex === 0 && $request['status'] === 'awaiting_clinic_approval' && empty($package['approved_at'])): ?>
                                                    <div class="mt-5 border-t border-slate-100 pt-5"><button type="button" id="approveReviewButton" data-package-id="<?= (int) $package['id'] ?>" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"><i class="fa-solid fa-check-double mr-2"></i>Approve the latest plan</button><p class="mt-2 text-center text-xs text-slate-500">You will confirm this decision before it is saved.</p></div>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div id="reviewApprovalStatus" class="mt-4 hidden rounded-xl border p-3 text-sm font-semibold" role="status"></div>
                        </section>
                        <?php endif; ?>

                        <!-- New Delivery Package from Admin -->
                        <?php if (!empty($deliverables)): ?>
                            <div class="mt-8 border-t border-slate-100 pt-8">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600"><i class="fa-solid fa-box-open"></i></div>
                                    <h2 class="text-lg font-bold text-[#13324a]">Delivery Package</h2>
                                </div>
                                
                                <div class="space-y-6">
                                    <?php
                                    $deliverable_meta = [
                                        'video' => ['title' => 'Plan Videos', 'icon' => 'fa-solid fa-video'],
                                        'instruction' => ['title' => 'Instructions & Sheets', 'icon' => 'fa-regular fa-file-lines'],
                                        'guide' => ['title' => 'Guide Files (STL)', 'icon' => 'fa-solid fa-cube'],
                                        'optional' => ['title' => 'Optional Files', 'icon' => 'fa-solid fa-paperclip'],
                                    ];
                                    ?>

                                    <?php foreach ($deliverable_meta as $type => $meta): ?>
                                        <?php if (!empty($deliverables[$type])): ?>
                                            <div>
                                                <h3 class="text-base font-bold text-[#13324a] mb-3"><?= $meta['title'] ?></h3>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                                    <?php foreach ($deliverables[$type] as $file): ?>
                                                        <?php $deliveryFileName = uploadedFileDisplayName($file['original_name'] ?? null, $file['file_path']); ?>
                                                        <a href="<?= htmlspecialchars(getPresignedUrl($s3Client, $bucketName, $file['file_path'])) ?>" target="_blank" class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-3 transition hover:border-[#1d5f8c] hover:shadow-sm group">
                                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 group-hover:bg-blue-50 group-hover:text-[#1d5f8c] transition">
                                                                <i class="<?= $meta['icon'] ?> text-lg"></i>
                                                            </div>
                                                            <div class="flex-1 overflow-hidden">
                                                                <p class="text-sm font-semibold text-slate-700 truncate" title="<?= htmlspecialchars($deliveryFileName) ?>"><?= htmlspecialchars($deliveryFileName) ?></p>
                                                                <p class="text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($file['content_type'] ?? null, $deliveryFileName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($file['file_size'] ?? null)) ?></p>
                                                                <span class="text-xs font-bold text-[#1d5f8c]">Download</span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif ($request['service_type'] === 'surgical_guide' && $request['status'] === 'completed'): ?>
                            <div class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                <p class="font-bold"><i class="fa-solid fa-circle-exclamation mr-2"></i>The delivery package is not available.</p>
                                <p class="mt-1 text-xs text-amber-700">Please contact the administration and mention request #<?= (int) $request['id'] ?>.</p>
                            </div>
                        <?php endif; ?>

                        <?php if($request['service_type'] == 'surgeon_request' && !empty($details['surgeon_name'])): ?>
                            <div class="mt-6 p-4 rounded-xl bg-teal-50 border border-teal-100 flex items-start gap-4">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white shadow-sm text-teal-600 text-lg">
                                    <i class="fa-solid fa-user-md"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-[#13324a]">Assigned Surgeon</h3>
                                    <p class="text-sm text-slate-600 mt-1">Dr. <?= htmlspecialchars($details['surgeon_name']) ?> has been assigned to this case.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <p class="text-slate-500 text-sm text-center py-8">No specific details found for this request.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($request['service_type'] === 'surgical_guide'): ?>
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="requestChatTitle">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div><h2 id="requestChatTitle" class="text-lg font-bold text-[#13324a]"><i class="fa-regular fa-comments mr-2 text-cyan-700"></i>Conversation with Easy Implant</h2><p class="mt-1 text-xs text-slate-500">Messages do not update automatically. Select “Refresh messages” to see new replies.</p></div>
                    <button type="button" id="refreshMessagesButton" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 transition hover:border-[#1d5f8c] hover:text-[#1d5f8c]"><i class="fa-solid fa-rotate mr-2"></i>Refresh messages</button>
                </div>
                <div id="requestChatStatus" class="mb-3 hidden rounded-xl p-3 text-sm font-semibold" role="status"></div>
                <div id="requestMessages" class="max-h-[32rem] space-y-3 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-4" aria-live="polite">
                    <?php if (!$requestMessages): ?><div id="requestMessagesEmpty" class="py-8 text-center text-sm text-slate-500"><i class="fa-regular fa-comment-dots mb-3 block text-2xl text-slate-300"></i>No messages yet. Send a note if you need clarification or changes.</div><?php endif; ?>
                    <?php foreach ($requestMessages as $message): ?>
                        <article data-message-id="<?= (int) $message['id'] ?>" class="flex <?= $message['sender_role'] === 'clinic' ? 'justify-end' : 'justify-start' ?>"><div class="max-w-[88%] rounded-2xl border px-4 py-3 <?= $message['sender_role'] === 'clinic' ? 'border-[#1d5f8c] bg-[#13324a] text-white' : 'border-slate-200 bg-white text-slate-700' ?>"><div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs <?= $message['sender_role'] === 'clinic' ? 'text-blue-100' : 'text-slate-400' ?>"><span class="font-bold"><?= htmlspecialchars($message['sender_name']) ?> · <?= $message['sender_role'] === 'clinic' ? 'Clinic' : 'Admin' ?></span><time><?= date('M d, Y, H:i', strtotime($message['created_at'])) ?></time></div><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6"><?= htmlspecialchars($message['message_text']) ?></p></div></article>
                    <?php endforeach; ?>
                </div>
                <?php if (surgicalGuideChatIsWritable($request['status'])): ?>
                <form id="requestMessageForm" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"><div class="flex-1"><label for="requestMessageText" class="mb-2 block text-sm font-bold text-[#13324a]">Your message</label><textarea id="requestMessageText" maxlength="<?= REQUEST_MESSAGE_MAX_LENGTH ?>" rows="3" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-[#1d5f8c] focus:ring-[#1d5f8c]" placeholder="Ask a question or explain the changes you need."></textarea></div><button id="sendMessageButton" type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#1d5f8c] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#13324a] disabled:opacity-60"><i class="fa-solid fa-paper-plane mr-2"></i>Send message</button></form>
                <?php else: ?><div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600"><i class="fa-solid fa-lock mr-2 text-slate-400"></i>This conversation is read-only because the request is completed or rejected.</div><?php endif; ?>
            </section>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h2 class="text-base font-bold text-[#13324a]"><i class="fa-solid fa-receipt text-slate-400 mr-2"></i> Payment Receipt</h2>
                    </div>
                    <div class="p-6">
                        <?php if ($payment): ?>
                            <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Status</p>
                                    <p class="text-sm font-bold <?= $payment['status'] === 'approved' ? 'text-emerald-600' : ($payment['status'] === 'rejected' ? 'text-red-600' : 'text-orange-600') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $payment['status'])) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Uploaded On</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= date('M d, Y', strtotime($payment['uploaded_at'])) ?></p>
                                </div>
                            </div>
                            
                            <?php 
                                // توليد رابط التحميل الآمن لإيصال الدفع
                                $receiptUrl = getPresignedUrl($s3Client, $bucketName, $payment['receipt_file_path']);
                                $receiptName = uploadedFileDisplayName($payment['receipt_original_name'] ?? null, $payment['receipt_file_path']);
                            ?>
                            <a href="<?= htmlspecialchars($receiptUrl) ?>" target="_blank" class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left transition hover:border-[#1d5f8c] hover:bg-white">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-[#1d5f8c] shadow-sm"><i class="fa-solid fa-receipt"></i></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-700" title="<?= htmlspecialchars($receiptName) ?>"><?= htmlspecialchars($receiptName) ?></span>
                                    <span class="mt-0.5 block text-xs text-slate-400"><?= htmlspecialchars(uploadedFileTypeLabel($payment['receipt_content_type'] ?? null, $receiptName)) ?> · <?= htmlspecialchars(uploadedFileSizeLabel($payment['receipt_file_size'] ?? null)) ?></span>
                                </span>
                                <span class="shrink-0 text-xs font-bold text-[#1d5f8c]">View receipt <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i></span>
                            </a>
                            
                            <?php if ($payment['status'] === 'rejected' && $request['status'] === 'pending_payment' && $request['service_type'] !== 'surgical_guide'): ?>
                                <div class="mt-4 text-center">
                                    <p class="text-xs text-red-500 mb-2">Your previous receipt was rejected. Please upload a new one.</p>
                                    <a href="upload_receipt.php?id=<?= $request['id'] ?>" class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-orange-600 shadow-sm">
                                        Upload New Receipt
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-6 text-slate-500">
                                <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 mb-3">
                                    <i class="fa-solid fa-file-invoice text-xl"></i>
                                </div>
                                <?php if ($request['service_type'] === 'surgical_guide'): ?>
                                    <p class="text-sm font-semibold text-[#13324a]">Manual receipts are not used for this request.</p><p class="mt-1 text-xs leading-5 text-slate-500">Online payment will appear here after the payment gateway is connected.</p>
                                <?php else: ?>
                                    <p class="text-sm font-medium">No payment receipt uploaded yet.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h2 class="text-base font-bold text-[#13324a]"><i class="fa-solid fa-comment-dots text-slate-400 mr-2"></i> Admin Notes</h2>
                    </div>
                    <div class="p-6">
                        <?php if (!empty($request['admin_notes'])): ?>
                            <div class="text-sm text-slate-700 bg-amber-50 p-4 rounded-xl border border-amber-100 whitespace-pre-wrap leading-relaxed">
                                <?= htmlspecialchars($request['admin_notes']) ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6 text-slate-500">
                                <i class="fa-regular fa-comment text-2xl text-slate-300 mb-3"></i>
                                <p class="text-sm font-medium">No notes from the administration yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

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
    chatStatus.className = `mb-3 rounded-xl border p-3 text-sm font-semibold ${isError ? 'border-red-100 bg-red-50 text-red-700' : 'border-cyan-100 bg-cyan-50 text-cyan-800'}`;
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

document.getElementById('refreshMessagesButton')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
        const response = await fetch(`api/request_messages.php?request_id=<?= (int) $request['id'] ?>&after_id=${latestMessageId()}&csrf_token=${encodeURIComponent(requestWorkflowCsrfToken)}`, {headers: {'Accept': 'application/json'}});
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
        window.location.reload();
    } catch (error) {
        statusBox.textContent = error.message || 'The plan approval could not be saved.';
        statusBox.className = 'mt-4 rounded-xl border border-red-100 bg-red-50 p-3 text-sm font-semibold text-red-700';
        button.disabled = false;
    }
});
</script>
</body>
</html>
