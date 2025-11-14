<?php
/**
 * Quiz Results Page
 * Displays quiz results and feedback for trainees
 *
 * Created: 2025-11-06
 * Author: Claude Code Assistant
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// Load training helpers if available
if (file_exists('includes/training_helpers.php')) {
    require_once 'includes/training_helpers.php';
}

// Get attempt ID from URL
$attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;

if ($attempt_id <= 0) {
    header('Location: training_dashboard.php');
    exit;
}

$page_title = 'Quiz Results';
$error_message = '';

// If this page was opened from the admin analytics dashboard, we may show full breakdown
$is_admin_view_flag = isset($_GET['admin_view']) && (
    $_GET['admin_view'] === '1' ||
    $_GET['admin_view'] === 'true'
);

// Only actually “force” the breakdown if the viewer is an admin/super-admin
$can_force_breakdown = $is_admin_view_flag && (is_admin() || is_super_admin());


// Check if user is training user
// --- BEGIN REPLACEMENT (allow any authenticated user to view their own results) ---
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
// --- END REPLACEMENT ---


// Get quiz attempt details
$attempt = null;
$quiz = null;
$questions = [];
$user_answers = [];

try {
    // Get attempt details

if (function_exists('log_debug')) {
    log_debug("quiz_results.php: fetching attempt_id=$attempt_id (viewer user_id=" . ($_SESSION['user_id'] ?? 'none') . ")");
}

// First try: tolerant join to course content (handles tq.content_type ''/NULL)
// NOTE: we fetch by attempt id only; access control is enforced in PHP.
$stmt = $pdo->prepare("
    SELECT uqa.*, tq.quiz_title, tq.passing_score,
           tcc.content_id, tcc.content_type,
           CASE tcc.content_type
               WHEN 'category'   THEN c.name
               WHEN 'subcategory' THEN sc.name
               WHEN 'post'       THEN p.title
           END AS content_name,
           CASE tcc.content_type
               WHEN 'category'   THEN 'category.php?id='
               WHEN 'subcategory' THEN 'subcategory.php?id='
               WHEN 'post'       THEN 'post.php?id='
           END AS content_url
    FROM user_quiz_attempts uqa
    JOIN training_quizzes tq
      ON uqa.quiz_id = tq.id
    JOIN training_course_content tcc
      ON tq.content_id = tcc.content_id
     AND (
            tq.content_type = tcc.content_type
         OR tq.content_type = ''
         OR tq.content_type IS NULL
     )
    LEFT JOIN categories c
      ON tcc.content_type = 'category'   AND tcc.content_id = c.id
    LEFT JOIN subcategories sc
      ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
    LEFT JOIN posts p
      ON tcc.content_type = 'post'       AND tcc.content_id = p.id
   WHERE uqa.id = ?
   LIMIT 1
");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    // Fallback: if the tcc row was deleted, derive content straight from the quiz row
    if (function_exists('log_debug')) {
        log_debug("quiz_results.php: primary fetch returned no row; attempting fallback join via training_quizzes only");
    }

    $stmt = $pdo->prepare("
        SELECT uqa.*, tq.quiz_title, tq.passing_score,
               tq.content_id,
               COALESCE(NULLIF(tq.content_type,''),'post') AS content_type,
               CASE COALESCE(NULLIF(tq.content_type,''),'post')
                   WHEN 'category'   THEN c.name
                   WHEN 'subcategory' THEN sc.name
                   WHEN 'post'       THEN p.title
               END AS content_name,
               CASE COALESCE(NULLIF(tq.content_type,''),'post')
                   WHEN 'category'   THEN 'category.php?id='
                   WHEN 'subcategory' THEN 'subcategory.php?id='
                   WHEN 'post'       THEN 'post.php?id='
               END AS content_url
        FROM user_quiz_attempts uqa
        JOIN training_quizzes tq
          ON uqa.quiz_id = tq.id
        LEFT JOIN categories c
          ON COALESCE(NULLIF(tq.content_type,''),'post') = 'category'   AND tq.content_id = c.id
        LEFT JOIN subcategories sc
          ON COALESCE(NULLIF(tq.content_type,''),'post') = 'subcategory' AND tq.content_id = sc.id
        LEFT JOIN posts p
          ON COALESCE(NULLIF(tq.content_type,''),'post') = 'post'       AND tq.content_id = p.id
       WHERE uqa.id = ?
       LIMIT 1
    ");
    $stmt->execute([$attempt_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (function_exists('log_debug')) {
    log_debug(
        "quiz_results.php: attempt fetch result = " .
        ($attempt ? 'FOUND (owner user_id=' . $attempt['user_id'] . ')' : 'NOT FOUND')
    );
}

// Enforce permissions: owner can see their attempt; admins/super-admins can see any
if ($attempt) {
    $viewer_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $owner_id  = isset($attempt['user_id']) ? (int)$attempt['user_id'] : 0;

    if ($owner_id !== $viewer_id && !is_admin() && !is_super_admin()) {
        if (function_exists('log_debug')) {
            log_debug("quiz_results.php: permission denied for viewer user_id={$viewer_id} on attempt owned by user_id={$owner_id}");
        }
        $error_message = 'You are not allowed to view this quiz attempt.';
        $attempt = null;
    }
}    
 

// If still no attempt and no more specific error was set, show generic message
if (!$attempt) {
    if (empty($error_message)) {
        $error_message = 'Quiz attempt not found.';
    }
} else {
    $quiz = [
        'quiz_title'    => $attempt['quiz_title'],
        'passing_score' => $attempt['passing_score'],
        'content_name'  => $attempt['content_name'],
        'content_url'   => $attempt['content_url'],
        'content_id'    => $attempt['content_id'],
        'content_type'  => $attempt['content_type']
    ];
    
    $earned_points  = isset($attempt['earned_points']) ? floatval($attempt['earned_points']) : 0.0;
    $total_points   = isset($attempt['total_points'])  ? floatval($attempt['total_points'])  : 0.0;
    $display_score  = ($total_points > 0) ? round(($earned_points / $total_points) * 100) : 0;

    // Optional: keep $attempt['score'] consistent for the rest of the template
    if ($display_score > 0 && (empty($attempt['score']) || intval($attempt['score']) === 0)) {
        $attempt['score'] = $display_score;
    }

        // Get questions and user answers
        $stmt = $pdo->prepare("
            SELECT qq.id, qq.question_text, qq.points,
                   uqa.selected_choice_id, uqa.is_correct, uqa.points_earned,
                   qac.choice_text, qac.is_correct as correct_answer
            FROM quiz_questions qq
            JOIN user_quiz_answers uqa ON qq.id = uqa.question_id
            JOIN quiz_answer_choices qac ON uqa.selected_choice_id = qac.id
            WHERE qq.quiz_id = ? AND uqa.attempt_id = ?
            ORDER BY qq.question_order, qq.id
        ");
        $stmt->execute([$attempt['quiz_id'], $attempt_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by question
        foreach ($results as $row) {
            $question_id = $row['id'];
            if (!isset($questions[$question_id])) {
                $questions[$question_id] = [
                    'id' => $row['id'],
                    'question_text' => $row['question_text'],
                    'points' => $row['points'],
                    'user_choice' => [
                        'text' => $row['choice_text'],
                        'is_correct' => $row['is_correct'],
                        'points_earned' => $row['points_earned']
                    ]
                ];
            }

            // Get all answer choices for this question
            if (!isset($user_answers[$question_id])) {
                $stmt = $pdo->prepare("
                    SELECT qac.choice_text, qac.is_correct
                    FROM quiz_answer_choices qac
                    WHERE qac.question_id = ?
                    ORDER BY qac.choice_order
                ");
                $stmt->execute([$question_id]);
                $user_answers[$question_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

} catch (PDOException $e) {
    $error_message = 'Error loading quiz results: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<style>
.results-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.results-header {
    text-align: center;
    margin-bottom: 30px;
}

.result-status {
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.result-status.passed {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.result-status.failed {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: white;
}

.result-icon {
    font-size: 64px;
    margin-bottom: 15px;
}

.result-title {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 10px;
}

.result-subtitle {
    font-size: 18px;
    opacity: 0.9;
}

.score-display {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.score-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    text-align: center;
}

.score-item {
    padding: 15px;
}

.score-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.score-number.excellent { color: #28a745; }
.score-number.good { color: #ffc107; }
.score-number.fair { color: #fd7e14; }
.score-number.poor { color: #dc3545; }

.score-label {
    color: #666;
    font-size: 14px;
}

.quiz-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border-left: 4px solid #667eea;
}

.quiz-info h3 {
    margin-top: 0;
    color: #333;
}

.quiz-info-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

.quiz-info-item {
    flex: 1;
    min-width: 150px;
}

.quiz-info-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.quiz-info-value {
    font-size: 16px;
    font-weight: 500;
    color: #333;
}

.question-results {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.question-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.question-number {
    background: #667eea;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    margin-right: 15px;
}

.question-text {
    font-size: 16px;
    font-weight: 500;
    color: #333;
    flex: 1;
}

.question-points {
    background: #e3f2fd;
    color: #1976d2;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.user-answer {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    border-left: 4px solid #667eea;
}

.user-answer-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.user-answer-text {
    font-size: 16px;
    color: #333;
}

.correct-answer {
    background: #d4edda;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    border-left: 4px solid #28a745;
}

.correct-answer-label {
    font-size: 12px;
    color: #155724;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.correct-answer-text {
    font-size: 16px;
    color: #155724;
}

.result-feedback {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    padding: 10px;
    border-radius: 6px;
}

.result-feedback.correct {
    background: #d4edda;
    color: #155724;
}

.result-feedback.incorrect {
    background: #f8d7da;
    color: #721c24;
}

.feedback-icon {
    font-size: 20px;
}

.feedback-text {
    font-weight: 500;
}

.navigation-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a6fd8;
    transform: translateY(-1px);
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background: #e0a800;
    transform: translateY(-1px);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Added so we can show a non-leaky message when not passed */
