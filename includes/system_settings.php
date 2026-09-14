<?php

function getSystemSetting(PDO $pdo, string $key, ?string $default = null): ?string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute([':setting_key' => $key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string) $value;
    } catch (PDOException $e) {
        // Keep public pages usable until the system_settings migration is applied.
        if ($e->getCode() !== '42S02') {
            error_log('System Setting Read Error: ' . $e->getMessage());
        }
        return $default;
    }
}

function setSystemSetting(
    PDO $pdo,
    string $key,
    ?string $value,
    string $valueType = 'text',
    string $group = 'general',
    ?int $updatedBy = null
): void {
    $stmt = $pdo->prepare("INSERT INTO system_settings
        (setting_key, setting_value, value_type, setting_group, updated_by)
        VALUES (:setting_key, :setting_value, :value_type, :setting_group, :updated_by)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            setting_group = VALUES(setting_group),
            updated_by = VALUES(updated_by)");
    $stmt->execute([
        ':setting_key' => $key,
        ':setting_value' => $value,
        ':value_type' => $valueType,
        ':setting_group' => $group,
        ':updated_by' => $updatedBy,
    ]);
}

function getSystemIconPath(PDO $pdo): ?string
{
    $path = getSystemSetting($pdo, 'system_icon_path');
    if (!$path || !preg_match('#^uploads/settings/system-icon-[a-f0-9]{32}\.(?:png|jpg|webp)$#', $path)) {
        return null;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    return is_file($absolutePath) ? $path : null;
}

function getSystemIconUrl(PDO $pdo, string $relativePrefix = ''): ?string
{
    $path = getSystemIconPath($pdo);
    if ($path === null) {
        return null;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

    return $relativePrefix . $encodedPath . '?v=' . filemtime($absolutePath);
}
