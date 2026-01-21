<?php
// fix-kitchen-now.php
require_once 'includes/db.php';

echo "<h2>🔧 Fix Kitchen Display NOW</h2>";
echo "<div style='padding: 20px; background: #fff3cd; border-radius: 10px;'>";

try {
    echo "<h4>Step 1: Cleaning duplicate stations...</h4>";
    
    // Delete all duplicate stations (keep only IDs 1-5)
    $deleted = $pdo->exec("DELETE FROM kitchen_stations WHERE id > 5");
    echo "✅ Deleted $deleted duplicate stations<br>";
    
    // Reset auto-increment
    $pdo->exec("ALTER TABLE kitchen_stations AUTO_INCREMENT = 6");
    
    echo "<h4>Step 2: Assigning products to stations...</h4>";
    
    // First, check if we have categories
    $categories = $pdo->query("SELECT id, name FROM categories LIMIT 5")->fetchAll();
    
    if (empty($categories)) {
        echo "⚠️ No categories found. Creating default categories...<br>";
        $pdo->exec("INSERT INTO categories (name) VALUES 
            ('Burgers'), ('Sides'), ('Drinks'), ('Pizza'), ('Desserts')");
        $categories = $pdo->query("SELECT id, name FROM categories")->fetchAll();
    }
    
    // Assign products based on category names
    $assigned = 0;
    foreach ($categories as $category) {
        $catName = strtolower($category['name']);
        $stationId = 1; // Default
        
        if (strpos($catName, 'burger') !== false || strpos($catName, 'main') !== false) $stationId = 1;
        elseif (strpos($catName, 'side') !== false || strpos($catName, 'fries') !== false) $stationId = 2;
        elseif (strpos($catName, 'drink') !== false || strpos($catName, 'beverage') !== false) $stationId = 3;
        elseif (strpos($catName, 'pizza') !== false || strpos($catName, 'pasta') !== false) $stationId = 4;
        elseif (strpos($catName, 'dessert') !== false || strpos($catName, 'sweet') !== false) $stationId = 5;
        
        $sql = "UPDATE products SET station_id = ? WHERE category_id = ? AND station_id IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$stationId, $category['id']]);
        $assigned += $stmt->rowCount();
    }
    
    echo "✅ Assigned $assigned products to stations based on categories<br>";
    
    // If still no assignments, assign randomly
    $remaining = $pdo->query("SELECT COUNT(*) as cnt FROM products WHERE station_id IS NULL")->fetch()['cnt'];
    if ($remaining > 0) {
        $sql = "UPDATE products SET station_id = FLOOR(1 + RAND() * 5) WHERE station_id IS NULL LIMIT 10";
        $randomAssigned = $pdo->exec($sql);
        echo "✅ Randomly assigned $randomAssigned more products to stations<br>";
    }
    
    echo "<h4>Step 3: Creating test kitchen orders...</h4>";
    
    // Clear old test data
    $pdo->exec("DELETE FROM orders WHERE order_number LIKE 'KIT-%'");
    
    // Get some products with stations
    $products = $pdo->query("
        SELECT p.id, p.name, p.station_id, ks.name as station_name 
        FROM products p 
        JOIN kitchen_stations ks ON p.station_id = ks.id 
        WHERE p.station_id IS NOT NULL 
        LIMIT 5
    ")->fetchAll();
    
    if (empty($products)) {
        echo "❌ No products with stations. Creating test products...<br>";
        
        $pdo->exec("
            INSERT INTO products (name, price, category_id, station_id) VALUES
            ('Classic Burger', 180.00, 1, 1),
            ('French Fries', 90.00, 2, 2),
            ('Soft Drink', 60.00, 3, 3),
            ('Pepperoni Pizza', 220.00, 4, 4),
            ('Chocolate Cake', 120.00, 5, 5)
        ");
        
        $products = $pdo->query("SELECT id, station_id FROM products WHERE station_id IS NOT NULL LIMIT 5")->fetchAll();
    }
    
    // Create 2 test orders
    $orders = [
        ['KIT-001', 'walkin', 'Table 3', 560.00],
        ['KIT-002', 'online', 'Sarah', 430.00]
    ];
    
    $orderIds = [];
    foreach ($orders as $order) {
        $subtotal = round($order[3] / 1.12, 2);
        $tax = round($order[3] - $subtotal, 2);
        
        $sql = "INSERT INTO orders (order_number, order_type, customer_nickname, subtotal, tax_amount, total_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'preparing')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order[0], $order[1], $order[2], $subtotal, $tax, $order[3]]);
        $orderId = $pdo->lastInsertId();
        $orderIds[] = $orderId;
        
        // Add display entry
        $sql2 = "INSERT INTO order_display_status (order_id, display_name, order_number, status, estimated_time) 
                 VALUES (?, ?, ?, 'preparing', ?)";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$orderId, $order[2], $order[0], rand(10, 25)]);
    }
    
    echo "✅ Created " . count($orderIds) . " test orders<br>";
    
    echo "<h4>Step 4: Adding kitchen items...</h4>";
    
    $itemsAdded = 0;
    foreach ($orderIds as $orderId) {
        // Add 2-3 random products to each order
        $randomProducts = array_rand($products, min(3, count($products)));
        if (!is_array($randomProducts)) {
            $randomProducts = [$randomProducts];
        }
        
        foreach ($randomProducts as $index) {
            $product = $products[$index];
            $quantity = rand(1, 3);
            $status = rand(0, 1) ? 'pending' : 'preparing';
            
            $sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, status) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // Get product price
            $priceSql = "SELECT price FROM products WHERE id = ?";
            $priceStmt = $pdo->prepare($priceSql);
            $priceStmt->execute([$product['id']]);
            $price = $priceStmt->fetch()['price'] ?? 100.00;
            
            $stmt->execute([$orderId, $product['id'], $quantity, $price, $price * $quantity, $status]);
            $itemsAdded++;
        }
    }
    
    echo "✅ Added $itemsAdded kitchen items<br>";
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ Kitchen Display Fixed!</h3>";
    
    // Show summary
    $summary = $pdo->query("
        SELECT 
            COUNT(DISTINCT ks.id) as stations,
            COUNT(DISTINCT p.id) as products_with_stations,
            COUNT(DISTINCT oi.id) as kitchen_items,
            SUM(CASE WHEN oi.status = 'pending' THEN 1 ELSE 0 END) as pending_items,
            SUM(CASE WHEN oi.status = 'preparing' THEN 1 ELSE 0 END) as preparing_items
        FROM kitchen_stations ks
        LEFT JOIN products p ON ks.id = p.station_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status NOT IN ('cancelled', 'completed')
    ")->fetch();
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px;'>";
    echo "<h5>Summary:</h5>";
    echo "<ul>";
    echo "<li>Kitchen Stations: " . $summary['stations'] . "</li>";
    echo "<li>Products with Stations: " . $summary['products_with_stations'] . "</li>";
    echo "<li>Total Kitchen Items: " . $summary['kitchen_items'] . "</li>";
    echo "<li>Pending Items: " . $summary['pending_items'] . "</li>";
    echo "<li>Preparing Items: " . $summary['preparing_items'] . "</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='mt-3'>";
    echo "<a href='display/kitchen.php' target='_blank' class='btn btn-success btn-lg'>
            <i class='fas fa-utensils'></i> View Kitchen Display Now
          </a>";
    echo "<a href='display/kitchen-api/get-kitchen-orders.php' target='_blank' class='btn btn-info btn-lg ms-2'>
            <i class='fas fa-code'></i> Check API
          </a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<pre>Debug: " . print_r($pdo->errorInfo(), true) . "</pre>";
}

echo "</div>";