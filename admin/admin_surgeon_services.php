<?php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/surgeon_services.php';

$success = '';
$error = '';
$allOnPackages = getSurgeonAllOnPackages();

if (empty($_SESSION['surgeon_services_csrf_token'])) {
    $_SESSION['surgeon_services_csrf_token'] = bin2hex(random_bytes(32));
}

try {
    ensureSurgeonServicesSchema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($_SESSION['surgeon_services_csrf_token'], $csrfToken)) {
            throw new InvalidArgumentException('Invalid form token. Refresh the page and try again.');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_all_on_prices') {
            $submittedFees = $_POST['team_fees'] ?? [];
            $validatedFees = [];
            foreach ($allOnPackages as $code => $package) {
                $rawFee = trim((string) ($submittedFees[$code] ?? ''));
                $fee = filter_var($rawFee, FILTER_VALIDATE_FLOAT);
                if ($rawFee === '' || $fee === false || $fee < 0) {
                    throw new InvalidArgumentException('Enter a valid non-negative team fee for ' . $package['label'] . '.');
                }
                $validatedFees[$code] = (float) $fee;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO surgeon_all_on_prices (package_code, team_fee)
                VALUES (:package_code, :team_fee)
                ON DUPLICATE KEY UPDATE team_fee = VALUES(team_fee)
            ");
            foreach ($validatedFees as $code => $fee) {
                $stmt->execute([':package_code' => $code, ':team_fee' => $fee]);
            }
            $pdo->commit();
            $success = 'All-on team prices updated successfully.';
        } elseif ($action === 'save') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['name'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '' || mb_strlen($name) > 190) {
                throw new InvalidArgumentException('Enter a service name of no more than 190 characters.');
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE surgeon_services
                    SET name = :name, notes = :notes, is_active = :is_active
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':notes' => $notes !== '' ? $notes : null,
                    ':is_active' => $isActive,
                    ':id' => $id,
                ]);
                $success = 'Surgical service updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO surgeon_services (name, notes, is_active)
                    VALUES (:name, :notes, :is_active)
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':notes' => $notes !== '' ? $notes : null,
                    ':is_active' => $isActive,
                ]);
                $success = 'Surgical service added successfully.';
            }
        } elseif ($action === 'toggle') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid service.');
            }
            $stmt = $pdo->prepare("UPDATE surgeon_services SET is_active = 1 - is_active WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $success = 'Surgical service status changed.';
        }

        $_SESSION['surgeon_services_csrf_token'] = bin2hex(random_bytes(32));
    }

    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    $editService = $editId ? getSurgeonServiceById($pdo, $editId) : null;
    $services = getAllSurgeonServices($pdo);
    $allOnPrices = getSurgeonAllOnPrices($pdo);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Surgeon Services Admin Error: ' . $e->getMessage());
    $services = $services ?? [];
    $editService = $editService ?? null;
    $allOnPrices = $allOnPrices ?? array_fill_keys(array_keys($allOnPackages), 0.00);
    if ($e instanceof InvalidArgumentException) {
        $error = $e->getMessage();
    } elseif ($e instanceof PDOException && $e->getCode() === '23000') {
        $error = 'A surgical service with this name already exists.';
    } else {
        $error = 'Could not load or save surgical services.';
    }
}

