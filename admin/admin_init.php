<?php
/**
 * Admin bootstrap for consistent include resolution.
 *
 * Ensures critical includes are loaded from the correct directory even when
 * admin pages are executed from different working directories.
 */

if (!function_exists('admin_include_base')) {
    /**
     * Locate the includes directory by checking local, parent, and configured paths.
     */
    function admin_include_base(): string
    {
        static $includeBase = null;

        if ($includeBase !== null) {
            return $includeBase;
        }

        $projectRoot = dirname(__DIR__);
        $configPath  = $projectRoot . '/system/config.php';

        if (file_exists($configPath)) {
            require_once $configPath;
        }

        $candidates = [
            __DIR__ . '/includes',
            $projectRoot . '/includes',
        ];

        if (defined('APP_INCLUDES')) {
            $candidates[] = APP_INCLUDES;
        }

        foreach ($candidates as $candidate) {
            if ($candidate && is_dir($candidate) && file_exists($candidate . '/auth_check.php')) {
                $includeBase = rtrim($candidate, '/');
                break;
            }
        }

        if ($includeBase === null) {
            http_response_code(500);
            die('Required includes directory is missing.');
        }

        return $includeBase;
    }

    /**
     * Require a file from the resolved includes directory with defensive checks.
     */
    function require_admin_include(string $filename): void
    {
        $includeBase = admin_include_base();
        $fullPath    = $includeBase . '/' . ltrim($filename, '/');

        if (!file_exists($fullPath)) {
            http_response_code(500);
            die('Required include file is missing.');
        }

        require_once $fullPath;
    }
}

// Load the common includes needed by admin pages.
require_admin_include('auth_check.php');
require_admin_include('db_connect.php');
require_admin_include('user_helpers.php');
