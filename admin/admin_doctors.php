<?php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/doctors.php';

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$emptyDoctor = ['id'=>0, 'display_name'=>'', 'slug'=>'', 'specialty'=>'', 'short_bio'=>'', 'qualifications'=>'', 'photo_path'=>null, 'user_id'=>'', 'is_published'=>0];
$doctorForm = $emptyDoctor;
$doctorError = '';
$doctorSuccess = '';
if (empty($_SESSION['doctors_csrf_token'])) $_SESSION['doctors_csrf_token'] = bin2hex(random_bytes(32));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['doctors_csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) throw new InvalidArgumentException('Your session expired. Refresh and try again.');
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        if ($id === false) throw new InvalidArgumentException('Invalid doctor.');
        $existingDoctor = $id ? getDoctor($pdo, $id) : null;
        if ($id && !$existingDoctor) throw new InvalidArgumentException('Doctor not found.');
        if (($_POST['action'] ?? '') === 'delete' && $id) {
            $pdo->prepare('DELETE FROM doctors WHERE id = ?')->execute([$id]);
            removeDoctorPhoto($existingDoctor['photo_path']);
            $doctorSuccess = 'Doctor deleted. Case videos were kept.';
        } elseif (($_POST['action'] ?? '') === 'save') {
            $doctorForm = array_merge($emptyDoctor, array_intersect_key($_POST, $emptyDoctor));
            $doctorForm['id'] = $id;
            $doctorForm['is_published'] = isset($_POST['is_published']) ? 1 : 0;
            $doctorForm['photo_path'] = $existingDoctor['photo_path'] ?? null;
            $newPhoto = null;
            try {
                if (!empty($_POST['remove_photo'])) $doctorForm['photo_path'] = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newPhoto = uploadDoctorPhoto($_FILES['photo']);
                    $doctorForm['photo_path'] = $newPhoto;
                }
                saveDoctor($pdo, $doctorForm, $id);
                if ($existingDoctor && $existingDoctor['photo_path'] !== $doctorForm['photo_path']) removeDoctorPhoto($existingDoctor['photo_path']);
                $doctorSuccess = $id ? 'Doctor updated.' : 'Doctor added.';
                $doctorForm = $emptyDoctor;
            } catch (Throwable $e) {
                removeDoctorPhoto($newPhoto);
                $doctorForm['photo_path'] = $existingDoctor['photo_path'] ?? null;
                throw $e;
            }
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }
    } elseif (isset($_GET['edit'])) {
        $doctorForm = getDoctor($pdo, (int) $_GET['edit']) ?? $emptyDoctor;
        if (!$doctorForm['id']) throw new InvalidArgumentException('Doctor not found.');
    }
} catch (InvalidArgumentException $e) {
    $doctorError = $e->getMessage();
} catch (Throwable $e) {
    error_log('Doctor management failed: ' . $e->getMessage());
    $doctorError = 'Could not save the change. Check that the doctor profiles migration is applied.';
}

