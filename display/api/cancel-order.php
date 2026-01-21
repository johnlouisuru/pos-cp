<?php
// display/api/cancel-order.php
require_once '../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$orderNumber = $_POST['order_number'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Update main order status to 'cancelled'
    $sql = "UPDATE orders SET status = 'cancelled', completed_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);
    
    // 2. Update display status to 'completed' (so it gets removed)
    $sql2 = "UPDATE order_display_status SET status = 'completed', display_until = NOW() WHERE order_id = ?";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$orderId]);
    
    // 3. Update all order items status to 'cancelled'
    $sql3 = "UPDATE order_items SET status = 'cancelled' WHERE order_id = ?";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute([$orderId]);
    
    // 4. Restore stock if needed (optional - you might want to keep stock reduced)
    // If you want to restore stock when cancelled:
    /*
    $sql4 = "UPDATE products p 
             JOIN order_items oi ON p.id = oi.product_id
             SET p.stock = p.stock + oi.quantity 
             WHERE oi.order_id = ? AND oi.status = 'cancelled'";
    $stmt4 = $pdo->prepare($sql4);
    $stmt4->execute([$orderId]);
    */
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Order #{$orderNumber} cancelled successfully"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>