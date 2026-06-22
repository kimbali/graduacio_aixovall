<?php
declare(strict_types=1);

/**
 * Secrets loader compatible amb CDmon (open_basedir).
 *
 * - Producció: /usr/home/rocanolich.com/secrets/*.php (fora de /web, permès)
 * - Fallback local: /web/api/secrets/*.php
 * - Fallback repositori: /secrets/*.php
 */
function load_secrets(string $name): array
{
    static $cache = [];

    if (isset($cache[$name])) {
        return $cache[$name];
    }

    $fileName = basename($name) . '.php';
    $webRoot = dirname(__DIR__);
    $repoRoot = dirname(__DIR__, 2);

    $paths = [
        $webRoot . '/../secrets/' . $fileName,
        __DIR__ . '/secrets/' . $fileName,
        $repoRoot . '/secrets/' . $fileName,
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            $cfg = require $path;

            if (!is_array($cfg)) {
                throw new RuntimeException($fileName . ' ha de retornar un array');
            }

            return $cache[$name] = $cfg;
        }
    }

    throw new RuntimeException('No trobo secrets ' . $name . ': ' . implode(' (ni ', $paths) . str_repeat(')', count($paths) - 1));
}
