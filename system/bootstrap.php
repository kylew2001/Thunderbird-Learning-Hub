<?php
// system/bootstrap.php

// APP_ROOT = /path/to/.../htdocs
define('APP_ROOT', dirname(__DIR__));

// Handy derived paths
define('APP_SYSTEM',  APP_ROOT . '/system');
define('APP_INCLUDES', APP_ROOT . '/includes');
define('APP_LOGS',    APP_ROOT . '/logs');
define('APP_UPLOADS', APP_ROOT . '/uploads');

// Optionally: a base URL if you want to generate links consistently
// This can be read from config or set per environment.
if (!defined('APP_BASE_URL')) {
    // e.g. 'https://devknowledgebase.xo.je'
    define('APP_BASE_URL', 'https://devknowledgebase.xo.je');
}

// Optional helpers
function app_path(string $path = ''): string {
    return APP_ROOT . ($path ? '/' . ltrim($path, '/') : '');
}

function app_url(string $path = ''): string {
    // If you later move everything under /kb/ you only fix it in one place
    $basePath = ''; // e.g. '/kb' if app isn’t in web root
    return rtrim(APP_BASE_URL, '/') . $basePath . ($path ? '/' . ltrim($path, '/') : '');
}
