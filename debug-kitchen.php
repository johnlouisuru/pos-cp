<?php
// debug-kitchen.php
require_once 'includes/db.php';

echo "<h2>🔧 Kitchen Display Debug</h2>";
echo "<div style='padding: 20px; background: #f0f0f0; border-radius: 10px;'>";

// Check database connection
try {
    $pdo->query("SELECT 1");
    echo "✅ Database connection: OK<br>";
} catch (Exception $e) {
    echo "❌ Database connection: FAILED<br>";
    exit;
}

// Check tables
$tables = ['kitchen_stations', 'products', 'order_items', 'orders', 'order_display_status'];
foreach ($tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "✅ Table '$table': EXISTS<br>";
    } catch (Exception $e) {
        echo "❌ Table '$table': MISSING<br>";
    }
}

echo "<hr>";

// Check order_items columns
echo "<h4>order_items Table Structure:</h4>";
$columns = $pdo->query("SHOW COLUMNS FROM order_items")->fetchAll();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";

// Check kitchen stations
echo "<h4>Kitchen Stations:</h4>";
$stations = $pdo->query("SELECT * FROM kitchen_stations")->fetchAll();
if (empty($stations)) {
    echo "❌ No kitchen stations found<br>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Color</th><th>Active</th></tr>";
    foreach ($stations as $station) {
        echo "<tr>";
        echo "<td>{$station['id']}</td>";
        echo "<td>{$station['name']}</td>";
        echo "<td>{$station['color_code']}</td>";
        echo "<td>" . ($station['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// Check products with stations
echo "<h4>Products with Stations:</h4>";
$products = $pdo->query("
    SELECT p.name, p.station_id, ks.name as station_name 
    FROM products p 
    LEFT JOIN kitchen_stations ks ON p.station_id = ks.id 
    WHERE p.station_id IS NOT NULL 
    LIMIT 10
")->fetchAll();

if (empty($products)) {
    echo "❌ No products assigned to stations<br>";
    echo "Run this SQL: <pre>UPDATE products SET station_id = 1 WHERE name LIKE '%burger%' LIMIT 5;</pre>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Product</th><th>Station ID</th><th>Station Name</th></tr>";
    foreach ($products as $product) {
        echo "<tr>";
        echo "<td>{$product['name']}</td>";
        echo "<td>{$product['station_id']}</td>";
        echo "<td>{$product['station_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// Test the kitchen API
echo "<h4>Test Kitchen API:</h4>";
echo '<a href="display/kitchen-api/get-kitchen-orders.php" target="_blank">Test API Directly</a><br>';

// Simulate API call
try {
    $testSql = "
        SELECT 
            oi.id as item_id,
            oi.order_id,
            oi.quantity,
            IFNULL(oi.status, oi.item_status) as item_status,
            oi.special_request,
            p.name as product_name,
            p.station_id,
            ods.display_name,
            ods.order_number,
            o.created_at as order_created
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN order_display_status ods ON o.id = ods.order_id
        WHERE p.station_id IS NOT NULL
            AND (oi.status IN ('pending', 'preparing') OR oi.item_status IN ('pending', 'preparing'))
            AND o.status NOT IN ('cancelled', 'completed')
        LIMIT 5
    ";
    
    $testResults = $pdo->query($testSql)->fetchAll();
    
    if (empty($testResults)) {
        echo "❌ No kitchen items found. Make sure:<br>";
        echo "1. Products have station_id<br>";
        echo "2. Orders have items<br>";
        echo "3. Items have status 'pending' or 'preparing'<br>";
    } else {
        echo "✅ Found " . count($testResults) . " kitchen items<br>";
        echo "<pre>" . print_r($testResults, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "❌ Test query failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h4>Quick Fixes:</h4>";
echo '<button onclick="runQuickFix()" class="btn btn-primary">Run Quick Database Fix</button>';
echo '<div id="quickFixResult" class="mt-2"></div>';

echo "</div>";

echo "<script>
function runQuickFix() {
    fetch('kitchen-fix.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('quickFixResult').innerHTML = data;
            location.reload();
        });
}
</script>";
?>