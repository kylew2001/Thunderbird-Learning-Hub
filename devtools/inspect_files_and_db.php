<?php
/**
 * Inspect Files & DB (read-only)
 * Lists the project directory tree and MySQL/MariaDB structure.
 *
 * Usage (optional query params):
 *   ?start=relative/path/from/script   (directory root override; default is script dir)
 *   &show_hidden=1                     (include dotfiles and ignored dirs)
 *   &include_system_dbs=1              (include information_schema, mysql, performance_schema, sys)
 *   &max_depth=6                       (limit directory recursion depth)
 *   &db_host=localhost&db_port=3306&db_user=root&db_pass=secret
 *                                      (used only if includes/db_connect.php is missing)
 *
 * Security notes:
 * - Read-only: does NOT read file contents, only names/metadata.
 * - If present, it will prefer includes/db_connect.php and an existing $pdo.
 * - No untrusted inputs are interpolated into SQL; names come from server listings.
 */

declare(strict_types=1);
@ini_set('display_errors', '0'); // keep output clean
@ini_set('memory_limit', '512M');
@set_time_limit(120);

// ---------- Utilities ----------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_truthy(?string $v): bool { return isset($v) && ($v === '1' || strcasecmp($v, 'true') === 0 || strcasecmp($v, 'yes') === 0); }
function fmtBytes(int $bytes): string {
    $units = ['B','KB','MB','GB','TB']; $i=0;
    while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
    return sprintf('%s %s', $bytes < 10 && $i ? number_format($bytes, 2) : number_format($bytes, 0), $units[$i]);
}

function safePath(string $base, string $candidate): string {
    $realBase = realpath($base) ?: rtrim($base, DIRECTORY_SEPARATOR);
    $candReal = realpath($candidate);
    if ($candReal === false || $candReal === '') {
        // If realpath fails (e.g., open_basedir), normalize manually
        $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);
        if ($candidate === '') return $realBase;
        $candNormalized = $candidate;
    } else {
        $candNormalized = rtrim($candReal, DIRECTORY_SEPARATOR);
    }
    // Clamp: ensure candidate stays under base
    if (strpos($candNormalized, $realBase) !== 0) {
        return $realBase;
    }
    return $candNormalized;
}


// ---------- Params ----------
// Polyfill for PHP < 8 if needed
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

// Script directory (e.g. /htdocs/devtools) and project root (one level up, /htdocs)
$SCRIPT_DIR    = realpath(__DIR__) ?: (getcwd() ?: __DIR__);
$PROJECT_ROOT  = realpath($SCRIPT_DIR . DIRECTORY_SEPARATOR . '..') ?: $SCRIPT_DIR;
// For directory browsing, use the project root (htdocs) as the base
$HERE          = $PROJECT_ROOT;

$startParamRaw = $_GET['start'] ?? '';
$startParam = is_string($startParamRaw) ? trim($startParamRaw) : '';

if ($startParam !== '') {
    // Allow absolute or relative, but clamp to $HERE
    $isAbsolute = str_starts_with($startParam, DIRECTORY_SEPARATOR) ||
                  preg_match('/^[A-Za-z]:\\\\/i', $startParam) === 1; // Windows drive
    $candidate = $isAbsolute ? $startParam : $HERE . DIRECTORY_SEPARATOR . $startParam;
    $ROOT = safePath($HERE, $candidate);
} else {
    $ROOT = $HERE;
}

// Final guard to avoid empty/invalid directory
if (!$ROOT || !is_dir($ROOT)) {
    $ROOT = $HERE;
}

$SHOW_HIDDEN = is_truthy($_GET['show_hidden'] ?? null);
$INCLUDE_SYSTEM_DBS = is_truthy($_GET['include_system_dbs'] ?? null);
$MAX_DEPTH = max(1, intval($_GET['max_depth'] ?? 8));


// Runtime-configurable ignore list (exact-name matches). Default: nothing ignored.
// Example: ?ignore=node_modules,vendor
$IGNORE_NAMES = [];
if (!empty($_GET['ignore']) && is_string($_GET['ignore'])) {
    $IGNORE_NAMES = array_values(array_filter(array_map('trim', explode(',', $_GET['ignore'])), function($x){ return $x !== ''; }));
}
// When SHOW_HIDDEN=0 we still hide dotfiles; this is separate from IGNORE_NAMES.
$SYSTEM_DBS = ['information_schema', 'mysql', 'performance_schema', 'sys'];

// ---------- Directory Tree ----------

