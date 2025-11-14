<?php
/**
 * Add Reply Handler
 * Processes reply submission from post.php form
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';

    // Validation
    if ($post_id <= 0) {
        header('Location: index.php');
        exit;
    }

    // Check if files are being uploaded
    $has_files = !empty($_FILES['files']['name'][0]) && $_FILES['files']['error'][0] == UPLOAD_ERR_OK;

    if (empty(strip_tags($content)) && !$has_files) {
        // Redirect back with error
        header('Location: post.php?id=' . $post_id);
        exit;
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Insert reply
        $stmt = $pdo->prepare("INSERT INTO replies (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$post_id, $_SESSION['user_id'], $content]);
        $reply_id = $pdo->lastInsertId();

        // Handle file uploads
        if (!empty($_FILES['files']['name'][0])) {
            $file_count = count($_FILES['files']['name']);

            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['files']['error'][$i] == UPLOAD_ERR_OK) {
                    $original_filename = $_FILES['files']['name'][$i];
                    $tmp_name = $_FILES['files']['tmp_name'][$i];
                    $file_size = $_FILES['files']['size'][$i];
                    $file_type = $_FILES['files']['type'][$i];

                    // Validate file size
                    if ($file_size > MAX_FILE_SIZE) {
                        continue;
                    }

                    // Sanitize filename
                    $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                    $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
                    $stored_filename = time() . '_' . $safe_filename . '.' . $file_ext;

                    // Determine if image or file
                    $is_image = in_array($file_ext, IMAGE_EXTENSIONS);
                    $upload_dir = $is_image ? UPLOAD_PATH_IMAGES : UPLOAD_PATH_FILES;
                    $file_path = $upload_dir . $stored_filename;

                    // Move uploaded file
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        // Insert file record
                        $stmt = $pdo->prepare("
                            INSERT INTO files (reply_id, original_filename, stored_filename, file_path, file_size, file_type)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$reply_id, $original_filename, $stored_filename, $file_path, $file_size, $file_type]);
                    }
                }
            }
        }

        // Commit transaction
        $pdo->commit();

        // Redirect to post with success message
        header('Location: post.php?id=' . $post_id . '&success=reply_added');
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database Error: " . $e->getMessage());
        // Redirect back to post
        header('Location: post.php?id=' . $post_id);
        exit;
    }

} else {
    // Not a POST request, redirect to home
    header('Location: index.php');
    exit;
}
?>
