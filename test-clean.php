<?php
// test-clean.php - ONE-CLICK TEST SOLUTION
require_once 'includes/db.php';

echo "<h1>🔄 Display System - Clean Test</h1>";
echo "<div style='padding: 20px; background: #f8f9fa; border-radius: 10px;'>";

try {
    // Step 1: Clean up
    echo "<h3>Step 1: Cleaning up old test data...</h3>";
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $deleteDisplay = $pdo->exec("DELETE FROM order_display_status");
    echo "Deleted $deleteDisplay display entries<br>";
    
    $deleteOrders = $pdo->exec("DELETE FROM orders WHERE order_number LIKE 'DISPLAY-%'");
    echo "Deleted $deleteOrders test orders<br>";
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<hr>";
    
    // Step 2: Create fresh test data
    echo "<h3>Step 2: Creating fresh test data...</h3>";
    
    $orders = [
        ['DISPLAY-001', 'online', 'Mike', 112.00],
        ['DISPLAY-002', 'online', 'Sarah', 224.00],
        ['DISPLAY-003', 'walkin', null, 336.00]
    ];
    
    foreach ($orders as $order) {
        $sql = "INSERT INTO orders (order_number, order_type, customer_nickname, subtotal, tax_amount, total_amount, status, order_date) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', CURDATE())";
        
        $subtotal = round($order[3] / 1.12, 2);
        $tax = round($order[3] - $subtotal, 2);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order[0], $order[1], $order[2], $subtotal, $tax, $order[3]]);
        
        $orderId = $pdo->lastInsertId();
        echo "Created order: {$order[0]} (ID: $orderId)<br>";
        
        // Add display entry
        $statuses = ['waiting', 'preparing', 'ready'];
        $status = $statuses[array_search($order[0], array_column($orders, 0))];
        $displayName = $order[2] ?: 'Counter Order';
        
        $sql2 = "INSERT INTO order_display_status (order_id, display_name, order_number, status, estimated_time) 
                 VALUES (?, ?, ?, ?, ?)";
        
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$orderId, $displayName, $order[0], $status, 15]);
        
        echo "&nbsp;&nbsp;↳ Added display entry with status: $status<br>";
    }
    
    echo "<hr>";
    
    // Step 3: Verify
    echo "<h3>Step 3: Verification...</h3>";
    
    $sql = "SELECT 
                COUNT(DISTINCT o.id) as order_count,
                COUNT(DISTINCT ods.id) as display_count
            FROM orders o
            LEFT JOIN order_display_status ods ON o.id = ods.order_id
            WHERE o.order_number LIKE 'DISPLAY-%'";
    
    $counts = $pdo->query($sql)->fetch();
    
    echo "Orders created: " . $counts['order_count'] . "<br>";
    echo "Display entries: " . $counts['display_count'] . "<br>";
    
    if ($counts['order_count'] == 3 && $counts['display_count'] == 3) {
        echo "<h2 style='color: green;'>✅ SUCCESS! Test data created properly.</h2>";
    } else {
        echo "<h2 style='color: orange;'>⚠️ Partial success. Check database.</h2>";
    }
    
    echo "<hr>";
    
    // Step 4: Show what was created
    echo "<h3>Step 4: Created Data Preview</h3>";
    
    $sql = "SELECT 
                o.order_number,
                o.customer_nickname,
                ods.display_name,
                ods.status,
                ods.estimated_time
            FROM orders o
            JOIN order_display_status ods ON o.id = ods.order_id
            WHERE o.order_number LIKE 'DISPLAY-%'
            ORDER BY ods.status, o.id";
    
    $results = $pdo->query($sql)->fetchAll();
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr style='background: #007bff; color: white;'>
            <th>Order #</th>
            <th>Customer</th>
            <th>Display Name</th>
            <th>Status</th>
            <th>Est. Time</th>
          </tr>";
    
    foreach ($results as $row) {
        $bgcolor = '';
        switch($row['status']) {
            case 'waiting': $bgcolor = '#fff3cd'; break;
            case 'preparing': $bgcolor = '#cce5ff'; break;
            case 'ready': $bgcolor = '#d4edda'; break;
        }
        
        echo "<tr style='background: $bgcolor;'>";
        echo "<td>{$row['order_number']}</td>";
        echo "<td>".($row['customer_nickname'] ?? 'Counter')."</td>";
        echo "<td>{$row['display_name']}</td>";
        echo "<td><strong>{$row['status']}</strong></td>";
        echo "<td>{$row['estimated_time']} min</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    
    // Step 5: Provide links
    echo "<h3>Step 5: Test Your Display</h3>";
    $displayUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/display/";
    echo "<p><a href='$displayUrl' target='_blank' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 18px;'>
            <strong>🚀 CLICK HERE TO VIEW DISPLAY</strong>
          </a></p>";
    
    echo "<p><a href='admin/display-control.php' style='padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>
            ⚙️ Go to Display Controls
          </a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ ERROR: " . $e->getMessage() . "</h2>";
    echo "<pre>Error details: " . print_r($pdo->errorInfo(), true) . "</pre>";
}

echo "</div>";
?>