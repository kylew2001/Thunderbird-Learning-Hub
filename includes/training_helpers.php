<?php
/**
 * Training System Helper Functions
 * Created: 2025-11-05
 * Updated: 2025-11-06 22:02:00 UTC - Enhanced assignment debugging and content count
 * Author: Claude Code Assistant
 *
 * This file contains all the core functions for the training system
 * including role checking, progress tracking, and course management.
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../system/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_INCLUDES . '/user_helpers.php';

if (!isset($pdo) || !$pdo) {
    require_once APP_INCLUDES . '/db_connect.php';
}


// ============================================================
// UNIFIED DEBUG LOGGING
// ============================================================

/**
 * Unified debug logging function that writes to view_debug_log.php
 * @param string $message Debug message to log
 * @param string $level Debug level (INFO, ERROR, DEBUG)
 */
function log_debug($message, $level = 'DEBUG') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$level] $message\n";

    // Also write to standard error log for server admins
    error_log("TRAINING DEBUG: $message");

    // Write to unified debug log file
    file_put_contents(__DIR__ . '/assignment_debug.log', $timestamp . " - " . $log_message, FILE_APPEND | LOCK_EX);
}

// ============================================================
// AUTOMATIC ROLE MANAGEMENT
// ============================================================

/**
 * Automatically manage user roles based on training assignments
 * This function should be called on every page load for authenticated users
 * @param PDO $pdo Database connection
 * @param int $user_id User ID (optional, defaults to current user)
 * @return array Status of role changes
 */
// --- BEGIN REPLACEMENT (auto_manage_user_roles with admin/super-admin exception) ---
function auto_manage_user_roles($pdo, $user_id = null) {
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    if (!$user_id) {
        return ['status' => 'no_user', 'changes' => []];
    }

    try {
        $user_stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['status' => 'user_not_found', 'changes' => []];
        }

        // Never auto-change Admins or Super Admins (use helpers to avoid string drift)
$role_lc = strtolower(trim((string)$user['role']));
if ($role_lc === 'admin' || $role_lc === 'super_admin' || $role_lc === 'super admin') {
    log_debug("Auto-manage skipped for privileged user {$user['id']} (role={$user['role']})", 'INFO');
    return ['status' => 'skipped_privileged', 'changes' => []];
}


// Robust healer: mark a course 'completed' iff there are NO remaining assigned POSTs
// without a matching 'completed' training_progress row (tolerates blank/NULL content_type).
$heal = $pdo->prepare("
    UPDATE user_training_assignments AS uta
    JOIN training_courses AS tc
      ON tc.id = uta.course_id
     AND tc.is_active = 1
   SET uta.status = 'completed',
       uta.completion_date = NOW()
 WHERE uta.user_id = ?
   AND uta.status <> 'completed'
   AND NOT EXISTS (
        SELECT 1
          FROM training_course_content AS tcc
         WHERE tcc.course_id = uta.course_id
           AND tcc.content_type = 'post'
           AND NOT EXISTS (
                SELECT 1
                  FROM training_progress AS tp
                 WHERE tp.user_id = uta.user_id
                   AND tp.content_id = tcc.content_id
                   AND (
                         tp.content_type = tcc.content_type
                      OR tp.content_type = ''
                      OR tp.content_type IS NULL
                   )
                   AND tp.status = 'completed'
           )
   )
");
$heal->execute([$user_id]);

// Optional: log what happened
try {
    $dbg = $pdo->prepare("
        SELECT course_id, status, completion_date
          FROM user_training_assignments
         WHERE user_id = ?
    ");
    $dbg->execute([$user_id]);
    $rows = $dbg->fetchAll(PDO::FETCH_ASSOC);
    log_debug('Healer post-check for user '.$user_id.': '.json_encode($rows), 'INFO');
} catch (Throwable $e) {
    // best-effort logging only
}

// How many active assignments?
$assignment_stmt = $pdo->prepare("
    SELECT COUNT(*) AS active_assignments
      FROM user_training_assignments uta
      JOIN training_courses tc ON uta.course_id = tc.id
     WHERE uta.user_id = ?
       AND tc.is_active = 1
       AND uta.status != 'completed'
");
$assignment_stmt->execute([$user_id]);
$active_assignments = (int) $assignment_stmt->fetchColumn();
$all_done = has_completed_all_training($pdo, $user_id);

        
        $new_role = null;
$changes  = [];

// If everything is completed, ensure they become a normal user
if ($all_done && strtolower($user['role']) === 'training') {
    $new_role = 'user';
    $changes[] = "User {$user['name']} → user (all training complete)";
} elseif ($active_assignments > 0 && strtolower($user['role']) === 'user') {
    // New training assigned → move back to training
    $new_role = 'training';
    $changes[] = "User {$user['name']} → training ({$active_assignments} active assignment(s))";
} elseif ($active_assignments === 0 && strtolower($user['role']) === 'training') {
    // Safety valve: no active assignments → user
    $new_role = 'user';
    $changes[] = "User {$user['name']} → user (no active assignments)";
}


        if ($new_role) {
            $update = $pdo->prepare("
    UPDATE users
       SET role = ?, previous_role = ?, updated_at = NOW()
     WHERE id = ?
");
$update->execute([$new_role, $user['role'], $user_id]);

            if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                $_SESSION['user_role'] = $new_role;
            }
        }

        return [
            'status' => 'success',
            'changes' => $changes,
            'user_id' => $user_id,
            'previous_role' => $user['role'],
            'new_role' => $new_role,
            'active_assignments' => $active_assignments,
        ];
    } catch (PDOException $e) {
        log_debug("Error in auto_manage_user_roles: " . $e->getMessage(), 'ERROR');
        return ['status' => 'error', 'message' => $e->getMessage(), 'changes' => []];
    }
}
// --- END REPLACEMENT ---


// Call automatic role management for authenticated users
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] && isset($_SESSION['user_id']) && isset($pdo) && $pdo) {
    $role_status = auto_manage_user_roles($pdo, $_SESSION['user_id']);
    if (!empty($role_status['changes'])) {
        log_debug("Automatic role management: " . implode('; ', $role_status['changes']), 'INFO');
    }
}

