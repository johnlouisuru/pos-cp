<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['items']) || empty($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order data']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Generate order number
    $order_number = generateOrderNumber($data['order_type']);
    
    // Calculate totals - FIXED: Include addon prices in subtotal calculation
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        $item_price = $item['price'];
        $item_addon_total = isset($item['addon_total']) ? $item['addon_total'] : 0;
        $subtotal += ($item_price + $item_addon_total) * $item['quantity'];
    }
    
    $tax_rate = getTaxRate();
    $tax_amount = $subtotal * $tax_rate;
    $total_amount = $subtotal + $tax_amount;
    
    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, order_type, customer_nickname, customer_phone,
            subtotal, tax_amount, total_amount, status, created_by, order_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, CURDATE())
    ");
    
    $stmt->execute([
        $order_number,
        $data['order_type'],
        $data['customer_nickname'] ?? null,
        $data['customer_phone'] ?? null,
        $subtotal,
        $tax_amount,
        $total_amount,
        $data['created_by']
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    // Insert order items - SINGLE CORRECTED VERSION
    foreach ($data['items'] as $item) {
        // Calculate item total including addons
        $item_price = $item['price'];
        $item_addon_total = isset($item['addon_total']) ? $item['addon_total'] : 0;
        $total_price = ($item_price + $item_addon_total) * $item['quantity'];
        
        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, quantity, unit_price, total_price, 
                special_request, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item_price,  // Base price only
            $total_price, // Total price including addons
            $item['special_request'] ?? null
        ]);
        
        $order_item_id = $pdo->lastInsertId();
        
        // Insert addons if any
        if (isset($item['addons']) && is_array($item['addons']) && count($item['addons']) > 0) {
            foreach ($item['addons'] as $addon) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_item_addons (
                        order_item_id, addon_id, quantity, price_at_time
                    ) VALUES (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $order_item_id,
                    $addon['addon_id'],
                    $addon['quantity'],
                    $addon['price']
                ]);
            }
        }
        
        // Update product stock if needed NOTE: UNCOMMENT IF STOCK MANAGEMENT IS REQUIRED
        // if (isset($item['stock']) && $item['stock'] !== null) {
        //     $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")
        //         ->execute([$item['quantity'], $item['product_id']]);
        // }
    }
    
    // Insert payment record
    $stmt = $pdo->prepare("
        INSERT INTO payments (
            order_id, payment_method, amount, reference_number, status
        ) VALUES (?, ?, ?, ?, 'completed')
    ");
    
    $stmt->execute([
        $order_id,
        $data['payment_method'],
        $total_amount,
        $data['payment_reference'] ?? null
    ]);
    
    // Create display status for kitchen
    $display_name = $data['customer_nickname'] ?: 'Counter Order';
    $stmt = $pdo->prepare("
        INSERT INTO order_display_status (
            order_id, display_name, order_number, status, estimated_time
        ) VALUES (?, ?, ?, 'waiting', 15)
    ");
    
    $stmt->execute([
        $order_id,
        $display_name,
        $order_number
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'subtotal' => $subtotal,
        'tax_amount' => $tax_amount,
        'total_amount' => $total_amount
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function generateOrderNumber($order_type) {
    global $pdo;
    
    $prefix = $order_type === 'walkin' ? 'WALK-IN-' : 'ONLINE-';
    
    // Get current year and month
    $date_part = date('ym');
    
    // Get next sequence number
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(order_number, LENGTH(?) + 1) AS UNSIGNED)) as last_num 
        FROM orders 
        WHERE order_number LIKE CONCAT(?, '%')
    ");
    
    $search_prefix = $prefix . $date_part;
    $stmt->execute([$search_prefix, $search_prefix]);
    $result = $stmt->fetch();
    
    $next_num = ($result['last_num'] ?: 0) + 1;
    
    return $search_prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

function getTaxRate() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'");
    $result = $stmt->fetch();
    
    return floatval($result['setting_value'] ?? 0);
}
?>