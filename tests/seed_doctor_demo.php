<?php
// Run manually with PHP CLI against the local database only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/case_videos.php';
if (APP_ENVIRONMENT !== 'local') throw new RuntimeException('Demo data is restricted to the local database.');

$profiles = [
    ['ahmed-arafa','د. أحمد عرفة','زراعة الأسنان والجراحة الموجهة — مثال للعرض','arafa.jpeg'],
    ['ahmed-hany','د. أحمد هاني','التخطيط الرقمي وتركيبات الأسنان — مثال للعرض','hany.jpeg'],
];
$caseTitles = ['زراعة ضرس باستخدام دليل جراحي','تأهيل الفك بالكامل — All-on-4','التخطيط الرقمي لزراعة الأسنان الأمامية'];
$caseDescriptions = [
    'نموذج عرض لحالة تعويض ضرس مفقود بزرعة مع التخطيط لموضعها باستخدام دليل جراحي، وإظهار مشاركة فريق التخطيط والتنفيذ.',
    'نموذج عرض لإعادة تأهيل فك كامل باستخدام أربع زرعات، مع تقديم مراحل التخطيط الجراحي وتصميم التعويض النهائي.',
    'نموذج عرض للتخطيط الرقمي لتعويض الأسنان الأمامية، يجمع بين موضع الزرعات وشكل التركيبات المقترحة وتنسيق عمل الفريق.',
];
$url = 'https://player.mediadelivery.net/embed/651916/2184b24d-1217-4a1c-adcd-8aaff03c9f10';
$pdo->beginTransaction();
try {
    $ids = [];
    foreach ($profiles as [$slug,$name,$specialty,$image]) {
        $find = $pdo->prepare('SELECT id FROM doctors WHERE slug=?');
        $find->execute([$slug]);
        $id = $find->fetchColumn();
        if (!$id) {
            $photo = 'uploads/doctors/doctor-' . md5('local-demo-' . $slug) . '.jpg';
            $directory = __DIR__ . '/../uploads/doctors';
            if (!is_dir($directory) && !mkdir($directory,0755,true)) throw new RuntimeException('Could not create photo directory.');
            if (!is_file(__DIR__ . '/../' . $photo) && !copy(__DIR__ . '/../images/doctors/' . $image,__DIR__ . '/../' . $photo)) throw new RuntimeException('Could not copy demo portrait.');
            $id = saveDoctor($pdo, [
            'display_name'=>$name, 'slug'=>$slug, 'specialty'=>$specialty,
            'short_bio'=>$slug === 'ahmed-arafa'
                ? 'نبذة تجريبية: يركز هذا النموذج على زراعة الأسنان والجراحة الموجهة، بدءًا من دراسة الحالة وتحديد مواضع الزرعات، مرورًا بتصميم الدليل الجراحي، وحتى تنسيق العمل مع فريق التركيبات. التخصص والوصف أمثلة لمعاينة الصفحة وليسا معلومات مهنية موثقة عن الطبيب.'
                : 'نبذة تجريبية: يركز هذا النموذج على دمج التخطيط الرقمي مع تصميم تركيبات الأسنان، وتحويل خطة العلاج إلى خطوات واضحة يتعاون فيها فريق الجراحة والتركيبات. التخصص والوصف أمثلة لمعاينة الصفحة وليسا معلومات مهنية موثقة عن الطبيب.',
            'qualifications'=>"بيانات تجريبية للمؤهلات — تُستبدل بالمؤهلات الفعلية\nمجال تدريبي توضيحي: التخطيط الرقمي للحالات\nمجال تدريبي توضيحي: تنسيق الجراحة والتركيبات",
            'photo_path'=>$photo, 'is_published'=>1,
            ]);
        }
        $ids[] = (int) $id;
    }
    $teams = [[$ids[0],$ids[1]],[$ids[0],$ids[1]],[$ids[1],$ids[0]]];
    foreach ($caseTitles as $position=>$title) {
        $find = $pdo->prepare('SELECT id FROM videos WHERE title=?');
        $find->execute([$title]);
        if (!$find->fetchColumn()) saveCaseVideo($pdo, [
            'title'=>$title,
            'description'=>$caseDescriptions[$position] . ' بيانات توضيحية فقط: الفيديو المستخدم هو رابط Bunny التجريبي نفسه، ولا يوثق الحالة المسماة أو مشاركة الأطباء فيها.',
            'video_url'=>$url,
        ], $teams[$position], $position === 2 ? ['التخطيط الرقمي والتركيبات — تجريبي','مراجعة الخطة الجراحية — تجريبي'] : ['الجراحة الموجهة — تجريبي','التخطيط والتركيبات — تجريبي']);
    }
    foreach ($profiles as [$slug]) {
        $doctor = getPublishedDoctor($pdo,$slug);
        if (!$doctor) throw new RuntimeException('Demo profile was not published.');
    }
    $check = $pdo->prepare('SELECT COUNT(*) FROM video_doctors vd JOIN videos v ON v.id=vd.video_id WHERE v.title IN (?,?,?)');
    $check->execute($caseTitles);
    if ((int) $check->fetchColumn() !== 6) throw new RuntimeException('Demo teams were not saved correctly.');
    $pdo->commit();
    echo "Demo ready: 2 named published doctors with portraits, 3 dental cases, 6 team links. Existing records preserved.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
