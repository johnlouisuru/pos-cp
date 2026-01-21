<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$period = $_GET['period'] ?? '7days';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Prepare date parameters
$date_params = [];
$date_condition = '';
$order_date_condition = '';

if ($start_date && $end_date) {
    $date_condition = "WHERE o.order_date BETWEEN ? AND ?";
    $order_date_condition = "WHERE order_date BETWEEN ? AND ?";
    $date_params = [$start_date, $end_date];
} else {
    switch ($period) {
        case '7days':
            $date_condition = "WHERE o.order_date >= CURDATE() - INTERVAL 7 DAY";
            $order_date_condition = "WHERE order_date >= CURDATE() - INTERVAL 7 DAY";
            break;
        case '15days':
            $date_condition = "WHERE o.order_date >= CURDATE() - INTERVAL 15 DAY";
            $order_date_condition = "WHERE order_date >= CURDATE() - INTERVAL 15 DAY";
            break;
        case '30days':
            $date_condition = "WHERE o.order_date >= CURDATE() - INTERVAL 30 DAY";
            $order_date_condition = "WHERE order_date >= CURDATE() - INTERVAL 30 DAY";
            break;
        case 'alltime':
            $date_condition = "";
            $order_date_condition = "";
            break;
    }
}

// 1. All products sold - CORRECTED VERSION
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.name,
        c.name as category_name,
        p.price,
        p.stock,
        p.min_stock,
        COALESCE(sales.total_sold, 0) as total_sold,
        COALESCE(sales.total_revenue, 0) as total_revenue,
        COALESCE(sales.total_sold / GREATEST(DATEDIFF(CURDATE(), MIN(sales.order_date)), 1), 0) as avg_daily_sales
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN (
        SELECT 
            oi.product_id,
            SUM(oi.quantity) as total_sold,
            SUM(oi.total_price) as total_revenue,
            MIN(o.order_date) as order_date
        FROM order_items oi
        LEFT JOIN orders o ON oi.order_id = o.id
        " . ($date_condition ? $date_condition . " " : "") . "
        GROUP BY oi.product_id
    ) sales ON p.id = sales.product_id
    GROUP BY p.id, p.name, c.name, p.price, p.stock, p.min_stock
    ORDER BY total_sold DESC
");
if ($date_condition && !empty($date_params)) {
    $stmt->execute($date_params);
} else {
    $stmt->execute();
}
$products_sold = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Most sold products (top 10)
$most_sold = array_slice($products_sold, 0, 10);

// 3. Products less than minimum stock
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.name,
        c.name as category_name,
        p.stock,
        p.min_stock,
        p.price,
        (p.min_stock - p.stock) as stock_needed,
        (p.min_stock - p.stock) * p.price as restock_cost
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock < p.min_stock
    ORDER BY (p.min_stock - p.stock) DESC
