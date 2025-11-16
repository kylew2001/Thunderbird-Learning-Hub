<?php
/**
 * Working Search Page - Uses LIKE search as fallback when FULLTEXT fails
 * Updated: 2025-11-05 (Fixed search with fallback to LIKE)
 */

$include_dirs = [
    __DIR__ . '/includes',
    dirname(__DIR__) . '/includes',
    dirname(__DIR__, 2) . '/includes'
];

function resolve_include_path(array $dirs, string $file): string {
    foreach ($dirs as $dir) {
        $candidate = rtrim($dir, '/\\') . '/' . $file;
        if (file_exists($candidate)) {
            return $candidate;
        }
    }
    return '';
}

$auth_check = resolve_include_path($include_dirs, 'auth_check.php');
$db_connect = resolve_include_path($include_dirs, 'db_connect.php');
$user_helpers = resolve_include_path($include_dirs, 'user_helpers.php');

foreach ([
    'auth_check.php' => $auth_check,
    'db_connect.php' => $db_connect,
    'user_helpers.php' => $user_helpers,
] as $name => $path) {
    if (empty($path)) {
        http_response_code(500);
        echo "Critical include missing: {$name}";
        exit;
    }
    require_once $path;
}

$page_title = 'Search Results';

// Check user permissions
$is_super_user = is_super_admin();
$is_admin = is_admin();
$current_user_id = $_SESSION['user_id'];

$search_query = trim($_GET['q'] ?? '');
$results = [];
$search_performed = false;

