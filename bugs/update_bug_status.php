<?php
/**
 * Update Bug Status Handler
 */

$include_paths = [
    __DIR__ . '/includes',
    __DIR__ . '/../includes',
    dirname(__DIR__) . '/includes',
];

$includes_dir = null;
foreach ($include_paths as $path) {
    if (file_exists($path . '/auth_check.php') && file_exists($path . '/db_connect.php')) {
        $includes_dir = $path;
        break;
    }
}

if ($includes_dir === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Required include files missing']);
    exit;
}

require_once $includes_dir . '/auth_check.php';
require_once $includes_dir . '/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

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