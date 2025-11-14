<?php
/**
 * Generic Page Template
 * Copy/rename this file to create new pages.
 * Then drop your custom logic + markup into the container below.
 */

require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';
require_once 'includes/user_helpers.php';

// Load training helpers if available (keeps behavior consistent with index.php)
if (file_exists('includes/training_helpers.php')) {
    require_once 'includes/training_helpers.php';
}

// Set the page title used by header.php
$page_title = 'Page Title Here';

// Include standard header (HTML <head>, nav, etc.)
include 'includes/header.php';
?>

<div class="container">
    <!--
        🚧 Blank Template

        Add your page-specific PHP/HTML inside this container.

        Examples of things you might drop here later:
        - Page-level heading
        - Forms
        - Tables / cards
        - Custom JS hooks

        Keep includes / auth / DB stuff at the top with the pattern above.
    -->
</div>

<?php
// Standard footer (includes your latest updates widget, bug report button, etc.)
include 'includes/footer.php';
?>