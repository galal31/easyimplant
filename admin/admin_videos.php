<?php
require_once 'includes/admin_header.php';
require_once __DIR__ . '/../includes/case_videos.php';

if (empty($_SESSION['case_videos_csrf_token'])) {
    $_SESSION['case_videos_csrf_token'] = bin2hex(random_bytes(32));
}
$error = '';
$success = '';
$form = ['id' => 0, 'title' => '', 'description' => '', 'video_url' => ''];
$selectedDoctors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!hash_equals($_SESSION['case_videos_csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'save') {
        $form = [
            'id' => $id ?: 0,
            'title' => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'video_url' => trim((string) ($_POST['video_url'] ?? '')),
        ];
        $doctorIds = is_array($_POST['doctor_ids'] ?? null) ? $_POST['doctor_ids'] : [];
        $contributions = is_array($_POST['contributions'] ?? null) ? $_POST['contributions'] : [];
        foreach ($doctorIds as $position => $doctorId) $selectedDoctors[] = ['doctor_id'=>$doctorId, 'contribution'=>$contributions[$position] ?? ''];
        if ($form['title'] === '' || mb_strlen($form['title']) > 255) {
            $error = 'Enter a case name of up to 255 characters.';
        } elseif (mb_strlen($form['description']) > 5000) {
            $error = 'Description must be 5,000 characters or fewer.';
        } elseif (caseVideoEmbedUrl($form['video_url']) === null) {
            $error = 'Paste a Bunny Stream embed link from player.mediadelivery.net or iframe.mediadelivery.net.';
        } else {
            try {
                saveCaseVideo($pdo, $form, $doctorIds, $contributions);
                $success = $form['id'] ? 'Case video updated.' : 'Case video added.';
                $form = ['id' => 0, 'title' => '', 'description' => '', 'video_url' => ''];
                $selectedDoctors = [];
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Case video save failed: ' . $e->getMessage());
                $error = 'Could not save the video. Please try again.';
            }
        }
    } elseif ($action === 'delete' && $id) {
        try {
            $stmt = $pdo->prepare('DELETE FROM videos WHERE id = ?');
            $stmt->execute([$id]);
            $success = $stmt->rowCount() ? 'Case video deleted.' : 'Video was already removed.';
        } catch (PDOException $e) {
            error_log('Case video delete failed: ' . $e->getMessage());
            $error = 'Could not delete the video.';
        }
    } else {
        $error = 'Invalid action.';
    }
}

