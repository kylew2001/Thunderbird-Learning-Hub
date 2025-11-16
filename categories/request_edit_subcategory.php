<?php
/**
 * Request Edit Subcategory Page
 * Allows normal users to submit edit requests for subcategory names
 * Admins can review these requests in the admin panel
 * Created: 2025-11-03 (Edit Request System)
 */

$includes_dir = dirname(__DIR__) . '/includes';
if (!is_dir($includes_dir)) {
    $fallback_includes = [__DIR__ . '/includes', dirname(__DIR__, 2) . '/includes'];
    foreach ($fallback_includes as $path) {
        if (is_dir($path)) {
            $includes_dir = $path;
            break;
        }
    }
}

if (!is_dir($includes_dir)) {
    http_response_code(500);
    exit('Critical includes path is missing.');
}

require_once $includes_dir . '/auth_check.php';
require_once $includes_dir . '/db_connect.php';
require_once $includes_dir . '/user_helpers.php';

$page_title = 'Request Edit Subcategory';
$error_message = '';
$success_message = '';
$subcategory = null;

// Get subcategory ID
$subcategory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($subcategory_id <= 0) {
    header('Location: index.php');
    exit;
}

// Check if edit requests table exists
$edit_requests_table_exists = false;
try {
    $pdo->query("SELECT id FROM edit_requests LIMIT 1");
    $edit_requests_table_exists = true;
} catch (PDOException $e) {
    $edit_requests_table_exists = false;
}

// Check if user is admin (admins should use regular edit)
if (is_admin()) {
    header('Location: edit_subcategory.php?id=' . $subcategory_id);
    exit;
}

// Fetch subcategory data
try {
    $stmt = $pdo->prepare("SELECT s.*, c.name as category_name FROM subcategories s JOIN categories c ON s.category_id = c.id WHERE s.id = ?");
    $stmt->execute([$subcategory_id]);
    $subcategory = $stmt->fetch();

    if (!$subcategory) {
        $error_message = 'Subcategory not found.';
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subcategory && $edit_requests_table_exists) {
    $requested_name = isset($_POST['requested_name']) ? trim($_POST['requested_name']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    // Validation
    if (empty($requested_name)) {
        $error_message = 'Requested name is required.';
    } elseif (strlen($requested_name) > 255) {
        $error_message = 'Requested name must be 255 characters or less.';
    } elseif (strtolower($requested_name) === strtolower($subcategory['name'])) {
        $error_message = 'Requested name is the same as the current name.';
    } elseif (strlen($reason) > 1000) {
        $error_message = 'Reason must be 1000 characters or less.';
    } else {
        // Check if there's already a pending request for this subcategory
        $check_stmt = $pdo->prepare("
            SELECT id FROM edit_requests
            WHERE item_type = 'subcategory'
            AND item_id = ?
            AND status = 'pending'
            LIMIT 1
        ");
        $check_stmt->execute([$subcategory_id]);
        $existing_request = $check_stmt->fetch();

        if ($existing_request) {
            $error_message = 'There is already a pending edit request for this subcategory. Please wait for the admin to review the existing request.';
        } else {
            try {
                // Insert edit request
                $stmt = $pdo->prepare("
                    INSERT INTO edit_requests (
                        item_type, item_id, current_name, requested_name,
                        user_id, reason
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    'subcategory',
                    $subcategory_id,
                    $subcategory['name'],
                    $requested_name,
                    $_SESSION['user_id'],
                    $reason
                ]);

                $success_message = 'Your edit request has been submitted successfully! An admin will review your request.';

            } catch (PDOException $e) {
                error_log("Database Error: " . $e->getMessage());
                $error_message = "Error submitting request. Please try again.";
            }
        }
    }
}

include $includes_dir . '/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>></span>
        <span><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
        <span>></span>
        <span><?php echo htmlspecialchars($subcategory['name']); ?></span>
        <span>></span>
        <span class="current">Request Edit</span>
    </div>

    <?php if (!$edit_requests_table_exists): ?>
        <div class="card">
            <div class="card-content" style="padding: 20px;">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                    <h3 style="color: #856404; margin: 0 0 8px 0;">⚠️ Edit Request System Not Available</h3>
                    <p style="color: #856404; margin: 0;">The edit request system is not yet available in your database. Please contact an admin to set up the edit requests functionality.</p>
                </div>
                <a href="index.php" class="btn btn-primary">Back to Home</a>
            </div>
        </div>
    <?php elseif (!$subcategory): ?>
        <div class="card">
            <div class="card-content" style="padding: 20px;">
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <a href="index.php" class="btn btn-primary">Back to Home</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📝 Request Edit Subcategory</h2>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="success-message" style="margin: 20px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; color: #155724;">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-message" style="margin: 20px; padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($success_message)): ?>
                <div class="card-content" style="padding: 20px;">
                    <div style="background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 8px 0; color: #2d3748;">Current Information</h3>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 8px;">
                            <strong>Category:</strong>
                            <span><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
                            <strong>Subcategory:</strong>
                            <span><?php echo htmlspecialchars($subcategory['name']); ?></span>
                        </div>
                    </div>

                    <form method="POST" action="request_edit_subcategory.php?id=<?php echo $subcategory_id; ?>">
                        <div class="form-group">
                            <label for="requested_name" class="form-label">Requested Subcategory Name *</label>
                            <input
                                type="text"
                                id="requested_name"
                                name="requested_name"
                                class="form-input"
                                value="<?php echo isset($_POST['requested_name']) ? htmlspecialchars($_POST['requested_name']) : ''; ?>"
                                required
                                maxlength="255"
                                placeholder="Enter the new name for this subcategory"
                            >
                            <div class="form-hint">Enter the new name you would like for this subcategory.</div>
                        </div>

                        <div class="form-group">
                            <label for="reason" class="form-label">Reason for Change *</label>
                            <textarea
                                id="reason"
                                name="reason"
                                class="form-input"
                                rows="4"
                                required
                                maxlength="1000"
                                placeholder="Please explain why this change is needed. This helps admins understand your request."
                            ><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
                            <div class="form-hint">Explain why this subcategory name should be changed. This information will be reviewed by an admin.</div>
                        </div>

                        <div style="background: #e3f2fd; border: 1px solid #bbdefb; border-radius: 6px; padding: 12px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 8px 0; color: #1976d2;">📋 Request Process</h4>
                            <ol style="margin: 0; padding-left: 20px; color: #424242; font-size: 14px;">
                                <li>You submit this edit request with your proposed changes</li>
                                <li>Admins receive a notification about your request</li>
                                <li>Admins review your request and can approve or decline it</li>
                                <li>If approved, the subcategory name will be updated</li>
                                <li>If declined, you'll need to submit a new request</li>
                            </ol>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Submit Edit Request</button>
                            <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="card-content" style="padding: 20px;">
                    <div style="text-align: center; margin: 20px 0;">
                        <a href="index.php" class="btn btn-primary">Back to Home</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include $includes_dir . '/footer.php'; ?>