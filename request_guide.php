<?php
// request_guide.php
require_once 'includes/db_connect.php';
require_once 'includes/surgical_guide_pricing.php';
require_once 'includes/implant_types.php';
require_once 'includes/surgical_guide_kits.php';

// Check if user is logged in and is a clinic
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    header("Location: login.php");
    exit;
}

$full_name = $_SESSION['full_name'];
$clinic_name = $_SESSION['clinic_name'];
$pricing = getSurgicalGuidePricing($pdo);
$pricing = getClinicSurgicalGuideQuote($pdo, (int) $_SESSION['user_id'], $pricing);
$previous_implants = (int) $pricing['clinic_free_progress'];
$implant_types = getActiveImplantTypes($pdo);
$guided_kit_options = getActiveSurgicalGuideKitOptions($pdo);
$minimum_operation_date = getMinimumGuideOperationDate();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Request Surgical Guide | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Outfit', 'Cairo', sans-serif;
            background: #f4f8fb;
        }
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
                <div class="flex items-center gap-4">
                    <div class="hidden sm:block text-right">
                        <p class="text-sm font-bold text-[#13324a] leading-tight"><?= htmlspecialchars($full_name) ?></p>
                        <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($clinic_name) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-8 text-center sm:text-left flex items-center gap-4">
            <div class="hidden sm:flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-[#1d5f8c]">
                <i class="fa-solid fa-layer-group text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[#13324a]">Request Surgical Guide</h1>
                <p class="text-sm text-slate-500 mt-1">Submit case details and upload scans to begin the planning process.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <form id="guideForm" enctype="multipart/form-data" class="space-y-6">
                <div id="errorMsg" class="hidden bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100"></div>
                <div id="successMsg" class="hidden bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm font-medium border border-emerald-100"></div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-[#13324a] mb-2">Type of Implant to be used</label>
                        <select id="implantTypeSelect" name="implant_type_id" required class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a] bg-white">
                            <option value="">Select implant type</option>
                            <?php foreach ($implant_types as $type): ?>
                                <option value="<?= (int) $type['id'] ?>"><?= htmlspecialchars($type['name'] . (!empty($type['brand']) ? ' - ' . $type['brand'] : '')) ?></option>
                            <?php endforeach; ?>
                            <option value="other">Other</option>
                        </select>
                        <input type="text" id="implantTypeOther" name="implant_type_other" class="hidden mt-3 block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="Write implant type name">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-[#13324a] mb-2">Planned Operation Date</label>
                        <input type="date" name="operation_date" min="<?= htmlspecialchars($minimum_operation_date) ?>" required class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a] bg-white">
                        <p class="text-xs text-slate-500 mt-1">The operation date must be at least two days after submitting the request.</p>
                    </div>

                    <div id="guidedKitSection" class="xl:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-5 opacity-60">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-[#13324a]">Guided Kit</label>
                            <p class="mt-1 text-xs text-slate-500">Select the implant type first, then tell us which guided kit will be used.</p>
                        </div>
                        <fieldset id="guidedKitFields" disabled>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="cursor-pointer rounded-xl border border-slate-200 bg-white p-4 transition hover:border-[#1d5f8c]">
                                    <div class="flex items-start gap-3">
                                        <input type="radio" name="guided_kit_source" value="rental" required class="mt-1 h-4 w-4 text-[#1d5f8c] focus:ring-[#1d5f8c]">
                                        <div>
                                            <p class="text-sm font-bold text-[#13324a]">Rent a guided kit</p>
                                            <p class="mt-1 text-xs text-slate-500">Choose one of the kits available from Easy Implant.</p>
                                        </div>
                                    </div>
                                </label>
                                <label class="cursor-pointer rounded-xl border border-slate-200 bg-white p-4 transition hover:border-[#1d5f8c]">
                                    <div class="flex items-start gap-3">
                                        <input type="radio" name="guided_kit_source" value="owned" required class="mt-1 h-4 w-4 text-[#1d5f8c] focus:ring-[#1d5f8c]">
                                        <div>
                                            <p class="text-sm font-bold text-[#13324a]">I have my own kit</p>
                                            <p class="mt-1 text-xs text-slate-500">Enter its name and whether it is sleeved or sleeveless.</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div id="guidedKitRentalFields" class="mt-4 hidden">
                                <label class="mb-2 block text-sm font-semibold text-[#13324a]">Available rental kit</label>
                                <select id="guidedKitOption" name="guided_kit_option_id" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]">
                                    <option value="">Select a guided kit</option>
                                    <?php foreach ($guided_kit_options as $kit): ?>
                                        <option value="<?= (int) $kit['id'] ?>" data-price="<?= htmlspecialchars((string) $kit['rental_price']) ?>"><?= htmlspecialchars($kit['name']) ?> — <?= formatMoney($kit['rental_price']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$guided_kit_options): ?>
                                    <p class="mt-2 text-xs font-semibold text-amber-600">No rental kits are available right now. Please choose “I have my own kit” or contact Easy Implant.</p>
                                <?php endif; ?>
                            </div>

                            <div id="guidedKitOwnedFields" class="mt-4 hidden space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-[#13324a]">Kit name</label>
                                        <input id="guidedKitName" type="text" name="guided_kit_name" maxlength="190" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]" placeholder="Enter the guided kit name">
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-[#13324a]">Kit type</label>
                                        <select id="guidedKitType" name="guided_kit_type" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]">
                                            <option value="">Select kit type</option>
                                            <option value="sleeved">Sleeved</option>
                                            <option value="sleeveless">Sleeveless</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-[#13324a]">Kit photos <span class="font-normal text-slate-400">(Optional)</span></label>
                                    <input id="guidedKitImages" type="file" name="guided_kit_images[]" accept="image/jpeg,image/png,image/webp" multiple class="block w-full cursor-pointer rounded-xl border border-slate-200 bg-white text-sm text-slate-500 file:mr-4 file:border-0 file:bg-blue-50 file:px-4 file:py-3 file:text-sm file:font-semibold file:text-[#1d5f8c] hover:file:bg-blue-100">
                                    <p class="mt-2 text-xs leading-relaxed text-slate-500">Upload clear photos of the guided kit and the components that will be used with the guide. For a Sleeved kit, include the sleeve and any drill keys/components if available. JPG, PNG, or WebP; up to 8 images and 10 MB each.</p>
                                    <div id="guidedKitImageNames" class="mt-2 text-xs font-semibold text-[#1d5f8c]"></div>
                                    <div id="guidedKitUploadStatus" class="mt-2 hidden text-xs font-bold text-[#13324a]"></div>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="xl:col-span-2 bg-white border border-slate-200 rounded-2xl p-5">
                        <div class="mb-5">
                            <div>
                                <label class="block text-sm font-semibold text-[#13324a]">Implant Locations</label>
                                <p class="text-xs text-slate-500 mt-1">Enter the number of implants needed in each area.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Upper Arch</h3>
                                <div class="space-y-3">
                                    <?php foreach (GUIDE_UPPER_REGIONS as $region): ?>
                                    <label class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <span class="text-sm font-semibold text-slate-700"><?= GUIDE_REGION_LABELS[$region] ?></span>
                                        <input type="number" min="0" max="32" step="1" value="0" name="<?= $region ?>" data-guide-count class="w-20 rounded-lg border border-slate-200 bg-white px-3 py-2 text-center text-sm font-bold text-[#13324a] focus:border-[#1d5f8c] focus:ring-[#1d5f8c]">
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Lower Arch</h3>
                                <div class="space-y-3">
                                    <?php foreach (GUIDE_LOWER_REGIONS as $region): ?>
                                    <label class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <span class="text-sm font-semibold text-slate-700"><?= GUIDE_REGION_LABELS[$region] ?></span>
                                        <input type="number" min="0" max="32" step="1" value="0" name="<?= $region ?>" data-guide-count class="w-20 rounded-lg border border-slate-200 bg-white px-3 py-2 text-center text-sm font-bold text-[#13324a] focus:border-[#1d5f8c] focus:ring-[#1d5f8c]">
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="xl:col-span-2 bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <label class="block text-sm font-semibold text-[#13324a] mb-4">Delivery Method</label>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:border-[#1d5f8c] bg-white transition">
                                <input type="radio" name="delivery_method" value="clinic_print" required class="mt-1 w-4 h-4 text-[#1d5f8c] focus:ring-[#1d5f8c]">
                                <div>
                                    <p class="text-sm font-bold text-[#13324a]">I will print it at my clinic</p>
                                    <p class="text-xs text-slate-500 mt-1">Admin will upload the Soft Guide (STL), Plan Video, and Instructions.</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:border-[#1d5f8c] bg-white transition">
                                <input type="radio" name="delivery_method" value="admin_print" required class="mt-1 w-4 h-4 text-[#1d5f8c] focus:ring-[#1d5f8c]">
                                <div>
                                    <p class="text-sm font-bold text-[#13324a]">Admin will print & deliver</p>
                                    <p class="text-xs text-slate-500 mt-1">Admin will upload the Plan Video and Instructions. Physical guide will be delivered.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="xl:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-[#13324a] mb-2">Upload CBCT Scan (DICOM/ZIP)</label>
                            <div class="mt-1 flex justify-center px-4 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-xl hover:border-[#1d5f8c] transition bg-slate-50 relative">
                                <div class="space-y-2 text-center">
                                    <i class="fa-solid fa-x-ray text-3xl text-slate-400"></i>
                                    <div class="text-sm text-slate-600">
                                        <label for="cbct_file" class="relative cursor-pointer bg-white rounded-md font-bold text-[#1d5f8c] hover:text-[#13324a] px-1">
                                            <span>Browse CBCT</span>
                                            <input id="cbct_file" name="cbct_file" type="file" required class="sr-only" accept=".zip,.rar,.dcm">
                                        </label>
                                    </div>
                                </div>
                                <div id="cbctFileName" class="absolute bottom-2 inset-x-0 text-center text-xs font-bold text-[#1d5f8c]"></div>
                            </div>

                            <div id="cbctProgressContainer" class="hidden mt-3">
                                <div class="flex justify-between text-xs font-bold text-[#13324a] mb-1"><span>Uploading CBCT...</span><span id="cbctProgressText">0%</span></div>
                                <div class="w-full bg-slate-200 rounded-full h-2">
                                    <div id="cbctProgressBar" class="bg-[#1d5f8c] h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-[#13324a] mb-2">Upload Intraoral Scan (STL/ZIP)</label>
                            <div class="mt-1 flex justify-center px-4 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-xl hover:border-[#1d5f8c] transition bg-slate-50 relative">
                                <div class="space-y-2 text-center">
                                    <i class="fa-solid fa-tooth text-3xl text-slate-400"></i>
                                    <div class="text-sm text-slate-600">
                                        <label for="stl_file" class="relative cursor-pointer bg-white rounded-md font-bold text-[#1d5f8c] hover:text-[#13324a] px-1">
                                            <span>Browse STL</span>
                                            <input id="stl_file" name="stl_file" type="file" required class="sr-only" accept=".zip,.rar,.stl">
                                        </label>
                                    </div>
                                </div>
                                <div id="stlFileName" class="absolute bottom-2 inset-x-0 text-center text-xs font-bold text-[#1d5f8c]"></div>
                            </div>

                            <div id="stlProgressContainer" class="hidden mt-3">
                                <div class="flex justify-between text-xs font-bold text-[#13324a] mb-1"><span>Uploading STL...</span><span id="stlProgressText">0%</span></div>
                                <div class="w-full bg-slate-200 rounded-full h-2">
                                    <div id="stlProgressBar" class="bg-[#1d5f8c] h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label class="block text-sm font-semibold text-[#13324a] mb-2">Additional Notes (Optional)</label>
                        <textarea name="notes" rows="3" class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="Any extra information for the planning team..."></textarea>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                    <a href="clinic_dashboard.php" class="px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</a>
                    <button type="submit" id="submitBtn" class="inline-flex items-center justify-center rounded-xl bg-[#1d5f8c] px-6 py-2.5 text-sm font-bold text-white transition hover:bg-[#13324a] shadow-sm disabled:opacity-70">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>

        <aside class="lg:sticky lg:top-24 space-y-4">
            <div class="bg-[#13324a] text-white rounded-3xl p-6 shadow-sm">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-100">Estimated Total</p>
                    <p id="estimatedTotal" class="text-4xl font-extrabold mt-2 leading-tight">0.00 EGP</p>
                </div>

                <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-2xl bg-white/10 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-blue-100">Protected Progress</p>
                        <p id="previousImplants" class="text-lg font-extrabold mt-1"><?= (int) $previous_implants ?></p>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-blue-100">Current</p>
                        <p id="currentImplants" class="text-lg font-extrabold mt-1">0</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-blue-100">Free</p>
                        <p id="freeImplants" class="text-lg font-extrabold mt-1">0</p>
                    </div>
                </div>

                <div id="priceBreakdown" class="mt-5 space-y-2 text-sm text-blue-50"></div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h2 class="text-sm font-bold text-[#13324a] mb-3">Pricing Rule</h2>
                <div class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between gap-3"><span>Clinic print first implant</span><span class="font-bold text-[#13324a]"><?= formatMoney($pricing['clinic_print_first_implant_price']) ?></span></div>
                    <div class="flex justify-between gap-3"><span>Admin print first implant</span><span class="font-bold text-[#13324a]"><?= formatMoney($pricing['admin_print_first_implant_price']) ?></span></div>
                    <div class="flex justify-between gap-3"><span>Additional implant</span><span class="font-bold text-[#13324a]"><?= formatMoney($pricing['additional_implant_price']) ?></span></div>
                    <div class="flex justify-between gap-3"><span>Your protected rule</span><span class="font-bold text-[#13324a]">Every <?= (int) $pricing['clinic_free_implant_every'] ?></span></div>
                    <div class="flex justify-between gap-3"><span>Current progress</span><span class="font-bold text-[#13324a]"><?= (int) $pricing['clinic_free_progress'] ?> / <?= (int) $pricing['clinic_free_implant_every'] ?></span></div>
                    <div class="flex justify-between gap-3"><span>Next-cycle rule</span><span class="font-bold text-[#13324a]">Every <?= (int) $pricing['next_free_implant_every'] ?></span></div>
                </div>
            </div>
        </aside>
        </div>

    </div>

    <script>
        const guidePricing = <?= json_encode($pricing) ?>;
        const loadedPricingVersion = <?= json_encode($pricing['version']) ?>;
        const upperRegions = <?= json_encode(GUIDE_UPPER_REGIONS) ?>;
        const lowerRegions = <?= json_encode(GUIDE_LOWER_REGIONS) ?>;

        function money(amount) {
            return Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EGP';
        }

        function getRegionTotal(regions) {
            return regions.reduce((sum, name) => {
                const input = document.querySelector(`[name="${name}"]`);
                return sum + Math.max(0, parseInt(input?.value || '0', 10));
            }, 0);
        }

        function calculateEstimate() {
            const upperCount = getRegionTotal(upperRegions);
            const lowerCount = getRegionTotal(lowerRegions);
            const totalImplants = upperCount + lowerCount;
            const deliveryMethod = document.querySelector('[name="delivery_method"]:checked')?.value || 'clinic_print';
            const firstPrice = deliveryMethod === 'admin_print'
                ? Number(guidePricing.admin_print_first_implant_price || 1700)
                : Number(guidePricing.clinic_print_first_implant_price || guidePricing.first_implant_price || 1300);
            const additionalPrice = Number(guidePricing.additional_implant_price || 0);
            const printFee = 0;
            let activeFreeEvery = Math.max(1, parseInt(guidePricing.clinic_free_implant_every, 10));
            const nextFreeEvery = Math.max(1, parseInt(guidePricing.next_free_implant_every, 10));
            let freeProgress = Math.max(0, parseInt(guidePricing.clinic_free_progress, 10));
            const upperSubtotal = upperCount > 0 ? firstPrice + ((upperCount - 1) * additionalPrice) : 0;
            const lowerSubtotal = lowerCount > 0 ? firstPrice + ((lowerCount - 1) * additionalPrice) : 0;
            let remainingForReward = totalImplants;
            let freeImplants = 0;
            const rewardRules = [];
            while (remainingForReward > 0) {
                const ruleUsed = activeFreeEvery;
                const progressBefore = freeProgress;
                const used = Math.min(remainingForReward, ruleUsed - progressBefore);
                freeProgress += used;
                remainingForReward -= used;
                let earned = 0;
                if (freeProgress === ruleUsed) {
                    earned = 1;
                    freeImplants++;
                    freeProgress = 0;
                    activeFreeEvery = nextFreeEvery;
                }
                rewardRules.push({ ruleUsed, progressBefore, used, progressAfter: freeProgress, earned });
            }
            const discount = freeImplants * additionalPrice;
            const selectedKit = document.querySelector('#guidedKitOption option:checked');
            const rentalPrice = document.querySelector('[name="guided_kit_source"]:checked')?.value === 'rental'
                ? Number(selectedKit?.dataset.price || 0)
                : 0;
            const total = Math.max(0, upperSubtotal + lowerSubtotal + printFee - discount + rentalPrice);

            document.getElementById('estimatedTotal').textContent = money(total);
            document.getElementById('currentImplants').textContent = totalImplants;
            document.getElementById('freeImplants').textContent = freeImplants;
            document.getElementById('priceBreakdown').innerHTML = `
                <div class="flex justify-between gap-3 rounded-xl bg-white/10 px-3 py-2"><span>Upper (${upperCount})</span><span class="font-bold">${money(upperSubtotal)}</span></div>
                <div class="flex justify-between gap-3 rounded-xl bg-white/10 px-3 py-2"><span>Lower (${lowerCount})</span><span class="font-bold">${money(lowerSubtotal)}</span></div>
                <div class="flex justify-between gap-3 rounded-xl bg-white/10 px-3 py-2"><span>Delivery method first price</span><span class="font-bold">${money(firstPrice)}</span></div>
                <div class="flex justify-between gap-3 rounded-xl bg-emerald-400/15 px-3 py-2"><span>Free implant discount</span><span class="font-bold">-${money(discount)}</span></div>
                <div class="flex justify-between gap-3 rounded-xl bg-white/10 px-3 py-2"><span>Reward progress after request</span><span class="font-bold">${freeProgress} / ${activeFreeEvery}</span></div>
                ${rewardRules.some((item) => item.ruleUsed !== rewardRules[0]?.ruleUsed) ? `<div class="rounded-xl bg-amber-300/15 px-3 py-2 text-xs">This request finishes your protected rule and continues on the current next-cycle rule.</div>` : ''}
                ${rentalPrice > 0 ? `<div class="flex justify-between gap-3 rounded-xl bg-cyan-400/15 px-3 py-2"><span>Guided kit rental</span><span class="font-bold">${money(rentalPrice)}</span></div>` : ''}
            `;
        }

        document.querySelectorAll('[data-guide-count], [name="delivery_method"]').forEach((input) => {
            input.addEventListener('input', calculateEstimate);
            input.addEventListener('change', calculateEstimate);
        });
        calculateEstimate();

        function setupFileInput(inputId, displayId) {
            document.getElementById(inputId).addEventListener('change', function(e) {
                document.getElementById(displayId).textContent = e.target.files[0] ? 'Selected: ' + e.target.files[0].name : '';
            });
        }
        setupFileInput('cbct_file', 'cbctFileName');
        setupFileInput('stl_file', 'stlFileName');

        const implantTypeSelect = document.getElementById('implantTypeSelect');
        const implantTypeOther = document.getElementById('implantTypeOther');
        const guidedKitSection = document.getElementById('guidedKitSection');
        const guidedKitFields = document.getElementById('guidedKitFields');
        const guidedKitRentalFields = document.getElementById('guidedKitRentalFields');
        const guidedKitOwnedFields = document.getElementById('guidedKitOwnedFields');
        const guidedKitOption = document.getElementById('guidedKitOption');
        const guidedKitName = document.getElementById('guidedKitName');
        const guidedKitType = document.getElementById('guidedKitType');
        const guidedKitImages = document.getElementById('guidedKitImages');

        function updateGuidedKitAvailability() {
            const enabled = implantTypeSelect.value !== '';
            guidedKitFields.disabled = !enabled;
            guidedKitSection.classList.toggle('opacity-60', !enabled);
            if (!enabled) {
                document.querySelectorAll('[name="guided_kit_source"]').forEach((radio) => radio.checked = false);
                updateGuidedKitFields();
            }
        }

        function updateGuidedKitFields() {
            const source = document.querySelector('[name="guided_kit_source"]:checked')?.value || '';
            const renting = source === 'rental';
            const owned = source === 'owned';
            guidedKitRentalFields.classList.toggle('hidden', !renting);
            guidedKitOwnedFields.classList.toggle('hidden', !owned);
            guidedKitOption.required = renting;
            guidedKitName.required = owned;
            guidedKitType.required = owned;
            guidedKitOption.disabled = !renting;
            guidedKitName.disabled = !owned;
            guidedKitType.disabled = !owned;
            guidedKitImages.disabled = !owned;
            if (!renting) guidedKitOption.value = '';
            if (!owned) {
                guidedKitName.value = '';
                guidedKitType.value = '';
                guidedKitImages.value = '';
                document.getElementById('guidedKitImageNames').textContent = '';
            }
            calculateEstimate();
        }

        implantTypeSelect.addEventListener('change', () => {
            const isOther = implantTypeSelect.value === 'other';
            implantTypeOther.classList.toggle('hidden', !isOther);
            implantTypeOther.required = isOther;
            if (!isOther) implantTypeOther.value = '';
            updateGuidedKitAvailability();
        });
        document.querySelectorAll('[name="guided_kit_source"]').forEach((radio) => radio.addEventListener('change', updateGuidedKitFields));
        guidedKitOption.addEventListener('change', calculateEstimate);
        guidedKitImages.addEventListener('change', () => {
            const files = Array.from(guidedKitImages.files || []);
            document.getElementById('guidedKitImageNames').textContent = files.length ? `${files.length} image(s) selected` : '';
        });
        updateGuidedKitAvailability();

        async function uploadFileToCloud(file, progressContainerId, progressBarId, progressTextId, endpoint = 'api/generate_presigned_url.php') {
        const progressContainer = progressContainerId ? document.getElementById(progressContainerId) : null;
        const progressBar = progressBarId ? document.getElementById(progressBarId) : null;
        const progressText = progressTextId ? document.getElementById(progressTextId) : null;

        progressContainer?.classList.remove('hidden');

        const presignedRes = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                filename: file.name,
                contentType: file.type || 'application/octet-stream',
                fileSize: file.size
            })
        });
        const presignedData = await presignedRes.json();
        if (!presignedRes.ok || presignedData.error) throw new Error(presignedData.error || 'Failed to get secure upload URL.');

        await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', presignedData.presigned_url, true);
            xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');

            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const percent = Math.round((event.loaded / event.total) * 100);
                    if (progressBar) progressBar.style.width = percent + '%';
                    if (progressText) progressText.textContent = percent + '%';
                }
            };
            xhr.onload = () => {
                if (xhr.status === 200) resolve();
                else reject(new Error('Failed to upload file.'));
            };
            xhr.onerror = () => reject(new Error('Network error during file upload.'));
            xhr.send(file);
        });
        return presignedData.object_key;
        }

        document.getElementById('guideForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('submitBtn');
            const errorMsg = document.getElementById('errorMsg');
            const successMsg = document.getElementById('successMsg');
            const cbctInput = document.getElementById('cbct_file');
            const stlInput = document.getElementById('stl_file');
            const kitImageFiles = Array.from(guidedKitImages.files || []);

            if (kitImageFiles.length > 8 || kitImageFiles.some((file) => file.size > 10 * 1024 * 1024)) {
                errorMsg.textContent = 'Please select no more than 8 kit images, with a maximum size of 10 MB each.';
                errorMsg.classList.remove('hidden');
                return;
            }

            if (!cbctInput.files[0] || !stlInput.files[0]) {
                errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> Please select both CBCT and STL files.';
                errorMsg.classList.remove('hidden');
                return;
            }

            const requestedImplants = getRegionTotal(upperRegions) + getRegionTotal(lowerRegions);
            if (requestedImplants < 1) {
                errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> Please enter at least one implant location.';
                errorMsg.classList.remove('hidden');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Uploading Files...';
            errorMsg.classList.add('hidden');
            successMsg.classList.add('hidden');

            try {
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Verifying Current Price...';
                const pricingCheckBody = new FormData(form);
                pricingCheckBody.delete('cbct_file');
                pricingCheckBody.delete('stl_file');
                pricingCheckBody.delete('guided_kit_images[]');
                pricingCheckBody.append('guided_kit_price_seen', document.querySelector('#guidedKitOption option:checked')?.dataset.price || '0');
                pricingCheckBody.append('pricing_version', loadedPricingVersion);
                const pricingCheckRes = await fetch('api/check_guide_pricing.php', {
                    method: 'POST',
                    body: pricingCheckBody
                });
                const pricingCheckData = await pricingCheckRes.json();
                if (!pricingCheckRes.ok || !pricingCheckData.success) {
                    throw new Error(pricingCheckData.error || 'Could not verify the current pricing.');
                }

                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Uploading Files...';
                // Upload files consecutively to ensure stable UI progress bars
                const cbctKey = await uploadFileToCloud(cbctInput.files[0], 'cbctProgressContainer', 'cbctProgressBar', 'cbctProgressText');
                const stlKey = await uploadFileToCloud(stlInput.files[0], 'stlProgressContainer', 'stlProgressBar', 'stlProgressText');

                const guidedKitUploads = [];
                if (document.querySelector('[name="guided_kit_source"]:checked')?.value === 'owned' && kitImageFiles.length) {
                    const kitUploadStatus = document.getElementById('guidedKitUploadStatus');
                    kitUploadStatus.classList.remove('hidden');
                    for (let index = 0; index < kitImageFiles.length; index++) {
                        const file = kitImageFiles[index];
                        kitUploadStatus.textContent = `Uploading kit image ${index + 1} of ${kitImageFiles.length}...`;
                        const key = await uploadFileToCloud(file, null, null, null, 'api/generate_guide_kit_upload_url.php');
                        guidedKitUploads.push({ key, name: file.name });
                    }
                    kitUploadStatus.textContent = 'Kit images uploaded.';
                }

                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Submitting Request...';

                const formData = new FormData(form);
                formData.append('cbct_file_path', cbctKey);
                formData.append('stl_file_path', stlKey);
                formData.append('pricing_version', loadedPricingVersion);
                formData.append('guided_kit_uploads', JSON.stringify(guidedKitUploads));
                formData.append('guided_kit_price_seen', document.querySelector('#guidedKitOption option:checked')?.dataset.price || '0');
                formData.delete('cbct_file');
                formData.delete('stl_file');
                formData.delete('guided_kit_images[]');

                const submitRes = await fetch('api/submit_guide.php', {
                    method: 'POST',
                    body: formData
                });

                const submitData = await submitRes.json();

                if (submitRes.ok && submitData.success) {
                    form.reset();
                    document.getElementById('cbctFileName').textContent = '';
                    document.getElementById('stlFileName').textContent = '';
                    document.getElementById('cbctProgressContainer').classList.add('hidden');
                    document.getElementById('stlProgressContainer').classList.add('hidden');
                    document.getElementById('guidedKitUploadStatus').classList.add('hidden');
                    updateGuidedKitAvailability();
                    successMsg.innerHTML = '<i class="fa-solid fa-check-circle mr-1"></i> ' + submitData.success
                        + ' Final price: ' + money(submitData.total_price)
                        + '. Free implants: ' + Number(submitData.free_implants || 0) + '.';
                    successMsg.classList.remove('hidden');
                    setTimeout(() => {
                        window.location.href = 'clinic_dashboard.php';
                    }, 2000);
                } else {
                    throw new Error(submitData.error || 'An error occurred while saving the request.');
                }
            } catch (err) {
                errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> ' + err.message;
                errorMsg.classList.remove('hidden');
                document.getElementById('cbctProgressContainer').classList.add('hidden');
                document.getElementById('stlProgressContainer').classList.add('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Submit Request';
            }
        });
    </script>
</body>

</html>
