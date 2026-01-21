<?php
// session_start();
require_once '../includes/db.php';

$orderNumber = $_GET['order'] ?? '';
$trackingPin = $_GET['pin'] ?? '';

// If no order number in URL, check localStorage via JavaScript or session
if (empty($orderNumber) && isset($_SESSION['last_order'])) {
    $orderNumber = $_SESSION['last_order']['order_number'];
    $trackingPin = $_SESSION['last_order']['tracking_pin'];
}

// Get order status
// Get order status
$order = null;
if (!empty($orderNumber) && !empty($trackingPin)) {
    $sql = "
        SELECT o.*, ods.status as display_status, ods.estimated_time 
        FROM orders o
        LEFT JOIN order_display_status ods ON o.id = ods.order_id
        WHERE o.order_number = ? 
        AND EXISTS (
            SELECT 1 FROM online_orders oo 
            WHERE oo.order_number = o.order_number 
            AND oo.tracking_pin = ?
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderNumber, $trackingPin]);
    $order = $stmt->fetch();
}

if (!$order) {
    // Show order lookup form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Track Order</title>
        <!-- Similar styling to confirmation page -->
    </head>
    <body>
        <div class="track-form">
            <h2>Track Your Order</h2>
            <form method="GET" action="track.php">
                <input type="text" name="order" placeholder="Order Number" required>
                <input type="text" name="pin" placeholder="Tracking PIN" required>
                <button type="submit">Track</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get order items
$itemsSql = "
    SELECT oi.*, p.name as product_name 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
";
$stmt = $pdo->prepare($itemsSql);
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Track Order #<?php echo $orderNumber; ?></title>
    <!-- Add styling similar to confirmation page -->
</head>
<body>
    <div class="tracking-page">
        <h1>Order #<?php echo $orderNumber; ?></h1>
        
        <!-- Status tracker -->
        <div class="status-tracker">
            <!-- Show progress based on order status -->
        </div>
        
        <!-- Order details -->
        <div class="order-details">
            <!-- Display items and status -->
        </div>
        
        <!-- Auto-refresh -->
        <script>
        setInterval(() => {
            location.reload();
        }, 30000); // Refresh every 30 seconds
        </script>
    </div>
</body>
</html>