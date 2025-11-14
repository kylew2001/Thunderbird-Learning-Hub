<?php
/**
 * Delete Bug Report Handler
 * Only super users can delete closed bug reports
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

// Check if user is super user
if ($_SESSION['user_id'] != 1) {
    header('Location: index.php');
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bug_id = intval($_POST['bug_id'] ?? 0);

    // Validate
    if ($bug_id > 0) {
        try {
            // First check if the bug exists and is closed
            $stmt = $pdo->prepare("SELECT id, status FROM bug_reports WHERE id = ?");
            $stmt->execute([$bug_id]);
            $bug = $stmt->fetch();

            if ($bug && $bug['status'] === 'closed') {
                // Delete the bug report
                $stmt = $pdo->prepare("DELETE FROM bug_reports WHERE id = ?");
                $stmt->execute([$bug_id]);

                // Redirect back with success message
                header('Location: bug_report.php?success=deleted');
                exit;
            } else {
                // Bug doesn't exist or isn't closed
                header('Location: bug_report.php?error=not_closed');
                exit;
            }

        } catch (PDOException $e) {
            error_log("Error deleting bug: " . $e->getMessage());
            header('Location: bug_report.php?error=delete_failed');
            exit;
        }
    }
}

// Redirect back if invalid request
header('Location: bug_report.php');
exit;
?>