<?php
/**
 * Search Page - Search categories, subcategories, posts, and content
 * Updated: 2025-11-05 (Enhanced search system with autocomplete and advanced search)
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';

$page_title = 'Search Results';

// Check if user is super user
$is_super_user = is_super_admin();
$current_user_id = $_SESSION['user_id'];

// Get search parameters
$search_query = trim($_GET['q'] ?? '');
$search_in = $_GET['search_in'] ?? 'all';
$date_range = $_GET['date_range'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'relevance';
$content_type = $_GET['content_type'] ?? 'all';
$author_id = $_GET['author_id'] ?? 'all';
$exact_match = isset($_GET['exact_match']);
$include_content = isset($_GET['include_content']) ? true : true; // Default to true for backward compatibility

$results = [];
$search_performed = false;

// Build date filter
$date_filter = '';
$date_filter_params = [];
switch ($date_range) {
    case 'today':
        $date_filter = 'AND DATE(created_at) = CURDATE()';
        break;
    case 'week':
        $date_filter = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        break;
    case 'month':
        $date_filter = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        break;
    case 'year':
        $date_filter = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
        break;
}

// Build author filter
$author_filter = '';
$author_filter_params = [];
if ($author_id !== 'all' && is_numeric($author_id)) {
    $author_filter = 'AND p.user_id = ?';
    $author_filter_params = [$author_id];
}

// Build content type filter
$content_type_filter = '';
switch ($content_type) {
    case 'posts':
        $content_type_filter = "AND type = 'post'";
        break;
    case 'replies':
        $content_type_filter = "AND type = 'reply'";
        break;
    case 'categories':
        $content_type_filter = "AND type = 'category'";
        break;
    case 'subcategories':
        $content_type_filter = "AND type = 'subcategory'";
        break;
}

// Build sort order
$sort_order = 'ORDER BY relevance DESC, created_at DESC';
switch ($sort_by) {
    case 'date_newest':
        $sort_order = 'ORDER BY created_at DESC';
        break;
    case 'date_oldest':
        $sort_order = 'ORDER BY created_at ASC';
        break;
    case 'title_alphabetical':
        $sort_order = 'ORDER BY title ASC';
        break;
}

// Modify search query for exact match if needed
$search_term = $search_query;
if ($exact_match) {
    $search_term = '"' . $search_query . '"';
}

if (!empty($search_query)) {
    $search_performed = true;

    try {
        // Enhanced Search System - Updated 2025-11-05
        // Check if we should search categories based on search_in parameter
        $search_categories = ($search_in === 'all' || $search_in === 'categories');

        // Search Categories (with visibility filtering)
        if ($is_super_user) {
            // Super Admins see all categories including it_only
            $category_search = $pdo->prepare("
                SELECT id, name, icon, 'category' as type, visibility,
                       MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM categories
                WHERE MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
            ");
            $category_search->execute([$search_query, $search_query]);
            $categories = $category_search->fetchAll();
        } elseif (is_admin()) {
            // Normal Admins see all categories except it_only
            $category_search = $pdo->prepare("
                SELECT id, name, icon, 'category' as type, visibility,
                       MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM categories
                WHERE visibility != 'it_only'
                AND MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
            ");
            $category_search->execute([$search_query, $search_query]);
            $categories = $category_search->fetchAll();
        } else {
            // Regular users only see accessible categories
            $category_search = $pdo->prepare("
                SELECT id, name, icon, 'category' as type, visibility,
                       MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM categories
                WHERE (visibility = 'public'
                       OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL)))
                AND MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
            ");
            $category_search->execute([$search_query, '%"' . $current_user_id . '"%', $search_query]);
            $categories = $category_search->fetchAll();
        }

        // Check if subcategory visibility columns exist
        $subcategory_visibility_columns_exist = false;
        try {
            $test_query = $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
            $subcategory_visibility_columns_exist = true;
        } catch (PDOException $e) {
            // Subcategory visibility columns don't exist yet
            $subcategory_visibility_columns_exist = false;
        }

        // Search Subcategories (with visibility filtering)
        if ($is_super_user) {
            // Super Admins can see all subcategories including it_only
            if ($subcategory_visibility_columns_exist) {
                $subcategory_search = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count, s.visibility,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    GROUP BY s.id
                    ORDER BY relevance DESC
                ");
            } else {
                $subcategory_search = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    GROUP BY s.id
                    ORDER BY relevance DESC
                ");
            }
            $subcategory_search->execute([$search_query, $search_query]);
            $subcategories = $subcategory_search->fetchAll();
        } elseif (is_admin()) {
            // Normal Admins can see all subcategories except it_only
            if ($subcategory_visibility_columns_exist) {
                $subcategory_search = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count, s.visibility,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE s.visibility != 'it_only' AND c.visibility != 'it_only'
                    AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    GROUP BY s.id
                    ORDER BY relevance DESC
                ");
            } else {
                $subcategory_search = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    GROUP BY s.id
                    ORDER BY relevance DESC
                ");
            }
            $subcategory_search->execute([$search_query, $search_query]);
            $subcategories = $subcategory_search->fetchAll();
        } else {
            // Regular users can only see accessible subcategories
            if ($subcategory_visibility_columns_exist) {
                $subcategory_search = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(DISTINCT p.id) as post_count, s.visibility,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (s.visibility = 'public'
                         OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                    AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    GROUP BY s.id
                    ORDER BY relevance DESC
                ");
                $subcategory_search->execute([$search_query, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%', $search_query]);
            } else {
                $subcategory_search = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    GROUP BY s.id
                    ORDER BY relevance DESC
                ");
                $subcategory_search->execute([$search_query, '%"' . $current_user_id . '"%', $search_query]);
            }
            $subcategories = $subcategory_search->fetchAll();
        }

        // Search Posts (with visibility filtering)
        if ($is_super_user) {
            // Super Admins can see all posts including it_only
            $post_search = $pdo->prepare("
                SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                       s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                       'post' as type,
                       (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                       MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) +
                       MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM posts p
                JOIN subcategories s ON p.subcategory_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) OR
                       MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                ORDER BY relevance DESC, p.created_at DESC
                LIMIT 50
            ");
            $post_search->execute([$search_query, $search_query, $search_query, $search_query, $search_query]);
            $posts = $post_search->fetchAll();
        } elseif (is_admin()) {
            // Normal Admins can see all posts except it_only
            $post_search = $pdo->prepare("
                SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                       s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                       'post' as type,
                       (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                       MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) +
                       MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM posts p
                JOIN subcategories s ON p.subcategory_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE p.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only'
                AND (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) OR
                     MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                ORDER BY relevance DESC, p.created_at DESC
                LIMIT 50
            ");
            $post_search->execute([$search_query, $search_query, $search_query, $search_query, $search_query]);
            $posts = $post_search->fetchAll();
        } else {
            // Regular users can only see accessible posts
            if ($subcategory_visibility_columns_exist) {
                $post_search = $pdo->prepare("
                    SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'post' as type,
                           (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                           MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) +
                           MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (s.visibility = 'public'
                         OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                    AND (p.privacy = 'public'
                         OR p.user_id = ?
                         OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                    AND (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) OR
                         MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                    ORDER BY relevance DESC, p.created_at DESC
                    LIMIT 50
                ");
                $post_search->execute([
                    $search_query, $search_query, $search_query,
                    '%"' . $current_user_id . '"%',
                    '%"' . $current_user_id . '"%',
                    $current_user_id,
                    '%"' . $current_user_id . '"%',
                    $search_query, $search_query
                ]);
            } else {
                $post_search = $pdo->prepare("
                    SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'post' as type,
                           (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                       MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) +
                       MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (p.privacy = 'public'
                         OR p.user_id = ?
                         OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                    AND (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) OR
                         MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                    ORDER BY relevance DESC, p.created_at DESC
                    LIMIT 50
                ");
                $post_search->execute([
                    $search_query, $search_query, $search_query,
                    '%"' . $current_user_id . '"%',
                    $current_user_id,
                    '%"' . $current_user_id . '"%',
                    $search_query, $search_query
                ]);
            }
            $posts = $post_search->fetchAll();
        }

        // Search Replies (with visibility filtering)
        if ($is_super_user) {
            // Super Admins can see all replies including it_only
            $reply_search = $pdo->prepare("
                SELECT r.id, r.post_id, r.user_id, r.created_at,
                       p.title as post_title, p.subcategory_id,
                       s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                       'reply' as type,
                       MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM replies r
                JOIN posts p ON r.post_id = p.id
                JOIN subcategories s ON p.subcategory_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC, r.created_at DESC
                LIMIT 50
            ");
            $reply_search->execute([$search_query, $search_query]);
            $replies = $reply_search->fetchAll();
        } elseif (is_admin()) {
            // Normal Admins can see all replies except it_only
            $reply_search = $pdo->prepare("
                SELECT r.id, r.post_id, r.user_id, r.created_at,
                       p.title as post_title, p.subcategory_id,
                       s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                       'reply' as type,
                       MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM replies r
                JOIN posts p ON r.post_id = p.id
                JOIN subcategories s ON p.subcategory_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE p.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only'
                AND MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC, r.created_at DESC
                LIMIT 50
            ");
            $reply_search->execute([$search_query, $search_query]);
            $replies = $reply_search->fetchAll();
        } else {
            // Regular users can only see accessible replies
            if ($subcategory_visibility_columns_exist) {
                $reply_search = $pdo->prepare("
                    SELECT r.id, r.post_id, r.user_id, r.created_at,
                           p.title as post_title, p.subcategory_id,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'reply' as type,
                           MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM replies r
                    JOIN posts p ON r.post_id = p.id
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (s.visibility = 'public'
                         OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                    AND (p.privacy = 'public'
                         OR p.user_id = ?
                         OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                    AND MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC, r.created_at DESC
                    LIMIT 50
                ");
                $reply_search->execute([
                    $search_query,
                    '%"' . $current_user_id . '"%',
                    '%"' . $current_user_id . '"%',
                    $current_user_id,
                    '%"' . $current_user_id . '"%',
                    $search_query
                ]);
            } else {
                $reply_search = $pdo->prepare("
                    SELECT r.id, r.post_id, r.user_id, r.created_at,
                           p.title as post_title, p.subcategory_id,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'reply' as type,
                           MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM replies r
                    JOIN posts p ON r.post_id = p.id
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (p.privacy = 'public'
                         OR p.user_id = ?
                         OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                    AND MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC, r.created_at DESC
                    LIMIT 50
                ");
                $reply_search->execute([
                    $search_query,
                    '%"' . $current_user_id . '"%',
                    $current_user_id,
                    '%"' . $current_user_id . '"%',
                    $search_query
                ]);
            }
            $replies = $reply_search->fetchAll();
        }

        // Combine all results and sort by relevance
        $all_results = array_merge($categories, $subcategories, $posts, $replies);

        // Sort by relevance (higher = more relevant)
        usort($all_results, function($a, $b) {
            $relevance_a = $a['relevance'] ?? 0;
            $relevance_b = $b['relevance'] ?? 0;

            if ($relevance_a == $relevance_b) {
                return 0;
            }
            return ($relevance_a > $relevance_b) ? -1 : 1;
        });

        $results = $all_results;

    } catch (PDOException $e) {
        error_log("Search Error: " . $e->getMessage());
        $error_message = "Search error occurred. Please try again.";
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="flex-between mb-20">
        <h2 style="font-size: 24px; color: #2d3748;">🔍 Search Results</h2>
        <a href="index.php" class="btn btn-secondary">← Back to Home</a>
    </div>

    <!-- Search Form -->
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="search.php" style="display: flex; gap: 10px;">
            <input
                type="text"
                name="q"
                class="form-input"
                value="<?php echo htmlspecialchars($search_query); ?>"
                placeholder="OLD OLD OLD PLZ SWITCH TO search_working.php"
                style="flex: 1; margin: 0;"
                autofocus
            >
            <button type="submit" class="btn btn-primary" style="margin: 0;">Search</button>
        </form>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($search_query)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <div class="empty-state-text">
                Enter a search term to find categories, posts, and content.
            </div>
        </div>
    <?php elseif ($search_performed && empty($results)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <div class="empty-state-text">
                No results found for "<?php echo htmlspecialchars($search_query); ?>".
                <br><br>
                <strong>Tips:</strong>
                <ul style="text-align: left; display: inline-block;">
                    <li>Try different keywords</li>
                    <li>Check spelling</li>
                    <li>Use broader terms</li>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <div class="search-results">
            <p style="margin-bottom: 20px; color: #666;">
                Found <?php echo count($results); ?> result<?php echo count($results) != 1 ? 's' : ''; ?>
                for "<?php echo htmlspecialchars($search_query); ?>"
            </p>

            <?php foreach ($results as $result): ?>
                <?php if ($result['type'] === 'category'): ?>
                    <div class="search-result-item" style="border-left: 4px solid #667eea; padding: 15px; margin-bottom: 15px; background: #f8f9fa; border-radius: 0 8px 8px 0;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <?php if ($result['icon']): ?>
                                <span style="font-size: 24px; margin-right: 10px;"><?php echo htmlspecialchars($result['icon']); ?></span>
                            <?php endif; ?>
                            <h3 style="margin: 0; color: #2d3748; font-size: 18px;">
                                <?php echo htmlspecialchars($result['name']); ?>
                                <span style="font-size: 12px; color: #666; margin-left: 10px;">Category</span>
                            </h3>
                        </div>
                        <div>
                            <a href="index.php" class="btn btn-primary btn-small">View Category</a>
                        </div>
                    </div>

                <?php elseif ($result['type'] === 'subcategory'): ?>
                    <div class="search-result-item" style="border-left: 4px solid #4299e1; padding: 15px; margin-bottom: 15px; background: #f8f9fa; border-radius: 0 8px 8px 0;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <?php if ($result['category_icon']): ?>
                                <span style="font-size: 20px; margin-right: 8px;"><?php echo htmlspecialchars($result['category_icon']); ?></span>
                            <?php endif; ?>
                            <h3 style="margin: 0; color: #2d3748; font-size: 16px;">
                                <?php echo htmlspecialchars($result['name']); ?>
                                <span style="font-size: 12px; color: #666; margin-left: 10px;">
                                    <?php echo $result['post_count']; ?> post(s)
                                </span>
                            </h3>
                        </div>
                        <div style="font-size: 14px; color: #666; margin-bottom: 10px;">
                            In: <?php echo htmlspecialchars($result['category_name']); ?>
                        </div>
                        <div>
                            <a href="subcategory.php?id=<?php echo $result['id']; ?>" class="btn btn-primary btn-small">View Subcategory</a>
                        </div>
                    </div>

                <?php elseif ($result['type'] === 'post'): ?>
                    <div class="search-result-item" style="border-left: 4px solid #48bb78; padding: 15px; margin-bottom: 15px; background: #f8f9fa; border-radius: 0 8px 8px 0;">
                        <h3 style="margin: 0 0 8px 0; color: #2d3748; font-size: 18px;">
                            <?php echo htmlspecialchars($result['title']); ?>
                            <span style="font-size: 12px; color: #666; margin-left: 10px;">Post</span>
                        </h3>
                        <div style="font-size: 14px; color: #666; margin-bottom: 10px;">
                            In: <?php echo htmlspecialchars($result['category_name']); ?> / <?php echo htmlspecialchars($result['subcategory_name']); ?>
                        </div>
                        <div style="font-size: 12px; color: #666; margin-bottom: 15px;">
                            Posted: <?php echo date('M j, Y \a\t g:i A', strtotime($result['created_at'])); ?>
                        </div>
                        <div>
                            <a href="post.php?id=<?php echo $result['id']; ?>" class="btn btn-primary btn-small">Read Post</a>
                        </div>
                    </div>

                <?php elseif ($result['type'] === 'reply'): ?>
                    <div class="search-result-item" style="border-left: 4px solid #ed8936; padding: 15px; margin-bottom: 15px; background: #f8f9fa; border-radius: 0 8px 8px 0;">
                        <h3 style="margin: 0 0 8px 0; color: #2d3748; font-size: 16px;">
                            Reply in: <?php echo htmlspecialchars($result['post_title']); ?>
                            <span style="font-size: 12px; color: #666; margin-left: 10px;">Reply</span>
                        </h3>
                        <div style="font-size: 14px; color: #666; margin-bottom: 10px;">
                            In: <?php echo htmlspecialchars($result['category_name']); ?> / <?php echo htmlspecialchars($result['subcategory_name']); ?>
                        </div>
                        <div style="font-size: 12px; color: #666; margin-bottom: 15px;">
                            Posted: <?php echo date('M j, Y \a\t g:i A', strtotime($result['created_at'])); ?>
                        </div>
                        <div>
                            <a href="post.php?id=<?php echo $result['post_id']; ?>#reply-<?php echo $result['id']; ?>" class="btn btn-primary btn-small">View Reply</a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.search-result-item {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.search-result-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.search-result-item h3 {
    line-height: 1.3;
}

.btn-small {
    font-size: 12px;
    padding: 6px 12px;
}
</style>

<?php include 'includes/footer.php'; ?>