<?php
// test-display-data.php
require_once 'includes/db.php';

echo "<h2>Generating Test Data for Display System</h2>";

try {
    // First, check if test orders already exist
    $checkSql = "SELECT order_number FROM orders WHERE order_number LIKE 'DISPLAY-TEST-%'";
    $existing = $pdo->query($checkSql)->fetchAll();
    
    if (!empty($existing)) {
        echo "<p>Test orders already exist. Deleting first...</p>";
        
        // Disable foreign key checks temporarily
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Delete existing test data
        $deleteSql = "DELETE FROM order_display_status WHERE order_number LIKE 'DISPLAY-TEST-%'";
        $pdo->exec($deleteSql);
        
        $deleteSql = "DELETE FROM orders WHERE order_number LIKE 'DISPLAY-TEST-%'";
        $pdo->exec($deleteSql);
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    
    // Create unique order numbers using timestamp
    $timestamp = time();
    $orders = [
        ['order_number' => 'DISPLAY-TEST-' . ($timestamp + 1), 'order_type' => 'online', 'customer' => 'Mike', 'amount' => 504.00],
        ['order_number' => 'DISPLAY-TEST-' . ($timestamp + 2), 'order_type' => 'online', 'customer' => 'Sarah', 'amount' => 358.40],
        ['order_number' => 'DISPLAY-TEST-' . ($timestamp + 3), 'order_type' => 'walkin', 'customer' => null, 'amount' => 313.60]
    ];
    
    $orderIds = [];
    
    foreach ($orders as $order) {
        $sql = "INSERT INTO orders (order_number, order_type, customer_nickname, subtotal, tax_amount, total_amount, status, order_date, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())";
        
        $subtotal = round($order['amount'] / 1.12, 2); // Assuming 12% tax
        $tax = round($order['amount'] - $subtotal, 2);
        
        // Set status based on customer name for testing
        $status = 'pending';
        if ($order['customer'] == 'Sarah') $status = 'preparing';
        if ($order['customer'] === null) $status = 'ready';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $order['order_number'],
            $order['order_type'],
            $order['customer'],
            $subtotal,
            $tax,
            $order['amount'],
            $status
        ]);
        
        $orderIds[$order['order_number']] = $pdo->lastInsertId();
        echo "Created order: {$order['order_number']} with ID: " . $pdo->lastInsertId() . "<br>";
    }
    
    // Create display entries
    $displayStatuses = [
        ['order_number' => $orders[0]['order_number'], 'status' => 'waiting', 'minutes' => 15],
        ['order_number' => $orders[1]['order_number'], 'status' => 'preparing', 'minutes' => 20],
        ['order_number' => $orders[2]['order_number'], 'status' => 'ready', 'minutes' => 10]
    ];
    
    foreach ($displayStatuses as $display) {
        $orderId = $orderIds[$display['order_number']];
        $displayName = ($display['order_number'] == $orders[2]['order_number']) ? 'Counter Order' : 
                      (strpos($display['order_number'], 'Mike') !== false ? 'Mike' : 'Sarah');
        
        $sql = "INSERT INTO order_display_status (order_id, display_name, order_number, status, estimated_time, display_until) 
                VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $orderId,
            $displayName,
            $display['order_number'],
            $display['status'],
            $display['minutes']
        ]);
        
        echo "Created display entry for: {$display['order_number']} with status: {$display['status']}<br>";
    }
    
    echo "<h3 style='color: green;'>✅ Test data created successfully!</h3>";
    echo "<p><a href='display/' target='_blank' class='btn btn-success'>View Display</a></p>";
    echo "<p><a href='admin/display-control.php' class='btn btn-primary'>Admin Controls</a></p>";
    
    // Show what was created
    echo "<h4>Created Data:</h4>";
    $showSql = "SELECT 
                    o.order_number,
                    o.customer_nickname,
                    o.status as order_status,
                    ods.display_name,
                    ods.status as display_status,
                    ods.estimated_time
                FROM orders o
                JOIN order_display_status ods ON o.id = ods.order_id
                WHERE o.order_number LIKE 'DISPLAY-TEST-%'
                ORDER BY o.id";
    
    $results = $pdo->query($showSql)->fetchAll();
    
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Order #</th><th>Customer</th><th>Order Status</th><th>Display Name</th><th>Display Status</th><th>Est. Time</th></tr>";
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>{$row['order_number']}</td>";
        echo "<td>".($row['customer_nickname'] ?? 'Counter')."</td>";
        echo "<td>{$row['order_status']}</td>";
        echo "<td>{$row['display_name']}</td>";
        echo "<td>{$row['display_status']}</td>";
        echo "<td>{$row['estimated_time']} min</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<pre>Error Code: " . $e->getCode() . "</pre>";
    echo "<pre>SQL State: " . $pdo->errorCode() . "</pre>";
    
    // Show current orders for debugging
    echo "<h4>Current orders in database:</h4>";
    try {
        $debugSql = "SELECT id, order_number, customer_nickname FROM orders ORDER BY id DESC LIMIT 10";
        $debugResults = $pdo->query($debugSql)->fetchAll();
        echo "<pre>" . print_r($debugResults, true) . "</pre>";
    } catch (Exception $e2) {
        echo "Cannot fetch debug info: " . $e2->getMessage();
    }
}
?>