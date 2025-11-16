<?php
/**
 * Home Page - Categories and Subcategories Display
 * Fixed version without PHP references to eliminate duplication issues
 * Last updated: 2025-11-03 (Subcategory visibility controls added)
 */

require_once __DIR__ . '/system/config.php';
require_once APP_INCLUDES . '/auth_check.php';
require_once APP_INCLUDES . '/db_connect.php';
require_once APP_INCLUDES . '/user_helpers.php';
require_once APP_INCLUDES . '/search_widget.php';

// Load training helpers if available
if (file_exists(APP_INCLUDES . '/training_helpers.php')) {
    require_once APP_INCLUDES . '/training_helpers.php';
}

// Fallback functions for training permissions
if (!function_exists('can_create_categories')) {
    function can_create_categories() {
        return is_admin() || is_super_admin();
    }
}
if (!function_exists('can_create_subcategories')) {
    function can_create_subcategories() {
        return is_admin() || is_super_admin();
    }
}

$page_title = 'Home';

try {
    // Check if user is super user
   $is_super_user = is_super_admin();
$current_user_id = $_SESSION['user_id'];

// Determine if current user is training without altering layout
$is_training = function_exists('is_training_user')
    ? is_training_user()
    : (isset($_SESSION['user_role']) && strtolower(trim($_SESSION['user_role'])) === 'training');

    // Check if visibility columns exist in database
    $visibility_columns_exist = false;
    $subcategory_visibility_columns_exist = false;
    $pinned_categories_table_exists = false;
    try {
        $test_query = $pdo->query("SELECT visibility FROM categories LIMIT 1");
        $visibility_columns_exist = true;
    } catch (PDOException $e) {
        // Visibility columns don't exist yet
        $visibility_columns_exist = false;
    }
    try {
        $test_query = $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
        $subcategory_visibility_columns_exist = true;
    } catch (PDOException $e) {
        // Subcategory visibility columns don't exist yet
        $subcategory_visibility_columns_exist = false;
    }
    try {
        $test_query = $pdo->query("SHOW TABLES LIKE 'user_pinned_categories'");
        $pinned_categories_table_exists = $test_query->rowCount() > 0;
    } catch (PDOException $e) {
        // Pinned categories table doesn't exist yet
        $pinned_categories_table_exists = false;
    }

    // Fetch pinned category IDs for current user
    $pinned_category_ids = [];
    try {
        // Try to fetch pinned categories (table will be created on first pin action if doesn't exist)
        $stmt = $pdo->prepare("SELECT category_id FROM user_pinned_categories WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$current_user_id]);
        $pinned_results = $stmt->fetchAll();
        foreach ($pinned_results as $row) {
            $pinned_category_ids[] = $row['category_id'];
        }
        $pinned_categories_table_exists = true;
    } catch (PDOException $e) {
        // Table doesn't exist yet, that's okay - it will be created on first pin
        $pinned_categories_table_exists = true; // Always show the button
        $pinned_category_ids = [];
    }

    if ($is_training) {
    $pinned_category_ids = [];
    $pinned_categories_table_exists = false;
    }
    
    if ($is_super_user) {
        // Super Admins can see everything including it_only categories
        if ($visibility_columns_exist) {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note FROM categories ORDER BY name ASC";
        } else {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
        }
        $categories_stmt = $pdo->query($categories_query);
        $raw_categories = $categories_stmt->fetchAll();
    } elseif (is_admin()) {
        // Normal Admins can see everything except it_only categories
        if ($visibility_columns_exist) {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note FROM categories WHERE visibility != 'it_only' ORDER BY name ASC";
        } else {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
        }
        $categories_stmt = $pdo->query($categories_query);
        $raw_categories = $categories_stmt->fetchAll();
    } else {
        // Regular users can only see public categories or restricted categories they have access to
        // They cannot see 'it_only' or 'hidden' categories
        if ($visibility_columns_exist) {
            $categories_query = "
                SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note
                FROM categories
                WHERE visibility = 'public'
                OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL))
                ORDER BY name ASC
            ";
            $categories_stmt = $pdo->prepare($categories_query);
            $categories_stmt->execute(['%"' . $current_user_id . '"%']);
        } else {
            // Old database - show all categories (assume all are public)
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
            $categories_stmt = $pdo->query($categories_query);
        }
        $raw_categories = $categories_stmt->fetchAll();
    }

    // Build categories array without using references
    $categories = [];
    $seen_ids = [];

    foreach ($raw_categories as $category_row) {
        $category_id = $category_row['id'];

        // Skip if we've already seen this ID
        if (in_array($category_id, $seen_ids)) {
            error_log("SKIPPING DUPLICATE CATEGORY ID: $category_id");
            continue;
        }

        // Add to categories array
        $categories[] = [
            'id' => $category_id,
            'name' => $category_row['name'],
            'icon' => $category_row['icon'],
            'creator_id' => $category_row['category_creator_id'],
            'visibility' => $category_row['visibility'] ?? 'public',
            'allowed_users' => $category_row['allowed_users'] ?? null,
            'visibility_note' => $category_row['visibility_note'] ?? null,
            'subcategories' => []
        ];
        $seen_ids[] = $category_id;
    }

    // Get subcategories without using references
    $final_categories = [];
    foreach ($categories as $index => $category) {
        $category_id = $category['id'];

        // Get subcategories with visibility filtering
        if ($is_super_user) {
            // Super Admins can see all subcategories including it_only
            if ($subcategory_visibility_columns_exist) {
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count,
                           s.visibility, s.allowed_users, s.visibility_note
                    FROM subcategories s
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE s.category_id = ?
                    GROUP BY s.id
                    ORDER BY
                        CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                        s.name ASC
                ";
                $subcategories_stmt = $pdo->prepare($subcategories_query);
                $subcategories_stmt->execute([$category_id]);
                $subcategories = $subcategories_stmt->fetchAll();
            } else {
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count
                    FROM subcategories s
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE s.category_id = ?
                    GROUP BY s.id
                    ORDER BY
                        CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                        s.name ASC
                ";
                $subcategories_stmt = $pdo->prepare($subcategories_query);
                $subcategories_stmt->execute([$category_id]);
                $subcategories = $subcategories_stmt->fetchAll();
            }
        } elseif (is_admin()) {
            // Normal Admins can see all subcategories except it_only
            if ($subcategory_visibility_columns_exist) {
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count,
                           s.visibility, s.allowed_users, s.visibility_note
                    FROM subcategories s
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE s.category_id = ? AND s.visibility != 'it_only'
                    GROUP BY s.id
                    ORDER BY
                        CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                        s.name ASC
                ";
                $subcategories_stmt = $pdo->prepare($subcategories_query);
                $subcategories_stmt->execute([$category_id]);
                $subcategories = $subcategories_stmt->fetchAll();
            } else {
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count
                    FROM subcategories s
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE s.category_id = ?
                    GROUP BY s.id
                    ORDER BY
                        CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                        s.name ASC
                ";
                $subcategories_stmt = $pdo->prepare($subcategories_query);
                $subcategories_stmt->execute([$category_id]);
                $subcategories = $subcategories_stmt->fetchAll();
            }
        } else {
            // Regular users can only see accessible subcategories
            // They cannot see 'it_only' or 'hidden' subcategories
            if ($subcategory_visibility_columns_exist && $visibility_columns_exist) {
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(DISTINCT p.id) AS post_count,
                           s.visibility, s.allowed_users, s.visibility_note
                    FROM subcategories s
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    JOIN categories c ON s.category_id = c.id
                    WHERE s.category_id = ?
                    AND (c.visibility = 'public'
                         OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (s.visibility = 'public'
                         OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                    GROUP BY s.id
                    ORDER BY
                        CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                        s.name ASC
                ";
                $subcategories_stmt = $pdo->prepare($subcategories_query);
                $subcategories_stmt->execute([$category_id, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%']);
                $subcategories = $subcategories_stmt->fetchAll();
            } else {
                // Old database - show all subcategories
                $subcategories_query = "
                    SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count
                    FROM subcategories s
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE s.category_id = ?
                    GROUP BY s.id
                    ORDER BY
                        CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END,
                        s.name ASC
                ";
                $subcategories_stmt = $pdo->prepare($subcategories_query);
                $subcategories_stmt->execute([$category_id]);
                $subcategories = $subcategories_stmt->fetchAll();
            }
        }

        // Create completely new category object
        $final_categories[] = [
            'id' => $category['id'],
            'name' => $category['name'],
            'icon' => $category['icon'],
            'creator_id' => $category['creator_id'],
            'visibility' => $category['visibility'] ?? 'public',
            'allowed_users' => $category['allowed_users'] ?? null,
            'visibility_note' => $category['visibility_note'] ?? null,
            'subcategories' => $subcategories
        ];
    }

    $categories = $final_categories;

// --- BEGIN REPLACEMENT ---
// TRAINING CONTENT FILTERING (only show subcategories that contain assigned posts)
if (function_exists('is_training_user') && is_training_user()) {
    try {
        // Build (category_id, subcategory_id) pairs that actually have assigned POSTS for this user
        $pairStmt = $pdo->prepare("
            SELECT DISTINCT
                c.id  AS category_id,
                s.id  AS subcategory_id
            FROM user_training_assignments uta
            JOIN training_courses tc
              ON uta.course_id = tc.id
             AND tc.is_active = 1
            JOIN training_course_content tcc
              ON uta.course_id = tcc.course_id
            JOIN posts p
              ON tcc.content_type = 'post'
             AND p.id = tcc.content_id
            JOIN subcategories s
              ON p.subcategory_id = s.id
            JOIN categories c
              ON s.category_id = c.id
            WHERE uta.user_id = ?
        ");
        $pairStmt->execute([$current_user_id]);
        $pairs = $pairStmt->fetchAll(PDO::FETCH_ASSOC);

        // Nothing assigned via posts → show nothing
        if (empty($pairs)) {
            $categories = [];
        } else {
            // Distinct categories that actually have assigned posts
            $assignedCategoryIds = array_values(array_unique(array_map(
                fn($r) => (int)$r['category_id'], $pairs
            )));

            // Map of category_id => set(subcategory_id) that have assigned posts
            $assignedSubsByCat = [];
            foreach ($pairs as $r) {
                $cid = (int)$r['category_id'];
                $sid = (int)$r['subcategory_id'];
                $assignedSubsByCat[$cid] = $assignedSubsByCat[$cid] ?? [];
                $assignedSubsByCat[$cid][$sid] = true;
            }

            // Keep only categories with assigned posts
            $categories = array_values(array_filter($categories, function ($cat) use ($assignedCategoryIds) {
                return in_array((int)$cat['id'], $assignedCategoryIds, true);
            }));

            // Within each kept category, keep only subcategories that have assigned posts
            foreach ($categories as &$catRef) {
                $cid = (int)$catRef['id'];
                $allowed = $assignedSubsByCat[$cid] ?? [];

                if (!empty($catRef['subcategories'])) {
                    $catRef['subcategories'] = array_values(array_filter(
                        $catRef['subcategories'],
                        function ($sc) use ($allowed) {
                            return isset($allowed[(int)$sc['id']]);
                        }
                    ));
                }
            }
            unset($catRef); // break reference
        }
    } catch (PDOException $e) {
        error_log("Training filter (posts-only) error: " . $e->getMessage());
        $categories = [];
    }
}
// --- END REPLACEMENT ---




    // Training users: sort alphabetically only (no pin priority). Others: keep pin-first logic.
if ($is_training) {
    uasort($categories, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $categories = array_values($categories);
} else {
    // Sort categories: pinned first (by pin order), then alphabetically
    uasort($categories, function($a, $b) use ($pinned_category_ids) {
        $a_pinned = in_array($a['id'], $pinned_category_ids);
        $b_pinned = in_array($b['id'], $pinned_category_ids);

        if ($a_pinned !== $b_pinned) {
            return $b_pinned <=> $a_pinned;
        }
        if ($a_pinned && $b_pinned) {
            $a_pin_index = array_search($a['id'], $pinned_category_ids);
            $b_pin_index = array_search($b['id'], $pinned_category_ids);
            return $a_pin_index <=> $b_pin_index;
        }
        return strcasecmp($a['name'], $b['name']);
    });
    $categories = array_values($categories);
}

    // Reset array keys to sequential indices
    $categories = array_values($categories);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
    $categories = [];
}

include APP_INCLUDES . '/header.php';
?>

<div class="container">
    <?php
        // One-liner search bar (same behavior everywhere).
        // Uses search_autocomplete.php and submits to search_working.php
        render_search_bar('/search/search_working.php');
    ?>

    <div class="flex-between mb-20">
        <h2 style="font-size: 24px; color: #2d3748;">Knowledge Categories</h2>
        <?php if (can_create_categories()): ?>
            <a href="/categories/add_category.php" class="btn btn-success">+ Add Category</a>
        <?php endif; ?>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-message">
            <?php
            $success_messages = [
                'category_added' => 'Category added successfully!',
                'category_updated' => 'Category updated successfully!',
                'category_deleted' => 'Category deleted successfully!',
                'subcategory_added' => 'Subcategory added successfully!',
                'subcategory_updated' => 'Subcategory updated successfully!',
                'subcategory_deleted' => 'Subcategory deleted successfully!'
            ];
            $success_key = $_GET['success'];
            echo isset($success_messages[$success_key]) ? $success_messages[$success_key] : 'Action completed successfully!';
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔒</div>
            <div class="empty-state-text">
                <?php
                // Check if there are any categories in the system at all
                $total_categories_stmt = $pdo->query("SELECT COUNT(*) FROM categories");
                $total_categories = $total_categories_stmt->fetchColumn();

                if ($total_categories > 0) {
                    echo "No accessible categories found. You don't have permission to view any posts in the available categories.";
                } else {
                    echo "No categories yet. Click \"Add Category\" to get started.";
                }
                ?>
            </div>
        </div>
    <?php else: ?>
        <div class="category-list">
            <?php
            $first_unpinned = true;
            foreach ($categories as $category):
                $is_pinned = $pinned_categories_table_exists && in_array($category['id'], $pinned_category_ids);

                // Add a "Pinned Categories" divider before first unpinned category
                if (!$is_pinned && $first_unpinned && count($pinned_category_ids) > 0):
                    $first_unpinned = false;
                    echo '<div style="margin: 20px 0 10px 0; padding: 10px 0; border-top: 2px solid #e2e8f0; text-align: center; color: #a0aec0; font-size: 12px; font-weight: 500; text-transform: uppercase;">Other Categories</div>';
                endif;
                if ($is_pinned):
                    $first_unpinned = false;
                endif;
            ?>
                <div class="category-item" <?php echo $is_pinned ? 'style="border-left: 4px solid #fbbf24; background: rgba(251, 191, 36, 0.02);"' : ''; ?>>
                    <div class="category-header">
                        <div class="category-name">
                            <?php if ($category['icon']): ?>
                                <span><?php echo htmlspecialchars($category['icon']); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                            <?php if (($is_super_user || is_admin()) && $visibility_columns_exist): ?>
                                <?php
                                $visibility_colors = [
                                    'public' => '#48bb78',
                                    'hidden' => '#f56565',
                                    'restricted' => '#ed8936',
                                    'it_only' => '#dc3545'
                                ];
                                $visibility_labels = [
                                    'public' => '🌐 Public',
                                    'hidden' => '🚫 Hidden',
                                    'restricted' => '👥 Restricted',
                                    'it_only' => '🔒 IT Only'
                                ];
                                $cat_visibility = $category['visibility'] ?? 'public';
                                ?>
                                <span style="color: <?php echo $visibility_colors[$cat_visibility] ?? '#666'; ?>; font-size: 11px; margin-left: 8px; padding: 2px 6px; background: rgba(0,0,0,0.1); border-radius: 3px;">
                                    <?php echo $visibility_labels[$cat_visibility] ?? 'Unknown'; ?>
                                </span>
                                <?php if (!empty($category['visibility_note'])): ?>
                                    <span style="color: #666; font-size: 10px; margin-left: 4px;" title="<?php echo htmlspecialchars($category['visibility_note']); ?>">📝</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="card-actions">
    <?php if (!$is_training && $pinned_categories_table_exists): ?>
        <button class="btn btn-small" style="background: <?php echo $is_pinned ? '#fbbf24' : '#e2e8f0'; ?>; color: <?php echo $is_pinned ? 'black' : '#4a5568'; ?>; border: none; cursor: pointer;" onclick="togglePinCategory(<?php echo $category['id']; ?>, this)" title="<?php echo $is_pinned ? 'Unpin category' : 'Pin category'; ?>">
            <?php echo $is_pinned ? '📌' : '📍'; ?> <?php echo $is_pinned ? 'Unpin' : 'Pin'; ?>
        </button>
    <?php endif; ?>

    <?php if (can_create_subcategories()): ?>
        <a href="/categories/add_subcategory.php?category_id=<?php echo $category['id']; ?>" class="btn btn-primary btn-small">+ Add Subcategory</a>
    <?php endif; ?>

    <?php if ($is_super_user || is_admin()): ?>
        <a href="/categories/edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-warning btn-small">Edit</a>
        <a href="/categories/delete_category.php?id=<?php echo $category['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure? This will delete the category and ALL subcategories, posts, and replies under it. This cannot be undone.');">Delete</a>
    <?php elseif (!$is_training): ?>
        <a href="/categories/request_edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-warning btn-small">Request Edit</a>
    <?php endif; ?>
</div>
                    </div>

                    <?php if (empty($category['subcategories'])): ?>
                        <div style="color: #a0aec0; font-style: italic; font-size: 14px;">
                            <?php if (can_create_subcategories()): ?>
                                No subcategories yet. Click "Add Subcategory" to create one.
                            <?php else: ?>
                                No subcategories yet.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="subcategory-list">
                            <?php foreach ($category['subcategories'] as $subcategory): ?>
                                <div class="subcategory-item">
                                    <a href="/categories/subcategory.php?id=<?php echo $subcategory['id']; ?>" class="subcategory-name">
                                        <?php echo htmlspecialchars($subcategory['name']); ?>
                                        <?php if (($is_super_user || is_admin()) && $subcategory_visibility_columns_exist): ?>
                                            <?php
                                            $visibility_colors = [
                                                'public' => '#48bb78',
                                                'hidden' => '#f56565',
                                                'restricted' => '#ed8936',
                                                'it_only' => '#dc3545'
                                            ];
                                            $visibility_labels = [
                                                'public' => '🌐',
                                                'hidden' => '🚫',
                                                'restricted' => '👥',
                                                'it_only' => '🔒'
                                            ];
                                            $subcat_visibility = $subcategory['visibility'] ?? 'public';
                                            ?>
                                            <span style="color: <?php echo $visibility_colors[$subcat_visibility] ?? '#666'; ?>; font-size: 10px; margin-left: 6px;" title="<?php echo ucfirst($subcat_visibility); ?> subcategory">
                                                <?php echo $visibility_labels[$subcat_visibility] ?? '?'; ?>
                                            </span>
                                            <?php if (!empty($subcategory['visibility_note'])): ?>
                                                <span style="color: #666; font-size: 9px; margin-left: 2px;" title="<?php echo htmlspecialchars($subcategory['visibility_note']); ?>">📝</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </a>
                                    <span class="post-count"><?php echo $subcategory['post_count']; ?> post(s)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Keep only the pin toggle here; the widget injects its own search JS/CSS once.
function togglePinCategory(categoryId, buttonElement) {
    const isPinned = buttonElement.textContent.includes('Unpin');
    const action = isPinned ? 'unpin' : 'pin';

    const originalText = buttonElement.textContent;
    buttonElement.textContent = '⏳ Loading...';
    buttonElement.disabled = true;

    fetch('/categories/toggle_pin_category.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'category_id=' + encodeURIComponent(categoryId) + '&action=' + encodeURIComponent(action)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to toggle pin'));
            buttonElement.textContent = originalText;
            buttonElement.disabled = false;
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error: Failed to toggle pin');
        buttonElement.textContent = originalText;
        buttonElement.disabled = false;
    });
}
</script>

<?php include APP_INCLUDES . '/footer.php'; ?>