");
$stmt->execute();
$low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Sales by order type - This should be correct
$stmt = $pdo->prepare("
    SELECT 
        o.order_type,
        COUNT(DISTINCT o.id) as order_count,
        (
            SELECT COUNT(oi2.id) 
            FROM order_items oi2 
            WHERE oi2.order_id = o.id
        ) as item_count,
        SUM(o.total_amount) as total_revenue,
        AVG(o.total_amount) as avg_order_value
    FROM orders o
    " . ($date_condition ? $date_condition . " " : "") . "
    GROUP BY o.order_type
    ORDER BY total_revenue DESC
");

if ($date_condition && !empty($date_params)) {
    $stmt->execute($date_params);
} else {
    $stmt->execute();
}
$sales_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Sales by payment method
$payment_sql = "
    SELECT 
        p.payment_method,
        COUNT(DISTINCT p.order_id) as transaction_count,
        SUM(p.amount) as total_amount,
        AVG(p.amount) as avg_transaction_value
    FROM payments p
    LEFT JOIN orders o ON p.order_id = o.id
    WHERE p.status = 'completed'
";

if ($date_condition) {
    $payment_sql .= " AND o.order_date >= CURDATE() - INTERVAL " . str_replace(['WHERE o.order_date >= CURDATE() - INTERVAL ', ' DAY'], '', $date_condition) . " DAY";
} elseif ($start_date && $end_date) {
    $payment_sql .= " AND o.order_date BETWEEN ? AND ?";
}

$payment_sql .= " GROUP BY p.payment_method ORDER BY total_amount DESC";

$stmt = $pdo->prepare($payment_sql);

if ($start_date && $end_date) {
    $stmt->execute([$start_date, $end_date]);
} else {
    $stmt->execute();
}
$sales_by_payment = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Daily sales trend (last 30 days)
$stmt = $pdo->prepare("
    SELECT 
        DATE(o.order_date) as sale_date,
        COUNT(DISTINCT o.id) as order_count,
        SUM(o.total_amount) as daily_revenue,
        SUM(oi.quantity) as items_sold,
        AVG(o.total_amount) as avg_order_value
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.order_date >= CURDATE() - INTERVAL 30 DAY
    GROUP BY DATE(o.order_date)
    ORDER BY sale_date DESC
    LIMIT 30
");
$stmt->execute();
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Top categories by revenue - CORRECTED
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.color_code,
        COUNT(DISTINCT p.id) as product_count,
        COALESCE(sales.items_sold, 0) as items_sold,
        COALESCE(sales.total_revenue, 0) as total_revenue,
        COALESCE(sales.total_revenue / NULLIF(sales.items_sold, 0), 0) as avg_item_price
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN (
        SELECT 
            p.category_id,
            SUM(oi.quantity) as items_sold,
            SUM(oi.total_price) as total_revenue
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN orders o ON oi.order_id = o.id
        " . ($date_condition ? $date_condition . " " : "") . "
        GROUP BY p.category_id
    ) sales ON c.id = sales.category_id
    GROUP BY c.id, c.name, c.color_code
    ORDER BY total_revenue DESC
");
if ($date_condition && !empty($date_params)) {
    $stmt->execute($date_params);
} else {
    $stmt->execute();
}
$categories_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Hourly sales analysis
$stmt = $pdo->prepare("
    SELECT 
        HOUR(o.created_at) as hour_of_day,
        COUNT(DISTINCT o.id) as order_count,
        SUM(o.total_amount) as hourly_revenue,
        AVG(o.total_amount) as avg_order_value
    FROM orders o
    " . ($date_condition ? $date_condition . " " : "") . "
    GROUP BY HOUR(o.created_at)
    ORDER BY hour_of_day
");
if ($date_condition && !empty($date_params)) {
    $stmt->execute($date_params);
} else {
    $stmt->execute();
}
$hourly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Addon sales performance - CORRECTED
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.name,
        a.price as addon_price,
        COALESCE(addon_sales.times_ordered, 0) as times_ordered,
        COALESCE(addon_sales.orders_with_addon, 0) as orders_with_addon,
        COALESCE(addon_sales.total_quantity, 0) as total_quantity,
        COALESCE(addon_sales.addon_revenue, 0) as addon_revenue
    FROM addons a
    LEFT JOIN (
        SELECT 
            oia.addon_id,
            COUNT(oia.id) as times_ordered,
            COUNT(DISTINCT oi.order_id) as orders_with_addon,
            SUM(oia.quantity) as total_quantity,
            SUM(oia.quantity * oia.price_at_time) as addon_revenue
        FROM order_item_addons oia
        LEFT JOIN order_items oi ON oia.order_item_id = oi.id
        LEFT JOIN orders o ON oi.order_id = o.id
        " . ($date_condition ? $date_condition . " " : "") . "
        GROUP BY oia.addon_id
    ) addon_sales ON a.id = addon_sales.addon_id
    ORDER BY addon_revenue DESC
");

if ($date_condition && !empty($date_params)) {
    $stmt->execute($date_params);
} else {
    $stmt->execute();
}
$addons_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 10. Customer behavior (for online orders)
$customer_sql = "
    SELECT 
        COUNT(DISTINCT customer_nickname) as unique_customers,
        COUNT(DISTINCT customer_phone) as customers_with_phone,
        AVG(total_amount) as avg_online_order_value,
        MAX(total_amount) as max_online_order,
        MIN(total_amount) as min_online_order
    FROM orders 
    WHERE order_type = 'online' 
    AND customer_nickname IS NOT NULL 
    AND customer_nickname != ''
";

if ($date_condition) {
    $customer_sql .= " AND " . str_replace('WHERE o.', '', $date_condition);
}

$stmt = $pdo->prepare($customer_sql);
if ($date_condition && !empty($date_params)) {
    $stmt->execute($date_params);
} else {
    $stmt->execute();
}
$customer_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 11. Inventory turnover rate
$total_inventory_value = 0;
$total_sold_value = 0;

foreach ($products_sold as $product) {
    if ($product['stock'] > 0) {
        $total_inventory_value += $product['stock'] * $product['price'];
    }
    $total_sold_value += $product['total_revenue'];
}

$inventory_turnover = $total_inventory_value > 0 ? ($total_sold_value / $total_inventory_value) : 0;

// 12. Station performance - CORRECTED
$stmt = $pdo->prepare("
    SELECT 
        ks.id,
        ks.name,
        ks.color_code,
        COUNT(DISTINCT p.id) as products_assigned,
        COALESCE(sales.items_processed, 0) as items_processed,
        COALESCE(sales.station_revenue, 0) as station_revenue,
        COALESCE(AVG(sales.avg_prep_time), 0) as avg_prep_time
    FROM kitchen_stations ks
    LEFT JOIN products p ON ks.id = p.station_id
    LEFT JOIN (
        SELECT 
            p.station_id,
            SUM(oi.quantity) as items_processed,
            SUM(oi.total_price) as station_revenue,
            AVG(TIMESTAMPDIFF(MINUTE, o.created_at, o.completed_at)) as avg_prep_time
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN orders o ON oi.order_id = o.id
        " . ($date_condition ? $date_condition . " AND o.status = 'completed'" : "WHERE o.status = 'completed'") . "
        GROUP BY p.station_id
    ) sales ON ks.id = sales.station_id
    GROUP BY ks.id, ks.name, ks.color_code
    ORDER BY station_revenue DESC
");
if ($date_condition && !empty($date_params)) {
    $stmt->execute($date_params);
} else {
    $stmt->execute();
}
$station_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 13. Sales Prediction for Tomorrow
$stmt = $pdo->prepare("
    SELECT 
        DATE(order_date) as sale_date,
        SUM(total_amount) as daily_revenue,
        COUNT(id) as daily_orders,
        (
            SELECT SUM(oi.quantity)
            FROM order_items oi 
            LEFT JOIN orders o2 ON oi.order_id = o2.id
            WHERE DATE(o2.order_date) = DATE(o.order_date)
        ) as daily_items
    FROM orders o
    WHERE order_date >= CURDATE() - INTERVAL 30 DAY
    AND order_date < CURDATE()
    GROUP BY DATE(order_date)
    ORDER BY sale_date DESC
");
$stmt->execute();
$daily_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate predictions
$predictions = [
    'revenue' => 0,
    'orders' => 0,
    'items' => 0,
    'confidence' => 'low',
    'trend' => 'stable',
    'peak_hour' => null,
    'busy_periods' => [],
    'recommended_stock' => []
];

if (count($daily_history) > 0) {
    // Simple moving average (7-day window)
    $recent_days = min(7, count($daily_history));
    $recent_data = array_slice($daily_history, 0, $recent_days);
    
    $sum_revenue = 0;
    $sum_orders = 0;
    $sum_items = 0;
    
    foreach ($recent_data as $day) {
        $sum_revenue += $day['daily_revenue'];
        $sum_orders += $day['daily_orders'];
        $sum_items += $day['daily_items'];
    }
    
    // Moving average prediction
    $predictions['revenue'] = round($sum_revenue / $recent_days, 2);
    $predictions['orders'] = round($sum_orders / $recent_days);
    $predictions['items'] = round($sum_items / $recent_days);
    
    // Calculate trend
    if ($recent_days >= 2) {
        $today_avg = $recent_data[0]['daily_revenue'] ?? 0;
        $yesterday_avg = $recent_data[1]['daily_revenue'] ?? 0;
        
        if ($today_avg > $yesterday_avg * 1.1) {
            $predictions['trend'] = 'up';
        } elseif ($today_avg < $yesterday_avg * 0.9) {
            $predictions['trend'] = 'down';
        } else {
            $predictions['trend'] = 'stable';
        }
    }
    
    // Confidence level based on data consistency
    $revenues = array_column($recent_data, 'daily_revenue');
    $std_dev = std_dev($revenues);
    $avg_revenue = array_sum($revenues) / count($revenues);
    $cv = ($std_dev / $avg_revenue) * 100; // Coefficient of variation
    
    if ($cv < 20) {
        $predictions['confidence'] = 'high';
    } elseif ($cv < 40) {
        $predictions['confidence'] = 'medium';
    } else {
        $predictions['confidence'] = 'low';
    }
    
    // Predict peak hours based on historical hourly data
    $hourly_stmt = $pdo->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as order_count,
            AVG(total_amount) as avg_order
        FROM orders
        WHERE created_at >= CURDATE() - INTERVAL 14 DAY
        GROUP BY HOUR(created_at)
        HAVING order_count > 0
        ORDER BY order_count DESC
        LIMIT 3
    ");
    $hourly_stmt->execute();
    $peak_hours = $hourly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $predictions['peak_hour'] = $peak_hours[0]['hour'] ?? null;
    $predictions['busy_periods'] = $peak_hours;
    
    // Predict top selling products for tomorrow
    $product_stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            c.name as category,
            SUM(oi.quantity) as total_sold,
            AVG(oi.quantity) as avg_daily_sales,
            p.stock,
            p.min_stock
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date >= CURDATE() - INTERVAL 7 DAY
        GROUP BY p.id, p.name, c.name, p.stock, p.min_stock
        HAVING total_sold > 0
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $product_stmt->execute();
    $top_products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate recommended stock for tomorrow
    foreach ($top_products as $product) {
        $avg_daily = $product['avg_daily_sales'] ?: 0;
        $recommended_stock = max(
            $product['min_stock'] * 2, // Buffer
            ceil($avg_daily * 1.5) // 1.5x daily average
        );
        
        $predictions['recommended_stock'][] = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'category' => $product['category'],
            'current_stock' => $product['stock'],
            'avg_daily_sales' => round($avg_daily, 1),
            'recommended_stock' => $recommended_stock,
            'need_to_order' => max(0, $recommended_stock - $product['stock']),
            'priority' => $product['stock'] < $product['min_stock'] ? 'high' : 
                         ($product['stock'] < $recommended_stock ? 'medium' : 'low')
        ];
    }
}

// Helper function for standard deviation
function std_dev($array) {
    $n = count($array);
    if ($n <= 1) {
        return 0;
    }
    $mean = array_sum($array) / $n;
    $sum_sq_diff = 0;
    foreach ($array as $val) {
        $diff = $val - $mean;
        $sum_sq_diff += $diff * $diff;
    }
    return sqrt($sum_sq_diff / ($n - 1));
}

// Day of week adjustment
$day_of_week = date('w'); // 0 (Sunday) to 6 (Saturday)
$tomorrow_dow = ($day_of_week + 1) % 7;

$stmt = $pdo->prepare("
    SELECT 
        DAYOFWEEK(order_date) as dow,
        AVG(total_amount) as avg_revenue,
        AVG(
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id)
        ) as avg_items_per_order
    FROM orders o
    WHERE order_date >= CURDATE() - INTERVAL 90 DAY
    GROUP BY DAYOFWEEK(order_date)
");
$stmt->execute();
$dow_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dow_multiplier = 1.0;
foreach ($dow_stats as $stat) {
    if ($stat['dow'] == $tomorrow_dow + 1) { // Adjust for MySQL DAYOFWEEK (1=Sunday)
        $global_avg = array_sum(array_column($dow_stats, 'avg_revenue')) / count($dow_stats);
        $dow_multiplier = $stat['avg_revenue'] / max($global_avg, 1);
        break;
    }
}

// Apply day of week adjustment to predictions
$predictions['revenue'] *= $dow_multiplier;
$predictions['orders'] = round($predictions['orders'] * $dow_multiplier);
$predictions['items'] = round($predictions['items'] * $dow_multiplier);

// Calculate totals for dashboard - CORRECTED
$total_revenue = 0;
$total_orders = 0;
$total_items = 0;

foreach ($products_sold as $product) {
    $total_revenue += $product['total_revenue'];
    $total_items += $product['total_sold'];
}

// Or even better, get these from a separate accurate query:
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        SUM(o.total_amount) as total_revenue,
        (
            SELECT SUM(quantity) 
            FROM order_items oi 
            LEFT JOIN orders o2 ON oi.order_id = o2.id
            " . ($date_condition ? $date_condition . " " : "") . "
        ) as total_items
    FROM orders o
    " . ($date_condition ? $date_condition . " " : "") . "
");
if ($date_condition && !empty($date_params)) {
    $stmt->execute($date_params);
} else {
    $stmt->execute();
}
$overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total_revenue = $overall_stats['total_revenue'] ?? 0;
$total_orders = $overall_stats['total_orders'] ?? 0;
$total_items = $overall_stats['total_items'] ?? 0;
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
$low_stock_count = count($low_stock_products);
$restock_cost = array_sum(array_column($low_stock_products, 'restock_cost'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .revenue-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .orders-card { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .items-card { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .avg-card { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stock-card { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .cost-card { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.1);
        }
        .category-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        .low-stock {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }
        .filter-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .prediction-card {
    border-left: 4px solid #17a2b8;
}
.prediction-high {
    border-left: 4px solid #28a745;
}
.prediction-medium {
    border-left: 4px solid #ffc107;
}
.prediction-low {
    border-left: 4px solid #dc3545;
}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i> Sales Reports
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
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
                        <a class="nav-link active" href="reports.php">
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
        <!-- Date Filters -->
        <div class="filter-box">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Report Period</label>
                    <select name="period" class="form-select" onchange="this.form.submit()">
                        <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="15days" <?php echo $period === '15days' ? 'selected' : ''; ?>>Last 15 Days</option>
                        <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="alltime" <?php echo $period === 'alltime' ? 'selected' : ''; ?>>All Time</option>
                        <option value="custom" <?php echo $start_date ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo $start_date; ?>" 
                           onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo $end_date; ?>" 
                           onchange="this.form.submit()">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Dashboard Stats -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-white revenue-card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Total Revenue</h6>
                                <h4 class="mb-0">₱<?php echo number_format($total_revenue, 2); ?></h4>
                                <small><?php echo $total_orders; ?> orders</small>
                            </div>
                            <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white orders-card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Total Orders</h6>
                                <h4 class="mb-0"><?php echo $total_orders; ?></h4>
                                <small><?php echo $total_items; ?> items</small>
                            </div>
                            <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white avg-card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Avg Order Value</h6>
                                <h4 class="mb-0">₱<?php echo number_format($avg_order_value, 2); ?></h4>
                                <small>Per transaction</small>
                            </div>
                            <i class="fas fa-chart-bar fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white items-card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Items Sold</h6>
                                <h4 class="mb-0"><?php echo $total_items; ?></h4>
                                <small><?php echo count($products_sold); ?> products</small>
                            </div>
                            <i class="fas fa-boxes fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white stock-card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Low Stock Items</h6>
                                <h4 class="mb-0"><?php echo $low_stock_count; ?></h4>
                                <small>Needs attention</small>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white cost-card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Restock Cost</h6>
                                <h4 class="mb-0">₱<?php echo number_format($restock_cost, 2); ?></h4>
                                <small>To replenish stock</small>
                            </div>
                            <i class="fas fa-truck-loading fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Sales Trend (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Sales by Order Type</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="orderTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabbed Content -->
        <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button">
                    <i class="fas fa-box me-1"></i> Products Sold
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button">
                    <i class="fas fa-exclamation-triangle me-1"></i> Low Stock
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button">
                    <i class="fas fa-list me-1"></i> Categories
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button">
                    <i class="fas fa-credit-card me-1"></i> Payment Methods
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="addons-tab" data-bs-toggle="tab" data-bs-target="#addons" type="button">
                    <i class="fas fa-plus-circle me-1"></i> Addons Performance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stations-tab" data-bs-toggle="tab" data-bs-target="#stations" type="button">
                    <i class="fas fa-blender me-1"></i> Station Performance
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="reportTabsContent">
            <!-- Products Sold Tab -->
            <div class="tab-pane fade show active" id="products" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Products Sold Report</h5>
                        <div>
                            <span class="badge bg-primary"><?php echo count($products_sold); ?> Products</span>
                            <span class="badge bg-success ms-2">₱<?php echo number_format($total_sold_value, 2); ?> Revenue</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Sold</th>
                                        <th>Revenue</th>
                                        <th>Avg Daily</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products_sold as $product): 
                                        $stock_status = $product['stock'] < $product['min_stock'] ? 'low-stock' : '';
                                    ?>
                                    <tr class="<?php echo $stock_status; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="category-badge" style="border-left: 3px solid <?php echo $product['category_name'] === 'Burgers' ? '#d0e628' : '#6c757d'; ?>">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <?php if ($product['stock'] <= 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($product['stock'] < $product['min_stock']): ?>
                                                <span class="badge bg-warning text-dark"><?php echo $product['stock']; ?> left</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?php echo $product['stock']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo $product['total_sold']; ?></strong></td>
                                        <td><strong>₱<?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                                        <td><?php echo number_format($product['avg_daily_sales'], 1); ?>/day</td>
                                        <td>
                                            <?php if ($product['avg_daily_sales'] > 5): ?>
                                                <span class="badge bg-success">High Demand</span>
                                            <?php elseif ($product['avg_daily_sales'] > 1): ?>
                                                <span class="badge bg-primary">Medium</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Low</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Low Stock Tab -->
            <div class="tab-pane fade" id="stock" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Low Stock Alert</h5>
                        <p class="text-muted mb-0">Products below minimum stock level</p>
                    </div>
                    <div class="card-body">
                        <?php if (count($low_stock_products) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Minimum Required</th>
                                        <th>Needed</th>
                                        <th>Price</th>
                                        <th>Restock Cost</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock_products as $product): 
                                        $urgency = ($product['stock'] == 0) ? 'high' : (($product['stock_needed'] > 5) ? 'medium' : 'low');
                                    ?>
                                    <tr class="low-stock">
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="category-badge">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $product['stock']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $product['min_stock']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?php echo $product['stock_needed']; ?></span>
                                        </td>
                                        <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <strong>₱<?php echo number_format($product['restock_cost'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($urgency == 'high'): ?>
                                                <span class="badge bg-danger">Urgent</span>
                                            <?php elseif ($urgency == 'medium'): ?>
                                                <span class="badge bg-warning text-dark">Medium</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Low</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Total restock cost: <strong>₱<?php echo number_format($restock_cost, 2); ?></strong> for <?php echo count($low_stock_products); ?> products
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            All products are sufficiently stocked! No low stock items found.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Categories Tab -->
            <div class="tab-pane fade" id="categories" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Category Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Products</th>
                                        <th>Items Sold</th>
                                        <th>Revenue</th>
                                        <th>Avg Item Price</th>
                                        <th>Revenue Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories_revenue as $category): 
                                        $revenue_share = $total_revenue > 0 ? ($category['total_revenue'] / $total_revenue * 100) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="category-badge" style="border-left: 3px solid <?php echo $category['color_code']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $category['product_count']; ?></td>
                                        <td><strong><?php echo $category['items_sold']; ?></strong></td>
                                        <td><strong>₱<?php echo number_format($category['total_revenue'], 2); ?></strong></td>
                                        <td>₱<?php echo number_format($category['avg_item_price'], 2); ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $revenue_share; ?>%; background-color: <?php echo $category['color_code']; ?>"
                                                     aria-valuenow="<?php echo $revenue_share; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo number_format($revenue_share, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payments Tab -->
            <div class="tab-pane fade" id="payments" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Payment Method Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <?php foreach ($sales_by_payment as $payment): 
                                $percentage = $total_revenue > 0 ? ($payment['total_amount'] / $total_revenue * 100) : 0;
                            ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title mb-1">
                                                    <?php echo ucfirst($payment['payment_method']); ?>
                                                </h6>
                                                <h4 class="mb-0">₱<?php echo number_format($payment['total_amount'], 2); ?></h4>
                                                <small class="text-muted"><?php echo $payment['transaction_count']; ?> transactions</small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-primary"><?php echo number_format($percentage, 1); ?>%</span>
                                                <div class="mt-2">
                                                    <small>Avg: ₱<?php echo number_format($payment['avg_transaction_value'], 2); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Addons Tab -->
            <div class="tab-pane fade" id="addons" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Addons Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Addon</th>
                                        <th>Price</th>
                                        <th>Orders With</th>
                                        <th>Times Ordered</th>
                                        <th>Quantity</th>
                                        <th>Revenue</th>
                                        <th>Popularity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($addons_performance as $addon): 
                                        $popularity = $addon['times_ordered'] > 10 ? 'high' : ($addon['times_ordered'] > 5 ? 'medium' : 'low');
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($addon['name']); ?></strong></td>
                                        <td>₱<?php echo number_format($addon['addon_price'], 2); ?></td>
                                        <td><?php echo $addon['orders_with_addon']; ?></td>
                                        <td><strong><?php echo $addon['times_ordered']; ?></strong></td>
                                        <td><?php echo $addon['total_quantity']; ?></td>
                                        <td><strong>₱<?php echo number_format($addon['addon_revenue'], 2); ?></strong></td>
                                        <td>
                                            <?php if ($popularity == 'high'): ?>
                                                <span class="badge bg-success">High</span>
                                            <?php elseif ($popularity == 'medium'): ?>
                                                <span class="badge bg-primary">Medium</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Low</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stations Tab -->
            <div class="tab-pane fade" id="stations" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Kitchen Station Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($station_performance as $station): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="card-title mb-0">
                                                <span class="badge" style="background-color: <?php echo $station['color_code']; ?>">
                                                    <?php echo htmlspecialchars($station['name']); ?>
                                                </span>
                                            </h6>
                                            <span class="badge bg-info"><?php echo $station['products_assigned']; ?> products</span>
                                        </div>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Items Processed</small>
                                                <h5><?php echo $station['items_processed']; ?></h5>
                                            </div>
                                            <div class="col-6 text-end">
                                                <small class="text-muted">Revenue</small>
                                                <h5>₱<?php echo number_format($station['station_revenue'], 2); ?></h5>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">Avg Prep Time</small>
                                            <div class="progress" style="height: 10px;">
                                                <?php 
                                                $time_score = min(100, ($station['avg_prep_time'] / 30) * 100);
                                                $color = $station['avg_prep_time'] <= 15 ? 'bg-success' : 
                                                        ($station['avg_prep_time'] <= 25 ? 'bg-warning' : 'bg-danger');
                                                ?>
                                                <div class="progress-bar <?php echo $color; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $time_score; ?>%"
                                                     aria-valuenow="<?php echo $station['avg_prep_time']; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="30">
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($station['avg_prep_time'], 1); ?> minutes</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recommendations Section -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i> Business Recommendations
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Inventory Recommendations -->
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">Inventory Management</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if ($low_stock_count > 0): ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                        <strong>Restock Alert:</strong> <?php echo $low_stock_count; ?> items need immediate attention
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-money-bill-wave text-success me-2"></i>
                                        <strong>Restock Cost:</strong> ₱<?php echo number_format($restock_cost, 2); ?> needed
                                    </li>
                                    <?php endif; ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-chart-line text-info me-2"></i>
                                        <strong>Inventory Turnover:</strong> <?php echo number_format($inventory_turnover, 2); ?>x
                                        <?php if ($inventory_turnover < 2): ?>
                                            <span class="badge bg-warning ms-2">Low Turnover</span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sales Recommendations -->
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">Sales Optimization</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php 
                                    $top_product = $most_sold[0] ?? null;
                                    $bottom_product = end($products_sold) ?: null;
                                    ?>
                                    <?php if ($top_product): ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-crown text-warning me-2"></i>
                                        <strong>Top Product:</strong> <?php echo htmlspecialchars($top_product['name']); ?>
                                        (<?php echo $top_product['total_sold']; ?> sold)
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($bottom_product && $bottom_product['total_sold'] == 0): ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                        <strong>Underperforming:</strong> <?php echo htmlspecialchars($bottom_product['name']); ?>
                                        (0 sales) - Consider promotions
                                    </li>
                                    <?php endif; ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-chart-pie text-primary me-2"></i>
                                        <strong>Avg Order Value:</strong> ₱<?php echo number_format($avg_order_value, 2); ?>
                                        <?php if ($avg_order_value < 200): ?>
                                            <span class="badge bg-warning ms-2">Consider upselling</span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Operational Recommendations -->
                    <div class="col-md-4">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">Operational Insights</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php 
                                    $peak_hour = null;
                                    $peak_revenue = 0;
                                    foreach ($hourly_sales as $hour) {
                                        if ($hour['hourly_revenue'] > $peak_revenue) {
                                            $peak_revenue = $hour['hourly_revenue'];
                                            $peak_hour = $hour['hour_of_day'];
                                        }
                                    }
                                    ?>
                                    <?php if ($peak_hour !== null): ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-clock text-primary me-2"></i>
                                        <strong>Peak Hour:</strong> <?php echo $peak_hour; ?>:00 - ₱<?php echo number_format($peak_revenue, 2); ?>
                                    </li>
                                    <?php endif; ?>
                                    <?php 
                                    $online_share = 0;
                                    $walkin_share = 0;
                                    foreach ($sales_by_type as $type) {
                                        if ($type['order_type'] == 'online') $online_share = $type['total_revenue'] / $total_revenue * 100;
                                        if ($type['order_type'] == 'walkin') $walkin_share = $type['total_revenue'] / $total_revenue * 100;
                                    }
                                    ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-mobile-alt text-success me-2"></i>
                                        <strong>Online Orders:</strong> <?php echo number_format($online_share, 1); ?>% of revenue
                                        <?php if ($online_share < 20): ?>
                                            <span class="badge bg-info ms-2">Growth Opportunity</span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-store text-warning me-2"></i>
                                        <strong>Walk-in Orders:</strong> <?php echo number_format($walkin_share, 1); ?>% of revenue
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Prediction Section -->
<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">
            <i class="fas fa-crystal-ball me-2"></i> Tomorrow's Sales Prediction
            <small class="float-end">Based on last <?php echo min(7, count($daily_history)); ?> days data</small>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Prediction Cards -->
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-muted">Predicted Revenue</h6>
                        <h3 class="text-primary">₱<?php echo number_format($predictions['revenue'], 2); ?></h3>
                        <div class="mt-2">
                            <span class="badge bg-<?php 
                                echo $predictions['confidence'] == 'high' ? 'success' : 
                                     ($predictions['confidence'] == 'medium' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($predictions['confidence']); ?> Confidence
                            </span>
                            <span class="badge bg-<?php 
                                echo $predictions['trend'] == 'up' ? 'success' : 
                                     ($predictions['trend'] == 'down' ? 'danger' : 'secondary'); 
                            ?> ms-2">
                                Trend: <?php echo ucfirst($predictions['trend']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-muted">Predicted Orders</h6>
                        <h3 class="text-primary"><?php echo $predictions['orders']; ?></h3>
                        <p class="text-muted mb-1">Avg <?php echo $total_orders > 0 ? number_format($predictions['orders'] / $total_orders * 100, 1) : '0'; ?>% of recent average</p>
                        <small class="text-muted">~<?php echo round($predictions['items'] / max($predictions['orders'], 1), 1); ?> items per order</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-muted">Predicted Items</h6>
                        <h3 class="text-primary"><?php echo $predictions['items']; ?></h3>
                        <p class="text-muted mb-1">Based on moving average</p>
                        <?php if ($dow_multiplier != 1.0): ?>
                        <small class="text-muted">
                            <i class="fas fa-calendar-day me-1"></i>
                            Day adjustment: <?php echo number_format($dow_multiplier * 100, 0); ?>% of weekly average
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Peak Hours Prediction -->
        <?php if (!empty($predictions['busy_periods'])): ?>
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Predicted Busy Hours</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($predictions['busy_periods'] as $period): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card text-center <?php echo $period === reset($predictions['busy_periods']) ? 'border-warning' : 'border-light'; ?>">
                                    <div class="card-body">
                                        <h5 class="mb-1"><?php echo $period['hour']; ?>:00</h5>
                                        <small class="text-muted"><?php echo $period['order_count']; ?> orders avg</small>
                                        <div class="mt-2">
                                            <small>₱<?php echo number_format($period['avg_order'], 2); ?> avg/order</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="alert alert-info mt-2">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Staffing Tip:</strong> Schedule extra staff during peak hours for better customer service
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stock Recommendations -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Recommended Stock for Tomorrow</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Current</th>
                                        <th>Recommended</th>
                                        <th>Need</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($predictions['recommended_stock'] as $item): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo htmlspecialchars($item['product_name']); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['category']); ?></small>
                                        </td>
                                        <td><?php echo $item['current_stock']; ?></td>
                                        <td><?php echo $item['recommended_stock']; ?></td>
                                        <td>
                                            <?php if ($item['need_to_order'] > 0): ?>
                                            <span class="badge bg-warning text-dark">+<?php echo $item['need_to_order']; ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-success">OK</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $item['priority'] == 'high' ? 'danger' : 
                                                     ($item['priority'] == 'medium' ? 'warning' : 'success'); 
                                            ?>">
                                                <?php echo ucfirst($item['priority']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Prediction Factors -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-secondary">
                    <h6><i class="fas fa-chart-line me-2"></i> Prediction Factors Considered:</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <small><i class="fas fa-check-circle text-success me-1"></i> 7-Day Moving Average</small>
                        </div>
                        <div class="col-md-3">
                            <small><i class="fas fa-check-circle text-success me-1"></i> Day of Week Pattern</small>
                        </div>
                        <div class="col-md-3">
                            <small><i class="fas fa-check-circle text-success me-1"></i> Historical Peak Hours</small>
                        </div>
                        <div class="col-md-3">
                            <small><i class="fas fa-check-circle text-success me-1"></i> Product Demand Trends</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actionable Insights -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Actionable Insights for Tomorrow</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-clock text-primary fa-2x"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6>Staff Planning</h6>
                                        <small class="text-muted">
                                            <?php if ($predictions['orders'] > 20): ?>
                                            Schedule 3+ staff during <?php echo $predictions['peak_hour'] ?? 'peak'; ?>:00 hour
                                            <?php elseif ($predictions['orders'] > 10): ?>
                                            Schedule 2 staff during busy periods
                                            <?php else: ?>
                                            Regular staffing should suffice
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-boxes text-success fa-2x"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6>Inventory Prep</h6>
                                        <small class="text-muted">
                                            Prepare <?php echo ceil($predictions['items'] * 1.1); ?> items total
                                            <br>
                                            Focus on top 5 selling products
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-money-bill-wave text-danger fa-2x"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6>Cash Flow</h6>
                                        <small class="text-muted">
                                            Expect ₱<?php echo number_format($predictions['revenue'] * 0.7, 2); ?> cash
                                            <br>
                                            Prepare <?php echo ceil($predictions['orders'] * 0.3); ?> e-receipts
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        
        <!-- Export Options -->
        <div class="card mt-4">
            <div class="card-body text-center">
                <h5 class="card-title">Export Reports</h5>
                <p class="card-text">Download reports for further analysis or sharing</p>
                <a href="export_reports.php?type=products&period=<?php echo $period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-outline-primary me-2">
                    <i class="fas fa-file-excel me-2"></i> Export to Excel
                </a>
                <a href="export_reports.php?type=pdf&period=<?php echo $period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-outline-danger">
                    <i class="fas fa-file-pdf me-2"></i> Export to PDF
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($daily_sales, 'sale_date')); ?>.reverse(),
                datasets: [{
                    label: 'Daily Revenue',
                    data: <?php echo json_encode(array_column($daily_sales, 'daily_revenue')); ?>.reverse(),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Orders',
                    data: <?php echo json_encode(array_column($daily_sales, 'order_count')); ?>.reverse(),
                    borderColor: '#f093fb',
                    backgroundColor: 'rgba(240, 147, 251, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Order Type Chart
        const orderTypeCtx = document.getElementById('orderTypeChart').getContext('2d');
        const orderTypeChart = new Chart(orderTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($sales_by_type, 'order_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($sales_by_type, 'total_revenue')); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#43e97b'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ₱${value.toFixed(2)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Auto-refresh every 60 seconds
        setInterval(() => {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');
                    // Update specific elements if needed
                });
        }, 60000);
    </script>
</body>
</html>