<?php
/**
 * Update Bug Status Handler
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
    $status = $_POST['status'] ?? '';

    // Validate
    if ($bug_id > 0 && in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
        try {
            $stmt = $pdo->prepare("UPDATE bug_reports SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $bug_id]);

            // Redirect back with success message
            header('Location: bug_report.php?success=updated');
            exit;
        } catch (PDOException $e) {
            error_log("Error updating bug status: " . $e->getMessage());
            header('Location: bug_report.php?error=update_failed');
            exit;
        }
    }
}

// Redirect back if invalid request
header('Location: bug_report.php');
exit;
?>