<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication and admin check
require_once __DIR__ . '/../includes/auth_check.php';

// Load database and helpers
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/training_helpers.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once dirname(__DIR__) . '/includes/include_path.php';
require_app_file('auth_check.php');

// Load database and helpers
require_app_file('db_connect.php');
require_app_file('training_helpers.php');
require_app_file('user_helpers.php');

// Check if user is admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Check if training tables exist
$training_tables_exist = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'training_courses'");
    $training_tables_exist = ($stmt->rowCount() > 0);
} catch (PDOException $e) {
    $error_message = "Error checking training tables: " . $e->getMessage();
}

// Check if course content table exists
$course_content_table_exists = false;
if ($training_tables_exist) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'training_course_content'");
        $course_content_table_exists = ($stmt->rowCount() > 0);

        // Create the table if it doesn't exist
        if (!$course_content_table_exists) {
            $create_table = "
                CREATE TABLE `training_course_content` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `course_id` int(11) NOT NULL,
                  `content_type` enum('category','subcategory','post') NOT NULL,
                  `content_id` int(11) NOT NULL,
                  `added_by` int(11) NOT NULL,
                  `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unique_course_content` (`course_id`,`content_type`,`content_id`),
                  KEY `idx_course` (`course_id`),
                  KEY `idx_content` (`content_type`,`content_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            $pdo->exec($create_table);
            $course_content_table_exists = true;
        }
    } catch (PDOException $e) {
        $error_message = "Error creating course content table: " . $e->getMessage();
    }
}

// Get course ID
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id <= 0) {
    header('Location: manage_training_courses.php');
    exit;
}

// Get course information
$course = null;
if ($training_tables_exist) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM training_courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            header('Location: manage_training_courses.php');
            exit;
        }
    } catch (PDOException $e) {
        $error_message = "Error loading course information: " . $e->getMessage();
    }
}

// Check what columns exist in training_course_content table
$available_columns = [];
if ($course_content_table_exists) {
    try {
        $columns_check = $pdo->query("SHOW COLUMNS FROM training_course_content");
        while ($row = $columns_check->fetch(PDO::FETCH_ASSOC)) {
            $available_columns[] = $row['Field'];
        }
    } catch (PDOException $e) {
        $error_message = "Error checking table columns: " . $e->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $training_tables_exist && $course) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'add_content':
            $content_type = isset($_POST['content_type']) ? $_POST['content_type'] : '';
            $add_all = isset($_POST['add_all']) && $_POST['add_all'] === 'true';

            if ($add_all) {
                // Add all content of the selected type
                try {
                    $added_count = 0;
                    $skipped_count = 0;

                    if ($content_type === 'category') {
                        // Add all subcategories and posts of selected category
                        $category_id = intval($_POST['category_id']);

                        // Build INSERT queries based on available columns
                        $insert_columns = ['course_id', 'content_type', 'content_id'];
                        $insert_values = ['?', 'category', '?'];
                        $insert_params = [$course_id, $category_id];

                        if (in_array('added_by', $available_columns)) {
                            $insert_columns[] = 'added_by';
                            $insert_values[] = '?';
                            $insert_params[] = $_SESSION['user_id'];
                        }

                        $columns_str = implode(', ', $insert_columns);
                        $values_str = implode(', ', $insert_values);

                        // Add the category itself
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO training_course_content ({$columns_str})
                            VALUES ({$values_str})
                        ");
                        $stmt->execute($insert_params);
                        if ($stmt->rowCount() > 0) $added_count++;
                        else $skipped_count++;

                        // Add all subcategories of this category
                        $subcat_columns = ['?', 'subcategory', 'id'];
                        $subcat_params = [$course_id];
                        if (in_array('added_by', $available_columns)) {
                            $subcat_columns[] = '?';
                            $subcat_params[] = $_SESSION['user_id'];
                        }

                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO training_course_content (course_id, content_type, content_id" .
                            (in_array('added_by', $available_columns) ? ', added_by' : '') . ")
                            SELECT " . implode(', ', $subcat_columns) . "
                            FROM subcategories WHERE category_id = ?
                        ");
                        $stmt->execute(array_merge($subcat_params, [$category_id]));
                        $added_count += $stmt->rowCount();

                        // Add all posts of this category
                        $post_columns = ['?', 'post', 'p.id'];
                        $post_params = [$course_id];
                        if (in_array('added_by', $available_columns)) {
                            $post_columns[] = '?';
                            $post_params[] = $_SESSION['user_id'];
                        }

                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO training_course_content (course_id, content_type, content_id" .
                            (in_array('added_by', $available_columns) ? ', added_by' : '') . ")
                            SELECT " . implode(', ', $post_columns) . "
                            FROM posts p
                            JOIN subcategories sc ON p.subcategory_id = sc.id
                            WHERE sc.category_id = ?
                        ");
                        $stmt->execute(array_merge($post_params, [$category_id]));
                        $added_count += $stmt->rowCount();

                    } elseif ($content_type === 'subcategory') {
                        // Add all posts of selected subcategory
                        $subcategory_id = intval($_POST['subcategory_id']);

                        // Add the subcategory itself
                        $insert_columns = ['course_id', 'content_type', 'content_id'];
                        $insert_values = ['?', 'subcategory', '?'];
                        $insert_params = [$course_id, $subcategory_id];

                        if (in_array('added_by', $available_columns)) {
                            $insert_columns[] = 'added_by';
                            $insert_values[] = '?';
                            $insert_params[] = $_SESSION['user_id'];
                        }

                        $columns_str = implode(', ', $insert_columns);
                        $values_str = implode(', ', $insert_values);

                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO training_course_content ({$columns_str})
                            VALUES ({$values_str})
                        ");
                        $stmt->execute($insert_params);
                        if ($stmt->rowCount() > 0) $added_count++;
                        else $skipped_count++;

                        // Add all posts of this subcategory
                        $post_columns = ['?', 'post', 'id'];
                        $post_params = [$course_id];
                        if (in_array('added_by', $available_columns)) {
                            $post_columns[] = '?';
                            $post_params[] = $_SESSION['user_id'];
                        }

                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO training_course_content (course_id, content_type, content_id" .
                            (in_array('added_by', $available_columns) ? ', added_by' : '') . ")
                            SELECT " . implode(', ', $post_columns) . "
                            FROM posts WHERE subcategory_id = ?
                        ");
                        $stmt->execute(array_merge($post_params, [$subcategory_id]));
                        $added_count += $stmt->rowCount();

                    }

                    $success_message = "Successfully added $added_count items to the course!" .
                                      ($skipped_count > 0 ? " ($skipped_count items were already in the course)" : "");

                } catch (PDOException $e) {
                    $error_message = "Error adding content to course: " . $e->getMessage();
                }
            } else {
                // Add single content item
                $content_id = isset($_POST['content_id']) ? intval($_POST['content_id']) : 0;

                if ($content_id > 0 && in_array($content_type, ['category', 'subcategory', 'post'])) {
                    try {
                        $insert_columns = ['course_id', 'content_type', 'content_id'];
                        $insert_values = ['?', '?', '?'];
                        $insert_params = [$course_id, $content_type, $content_id];

                        if (in_array('added_by', $available_columns)) {
                            $insert_columns[] = 'added_by';
                            $insert_values[] = '?';
                            $insert_params[] = $_SESSION['user_id'];
                        }

                        $columns_str = implode(', ', $insert_columns);
                        $values_str = implode(', ', $insert_values);

                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO training_course_content ({$columns_str})
                            VALUES ({$values_str})
                        ");
                        $result = $stmt->execute($insert_params);

                        if ($stmt->rowCount() > 0) {
                            $success_message = "Content added to course successfully!";
                        } else {
                            $error_message = "This content is already added to the course.";
                        }
                    } catch (PDOException $e) {
                        $error_message = "Error adding content to course: " . $e->getMessage();
                    }
                }
            }
            break;

        case 'remove_content':
            $content_id = isset($_POST['content_id']) ? intval($_POST['content_id']) : 0;
            $content_type = isset($_POST['content_type']) ? $_POST['content_type'] : '';

            if ($content_id > 0 && in_array($content_type, ['category', 'subcategory', 'post'])) {
                try {
                    if ($content_type !== 'post') {
                        // Legacy support: category/subcategory removals behave as before
                        $stmt = $pdo->prepare("
                            DELETE FROM training_course_content
                            WHERE course_id = ? AND content_type = ? AND content_id = ?
                        ");
                        $stmt->execute([$course_id, $content_type, $content_id]);
                        if ($stmt->rowCount() > 0) {
                            $success_message = "Content removed from course successfully!";
                        } else {
                            $error_message = "Content not found in course.";
                        }
                        break;
                    }

                    // 1) Remove the POST from the course content
                    $delPost = $pdo->prepare("
                        DELETE FROM training_course_content
                        WHERE course_id = ? AND content_type = 'post' AND content_id = ?
                    ");
                    $delPost->execute([$course_id, $content_id]);

                    if ($delPost->rowCount() === 0) {
                        $error_message = "Content not found in course.";
                        break;
                    }

                    // 2) Find its subcategory and category
                    $findSC = $pdo->prepare("
                        SELECT sc.id AS subcategory_id, sc.category_id AS category_id
                        FROM posts p
                        LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
                        WHERE p.id = ?
                        LIMIT 1
                    ");
                    $findSC->execute([$content_id]);
                    $link = $findSC->fetch(PDO::FETCH_ASSOC);

                    $success_message = "Post removed from course successfully!";

                    if (!$link) {
                        // Post had no subcategory/category link; we're done
                        break;
                    }

                    $subId = (int)($link['subcategory_id'] ?? 0);
                    $catId = (int)($link['category_id'] ?? 0);

                    // 3) If no other assigned POSTS remain in the same subcategory, remove the subcategory entry
                    if ($subId > 0) {
                        $countSC = $pdo->prepare("
                            SELECT COUNT(*) AS cnt
                            FROM training_course_content tcc
                            JOIN posts p2 ON p2.id = tcc.content_id AND tcc.content_type = 'post'
                            WHERE tcc.course_id = ? AND p2.subcategory_id = ?
                        ");
                        $countSC->execute([$course_id, $subId]);
                        $cnt = (int)$countSC->fetchColumn();

                        if ($cnt === 0) {
                            $delSC = $pdo->prepare("
                                DELETE FROM training_course_content
                                WHERE course_id = ? AND content_type = 'subcategory' AND content_id = ?
                            ");
                            $delSC->execute([$course_id, $subId]);
                        }
                    }

                    // 4) If no other assigned POSTS remain in the same category, remove the category entry
                    if ($catId > 0) {
                        $countCat = $pdo->prepare("
                            SELECT COUNT(*) AS cnt
                            FROM training_course_content tcc
                            JOIN posts p2 ON p2.id = tcc.content_id AND tcc.content_type = 'post'
                            JOIN subcategories sc2 ON sc2.id = p2.subcategory_id
                            WHERE tcc.course_id = ? AND sc2.category_id = ?
                        ");
                        $countCat->execute([$course_id, $catId]);
                        $cntC = (int)$countCat->fetchColumn();

                        if ($cntC === 0) {
                            $delCat = $pdo->prepare("
                                DELETE FROM training_course_content
                                WHERE course_id = ? AND content_type = 'category' AND content_id = ?
                            ");
                            $delCat->execute([$course_id, $catId]);
                        }
                    }
                } catch (PDOException $e) {
                    $error_message = "Error removing content from course: " . $e->getMessage();
                }
            }
            break;

        case 'add_bulk_content':
            // Handle bulk content selection
            if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
                try {
                    $added_count = 0;
                    $skipped_count = 0;

                    foreach ($_POST['selected_items'] as $item) {
                        $parts = explode(':', $item);
                        if (count($parts) === 2) {
                            $content_type = $parts[0];
                            $content_id = intval($parts[1]);

                            $insert_columns = ['course_id', 'content_type', 'content_id'];
                            $insert_values = ['?', '?', '?'];
                            $insert_params = [$course_id, $content_type, $content_id];

                            if (in_array('added_by', $available_columns)) {
                                $insert_columns[] = 'added_by';
                                $insert_values[] = '?';
                                $insert_params[] = $_SESSION['user_id'];
                            }

                            $columns_str = implode(', ', $insert_columns);
                            $values_str = implode(', ', $insert_values);

                            $stmt = $pdo->prepare("
                                INSERT IGNORE INTO training_course_content ({$columns_str})
                                VALUES ({$values_str})
                            ");
                            $stmt->execute($insert_params);

                            if ($stmt->rowCount() > 0) {
                                $added_count++;
                            } else {
                                $skipped_count++;
                            }
                        }
                    }

                    $success_message = "Successfully added $added_count items to the course!" .
                                      ($skipped_count > 0 ? " ($skipped_count items were already in the course)" : "");

                } catch (PDOException $e) {
                    $error_message = "Error adding content to course: " . $e->getMessage();
                }
            }
            break;
    }
}

// Posts-focused list for display: one card per POST with its category + subcategory
$course_posts = [];
if ($training_tables_exist && $course && $course_content_table_exists) {
    try {
        // columns exist check (to detect optional fields like 'added_by' in other installs)
        $columns_check = $pdo->query("SHOW COLUMNS FROM training_course_content");
        $tcc_cols = [];
        while ($row = $columns_check->fetch(PDO::FETCH_ASSOC)) {
            $tcc_cols[] = $row['Field'];
        }
        $hasAddedBy = in_array('added_by', $tcc_cols);

        $joinUser = $hasAddedBy ? "LEFT JOIN users u ON u.id = tcc.added_by" : "";

        // Try the common user name fields in order; fall back to username, then 'Unknown'
        $addedBy  = $hasAddedBy
            ? "COALESCE(NULLIF(u.name,''), NULLIF(u.full_name,''), NULLIF(u.display_name,''), NULLIF(u.username,''), 'Unknown') AS added_by_username,"
            : "";

        // Your table defines `added_at` (not `created_at`). If missing, fall back to NOW().
        $createdAt = in_array('added_at', $tcc_cols) ? "tcc.added_at AS added_at" : "CURRENT_TIMESTAMP() AS added_at";

        $sql = "
            SELECT
                p.id AS post_id,
                p.title,
                sc.id AS subcategory_id,
                sc.name AS subcategory_name,
                c.id AS category_id,
                c.name AS category_name,
                {$addedBy}
                {$createdAt}
            FROM training_course_content tcc
            JOIN posts p
                ON tcc.content_type = 'post'
               AND p.id = tcc.content_id
            LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
            LEFT JOIN categories c ON c.id = sc.category_id
            {$joinUser}
            WHERE tcc.course_id = ?
            ORDER BY c.name, sc.name, p.title
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id]);
        $course_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Error loading course content: " . $e->getMessage();
    }
}

// Get available content to add
$available_categories = [];
$available_subcategories = [];
$available_posts = [];

if ($training_tables_exist) {
    try {
        // Get categories
        $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
        $available_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get subcategories
        $stmt = $pdo->query("
            SELECT sc.id, sc.name, c.name as category_name
            FROM subcategories sc
            LEFT JOIN categories c ON sc.category_id = c.id
            ORDER BY c.name, sc.name
        ");
        $available_subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get posts
        $stmt = $pdo->query("
            SELECT p.id, p.title, c.name as category_name, sc.name as subcategory_name
            FROM posts p
            LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
            LEFT JOIN categories c ON sc.category_id = c.id
            ORDER BY c.name, sc.name, p.title
        ");
        $available_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Error loading available content: " . $e->getMessage();
    }
}

// Page title for shared header
$page_title = 'Manage Course Content';
require_once __DIR__ . '/includes/header.php';
?>
<style>
/* Scope all page-specific styles to avoid fighting global theme classes */
.manage-course-content .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.manage-course-content .header-nav {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 16px 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.manage-course-content .content-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}
.manage-course-content .section-header {
    background: #f8f9fa;
    padding: 16px 20px;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}
.manage-course-content .section-content { padding: 20px; }
.manage-course-content .content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.manage-course-content .content-item {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 16px;
    position: relative;
}
.manage-course-content .content-item h4 {
    margin: 0 0 8px 0;
    color: #495057;
    font-size: 16px;
}
.manage-course-content .content-item .meta {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
}
/* Pin the action area to the bottom-right of the card */
.manage-course-content .content-item .actions {
    position: absolute;
    right: 12px;
    bottom: 12px;
}

/* Ensure content never gets covered by the action button */
.manage-course-content .content-item {
    padding-bottom: 56px; /* space for the bottom-right Remove button */
}
/* Tidy spacing for the badges/title stack */
.manage-course-content .badge-row { margin: 0 0 8px 0; }
.manage-course-content .content-item h4 { margin: 0 0 4px 0; }


.manage-course-content .add-content-form {
    background: #e9ecef;
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 20px;
}
.manage-course-content .form-group { margin-bottom: 16px; }
.manage-course-content .form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #495057;
}
.manage-course-content .form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}
.manage-course-content .form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}
.manage-course-content .checkbox-group {
    margin: 12px 0;
    padding: 12px;
    background: white;
    border: 1px solid #ced4da;
    border-radius: 4px;
    max-height: 200px;
    overflow-y: auto;
}
.manage-course-content .checkbox-item {
    display: flex;
    align-items: center;
    padding: 4px 0;
}
.manage-course-content .checkbox-item input[type="checkbox"] {
    margin-right: 8px;
    transform: scale(1.2);
}
.manage-course-content .checkbox-item label { margin: 0; cursor: pointer; flex: 1; }
.manage-course-content .select-all-container {
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #dee2e6;
}
.manage-course-content .error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 16px;
    border: 1px solid #f5c6cb;
}
.manage-course-content .success {
    background: #d4edda;
    color: #155724;
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 16px;
    border: 1px solid #c3e6cb;
}
    
    /* Card action buttons: smaller + tighter than global .btn */
.manage-course-content .content-item .btn {
    margin: 2px;
    padding: 4px 10px;
    font-size: 12px;
    line-height: 1.2;
    border-radius: 4px;
}
.manage-course-content .content-item .btn.btn-sm {
    padding: 3px 8px;
    font-size: 11px;
}

/* Keep header back button styled like the gradient header */
.manage-course-content .header-nav .btn {
    background: rgba(255,255,255,0.2);
    color: #fff;
    border: none;
    padding: 8px 14px;
    font-size: 13px;
    line-height: 1.2;
    border-radius: 4px;
    transition: background 0.3s;
}
.manage-course-content .header-nav .btn:hover {
    background: rgba(255,255,255,0.3);
    color: #fff;
    text-decoration: none;
}

/* Position the meta text bottom-left, paired with Remove bottom-right */
.manage-course-content .content-item {
    position: relative;
    padding-bottom: 56px; /* keeps space for buttons + meta */
}
.manage-course-content .content-item .meta {
    position: absolute;
    left: 12px;
    bottom: 12px;
    font-size: 12px;
    color: #6c757d;
}
/* Remove any extra top margin from meta now that it’s anchored */
.manage-course-content .content-item .meta[style] { margin-top: 0 !important; }

/* Badges: snug padding + proper vertical centering */
.manage-course-content .content-type-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.1;
    white-space: nowrap;
    vertical-align: middle;
}
.manage-course-content .badge-row {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin: 4px 0 0 0;
}
.manage-course-content .empty-state { text-align: center; padding: 40px; color: #6c757d; }
.manage-course-content .empty-state-icon { font-size: 48px; margin-bottom: 16px; }


.manage-course-content .badge-category { background: #007bff; color: #fff; }
.manage-course-content .badge-subcategory { background: #28a745; color: #fff; }
.manage-course-content .badge-post { background: #ffc107; color: #212529; }

.manage-course-content .flow-indicator {
    background: #e3f2fd; border: 1px solid #2196f3; border-radius: 4px;
    padding: 12px; margin-bottom: 16px; color: #1565c0; font-size: 14px;
}
.manage-course-content .button-group { display: flex; gap: 8px; margin-top: 12px; }
.manage-course-content .radio-group { margin: 12px 0; }
.manage-course-content .radio-item { display: flex; align-items: center; margin: 8px 0; }
.manage-course-content .radio-item input[type="radio"] { margin-right: 8px; transform: scale(1.2); }
.manage-course-content .radio-item label { margin: 0; cursor: pointer; }
</style>

<div class="manage-course-content">
    <div class="container">
        <div class="header-nav">
            <div>
                <h1 style="margin: 0 0 4px 0;">📚 Course Content Management</h1>
                <p style="margin: 0; opacity: 0.9;"><?php echo htmlspecialchars($course['name'] ?? 'Course'); ?></p>
            </div>
            <a href="manage_training_courses.php" class="btn btn-secondary">← Back to Courses</a>
        </div>

        <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="error">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$training_tables_exist): ?>
            <div class="content-section">
                <div class="section-content">
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px;">
                        <h3 style="color: #856404; margin: 0 0 8px 0;">⚠️ Database Setup Required</h3>
                        <p style="color: #856404; margin: 0;">The training tables don't exist in your database. Please import the add_training_system.sql file first.</p>
                    </div>
                </div>
            </div>
        <?php elseif (!$course): ?>
            <div class="content-section">
                <div class="section-content">
                    <div class="empty-state">
                        <div class="empty-state-icon">❌</div>
                        <h3>Course Not Found</h3>
                        <p>The requested course could not be found.</p>
                        <a href="manage_training_courses.php" class="btn btn-primary">Back to Courses</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Add Content Form -->
            <div class="content-section">
                <div class="section-header">➕ Add Content to Course</div>
                <div class="section-content">
                    <div class="flow-indicator">
                        💡 <strong>Selection Flow:</strong> Choose a category, then select "All items" or specific subcategories, then select "All posts" or specific posts.
                    </div>

                    <form method="POST" action="" id="addContentForm" style="margin: 0;">
                        <input type="hidden" name="action" value="add_bulk_content">

                        <!-- Step 1: Select Category -->
                        <div class="form-group">
                            <label for="category_id">Step 1: Select Category:</label>
                            <select name="category_id" id="category_id" class="form-control" required>
                                <option value="">Select a category...</option>
                                <?php foreach ($available_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Step 2: Select Subcategories -->
                        <div class="form-group" id="subcategory_step" style="display: none;">
                            <label>Step 2: Select Subcategories:</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="subcategory_selection" id="all_subcategories" value="all" checked>
                                    <label for="all_subcategories">📂 All subcategories in this category</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="subcategory_selection" id="specific_subcategories" value="specific">
                                    <label for="specific_subcategories">📁 Select specific subcategories:</label>
                                </div>
                            </div>
                            <div class="checkbox-group" id="subcategory_checkboxes" style="display: none;">
                                <div class="select-all-container">
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="select_all_subcategories" onchange="toggleAllSubcategories()">
                                        <label for="select_all_subcategories"><strong>Select All Subcategories</strong></label>
                                    </div>
                                </div>
                                <div id="subcategory_list"></div>
                            </div>
                        </div>

                        <!-- Step 3: Select Posts -->
                        <div class="form-group" id="post_step" style="display: none;">
                            <label>Step 3: Select Posts:</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="post_selection" id="all_posts" value="all" checked>
                                    <label for="all_posts">📄 All posts in selected subcategories</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="post_selection" id="specific_posts" value="specific">
                                    <label for="specific_posts">📝 Select specific posts:</label>
                                </div>
                            </div>
                            <div class="checkbox-group" id="post_checkboxes" style="display: none;">
                                <div class="select-all-container">
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="select_all_posts" onchange="toggleAllPosts()">
                                        <label for="select_all_posts"><strong>Select All Posts</strong></label>
                                    </div>
                                </div>
                                <div id="post_list"></div>
                            </div>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-success">📚 Add Selected Content</button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">🔄 Reset Selection</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Course Content -->
            <div class="content-section">
                <div class="section-header">📋 Current Course Content (<?php echo count($course_posts); ?> items)</div>
                <div class="section-content">
                    <?php if (empty($course_posts)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📚</div>
                            <h3>No Content Added Yet</h3>
                            <p>This course doesn't have any content assigned yet. Use the form above to add content.</p>
                        </div>
                    <?php else: ?>
                        <div class="content-grid">
                            <?php foreach ($course_posts as $post): ?>
                                <div class="content-item">
                                    <!-- Badges first -->
<div class="badge-row">
    <?php if (!empty($post['category_name'])): ?>
        <span class="content-type-badge badge-category">
            <?php echo htmlspecialchars($post['category_name']); ?>
        </span>
    <?php endif; ?>
    <?php if (!empty($post['subcategory_name'])): ?>
        <span class="content-type-badge badge-subcategory">
            <?php echo htmlspecialchars($post['subcategory_name']); ?>
        </span>
    <?php endif; ?>
</div>

<!-- Then the post title -->
<h4><?php echo htmlspecialchars($post['title'] ?? 'Untitled Post'); ?></h4>

<!-- Meta stays under the title -->
<div class="meta" style="margin-top:6px;">
    <?php
        $addedAt = isset($post['added_at']) ? date('M j, Y', strtotime($post['added_at'])) : '';
        if (!empty($hasAddedBy) && isset($post['added_by_username'])) {
            echo 'Added by ' . htmlspecialchars($post['added_by_username']);
            if ($addedAt) { echo ' on ' . $addedAt; }
        } else {
            if ($addedAt) { echo 'Added on ' . $addedAt; }
        }
    ?>
</div>

<!-- Remove button anchored to bottom-right (CSS handles position) -->
<div class="actions">
    <form method="POST" action="" style="margin: 0;">
        <input type="hidden" name="action" value="remove_content">
        <input type="hidden" name="content_type" value="post">
        <input type="hidden" name="content_id" value="<?php echo (int)$post['post_id']; ?>">
        <button type="submit" class="btn btn-danger btn-sm"
                onclick="return confirm('Remove this post from the course? This may also remove its subcategory/category if they become empty.')">
            🗑️ Remove
        </button>
    </form>
</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Data storage
let categoriesData = <?php echo json_encode($available_categories); ?>;
let subcategoriesData = <?php echo json_encode($available_subcategories); ?>;
let postsData = <?php echo json_encode($available_posts); ?>;

// Handle category selection
document.getElementById('category_id').addEventListener('change', function() {
    const categoryId = parseInt(this.value);

    if (categoryId > 0) {
        loadSubcategories(categoryId);
        document.getElementById('subcategory_step').style.display = 'block';
    } else {
        document.getElementById('subcategory_step').style.display = 'none';
        document.getElementById('post_step').style.display = 'none';
    }
});

// Handle subcategory selection type
document.querySelectorAll('input[name="subcategory_selection"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'specific') {
            document.getElementById('subcategory_checkboxes').style.display = 'block';
        } else {
            document.getElementById('subcategory_checkboxes').style.display = 'none';
            document.getElementById('post_step').style.display = 'block';
            loadPostsForAllSubcategories();
        }
    });
});

// Handle post selection type
document.querySelectorAll('input[name="post_selection"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'specific') {
            document.getElementById('post_checkboxes').style.display = 'block';
        } else {
            document.getElementById('post_checkboxes').style.display = 'none';
        }
    });
});

// Load subcategories for selected category
function loadSubcategories(categoryId) {
    const subcategoryList = document.getElementById('subcategory_list');
    subcategoryList.innerHTML = '';

    const filteredSubcategories = subcategoriesData.filter(sub => sub.category_name === getCategoryName(categoryId));

    filteredSubcategories.forEach(subcategory => {
        const div = document.createElement('div');
        div.className = 'checkbox-item';
        div.innerHTML = `
            <input type="checkbox" name="selected_items[]" value="subcategory:${subcategory.id}" id="sub_${subcategory.id}" class="subcategory-checkbox" onchange="updatePostsForSubcategories()">
            <label for="sub_${subcategory.id}">${subcategory.name}</label>
        `;
        subcategoryList.appendChild(div);
    });
}

// Load posts for all subcategories
function loadPostsForAllSubcategories() {
    const categoryId = parseInt(document.getElementById('category_id').value);
    const categoryName = getCategoryName(categoryId);
    const postList = document.getElementById('post_list');
    postList.innerHTML = '';

    const filteredPosts = postsData.filter(post => post.category_name === categoryName);

    filteredPosts.forEach(post => {
        const div = document.createElement('div');
        div.className = 'checkbox-item';
        div.innerHTML = `
            <input type="checkbox" name="selected_items[]" value="post:${post.id}" id="post_${post.id}" class="post-checkbox">
            <label for="post_${post.id}">${post.subcategory_name} → ${post.title}</label>
        `;
        postList.appendChild(div);
    });

    document.getElementById('post_step').style.display = 'block';
}

// Update posts when specific subcategories are selected
function updatePostsForSubcategories() {
    const selectedSubcategories = Array.from(document.querySelectorAll('.subcategory-checkbox:checked'));

    if (selectedSubcategories.length > 0) {
        document.getElementById('post_step').style.display = 'block';

        const postList = document.getElementById('post_list');
        postList.innerHTML = '';

        selectedSubcategories.forEach(checkbox => {
            const subcategoryId = parseInt(checkbox.value.split(':')[1]);
            const subcategory = subcategoriesData.find(sub => sub.id === subcategoryId);

            if (subcategory) {
                const filteredPosts = postsData.filter(post => post.subcategory_name === subcategory.name);

                filteredPosts.forEach(post => {
                    const div = document.createElement('div');
                    div.className = 'checkbox-item';
                    div.innerHTML = `
                        <input type="checkbox" name="selected_items[]" value="post:${post.id}" id="post_${post.id}" class="post-checkbox">
                        <label for="post_${post.id}">${post.title}</label>
                    `;
                    postList.appendChild(div);
                });
            }
        });
    } else {
        document.getElementById('post_step').style.display = 'none';
    }
}

// Helper function to get category name
function getCategoryName(categoryId) {
    const category = categoriesData.find(cat => cat.id === categoryId);
    return category ? category.name : '';
}

// Toggle all subcategories
function toggleAllSubcategories() {
    const selectAll = document.getElementById('select_all_subcategories');
    const checkboxes = document.querySelectorAll('.subcategory-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });

    updatePostsForSubcategories();
}

// Toggle all posts
function toggleAllPosts() {
    const selectAll = document.getElementById('select_all_posts');
    const checkboxes = document.querySelectorAll('.post-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// Reset form
function resetForm() {
    document.getElementById('addContentForm').reset();
    document.getElementById('subcategory_step').style.display = 'none';
    document.getElementById('post_step').style.display = 'none';
    document.getElementById('subcategory_checkboxes').style.display = 'none';
    document.getElementById('post_checkboxes').style.display = 'none';
}

// Handle form submission to include selected items
document.getElementById('addContentForm').addEventListener('submit', function(e) {
    const categoryId = parseInt(document.getElementById('category_id').value);
    const subcategorySelection = document.querySelector('input[name="subcategory_selection"]:checked');
    const postSelection = document.querySelector('input[name="post_selection"]:checked');

    // Always add the selected category
    const categoryInput = document.createElement('input');
    categoryInput.type = 'hidden';
    categoryInput.name = 'selected_items[]';
    categoryInput.value = `category:${categoryId}`;
    this.appendChild(categoryInput);

    if (subcategorySelection && subcategorySelection.value === 'all') {
        // Add all subcategories of this category
        const categoryName = getCategoryName(categoryId);
        subcategoriesData.forEach(subcategory => {
            if (subcategory.category_name === categoryName) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_items[]';
                input.value = `subcategory:${subcategory.id}`;
                this.appendChild(input);
            }
        });

        // Add all posts if post selection is "all"
        if (postSelection && postSelection.value === 'all') {
            postsData.forEach(post => {
                if (post.category_name === categoryName) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_items[]';
                    input.value = `post:${post.id}`;
                    this.appendChild(input);
                }
            });
        }
    }
});
</script>

<?php require_app_file('footer.php'); ?>
