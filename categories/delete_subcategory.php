<?php
/**
 * Delete Subcategory Handler
 * Deletes a subcategory and all associated posts, replies, and files
 * CASCADE delete will handle related records automatically
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

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