// Handle AJAX requests for live progress updates
if (isset($_GET['action']) && $_GET['action'] === 'get_training_progress') {
    header('Content-Type: application/json');

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];

    if (function_exists('log_debug')) {
        log_debug("AJAX progress request - User ID: $user_id");
    }

    if (should_show_training_progress($pdo, $user_id)) {
        $progress = get_overall_training_progress($pdo, $user_id);

        if (function_exists('log_debug')) {
            log_debug("Progress data: " . json_encode($progress));
        }

        echo json_encode([
            'success' => true,
            'progress' => $progress,
            'percentage' => $progress['percentage'],
            'completed_items' => $progress['completed_items'],
            'total_items' => $progress['total_items']
        ]);
    } else {
        if (function_exists('log_debug')) {
            log_debug("should_show_training_progress returned false for user $user_id");
        }
        echo json_encode([
            'success' => false,
            'message' => 'No training progress available'
        ]);
    }
    exit;
}

// Handle AJAX requests for updating time spent
if (isset($_GET['action']) && $_GET['action'] === 'update_time_spent') {
    header('Content-Type: application/json');

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];
    $content_id = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
    $time_spent = isset($_GET['time_spent']) ? intval($_GET['time_spent']) : 0;

    if ($user_id > 0 && $content_id > 0 && $time_spent > 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE training_progress
                SET time_spent_minutes = time_spent_minutes + ?, updated_at = NOW()
                WHERE user_id = ? AND content_type = 'post' AND content_id = ?
            ");
            $stmt->execute([$time_spent, $user_id, $content_id]);

            echo json_encode([
                'success' => true,
                'time_added' => $time_spent
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid parameters'
        ]);
    }
    exit;
}

// Handle AJAX requests for marking content as complete
if (isset($_GET['action']) && $_GET['action'] === 'mark_complete') {
    header('Content-Type: application/json');

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];
    $content_id = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
    $content_type = isset($_GET['content_type']) ? $_GET['content_type'] : 'post';

    if ($user_id > 0 && $content_id > 0) {
        try {
            $success = mark_content_complete($pdo, $user_id, $content_type, $content_id, 0);

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Content marked as complete' : 'Failed to mark complete'
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid parameters'
        ]);
    }
    exit;
}

// ============================================================
// ROLE CHECKING FUNCTIONS
// ============================================================

/**
 * Check if current user can create posts
 * @return bool True if user can create posts
 */
function can_create_posts() {
    return is_admin() || is_super_admin();
}

