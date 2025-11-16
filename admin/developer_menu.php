<?php
/**
 * Developer Menu - Popup Menu for Admin Users
 * Displays all PHP files in the directory as clickable links
 */

require_once __DIR__ . '/../includes/auth_check.php';

// Only allow admin users
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    http_response_code(403);
    exit('Access denied');
}

// Get all PHP files in current directory
function getPhpFiles($dir) {
    $files = [];
    $excludeFiles = [
        'developer_menu.php',
        'logout.php',
        'includes/auth_check.php',
        'includes/db_connect.php'
    ];

    if ($handle = opendir($dir)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." &&
                pathinfo($entry, PATHINFO_EXTENSION) === 'php' &&
                !in_array($entry, $excludeFiles) &&
                !str_starts_with($entry, '.')) {

                $filePath = $dir . '/' . $entry;
                if (is_file($filePath)) {
                    $fileSize = filesize($filePath);
                    $modifiedTime = filemtime($filePath);

                    $files[] = [
                        'name' => $entry,
                        'path' => $entry,
                        'size' => $fileSize,
                        'modified' => $modifiedTime,
                        'type' => getFileType($entry)
                    ];
                }
            }
        }
        closedir($handle);
    }

    // Sort by name
    usort($files, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $files;
}

function getFileType($filename) {
    $filename = strtolower($filename);

    if (str_contains($filename, 'index') || str_contains($filename, 'home')) {
        return '🏠 Main';
    } elseif (str_contains($filename, 'add_') || str_contains($filename, 'create') || str_contains($filename, 'new')) {
        return '➕ Add/Create';
    } elseif (str_contains($filename, 'edit') || str_contains($filename, 'update') || str_contains($filename, 'modify')) {
        return '✏️ Edit/Update';
    } elseif (str_contains($filename, 'delete') || str_contains($filename, 'remove')) {
        return '🗑️ Delete';
    } elseif (str_contains($filename, 'search') || str_contains($filename, 'find')) {
        return '🔍 Search';
    } elseif (str_contains($filename, 'export') || str_contains($filename, 'pdf') || str_contains($filename, 'download')) {
        return '📄 Export/Download';
    } elseif (str_contains($filename, 'category') || str_contains($filename, 'sub')) {
        return '📂 Categories';
    } elseif (str_contains($filename, 'post') || str_contains($filename, 'reply')) {
        return '📝 Posts/Replies';
    } elseif (str_contains($filename, 'user') || str_contains($filename, 'login') || str_contains($filename, 'auth')) {
        return '👤 Users/Auth';
    } elseif (str_contains($filename, 'file') || str_contains($filename, 'upload') || str_contains($filename, 'attachment')) {
        return '📎 Files/Uploads';
    } elseif (str_contains($filename, 'admin') || str_contains($filename, 'manage') || str_contains($filename, 'config')) {
        return '⚙️ Admin/Config';
    } elseif (str_contains($filename, 'test') || str_contains($filename, 'debug')) {
        return '🧪 Test/Debug';
    } else {
        return '📄 Other';
    }
}

// Get files from database directory too
$dbFiles = [];
if (is_dir('database')) {
    $dbFiles = getPhpFiles('database');
}

$mainFiles = getPhpFiles('.');

