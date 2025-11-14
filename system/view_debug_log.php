<?php
/**
 * Debug Log Viewer for Assignment Issues
 * This file displays the assignment debug log in a readable format
 *
 * Created: 2025-11-07 10:29:00 UTC
 * Purpose: View assignment debugging information
 */

require_once 'includes/auth_check.php';
require_once 'includes/user_helpers.php';

// Allow access if debug console is enabled by super admin or if user is admin
$debug_enabled = isset($_COOKIE['debug_console_enabled']) && $_COOKIE['debug_console_enabled'] === 'true';
if (!is_admin() && !$debug_enabled) {
    http_response_code(403);
    die('Access denied. Debug console must be enabled by super admin.');
}

$debug_log_file = __DIR__ . '/includes/assignment_debug.log';
$page_title = 'Assignment Debug Log';

// Get the last 50 lines of debug info
$debug_content = '';
$max_lines = 50;

if (file_exists($debug_log_file)) {
    $lines = file($debug_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $debug_content = implode("\n", array_slice($lines, -$max_lines));
} else {
    $debug_content = "Debug log file does not exist. No assignment attempts have been made yet.";
}

// Function to clear the log
if (isset($_GET['action']) && $_GET['action'] === 'clear_log') {
    if (file_exists($debug_log_file)) {
        unlink($debug_log_file);
    }
    header('Location: view_debug_log.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        .debug-content { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap; max-height: 500px; overflow-y: auto; }
        .debug-content:empty { color: #6c757d; font-style: italic; }
        .actions { margin: 15px 0; }
        .btn { background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; cursor: pointer; }
        .btn-danger { background: #dc3545; }
        .btn:hover { opacity: 0.9; }
        .log-info { background: #e9ecef; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
        .highlight { background: #fff3cd; padding: 2px 4px; border-radius: 2px; }
        .error { color: #dc3545; font-weight: bold; }
        .success { color: #28a745; font-weight: bold; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐛 Assignment Debug Log Viewer</h1>
            <p>This page shows debugging information from the assignment form submissions.</p>
        </div>

        <div class="log-info">
            <strong>Debug File:</strong> <?php echo $debug_log_file; ?><br>
            <strong>Status:</strong> <?php echo file_exists($debug_log_file) ? '✅ File exists (' . filesize($debug_log_file) . ' bytes)' : '❌ File not found'; ?><br>
            <strong>Last Modified:</strong> <?php echo file_exists($debug_log_file) ? date('Y-m-d H:i:s', filemtime($debug_log_file)) : 'N/A'; ?>
        </div>

        <div class="actions">
            <a href="manage_training_courses.php" class="btn">← Back to Course Management</a>
            <?php if (file_exists($debug_log_file) && filesize($debug_log_file) > 0): ?>
                <a href="?action=clear_log" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear the debug log?')">🗑️ Clear Log</a>
            <?php endif; ?>
        </div>

        <h3>Debug Output (Last <?php echo $max_lines; ?> lines)</h3>
        <div class="debug-content"><?php
            if (!empty($debug_content)) {
                // Highlight important parts
                $highlighted = $debug_content;
                $highlighted = preg_replace('/(ASSIGN_COURSE CASE TRIGGERED)/', '<span class="success">$1</span>', $highlighted);
                $highlighted = preg_replace('/(ERROR:.*)/', '<span class="error">$1</span>', $highlighted);
                $highlighted = preg_replace('/(DEBUG:.*POST data.*)/', '<span class="info">$1</span>', $highlighted);
                $highlighted = preg_replace('/(=== .* ===)/', '<span class="highlight">$1</span>', $highlighted);
                echo htmlspecialchars($highlighted);
            } else {
                echo "No debug information available. Try making an assignment attempt first.";
            }
        ?></div>

        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 14px;">
            <h4>How to Use:</h4>
            <ol>
                <li>Go to <a href="manage_training_courses.php">Training Course Management</a></li>
                <li>Click the <strong>"Assign"</strong> button for any course</li>
                <li>Select one or more users and click <strong>"Apply Changes"</strong></li>
                <li>Come back to this page to see the debug output</li>
            </ol>
            <p><strong>Note:</strong> This log shows exactly what happens when you submit the assignment form, including any errors.</p>
        </div>
    </div>
</body>
</html>