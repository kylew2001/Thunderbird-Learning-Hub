<?php
/**
 * Delete Post Handler
 * Deletes a post and all associated replies and files
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// PERMISSION CHECK: Only admins and super admins can delete posts
if (!is_admin() && !is_super_admin()) {
    header('Location: index.php?error=permission_denied');
    exit;
}

// Get post ID
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($post_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch post info to get subcategory_id for redirect
try {
    $stmt = $pdo->prepare("
        SELECT p.*, s.id AS subcategory_id
        FROM posts p
        JOIN subcategories s ON p.subcategory_id = s.id
        WHERE p.id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        header('Location: index.php');
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Fetch and delete all files associated with the post and its replies
    $stmt = $pdo->prepare("
        SELECT file_path FROM files
        WHERE post_id = ? OR reply_id IN (
            SELECT id FROM replies WHERE post_id = ?
        )
    ");
    $stmt->execute([$post_id, $post_id]);
    $files = $stmt->fetchAll();

    // Delete physical files
    foreach ($files as $file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
    }

    // Delete file records from database
    $stmt = $pdo->prepare("
        DELETE FROM files
        WHERE post_id = ? OR reply_id IN (
            SELECT id FROM replies WHERE post_id = ?
        )
    ");
    $stmt->execute([$post_id, $post_id]);

    // Delete all replies
    $stmt = $pdo->prepare("DELETE FROM replies WHERE post_id = ?");
    $stmt->execute([$post_id]);

    // Delete the post
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);

    // Commit transaction
    $pdo->commit();

    // Redirect to subcategory with success message
    header('Location: subcategory.php?id=' . $post['subcategory_id'] . '&success=post_deleted');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database Error: " . $e->getMessage());

    // Redirect back with error message
    header('Location: post.php?id=' . $post_id . '&error=delete_failed');
    exit;
}
?>