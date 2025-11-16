<?php
/**
 * Edit Category Form
 * Updates an existing category
 * Updated: 2025-11-05 (Removed hardcoded user fallback - database-only users)
 *
 * FIXED: Removed hardcoded user fallback that was interfering with database authentication
 * - User selection now requires database users table
 * - Complete database-driven user system integration
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
$config_path = dirname(__DIR__) . '/system/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

if (!function_exists('resolve_includes_base')) {
    function resolve_includes_base(): string {
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        $candidates = [];

        if (defined('APP_INCLUDES')) {
            $candidates[] = rtrim(APP_INCLUDES, '/');
        }

        $candidates[] = __DIR__ . '/includes';
        $candidates[] = __DIR__ . '/../includes';
        $candidates[] = dirname(__DIR__) . '/includes';

        foreach ($candidates as $candidate) {
            if ($candidate && is_dir($candidate)) {
                $base = $candidate;
                return $base;
            }
        }

        return '';
    }
}

$includes_base = resolve_includes_base();
if (empty($includes_base)) {
    http_response_code(500);
    echo 'Required includes directory is missing.';
    exit;
}

require_once $includes_base . '/auth_check.php';
require_once $includes_base . '/db_connect.php';
require_once $includes_base . '/user_helpers.php';
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

$page_title = 'Edit Category';
$error_message = '';
$category = null;

// Get category ID
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id <= 0) {
    header('Location: index.php');
    exit;
}

// Check if user is admin
$is_admin = is_admin();
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

// Fetch category data
try {
    if ($is_admin && $users_table_exists) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, name, icon, user_id FROM categories WHERE id = ?");
    }
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();

    if (!$category) {
        $error_message = 'Category not found.';
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $category) {
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

        // Update category
        try {
            if ($is_admin) {
                $stmt = $pdo->prepare("
                    UPDATE categories SET
                        name = ?,
                        icon = ?,
                        visibility = ?,
                        allowed_users = ?,
                        visibility_note = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $icon, $visibility, $allowed_users_json, $visibility_note, $category_id]);
            } else {
                // Regular users can only edit name and icon
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, icon = ? WHERE id = ?");
                $stmt->execute([$name, $icon, $category_id]);
            }

            // Redirect to home with success message
            header('Location: index.php?success=category_updated');
            exit;

        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $error_message = "Database error occurred. Please try again.";
        }
    }
}

include $includes_base . '/header.php';
include $includes_dir . '/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>></span>
        <span class="current">Edit Category</span>
    </div>

    <?php if (!$category): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <a href="index.php" class="btn btn-primary">Back to Home</a>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Category</h2>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit_category.php?id=<?php echo $category_id; ?>">
                <div class="form-group">
                    <label for="name" class="form-label">Category Name *</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input"
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($category['name']); ?>"
                        required
                        maxlength="255"
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
                        value="<?php echo isset($_POST['icon']) ? htmlspecialchars($_POST['icon']) : htmlspecialchars($category['icon']); ?>"
                        maxlength="50"
                        placeholder="e.g., 🔧 or 📚 or 💻"
                    >
                    <div class="form-hint">Add an emoji icon for visual identification (optional)</div>
                </div>

                <?php if ($is_admin): ?>
                    <?php
                    // Decode existing allowed users for pre-selection
                    $selected_users = [];
                    if (!empty($category['allowed_users'])) {
                        $selected_users = json_decode($category['allowed_users'], true) ?? [];
                    }
                    ?>
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
                                    <option value="<?php echo $value; ?>" <?php echo (($_POST['visibility'] ?? $category['visibility'] ?? 'public') === $value) ? 'selected' : ''; ?>>
                                        <?php echo $visibility_labels[$value] ?? $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">Control who can see this category</div>
                        </div>

                        <div class="form-group" id="allowed_users_group" style="display: <?php echo (($_POST['visibility'] ?? $category['visibility'] ?? 'public') === 'restricted') ? 'block' : 'none'; ?>;">
                            <label class="form-label">Allowed Users</label>
                            <div style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 12px; max-height: 200px; overflow-y: auto;">
                                <?php foreach ($all_users as $user): ?>
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
                            ><?php echo htmlspecialchars($_POST['visibility_note'] ?? $category['visibility_note'] ?? ''); ?></textarea>
                            <div class="form-hint">Internal note for admins about why this category has visibility restrictions</div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update Category</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

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

<?php include $includes_base . '/footer.php'; ?>
<?php include $includes_dir . '/footer.php'; ?>