.alert-info {
    background: #e7f3ff;
    color: #084298;
    border: 1px solid #b6d4fe;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #495057;
}

.study-tips {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.study-tips h4 {
    margin-top: 0;
    color: #856404;
}

.study-tips ul {
    margin-bottom: 0;
}

.study-tips li {
    margin-bottom: 8px;
    color: #856404;
}

.next-steps {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.next-steps h4 {
    margin-top: 0;
    color: #0c5460;
}

.next-steps ul {
    margin-bottom: 0;
}

.next-steps li {
    margin-bottom: 8px;
    color: #0c5460;
}

@media (max-width: 768px) {
    .results-container {
        padding: 10px;
    }

    .score-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .navigation-buttons {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="results-container">
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            <br><br>
            <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    <?php elseif (!$attempt): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📄</div>
            <h3>Results Not Found</h3>
            <p>The quiz results you're looking for could not be found.</p>
            <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    <?php else: ?>
        <!-- Result Status -->
        <div class="result-status <?php echo $attempt['status'] === 'passed' ? 'passed' : 'failed'; ?>">
            <div class="result-icon">
                <?php echo $attempt['status'] === 'passed' ? '🎉' : '❌'; ?>
            </div>
            <div class="result-title">
                <?php echo $attempt['status'] === 'passed' ? 'Congratulations!' : 'Not Quite There'; ?>
            </div>
            <div class="result-subtitle">
                <?php echo $attempt['status'] === 'passed'
                    ? 'You passed the quiz!'
                    : 'You didn\'t meet the passing score.'; ?>
            </div>
        </div>

        <!-- Score Display -->
        <div class="score-display">
            <div class="score-grid">
                <div class="score-item">
                    <div class="score-number <?php
    if ($display_score >= 90) echo 'excellent';
    elseif ($display_score >= 70) echo 'good';
    elseif ($display_score >= 50) echo 'fair';
    else echo 'poor';
?>">
    <?php echo $display_score; ?>%
</div>
                    <div class="score-label">Your Score</div>
                </div>
                <div class="score-item">
                    <div class="score-number"><?php echo $quiz['passing_score']; ?>%</div>
                    <div class="score-label">Passing Score</div>
                </div>
                <div class="score-item">
                    <div class="score-number"><?php echo $attempt['earned_points']; ?>/<?php echo $attempt['total_points']; ?></div>
                    <div class="score-label">Points Earned</div>
                </div>
                <div class="score-item">
                    <div class="score-number"><?php echo count($questions); ?></div>
                    <div class="score-label">Questions</div>
                </div>
            </div>
        </div>

        <!-- Quiz Information -->
        <div class="quiz-info">
            <h3>📝 Quiz Information</h3>
            <div class="quiz-info-row">
                <div class="quiz-info-item">
                    <div class="quiz-info-label">Content</div>
                    <div class="quiz-info-value"><?php echo htmlspecialchars($quiz['content_name']); ?></div>
                </div>
                <div class="quiz-info-item">
                    <div class="quiz-info-label">Quiz Title</div>
                    <div class="quiz-info-value"><?php echo htmlspecialchars($quiz['quiz_title']); ?></div>
                </div>
                <div class="quiz-info-item">
                    <div class="quiz-info-label">Attempt Number</div>
                    <div class="quiz-info-value"><?php echo $attempt['attempt_number']; ?></div>
                </div>
                <div class="quiz-info-item">
                    <div class="quiz-info-label">Time Taken</div>
                    <div class="quiz-info-value"><?php echo $attempt['time_taken_minutes']; ?> minutes</div>
                </div>
                <div class="quiz-info-item">
                    <div class="quiz-info-label">Completed</div>
                    <div class="quiz-info-value"><?php echo date('M j, Y \a\t g:i A', strtotime($attempt['completed_at'])); ?></div>
                </div>
            </div>
        </div>

        <?php if ($attempt['status'] === 'passed' || $can_force_breakdown): ?>
    <!-- Question Results -->
    <h3 style="margin-bottom: 20px;">📊 Question Breakdown</h3>

    <?php if ($can_force_breakdown && $attempt['status'] !== 'passed'): ?>
        <div class="alert alert-warning" style="margin-bottom: 15px;">
            Admin view: you can see the full question breakdown even though the user has not passed this quiz yet.
        </div>
    <?php endif; ?>

    <?php foreach ($questions as $index => $question): ?>
        <div class="question-results">
            <div class="question-header">
                <div class="question-number"><?php echo $index + 1; ?></div>
                <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                <div class="question-points"><?php echo $question['points']; ?> pts</div>
            </div>

            <div class="user-answer">
                <div class="user-answer-label">Your Answer</div>
                <div class="user-answer-text"><?php echo htmlspecialchars($question['user_choice']['text']); ?></div>
                <div class="result-feedback <?php echo $question['user_choice']['is_correct'] ? 'correct' : 'incorrect'; ?>">
                    <span class="feedback-icon"><?php echo $question['user_choice']['is_correct'] ? '✅' : '❌'; ?></span>
                    <span class="feedback-text">
                        <?php echo $question['user_choice']['is_correct'] ? 'Correct' : 'Incorrect'; ?>
                    </span>
                </div>
            </div>

            <?php if (!$question['user_choice']['is_correct']): ?>
                <div class="correct-answer">
                    <div class="correct-answer-label">Correct Answer</div>
                    <div class="correct-answer-text">
                        <?php
                        foreach ($user_answers[$question['id']] as $choice) {
                            if ($choice['is_correct']) {
                                echo htmlspecialchars($choice['choice_text']);
                                break;
                            }
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <!-- Do NOT leak answers before passing to normal users -->
    <div class="alert alert-info" style="margin-top: 10px;">
        The question breakdown will be available after you pass this quiz.
    </div>
<?php endif; ?>


        <!-- Study Tips (if failed) -->
        <?php if ($attempt['status'] === 'failed'): ?>
            <div class="study-tips">
                <h4>Tips</h4>
                <ul>
                    <li>Review the content material carefully before attempting the quiz again</li>
                    <li>Pay special attention to the questions you got wrong</li>
                    <li>Take your time to read and understand each question</li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Next Steps -->
        <div class="next-steps">
            <h4>Next Steps</h4>
            <ul>
                <li>
                    <?php if ($attempt['status'] === 'passed'): ?>
                        Great job! You've completed this training content. Continue with your other assigned training materials.
                    <?php else: ?>
                        Review the content material and try the quiz again when you feel ready.
                    <?php endif; ?>
                </li>
                <li>Check your training dashboard to see your overall progress</li>
                <li>Contact your trainer if you need help with any of the material</li>
            </ul>
        </div>

        <!-- Navigation Buttons -->
        <div class="navigation-buttons">
            <a href="<?php echo htmlspecialchars($quiz['content_url'] . $quiz['content_id']); ?>" class="btn btn-secondary">
                📚 Review Content
            </a>

            <?php if ($attempt['status'] === 'failed'): ?>
                <a href="take_quiz.php?quiz_id=<?php echo $attempt['quiz_id']; ?>&content_id=<?php echo $quiz['content_id']; ?>&content_type=<?php echo $quiz['content_type']; ?>" class="btn btn-warning">
                    🔄 Retake Quiz
                </a>
            <?php endif; ?>

            <a href="training_dashboard.php" class="btn btn-primary">
                📊 Training Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>