<?php
// session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get all kitchen stations
$stmt = $pdo->query("SELECT * FROM kitchen_stations ORDER BY display_order");
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all products with their current station
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name, ks.name as station_name, ks.color_code
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN kitchen_stations ks ON p.station_id = ks.id 
    ORDER BY c.display_order, p.name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group products by category for better organization
$products_by_category = [];
foreach ($products as $product) {
    $cat_id = $product['category_id'];
    if (!isset($products_by_category[$cat_id])) {
        $products_by_category[$cat_id] = [
            'category_name' => $product['category_name'],
            'products' => []
        ];
    }
    $products_by_category[$cat_id]['products'][] = $product;
}

// Handle bulk assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_assign'])) {
    $station_id = $_POST['station_id'];
    $product_ids = $_POST['product_ids'] ?? [];
    
    if (!empty($product_ids)) {
        try {
            $pdo->beginTransaction();
            
            // Update selected products
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $pdo->prepare("UPDATE products SET station_id = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$station_id], $product_ids));
            
            // Update products NOT selected to remove station assignment
            if (empty($station_id)) {
                // If assigning to "No Station", clear all other products' stations
                $all_products_stmt = $pdo->query("SELECT id FROM products");
                $all_product_ids = array_column($all_products_stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
                $unselected_ids = array_diff($all_product_ids, $product_ids);
                
                if (!empty($unselected_ids)) {
                    $placeholders = implode(',', array_fill(0, count($unselected_ids), '?'));
                    $stmt = $pdo->prepare("UPDATE products SET station_id = NULL WHERE id IN ($placeholders)");
                    $stmt->execute($unselected_ids);
                }
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Successfully assigned " . count($product_ids) . " product(s) to station.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error updating assignments: " . $e->getMessage();
        }
    } else {
        $_SESSION['warning'] = "No products selected for assignment.";
    }
    
    header('Location: station_assignments.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Assignments - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .station-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
            margin: 2px;
        }
        
        .product-card {
            transition: all 0.3s;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-card.selected {
            border-color: #0d6efd;
            background-color: #f0f8ff;
        }
        
        .category-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .color-indicator {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            border: 1px solid #dee2e6;
        }
        
        .station-card {
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        
        .station-card:hover {
            border-color: #0d6efd;
        }
        
        .station-card.active {
            border-color: #0d6efd;
            background-color: #f0f8ff;
        }
        
        .stat-box {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .stat-box h3 {
            margin: 0;
            font-weight: bold;
        }
        
        .drag-handle {
            cursor: move;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-project-diagram me-2"></i> Station Assignments
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-box me-1"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kitchen_stations.php">
                            <i class="fas fa-utensils me-1"></i> Stations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="station_assignments.php">
                            <i class="fas fa-project-diagram me-1"></i> Assignments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pos.php">
                            <i class="fas fa-cash-register me-1"></i> POS
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Column: Stations -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-utensils me-2"></i> Kitchen Stations</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" id="searchStation" class="form-control" placeholder="Search stations...">
                        </div>
                        
                        <div id="stationsList">
                            <!-- "No Station" option -->
                            <div class="station-card mb-2" data-station-id="" onclick="selectStation('')">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-times-circle fa-2x text-muted mb-2"></i>
                                        <h6 class="mb-0">No Station</h6>
                                        <small class="text-muted">Unassigned products</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Station cards -->
                            <?php foreach ($stations as $station): ?>
                            <div class="station-card mb-2" data-station-id="<?php echo $station['id']; ?>" 
                                 onclick="selectStation(<?php echo $station['id']; ?>)">
                                <div class="card" style="border-left: 4px solid <?php echo $station['color_code']; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($station['name']); ?></h6>
                                                <p class="text-muted mb-1 small"><?php echo htmlspecialchars($station['description'] ?: 'No description'); ?></p>
                                            </div>
                                            <div>
                                                <?php if (!$station['is_active']): ?>
                                                <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <i class="fas fa-box me-1"></i>
                                                <?php 
                                                $product_count = array_reduce($products, function($carry, $product) use ($station) {
                                                    return $carry + ($product['station_id'] == $station['id'] ? 1 : 0);
                                                }, 0);
                                                echo $product_count . ' product(s)';
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Assignment Controls -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i> Assignment Controls</h5>
                    </div>
                    <div class="card-body">
                        <form id="bulkAssignmentForm" method="POST">
                            <input type="hidden" id="selectedStationId" name="station_id">
                            <input type="hidden" id="selectedProductIds" name="product_ids">
                            
                            <div class="mb-3">
                                <label class="form-label">Selected Station</label>
                                <div id="selectedStationInfo" class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Click on a station to select
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Selected Products</label>
                                <div id="selectedProductsInfo" class="alert alert-secondary">
                                    <span id="selectedCount">0</span> product(s) selected
                                </div>
                            </div>
                            
                            <button type="submit" name="bulk_assign" class="btn btn-primary w-100" disabled id="assignBtn">
                                <i class="fas fa-link me-2"></i> Assign Selected Products
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="selectAllProducts()">
                                <i class="fas fa-check-square me-2"></i> Select All in Category
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="clearSelection()">
                                <i class="fas fa-times-circle me-2"></i> Clear Selection
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Products by Category -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i> Products by Category</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="toggleAllCategories()">
                                <i class="fas fa-layer-group me-1"></i> Toggle All
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="filterBySelectedStation()">
                                <i class="fas fa-filter me-1"></i> Filter by Station
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php foreach ($products_by_category as $cat_id => $category): ?>
                        <div class="category-section" data-category-id="<?php echo $cat_id; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-folder me-2"></i>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                    <small class="text-muted">(<?php echo count($category['products']); ?> products)</small>
                                </h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="toggleCategory(<?php echo $cat_id; ?>)">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row" id="categoryProducts_<?php echo $cat_id; ?>">
                                <?php foreach ($category['products'] as $product): ?>
                                <div class="col-md-4 col-lg-3 mb-3">
                                    <div class="product-card" 
                                         data-product-id="<?php echo $product['id']; ?>"
                                         data-station-id="<?php echo $product['station_id']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="form-check">
                                                    <input class="form-check-input product-checkbox" 
                                                           type="checkbox" 
                                                           id="product_<?php echo $product['id']; ?>"
                                                           onchange="toggleProductSelection(<?php echo $product['id']; ?>)">
                                                </div>
                                                <div class="drag-handle">
                                                    <i class="fas fa-arrows-alt"></i>
                                                </div>
                                            </div>
                                            
                                            <h6 class="card-title mt-2 mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <p class="text-muted mb-2 small"><?php echo htmlspecialchars($product['description'] ?: 'No description'); ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong class="text-success">₱<?php echo number_format($product['price'], 2); ?></strong>
                                                <span class="badge bg-secondary">Stock: <?php echo $product['stock']; ?></span>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <?php if ($product['station_name']): ?>
                                                <span class="station-badge" style="background-color: <?php echo $product['color_code']; ?>20; color: <?php echo $product['color_code']; ?>; border: 1px solid <?php echo $product['color_code']; ?>;">
                                                    <i class="fas fa-utensils me-1"></i>
                                                    <?php echo htmlspecialchars($product['station_name']); ?>
                                                </span>
                                                <?php else: ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-times me-1"></i> No Station
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="stat-box bg-primary text-white">
                            <i class="fas fa-boxes fa-2x mb-2"></i>
                            <h3><?php echo count($products); ?></h3>
                            <p>Total Products</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box bg-success text-white">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <h3>
                                <?php 
                                $assigned_count = array_reduce($products, function($carry, $product) {
                                    return $carry + ($product['station_id'] ? 1 : 0);
                                }, 0);
                                echo $assigned_count;
                                ?>
                            </h3>
                            <p>Assigned Products</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box bg-warning text-white">
                            <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                            <h3><?php echo count($products) - $assigned_count; ?></h3>
                            <p>Unassigned Products</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box bg-info text-white">
                            <i class="fas fa-utensils fa-2x mb-2"></i>
                            <h3><?php echo count($stations); ?></h3>
                            <p>Total Stations</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Edit Modal -->
    <div class="modal fade" id="quickEditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Quick Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="quickEditForm">
                        <input type="hidden" id="quickEditProductId">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" id="quickEditProductName" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign to Station</label>
                            <select class="form-select" id="quickEditStationId">
                                <option value="">No Station</option>
                                <?php foreach ($stations as $station): ?>
                                <option value="<?php echo $station['id']; ?>">
                                    <?php echo htmlspecialchars($station['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveQuickAssignment()">Save</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        let selectedStationId = null;
        let selectedProductIds = new Set();

        // Check for pre-selected station from other pages
        $(document).ready(function() {
            const preSelectStationId = localStorage.getItem('preSelectStationId');
            const preSelectStationName = localStorage.getItem('preSelectStationName');
            const assignProductId = localStorage.getItem('assignProductId');
            
            if (preSelectStationId) {
                // Auto-select the station
                setTimeout(() => {
                    selectStation(preSelectStationId);
                    
                    // Auto-select products if coming from product edit page
                    if (assignProductId && assignProductId !== 'new') {
                        $(`#product_${assignProductId}`).prop('checked', true);
                        toggleProductSelection(parseInt(assignProductId));
                        
                        // Scroll to the product
                        const productCard = $(`[data-product-id="${assignProductId}"]`);
                        $('html, body').animate({
                            scrollTop: productCard.offset().top - 100
                        }, 500);
                        
                        productCard.addClass('selected');
                    }
                    
                    // Clear stored values
                    localStorage.removeItem('preSelectStationId');
                    localStorage.removeItem('preSelectStationName');
                    localStorage.removeItem('assignProductId');
                    
                    showAlert('info', `Ready to assign products to "${preSelectStationName}"`);
                }, 500);
            }
        });
        
        $(document).ready(function() {
            // Initialize Sortable for each category
            <?php foreach ($products_by_category as $cat_id => $category): ?>
            new Sortable(document.getElementById('categoryProducts_<?php echo $cat_id; ?>'), {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'bg-light'
            });
            <?php endforeach; ?>
            
            // Search stations
            $('#searchStation').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                $('.station-card').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(searchTerm));
                });
            });
            
            // Handle bulk form submission
            $('#bulkAssignmentForm').on('submit', function(e) {
                if (selectedProductIds.size === 0) {
                    e.preventDefault();
                    showAlert('warning', 'Please select at least one product');
                    return;
                }
                
                if (selectedStationId === null) {
                    e.preventDefault();
                    showAlert('warning', 'Please select a station first');
                    return;
                }
                
                $('#selectedProductIds').val(Array.from(selectedProductIds).join(','));
                $('#selectedStationId').val(selectedStationId);
                
                // Show confirmation
                e.preventDefault();
                Swal.fire({
                    title: 'Confirm Assignment',
                    html: `Assign <strong>${selectedProductIds.size}</strong> product(s) to selected station?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, assign them!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $(this).off('submit').submit();
                    }
                });
            });
        });
        
        function selectStation(stationId) {
            selectedStationId = stationId;
            
            // Update UI
            $('.station-card').removeClass('active');
            $(`.station-card[data-station-id="${stationId}"]`).addClass('active');
            
            // Update station info
            if (stationId) {
                const stationCard = $(`.station-card[data-station-id="${stationId}"]`);
                const stationName = stationCard.find('h6').text();
                const stationDesc = stationCard.find('.small').text();
                const productCount = stationCard.find('.text-muted small').text();
                
                $('#selectedStationInfo').html(`
                    <div class="d-flex align-items-center">
                        <div style="width: 15px; height: 15px; background-color: ${stationCard.find('.card').css('border-left-color')}; 
                             margin-right: 10px; border-radius: 3px;"></div>
                        <div>
                            <strong>${stationName}</strong><br>
                            <small class="text-muted">${stationDesc}</small><br>
                            <small class="text-muted">${productCount}</small>
                        </div>
                    </div>
                `);
            } else {
                $('#selectedStationInfo').html(`
                    <div class="d-flex align-items-center">
                        <i class="fas fa-times-circle fa-lg text-muted me-3"></i>
                        <div>
                            <strong>No Station</strong><br>
                            <small class="text-muted">Products will be unassigned</small>
                        </div>
                    </div>
                `);
            }
            
            // Enable/disable assign button
            updateAssignButton();
        }
        
        function toggleProductSelection(productId) {
            const checkbox = $(`#product_${productId}`);
            const productCard = $(`[data-product-id="${productId}"]`);
            
            if (checkbox.prop('checked')) {
                selectedProductIds.add(productId);
                productCard.addClass('selected');
            } else {
                selectedProductIds.delete(productId);
                productCard.removeClass('selected');
            }
            
            updateSelectedCount();
            updateAssignButton();
        }
        
        function updateSelectedCount() {
            $('#selectedCount').text(selectedProductIds.size);
            $('#selectedProductsInfo').toggleClass('alert-primary', selectedProductIds.size > 0);
        }
        
        function updateAssignButton() {
            const btn = $('#assignBtn');
            const canAssign = selectedStationId !== null && selectedProductIds.size > 0;
            btn.prop('disabled', !canAssign);
            btn.toggleClass('btn-primary', canAssign);
            btn.toggleClass('btn-secondary', !canAssign);
        }
        
        function selectAllProducts() {
            if (selectedStationId === null) {
                showAlert('warning', 'Please select a station first');
                return;
            }
            
            // Get current category (first selected product's category or all)
            const selectedProduct = $('.product-checkbox:checked').first();
            let categoryId = null;
            
            if (selectedProduct.length > 0) {
                const productId = selectedProduct.attr('id').replace('product_', '');
                categoryId = $(`[data-product-id="${productId}"]`).closest('.category-section').data('category-id');
            }
            
            let checkboxes;
            if (categoryId) {
                // Select all in current category
                checkboxes = $(`#categoryProducts_${categoryId} .product-checkbox`);
            } else {
                // Select all products
                checkboxes = $('.product-checkbox');
            }
            
            checkboxes.each(function() {
                const productId = $(this).attr('id').replace('product_', '');
                if (!selectedProductIds.has(parseInt(productId))) {
                    $(this).prop('checked', true);
                    selectedProductIds.add(parseInt(productId));
                    $(`[data-product-id="${productId}"]`).addClass('selected');
                }
            });
            
            updateSelectedCount();
            updateAssignButton();
            
            const count = checkboxes.length;
            showAlert('success', `Selected ${count} product(s)`);
        }
        
        function clearSelection() {
            selectedProductIds.clear();
            $('.product-checkbox').prop('checked', false);
            $('.product-card').removeClass('selected');
            updateSelectedCount();
            updateAssignButton();
        }
        
        function toggleCategory(categoryId) {
            const productsDiv = $(`#categoryProducts_${categoryId}`);
            const toggleBtn = $(`[onclick="toggleCategory(${categoryId})"]`);
            
            if (productsDiv.is(':visible')) {
                productsDiv.slideUp();
                toggleBtn.html('<i class="fas fa-chevron-right"></i>');
            } else {
                productsDiv.slideDown();
                toggleBtn.html('<i class="fas fa-chevron-down"></i>');
            }
        }
        
        function toggleAllCategories() {
            const allVisible = $('.category-section .row:visible').length === $('.category-section .row').length;
            
            $('.category-section .row').each(function() {
                if (allVisible) {
                    $(this).slideUp();
                } else {
                    $(this).slideDown();
                }
            });
            
            $('.category-section').each(function() {
                const categoryId = $(this).data('category-id');
                const toggleBtn = $(`[onclick="toggleCategory(${categoryId})"]`);
                toggleBtn.html(allVisible ? '<i class="fas fa-chevron-right"></i>' : '<i class="fas fa-chevron-down"></i>');
            });
        }
        
        function filterBySelectedStation() {
            if (selectedStationId === null) {
                showAlert('warning', 'Please select a station first to filter');
                return;
            }
            
            $('.product-card').each(function() {
                const productStationId = $(this).data('station-id') || '';
                const shouldShow = productStationId == selectedStationId;
                $(this).parent().toggle(shouldShow);
            });
            
            showAlert('info', `Showing products assigned to selected station`);
        }
        
        // Quick assignment for individual products
        function quickAssignProduct(productId) {
            const productCard = $(`[data-product-id="${productId}"]`);
            const productName = productCard.find('.card-title').text();
            const currentStationId = productCard.data('station-id') || '';
            
            $('#quickEditProductId').val(productId);
            $('#quickEditProductName').val(productName);
            $('#quickEditStationId').val(currentStationId);
            
            $('#quickEditModal').modal('show');
        }
        
        function saveQuickAssignment() {
            const productId = $('#quickEditProductId').val();
            const stationId = $('#quickEditStationId').val();
            
            $.ajax({
                url: '../api/products.php?action=update_station',
                type: 'POST',
                data: {
                    product_id: productId,
                    station_id: stationId
                },
                success: function(response) {
                    if (response.success) {
                        $('#quickEditModal').modal('hide');
                        showAlert('success', response.message);
                        
                        // Update UI
                        const productCard = $(`[data-product-id="${productId}"]`);
                        productCard.data('station-id', stationId);
                        
                        // Reload page after delay
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('error', response.message);
                    }
                },
                error: function() {
                    showAlert('error', 'Failed to update assignment');
                }
            });
        }
        
        // Add double-click to products for quick assignment
        $(document).on('dblclick', '.product-card', function() {
            const productId = $(this).data('product-id');
            quickAssignProduct(productId);
        });
        
        function showAlert(type, message) {
            Swal.fire({
                icon: type,
                title: type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Warning!',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
        
        // Add to existing products.php JavaScript
        function openStationAssignmentModal() {
            // Store current product ID if in edit mode
            const productId = $('#editProductId').val() || 'new';
            localStorage.setItem('assignProductId', productId);
            
            // Open assignments page in new tab
            window.open('station_assignments.php', '_blank');
        }
    </script>
</body>
</html>