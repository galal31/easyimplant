<?php

require_once __DIR__ . '/../includes/surgical_guide_pricing.php';

function assertPricingValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function guideCounts(array $overrides = []): array
{
    return array_merge(array_fill_keys(array_merge(GUIDE_UPPER_REGIONS, GUIDE_LOWER_REGIONS), 0), $overrides);
}

function pricingForRule(int $freeEvery): array
{
    return [
        'clinic_print_first_implant_price' => 1300.0,
        'admin_print_first_implant_price' => 1700.0,
        'additional_implant_price' => 300.0,
        'free_implant_every' => $freeEvery,
    ];
}

$ruleTen = calculateSurgicalGuidePrice(
    guideCounts(['upper_anterior' => 1]),
    'clinic_print',
    9,
    pricingForRule(10)
);
assertPricingValue(1, $ruleTen['free_implants'], 'The configured interval must control free implants.');
assertPricingValue(1000.0, $ruleTen['total_price'], 'The saved discount must use the configured additional implant price.');

$ruleTwelve = calculateSurgicalGuidePrice(
    guideCounts(['upper_anterior' => 1]),
    'clinic_print',
    9,
    pricingForRule(12)
);
assertPricingValue(0, $ruleTwelve['free_implants'], 'A different configured interval must produce a different result.');
assertPricingValue(1300.0, $ruleTwelve['total_price'], 'The first implant price must remain intact when no free implant is earned.');

$twoArches = calculateSurgicalGuidePrice(
    guideCounts(['upper_anterior' => 2, 'lower_anterior' => 2]),
    'admin_print',
    0,
    pricingForRule(12)
);
assertPricingValue(2000.0, $twoArches['upper_subtotal'], 'The first implant price must be applied to the upper arch.');
assertPricingValue(2000.0, $twoArches['lower_subtotal'], 'The first implant price must be applied independently to the lower arch.');
assertPricingValue(4000.0, $twoArches['total_price'], 'Both arch subtotals must be included.');

$multipleRewards = calculateSurgicalGuidePrice(
    guideCounts(['upper_anterior' => 13]),
    'clinic_print',
    23,
    pricingForRule(12)
);
assertPricingValue(2, $multipleRewards['free_implants'], 'One request may cross more than one configured reward boundary.');

$protectedRule = calculateClinicFreeImplantAllocation(1, 3, 5, 7);
assertPricingValue(0, $protectedRule['free_implants'], 'Changing the admin default must not replace the clinic protected rule mid-cycle.');
assertPricingValue(4, $protectedRule['progress_after'], 'The clinic must continue its protected cycle without a reset.');
assertPricingValue(5, $protectedRule['active_free_implant_every_after'], 'The old rule must remain active until its reward boundary is reached.');

$crossToNewRule = calculateClinicFreeImplantAllocation(3, 3, 5, 7);
assertPricingValue(1, $crossToNewRule['free_implants'], 'The request must earn the reward that closes the protected rule.');
assertPricingValue(1, $crossToNewRule['progress_after'], 'Remaining implants must continue in the new cycle.');
assertPricingValue(7, $crossToNewRule['active_free_implant_every_after'], 'The latest admin default must become active after closing the old cycle.');
assertPricingValue(2, count($crossToNewRule['rule_path']), 'The snapshot must record both rules used by a crossing request.');

$multipleProtectedRewards = calculateClinicFreeImplantAllocation(16, 3, 5, 7);
assertPricingValue(3, $multipleProtectedRewards['free_implants'], 'One request may close the protected cycle and multiple new cycles.');
assertPricingValue(3, count($multipleProtectedRewards['rule_path']), 'Every crossed reward segment must be preserved in the snapshot.');

$protectedPrice = calculateSurgicalGuidePrice(
    guideCounts(['upper_anterior' => 3]),
    'clinic_print',
    3,
    array_merge(pricingForRule(7), [
        'clinic_free_implant_every' => 5,
        'clinic_free_progress' => 3,
        'next_free_implant_every' => 7,
    ])
);
assertPricingValue(1, $protectedPrice['free_implants'], 'Server pricing must use the clinic protected rule before the new default.');
assertPricingValue(1600.0, $protectedPrice['total_price'], 'The saved discount must match the protected-rule allocation.');
assertPricingValue(1, $protectedPrice['free_progress_after'], 'Server pricing must save the new-cycle remainder.');

$invalidSettingsRejected = false;
try {
    calculateSurgicalGuidePrice(guideCounts(['upper_anterior' => 1]), 'clinic_print', 0, []);
} catch (InvalidArgumentException) {
    $invalidSettingsRejected = true;
}
assertPricingValue(true, $invalidSettingsRejected, 'Missing runtime pricing settings must not silently fall back to 12.');

echo "Surgical guide pricing tests passed.\n";
