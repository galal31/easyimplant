<?php

function uploadedFileDisplayName(?string $originalName, string $filePath): string
{
    $originalName = trim((string) $originalName);
    if ($originalName !== '') {
        return basename(str_replace('\\', '/', $originalName));
    }

    $fallback = basename(str_replace('\\', '/', $filePath));
    $fallback = preg_replace('/^req_\d+_\d+_/i', '', $fallback);
    $fallback = preg_replace('/^guide_(?:(?:kit_)?[a-f0-9]{16}|\d{10})_/i', '', $fallback);

    return $fallback !== '' ? $fallback : 'Uploaded file';
}

function uploadedFileSizeLabel($bytes): string
{
    if ($bytes === null || $bytes === '' || !is_numeric($bytes) || (int) $bytes < 1) {
        return 'Size unavailable';
    }

    $size = (float) $bytes;
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    $precision = $unitIndex === 0 ? 0 : 2;
    return number_format($size, $precision) . ' ' . $units[$unitIndex];
}

function uploadedFileTypeLabel(?string $contentType, string $displayName): string
{
    $contentType = strtolower(trim((string) $contentType));
    $knownTypes = [
        'application/pdf' => 'PDF',
        'video/mp4' => 'MP4 video',
        'image/jpeg' => 'JPEG image',
        'image/png' => 'PNG image',
        'image/webp' => 'WebP image',
        'application/zip' => 'ZIP archive',
        'application/x-rar-compressed' => 'RAR archive',
        'model/stl' => 'STL model',
    ];

    if (isset($knownTypes[$contentType])) {
        return $knownTypes[$contentType];
    }

    $extension = strtoupper((string) pathinfo($displayName, PATHINFO_EXTENSION));
    if ($extension !== '') {
        return $extension . ' file';
    }

    if ($contentType !== '' && $contentType !== 'application/octet-stream') {
        return $contentType;
    }

    return 'File';
}
