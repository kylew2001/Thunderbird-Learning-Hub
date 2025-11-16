<?php
/**
 * Delete Category Handler
 * Deletes a category and all associated subcategories, posts, replies, and files
 * CASCADE delete will handle related records automatically
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Get category ID
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id <= 0) {
    header('Location: index.php');
    exit;
}

// Delete category (CASCADE will handle subcategories, posts, replies, files)
try {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);

    // Redirect to home with success message
    header('Location: index.php?success=category_deleted');
    exit;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    // Redirect with error
    header('Location: index.php');
    exit;
}
?>
