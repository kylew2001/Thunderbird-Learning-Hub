<?php
/**
 * Debug script to test user assignment functionality
 * Created: 2025-11-06 22:02:00 UTC
 * Purpose: Test assignment functionality independently of web interface
 */

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/training_helpers.php';

// Test data
$test_course_id = 1; // Use an existing course ID
$test_user_ids = [1]; // Use an existing user ID
$test_assigned_by = 1; // Use admin user ID

echo "Testing assignment functionality...\n";
echo "Course ID: $test_course_id\n";
echo "User IDs: " . json_encode($test_user_ids) . "\n";
echo "Assigned by: $test_assigned_by\n";

// Check if course exists
$course_check = $pdo->prepare("SELECT id, name FROM training_courses WHERE id = ?");
$course_check->execute([$test_course_id]);
$course = $course_check->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    echo "ERROR: Course $test_course_id not found!\n";
    exit;
}

echo "Course found: " . $course['name'] . "\n";

// Check if users exist
foreach ($test_user_ids as $user_id) {
    $user_check = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
    $user_check->execute([$user_id]);
    $user = $user_check->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "ERROR: User $user_id not found!\n";
        exit;
    }

    echo "User found: " . $user['name'] . " (role: " . $user['role'] . ")\n";
}

// Check current assignments
$assignment_check = $pdo->prepare("
    SELECT user_id, course_id, assigned_by, assigned_date, status
    FROM user_training_assignments
    WHERE course_id = ? AND user_id IN (?, ?)
");
$assignment_check->execute([$test_course_id, $test_user_ids[0], $test_user_ids[0] ?? 0]);
$current_assignments = $assignment_check->fetchAll(PDO::FETCH_ASSOC);

echo "Current assignments:\n";
if (empty($current_assignments)) {
    echo "  No current assignments found\n";
} else {
    foreach ($current_assignments as $assignment) {
        echo "  User {$assignment['user_id']} -> Course {$assignment['course_id']} (status: {$assignment['status']})\n";
    }
}

// Test the assignment function
echo "\nCalling assign_course_to_users function...\n";
$result = assign_course_to_users($pdo, $test_course_id, $test_user_ids, $test_assigned_by);
echo "Function returned: $result\n";

// Check assignments after function call
$assignment_check->execute([$test_course_id, $test_user_ids[0], $test_user_ids[0] ?? 0]);
$new_assignments = $assignment_check->fetchAll(PDO::FETCH_ASSOC);

echo "Assignments after function call:\n";
if (empty($new_assignments)) {
    echo "  No assignments found (THIS IS THE PROBLEM!)\n";
} else {
    foreach ($new_assignments as $assignment) {
        echo "  User {$assignment['user_id']} -> Course {$assignment['course_id']} (status: {$assignment['status']}, assigned_by: {$assignment['assigned_by']}, assigned_date: {$assignment['assigned_date']})\n";
    }
}

echo "\nDebug complete.\n";
?>