<?php
/**
 * Debug Testing Hub
 *
 * Runs lightweight health checks across the project and lints every PHP file it finds.
 * Use query params to customize scanning:
 *   - include_vendor=1  (scan vendor/ too, may be slow)
 *   - max_depth=8       (limit recursion depth; default 8)
 *   - start=relative/path/from/project/root (change starting directory)
 *   - ignore=dir1,dir2  (comma-separated names to skip)
 */

declare(strict_types=1);
@ini_set('display_errors', '0');
@ini_set('memory_limit', '1G');
@set_time_limit(240);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (file_exists(__DIR__ . '/../includes/user_helpers.php')) {
    require_once __DIR__ . '/../includes/user_helpers.php';
}

// ---------- Helpers ----------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_truthy(?string $v): bool { return isset($v) && ($v === '1' || strcasecmp($v, 'true') === 0 || strcasecmp($v, 'yes') === 0); }

function bool_env_flag(string $name, bool $default = false): bool {
    $raw = $_GET[$name] ?? null;
    return $raw === null ? $default : is_truthy(is_string($raw) ? $raw : null);
}

function safe_int_param(string $name, int $default, int $min, int $max): int {
    $raw = $_GET[$name] ?? null;
    if (!is_string($raw) || trim($raw) === '') {
        return $default;
    }
    $val = intval($raw);
    return max($min, min($val, $max));
}

function format_duration(float $seconds): string {
    if ($seconds < 1) {
        return sprintf('%d ms', (int)round($seconds * 1000));
    }
    return sprintf('%.2f s', $seconds);
}

function lint_php_file(string $filePath): array {
    $start = microtime(true);

    if (!is_readable($filePath)) {
        return ['status' => 'skip', 'message' => 'Not readable', 'duration' => microtime(true) - $start];
    }

    // Prefer proc_open for better control; fallback to shell_exec if necessary.
    $phpBinary = PHP_BINARY ?: 'php';
    $command = escapeshellcmd($phpBinary) . ' -d display_errors=0 -d log_errors=0 -l ' . escapeshellarg($filePath);

    $output = '';
    $exitCode = 0;

    if (function_exists('proc_open')) {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptors, $pipes);
        if (is_resource($process)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $exitCode = proc_close($process);
            $output = trim($stdout . "\n" . $stderr);
        } else {
            $exitCode = -1;
            $output = 'proc_open unavailable';
        }
    } elseif (function_exists('shell_exec')) {
        $output = shell_exec($command . ' 2>&1');
        $exitCode = is_string($output) ? (stripos($output, 'No syntax errors') !== false ? 0 : 1) : -1;
    } else {
        return ['status' => 'skip', 'message' => 'No process execution functions enabled', 'duration' => microtime(true) - $start];
    }

    $duration = microtime(true) - $start;

    if ($exitCode === 0) {
        return ['status' => 'pass', 'message' => 'No syntax errors detected', 'duration' => $duration];
    }

    return ['status' => 'fail', 'message' => $output ?: 'Syntax check failed', 'duration' => $duration];
}

function can_descend(string $path, array $ignoreSet): bool {
    $name = basename($path);
    return !isset($ignoreSet[$name]);
}

function collect_php_files(string $root, int $maxDepth, array $ignoreNames): array {
    $files = [];
    $ignoreSet = array_flip($ignoreNames);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            function ($current, $key, $iterator) use ($ignoreSet, $maxDepth, $root) {
                $subPath = '';

                if (is_object($iterator) && method_exists($iterator, 'getSubPath')) {
                    $subPath = $iterator->getSubPath();
                } elseif ($current instanceof SplFileInfo) {
                    $path = $current->getPathname();
                    $relative = $root !== '' ? ltrim(substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR))), DIRECTORY_SEPARATOR) : $path;
                    $subPath = $relative === false ? '' : $relative;
                }

                $depth = $subPath === '' ? 0 : substr_count($subPath, DIRECTORY_SEPARATOR) + 1;

                if ($depth >= $maxDepth) {
                    return false;
                }

                $name = $current->getFilename();
                if (isset($ignoreSet[$name])) {
                    return false;
                }

                // Skip hidden directories/files unless explicitly allowed via ignore list removal
                if ($name !== '.' && $name !== '..' && isset($name[0]) && $name[0] === '.') {
                    return false;
                }

                return true;
            }
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
            $files[] = $fileInfo->getPathname();
        }
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

// ---------- Parameters ----------
$projectRoot = realpath(__DIR__ . '/..') ?: __DIR__;
$startParamRaw = $_GET['start'] ?? '';
$startDir = $projectRoot;
if (is_string($startParamRaw) && trim($startParamRaw) !== '') {
    $candidate = realpath($projectRoot . '/' . ltrim($startParamRaw, '/\\'));
    if ($candidate !== false && strpos($candidate, $projectRoot) === 0 && is_dir($candidate)) {
        $startDir = $candidate;
    }
}

$maxDepth = safe_int_param('max_depth', 8, 1, 20);
$includeVendor = bool_env_flag('include_vendor', false);
$extraIgnoresRaw = $_GET['ignore'] ?? '';
$extraIgnores = array_values(array_filter(array_map('trim', is_string($extraIgnoresRaw) ? explode(',', $extraIgnoresRaw) : [])));