try {
    $allDoctors = $pdo->query('SELECT d.*, u.full_name AS account_name FROM doctors d LEFT JOIN users u ON u.id=d.user_id ORDER BY d.display_name, d.id')->fetchAll();
    $surgeonAccounts = $pdo->query("SELECT id,full_name FROM users WHERE role='surgeon' ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    error_log('Doctor list failed: ' . $e->getMessage());
    $allDoctors = $surgeonAccounts = [];
    $doctorError = 'Apply the doctor profiles migration to start managing doctors.';
}
$doctorTable = adminTableState($allDoctors, ['display_name','specialty','slug','account_name'], 'doctors');
?>
<div class="mb-6"><h2 class="text-2xl font-bold text-[#13324a]">Doctors</h2><p class="mt-1 text-sm text-slate-500">Create a public profile and select its doctor in case videos.</p></div>
<?php if ($doctorError): ?><div role="alert" class="mb-6 rounded-xl bg-red-50 p-4 text-sm text-red-700"><?= $escape($doctorError) ?></div><?php endif; ?>
<?php if ($doctorSuccess): ?><div role="status" class="mb-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700"><?= $escape($doctorSuccess) ?></div><?php endif; ?>
<div class="grid gap-6 xl:grid-cols-[minmax(300px,1fr)_minmax(0,2fr)]">
    <form method="post" enctype="multipart/form-data" class="space-y-4 self-start rounded-2xl border border-slate-200 bg-white p-6">
        <h3 class="text-lg font-bold text-[#13324a]"><?= $doctorForm['id'] ? 'Edit doctor' : 'Add doctor' ?></h3>
        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['doctors_csrf_token']) ?>"><input type="hidden" name="id" value="<?= (int) $doctorForm['id'] ?>"><input type="hidden" name="action" value="save">
        <div><label for="doctor-name" class="mb-2 block text-sm font-semibold">Doctor name</label><input id="doctor-name" name="display_name" required maxlength="190" value="<?= $escape($doctorForm['display_name']) ?>" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"></div>
        <div><label for="doctor-slug" class="mb-2 block text-sm font-semibold">Profile link (slug)</label><input id="doctor-slug" name="slug" maxlength="160" value="<?= $escape($doctorForm['slug']) ?>" placeholder="doctor-name" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><p class="mt-2 text-xs leading-5 text-slate-500">Leave blank to generate it from the name. An existing link stays the same unless you change it here.</p></div>
        <div><label for="doctor-specialty" class="mb-2 block text-sm font-semibold">Specialty</label><input id="doctor-specialty" name="specialty" maxlength="190" value="<?= $escape($doctorForm['specialty']) ?>" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"></div>
        <div><label for="doctor-bio" class="mb-2 block text-sm font-semibold">Biography</label><textarea id="doctor-bio" name="short_bio" rows="4" maxlength="10000" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><?= $escape($doctorForm['short_bio']) ?></textarea></div>
        <div><label for="doctor-qualifications" class="mb-2 block text-sm font-semibold">Qualifications (optional)</label><textarea id="doctor-qualifications" name="qualifications" rows="3" maxlength="10000" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><?= $escape($doctorForm['qualifications']) ?></textarea><p class="mt-1 text-xs text-slate-500">Write each qualification on its own line.</p></div>
        <div><label for="doctor-photo" class="mb-2 block text-sm font-semibold">Portrait photo (optional)</label><input id="doctor-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="max-w-full text-sm"><p class="mt-2 text-xs text-slate-500">JPG, PNG, or WebP. Maximum 5 MB. A portrait crop works best.</p>
            <?php if ($doctorForm['photo_path']): ?><img src="../<?= $escape($doctorForm['photo_path']) ?>" alt="Current portrait" class="mt-3 h-32 w-24 rounded-lg object-cover"><label class="mt-2 flex gap-2 text-sm"><input type="checkbox" name="remove_photo" value="1">Remove current photo</label><?php endif; ?>
        </div>
        <div><label for="doctor-account" class="mb-2 block text-sm font-semibold">Surgeon account (optional)</label><select id="doctor-account" name="user_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><option value="">No linked account</option><?php foreach ($surgeonAccounts as $account): ?><option value="<?= (int) $account['id'] ?>" <?= (string) $doctorForm['user_id'] === (string) $account['id'] ? 'selected' : '' ?>><?= $escape($account['full_name']) ?></option><?php endforeach; ?></select></div>
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_published" value="1" <?= $doctorForm['is_published'] ? 'checked' : '' ?>>Publish public profile</label>
        <button type="submit" class="w-full rounded-xl bg-[#13324a] px-4 py-3 text-sm font-bold text-white hover:bg-[#1d5f8c]">Save doctor</button>
        <?php if ($doctorForm['id']): ?><a href="admin_doctors.php" class="block text-center text-sm text-slate-600">Cancel editing</a><?php endif; ?>
    </form>
    <div class="min-w-0 self-start overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <?php adminTableToolbar('doctors', $doctorTable, 'Search doctors...'); ?>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-4">Doctor</th><th class="px-5 py-4">Profile link</th><th class="px-5 py-4">Visibility</th><th class="px-5 py-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100" data-admin-table-body="doctors">
                <?php if (!$doctorTable['rows']): ?><tr><td colspan="4" class="p-8 text-center text-slate-500">No doctors found.</td></tr><?php endif; ?>
                <?php foreach ($doctorTable['rows'] as $doctor): ?><tr>
                    <td class="px-5 py-4"><p class="font-bold text-[#13324a]"><?= $escape($doctor['display_name']) ?></p><p class="mt-1 text-xs text-slate-500"><?= $escape($doctor['specialty']) ?></p><?php if ($doctor['account_name']): ?><p class="mt-1 text-xs text-slate-500">Account: <?= $escape($doctor['account_name']) ?></p><?php endif; ?></td>
                    <td class="px-5 py-4"><span class="break-all text-xs"><?= $escape($doctor['slug']) ?></span><?php if ($doctor['is_published']): ?><a href="../<?= $escape(doctorProfileUrl($doctor['slug'])) ?>" target="_blank" rel="noopener" class="mt-2 block font-semibold text-[#1d5f8c]">View profile ↗</a><?php endif; ?></td>
                    <td class="px-5 py-4 text-xs"><?= $doctor['is_published'] ? 'Published' : 'Draft' ?></td>
                    <td class="px-5 py-4"><div class="flex items-center gap-3"><a href="?edit=<?= (int) $doctor['id'] ?>" class="font-semibold text-[#1d5f8c]">Edit</a><form method="post" onsubmit="return confirm('Delete this doctor and remove their links from case videos? The videos will be kept.');"><input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['doctors_csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $doctor['id'] ?>"><button class="font-semibold text-red-600">Delete</button></form></div></td>
                </tr><?php endforeach; ?>
            </tbody>
        </table></div>
        <?php adminTablePagination('doctors', $doctorTable); ?>
    </div>
</div>
<?php require_once 'includes/admin_footer.php'; ?>
