<?php
// quick-test.php
require_once 'includes/db.php';

echo "<h2>Quick Display Test</h2>";

// Method 1: Use any existing order
$sql = "SELECT id, order_number, customer_nickname FROM orders LIMIT 3";
$existingOrders = $pdo->query($sql)->fetchAll();

if (empty($existingOrders)) {
    echo "<p>No orders found. Creating test orders first...</p>";
    
    // Create one simple test order
    $sql = "INSERT INTO orders (order_number, order_type, subtotal, tax_amount, total_amount, status, order_date) 
            VALUES ('QUICK-TEST-001', 'walkin', 100.00, 12.00, 112.00, 'pending', CURDATE())";
    $pdo->exec($sql);
    $orderId = $pdo->lastInsertId();
    
    $existingOrders = [['id' => $orderId, 'order_number' => 'QUICK-TEST-001', 'customer_nickname' => null]];
}

echo "<p>Found " . count($existingOrders) . " existing orders.</p>";

// Add display entries for these orders
$counter = 1;
foreach ($existingOrders as $order) {
    $displayName = $order['customer_nickname'] ?: "Counter Order {$counter}";
    $statuses = ['waiting', 'preparing', 'ready'];
    $status = $statuses[$counter - 1] ?? 'waiting';
    
    $sql = "INSERT INTO order_display_status (order_id, display_name, order_number, status, estimated_time) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                estimated_time = VALUES(estimated_time)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order['id'], $displayName, $order['order_number'], $status, 15]);
    
    echo "Added display entry for: {$order['order_number']} as '{$displayName}' with status: {$status}<br>";
    $counter++;
}

echo "<h3 style='color: green;'>✅ Display test data added!</h3>";
echo "<p><a href='display/' target='_blank'>View Display Now</a></p>";
?>