function renderDirTree(string $path, array $ignore, int $depth = 0, int $maxDepth = 8): void {
    if ($depth > $maxDepth) return;
    if ($path === '' || !is_dir($path)) return;

    $entries = @scandir($path);
    if ($entries === false) return;

    // Filter dot entries
    $entries = array_values(array_filter($entries, function($name) {
        return $name !== '.' && $name !== '..';
    }));

    // Hide dotfiles unless SHOW_HIDDEN=1
    if (!$GLOBALS['SHOW_HIDDEN']) {
        $entries = array_values(array_filter($entries, function($name) {
            return !str_starts_with($name, '.');
        }));
    }

    // Ignore list (exact name matches only; case-sensitive)
if (!empty($ignore)) {
    $ignoreSet = array_flip($ignore);
    $entries = array_values(array_filter($entries, function($name) use ($ignoreSet) {
        return !isset($ignoreSet[$name]);
    }));
}

    // Sort: folders first (nat case), then files (nat case)
    natcasesort($entries);
    $entries = array_values($entries);

    // Build two lists by checking is_dir
    $dirs = [];
    $files = [];
    foreach ($entries as $name) {
        $full = $path . DIRECTORY_SEPARATOR . $name;
        if (@is_dir($full)) {
            $dirs[] = $name;
        } else {
            $files[] = $name;
        }
    }

    echo "<ul class='tree'>";

    foreach ($dirs as $name) {
        $full = $path . DIRECTORY_SEPARATOR . $name;
        echo "<li class='dir'><details ".($depth<2?'open':'')."><summary>📁 ".h($name)."</summary>";
        renderDirTree($full, $ignore, $depth+1, $maxDepth);
        echo "</details></li>";
    }

    foreach ($files as $name) {
        echo "<li class='file'>📄 ".h($name)."</li>";
    }

    echo "</ul>";
}


$pdo = null;
$DB_ERRORS = [];
$DB_RESULT = [];
$DB_NOTES = [];

try {
    // Prefer existing project connector relative to the script location (htdocs),
    // not the browsing root (which can change via ?start=...).
    $PROJECT_ROOT = $HERE; // script directory under htdocs
    $dbConnect = $PROJECT_ROOT . '/includes/db_connect.php';
    if (file_exists($dbConnect)) {
        /** @noinspection PhpIncludeInspection */
        require_once $dbConnect;
    }


    if (!($pdo instanceof PDO)) {
        // Fallback to GET params (host/user/pass/port)
        $host = $_GET['db_host'] ?? 'localhost';
        $port = intval($_GET['db_port'] ?? '3306');
        $user = $_GET['db_user'] ?? '';
        $pass = $_GET['db_pass'] ?? '';
        // Connect without selecting a specific DB to enumerate all
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 15,
        ]);
    }

    // SHOW DATABASES
    $databases = [];
try {
    foreach ($pdo->query("SHOW DATABASES") as $row) {
        $dbName = (string)$row['Database'];
        if (!$INCLUDE_SYSTEM_DBS && in_array($dbName, $SYSTEM_DBS, true)) continue;
        $databases[] = $dbName;
    }
    sort($databases, SORT_NATURAL | SORT_FLAG_CASE);
} catch (Throwable $e) {
    // Access denied for SHOW DATABASES — use current schema
    
// Access denied for SHOW DATABASES — use current schema
try {
    $currentDb = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
    if ($currentDb !== '') {
        $databases = [$currentDb];
        $DB_NOTES[] = "Limited privileges: showing only current DB “{$currentDb}”.";
    } else {
        $DB_ERRORS[] = "Limited DB privileges and no current database selected.";
    }
} catch (Throwable $e2) {
    $DB_ERRORS[] = "Unable to determine current database.";
}


}
    foreach ($databases as $dbName) {
        $dbInfo = ['name' => $dbName, 'tables' => []];

        // Tables
        $tables = [];
        foreach ($pdo->query("SHOW FULL TABLES FROM `{$dbName}`") as $row) {
            // The column name is "Tables_in_{$dbName}" (varies), find it dynamically:
            $tblName = null;
            foreach ($row as $k => $v) {
                if (stripos($k, "Tables_in_") === 0) { $tblName = (string)$v; break; }
            }
            if (!$tblName) continue;
            $tables[] = $tblName;
        }
        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($tables as $tbl) {
            $tableInfo = ['name' => $tbl, 'columns' => [], 'indexes' => [], 'foreign_keys' => []];

            $tableInfo = ['name' => $tbl, 'columns' => []];

// Columns (names only)
foreach ($pdo->query("SHOW COLUMNS FROM `{$dbName}`.`{$tbl}`") as $col) {
    $tableInfo['columns'][] = (string)($col['Field'] ?? '');
}

$dbInfo['tables'][] = $tableInfo;
        }

        $DB_RESULT[] = $dbInfo;
    }
} catch (Throwable $e) {
    $DB_ERRORS[] = $e->getMessage();
}

