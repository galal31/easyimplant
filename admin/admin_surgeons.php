<?php
require_once 'includes/admin_header.php';
require_once '../includes/surgeon_operations.php';
if (empty($_SESSION['surgeons_csrf_token'])) $_SESSION['surgeons_csrf_token']=bin2hex(random_bytes(32));
$error=$success='';
$form=['id'=>0,'full_name'=>'','phone'=>'','specialty'=>'','notes'=>'','is_available'=>1];
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!hash_equals($_SESSION['surgeons_csrf_token'],(string)($_POST['csrf_token'] ?? ''))) throw new DomainException('Your session expired. Refresh the page.');
        if (($_POST['action'] ?? '')==='save') { saveSurgeon($pdo,$_POST); $success='Surgeon saved.'; }
        elseif (($_POST['action'] ?? '')==='delete') $success=deleteOrDisableSurgeon($pdo,(int)($_POST['id'] ?? 0));
        else throw new DomainException('Invalid action.');
    }
    if (!empty($_GET['edit'])) { $stmt=$pdo->prepare('SELECT * FROM surgeons WHERE id=?'); $stmt->execute([(int)$_GET['edit']]); $form=$stmt->fetch() ?: $form; }
} catch (DomainException $e) { $error=$e->getMessage(); if (($_POST['action'] ?? '')==='save') $form=array_merge($form,$_POST,['is_available'=>empty($_POST['is_available'])?0:1]); }
catch (Throwable $e) { error_log('Surgeon CRUD: '.$e->getMessage()); $error='Could not save surgeons. Check the surgeon-operations migration.'; }
try { $rows=$pdo->query('SELECT s.*,COUNT(DISTINCT h.request_id) AS operation_count FROM surgeons s LEFT JOIN surgeon_assignment_history h ON h.surgeon_id=s.id GROUP BY s.id ORDER BY s.full_name')->fetchAll(); }
catch (Throwable $e) { error_log('Surgeon list: '.$e->getMessage()); $error='Could not load surgeons. Check the surgeon-operations migration.'; $rows=[]; }
$table=adminTableState($rows,['full_name','phone','specialty','is_available'],'surgeons');
?>
<div class="mb-6"><h2 class="text-2xl font-bold text-[#13324a]">Surgeons</h2><p class="mt-1 text-sm text-slate-500">Manage surgeons and their operation history. Coordination is handled by administration.</p></div>
<?php if ($error): ?><p role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-red-700"><?= $esc($error) ?></p><?php endif; ?>
<?php if ($success): ?><p role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-emerald-700"><?= $esc($success) ?></p><?php endif; ?>
<div class="grid gap-6 xl:grid-cols-[minmax(280px,1fr)_minmax(0,2fr)]">
<form method="post" class="space-y-4 self-start rounded-2xl border border-slate-200 bg-white p-6">
<h3 class="text-lg font-bold"><?= $form['id'] ? 'Edit surgeon' : 'Add surgeon' ?></h3>
<input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['surgeons_csrf_token']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
<?php foreach (['full_name'=>'Name','phone'=>'Phone','specialty'=>'Specialty'] as $key=>$label): ?><div><label for="surgeon-<?= $key ?>" class="mb-2 block text-sm font-semibold"><?= $label ?></label><input id="surgeon-<?= $key ?>" name="<?= $key ?>" <?= $key!=='specialty'?'required':'' ?> maxlength="<?= $key==='phone'?40:190 ?>" value="<?= $esc($form[$key]) ?>" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"></div><?php endforeach; ?>
<div><label for="surgeon-notes" class="mb-2 block text-sm font-semibold">Internal notes</label><textarea id="surgeon-notes" name="notes" maxlength="4000" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><?= $esc($form['notes']) ?></textarea></div>
<label class="flex gap-2 text-sm"><input type="checkbox" name="is_available" value="1" <?= !empty($form['is_available'])?'checked':'' ?>>Available for new operations</label>
<button class="w-full rounded-xl bg-[#13324a] py-3 font-bold text-white">Save surgeon</button>
<?php if ($form['id']): ?><a class="block text-center text-sm" href="admin_surgeons.php">Cancel editing</a><?php endif; ?>
</form>
<div class="min-w-0 self-start overflow-hidden rounded-2xl border border-slate-200 bg-white">
<?php adminTableToolbar('surgeons',$table,'Search surgeons...'); ?>
<div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="bg-slate-50"><tr><?php foreach(['Surgeon','Phone','Specialty','Available','Operations','Actions'] as $label): ?><th class="px-4 py-3"><?= $label ?></th><?php endforeach; ?></tr></thead><tbody class="divide-y" data-admin-table-body="surgeons">
<?php if (!$table['rows']): ?><tr><td colspan="6" class="p-6 text-center text-slate-500">No surgeons found.</td></tr><?php endif; ?>
<?php foreach($table['rows'] as $s): ?><tr><td class="px-4 py-3 font-bold"><?= $esc($s['full_name']) ?></td><td class="px-4 py-3"><?= $esc($s['phone']) ?></td><td class="px-4 py-3"><?= $esc($s['specialty']) ?></td><td class="px-4 py-3"><?= $s['is_available']?'Yes':'No' ?></td><td class="px-4 py-3"><?= (int)$s['operation_count'] ?></td><td class="flex gap-3 px-4 py-3"><a href="admin_surgeons.php?edit=<?= (int)$s['id'] ?>">Edit</a><a href="admin_surgeon_history.php?id=<?= (int)$s['id'] ?>">History</a><form method="post" onsubmit="return confirm('Delete this surgeon? Surgeons with operation history will be disabled instead.');"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['surgeons_csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="text-red-600">Delete / disable</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php adminTablePagination('surgeons',$table); ?></div></div>
<?php require 'includes/admin_footer.php'; ?>
