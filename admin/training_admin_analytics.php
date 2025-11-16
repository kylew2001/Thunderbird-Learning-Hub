<?php
/**
 * Generic Page Template
 * Copy/rename this file to create new pages.
 * Then drop your custom logic + markup into the container below.
 */

// Robust include loader to tolerate different working directories
$include_loader = function (string $relativePath) {
    $paths = [
        __DIR__ . '/' . ltrim($relativePath, '/'),
        __DIR__ . '/../' . ltrim($relativePath, '/'),
        dirname(__DIR__) . '/' . ltrim($relativePath, '/'),
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }

    http_response_code(500);
    echo "<div class='alert alert-danger mt-4'>Required include missing: " . htmlspecialchars($relativePath) . "</div>";
    exit;
};

$include_loader('includes/auth_check.php');
$include_loader('includes/db_connect.php');
$include_loader('includes/user_helpers.php');

// Load training helpers if available (keeps behavior consistent with index.php)
$training_helper_paths = [
    __DIR__ . '/includes/training_helpers.php',
    __DIR__ . '/../includes/training_helpers.php',
    dirname(__DIR__) . '/includes/training_helpers.php',
];

foreach ($training_helper_paths as $training_helper_path) {
    if (file_exists($training_helper_path)) {
        require_once $training_helper_path;
        break;
    }
}

// Set the page title used by header.php
$page_title = 'Training Analytics Dashboard';


// Include standard header (HTML <head>, nav, etc.)
include 'includes/header.php';
?>

<style>
    /* Per-user course progress table */
    .course-progress-table th:nth-child(1) { width: 40%; }
    .course-progress-table th:nth-child(2) { width: 15%; }
    .course-progress-table th:nth-child(3) { width: 45%; }

    .status-pill {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 500;
    }
    .status-pill.completed {
        background: #d4edda;
        color: #155724;
    }
    .status-pill.in-progress {
        background: #fff3cd;
        color: #856404;
    }
    .status-pill.not-started {
        background: #e2e3e5;
        color: #383d41;
    }

    .quiz-latest {
        font-size: 13px;
        margin-bottom: 4px;
    }

    .quiz-attempts-table {
        font-size: 12px;
        background: #f8f9fa;
    }
    .quiz-attempts-table th,
    .quiz-attempts-table td {
        padding: 4px 6px;
    }
</style>

<div class="container">
    <?php
// Page title
$page_title = 'Training Analytics Dashboard';

// Admin-only access
if (!is_admin() && !is_super_admin()) {
    echo "<div class='alert alert-danger mt-4'>Access denied. Admin privileges required.</div>";
    include 'includes/footer.php';
    exit;
}
?>

<h2 class="mt-4 mb-4">📊 Training Analytics Dashboard</h2>

<?php
// -----------------------------------------------------
// SECTION 1: Course Overview (MVP foundation)
// -----------------------------------------------------

try {
    // Fetch all active courses
    $course_stmt = $pdo->query("
        SELECT id, name, department
        FROM training_courses
        WHERE is_active = 1
        ORDER BY name ASC
    ");

    $courses = $course_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    include 'includes/footer.php';
    exit;
}
?>

<div class="card mb-4">
    <div class="card-header">
        <strong>Course Overview</strong>
    </div>
    <div class="card-body">

        <?php if (empty($courses)): ?>
            <p>No active training courses found.</p>
        <?php else: ?>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Department</th>
                    <th>Assigned</th>
                    <th>Completed</th>
                    <th>Completion Rate</th>
                    <th>View</th>
                </tr>
            </thead>
            <tbody>

            <?php
            foreach ($courses as $course) {

                $course_id = intval($course['id']);

                // Count assigned users
                $assigned_stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT user_id) AS total_assigned
                    FROM user_training_assignments
                    WHERE course_id = ?
                ");
                $assigned_stmt->execute([$course_id]);
                $assigned = $assigned_stmt->fetch(PDO::FETCH_ASSOC)['total_assigned'] ?? 0;

                // Count completed users
                $completed_stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT user_id) AS total_completed
                    FROM training_history
                    WHERE course_id = ?
                      AND course_completed_date IS NOT NULL
                ");
                $completed_stmt->execute([$course_id]);
                $completed = $completed_stmt->fetch(PDO::FETCH_ASSOC)['total_completed'] ?? 0;

                // Compute completion %
                $rate = ($assigned > 0)
                    ? round(($completed / $assigned) * 100, 1)
                    : 0;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                    <td><?php echo htmlspecialchars($course['department']); ?></td>
                    <td><?php echo $assigned; ?></td>
                    <td><?php echo $completed; ?></td>
                    <td><?php echo $rate; ?>%</td>
                    <td>
                        <a href="?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-sm">
                            View
                        </a>
                    </td>
                </tr>
            <?php } ?>

            </tbody>
        </table>

        <?php endif; ?>

    </div>
