<?php
define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/case_videos.php';
if (APP_ENVIRONMENT !== 'local') throw new RuntimeException('Run doctor profile tests on the local database only.');

function doctorCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function doctorReject(callable $operation, string $message): void
{
    try { $operation(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException($message);
}

$tag = bin2hex(random_bytes(6));
$profileData = ['display_name'=>'Test Doctor ' . $tag, 'slug'=>'doctor-test-' . $tag, 'specialty'=>'Implant dentistry', 'short_bio'=>'Test biography', 'qualifications'=>"First qualification\nSecond qualification", 'is_published'=>1];
$bunnyUrl = 'https://player.mediadelivery.net/embed/651916/2184b24d-1217-4a1c-adcd-8aaff03c9f10';
$pdo->beginTransaction();
try {
    $profileId = saveDoctor($pdo, $profileData);
    doctorCheck($profileId > 0 && getPublishedDoctor($pdo, $profileData['slug']) !== null, 'Published profile was not created.');
    doctorReject(fn()=>saveDoctor($pdo, $profileData), 'Duplicate slug accepted.');
    $automatic = ['display_name'=>'Automatic ' . $tag, 'is_published'=>0];
    $draftId = saveDoctor($pdo, $automatic);
    $collisionId = saveDoctor($pdo, $automatic);
    doctorCheck(getDoctor($pdo, $draftId)['slug'] !== getDoctor($pdo, $collisionId)['slug'], 'Automatic slugs collided.');
    doctorCheck(getPublishedDoctor($pdo, getDoctor($pdo, $draftId)['slug']) === null, 'Draft profile exposed.');
    $editedData = array_merge($profileData, ['display_name'=>'Renamed ' . $tag,'slug'=>'']);
    saveDoctor($pdo, $editedData, $profileId);
    doctorCheck(getDoctor($pdo, $profileId)['slug'] === $profileData['slug'], 'Changing the name changed the profile link.');
    doctorCheck(doctorSlug('دكتور أحمد علي') === 'دكتور-أحمد-علي', 'Arabic slug failed.');
    doctorCheck(str_contains(doctorProfileUrl('دكتور-أحمد'), '%D8'), 'Arabic profile URL was not encoded.');
    doctorReject(fn()=>saveDoctor($pdo, array_merge($profileData,['slug'=>'other-'.$tag,'user_id'=>-1])), 'Invalid account accepted.');

    $caseData = ['id'=>0, 'title'=>'Case ' . $tag, 'description'=>'Case description', 'video_url'=>$bunnyUrl];
    $caseId = saveCaseVideo($pdo, $caseData, [$profileId,$draftId], ['Surgery','Planning']);
    $teamMap = videoDoctorMap($pdo, [$caseId], false);
    doctorCheck(count($teamMap[$caseId]) === 2 && $teamMap[$caseId][0]['doctor_id'] == $profileId, 'Team order not saved.');
    doctorCheck(count(videoDoctorMap($pdo, [$caseId])[$caseId]) === 1, 'Draft doctor appeared publicly.');
    doctorReject(fn()=>saveCaseVideo($pdo, array_merge($caseData,['id'=>$caseId,'title'=>'Bad update']),[$profileId,PHP_INT_MAX]), 'Invalid team accepted.');
    doctorCheck($pdo->query('SELECT title FROM videos WHERE id=' . $caseId)->fetchColumn() === $caseData['title'], 'Invalid team saved partial video changes.');
    doctorReject(fn()=>saveCaseVideo($pdo, array_merge($caseData,['id'=>$caseId]),[$profileId,$profileId]), 'Duplicate doctor accepted.');
    saveCaseVideo($pdo, array_merge($caseData,['id'=>$caseId]),[$draftId,$profileId],['Planning','Surgery']);
    doctorCheck(videoDoctorMap($pdo,[$caseId],false)[$caseId][0]['doctor_id'] == $draftId, 'Team reordering failed.');

    $_GET = ['slug'=>$profileData['slug']];
    ob_start(); include __DIR__ . '/../doctor.php'; $profileHtml = ob_get_clean();
    doctorCheck(str_contains($profileHtml, $caseData['title']) && str_contains($profileHtml,'data-case-target='), 'Profile cases missing.');
    doctorCheck(!str_contains($profileHtml,'<iframe'), 'Profile eagerly loaded videos.');
    doctorCheck(str_contains($profileHtml,'index.php#services'), 'Shared header links do not return home.');
    saveDoctor($pdo, array_merge($editedData,['is_published'=>0]),$profileId);
    ob_start(); include __DIR__ . '/../doctor.php'; $draftHtml = ob_get_clean();
    doctorCheck(http_response_code() === 404 && !str_contains($draftHtml,$editedData['display_name']), 'Unpublished profile leaked.');
    $_GET = ['slug'=>'missing-profile-' . $tag];
    ob_start(); include __DIR__ . '/../doctor.php'; ob_end_clean();
    doctorCheck(http_response_code() === 404, 'Unknown slug did not return 404.');

    doctorReject(fn()=>validateDoctorPhoto(['error'=>UPLOAD_ERR_OK,'size'=>100,'tmp_name'=>__FILE__]), 'Non-image upload accepted.');
    $photoFile = __DIR__ . '/../images/doctors/hany.jpeg';
    doctorCheck(validateDoctorPhoto(['error'=>UPLOAD_ERR_OK,'size'=>filesize($photoFile),'tmp_name'=>$photoFile]) === 'jpg', 'Real JPEG rejected.');

    $_SESSION = ['user_id'=>1,'role'=>'admin','full_name'=>'Test Admin'];
    $_SERVER['PHP_SELF'] = 'admin_doctors.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];
    chdir(__DIR__ . '/../admin');
    ob_start(); include __DIR__ . '/../admin/admin_doctors.php'; $adminHtml = ob_get_clean();
    doctorCheck(str_contains($adminHtml,'href="admin_doctors.php"'), 'Doctors sidebar link missing.');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['action'=>'delete','id'=>$profileId,'csrf_token'=>'invalid'];
    ob_start(); include __DIR__ . '/../admin/admin_doctors.php'; ob_end_clean();
    doctorCheck(getDoctor($pdo,$profileId) !== null, 'Invalid CSRF deleted doctor.');
    $_POST['csrf_token'] = $_SESSION['doctors_csrf_token'];
    ob_start(); include __DIR__ . '/../admin/admin_doctors.php'; $deleteHtml = ob_get_clean();
    doctorCheck(str_contains($deleteHtml,'Doctor deleted.') && getDoctor($pdo,$profileId) === null, 'Doctor deletion failed.');
    doctorCheck($pdo->query('SELECT COUNT(*) FROM videos WHERE id=' . $caseId)->fetchColumn() == 1, 'Deleting doctor deleted a case.');
    doctorCheck(count(videoDoctorMap($pdo,[$caseId],false)[$caseId]) === 1, 'Deleting doctor did not clean its team link.');
    $pdo->prepare('DELETE FROM videos WHERE id=?')->execute([$caseId]);
    doctorCheck(videoDoctorMap($pdo,[$caseId],false) === [], 'Deleting video left team links.');
    echo "Doctor CRUD, slug stability, publication, teams, rollback, upload validation, public page, and CSRF passed.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
