<?php
/**
 * Debug Search Script
 * Test search functionality directly with database
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Search Debug Test</h1>";

// Test database connection
try {
    require_once 'includes/db_connect.php';
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Test 1: Check if categories exist
echo "<h2>1. Categories Test</h2>";
try {
    $stmt = $pdo->query("SELECT id, name, icon FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();

    echo "<h3>All Categories:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Icon</th></tr>";

    $found_big_chill = false;
    foreach ($categories as $cat) {
        echo "<tr><td>{$cat['id']}</td><td>{$cat['name']}</td><td>{$cat['icon']}</td></tr>";
        if (stripos($cat['name'], 'big chill') !== false || stripos($cat['name'], 'big') !== false) {
            $found_big_chill = true;
            echo "<tr style='background-color: yellow;'><td colspan='3'>🎯 Found relevant category: {$cat['name']}</td></tr>";
        }
    }
    echo "</table>";

    if ($found_big_chill) {
        echo "✅ Found category containing 'Big Chill'<br>";
    } else {
        echo "❌ No category found containing 'Big Chill'<br>";
    }

} catch (Exception $e) {
    echo "❌ Error fetching categories: " . $e->getMessage() . "<br>";
}

// Test 2: Check FULLTEXT indexes
echo "<h2>2. FULLTEXT Index Test</h2>";
try {
    $stmt = $pdo->query("SHOW INDEX FROM categories WHERE Index_type = 'FULLTEXT'");
    $indexes = $stmt->fetchAll();

    if (count($indexes) > 0) {
        echo "✅ Categories table has FULLTEXT indexes:<br>";
        foreach ($indexes as $index) {
            echo "- {$index['Key_name']} on column(s): {$index['Column_name']}<br>";
        }
    } else {
        echo "⚠️ No FULLTEXT indexes on categories table<br>";
        echo "ℹ️ This might be why search isn't working<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking indexes: " . $e->getMessage() . "<br>";
}

// Test 3: Test LIKE search (alternative to FULLTEXT)
echo "<h2>3. Test LIKE Search for 'big'</h2>";
try {
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE name LIKE ? ORDER BY name");
    $stmt->execute(['%big%']);
    $like_results = $stmt->fetchAll();

    echo "<h3>LIKE Search Results:</h3>";
    if (count($like_results) > 0) {
        foreach ($like_results as $result) {
            echo "📋 {$result['name']} (ID: {$result['id']})<br>";
        }
    } else {
        echo "❌ No results found with LIKE '%big%'<br>";
    }
} catch (Exception $e) {
    echo "❌ Error with LIKE search: " . $e->getMessage() . "<br>";
}

// Test 4: Test FULLTEXT search
echo "<h2>4. Test FULLTEXT Search for 'big'</h2>";
try {
    $stmt = $pdo->prepare("SELECT id, name, MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance FROM categories WHERE MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) ORDER BY relevance DESC");
    $stmt->execute(['big', 'big']);
    $fulltext_results = $stmt->fetchAll();

    echo "<h3>FULLTEXT Search Results:</h3>";
    if (count($fulltext_results) > 0) {
        foreach ($fulltext_results as $result) {
            echo "📋 {$result['name']} (ID: {$result['id']}) Relevance: {$result['relevance']}<br>";
        }
    } else {
        echo "❌ No results found with FULLTEXT search<br>";
    }
} catch (Exception $e) {
    echo "❌ Error with FULLTEXT search: " . $e->getMessage() . "<br>";
    echo "ℹ️ This confirms FULLTEXT search is not working<br>";
}

// Test 5: Test search autocomplete without authentication
echo "<h2>5. Test Search Autocomplete (No Auth)</h2>";
$query = 'big';
echo "<h3>Simulating search autocomplete for: '$query'</h3>";

try {
    // Simulate the autocomplete search without authentication
    $stmt = $pdo->prepare("
        SELECT id, name, icon, 'category' as type,
               MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
        FROM categories
        WHERE MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
        ORDER BY relevance DESC
        LIMIT 3
    ");
    $stmt->execute([$query, $query]);
    $autocomplete_results = $stmt->fetchAll();

    echo "<h4>Autocomplete Results:</h4>";
    if (count($autocomplete_results) > 0) {
        foreach ($autocomplete_results as $result) {
            echo "🔍 {$result['name']} (Type: {$result['type']}, Relevance: {$result['relevance']})<br>";
        }
    } else {
        echo "❌ No autocomplete results found<br>";
    }

    // Test fallback LIKE for autocomplete
    echo "<h4>Fallback LIKE Autocomplete Results:</h4>";
    $stmt = $pdo->prepare("SELECT id, name, icon, 'category' as type FROM categories WHERE name LIKE ? ORDER BY name LIMIT 3");
    $stmt->execute(['%' . $query . '%']);
    $like_results = $stmt->fetchAll();

    if (count($like_results) > 0) {
        foreach ($like_results as $result) {
            echo "🔍 {$result['name']} (Type: {$result['type']})<br>";
        }
    } else {
        echo "❌ No LIKE autocomplete results found<br>";
    }

} catch (Exception $e) {
    echo "❌ Error with autocomplete test: " . $e->getMessage() . "<br>";
}

// Test 6: Test actual search API endpoint
echo "<h2>6. Test Search Autocomplete API</h2>";
echo "<h3>Testing search_autocomplete.php?q=big</h3>";

// Try to call the autocomplete API
if (file_exists('search_autocomplete.php')) {
    // We can't directly test this in a browser environment, but we can simulate
    echo "✅ search_autocomplete.php file exists<br>";
    echo "ℹ️ To test manually: search_autocomplete.php?q=big<br>";
} else {
    echo "❌ search_autocomplete.php file missing<br>";
}

// Test 7: Test search_fixed.php
echo "<h2>7. Test Search Results Page</h2>";
if (file_exists('search_fixed.php')) {
    echo "✅ search_fixed.php file exists<br>";
    echo "ℹ️ To test manually: search_fixed.php?q=big<br>";
} else {
    echo "❌ search_fixed.php file missing<br>";
}

echo "<h2>Debug Summary</h2>";
echo "<p>Based on the tests above, the issue is likely:</p>";
echo "<ol>";
echo "<li>Missing FULLTEXT indexes on database tables</li>";
echo "<li>OR the search query is not finding 'Big Chill' due to case sensitivity</li>";
echo "<li>OR there's an authentication/session issue in the search APIs</li>";
echo "</ol>";

echo "<p><strong>Immediate Fix Needed:</strong> Create a fallback search that uses LIKE when FULLTEXT fails.</p>";
echo "<p><a href='index.php'>🔍 Back to Main Page</a></p>";
?>