<?php
/**
 * Subcategory Page - List Posts
 * Shows all posts in a subcategory with title, preview, and timestamp
 * Updated: 2025-11-05 (Removed hardcoded SQL user fallbacks - database-only users)
 *
 * FIXED: Removed hardcoded SQL user fallbacks that were interfering with database authentication
 * - User display now requires database users table
 * - Complete database-driven user system integration
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

$page_title = 'Posts';
$error_message = '';
$subcategory = null;
$posts = [];

// Get subcategory ID
$subcategory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($subcategory_id <= 0) {
    header('Location: index.php');
    exit;
}

// Check if subcategory visibility columns exist
$subcategory_visibility_columns_exist = false;
$category_visibility_columns_exist = false;
try {
    $test_query = $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
    $subcategory_visibility_columns_exist = true;
} catch (PDOException $e) {
    // Subcategory visibility columns don't exist yet
}

try {
    $test_query = $pdo->query("SELECT visibility FROM categories LIMIT 1");
    $category_visibility_columns_exist = true;
} catch (PDOException $e) {
    // Category visibility columns don't exist yet
}

// Fetch subcategory with category info for breadcrumb and visibility check
try {
    $current_user_id = $_SESSION['user_id'];
    $is_super_user = is_super_admin();
    $is_admin = is_admin();

    if ($is_super_user && $subcategory_visibility_columns_exist) {
        // Super Admins can see all subcategories including it_only
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    } elseif ($is_admin && $subcategory_visibility_columns_exist) {
        // Normal Admins can see all subcategories except it_only
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ? AND s.visibility != 'it_only' AND c.visibility != 'it_only'
        ");
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    } elseif ($subcategory_visibility_columns_exist && $category_visibility_columns_exist) {
        // Regular users need visibility checks
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ?
            AND (c.visibility = 'public'
                 OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
            AND (s.visibility = 'public'
                 OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
        ");
        $stmt->execute([$subcategory_id, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%']);
        $subcategory = $stmt->fetch();
    } else {
        // Old database - no visibility checks
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name, c.id AS category_id
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    }

    if (!$subcategory) {
        $error_message = 'Subcategory not found or you do not have permission to access it.';
    } else {
        // Fetch posts in this subcategory with privacy filtering
        $current_user_id = $_SESSION['user_id'];

        if ($is_super_user) {
            // Super Admins can see all posts including it_only - no privacy filtering
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        u.name AS author_name,
                        u.color AS author_color,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.subcategory_id = ?
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            } else {
                // No fallback - require database users table
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    WHERE p.subcategory_id = ?
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            }
            $stmt->execute([$subcategory_id]);
        } elseif ($is_admin) {
            // Normal Admins can see all posts except it_only - no privacy filtering
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        u.name AS author_name,
                        u.color AS author_color,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.subcategory_id = ? AND p.privacy != 'it_only'
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            } else {
                // No fallback - require database users table
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    WHERE p.subcategory_id = ? AND p.privacy != 'it_only'
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            }
            $stmt->execute([$subcategory_id]);
        } else {
            // Regular users get privacy filtering
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        u.name AS author_name,
                        u.color AS author_color,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.subcategory_id = ? AND (
                        p.privacy = 'public' OR
                        p.user_id = ? OR
                        (p.privacy = 'shared' AND p.shared_with LIKE ?)
                    )
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            } else {
                // No fallback - require database users table
                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.title,
                        p.content,
                        p.created_at,
                        p.user_id,
                        p.privacy,
                        p.shared_with,
                        COUNT(r.id) AS reply_count
                    FROM posts p
                    LEFT JOIN replies r ON p.id = r.post_id
                    WHERE p.subcategory_id = ? AND (
                        p.privacy = 'public' OR
                        p.user_id = ? OR
                        (p.privacy = 'shared' AND p.shared_with LIKE ?)
                    )
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ");
            }
            $shared_with_pattern = '%"' . $current_user_id . '"%';
            $stmt->execute([$subcategory_id, $current_user_id, $shared_with_pattern]);
        }
        $posts = $stmt->fetchAll();

        // Sort posts alphabetically by title (case-insensitive)
        usort($posts, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

// Helper function to format timestamp
function format_timestamp($timestamp) {
    return date('M j, Y \a\t g:i A', strtotime($timestamp));
}

// Helper function to create text preview (strip HTML, limit to 200 chars)
function create_preview($html_content, $length = 200) {
    // Convert HTML entities first
    $text = html_entity_decode($html_content, ENT_QUOTES, 'UTF-8');
    // Strip HTML tags
    $text = strip_tags($text);
    // Replace multiple whitespace with single space
    $text = preg_replace('/\s+/', ' ', $text);
    // Trim whitespace
    $text = trim($text);
    // Handle empty content
    if (empty($text)) {
        return '[No text content - attachments only]';
    }
    // Truncate if too long
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

include 'includes/header.php';
?>

<div class="container">
    <?php
    require_once __DIR__ . '/includes/search_widget.php';
    // Point to your known-good endpoint that works like index:
    render_search_bar('search_working.php');
    ?>

    <script>
    // === Unified Search Autocomplete (same as index) ===
    let searchTimeout;
    let currentAutocompleteResults = [];

    // Input handler with debounce
    document.addEventListener('DOMContentLoaded', function () {
        const inputEl = document.getElementById('searchInput');
        const dropdown = document.getElementById('autocompleteDropdown');

        if (!inputEl) return;

        inputEl.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            clearTimeout(searchTimeout);

            if (query.length < 2) {
                dropdown.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                performAutocompleteSearch(query);
            }, 300);
        });

        // Hide autocomplete when clicking outside
        document.addEventListener('click', function(e) {
            const searchContainer = dropdown?.parentElement; // the .card with position: relative
            if (searchContainer && !searchContainer.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Keyboard navigation
        inputEl.addEventListener('keydown', function(e) {
            if (dropdown.style.display === 'none' || currentAutocompleteResults.length === 0) return;

            let selectedIndex = -1;
            const items = dropdown.querySelectorAll('.autocomplete-item');

            // find selected
            for (let i = 0; i < items.length; i++) {
                if (items[i].classList.contains('selected')) {
                    selectedIndex = i;
                    break;
                }
            }

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    selectedIndex = (selectedIndex + 1) % items.length;
                    updateSelectedAutocompleteItem(items, selectedIndex);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
                    updateSelectedAutocompleteItem(items, selectedIndex);
                    break;
                case 'Enter':
                    e.preventDefault();
                    if (selectedIndex >= 0 && items[selectedIndex]) {
                        items[selectedIndex].click();
                    } else {
                        document.getElementById('searchForm').submit();
                    }
                    break;
                case 'Escape':
                    dropdown.style.display = 'none';
                    break;
            }
        });
    });

    function updateSelectedAutocompleteItem(items, selectedIndex) {
        items.forEach(item => item.classList.remove('selected'));
        if (items[selectedIndex]) {
            items[selectedIndex].classList.add('selected');
            const titleElement = items[selectedIndex].querySelector('.autocomplete-title');
            if (titleElement) {
                const inputEl = document.getElementById('searchInput');
                if (inputEl) inputEl.value = titleElement.textContent;
            }
        }
    }

    function performAutocompleteSearch(query) {
        fetch('search_autocomplete.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                currentAutocompleteResults = data.results || [];
                displayAutocompleteResults(currentAutocompleteResults);
            })
            .catch(error => {
                console.error('Autocomplete search error:', error);
                const dropdown = document.getElementById('autocompleteDropdown');
                if (dropdown) dropdown.style.display = 'none';
            });
    }

    function displayAutocompleteResults(results) {
        const dropdown = document.getElementById('autocompleteDropdown');
        if (!dropdown) return;

        if (results.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        let html = '';
        results.forEach(result => {
            const typeIcon = getTypeIcon(result.type);
            const typeColor = getTypeColor(result.type);

            html += `
                <div class="autocomplete-item" onclick="selectAutocompleteResult('${result.url}')" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; transition: background-color 0.2s;">
                    <div style="font-size: 18px; color: ${typeColor};">${typeIcon}</div>
                    <div style="flex: 1;">
                        <div class="autocomplete-title" style="font-weight: 500; color: #2d3748; margin-bottom: 2px;">${result.title}</div>
                        <div style="font-size: 12px; color: #718096;">${result.subtitle}</div>
                    </div>
                    <div style="font-size: 12px; color: #a0aec0; text-transform: uppercase; font-weight: 500;">${result.type}</div>
                </div>
            `;
        });

        dropdown.innerHTML = html;
        dropdown.style.display = 'block';

        // Hover effect
        dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('mouseenter', function() { this.style.backgroundColor = '#f7fafc'; });
            item.addEventListener('mouseleave', function() { this.style.backgroundColor = 'transparent'; });
        });
    }

    function getTypeIcon(type) {
        const icons = { 'category': '📁', 'subcategory': '📂', 'post': '📄' };
        return icons[type] || '📄';
    }

    function getTypeColor(type) {
        const colors = { 'category': '#667eea', 'subcategory': '#4299e1', 'post': '#48bb78' };
        return colors[type] || '#718096';
    }

    function selectAutocompleteResult(url) {
        window.location.href = url;
    }
    </script>

    <style>
    .autocomplete-item.selected { background-color: #e6f3ff !important; }
    .autocomplete-item:hover { background-color: #f7fafc; }
    #autocompleteDropdown { animation: slideDown 0.2s ease-out; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    </style>

    <?php if ($subcategory): ?>
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span>></span>
            <span><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
            <span>></span>
            <span class="current"><?php echo htmlspecialchars($subcategory['name']); ?></span>
        </div>

        <div class="flex-between mb-20">
            <div>
                <h2 style="font-size: 24px; color: #2d3748; margin-bottom: 8px;">
                    <?php echo htmlspecialchars($subcategory['name']); ?>
                </h2>
                <div class="subcategory-actions">
                    <?php if ($is_admin): ?>
                        <a href="edit_subcategory.php?id=<?php echo $subcategory_id; ?>" class="btn btn-warning btn-small">Edit Subcategory</a>
                        <a href="delete_subcategory.php?id=<?php echo $subcategory_id; ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure? This will delete the subcategory and ALL posts and replies under it. This cannot be undone.');">Delete Subcategory</a>
                    <?php else: ?>
                        <a href="request_edit_subcategory.php?id=<?php echo $subcategory_id; ?>" class="btn btn-warning btn-small">Request Edit</a>
                    <?php endif; ?>
                </div>
            </div>
            <a href="add_post.php?subcategory_id=<?php echo $subcategory_id; ?>" class="btn btn-success">+ Add Post</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <a href="index.php" class="btn btn-primary">Back to Home</a>
    <?php elseif (isset($_GET['success'])): ?>
        <div class="success-message">
            <?php
            $success_messages = [
                'post_added' => 'Post created successfully!',
                'post_updated' => 'Post updated successfully!',
                'post_deleted' => 'Post deleted successfully!'
            ];
            $success_key = $_GET['success'];
            echo isset($success_messages[$success_key]) ? $success_messages[$success_key] : 'Action completed successfully!';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($subcategory && empty($posts)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📝</div>
            <div class="empty-state-text">No posts yet. Click "Add Post" to create one.</div>
        </div>
    <?php elseif ($subcategory): ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <div class="post-item">
                    <div class="post-header">
                        <a href="post.php?id=<?php echo $post['id']; ?>">
                            <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                        </a>
                        <div class="post-privacy-indicator">
                            <?php
                            $privacy_icons = [
                                'public' => '🌐',
                                'private' => '🔒',
                                'shared' => '👥',
                                'it_only' => '🔐'
                            ];
                            echo $privacy_icons[$post['privacy']] ?? '📝';
                            ?>
                        </div>
                    </div>
                    <div class="post-meta">
                        <span style="color: <?php echo htmlspecialchars($post['author_color']); ?>">
                            <?php echo htmlspecialchars($post['author_name']); ?>
                        </span>
                        <span><?php echo format_timestamp($post['created_at']); ?></span>
                        <span><?php echo $post['reply_count']; ?> update(s)</span>
                    </div>
                    <?php if (!empty($post['content'])): ?>
                        <div class="post-preview">
                            <?php echo htmlspecialchars(create_preview($post['content'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    // Show edit/delete buttons only for post owners or admins
                    if ($is_admin || $post['user_id'] == $current_user_id):
                    ?>
                        <div class="post-actions" style="margin-top: 8px; display: flex; gap: 8px;">
                            <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-small" style="background: #ffc107; color: black; text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 12px;">✏️ Edit</a>
                            <a href="delete_post.php?id=<?php echo $post['id']; ?>" class="btn btn-small" style="background: #dc3545; color: white; text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 12px;" onclick="return confirm('Are you sure you want to delete this post? This cannot be undone.');">🗑️ Delete</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>