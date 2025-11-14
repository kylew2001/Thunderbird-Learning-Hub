<?php
/**
 * Edit Post Form
 * Edits an existing post with rich text content and file uploads
 * Updated: 2025-11-05 (Removed hardcoded user references - database-only users)
 *
 * FIXED: Removed hardcoded user array references that were interfering with database authentication
 * - Shared user display now uses database queries instead of $GLOBALS['USERS']
 * - User selection in sharing form uses database users
 * - Complete database-driven user system integration
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// Load training helpers if available
if (file_exists('includes/training_helpers.php')) {
    require_once 'includes/training_helpers.php';
}

$page_title = 'Edit Post';
$error_message = '';
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$post = null;

if ($post_id <= 0) {
    header('Location: index.php');
    exit;
}

// PERMISSION CHECK: Only admins and super admins can edit posts
if (!is_admin() && !is_super_admin()) {
    $error_message = 'You do not have permission to edit posts. Only administrators can edit posts.';
}

// Fetch post data
try {
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            s.name AS subcategory_name,
            s.id AS subcategory_id,
            c.name AS category_name
        FROM posts p
        JOIN subcategories s ON p.subcategory_id = s.id
        JOIN categories c ON s.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        $error_message = 'Post not found.';
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $post) {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $privacy = isset($_POST['privacy']) ? $_POST['privacy'] : $post['privacy'];
    $shared_with = isset($_POST['shared_with']) ? $_POST['shared_with'] : json_decode($post['shared_with'] ?: '[]', true);
    $files_to_delete = isset($_POST['delete_files']) ? $_POST['delete_files'] : [];

    // Only allow owner to edit privacy
    if ($post['user_id'] != $_SESSION['user_id']) {
        $privacy = $post['privacy'];
        $shared_with = json_decode($post['shared_with'] ?: '[]', true);
    }

    // Validation
    if (empty($title)) {
        $error_message = 'Post title is required.';
    } elseif (strlen($title) > 500) {
        $error_message = 'Post title must be 500 characters or less.';
    } elseif (empty(strip_tags($content))) {
        $error_message = 'Post content is required.';
    } elseif ($privacy === 'shared' && empty($shared_with)) {
        $error_message = 'Please select at least one user to share with.';
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Prepare shared_with JSON
            $shared_with_json = ($privacy === 'shared') ? json_encode($shared_with) : null;

            // Update post
            $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, privacy = ?, shared_with = ?, edited = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$title, $content, $privacy, $shared_with_json, $post_id]);

            // Handle file deletions
            if (!empty($files_to_delete)) {
                foreach ($files_to_delete as $file_id) {
                    // Get file info before deletion
                    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND post_id = ?");
                    $stmt->execute([$file_id, $post_id]);
                    $file = $stmt->fetch();

                    if ($file) {
                        // Delete physical file
                        if (file_exists($file['file_path'])) {
                            unlink($file['file_path']);
                        }

                        // Delete database record
                        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ? AND post_id = ?");
                        $stmt->execute([$file_id, $post_id]);
                    }
                }
            }

          // Handle new file uploads (regular download attachments)
            if (!empty($_FILES['files']['name'][0])) {
                $upload_errors = [];
                $file_count = count($_FILES['files']['name']);

                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['files']['error'][$i] == UPLOAD_ERR_OK) {
                        $original_filename = $_FILES['files']['name'][$i];
                        $tmp_name = $_FILES['files']['tmp_name'][$i];
                        $file_size = $_FILES['files']['size'][$i];
                        $file_type = $_FILES['files']['type'][$i];

                        // Validate file size
                        if ($file_size > MAX_FILE_SIZE) {
                            $upload_errors[] = "File '{$original_filename}' exceeds 20 MB limit.";
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
                            // Insert file record as 'download' type (same as add_post.php)
                            $stmt = $pdo->prepare("
                                INSERT INTO files (post_id, original_filename, stored_filename, file_path, file_size, file_type, file_type_category)
                                VALUES (?, ?, ?, ?, ?, ?, 'download')
                            ");
                            $stmt->execute([$post_id, $original_filename, $stored_filename, $file_path, $file_size, $file_type]);
                        } else {
                            $upload_errors[] = "Failed to upload '{$original_filename}'.";
                        }
                    }
                }

                if (!empty($upload_errors)) {
                    $error_message = implode('<br>', $upload_errors);
                }
            }

            // Handle preview file uploads (PDF/DOCX for inline viewing)
            if (!empty($_FILES['preview_files']['name'][0])) {
                $preview_errors = [];
                $preview_file_count = count($_FILES['preview_files']['name']);

                for ($i = 0; $i < $preview_file_count; $i++) {
                    if ($_FILES['preview_files']['error'][$i] == UPLOAD_ERR_OK) {
                        $original_filename = $_FILES['preview_files']['name'][$i];
                        $tmp_name = $_FILES['preview_files']['tmp_name'][$i];
                        $file_size = $_FILES['preview_files']['size'][$i];
                        $file_type = $_FILES['preview_files']['type'][$i];

                        // Validate file type (only PDF or DOC/DOCX)
                        $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                        if (!in_array($file_ext, ['pdf', 'doc', 'docx'])) {
                            $preview_errors[] = "File '{$original_filename}' is not a supported preview format. Only PDF and Word documents are allowed.";
                            continue;
                        }

                        // Validate file size
                        if ($file_size > MAX_FILE_SIZE) {
                            $preview_errors[] = "File '{$original_filename}' exceeds 20 MB limit.";
                            continue;
                        }

                        // Sanitize filename
                        $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
                        $stored_filename = time() . '_preview_' . $safe_filename . '.' . $file_ext;

                        // Store preview files in a separate directory (same pattern as add_post.php)
                        $upload_dir = UPLOAD_PATH_FILES . 'preview/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $file_path = $upload_dir . $stored_filename;

                        // Move uploaded file
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            // Insert file record as 'preview' type
                            $stmt = $pdo->prepare("
                                INSERT INTO files (post_id, original_filename, stored_filename, file_path, file_size, file_type, file_type_category)
                                VALUES (?, ?, ?, ?, ?, ?, 'preview')
                            ");
                            $stmt->execute([$post_id, $original_filename, $stored_filename, $file_path, $file_size, $file_type]);
                        } else {
                            $preview_errors[] = "Failed to upload preview file '{$original_filename}'.";
                        }
                    }
                }

                if (!empty($preview_errors)) {
                    if (!empty($error_message)) {
                        $error_message .= '<br>';
                    }
                    $error_message .= implode('<br>', $preview_errors);
                }
            }

            // Commit transaction
            $pdo->commit();

            // Redirect to post detail page
            header('Location: post.php?id=' . $post_id);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database Error: " . $e->getMessage());
            $error_message = "Database error occurred. Please try again.";
        }
    }
}

// Fetch existing files
$existing_files = [];
if ($post) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM files WHERE post_id = ? ORDER BY uploaded_at ASC");
        $stmt->execute([$post_id]);
        $existing_files = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div class="container">
    <?php if ($post): ?>
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span>></span>
            <span><?php echo htmlspecialchars($post['category_name']); ?></span>
            <span>></span>
            <a href="subcategory.php?id=<?php echo $post['subcategory_id']; ?>"><?php echo htmlspecialchars($post['subcategory_name']); ?></a>
            <span>></span>
            <a href="post.php?id=<?php echo $post_id; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
            <span>></span>
            <span class="current">Edit Post</span>
        </div>
    <?php endif; ?>

    <?php if (!$post): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <a href="index.php" class="btn btn-primary">Back to Home</a>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Post</h2>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit_post.php?id=<?php echo $post_id; ?>" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title" class="form-label">Post Title *</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        class="form-input"
                        value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : htmlspecialchars($post['title']); ?>"
                        required
                        maxlength="500"
                        placeholder="Enter a descriptive title for your post"
                    >
                    <div class="form-hint">Max 500 characters</div>
                </div>

                <div class="form-group">
                    <label for="content" class="form-label">Content *</label>
                    <textarea id="content" name="content" class="form-textarea"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : htmlspecialchars($post['content']); ?></textarea>
                    <div class="form-hint">Use the toolbar to format your content</div>
                </div>

                <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                    <div class="form-group">
                        <label for="privacy" class="form-label">Privacy Settings *</label>
                        <select id="privacy" name="privacy" class="form-select" onchange="toggleSharedUsers()">
                            <?php
                            $current_privacy = isset($_POST['privacy']) ? $_POST['privacy'] : $post['privacy'];
                            // Use all options for Super Admins, regular options for others
                            $privacy_options = is_super_admin() ? $GLOBALS['PRIVACY_OPTIONS_ALL'] : $GLOBALS['PRIVACY_OPTIONS'];
                            foreach ($privacy_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($current_privacy == $value) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="shared_users_group" style="display: none;">
                        <label class="form-label">Share With Users *</label>
                        <div class="checkbox-group">
                            <?php
                            // Get all users from database
                            try {
                                $users_stmt = $pdo->query("SELECT id, name, color FROM users WHERE is_active = 1 ORDER BY name ASC");
                                $db_users = $users_stmt->fetchAll();
                            } catch (PDOException $e) {
                                $db_users = [];
                            }

                            $current_shared = isset($_POST['shared_with']) ? $_POST['shared_with'] : json_decode($post['shared_with'] ?: '[]', true);
                            foreach ($db_users as $user): ?>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="shared_with[]" value="<?php echo $user['id']; ?>" <?php echo (in_array($user['id'], $current_shared)) ? 'checked' : ''; ?>>
                                        <span style="color: <?php echo $user['color']; ?>"><?php echo htmlspecialchars($user['name']); ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Privacy Settings</label>
                        <div class="form-readonly">
                            <?php
                            $privacy_label = $GLOBALS['PRIVACY_OPTIONS'][$post['privacy']] ?? $post['privacy'];
                            echo htmlspecialchars($privacy_label);
                            if ($post['privacy'] === 'shared' && !empty($post['shared_with'])) {
                                $shared_users = json_decode($post['shared_with'], true);
                                $shared_names = [];
                                foreach ($shared_users as $user_id) {
                                    // Get user from database
                                    try {
                                        $user_stmt = $pdo->prepare("SELECT name, color FROM users WHERE id = ? AND is_active = 1");
                                        $user_stmt->execute([$user_id]);
                                        $user = $user_stmt->fetch();
                                        if ($user) {
                                            $shared_names[] = '<span style="color: ' . $user['color'] . '">' . htmlspecialchars($user['name']) . '</span>';
                                        }
                                    } catch (PDOException $e) {
                                        // Skip if user not found
                                    }
                                }
                                if (!empty($shared_names)) {
                                    echo ' (shared with: ' . implode(', ', $shared_names) . ')';
                                }
                            }
                            ?>
                        </div>
                        <div class="form-hint">Only the post author can change privacy settings</div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($existing_files)): ?>
                    <div class="form-group">
                        <label class="form-label">Current Attachments</label>
                        <div class="file-list">
                            <?php foreach ($existing_files as $file): ?>
                                <div class="file-item">
                                    <label>
                                        <input type="checkbox" name="delete_files[]" value="<?php echo $file['id']; ?>">
                                        <span><?php echo htmlspecialchars($file['original_filename']); ?></span>
                                        <span class="file-size">(<?php echo number_format($file['file_size'] / 1024, 1); ?> KB)</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-hint">Check to delete existing files</div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="files" class="form-label">Attach Files for User Download (Optional)</label>
                    <input
                        type="file"
                        id="files"
                        name="files[]"
                        class="form-file"
                        multiple
                        accept="*/*"
                    >
                    <div class="form-hint">Max 20 MB per file. These files will be available for users to download.</div>
                </div>

                <div class="form-group">
                    <label for="preview_files" class="form-label">📄 Add PDF/DOCX Files for Inline Preview (Optional)</label>
                    <input
                        type="file"
                        id="preview_files"
                        name="preview_files[]"
                        class="form-file"
                        multiple
                        accept=".pdf,.doc,.docx"
                    >
                    <div class="form-hint">PDF and Word documents that will display inline in the post for easy viewing without downloading. Max 20 MB per file.</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update Post</button>
                    <a href="post.php?id=<?php echo $post_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- TinyMCE Rich Text Editor -->
<script src="vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    function toggleSharedUsers() {
        const privacy = document.getElementById('privacy').value;
        const sharedUsersGroup = document.getElementById('shared_users_group');

        if (privacy === 'shared') {
            sharedUsersGroup.style.display = 'block';
        } else {
            sharedUsersGroup.style.display = 'none';
        }
    }

    tinymce.init({
        selector: '#content',
        license_key: 'gpl',
        height: 400,
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

    // Initialize privacy field
    document.addEventListener('DOMContentLoaded', function() {
        toggleSharedUsers();
    });
</script>

<?php include 'includes/footer.php'; ?>