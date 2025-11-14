<?php
/**
 * User Helper Functions
 * Functions for user management and role checking
 * Created: 2025-11-03 (Database-driven user system)
 */

/**
 * Check if current user is an admin
 * @return bool True if user is admin
 */
function _normalize_role($r) {
    $r = strtolower(trim((string)$r));
    // unify underscores/spaces
    if ($r === 'super admin') $r = 'super_admin';
    return $r;
}

function is_admin() {
    // Check database role first (case-insensitive)
    if (isset($_SESSION['user_role'])) {
    $r = _normalize_role($_SESSION['user_role']);
    return ($r === 'admin' || $r === 'super_admin');
}

    // Fallback to checking database directly if user_id is set
    if (isset($_SESSION['user_id'])) {
        try {
            require_once 'db_connect.php';
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user && strtolower($user['role']) === 'admin') {
                return true;
            }
        } catch (PDOException $e) {
            // If database check fails, continue to next fallback
        }
    }

    // No hardcoded fallbacks - use database only
    return false;
}

/**
 * Check if current user is super admin
 * @return bool True if user is super admin
 */
function is_super_admin() {
    // Check database role first (case-insensitive)
    if (isset($_SESSION['user_role'])) {
    return _normalize_role($_SESSION['user_role']) === 'super_admin';
}

    // Fallback to checking database directly
    if (isset($_SESSION['user_id'])) {
        try {
            require_once 'db_connect.php';
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user && strtolower($user['role']) === 'super admin') {
                return true;
            }
        } catch (PDOException $e) {
            // If database check fails, return false
        }
    }

    return false;
}

// Avoid re-declaration if training_helpers.php already defines this.
if (!function_exists('is_training_user')) {
    /**
     * Check if current user is a training user
     * @return bool True if user is training
     */
    function is_training_user() {
        if (isset($_SESSION['user_role'])) {
            return _normalize_role($_SESSION['user_role']) === 'training';
        }

        if (isset($_SESSION['user_id'])) {
            try {
                require_once 'db_connect.php';
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND is_active = 1");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                if ($user) {
                    return _normalize_role($user['role']) === 'training';
                }
            } catch (PDOException $e) {
                // fall through
            }
        }
        return false;
    }
}

/**
 * Get current user's role display name
 * @return string Role display name
 */
function get_user_role_display() {
    // Check database role directly (case-insensitive)
    if (isset($_SESSION['user_role'])) {
        $role = strtolower($_SESSION['user_role']);
        if ($role === 'super admin') return 'Super Admin';
        if ($role === 'admin') return 'Admin';
        if ($role === 'training') return 'Training';
        return 'User';
    }

    // Fallback to function checks
    if (is_super_admin()) {
        return 'Super Admin';
    } elseif (is_admin()) {
        return 'Admin';
    } else {
        return 'User';
    }
}

/**
 * Get current user's color
 * @return string Hex color code
 */
function get_user_color() {
    return $_SESSION['user_color'] ?? '#4A90E2';
}

/**
 * Check if users table exists in database
 * @param PDO $pdo Database connection
 * @return bool True if table exists
 */
function users_table_exists($pdo) {
    try {
        $pdo->query("SELECT id FROM users LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get user by ID from database
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array|null User data or null if not found
 */
function get_user_by_id($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all active users from database
 * @param PDO $pdo Database connection
 * @return array Array of users
 */
function get_all_users($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM users WHERE is_active = 1 ORDER BY name ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user display name with role badge
 * @return string HTML for user display
 */
function get_user_display_with_role() {
    $user_name = $_SESSION['user_name'] ?? 'Unknown User';
    $role = get_user_role_display();
    $color = get_user_color();

    $role_colors = [
        'Super Admin' => '#dc3545',
        'Admin' => '#28a745',
        'Training' => '#17a2b8',
        'User' => '#6c757d'
    ];

    $role_color = $role_colors[$role] ?? '#6c757d';

    return '<span style="color: ' . $color . '; font-weight: 500;">' . htmlspecialchars($user_name) . '</span> ' .
           '<span style="background: ' . $role_color . '; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 500; margin-left: 6px;">' . htmlspecialchars($role) . '</span>';
}
?>