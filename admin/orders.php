<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $order_id = $_POST['order_id'] ?? 0;
        $new_status = $_POST['status'] ?? '';
        $user_id = $_SESSION['user_id'];
        
        if ($order_id && $new_status) {
            // Get current status first
            $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $current_status = $stmt->fetchColumn();
            
            if ($current_status) {
                // Update order status
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $order_id]);
                
                // Log status change
                $stmt = $pdo->prepare("
                    INSERT INTO status_change_logs (order_id, user_id, old_status, new_status, notes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$order_id, $user_id, $current_status, $new_status, 'Status updated via admin panel']);
                
                // If marking as completed, update completion time
                if ($new_status === 'completed' && $current_status !== 'completed') {
                    $pdo->prepare("UPDATE orders SET completed_at = NOW() WHERE id = ?")->execute([$order_id]);
                }
                
                // If marking as cancelled from completed, restore stock (trigger handles this)
                if ($new_status === 'cancelled' && $current_status === 'completed') {
                    $pdo->prepare("UPDATE orders SET completed_at = NULL WHERE id = ?")->execute([$order_id]);
                }
            }
        }
    } elseif ($action === 'delete_order') {
        $order_id = $_POST['order_id'] ?? 0;
        
        if ($order_id) {
            // First delete child records
            $pdo->prepare("DELETE FROM order_item_addons WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = ?)")->execute([$order_id]);
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
            $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$order_id]);
            $pdo->prepare("DELETE FROM status_change_logs WHERE order_id = ?")->execute([$order_id]);
            $pdo->prepare("DELETE FROM order_display_status WHERE order_id = ?")->execute([$order_id]);
            
            // Then delete the order
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
        }
    }
    
    header('Location: orders.php');
    exit();
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$order_type = $_GET['order_type'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($status !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status;
}

if ($order_type !== 'all') {
    $where_conditions[] = "o.order_type = ?";
    $params[] = $order_type;
}

if ($date_from) {
    $where_conditions[] = "o.order_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "o.order_date <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(o.order_number LIKE ? OR o.customer_nickname LIKE ? OR o.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Build ORDER BY clause
$order_by = match($sort) {
    'oldest' => "o.created_at ASC",
    'highest' => "o.total_amount DESC",
    'lowest' => "o.total_amount ASC",
    default => "o.created_at DESC"
};

// Get total counts for stats
$total_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(total_amount) as total_revenue,
    SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as completed_revenue
FROM orders";
$stmt = $pdo->query($total_query);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's stats
$today_query = "SELECT 
    COUNT(*) as today_orders,
    SUM(total_amount) as today_revenue
FROM orders 
WHERE DATE(order_date) = CURDATE()";
$stmt = $pdo->query($today_query);
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get orders with pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $where_sql");
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

// Get orders
$query = "
    SELECT 
        o.*,
        u.full_name as cashier_name,
        COUNT(DISTINCT oi.id) as item_count,
        SUM(oi.quantity) as total_items,
        GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names
    FROM orders o
    LEFT JOIN users u ON o.created_by = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    $where_sql
    GROUP BY o.id
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent status changes for display
$recent_status_changes = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    
    $stmt = $pdo->prepare("
        SELECT scl.*, u.full_name as changed_by
        FROM status_change_logs scl
        LEFT JOIN users u ON scl.user_id = u.id
        WHERE scl.order_id IN ($placeholders)
        ORDER BY scl.created_at DESC
    ");
    $stmt->execute($order_ids);
    $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($changes as $change) {
        $recent_status_changes[$change['order_id']][] = $change;
    }
}

// Get payment information
$payment_info = [];
if (!empty($orders)) {
    $stmt = $pdo->prepare("
        SELECT p.order_id, p.payment_method, p.amount, p.status as payment_status, 
               p.reference_number, p.payment_date
        FROM payments p
        WHERE p.order_id IN ($placeholders)
    ");
    $stmt->execute($order_ids);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payments as $payment) {
        $payment_info[$payment['order_id']] = $payment;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
        }
        
        body {
            background: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .stat-card {
            border-radius: 12px;
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .order-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .order-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-preparing { background: #d4edda; color: #155724; }
        .status-ready { background: #cce5ff; color: #004085; }
        .status-completed { background: #28a745; color: white; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .type-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .type-walkin { background: #e3f2fd; color: #1976d2; }
        .type-online { background: #f3e5f5; color: #7b1fa2; }
        
        .payment-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        .filter-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .order-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .order-timeline::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
        }
        
        .timeline-item.active::before {
            background: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .order-details-table {
            font-size: 14px;
        }
        
        .order-details-table th {
            font-weight: 600;
            background: #f8f9fa;
        }
        
        .modal-header {
            background: var(--dark-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .action-btn {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
            
            .order-card {
                margin-bottom: 10px;
            }
            
            .table-responsive {
                font-size: 14px;
            }
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        .pagination .page-link:hover {
            color: var(--dark-color);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-clipboard-list me-2"></i> Order Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-box me-1"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-list me-1"></i> Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="addons.php">
                            <i class="fas fa-plus-circle me-1"></i> Addons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pos.php">
                            <i class="fas fa-cash-register me-1"></i> POS
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-line me-1"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php">
                            <i class="fas fa-clipboard-list me-1"></i> Orders
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Orders</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total_orders']); ?></h3>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>
                                    <?php echo $today_stats['today_orders'] ?? 0; ?> today
                                </small>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Completed Orders</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['completed_orders']); ?></h3>
                                <small class="text-success"><?php echo $stats['completed_orders'] > 0 ? number_format(($stats['completed_orders'] / $stats['total_orders']) * 100, 1) : '0'; ?>% rate</small>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Pending Orders</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['pending_orders']); ?></h3>
                                <small class="text-warning">Needs attention</small>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Revenue</h6>
                                <h3 class="mb-0">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                                <small class="text-success">
                                    <i class="fas fa-money-bill-wave me-1"></i>
                                    ₱<?php echo number_format($today_stats['today_revenue'] ?? 0, 2); ?> today
                                </small>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Box -->
        <div class="filter-box">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="preparing" <?php echo $status === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="ready" <?php echo $status === 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Order Type</label>
                    <select name="order_type" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $order_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="walkin" <?php echo $order_type === 'walkin' ? 'selected' : ''; ?>>Walk-in</option>
                        <option value="online" <?php echo $order_type === 'online' ? 'selected' : ''; ?>>Online</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="highest" <?php echo $sort === 'highest' ? 'selected' : ''; ?>>Highest Amount</option>
                        <option value="lowest" <?php echo $sort === 'lowest' ? 'selected' : ''; ?>>Lowest Amount</option>
                    </select>
                </div>
                
                <div class="col-md-8">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by order number, customer name, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <a href="orders.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-2"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Orders Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Orders List
                    <span class="badge bg-secondary ms-2"><?php echo number_format($total_orders); ?> orders found</span>
                </h5>
                <div>
                    <a href="pos.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus me-1"></i> New Order
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>No orders found</h5>
                    <p class="text-muted">Try adjusting your filters or create a new order</p>
                    <a href="pos.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Create New Order
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                $payment = $payment_info[$order['id']] ?? [];
                                $timeline = $recent_status_changes[$order['id']] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    <?php if ($order['cashier_name']): ?>
                                    <br>
                                    <small class="text-muted">by <?php echo htmlspecialchars($order['cashier_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['customer_nickname']): ?>
                                    <strong><?php echo htmlspecialchars($order['customer_nickname']); ?></strong>
                                    <?php if ($order['customer_phone']): ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">No name</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo $order['order_type']; ?>">
                                        <?php echo ucfirst($order['order_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-secondary me-2"><?php echo $order['item_count']; ?></span>
                                        <small class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($order['product_names']); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($order['product_names'] ?? 'No items', 0, 30, '...')); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-success">₱<?php echo number_format($order['total_amount'], 2); ?></strong>
                                    <?php if ($payment): ?>
                                    <br>
                                    <small class="payment-badge"><?php echo ucfirst($payment['payment_method']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                    <?php if ($order['status'] === 'pending' && $order['order_type'] === 'online'): ?>
                                    <br>
                                    <small class="text-warning">
                                        <i class="fas fa-clock me-1"></i> Needs confirmation
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex">
                                        <button class="btn btn-sm btn-outline-primary action-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#orderDetailsModal"
                                                onclick="showOrderDetails(<?php echo $order['id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                                        <button class="btn btn-sm btn-outline-warning action-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#updateStatusModal"
                                                onclick="prepareStatusUpdate(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>', '<?php echo htmlspecialchars(addslashes($order['order_number'])); ?>')"
                                                title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] === 'pending' || $order['status'] === 'cancelled'): ?>
                                        <button class="btn btn-sm btn-outline-danger action-btn"
                                                onclick="deleteOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars(addslashes($order['order_number'])); ?>')"
                                                title="Delete Order">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details - <span id="modalOrderNumber"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="orderDetailsContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" id="updateOrderId">
                    
                    <div class="modal-body">
                        <p>Update status for order: <strong id="updateOrderNumber"></strong></p>
                        
                        <div class="mb-3">
                            <label for="statusSelect" class="form-label">New Status</label>
                            <select class="form-select" id="statusSelect" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="preparing">Preparing</option>
                                <option value="ready">Ready</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Status changes will be logged and may trigger inventory updates.</small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function showOrderDetails(orderId) {
            const modalOrderNumber = document.getElementById('modalOrderNumber');
            const content = document.getElementById('orderDetailsContent');
            
            content.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            fetch(`../api/get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalOrderNumber.textContent = data.order.order_number;
                        content.innerHTML = buildOrderDetailsHTML(data);
                    } else {
                        content.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Error loading order details
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Network error. Please try again.
                        </div>
                    `;
                });
        }
        
        function buildOrderDetailsHTML(data) {
            const order = data.order;
            const items = data.items;
            const timeline = data.timeline || [];
            
            let html = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Customer Information</h6>
                        <table class="table table-sm order-details-table">
                            <tr>
                                <th width="40%">Order Number:</th>
                                <td>${escapeHtml(order.order_number)}</td>
                            </tr>
                            <tr>
                                <th>Customer Name:</th>
                                <td>${order.customer_nickname ? escapeHtml(order.customer_nickname) : '<span class="text-muted">Not specified</span>'}</td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td>${order.customer_phone ? escapeHtml(order.customer_phone) : '<span class="text-muted">Not specified</span>'}</td>
                            </tr>
                            <tr>
                                <th>Order Type:</th>
                                <td><span class="type-badge type-${order.order_type}">${order.order_type}</span></td>
                            </tr>
                            <tr>
                                <th>Created By:</th>
                                <td>${order.cashier_name || '<span class="text-muted">Unknown</span>'}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Order Summary</h6>
                        <table class="table table-sm order-details-table">
                            <tr>
                                <th width="40%">Status:</th>
                                <td><span class="status-badge status-${order.status}">${order.status}</span></td>
                            </tr>
                            <tr>
                                <th>Date:</th>
                                <td>${new Date(order.created_at).toLocaleString()}</td>
                            </tr>
                            <tr>
                                <th>Subtotal:</th>
                                <td>₱${parseFloat(order.subtotal).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <th>Tax (12%):</th>
                                <td>₱${parseFloat(order.tax_amount).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <th>Total:</th>
                                <td><strong class="text-success">₱${parseFloat(order.total_amount).toFixed(2)}</strong></td>
                            </tr>
                        </table>
                        
                        ${data.payment ? `
                            <h6 class="mt-3">Payment Information</h6>
                            <table class="table table-sm order-details-table">
                                <tr>
                                    <th width="40%">Method:</th>
                                    <td>${data.payment.payment_method}</td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td><span class="badge ${data.payment.status === 'completed' ? 'bg-success' : 'bg-warning'}">${data.payment.status}</span></td>
                                </tr>
                                <tr>
                                    <th>Amount:</th>
                                    <td>₱${parseFloat(data.payment.amount).toFixed(2)}</td>
                                </tr>
                                ${data.payment.reference_number ? `
                                <tr>
                                    <th>Reference:</th>
                                    <td>${escapeHtml(data.payment.reference_number)}</td>
                                </tr>
                                ` : ''}
                                <tr>
                                    <th>Date:</th>
                                    <td>${new Date(data.payment.payment_date).toLocaleString()}</td>
                                </tr>
                            </table>
                        ` : ''}
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Order Items</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Addons</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;
            
            items.forEach(item => {
                let addonsHtml = '';
                if (item.addons && item.addons.length > 0) {
                    addonsHtml = '<ul class="list-unstyled mb-0">';
                    item.addons.forEach(addon => {
                        addonsHtml += `<li><small>+ ${addon.quantity}× ${escapeHtml(addon.name)} (₱${addon.price_at_time})</small></li>`;
                    });
                    addonsHtml += '</ul>';
                }
                
                html += `
                    <tr>
                        <td>
                            <strong>${escapeHtml(item.product_name)}</strong>
                            ${item.special_request ? `<br><small class="text-muted"><i>"${escapeHtml(item.special_request)}"</i></small>` : ''}
                        </td>
                        <td>${item.quantity}</td>
                        <td>₱${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>${addonsHtml}</td>
                        <td><strong>₱${parseFloat(item.total_price).toFixed(2)}</strong></td>
                    </tr>
                `;
            });
            
            html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            if (timeline.length > 0) {
                html += `
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Order Timeline</h6>
                        </div>
                        <div class="card-body">
                            <div class="order-timeline">
                `;
                
                timeline.forEach((event, index) => {
                    const isActive = index === 0;
                    html += `
                        <div class="timeline-item ${isActive ? 'active' : ''}">
                            <strong>${escapeHtml(event.new_status)}</strong>
                            <div class="text-muted">
                                <small>${new Date(event.created_at).toLocaleString()}</small>
                                ${event.changed_by ? `<br><small>by ${escapeHtml(event.changed_by)}</small>` : ''}
                            </div>
                            ${event.notes ? `<div class="mt-1"><small><i>${escapeHtml(event.notes)}</i></small></div>` : ''}
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                    </div>
                `;
            }
            
            return html;
        }
        
        function prepareStatusUpdate(orderId, currentStatus, orderNumber) {
            document.getElementById('updateOrderId').value = orderId;
            document.getElementById('updateOrderNumber').textContent = orderNumber;
            
            const statusSelect = document.getElementById('statusSelect');
            statusSelect.value = currentStatus;
            
            // Disable current status option
            Array.from(statusSelect.options).forEach(option => {
                option.disabled = option.value === currentStatus;
            });
        }
        
        function deleteOrder(orderId, orderNumber) {
            Swal.fire({
                title: 'Delete Order?',
                html: `Are you sure you want to delete order <strong>${escapeHtml(orderNumber)}</strong>?<br><small class="text-danger">This action cannot be undone!</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_order';
                    form.appendChild(actionInput);
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'order_id';
                    idInput.value = orderId;
                    form.appendChild(idInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Auto-refresh page every 60 seconds if on first page
        <?php if ($page === 1): ?>
        setTimeout(() => {
            window.location.reload();
        }, 60000);
        <?php endif; ?>
    </script>
</body>
</html>