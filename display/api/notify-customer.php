<?php
require_once '../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orderNumber = $_POST['order_number'] ?? null;

if (!$orderNumber) {
    echo json_encode(['success' => false, 'message' => 'Missing order number']);
    exit;
}

try {
    // Get order details
    $sql = "SELECT * FROM orders WHERE order_number = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // For now, just log the notification
    // In a real system, you would send SMS/email/notification here
    error_log("Customer notification sent for order: $orderNumber");
    
    // Update notification count
    $updateSql = "UPDATE orders SET notification_count = notification_count + 1 WHERE order_number = ?";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([$orderNumber]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Customer notified successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>