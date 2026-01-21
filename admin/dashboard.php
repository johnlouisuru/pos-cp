<?php

require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get statistics
$stats = [];

// Today's sales
$stmt = $pdo->prepare("
    SELECT COUNT(*) as orders, SUM(total_amount) as revenue 
    FROM orders 
    WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'
");
$stmt->execute();
$stats['today'] = $stmt->fetch();

// Monthly sales
$stmt = $pdo->prepare("
    SELECT COUNT(*) as orders, SUM(total_amount) as revenue 
    FROM orders 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE()) 
    AND status != 'cancelled'
");
$stmt->execute();
$stats['month'] = $stmt->fetch();

// Pending orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('pending', 'preparing')");
$stats['pending'] = $stmt->fetch();

// Low stock items
$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock <= min_stock AND stock <= 0");
$stats['low_stock'] = $stmt->fetch();

// Recent orders
$stmt = $pdo->query("
    SELECT o.*, u.username as cashier 
    FROM orders o 
    LEFT JOIN users u ON o.created_by = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$recent_orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .bg-sales { background: #3498db; }
        .bg-revenue { background: #2ecc71; }
        .bg-pending { background: #f39c12; }
        .bg-stock { background: #e74c3c; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i> Admin Dashboard
            </a>
            <div class="d-flex">
                <a href="pos.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-cash-register"></i> POS
                </a>
                <a href="logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-sales me-1">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo $stats['today']['orders'] ?? 0; ?></h5>
                            <p class="text-muted mb-0">Today's Orders</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-revenue me-3">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">₱<?php echo number_format($stats['today']['revenue'] ?? 0, 2); ?></h5>
                            <p class="text-muted mb-0">Today's Revenue</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-pending me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo $stats['pending']['count'] ?? 0; ?></h5>
                            <p class="text-muted mb-0">Pending Orders</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-stock me-3">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo $stats['low_stock']['count'] ?? 0; ?></h5>
                            <p class="text-muted mb-0">Low Stocks</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i> Recent Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Type</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Cashier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $order['order_type'] === 'walkin' ? 'info' : 'success'; ?>">
                                                <?php echo ucfirst($order['order_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['customer_nickname'] ?: 'Walk-in'); ?></td>
                                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'preparing' => 'info',
                                                'ready' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($order['cashier'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <a href="pos.php" class="btn btn-primary w-100">
                                    <i class="fas fa-plus-circle me-2"></i> New Sale
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="orders.php" class="btn btn-info w-100">
                                    <i class="fas fa-list me-2"></i> View All Orders
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="products.php" class="btn btn-success w-100">
                                    <i class="fas fa-box me-2"></i> Products
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="reports.php" class="btn btn-warning w-100">
                                    <i class="fas fa-chart-bar me-2"></i> Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>