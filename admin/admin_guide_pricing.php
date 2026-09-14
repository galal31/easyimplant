<?php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';
require_once __DIR__ . '/../includes/surgical_guide_kits.php';

$success = '';
$error = '';
$currentPricing = getSurgicalGuidePricing($pdo);
ensureSurgicalGuideKitsSchema($pdo);
if (empty($_SESSION['pricing_csrf_token'])) {
    $_SESSION['pricing_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = (string) ($_POST['action'] ?? 'save_pricing');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (in_array($formAction, ['save_guided_kit', 'toggle_guided_kit'], true)) {
        if (!hash_equals($_SESSION['pricing_csrf_token'], $csrfToken)) {
            $error = 'Your session token is invalid. Please refresh the page and try again.';
        } elseif ($formAction === 'save_guided_kit') {
            $kitId = filter_input(INPUT_POST, 'kit_id', FILTER_VALIDATE_INT);
            $kitName = trim((string) ($_POST['kit_name'] ?? ''));
            $kitPrice = filter_input(INPUT_POST, 'kit_rental_price', FILTER_VALIDATE_FLOAT);
            if ($kitName === '' || mb_strlen($kitName) > 190) {
                $error = 'Please enter a guided kit name up to 190 characters.';
            } elseif ($kitPrice === false || $kitPrice === null || $kitPrice < 0) {
                $error = 'Please enter a valid guided kit rental price.';
            } else {
                try {
                    if ($kitId) {
                        $stmt = $pdo->prepare("UPDATE surgical_guide_kit_options SET name = :name, rental_price = :price WHERE id = :id");
                        $stmt->execute([':name' => $kitName, ':price' => $kitPrice, ':id' => $kitId]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO surgical_guide_kit_options (name, rental_price) VALUES (:name, :price)");
                        $stmt->execute([':name' => $kitName, ':price' => $kitPrice]);
                    }
                    $success = $kitId ? 'Guided kit updated successfully.' : 'Guided kit added successfully.';
                } catch (PDOException $e) {
                    $error = $e->getCode() === '23000' ? 'A guided kit with this name already exists.' : 'Could not save the guided kit.';
                }
            }
        } else {
            $kitId = filter_input(INPUT_POST, 'kit_id', FILTER_VALIDATE_INT);
            $kitActive = filter_input(INPUT_POST, 'kit_active', FILTER_VALIDATE_INT);
            if (!$kitId || !in_array($kitActive, [0, 1], true)) {
                $error = 'Invalid guided kit update.';
            } else {
                $stmt = $pdo->prepare("UPDATE surgical_guide_kit_options SET is_active = :active WHERE id = :id");
                $stmt->execute([':active' => $kitActive, ':id' => $kitId]);
                $success = $kitActive ? 'Guided kit activated.' : 'Guided kit disabled.';
            }
        }
    } else {
    $clinicPrintFirstEgp = filter_input(INPUT_POST, 'clinic_print_first_implant_price_egp', FILTER_VALIDATE_FLOAT);
    $clinicPrintFirstUsd = filter_input(INPUT_POST, 'clinic_print_first_implant_price_usd', FILTER_VALIDATE_FLOAT);
    $adminPrintFirstEgp = filter_input(INPUT_POST, 'admin_print_first_implant_price_egp', FILTER_VALIDATE_FLOAT);
    $adminPrintFirstUsd = filter_input(INPUT_POST, 'admin_print_first_implant_price_usd', FILTER_VALIDATE_FLOAT);
    $additionalImplantPriceEgp = filter_input(INPUT_POST, 'additional_implant_price_egp', FILTER_VALIDATE_FLOAT);
    $additionalImplantPriceUsd = filter_input(INPUT_POST, 'additional_implant_price_usd', FILTER_VALIDATE_FLOAT);
    $freeImplantEvery = filter_input(INPUT_POST, 'free_implant_every', FILTER_VALIDATE_INT);

    if (!hash_equals($_SESSION['pricing_csrf_token'], $csrfToken)) {
        $error = 'Your session token is invalid. Please refresh the page and try again.';
    } elseif ($clinicPrintFirstEgp === false || $clinicPrintFirstEgp === null || $clinicPrintFirstEgp < 0) {
        $error = 'Please enter a valid Egypt clinic-print first implant price.';
    } elseif ($clinicPrintFirstUsd === false || $clinicPrintFirstUsd === null || $clinicPrintFirstUsd < 0) {
        $error = 'Please enter a valid outside-Egypt clinic-print first implant price.';
    } elseif ($adminPrintFirstEgp === false || $adminPrintFirstEgp === null || $adminPrintFirstEgp < 0) {
        $error = 'Please enter a valid Egypt admin-print first implant price.';
    } elseif ($adminPrintFirstUsd === false || $adminPrintFirstUsd === null || $adminPrintFirstUsd < 0) {
        $error = 'Please enter a valid outside-Egypt admin-print first implant price.';
    } elseif ($additionalImplantPriceEgp === false || $additionalImplantPriceEgp === null || $additionalImplantPriceEgp < 0) {
        $error = 'Please enter a valid Egypt additional implant price.';
    } elseif ($additionalImplantPriceUsd === false || $additionalImplantPriceUsd === null || $additionalImplantPriceUsd < 0) {
        $error = 'Please enter a valid outside-Egypt additional implant price.';
    } elseif ($freeImplantEvery === false || $freeImplantEvery === null || $freeImplantEvery < 1) {
        $error = 'Free implant interval must be at least 1.';
    } else {
        try {
            $ruleChanged = (int) $currentPricing['free_implant_every'] !== (int) $freeImplantEvery;
            $pricingChanges = [
                'clinic_print_first_implant_price_egp' => [(float) $currentPricing['clinic_print_first_implant_price_egp'], (float) $clinicPrintFirstEgp],
                'clinic_print_first_implant_price_usd' => [(float) $currentPricing['clinic_print_first_implant_price_usd'], (float) $clinicPrintFirstUsd],
                'admin_print_first_implant_price_egp' => [(float) $currentPricing['admin_print_first_implant_price_egp'], (float) $adminPrintFirstEgp],
                'admin_print_first_implant_price_usd' => [(float) $currentPricing['admin_print_first_implant_price_usd'], (float) $adminPrintFirstUsd],
                'additional_implant_price_egp' => [(float) $currentPricing['additional_implant_price_egp'], (float) $additionalImplantPriceEgp],
                'additional_implant_price_usd' => [(float) $currentPricing['additional_implant_price_usd'], (float) $additionalImplantPriceUsd],
                'free_implant_every' => [(float) $currentPricing['free_implant_every'], (float) $freeImplantEvery],
            ];
            $pdo->beginTransaction();

            if ($ruleChanged) {
                createSurgicalGuideFreeRuleCycle(
                    $pdo,
                    (int) $freeImplantEvery,
                    isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO surgical_guide_pricing_settings (setting_key, setting_value)
                VALUES (:setting_key, :setting_value)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            $updates = [
                'clinic_print_first_implant_price_egp' => $clinicPrintFirstEgp,
                'clinic_print_first_implant_price_usd' => $clinicPrintFirstUsd,
                'admin_print_first_implant_price_egp' => $adminPrintFirstEgp,
                'admin_print_first_implant_price_usd' => $adminPrintFirstUsd,
                'additional_implant_price_egp' => $additionalImplantPriceEgp,
                'additional_implant_price_usd' => $additionalImplantPriceUsd,
                // Existing calculation keys remain synchronized to Egypt prices until routing is approved.
                'clinic_print_first_implant_price' => $clinicPrintFirstEgp,
                'admin_print_first_implant_price' => $adminPrintFirstEgp,
                'additional_implant_price' => $additionalImplantPriceEgp,
                'free_implant_every' => $freeImplantEvery,
                // Backward compatibility keys. admin_print_fee is intentionally zero now.
                'first_implant_price' => $clinicPrintFirstEgp,
                'admin_print_fee' => 0,
            ];

            foreach ($updates as $key => $value) {
                $stmt->execute([
                    ':setting_key' => $key,
                    ':setting_value' => $value,
                ]);
            }

            $logStmt = $pdo->prepare("INSERT INTO surgical_guide_pricing_logs
                (admin_id, setting_key, old_value, new_value)
                VALUES (:admin_id, :setting_key, :old_value, :new_value)");
            foreach ($pricingChanges as $key => [$oldValue, $newValue]) {
                if (abs($oldValue - $newValue) < 0.00001) continue;
                $logStmt->execute([
                    ':admin_id' => (int) $_SESSION['user_id'],
                    ':setting_key' => $key,
                    ':old_value' => $oldValue,
                    ':new_value' => $newValue,
                ]);
            }

            $pdo->commit();
            $_SESSION['pricing_csrf_token'] = bin2hex(random_bytes(32));
            $success = $ruleChanged
                ? 'Pricing updated. The free implant counter started a new cycle from zero for every clinic.'
                : 'Surgical guide pricing updated successfully.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Guide Pricing Update Error: ' . $e->getMessage());
            $error = 'Could not save pricing settings.';
        }
    }
    }
}

$pricing = getSurgicalGuidePricing($pdo);
$guidedKits = getAllSurgicalGuideKitOptions($pdo);
?>

<div class="mb-6 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-[#13324a]">Surgical Guide Pricing</h2>
        <p class="text-slate-500 text-sm mt-1">Store a separate EGP price for Egypt and USD price for outside Egypt. Current requests still use Egypt prices until location routing is enabled.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="mb-6 rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">
        <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 rounded-xl border border-red-100 bg-red-50 px-5 py-4 text-sm font-semibold text-red-600">
        <i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="POST" class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <input type="hidden" name="action" value="save_pricing">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['pricing_csrf_token']) ?>">
        <div class="space-y-5">
            <div class="hidden md:grid grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)_minmax(0,1fr)] gap-4 px-4 text-xs font-bold uppercase tracking-wider text-slate-400">
                <span>Price item</span>
                <span>Inside Egypt / EGP</span>
                <span>Outside Egypt / USD</span>
            </div>

            <?php
            $dualPriceRows = [
                ['Clinic Prints Locally - First Implant Price Per Arch', 'clinic_print_first_implant_price', 'Used when the doctor or clinic prints the guide.'],
                ['Admin Prints & Delivers - First Implant Price Per Arch', 'admin_print_first_implant_price', 'Used when the admin prints and delivers the guide.'],
                ['Additional Implant Price', 'additional_implant_price', 'Applied after the first implant in each arch.'],
            ];
            ?>
            <?php foreach ($dualPriceRows as [$label, $key, $help]): ?>
                <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)_minmax(0,1fr)] gap-4 rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <div>
                        <p class="text-sm font-bold text-[#13324a]"><?= htmlspecialchars($label) ?></p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500"><?= htmlspecialchars($help) ?></p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-400 md:hidden">Inside Egypt / EGP</label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" name="<?= htmlspecialchars($key) ?>_egp" value="<?= htmlspecialchars(number_format((float) $pricing[$key . '_egp'], 2, '.', '')) ?>" required class="block w-full rounded-xl border border-slate-200 py-3 pl-4 pr-14 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]">
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-slate-400">EGP</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-400 md:hidden">Outside Egypt / USD</label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" name="<?= htmlspecialchars($key) ?>_usd" value="<?= htmlspecialchars(number_format((float) $pricing[$key . '_usd'], 2, '.', '')) ?>" required class="block w-full rounded-xl border border-slate-200 py-3 pl-4 pr-14 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]">
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-slate-400">USD</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="rounded-2xl border border-orange-100 bg-orange-50/60 p-4">
                <label class="block text-sm font-semibold text-[#13324a] mb-2">Free Implant Every</label>
                <input type="number" step="1" min="1" name="free_implant_every" value="<?= htmlspecialchars((int) $pricing['free_implant_every']) ?>" required class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]">
                <p class="text-xs text-slate-500 mt-1">Current example: implants number <?= (int) $pricing['free_implant_every'] ?>, <?= (int) $pricing['free_implant_every'] * 2 ?>, <?= (int) $pricing['free_implant_every'] * 3 ?>... are free.</p>
                <p class="mt-2 text-xs font-semibold text-orange-600">Changing this number starts a new free-implant cycle from zero for every clinic.</p>
            </div>
        </div>


        <div class="mt-6 pt-5 border-t border-slate-100 flex justify-end">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#13324a] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#1d5f8c] shadow-sm">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Save Pricing
            </button>
        </div>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-bold text-[#13324a] mb-4">Current Rule</h3>
        <div class="space-y-4 text-sm">
            <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Clinic print / 5 implants in one arch</p>
                <p class="font-bold text-slate-700 mt-1"><?= formatCurrencyMoney($pricing['clinic_print_first_implant_price_egp'] + (4 * $pricing['additional_implant_price_egp']), 'EGP') ?></p>
                <p class="font-bold text-blue-600 mt-1"><?= formatCurrencyMoney($pricing['clinic_print_first_implant_price_usd'] + (4 * $pricing['additional_implant_price_usd']), 'USD') ?></p>
            </div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Admin print / 5 implants in one arch</p>
                <p class="font-bold text-slate-700 mt-1"><?= formatCurrencyMoney($pricing['admin_print_first_implant_price_egp'] + (4 * $pricing['additional_implant_price_egp']), 'EGP') ?></p>
                <p class="font-bold text-blue-600 mt-1"><?= formatCurrencyMoney($pricing['admin_print_first_implant_price_usd'] + (4 * $pricing['additional_implant_price_usd']), 'USD') ?></p>
            </div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Free implant discount</p>
                <p class="font-bold text-emerald-600 mt-1">-<?= formatCurrencyMoney($pricing['additional_implant_price_egp'], 'EGP') ?></p>
                <p class="font-bold text-blue-600 mt-1">-<?= formatCurrencyMoney($pricing['additional_implant_price_usd'], 'USD') ?></p>
            </div>
            <p class="text-xs leading-relaxed text-slate-500">Both currencies are stored. Existing calculations continue to use EGP until the location-based pricing rule is implemented.</p>
        </div>
    </div>
</div>

<section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h3 class="text-lg font-bold text-[#13324a]">Guided Kit Rentals</h3>
            <p class="mt-1 text-sm text-slate-500">Add the kits clinics can rent. The saved price is added to the surgical guide request total.</p>
        </div>
    </div>

    <form method="POST" class="mb-6 grid grid-cols-1 gap-4 rounded-2xl border border-blue-100 bg-blue-50/50 p-4 md:grid-cols-[minmax(0,1fr)_220px_auto] md:items-end">
        <input type="hidden" name="action" value="save_guided_kit">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['pricing_csrf_token']) ?>">
        <div>
            <label class="mb-2 block text-sm font-semibold text-[#13324a]">Kit name</label>
            <input type="text" name="kit_name" maxlength="190" required class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-[#13324a] focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]" placeholder="Example: Neodent Guided Surgery Kit">
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold text-[#13324a]">Rental price</label>
            <div class="relative">
                <input type="number" name="kit_rental_price" min="0" step="0.01" required class="block w-full rounded-xl border border-slate-200 bg-white py-3 pl-4 pr-14 text-sm text-[#13324a] focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]">
                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-slate-400">EGP</span>
            </div>
        </div>
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#1d5f8c] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#13324a]">
            <i class="fa-solid fa-plus mr-2"></i>Add Kit
        </button>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500">
                <tr><th class="px-4 py-3">Kit</th><th class="px-4 py-3">Rental price</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!$guidedKits): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No rental guided kits have been added yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($guidedKits as $kit): ?>
                    <tr>
                        <td colspan="4" class="p-0">
                            <form method="POST" class="grid min-w-[760px] grid-cols-[minmax(0,1fr)_180px_120px_220px] items-center gap-3 px-4 py-3">
                                <input type="hidden" name="action" value="save_guided_kit">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['pricing_csrf_token']) ?>">
                                <input type="hidden" name="kit_id" value="<?= (int) $kit['id'] ?>">
                                <input type="text" name="kit_name" maxlength="190" required value="<?= htmlspecialchars($kit['name']) ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-[#13324a] focus:border-[#1d5f8c] focus:ring-[#1d5f8c]">
                                <div class="relative"><input type="number" name="kit_rental_price" min="0" step="0.01" required value="<?= htmlspecialchars(number_format((float) $kit['rental_price'], 2, '.', '')) ?>" class="w-full rounded-lg border border-slate-200 py-2 pl-3 pr-12 text-sm focus:border-[#1d5f8c] focus:ring-[#1d5f8c]"><span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[10px] font-bold text-slate-400">EGP</span></div>
                                <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-bold <?= (int) $kit['is_active'] === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= (int) $kit['is_active'] === 1 ? 'Active' : 'Disabled' ?></span>
                                <div class="flex justify-end gap-2">
                                    <button type="submit" class="rounded-lg bg-[#13324a] px-3 py-2 text-xs font-bold text-white hover:bg-[#1d5f8c]">Save</button>
                                    <button type="submit" formaction="admin_guide_pricing.php" name="action" value="toggle_guided_kit" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50" onclick="this.form.querySelector('[name=kit_active]').value='<?= (int) $kit['is_active'] === 1 ? '0' : '1' ?>'">
                                        <?= (int) $kit['is_active'] === 1 ? 'Disable' : 'Activate' ?>
                                    </button>
                                    <input type="hidden" name="kit_active" value="<?= (int) $kit['is_active'] === 1 ? '0' : '1' ?>">
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once 'includes/admin_footer.php'; ?>
