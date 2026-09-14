<?php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/implant_types.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';

$success = '';
$error = '';

try {
    ensureImplantTypesSchema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $priceInput = trim($_POST['price'] ?? '');
            $price = filter_var($priceInput, FILTER_VALIDATE_FLOAT);
            $priceUsdInput = trim($_POST['price_usd'] ?? '');
            $priceUsd = filter_var($priceUsdInput, FILTER_VALIDATE_FLOAT);
            $notes = trim($_POST['notes'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                $error = 'Implant type name is required.';
            } elseif ($priceInput === '' || $price === false || $price < 0) {
                $error = 'Egypt implant price must be a valid non-negative number.';
            } elseif ($priceUsdInput === '' || $priceUsd === false || $priceUsd < 0) {
                $error = 'Outside-Egypt implant price must be a valid non-negative number.';
            } elseif ($id) {
                $stmt = $pdo->prepare("
                    UPDATE implant_types
                    SET name = :name, brand = :brand, price = :price, price_usd = :price_usd, notes = :notes, is_active = :is_active
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':brand' => $brand ?: null,
                    ':price' => $price,
                    ':price_usd' => $priceUsd,
                    ':notes' => $notes ?: null,
                    ':is_active' => $isActive,
                    ':id' => $id,
                ]);
                $success = 'Implant type updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO implant_types (name, brand, price, price_usd, notes, is_active)
                    VALUES (:name, :brand, :price, :price_usd, :notes, :is_active)
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':brand' => $brand ?: null,
                    ':price' => $price,
                    ':price_usd' => $priceUsd,
                    ':notes' => $notes ?: null,
                    ':is_active' => $isActive,
                ]);
                $success = 'Implant type added successfully.';
            }
        } elseif ($action === 'toggle') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE implant_types SET is_active = 1 - is_active WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $success = 'Implant type status changed.';
            }
        }
    }

    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    $editType = $editId ? getImplantTypeById($pdo, $editId) : null;
    $implantTypes = getAllImplantTypes($pdo);
    $usageRows = getImplantTypeUsage($pdo);
} catch (PDOException $e) {
    error_log('Implant Types Admin Error: ' . $e->getMessage());
    $implantTypes = [];
    $usageRows = [];
    $editType = null;
    $error = $error ?: 'Could not load implant types.';
}

$typesTable = adminTableState($implantTypes, ['name', 'brand', 'price', 'price_usd', 'notes', 'is_active', 'request_count', 'implant_count'], 'implant_types');
$usageTable = adminTableState($usageRows, ['implant_type_name', 'request_count', 'implant_count', 'revenue', 'is_other'], 'implant_usage');
?>

