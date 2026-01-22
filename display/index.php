<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// display/index.php (UPDATED)
require_once '../includes/db.php';
require_once '../includes/display-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../admin/login.php');
    exit();
}

// display/index.php - Update the settings line
$settings = getDisplaySettings();
$maxItems = $settings['max_display_items'] ?? 10; // Default to 10 if not set
$orders = getProcessingOrders($maxItems);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['display_title'] ?? 'Processing Orders'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/display.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --waiting-color: #95a5a6;
        }
        /* Temporary debug - remove after fixing */
        
    </style>
</head>
<body class="display-body">
    <!-- Header -->
    <header class="display-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-title">
                        <i class="fas fa-bell"></i>
                        <?php echo htmlspecialchars($settings['display_title'] ?? '🛎️ Processing Orders'); ?>
                    </h1>
                    <div class="display-subtitle">
                        <span id="current-time"></span>
                        • 
                        <span id="order-count"><?php echo count($orders); ?> active orders</span>
                        •
                        <span id="last-updated">Updated: Just now</span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="display-controls">
                        <button class="btn btn-sm btn-outline-light" onclick="refreshDisplay()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-outline-light" onclick="toggleFullscreen()">
                            <i class="fas fa-expand"></i> Fullscreen
                        </button>
                        <div class="form-check form-switch d-inline-block ms-2">
                            <input class="form-check-input" type="checkbox" id="soundToggle" checked>
                            <label class="form-check-label text-light" for="soundToggle">Sound</label>
                        </div>
                        <a class="btn btn-sm btn-outline-light" href="../admin/logout.php">
                                <i class="fas fa-door-open"></i> Logout
                            </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mt-4">
        <?php if (empty($orders)): ?>
            <div class="no-orders text-center py-5">
                <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                <h3 class="text-muted">No orders in progress</h3>
                <p class="text-muted">Waiting for new orders...</p>
            </div>
        <?php else: ?>
            <div class="row" id="orders-container">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $items = getOrderItemsForDisplay($order['order_id']);
                    $statusClass = 'status-' . $order['status'];
                    $statusIcons = [
                        'waiting' => 'fas fa-clock',
                        'preparing' => 'fas fa-utensils',
                        'ready' => 'fas fa-check-circle',
                        'completed' => 'fas fa-box'
                    ];
                    ?>
                    
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="order-card <?php echo $statusClass; ?>">
                            <div class="order-header">
                                <div class="order-meta">
                                    <span class="order-number badge bg-dark">
                                        <?php echo htmlspecialchars($order['order_number']); ?>
                                    </span>
                                    <span class="order-type badge bg-secondary">
                                        <?php echo strtoupper($order['order_type']); ?>
                                    </span>
                                </div>
                                <h5 class="customer-name">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($order['display_name']); ?> 
                                </h5>
                                <div class="order-timing">
                                    <span class="order-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('h:i A', strtotime($order['order_created'])); ?>
                                    </span>
                                    •
                                    <span class="estimated-time">
                                        <i class="fas fa-hourglass-half"></i>
                                        Est: <?php echo $order['estimated_time']; ?> min
                                    </span>
                                </div>
                            </div>
                            
                            <div class="order-body">
                                <!-- // In your display/index.php, add temporary debug: -->
