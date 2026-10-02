<?php
require_once 'includes/admin_header.php';
$stmt=$pdo->prepare('SELECT * FROM surgeons WHERE id=?'); $stmt->execute([(int)($_GET['id'] ?? 0)]); $surgeon=$stmt->fetch();
if (!$surgeon) { http_response_code(404); echo '<p>Surgeon not found.</p>'; require 'includes/admin_footer.php'; exit; }
$stmt=$pdo->prepare('SELECT h.*,r.status,u.clinic_name,sr.surgeon_id AS current_surgeon_id,sr.performed_at,sr.financial_review_required FROM surgeon_assignment_history h JOIN requests r ON r.id=h.request_id JOIN users u ON u.id=r.user_id JOIN surgeon_requests sr ON sr.request_id=r.id WHERE h.surgeon_id=? ORDER BY h.id DESC');
$stmt->execute([$surgeon['id']]); $rows=$stmt->fetchAll();
foreach ($rows as &$row) $row['status_label']=['pending_review'=>'Review and coordination','pending_payment'=>'Awaiting payment','in_progress'=>'Paid awaiting operation','completed'=>'Operation performed','rejected'=>'Rejected','cancelled'=>'Cancelled financial review'][$row['status']] ?? 'Historical status';
unset($row);
$table=adminTableState($rows,['request_id','clinic_name','status','status_label','operation_at','assigned_at','surgeon_name_snapshot'],'surgeon_history');
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<div class="mb-6"><a href="admin_surgeons.php" class="text-sm text-blue-700">Back to surgeons</a><h2 class="mt-2 text-2xl font-bold text-[#13324a]"><?= $esc($surgeon['full_name']) ?> — Operation history</h2><p class="mt-2 text-slate-500"><?= $esc($surgeon['phone']) ?> · <?= $esc($surgeon['specialty']) ?></p><p class="mt-1 text-sm text-slate-500">Includes previous assignments and appointment changes. Missing dates identify legacy records.</p></div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><?php adminTableToolbar('surgeon_history',$table,'Search operations...'); ?><div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="bg-slate-50"><tr><?php foreach(['Request','Clinic','Assigned as','Appointment at assignment','Recorded','Status','Performed','Assignment'] as $label): ?><th class="px-4 py-3"><?= $label ?></th><?php endforeach; ?></tr></thead><tbody class="divide-y" data-admin-table-body="surgeon_history">
<?php if (!$table['rows']): ?><tr><td colspan="8" class="p-6 text-center">No operation history.</td></tr><?php endif; ?>
<?php foreach($table['rows'] as $h): ?><tr><td class="px-4 py-3"><a class="font-bold text-blue-700" href="admin_view_request.php?id=<?= (int)$h['request_id'] ?>">#<?= (int)$h['request_id'] ?></a></td><td class="px-4 py-3"><?= $esc($h['clinic_name']) ?></td><td class="px-4 py-3"><?= $esc($h['surgeon_name_snapshot']) ?></td><td class="px-4 py-3"><?= $esc($h['operation_at'] ?? 'Not recorded (legacy)') ?></td><td class="px-4 py-3"><?= $esc($h['assigned_at'] ?? 'Not recorded (legacy)') ?></td><td class="px-4 py-3"><?= $esc($h['status_label']) ?><?= $h['financial_review_required']?' — financial review required':'' ?></td><td class="px-4 py-3"><?= $esc($h['performed_at'] ?? 'Not recorded') ?></td><td class="px-4 py-3"><?= (int)$h['current_surgeon_id']===(int)$surgeon['id']?'Current surgeon':'Previous surgeon' ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php adminTablePagination('surgeon_history',$table); ?></div>
<?php require 'includes/admin_footer.php'; ?>
