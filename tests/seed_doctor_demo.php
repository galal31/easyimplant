<?php
// Run manually with PHP CLI against the local database only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/case_videos.php';
if (APP_ENVIRONMENT !== 'local') throw new RuntimeException('Demo data is restricted to the local database.');

$profiles = [
    ['demo-ahmed','د. أحمد — ملف تجريبي','زراعة الأسنان والجراحة الموجهة'],
    ['demo-mariam','د. مريم — ملف تجريبي','التخطيط الرقمي وتصميم الأدلة الجراحية'],
    ['demo-omar','د. عمر — ملف تجريبي','تركيبات الأسنان والتأهيل الوظيفي'],
];
$caseTitles = ['حالة تجريبية — زراعة موجهة','حالة تجريبية — تأهيل الفك بالكامل','حالة تجريبية — التخطيط الرقمي'];
$url = 'https://player.mediadelivery.net/embed/651916/2184b24d-1217-4a1c-adcd-8aaff03c9f10';
$pdo->beginTransaction();
try {
    $ids = [];
    foreach ($profiles as [$slug,$name,$specialty]) {
        $find = $pdo->prepare('SELECT id FROM doctors WHERE slug=?');
        $find->execute([$slug]);
        $id = $find->fetchColumn();
        if (!$id) $id = saveDoctor($pdo, [
            'display_name'=>$name, 'slug'=>$slug, 'specialty'=>$specialty,
            'short_bio'=>'هذا ملف تجريبي لمعاينة تصميم الصفحة وربط الطبيب بالحالات. الاسم والخبرات هنا أمثلة توضيحية وليست بيانات مهنية حقيقية.',
            'qualifications'=>"مؤهل تجريبي في طب الأسنان\nتدريب تجريبي على التخطيط الرقمي\nمشاركة تجريبية ضمن فريق الحالة",
            'is_published'=>1,
        ]);
        $ids[] = (int) $id;
    }
    $teams = [[$ids[0],$ids[1]],[$ids[0],$ids[2]],[$ids[1],$ids[2]]];
    foreach ($caseTitles as $position=>$title) {
        $find = $pdo->prepare('SELECT id FROM videos WHERE title=?');
        $find->execute([$title]);
        if (!$find->fetchColumn()) saveCaseVideo($pdo, [
            'title'=>$title,
            'description'=>'بيانات تجريبية لمعاينة معرض الحالات وأسماء الفريق. جميع الأمثلة تستخدم نفس رابط Bunny المرسل للاختبار؛ العنوان والوصف لا يصفان محتوى الفيديو الفعلي.',
            'video_url'=>$url,
        ], $teams[$position], ['التنفيذ والتخطيط — تجريبي','المشاركة في الحالة — تجريبي']);
    }
    foreach ($profiles as [$slug]) {
        $doctor = getPublishedDoctor($pdo,$slug);
        if (!$doctor) throw new RuntimeException('Demo profile was not published.');
    }
    $check = $pdo->prepare('SELECT COUNT(*) FROM video_doctors vd JOIN videos v ON v.id=vd.video_id WHERE v.title IN (?,?,?)');
    $check->execute($caseTitles);
    if ((int) $check->fetchColumn() !== 6) throw new RuntimeException('Demo teams were not saved correctly.');
    $pdo->commit();
    echo "Demo ready: 3 published doctors, 3 videos, 6 team links. Existing records preserved.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
