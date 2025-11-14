<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}

/**
 * Ensure automatic role management executes on every authenticated request.
 * training_helpers.php will self-load db_connect.php if $pdo isn’t set and
 * runs auto_manage_user_roles() for the current user.
 */
$th = __DIR__ . '/training_helpers.php';
if (file_exists($th)) {
    require_once $th;
}
?>