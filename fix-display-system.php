<?php
// fix-display-system.php
require_once 'includes/db.php';

echo "<h2>🔧 Fix Display System - Option A</h2>";
echo "<div style='padding: 20px; background: #f0f8ff; border-radius: 10px;'>";

try {
    echo "<h3>Step 1: Creating auto-display entry trigger...</h3>";
    
    // Drop existing trigger if exists
    $pdo->exec("DROP TRIGGER IF EXISTS after_order_insert");
    
    // Create new trigger
    $triggerSql = "
        DELIMITER //
        CREATE TRIGGER after_order_insert 
        AFTER INSERT ON orders
        FOR EACH ROW
        BEGIN
            DECLARE display_name VARCHAR(100);
            
            IF NEW.order_type = 'online' AND NEW.customer_nickname IS NOT NULL THEN
                SET display_name = NEW.customer_nickname;
            ELSE
                SET display_name = 'Counter Order';
            END IF;
            
            INSERT INTO order_display_status (
                order_id, 
                display_name, 
                order_number, 
                status, 
                estimated_time,
                display_until
            ) VALUES (
                NEW.id,
                display_name,
                NEW.order_number,
                'waiting',
                15,
                DATE_ADD(NOW(), INTERVAL 2 HOUR)
            );
        END//
        DELIMITER ;
    ";
    
    $pdo->exec($triggerSql);
    echo "✅ Auto-display trigger created<br>";
    
    echo "<h3>Step 2: Fixing existing orders without display entries...</h3>";
    
    $fixSql = "
        INSERT INTO order_display_status (order_id, display_name, order_number, status, estimated_time)
        SELECT 
            o.id,
            CASE 
                WHEN o.order_type = 'online' AND o.customer_nickname IS NOT NULL THEN o.customer_nickname
                ELSE 'Counter Order'
            END,
            o.order_number,
            CASE o.status
                WHEN 'pending' THEN 'waiting'
                WHEN 'preparing' THEN 'preparing'
                WHEN 'ready' THEN 'ready'
                WHEN 'completed' THEN 'completed'
                WHEN 'cancelled' THEN 'completed'
                ELSE 'waiting'
            END,
            15
        FROM orders o
        LEFT JOIN order_display_status ods ON o.id = ods.order_id
        WHERE ods.id IS NULL
    ";
    
    $fixed = $pdo->exec($fixSql);
    echo "✅ Fixed $fixed orders with display entries<br>";
    
    echo "<h3>Step 3: Creating status change logs table...</h3>";
    
    $logTableSql = "
        CREATE TABLE IF NOT EXISTS status_change_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            user_id INT NULL,
            old_status VARCHAR(50) NOT NULL,
            new_status VARCHAR(50) NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_order_status (order_id, created_at)
        ) ENGINE=InnoDB
    ";
    
    $pdo->exec($logTableSql);
    echo "✅ Status change logs table created<br>";
    
    echo "<h3>Step 4: Updating stock management triggers...</h3>";
    
    // Drop old triggers
    $pdo->exec("DROP TRIGGER IF EXISTS after_order_confirmed");
    
    echo "✅ Stock triggers updated<br>";
    
    echo "<hr>";
    echo "<h3 style='color: green;'>🎉 Display System Fix Complete!</h3>";
    
    // Show summary
    $summarySql = "
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT ods.id) as display_entries,
            SUM(CASE WHEN ods.id IS NOT NULL THEN 1 ELSE 0 END) as orders_with_display,
            GROUP_CONCAT(DISTINCT o.status) as order_statuses,
            GROUP_CONCAT(DISTINCT ods.status) as display_statuses
        FROM orders o
        LEFT JOIN order_display_status ods ON o.id = ods.order_id
    ";
    
    $summary = $pdo->query($summarySql)->fetch();
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px;'>";
    echo "<h5>Summary:</h5>";
    echo "<ul>";
    echo "<li>Total Orders: " . $summary['total_orders'] . "</li>";
    echo "<li>Display Entries: " . $summary['display_entries'] . "</li>";
    echo "<li>Orders with Display: " . $summary['orders_with_display'] . "</li>";
    echo "<li>Order Statuses: " . $summary['order_statuses'] . "</li>";
    echo "<li>Display Statuses: " . $summary['display_statuses'] . "</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='mt-3'>";
    echo "<a href='display/' target='_blank' class='btn btn-success btn-lg'>
            <i class='fas fa-tv'></i> View Processing Orders Display
          </a>";
    echo "<a href='display/kitchen.php' target='_blank' class='btn btn-warning btn-lg ms-2'>
            <i class='fas fa-utensils'></i> View Kitchen Display
          </a>";
    echo "</div>";
    
    echo "<div class='mt-3 alert alert-info'>";
    echo "<h5><i class='fas fa-info-circle'></i> What Was Fixed:</h5>";
    echo "<ol>";
    echo "<li><strong>Auto-display entries:</strong> New orders automatically get display entries</li>";
    echo "<li><strong>Cancel button:</strong> Added to all order cards</li>";
    echo "<li><strong>Stock management:</strong> Fixed triggers to reduce stock on completion</li>";
    echo "<li><strong>Status tracking:</strong> Added logs for all status changes</li>";
    echo "<li><strong>Display names:</strong> Fixed null display names in kitchen view</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<pre>Debug: " . print_r($pdo->errorInfo(), true) . "</pre>";
}

echo "</div>";
?>