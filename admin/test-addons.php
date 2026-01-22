<?php
// test-addons.php
require_once '../config/database.php';

echo "<h3>Testing Addon Configuration</h3>";

// Test product
$product_id = 13; // Brown Sugar 16oz

$sql = "SELECT id, name, has_addons FROM products WHERE id = $product_id";
$product = $pdo->query($sql)->fetch();
echo "<h4>Product: " . $product['name'] . " (ID: $product_id)</h4>";
echo "has_addons: " . ($product['has_addons'] ? 'YES' : 'NO') . "<br><br>";

// Check global addons
$sql = "SELECT * FROM addons WHERE is_global = 1 AND is_available = 1";
$global = $pdo->query($sql)->fetchAll();
echo "<h4>Global Addons:</h4>";
if (count($global) > 0) {
    foreach ($global as $addon) {
        echo "- {$addon['name']} (ID: {$addon['id']})<br>";
    }
} else {
    echo "None<br>";
}

// Check product-specific addons
$sql = "SELECT a.* FROM addons a 
        JOIN product_addons pa ON a.id = pa.addon_id 
        WHERE pa.product_id = $product_id AND a.is_available = 1";
$specific = $pdo->query($sql)->fetchAll();
echo "<h4>Product-Specific Addons:</h4>";
if (count($specific) > 0) {
    foreach ($specific as $addon) {
        echo "- {$addon['name']} (ID: {$addon['id']})<br>";
    }
} else {
    echo "None<br>";
}

// Test the API query directly
echo "<h4>API Query Results:</h4>";
$sql = "
    SELECT DISTINCT a.* 
    FROM addons a 
    LEFT JOIN product_addons pa ON a.id = pa.addon_id
    WHERE a.is_available = 1 
    AND (a.is_global = 1 OR pa.product_id = $product_id)
    ORDER BY a.name
";
$results = $pdo->query($sql)->fetchAll();
if (count($results) > 0) {
    foreach ($results as $addon) {
        echo "- {$addon['name']} (ID: {$addon['id']}, Global: {$addon['is_global']})<br>";
    }
} else {
    echo "No addons found with this query<br>";
}
?>