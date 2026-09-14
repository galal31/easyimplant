<?php

function normalizeDatabaseRequestHost(string $host): string
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return '';
    }

    if ($host[0] === '[') {
        $closingBracket = strpos($host, ']');
        return $closingBracket === false ? $host : substr($host, 1, $closingBracket - 1);
    }

    if (substr_count($host, ':') > 1) {
        return $host;
    }

    return (string) preg_replace('/:\d+$/', '', $host);
}

function resolveDatabaseConfig(string $requestHost = '', ?string $appEnvironment = null, ?bool $isCli = null): array
{
    $environmentOverride = strtolower(trim((string) $appEnvironment));
    $normalizedHost = normalizeDatabaseRequestHost($requestHost);
    $isCli = $isCli ?? in_array(PHP_SAPI, ['cli', 'phpdbg'], true);

    if ($environmentOverride === 'local') {
        $isLocal = true;
    } elseif ($environmentOverride === 'production') {
        $isLocal = false;
    } else {
        $isLocal = $isCli
            || $normalizedHost === ''
            || in_array($normalizedHost, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($normalizedHost, '.localhost');
    }

    if ($isLocal) {
        return [
            'environment' => 'local',
            'host' => getenv('DB_LOCAL_HOST') ?: '127.0.0.1',
            'name' => getenv('DB_LOCAL_NAME') ?: 'easyimplant_db',
            'user' => getenv('DB_LOCAL_USER') ?: 'root',
            'password' => getenv('DB_LOCAL_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ];
    }

    return [
        'environment' => 'production',
        'host' => getenv('DB_HOST') ?: 'CHANGE_ME',
        'name' => getenv('DB_NAME') ?: 'CHANGE_ME',
        'user' => getenv('DB_USER') ?: 'CHANGE_ME',
        'password' => getenv('DB_PASSWORD') ?: 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ];
}

function currentDatabaseConfig(): array
{
    $appEnvironment = getenv('APP_ENV');

    return resolveDatabaseConfig(
        (string) ($_SERVER['HTTP_HOST'] ?? ''),
        $appEnvironment === false ? null : $appEnvironment
    );
}