<div class="mb-6 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-[#13324a]">Implant Types</h2>
        <p class="text-slate-500 text-sm mt-1">Manage available implant types and prices. The Egypt price is used in new dental-implant surgeon requests; existing requests keep their saved price.</p>
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
    <div class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-bold text-[#13324a] mb-5"><?= $editType ? 'Edit Implant Type' : 'Add Implant Type' ?></h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save">
            <?php if ($editType): ?>
                <input type="hidden" name="id" value="<?= (int) $editType['id'] ?>">
            <?php endif; ?>
            <div>
                <label class="block text-sm font-semibold text-[#13324a] mb-2">Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($editType['name'] ?? '') ?>" required class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="E.g., Dentium SuperLine">
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#13324a] mb-2">Brand / Company</label>
                <input type="text" name="brand" value="<?= htmlspecialchars($editType['brand'] ?? '') ?>" class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="Optional">
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#13324a] mb-2">Implant Prices</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Inside Egypt / EGP</label>
                        <input type="number" name="price" step="0.01" min="0" value="<?= htmlspecialchars(number_format((float) ($editType['price'] ?? 0), 2, '.', '')) ?>" required class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="0.00">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Outside Egypt / USD</label>
                        <input type="number" name="price_usd" step="0.01" min="0" value="<?= htmlspecialchars(number_format((float) ($editType['price_usd'] ?? 0), 2, '.', '')) ?>" required class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="0.00">
                    </div>
                </div>
                <p class="mt-1 text-xs text-slate-500">The Egypt price is used for new surgeon implant requests. The outside-Egypt price remains stored for future use.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#13324a] mb-2">Notes</label>
                <textarea name="notes" rows="3" class="block w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="Optional"><?= htmlspecialchars($editType['notes'] ?? '') ?></textarea>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_active" value="1" <?= !$editType || (int) $editType['is_active'] ? 'checked' : '' ?> class="rounded border-slate-300 text-[#1d5f8c] focus:ring-[#1d5f8c]">
                Active for new requests
            </label>
            <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-[#13324a] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#1d5f8c]">
                <i class="fa-solid fa-floppy-disk mr-2"></i> <?= $editType ? 'Save Changes' : 'Add Type' ?>
            </button>
            <?php if ($editType): ?>
                <a href="admin_implant_types.php" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-200">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50">
            <h3 class="text-lg font-bold text-[#13324a]">Available Types</h3>
        </div>
        <?php adminTableToolbar('implant_types', $typesTable, 'Search implant types...'); ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-white text-slate-500 font-semibold text-[11px]">
                    <tr>
                        <th class="px-6 py-4">Name</th>
                        <th class="px-6 py-4">Brand</th>
                        <th class="px-6 py-4">Egypt Price</th>
                        <th class="px-6 py-4">Outside Price</th>
                        <th class="px-6 py-4">Requests</th>
                        <th class="px-6 py-4">Implants</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700" data-admin-table-body="implant_types">
                    <?php if (!$typesTable['rows']): ?>
                        <tr><td colspan="8" class="px-6 py-12 text-center text-slate-500">No implant types match your search.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($typesTable['rows'] as $type): ?>
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-4 font-bold text-[#13324a]"><?= htmlspecialchars($type['name']) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($type['brand'] ?: '-') ?></td>
                            <td class="px-6 py-4 font-semibold"><?= formatCurrencyMoney($type['price'], 'EGP') ?></td>
                            <td class="px-6 py-4 font-semibold text-blue-600"><?= formatCurrencyMoney($type['price_usd'], 'USD') ?></td>
                            <td class="px-6 py-4"><?= (int) $type['request_count'] ?></td>
                            <td class="px-6 py-4"><?= (int) $type['implant_count'] ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= (int) $type['is_active'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                    <?= (int) $type['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="admin_implant_types.php?edit=<?= (int) $type['id'] ?>" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-[#1d5f8c] transition hover:bg-blue-500 hover:text-white">Edit</a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $type['id'] ?>">
                                    <button type="submit" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-[#13324a] hover:text-white">
                                        <?= (int) $type['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php adminTablePagination('implant_types', $typesTable); ?>
    </div>
</div>

<div class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50">
        <h3 class="text-lg font-bold text-[#13324a]">Most Requested Implant Types</h3>
    </div>
    <?php adminTableToolbar('implant_usage', $usageTable, 'Search implant usage...'); ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-white text-slate-500 font-semibold text-[11px]">
                <tr>
                    <th class="px-6 py-4">Type</th>
                    <th class="px-6 py-4">Requests</th>
                    <th class="px-6 py-4">Implants</th>
                    <th class="px-6 py-4">Revenue</th>
                    <th class="px-6 py-4">Source</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700 font-medium" data-admin-table-body="implant_usage">
                <?php if (!$usageTable['rows']): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No usage matches your search.</td></tr>
                <?php endif; ?>

                <?php foreach ($usageTable['rows'] as $row): ?>
                    <tr>
                        <td class="px-6 py-4 font-bold text-[#13324a]"><?= htmlspecialchars($row['implant_type_name'] ?: 'Unknown') ?></td>
                        <td class="px-6 py-4"><?= (int) $row['request_count'] ?></td>
                        <td class="px-6 py-4"><?= (int) $row['implant_count'] ?></td>
                        <td class="px-6 py-4"><?= formatMoney($row['revenue']) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= (int) $row['is_other'] ? 'bg-orange-50 text-orange-600 border border-orange-100' : 'bg-blue-50 text-[#1d5f8c] border border-blue-100' ?>">
                                <?= (int) $row['is_other'] ? 'Other' : 'Catalog' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php adminTablePagination('implant_usage', $usageTable); ?>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
