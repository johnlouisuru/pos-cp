<?php
// display/kitchen-api/update-item-status.php - FIXED
require_once '../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$itemId = $_POST['item_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$itemId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Validate status
$validStatuses = ['pending', 'preparing', 'ready', 'cancelled'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Check if status column exists
    $checkSql = "SHOW COLUMNS FROM order_items LIKE 'status'";
    $hasStatusColumn = $pdo->query($checkSql)->fetch();
    $statusColumn = $hasStatusColumn ? 'status' : 'item_status';
    
    // Update item status - FIXED
    $sql = "UPDATE order_items SET {$statusColumn} = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $itemId]);
    
    // Check if all items in the order are ready
    if ($status === 'ready') {
        $orderSql = "
            SELECT oi.order_id 
            FROM order_items oi 
            WHERE oi.id = ?
        ";
        $orderStmt = $pdo->prepare($orderSql);
        $orderStmt->execute([$itemId]);
        $order = $orderStmt->fetch();
        
        if ($order) {
            // Check if all items in this order are ready
            $checkSql = "
                SELECT COUNT(*) as pending_count 
                FROM order_items 
                WHERE order_id = ? AND {$statusColumn} IN ('pending', 'preparing')
            ";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$order['order_id']]);
            $result = $checkStmt->fetch();
            
            // If no pending/preparing items, update order status
            if ($result['pending_count'] == 0) {
                $updateOrderSql = "UPDATE orders SET status = 'ready' WHERE id = ?";
                $updateStmt = $pdo->prepare($updateOrderSql);
                $updateStmt->execute([$order['order_id']]);
                
                // Also update display status
                $updateDisplaySql = "UPDATE order_display_status SET status = 'ready' WHERE order_id = ?";
                $updateDisplayStmt = $pdo->prepare($updateDisplaySql);
                $updateDisplayStmt->execute([$order['order_id']]);
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Status updated']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>