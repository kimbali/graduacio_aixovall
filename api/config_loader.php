<?php
declare(strict_types=1);

function loadApiConfig(): void
{
    if (defined('DB_HOST') || defined('SMTP_HOST')) {
        return;
    }

    require_once shouldUseLocalConfig()
        ? __DIR__ . '/local_config.php'
        : __DIR__ . '/config.php';
}

function shouldUseLocalConfig(): bool
{
    $appEnv = strtolower((string) getenv('APP_ENV'));
    if ($appEnv === 'local' || $appEnv === 'development') {
        return true;
    }

    if ($appEnv === 'production') {
        return false;
    }

    $serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''));

    return PHP_SAPI === 'cli-server'
        || str_starts_with($serverName, 'localhost')
        || str_starts_with($serverName, '127.0.0.1');
}
