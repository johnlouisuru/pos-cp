<?php
// display/kitchen-api/get-kitchen-orders.php - FIXED VERSION
require_once '../../includes/db.php';

header('Content-Type: application/json');

$stationId = $_GET['station_id'] ?? 'all';

try {
    // Get DISTINCT kitchen stations
    $stationsSql = "
        SELECT DISTINCT ks.* 
        FROM kitchen_stations ks 
        WHERE ks.is_active = 1 
        ORDER BY ks.display_order
    ";
    $stmt = $pdo->prepare($stationsSql);
    $stmt->execute();
    $stations = $stmt->fetchAll();
    
    $resultStations = [];
    $totalItems = 0;
    $urgentItems = 0;
    $newItems = 0;
    
    foreach ($stations as $station) {
        // Skip if filtering by station
        if ($stationId !== 'all' && $station['id'] != $stationId) {
            continue;
        }
        
        // In the itemsSql query, update to ensure we get display info
$itemsSql = "
    SELECT 
        oi.id as item_id,
        oi.order_id,
        oi.quantity,
        oi.status as item_status,
        oi.special_request,
        p.name as product_name,
        p.station_id,
        -- Get display name with fallback
        COALESCE(
            ods.display_name, 
            o.customer_nickname, 
            CASE 
                WHEN o.order_type = 'online' THEN CONCAT('Online #', o.order_number)
                ELSE CONCAT('Walk-in #', o.order_number)
            END
        ) as display_name,
        -- Get order number from display table or orders table
        COALESCE(ods.order_number, o.order_number) as order_number,
        o.created_at as order_created
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    LEFT JOIN order_display_status ods ON o.id = ods.order_id
    WHERE p.station_id = ?
        AND oi.status IN ('pending', 'preparing')
        AND o.status NOT IN ('cancelled', 'completed')
    ORDER BY 
        CASE oi.status 
            WHEN 'pending' THEN 1
            WHEN 'preparing' THEN 2
            ELSE 3
        END,
        o.created_at ASC
";
        
        $stmt = $pdo->prepare($itemsSql);
        $stmt->execute([$station['id']]);
        $items = $stmt->fetchAll();
        
        // Count items
        $itemCount = count($items);
        $totalItems += $itemCount;
        
        // Count urgent items (pending > 10 minutes)
        foreach ($items as $item) {
            if ($item['item_status'] === 'pending') {
                $orderTime = strtotime($item['order_created']);
                if (time() - $orderTime > 600) { // 10 minutes
                    $urgentItems++;
                }
                
                // Check if new (last 2 minutes)
                if (time() - $orderTime < 120) {
                    $newItems++;
                }
            }
        }
        
        $resultStations[] = [
            'id' => $station['id'],
            'name' => $station['name'],
            'color_code' => $station['color_code'],
            'item_count' => $itemCount,
            'items' => $items
        ];
    }
    
    echo json_encode([
        'success' => true,
        'stations' => $resultStations,
        'stats' => [
            'total_items' => $totalItems,
            'urgent_items' => $urgentItems
        ],
        'newItems' => $newItems,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>