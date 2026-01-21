<?php
// test-display.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db.php';
require_once '../includes/display-functions.php';

echo "<h2>Testing Display Functions</h2>";

// Test 1: Check database connection
echo "<h3>Test 1: Database Connection</h3>";
try {
    $test = $pdo->query("SELECT COUNT(*) as count FROM order_items WHERE order_id = 2");
    $result = $test->fetch();
    echo "Items for order 2: " . $result['count'] . "<br>";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// Test 2: Direct test of getOrderItemsForDisplay
echo "<h3>Test 2: getOrderItemsForDisplay(2)</h3>";
$items = getOrderItemsForDisplay(2);
echo "Number of items returned: " . count($items) . "<br>";
echo "<pre>";
print_r($items);
echo "</pre>";

// Test 3: Check if items array has expected keys
if (!empty($items)) {
    echo "<h3>Test 3: Array Keys</h3>";
    foreach (array_keys($items[0]) as $key) {
        echo "Key: '$key'<br>";
    }
}

// Test 4: Test with your display logic
echo "<h3>Test 4: Display Simulation</h3>";
if (!empty($items)) {
    foreach ($items as $item) {
        echo "<div>";
        echo "<strong>Quantity:</strong> " . $item['quantity'] . "x<br>";
        echo "<strong>Product Name:</strong> " . ($item['name'] ?? 'NO NAME KEY') . "<br>";
        echo "<strong>Special Request:</strong> " . ($item['special_request'] ?? 'None') . "<br>";
        echo "</div><hr>";
    }
} else {
    echo "No items found!<br>";
}

// Test 5: Check the actual $orders array from getProcessingOrders
echo "<h3>Test 5: getProcessingOrders()</h3>";
$orders = getProcessingOrders(10);
echo "Total orders: " . count($orders) . "<br>";
foreach ($orders as $order) {
    echo "Order ID: " . $order['order_id'] . " | Order Number: " . $order['order_number'] . "<br>";
}
?>