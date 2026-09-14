<?php
require_once 'includes/admin_header.php';

$success = '';
$error = '';
$maxIconSize = 2 * 1024 * 1024;
$currentIconPath = getSystemIconPath($pdo);

if (empty($_SESSION['settings_csrf_token'])) {
    $_SESSION['settings_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $file = $_FILES['system_icon'] ?? null;

    if (!hash_equals($_SESSION['settings_csrf_token'], $csrfToken)) {
        $error = 'Your session token is invalid. Refresh the page and try again.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Choose a valid icon file before saving.';
    } elseif ((int) $file['size'] < 1 || (int) $file['size'] > $maxIconSize) {
        $error = 'The icon must be smaller than 2 MB.';
    } else {
        $allowedMimeTypes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($file['tmp_name']);
        $imageSize = @getimagesize($file['tmp_name']);

        if (!isset($allowedMimeTypes[$mimeType]) || $imageSize === false) {
            $error = 'Use a real PNG, JPG, or WebP image.';
        } elseif ($imageSize[0] < 32 || $imageSize[1] < 32 || $imageSize[0] > 2048 || $imageSize[1] > 2048) {
            $error = 'Icon dimensions must be between 32×32 and 2048×2048 pixels.';
        } else {
            $uploadDirectory = __DIR__ . '/../uploads/settings';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
                $error = 'Could not prepare the icon upload directory.';
            } else {
                $filename = 'system-icon-' . bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
                $absolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                $databasePath = 'uploads/settings/' . $filename;

                if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                    $error = 'Could not save the uploaded icon.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        setSystemSetting($pdo, 'system_icon_path', $databasePath, 'image', 'branding', (int) $_SESSION['user_id']);
                        $pdo->commit();

                        if ($currentIconPath && $currentIconPath !== $databasePath) {
                            $oldAbsolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentIconPath);
                            $resolvedUploadDirectory = realpath($uploadDirectory);
                            $resolvedOldPath = realpath($oldAbsolutePath);
                            if ($resolvedUploadDirectory && $resolvedOldPath && dirname($resolvedOldPath) === $resolvedUploadDirectory) {
                                @unlink($resolvedOldPath);
                            }
                        }

                        $currentIconPath = $databasePath;
                        $_SESSION['settings_csrf_token'] = bin2hex(random_bytes(32));
                        $success = 'System icon updated successfully.';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        @unlink($absolutePath);
                        error_log('System Icon Update Error: ' . $e->getMessage());
                        $error = 'Could not save the system icon setting. Make sure the system settings migration is applied.';
                    }
                }
            }
        }
    }
}

$currentSystemIconUrl = getSystemIconUrl($pdo, '../');
?>

<div class="mb-6">
    <p class="mb-1 text-xs font-bold uppercase tracking-[0.18em] text-[#1d5f8c]">System</p>
    <h2 class="text-2xl font-bold text-[#13324a]">Settings</h2>
    <p class="mt-1 text-sm text-slate-500">Manage shared branding used across login and dashboard headers.</p>
</div>