if (!empty($search_query)) {
    $search_performed = true;

    try {
        // Check if visibility columns exist
        $visibility_columns_exist = false;
        $subcategory_visibility_columns_exist = false;
        try {
            $pdo->query("SELECT visibility FROM categories LIMIT 1");
            $visibility_columns_exist = true;
        } catch (PDOException $e) {
            $visibility_columns_exist = false;
        }
        try {
            $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
            $subcategory_visibility_columns_exist = true;
        } catch (PDOException $e) {
            $subcategory_visibility_columns_exist = false;
        }

        // Search Categories - try FULLTEXT first, fallback to LIKE
        $categories = [];
        try {
            if ($is_super_user) {
                $stmt = $pdo->prepare("
                    SELECT id, name, icon, 'category' as type, visibility,
                           MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM categories
                    WHERE MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC
                ");
                $stmt->execute([$search_query, $search_query]);
                $categories = $stmt->fetchAll();
            } elseif ($is_admin) {
                $stmt = $pdo->prepare("
                    SELECT id, name, icon, 'category' as type, visibility,
                           MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM categories
                    WHERE visibility != 'it_only'
                    AND MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC
                ");
                $stmt->execute([$search_query, $search_query]);
                $categories = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, name, icon, 'category' as type, visibility,
                           MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM categories
                    WHERE (visibility = 'public'
                           OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL)))
                    AND MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC
                ");
                $stmt->execute([$search_query, '%"' . $current_user_id . '"%', $search_query]);
                $categories = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            // Fallback to LIKE search
            error_log("FULLTEXT category search failed, using LIKE fallback: " . $e->getMessage());

            if ($is_super_user) {
                $stmt = $pdo->prepare("
                    SELECT id, name, icon, 'category' as type, visibility, 1 as relevance
                    FROM categories
                    WHERE name LIKE ?
                    ORDER BY name
                ");
                $stmt->execute(['%' . $search_query . '%']);
                $categories = $stmt->fetchAll();
            } elseif ($is_admin) {
                $stmt = $pdo->prepare("
                    SELECT id, name, icon, 'category' as type, visibility, 1 as relevance
                    FROM categories
                    WHERE visibility != 'it_only' AND name LIKE ?
                    ORDER BY name
                ");
                $stmt->execute(['%' . $search_query . '%']);
                $categories = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, name, icon, 'category' as type, visibility, 1 as relevance
                    FROM categories
                    WHERE (visibility = 'public'
                           OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL)))
                    AND name LIKE ?
                    ORDER BY name
                ");
                $stmt->execute(['%' . $current_user_id . '"%', '%' . $search_query . '%']);
                $categories = $stmt->fetchAll();
            }
        }

        // Search Subcategories - try FULLTEXT first, fallback to LIKE
        $subcategories = [];
        try {
            if ($is_super_user) {
                $stmt = $pdo->prepare("
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
                $stmt->execute([$search_query, $search_query]);
                $subcategories = $stmt->fetchAll();
            } elseif ($is_admin) {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE c.visibility != 'it_only'
                    AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    GROUP BY s.id
                    ORDER BY relevance DESC
                ");
                $stmt->execute([$search_query, $search_query]);
                $subcategories = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count,
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
                $stmt->execute([$search_query, '%"' . $current_user_id . '"%', $search_query]);
                $subcategories = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            // Fallback to LIKE search
            error_log("FULLTEXT subcategory search failed, using LIKE fallback: " . $e->getMessage());

            if ($is_super_user) {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count, 1 as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE s.name LIKE ?
                    GROUP BY s.id
                    ORDER BY s.name
                ");
                $stmt->execute(['%' . $search_query . '%']);
                $subcategories = $stmt->fetchAll();
            } elseif ($is_admin) {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count, 1 as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE c.visibility != 'it_only' AND s.name LIKE ?
                    GROUP BY s.id
                    ORDER BY s.name
                ");
                $stmt->execute(['%' . $search_query . '%']);
                $subcategories = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                           'subcategory' as type, COUNT(p.id) as post_count, 1 as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    LEFT JOIN posts p ON s.id = p.subcategory_id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND s.name LIKE ?
                    GROUP BY s.id
                    ORDER BY s.name
                ");
                $stmt->execute(['%' . $current_user_id . '"%', '%' . $search_query . '%']);
                $subcategories = $stmt->fetchAll();
            }
        }

        // Search Posts - try FULLTEXT first, fallback to LIKE
        $posts = [];
        try {
            if ($is_super_user) {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'post' as type,
                           (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                           MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) OR
                           MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                    ORDER BY relevance DESC, p.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([$search_query, $search_query, $search_query, $search_query]);
                $posts = $stmt->fetchAll();
            } elseif ($is_admin) {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'post' as type,
                           (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                           MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE c.visibility != 'it_only'
                    AND (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) OR
                         MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                    ORDER BY relevance DESC, p.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([$search_query, $search_query, $search_query, $search_query]);
                $posts = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'post' as type,
                           (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                           MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (p.privacy = 'public' OR p.user_id = ?)
                    AND (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) OR
                         MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                    ORDER BY relevance DESC, p.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([
                    $search_query, $search_query,
                    '%"' . $current_user_id . '"%',
                    $current_user_id,
                    $search_query, $search_query
                ]);
                $posts = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            // Fallback to LIKE search
            error_log("FULLTEXT post search failed, using LIKE fallback: " . $e->getMessage());

            if ($is_super_user) {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'post' as type, 1 as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE p.title LIKE ? OR p.content LIKE ?
                    ORDER BY p.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute(['%' . $search_query . '%', '%' . $search_query . '%']);
                $posts = $stmt->fetchAll();
            } elseif ($is_admin) {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'post' as type, 1 as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE c.visibility != 'it_only'
                    AND (p.title LIKE ? OR p.content LIKE ?)
                    ORDER BY p.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute(['%' . $search_query . '%', '%' . $search_query . '%']);
                $posts = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                           s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                           'post' as type, 1 as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (p.privacy = 'public' OR p.user_id = ?)
                    AND (p.title LIKE ? OR p.content LIKE ?)
                    ORDER BY p.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([
                    '%' . $current_user_id . '"%',
                    $current_user_id,
                    '%' . $search_query . '%',
                    '%' . $search_query . '%'
                ]);
                $posts = $stmt->fetchAll();
            }
        }

        // Combine all results and sort by relevance
        $all_results = array_merge($categories, $subcategories, $posts);

        // Sort by relevance (higher = more relevant), fallback to created_at for posts
        usort($all_results, function($a, $b) {
            $relevance_a = $a['relevance'] ?? 0;
            $relevance_b = $b['relevance'] ?? 0;

            if ($relevance_a == $relevance_b) {
                // If same relevance, posts with more recent creation date first
                $created_a = $a['created_at'] ?? '';
                $created_b = $b['created_at'] ?? '';
                if ($created_a && $created_b) {
                    return strtotime($created_b) <=> strtotime($created_a);
                }
            }
            return ($relevance_a > $relevance_b) ? -1 : 1;
        });

        $results = $all_results;

    } catch (PDOException $e) {
        error_log("Search Error: " . $e->getMessage());
        $error_message = "Search error occurred. Please try again.";
    }
}

include resolve_include_path($include_dirs, 'header.php');
?>

<div class="container">
    <div class="flex-between mb-20">
        <h2 style="font-size: 24px; color: #2d3748;">🔍 Search Results</h2>
        <a href="index.php" class="btn btn-secondary">← Back to Home</a>
    </div>

    <!-- Search Form -->
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="search_working.php">
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <input
                    type="text"
                    name="q"
                    class="form-input"
                    value="<?php echo htmlspecialchars($search_query); ?>"
                    placeholder="Search categories, posts, and content..."
                    style="flex: 1; margin: 0;"
                    autofocus
                >
                <button type="submit" class="btn btn-primary" style="margin: 0;">Search</button>
            </div>
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

<?php include resolve_include_path($include_dirs, 'footer.php'); ?>