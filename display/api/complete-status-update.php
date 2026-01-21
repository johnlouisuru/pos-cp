<?php
// display/api/complete-status-update.php
require_once '../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$newStatus = $_POST['status'] ?? null;
$userId = $_POST['user_id'] ?? null; // For logging

if (!$orderId || !$newStatus) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Validate status
$validStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get current order info
    $orderSql = "SELECT * FROM orders WHERE id = ?";
    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Map order status to display status
    $displayStatusMap = [
        'pending' => 'waiting',
        'confirmed' => 'waiting',
        'preparing' => 'preparing',
        'ready' => 'ready',
        'completed' => 'completed',
        'cancelled' => 'completed'
    ];
    
    $displayStatus = $displayStatusMap[$newStatus] ?? 'waiting';
    
    // 1. Update main order
    $updateOrderSql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
    if ($newStatus === 'confirmed' || $newStatus === 'completed') {
        $updateOrderSql = "UPDATE orders SET status = ?, confirmed_by = ?, updated_at = NOW() WHERE id = ?";
        $orderStmt = $pdo->prepare($updateOrderSql);
        $orderStmt->execute([$newStatus, $userId, $orderId]);
    } else {
        $orderStmt = $pdo->prepare($updateOrderSql);
        $orderStmt->execute([$newStatus, $orderId]);
    }
    
    // 2. Update display status
    $updateDisplaySql = "UPDATE order_display_status SET status = ?, updated_at = NOW() WHERE order_id = ?";
    $displayStmt = $pdo->prepare($updateDisplaySql);
    $displayStmt->execute([$displayStatus, $orderId]);
    
    // 3. If marking as ready, check if all items are ready
    if ($newStatus === 'ready') {
        // Check if all items are ready
        $itemsSql = "SELECT COUNT(*) as count FROM order_items WHERE order_id = ? AND status IN ('pending', 'preparing')";
        $itemsStmt = $pdo->prepare($itemsSql);
        $itemsStmt->execute([$orderId]);
        $itemsResult = $itemsStmt->fetch();
        
        if ($itemsResult['count'] > 0) {
            // Not all items are ready yet, so don't mark order as ready
            $updateOrderSql = "UPDATE orders SET status = 'preparing' WHERE id = ?";
            $updateStmt = $pdo->prepare($updateOrderSql);
            $updateStmt->execute([$orderId]);
        }
    }
    
    // 4. Log the status change
    $logSql = "INSERT INTO status_change_logs (order_id, user_id, old_status, new_status, notes) 
               VALUES (?, ?, ?, ?, ?)";
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([$orderId, $userId, $order['status'], $newStatus, 'Updated via display system']);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Order #{$order['order_number']} updated to {$newStatus}"
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>