<?php if ($success): ?>
    <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">
        <i class="fa-solid fa-circle-check"></i>
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 px-5 py-4 text-sm font-semibold text-red-600">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
    <form method="POST" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="border-b border-slate-100 pb-5">
            <h3 class="text-lg font-bold text-[#13324a]">System icon</h3>
            <p class="mt-1 text-sm leading-6 text-slate-500">Upload one square image and the system will reuse it consistently in the login page and both dashboard headers.</p>
        </div>

        <label for="system_icon" class="mt-6 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center transition hover:border-[#1d5f8c] hover:bg-blue-50/40 focus-within:border-[#1d5f8c] focus-within:ring-2 focus-within:ring-[#1d5f8c]/20">
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-xl text-[#1d5f8c] shadow-sm ring-1 ring-slate-200">
                <i class="fa-solid fa-cloud-arrow-up"></i>
            </span>
            <span class="mt-4 text-sm font-bold text-[#13324a]">Choose a new system icon</span>
            <span class="mt-1 text-xs leading-5 text-slate-500">PNG, JPG, or WebP · 32–2048 px · maximum 2 MB</span>
            <input id="system_icon" name="system_icon" type="file" accept="image/png,image/jpeg,image/webp" required class="sr-only">
            <span id="selectedIconName" class="mt-3 hidden rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-[#1d5f8c]"></span>
        </label>

        <div class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs leading-5 text-slate-500">The previous uploaded icon is replaced only after the new setting is saved successfully.</p>
            <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-[#13324a] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#1d5f8c] focus:outline-none focus:ring-2 focus:ring-[#1d5f8c] focus:ring-offset-2">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Save icon
            </button>
        </div>
    </form>

    <aside class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50 px-6 py-5">
            <h3 class="text-lg font-bold text-[#13324a]">Live placement preview</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">Select a file to preview all three placements before saving.</p>
        </div>

        <div class="space-y-4 p-6">
            <div class="rounded-2xl border border-slate-200 p-4">
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Login</p>
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-[#13324a] text-white shadow-md">
                        <img data-icon-preview-image src="<?= htmlspecialchars($currentSystemIconUrl ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="System icon preview" class="h-9 w-9 object-contain <?= $currentSystemIconUrl ? '' : 'hidden' ?>">
                        <i data-icon-preview-fallback class="fa-solid fa-tooth text-xl <?= $currentSystemIconUrl ? 'hidden' : '' ?>"></i>
                    </span>
                    <span><strong class="block text-sm text-[#13324a]">Welcome Back</strong><small class="text-xs text-slate-400">Login card</small></span>
                </div>
            </div>

            <div class="rounded-2xl bg-[#13324a] p-4 text-white">
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-300">Admin header</p>
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-white/10">
                        <img data-icon-preview-image src="<?= htmlspecialchars($currentSystemIconUrl ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="System icon preview" class="h-7 w-7 object-contain <?= $currentSystemIconUrl ? '' : 'hidden' ?>">
                        <i data-icon-preview-fallback class="fa-solid fa-tooth text-sm <?= $currentSystemIconUrl ? 'hidden' : '' ?>"></i>
                    </span>
                    <strong class="text-sm">Admin Panel</strong>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4">
                <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Clinic header</p>
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-[#13324a] text-white">
                        <img data-icon-preview-image src="<?= htmlspecialchars($currentSystemIconUrl ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="System icon preview" class="h-7 w-7 object-contain <?= $currentSystemIconUrl ? '' : 'hidden' ?>">
                        <i data-icon-preview-fallback class="fa-solid fa-tooth text-sm <?= $currentSystemIconUrl ? 'hidden' : '' ?>"></i>
                    </span>
                    <strong class="text-sm text-[#13324a]">Easy Implant</strong>
                </div>
            </div>
        </div>
    </aside>
</div>

<script>
const systemIconInput = document.getElementById('system_icon');
const selectedIconName = document.getElementById('selectedIconName');
let selectedIconPreviewUrl = null;

systemIconInput.addEventListener('change', () => {
    const file = systemIconInput.files[0];
    if (!file) return;

    if (selectedIconPreviewUrl) URL.revokeObjectURL(selectedIconPreviewUrl);
    selectedIconPreviewUrl = URL.createObjectURL(file);

    document.querySelectorAll('[data-icon-preview-image]').forEach((image) => {
        image.src = selectedIconPreviewUrl;
        image.classList.remove('hidden');
    });
    document.querySelectorAll('[data-icon-preview-fallback]').forEach((fallback) => fallback.classList.add('hidden'));

    selectedIconName.textContent = file.name;
    selectedIconName.classList.remove('hidden');
});

window.addEventListener('beforeunload', () => {
    if (selectedIconPreviewUrl) URL.revokeObjectURL(selectedIconPreviewUrl);
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