</div>

<?php
// -----------------------------------------------------
// SECTION 2 — COURSE DETAILS VIEW
// -----------------------------------------------------

if (isset($_GET['course_id']) && ($course_id = intval($_GET['course_id'])) > 0 && !isset($_GET['user_id'])) {

    // Fetch course info
    $course_stmt = $pdo->prepare("
        SELECT id, name, department, description
        FROM training_courses
        WHERE id = ?
    ");
    $course_stmt->execute([$course_id]);
    $course_info = $course_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course_info) {
        echo "<div class='alert alert-danger'>Course not found.</div>";
    } else {

        echo "<h3 class='mt-4 mb-3'>📘 Course: " . htmlspecialchars($course_info['name']) . "</h3>";

        // Fetch all assigned users for this course
        $assigned_stmt = $pdo->prepare("
            SELECT uta.user_id, u.name
            FROM user_training_assignments AS uta
            JOIN users AS u ON u.id = uta.user_id
            WHERE uta.course_id = ?
            ORDER BY u.name ASC
        ");
        $assigned_stmt->execute([$course_id]);
        $assigned_users = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Per-user totals will be computed using the same logic as get_overall_training_progress()
        // inside the Assigned Users loop, to keep percentages consistent with the trainee dashboard.

        
        
        if (empty($assigned_users)) {
            echo "<div class='alert alert-warning'>No users are assigned to this course.</div>";
        } else {

            echo "<div class='card mb-4'>
                <div class='card-header'><strong>Assigned Users</strong></div>
                <div class='card-body'>";

            // Overall course status counters
            $total_assigned    = 0;
            $total_completed   = 0;
            $total_in_progress = 0;
            $total_not_started = 0;

            // Collect table rows while we compute counts
            $rows_html = '';

            foreach ($assigned_users as $user) {

                $uid = (int)$user['user_id'];
                $total_assigned++;

                // Same formula as get_overall_training_progress(), scoped to this user+course
                $progress_stmt = $pdo->prepare("
                    SELECT
                        COUNT(DISTINCT tcc.id) AS total_items,
                        COUNT(DISTINCT CASE WHEN tp.status = 'completed'   THEN tcc.id END) AS completed_items,
                        COUNT(DISTINCT CASE WHEN tp.status = 'in_progress' THEN tcc.id END) AS in_progress_items
                    FROM user_training_assignments uta
                    JOIN training_courses tc
                      ON tc.id = uta.course_id
                    JOIN training_course_content tcc
                      ON uta.course_id = tcc.course_id
                    LEFT JOIN training_progress tp
                      ON tcc.content_id = tp.content_id
                     AND tp.user_id     = uta.user_id
                     AND (
                            tcc.content_type = tp.content_type
                         OR tp.content_type = ''
                         OR tp.content_type IS NULL
                         )
                    WHERE uta.user_id      = ?
                      AND uta.course_id    = ?
                      AND tc.is_active     = 1
                      AND tcc.content_type = 'post'
                      AND (tcc.is_required = 1 OR tcc.is_required IS NULL)
                ");
                $progress_stmt->execute([$uid, $course_id]);
                $p = $progress_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $total_items       = (int)($p['total_items'] ?? 0);
                $completed_items   = (int)($p['completed_items'] ?? 0);
                $in_progress_items = (int)($p['in_progress_items'] ?? 0);

                $percent = $total_items > 0
                    ? round(($completed_items / $total_items) * 100)
                    : 0;

                // Clamp 0–100
                if ($percent < 0) {
                    $percent = 0;
                } elseif ($percent > 100) {
                    $percent = 100;
                }

                // Derive status from the same signals the dashboard uses:
                if ($total_items > 0 && $completed_items >= $total_items) {
                    $status = 'Completed';
                } elseif ($in_progress_items > 0 || $completed_items > 0) {
                    $status = 'In Progress';
                } else {
                    $status = 'Not Started';
                }

                // Bump counters
                if ($status === 'Completed') {
                    $total_completed++;
                } elseif ($status === 'In Progress') {
                    $total_in_progress++;
                } else {
                    $total_not_started++;
                }

                // Build table row HTML
                $rows_html .= '<tr>'
                    . '<td>' . htmlspecialchars($user['name']) . '</td>'
                    . '<td>' . $percent . '%</td>'
                    . '<td>' . $status . '</td>'
                    . '<td><a href="?course_id=' . $course_id . '&user_id=' . $uid . '" '
                    . 'class="btn btn-sm btn-secondary">View User</a></td>'
                    . '</tr>';
            }

            // Summary block above the table
            echo "
                <div class='alert alert-info mb-3' role='alert'>
                    <strong>{$total_assigned}</strong> assigned &middot;
                    <strong>{$total_in_progress}</strong> in progress &middot;
                    <strong>{$total_completed}</strong> completed &middot;
                    <strong>{$total_not_started}</strong> not started
                </div>
            ";

            // Now render the table with the collected rows
            echo "
                <table class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Completion %</th>
                            <th>Status</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rows_html}
                    </tbody>
                </table>
                </div>
            </div>";
        } 
    }
}


// -----------------------------------------------------
// SECTION 3 — INDIVIDUAL USER VIEW (WITH ATTEMPTS)
// -----------------------------------------------------

if (isset($_GET['course_id']) && isset($_GET['user_id']) &&
    ($course_id = intval($_GET['course_id'])) > 0 &&
    ($user_id = intval($_GET['user_id'])) > 0) {

    // Fetch user name
    $user_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_name = $user_stmt->fetchColumn();

    if (!$user_name) {
        echo "<div class='alert alert-danger'>User not found.</div>";
    } else {

        echo "<h3 class='mt-4 mb-3'>👤 User: " . htmlspecialchars($user_name) . "</h3>";

        // Fetch course content items (for detailed row view)
        $content_stmt = $pdo->prepare("
            SELECT id, content_type, content_id
            FROM training_course_content
            WHERE course_id = ?
            ORDER BY training_order ASC
        ");
        $content_stmt->execute([$course_id]);
        $content_items = $content_stmt->fetchAll(PDO::FETCH_ASSOC);

        // High-level summary for this user + course
        // Uses the same logic as get_overall_training_progress(), but scoped
        // to this specific course + user and only required post items.
        $summary_stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT tcc.id) AS total_items,
                COUNT(DISTINCT CASE WHEN tp.status = 'completed'   THEN tcc.id END) AS completed_items,
                COUNT(DISTINCT CASE WHEN tp.status = 'in_progress' THEN tcc.id END) AS in_progress_items
            FROM user_training_assignments uta
            JOIN training_courses tc
              ON tc.id = uta.course_id
            JOIN training_course_content tcc
              ON uta.course_id = tcc.course_id
            LEFT JOIN training_progress tp
              ON tcc.content_id = tp.content_id
             AND tp.user_id     = uta.user_id
             AND (
                    tcc.content_type = tp.content_type
                 OR tp.content_type = ''
                 OR tp.content_type IS NULL
                 )
            WHERE uta.user_id      = ?
              AND uta.course_id    = ?
              AND tc.is_active     = 1
              AND tcc.content_type = 'post'
              AND (tcc.is_required = 1 OR tcc.is_required IS NULL)
        ");
        $summary_stmt->execute([$user_id, $course_id]);
        $s = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $sum_total       = (int)($s['total_items'] ?? 0);
        $sum_completed   = (int)($s['completed_items'] ?? 0);
        $sum_in_progress = (int)($s['in_progress_items'] ?? 0);
        $sum_not_started = max(0, $sum_total - $sum_completed - $sum_in_progress);

        $sum_percent = $sum_total > 0
            ? round(($sum_completed / $sum_total) * 100)
            : 0;

                echo "
        <div class='card mb-4'>
            <div class='card-header'><strong>Course Progress</strong></div>
            <div class='card-body'>
                <div class='alert alert-info mb-3' role='alert'>
                    <strong>{$sum_total}</strong> required items &middot;
                    <strong>{$sum_in_progress}</strong> in progress &middot;
                    <strong>{$sum_completed}</strong> completed &middot;
                    <strong>{$sum_not_started}</strong> not started
                    <span style='float:right;'>
                        Overall completion: <strong>{$sum_percent}%</strong>
                    </span>
                </div>
                <table class='table table-bordered table-striped course-progress-table'>
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Status</th>
                            <th>Quiz</th>
                        </tr>
                    </thead>
                    <tbody>
        ";


        foreach ($content_items as $item) {

            $ctype = $item['content_type'];
            $cid   = intval($item['content_id']);
            
            // Only show post rows in this table (hide categories, subcategories, etc.)
            if ($ctype !== 'post') {
                continue;
            }

            // Fetch progress entry
            // Some older rows may not have course_id set, so key off user + content
            $prog_stmt = $pdo->prepare("
                SELECT status, quiz_score, quiz_completed
                FROM training_progress
                WHERE user_id = ?
                  AND content_type = ?
                  AND content_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            
            $prog_stmt->execute([$user_id, $ctype, $cid]);
            $progress = $prog_stmt->fetch(PDO::FETCH_ASSOC);

            // Raw DB status (in_progress, completed, etc.)
            $status_raw = $progress['status'] ?? 'not_started';
            $status_lc  = strtolower((string)$status_raw);

            if ($status_lc === 'completed') {
                $status_label = 'Completed';
            } elseif ($status_lc === 'in_progress') {
                $status_label = 'In Progress';
            } elseif ($status_lc === 'not_started') {
                $status_label = 'Not Started';
            } else {
                // Fallback: capitalize whatever we got
                $status_label = ucfirst($status_lc ?: 'Not Started');
            }
            // Map status to pill style for the table
            $status_class = 'not-started';
            if ($status_lc === 'completed') {
                $status_class = 'completed';
            } elseif ($status_lc === 'in_progress') {
                $status_class = 'in-progress';
            }

            $status_html = "<span class='status-pill {$status_class}'>" . htmlspecialchars($status_label) . "</span>";
            

            // Fetch names of content
            $title = '';
            if ($ctype === 'post') {
                $post_stmt = $pdo->prepare("SELECT title FROM posts WHERE id = ?");
                $post_stmt->execute([$cid]);
                $title = $post_stmt->fetchColumn() ?: "Post #{$cid}";
            } else if ($ctype === 'quiz') {
                $quiz_stmt = $pdo->prepare("SELECT quiz_title FROM training_quizzes WHERE id = ?");
                $quiz_stmt->execute([$cid]);
                $title = $quiz_stmt->fetchColumn() ?: "Quiz #{$cid}";
            }

     // Quiz info if applicable (latest summary + full attempts list)
            // Works for:
            //  - content_type = 'quiz'  (quiz row in course content)
            //  - content_type = 'post'  (quiz attached to a post)
            $quiz_info         = '';
            $attempts_html     = '';
            $quiz_id_for_item  = null;

            if ($ctype === 'quiz') {
                // Direct quiz content
                $quiz_id_for_item = $cid;
            } elseif ($ctype === 'post') {
                // Quiz attached to this post (same pattern as training_dashboard)
                $quiz_lookup = $pdo->prepare("
                    SELECT id
                    FROM training_quizzes
                    WHERE content_id = ?
                      AND LOWER(COALESCE(content_type, '')) IN ('post', '')
                    LIMIT 1
                ");
                $quiz_lookup->execute([$cid]);
                $quiz_id_for_item = $quiz_lookup->fetchColumn() ?: null;
            }

            if ($quiz_id_for_item) {
                // Fetch attempts (latest first)
                                $attempt_stmt = $pdo->prepare("
                    SELECT id, attempt_number, score, status, completed_at
                    FROM user_quiz_attempts
                    WHERE user_id = ?
                      AND quiz_id = ?
                      AND status IN ('passed','failed')
                    ORDER BY attempt_number DESC
                ");

                $attempt_stmt->execute([$user_id, $quiz_id_for_item]);
                $attempts = $attempt_stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($attempts) {
                    $latest        = $attempts[0];
                    $latest_score  = (int)($latest['score'] ?? 0);
                    $latest_status = htmlspecialchars($latest['status'] ?? '', ENT_QUOTES, 'UTF-8');
                    $latest_date   = $latest['completed_at']
                        ? date('Y-m-d H:i', strtotime($latest['completed_at']))
                        : '—';

                    // Latest attempt summary (text above the table)
                    $quiz_info = "Latest: {$latest_score}% ({$latest_status}) on {$latest_date}";

                    // Build mini attempts table
                                        $attempts_html .= "<div style='margin-top: 6px;'>";
                    $attempts_html .= "<table class='table table-sm table-bordered mb-0 quiz-attempts-table'>";

                    $attempts_html .= "<thead><tr>"
                                    . "<th>#</th>"
                                    . "<th>Score</th>"
                                    . "<th>Status</th>"
                                    . "<th>Date</th>"
                                    . "<th>Result</th>"
                                    . "</tr></thead><tbody>";

                    foreach ($attempts as $att) {
                        $att_id      = (int)$att['id'];
                        $att_num     = (int)($att['attempt_number'] ?? 0);
                        $att_score   = (int)($att['score'] ?? 0);
                        $att_status  = htmlspecialchars($att['status'] ?? '', ENT_QUOTES, 'UTF-8');
                        $att_date    = $att['completed_at']
                            ? date('Y-m-d H:i', strtotime($att['completed_at']))
                            : '—';

                        $attempts_html .= "<tr>"
    . "<td>{$att_num}</td>"
    . "<td>{$att_score}%</td>"
    . "<td>{$att_status}</td>"
    . "<td>{$att_date}</td>"
    // admin_view=1 flags that this was opened from the admin analytics page
    . "<td><a href='quiz_results.php?attempt_id=" . intval($att_id) . "&admin_view=1' class='btn btn-xs btn-primary'>View</a></td>"
    . "</tr>";

                    }

                    $attempts_html .= "</tbody></table></div>";
                } else {
                    $quiz_info = 'No completed attempts yet';
                }
            }
            
            if ($quiz_info !== '') {
                $quiz_info = "<div class='quiz-latest'>{$quiz_info}</div>";
            }

                        echo "
                <tr>
                    <td>" . htmlspecialchars($title) . "</td>
                    <td>{$status_html}</td>
                    <td>{$quiz_info}{$attempts_html}</td>
                </tr>
            ";

        }

        echo "
                </tbody>
            </table>
            </div>
        </div>
        ";
    }
}
?>

</div>

<?php
// Standard footer (includes your latest updates widget, bug report button, etc.)
include 'includes/footer.php';
?>
