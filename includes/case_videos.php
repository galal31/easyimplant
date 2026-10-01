<?php

function caseVideoEmbedUrl(string $url): ?string
{
    $url = trim($url);
    if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $parts = parse_url($url);
    $host = strtolower($parts['host'] ?? '');
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https'
        || !in_array($host, ['iframe.mediadelivery.net', 'player.mediadelivery.net'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
        || !preg_match('~^/embed/[0-9]+/[a-f0-9-]{36}/?$~i', $parts['path'] ?? '')) {
        return null;
    }
    return 'https://' . $host . rtrim($parts['path'], '/');
}

function renderCaseVideoPlayer(string $url, string $title): void
{
    $embedUrl = caseVideoEmbedUrl($url);
    if ($embedUrl === null) {
        return;
    }
    ?>
    <div class="case-video-player" data-case-video>
        <button type="button" class="case-video-play" data-video-src="<?= htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Play <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
            <span class="case-video-play-icon" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
            <span class="case-video-play-label">Play video</span>
        </button>
    </div>
    <?php
}

function renderCaseVideos(PDO $pdo, int $limit = 6): void
{
    try {
        $stmt = $pdo->query("SELECT title, description, video_url FROM videos WHERE video_url LIKE 'https://iframe.mediadelivery.net/embed/%' OR video_url LIKE 'https://player.mediadelivery.net/embed/%' ORDER BY created_at DESC, id DESC LIMIT " . (int) $limit);
        $videos = [];
        foreach ($stmt as $video) {
            if (caseVideoEmbedUrl((string) $video['video_url']) !== null) {
                $videos[] = $video;
            }
            if (count($videos) >= $limit) {
                break;
            }
        }
    } catch (PDOException $e) {
        error_log('Case videos unavailable: ' . $e->getMessage());
        return;
    }
    if (!$videos) {
        return;
    }
    ?>
    <section id="real-cases" class="case-videos-section" aria-labelledby="case-videos-title">
        <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
            <div class="section-heading reveal">
                <p class="section-eyebrow" data-i18n="cases_badge">Real cases</p>
                <h2 id="case-videos-title" data-i18n="cases_title">Real implant cases in video</h2>
                <p data-i18n="cases_intro">Explore examples from clinical cases. Videos load only when you choose to play them.</p>
            </div>
            <div class="case-videos-grid">
                <?php foreach ($videos as $video): ?>
                    <article class="case-video-card reveal">
                        <?php renderCaseVideoPlayer((string) $video['video_url'], (string) $video['title']); ?>
                        <div class="case-video-copy">
                            <h3><?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <?php if (trim((string) $video['description']) !== ''): ?>
                                <p><?= nl2br(htmlspecialchars($video['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}
