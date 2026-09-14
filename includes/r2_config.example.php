<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;

$bucketName = getenv('R2_BUCKET_NAME') ?: 'CHANGE_ME';
$accountId = getenv('R2_ACCOUNT_ID') ?: 'CHANGE_ME';
$accessKey = getenv('R2_ACCESS_KEY_ID') ?: 'CHANGE_ME';
$secretKey = getenv('R2_SECRET_ACCESS_KEY') ?: 'CHANGE_ME';

$s3Client = new S3Client([
    'region' => 'auto',
    'endpoint' => "https://{$accountId}.r2.cloudflarestorage.com",
    'version' => 'latest',
    'credentials' => [
        'key' => $accessKey,
        'secret' => $secretKey,
    ],
]);

