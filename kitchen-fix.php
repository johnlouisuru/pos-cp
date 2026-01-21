<?php
// kitchen-fix.php
require_once 'includes/db.php';

echo "<h3>Running Kitchen Display Fixes...</h3>";

try {
    // 1. Add status column if needed
    $check = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'status'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE order_items ADD COLUMN status ENUM('pending', 'preparing', 'ready', 'cancelled') DEFAULT 'pending'");
        echo "✅ Added status column to order_items<br>";
        
        // Copy data from item_status if exists
        $check2 = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'item_status'")->fetch();
        if ($check2) {
            $pdo->exec("UPDATE order_items SET status = item_status WHERE item_status IS NOT NULL");
            echo "✅ Copied data from item_status to status<br>";
        }
    } else {
        echo "✅ Status column already exists<br>";
    }
    
    // 2. Add station_id to products
    $check3 = $pdo->query("SHOW COLUMNS FROM products LIKE 'station_id'")->fetch();
    if (!$check3) {
        $pdo->exec("ALTER TABLE products ADD COLUMN station_id INT NULL");
        echo "✅ Added station_id to products<br>";
    } else {
        echo "✅ station_id column already exists<br>";
    }
    
    // 3. Create kitchen_stations table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kitchen_stations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            description VARCHAR(100),
            color_code VARCHAR(7) DEFAULT '#3498db',
            display_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    echo "✅ Created/verified kitchen_stations table<br>";
    
    // 4. Add sample stations
    $stations = [
        ['Burger Station', 'All burger preparations', '#e74c3c', 1],
        ['Fry Station', 'Fries and sides', '#f39c12', 2],
        ['Drink Station', 'Beverages and drinks', '#3498db', 3]
    ];
    
    foreach ($stations as $station) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO kitchen_stations (name, description, color_code, display_order) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute($station);
    }
    echo "✅ Added sample kitchen stations<br>";
    
    // 5. Assign some products to stations
    $update1 = $pdo->exec("UPDATE products SET station_id = 1 WHERE name LIKE '%burger%' AND station_id IS NULL LIMIT 3");
    $update2 = $pdo->exec("UPDATE products SET station_id = 2 WHERE name LIKE '%fries%' AND station_id IS NULL LIMIT 3");
    $update3 = $pdo->exec("UPDATE products SET station_id = 3 WHERE name LIKE '%drink%' OR name LIKE '%coke%' OR name LIKE '%juice%' AND station_id IS NULL LIMIT 3");
    
    echo "✅ Assigned products to stations:<br>";
    echo "&nbsp;&nbsp;- Burger Station: $update1 products<br>";
    echo "&nbsp;&nbsp;- Fry Station: $update2 products<br>";
    echo "&nbsp;&nbsp;- Drink Station: $update3 products<br>";
    
    // 6. Create test data if no kitchen items
    $testCheck = $pdo->query("
        SELECT COUNT(*) as count FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE p.station_id IS NOT NULL
    ")->fetch();
    
    if ($testCheck['count'] == 0) {
        echo "⚠️ No kitchen items found. Creating test data...<br>";
        
        // Get a product with station
        $product = $pdo->query("SELECT id FROM products WHERE station_id IS NOT NULL LIMIT 1")->fetch();
        
        if ($product) {
            // Create a test order
            $pdo->exec("
                INSERT INTO orders (order_number, order_type, subtotal, tax_amount, total_amount, status) 
                VALUES ('KITCHEN-TEST-1', 'walkin', 100, 12, 112, 'pending')
            ");
            $orderId = $pdo->lastInsertId();
            
            // Add item
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, status) 
                VALUES (?, ?, 2, 50, 100, 'pending')
            ");
            $stmt->execute([$orderId, $product['id']]);
            
            echo "✅ Created test kitchen order #KITCHEN-TEST-1<br>";
        }
    }
    
    echo "<h4 style='color: green;'>✅ Kitchen display fix completed!</h4>";
    echo "<p>Now try: <a href='display/kitchen.php' target='_blank'>Kitchen Display</a></p>";
    
} catch (Exception $e) {
    echo "<h4 style='color: red;'>❌ Error: " . $e->getMessage() . "</h4>";
}
?>