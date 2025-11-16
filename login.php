<?php
/**
 * Login Page
 * Handles user authentication with database-driven system
 * Updated: 2025-11-05 (Removed hardcoded user fallback - database-only authentication)
 *
 * FIXED: Complete removal of hardcoded user authentication
 * - Now fully database-driven with proper PIN verification
 * - Supports both hashed (bcrypt) and plaintext PINs during transition
 * - Proper session management and brute force protection
 */

require_once __DIR__ . '/system/config.php';
session_start();
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    if (time() - $_SESSION['login_time'] <= SESSION_TIMEOUT) {
        header('Location: /index.php');
        exit;
    }
}
$error_message = '';
$attempts_remaining = 10; // Show warning at 3 attempts remaining

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';

    // Simple session-based attempt tracking for now
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_attempts_time'] = time();
    }

    // Reset attempts if 30 minutes have passed
    if (time() - $_SESSION['login_attempts_time'] > 1800) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_attempts_time'] = time();
    }

    // Check if too many attempts
    if ($_SESSION['login_attempts'] >= 10) {
        $time_since_first = time() - $_SESSION['login_attempts_time'];
        if ($time_since_first < 120) {
            $wait_seconds = 120 - $time_since_first;
            $wait_minutes = ceil($wait_seconds / 60);
            $error_message = "🔒 Too many login attempts. Please wait $wait_minutes minute(s) before trying again.";
        } else {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_attempts_time'] = time();
        }
    }

    if (empty($error_message)) {
        // Try database authentication
        try {
            require_once APP_INCLUDES . '/db_connect.php';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE is_active = 1");
            $stmt->execute();
            $all_users = $stmt->fetchAll();

            $user_found = false;
            $user = null;

            // Find user by checking PIN
            foreach ($all_users as $db_user) {
                if (empty($db_user['pin'])) continue;

                // Check if PIN is hashed (bcrypt starts with $2y$)
                if (strpos($db_user['pin'], '$2y$') === 0) {
                    // Hashed PIN - try direct verify first
                    if (password_verify($entered_pin, $db_user['pin'])) {
                        $user = $db_user;
                        $user_found = true;
                        break;
                    }
                } else {
                    // Plaintext PIN - direct comparison
                    if ($entered_pin === $db_user['pin']) {
                        $user = $db_user;
                        $user_found = true;
                        break;
                    }
                }
            }

            if ($user_found) {
                // SUCCESS: Login successful!
                $update_stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->execute([$user['id']]);

                // Reset session attempts
                $_SESSION['login_attempts'] = 0;
                $_SESSION['login_attempts_time'] = time();

                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_color'] = $user['color'];
                $_SESSION['user_role'] = $user['role'];

                header('Location: /index.php');
                exit;
            } else {
                // FAILED: Login failed
                $_SESSION['login_attempts']++;
                $attempts_remaining = max(0, 10 - $_SESSION['login_attempts']);

                if ($_SESSION['login_attempts'] >= 10) {
                    $_SESSION['login_attempts_time'] = time();
                    $error_message = "🔒 Too many failed login attempts. Please wait 2 minutes before trying again.";
                } elseif ($attempts_remaining <= 3 && $attempts_remaining > 0) {
                    $error_message = "❌ Invalid PIN. ⚠️ $attempts_remaining attempt(s) remaining before 2-minute cooldown.";
                } else {
                    $error_message = "❌ Invalid PIN. Please try again.";
                }
            }

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $error_message = 'Database authentication error. Please contact support.';
        }

        // No hardcoded fallback - database only
        if (!$user_found && empty($error_message)) {
            $_SESSION['login_attempts']++;
            $attempts_remaining = max(0, 10 - $_SESSION['login_attempts']);
            if ($_SESSION['login_attempts'] >= 10) {
                $_SESSION['login_attempts_time'] = time();
                $error_message = "🔒 Too many failed login attempts. Please wait 2 minutes before trying again.";
            } elseif ($attempts_remaining <= 3 && $attempts_remaining > 0) {
                $error_message = "❌ Invalid PIN. ⚠️ $attempts_remaining attempt(s) remaining before 2-minute cooldown.";
            } else {
                $error_message = "❌ Invalid PIN. Please try again.";
            }
        }
    }
}

if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $error_message = 'Your session has expired. Please log in again.';
}
$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1 class="login-title"><?php echo SITE_NAME; ?></h1>
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="pin" class="form-label">Enter PIN</label>
                    <input type="password" id="pin" name="pin" class="pin-input" maxlength="4" pattern="[0-9]{4}" placeholder="••••" required autofocus inputmode="numeric">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
        </div>
    </div>
</body>
</html>