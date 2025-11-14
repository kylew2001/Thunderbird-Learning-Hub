<?php
/**
 * Add Subcategory Form
 * Creates a new subcategory under a category
 * Updated: 2025-11-05 (Removed hardcoded user fallback - database-only users)
 *
 * FIXED: Removed hardcoded user fallback that was interfering with database authentication
 * - User selection now requires database users table
 * - Complete database-driven user system integration
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

$page_title = 'Add Subcategory';
$error_message = '';
$preselected_category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Fetch all categories for dropdown (with visibility if available)
try {
    $test_query = $pdo->query("SELECT visibility FROM categories LIMIT 1");
    $categories_has_visibility = true;
} catch (PDOException $e) {
    $categories_has_visibility = false;
}

try {
    if ($categories_has_visibility) {
        $stmt = $pdo->query("SELECT id, name, visibility FROM categories ORDER BY name ASC");
    } else {
        $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    }
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
    $categories = [];
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        // Verify category exists
        try {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $cat_exists = $stmt->fetch();

            if (!$cat_exists) {
                $error_message = 'Invalid category selected.';
            } else {
                // Insert subcategory
                if ($visibility_columns_exist) {
                    // Process allowed users
                    $allowed_users_json = !empty($allowed_users_array) ? json_encode($allowed_users_array) : null;

                    $stmt = $pdo->prepare("INSERT INTO subcategories (category_id, user_id, name, visibility, allowed_users, visibility_note) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$category_id, $_SESSION['user_id'], $name, $visibility, $allowed_users_json, $visibility_note]);
                } else {
                    // Old database - insert without visibility fields
                    $stmt = $pdo->prepare("INSERT INTO subcategories (category_id, user_id, name) VALUES (?, ?, ?)");
                    $stmt->execute([$category_id, $_SESSION['user_id'], $name]);
                }

                // Redirect to home with success message
                header('Location: index.php?success=subcategory_added');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $error_message = "Database error occurred. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>></span>
        <span class="current">Add Subcategory</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Add New Subcategory</h2>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($categories)): ?>
            <div class="error-message">
                No categories available. Please create a category first.
            </div>
            <a href="add_category.php" class="btn btn-success">Add Category</a>
        <?php else: ?>
            <form method="POST" action="add_subcategory.php">
                <!-- Hidden field to track if visibility was manually changed -->
                <input type="hidden" id="visibility_manually_set" value="false">

                <div class="form-group">
                    <label for="category_id" class="form-label">Parent Category *</label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo ($preselected_category_id == $cat['id'] || (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id'])) ? 'selected' : ''; ?>>
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
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                        required
                        maxlength="255"
                        placeholder="e.g., Printers"
                    >
                    <div class="form-hint">Enter a descriptive name for this subcategory (max 255 characters)</div>
                </div>

                <?php if ($visibility_columns_exist): ?>
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

                            // Determine current visibility to select
                            $current_visibility = isset($_POST['visibility']) ? $_POST['visibility'] : null;

                            foreach ($visibility_options as $value => $label):
                            ?>
                                <option value="<?php echo $value; ?>" <?php echo ($current_visibility == $value) ? 'selected' : ''; ?>>
                                    <?php echo $visibility_labels[$value] ?? $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Control who can see this subcategory (defaults to parent category's visibility)</div>
                    </div>

                    <div class="form-group" id="allowed_users_group" style="display: <?php echo (isset($_POST['visibility']) && $_POST['visibility'] == 'restricted') ? 'block' : 'none'; ?>;">
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
                        ><?php echo isset($_POST['visibility_note']) ? htmlspecialchars($_POST['visibility_note']) : ''; ?></textarea>
                        <div class="form-hint">Optional note for yourself about why this subcategory has restricted visibility (only visible to admins)</div>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Create Subcategory</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Category visibility mapping
const categoryVisibility = {
    <?php
    if ($categories_has_visibility) {
        $first = true;
        foreach ($categories as $cat):
            if (!$first) echo ',';
            $vis = $cat['visibility'] ?? 'public';
            echo $cat['id'] . ': "' . $vis . '"';
            $first = false;
        endforeach;
    }
    ?>
};

function updateVisibilityFromCategory() {
    const categorySelect = document.getElementById('category_id');
    const visibilitySelect = document.getElementById('visibility');
    const categoryId = parseInt(categorySelect.value);
    const manuallySet = document.getElementById('visibility_manually_set').value === 'true';

    // If a category is selected and we have visibility data, and user hasn't manually changed it, update the dropdown
    if (categoryId && categoryVisibility[categoryId] && !manuallySet) {
        const parentVisibility = categoryVisibility[categoryId];
        visibilitySelect.value = parentVisibility;
        visibilitySelect.dispatchEvent(new Event('change'));
    }
}

function toggleAllowedUsers() {
    var visibility = document.getElementById('visibility').value;
    var allowedUsersGroup = document.getElementById('allowed_users_group');

    if (visibility === 'restricted') {
        allowedUsersGroup.style.display = 'block';
    } else {
        allowedUsersGroup.style.display = 'none';
    }
}

// Mark visibility as manually set when user changes it
function markVisibilityAsManuallySet() {
    document.getElementById('visibility_manually_set').value = 'true';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('category_id');
    const visibilitySelect = document.getElementById('visibility');

    // Add event listener to category select
    categorySelect.addEventListener('change', function() {
        document.getElementById('visibility_manually_set').value = 'false';
        updateVisibilityFromCategory();
    });

    // Add event listener to visibility select to track manual changes
    visibilitySelect.addEventListener('change', function() {
        markVisibilityAsManuallySet();
        toggleAllowedUsers();
    });

    // Initial setup
    updateVisibilityFromCategory();
    toggleAllowedUsers();
});
</script>

<?php include 'includes/footer.php'; ?>