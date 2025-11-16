<?php
/**
 * Add Category Form
 * Creates a new category
 * Updated: 2025-11-05 (Removed hardcoded user fallback - database-only users)
 *
 * FIXED: Removed hardcoded user fallback that was interfering with database authentication
 * - User selection now requires database users table
 * - Complete database-driven user system integration
 */

// Resolve includes using absolute paths to prevent directory-related failures
require_once __DIR__ . '/../system/config.php';
require_once APP_INCLUDES . '/auth_check.php';
require_once APP_INCLUDES . '/db_connect.php';
require_once APP_INCLUDES . '/user_helpers.php';

$page_title = 'Add Category';
$error_message = '';
$success = false;

// Check if user permissions
$is_admin = is_admin();
$is_super_user = is_super_admin();
$users_table_exists = false;
$all_users = [];

// Get all users for checkbox selection
if ($is_admin) {
    $users_table_exists = users_table_exists($pdo);
    if ($users_table_exists) {
        $all_users = get_all_users($pdo);
    } else {
        // No fallback - require database users table
        $all_users = [];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $icon = isset($_POST['icon']) ? trim($_POST['icon']) : '';
    $visibility = isset($_POST['visibility']) ? $_POST['visibility'] : 'public';
    $allowed_users_array = isset($_POST['allowed_users']) ? $_POST['allowed_users'] : [];
    $visibility_note = isset($_POST['visibility_note']) ? trim($_POST['visibility_note']) : '';

    // Validation
    if (empty($name)) {
        $error_message = 'Category name is required.';
    } elseif (strlen($name) > 255) {
        $error_message = 'Category name must be 255 characters or less.';
    } elseif ($visibility === 'it_only' && !is_super_admin()) {
        $error_message = 'Only Super Admins can set visibility to "Restricted - For IT Only".';
    } elseif ($visibility === 'restricted' && empty($allowed_users_array)) {
        $error_message = 'Please select at least one user for restricted visibility.';
    } else {
        // Prepare visibility data
        $allowed_users_json = null;
        if ($visibility === 'restricted' && !empty($allowed_users_array)) {
            $allowed_users_json = json_encode($allowed_users_array);
        }

        // Insert category
        try {
            if ($is_admin) {
                $stmt = $pdo->prepare("
                    INSERT INTO categories (user_id, name, icon, visibility, allowed_users, visibility_note)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $name, $icon, $visibility, $allowed_users_json, $visibility_note]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, icon) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $name, $icon]);
            }

            // Redirect to home with success message
            header('Location: index.php?success=category_added');
            exit;

        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $error_message = "Database error occurred. Please try again.";
        }
    }
}

include APP_INCLUDES . '/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>></span>
        <span class="current">Add Category</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Add New Category</h2>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="add_category.php">
            <div class="form-group">
                <label for="name" class="form-label">Category Name *</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-input"
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                    required
                    maxlength="255"
                    placeholder="e.g., Hardware Issues"
                >
                <div class="form-hint">Enter a descriptive name for this category (max 255 characters)</div>
            </div>

            <div class="form-group">
                <label for="icon" class="form-label">Icon (Optional)</label>
                <input
                    type="text"
                    id="icon"
                    name="icon"
                    class="form-input"
                    value="<?php echo isset($_POST['icon']) ? htmlspecialchars($_POST['icon']) : ''; ?>"
                    maxlength="50"
                    placeholder="e.g., 🔧 or 📚 or 💻"
                >
                <div class="form-hint">Add an emoji icon for visual identification (optional)</div>
            </div>

            <?php if ($is_admin): ?>
                <div style="background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px;">
                        🔐 Visibility Controls (Admin Only)
                    </h3>

                    <div class="form-group">
                        <label for="visibility" class="form-label">Visibility</label>
                        <select id="visibility" name="visibility" class="form-input" onchange="toggleVisibilityOptions()">
                            <?php
                            // Use all options for Super Admins, regular options for others
                            $visibility_options = is_super_admin() ? $GLOBALS['VISIBILITY_OPTIONS_ALL'] : $GLOBALS['VISIBILITY_OPTIONS'];
                            $visibility_labels = [
                                'public' => '🌐 Public - Everyone can see',
                                'restricted' => '👥 Restricted - Only specific users',
                                'hidden' => '🚫 Hidden - Only admins can see',
                                'it_only' => '🔒 Restricted - For IT Only'
                            ];
                            foreach ($visibility_options as $value => $label):
                            ?>
                                <option value="<?php echo $value; ?>" <?php echo (($_POST['visibility'] ?? 'public') === $value) ? 'selected' : ''; ?>>
                                    <?php echo $visibility_labels[$value] ?? $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Control who can see this category</div>
                    </div>

                    <div class="form-group" id="allowed_users_group" style="display: <?php echo (($_POST['visibility'] ?? 'public') === 'restricted') ? 'block' : 'none'; ?>;">
                        <label class="form-label">Allowed Users</label>
                        <div style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 12px; max-height: 200px; overflow-y: auto;">
                            <?php foreach ($all_users as $user): ?>
                                <label style="display: block; margin-bottom: 8px; cursor: pointer; padding: 4px; border-radius: 3px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f0f0f0'" onmouseout="this.style.backgroundColor='transparent'">
                                    <input
                                        type="checkbox"
                                        name="allowed_users[]"
                                        value="<?php echo $user['id']; ?>"
                                        <?php echo (isset($_POST['allowed_users']) && in_array($user['id'], $_POST['allowed_users'])) ? 'checked' : ''; ?>
                                        style="margin-right: 8px;"
                                    >
                                    <span style="display: inline-block; width: 12px; height: 12px; background: <?php echo htmlspecialchars($user['color']); ?>; border-radius: 50%; margin-right: 6px; vertical-align: middle;"></span>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                    <span style="color: #666; font-size: 12px; margin-left: 4px;">(ID: <?php echo $user['id']; ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-hint">Select the users who can access this restricted category. These users will be able to see and use this category.</div>
                    </div>

                    <div class="form-group">
                        <label for="visibility_note" class="form-label">Visibility Note (Optional)</label>
                        <textarea
                            id="visibility_note"
                            name="visibility_note"
                            class="form-input"
                            rows="2"
                            placeholder="Admin notes about this visibility restriction..."
                        ><?php echo htmlspecialchars($_POST['visibility_note'] ?? ''); ?></textarea>
                        <div class="form-hint">Internal note for admins about why this category has visibility restrictions</div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">Create Category</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php if ($is_super_user): ?>
<script>
function toggleVisibilityOptions() {
    const visibility = document.getElementById('visibility').value;
    const allowedUsersGroup = document.getElementById('allowed_users_group');

    if (visibility === 'restricted') {
        allowedUsersGroup.style.display = 'block';
    } else {
        allowedUsersGroup.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleVisibilityOptions();
});
</script>
<?php endif; ?>

<?php include APP_INCLUDES . '/footer.php'; ?>