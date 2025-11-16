<?php
echo "Basic page loaded successfully!<br>";

// Test if includes folder is accessible
if (file_exists('includes/auth_check.php')) {
    echo "auth_check.php exists<br>";
    try {
        require_once __DIR__ . '/../includes/auth_check.php';
        echo "auth_check.php loaded successfully<br>";
    } catch (Exception $e) {
        echo "Error loading auth_check.php: " . $e->getMessage() . "<br>";
    }
} else {
    echo "auth_check.php NOT FOUND<br>";
}

// Test database connection
try {
    require_once __DIR__ . '/../includes/db_connect.php';
    echo "db_connect.php loaded successfully<br>";
    if (isset($pdo)) {
        echo "Database connection established<br>";
    } else {
        echo "Database connection NOT established<br>";
    }
} catch (Exception $e) {
    echo "Error with database: " . $e->getMessage() . "<br>";
}

echo "End of basic test";
?>