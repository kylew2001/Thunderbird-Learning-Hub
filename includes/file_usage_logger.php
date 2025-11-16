<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../system/config.php';
}

// DO NOT DELETE LOGIC — TEMPORARY ONLY
$logFile = APP_LOGS . '/file_usage.log';
if (!is_dir(APP_LOGS)) {
    mkdir(APP_LOGS, 0755, true);
}

file_put_contents($logFile, ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown') . "\n", FILE_APPEND);
?>