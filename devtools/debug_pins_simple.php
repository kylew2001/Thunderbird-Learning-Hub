<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$page_title = 'PIN Debug Tool';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .result { background: white; padding: 10px; margin: 10px 0; border: 1px solid #ddd; }
        .success { background: #d4edda; color: #155724; }
        .failure { background: #f8d7da; color: #721c24; }
        button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input, select { padding: 5px; margin: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>PIN Debug Tool</h1>

    <?php
    // Get all users
    try {
        $stmt = $pdo->query("SELECT id, name, pin, is_active FROM users ORDER BY name ASC");
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo '<div class="result failure">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $users = [];
    }
    ?>

    <div class="section">
        <h3>Current Users</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Active</th>
                <th>PIN Type</th>
                <th>PIN Preview</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo $user['is_active'] ? 'Yes' : 'No'; ?></td>
                    <td>
                        <?php
                        if (empty($user['pin'])) {
                            echo 'No PIN';
                        } elseif (strpos($user['pin'], '$2y$') === 0) {
                            echo 'Hashed';
                        } else {
                            echo 'Plaintext';
                        }
                        ?>
                    </td>
                    <td><?php echo !empty($user['pin']) ? substr($user['pin'], 0, 20) . '...' : 'None'; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h3>Test PIN Reset</h3>
        <form method="post">
            <label>
                User:
                <select name="user_id" required>
                    <option value="">Choose user...</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                New PIN:
                <input type="text" name="new_pin" pattern="[0-9]{4}" maxlength="4" placeholder="1111" required>
            </label>
            <button type="submit">Reset PIN</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['new_pin'])) {
            $user_id = intval($_POST['user_id']);
            $new_pin = trim($_POST['new_pin']);

            echo '<div class="result">';

            // Test PIN hashing
            echo "<h4>Testing PIN Hashing</h4>";
            echo "New PIN: " . htmlspecialchars($new_pin) . "<br>";

            $hashed_pin = password_hash($new_pin, PASSWORD_BCRYPT, ['cost' => 10]);
            echo "Generated Hash: " . substr($hashed_pin, 0, 40) . "...<br>";

            // Test verification
            $verify_result = password_verify($new_pin, $hashed_pin);
            echo "Immediate verification: " . ($verify_result ? 'SUCCESS' : 'FAILED') . "<br><br>";

            if ($verify_result) {
                // Update database
                try {
                    $stmt = $pdo->prepare("UPDATE users SET pin = ? WHERE id = ?");
                    $update_result = $stmt->execute([$hashed_pin, $user_id]);

                    if ($update_result) {
                        echo "Database update: SUCCESS<br>";

                        // Verify stored PIN
                        $verify_stmt = $pdo->prepare("SELECT name, pin FROM users WHERE id = ?");
                        $verify_stmt->execute([$user_id]);
                        $stored_user = $verify_stmt->fetch();

                        if ($stored_user) {
                            echo "Stored PIN: " . substr($stored_user['pin'], 0, 40) . "...<br>";

                            $stored_verify = password_verify($new_pin, $stored_user['pin']);
                            echo "Stored PIN verification: " . ($stored_verify ? 'SUCCESS' : 'FAILED') . "<br>";

                            if ($stored_verify) {
                                echo '<div class="result success">✅ PIN reset successful! User should now be able to login.</div>';
                            } else {
                                echo '<div class="result failure">❌ Critical error: PIN verification failed after storage.</div>';
                            }
                        }
                    } else {
                        echo "Database update: FAILED<br>";
                    }
                } catch (PDOException $e) {
                    echo "Database error: " . htmlspecialchars($e->getMessage()) . "<br>";
                }
            } else {
                echo '<div class="result failure">❌ PIN hashing failed - this is a PHP issue.</div>';
            }

            echo '</div>';
        }
        ?>
    </div>

    <div class="section">
        <h3>Test PIN Verification</h3>
        <form method="post">
            <label>
                User:
                <select name="test_user_id" required>
                    <option value="">Choose user...</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                PIN to test:
                <input type="text" name="test_pin" placeholder="Enter PIN" required>
            </label>
            <button type="submit">Test PIN</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_user_id']) && isset($_POST['test_pin'])) {
            $test_user_id = intval($_POST['test_user_id']);
            $test_pin = trim($_POST['test_pin']);

            echo '<div class="result">';

            // Find user
            $test_user = null;
            foreach ($users as $user) {
                if ($user['id'] == $test_user_id) {
                    $test_user = $user;
                    break;
                }
            }

            if ($test_user) {
                echo "Testing user: " . htmlspecialchars($test_user['name']) . "<br>";
                echo "Test PIN: " . htmlspecialchars($test_pin) . "<br>";

                if (strpos($test_user['pin'], '$2y$') === 0) {
                    $verify_result = password_verify($test_pin, $test_user['pin']);
                    echo "Verification result: " . ($verify_result ? 'SUCCESS' : 'FAILED') . "<br>";

                    if ($verify_result) {
                        echo '<div class="result success">✅ PIN is correct!</div>';
                    } else {
                        echo '<div class="result failure">❌ PIN is incorrect.</div>';
                    }
                } else {
                    $verify_result = ($test_pin === $test_user['pin']);
                    echo "Verification result: " . ($verify_result ? 'SUCCESS' : 'FAILED') . "<br>";

                    if ($verify_result) {
                        echo '<div class="result success">✅ PIN is correct!</div>';
                    } else {
                        echo '<div class="result failure">❌ PIN is incorrect.</div>';
                    }
                }
            } else {
                echo "User not found.";
            }

            echo '</div>';
        }
        ?>
    </div>

    <p><a href="manage_users.php">← Back to User Management</a></p>
</body>
</html>