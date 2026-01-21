<?php
session_start();

// Check if we have order data
if (!isset($_SESSION['last_order'])) {
    header('Location: menu.php');
    exit;
}

$order = $_SESSION['last_order'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Restaurant Name</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .confirmation-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .confirmation-header {
            background: #2ecc71;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .order-details {
            padding: 30px;
        }
        .order-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
        }
        .tracking-pin {
            background: #f8f9fa;
            border: 2px dashed #3498db;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
        }
        .pin-code {
            font-size: 2.5rem;
            font-weight: bold;
            color: #e74c3c;
            letter-spacing: 5px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .btn-action {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .instructions {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .qr-placeholder {
            background: #e0e0e0;
            width: 200px;
            height: 200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="confirmation-card">
        <div class="confirmation-header">
            <i class="fas fa-check-circle fa-4x mb-3"></i>
            <h1>Order Confirmed!</h1>
            <p class="mb-0">Thank you for your order, <?php echo htmlspecialchars($order['customer_name']); ?>!</p>
        </div>
        
        <div class="order-details">
            <div class="order-number">
                <a href="order-status.php?order=<?php echo $order['order_number']; ?>&pin=<?php echo $order['tracking_pin']; ?>"><?php echo $order['order_number']; ?></a>
            </div>
            
            <div class="text-center mb-4">
                <h5><i class="fas fa-clock"></i> Estimated Pickup: 15-20 minutes</h5>
                <p class="text-muted">We'll notify you when your order is ready.</p>
            </div>
            
            <div class="tracking-pin">
                <h6>Your Tracking PIN:</h6>
                <div class="pin-code"><a href="order-status.php?order=<?php echo $order['order_number']; ?>&pin=<?php echo $order['tracking_pin']; ?>"><?php echo $order['tracking_pin']; ?></a></div>
                <small class="text-muted">Click this link to track your order status</small>
            </div>
            
            <div class="instructions">
                <h6><i class="fas fa-info-circle"></i> What's Next?</h6>
                <ul class="mb-0">
                    <li>Proceed to the counter for pickup</li>
                    <li>Show your order number or PIN to our staff</li>
                    <li>Pay when you pick up your order</li>
                    <li>Track your order status using the link below</li>
                </ul>
            </div>
            
            <!-- QR Code Placeholder -->
            <div class="qr-code">
                <h6>Scan to Track:</h6>
                <div class="qr-placeholder">
                    <i class="fas fa-qrcode fa-5x text-muted"></i>
                </div>
                <small class="text-muted">QR code for quick tracking</small>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-primary btn-action" onclick="trackOrder()">
                    <i class="fas fa-map-pin"></i> Track Order
                </button>
                <button class="btn btn-success btn-action" onclick="saveToPhone()">
                    <i class="fas fa-save"></i> Save Details
                </button>
                <a href="menu.php" class="btn btn-outline-secondary btn-action">
                    <i class="fas fa-home"></i> New Order
                </a>
            </div>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-phone"></i> Questions? Call us: (123) 456-7890
                </small>
            </div>
        </div>
    </div>

    <script>
        // Save order details to phone
        function saveToPhone() {
            const orderData = {
                orderNumber: '<?php echo $order['order_number']; ?>',
                trackingPin: '<?php echo $order['tracking_pin']; ?>',
                savedAt: new Date().toISOString()
            };
            
            localStorage.setItem('lastOrder', JSON.stringify(orderData));
            alert('Order details saved! You can track your order anytime.');
        }
        
        // Track order
        function trackOrder() {
            window.location.href = `order-status.php?order=<?php echo $order['order_number']; ?>&pin=<?php echo $order['tracking_pin']; ?>`;
        }
        
        // Auto-save to localStorage
        window.onload = function() {
            saveToPhone();
        };
    </script>
</body>
</html>

<?php
// Clear the order data after displaying (optional)
// unset($_SESSION['last_order']);
?>