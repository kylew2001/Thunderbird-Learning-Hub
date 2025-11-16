<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../system/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_INCLUDES . '/file_usage_logger.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php

$host = $_SERVER['HTTP_HOST'] ?? '';
$is_dev = stripos($host, 'devknowledgebase.xo.je') === 0;

$full_title = isset($page_title)
    ? htmlspecialchars($page_title) . ' - ' . SITE_NAME
    : SITE_NAME;

// Add prefix for dev domain
if ($is_dev) {
    $full_title = 'Dev - ' . $full_title;
}
?>
<title><?php echo $full_title; ?></title>

    <link rel="stylesheet" href="/assets/css/style.css?v=20251112">

<?php
$host = $_SERVER['HTTP_HOST'] ?? '';

$is_dev  = stripos($host, 'devknowledgebase.xo.je') === 0;
$is_prod = stripos($host, 'svsknowledgebase.xo.je') === 0;

// DEV FAVICON
if ($is_dev): ?>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/dev-favicon.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/dev-favicon.png?v=2">
    <link rel="shortcut icon" href="/assets/images/dev-favicon.png?v=2">

<?php
// PROD FAVICON
elseif ($is_prod): ?>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/prod-favicon.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/prod-favicon.png?v=1">
    <link rel="shortcut icon" href="/assets/images/prod-favicon.png?v=1">
<?php endif; ?>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1><?php echo SITE_NAME; ?></h1>
            <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
                <?php
                require_once APP_INCLUDES . '/user_helpers.php';
                require_once APP_INCLUDES . '/db_connect.php';

                if (file_exists(APP_INCLUDES . '/training_helpers.php')) {
                    require_once APP_INCLUDES . '/training_helpers.php';
                }

                // Fallback function in case training_helpers.php doesn't exist
                if (!function_exists('is_training_user')) {
                    function is_training_user() {
                        return isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'training';
                    }
                }

                // Fallback function for progress calculation
                if (!function_exists('get_overall_training_progress')) {
                    function get_overall_training_progress($pdo, $user_id) {
                        return [
                            'percentage' => 0,
                            'completed_items' => 0,
                            'total_items' => 0,
                            'in_progress_items' => 0,
                            'completed_courses' => 0,
                            'total_courses' => 0
                        ];
                    }
                }

                // Fallback function for progress bar visibility
                if (!function_exists('should_show_training_progress')) {
                    function should_show_training_progress($pdo, $user_id) {
                        return isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'training';
                    }
                }

                // Get admin notification counts
                $pending_edit_requests = 0;
                $unresolved_bugs = 0;

                if (is_admin()) {
                    try {
                        // Get pending edit requests count
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM edit_requests WHERE status = 'pending'");
                        $result = $stmt->fetch();
                        $pending_edit_requests = $result['count'] ?? 0;

                        // Get unresolved bugs count
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM bug_reports WHERE status != 'resolved'");
                        $result = $stmt->fetch();
                        $unresolved_bugs = $result['count'] ?? 0;
                        
                        // NEW: unassigned quizzes count (is_assigned = 0)
        $stmt = $pdo->query("SELECT COUNT(*) AS count FROM training_quizzes WHERE is_assigned = 0");
        $result = $stmt->fetch();
        $unassigned_quizzes = $result['count'] ?? 0;
                        
                    } catch (PDOException $e) {
                        // If tables don't exist, just show 0
                        $pending_edit_requests = 0;
                        $unresolved_bugs = 0;
                    }
                }
                ?>
                <div class="user-info" style="display: flex; align-items: center; gap: 12px;">
                    <!-- Admin Notifications -->
                    <?php if (is_admin() && $pending_edit_requests > 0): ?>
                    <a href="/admin/manage_edit_requests.php" style="display: flex; align-items: center; gap: 4px; background: #ffc107; color: #212529; padding: 4px 8px; border-radius: 12px; text-decoration: none; font-size: 11px; font-weight: 500;" title="Pending Edit Requests">
                        <span>📝</span>
                        <span><?php echo $pending_edit_requests; ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <!-- NEW: Unassigned Quizzes -->
