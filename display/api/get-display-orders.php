<?php
// api/get-display-orders.php
require_once '../../includes/db.php';
require_once '../../includes/display-functions.php';

header('Content-Type: application/json');

try {
    // Get display settings
    $settings = getDisplaySettings();
    $maxItems = $settings['max_display_items'] ?? 10;
    
    // Get processing orders
    $orders = getProcessingOrders($maxItems);
    
    // Enhance orders with items and addons
    $enhancedOrders = [];
    
    foreach ($orders as $order) {
        // Get items for this order
        $items = getOrderItemsForDisplay($order['order_id']);
        
        // Enhance each item with addons
        $enhancedItems = [];
        foreach ($items as $item) {
            // Get addons for this item
            $addons = getItemAddons($item['id']);
            $item['addons'] = $addons;
            $enhancedItems[] = $item;
        }
        
        $order['items'] = $enhancedItems;
        $enhancedOrders[] = $order;
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $enhancedOrders,
        'count' => count($enhancedOrders)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'orders' => []
    ]);
}
?>