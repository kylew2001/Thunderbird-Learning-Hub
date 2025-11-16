<?php
/**
 * Delete Reply Handler
 * Deletes a reply and all associated files
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

// Get reply ID
$reply_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($reply_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get post ID for redirect
try {
    $stmt = $pdo->prepare("SELECT post_id FROM replies WHERE id = ?");
    $stmt->execute([$reply_id]);
    $reply = $stmt->fetch();

    if (!$reply) {
        header('Location: index.php');
        exit;
    }

    $post_id = $reply['post_id'];

    // Get all files associated with this reply
    $stmt = $pdo->prepare("SELECT file_path FROM files WHERE reply_id = ?");
    $stmt->execute([$reply_id]);
    $files = $stmt->fetchAll();

    // Delete files from filesystem
    foreach ($files as $file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
    }

    // Delete reply (CASCADE will handle file records)
    $stmt = $pdo->prepare("DELETE FROM replies WHERE id = ?");
    $stmt->execute([$reply_id]);

    // Redirect to post with success message
    header('Location: post.php?id=' . $post_id . '&success=reply_deleted');
    exit;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>
