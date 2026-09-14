<?php
// admin_header.php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/system_settings.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/admin_table.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'];
$current_page = basename($_SERVER['PHP_SELF']);
$adminSystemIconUrl = getSystemIconUrl($pdo, '../');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased flex flex-col min-h-screen">

    <!-- Top Navigation -->
    <nav class="bg-[#13324a] text-white border-b border-[#0f2233] sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white">
                            <?php if ($adminSystemIconUrl): ?>
                                <img src="<?= htmlspecialchars($adminSystemIconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Easy Implant" class="h-7 w-7 object-contain">
                            <?php else: ?>
                                <i class="fa-solid fa-tooth text-sm"></i>
                            <?php endif; ?>
                        </div>
                        <span class="font-bold text-white text-lg hidden sm:block">Admin Panel</span>
                    </div>
                    
                    <!-- Navigation Links -->
                    <div class="hidden md:flex space-x-2">
                        <a href="admin_dashboard.php" class="px-3 py-2 rounded-lg text-sm font-semibold transition <?= $current_page == 'admin_dashboard.php' ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>"><i class="fa-solid fa-chart-pie mr-1.5"></i> Dashboard</a>
                        <a href="admin_clinics.php" class="px-3 py-2 rounded-lg text-sm font-semibold transition <?= in_array($current_page, ['admin_clinics.php', 'admin_clinic_accounts.php', 'admin_clinic_account.php', 'admin_edit_clinic.php'], true) ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>"><i class="fa-solid fa-hospital-user mr-1.5"></i> Clinics</a>
                        <a href="admin_requests.php" class="px-3 py-2 rounded-lg text-sm font-semibold transition <?= $current_page == 'admin_requests.php' ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>"><i class="fa-solid fa-list-check mr-1.5"></i> Requests</a>
                        <a href="admin_implant_types.php" class="px-3 py-2 rounded-lg text-sm font-semibold transition <?= $current_page == 'admin_implant_types.php' ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>"><i class="fa-solid fa-tooth mr-1.5"></i> Implants</a>
                        <a href="admin_guide_pricing.php" class="px-3 py-2 rounded-lg text-sm font-semibold transition <?= $current_page == 'admin_guide_pricing.php' ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>"><i class="fa-solid fa-tags mr-1.5"></i> Pricing</a>
                        <a href="admin_surgeon_travel_pricing.php" class="px-3 py-2 rounded-lg text-sm font-semibold transition <?= $current_page == 'admin_surgeon_travel_pricing.php' ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>"><i class="fa-solid fa-car mr-1.5"></i> Travel</a>
                        <a href="admin_surgeon_services.php" class="px-3 py-2 rounded-lg text-sm font-semibold transition <?= $current_page == 'admin_surgeon_services.php' ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>"><i class="fa-solid fa-user-doctor mr-1.5"></i> Services</a>
                        <a href="admin_settings.php" class="px-3 py-2 rounded-lg text-sm font-semibold transition <?= $current_page == 'admin_settings.php' ? 'bg-[#1d5f8c] text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' ?>"><i class="fa-solid fa-gear mr-1.5"></i> Settings</a>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="hidden sm:block text-right">
                        <p class="text-sm font-bold text-white leading-tight"><?= htmlspecialchars($full_name) ?></p>
                        <p class="text-[11px] font-medium text-slate-300 uppercase tracking-wide">Administrator</p>
                    </div>
                    <a href="../logout.php" class="inline-flex items-center justify-center rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-500 hover:border-red-500">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