<?php if (is_admin() && $unassigned_quizzes > 0): ?>
<a href="/admin/manage_quizzes.php?filter=unassigned" style="display: flex; align-items: center; gap: 4px; background: #6f42c1; color: white; padding: 4px 8px; border-radius: 12px; text-decoration: none; font-size: 11px; font-weight: 500;" title="Unassigned Quizzes">
    <span>❗</span>
    <span><?php echo $unassigned_quizzes; ?></span>
</a>
<?php endif; ?>

                    <?php if (is_super_admin() && $unresolved_bugs > 0): ?>
                    <a href="/bugs/bug_report.php" style="display: flex; align-items: center; gap: 4px; background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; text-decoration: none; font-size: 11px; font-weight: 500;" title="Unresolved Bugs">
                        <span>🐛</span>
                        <span><?php echo $unresolved_bugs; ?></span>
                    </a>
                    <?php endif; ?>

                    <!-- Admin Menu Toggle (Admin Only) -->
                    <?php if (is_admin()): ?>
                    <div class="developer-menu-toggle" onclick="toggleDevMenu()" title="Admin Menu">⚙️</div>
                    <?php endif; ?>

                    <!-- User Info -->
                    <span class="user-name" style="color: <?php echo htmlspecialchars(get_user_color()); ?>">
                        <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        <?php
                        $role_colors = [
                            'Super Admin' => '#dc3545',
                            'Admin' => '#28a745',
                            'User' => '#6c757d',
                            'Training' => '#17a2b8'
                        ];
                        $current_role = get_user_role_display();
                        $role_color = $role_colors[$current_role] ?? '#6c757d';
                        ?>
                        <span style="background: <?php echo $role_color; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;"><?php echo htmlspecialchars($current_role); ?></span>
                    </span>

                    <!-- Training Progress Bar (Training Users and Admins with Active Training) -->
                    <?php
    // Allow pages to suppress the header's progress bar by setting $HIDE_HEADER_TRAINING_BAR = true before including header.php
    $hideBar = isset($HIDE_HEADER_TRAINING_BAR) && $HIDE_HEADER_TRAINING_BAR === true;
