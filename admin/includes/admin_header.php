<?php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/system_settings.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/admin_table.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Administrator';
$current_page = basename($_SERVER['PHP_SELF']);
$adminSystemIconUrl = getSystemIconUrl($pdo, '../');
$adminNav = [
    ['admin_dashboard.php', 'Dashboard', 'fa-chart-pie', ['admin_dashboard.php']],
    ['admin_clinics.php', 'Clinics', 'fa-hospital-user', ['admin_clinics.php', 'admin_clinic_accounts.php', 'admin_clinic_account.php', 'admin_edit_clinic.php']],
    ['admin_requests.php', 'Requests', 'fa-list-check', ['admin_requests.php', 'admin_view_request.php']],
    ['admin_implant_types.php', 'Implants', 'fa-tooth', ['admin_implant_types.php']],
    ['admin_guide_pricing.php', 'Pricing', 'fa-tags', ['admin_guide_pricing.php']],
    ['admin_surgeon_travel_pricing.php', 'Travel', 'fa-car', ['admin_surgeon_travel_pricing.php']],
    ['admin_surgeon_services.php', 'Services', 'fa-user-doctor', ['admin_surgeon_services.php']],
    ['admin_videos.php', 'Case Videos', 'fa-video', ['admin_videos.php']],
    ['admin_doctors.php', 'Doctors', 'fa-user-doctor', ['admin_doctors.php']],
    ['admin_settings.php', 'Settings', 'fa-gear', ['admin_settings.php']],
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/case-videos.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">
    <div id="admin-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/60 lg:hidden"></div>
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-[#13324a] text-white shadow-xl transition-transform duration-200 lg:translate-x-0" aria-label="Admin navigation">
        <div class="flex h-20 items-center gap-3 border-b border-white/10 px-5">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10">
                <?php if ($adminSystemIconUrl): ?><img src="<?= htmlspecialchars($adminSystemIconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Easy Implant" class="h-7 w-7 object-contain"><?php else: ?><i class="fa-solid fa-tooth text-sm"></i><?php endif; ?>
            </div>
            <span class="font-bold text-lg">Admin Panel</span>
            <button id="admin-sidebar-close" type="button" class="ml-auto rounded-lg p-2 text-slate-300 hover:bg-white/10 lg:hidden" aria-label="Close navigation"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Admin pages">
            <?php foreach ($adminNav as [$href, $label, $icon, $activePages]): ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition <?= in_array($current_page, $activePages, true) ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>" <?= in_array($current_page, $activePages, true) ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> w-5 text-center" aria-hidden="true"></i><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="border-t border-white/10 p-4">
            <p class="truncate text-sm font-bold"><?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mb-3 text-xs text-slate-300">Administrator</p>
            <a href="../logout.php" class="flex items-center gap-3 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-semibold transition hover:bg-red-500"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
        </div>
    </aside>
    <div class="min-h-screen lg:pl-64">
        <div class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm lg:hidden">
            <button id="admin-sidebar-open" type="button" class="rounded-lg p-2 text-[#13324a]" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button>
            <span class="font-bold text-[#13324a]">Admin Panel</span>
            <span class="w-9" aria-hidden="true"></span>
        </div>
        <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
