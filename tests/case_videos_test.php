<?php
define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/case_videos.php';

function checkCaseVideo(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$validUrl = 'https://player.mediadelivery.net/embed/651916/2184b24d-1217-4a1c-adcd-8aaff03c9f10';
checkCaseVideo(caseVideoEmbedUrl($validUrl) === $validUrl, 'Bunny URL rejected');
checkCaseVideo(caseVideoEmbedUrl('https://iframe.mediadelivery.net/embed/651916/2184b24d-1217-4a1c-adcd-8aaff03c9f10') !== null, 'Legacy Bunny URL rejected');
checkCaseVideo(caseVideoEmbedUrl('https://evil.example/embed/12345/12345678-1234-1234-1234-123456789abc') === null, 'Other host accepted');
checkCaseVideo(caseVideoEmbedUrl('javascript:alert(1)') === null, 'Unsafe URL accepted');

$_SESSION = ['user_id' => 1, 'role' => 'admin', 'full_name' => 'Test Admin'];
$_SERVER['PHP_SELF'] = 'admin_videos.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
chdir(__DIR__ . '/../admin');
$pdo->beginTransaction();
try {
    ob_start(); include __DIR__ . '/../admin/admin_videos.php'; $html = ob_get_clean();
    checkCaseVideo(str_contains($html, 'id="admin-sidebar"'), 'Admin sidebar missing');
    foreach (['admin_dashboard.php', 'admin_clinics.php', 'admin_requests.php', 'admin_implant_types.php', 'admin_guide_pricing.php', 'admin_surgeon_travel_pricing.php', 'admin_surgeon_services.php', 'admin_settings.php', 'admin_videos.php'] as $href) {
        checkCaseVideo(str_contains($html, 'href="' . $href . '"'), 'Admin link missing: ' . $href);
    }

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf_token' => $_SESSION['case_videos_csrf_token'], 'action' => 'save', 'id' => '0', 'title' => 'Test case', 'description' => 'Initial description', 'video_url' => $validUrl];
    ob_start(); include __DIR__ . '/../admin/admin_videos.php'; $html = ob_get_clean();
    checkCaseVideo(str_contains($html, 'Case video added.'), 'Create failed');
    $id = (int) $pdo->query("SELECT id FROM videos WHERE title = 'Test case' ORDER BY id DESC LIMIT 1")->fetchColumn();
    checkCaseVideo($id > 0, 'Created ID missing');

    ob_start(); renderCaseVideos($pdo); $publicHtml = ob_get_clean();
    checkCaseVideo(str_contains($publicHtml, 'Test case'), 'Public case missing');
    checkCaseVideo(str_contains($publicHtml, 'data-video-src="' . $validUrl . '"'), 'Lazy play control missing');
    checkCaseVideo(!str_contains($publicHtml, '<iframe'), 'Video loaded before click');

    $_POST = ['csrf_token' => $_SESSION['case_videos_csrf_token'], 'action' => 'save', 'id' => (string) $id, 'title' => 'Edited case', 'description' => 'Updated description', 'video_url' => $validUrl];
    ob_start(); include __DIR__ . '/../admin/admin_videos.php'; $html = ob_get_clean();
    checkCaseVideo(str_contains($html, 'Case video updated.'), 'Update failed');
    checkCaseVideo($pdo->query('SELECT title FROM videos WHERE id = ' . $id)->fetchColumn() === 'Edited case', 'Update not persisted');

    $_POST['csrf_token'] = 'invalid';
    $_POST['action'] = 'delete';
    ob_start(); include __DIR__ . '/../admin/admin_videos.php'; $html = ob_get_clean();
    checkCaseVideo(str_contains($html, 'Your session expired.'), 'CSRF not rejected');
    checkCaseVideo((int) $pdo->query('SELECT COUNT(*) FROM videos WHERE id = ' . $id)->fetchColumn() === 1, 'Invalid CSRF deleted video');

    $_POST['csrf_token'] = $_SESSION['case_videos_csrf_token'];
    ob_start(); include __DIR__ . '/../admin/admin_videos.php'; $html = ob_get_clean();
    checkCaseVideo(str_contains($html, 'Case video deleted.'), 'Delete failed');
    checkCaseVideo((int) $pdo->query('SELECT COUNT(*) FROM videos WHERE id = ' . $id)->fetchColumn() === 0, 'Delete not persisted');
    echo "Case video CRUD, URL validation, lazy render, and admin navigation passed.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
