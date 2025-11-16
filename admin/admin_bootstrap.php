<?php
/**
 * Admin include bootstrapper.
 * Provides resilient loading for shared include files when admin pages are
 * executed from different working directories or hosting environments.
 */

/**
 * Require an include file using several fallback locations.
 *
 * @param string $relativePath Path relative to project root or /includes
 * @return string The resolved path that was loaded
 */
function require_admin_include(string $relativePath): string
{
    $relativePath = ltrim($relativePath, '/');

    $searchPaths = [
        __DIR__ . '/' . $relativePath,
        __DIR__ . '/../' . $relativePath,
        dirname(__DIR__) . '/' . $relativePath,
        __DIR__ . '/includes/' . $relativePath,
        dirname(__DIR__) . '/includes/' . $relativePath,
    ];

    foreach ($searchPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return $path;
        }
    }

    http_response_code(500);
    $message = 'Critical include missing: ' . $relativePath;

    if (php_sapi_name() === 'cli') {
        throw new RuntimeException($message);
    }

    die($message);
}
