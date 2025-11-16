<?php
require_once __DIR__ . '/system/config.php';

session_start();
session_unset();
session_destroy();
header('Location: /login.php');
exit;
?>
