<?php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/surgical_guide_pricing.php';

$success = '';
$error = '';
$governorates = getEgyptGovernorates();

if (empty($_SESSION['surgeon_travel_pricing_csrf_token'])) {
    $_SESSION['surgeon_travel_pricing_csrf_token'] = bin2hex(random_bytes(32));
}

try {
    ensureSurgeonGovernoratePricingSchema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['surgeon_travel_pricing_csrf_token'], $csrfToken)) {
            throw new InvalidArgumentException('Invalid form token. Refresh the page and try again.');
        }

        $submittedPrices = $_POST['prices'] ?? [];
        $submittedAvailability = $_POST['available'] ?? [];
        $validatedPrices = [];
        foreach ($governorates as $code => $label) {
            $rawPrice = trim((string) ($submittedPrices[$code] ?? ''));
            $price = filter_var($rawPrice, FILTER_VALIDATE_FLOAT);
            if ($rawPrice === '' || $price === false || $price < 0) {
                throw new InvalidArgumentException("Enter a valid non-negative price for {$label}.");
            }
            $validatedPrices[$code] = (float) $price;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE surgeon_governorate_prices
            SET price = :price, is_available = :is_available
            WHERE governorate_code = :governorate_code
        ");
        foreach ($validatedPrices as $code => $price) {
            $stmt->execute([
                ':price' => $price,
                ':is_available' => isset($submittedAvailability[$code]) ? 1 : 0,
                ':governorate_code' => $code,
            ]);
        }
        $pdo->commit();

        $_SESSION['surgeon_travel_pricing_csrf_token'] = bin2hex(random_bytes(32));
        $success = 'Surgeon travel prices updated successfully.';
    }

    $settings = getSurgeonGovernorateSettings($pdo);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Surgeon Travel Pricing Error: ' . $e->getMessage());
    $settings = $settings ?? array_map(
        fn($label) => ['label' => $label, 'price' => 0, 'is_available' => true],
        $governorates
    );
    $error = $e instanceof InvalidArgumentException
        ? $e->getMessage()
        : 'Could not load or save surgeon travel prices.';
}
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-[#13324a]">Surgeon Travel Pricing</h2>
        <p class="mt-1 text-sm text-slate-500">Choose the governorates available for clinic registration and set the Uber/travel amount included in new dental-implant surgeon requests. Existing requests keep their saved price.</p>
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

<form method="POST" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['surgeon_travel_pricing_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <div class="overflow-x-auto">
        <table class="w-full whitespace-nowrap text-left text-sm">
            <thead class="border-b-2 border-slate-100 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-6 py-4">Governorate</th>
                    <th class="px-6 py-4">Available for delivery</th>
                    <th class="px-6 py-4">Travel Price (EGP)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                <?php foreach ($governorates as $code => $label): ?>
                    <tr class="transition hover:bg-slate-50/60">
                        <td class="px-6 py-4 font-bold text-[#13324a]"><?= htmlspecialchars($label) ?></td>
                        <td class="px-6 py-3">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600">
                                <input type="checkbox" name="available[<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>]" value="1" <?= !empty($settings[$code]['is_available']) ? 'checked' : '' ?> class="rounded border-slate-300 text-[#1d5f8c] focus:ring-[#1d5f8c]">
                                Enabled
                            </label>
                        </td>
                        <td class="px-6 py-3">
                            <input type="number" name="prices[<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>]" min="0" step="0.01" required value="<?= htmlspecialchars(number_format((float) ($settings[$code]['price'] ?? 0), 2, '.', '')) ?>" class="w-48 rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-[#13324a] transition focus:border-[#1d5f8c] focus:ring-2 focus:ring-[#1d5f8c]">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-6 py-5">
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#13324a] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#1d5f8c]">
            <i class="fa-solid fa-floppy-disk mr-2"></i> Save Travel Prices
        </button>
    </div>
</form>

<?php require_once 'includes/admin_footer.php'; ?>
