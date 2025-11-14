<?php
/**
 * User Management Page
 * Admin interface for managing users - add, edit, delete, reset pins
 * Only accessible by admin users
 *
 * Updated: 2025-11-05 (Enhanced Security - Role-Based Access Control)
 * Author: Claude Code Assistant
 * Version: 2.4.1
 *
 * SECURITY ENHANCEMENTS:
 * - Implemented proper super admin vs regular admin role separation
 * - Regular admins cannot edit super admin accounts
 * - Enhanced UI security with database-driven role checking
 * - Fixed session/database synchronization issues
 *
 * ROLE HIERARCHY:
 * - Super Admin: Can manage ALL users including other super admins
 * - Admin: Can manage regular users and other admins, NOT super admins
 * - User: Can only edit own profile
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

// Simple super admin check function - just check session
function is_current_user_super_admin($pdo) {
    $session_role = strtolower($_SESSION['user_role'] ?? '');
    return ($session_role === 'super admin');
}

$page_title = 'User Management';
$success_message = '';
$error_message = '';

// Check if users table exists
$users_table_exists = false;
try {
    $pdo->query("SELECT id FROM users LIMIT 1");
    $users_table_exists = true;
} catch (PDOException $e) {
    $error_message = "Users table doesn't exist. Please import the database schema first.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $users_table_exists) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'add_user':
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
            $color = isset($_POST['color']) ? trim($_POST['color']) : '#4A90E2';
            $role = isset($_POST['role']) ? $_POST['role'] : 'user';

            // Validation
            if (empty($name)) {
                $error_message = 'User name is required.';
            } elseif (strlen($name) > 255) {
                $error_message = 'User name must be 255 characters or less.';
            } elseif (empty($pin) || !preg_match('/^[0-9]{4}$/', $pin)) {
                $error_message = 'PIN must be exactly 4 digits.';
            } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $error_message = 'Color must be a valid hex color code.';
            } elseif (strtolower($role) === 'super admin' && !is_current_user_super_admin($pdo)) {
                                $error_message = 'Only super admins can create or assign super admin roles.';
            } else {
                try {
                    // Hash the PIN using bcrypt (no salt needed - bcrypt handles it internally)
                    $hashed_pin = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]);

                    $stmt = $pdo->prepare("INSERT INTO users (name, pin, color, role, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $hashed_pin, $color, $role, $_SESSION['user_id']]);
                    $success_message = 'User created successfully!';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error_message = 'Error: This PIN could not be created. Please try again.';
                    } else {
                        $error_message = 'Error creating user: ' . $e->getMessage();
                    }
                }
            }
            break;

        case 'edit_user':
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $color = isset($_POST['color']) ? trim($_POST['color']) : '#4A90E2';
            $role = isset($_POST['role']) ? $_POST['role'] : 'user';

            // Get the user being edited to check their current role and is_active status
            $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch();

            // Don't allow editing super admins unless current user is also a super admin
            $current_user_is_super_admin = is_current_user_super_admin($pdo);
            $target_is_super_admin = $target_user && strtolower($target_user['role']) === 'super admin';

            if ($target_is_super_admin && !$current_user_is_super_admin) {
                $error_message = 'Cannot edit super admin users. Only super admins can modify other super admins.';
            } elseif ($user_id <= 0) {
                $error_message = 'Invalid user ID.';
            } elseif (empty($name)) {
                $error_message = 'User name is required.';
            } elseif (strlen($name) > 255) {
                $error_message = 'User name must be 255 characters or less.';
            } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $error_message = 'Color must be a valid hex color code.';
            } elseif (strtolower($role) === 'super admin' && !$current_user_is_super_admin) {
                                $error_message = 'Only super admins can create or assign super admin roles.';
            } else {
                try {
                    // Preserve the current is_active status - use separate toggle_status action to change it
                    $is_active = $target_user['is_active'];
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, color = ?, role = ? WHERE id = ?");
                    $stmt->execute([$name, $color, $role, $user_id]);
                    $success_message = 'User updated successfully!';

                    // If editing current user, update session immediately
                    if ($user_id == $_SESSION['user_id']) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_color'] = $color;
                        $_SESSION['user_role'] = $role;
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error updating user: ' . $e->getMessage();
                }
            }
            break;

        case 'delete_user':
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            // Get the user being deleted to check their role
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch();

            // Don't allow deleting super admins unless current user is also a super admin
            if ($target_user && strtolower($target_user['role']) === 'super admin' && !is_current_user_super_admin($pdo)) {
                                $error_message = 'Cannot delete super admin users. Only super admins can delete other super admins.';
            } elseif ($user_id <= 0) {
                $error_message = 'Invalid user ID.';
            } elseif ($user_id == $_SESSION['user_id']) {
                $error_message = 'You cannot delete your own account.';
            } else {
                try {
                    // Check if user has posts or other data
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $post_count = $stmt->fetchColumn();

                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM replies WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $reply_count = $stmt->fetchColumn();

                    if ($post_count > 0 || $reply_count > 0) {
                        $error_message = 'Cannot delete user - they have associated posts or replies. Deactivate the account instead.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $success_message = 'User deleted successfully!';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error deleting user: ' . $e->getMessage();
                }
            }
            break;

        case 'reset_pin':
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $new_pin = isset($_POST['new_pin']) ? trim($_POST['new_pin']) : '';

            // Get the user whose PIN is being reset to check their role
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch();

            // Don't allow resetting PIN for super admins unless current user is also a super admin
            if ($target_user && strtolower($target_user['role']) === 'super admin' && !is_current_user_super_admin($pdo)) {
                                $error_message = 'Cannot reset super admin PINs. Only super admins can reset other super admin PINs.';
            } elseif ($user_id <= 0) {
                $error_message = 'Invalid user ID.';
            } elseif (empty($new_pin) || !preg_match('/^[0-9]{4}$/', $new_pin)) {
                $error_message = 'New PIN must be exactly 4 digits.';
            } else {
                try {
                    // Hash the PIN using bcrypt (no salt needed - bcrypt handles it internally)
                    $hashed_pin = password_hash($new_pin, PASSWORD_BCRYPT, ['cost' => 10]);

                    // Also reset failed_attempts when PIN is reset
                    $stmt = $pdo->prepare("UPDATE users SET pin = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?");
                    $stmt->execute([$hashed_pin, $user_id]);

                    $success_message = 'PIN reset successfully! Login attempts have been reset.';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error_message = 'Error: This PIN could not be reset. Please try a different PIN.';
                    } else {
                        $error_message = 'Error resetting PIN. Please try again or contact support.';
                    }
                }
            }
            break;

        case 'toggle_status':
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            // Get the user whose status is being changed to check their role
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch();

            // Don't allow deactivating super admins unless current user is also a super admin
            if ($target_user && strtolower($target_user['role']) === 'super admin' && !is_current_user_super_admin($pdo)) {
                                $error_message = 'Cannot change super admin status. Only super admins can modify other super admin accounts.';
            } elseif ($user_id <= 0) {
                $error_message = 'Invalid user ID.';
            } elseif ($user_id == $_SESSION['user_id']) {
                $error_message = 'You cannot deactivate your own account.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success_message = 'User status updated successfully!';
                } catch (PDOException $e) {
                    $error_message = 'Error updating user status: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Fetch all users
$users = [];
if ($users_table_exists) {
    try {
        $stmt = $pdo->query("
            SELECT u.*,
                   (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count,
                   (SELECT COUNT(*) FROM replies WHERE user_id = u.id) as reply_count
            FROM users u
            ORDER BY u.id ASC
        ");
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error_message = 'Error fetching users: ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>></span>
        <span class="current">User Management</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">👥 User Management</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-primary" onclick="showAddUserModal()">➕ Add New User</button>
                <a href="index.php" class="btn btn-secondary">← Back to Home</a>
            </div>
        </div>

        <?php if (!is_current_user_super_admin($pdo)): ?>
        <div style="margin: 20px; padding: 12px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; color: #856404;">
            <strong>🔒 Admin Access Notice:</strong> As a regular admin, you can manage regular users and admins, but you cannot edit or modify super admin accounts. Only super admins have full control over all user types.
        </div>
        <?php endif; ?>

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

        <?php if (!$users_table_exists): ?>
            <div class="card-content" style="padding: 20px;">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                    <h3 style="color: #856404; margin: 0 0 8px 0;">⚠️ Database Setup Required</h3>
                    <p style="color: #856404; margin: 0;">The users table doesn't exist in your database. Please import the following SQL file first:</p>
                    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; margin: 8px 0; font-family: monospace; font-size: 12px;">
                        database/create_users_table.sql
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card-content" style="padding: 0;">
                <?php if (empty($users)): ?>
                    <div style="padding: 40px; text-align: center; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
                        <h3>No Users Found</h3>
                        <p>There are no users in the database. Click "Add New User" to create the first user.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">ID</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">PIN</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Color</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Role</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Status</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Posts</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Last Login</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr style="border-bottom: 1px solid #dee2e6; <?php echo $user['id'] == 1 ? 'background: #fff3cd;' : ''; ?>">
                                        <td style="padding: 12px;">
                                            <?php echo $user['id']; ?>
                                            <?php if ($user['role'] === 'super admin'): ?>
                                                <span style="background: #dc3545; color: white; font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: 4px;">SUPER ADMIN</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px; font-weight: 500;"><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td style="padding: 12px;">
                                            <span style="color: #a0aec0; font-style: italic;">••••</span>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="display: inline-block; width: 20px; height: 20px; background: <?php echo htmlspecialchars($user['color']); ?>; border: 1px solid #dee2e6; border-radius: 3px; vertical-align: middle;" title="<?php echo htmlspecialchars($user['color']); ?>"></span>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php
                                            $role_color = '#6c757d'; // default for user
                                            if ($user['role'] === 'admin') {
                                                $role_color = '#28a745';
                                            } elseif ($user['role'] === 'training') {
                                                $role_color = '#17a2b8';
                                            } elseif ($user['role'] === 'super admin') {
                                                $role_color = '#dc3545';
                                            }
                                            ?>
                                            <span style="background: <?php echo $role_color; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: <?php echo $user['is_active'] ? '#28a745' : '#dc3545'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px;"><?php echo intval($user['post_count'] + $user['reply_count']); ?></td>
                                        <td style="padding: 12px; color: #6c757d; font-size: 12px;">
                                            <?php
                                            if ($user['last_login']) {
                                                echo date('M j, Y H:i', strtotime($user['last_login']));
                                            } else {
                                                echo 'Never';
                                            }
                                            ?>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <?php
                                                // Check current user's role directly from database (more reliable than session)
                                                $target_is_super_admin = (strtolower($user['role']) === 'super admin');
                                                $current_user_is_super_admin = false;

                                                try {
                                                    $current_check = $pdo->prepare("SELECT role FROM users WHERE id = ? AND is_active = 1");
                                                    $current_check->execute([$_SESSION['user_id']]);
                                                    $current_user_data = $current_check->fetch();
                                                    $db_role = strtolower($current_user_data['role'] ?? '');
                                                    $current_user_is_super_admin = ($db_role === 'super admin');
                                                } catch (Exception $e) {
                                                    $current_user_is_super_admin = false;
                                                }

                                                if ($target_is_super_admin && !$current_user_is_super_admin): ?>
                                                    <span style="color: #dc3545; font-size: 11px; font-weight: 500;">🔒 Super Admin</span>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm" style="background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['pin']); ?>', '<?php echo htmlspecialchars($user['color']); ?>', '<?php echo $user['role']; ?>', <?php echo $user['is_active']; ?>)">Edit</button>

                                                    <button type="button" class="btn btn-sm" style="background: #ffc107; color: black; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showResetPinModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">Reset PIN</button>

                                                    <button type="button" class="btn btn-sm" style="background: <?php echo $user['is_active'] ? '#dc3545' : '#28a745'; ?>; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', <?php echo $user['is_active']; ?>)">
                                                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </button>

                                                    <?php if ($user['id'] != $_SESSION['user_id'] && ($user['post_count'] + $user['reply_count']) == 0): ?>
                                                        <button type="button" class="btn btn-sm" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">Delete</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="padding: 16px; background: #f8f9fa; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">
                        <strong>🔒 Security Note:</strong> User ID 1 (<?php echo htmlspecialchars($users[0]['name'] ?? 'Kyle Walker'); ?>) is the super admin and cannot be edited or deleted from this interface. This user can only be modified directly in the database.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 16px 0;">➕ Add New User</h3>
        <form method="POST" action="manage_users.php">
            <input type="hidden" name="action" value="add_user">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Name *</label>
                <input type="text" name="name" required maxlength="255" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">PIN (4 digits) *</label>
                <input type="text" name="pin" required maxlength="4" pattern="[0-9]{4}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Color</label>
                <input type="color" name="color" value="#4A90E2" style="width: 100%; height: 40px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Role</label>
                <select name="role" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="user">User</option>
                    <option value="training">Training</option>
                    <option value="admin">Admin</option>
                    <?php if (is_current_user_super_admin($pdo)): ?>
                    <option value="super admin">Super Admin</option>
                    <?php endif; ?>
                </select>
                <div style="margin-top: 4px; font-size: 12px; color: #6c757d; font-style: italic;">
                    Training: Limited access, must complete assigned training materials<br>
                    User: Full access to all content, no post creation<br>
                    Admin: Can manage content and users<br>
                    <?php if (!is_current_user_super_admin($pdo)): ?>
                    Note: Only super admins can create super admin accounts
                    <?php endif; ?>
                </div>
                <?php if (!is_current_user_super_admin($pdo)): ?>
                <div style="margin-top: 4px; font-size: 12px; color: #6c757d; font-style: italic;">
                    Note: Only super admins can create super admin accounts
                </div>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideAddUserModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 16px 0;">✏️ Edit User</h3>
        <form method="POST" action="manage_users.php">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" id="edit_user_id" name="user_id">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Name *</label>
                <input type="text" id="edit_name" name="name" required maxlength="255" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Color</label>
                <input type="color" id="edit_color" name="color" style="width: 100%; height: 40px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Role</label>
                <select id="edit_role" name="role" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="user">User</option>
                    <option value="training">Training</option>
                    <option value="admin">Admin</option>
                    <?php if (is_current_user_super_admin($pdo)): ?>
                    <option value="super admin">Super Admin</option>
                    <?php endif; ?>
                </select>
                <?php if (!is_current_user_super_admin($pdo)): ?>
                <div style="margin-top: 4px; font-size: 12px; color: #6c757d; font-style: italic;">
                    Note: Only super admins can assign super admin roles
                </div>
                <?php endif; ?>
            </div>

            <div style="margin-bottom: 16px; padding: 12px; background: #f8f9fa; border-radius: 4px; font-size: 12px; color: #6c757d;">
                <strong>Note:</strong> To change a user's active status, use the "Deactivate" or "Activate" button in the table.
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideEditUserModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset PIN Modal -->
<div id="resetPinModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 400px;">
        <h3 style="margin: 0 0 16px 0;">🔄 Reset PIN</h3>
        <form method="POST" action="manage_users.php">
            <input type="hidden" name="action" value="reset_pin">
            <input type="hidden" id="reset_user_id" name="user_id">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">User</label>
                <div style="padding: 8px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; font-weight: 500;" id="reset_user_name"></div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">New PIN (4 digits) *</label>
                <input type="text" name="new_pin" required maxlength="4" pattern="[0-9]{4}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideResetPinModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #ffc107; color: black; border: none; border-radius: 4px; cursor: pointer;">Reset PIN</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddUserModal() {
    document.getElementById('addUserModal').style.display = 'block';
}

function hideAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
}

function showEditUserModal(userId, name, pin, color, role, isActive) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_color').value = color;
    document.getElementById('edit_role').value = role;
    document.getElementById('editUserModal').style.display = 'block';
}

function hideEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function showResetPinModal(userId, name) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = name;
    document.getElementById('resetPinModal').style.display = 'block';
}

function hideResetPinModal() {
    document.getElementById('resetPinModal').style.display = 'none';
}

function toggleUserStatus(userId, name, currentStatus) {
    if (confirm(`Are you sure you want to ${currentStatus ? 'deactivate' : 'activate'} the user "${name}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_users.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle_status';

        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;

        form.appendChild(actionInput);
        form.appendChild(userIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteUser(userId, name) {
    if (confirm(`Are you sure you want to delete the user "${name}"? This action cannot be undone.`)) {
        if (confirm(`WARNING: This will permanently delete "${name}" and all their data. Are you absolutely sure?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_users.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_user';

            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;

            form.appendChild(actionInput);
            form.appendChild(userIdInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.id === 'addUserModal') {
        hideAddUserModal();
    } else if (event.target.id === 'editUserModal') {
        hideEditUserModal();
    } else if (event.target.id === 'resetPinModal') {
        hideResetPinModal();
    }
}
</script>

<?php include 'includes/footer.php'; ?>