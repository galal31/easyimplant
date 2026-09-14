<?php
$clinicSection = $clinicSection ?? 'management';
$clinicTabs = [
    'management' => [
        'href' => 'admin_clinics.php',
        'icon' => 'fa-hospital-user',
        'label' => 'Clinic Management',
        'description' => 'Access & status',
    ],
    'accounts' => [
        'href' => 'admin_clinic_accounts.php',
        'icon' => 'fa-wallet',
        'label' => 'Clinic Accounts',
        'description' => 'Finance & implants',
    ],
];
?>

<nav aria-label="Clinic sections" class="mb-6 overflow-x-auto pb-1">
    <div class="inline-flex min-w-full gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm sm:min-w-0">
        <?php foreach ($clinicTabs as $key => $tab): ?>
            <?php $isActive = $clinicSection === $key; ?>
            <a
                href="<?= htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8') ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
                class="flex min-w-[190px] flex-1 items-center gap-3 rounded-xl px-4 py-3 transition focus:outline-none focus:ring-2 focus:ring-[#1d5f8c] focus:ring-offset-2 <?= $isActive ? 'bg-[#13324a] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-[#13324a]' ?>"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg <?= $isActive ? 'bg-white/10 text-white' : 'bg-slate-100 text-[#1d5f8c]' ?>">
                    <i class="fa-solid <?= htmlspecialchars($tab['icon'], ENT_QUOTES, 'UTF-8') ?> text-sm"></i>
                </span>
                <span class="text-left">
                    <span class="block text-sm font-bold"><?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block text-[11px] font-medium <?= $isActive ? 'text-slate-300' : 'text-slate-400' ?>"><?= htmlspecialchars($tab['description'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
