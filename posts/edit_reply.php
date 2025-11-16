<?php
/**
 * Edit Reply Form
 * Updates existing reply with file management
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

$page_title = 'Edit Update';
$error_message = '';
$reply = null;
$files = [];

// Get reply ID
$reply_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($reply_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch reply data with post info
try {
    $stmt = $pdo->prepare("SELECT * FROM replies WHERE id = ?");
    $stmt->execute([$reply_id]);
    $reply = $stmt->fetch();

    if (!$reply) {
        $error_message = 'Update not found.';
    } else {
        // Fetch existing files
        $stmt = $pdo->prepare("SELECT * FROM files WHERE reply_id = ? ORDER BY uploaded_at ASC");
        $stmt->execute([$reply_id]);
        $files = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reply) {
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $files_to_delete = isset($_POST['delete_files']) ? $_POST['delete_files'] : [];

    // Check if new files are being uploaded
    $has_new_files = !empty($_FILES['files']['name'][0]) && $_FILES['files']['error'][0] == UPLOAD_ERR_OK;

    // Check if reply will have files after deletion (existing files - deleted files + new files)
    $existing_file_count = count($files);
    $deleted_count = count($files_to_delete);
    $will_have_files = ($existing_file_count - $deleted_count + ($has_new_files ? 1 : 0)) > 0;

    // Validation
    if (empty(strip_tags($content)) && !$will_have_files) {
        $error_message = 'Update content or at least one file attachment is required.';
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Update reply (set edited flag)
            $stmt = $pdo->prepare("UPDATE replies SET content = ?, edited = 1 WHERE id = ?");
            $stmt->execute([$content, $reply_id]);

            // Delete selected files
            if (!empty($files_to_delete)) {
                foreach ($files_to_delete as $file_id) {
                    // Get file info
                    $stmt = $pdo->prepare("SELECT file_path FROM files WHERE id = ?");
                    $stmt->execute([$file_id]);
                    $file_info = $stmt->fetch();

                    if ($file_info) {
                        // Delete file from filesystem
                        if (file_exists($file_info['file_path'])) {
                            unlink($file_info['file_path']);
                        }

                        // Delete file record
                        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
                        $stmt->execute([$file_id]);
                    }
                }
            }

            // Handle new file uploads
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
            header('Location: post.php?id=' . $reply['post_id'] . '&success=reply_updated');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database Error: " . $e->getMessage());
            $error_message = "Database error occurred. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <?php if (!$reply): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <a href="index.php" class="btn btn-primary">Back to Home</a>
    <?php else: ?>
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span>></span>
            <a href="post.php?id=<?php echo $reply['post_id']; ?>">Back to Post</a>
            <span>></span>
            <span class="current">Edit Update</span>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Update</h2>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit_reply.php?id=<?php echo $reply_id; ?>" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="content" class="form-label">Content *</label>
                    <textarea id="content" name="content" class="form-textarea"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : htmlspecialchars($reply['content']); ?></textarea>
                </div>

                <!-- Existing Files -->
                <?php if (!empty($files)): ?>
                    <div class="form-group">
                        <label class="form-label">Existing Attachments</label>
                        <div class="file-manager">
                            <div class="existing-files">
                                <?php foreach ($files as $file): ?>
                                    <div class="file-item">
                                        <input
                                            type="checkbox"
                                            id="file_<?php echo $file['id']; ?>"
                                            name="delete_files[]"
                                            value="<?php echo $file['id']; ?>"
                                            class="file-item-checkbox"
                                        >
                                        <label for="file_<?php echo $file['id']; ?>" class="file-item-name">
                                            <?php echo htmlspecialchars($file['original_filename']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-hint">Check files you want to delete</div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- New File Upload -->
                <div class="form-group">
                    <label for="files" class="form-label">Add New Files (Optional)</label>
                    <input type="file" id="files" name="files[]" class="form-file" multiple>
                    <div class="form-hint">Max 20 MB per file</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update</button>
                    <a href="post.php?id=<?php echo $reply['post_id']; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- TinyMCE -->
<script src="vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#content',
        license_key: 'gpl',
        height: 250,
        menubar: false,
        plugins: 'lists link code table textcolor colorpicker image',
        toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | link | table | image | code | removeformat',
        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
        branding: false,
        promotion: false,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false,
        images_upload_url: 'upload_image.php',
        automatic_uploads: true
    });
</script>

<?php include 'includes/footer.php'; ?>
