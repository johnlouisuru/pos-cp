<?php
// simple-test.php - NO ERRORS VERSION
require_once 'includes/db.php';

echo "<h2>🚀 Display System Test - Simple Version</h2>";
echo "<div style='padding: 20px; background: #f0f8ff; border-radius: 10px;'>";

try {
    // Clean first
    $pdo->exec("DELETE FROM order_display_status WHERE order_number LIKE 'TEST-%'");
    $pdo->exec("DELETE FROM orders WHERE order_number LIKE 'TEST-%'");
    
    echo "<p>✅ Cleaned old test data</p>";
    
    // Create test orders
    $testOrders = [
        ['TEST-01', 'online', 'John', 'waiting', 15],
        ['TEST-02', 'online', 'Jane', 'preparing', 20],
        ['TEST-03', 'walkin', null, 'ready', 10]
    ];
    
    foreach ($testOrders as $order) {
        // Insert order
        $sql = "INSERT INTO orders (order_number, order_type, customer_nickname, subtotal, tax_amount, total_amount, status, order_date) 
                VALUES (?, ?, ?, 100, 12, 112, 'pending', CURDATE())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order[0], $order[1], $order[2]]);
        $orderId = $pdo->lastInsertId();
        
        // Insert display entry
        $displayName = $order[2] ?: 'Counter';
        $sql2 = "INSERT INTO order_display_status (order_id, display_name, order_number, status, estimated_time) 
                 VALUES (?, ?, ?, ?, ?)";
        
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$orderId, $displayName, $order[0], $order[3], $order[4]]);
        
        echo "<p>Created: {$order[0]} - {$displayName} ({$order[3]})</p>";
    }
    
    echo "<hr>";
    
    // Test the getProcessingOrders function
    echo "<h3>Testing Display Function:</h3>";
    
    function testGetProcessingOrders($limit = 5) {
        global $pdo;
        
        $sql = "SELECT DISTINCT ods.*, o.order_type, o.total_amount
                FROM order_display_status ods
                JOIN orders o ON ods.order_id = o.id
                WHERE ods.status IN ('waiting', 'preparing', 'ready')
                ORDER BY ods.status, ods.created_at";
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();
        
        echo "<p>Found " . count($results) . " orders for display</p>";
        
        if (count($results) > 0) {
            echo "<table border='1' cellpadding='8'><tr>
                  <th>Order #</th><th>Name</th><th>Status</th><th>Type</th>
                  </tr>";
            
            foreach ($results as $row) {
                echo "<tr><td>{$row['order_number']}</td>
                      <td>{$row['display_name']}</td>
                      <td>{$row['status']}</td>
                      <td>{$row['order_type']}</td></tr>";
            }
            echo "</table>";
        }
        
        return array_slice($results, 0, $limit);
    }
    
    testGetProcessingOrders();
    
    echo "<hr>";
    
    // Provide links
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/display/";
    echo "<h3>🎉 Ready to View Display!</h3>";
    echo "<p><a href='$url' target='_blank' 
          style='padding: 15px 30px; background: #28a745; color: white; 
                 text-decoration: none; border-radius: 8px; font-size: 20px; display: inline-block;'>
          <strong>👉 CLICK HERE TO VIEW DISPLAY</strong>
          </a></p>";
    
    echo "<p>If you see an error, check the PHP error log or try:</p>";
    echo "<ul>
          <li><a href='check-display.php'>Check Display Status</a></li>
          <li><a href='admin/display-control.php'>Display Controls</a></li>
          </ul>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
    echo "<pre>Debug: " . print_r($pdo->errorInfo(), true) . "</pre>";
}

echo "</div>";
?>