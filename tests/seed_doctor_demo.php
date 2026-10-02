<?php
// Run manually with PHP CLI against the local database only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/case_videos.php';
if (APP_ENVIRONMENT !== 'local') throw new RuntimeException('Demo data is restricted to the local database.');

$refresh = in_array('--refresh-content', $argv, true);
$refreshQualifications = in_array('--refresh-qualifications', $argv, true);
$qualificationsBySlug = [
    'ahmed-arafa'=>"التخطيط الرقمي لمواضع زراعة الأسنان\nالجراحة الموجهة باستخدام الأدلة الجراحية\nتنسيق خطة الزراعة مع التركيبات النهائية",
    'ahmed-hany'=>"التخطيط الرقمي لتركيبات الأسنان\nتصميم التعويضات المدعومة بالزرعات\nدراسة الإطباق وتناسق الابتسامة",
];
$profiles = [
    ['ahmed-arafa','د. أحمد عرفة','زراعة الأسنان والجراحة الموجهة','arafa.jpeg'],
    ['ahmed-hany','د. أحمد هاني','التخطيط الرقمي وتركيبات الأسنان','hany.jpeg'],
];
$caseTitles = ['زراعة ضرس باستخدام دليل جراحي','تأهيل الفك بالكامل — All-on-4','التخطيط الرقمي لزراعة الأسنان الأمامية'];
$caseDescriptions = [
    'تعويض الضرس المفقود يجمع بين دراسة موضع الزرعة وتصميم الدليل الجراحي والتخطيط للتركيبة النهائية.',
    'التأهيل الكامل للفك يربط بين التخطيط لمواضع الزرعات وتصميم التعويض النهائي واستعادة وظيفة الأسنان.',
    'التخطيط الرقمي للأسنان الأمامية يجمع بين موضع الزرعات وشكل التركيبات وتناسق الابتسامة.',
];
$url = 'https://player.mediadelivery.net/embed/651916/2184b24d-1217-4a1c-adcd-8aaff03c9f10';
$pdo->beginTransaction();
try {
    $ids = [];
    foreach ($profiles as [$slug,$name,$specialty,$image]) {
        $find = $pdo->prepare('SELECT id FROM doctors WHERE slug=?');
        $find->execute([$slug]);
        $id = $find->fetchColumn();
        if ($id && $refreshQualifications) {
            $pdo->prepare('UPDATE doctors SET qualifications=? WHERE id=?')->execute([$qualificationsBySlug[$slug],$id]);
        }
        if (!$id || $refresh) {
            $existing = $id ? getDoctor($pdo, (int) $id) : [];
            $photo = 'uploads/doctors/doctor-' . md5('local-demo-' . $slug) . '.jpg';
            $directory = __DIR__ . '/../uploads/doctors';
            if (!is_dir($directory) && !mkdir($directory,0755,true)) throw new RuntimeException('Could not create photo directory.');
            if (!is_file(__DIR__ . '/../' . $photo) && !copy(__DIR__ . '/../images/doctors/' . $image,__DIR__ . '/../' . $photo)) throw new RuntimeException('Could not copy demo portrait.');
            $id = saveDoctor($pdo, array_merge($existing, [
            'display_name'=>$name, 'slug'=>$slug, 'specialty'=>$specialty,
            'short_bio'=>$slug === 'ahmed-arafa'
                ? 'زراعة الأسنان والجراحة الموجهة تجمع بين دراسة الحالة والتخطيط الرقمي لمواضع الزرعات وتصميم الدليل الجراحي. ويكتمل مسار العلاج بتنسيق خطة الجراحة مع تصميم التركيبات لتحقيق التوازن بين وظيفة الأسنان وشكل الابتسامة.'
                : 'التخطيط الرقمي وتركيبات الأسنان يربطان بين تفاصيل الحالة وتصميم التعويض النهائي. ويشمل هذا المجال دراسة تناسق الابتسامة وعلاقة الأسنان بالفكين وتنسيق خطوات العمل بين الجراحة والتركيبات.',
            'qualifications'=>$qualificationsBySlug[$slug],
            'photo_path'=>$existing['photo_path'] ?? $photo, 'is_published'=>$existing['is_published'] ?? 1,
            ]), (int) $id);
        }
        $ids[] = (int) $id;
    }
    $teams = [[$ids[0],$ids[1]],[$ids[0],$ids[1]],[$ids[1],$ids[0]]];
    foreach ($caseTitles as $position=>$title) {
        $find = $pdo->prepare('SELECT id FROM videos WHERE title=?');
        $find->execute([$title]);
        $caseId = $find->fetchColumn();
        if (!$caseId || $refresh) saveCaseVideo($pdo, [
            'id'=>(int) $caseId,
            'title'=>$title,
            'description'=>$caseDescriptions[$position],
            'video_url'=>$url,
        ], $teams[$position], $position === 2 ? ['التخطيط الرقمي والتركيبات','مراجعة الخطة الجراحية'] : ['الجراحة الموجهة','التخطيط والتركيبات']);
    }
    foreach ($profiles as [$slug]) {
        $doctor = getPublishedDoctor($pdo,$slug);
        if (!$doctor) throw new RuntimeException('Demo profile was not published.');
        if ($refreshQualifications && $doctor['qualifications'] !== $qualificationsBySlug[$slug]) throw new RuntimeException('Qualifications were not saved.');
        if ($refresh && preg_match('/تجريب|توضيح|مثال للعرض/u', implode(' ', [$doctor['specialty'],$doctor['short_bio'],$doctor['qualifications']]))) throw new RuntimeException('Profile content was not refreshed.');
    }
    $check = $pdo->prepare('SELECT COUNT(*) FROM video_doctors vd JOIN videos v ON v.id=vd.video_id WHERE v.title IN (?,?,?)');
    $check->execute($caseTitles);
    if ((int) $check->fetchColumn() !== 6) throw new RuntimeException('Demo teams were not saved correctly.');
    if ($refresh) {
        $contentCheck = $pdo->prepare('SELECT v.description,vd.contribution FROM videos v JOIN video_doctors vd ON vd.video_id=v.id WHERE v.title IN (?,?,?)');
        $contentCheck->execute($caseTitles);
        foreach ($contentCheck as $row) if (preg_match('/تجريب|توضيح|نموذج عرض/u', implode(' ', $row))) throw new RuntimeException('Case content was not refreshed.');
    }
    $pdo->commit();
    echo "Demo ready: 2 named published doctors with portraits, 3 dental cases, 6 team links. Existing records preserved.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
