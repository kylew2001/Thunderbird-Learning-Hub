<?php
require_once 'includes/db_connect.php';

echo "<h1>DEBUG USERS TABLE</h1>";

try {
    // Check table structure
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Table Structure:</h2>";
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Get all users
    $stmt = $pdo->query("SELECT * FROM users WHERE is_active = 1 ORDER BY id ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>All Users:</h2>";
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Role</th><th>Email</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check for duplicates by name
    $name_counts = [];
    foreach ($users as $user) {
        $name_counts[$user['name']] = ($name_counts[$user['name']] ?? 0) + 1;
    }

    echo "<h2>Duplicate Names:</h2>";
    foreach ($name_counts as $name => $count) {
        if ($count > 1) {
            echo "<p style='color: red;'>" . htmlspecialchars($name) . " appears " . $count . " times</p>";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>