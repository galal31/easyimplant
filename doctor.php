<?php
if (!defined('EASYIMPLANT_SKIP_SESSION')) define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/case_videos.php';
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$slug = trim((string) ($_GET['slug'] ?? ''));
$doctor = null;
$cases = [];
$teams = [];
$unavailable = false;
try {
    if ($slug !== '' && mb_strlen($slug) <= 191) $doctor = getPublishedDoctor($pdo, $slug);
    if ($doctor) {
        $stmt = $pdo->prepare('SELECT v.id,v.title,v.description,v.video_url,vd.contribution FROM videos v JOIN video_doctors vd ON vd.video_id=v.id WHERE vd.doctor_id=? ORDER BY v.created_at DESC,v.id DESC');
        $stmt->execute([$doctor['id']]);
        $cases = array_values(array_filter($stmt->fetchAll(), static fn($case) => caseVideoEmbedUrl($case['video_url']) !== null));
        $teams = videoDoctorMap($pdo, array_column($cases, 'id'));
    }
} catch (PDOException $e) {
    error_log('Doctor profile unavailable: ' . $e->getMessage());
    $unavailable = true;
    $doctor = null;
}
if (!$doctor) http_response_code($unavailable ? 503 : 404);
$qualifications = $doctor ? array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $doctor['qualifications'])))) : [];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="scroll-smooth">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($doctor ? $doctor['display_name'] . ' | Easy Implant' : 'Doctor profile | Easy Implant') ?></title>
    <meta name="description" content="<?= $escape($doctor ? mb_substr(strip_tags($doctor['short_bio'] ?: $doctor['display_name'] . ' — ' . $doctor['specialty']), 0, 160) : 'Doctor profile on Easy Implant.') ?>">
    <?php if (!$doctor): ?><meta name="robots" content="noindex"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/case-videos.css"><link rel="stylesheet" href="css/doctor-profile.css">
</head>
<body class="home-page doctor-page">
    <?php ob_start(); include __DIR__ . '/includes/header.php'; echo str_replace('href="#', 'href="index.php#', ob_get_clean()); ?>
    <main>
        <?php if (!$doctor): ?>
            <section class="doctor-unavailable profile-shell">
                <p class="profile-eyebrow">Easy Implant</p>
                <h1 data-i18n="profile_unavailable_title">This profile is unavailable</h1>
                <p data-i18n="profile_unavailable_text">The doctor profile may not be published, or the link may have changed.</p>
                <a href="index.php" class="profile-text-link" data-i18n="profile_back_home">Back to home</a>
            </section>
        <?php else: ?>
            <section class="doctor-intro profile-shell" aria-labelledby="doctor-name">
                <div class="doctor-intro-copy">
                    <a href="index.php#real-cases" class="profile-back-link"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span data-i18n="profile_back_cases">Explore real cases</span></a>
                    <h1 id="doctor-name" dir="auto"><?= $escape($doctor['display_name']) ?></h1>
                    <?php if ($doctor['specialty']): ?><p class="doctor-specialty" dir="auto"><?= $escape($doctor['specialty']) ?></p><?php endif; ?>
                    <?php if ($doctor['short_bio']): ?><p class="doctor-summary" dir="auto"><?= nl2br($escape($doctor['short_bio'])) ?></p><?php endif; ?>
                    <?php if ($cases): ?><a href="#doctor-cases" class="profile-text-link"><span data-i18n="profile_watch_cases">Watch clinical cases</span><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></a><?php endif; ?>
                </div>
                <figure class="doctor-portrait">
                    <?php if ($doctor['photo_path']): ?><img src="<?= $escape($doctor['photo_path']) ?>" alt="<?= $escape($doctor['display_name']) ?>" width="720" height="900" fetchpriority="high"><?php else: ?><div class="doctor-portrait-placeholder" aria-hidden="true"><span><?= $escape(mb_substr($doctor['display_name'], 0, 1)) ?></span></div><?php endif; ?>
                    <figcaption><span><?= $escape($doctor['display_name']) ?></span></figcaption>
                </figure>
            </section>
            <?php if ($qualifications): ?>
                <section class="doctor-credentials profile-shell" aria-labelledby="credentials-title">
                    <div><p class="profile-eyebrow" data-i18n="profile_background">Professional background</p><h2 id="credentials-title" data-i18n="profile_qualifications">Qualifications</h2></div>
                    <ul><?php foreach ($qualifications as $qualification): ?><li dir="auto"><?= $escape($qualification) ?></li><?php endforeach; ?></ul>
                </section>
            <?php endif; ?>
            <?php if ($cases): ?>
                <section id="doctor-cases" class="doctor-case-section" aria-labelledby="doctor-cases-title">
                    <div class="profile-shell">
                        <div class="doctor-case-heading"><div><p class="profile-eyebrow" data-i18n="cases_badge">Real cases</p><h2 id="doctor-cases-title" data-i18n="profile_case_title">Clinical work, in focus</h2></div><p data-i18n="profile_case_intro">Choose a case to explore the work and the team behind it.</p></div>
                        <div class="doctor-case-layout">
                            <div class="doctor-case-stage" id="doctor-case-stage">
                                <?php $firstCase = $cases[0]; renderCaseVideoPlayer($firstCase['video_url'], $firstCase['title']); ?>
                                <div class="doctor-case-detail"><h3 dir="auto"><?= $escape($firstCase['title']) ?></h3><?php if ($firstCase['description']): ?><p dir="auto"><?= nl2br($escape($firstCase['description'])) ?></p><?php endif; ?><?php renderVideoDoctorLinks($teams[$firstCase['id']] ?? []); ?></div>
                            </div>
                            <div class="doctor-case-list" aria-label="Clinical cases" data-i18n-aria-label="profile_case_list">
                                <?php foreach ($cases as $position => $case): ?><button type="button" class="doctor-case-choice" data-case-target="case-<?= (int) $case['id'] ?>" aria-controls="doctor-case-stage" aria-pressed="<?= $position === 0 ? 'true' : 'false' ?>"><span class="doctor-case-choice-title" dir="auto"><?= $escape($case['title']) ?></span><?php if ($case['contribution']): ?><span class="doctor-case-contribution" dir="auto"><?= $escape($case['contribution']) ?></span><?php endif; ?><span class="doctor-case-choice-arrow" aria-hidden="true">↗</span></button><?php endforeach; ?>
                            </div>
                        </div>
                        <p id="case-selection-status" class="sr-only" role="status"></p>
                        <?php foreach ($cases as $case): ?><template id="case-<?= (int) $case['id'] ?>"><?php renderCaseVideoPlayer($case['video_url'], $case['title']); ?><div class="doctor-case-detail"><h3 dir="auto"><?= $escape($case['title']) ?></h3><?php if ($case['description']): ?><p dir="auto"><?= nl2br($escape($case['description'])) ?></p><?php endif; ?><?php renderVideoDoctorLinks($teams[$case['id']] ?? []); ?></div></template><?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="js/translations.js"></script><script src="js/main.js"></script><script src="js/case-videos.js"></script><script src="js/doctor-profile.js"></script>
</body>
</html>