$servicesTable = adminTableState($services, ['name', 'notes', 'is_active', 'request_count'], 'surgeon_services');
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-[#13324a]">Surgeon Services</h2>
    <p class="mt-1 text-sm text-slate-500">Add the surgical cases surgeons can handle. These services do not have an automatic price; clinics will see “سيتم الرد بعرض سعر”.</p>
</div>

<?php if ($success): ?>
    <div class="mb-6 rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-6 rounded-xl border border-red-100 bg-red-50 px-5 py-4 text-sm font-semibold text-red-600"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['surgeon_services_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="save_all_on_prices">
    <div class="border-b border-slate-100 bg-slate-50 px-6 py-5">
        <h3 class="text-lg font-bold text-[#13324a]">All-on Team Pricing</h3>
        <p class="mt-1 text-sm text-slate-500">Set the team-work price for one arch. If both arches are selected, the applicable team prices are added together.</p>
    </div>
    <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
        <?php foreach ($allOnPackages as $code => $package): ?>
            <div>
                <label class="mb-2 block text-sm font-semibold text-[#13324a]"><?= htmlspecialchars($package['label']) ?> / one arch</label>
                <div class="relative">
                    <input type="number" name="team_fees[<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>]" min="0" step="0.01" required value="<?= htmlspecialchars(number_format((float) ($allOnPrices[$code] ?? 0), 2, '.', '')) ?>" class="block w-full rounded-xl border border-slate-200 px-4 py-3 pr-16 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]">
                    <span class="absolute inset-y-0 right-4 flex items-center text-xs font-bold text-slate-400">EGP</span>
                </div>
                <p class="mt-1 text-xs text-slate-500"><?= (int) $package['implant_count'] ?> implants per selected arch.</p>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-6 py-4">
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#13324a] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#1d5f8c]"><i class="fa-solid fa-floppy-disk mr-2"></i>Save Team Prices</button>
    </div>
</form>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-1">
        <h3 class="mb-5 text-lg font-bold text-[#13324a]"><?= $editService ? 'Edit Surgical Service' : 'Add Surgical Service' ?></h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['surgeon_services_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="save">
            <?php if ($editService): ?><input type="hidden" name="id" value="<?= (int) $editService['id'] ?>"><?php endif; ?>
            <div>
                <label class="mb-2 block text-sm font-semibold text-[#13324a]">Service Name</label>
                <input type="text" name="name" maxlength="190" required value="<?= htmlspecialchars($editService['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]" placeholder="E.g., Sinus lift">
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-[#13324a]">Notes</label>
                <textarea name="notes" rows="4" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]" placeholder="Optional internal or clinic-facing details"><?= htmlspecialchars($editService['notes'] ?? '') ?></textarea>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_active" value="1" <?= !$editService || (int) $editService['is_active'] ? 'checked' : '' ?> class="rounded border-slate-300 text-[#1d5f8c] focus:ring-[#1d5f8c]">
                Active for new requests
            </label>
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-[#13324a] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#1d5f8c]"><i class="fa-solid fa-floppy-disk mr-2"></i><?= $editService ? 'Save Changes' : 'Add Service' ?></button>
            <?php if ($editService): ?><a href="admin_surgeon_services.php" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-200">Cancel Edit</a><?php endif; ?>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
        <div class="border-b border-slate-100 bg-slate-50 px-6 py-5">
            <h3 class="text-lg font-bold text-[#13324a]">Quote-only Services</h3>
        </div>
        <?php adminTableToolbar('surgeon_services', $servicesTable, 'Search services...'); ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b-2 border-slate-100 bg-white text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-6 py-4">Service</th><th class="px-6 py-4">Requests</th><th class="px-6 py-4">Status</th><th class="px-6 py-4 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700" data-admin-table-body="surgeon_services">
                    <?php if (!$servicesTable['rows']): ?><tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No surgical services match your search.</td></tr><?php endif; ?>
                    <?php foreach ($servicesTable['rows'] as $service): ?>
                        <tr class="transition hover:bg-slate-50/60">
                            <td class="px-6 py-4"><div class="font-bold text-[#13324a]"><?= htmlspecialchars($service['name']) ?></div><?php if ($service['notes']): ?><div class="mt-1 max-w-md whitespace-normal text-xs text-slate-500"><?= htmlspecialchars($service['notes']) ?></div><?php endif; ?></td>
                            <td class="px-6 py-4"><?= (int) $service['request_count'] ?></td>
                            <td class="px-6 py-4"><span class="rounded-full border px-2.5 py-1 text-xs font-bold <?= (int) $service['is_active'] ? 'border-emerald-100 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-100 text-slate-500' ?>"><?= (int) $service['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <a href="admin_surgeon_services.php?edit=<?= (int) $service['id'] ?>" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-[#1d5f8c] transition hover:bg-blue-500 hover:text-white">Edit</a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['surgeon_services_csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                    <button type="submit" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-[#13324a] hover:text-white"><?= (int) $service['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php adminTablePagination('surgeon_services', $servicesTable); ?>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
