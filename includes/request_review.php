<?php

const REQUEST_MESSAGE_MAX_LENGTH = 4000;
const REQUEST_MESSAGE_REFRESH_COOLDOWN_SECONDS = 5;
const REQUEST_REVIEW_MAX_FILE_SIZE = 262144000;

function consumeRequestMessageRefreshLimit(
    int $requestId,
    int $userId,
    string $role,
    ?float $now = null
): int {
    $now ??= microtime(true);
    $sessionKey = 'request_message_refresh_limits';
    $limits = $_SESSION[$sessionKey] ?? [];
    if (!is_array($limits)) $limits = [];

    $limitKey = $role . ':' . $userId . ':' . $requestId;
    $lastRefresh = (float) ($limits[$limitKey] ?? 0);
    $remaining = REQUEST_MESSAGE_REFRESH_COOLDOWN_SECONDS - ($now - $lastRefresh);
    if ($remaining > 0) {
        return min(
            REQUEST_MESSAGE_REFRESH_COOLDOWN_SECONDS,
            max(1, (int) ceil($remaining))
        );
    }

    foreach ($limits as $key => $timestamp) {
        if ((float) $timestamp <= $now - 60) unset($limits[$key]);
    }
    $limits[$limitKey] = $now;
    $_SESSION[$sessionKey] = $limits;

    return 0;
}

function surgicalGuideChatIsWritable(string $status): bool
{
    return in_array($status, ['pending_review', 'awaiting_clinic_approval', 'pending_payment', 'in_progress'], true);
}

function requestReviewAllowedFiles(): array
{
    return [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime'],
        'doc' => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    ];
}

function normalizeRequestReviewUpload(string $filename, string $contentType, int $fileSize): array
{
    $originalName = basename(str_replace('\\', '/', trim($filename)));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $contentType = strtolower(trim(explode(';', $contentType, 2)[0]));
    $allowed = requestReviewAllowedFiles();

    if (isset($allowed[$extension]) && ($contentType === '' || $contentType === 'application/octet-stream')) {
        $contentType = $allowed[$extension][0];
    }

    if (
        $originalName === ''
        || mb_strlen($originalName) > 255
        || !isset($allowed[$extension])
        || !in_array($contentType, $allowed[$extension], true)
        || $fileSize < 1
        || $fileSize > REQUEST_REVIEW_MAX_FILE_SIZE
    ) {
        throw new InvalidArgumentException('Allowed review files are JPG, PNG, WebP, PDF, MP4, WebM, MOV, Word, Excel, and PowerPoint up to 250 MB each.');
    }

    return [
        'original_name' => $originalName,
        'extension' => $extension,
        'content_type' => $contentType,
        'file_size' => $fileSize,
    ];
}

function detectRequestReviewFileType(string $bytes): ?string
{
    if (str_starts_with($bytes, "\xFF\xD8\xFF")) return 'image/jpeg';
    if (str_starts_with($bytes, "\x89PNG\x0D\x0A\x1A\x0A")) return 'image/png';
    if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') return 'image/webp';
    if (str_starts_with($bytes, '%PDF-')) return 'application/pdf';
    if (strlen($bytes) >= 8 && substr($bytes, 4, 4) === 'ftyp') return 'video/mp4';
    if (str_starts_with($bytes, "\x1A\x45\xDF\xA3")) return 'video/webm';
    if (str_starts_with($bytes, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) return 'application/x-ole-storage';
    if (str_starts_with($bytes, "PK\x03\x04") || str_starts_with($bytes, "PK\x05\x06") || str_starts_with($bytes, "PK\x07\x08")) return 'application/zip';

    return null;
}

function requestReviewDetectedTypeMatches(string $extension, string $detectedType): bool
{
    $expected = requestReviewAllowedFiles()[$extension] ?? [];
    if ($extension === 'mov' && $detectedType === 'video/mp4') return true;

    return in_array($detectedType, $expected, true);
}

function fetchRequestReviewPackages(PDO $pdo, int $requestId): array
{
    $stmt = $pdo->prepare("SELECT rp.*, admin.full_name AS admin_name, approver.full_name AS approver_name
        FROM request_review_packages rp
        LEFT JOIN users admin ON admin.id = rp.admin_id
        LEFT JOIN users approver ON approver.id = rp.approved_by
        WHERE rp.request_id = :request_id
        ORDER BY rp.id DESC");
    $stmt->execute([':request_id' => $requestId]);
    $packages = $stmt->fetchAll();
    if (!$packages) return [];

    $packageIds = array_map(static fn(array $package): int => (int) $package['id'], $packages);
    $placeholders = implode(',', array_fill(0, count($packageIds), '?'));
    $filesStmt = $pdo->prepare("SELECT id, package_id, file_path, original_name, content_type, file_size, created_at
        FROM request_review_files WHERE package_id IN ($placeholders) ORDER BY id");
    $filesStmt->execute($packageIds);
    $filesByPackage = [];
    foreach ($filesStmt->fetchAll() as $file) {
        $filesByPackage[(int) $file['package_id']][] = $file;
    }

    foreach ($packages as &$package) {
        $package['files'] = $filesByPackage[(int) $package['id']] ?? [];
    }
    unset($package);

    return $packages;
}

function fetchRequestMessages(PDO $pdo, int $requestId, int $afterId = 0): array
{
    $stmt = $pdo->prepare("SELECT m.id, m.sender_id, m.sender_role, m.message_text, m.created_at,
            COALESCE(u.full_name, IF(m.sender_role = 'admin', 'Easy Implant Admin', 'Clinic')) AS sender_name
        FROM request_messages m
        LEFT JOIN users u ON u.id = m.sender_id
        WHERE m.request_id = :request_id AND m.id > :after_id
        ORDER BY m.id ASC");
    $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
    $stmt->bindValue(':after_id', max(0, $afterId), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function requestMessageJson(array $message): array
{
    return [
        'id' => (int) $message['id'],
        'sender_role' => $message['sender_role'],
        'sender_name' => $message['sender_name'],
        'message_text' => $message['message_text'],
        'created_at' => $message['created_at'],
        'created_label' => date('M d, Y, H:i', strtotime($message['created_at'])),
    ];
}

function requireSurgicalGuideConversationAccess(PDO $pdo, int $requestId, int $userId, string $role, bool $forUpdate = false): array
{
    $sql = "SELECT id, user_id, service_type, status FROM requests WHERE id = :id";
    if ($role === 'clinic') $sql .= " AND user_id = :user_id";
    if ($forUpdate) $sql .= " FOR UPDATE";

    $stmt = $pdo->prepare($sql);
    $params = [':id' => $requestId];
    if ($role === 'clinic') $params[':user_id'] = $userId;
    $stmt->execute($params);
    $request = $stmt->fetch();

    if (!$request || $request['service_type'] !== 'surgical_guide') {
        throw new RuntimeException('This Surgical Guide request was not found or is not available to your account.');
    }

    return $request;
}