?>
                    <?php if (!$hideBar && should_show_training_progress($pdo, $_SESSION['user_id'])): ?>
                        <div class="training-progress-header" onclick="window.location='/training/training_dashboard.php'">
                            <div class="progress-info" id="training-progress-info">
                                <span class="progress-icon">🎓</span>
                                <span class="progress-text">Training: <span id="progress-percentage">0</span>%</span>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" id="progress-bar-fill" style="width: 0%;"></div>
                                </div>
                                <span class="progress-detail" id="progress-detail"><span id="completed-items">0</span> of <span id="total-items">0</span> items</span>
                            </div>
                        </div>

                        <script>
                        // Live training progress updates
                        let trainingProgressInterval = null;

                        function updateTrainingProgress() {
                            fetch('/includes/training_helpers.php?action=get_training_progress&user_id=<?php echo $_SESSION['user_id']; ?>')

                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const percentage = data.percentage || 0;
                                        const completedItems = data.completed_items || 0;
                                        const totalItems = data.total_items || 0;

                                        // Update percentage
                                        document.getElementById('progress-percentage').textContent = percentage;

                                        // Update progress bar
                                        const barFill = document.getElementById('progress-bar-fill');
                                        barFill.style.width = percentage + '%';

                                        // Update progress bar color based on percentage
                                        if (percentage >= 75) {
                                            barFill.style.background = '#28a745';
                                        } else if (percentage >= 50) {
                                            barFill.style.background = '#ffc107';
                                        } else {
                                            barFill.style.background = '#dc3545';
                                        }

                                        // Update progress detail
                                        document.getElementById('completed-items').textContent = completedItems;
                                        document.getElementById('total-items').textContent = totalItems;

                                        // Auto-stop when 100% complete
                                        if (percentage >= 100 && trainingProgressInterval) {
                                            clearInterval(trainingProgressInterval);
                                            trainingProgressInterval = null;
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error updating training progress:', error);
                                });
                        }

                        // Start live updates
                        updateTrainingProgress();
                        trainingProgressInterval = setInterval(updateTrainingProgress, 30000); // Update every 30 seconds

                        // Clean up on page unload
                        window.addEventListener('beforeunload', function() {
                            if (trainingProgressInterval) {
                                clearInterval(trainingProgressInterval);
                            }
                        });
                        </script>
                    <?php endif; ?>

                    <a href="/logout.php" class="logout-btn">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

  <?php
  // Include admin menu dropdown logic
  if (is_admin()):

    // Get all PHP files relative to APP_ROOT for reliable linking
    function getPhpFilesDropdown($dir) {
        $files = [];
        $excludeFiles = [
            'developer_menu.php',
            'logout.php',
            'includes/auth_check.php',
            'includes/db_connect.php'
        ];

        $baseDir = rtrim(app_path($dir), '/');
        if (!is_dir($baseDir)) {
            return [];
        }

        if ($handle = opendir($baseDir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." &&
                    pathinfo($entry, PATHINFO_EXTENSION) === 'php' &&
                    !in_array($entry, $excludeFiles) &&
                    !str_starts_with($entry, '.')) {

                    $filePath = $baseDir . '/' . $entry;
                    if (is_file($filePath)) {
                        $relativePath = ltrim(str_replace(APP_ROOT, '', realpath($filePath)), '/');
                        $files[] = [
                            'name' => $entry,
                            'path' => '/' . $relativePath,
                            'type' => getFileTypeDropdown($entry)
                        ];
                    }
                }
            }
            closedir($handle);
        }

        usort($files, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $files;
    }

    function getFileTypeDropdown($filename) {
        $filename = strtolower($filename);

        if (str_contains($filename, 'index') || str_contains($filename, 'home')) {
            return '🏠';
        } elseif (str_contains($filename, 'add_') || str_contains($filename, 'create') || str_contains($filename, 'new')) {
            return '➕';
        } elseif (str_contains($filename, 'edit') || str_contains($filename, 'update') || str_contains($filename, 'modify')) {
            return '✏️';
        } elseif (str_contains($filename, 'delete') || str_contains($filename, 'remove')) {
            return '🗑️';
        } elseif (str_contains($filename, 'search') || str_contains($filename, 'find')) {
            return '🔍';
        } elseif (str_contains($filename, 'export') || str_contains($filename, 'pdf') || str_contains($filename, 'download')) {
            return '📄';
        } elseif (str_contains($filename, 'category') || str_contains($filename, 'sub')) {
            return '📂';
        } elseif (str_contains($filename, 'post') || str_contains($filename, 'reply')) {
            return '📝';
        } elseif (str_contains($filename, 'user') || str_contains($filename, 'login') || str_contains($filename, 'auth')) {
            return '👤';
        } elseif (str_contains($filename, 'file') || str_contains($filename, 'upload') || str_contains($filename, 'attachment')) {
            return '📎';
        } elseif (str_contains($filename, 'admin') || str_contains($filename, 'manage') || str_contains($filename, 'config')) {
            return '⚙️';
        } elseif (str_contains($filename, 'test') || str_contains($filename, 'debug')) {
            return '🧪';
        } else {
            return '📄';
        }
    }

    $mainFiles = getPhpFilesDropdown('.');
    $dbFiles = [];
    if (is_dir(app_path('database'))) {
        $dbFiles = getPhpFilesDropdown('database');
    }

    $htmlFiles = [];
    if ($handle = opendir(APP_ROOT)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." &&
                pathinfo($entry, PATHINFO_EXTENSION) === 'html' &&
                !str_starts_with($entry, '.')) {

                $htmlFiles[] = [
                    'name' => $entry,
                    'path' => '/' . ltrim($entry, '/'),
                    'type' => '🌐'
                ];
            }
        }
        closedir($handle);
    }
    sort($htmlFiles);
  ?>

  <!-- LinkedIn-style Developer Menu Dropdown -->
  <div class="dev-dropdown-overlay" id="devDropdownOverlay"></div>
  <div class="dev-dropdown-menu" id="devDropdownMenu" onclick="event.stopPropagation()">
    <div class="dev-dropdown-header">
      <h3>⚙️ Admin Menu</h3>
      <?php if (is_super_admin()): ?>
      <div class="dev-dropdown-stats">
        <span class="stat-badge"><?php echo count($mainFiles); ?> PHP</span>
        <span class="stat-badge"><?php echo count($dbFiles); ?> SQL</span>
        <span class="stat-badge"><?php echo count($htmlFiles); ?> HTML</span>
      </div>
      <?php endif; ?>
    </div>

    <div class="dev-dropdown-content">
      <div class="dev-dropdown-section">
        <div class="dev-section-title">🛠️ Admin Tools</div>
        <div class="dev-file-list">
          <a href="/admin/manage_users.php" class="dev-file-item">
            <span class="dev-file-icon">👥</span>
            <span class="dev-file-name">User Management</span>
          </a>
          <a href="/admin/manage_training_courses.php" class="dev-file-item">
            <span class="dev-file-icon">🎓</span>
            <span class="dev-file-name">Training Courses</span>
          </a>
          <a href="/admin/manage_quizzes.php" class="dev-file-item">
            <span class="dev-file-icon">📝</span>
            <span class="dev-file-name">Manage Quizzes</span>
          </a>
          <a href="/admin/training_admin_analytics.php" class="dev-file-item">
            <span class="dev-file-icon">📊</span>
            <span class="dev-file-name">Admin Training Dashboard</span>
          </a>
          <a href="/admin/manage_edit_requests.php" class="dev-file-item">
            <span class="dev-file-icon">📝</span>
            <span class="dev-file-name">Edit Requests</span>
          </a>
          <a href="/bugs/bug_report.php" class="dev-file-item">
            <span class="dev-file-icon">🐛</span>
            <span class="dev-file-name">Bug Report System</span>
          </a>
          <?php if (is_super_admin()): ?>
          <div class="dev-file-item" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
            <div onclick="toggleDebugConsole()" style="flex: 1;">
              <span class="dev-file-icon">🧪</span>
              <span class="dev-file-name">Debug Console</span>
            </div>
            <div style="margin-left: 8px;" onclick="toggleDebugSwitch(event)">
              <label class="debug-toggle-switch">
                <input type="checkbox" id="debugToggleSwitch" onchange="handleDebugSwitch(event)">
                <span class="debug-slider"></span>
              </label>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (is_super_admin()): ?>
      <?php if (!empty($mainFiles)): ?>
      <div class="dev-dropdown-section">
        <div class="dev-section-title">📄 Main PHP Files</div>
        <div class="dev-file-list">
          <?php foreach ($mainFiles as $file): ?>
          <a href="<?php echo htmlspecialchars($file['path']); ?>" class="dev-file-item">
            <span class="dev-file-icon"><?php echo $file['type']; ?></span>
            <span class="dev-file-name"><?php echo htmlspecialchars($file['name']); ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($dbFiles)): ?>
      <div class="dev-dropdown-section">
        <div class="dev-section-title">🗄️ Database Files</div>
        <div class="dev-file-list">
          <?php foreach ($dbFiles as $file): ?>
          <a href="<?php echo htmlspecialchars($file['path']); ?>" class="dev-file-item">
            <span class="dev-file-icon">🗄️</span>
            <span class="dev-file-name"><?php echo htmlspecialchars($file['name']); ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($htmlFiles)): ?>
      <div class="dev-dropdown-section">
        <div class="dev-section-title">🌐 HTML Test Files</div>
        <div class="dev-file-list">
          <?php foreach ($htmlFiles as $file): ?>
          <a href="<?php echo htmlspecialchars($file['path']); ?>" class="dev-file-item">
            <span class="dev-file-icon">🌐</span>
            <span class="dev-file-name"><?php echo htmlspecialchars($file['name']); ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Debug Console CSS -->
  <style>
  .debug-toggle-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 20px;
  }

  .debug-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }

  .debug-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 20px;
  }

  .debug-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
  }

  input:checked + .debug-slider {
    background-color: #48bb78;
  }

  input:checked + .debug-slider:before {
    transform: translateX(20px);
  }

  .debug-persistent-indicator {
    position: fixed;
    bottom: 20px;
    left: 20px;
    background: #2d3748;
    color: white;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 12px;
    z-index: 9998;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    display: none;
    transition: all 0.3s ease;
  }

  .debug-persistent-indicator:hover {
    background: #4a5568;
    transform: scale(1.05);
  }
  </style>

  <!-- Debug Console (Persistent - visible to all when enabled by super admin) -->
  <div id="debugPersistentIndicator" class="debug-persistent-indicator" onclick="toggleDebugConsole()" style="display: none;">
    🧪 Debug Console
  </div>

  <!-- Debug Console HTML (always available, controlled by JavaScript) -->
  <div id="debugConsole" style="display: none; position: fixed; bottom: 20px; left: 20px; width: 400px; max-width: 90vw; background: #2d3748; color: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 9999; font-family: 'Courier New', monospace; font-size: 12px;">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #1a202c; border-radius: 8px 8px 0 0; cursor: pointer;" onclick="toggleDebugConsoleSize()">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 16px;">🧪</span>
        <span style="font-weight: 600;">Debug Console</span>
        <span id="debugStatus" style="font-size: 10px; background: #4a5568; padding: 2px 6px; border-radius: 3px;">Ready</span>
      </div>
      <div style="display: flex; gap: 8px;">
        <button onclick="refreshDebugLog(event)" style="background: #4299e1; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px;">🔄 Refresh</button>
        <button onclick="clearDebugLog(event)" style="background: #e53e3e; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px;">🗑️ Clear</button>
        <button onclick="toggleDebugConsole(event)" style="background: #718096; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px;">✕</button>
      </div>
    </div>
    <div id="debugContent" style="max-height: 200px; overflow-y: auto; padding: 12px; background: #1a202c; border-radius: 0 0 8px 8px;">
      <div id="debugLogContent" style="white-space: pre-wrap; line-height: 1.4;">
        Loading debug logs...
      </div>
    </div>
    <div id="debugExpanded" style="display: none; max-height: 400px; overflow-y: auto; padding: 12px; background: #1a202c; border-radius: 0 0 8px 8px;">
      <div id="debugExpandedContent" style="white-space: pre-wrap; line-height: 1.4;">
        Loading debug logs...
      </div>
    </div>
  </div>

  <script>
  // Debug Console functionality with persistence
  let debugExpanded = false;
  let debugAutoRefresh = false;
  let debugRefreshInterval = null;
  let debugEnabled = false;

  // Initialize debug console state from localStorage
  function initDebugConsole() {
    debugEnabled = localStorage.getItem('debugConsoleEnabled') === 'true';
    const toggle = document.getElementById('debugToggleSwitch');
    const console = document.getElementById('debugConsole');
    const indicator = document.getElementById('debugPersistentIndicator');

    if (debugEnabled) {
      if (toggle) toggle.checked = true;
      if (console) console.style.display = 'block';
      if (indicator) indicator.style.display = 'block';
      refreshDebugLog();
    }
  }

  function toggleDebugConsole(event) {
    if (event) {
      event.stopPropagation();
    }
    const console = document.getElementById('debugConsole');
    const isVisible = console.style.display !== 'none';
    console.style.display = isVisible ? 'none' : 'block';

    if (!isVisible) {
      refreshDebugLog();
    }
  }

  function toggleDebugSwitch(event) {
    event.stopPropagation();
    const toggle = document.getElementById('debugToggleSwitch');
    toggle.checked = !toggle.checked;
    handleDebugSwitch(event);
  }

  function handleDebugSwitch(event) {
    if (event) {
      event.stopPropagation();
    }

    const toggle = document.getElementById('debugToggleSwitch');
    const console = document.getElementById('debugConsole');
    const indicator = document.getElementById('debugPersistentIndicator');

    debugEnabled = toggle.checked;

    // Save state to localStorage
    localStorage.setItem('debugConsoleEnabled', debugEnabled);

    // Set cookie for PHP side detection
    document.cookie = 'debug_console_enabled=' + debugEnabled + '; path=/; max-age=86400';

    if (debugEnabled) {
      if (console) {
        console.style.display = 'block';
        refreshDebugLog();
      }
      if (indicator) indicator.style.display = 'block';
    } else {
      if (console) console.style.display = 'none';
      if (indicator) indicator.style.display = 'none';
    }
  }

  function toggleDebugConsoleSize(event) {
    if (event) {
      event.stopPropagation();
    }
    debugExpanded = !debugExpanded;
    const content = document.getElementById('debugContent');
    const expanded = document.getElementById('debugExpanded');

    if (debugExpanded) {
      content.style.display = 'none';
      expanded.style.display = 'block';
    } else {
      content.style.display = 'block';
      expanded.style.display = 'none';
    }
  }

  function refreshDebugLog(event) {
    if (event) {
      event.stopPropagation();
    }

    const status = document.getElementById('debugStatus');
    const content = document.getElementById('debugLogContent');
    const expandedContent = document.getElementById('debugExpandedContent');

    status.textContent = 'Loading...';
    status.style.background = '#ed8936';

    fetch('view_debug_log.php')
      .then(response => response.text())
      .then(html => {
        // Extract just the debug content from the HTML
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const debugContent = doc.querySelector('.debug-content');

        if (debugContent) {
          content.innerHTML = debugContent.innerHTML;
          expandedContent.innerHTML = debugContent.innerHTML;
          status.textContent = 'Live';
          status.style.background = '#48bb78';
        } else {
          content.textContent = 'No debug logs available.';
          expandedContent.textContent = 'No debug logs available.';
          status.textContent = 'No Logs';
          status.style.background = '#4a5568';
        }
      })
      .catch(error => {
        content.textContent = 'Error loading debug logs: ' + error.message;
        expandedContent.textContent = 'Error loading debug logs: ' + error.message;
        status.textContent = 'Error';
        status.style.background = '#e53e3e';
      });
  }

  function clearDebugLog(event) {
    if (event) {
      event.stopPropagation();
    }

    if (confirm('Are you sure you want to clear the debug log?')) {
      fetch('view_debug_log.php?action=clear_log')
        .then(response => response.text())
        .then(() => {
          const content = document.getElementById('debugLogContent');
          const expandedContent = document.getElementById('debugExpandedContent');
          const status = document.getElementById('debugStatus');

          content.innerHTML = '<div style="color: #68d391;">✅ Debug log cleared successfully</div>';
          expandedContent.innerHTML = '<div style="color: #68d391;">✅ Debug log cleared successfully</div>';
          status.textContent = 'Cleared';
          status.style.background = '#38a169';

          setTimeout(() => {
            refreshDebugLog();
          }, 2000);
        })
        .catch(error => {
          alert('Error clearing debug log: ' + error.message);
        });
    }
  }

  // Close debug console when clicking outside (but not when clicking the debug indicator)
  document.addEventListener('click', function(event) {
    const console = document.getElementById('debugConsole');
    const indicator = document.getElementById('debugPersistentIndicator');
    const devMenu = document.getElementById('devDropdownMenu');

    if (console && console.style.display !== 'none' &&
        !console.contains(event.target) &&
        (!indicator || !indicator.contains(event.target)) &&
        (!devMenu || !devMenu.contains(event.target))) {
      console.style.display = 'none';
    }
  });

  // Keyboard shortcut for debug console (Ctrl+Shift+D)
  document.addEventListener('keydown', function(event) {
    if (event.ctrlKey && event.shiftKey && event.key === 'D') {
      toggleDebugConsole();
    }
  });

  // Initialize debug console when page loads
  document.addEventListener('DOMContentLoaded', function() {
    initDebugConsole();
  });
  </script>