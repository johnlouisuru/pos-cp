<?php
// create-test-kitchen.php - COMPLETE FIX
require_once 'includes/db.php';

echo "<h2>🍳 COMPLETE Kitchen Test Setup</h2>";
echo "<div style='padding: 20px; background: #f0f8ff; border-radius: 10px;'>";

try {
    echo "<h3>Phase 1: Database Setup</h3>";
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Clean up old test data
    echo "Cleaning old test data...<br>";
    $pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'KTEST-%')");
    $pdo->exec("DELETE FROM order_display_status WHERE order_number LIKE 'KTEST-%'");
    $pdo->exec("DELETE FROM orders WHERE order_number LIKE 'KTEST-%'");
    echo "✅ Cleaned old test data<br>";
    
    // 2. Ensure we have categories
    echo "Checking categories...<br>";
    $categories = $pdo->query("SELECT COUNT(*) as cnt FROM categories")->fetch()['cnt'];
    
    if ($categories == 0) {
        echo "Creating default categories...<br>";
        $pdo->exec("
            INSERT INTO categories (name, display_order) VALUES 
            ('Burgers', 1),
            ('Sides', 2),
            ('Drinks', 3),
            ('Pizza', 4),
            ('Desserts', 5)
        ");
        echo "✅ Created 5 categories<br>";
    }
    
    // 3. Get or create products with ALL required fields
    echo "Checking products...<br>";
    $productsCount = $pdo->query("SELECT COUNT(*) as cnt FROM products")->fetch()['cnt'];
    
    if ($productsCount == 0) {
        echo "Creating test products...<br>";
        
        // Get category IDs
        $catIds = $pdo->query("SELECT id FROM categories ORDER BY display_order LIMIT 5")->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Create products for each category
        $testProducts = [
            ['Classic Burger', 180.00, $catIds[0] ?? 1, 1, 10],
            ['Cheeseburger', 200.00, $catIds[0] ?? 1, 1, 10],
            ['Bacon Burger', 220.00, $catIds[0] ?? 1, 1, 10],
            ['French Fries', 90.00, $catIds[1] ?? 2, 2, 5],
            ['Onion Rings', 110.00, $catIds[1] ?? 2, 2, 5],
            ['Soft Drink', 60.00, $catIds[2] ?? 3, 3, 0],
            ['Iced Tea', 70.00, $catIds[2] ?? 3, 3, 0],
            ['Pepperoni Pizza', 220.00, $catIds[3] ?? 4, 4, 15],
            ['Chocolate Cake', 120.00, $catIds[4] ?? 5, 5, 10]
        ];
        
        foreach ($testProducts as $product) {
            $sql = "INSERT INTO products (name, price, category_id, station_id, preparation_time) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($product);
        }
        echo "✅ Created 9 test products<br>";
    } else {
        // Ensure existing products have station_id
        echo "Updating existing products...<br>";
        
        // Get products without station_id
        $updateSql = "
            UPDATE products 
            SET station_id = 
                CASE 
                    WHEN category_id = 1 THEN 1
                    WHEN category_id = 2 THEN 2
                    WHEN category_id = 3 THEN 3
                    WHEN category_id = 4 THEN 4
                    WHEN category_id = 5 THEN 5
                    ELSE 1
                END
            WHERE station_id IS NULL
        ";
        $updated = $pdo->exec($updateSql);
        echo "✅ Updated $updated products with station IDs<br>";
    }
    
    echo "<h3>Phase 2: Create Test Orders</h3>";
    
    // 4. Create test orders
    $testOrders = [
        ['KTEST-001', 'walkin', 'Table 3', 560.00],
        ['KTEST-002', 'online', 'Sarah', 450.00],
        ['KTEST-003', 'walkin', 'Counter', 320.00]
    ];
    
    $orderIds = [];
    foreach ($testOrders as $order) {
        $subtotal = round($order[3] / 1.12, 2);
        $tax = round($order[3] - $subtotal, 2);
        
        $sql = "INSERT INTO orders (order_number, order_type, customer_nickname, subtotal, tax_amount, total_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'preparing')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order[0], $order[1], $order[2], $subtotal, $tax, $order[3]]);
        $orderId = $pdo->lastInsertId();
        $orderIds[$order[0]] = $orderId;
        
        echo "Created order: {$order[0]} (ID: $orderId)<br>";
    }
    
    // 5. Add display entries
    foreach ($orderIds as $orderNumber => $orderId) {
        $displayName = $testOrders[array_search($orderNumber, array_column($testOrders, 0))][2];
        $estimatedTime = rand(10, 25);
        
        $sql = "INSERT INTO order_display_status (order_id, display_name, order_number, status, estimated_time) 
                VALUES (?, ?, ?, 'preparing', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderId, $displayName, $orderNumber, $estimatedTime]);
        
        echo "Added display for: $orderNumber<br>";
    }
    
    echo "<h3>Phase 3: Add Kitchen Items</h3>";
    
    // 6. Get products from each station
    $stations = [1, 2, 3]; // Burger, Fry, Drink stations
    $stationProducts = [];
    
    foreach ($stations as $stationId) {
        $sql = "SELECT id, name, price FROM products WHERE station_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$stationId]);
        $stationProducts[$stationId] = $stmt->fetchAll();
        
        if (empty($stationProducts[$stationId])) {
            echo "⚠️ Warning: No products found for station $stationId<br>";
        }
    }
    
    // 7. Add items to orders
    $itemsAdded = 0;
    $statuses = ['pending', 'preparing'];
    
    foreach ($orderIds as $orderNumber => $orderId) {
        echo "Adding items to $orderNumber:<br>";
        
        // Add items from different stations
        foreach ($stations as $stationId) {
            if (!empty($stationProducts[$stationId])) {
                $product = $stationProducts[$stationId][array_rand($stationProducts[$stationId])];
                $quantity = rand(1, 3);
                $status = $statuses[array_rand($statuses)];
                $specialRequest = rand(0, 1) ? 'No onions' : null;
                
                $sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, status, special_request) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $orderId,
                    $product['id'],
                    $quantity,
                    $product['price'],
                    $product['price'] * $quantity,
                    $status,
                    $specialRequest
                ]);
                
                $itemsAdded++;
                echo "&nbsp;&nbsp;• Added {$quantity}x {$product['name']} (status: $status)<br>";
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ SUCCESS! Created $itemsAdded kitchen items</h3>";
    
    // Show detailed summary
    echo "<h4>Kitchen Test Data Summary:</h4>";
    
    $summarySql = "
        SELECT 
            o.order_number,
            ods.display_name,
            COUNT(oi.id) as total_items,
            GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ') as items,
            SUM(CASE WHEN oi.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN oi.status = 'preparing' THEN 1 ELSE 0 END) as preparing
        FROM orders o
        JOIN order_display_status ods ON o.id = ods.order_id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.order_number LIKE 'KTEST-%'
        GROUP BY o.id
        ORDER BY o.created_at
    ";
    
    $ordersSummary = $pdo->query($summarySql)->fetchAll();
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #007bff; color: white;'>
            <th>Order #</th>
            <th>Customer</th>
            <th>Total Items</th>
            <th>Items</th>
            <th>Pending</th>
            <th>Preparing</th>
          </tr>";
    
    foreach ($ordersSummary as $row) {
        echo "<tr>";
        echo "<td><strong>{$row['order_number']}</strong></td>";
        echo "<td>{$row['display_name']}</td>";
        echo "<td>{$row['total_items']}</td>";
        echo "<td>{$row['items']}</td>";
        echo "<td>{$row['pending']}</td>";
        echo "<td>{$row['preparing']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Station summary
    echo "<h4>Items by Kitchen Station:</h4>";
    
    $stationSql = "
        SELECT 
            ks.name as station,
            COUNT(oi.id) as total_items,
            SUM(CASE WHEN oi.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN oi.status = 'preparing' THEN 1 ELSE 0 END) as preparing
        FROM kitchen_stations ks
        LEFT JOIN products p ON ks.id = p.station_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.order_number LIKE 'KTEST-%'
        GROUP BY ks.id
        HAVING total_items > 0
        ORDER BY ks.display_order
    ";
    
    $stationSummary = $pdo->query($stationSql)->fetchAll();
    
    if (!empty($stationSummary)) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #28a745; color: white;'>
                <th>Station</th>
                <th>Total Items</th>
                <th>Pending</th>
                <th>Preparing</th>
              </tr>";
        
        foreach ($stationSummary as $row) {
            echo "<tr>";
            echo "<td><strong>{$row['station']}</strong></td>";
            echo "<td>{$row['total_items']}</td>";
            echo "<td>{$row['pending']}</td>";
            echo "<td>{$row['preparing']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ No items found in kitchen stations</p>";
    }
    
    echo "<div class='mt-4 text-center'>";
    echo "<a href='display/kitchen.php' target='_blank' class='btn btn-success btn-lg' style='padding: 15px 30px;'>
            <i class='fas fa-utensils'></i> VIEW KITCHEN DISPLAY
          </a>";
    echo "<a href='debug-kitchen.php' class='btn btn-info btn-lg ms-3' style='padding: 15px 30px;'>
            <i class='fas fa-bug'></i> DEBUG INFO
          </a>";
    echo "</div>";
    
    echo "<div class='mt-3 alert alert-info'>";
    echo "<h5><i class='fas fa-info-circle'></i> What to Expect:</h5>";
    echo "<ul>";
    echo "<li>3 test orders (KTEST-001, KTEST-002, KTEST-003)</li>";
    echo "<li>Items distributed across Burger, Fry, and Drink stations</li>";
    echo "<li>Mixed statuses (some pending, some preparing)</li>";
    echo "<li>Click on any item in kitchen display to update its status</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h3 style='color: red;'>❌ ERROR: " . $e->getMessage() . "</h3>";
    echo "<pre>Debug Info: " . print_r($pdo->errorInfo(), true) . "</pre>";
}

echo "</div>";
?>