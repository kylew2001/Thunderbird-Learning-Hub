<?php
/**
 * Simple include resolver to safely load project files from known roots.
 */

if (!function_exists('require_app_file')) {
    /**
     * Require a file using common project roots (includes/, project root, parent fallback).
     */
    function require_app_file(string $relative_path): void
    {
        $normalized = ltrim($relative_path, '/');

        $candidates = [
            __DIR__ . '/' . $normalized,                 // includes/* (primary)
            dirname(__DIR__) . '/' . $normalized,        // project-root relative
            dirname(__DIR__, 2) . '/' . $normalized,     // parent-of-root fallback
            dirname(__DIR__) . '/includes/' . $normalized // explicit includes/ prefix
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                require_once $candidate;
                return;
            }
        }

        http_response_code(500);
        die('Required file missing: ' . htmlspecialchars($relative_path));
    }
}

