<?php
// upload_receipt.php
require_once 'includes/db_connect.php';

// Check if user is logged in and is a clinic
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    header("Location: login.php");
    exit;
}

$request_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$request_id) {
    die("Invalid request ID.");
}

// Verify this request belongs to this clinic AND is in 'pending_payment' status
try {
    $stmt = $pdo->prepare("
        SELECT r.service_type, r.status, sgd.total_price
        FROM requests r
        LEFT JOIN surgical_guide_details sgd ON sgd.request_id = r.id
        WHERE r.id = :id AND r.user_id = :user_id
    ");
    $stmt->execute([':id' => $request_id, ':user_id' => $_SESSION['user_id']]);
    $request = $stmt->fetch();

    if (!$request) {
        die("Request not found or unauthorized.");
    }

    if ($request['service_type'] === 'surgical_guide') {
        http_response_code(409);
        die("Manual payment receipts are disabled for Surgical Guide requests. Online payment will be available after the payment gateway is connected.");
    }

    if ($request['status'] !== 'pending_payment') {
        die("Payment is not currently pending for this request.");
    }
    
    // Check if a payment receipt is already uploaded and pending verification
    $stmt_pay = $pdo->prepare("SELECT id FROM payments WHERE request_id = :id AND status = 'pending_verification'");
    $stmt_pay->execute([':id' => $request_id]);
    if($stmt_pay->fetch()) {
        $already_uploaded = true;
    } else {
        $already_uploaded = false;
    }

} catch (\PDOException $e) {
    die("Database error.");
}

$full_name = $_SESSION['full_name'];
$clinic_name = $_SESSION['clinic_name'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Upload Receipt | Easy Implant</title>
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
                    <a href="clinic_dashboard.php" class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-slate-200 hover:text-[#13324a]">
                        <i class="fa-solid fa-arrow-left text-sm"></i>
                    </a>
                    <span class="font-bold text-[#13324a] text-lg hidden sm:block">Easy Implant</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <div class="mb-8 text-center sm:text-left flex items-center gap-4">
            <div class="hidden sm:flex h-16 w-16 items-center justify-center rounded-2xl bg-orange-50 text-orange-500">
                <i class="fa-solid fa-file-invoice-dollar text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[#13324a]">Upload Payment Receipt</h1>
                <p class="text-sm text-slate-500 mt-1">Request #<?= str_pad($request_id, 5, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <?php if($already_uploaded): ?>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 text-center">
                <div class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 mb-4">
                    <i class="fa-solid fa-check text-3xl"></i>
                </div>
                <h2 class="text-xl font-bold text-[#13324a] mb-2">Receipt Already Uploaded</h2>
                <p class="text-slate-500 text-sm mb-6">Your payment receipt is currently being verified by the administration team. Once approved, the request will move to "In Progress".</p>
                <a href="clinic_dashboard.php" class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-6 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-200">Return to Dashboard</a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-8">
                
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 mb-6">
                    <h3 class="text-sm font-bold text-[#13324a] mb-2">Payment Instructions</h3>
                    <?php if ($request['service_type'] === 'surgical_guide' && $request['total_price'] !== null): ?>
                        <div class="mb-3 rounded-lg bg-white border border-slate-200 px-4 py-3">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Required Amount</p>
                            <p class="text-2xl font-extrabold text-[#13324a] mt-1"><?= number_format((float) $request['total_price'], 2) ?> EGP</p>
                        </div>
                    <?php endif; ?>
                    <ul class="text-sm text-slate-600 space-y-1 list-disc list-inside ml-4">
                        <li>Please transfer the agreed amount to our official bank account or digital wallet.</li>
                        <li>Ensure the transfer reference includes your clinic name or Request ID.</li>
                        <li>Upload a clear image or PDF of the successful transfer receipt below.</li>
                    </ul>
                </div>

                <form id="receiptForm" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="request_id" value="<?= $request_id ?>">

                    <div id="errorMsg" class="hidden bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100"></div>
                    <div id="successMsg" class="hidden bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm font-medium border border-emerald-100"></div>

                    <div>
                        <label class="block text-sm font-semibold text-[#13324a] mb-2">Upload Receipt (Image / PDF)</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-xl hover:border-[#1d5f8c] transition bg-white relative">
                            <div class="space-y-2 text-center">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400"></i>
                                <div class="text-sm text-slate-600">
                                    <label for="receipt_file" class="relative cursor-pointer rounded-md font-bold text-[#1d5f8c] hover:text-[#13324a] focus-within:outline-none px-1">
                                        <span>Click to upload</span>
                                        <input id="receipt_file" name="receipt_file" type="file" required class="sr-only" accept="image/jpeg,image/png,image/webp,application/pdf">
                                    </label>
                                </div>
                                <p class="text-xs text-slate-500">JPG, PNG, WEBP, PDF up to 10MB</p>
                            </div>
                            <div id="fileNameDisplay" class="absolute bottom-2 inset-x-0 text-center text-xs font-bold text-[#1d5f8c]"></div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-[#13324a] mb-2">Transferred Amount (Optional)</label>
                        <input type="number" step="0.01" name="amount" value="<?= $request['service_type'] === 'surgical_guide' && $request['total_price'] !== null ? htmlspecialchars($request['total_price']) : '' ?>" class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition text-sm text-[#13324a]" placeholder="E.g., 500.00">
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <button type="submit" id="submitBtn" class="w-full flex items-center justify-center rounded-xl bg-orange-500 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-orange-600 shadow-sm disabled:opacity-70">
                            <i class="fa-solid fa-paper-plane mr-2"></i> Submit Receipt for Verification
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <script>
        const fileInput = document.getElementById('receipt_file');
        if(fileInput) {
            fileInput.addEventListener('change', function(e) {
                const fileNameDisplay = document.getElementById('fileNameDisplay');
                if(e.target.files[0]) {
                    fileNameDisplay.textContent = 'Selected: ' + e.target.files[0].name;
                } else {
                    fileNameDisplay.textContent = '';
                }
            });

            document.getElementById('receiptForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = e.target;
                const btn = document.getElementById('submitBtn');
                const errorMsg = document.getElementById('errorMsg');
                const successMsg = document.getElementById('successMsg');
                
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Uploading...';
                errorMsg.classList.add('hidden');
                successMsg.classList.add('hidden');

                try {
                    const response = await fetch('api/upload_receipt.php', {
                        method: 'POST',
                        body: new FormData(form)
                    });
                    
                    const data = await response.json();

                    if (response.ok) {
                        form.reset();
                        document.getElementById('fileNameDisplay').textContent = '';
                        successMsg.innerHTML = '<i class="fa-solid fa-check-circle mr-1"></i> ' + data.success;
                        successMsg.classList.remove('hidden');
                        setTimeout(() => { window.location.reload(); }, 2000);
                    } else {
                        errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> ' + (data.error || 'An error occurred.');
                        errorMsg.classList.remove('hidden');
                    }
                } catch (err) {
                    errorMsg.innerHTML = '<i class="fa-solid fa-wifi mr-1"></i> Network error. Please check your connection.';
                    errorMsg.classList.remove('hidden');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i> Submit Receipt for Verification';
                }
            });
        }
    </script>
</body>
</html>