$defaultIgnores = ['uploads', 'images', 'assets', 'docs', 'system', 'vendor', '.git', '.idea'];
if ($includeVendor) {
    $defaultIgnores = array_values(array_filter($defaultIgnores, function ($name) {
        return $name !== 'vendor';
    }));
}

$ignoreNames = array_values(array_unique(array_merge($defaultIgnores, $extraIgnores)));

// ---------- Database health check ----------
$dbStatus = 'unknown';
$dbMessage = '';
$dbDetails = [];

try {
    $stmt = $pdo->query('SELECT 1 as ok');
    $dbStatus = 'pass';
    $dbMessage = 'Database connection healthy';

    $tables = ['users', 'categories', 'posts', 'training_courses', 'training_course_content', 'training_progress'];
    foreach ($tables as $table) {
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM `{$table}`");
            $row = $countStmt->fetch(PDO::FETCH_ASSOC);
            $dbDetails[$table] = isset($row['total']) ? intval($row['total']) : 0;
        } catch (Throwable $e) {
            $dbDetails[$table] = 'error: ' . $e->getMessage();
        }
    }
} catch (Throwable $e) {
    $dbStatus = 'fail';
    $dbMessage = 'DB check failed: ' . $e->getMessage();
}

// ---------- File linting ----------
$files = collect_php_files($startDir, $maxDepth, $ignoreNames);
$totalFiles = count($files);
$results = [];
$stats = ['pass' => 0, 'fail' => 0, 'skip' => 0];

foreach ($files as $path) {
    $result = lint_php_file($path);
    $status = $result['status'];
    if (!isset($stats[$status])) {
        $stats[$status] = 0;
    }
    $stats[$status]++;

    $results[] = [
        'file' => $path,
        'status' => $status,
        'message' => $result['message'],
        'duration' => $result['duration'],
        'modified' => filemtime($path) ?: null,
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Debug Testing Hub</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f7fb; color: #1f2933; }
        h1 { margin-bottom: 0.5rem; }
        .summary { display: flex; gap: 1rem; flex-wrap: wrap; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 1rem; min-width: 240px; }
        .status-pass { color: #0a7c2f; font-weight: 700; }
        .status-fail { color: #b00020; font-weight: 700; }
        .status-skip { color: #947600; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: #fff; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #eef2f7; position: sticky; top: 0; z-index: 1; }
        tr:hover { background: #f8fafc; }
        .controls form { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
        .badge { padding: 4px 8px; border-radius: 999px; font-size: 0.85em; }
        .badge.pass { background: #dcf5e4; color: #0a7c2f; }
        .badge.fail { background: #ffe5e5; color: #b00020; }
        .badge.skip { background: #fff4d6; color: #947600; }
        .small { font-size: 0.9em; color: #52606d; }
    </style>
</head>
<body>
    <h1>Debug Testing Hub</h1>
    <p class="small">Project root: <?php echo h($projectRoot); ?> | Scanned from: <?php echo h($startDir); ?></p>

    <div class="controls">
        <form method="get">
            <label>Start: <input type="text" name="start" value="<?php echo h($startParamRaw ?? ''); ?>" placeholder="relative/path" /></label>
            <label>Max depth: <input type="number" name="max_depth" value="<?php echo h((string)$maxDepth); ?>" min="1" max="20" /></label>
            <label><input type="checkbox" name="include_vendor" value="1" <?php echo $includeVendor ? 'checked' : ''; ?> /> Include vendor</label>
            <label>Ignore (comma): <input type="text" name="ignore" value="<?php echo h($extraIgnoresRaw); ?>" placeholder="cache,tmp" /></label>
            <button type="submit">Rescan</button>
        </form>
    </div>

    <div class="summary">
        <div class="card">
            <div>Database check</div>
            <div class="<?php echo $dbStatus === 'pass' ? 'status-pass' : 'status-fail'; ?>">
                <?php echo h(strtoupper($dbStatus)); ?>
            </div>
            <div class="small"><?php echo h($dbMessage); ?></div>
            <?php if (!empty($dbDetails)) : ?>
                <ul class="small">
                    <?php foreach ($dbDetails as $table => $count) : ?>
                        <li><?php echo h($table); ?>: <?php echo h((string)$count); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card">
            <div>File linting</div>
            <div>Total files: <?php echo (int)$totalFiles; ?></div>
            <div class="small">
                <span class="badge pass">Pass: <?php echo (int)$stats['pass']; ?></span>
                <span class="badge fail">Fail: <?php echo (int)$stats['fail']; ?></span>
                <span class="badge skip">Skip: <?php echo (int)$stats['skip']; ?></span>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>File</th>
                <th>Status</th>
                <th>Message</th>
                <th>Last Modified</th>
                <th>Duration</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row) : ?>
                <tr>
                    <td class="small"><?php echo h(str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $row['file'])); ?></td>
                    <td>
                        <?php
                        $statusClass = 'badge ' . ($row['status'] === 'pass' ? 'pass' : ($row['status'] === 'fail' ? 'fail' : 'skip'));
                        echo '<span class="' . $statusClass . '">' . h(strtoupper($row['status'])) . '</span>';
                        ?>
                    </td>
                    <td class="small"><?php echo h($row['message']); ?></td>
                    <td class="small"><?php echo $row['modified'] ? date('Y-m-d H:i:s', $row['modified']) : 'n/a'; ?></td>
                    <td class="small"><?php echo h(format_duration($row['duration'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