try {
    $videos = $pdo->query('SELECT id, title, description, video_url, created_at FROM videos ORDER BY created_at DESC, id DESC')->fetchAll();
} catch (PDOException $e) {
    error_log('Case videos list failed: ' . $e->getMessage());
    $videos = [];
    $error = 'Could not load videos. Apply the Bunny case videos migration if it has not run.';
}
$videosTable = adminTableState($videos, ['title', 'description', 'video_url', 'created_at'], 'videos');
try {
    $availableDoctors = $pdo->query('SELECT id,display_name,is_published FROM doctors ORDER BY display_name')->fetchAll();
    $videoDoctors = videoDoctorMap($pdo, array_column($videos, 'id'), false);
} catch (PDOException $e) {
    $availableDoctors = $videoDoctors = [];
    $error = 'Apply the doctor profiles migration before saving case videos.';
}
?>
<div class="mb-6"><h2 class="text-2xl font-bold text-[#13324a]">Real Case Videos</h2><p class="text-slate-500 text-sm mt-1">Add a case name, description, and Bunny Stream embed link. No video upload is needed here.</p></div>
<?php if ($error): ?><div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm font-semibold"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($success): ?><div class="bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6 text-sm font-semibold"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-1"><div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h3 id="video-form-heading" class="text-lg font-bold text-[#13324a] mb-4 border-b border-slate-100 pb-3"><?= $form['id'] ? 'Edit Case Video' : 'Add Case Video' ?></h3>
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['case_videos_csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="case-video-id" value="<?= (int) $form['id'] ?>">
            <div><label for="case-title" class="block text-sm font-bold text-[#13324a] mb-2">Case name</label><input id="case-title" type="text" name="title" maxlength="255" required value="<?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white"></div>
            <div><label for="case-description" class="block text-sm font-bold text-[#13324a] mb-2">Description</label><textarea id="case-description" name="description" maxlength="5000" rows="4" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white"><?= htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
            <div><label for="case-url" class="block text-sm font-bold text-[#13324a] mb-2">Bunny video link</label><input id="case-url" type="url" name="video_url" maxlength="2048" required placeholder="https://player.mediadelivery.net/embed/..." value="<?= htmlspecialchars($form['video_url'], ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white"><p class="text-xs text-slate-500 mt-2">In Bunny Stream, copy the video’s Embed URL and paste it here.</p></div>
            <fieldset class="space-y-3"><legend class="text-sm font-bold text-[#13324a]">Doctors who worked on this case</legend><p class="text-xs leading-5 text-slate-500">Choose existing profiles, describe their contribution, and use the arrows to order their names. Draft profiles stay hidden on the public site.</p><div id="case-doctors" class="space-y-3"></div><button type="button" id="add-case-doctor" class="text-sm font-semibold text-[#1d5f8c]" <?= !$availableDoctors ? 'disabled' : '' ?>>+ Add doctor</button><a href="admin_doctors.php" class="ml-3 text-xs text-slate-500 underline">Manage doctors</a></fieldset>
            <button type="submit" class="w-full bg-[#13324a] hover:bg-[#1d5f8c] text-white px-4 py-2.5 rounded-xl text-sm font-bold transition">Save video</button>
            <button id="case-video-cancel" type="button" class="<?= $form['id'] ? '' : 'hidden ' ?>w-full text-sm font-semibold text-slate-600 hover:text-[#13324a]">Cancel editing</button>
        </form>
    </div></div>
    <div class="xl:col-span-2"><div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50"><h3 class="text-lg font-bold text-[#13324a]">Case videos</h3></div>
        <?php adminTableToolbar('videos', $videosTable, 'Search videos...'); ?>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="uppercase tracking-wider border-b-2 border-slate-100 bg-white text-slate-500 font-semibold text-[11px]"><tr><th class="px-6 py-4">Preview</th><th class="px-6 py-4">Case</th><th class="px-6 py-4">Added</th><th class="px-6 py-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100 text-slate-700" data-admin-table-body="videos">
                <?php if (!$videosTable['rows']): ?><tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No videos yet.</td></tr><?php endif; ?>
                <?php foreach ($videosTable['rows'] as $video): ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4"><div class="w-40 rounded-lg overflow-hidden"><?php if (caseVideoEmbedUrl($video['video_url'])) { renderCaseVideoPlayer($video['video_url'], $video['title']); } else { echo '<span class="text-xs text-amber-700">Legacy video. Edit its link.</span>'; } ?></div></td>
                        <td class="px-6 py-4 min-w-64"><p class="font-bold text-[#13324a]"><?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?></p><p class="mt-1 max-w-md whitespace-normal text-xs leading-5 text-slate-500"><?= nl2br(htmlspecialchars($video['description'] ?: 'No description', ENT_QUOTES, 'UTF-8')) ?></p><?php foreach ($videoDoctors[$video['id']] ?? [] as $caseDoctor): ?><p class="mt-2 text-xs text-[#1d5f8c]"><?= htmlspecialchars($caseDoctor['display_name'] . ($caseDoctor['contribution'] ? ' · ' . $caseDoctor['contribution'] : '') . (!$caseDoctor['is_published'] ? ' (Draft)' : ''), ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-500"><?= htmlspecialchars(date('M d, Y', strtotime($video['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-6 py-4"><div class="flex gap-2"><button type="button" class="case-video-edit px-3 py-2 rounded-lg bg-blue-50 text-[#1d5f8c] font-semibold" data-id="<?= (int) $video['id'] ?>" data-title="<?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?>" data-description="<?= htmlspecialchars($video['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-url="<?= htmlspecialchars($video['video_url'], ENT_QUOTES, 'UTF-8') ?>" data-doctors="<?= htmlspecialchars(json_encode($videoDoctors[$video['id']] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                            <form method="post" onsubmit="return confirm('Delete this case video?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['case_videos_csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $video['id'] ?>"><button type="submit" class="px-3 py-2 rounded-lg bg-red-50 text-red-600 font-semibold">Delete</button></form></div></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php adminTablePagination('videos', $videosTable); ?>
    </div></div>
</div>
<template id="case-doctor-template"><div class="case-doctor-row rounded-xl border border-slate-200 p-3 space-y-2"><label class="block text-xs font-semibold">Doctor<select name="doctor_ids[]" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"><option value="">Choose a doctor</option><?php foreach ($availableDoctors as $option): ?><option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['display_name'] . (!$option['is_published'] ? ' (Draft)' : ''), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label class="block text-xs font-semibold">Contribution (optional)<input name="contributions[]" maxlength="190" placeholder="e.g. Surgical planning" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label><div class="flex gap-3"><button type="button" data-doctor-up aria-label="Move doctor up">↑</button><button type="button" data-doctor-down aria-label="Move doctor down">↓</button><button type="button" data-doctor-remove class="ml-auto text-xs text-red-600">Remove</button></div></div></template>
<script>
const caseDoctorsContainer = document.getElementById('case-doctors');
function addCaseDoctor(doctor = {}) {
    const row = document.getElementById('case-doctor-template').content.cloneNode(true);
    row.querySelector('select').value = String(doctor.doctor_id || '');
    row.querySelector('input').value = doctor.contribution || '';
    caseDoctorsContainer.append(row);
}
function setCaseDoctors(doctors) {
    caseDoctorsContainer.replaceChildren();
    doctors.forEach(addCaseDoctor);
}
setCaseDoctors(<?= json_encode($selectedDoctors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
document.getElementById('add-case-doctor').addEventListener('click', () => addCaseDoctor());
caseDoctorsContainer.addEventListener('click', event => {
    const row = event.target.closest('.case-doctor-row');
    if (!row) return;
    if (event.target.closest('[data-doctor-remove]')) row.remove();
    if (event.target.closest('[data-doctor-up]') && row.previousElementSibling) row.previousElementSibling.before(row);
    if (event.target.closest('[data-doctor-down]') && row.nextElementSibling) row.nextElementSibling.after(row);
});
document.addEventListener('click', function (event) {
    const edit = event.target.closest('.case-video-edit');
    if (!edit) return;
    document.getElementById('case-video-id').value = edit.dataset.id;
    document.getElementById('case-title').value = edit.dataset.title;
    document.getElementById('case-description').value = edit.dataset.description;
    document.getElementById('case-url').value = edit.dataset.url;
    setCaseDoctors(JSON.parse(edit.dataset.doctors || '[]'));
    document.getElementById('video-form-heading').textContent = 'Edit Case Video';
    document.getElementById('case-video-cancel').classList.remove('hidden');
    document.getElementById('case-title').focus();
});
document.getElementById('case-video-cancel').addEventListener('click', function () {
    document.getElementById('case-video-id').value = '0';
    document.getElementById('case-title').value = '';
    document.getElementById('case-description').value = '';
    document.getElementById('case-url').value = '';
    setCaseDoctors([]);
    document.getElementById('video-form-heading').textContent = 'Add Case Video';
    this.classList.add('hidden');
});
</script>
<?php require_once 'includes/admin_footer.php'; ?>
