<?php
/**
 * Edit Subcategory Form
 * Updates an existing subcategory
 * Updated: 2025-11-05 (Removed hardcoded user fallback - database-only users)
 *
 * FIXED: Removed hardcoded user fallback that was interfering with database authentication
 * - User selection now requires database users table
 * - Complete database-driven user system integration
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

$page_title = 'Edit Subcategory';
$error_message = '';
$subcategory = null;

// Get subcategory ID
$subcategory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($subcategory_id <= 0) {
    header('Location: index.php');
    exit;
}

// Check if visibility columns exist
$visibility_columns_exist = false;
try {
    $test_query = $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
    $visibility_columns_exist = true;
} catch (PDOException $e) {
    // Visibility columns don't exist yet
    $visibility_columns_exist = false;
}

// Check if user is admin for permissions
$is_admin = is_admin();

// Check if visibility columns exist and get users for checkboxes
$users_table_exists = false;
$all_users = [];

if ($visibility_columns_exist) {
    $users_table_exists = users_table_exists($pdo);
    if ($users_table_exists) {
        $all_users = get_all_users($pdo);
    } else {
        // No fallback - require database users table
        $all_users = [];
    }
}

// Fetch subcategory data
try {
    if ($visibility_columns_exist && $is_admin) {
        $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, category_id, user_id, name, created_at FROM subcategories WHERE id = ?");
    }
    $stmt->execute([$subcategory_id]);
    $subcategory = $stmt->fetch();

    if (!$subcategory) {
        $error_message = 'Subcategory not found.';
    }

    // Fetch all categories for dropdown
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
    $categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subcategory) {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

    // Visibility fields (only if columns exist)
    $visibility = isset($_POST['visibility']) ? $_POST['visibility'] : 'public';
    $allowed_users_array = isset($_POST['allowed_users']) ? $_POST['allowed_users'] : [];
    $visibility_note = isset($_POST['visibility_note']) ? trim($_POST['visibility_note']) : '';

    // Validation
    if (empty($name)) {
        $error_message = 'Subcategory name is required.';
    } elseif (strlen($name) > 255) {
        $error_message = 'Subcategory name must be 255 characters or less.';
    } elseif ($category_id <= 0) {
        $error_message = 'Please select a parent category.';
    } elseif ($visibility_columns_exist && $visibility === 'it_only' && !is_super_admin()) {
        $error_message = 'Only Super Admins can set visibility to "Restricted - For IT Only".';
    } elseif ($visibility_columns_exist && $visibility === 'restricted') {
        // For restricted subcategories, always include the creator
        $allowed_users_array = array_unique(array_merge([$_SESSION['user_id']], $allowed_users_array));
    } else {
        // Update subcategory
        try {
            if ($visibility_columns_exist) {
                // Process allowed users
                $allowed_users_json = !empty($allowed_users_array) ? json_encode($allowed_users_array) : null;

                $stmt = $pdo->prepare("UPDATE subcategories SET category_id = ?, name = ?, visibility = ?, allowed_users = ?, visibility_note = ? WHERE id = ?");
                $stmt->execute([$category_id, $name, $visibility, $allowed_users_json, $visibility_note, $subcategory_id]);
            } else {
                // Old database - update without visibility fields
                $stmt = $pdo->prepare("UPDATE subcategories SET category_id = ?, name = ? WHERE id = ?");
                $stmt->execute([$category_id, $name, $subcategory_id]);
            }

            // Redirect to home with success message
            header('Location: index.php?success=subcategory_updated');
            exit;

        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $error_message = "Database error occurred. Please try again.";
        }
    }
}

include $includes_dir . '/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>></span>
        <span class="current">Edit Subcategory</span>
    </div>

    <?php if (!$subcategory): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <a href="index.php" class="btn btn-primary">Back to Home</a>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Subcategory</h2>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit_subcategory.php?id=<?php echo $subcategory_id; ?>">
                <div class="form-group">
                    <label for="category_id" class="form-label">Parent Category *</label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php
                                $selected_id = isset($_POST['category_id']) ? $_POST['category_id'] : $subcategory['category_id'];
                                echo ($selected_id == $cat['id']) ? 'selected' : '';
                                ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Select the category this subcategory belongs to</div>
                </div>

                <div class="form-group">
                    <label for="name" class="form-label">Subcategory Name *</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input"
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($subcategory['name']); ?>"
                        required
                        maxlength="255"
                    >
                    <div class="form-hint">Enter a descriptive name for this subcategory (max 255 characters)</div>
                </div>

                <?php if ($visibility_columns_exist): ?>
                    <?php
                    // Decode existing allowed users for pre-selection
                    $selected_users = [];
                    if (!empty($subcategory['allowed_users'])) {
                        $selected_users = json_decode($subcategory['allowed_users'], true) ?? [];
                    }
                    ?>

                    <div class="form-group">
                        <label for="visibility" class="form-label">Visibility *</label>
                        <select id="visibility" name="visibility" class="form-select" required onchange="toggleAllowedUsers()">
                            <?php
                            // Use all options for Super Admins, regular options for others
                            $visibility_options = is_super_admin() ? $GLOBALS['VISIBILITY_OPTIONS_ALL'] : $GLOBALS['VISIBILITY_OPTIONS'];
                            $visibility_labels = [
                                'public' => '🌐 Public - Everyone can see this subcategory',
                                'restricted' => '👥 Restricted - Only specific users can see this subcategory',
                                'hidden' => '🚫 Hidden - Nobody can see this subcategory (for archiving)',
                                'it_only' => '🔒 Restricted - For IT Only'
                            ];
                            $current_visibility = isset($_POST['visibility']) ? $_POST['visibility'] : $subcategory['visibility'];
                            foreach ($visibility_options as $value => $label):
                            ?>
                                <option value="<?php echo $value; ?>" <?php echo ($current_visibility == $value) ? 'selected' : ''; ?>>
                                    <?php echo $visibility_labels[$value] ?? $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Control who can see this subcategory</div>
                    </div>

                    <div class="form-group" id="allowed_users_group" style="display: <?php echo ((isset($_POST['visibility']) ? $_POST['visibility'] : $subcategory['visibility']) == 'restricted') ? 'block' : 'none'; ?>;">
                        <label class="form-label">Allowed Users</label>
                              <div style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 12px; max-height: 200px; overflow-y: auto;">
                            <?php if (empty($all_users)): ?>
                                <div style="color: #999; text-align: center; padding: 20px;">No users found in database</div>
                            <?php else: ?>
                                <?php
                                $current_user_id = $_SESSION['user_id'];
                                $other_users = array_filter($all_users, function($user) use ($current_user_id) {
                                    return $user['id'] != $current_user_id;
                                });
                                ?>

                                <?php if (empty($other_users)): ?>
                                    <div style="color: #999; text-align: center; padding: 20px;">
                                        <div style="font-weight: 500; margin-bottom: 8px;">👤 You automatically have access</div>
                                        <div style="font-size: 12px;">No other users available to share with</div>
                                    </div>
                                <?php else: ?>
                                    <div style="background: #e8f5e8; border-left: 4px solid #28a745; padding: 8px; margin-bottom: 12px; font-size: 12px; color: #155724;">
                                        👤 You automatically have access as the creator. Select additional users to share with:
                                    </div>
                                    <?php foreach ($other_users as $user): ?>
                                    <?php
                                    $is_selected = in_array($user['id'], $selected_users) ||
                                                   (isset($_POST['allowed_users']) && in_array($user['id'], $_POST['allowed_users']));
                                    ?>
                                    <label style="display: block; margin-bottom: 8px; cursor: pointer; padding: 4px; border-radius: 3px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f0f0f0'" onmouseout="this.style.backgroundColor='transparent'">
                                        <input
                                            type="checkbox"
                                            name="allowed_users[]"
                                            value="<?php echo $user['id']; ?>"
                                            <?php echo $is_selected ? 'checked' : ''; ?>
                                            style="margin-right: 8px;"
                                        >
                                        <span style="display: inline-block; width: 12px; height: 12px; background: <?php echo htmlspecialchars($user['color']); ?>; border-radius: 50%; margin-right: 6px; vertical-align: middle;"></span>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                        <span style="color: #666; font-size: 12px; margin-left: 4px;">(ID: <?php echo $user['id']; ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-hint">Select the users who can access this restricted subcategory. These users will be able to see and use this subcategory.</div>
                    </div>

                    <div class="form-group">
                        <label for="visibility_note" class="form-label">Visibility Note</label>
                        <textarea
                            id="visibility_note"
                            name="visibility_note"
                            class="form-input"
                            placeholder="Optional note about why this subcategory has restricted access..."
                            rows="2"
                        ><?php echo isset($_POST['visibility_note']) ? htmlspecialchars($_POST['visibility_note']) : htmlspecialchars($subcategory['visibility_note'] ?? ''); ?></textarea>
                        <div class="form-hint">Optional note for yourself about why this subcategory has restricted visibility (only visible to admins)</div>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update Subcategory</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleAllowedUsers() {
    var visibility = document.getElementById('visibility').value;
    var allowedUsersGroup = document.getElementById('allowed_users_group');

    if (visibility === 'restricted') {
        allowedUsersGroup.style.display = 'block';
    } else {
        allowedUsersGroup.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleAllowedUsers();
});
</script>

<?php include $includes_dir . '/footer.php'; ?>