<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'No order ID provided']);
    exit();
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name as cashier_name
        FROM orders o
        LEFT JOIN users u ON o.created_by = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }
    
    // Get order items with product names
    $stmt = $pdo->prepare("
        SELECT 
            oi.*,
            p.name as product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get addons for each item
    foreach ($items as &$item) {
        $stmt = $pdo->prepare("
            SELECT oia.*, a.name
            FROM order_item_addons oia
            LEFT JOIN addons a ON oia.addon_id = a.id
            WHERE oia.order_item_id = ?
        ");
        $stmt->execute([$item['id']]);
        $item['addons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get payment information
    $stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE order_id = ? 
        ORDER BY payment_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get status timeline
    $stmt = $pdo->prepare("
        SELECT scl.*, u.full_name as changed_by
        FROM status_change_logs scl
        LEFT JOIN users u ON scl.user_id = u.id
        WHERE scl.order_id = ?
        ORDER BY scl.created_at DESC
    ");
    $stmt->execute([$order_id]);
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'payment' => $payment,
        'timeline' => $timeline
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}