<div class="order-items">
    <h6 class="items-title mb-2">
        <i class="fas fa-list me-2"></i>Order Items
    </h6>
    
    <ul class="item-list list-unstyled">
        <?php if (empty($items)): ?>
            <li class="no-items text-muted py-2 px-3 rounded bg-light">
                <i class="fas fa-info-circle me-2"></i>No items found
            </li>
        <?php else: ?>
            <?php foreach ($items as $index => $item): ?>
                <li class="item-entry <?php echo $index === 0 ? 'first-item' : ''; ?> <?php echo $index === count($items) - 1 ? 'last-item' : ''; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="item-info">
                            <span class="item-quantity badge bg-secondary me-2">
                                <?php echo $item['quantity']; ?>x
                            </span>
                            <span class="item-name fw-semibold">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($item['category'])): ?>
                            <span class="item-category badge bg-light text-dark border">
                                <?php echo htmlspecialchars($item['category']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($item['special_request'])): ?>
                        <div class="special-request alert alert-warning alert-sm py-1 px-2 mb-0 mt-1">
                            <i class="fas fa-sticky-note me-1"></i>
                            <small class="request-text">
                                <?php echo htmlspecialchars($item['special_request']); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>
                                
                                <div class="order-footer">
                                    <div class="status-indicator">
                                        <i class="<?php echo $statusIcons[$order['status']]; ?>"></i>
                                        <span class="status-text"><?php echo ucfirst($order['status']); ?></span>
                                    </div>
                                    <div class="order-amount">
                                        <i class="fas fa-receipt"></i>
                                        ₱<?php echo number_format($order['total_amount'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            
                                <!-- Enhanced order-actions section -->
                                <div class="order-actions">
                                    <?php 
                                    $currentStatus = $order['status'];
                                    $orderNumber = htmlspecialchars($order['order_number']);
                                    $orderId = $order['order_id'];
                                    ?>
                                    
                                    <!-- Status progression buttons -->
                                    <?php if ($currentStatus == 'waiting'): ?>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="updateOrderStatus(<?php echo $orderId; ?>, 'confirmed')"
                                                title="Confirm order and start preparation">
                                            <i class="fas fa-check-circle"></i> Confirm
                                        </button>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="updateOrderStatus(<?php echo $orderId; ?>, 'preparing')"
                                                title="Start preparing order">
                                            <i class="fas fa-play"></i> Start Prep
                                        </button>
                                        
                                    <?php elseif ($currentStatus == 'preparing'): ?>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="updateOrderStatus(<?php echo $orderId; ?>, 'ready')"
                                                title="Mark order as ready for pickup">
                                            <i class="fas fa-check"></i> Mark Ready
                                        </button>
                                        
                                    <?php elseif ($currentStatus == 'ready'): ?>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="updateOrderStatus(<?php echo $orderId; ?>, 'completed')"
                                                title="Mark order as served/completed">
                                            <i class="fas fa-box"></i> Mark Served
                                        </button>
                                        <button class="btn btn-sm btn-info" 
                                                onclick="notifyCustomer('<?php echo $orderNumber; ?>')"
                                                title="Notify customer their order is ready">
                                            <i class="fas fa-bell"></i> Notify
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Cancel button (always visible except for completed/cancelled) -->
                                    <?php if (!in_array($currentStatus, ['completed', 'cancelled'])): ?>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="cancelOrder(<?php echo $orderId; ?>, '<?php echo $orderNumber; ?>')"
                                                title="Cancel this order">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- View details button -->
                                    <!-- <button class="btn btn-sm btn-outline-secondary" 
                                            onclick="viewOrderDetails(<?php echo $orderId; ?>)"
                                            title="View order details">
                                        <i class="fas fa-eye"></i> Details
                                    </button> -->
                                </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="display-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="next-number">
                        <i class="fas fa-forward"></i>
                        Next Order: 
                        <span id="next-order-number">Loading...</span>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="refresh-info">
                        <i class="fas fa-sync"></i>
                        Auto-refreshing every <span id="refresh-interval"><?php echo $settings['refresh_interval'] ?? 30; ?></span> seconds
                        <span id="refresh-countdown"><?php echo $settings['refresh_interval'] ?? 30; ?></span>s
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Sound element -->
    <audio id="notification-sound" preload="auto">
        <source src="sounds/kitchen-notification.mp3" type="audio/mpeg">
    </audio>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/display.js"></script>
    
    <script>
        // Initialize with settings from PHP
        const refreshInterval = <?php echo $settings['refresh_interval'] ?? 30; ?>;
        
        $(document).ready(function() {
            updateTime();
            setInterval(updateTime, 1000);
            startAutoRefresh(refreshInterval);
            getNextOrderNumber();
        });

        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = 
                now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit' });
        }
        
        function startAutoRefresh(intervalSeconds) {
            // This will be defined in display.js
            console.log('Auto-refresh interval:', intervalSeconds);
        }
    </script>
</body>
</html>