/**
 * Check if current user can create categories
 * @return bool True if user can create categories
 */
function can_create_categories() {
    return is_admin() || is_super_admin();
}

/**
 * Check if current user can create subcategories
 * @return bool True if user can create subcategories
 */
function can_create_subcategories() {
    return is_admin() || is_super_admin();
}

/**
 * Check if current user can access specific content
 * @param PDO $pdo Database connection
 * @param int $content_id Content ID
 * @param string $content_type Content type (post, category, subcategory)
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool True if user can access content
 */
function can_access_content($pdo, $content_id, $content_type, $user_id = null) {
    $user_id = $user_id ?? $_SESSION['user_id'];

    // Super admins can access everything
    if (is_super_admin()) {
        return true;
    }

    // Training users have restricted access
    if (is_training_user()) {
        return is_assigned_training_content($pdo, $user_id, $content_id, $content_type);
    }

    // Regular users and admins have normal access
    return true;
}

// ============================================================
// TRAINING COURSE MANAGEMENT FUNCTIONS
// ============================================================

/**
 * Create a new training course
 * @param PDO $pdo Database connection
 * @param string $name Course name
 * @param string $description Course description
 * @param string $department Department (optional)
 * @param int $created_by User ID of creator
 * @return int Course ID or false on failure
 */
function create_training_course($pdo, $name, $description, $department, $created_by) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO training_courses (name, description, department, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $department, $created_by]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating training course: " . $e->getMessage());
        return false;
    }
}

/**
 * Add content to a training course
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param string $content_type Content type (category, subcategory, post)
 * @param int $content_id Content ID
 * @param int $time_required Time required in minutes
 * @param string $admin_notes Admin notes (optional)
 * @param int $training_order Order in training sequence
 * @return bool Success status
 */
function add_content_to_course($pdo, $course_id, $content_type, $content_id, $time_required = 0, $admin_notes = '', $training_order = 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO training_course_content
            (course_id, content_type, content_id, time_required_minutes, admin_notes, training_order)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            time_required_minutes = VALUES(time_required_minutes),
            admin_notes = VALUES(admin_notes),
            training_order = VALUES(training_order)
        ");
        return $stmt->execute([$course_id, $content_type, $content_id, $time_required, $admin_notes, $training_order]);
    } catch (PDOException $e) {
        error_log("Error adding content to course: " . $e->getMessage());
        return false;
    }
}

/**
 * Assign course to users
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param array $user_ids Array of user IDs
 * @param int $assigned_by User ID making the assignment
 * @return bool Success status
 */