// Get HTML files too
$htmlFiles = [];
if ($handle = opendir('.')) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != ".." &&
            pathinfo($entry, PATHINFO_EXTENSION) === 'html' &&
            !str_starts_with($entry, '.')) {

            $htmlFiles[] = [
                'name' => $entry,
                'path' => $entry,
                'size' => filesize($entry),
                'modified' => filemtime($entry),
                'type' => '🌐 HTML'
            ];
        }
    }
    closedir($handle);
}
sort($htmlFiles);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Menu - PHP Files</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 800px;
            max-height: 90vh;
            width: 100%;
            display: flex;
            flex-direction: column;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 16px 16px 0 0;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s ease;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .section {
            margin-bottom: 24px;
        }

        .section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 8px;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.2s ease;
            gap: 8px;
        }

        .file-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: #1e293b;
        }

        .file-icon {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .file-info {
            flex: 1;
            min-width: 0;
        }

        .file-name {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-meta {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        .file-type {
            background: #e2e8f0;
            color: #475569;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
            white-space: nowrap;
        }

        .stats {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #4299e1;
        }

        .stat-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Mobile responsive */
        @media (max-width: 640px) {
            .modal-container {
                margin: 10px;
                max-height: 95vh;
            }

            .modal-header {
                padding: 16px 20px;
            }

            .modal-header h2 {
                font-size: 18px;
            }

            .modal-body {
                padding: 16px;
            }

            .file-grid {
                grid-template-columns: 1fr;
            }

            .stats {
                gap: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="modal-container">
        <div class="modal-header">
            <h2>⚙️ Developer Menu - PHP Files</h2>
            <div class="close-btn" onclick="window.close()">✕</div>
        </div>

        <div class="modal-body">
            <div class="section" style="margin-bottom: 24px;">
                <div class="section-title">⚙️ Admin Management</div>
                <div class="file-grid">
                    <a href="manage_users.php" target="_blank" class="file-item" style="background: #e8f5e8; border-color: #4CAF50;">
                        <div class="file-icon">👥</div>
                        <div class="file-info">
                            <div class="file-name">User Management</div>
                            <div class="file-meta">Add, edit, delete users</div>
                        </div>
                        <div class="file-type" style="background: #4CAF50; color: white;">ADMIN</div>
                    </a>
                    <a href="bug_report.php" target="_blank" class="file-item" style="background: #fff3cd; border-color: #ffc107;">
                        <div class="file-icon">🐛</div>
                        <div class="file-info">
                            <div class="file-name">Bug Reports</div>
                            <div class="file-meta">View and manage bugs</div>
                        </div>
                        <div class="file-type" style="background: #ffc107; color: black;">ADMIN</div>
                    </a>
                </div>
            </div>

            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($mainFiles); ?></div>
                    <div class="stat-label">Main PHP Files</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($dbFiles); ?></div>
                    <div class="stat-label">Database Files</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($htmlFiles); ?></div>
                    <div class="stat-label">HTML Files</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($mainFiles) + count($dbFiles) + count($htmlFiles); ?></div>
                    <div class="stat-label">Total Files</div>
                </div>
            </div>

            <?php if (!empty($mainFiles)): ?>
            <div class="section">
                <div class="section-title">📄 Main PHP Files</div>
                <div class="file-grid">
                    <?php foreach ($mainFiles as $file): ?>
                    <a href="<?php echo htmlspecialchars($file['path']); ?>" target="_blank" class="file-item">
                        <div class="file-icon"><?php echo $file['type']; ?></div>
                        <div class="file-info">
                            <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                            <div class="file-meta"><?php echo number_format($file['size'] / 1024, 1); ?> KB</div>
                        </div>
                        <div class="file-type">PHP</div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($dbFiles)): ?>
            <div class="section">
                <div class="section-title">🗄️ Database Files</div>
                <div class="file-grid">
                    <?php foreach ($dbFiles as $file): ?>
                    <a href="<?php echo htmlspecialchars($file['path']); ?>" target="_blank" class="file-item">
                        <div class="file-icon">🗄️</div>
                        <div class="file-info">
                            <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                            <div class="file-meta"><?php echo number_format($file['size'] / 1024, 1); ?> KB</div>
                        </div>
                        <div class="file-type">SQL</div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($htmlFiles)): ?>
            <div class="section">
                <div class="section-title">🌐 HTML Files</div>
                <div class="file-grid">
                    <?php foreach ($htmlFiles as $file): ?>
                    <a href="<?php echo htmlspecialchars($file['path']); ?>" target="_blank" class="file-item">
                        <div class="file-icon">🌐</div>
                        <div class="file-info">
                            <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                            <div class="file-meta"><?php echo number_format($file['size'] / 1024, 1); ?> KB</div>
                        </div>
                        <div class="file-type">HTML</div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Close on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                window.close();
            }
        });

        // Close on background click
        document.body.addEventListener('click', function(event) {
            if (event.target === document.body) {
                window.close();
            }
        });
    </script>
</body>
</html>