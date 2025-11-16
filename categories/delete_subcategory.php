<?php
/**
 * Delete Subcategory Handler
 * Deletes a subcategory and all associated posts, replies, and files
 * CASCADE delete will handle related records automatically
 */

$config_path = dirname(__DIR__) . '/system/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

if (!function_exists('resolve_includes_base')) {
    function resolve_includes_base(): string {
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        $candidates = [];

        if (defined('APP_INCLUDES')) {
            $candidates[] = rtrim(APP_INCLUDES, '/');
        }

        $candidates[] = __DIR__ . '/includes';
        $candidates[] = __DIR__ . '/../includes';
        $candidates[] = dirname(__DIR__) . '/includes';

        foreach ($candidates as $candidate) {
            if ($candidate && is_dir($candidate)) {
                $base = $candidate;
                return $base;
            }
        }

        return '';
    }
}

$includes_base = resolve_includes_base();
if (empty($includes_base)) {
    http_response_code(500);
    echo 'Required includes directory is missing.';
    exit;
}

require_once $includes_base . '/auth_check.php';
require_once $includes_base . '/db_connect.php';

// Get subcategory ID
$subcategory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($subcategory_id <= 0) {
    header('Location: index.php');
    exit;
}

// Delete subcategory (CASCADE will handle posts, replies, files)
try {
    $stmt = $pdo->prepare("DELETE FROM subcategories WHERE id = ?");
    $stmt->execute([$subcategory_id]);

    // Redirect to home with success message
    header('Location: index.php?success=subcategory_deleted');
    exit;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    // Redirect with error
    header('Location: index.php');
    exit;
}
?>
