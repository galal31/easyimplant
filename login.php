<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/system_settings.php';
$loginSystemIconUrl = getSystemIconUrl($pdo);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }
    </style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen px-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-lg border border-slate-200 p-8">
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#13324a] text-white shadow-md mb-4">
                <?php if ($loginSystemIconUrl): ?>
                    <img src="<?= htmlspecialchars($loginSystemIconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Easy Implant" class="h-9 w-9 object-contain">
                <?php else: ?>
                    <i class="fa-solid fa-tooth text-xl"></i>
                <?php endif; ?>
            </a>
            <h2 class="text-2xl font-bold text-[#13324a]">Welcome Back</h2>
            <p class="text-sm text-slate-500 mt-2">Sign in to your clinic account to manage requests.</p>
        </div>

        <form id="loginForm" class="space-y-5">
            <div id="errorMsg" class="hidden bg-red-50 text-red-600 p-3 rounded-lg text-sm font-medium border border-red-100"></div>

            <div>
                <label class="block text-sm font-semibold text-[#13324a] mb-1">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-regular fa-envelope"></i>
                    </div>
                    <input type="email" id="email" name="email" required class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a] placeholder-slate-400" placeholder="clinic@example.com">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-[#13324a] mb-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <input type="password" id="password" name="password" required class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a] placeholder-slate-400" placeholder="••••••••">
                </div>
            </div>

            <button type="submit" id="submitBtn" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-[#1d5f8c] hover:bg-[#13324a] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1d5f8c] transition disabled:opacity-70">
                Sign In
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Don't have a clinic account? <a href="register.php" class="font-bold text-[#1d5f8c] hover:underline">Register here</a>
        </p>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('submitBtn');
            const errorMsg = document.getElementById('errorMsg');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Signing in...';
            errorMsg.classList.add('hidden');

            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                
                const data = await response.json();

                if (response.ok) {
                    window.location.href = data.redirect;
                } else {
                    errorMsg.textContent = data.error || 'An error occurred. Please try again.';
                    errorMsg.classList.remove('hidden');
                }
            } catch (err) {
                errorMsg.textContent = 'Network error. Please check your connection.';
                errorMsg.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Sign In';
            }
        });
    </script>
</body>
</html>