function assign_course_to_users($pdo, $course_id, $user_ids, $assigned_by) {
    try {
        error_log("DEBUG: assign_course_to_users called with course_id=$course_id, user_ids=" . json_encode($user_ids) . ", assigned_by=$assigned_by");

        $pdo->beginTransaction();
        $assigned_count = 0;

        $stmt = $pdo->prepare("
            INSERT INTO user_training_assignments (user_id, course_id, assigned_by, assigned_date)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
            assigned_date = CURRENT_TIMESTAMP,
            assigned_by = VALUES(assigned_by)
        ");

        // Get user info for role conversion
        $user_info_stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");

        foreach ($user_ids as $user_id) {
            error_log("DEBUG: Processing user_id=$user_id for course_id=$course_id");

            try {
                $stmt->execute([$user_id, $course_id, $assigned_by]);
                $rows_affected = $stmt->rowCount();
                error_log("DEBUG: INSERT/UPDATE rows affected for user_id=$user_id: $rows_affected");
            } catch (PDOException $e) {
                error_log("DEBUG: Database error for user_id=$user_id: " . $e->getMessage());
                throw $e; // Re-throw to be caught by outer try-catch
            }

            if ($rows_affected > 0) {
                $assigned_count++;
            }

            // Convert normal users to training role
            $user_info_stmt->execute([$user_id]);
            $user_data = $user_info_stmt->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: User data for user_id=$user_id: " . json_encode($user_data));

            if ($user_data && $user_data['role'] === 'user') {
                error_log("DEBUG: Converting user_id=$user_id from 'user' to 'training' role");
                // Convert user to training role
                $role_stmt = $pdo->prepare("
                    UPDATE users
                    SET role = 'training', previous_role = 'user'
                    WHERE id = ?
                ");
                $role_rows = $role_stmt->execute([$user_id]);
                error_log("DEBUG: Role conversion rows affected for user_id=$user_id: $role_rows");
            } else {
                error_log("DEBUG: User_id=$user_id already has role: " . ($user_data['role'] ?? 'NULL'));
            }
        }

        $pdo->commit();
        error_log("DEBUG: assign_course_to_users returning assigned_count=$assigned_count");
        return $assigned_count;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("DEBUG: assign_course_to_users ERROR: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get all training courses
 * @param PDO $pdo Database connection
 * @param bool $active_only Only show active courses
 * @return array List of courses
 */
function get_training_courses($pdo, $active_only = true) {
    try {
        $sql = "
            SELECT tc.*, u.name as creator_name,
                   COUNT(DISTINCT uta.user_id) as assigned_users,
                   COUNT(DISTINCT CASE WHEN uta.status = 'completed' THEN uta.user_id END) as completed_users,
                   COUNT(DISTINCT CASE WHEN tcc.content_type = 'post' THEN tcc.id END) as content_count
            FROM training_courses tc
            LEFT JOIN users u ON tc.created_by = u.id
            LEFT JOIN user_training_assignments uta ON tc.id = uta.course_id
            LEFT JOIN training_course_content tcc ON tc.id = tcc.course_id
        ";

        if ($active_only) {
            $sql .= " WHERE tc.is_active = TRUE";
        }

        $sql .= " GROUP BY tc.id ORDER BY tc.name";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting training courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get content assigned to a course
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return array List of course content
 */
function get_course_content($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT tcc.*,
                   CASE tcc.content_type
                       WHEN 'category' THEN c.name
                       WHEN 'subcategory' THEN sc.name
                       WHEN 'post' THEN p.title
                   END as content_name
            FROM training_course_content tcc
            LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
            LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
            LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
            WHERE tcc.course_id = ?
            ORDER BY tcc.training_order, tcc.content_type, tcc.content_id
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting course content: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// USER TRAINING ASSIGNMENT FUNCTIONS
// ============================================================

/**
 * Get courses assigned to a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array List of assigned courses
 */
function get_user_assigned_courses($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*, uta.status as assignment_status, uta.assigned_date, uta.completion_date,
                   0 as progress_percentage
            FROM training_courses tc
            JOIN user_training_assignments uta ON tc.id = uta.course_id
            WHERE uta.user_id = ? AND tc.is_active = TRUE
            ORDER BY uta.assigned_date, tc.name
        ");
        $stmt->execute([$user_id]);

        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate progress for each course
        foreach ($courses as &$course) {
            $course['progress_percentage'] = calculate_course_progress($pdo, $user_id, $course['id']);
        }

        return $courses;
    } catch (PDOException $e) {
        error_log("Error getting user assigned courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if content is assigned to user's training
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $content_id Content ID
 * @param string $content_type Content type
 * @return bool True if content is in user's training
 */
function is_assigned_training_content($pdo, $user_id, $content_id, $content_type) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM training_course_content tcc
            JOIN user_training_assignments uta ON tcc.course_id = uta.course_id
            WHERE uta.user_id = ?
            AND tcc.content_type = ?
            AND tcc.content_id = ?
            AND uta.status != 'completed'
        ");
        $stmt->execute([$user_id, $content_type, $content_id]);
        return $stmt->fetch()['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking training content assignment: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// PROGRESS TRACKING FUNCTIONS
// ============================================================

/**
 * Calculate overall training progress for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Progress data
 */
function get_overall_training_progress($pdo, $user_id) {
    try {
        // Count only POSTS for training progress (not categories/subcategories)
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT tcc.id) as total_items,
                COUNT(DISTINCT CASE WHEN tp.status = 'completed' THEN tcc.id END) as completed_items,
                COUNT(DISTINCT CASE WHEN tp.status = 'in_progress' THEN tcc.id END) as in_progress_items,
                COUNT(DISTINCT uta.course_id) as total_courses,
                COUNT(DISTINCT CASE WHEN uta.status = 'completed' THEN uta.course_id END) as completed_courses
            FROM user_training_assignments uta
            JOIN training_courses tc ON uta.course_id = tc.id
            JOIN training_course_content tcc ON uta.course_id = tcc.course_id
            LEFT JOIN training_progress tp ON tcc.content_id = tp.content_id
                AND tp.user_id = ?
                AND (tcc.content_type = tp.content_type OR tp.content_type = '' OR tp.content_type IS NULL)
            WHERE uta.user_id = ?
            AND tc.is_active = 1
            AND tcc.content_type = 'post'  -- Only count posts
        ");
        $stmt->execute([$user_id, $user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_items = (int)$data['total_items'];
        $completed_items = (int)$data['completed_items'];
        $in_progress_items = (int)$data['in_progress_items'];
        $total_courses = (int)$data['total_courses'];
        $completed_courses = (int)$data['completed_courses'];

        $percentage = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;

        // Debug logging
        log_debug("Training progress for user $user_id - Total: $total_items, Completed: $completed_items, Percentage: $percentage");

        return [
            'total_items' => $total_items,
            'completed_items' => $completed_items,
            'in_progress_items' => $in_progress_items,
            'total_courses' => $total_courses,
            'completed_courses' => $completed_courses,
            'percentage' => $percentage
        ];
    } catch (PDOException $e) {
        error_log("Error calculating overall progress: " . $e->getMessage());
        return [
            'total_items' => 0,
            'completed_items' => 0,
            'in_progress_items' => 0,
            'total_courses' => 0,
            'completed_courses' => 0,
            'percentage' => 0
        ];
    }
}

/**
 * Calculate progress for a specific course
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $course_id Course ID
 * @return array Progress data
 */
function calculate_course_progress($pdo, $user_id, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(tcc.id) as total_items,
                COUNT(CASE WHEN tp.quiz_completed = TRUE THEN tcc.id END) as completed_items,
                COUNT(CASE WHEN tp.quiz_completed = FALSE AND tp.status = 'in_progress' THEN tcc.id END) as in_progress_items
            FROM training_course_content tcc
            LEFT JOIN training_progress tp ON tcc.content_id = tp.content_id
                AND tp.user_id = ?
                AND (tcc.content_type = tp.content_type OR tp.content_type = '' OR tp.content_type IS NULL)
            WHERE tcc.course_id = ?
        ");
        $stmt->execute([$user_id, $course_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_items = (int)$data['total_items'];
        $completed_items = (int)$data['completed_items'];

        return $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
    } catch (PDOException $e) {
        error_log("Error calculating course progress: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark content as completed for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $content_type Content type
 * @param int $content_id Content ID
 * @param int $time_spent Time spent in minutes
 * @return bool Success status
 */
function mark_content_complete($pdo, $user_id, $content_type, $content_id, $time_spent = 0) {
    try {
        $pdo->beginTransaction();

        // Get course ID for this content
        $course_stmt = $pdo->prepare("
            SELECT course_id FROM training_course_content
            WHERE content_type = ? AND content_id = ?
            LIMIT 1
        ");
        $course_stmt->execute([$content_type, $content_id]);
        $course_data = $course_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course_data) {
            $pdo->rollBack();
            return false;
        }

        $course_id = $course_data['course_id'];

        // Update or insert progress record
        $progress_stmt = $pdo->prepare("
            INSERT INTO training_progress
            (user_id, course_id, content_type, content_id, status, completion_date, time_spent_minutes, time_started)
            VALUES (?, ?, ?, ?, 'completed', CURRENT_TIMESTAMP, ?,
                COALESCE((SELECT time_started FROM training_progress
                         WHERE user_id = ? AND content_type = ? AND content_id = ?), CURRENT_TIMESTAMP))
            ON DUPLICATE KEY UPDATE
            status = 'completed',
            completion_date = CURRENT_TIMESTAMP,
            time_spent_minutes = time_spent_minutes + VALUES(time_spent_minutes),
            updated_at = CURRENT_TIMESTAMP
        ");
        $progress_stmt->execute([$user_id, $course_id, $content_type, $content_id, $time_spent, $user_id, $content_type, $content_id]);

        // Save to permanent history
        save_to_training_history($pdo, $user_id, $course_id, $content_type, $content_id, $time_spent);

        // Check if course is now complete
        update_course_completion_status($pdo, $user_id, $course_id);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error marking content complete: " . $e->getMessage());
        return false;
    }
}

/**
 * Save completion to permanent training history
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $course_id Course ID
 * @param string $content_type Content type
 * @param int $content_id Content ID
 * @param int $time_spent Time spent in minutes
 * @return bool Success status
 */
function save_to_training_history($pdo, $user_id, $course_id, $content_type, $content_id, $time_spent) {
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO training_history
            (user_id, course_id, content_type, content_id, completion_date, time_spent_minutes, original_assignment_date)
            SELECT ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, assigned_date
            FROM user_training_assignments
            WHERE user_id = ? AND course_id = ?
        ");
        return $stmt->execute([$user_id, $course_id, $content_type, $content_id, $time_spent, $user_id, $course_id]);
    } catch (PDOException $e) {
        error_log("Error saving to training history: " . $e->getMessage());
        return false;
    }
}

/**
 * Update course completion status
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $course_id Course ID
 * @return bool Success status
 */
function update_course_completion_status($pdo, $user_id, $course_id) {
    try {
        // Check if all content is completed

$stmt = $pdo->prepare("
    SELECT COUNT(tcc.id) AS total_items,
           COUNT(CASE WHEN tp.status = 'completed' THEN tcc.id END) AS completed_items
    FROM training_course_content tcc
    LEFT JOIN training_progress tp
      ON tp.user_id     = ?
     AND tcc.content_id = tp.content_id
     AND (
            tcc.content_type = tp.content_type
         OR tp.content_type = ''
         OR tp.content_type IS NULL
         )
    WHERE tcc.course_id = ?
      AND tcc.content_type = 'post'
");

        $stmt->execute([$user_id, $course_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_items = (int)$data['total_items'];
        $completed_items = (int)$data['completed_items'];

        if ($total_items > 0 && $completed_items === $total_items) {
            // Mark course as completed
            $update_stmt = $pdo->prepare("
                UPDATE user_training_assignments
                SET status = 'completed', completion_date = CURRENT_TIMESTAMP
                WHERE user_id = ? AND course_id = ?
            ");
            $update_stmt->execute([$user_id, $course_id]);

            // Update history with course completion date
            $history_stmt = $pdo->prepare("
                UPDATE training_history
                SET course_completed_date = CURRENT_TIMESTAMP
                WHERE user_id = ? AND course_id = ? AND course_completed_date IS NULL
            ");
            $history_stmt->execute([$user_id, $course_id]);

            // Check if user has completed all assigned courses
            promote_user_if_training_complete($pdo, $user_id);
        }

        return true;
    } catch (PDOException $e) {
        error_log("Error updating course completion: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has completed training and promote to user role
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool True if user was promoted
 */
// --- BEGIN REPLACEMENT (promote_user_if_training_complete: explicit guard) ---
function promote_user_if_training_complete($pdo, $user_id) {
    try {
        // Only consider users who are currently 'training'
        $role_stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $role_stmt->execute([$user_id]);
        $role = strtolower((string)$role_stmt->fetchColumn());
        if ($role !== 'training') {
            return false;
        }

        // All assigned courses completed?
        $stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_courses,
        COUNT(CASE WHEN uta.status != 'completed' THEN 1 END) AS incomplete_courses
    FROM user_training_assignments uta
    JOIN training_courses tc
      ON tc.id = uta.course_id
    WHERE uta.user_id = ?
      AND tc.is_active = 1
");
$stmt->execute([$user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$data['total_courses'] > 0 && (int)$data['incomplete_courses'] === 0) {
            // --- BEGIN REPLACEMENT (DB + session + reason/date) ---
$update_stmt = $pdo->prepare("
    UPDATE users
       SET role = 'user',
           previous_role = 'training',
           original_training_completion = CURRENT_TIMESTAMP,
           updated_at = NOW()
     WHERE id = ? AND role = 'training'
");
$ok = $update_stmt->execute([$user_id]);

if ($ok && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
    $_SESSION['user_role'] = 'user';
}

return $ok;
// --- END REPLACEMENT ---

        }
        return false;
    } catch (PDOException $e) {
        error_log("Error promoting user: " . $e->getMessage());
        return false;
    }
}
// --- END REPLACEMENT ---


/**
 * Get next required training item for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array|null Next item data or null
 */
function get_next_training_item($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT tcc.*,
                   CASE tcc.content_type
                       WHEN 'category' THEN c.name
                       WHEN 'subcategory' THEN sc.name
                       WHEN 'post' THEN p.title
                   END as content_name,
                   CASE tcc.content_type
                       WHEN 'category' THEN CONCAT('category.php?id=', c.id)
                       WHEN 'subcategory' THEN CONCAT('subcategory.php?id=', sc.id)
                       WHEN 'post' THEN CONCAT('post.php?id=', p.id)
                   END as content_url
            FROM training_course_content tcc
            JOIN user_training_assignments uta ON tcc.course_id = uta.course_id
            LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
            LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
            LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
            LEFT JOIN training_progress tp ON tcc.content_type = tp.content_type
                AND tcc.content_id = tp.content_id
                AND tp.user_id = ?
            WHERE uta.user_id = ?
            AND uta.status != 'completed'
            AND (tp.status IS NULL OR tp.status != 'completed')
            ORDER BY tcc.training_order, tcc.content_type, tcc.content_id
            LIMIT 1
        ");
        $stmt->execute([$user_id, $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Error getting next training item: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// TRAINING HISTORY FUNCTIONS
// ============================================================

/**
 * Check if content is already completed in training history
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $content_type Content type
 * @param int $content_id Content ID
 * @return bool True if already completed
 */
function is_already_completed_in_history($pdo, $user_id, $content_type, $content_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM training_history
            WHERE user_id = ? AND content_type = ? AND content_id = ?
        ");
        $stmt->execute([$user_id, $content_type, $content_id]);
        return $stmt->fetch()['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking training history: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's training history
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Training history
 */
function get_training_history($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT th.*, tc.name as course_name,
                   CASE th.content_type
                       WHEN 'category' THEN c.name
                       WHEN 'subcategory' THEN sc.name
                       WHEN 'post' THEN p.title
                   END as content_name
            FROM training_history th
            JOIN training_courses tc ON th.course_id = tc.id
            LEFT JOIN categories c ON th.content_type = 'category' AND th.content_id = c.id
            LEFT JOIN subcategories sc ON th.content_type = 'subcategory' AND th.content_id = sc.id
            LEFT JOIN posts p ON th.content_type = 'post' AND th.content_id = p.id
            WHERE th.user_id = ?
            ORDER BY th.completion_date DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting training history: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// TRAINING REVERSION FUNCTIONS
// ============================================================

/**
 * Revert user to training role when new content is added
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID with new content
 * @return int Number of users reverted
 */
function handle_new_training_content($pdo, $course_id) {
    try {
        // Get users who completed this course
        $stmt = $pdo->prepare("
            SELECT DISTINCT uta.user_id, u.name, u.role
            FROM user_training_assignments uta
            JOIN users u ON uta.user_id = u.id
            WHERE uta.course_id = ? AND uta.status = 'completed' AND u.role != 'training'
        ");
        $stmt->execute([$course_id]);
        $completed_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reverted_count = 0;

        foreach ($completed_users as $user) {
            if (revert_user_to_training($pdo, $user['user_id'], $course_id, "New content added to course ID: $course_id")) {
                $reverted_count++;
            }
        }

        return $reverted_count;
    } catch (PDOException $e) {
        error_log("Error handling new training content: " . $e->getMessage());
        return 0;
    }
}

/**
 * Revert a specific user to training role
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $course_id Course ID with new content
 * @param string $reason Reason for reversion
 * @return bool Success status
 */
// --- BEGIN REPLACEMENT (revert_user_to_training: only flip normal users) ---
function revert_user_to_training($pdo, $user_id, $course_id, $reason) {
    try {
        $pdo->beginTransaction();

        // Only flip plain users (never admins or super admins)
        $user_stmt = $pdo->prepare("
            UPDATE users
               SET role = 'training',
                   previous_role = role,
                   training_revert_reason = ?,
                   original_training_completion = NOW()
             WHERE id = ?
               AND role = 'user'
        ");
        $user_stmt->execute([$reason, $user_id]);

        // Reset assignment + progress for that course (safe for any role)
        $assignment_stmt = $pdo->prepare("
            UPDATE user_training_assignments
               SET status = 'in_progress',
                   completion_date = NULL
             WHERE user_id = ? AND course_id = ?
        ");
        $assignment_stmt->execute([$user_id, $course_id]);

        $progress_stmt = $pdo->prepare("
            DELETE FROM training_progress
             WHERE user_id = ?
               AND course_id = ?
               AND status = 'completed'
        ");
        $progress_stmt->execute([$user_id, $course_id]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error reverting user to training: " . $e->getMessage());
        return false;
    }
}
// --- END REPLACEMENT ---


// ============================================================
// TRAINING VISIBILITY AND PROGRESS FUNCTIONS
// ============================================================

/**
 * Check if user should see training progress bar
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool True if user should see progress bar
 */
function should_show_training_progress($pdo, $user_id = null) {
    $user_id = $user_id ?? $_SESSION['user_id'];

    if (function_exists('log_debug')) {
        log_debug("should_show_training_progress called - User ID: $user_id, User role: " . ($_SESSION['user_role'] ?? 'none'));
    }

    // Always show for training users
    if (is_training_user()) {
        if (function_exists('log_debug')) {
            log_debug("User is training user - showing progress bar");
        }
        return true;
    }

    // Show for admins/super admins if they have active training assignments
    if (is_admin() || is_super_admin()) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM user_training_assignments uta
                WHERE uta.user_id = ?
                AND uta.status IN ('not_started', 'in_progress')
                AND EXISTS (
                    SELECT 1 FROM training_courses tc
                    WHERE tc.id = uta.course_id AND tc.is_active = 1
                )
            ");
            $stmt->execute([$user_id]);
            $active_assignments = $stmt->fetchColumn();

            if (function_exists('log_debug')) {
                log_debug("Admin user has $active_assignments active assignments");
            }

            return $active_assignments > 0;
        } catch (PDOException $e) {
            if (function_exists('log_debug')) {
                log_debug("Database error in should_show_training_progress: " . $e->getMessage());
            }
            return false;
        }
    }

    if (function_exists('log_debug')) {
        log_debug("User is not training user or admin - not showing progress bar");
    }

    return false;
}

/**
 * Get user's assigned content for visibility filtering
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Array of assigned content IDs by type
 */
function get_user_assigned_content_ids($pdo, $user_id) {
    $assigned_content = [
        'category' => [],
        'subcategory' => [],
        'post' => []
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT tcc.content_type, tcc.content_id
            FROM user_training_assignments uta
            JOIN training_course_content tcc ON uta.course_id = tcc.course_id
            WHERE uta.user_id = ?
            AND uta.status IN ('not_started', 'in_progress', 'completed')
            AND EXISTS (
                SELECT 1 FROM training_courses tc
                WHERE tc.id = uta.course_id AND tc.is_active = 1
            )
        ");
        $stmt->execute([$user_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $assigned_content[$row['content_type']][] = $row['content_id'];
        }

        // Remove duplicates
        foreach ($assigned_content as $type => &$ids) {
            $ids = array_unique($ids);
        }

    } catch (PDOException $e) {
        error_log("Error getting assigned content IDs: " . $e->getMessage());
    }

    return $assigned_content;
}

/**
 * Filter content based on training assignments
 * @param PDO $pdo Database connection
 * @param array $content_items Array of content items
 * @param string $content_type Type of content (category, subcategory, post)
 * @param int $user_id User ID (optional, defaults to current user)
 * @return array Filtered content items
 */
function filter_content_for_training_user($pdo, $content_items, $content_type, $user_id = null) {
    $user_id = $user_id ?? $_SESSION['user_id'];

    // If not a training user, return all content
    if (!is_training_user()) {
        return $content_items;
    }

    // Get assigned content IDs
    $assigned_ids = get_user_assigned_content_ids($pdo, $user_id);
    $type_assigned_ids = $assigned_ids[$content_type] ?? [];

    // Filter content to only show assigned items
    return array_filter($content_items, function($item) use ($type_assigned_ids) {
        return in_array($item['id'], $type_assigned_ids);
    });
}

/**
 * Check if user has completed all training and should be promoted
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool True if user completed all training
 */
function has_completed_all_training($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_courses,
                   COUNT(CASE WHEN status != 'completed' THEN 1 END) as incomplete_courses
            FROM user_training_assignments
            WHERE user_id = ?
            AND EXISTS (
                SELECT 1 FROM training_courses tc
                WHERE tc.id = course_id AND tc.is_active = 1
            )
        ");
        $stmt->execute([$user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)$data['total_courses'] > 0 && (int)$data['incomplete_courses'] === 0;
    } catch (PDOException $e) {
        error_log("Error checking training completion: " . $e->getMessage());
        return false;
    }
}

?>