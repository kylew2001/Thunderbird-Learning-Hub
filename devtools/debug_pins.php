<?php
/**
 * PIN Debug Tool
 * Helps diagnose PIN hashing and verification issues
 * Only accessible to admin users
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$page_title = 'PIN Debug Tool';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .debug-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .debug-result {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .success { background-color: #d4edda; color: #155724; }
        .failure { background-color: #f8d7da; color: #721c24; }
        .info { background-color: #d1ecf1; color: #0c5460; }
        .btn-test { background: #17a2b8; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PIN Debug Tool</h1>
        <p>This tool helps diagnose PIN hashing and verification issues.</p>

        <?php
        // Get all users from database
        try {
            $stmt = $pdo->query("SELECT id, name, pin, is_active FROM users ORDER BY name ASC");
            $users = $stmt->fetchAll();
        } catch (PDOException $e) {
            echo '<div class="error-message">Error fetching users: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $users = [];
        }
        ?>

        <div class="debug-section">
            <h3>Current Users and PIN Status</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #e9ecef;">
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">User ID</th>
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Name</th>
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Active</th>
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">PIN Type</th>
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">PIN Preview</th>
                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Test PIN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ddd;"><?php echo $user['id']; ?></td>
                            <td style="padding: 8px; border: 1px solid #ddd;"><?php echo htmlspecialchars($user['name']); ?></td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <?php echo $user['is_active'] ? '✅ Yes' : '❌ No'; ?>
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <?php
                                if (empty($user['pin'])) {
                                    echo '<span style="color: red;">No PIN</span>';
                                } elseif (strpos($user['pin'], '$2y$') === 0) {
                                    echo '<span style="color: green;">Hashed</span>';
                                } else {
                                    echo '<span style="color: orange;">Plaintext</span>';
                                }
                                ?>
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace;">
                                <?php
                                if (!empty($user['pin'])) {
                                    echo substr($user['pin'], 0, 25) . '...';
                                } else {
                                    echo '<em>None</em>';
                                }
                                ?>
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="test_user_id" value="<?php echo $user['id']; ?>">
                                    <input type="text" name="test_pin" placeholder="Enter PIN to test" style="width: 80px; padding: 4px;" required>
                                    <button type="submit" class="btn-test">Test</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        // Handle PIN testing
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_user_id']) && isset($_POST['test_pin'])) {
            $test_user_id = intval($_POST['test_user_id']);
            $test_pin = trim($_POST['test_pin']);

            echo '<div class="debug-section">';
            echo '<h3>PIN Verification Test Results</h3>';

            // Find the user
            $test_user = null;
            foreach ($users as $user) {
                if ($user['id'] == $test_user_id) {
                    $test_user = $user;
                    break;
                }
            }

            if ($test_user) {
                echo '<div class="debug-result info">';
                echo "<strong>Testing User:</strong> " . htmlspecialchars($test_user['name']) . " (ID: {$test_user['id']})<br>";
                echo "<strong>Test PIN:</strong> " . htmlspecialchars($test_pin) . "<br>";
                echo "<strong>Stored PIN Type:</strong> ";
                if (strpos($test_user['pin'], '$2y$') === 0) {
                    echo "Hashed (bcrypt)<br>";
                    echo "<strong>Stored PIN:</strong> " . substr($test_user['pin'], 0, 30) . "...<br>";

                    // Test the verification
                    $verify_result = password_verify($test_pin, $test_user['pin']);
                    echo "<strong>Verification Result:</strong> " . ($verify_result ? '✅ SUCCESS' : '❌ FAILED') . "<br>";

                    if ($verify_result) {
                        echo '<div class="debug-result success">PIN is correct! User should be able to login.</div>';
                    } else {
                        echo '<div class="debug-result failure">PIN verification failed. This PIN will not work for login.</div>';

                        // Let's also test some common PINs for debugging
                        echo '<h4>Testing Common PINs:</h4>';
                        $common_pins = ['1111', '2222', '1234', '0000', $test_user['pin']]; // Include the actual PIN for comparison
                        foreach ($common_pins as $common_pin) {
                            if ($common_pin === $test_pin) continue; // Skip the one we already tested

                            $common_verify = password_verify($common_pin, $test_user['pin']);
                            echo "PIN '$common_pin': " . ($common_verify ? '✅ SUCCESS' : '❌ FAILED') . "<br>";
                        }
                    }
                } else {
                    echo "Plaintext<br>";
                    echo "<strong>Stored PIN:</strong> " . htmlspecialchars($test_user['pin']) . "<br>";

                    // Test the verification
                    $verify_result = ($test_pin === $test_user['pin']);
                    echo "<strong>Verification Result:</strong> " . ($verify_result ? '✅ SUCCESS' : '❌ FAILED') . "<br>";

                    if ($verify_result) {
                        echo '<div class="debug-result success">PIN is correct! User should be able to login.</div>';
                    } else {
                        echo '<div class="debug-result failure">PIN verification failed. This PIN will not work for login.</div>';
                    }
                }
                echo '</div>';
            } else {
                echo '<div class="debug-result failure">User not found.</div>';
            }

            echo '</div>';
        }
        ?>

        <div class="debug-section">
            <h3>Test PIN Hashing</h3>
            <form method="post">
                <label style="display: block; margin-bottom: 10px;">
                    Enter a 4-digit PIN to hash:
                    <input type="text" name="hash_pin" pattern="[0-9]{4}" maxlength="4" placeholder="1111" style="margin-left: 10px; padding: 4px;" required>
                </label>
                <button type="submit" class="btn-test">Generate Hash</button>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hash_pin'])) {
                $hash_pin = trim($_POST['hash_pin']);
                if (preg_match('/^[0-9]{4}$/', $hash_pin)) {
                    $generated_hash = password_hash($hash_pin, PASSWORD_BCRYPT, ['cost' => 10]);
                    $verify_back = password_verify($hash_pin, $generated_hash);

                    echo '<div class="debug-result info">';
                    echo "<strong>Original PIN:</strong> " . htmlspecialchars($hash_pin) . "<br>";
                    echo "<strong>Generated Hash:</strong> " . htmlspecialchars($generated_hash) . "<br>";
                    echo "<strong>Hash Length:</strong> " . strlen($generated_hash) . " characters<br>";
                    echo "<strong>Verification (original PIN):</strong> " . ($verify_back ? '✅ SUCCESS' : '❌ FAILED') . "<br>";

                    // Test that it fails with wrong PIN
                    $wrong_verify = password_verify('9999', $generated_hash);
                    echo "<strong>Verification (wrong PIN 9999):</strong> " . ($wrong_verify ? '❌ UNEXPECTED SUCCESS' : '✅ CORRECTLY FAILED') . "<br>";
                    echo '</div>';
                }
            }
            ?>
        </div>

        <div class="debug-section">
            <h3>Live PIN Reset Test</h3>
            <p>This will test the actual PIN reset process used in manage_users.php</p>
            <form method="post">
                <label style="display: block; margin-bottom: 10px;">
                    Select User:
                    <select name="reset_user_id" style="margin-left: 10px; padding: 4px;" required>
                        <option value="">Choose a user...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="display: block; margin-bottom: 10px;">
                    New PIN:
                    <input type="text" name="reset_pin" pattern="[0-9]{4}" maxlength="4" placeholder="1111" style="margin-left: 10px; padding: 4px;" required>
                </label>
                <button type="submit" class="btn-test" style="background: #28a745;">Test Reset Process</button>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_id']) && isset($_POST['reset_pin'])) {
                $reset_user_id = intval($_POST['reset_user_id']);
                $reset_pin = trim($_POST['reset_pin']);

                echo '<div class="debug-result info">';

                // Find the user
                $reset_user = null;
                foreach ($users as $user) {
                    if ($user['id'] == $reset_user_id) {
                        $reset_user = $user;
                        break;
                    }
                }

                if ($reset_user) {
                    echo "<strong>Testing Reset Process:</strong><br>";
                    echo "User: " . htmlspecialchars($reset_user['name']) . " (ID: {$reset_user['id'])<br>";
                    echo "New PIN: " . htmlspecialchars($reset_pin) . "<br><br>";

                    // Simulate the exact process from manage_users.php
                    $hashed_pin = password_hash($reset_pin, PASSWORD_BCRYPT, ['cost' => 10]);

                    echo "<strong>Step 1 - PIN Hashing:</strong><br>";
                    echo "Generated Hash: " . substr($hashed_pin, 0, 40) . "...<br>";
                    echo "Hash Length: " . strlen($hashed_pin) . " characters<br><br>";

                    // Test the hash immediately
                    $immediate_verify = password_verify($reset_pin, $hashed_pin);
                    echo "<strong>Step 2 - Immediate Verification:</strong><br>";
                    echo "Result: " . ($immediate_verify ? '✅ SUCCESS' : '❌ FAILED') . "<br><br>";

                    if ($immediate_verify) {
                        echo "<strong>Step 3 - Database Update:</strong><br>";
                        try {
                            $stmt = $pdo->prepare("UPDATE users SET pin = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?");
                            $update_result = $stmt->execute([$hashed_pin, $reset_user_id]);

                            if ($update_result) {
                                echo "Database update: ✅ SUCCESS<br><br>";

                                // Verify it was stored correctly
                                $verify_stmt = $pdo->prepare("SELECT name, pin FROM users WHERE id = ?");
                                $verify_stmt->execute([$reset_user_id]);
                                $stored_user = $verify_stmt->fetch();

                                if ($stored_user) {
                                    echo "<strong>Step 4 - Verification After Storage:</strong><br>";
                                    echo "Stored PIN: " . substr($stored_user['pin'], 0, 40) . "...<br>";

                                    // Test the stored PIN
                                    $stored_verify = password_verify($reset_pin, $stored_user['pin']);
                                    echo "Stored PIN verification: " . ($stored_verify ? '✅ SUCCESS' : '❌ FAILED') . "<br><br>";

                                    if ($stored_verify) {
                                        echo '<div class="debug-result success">🎉 PIN reset test completed successfully! The user should now be able to login.</div>';
                                    } else {
                                        echo '<div class="debug-result failure">❌ Critical error: PIN verification failed after database storage. This indicates a database or encoding issue.</div>';
                                    }
                                } else {
                                    echo '<div class="debug-result failure">❌ Error: Could not retrieve user after update.</div>';
                                }
                            } else {
                                echo '<div class="debug-result failure">❌ Database update failed.</div>';
                            }
                        } catch (PDOException $e) {
                            echo '<div class="debug-result failure">❌ Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    } else {
                        echo '<div class="debug-result failure">❌ Critical error: PIN hash verification failed immediately. This is a PHP issue.</div>';
                    }
                } else {
                    echo '<div class="debug-result failure">User not found.</div>';
                }

                echo '</div>';
            }
            ?>
        </div>

        <div class="debug-section">
            <h3>System Information</h3>
            <div class="debug-result info">
                <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?><br>
                <strong>password_hash() available:</strong> <?php echo function_exists('password_hash') ? '✅ Yes' : '❌ No'; ?><br>
                <strong>password_verify() available:</strong> <?php echo function_exists('password_verify') ? '✅ Yes' : '❌ No'; ?><br>
                <strong>BCRYPT Algorithm:</strong> <?php echo defined('PASSWORD_BCRYPT') ? '✅ Available' : '❌ Not Available'; ?><br>
            </div>
        </div>

        <p><a href="manage_users.php" class="btn btn-secondary">← Back to User Management</a></p>
    </div>
</body>
</html>