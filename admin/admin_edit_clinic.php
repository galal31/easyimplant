<?php
// admin_edit_clinic.php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/locations.php';

$allEgyptGovernorates = getEgyptGovernorates();
$egyptGovernorates = getAvailableEgyptGovernorates($pdo);
$outsideEgyptCountries = getOutsideEgyptCountries();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = $id !== false && $id > 0;

$errors = [];
$success = '';

// Default values
$full_name = '';
$clinic_name = '';
$email = '';
$phone = '';
$country = 'egypt';
$governorate = '';
$location_scope = 'inside_egypt';
$outside_country = '';
$stored_country = null;
$stored_governorate = null;
$status = 'approved';

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT full_name, clinic_name, email, phone, country, governorate, status FROM users WHERE id = :id AND role = 'clinic'");
    $stmt->execute([':id' => $id]);
    $clinic = $stmt->fetch();
    if (!$clinic) {
        die("Clinic not found.");
    }
    $full_name = $clinic['full_name'];
    $clinic_name = $clinic['clinic_name'];
    $email = $clinic['email'];
    $phone = $clinic['phone'];
    $country = $clinic['country'];
    $stored_country = $country;
    $governorate = $clinic['governorate'] ?? '';
    $stored_governorate = $governorate;
    $location_scope = strtolower($country) === 'egypt' ? 'inside_egypt' : 'outside_egypt';
    $outside_country = $location_scope === 'outside_egypt' ? $country : '';
    $status = $clinic['status'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect data
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING));
    $clinic_name = trim(filter_input(INPUT_POST, 'clinic_name', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $location_scope = trim((string) filter_input(INPUT_POST, 'location_scope', FILTER_SANITIZE_STRING));
    $governorate = trim((string) filter_input(INPUT_POST, 'governorate', FILTER_SANITIZE_STRING));
    $outside_country = trim((string) filter_input(INPUT_POST, 'outside_country', FILTER_SANITIZE_STRING));
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($full_name) || empty($clinic_name) || empty($email) || empty($phone) || empty($location_scope) || empty($status)) {
        $errors[] = 'All fields except password are required.';
    }

    if ($location_scope === 'inside_egypt') {
        $canKeepUnavailableGovernorate = $is_edit
            && $governorate === $stored_governorate
            && array_key_exists($governorate, $allEgyptGovernorates);
        if (!array_key_exists($governorate, $egyptGovernorates) && !$canKeepUnavailableGovernorate) {
            $errors[] = 'Please select an available governorate.';
        }
        $country = 'egypt';
        $outside_country = '';
    } elseif ($location_scope === 'outside_egypt') {
        $validOutsideCountries = getOutsideEgyptCountries();
        $legacyOutsideCountry = $is_edit && $outside_country === $stored_country && !in_array($outside_country, $validOutsideCountries, true)
            ? $stored_country
            : null;
        if (!in_array($outside_country, $validOutsideCountries, true) && $outside_country !== $legacyOutsideCountry) {
            $errors[] = 'Please enter a valid country name.';
        }
        $country = $outside_country;
        $governorate = null;
    } else {
        $errors[] = 'Invalid clinic location selection.';
        $country = '';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if (!$is_edit && empty($password)) {
        $errors[] = 'Password is required for new clinics.';
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name, clinic_name = :clinic_name, email = :email, phone = :phone, country = :country, governorate = :governorate, status = :status, password = :password WHERE id = :id AND role = 'clinic'");
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':clinic_name' => $clinic_name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':country' => $country,
                        ':governorate' => $governorate,
                        ':status' => $status,
                        ':password' => $hashed_password,
                        ':id' => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name, clinic_name = :clinic_name, email = :email, phone = :phone, country = :country, governorate = :governorate, status = :status WHERE id = :id AND role = 'clinic'");
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':clinic_name' => $clinic_name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':country' => $country,
                        ':governorate' => $governorate,
                        ':status' => $status,
                        ':id' => $id
                    ]);
                }
                $success = 'Clinic updated successfully.';
            } else {
                // Check if email exists
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt_check->execute([':email' => $email]);
                if ($stmt_check->fetch()) {
                    $errors[] = 'Email is already registered.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, clinic_name, email, password, phone, country, governorate, role, status) VALUES (:full_name, :clinic_name, :email, :password, :phone, :country, :governorate, 'clinic', :status)");
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':clinic_name' => $clinic_name,
                        ':email' => $email,
                        ':password' => $hashed_password,
                        ':phone' => $phone,
                        ':country' => $country,
                        ':governorate' => $governorate,
                        ':status' => $status
                    ]);
                    $success = 'Clinic created successfully.';
                    $is_edit = true;
                    $id = $pdo->lastInsertId();
                }
            }
        } catch (\PDOException $e) {
            error_log("Edit Clinic DB Error: " . $e->getMessage());
            $errors[] = 'Database error occurred.';
        }
    }
}
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold text-[#13324a]"><?= $is_edit ? 'Edit Clinic' : 'Add New Clinic' ?></h2>
        <p class="text-slate-500 text-sm mt-1"><?= $is_edit ? 'Update existing clinic details.' : 'Create a new clinic manually.' ?></p>
    </div>
    <a href="admin_clinics.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-2">
        <i class="fa-solid fa-arrow-left"></i> Back to Clinics
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 max-w-3xl">
    <?php if(!empty($errors)): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm font-semibold">
            <ul class="list-disc list-inside ml-4">
                <?php foreach($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6 text-sm font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-[#13324a] mb-2">Doctor's Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition" placeholder="Dr. John Doe">
            </div>
            <div>
                <label class="block text-sm font-bold text-[#13324a] mb-2">Clinic Name</label>
                <input type="text" name="clinic_name" value="<?= htmlspecialchars($clinic_name) ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition" placeholder="Smile Clinic">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-[#13324a] mb-2">Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition" placeholder="clinic@example.com">
            </div>
            <div>
                <label class="block text-sm font-bold text-[#13324a] mb-2">Phone Number</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition" placeholder="+201000000000">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-[#13324a] mb-2">Clinic Location</label>
                <select name="location_scope" id="locationScope" required class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition">
                    <option value="inside_egypt" <?= $location_scope === 'inside_egypt' ? 'selected' : '' ?>>Inside Egypt</option>
                    <option value="outside_egypt" <?= $location_scope === 'outside_egypt' ? 'selected' : '' ?>>Outside Egypt</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-[#13324a] mb-2">Status</label>
                <select name="status" required class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition">
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved (Active)</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paused" <?= $status === 'paused' ? 'selected' : '' ?>>Paused</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
        </div>

        <div id="governorateField">
            <label class="block text-sm font-bold text-[#13324a] mb-2">Governorate</label>
            <select name="governorate" id="governorate" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition">
                <option value="">Select governorate</option>
                <?php if ($governorate !== '' && !array_key_exists($governorate, $egyptGovernorates) && array_key_exists($governorate, $allEgyptGovernorates)): ?>
                    <option value="<?= htmlspecialchars($governorate, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($allEgyptGovernorates[$governorate]) ?> (currently unavailable)</option>
                <?php endif; ?>
                <?php foreach ($egyptGovernorates as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $governorate === $code ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="outsideCountryField" class="hidden">
            <label class="block text-sm font-bold text-[#13324a] mb-2">Country</label>
            <select name="outside_country" id="outsideCountry" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition">
                <option value="">Select country</option>
                <?php if ($outside_country !== '' && !in_array($outside_country, $outsideEgyptCountries, true)): ?>
                    <option value="<?= htmlspecialchars($outside_country, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars(formatClinicLocation($outside_country, null)) ?> (legacy)</option>
                <?php endif; ?>
                <?php foreach ($outsideEgyptCountries as $countryName): ?>
                    <option value="<?= htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') ?>" <?= $outside_country === $countryName ? 'selected' : '' ?>><?= htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-bold text-[#13324a] mb-2">Password</label>
            <input type="password" name="password" <?= $is_edit ? '' : 'required' ?> class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-[#1d5f8c] focus:border-[#1d5f8c] bg-slate-50 focus:bg-white transition" placeholder="<?= $is_edit ? 'Leave blank to keep current password' : 'Enter new password' ?>">
        </div>

        <div class="pt-4 border-t border-slate-100 flex justify-end">
            <button type="submit" class="bg-[#13324a] hover:bg-[#1d5f8c] text-white px-6 py-3 rounded-xl text-sm font-bold transition flex items-center gap-2">
                <i class="fa-solid fa-save"></i> <?= $is_edit ? 'Save Changes' : 'Create Clinic' ?>
            </button>
        </div>
    </form>
</div>

<script>
    const locationScope = document.getElementById('locationScope');
    const governorateField = document.getElementById('governorateField');
    const governorate = document.getElementById('governorate');
    const outsideCountryField = document.getElementById('outsideCountryField');
    const outsideCountry = document.getElementById('outsideCountry');

    function syncClinicLocationFields() {
        const insideEgypt = locationScope.value === 'inside_egypt';
        governorateField.classList.toggle('hidden', !insideEgypt);
        governorate.disabled = !insideEgypt;
        governorate.required = insideEgypt;
        outsideCountryField.classList.toggle('hidden', insideEgypt);
        outsideCountry.disabled = insideEgypt;
        outsideCountry.required = !insideEgypt;
    }

    locationScope.addEventListener('change', syncClinicLocationFields);
    syncClinicLocationFields();
</script>

<?php require_once 'includes/admin_footer.php'; ?>
