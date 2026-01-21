<?php
require_once '../../includes/db.php';
require_once '../../includes/display-functions.php';

header('Content-Type: application/json');

$settings = getDisplaySettings();
$orders = getProcessingOrders($settings['max_display_items'] ?? 10);

// Get items for each order
foreach ($orders as &$order) {
    $items = getOrderItemsForDisplay($order['order_id']);
    $itemDescriptions = [];
    
    foreach ($items as $item) {
        $desc = $item['quantity'] . 'x ' . $item['name'];
        if (!empty($item['special_request'])) {
            $desc .= ' (' . $item['special_request'] . ')';
        }
        $itemDescriptions[] = $desc;
    }
    
    $order['items'] = implode(' | ', $itemDescriptions);
}

// Get previous order count from session
// session_start();
$previousCount = $_SESSION['last_order_count'] ?? 0;
$newOrders = max(0, count($orders) - $previousCount);

$_SESSION['last_order_count'] = count($orders);

echo json_encode([
    'success' => true,
    'orders' => $orders,
    'newOrders' => $newOrders,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>