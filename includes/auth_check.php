<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../system/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_INCLUDES . '/user_helpers.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: /login.php?expired=1');
    exit;
}

/**
 * Ensure automatic role management executes on every authenticated request.
 * training_helpers.php will self-load db_connect.php if $pdo isn’t set and
 * runs auto_manage_user_roles() for the current user.
 */
$training_helpers = APP_INCLUDES . '/training_helpers.php';
if (file_exists($training_helpers)) {
    require_once $training_helpers;
}
?>