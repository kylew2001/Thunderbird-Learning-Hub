<?php
/**
 * Quiz Management Page
 * Admin interface for creating and managing training quizzes
 * Only accessible by admin users
 *
 * Created: 2025-11-06
 * Author: Claude Code Assistant
 */

require_once __DIR__ . '/admin_init.php';
$includesDir = admin_include_base();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once dirname(__DIR__) . '/includes/include_path.php';
require_app_file('auth_check.php');
require_app_file('db_connect.php');
require_app_file('user_helpers.php');

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$page_title = 'Quiz Management';
$success_message = '';
$error_message = '';

// Check if quiz tables exist
$quiz_tables_exist = false;
try {
    $pdo->query("SELECT id FROM training_quizzes LIMIT 1");
    $quiz_tables_exist = true;
} catch (PDOException $e) {
    $error_message = "Quiz tables don't exist. Please import the add_quiz_system.sql file first.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz_tables_exist) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'create_quiz':
            $content_id   = isset($_POST['content_id']) ? intval($_POST['content_id']) : 0;
$content_type = isset($_POST['content_type']) ? $_POST['content_type'] : '';
$quiz_title   = isset($_POST['quiz_title']) ? trim($_POST['quiz_title']) : '';

// If the select still sends "post|123", split it safely.
if (strpos($content_type, '|') !== false) {
    list($ct_raw, $cid_raw) = explode('|', $content_type, 2);
    $ct_raw = strtolower(trim($ct_raw));
    if ($content_id <= 0 && ctype_digit($cid_raw)) {
        $content_id = (int)$cid_raw;
    }
    $content_type = $ct_raw;
}

// Normalize to the only allowed value for new quizzes (posts-only UI).
if (!in_array($content_type, ['post','posts','article','post_item'], true)) {
    $content_type = 'post';
} else {
    $content_type = 'post';
}
            $quiz_description = isset($_POST['quiz_description']) ? trim($_POST['quiz_description']) : '';
            $passing_score = isset($_POST['passing_score']) ? intval($_POST['passing_score']) : 100;
            $time_limit = isset($_POST['time_limit']) && !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null;

            // Validation
            if (empty($content_type) || $content_id <= 0) {
                $error_message = 'Please select training content for the quiz.';
            } elseif (empty($quiz_title)) {
                $error_message = 'Quiz title is required.';
            } elseif (strlen($quiz_title) > 255) {
                $error_message = 'Quiz title must be 255 characters or less.';
            } elseif ($passing_score < 0 || $passing_score > 100) {
                $error_message = 'Passing score must be between 0 and 100.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO training_quizzes
                        (content_id, content_type, quiz_title, quiz_description, passing_score, time_limit_minutes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$content_id, $content_type, $quiz_title, $quiz_description, $passing_score, $time_limit, $_SESSION['user_id']]);
                    $success_message = 'Quiz created successfully!';
                } catch (PDOException $e) {
                    $error_message = 'Error creating quiz: ' . $e->getMessage();
                }
            }
            break;

        case 'edit_quiz':
            $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
            $quiz_title = isset($_POST['quiz_title']) ? trim($_POST['quiz_title']) : '';
            $quiz_description = isset($_POST['quiz_description']) ? trim($_POST['quiz_description']) : '';
            $passing_score = isset($_POST['passing_score']) ? intval($_POST['passing_score']) : 100;
            $time_limit = isset($_POST['time_limit']) && !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($quiz_id <= 0) {
                $error_message = 'Invalid quiz ID.';
            } elseif (empty($quiz_title)) {
                $error_message = 'Quiz title is required.';
            } else {
                try {
                    $stmt = $pdo->prepare("
    UPDATE training_quizzes
    SET
        quiz_title        = :title,
        quiz_description  = :descr,
        passing_score     = :passing,
        time_limit_minutes= :tlimit,
        is_active         = :active,
        updated_at        = CURRENT_TIMESTAMP
    WHERE id = :id
");

/* Bind required params */
$stmt->bindValue(':title',   $quiz_title, PDO::PARAM_STR);
$stmt->bindValue(':descr',   $quiz_description, PDO::PARAM_STR);
$stmt->bindValue(':passing', $passing_score, PDO::PARAM_INT);
$stmt->bindValue(':active',  $is_active, PDO::PARAM_INT);
$stmt->bindValue(':id',      $quiz_id, PDO::PARAM_INT);

/* Bind nullable time limit explicitly */
if ($time_limit === null) {
    $stmt->bindValue(':tlimit', null, PDO::PARAM_NULL);
} else {
    $stmt->bindValue(':tlimit', $time_limit, PDO::PARAM_INT);
}

$stmt->execute();
$stmt->closeCursor();

$success_message = 'Quiz updated successfully!';
                } catch (PDOException $e) {
                    $error_message = 'Error updating quiz: ' . $e->getMessage();
                }
            }
            break;

        case 'toggle_status':
            $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;

            if ($quiz_id <= 0) {
                $error_message = 'Invalid quiz ID.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE training_quizzes SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$is_active, $quiz_id]);
                    $success_message = $is_active ? 'Quiz activated.' : 'Quiz deactivated.';
                } catch (PDOException $e) {
                    $error_message = 'Error updating quiz status: ' . $e->getMessage();
                }
            }
            break;
           // (Add this entire case block)
        case 'delete_quiz':
            $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;

            if ($quiz_id <= 0) {
                $error_message = 'Invalid quiz ID.';
                break;
            }

            try {
                $pdo->beginTransaction();

                /* 1) Delete per-question *choices* tied to this quiz */
                $stmt = $pdo->prepare("
                    DELETE ac
                    FROM quiz_answer_choices ac
                    JOIN quiz_questions qq ON qq.id = ac.question_id
                    WHERE qq.quiz_id = :qid
                ");
                $stmt->execute([':qid' => $quiz_id]);

                /* 2) Delete per-attempt *answers* tied to this quiz */
                $stmt = $pdo->prepare("
                    DELETE uqa
                    FROM user_quiz_answers uqa
                    JOIN user_quiz_attempts a ON a.id = uqa.attempt_id
                    WHERE a.quiz_id = :qid
                ");
                $stmt->execute([':qid' => $quiz_id]);

                /* 3) Delete questions for this quiz */
                $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = :qid");
                $stmt->execute([':qid' => $quiz_id]);

                /* 4) Delete user attempts for this quiz */
                $stmt = $pdo->prepare("DELETE FROM user_quiz_attempts WHERE quiz_id = :qid");
                $stmt->execute([':qid' => $quiz_id]);

                /* 5) Delete statistics row (if present) */
                $stmt = $pdo->prepare("DELETE FROM quiz_statistics WHERE quiz_id = :qid");
                $stmt->execute([':qid' => $quiz_id]);

                /* 6) Finally delete the quiz record */
                $stmt = $pdo->prepare("DELETE FROM training_quizzes WHERE id = :qid");
                $stmt->execute([':qid' => $quiz_id]);

                $pdo->commit();
                $success_message = 'Quiz deleted successfully.';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = 'Error deleting quiz: ' . $e->getMessage();
            }
            break;
    }
}


