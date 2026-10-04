<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/locations.php';
require_once __DIR__ . '/includes/user_language.php';
$egyptGovernorates = getAvailableEgyptGovernorates($pdo);
$outsideEgyptCountries = getOutsideEgyptCountries();
?>
<!DOCTYPE html>
<html lang="<?= userLanguageAttribute() ?>" dir="<?= userDirectionAttribute() ?>" class="scroll-smooth" data-i18n-title="register_title">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register | Easy Implant</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="css/user-i18n.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Outfit', 'Cairo', sans-serif; background: #f4f8fb; }
    </style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen px-4 py-12">

    <div class="max-w-xl w-full bg-white rounded-3xl shadow-lg border border-slate-200 p-8">
        <div class="mb-5 flex justify-end"><?php $userLanguageSwitcherCompact = true; include __DIR__ . '/includes/user_language_switcher.php'; ?></div>
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#13324a] text-white shadow-md mb-4">
                <i class="fa-solid fa-tooth text-xl"></i>
            </a>
            <h2 class="text-2xl font-bold text-[#13324a]" data-i18n="register_heading">Create Clinic Account</h2>
            <p class="text-sm text-slate-500 mt-2" data-i18n="register_intro">Join Easy Implant to start managing your requests.</p>
        </div>

        <form id="registerForm" class="space-y-5">
            <div id="errorMsg" class="hidden bg-red-50 text-red-600 p-3 rounded-lg text-sm font-medium border border-red-100"></div>
            <div id="successMsg" class="hidden bg-green-50 text-green-600 p-3 rounded-lg text-sm font-medium border border-green-100"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-[#13324a] mb-1" data-i18n="doctor_full_name">Doctor's Full Name</label>
                    <input type="text" name="full_name" required class="block w-full px-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="Dr. Ahmed Ali" data-i18n-placeholder="doctor_name_placeholder" dir="auto">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-[#13324a] mb-1" data-i18n="clinic_name">Clinic Name</label>
                    <input type="text" name="clinic_name" required class="block w-full px-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="Smile Dental Clinic" data-i18n-placeholder="clinic_name_placeholder" dir="auto">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-[#13324a] mb-1" data-i18n="email_address">Email Address</label>
                    <input type="email" name="email" required class="block w-full px-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="clinic@example.com" data-i18n-placeholder="email_placeholder" dir="ltr">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-[#13324a] mb-1" data-i18n="phone_whatsapp">Phone Number / WhatsApp</label>
                    <input type="text" name="phone" required class="block w-full px-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="+201000000000" dir="ltr">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-[#13324a] mb-1" data-i18n="password">Password</label>
                    <input type="password" name="password" required minlength="8" class="block w-full px-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a]" placeholder="Min. 8 characters" data-i18n-placeholder="password_placeholder" dir="ltr">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-[#13324a] mb-1" data-i18n="clinic_location">Clinic Location</label>
                    <select name="location_scope" id="locationScope" required class="block w-full px-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a] bg-white">
                        <option value="inside_egypt" data-i18n="inside_egypt">Inside Egypt</option>
                        <option value="outside_egypt" data-i18n="outside_egypt">Outside Egypt</option>
                    </select>
                </div>
            </div>

            <div id="governorateField">
                <label class="block text-sm font-semibold text-[#13324a] mb-1" data-i18n="governorate">Governorate</label>
                <select name="governorate" id="governorate" required class="block w-full px-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a] bg-white">
                    <option value="" data-i18n="select_governorate">Select governorate</option>
                    <?php foreach ($egyptGovernorates as $code => $label): ?>
                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$egyptGovernorates): ?>
                    <p class="mt-1 text-xs font-semibold text-orange-600" data-i18n="no_governorates">No governorates are currently available. Choose Outside Egypt or contact support.</p>
                <?php endif; ?>
            </div>

            <div id="outsideCountryField" class="hidden">
                <label class="block text-sm font-semibold text-[#13324a] mb-1" data-i18n="country">Country</label>
                <select name="outside_country" id="outsideCountry" disabled class="block w-full px-3 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1d5f8c] focus:border-[#1d5f8c] transition text-sm text-[#13324a] bg-white">
                    <option value="" data-i18n="select_country">Select country</option>
                    <?php foreach ($outsideEgyptCountries as $countryName): ?>
                        <option value="<?= htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" id="submitBtn" class="w-full flex justify-center mt-4 py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-[#1d5f8c] hover:bg-[#13324a] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1d5f8c] transition disabled:opacity-70">
                <span data-i18n="create_account">Create Account</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <span data-i18n="have_account">Already have an account?</span> <a href="login.php" class="font-bold text-[#1d5f8c] hover:underline" data-i18n="sign_in">Sign In</a>
        </p>
    </div>

    <script src="js/translations.js"></script>
    <script src="js/user-page-translations.js"></script>
    <script src="js/main.js"></script>
    <script>
        const locationScope = document.getElementById('locationScope');
        const governorateField = document.getElementById('governorateField');
        const governorate = document.getElementById('governorate');
        const outsideCountryField = document.getElementById('outsideCountryField');
        const outsideCountry = document.getElementById('outsideCountry');

        function syncLocationFields() {
            const insideEgypt = locationScope.value === 'inside_egypt';
            governorateField.classList.toggle('hidden', !insideEgypt);
            governorate.disabled = !insideEgypt;
            governorate.required = insideEgypt;
            outsideCountryField.classList.toggle('hidden', insideEgypt);
            outsideCountry.disabled = insideEgypt;
            outsideCountry.required = !insideEgypt;
        }

        locationScope.addEventListener('change', syncLocationFields);
        syncLocationFields();

        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('submitBtn');
            const errorMsg = document.getElementById('errorMsg');
            const successMsg = document.getElementById('successMsg');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> ' + i18nText('creating_account', 'Creating account...');
            errorMsg.classList.add('hidden');
            successMsg.classList.add('hidden');

            try {
                const response = await fetch('api/register.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                
                const data = await response.json();

                if (response.ok) {
                    form.reset();
                    syncLocationFields();
                    successMsg.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> ' + i18nText('account_created', data.success);
                    successMsg.classList.remove('hidden');
                    setTimeout(() => { window.location.href = 'login.php'; }, 3000);
                } else {
                    errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation me-1"></i> ' + i18nApiMessage(data.error, 'generic_error');
                    errorMsg.classList.remove('hidden');
                }
            } catch (err) {
                errorMsg.innerHTML = '<i class="fa-solid fa-wifi me-1"></i> ' + i18nText('network_error', 'Network error. Please check your connection.');
                errorMsg.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.textContent = i18nText('create_account', 'Create Account');
            }
        });
    </script>
</body>
</html>
