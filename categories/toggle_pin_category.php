<?php
/**
 * Toggle Pin Category Handler
 * AJAX endpoint for pinning/unpinning categories per user
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';

// Disallow pinning for training users
if (function_exists('is_training_user') && is_training_user()) {
    echo json_encode(['success' => false, 'error' => 'Pinning is disabled for training users']);
    exit;
}

header('Content-Type: application/json');

// Only authenticated users can pin/unpin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($category_id <= 0 || !in_array($action, ['pin', 'unpin'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Check if pinned_categories table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_pinned_categories'");
    $table_exists = $stmt->rowCount() > 0;

    if (!$table_exists) {
        echo json_encode(['success' => false, 'error' => 'Pinned categories table not yet created. Please run database migration.']);
        exit;
    }

    // Verify category exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        exit;
    }

    if ($action === 'pin') {
        // Insert pin record (will be ignored if duplicate due to UNIQUE constraint)
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user_pinned_categories (user_id, category_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $category_id]);
        echo json_encode(['success' => true, 'action' => 'pinned']);
    } else {
        // Delete pin record
        $stmt = $pdo->prepare("
            DELETE FROM user_pinned_categories
            WHERE user_id = ? AND category_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $category_id]);
        echo json_encode(['success' => true, 'action' => 'unpinned']);
    }

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>