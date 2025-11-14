<?php
/**
 * Step-by-step debug to find duplication source
 */

require_once 'includes/db_connect.php';

echo "<h1>Step-by-Step Debug</h1>";

// Step 1: Get raw categories
echo "<h2>Step 1: Raw Categories Query</h2>";
$categories_query = "SELECT id, name, icon FROM categories ORDER BY name ASC";
$categories_stmt = $pdo->query($categories_query);
$raw_categories = $categories_stmt->fetchAll();

echo "Raw result count: " . count($raw_categories) . "<br>";
foreach ($raw_categories as $i => $cat) {
    echo "Index $i: ID {$cat['id']} = {$cat['name']}<br>";
}

// Step 2: Build array with ID as key
echo "<h2>Step 2: Building Categories Array</h2>";
$categories = [];
foreach ($raw_categories as $i => $cat) {
    $cat_id = $cat['id'];

    echo "Processing raw category index $i (ID $cat_id): {$cat['name']}<br>";

    if (!isset($categories[$cat_id])) {
        $categories[$cat_id] = [
            'id' => $cat_id,
            'name' => $cat['name'],
            'icon' => $cat['icon'],
            'subcategories' => []
        ];
        echo "  -> Added to array. Array now has " . count($categories) . " items<br>";
    } else {
        echo "  -> SKIPPED (already exists)<br>";
    }
}

echo "<br>After building, categories array has " . count($categories) . " items:<br>";
foreach ($categories as $id => $cat) {
    echo "Key $id: {$cat['name']}<br>";
}

// Step 3: Get subcategories (check if this causes duplication)
echo "<h2>Step 3: Adding Subcategories</h2>";
$step3_categories = $categories; // Copy for comparison

foreach ($step3_categories as $category_id => &$category) {
    echo "<br>Processing category ID $category_id: {$category['name']}<br>";

    $subcategories_query = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC";
    $subcategories_stmt = $pdo->prepare($subcategories_query);
    $subcategories_stmt->execute([$category_id]);
    $subcategories = $subcategories_stmt->fetchAll();

    echo "  Found " . count($subcategories) . " subcategories<br>";
    $category['subcategories'] = $subcategories;

    echo "  Array now has " . count($step3_categories) . " items<br>";
}

echo "<br>After subcategories, array has " . count($step3_categories) . " items:<br>";
foreach ($step3_categories as $id => $cat) {
    echo "Key $id: {$cat['name']} ({$cat['id']})<br>";
}

// Step 4: Check if array_values causes issues
echo "<h2>Step 4: Converting with array_values</h2>";
$final_categories = array_values($step3_categories);

echo "After array_values, array has " . count($final_categories) . " items:<br>";
foreach ($final_categories as $i => $cat) {
    echo "Index $i: ID {$cat['id']} = {$cat['name']}<br>";
}

// Step 5: Check PHP version and array behavior
echo "<h2>Step 5: Environment Check</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Memory usage: " . memory_get_usage(true) . " bytes<br>";

?>