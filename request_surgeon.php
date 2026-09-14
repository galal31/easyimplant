<?php
require_once 'includes/db_connect.php';
require_once __DIR__ . '/includes/implant_types.php';
require_once __DIR__ . '/includes/locations.php';
require_once __DIR__ . '/includes/surgeon_services.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$clinicName = $_SESSION['clinic_name'];
$pageError = '';
$travelSetting = null;
$implantTypes = [];
$customServices = [];
$regularPackages = getSurgeonImplantPackages();
$allOnPackages = getSurgeonAllOnPackages();
$allOnPrices = [];

if (empty($_SESSION['surgeon_request_csrf_token'])) {
    $_SESSION['surgeon_request_csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $stmt = $pdo->prepare("SELECT country, governorate FROM users WHERE id = :id AND role = 'clinic'");
    $stmt->execute([':id' => $userId]);
    $clinic = $stmt->fetch();
    if (!$clinic || strtolower((string) $clinic['country']) !== 'egypt') {
        http_response_code(403);
        $pageError = 'Surgeon requests are currently available to clinics inside Egypt only.';
    } else {
        $settings = getSurgeonGovernorateSettings($pdo);
        $travelSetting = $settings[$clinic['governorate'] ?? ''] ?? null;
        if (!$travelSetting || !$travelSetting['is_available']) {
            $pageError = 'Surgeon service is not currently available for your clinic governorate. Please contact support.';
        } else {
            $implantTypes = getActiveImplantTypes($pdo);
            $customServices = getActiveSurgeonServices($pdo);
            $allOnPrices = getSurgeonAllOnPrices($pdo);
        }
    }
} catch (Throwable $e) {
    error_log('Surgeon Request Page Error: ' . $e->getMessage());
    $pageError = 'Could not load surgeon services. Please try again later.';
}

$implantPrices = [];
foreach ($implantTypes as $implantType) {
    $implantPrices[(string) $implantType['id']] = (float) $implantType['price'];
}
$regularPackageFees = [];
foreach ($regularPackages as $code => $package) {
    $regularPackageFees[$code] = (float) $package['doctor_fee_per_implant'];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Surgeon | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<nav class="sticky top-0 z-30 border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><div class="flex h-16 justify-between">
        <div class="flex items-center gap-3"><a href="clinic_dashboard.php" class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-slate-200 hover:text-[#13324a]"><i class="fa-solid fa-arrow-left text-sm"></i></a><span class="hidden text-lg font-bold text-[#13324a] sm:block">Easy Implant</span></div>
        <div class="hidden text-right sm:block self-center"><p class="text-sm font-bold leading-tight text-[#13324a]"><?= htmlspecialchars($fullName) ?></p><p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($clinicName) ?></p></div>
    </div></div>
</nav>

<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8 flex items-center gap-4">
        <div class="hidden h-16 w-16 items-center justify-center rounded-2xl bg-teal-50 text-[#2b8a9e] sm:flex"><i class="fa-solid fa-user-doctor text-2xl"></i></div>
        <div><h1 class="text-2xl font-bold text-[#13324a]">Request Implant Surgeon</h1><p class="mt-1 text-sm text-slate-500">Build the treatment plan and review its estimated price before submitting.</p></div>
    </div>

    <?php if ($pageError): ?>
        <div class="rounded-3xl border border-orange-200 bg-orange-50 p-8 text-center shadow-sm"><i class="fa-solid fa-circle-info mb-3 text-2xl text-orange-500"></i><p class="font-semibold text-orange-800"><?= htmlspecialchars($pageError) ?></p><a href="clinic_dashboard.php" class="mt-5 inline-flex rounded-xl bg-[#13324a] px-5 py-2.5 text-sm font-bold text-white">Back to Dashboard</a></div>
    <?php else: ?>
    <form id="surgeonForm" class="space-y-6">
        <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($_SESSION['surgeon_request_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <div id="errorMsg" class="hidden rounded-xl border border-red-100 bg-red-50 p-4 text-sm font-medium text-red-600"></div>
        <div id="successMsg" class="hidden rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-medium text-emerald-700"></div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-5 flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#1d5f8c]"><i class="fa-solid fa-stethoscope"></i></div><div><h2 class="font-bold text-[#13324a]">Surgical Service</h2><p class="text-xs text-slate-500">What service does the patient need?</p></div></div>
            <label class="mb-2 block text-sm font-semibold text-[#13324a]">Required Service</label>
            <select name="surgical_service" id="surgicalService" required class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a] focus:border-[#2b8a9e] focus:ring-2 focus:ring-[#2b8a9e]">
                <option value="">Select surgical service</option>
                <option value="dental_implant">Dental Implant — زرع أسنان</option>
                <?php foreach ($customServices as $service): ?><option value="custom:<?= (int) $service['id'] ?>"><?= htmlspecialchars($service['name']) ?></option><?php endforeach; ?>
            </select>
            <div id="quoteNotice" class="mt-4 hidden rounded-xl border border-orange-200 bg-orange-50 p-4 text-center font-bold text-orange-700" dir="rtl">سيتم الرد بعرض سعر</div>
        </section>

        <section id="implantSection" class="hidden rounded-3xl border border-teal-100 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-5"><h2 class="text-lg font-bold text-[#13324a]">Dental Implant Plan</h2><p class="mt-1 text-sm text-slate-500">Choose a regular implant package or plan each All-on arch separately.</p></div>
            <label class="mb-2 block text-sm font-semibold text-[#13324a]">Treatment Type</label>
            <select name="implant_package" id="implantPackage" disabled class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a] focus:border-[#2b8a9e] focus:ring-2 focus:ring-[#2b8a9e]">
                <option value="">Select treatment type</option>
                <?php foreach ($regularPackages as $code => $package): ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($package['label']) ?> — <?= number_format($package['doctor_fee_per_implant'], 0) ?> EGP / implant</option><?php endforeach; ?>
                <option value="all_on_arches">All-on-4 / All-on-6 A to Z — choose each arch</option>
            </select>

            <div id="regularFields" class="mt-5 hidden grid grid-cols-1 gap-5 rounded-2xl border border-slate-200 bg-slate-50 p-5 md:grid-cols-3">
                <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Implant Type</label><select name="implant_type_id" id="implantType" disabled class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a]"><option value="">Select implant type</option><?php foreach ($implantTypes as $type): ?><option value="<?= (int) $type['id'] ?>"><?= htmlspecialchars($type['name']) ?><?= $type['brand'] ? ' — ' . htmlspecialchars($type['brand']) : '' ?> (<?= number_format((float) $type['price'], 2) ?> EGP)</option><?php endforeach; ?></select></div>
                <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Number of Implants</label><input type="number" name="implant_count" id="implantCount" min="1" max="32" value="1" disabled class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a]"></div>
                <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Who provides the implants?</label><select name="implant_provider" id="implantProvider" disabled class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a]"><option value="">Select provider</option><option value="easy_implant">Easy Implant</option><option value="clinic">Clinic</option></select></div>
            </div>

            <div id="allOnFields" class="mt-5 hidden grid grid-cols-1 gap-5 lg:grid-cols-2">
                <?php foreach (['upper' => 'Upper Arch', 'lower' => 'Lower Arch'] as $arch => $archLabel): ?>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5" data-arch-card="<?= $arch ?>">
                        <div class="mb-4 flex items-center gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-[#2b8a9e] shadow-sm"><i class="fa-solid fa-teeth-open"></i></div><h3 class="font-bold text-[#13324a]"><?= $archLabel ?></h3></div>
                        <div class="space-y-4">
                            <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">All-on Package</label><select name="<?= $arch ?>_package" id="<?= $arch ?>Package" disabled class="arch-package block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a]"><option value="">No treatment for this arch</option><?php foreach ($allOnPackages as $code => $package): ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($package['label']) ?> — <?= (int) $package['implant_count'] ?> implants</option><?php endforeach; ?></select></div>
                            <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Implant Type</label><select name="<?= $arch ?>_implant_type_id" id="<?= $arch ?>ImplantType" disabled class="arch-detail block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a]"><option value="">Select implant type</option><?php foreach ($implantTypes as $type): ?><option value="<?= (int) $type['id'] ?>"><?= htmlspecialchars($type['name']) ?><?= $type['brand'] ? ' — ' . htmlspecialchars($type['brand']) : '' ?> (<?= number_format((float) $type['price'], 2) ?> EGP)</option><?php endforeach; ?></select></div>
                            <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Who provides this arch's implants?</label><select name="<?= $arch ?>_implant_provider" id="<?= $arch ?>ImplantProvider" disabled class="arch-detail block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a]"><option value="">Select provider</option><option value="easy_implant">Easy Implant</option><option value="clinic">Clinic</option></select></div>
                            <div id="<?= $arch ?>Summary" class="hidden rounded-xl border border-teal-100 bg-white p-3 text-xs text-slate-600"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="priceBreakdown" class="mt-5 hidden overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="grid grid-cols-2 gap-3 p-4 text-sm sm:grid-cols-4">
                    <div><p id="workLabel" class="text-xs text-slate-500">Professional work</p><p id="workTotal" class="mt-1 font-bold text-[#13324a]">0.00 EGP</p></div>
                    <div><p class="text-xs text-slate-500">Implants supplied by us</p><p id="implantCostTotal" class="mt-1 font-bold text-[#13324a]">0.00 EGP</p></div>
                    <div><p class="text-xs text-slate-500">Travel to <?= htmlspecialchars($travelSetting['label']) ?></p><p class="mt-1 font-bold text-[#13324a]"><?= number_format((float) $travelSetting['price'], 2) ?> EGP</p></div>
                    <div class="rounded-lg bg-[#13324a] px-3 py-2 text-white"><p class="text-xs text-slate-300">Estimated total</p><p id="estimatedTotal" class="mt-1 text-base font-extrabold">0.00 EGP</p></div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="mb-5 text-lg font-bold text-[#13324a]">Patient Details</h2>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Patient Name</label><input type="text" name="patient_name" maxlength="100" required class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-[#13324a]"></div>
                <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Patient Age</label><input type="number" name="patient_age" required min="1" max="120" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-[#13324a]"></div>
                <div class="md:col-span-2"><label class="mb-2 block text-sm font-semibold text-[#13324a]">Medical History & Considerations</label><textarea name="medical_history" required rows="3" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-[#13324a]"></textarea></div>
                <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Proposed Operation Date</label><input type="date" name="proposed_date" required min="<?= date('Y-m-d') ?>" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a]"></div>
                <div><label class="mb-2 block text-sm font-semibold text-[#13324a]">Additional Notes</label><textarea name="notes" rows="3" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-[#13324a]"></textarea></div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-5"><h2 class="text-lg font-bold text-[#13324a]">Optional Clinical Files</h2><p class="mt-1 text-sm text-slate-500">Files upload directly from your browser to Cloudflare R2. They do not pass through the application server.</p></div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <?php foreach (['cbct' => ['CBCT Files', 'fa-x-ray', '.zip,.rar,.7z,.dcm'], 'lab' => ['Lab Results', 'fa-flask-vial', '.pdf,.jpg,.jpeg,.png,.webp,.zip,.rar,.7z']] as $category => $meta): ?>
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-start gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-[#1d5f8c] shadow-sm"><i class="fa-solid <?= $meta[1] ?>"></i></div><div><h3 class="font-bold text-[#13324a]"><?= $meta[0] ?></h3><p class="text-xs text-slate-500">Optional — multiple large files supported.</p></div></div>
                        <label class="mt-4 inline-flex cursor-pointer items-center rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#1d5f8c] shadow-sm ring-1 ring-slate-200 transition hover:ring-[#1d5f8c]"><i class="fa-solid fa-cloud-arrow-up mr-2"></i>Choose Files<input type="file" id="<?= $category ?>Files" name="<?= $category ?>_files" multiple accept="<?= $meta[2] ?>" class="sr-only"></label>
                        <div id="<?= $category ?>FileList" class="mt-4 space-y-2"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="flex justify-end gap-3"><a href="clinic_dashboard.php" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-600">Cancel</a><button type="submit" id="submitBtn" class="inline-flex items-center justify-center rounded-xl bg-[#2b8a9e] px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#1e697a] disabled:opacity-70">Submit Request</button></div>
    </form>
    <?php endif; ?>
</main>

<?php if (!$pageError): ?>
<script>
const implantPrices = <?= json_encode($implantPrices, JSON_UNESCAPED_SLASHES) ?>;
const regularPackageFees = <?= json_encode($regularPackageFees, JSON_UNESCAPED_SLASHES) ?>;
const allOnPackages = <?= json_encode($allOnPackages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const allOnPrices = <?= json_encode($allOnPrices, JSON_UNESCAPED_SLASHES) ?>;
const travelPrice = <?= json_encode((float) $travelSetting['price']) ?>;
const form = document.getElementById('surgeonForm');
const serviceSelect = document.getElementById('surgicalService');
const implantSection = document.getElementById('implantSection');
const implantPackage = document.getElementById('implantPackage');
const regularFields = document.getElementById('regularFields');
const allOnFields = document.getElementById('allOnFields');
const regularInputs = [document.getElementById('implantType'), document.getElementById('implantCount'), document.getElementById('implantProvider')];
const archNames = ['upper', 'lower'];

function money(value) { return Number(value).toLocaleString('en-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' EGP'; }
function setRequiredEnabled(element, enabled) { element.disabled = !enabled; element.required = enabled; }

function syncArch(arch) {
    const packageSelect = document.getElementById(arch + 'Package');
    const enabled = !packageSelect.disabled && packageSelect.value !== '';
    document.querySelectorAll(`[data-arch-card="${arch}"] .arch-detail`).forEach(field => setRequiredEnabled(field, enabled));
    updatePrice();
}

function syncPlanFields() {
    const dentalImplant = serviceSelect.value === 'dental_implant';
    const regular = dentalImplant && Object.prototype.hasOwnProperty.call(regularPackageFees, implantPackage.value);
    const allOn = dentalImplant && implantPackage.value === 'all_on_arches';
    implantSection.classList.toggle('hidden', !dentalImplant);
    document.getElementById('quoteNotice').classList.toggle('hidden', !serviceSelect.value.startsWith('custom:'));
    setRequiredEnabled(implantPackage, dentalImplant);
    regularFields.classList.toggle('hidden', !regular);
    allOnFields.classList.toggle('hidden', !allOn);
    regularInputs.forEach(field => setRequiredEnabled(field, regular));
    archNames.forEach(arch => {
        const packageSelect = document.getElementById(arch + 'Package');
        packageSelect.disabled = !allOn;
        if (!allOn) packageSelect.required = false;
        syncArch(arch);
    });
    updatePrice();
}

function updatePrice() {
    const breakdown = document.getElementById('priceBreakdown');
    if (serviceSelect.value !== 'dental_implant') { breakdown.classList.add('hidden'); return; }
    let workTotal = 0, implantTotal = 0, ready = false, incomplete = false;
    if (Object.prototype.hasOwnProperty.call(regularPackageFees, implantPackage.value)) {
        const count = Number.parseInt(document.getElementById('implantCount').value, 10);
        const typeId = document.getElementById('implantType').value;
        const provider = document.getElementById('implantProvider').value;
        ready = Number.isInteger(count) && count >= 1 && count <= 32 && Number.isFinite(implantPrices[typeId]) && provider !== '';
        if (ready) {
            workTotal = count * regularPackageFees[implantPackage.value];
            implantTotal = provider === 'easy_implant' ? count * implantPrices[typeId] : 0;
            document.getElementById('workLabel').textContent = 'Surgeon work';
        }
    } else if (implantPackage.value === 'all_on_arches') {
        let selectedArches = 0;
        archNames.forEach(arch => {
            const packageCode = document.getElementById(arch + 'Package').value;
            const summary = document.getElementById(arch + 'Summary');
            if (!packageCode) { summary.classList.add('hidden'); return; }
            selectedArches++;
            const typeId = document.getElementById(arch + 'ImplantType').value;
            const provider = document.getElementById(arch + 'ImplantProvider').value;
            if (!allOnPackages[packageCode] || !Number.isFinite(implantPrices[typeId]) || !provider) { incomplete = true; summary.classList.add('hidden'); return; }
            const count = Number(allOnPackages[packageCode].implant_count);
            const teamFee = Number(allOnPrices[packageCode]);
            const implantCost = provider === 'easy_implant' ? count * implantPrices[typeId] : 0;
            workTotal += teamFee; implantTotal += implantCost;
            summary.textContent = `${count} implants · Team ${money(teamFee)} · Implants ${money(implantCost)}`;
            summary.classList.remove('hidden');
        });
        ready = selectedArches > 0 && !incomplete;
        document.getElementById('workLabel').textContent = 'Team work for selected arches';
    }
    breakdown.classList.toggle('hidden', !ready);
    if (!ready) return;
    document.getElementById('workTotal').textContent = money(workTotal);
    document.getElementById('implantCostTotal').textContent = money(implantTotal);
    document.getElementById('estimatedTotal').textContent = money(workTotal + implantTotal + travelPrice);
}

serviceSelect.addEventListener('change', syncPlanFields);
implantPackage.addEventListener('change', syncPlanFields);
regularInputs.forEach(field => field.addEventListener('input', updatePrice));
archNames.forEach(arch => {
    document.getElementById(arch + 'Package').addEventListener('change', () => syncArch(arch));
    document.getElementById(arch + 'ImplantType').addEventListener('change', updatePrice);
    document.getElementById(arch + 'ImplantProvider').addEventListener('change', updatePrice);
});
syncPlanFields();

function showSelectedFiles(category) {
    const input = document.getElementById(category + 'Files');
    const list = document.getElementById(category + 'FileList');
    list.innerHTML = '';
    Array.from(input.files).forEach((file, index) => {
        const row = document.createElement('div');
        row.className = 'rounded-lg border border-slate-200 bg-white p-3';
        row.innerHTML = `<div class="flex justify-between gap-3 text-xs"><span class="truncate font-semibold text-slate-700"></span><span class="shrink-0 text-slate-400">${(file.size / 1024 / 1024).toFixed(1)} MB</span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-0 rounded-full bg-[#2b8a9e]" data-progress></div></div><p class="mt-1 text-[11px] text-slate-400" data-status>Ready to upload</p>`;
        row.querySelector('span').textContent = file.name;
        row.dataset.fileIndex = String(index);
        list.appendChild(row);
    });
}
['cbct', 'lab'].forEach(category => document.getElementById(category + 'Files').addEventListener('change', () => showSelectedFiles(category)));

async function uploadFile(file, category, row) {
    const csrfToken = document.getElementById('csrfToken').value;
    const response = await fetch('api/generate_surgeon_upload_url.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({csrf_token: csrfToken, category, filename: file.name, content_type: file.type || 'application/octet-stream', file_size: file.size})});
    const data = await response.json();
    if (!response.ok || !data.presigned_url) throw new Error(data.error || `Could not prepare ${file.name} for upload.`);
    const progress = row.querySelector('[data-progress]');
    const status = row.querySelector('[data-status]');
    status.textContent = 'Uploading directly to Cloudflare R2...';
    try {
        await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', data.presigned_url, true);
            xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
            xhr.upload.onprogress = event => { if (event.lengthComputable) progress.style.width = Math.round(event.loaded / event.total * 100) + '%'; };
            xhr.onload = () => xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`Upload failed for ${file.name}.`));
            xhr.onerror = () => reject(new Error(`Network error while uploading ${file.name}.`));
            xhr.send(file);
        });
    } catch (error) {
        await cleanupUploads([{file_path: data.object_key}]);
        throw error;
    }
    progress.style.width = '100%'; status.textContent = 'Uploaded'; status.className = 'mt-1 text-[11px] font-semibold text-emerald-600';
    return {file_path: data.object_key, file_category: category, original_name: file.name, content_type: file.type || 'application/octet-stream', file_size: file.size};
}

async function cleanupUploads(files) {
    const keys = files.map(file => file.file_path);
    if (!keys.length) return;
    try { await fetch('api/cleanup_surgeon_uploads.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({csrf_token: document.getElementById('csrfToken').value, keys})}); } catch (_) {}
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    const errorMsg = document.getElementById('errorMsg');
    const successMsg = document.getElementById('successMsg');
    const button = document.getElementById('submitBtn');
    if (implantPackage.value === 'all_on_arches' && !archNames.some(arch => document.getElementById(arch + 'Package').value)) { errorMsg.textContent = 'Choose at least one arch for the All-on treatment.'; errorMsg.classList.remove('hidden'); return; }
    let uploadedFiles = [];
    button.disabled = true; errorMsg.classList.add('hidden'); successMsg.classList.add('hidden');
    try {
        const totalFiles = document.getElementById('cbctFiles').files.length + document.getElementById('labFiles').files.length;
        if (totalFiles > 40) throw new Error('You can upload up to 40 files per request.');
        button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Uploading files directly to R2...';
        for (const category of ['cbct', 'lab']) {
            const input = document.getElementById(category + 'Files');
            const rows = document.querySelectorAll(`#${category}FileList [data-file-index]`);
            for (let index = 0; index < input.files.length; index++) uploadedFiles.push(await uploadFile(input.files[index], category, rows[index]));
        }
        button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Saving request...';
        const formData = new FormData(form);
        formData.delete('cbct_files'); formData.delete('lab_files');
        formData.append('surgeon_files', JSON.stringify(uploadedFiles));
        const response = await fetch('api/submit_surgeon.php', {method: 'POST', body: formData});
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not save the request.');
        successMsg.textContent = data.success; successMsg.classList.remove('hidden'); uploadedFiles = [];
        setTimeout(() => { window.location.href = 'view_request.php?id=' + data.request_id; }, 1200);
    } catch (error) {
        await cleanupUploads(uploadedFiles);
        errorMsg.textContent = error.message; errorMsg.classList.remove('hidden');
    } finally {
        button.disabled = false; button.textContent = 'Submit Request';
    }
});
</script>
<?php endif; ?>
</body>
</html>