// Get data for display
$quizzes = [];
$training_content = [];

$filter = isset($_GET['filter']) ? strtolower(trim($_GET['filter'])) : '';
$filter = in_array($filter, ['unassigned']) ? $filter : ''; // only allow known filters

$unassigned_count = 0;
$total_quiz_count = 0;

if ($quiz_tables_exist) {
    try {
        $cnt = $pdo->query("SELECT
    SUM(CASE WHEN is_assigned = 0 THEN 1 ELSE 0 END) AS unassigned_cnt,
    COUNT(*) AS total_cnt
FROM training_quizzes");
$r = $cnt->fetch(PDO::FETCH_ASSOC);
$unassigned_count = (int)($r['unassigned_cnt'] ?? 0);
$total_quiz_count = (int)($r['total_cnt'] ?? 0);
        // Update quiz statistics manually (since we can't use triggers)
       



// --- BEGIN REPLACEMENT ---
// Ensure a stats row exists for every quiz (safe if already present)
$pdo->query("
    INSERT INTO quiz_statistics (quiz_id)
    SELECT tq.id
    FROM training_quizzes tq
    WHERE NOT EXISTS (
        SELECT 1 FROM quiz_statistics qs WHERE qs.quiz_id = tq.id
    )
");

// Update quiz statistics manually (completed attempts only; robust score fallback)
$update_stats_stmt = $pdo->query("
    UPDATE quiz_statistics qs
    JOIN training_quizzes tq ON tq.id = qs.quiz_id
    SET
        /* Completed attempts only */
        qs.total_attempts = (
            SELECT COUNT(*)
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = qs.quiz_id
              AND uqa.completed_at IS NOT NULL
        ),
        /* Distinct users with a completed attempt */
        qs.total_users = (
            SELECT COUNT(DISTINCT uqa.user_id)
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = qs.quiz_id
              AND uqa.completed_at IS NOT NULL
        ),
        /* Average percent: prefer score; fallback to earned/total * 100 */
        qs.average_score = COALESCE((
            SELECT AVG(COALESCE(uqa.score, (uqa.earned_points / NULLIF(uqa.total_points,0)) * 100))
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = qs.quiz_id
              AND uqa.completed_at IS NOT NULL
        ), 0),
        /* High/low with the same logic */
        qs.highest_score = COALESCE((
            SELECT MAX(COALESCE(uqa.score, (uqa.earned_points / NULLIF(uqa.total_points,0)) * 100))
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = qs.quiz_id
              AND uqa.completed_at IS NOT NULL
        ), 0),
        qs.lowest_score = COALESCE((
            SELECT MIN(COALESCE(uqa.score, (uqa.earned_points / NULLIF(uqa.total_points,0)) * 100))
            FROM user_quiz_attempts uqa
            WHERE uqa.quiz_id = qs.quiz_id
              AND uqa.completed_at IS NOT NULL
        ), 0),
        /* Pass rate based on status='passed' out of completed attempts */
        qs.pass_rate = (
            CASE
              WHEN (
                SELECT COUNT(*)
                FROM user_quiz_attempts uqa
                WHERE uqa.quiz_id = qs.quiz_id
                  AND uqa.completed_at IS NOT NULL
              ) = 0
              THEN 0
              ELSE (
                (
                  SELECT COUNT(*)
                  FROM user_quiz_attempts uqa
                  WHERE uqa.quiz_id = qs.quiz_id
                    AND uqa.completed_at IS NOT NULL
                    AND uqa.status = 'passed'
                ) * 100.0
              ) / (
                SELECT COUNT(*)
                FROM user_quiz_attempts uqa
                WHERE uqa.quiz_id = qs.quiz_id
                  AND uqa.completed_at IS NOT NULL
              )
            END
        ),
        /* Reliable question count */
        qs.total_questions = (
            SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = qs.quiz_id
        )
");
$update_stats_stmt->closeCursor();

        // Get quizzes with statistics
       /*
 * Normalize content_type so legacy values like "posts", "article", "subcat" still resolve.
 * Then join using the normalized type and produce a friendly display string.
 */
/*
 * Make content robust when content_type is empty:
 * - Always try to resolve a Post by posts.id = content_id (primary fallback).
 * - If content_type is present/normalized, still allow category/subcategory resolution.
 * - Provide a stable class for the badge (post/subcategory/category) based on what actually matched.
 */
// Apply optional filter to the quiz list
$where = ($filter === 'unassigned') ? "WHERE tq_all.is_assigned = 0" : "";

/* Same SELECT as before; we just insert $where before ORDER BY */
$sql = "
    SELECT
        tq_all.*,
        qs.*,

        tq_all.quiz_title AS quiz_title_display,

        CASE
            WHEN p.id  IS NOT NULL THEN p.title
            WHEN p_fallback.id IS NOT NULL THEN p_fallback.title
            WHEN sc.id IS NOT NULL THEN sc.name
            WHEN c.id  IS NOT NULL THEN c.name
            ELSE NULL
        END AS content_name,

        CASE
            WHEN p.id  IS NOT NULL THEN CONCAT('Post: ', COALESCE(p.title, 'Unknown'))
            WHEN p_fallback.id IS NOT NULL THEN CONCAT('Post: ', COALESCE(p_fallback.title, 'Unknown'))
            WHEN sc.id IS NOT NULL THEN CONCAT('Subcategory: ', COALESCE(sc.name, 'Unknown'))
            WHEN c.id  IS NOT NULL THEN CONCAT('Category: ', COALESCE(c.name, 'Unknown'))
            ELSE 'Content: Unknown'
        END AS content_display,

        CASE
            WHEN p.id  IS NOT NULL THEN 'post'
            WHEN p_fallback.id IS NOT NULL THEN 'post'
            WHEN sc.id IS NOT NULL THEN 'subcategory'
            WHEN c.id  IS NOT NULL THEN 'category'
            ELSE ''
        END AS content_class,

        COALESCE(
            qs.total_questions,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = tq_all.id)
        ) AS total_questions,

        (SELECT COUNT(*) FROM user_quiz_attempts uqa
         WHERE uqa.quiz_id = tq_all.id AND uqa.completed_at IS NOT NULL) AS total_users_attempted,

        (SELECT COUNT(*) FROM user_quiz_attempts uqa
         WHERE uqa.quiz_id = tq_all.id AND uqa.completed_at IS NOT NULL AND uqa.status = 'passed') AS users_passed,

        COALESCE(qs.average_score,
            (SELECT AVG(COALESCE(uqa.score, (uqa.earned_points / NULLIF(uqa.total_points,0)) * 100))
             FROM user_quiz_attempts uqa
             WHERE uqa.quiz_id = tq_all.id AND uqa.completed_at IS NOT NULL)
        ) AS average_score,

        u.name AS creator_name
    FROM (
        SELECT
            tq.*,
            CASE
                WHEN LOWER(tq.content_type) IN ('post','posts','article','post_item') THEN 'post'
                WHEN LOWER(tq.content_type) IN ('subcategory','subcat')              THEN 'subcategory'
                WHEN LOWER(tq.content_type) IN ('category','cat')                    THEN 'category'
                ELSE ''
            END AS content_type_norm
        FROM training_quizzes tq
    ) tq_all
    LEFT JOIN quiz_statistics qs ON tq_all.id = qs.quiz_id

    LEFT JOIN categories    c  ON (tq_all.content_type_norm = 'category'    AND tq_all.content_id = c.id)
    LEFT JOIN subcategories sc ON (tq_all.content_type_norm = 'subcategory' AND tq_all.content_id = sc.id)
    LEFT JOIN posts         p  ON (tq_all.content_type_norm = 'post'        AND tq_all.content_id = p.id)
    LEFT JOIN posts p_fallback ON (p.id IS NULL AND tq_all.content_id = p_fallback.id)
    LEFT JOIN users u ON tq_all.created_by = u.id

    $where
    ORDER BY (tq_all.is_assigned = 0) DESC, tq_all.created_at DESC
";

$stmt = $pdo->query($sql);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get training content for dropdown
        $stmt = $pdo->query("
    SELECT DISTINCT
        'post' AS content_type,
        p.id   AS content_id,
        CONCAT('Post: ', p.title) AS display_name
    FROM training_course_content tcc
    JOIN posts p ON p.id = tcc.content_id
    WHERE tcc.content_type = 'post'
      AND NOT EXISTS (
          SELECT 1
          FROM training_quizzes tq
          WHERE tq.content_id = tcc.content_id
            AND LOWER(tq.content_type) IN ('post','posts','article','post_item')
      )
    ORDER BY p.title
");
$training_content = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = 'Error loading quiz data: ' . $e->getMessage();
    }
}

include $includesDir . '/header.php';
require_app_file('header.php');
?>

<style>
.quiz-management {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.quiz-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.quiz-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.quiz-title {
    color: #333;
    margin: 0 0 5px 0;
    font-size: 18px;
}

.quiz-meta {
    color: #666;
    font-size: 14px;
    margin-bottom: 10px;
}

.quiz-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin: 15px 0;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.stat-number {
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
}

.stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

.quiz-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
    transition: background-color 0.2s;
}

.btn-primary { background: #667eea; color: white; }
.btn-primary:hover { background: #5a6fd8; }

.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #218838; }

.btn-warning { background: #ffc107; color: #212529; }
.btn-warning:hover { background: #e0a800; }

.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; }

.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}

.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    position: relative;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.quiz-content-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    background-color: white;
}

.content-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    margin-left: 8px;
}

.content-badge.category { background: #e3f2fd; color: #1976d2; }
.content-badge.subcategory { background: #f3e5f5; color: #7b1fa2; }
.content-badge.post { background: #e8f5e8; color: #388e3c; }
.content-badge.quiz { background: #fff3cd; color: #856404; } /* NEW */

</style>

<div class="quiz-management">
    <div class="section-header">
        <h1>📝 Quiz Management</h1>
        <p>Create and manage training quizzes for your knowledge base content</p>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if (!$quiz_tables_exist): ?>
        <div class="quiz-card">
            <h3>⚠️ Quiz System Not Set Up</h3>
            <p>The quiz system tables are not available. Please import the add_quiz_system.sql file first.</p>
            <a href="manage_training_courses.php" class="btn btn-secondary">← Back to Training Management</a>
        </div>
    <?php else: ?>
        <!-- Create New Quiz Section -->
        <div class="quiz-card">
            <h2>➕ Create New Quiz</h2>
            <?php if (empty($training_content)): ?>
                <p>All available training content already has quizzes assigned.</p>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="create_quiz">

                    <div class="form-group">
                        <label for="content">Training Content:</label>
                        <select name="content_type" id="content_type" class="quiz-content-select" required onchange="updateContentOptions()">
                            <option value="">Select Content Type</option>
                            <?php
                            $grouped_content = [];
                            foreach ($training_content as $content) {
                                $grouped_content[$content['content_type']][] = $content;
                            }

                            foreach ($grouped_content as $type => $items):
                                $type_label = ucfirst($type);
                                echo "<optgroup label='$type_label'>";
                                foreach ($items as $item):
                                    echo "<option value='{$item['content_type']}|{$item['content_id']}'>{$item['display_name']}</option>";
                                endforeach;
                                echo "</optgroup>";
                            endforeach;
                            ?>
                        </select>
                        <input type="hidden" name="content_id" id="content_id" required>
                    </div>

                    <div class="form-group">
                        <label for="quiz_title">Quiz Title:</label>
                        <input type="text" name="quiz_title" id="quiz_title" class="form-control" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="quiz_description">Quiz Description (Optional):</label>
                        <textarea name="quiz_description" id="quiz_description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="passing_score">Passing Score (%):</label>
                        <input type="number" name="passing_score" id="passing_score" class="form-control" min="0" max="100" value="80" required>
                    </div>

                    <div class="form-group">
                        <label for="time_limit">Time Limit (minutes, optional):</label>
                        <input type="number" name="time_limit" id="time_limit" class="form-control" min="1" placeholder="No time limit">
                    </div>

                    <button type="submit" class="btn btn-primary">Create Quiz</button>
                    <a href="manage_training_courses.php" class="btn btn-secondary">← Back</a>
                </form>
            <?php endif; ?>
        </div>

        <!-- Existing Quizzes Section -->
        <div class="quiz-card">
            <h2>📚 Existing Quizzes</h2>
            <div style="margin: 10px 0 20px 0; display: flex; gap: 8px; flex-wrap: wrap;">
    <a href="manage_quizzes.php"
       class="btn <?php echo ($filter === '' ? 'btn-primary' : 'btn-secondary'); ?>">
       All (<?php echo (int)$total_quiz_count; ?>)
    </a>
    <a href="manage_quizzes.php?filter=unassigned"
       class="btn <?php echo ($filter === 'unassigned' ? 'btn-primary' : 'btn-secondary'); ?>"
       title="Quizzes that need to be re-attached to content/course">
       ❗ Unassigned (<?php echo (int)$unassigned_count; ?>)
    </a>
</div>

<?php if (empty($quizzes)): ?>
    <p>No quizzes found<?php echo $filter === 'unassigned' ? ' in Unassigned.' : '.'; ?></p>
<?php else: ?>
    <?php foreach ($quizzes as $quiz): ?>
        <?php
        $isUnassigned = isset($quiz['is_assigned']) && (int)$quiz['is_assigned'] === 0;
        $cardBorder = $isUnassigned
            ? '#6f42c1'   // purple edge if unassigned
            : ($quiz['is_active'] ? '#28a745' : '#dc3545');
        $cardBg = $isUnassigned ? 'background: #f4ecff;' : '';
        ?>
        <div class="quiz-card" style="border-left: 4px solid <?php echo $cardBorder; ?>; <?php echo $cardBg; ?>">
            <div class="quiz-header">
                <div>
                    <h3 class="quiz-title">
                        <?php echo htmlspecialchars($quiz['quiz_title'] ?? ''); ?>
                        <?php if ($isUnassigned): ?>
                            <span class="content-badge quiz" style="margin-left:8px; background:#6f42c1; color:#fff;">Unassigned</span>
                        <?php elseif (!$quiz['is_active']): ?>
                            <span style="color: #dc3545; font-size: 12px;">(Inactive)</span>
                        <?php endif; ?>
                    </h3>
                    <div class="quiz-meta">
                        <span class="content-badge quiz">
                            Quiz: <?php echo htmlspecialchars($quiz['quiz_title_display'] ?? ($quiz['quiz_title'] ?? '')); ?>
                        </span>
                        <span class="content-badge <?php echo htmlspecialchars($quiz['content_class'] ?? ''); ?>">
                            <?php echo htmlspecialchars($quiz['content_display'] ?? 'Content: Unknown'); ?>
                        </span>
                        • Passing Score: <?php echo (int)$quiz['passing_score']; ?>%
                        <?php if (!empty($quiz['time_limit_minutes'])): ?>
                            • Time Limit: <?php echo (int)$quiz['time_limit_minutes']; ?> minutes
                        <?php endif; ?>
                        • Created by <?php echo htmlspecialchars($quiz['creator_name'] ?? 'Unknown'); ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($quiz['total_questions'])): ?>
                <div class="quiz-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo (int)$quiz['total_questions']; ?></div>
                        <div class="stat-label">Questions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo (int)($quiz['total_users_attempted'] ?? 0); ?></div>
                        <div class="stat-label">Attempts</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo (int)($quiz['users_passed'] ?? 0); ?></div>
                        <div class="stat-label">Passed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $quiz['average_score'] ? round($quiz['average_score']) : 0; ?>%</div>
                        <div class="stat-label">Avg Score</div>
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #6c757d; font-style: italic;">No questions added yet</p>
            <?php endif; ?>

            <div class="quiz-actions">
                <a href="manage_quiz_questions.php?quiz_id=<?php echo (int)$quiz['id']; ?>" class="btn btn-primary">
                    <?php echo (!empty($quiz['total_questions']) && (int)$quiz['total_questions'] > 0) ? '📝 Edit Questions' : '➕ Add Questions'; ?>
                </a>

                <a href="manage_quiz_questions.php?quiz_id=<?php echo (int)$quiz['id']; ?>&edit=true" class="btn btn-warning">
                    ✏️ Edit Quiz
                </a>

                <button onclick="toggleQuizStatus(<?php echo (int)$quiz['id']; ?>, <?php echo $quiz['is_active'] ? 0 : 1; ?>)" class="btn btn-secondary">
                    <?php echo $quiz['is_active'] ? '🔒 Deactivate' : '🔓 Activate'; ?>
                </button>

                <button
    class="btn btn-danger"
    data-quiz-id="<?php echo (int)$quiz['id']; ?>"
    data-quiz-title="<?php echo htmlspecialchars($quiz['quiz_title'] ?? '', ENT_QUOTES); ?>"
    onclick="deleteQuiz(this)">
    🗑️ Delete
</button>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>  <!-- closes: if (!$quiz_tables_exist) -->

</div>

<!-- Edit Quiz Modal -->
<div id="editQuizModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>✏️ Edit Quiz</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_quiz">
            <input type="hidden" name="quiz_id" id="edit_quiz_id">

            <div class="form-group">
                <label for="edit_quiz_title">Quiz Title:</label>
                <input type="text" name="quiz_title" id="edit_quiz_title" class="form-control" required maxlength="255">
            </div>

            <div class="form-group">
                <label for="edit_quiz_description">Quiz Description:</label>
                <textarea name="quiz_description" id="edit_quiz_description" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label for="edit_passing_score">Passing Score (%):</label>
                <input type="number" name="passing_score" id="edit_passing_score" class="form-control" min="0" max="100" required>
            </div>

            <div class="form-group">
                <label for="edit_time_limit">Time Limit (minutes, optional):</label>
                <input type="number" name="time_limit" id="edit_time_limit" class="form-control" min="1" placeholder="No time limit">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                    Quiz is active
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
function updateContentOptions() {
    const select = document.getElementById('content_type');
    const contentIdInput = document.getElementById('content_id');

    if (select.value) {
        const [contentType, contentId] = select.value.split('|');
        contentIdInput.value = contentId;
    } else {
        contentIdInput.value = '';
    }
}

// No longer needed because “Edit Quiz” is now a direct link.
// Keeping a stub in case something else calls it.
function editQuiz(quizId) {
    window.location.href = 'manage_quiz_questions.php?quiz_id=' + encodeURIComponent(quizId) + '&edit=true';
}

function toggleQuizStatus(quizId, newStatus) {
    if (confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this quiz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="quiz_id" value="${quizId}">
            <input type="hidden" name="is_active" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteQuiz(btn) {
    const quizId = btn.getAttribute('data-quiz-id');
    const quizTitle = btn.getAttribute('data-quiz-title') || '';
    if (confirm(`Are you sure you want to delete "${quizTitle}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_quiz">
            <input type="hidden" name="quiz_id" value="${quizId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeEditModal() {
    document.getElementById('editQuizModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editQuizModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include $includesDir . '/footer.php'; ?>
<?php require_app_file('footer.php'); ?>
