<?php
// system/config.php
require_once __DIR__ . '/bootstrap.php';
/**
 * Application Configuration
 * Contains database settings, users, and global constants
 * Updated: 2025-11-05 (Complete hardcoded user removal - database-only authentication)
 *
 * FIXED: Removed all hardcoded user references that were interfering with database authentication
 * - Disabled $GLOBALS['USERS'] array completely
 * - System now fully database-driven for user management
 * - Resolved issue where PIN changes weren't working due to hardcoded fallback
 */

define('DB_HOST', 'sql100.infinityfree.com');
define('DB_NAME', 'if0_40307645_devknowledgebase');
define('DB_USER', 'if0_40307645');
define('DB_PASS', '1BcS944XiyGO');
define('SESSION_TIMEOUT', 7200);
define('MAX_FILE_SIZE', 20971520);
define('UPLOAD_PATH_IMAGES', 'uploads/images/');
define('UPLOAD_PATH_FILES', 'uploads/files/');
define('IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp']);
define('SITE_NAME', 'Thunderbird Learning Hub');

// Hardcoded User Accounts - DISABLED - Using database only
// $GLOBALS['USERS'] = [
//     '7982' => [
//         'id' => 1,
//         'name' => 'Admin Kyle',
//         'pin' => '7982',
//         'color' => '#4A90E2'
//     ],
//     '1234' => [
//         'id' => 2,
//         'name' => 'Cody Kirsten',
//         'pin' => '1234',
//         'color' => '#7B68EE'
//     ],
//     '5678' => [
//         'id' => 3,
//         'name' => 'Deegan Begovich',
//         'pin' => '5678',
//         'color' => '#E74C3C'
//     ]
// ];

// Empty array to prevent errors when referenced
$GLOBALS['USERS'] = [];

// Privacy Settings
$GLOBALS['PRIVACY_OPTIONS'] = [
    'public' => 'Public - All Users',
    'private' => 'Private - Only Me',
    'shared' => 'Shared - Specific Users'
];

// All Privacy Options (including restricted)
$GLOBALS['PRIVACY_OPTIONS_ALL'] = [
    'public' => 'Public - All Users',
    'private' => 'Private - Only Me',
    'shared' => 'Shared - Specific Users',
    'it_only' => 'Restricted - For IT Only'
];

// Visibility Settings for Categories and Subcategories (regular users)
$GLOBALS['VISIBILITY_OPTIONS'] = [
    'public' => 'Public - All Users',
    'hidden' => 'Hidden - Admin Only',
    'restricted' => 'Restricted - Specific Users'
];

// All Visibility Options (including IT only - Super Admins only)
$GLOBALS['VISIBILITY_OPTIONS_ALL'] = [
    'public' => 'Public - All Users',
    'hidden' => 'Hidden - Admin Only',
    'restricted' => 'Restricted - Specific Users',
    'it_only' => 'Restricted - For IT Only'
];

error_reporting(E_ALL);
ini_set('display_errors', 1);