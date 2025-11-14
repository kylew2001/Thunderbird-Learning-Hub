<?php
/**
 * Search Autocomplete API
 * Returns top 3 search results for autocomplete dropdown
 * Updated: 2025-11-05 (Enhanced search system with autocomplete)
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

header('Content-Type: application/json');

// Get search query
$query = trim($_GET['q'] ?? '');
$results = [];

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    // Check user permissions
    $is_super_user = is_super_admin();
    $is_admin = is_admin();
    $current_user_id = $_SESSION['user_id'];

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

    // Search Categories (title search only for autocomplete)
    $category_results = [];

    // Try FULLTEXT search first, fallback to LIKE if it fails
    try {
        if ($is_super_user) {
            $stmt = $pdo->prepare("
                SELECT id, name, icon, 'category' as type,
                       MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM categories
                WHERE MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
                LIMIT 3
            ");
            $stmt->execute([$query, $query]);
            $category_results = $stmt->fetchAll();
        } elseif ($is_admin) {
            $stmt = $pdo->prepare("
                SELECT id, name, icon, 'category' as type,
                       MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM categories
                WHERE visibility != 'it_only'
                AND MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
                LIMIT 3
            ");
            $stmt->execute([$query, $query]);
            $category_results = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("
                SELECT id, name, icon, 'category' as type,
                       MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM categories
                WHERE (visibility = 'public'
                       OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL)))
                AND MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
                LIMIT 3
            ");
            $stmt->execute([$query, '%"' . $current_user_id . '"%', $query]);
            $category_results = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Fallback to LIKE search if FULLTEXT fails
        error_log("FULLTEXT search failed, using LIKE fallback: " . $e->getMessage());

        if ($is_super_user) {
            $stmt = $pdo->prepare("
                SELECT id, name, icon, 'category' as type, 1 as relevance
                FROM categories
                WHERE name LIKE ?
                ORDER BY name
                LIMIT 3
            ");
            $stmt->execute(['%' . $query . '%']);
            $category_results = $stmt->fetchAll();
        } elseif ($is_admin) {
            $stmt = $pdo->prepare("
                SELECT id, name, icon, 'category' as type, 1 as relevance
                FROM categories
                WHERE visibility != 'it_only' AND name LIKE ?
                ORDER BY name
                LIMIT 3
            ");
            $stmt->execute(['%' . $query . '%']);
            $category_results = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("
                SELECT id, name, icon, 'category' as type, 1 as relevance
                FROM categories
                WHERE (visibility = 'public'
                       OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL)))
                AND name LIKE ?
                ORDER BY name
                LIMIT 3
            ");
            $stmt->execute(['%' . $current_user_id . '"%', '%' . $query . '%']);
            $category_results = $stmt->fetchAll();
        }
    }

    // Search Subcategories
    $subcategory_results = [];

    // Try FULLTEXT search first, fallback to LIKE if it fails
    try {
        if ($is_super_user) {
            $stmt = $pdo->prepare("
                SELECT s.id, s.name, c.name as category_name, c.icon, 'subcategory' as type,
                       MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM subcategories s
                JOIN categories c ON s.category_id = c.id
                WHERE MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
                LIMIT 3
            ");
            $stmt->execute([$query, $query]);
            $subcategory_results = $stmt->fetchAll();
        } elseif ($is_admin) {
            $stmt = $pdo->prepare("
                SELECT s.id, s.name, c.name as category_name, c.icon, 'subcategory' as type,
                       MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM subcategories s
                JOIN categories c ON s.category_id = c.id
                WHERE s.visibility != 'it_only' AND c.visibility != 'it_only'
                AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
                LIMIT 3
            ");
            $stmt->execute([$query, $query]);
            $subcategory_results = $stmt->fetchAll();
        } else {
            if ($subcategory_visibility_columns_exist) {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, c.name as category_name, c.icon, 'subcategory' as type,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (s.visibility = 'public'
                         OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                    AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC
                    LIMIT 3
                ");
                $stmt->execute([$query, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%', $query]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, c.name as category_name, c.icon, 'subcategory' as type,
                           MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC
                    LIMIT 3
                ");
                $stmt->execute([$query, '%"' . $current_user_id . '"%', $query]);
            }
            $subcategory_results = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Fallback to LIKE search if FULLTEXT fails
        error_log("FULLTEXT subcategory search failed, using LIKE fallback: " . $e->getMessage());

        if ($is_super_user) {
            $stmt = $pdo->prepare("
                SELECT s.id, s.name, c.name as category_name, c.icon, 'subcategory' as type, 1 as relevance
                FROM subcategories s
                JOIN categories c ON s.category_id = c.id
                WHERE s.name LIKE ?
                ORDER BY s.name
                LIMIT 3
            ");
            $stmt->execute(['%' . $query . '%']);
            $subcategory_results = $stmt->fetchAll();
        } elseif ($is_admin) {
            $stmt = $pdo->prepare("
                SELECT s.id, s.name, c.name as category_name, c.icon, 'subcategory' as type, 1 as relevance
                FROM subcategories s
                JOIN categories c ON s.category_id = c.id
                WHERE s.visibility != 'it_only' AND c.visibility != 'it_only' AND s.name LIKE ?
                ORDER BY s.name
                LIMIT 3
            ");
            $stmt->execute(['%' . $query . '%']);
            $subcategory_results = $stmt->fetchAll();
        } else {
            if ($subcategory_visibility_columns_exist) {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, c.name as category_name, c.icon, 'subcategory' as type, 1 as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (s.visibility = 'public'
                         OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                    AND s.name LIKE ?
                    ORDER BY s.name
                    LIMIT 3
                ");
                $stmt->execute(['%' . $current_user_id . '"%', '%' . $current_user_id . '"%', '%' . $query . '%']);
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name, c.name as category_name, c.icon, 'subcategory' as type, 1 as relevance
                    FROM subcategories s
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND s.name LIKE ?
                    ORDER BY s.name
                    LIMIT 3
                ");
                $stmt->execute(['%' . $current_user_id . '"%', '%' . $query . '%']);
            }
            $subcategory_results = $stmt->fetchAll();
        }
    }

    // Search Posts (title only for autocomplete to keep it fast)
    $post_results = [];

    // Try FULLTEXT search first, fallback to LIKE if it fails
    try {
        if ($is_super_user) {
            $stmt = $pdo->prepare("
                SELECT p.id, p.title, s.name as subcategory_name, c.name as category_name, c.icon, 'post' as type,
                       MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM posts p
                JOIN subcategories s ON p.subcategory_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
                LIMIT 3
            ");
            $stmt->execute([$query, $query]);
            $post_results = $stmt->fetchAll();
        } elseif ($is_admin) {
            $stmt = $pdo->prepare("
                SELECT p.id, p.title, s.name as subcategory_name, c.name as category_name, c.icon, 'post' as type,
                       MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM posts p
                JOIN subcategories s ON p.subcategory_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE p.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only'
                AND MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
                LIMIT 3
            ");
            $stmt->execute([$query, $query]);
            $post_results = $stmt->fetchAll();
        } else {
            if ($subcategory_visibility_columns_exist) {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, s.name as subcategory_name, c.name as category_name, c.icon, 'post' as type,
                           MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
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
                    AND MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC
                    LIMIT 3
                ");
                $stmt->execute([
                    $query,
                    '%"' . $current_user_id . '"%',
                    '%"' . $current_user_id . '"%',
                    $current_user_id,
                    '%"' . $current_user_id . '"%',
                    $query
                ]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, s.name as subcategory_name, c.name as category_name, c.icon, 'post' as type,
                           MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (p.privacy = 'public'
                         OR p.user_id = ?
                         OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                    AND MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance DESC
                    LIMIT 3
                ");
                $stmt->execute([
                    $query,
                    '%"' . $current_user_id . '"%',
                    $current_user_id,
                    '%"' . $current_user_id . '"%',
                    $query
                ]);
            }
            $post_results = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Fallback to LIKE search if FULLTEXT fails
        error_log("FULLTEXT post search failed, using LIKE fallback: " . $e->getMessage());

        if ($is_super_user) {
            $stmt = $pdo->prepare("
                SELECT p.id, p.title, s.name as subcategory_name, c.name as category_name, c.icon, 'post' as type, 1 as relevance
                FROM posts p
                JOIN subcategories s ON p.subcategory_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE p.title LIKE ?
                ORDER BY p.title
                LIMIT 3
            ");
            $stmt->execute(['%' . $query . '%']);
            $post_results = $stmt->fetchAll();
        } elseif ($is_admin) {
            $stmt = $pdo->prepare("
                SELECT p.id, p.title, s.name as subcategory_name, c.name as category_name, c.icon, 'post' as type, 1 as relevance
                FROM posts p
                JOIN subcategories s ON p.subcategory_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE p.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only' AND p.title LIKE ?
                ORDER BY p.title
                LIMIT 3
            ");
            $stmt->execute(['%' . $query . '%']);
            $post_results = $stmt->fetchAll();
        } else {
            if ($subcategory_visibility_columns_exist) {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, s.name as subcategory_name, c.name as category_name, c.icon, 'post' as type, 1 as relevance
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
                    AND p.title LIKE ?
                    ORDER BY p.title
                    LIMIT 3
                ");
                $stmt->execute([
                    '%' . $current_user_id . '"%',
                    '%' . $current_user_id . '"%',
                    $current_user_id,
                    '%' . $current_user_id . '"%',
                    '%' . $query . '%'
                ]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.id, p.title, s.name as subcategory_name, c.name as category_name, c.icon, 'post' as type, 1 as relevance
                    FROM posts p
                    JOIN subcategories s ON p.subcategory_id = s.id
                    JOIN categories c ON s.category_id = c.id
                    WHERE (c.visibility = 'public'
                           OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                    AND (p.privacy = 'public'
                         OR p.user_id = ?
                         OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                    AND p.title LIKE ?
                    ORDER BY p.title
                    LIMIT 3
                ");
                $stmt->execute([
                    '%' . $current_user_id . '"%',
                    $current_user_id,
                    '%' . $current_user_id . '"%',
                    '%' . $query . '%'
                ]);
            }
            $post_results = $stmt->fetchAll();
        }
    }

    // Combine and format results
    $all_results = array_merge($category_results, $subcategory_results, $post_results);

    // Sort by relevance and take top 3
    usort($all_results, function($a, $b) {
        $relevance_a = $a['relevance'] ?? 0;
        $relevance_b = $b['relevance'] ?? 0;
        return $relevance_b <=> $relevance_a;
    });

    $top_results = array_slice($all_results, 0, 3);

    // Format results for JSON response
    $formatted_results = [];
    foreach ($top_results as $result) {
        $formatted_result = [
            'id' => $result['id'],
            'title' => htmlspecialchars($result['name'] ?? $result['title']),
            'type' => $result['type'],
            'icon' => $result['icon'] ?? '',
            'url' => '',
            'subtitle' => ''
        ];

        // Set URL and subtitle based on type
        switch ($result['type']) {
            case 'category':
                // For categories, we'll use a simple search that matches the category name exactly
                $formatted_result['url'] = 'search_working.php?q=' . urlencode($result['name']);
                $formatted_result['subtitle'] = 'Category';
                break;
            case 'subcategory':
                $formatted_result['url'] = 'subcategory.php?id=' . $result['id'];
                $formatted_result['subtitle'] = 'In ' . htmlspecialchars($result['category_name']);
                break;
            case 'post':
                $formatted_result['url'] = 'post.php?id=' . $result['id'];
                $formatted_result['subtitle'] = 'In ' . htmlspecialchars($result['category_name']) . ' / ' . htmlspecialchars($result['subcategory_name']);
                break;
        }

        $formatted_results[] = $formatted_result;
    }

    echo json_encode(['results' => $formatted_results]);

} catch (PDOException $e) {
    error_log("Search Autocomplete Error: " . $e->getMessage());
    echo json_encode(['results' => []]);
}
?>