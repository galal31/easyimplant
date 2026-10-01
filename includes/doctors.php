<?php

function doctorProfileUrl(string $slug): string
{
    return 'doctor.php?slug=' . rawurlencode($slug);
}

function doctorSlug(string $name): string
{
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower(trim($name), 'UTF-8'));
    return trim(mb_substr((string) $slug, 0, 160, 'UTF-8'), '-');
}

function getDoctor(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM doctors WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getPublishedDoctor(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT id, display_name, slug, specialty, short_bio, qualifications, photo_path FROM doctors WHERE slug = ? AND is_published = 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function saveDoctor(PDO $pdo, array $data, int $id = 0): int
{
    $existing = $id ? getDoctor($pdo, $id) : null;
    if ($id && !$existing) throw new InvalidArgumentException('Doctor not found.');
    $name = trim((string) ($data['display_name'] ?? ''));
    $specialty = trim((string) ($data['specialty'] ?? ''));
    $bio = trim((string) ($data['short_bio'] ?? ''));
    $qualifications = trim((string) ($data['qualifications'] ?? ''));
    if ($name === '' || mb_strlen($name) > 190) throw new InvalidArgumentException('Enter a doctor name of up to 190 characters.');
    if (mb_strlen($specialty) > 190 || mb_strlen($bio) > 10000 || mb_strlen($qualifications) > 10000) throw new InvalidArgumentException('Specialty must be up to 190 characters; biography and qualifications up to 10,000 each.');

    $requestedSlug = trim((string) ($data['slug'] ?? ''));
    $automatic = $requestedSlug === '' && !$existing;
    $slug = $requestedSlug === '' && $existing ? $existing['slug'] : doctorSlug($requestedSlug ?: $name);
    if ($slug === '') $slug = 'doctor-' . bin2hex(random_bytes(5));
    $check = $pdo->prepare('SELECT id FROM doctors WHERE slug = ? AND id <> ?');
    $base = $slug;
    for ($suffix = 2; ; $suffix++) {
        $check->execute([$slug, $id]);
        if (!$check->fetchColumn()) break;
        if (!$automatic) throw new InvalidArgumentException('This profile link is already used. Choose another slug.');
        $slug = $base . '-' . $suffix;
    }

    $userId = null;
    if ((string) ($data['user_id'] ?? '') !== '') {
        $userId = filter_var($data['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$userId) throw new InvalidArgumentException('Choose a valid surgeon account.');
        $user = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'surgeon'");
        $user->execute([$userId]);
        if (!$user->fetchColumn()) throw new InvalidArgumentException('The linked account must be a surgeon.');
    }
    $photo = $data['photo_path'] ?? ($existing['photo_path'] ?? null);
    if ($photo !== null && !preg_match('~^uploads/doctors/doctor-[a-f0-9]{32}\.(jpg|png|webp)$~', $photo)) throw new InvalidArgumentException('Invalid doctor photo.');
    $values = [$userId, $name, $slug, $specialty, $bio, $qualifications, $photo, !empty($data['is_published']) ? 1 : 0];
    try {
        if ($id) {
            $values[] = $id;
            $pdo->prepare('UPDATE doctors SET user_id=?, display_name=?, slug=?, specialty=?, short_bio=?, qualifications=?, photo_path=?, is_published=? WHERE id=?')->execute($values);
        } else {
            $pdo->prepare('INSERT INTO doctors (user_id, display_name, slug, specialty, short_bio, qualifications, photo_path, is_published) VALUES (?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) throw new InvalidArgumentException('The slug or surgeon account is already linked to another doctor.');
        throw $e;
    }
    return $id;
}

function validateDoctorPhoto(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) throw new InvalidArgumentException('Choose an image smaller than 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $size = @getimagesize($file['tmp_name']);
    if (!isset($types[$mime]) || !$size || min($size[0], $size[1]) < 100 || max($size[0], $size[1]) > 6000) throw new InvalidArgumentException('Use a real JPG, PNG, or WebP image between 100 and 6,000 pixels.');
    return $types[$mime];
}

function uploadDoctorPhoto(array $file): string
{
    $extension = validateDoctorPhoto($file);
    $directory = dirname(__DIR__) . '/uploads/doctors';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Could not prepare photo storage.');
    $path = 'uploads/doctors/doctor-' . bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__) . '/' . $path)) throw new RuntimeException('Could not save the photo.');
    return $path;
}

function removeDoctorPhoto(?string $path): void
{
    if ($path && preg_match('~^uploads/doctors/doctor-[a-f0-9]{32}\.(jpg|png|webp)$~', $path)) {
        $absolute = dirname(__DIR__) . '/' . $path;
        $directory = realpath(dirname(__DIR__) . '/uploads/doctors');
        if ($directory && is_file($absolute) && dirname(realpath($absolute)) === $directory) @unlink($absolute);
    }
}

function videoDoctorMap(PDO $pdo, array $videoIds, bool $publishedOnly = true): array
{
    $ids = array_values(array_unique(array_map('intval', $videoIds)));
    if (!$ids) return [];
    $stmt = $pdo->prepare('SELECT vd.video_id, vd.doctor_id, vd.contribution, vd.display_order, d.display_name, d.slug, d.is_published FROM video_doctors vd JOIN doctors d ON d.id = vd.doctor_id WHERE vd.video_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' . ($publishedOnly ? ' AND d.is_published = 1' : '') . ' ORDER BY vd.display_order, d.display_name');
    $stmt->execute($ids);
    $map = [];
    foreach ($stmt as $row) $map[$row['video_id']][] = $row;
    return $map;
}

function saveVideoDoctors(PDO $pdo, int $videoId, array $ids, array $contributions = []): void
{
    $ids = array_values($ids);
    if (count($ids) > 30) throw new InvalidArgumentException('Choose up to 30 doctors per case.');
    $seen = [];
    $rows = [];
    foreach ($ids as $position => $rawId) {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id || isset($seen[$id])) throw new InvalidArgumentException('Choose each existing doctor once.');
        $seen[$id] = true;
        if (!getDoctor($pdo, $id)) throw new InvalidArgumentException('One of the selected doctors no longer exists.');
        $role = trim((string) ($contributions[$position] ?? ''));
        if (mb_strlen($role) > 190) throw new InvalidArgumentException('Each doctor’s contribution must be up to 190 characters.');
        $rows[] = [$videoId, $id, $role, $position];
    }
    $pdo->prepare('DELETE FROM video_doctors WHERE video_id = ?')->execute([$videoId]);
    $insert = $pdo->prepare('INSERT INTO video_doctors (video_id,doctor_id,contribution,display_order) VALUES (?,?,?,?)');
    foreach ($rows as $row) $insert->execute($row);
}

function renderVideoDoctorLinks(array $doctors): void
{
    if (!$doctors) return;
    echo '<ul class="case-doctor-links">';
    foreach ($doctors as $doctor) {
        echo '<li><a href="' . htmlspecialchars(doctorProfileUrl($doctor['slug']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($doctor['display_name'], ENT_QUOTES, 'UTF-8') . '</a>';
        if ($doctor['contribution'] !== '') echo '<span>' . htmlspecialchars($doctor['contribution'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</li>';
    }
    echo '</ul>';
}
