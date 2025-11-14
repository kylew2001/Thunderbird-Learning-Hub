<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication and admin check
require_once 'includes/auth_check.php';

// Load database and helpers
require_once 'includes/db_connect.php';
require_once 'includes/training_helpers.php';
require_once 'includes/user_helpers.php';

// Check if user is admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Check if training tables exist
$training_tables_exist = false;
try {
    $stmt = $pdo->query("SELECT id FROM training_courses LIMIT 1");
    $training_tables_exist = true;
} catch (PDOException $e) {
    $error_message = "Training tables don't exist. Please import the add_training_system.sql file first.";
}

// Get course ID
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
        $error_message = "Error loading course information.";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $training_tables_exist && $course) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'update_course':
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $department = isset($_POST['department']) ? trim($_POST['department']) : '';
            $estimated_hours = isset($_POST['estimated_hours']) ? floatval($_POST['estimated_hours']) : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Validation
            if (empty($name)) {
                $error_message = 'Course name is required.';
            } elseif (strlen($name) > 255) {
                $error_message = 'Course name must be 255 characters or less.';
            } elseif ($estimated_hours < 0 || $estimated_hours > 999.9) {
                $error_message = 'Estimated hours must be between 0 and 999.9.';
            } else {
                try {
                    $update_stmt = $pdo->prepare("
                        UPDATE training_courses
                        SET name = ?, description = ?, department = ?, estimated_hours = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $result = $update_stmt->execute([$name, $description, $department, $estimated_hours, $is_active, $course_id]);

                    if ($result) {
                        // Refresh course data
                        $stmt = $pdo->prepare("SELECT * FROM training_courses WHERE id = ?");
                        $stmt->execute([$course_id]);
                        $course = $stmt->fetch(PDO::FETCH_ASSOC);

                        $success_message = 'Training course updated successfully!';
                    } else {
                        $error_message = 'Error updating training course.';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Database error: ' . $e->getMessage();
                }
            }
            break;

        case 'toggle_status':
            try {
                $new_status = $course['is_active'] ? 0 : 1;
                $update_stmt = $pdo->prepare("UPDATE training_courses SET is_active = ?, updated_at = NOW() WHERE id = ?");
                $result = $update_stmt->execute([$new_status, $course_id]);

                if ($result) {
                    // Refresh course data
                    $stmt = $pdo->prepare("SELECT * FROM training_courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch(PDO::FETCH_ASSOC);

                    $success_message = 'Course status updated successfully!';
                } else {
                    $error_message = 'Error updating course status.';
                }
            } catch (PDOException $e) {
                $error_message = 'Database error: ' . $e->getMessage();
            }
            break;
    }
}

// Get course statistics
$course_stats = [];
if ($training_tables_exist && $course) {
    try {
        // Get assigned users count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_users,
                   COUNT(CASE WHEN uta.status = 'completed' THEN 1 END) as completed_users
            FROM user_training_assignments uta
            WHERE uta.course_id = ?
        ");
        $stmt->execute([$course_id]);
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get content count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as content_items
            FROM training_course_content
            WHERE course_id = ?
        ");
        $stmt->execute([$course_id]);
        $content_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $course_stats = [
            'total_users' => $user_stats['total_users'],
            'completed_users' => $user_stats['completed_users'],
            'content_items' => $content_stats['content_items']
        ];
    } catch (PDOException $e) {
        // Handle errors gracefully
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - <?php echo htmlspecialchars($course['name'] ?? 'Course'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .header-nav {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .back-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            transition: background 0.3s;
        }
        .back-link:hover {
            background: rgba(255,255,255,0.3);
            text-decoration: none;
            color: white;
        }
        .course-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        .main-content, .sidebar {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .section-header {
            background: #f8f9fa;
            padding: 16px 20px;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        .section-content {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 16px;
            text-align: center;
            margin-bottom: 16px;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 16px;
            border: 1px solid #f5c6cb;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 16px;
            border: 1px solid #c3e6cb;
        }
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .quick-actions .btn {
            text-align: left;
            justify-content: flex-start;
        }
        @media (max-width: 768px) {
            .course-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-nav">
            <div>
                <h1 style="margin: 0 0 4px 0;">✏️ Edit Training Course</h1>
                <p style="margin: 0; opacity: 0.9;"><?php echo htmlspecialchars($course['name'] ?? 'Course'); ?></p>
            </div>
            <a href="manage_training_courses.php" class="back-link">← Back to Courses</a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$training_tables_exist): ?>
            <div class="main-content">
                <div class="section-content">
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px;">
                        <h3 style="color: #856404; margin: 0 0 8px 0;">⚠️ Database Setup Required</h3>
                        <p style="color: #856404; margin: 0;">The training tables don't exist in your database. Please import the add_training_system.sql file first.</p>
                    </div>
                </div>
            </div>
        <?php elseif (!$course): ?>
            <div class="main-content">
                <div class="section-content">
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <h3>Course Not Found</h3>
                        <p>The requested course could not be found.</p>
                        <a href="manage_training_courses.php" class="btn btn-primary">Back to Courses</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="course-layout">
                <!-- Main Content - Edit Form -->
                <div class="main-content">
                    <div class="section-header">📝 Course Details</div>
                    <div class="section-content">
                        <form method="POST" action="" style="margin: 0;">
                            <input type="hidden" name="action" value="update_course">

                            <div class="form-group">
                                <label for="name">Course Name <span style="color: #dc3545;">*</span></label>
                                <input type="text" name="name" id="name" class="form-control"
                                       value="<?php echo htmlspecialchars($course['name']); ?>"
                                       maxlength="255" required>
                            </div>

                            <div class="form-group">
                                <label for="department">Department</label>
                                <input type="text" name="department" id="department" class="form-control"
                                       value="<?php echo htmlspecialchars($course['department'] ?? ''); ?>"
                                       maxlength="100" placeholder="e.g., Human Resources">
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" class="form-control"
                                          rows="4" maxlength="1000"
                                          placeholder="Course description and objectives..."><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="estimated_hours">Estimated Hours</label>
                                <input type="number" name="estimated_hours" id="estimated_hours" class="form-control"
                                       value="<?php echo htmlspecialchars($course['estimated_hours']); ?>"
                                       min="0" max="999.9" step="0.1" style="width: 150px;">
                                <span style="margin-left: 8px; color: #6c757d; font-size: 13px;">Optional: Estimated completion time</span>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="is_active" id="is_active"
                                           <?php echo $course['is_active'] ? 'checked' : ''; ?>>
                                    <label for="is_active" style="margin: 0;">Course is active and available for assignment</label>
                                </div>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                                <a href="manage_training_courses.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sidebar - Stats and Actions -->
                <div class="sidebar">
                    <div class="section-header">📊 Course Statistics</div>
                    <div class="section-content">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $course_stats['total_users'] ?? 0; ?></div>
                            <div class="stat-label">Assigned Users</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-number"><?php echo $course_stats['completed_users'] ?? 0; ?></div>
                            <div class="stat-label">Completed Users</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-number"><?php echo $course_stats['content_items'] ?? 0; ?></div>
                            <div class="stat-label">Content Items</div>
                        </div>

                        <div style="margin: 20px 0;">
                            <strong>Course Status:</strong>
                            <div style="margin-top: 8px;">
                                <span class="status-badge <?php echo $course['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>

                        <hr style="margin: 20px 0; border: none; border-top: 1px solid #dee2e6;">

                        <div>
                            <strong>Created:</strong><br>
                            <span style="color: #6c757d; font-size: 14px;">
                                <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                            </span>
                        </div>

                        <?php if ($course['updated_at'] && $course['updated_at'] !== $course['created_at']): ?>
                        <div style="margin-top: 8px;">
                            <strong>Last Updated:</strong><br>
                            <span style="color: #6c757d; font-size: 14px;">
                                <?php echo date('M j, Y', strtotime($course['updated_at'])); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="section-header">⚡ Quick Actions</div>
                    <div class="section-content">
                        <div class="quick-actions">
                            <form method="POST" action="" style="margin: 0;">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="submit" class="btn <?php echo $course['is_active'] ? 'btn-warning' : 'btn-success'; ?>" style="width: 100%;">
                                    <?php echo $course['is_active'] ? '🔒 Deactivate Course' : '🔓 Activate Course'; ?>
                                </button>
                            </form>

                            <a href="manage_course_content.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary" style="width: 100%;">
                                📚 Manage Content
                            </a>

                            <a href="manage_training_courses.php" class="btn btn-secondary" style="width: 100%;">
                                ← Back to Courses
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>