// ---------- HTML ----------
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Inspect Files & DB</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    :root { --fg:#111; --muted:#666; --bg:#fff; --card:#f7f7f9; --code:#f0f0f0; }
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; color: var(--fg); background: var(--bg); }
    header { position: sticky; top:0; background: #fff; border-bottom: 1px solid #eee; padding: 12px 16px; z-index: 1;}
    header h1 { margin: 0; font-size: 18px; }
    main { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 16px; }
    section { background: var(--card); border: 1px solid #eee; border-radius: 12px; padding: 16px; }
    .meta { color: var(--muted); font-size: 12px; margin-left: 8px; }
    .tree { list-style: none; padding-left: 18px; margin: 8px 0; }
    .tree .dir, .tree .file { margin: 2px 0; }
    details > summary { cursor: pointer; }
    code, pre { background: var(--code); padding: 2px 6px; border-radius: 6px; }
    .error { color: #a40000; background: #ffecec; border: 1px solid #f5c2c2; padding: 8px; border-radius: 8px; margin: 8px 0; }
    .pill { display:inline-block; background:#eee; border-radius:999px; padding:2px 8px; font-size:12px; margin-left:6px; }
    table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
    th, td { border-bottom: 1px solid #eaeaea; padding: 6px 8px; text-align: left; font-size: 13px; vertical-align: top; }
    th { background: #fafafa; }
    .columns, .indexes, .fks { margin-bottom: 16px; }
    .note { color: var(--muted); font-size: 12px; }
    @media (max-width: 960px) { main { grid-template-columns: 1fr; } }
    .actions { display:flex; gap:8px; margin:8px 0 12px; }
.actions button {
    font: inherit; padding: 6px 10px; border-radius: 8px; border: 1px solid #ddd; background:#fafafa; cursor: pointer;
}
.actions button:hover { background:#f0f0f0; }
.actions button:active { transform: translateY(1px); }
</style>
</head>
<body>
<header>
    <h1>Inspect Files &amp; DB
        <?php
$ROOT_DISPLAY = ($ROOT === $HERE) ? '.' : ltrim(str_replace($HERE . DIRECTORY_SEPARATOR, '', $ROOT), DIRECTORY_SEPARATOR);
?>
<span class="pill">start=<?=h($ROOT_DISPLAY)?></span>
        <span class="pill">hidden=<?=$SHOW_HIDDEN?'on':'off'?></span>
        <span class="pill">system_dbs=<?=$INCLUDE_SYSTEM_DBS?'on':'off'?></span>
        <span class="pill">max_depth=<?=intval($MAX_DEPTH)?></span>
    </h1>
    <div class="note">
        Toggle options via query string. Example:
        <code>?show_hidden=1&amp;include_system_dbs=1&amp;max_depth=6</code>
    </div>
</header>

<main>



<section id="dir-section">
    <h2>Directory Tree</h2>
    <div class="actions">
        <button type="button" onclick="toggleAllInSection('dir-section', true)">Expand all</button>
        <button type="button" onclick="toggleAllInSection('dir-section', false)">Collapse all</button>
    </div>
    <div class="note">
        Root: <code><?=h($ROOT)?></code>
        <?php if (!empty($IGNORE_NAMES)): ?>
            &nbsp;·&nbsp;Ignoring: <code><?=h(implode(', ', $IGNORE_NAMES))?></code>
        <?php else: ?>
            &nbsp;·&nbsp;Ignoring: <code>(nothing)</code>
        <?php endif; ?>
    </div>
    <?php renderDirTree($ROOT, $IGNORE_NAMES, 0, $MAX_DEPTH); ?>
</section>



    <section id="db-section">
    <h2>Database Structure</h2>
    <div class="actions">
        <button type="button" onclick="toggleAllInSection('db-section', true)">Expand all</button>
        <button type="button" onclick="toggleAllInSection('db-section', false)">Collapse all</button>
    </div>
<?php if (!empty($DB_NOTES)): ?>
    <?php foreach ($DB_NOTES as $n): ?>
        <div class="note"><?=h($n)?></div>
    <?php endforeach; ?>
<?php endif; ?>
        <?php if ($DB_ERRORS): ?>
            <?php foreach ($DB_ERRORS as $err): ?>
                <div class="error"><?=h($err)?></div>
            <?php endforeach; ?>
            <div class="note">
                Tip: If your project has <code>includes/db_connect.php</code> that creates <code>$pdo</code>, this script will use it.
                Otherwise provide <code>db_host</code>, <code>db_port</code>, <code>db_user</code>, <code>db_pass</code> in the query string.
            </div>
        <?php elseif (!$DB_RESULT): ?>
            <div class="note">No databases found or insufficient privileges.</div>
        <?php else: ?>
            <?php foreach ($DB_RESULT as $db): ?>
                <details open>
                    <summary><strong>🗄️ <?=h($db['name'])?></strong>
                        <span class="meta"><?=count($db['tables'])?> table(s)</span>
                    </summary>
                    <?php if (empty($db['tables'])): ?>
                        <div class="note">No tables.</div>
                    <?php else: ?>
                        <?php foreach ($db['tables'] as $tbl): ?>
                            <details>
    <summary>📦 <?=h($tbl['name'])?> <span class="meta"><?=count($tbl['columns'])?> column(s)</span></summary>
    <?php if (empty($tbl['columns'])): ?>
        <div class="note">No columns found.</div>
    <?php else: ?>
        <ul class="tree" style="margin-top:6px;">
            <?php foreach ($tbl['columns'] as $colName): ?>
                <li>🔹 <?=h($colName)?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</details>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
    <script>
function toggleAllInSection(sectionId, open) {
    var root = document.getElementById(sectionId);
    if (!root) return;
    var details = root.querySelectorAll('details');
    details.forEach(function(d){ d.open = !!open; });
}
</script>
</body>
</html>
