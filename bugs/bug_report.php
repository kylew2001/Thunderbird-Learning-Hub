<?php
/**
 * Bug Report System - Submit and View Bug Reports
 * Accessible to all users with role-based permissions
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

$page_title = 'Bug Report System';

// Check if user is super user
$is_super_user = ($_SESSION['user_id'] == 1);
$current_user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $page = trim($_POST['page'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $steps_to_reproduce = trim($_POST['steps_to_reproduce'] ?? '');
    $expected_behavior = trim($_POST['expected_behavior'] ?? '');
    $actual_behavior = trim($_POST['actual_behavior'] ?? '');

    $errors = [];

    // Validate only required fields
    if (empty($title)) {
        $errors[] = 'Bug title is required';
    }
    if (empty($description)) {
        $errors[] = 'Description is required';
    }

    // Handle optional fields - if empty, use space as placeholder
    if (empty($page)) {
        $page = ' '; // Space placeholder for empty page URL
    }
    if (empty($steps_to_reproduce)) {
        $steps_to_reproduce = ' '; // Space placeholder for empty steps
    }
    if (empty($expected_behavior)) {
        $expected_behavior = ' '; // Space placeholder for empty expected behavior
    }
    if (empty($actual_behavior)) {
        $actual_behavior = ' '; // Space placeholder for empty actual behavior
    }

    if (empty($errors)) {
        try {
            // Insert bug report into database
            $stmt = $pdo->prepare("
                INSERT INTO bug_reports (
                    title, description, page_url, priority, steps_to_reproduce,
                    expected_behavior, actual_behavior, user_id, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
            ");

            $stmt->execute([
                $title,
                $description,
                $page,
                $priority,
                $steps_to_reproduce,
                $expected_behavior,
                $actual_behavior,
                $_SESSION['user_id']
            ]);

            $success_message = "Bug report submitted successfully! Report ID: " . $pdo->lastInsertId();

            // Clear form data
            $_POST = [];

        } catch (PDOException $e) {
            $error_message = "Error submitting bug report: " . $e->getMessage();
        }
    }
}

// Get existing bug reports
try {
    if ($is_super_user) {
        // Super users can see all bug reports
        $stmt = $pdo->query("SELECT * FROM bug_reports ORDER BY created_at DESC");
        $bug_reports = $stmt->fetchAll();
    } else {
        // Regular users can only see their own bug reports
        $stmt = $pdo->prepare("SELECT * FROM bug_reports WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$current_user_id]);
        $bug_reports = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $bug_reports = [];
    error_log("Error fetching bug reports: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container">
    <div class="flex-between mb-20">
        <h2 style="font-size: 24px; color: #2d3748;">
            🐛 Bug Report System
            <?php if (!$is_super_user): ?>
                <span style="font-size: 14px; color: #666; font-weight: normal;">(Your Reports Only)</span>
            <?php endif; ?>
        </h2>
        <a href="index.php" class="btn btn-secondary">← Back to Home</a>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
        <div class="success-message">
            Bug status updated successfully!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'update_failed'): ?>
        <div class="error-message">
            Failed to update bug status. Please try again.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
        <div class="success-message">
            Bug report deleted successfully!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'not_closed'): ?>
        <div class="error-message">
            Only closed bug reports can be deleted.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>
        <div class="error-message">
            Failed to delete bug report. Please try again.
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <strong>Please fix the following errors:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Bug Report Form -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3>Submit New Bug Report</h3>
        </div>
        <div class="card-content">
            <form method="POST" action="bug_report.php">
                <div class="form-group">
                    <label for="title" class="form-label">Bug Title *</label>
                    <input type="text" id="title" name="title" class="form-input"
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                           placeholder="Brief description of the bug" required>
                </div>

                <div class="form-group">
                    <label for="page" class="form-label">Page URL (optional)</label>
                    <input type="url" id="page" name="page" class="form-input"
                           value="<?php echo htmlspecialchars($_POST['page'] ?? ''); ?>"
                           placeholder="https://your-site.com/page.php (leave blank if unknown)">
                </div>

                <div class="form-group">
                    <label for="priority" class="form-label">Priority</label>
                    <select id="priority" name="priority" class="form-input">
                        <option value="low" <?php echo (($_POST['priority'] ?? '') === 'low') ? 'selected' : ''; ?>>🟢 Low</option>
                        <option value="medium" <?php echo (($_POST['priority'] ?? 'medium') === 'medium') ? 'selected' : ''; ?>>🟡 Medium</option>
                        <option value="high" <?php echo (($_POST['priority'] ?? '') === 'high') ? 'selected' : ''; ?>>🟠 High</option>
                        <option value="critical" <?php echo (($_POST['priority'] ?? '') === 'critical') ? 'selected' : ''; ?>>🔴 Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Description *</label>
                    <textarea id="description" name="description" class="form-input" rows="4"
                              placeholder="Detailed description of the bug" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="steps_to_reproduce" class="form-label">Steps to Reproduce (optional)</label>
                    <textarea id="steps_to_reproduce" name="steps_to_reproduce" class="form-input" rows="4"
                              placeholder="1. Go to...&#10;2. Click on...&#10;3. Expected...&#10;4. Actual... (leave blank if unknown)"><?php echo htmlspecialchars($_POST['steps_to_reproduce'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="expected_behavior" class="form-label">Expected Behavior (optional)</label>
                    <textarea id="expected_behavior" name="expected_behavior" class="form-input" rows="3"
                              placeholder="What should have happened (leave blank if unknown)"><?php echo htmlspecialchars($_POST['expected_behavior'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="actual_behavior" class="form-label">Actual Behavior (optional)</label>
                    <textarea id="actual_behavior" name="actual_behavior" class="form-input" rows="3"
                              placeholder="What actually happened (leave blank if unknown)"><?php echo htmlspecialchars($_POST['actual_behavior'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">🐛 Submit Bug Report</button>
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Bug Reports -->
    <div class="card">
        <div class="card-header">
            <h3>
                Existing Bug Reports (<?php echo count($bug_reports); ?> total)
                <?php if (!$is_super_user): ?>
                    <span style="font-size: 12px; color: #666; font-weight: normal;">- Your reports only</span>
                <?php else: ?>
                    <span style="font-size: 12px; color: #666; font-weight: normal;">- All users</span>
                <?php endif; ?>
            </h3>
        </div>
        <div class="card-content">
            <?php if (empty($bug_reports)): ?>
                <p style="color: #666; font-style: italic;">No bug reports submitted yet.</p>
            <?php else: ?>
                <?php foreach ($bug_reports as $report): ?>
                    <div class="bug-report-item" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 15px; background: #f8f9fa;">
                        <div class="bug-header" style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h4 style="margin: 0 0 8px 0; color: #2d3748;">
                                    #<?php echo $report['id']; ?>: <?php echo htmlspecialchars($report['title']); ?>
                                </h4>
                                <div style="font-size: 12px; color: #666;">
                                    <?php
                                    $priority_colors = [
                                        'low' => '#48bb78',
                                        'medium' => '#ed8936',
                                        'high' => '#f56565',
                                        'critical' => '#e53e3e'
                                    ];
                                    $priority_labels = [
                                        'low' => '🟢 Low',
                                        'medium' => '🟡 Medium',
                                        'high' => '🟠 High',
                                        'critical' => '🔴 Critical'
                                    ];

                                    // Get reporter name - fallback to "Admin Kyle" if user ID 1
                                    $reporter_name = 'Unknown';
                                    if ($report['user_id'] == 1) {
                                        $reporter_name = 'Admin Kyle';
                                    } elseif (!empty($report['reporter_name'])) {
                                        $reporter_name = $report['reporter_name'];
                                    }
                                    ?>
                                    <span style="color: <?php echo $priority_colors[$report['priority']]; ?>; font-weight: bold;">
                                        <?php echo $priority_labels[$report['priority']]; ?>
                                    </span>
                                    <span style="margin: 0 10px;">•</span>
                                    <span>Reported by: <?php echo htmlspecialchars($reporter_name); ?></span>
                                    <span style="margin: 0 10px;">•</span>
                                    <span><?php echo date('M j, Y \a\t g:i A', strtotime($report['created_at'])); ?></span>
                                </div>
                            </div>
                            <div>
                                <?php
                                $status_colors = [
                                    'open' => '#f56565',
                                    'in_progress' => '#ed8936',
                                    'resolved' => '#48bb78',
                                    'closed' => '#718096'
                                ];
                                $status_labels = [
                                    'open' => '🔴 Open',
                                    'in_progress' => '🟡 In Progress',
                                    'resolved' => '🟢 Resolved',
                                    'closed' => '⚫ Closed'
                                ];
                                ?>
                                <span style="background: <?php echo $status_colors[$report['status']]; ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                                    <?php echo $status_labels[$report['status']]; ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($report['page_url']) && trim($report['page_url']) !== ''): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Page:</strong>
                                <a href="<?php echo htmlspecialchars($report['page_url']); ?>" target="_blank" style="color: #4299e1; text-decoration: none;">
                                    <?php echo htmlspecialchars($report['page_url']); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 10px;">
                            <strong>Description:</strong>
                            <p style="margin: 5px 0; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                        </div>

                        <?php if (!empty($report['steps_to_reproduce']) && trim($report['steps_to_reproduce']) !== ''): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Steps to Reproduce:</strong>
                                <p style="margin: 5px 0; line-height: 1.5; white-space: pre-line;"><?php echo htmlspecialchars($report['steps_to_reproduce']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($report['expected_behavior']) && trim($report['expected_behavior']) !== ''): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Expected Behavior:</strong>
                                <p style="margin: 5px 0; line-height: 1.5; white-space: pre-line;"><?php echo htmlspecialchars($report['expected_behavior']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($report['actual_behavior']) && trim($report['actual_behavior']) !== ''): ?>
                            <div style="margin-bottom: 15px;">
                                <strong>Actual Behavior:</strong>
                                <p style="margin: 5px 0; line-height: 1.5; white-space: pre-line;"><?php echo htmlspecialchars($report['actual_behavior']); ?></p>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php if ($is_super_user): ?>
                                <form method="POST" action="update_bug_status.php" style="display: inline;">
                                    <input type="hidden" name="bug_id" value="<?php echo $report['id']; ?>">
                                    <select name="status" class="form-input" style="width: auto; font-size: 12px; padding: 4px 8px;">
                                        <option value="open" <?php echo $report['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="in_progress" <?php echo $report['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $report['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-small">Update Status</button>
                                </form>

                                <?php if ($report['status'] === 'closed'): ?>
                                    <form method="POST" action="delete_bug.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this closed bug report? This cannot be undone.');">
                                        <input type="hidden" name="bug_id" value="<?php echo $report['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">🗑️ Delete</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Regular users can only view status, not change it -->
                                <span style="color: #666; font-size: 12px; padding: 4px 8px; background: #f7fafc; border-radius: 4px;">
                                    Status: <?php echo $status_labels[$report['status']]; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.bug-report-item {
    transition: box-shadow 0.2s ease;
}

.bug-report-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #2d3748;
}

.form-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
}

.form-input:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.card-header {
    background: #f7fafc;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    border-radius: 8px 8px 0 0;
}

.card-header h3 {
    margin: 0;
    color: #2d3748;
}

.card-content {
    padding: 20px;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}
</style>

<?php include 'includes/footer.php'; ?>