<?php
// session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get all kitchen stations
$stmt = $pdo->query("SELECT * FROM kitchen_stations ORDER BY display_order, name");
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products assigned to each station
$products_by_station = [];
foreach ($stations as $station) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as product_count FROM products WHERE station_id = ?");
    $stmt->execute([$station['id']]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    $products_by_station[$station['id']] = $count['product_count'];
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $station_id = $_POST['delete_id'];
    
    // Check if station has products assigned
    $stmt = $pdo->prepare("SELECT COUNT(*) as product_count FROM products WHERE station_id = ?");
    $stmt->execute([$station_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['product_count'] > 0) {
        $_SESSION['error'] = "Cannot delete station with assigned products. Reassign products first.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM kitchen_stations WHERE id = ?");
            $stmt->execute([$station_id]);
            $_SESSION['success'] = "Station deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting station: " . $e->getMessage();
        }
    }
    header('Location: kitchen_stations.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Kitchen Stations - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: #fff;
            border-bottom: 2px solid #e9ecef;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            font-size: 12px;
        }
        
        .badge-status {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 8px 12px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .modal-content {
            border-radius: 10px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            display: inline-block;
            margin-right: 10px;
            border: 1px solid #dee2e6;
        }
        
        .station-card {
            transition: all 0.3s;
            border-left: 4px solid #6c757d;
        }
        
        .station-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-count-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-utensils me-2"></i> Kitchen Stations
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
                        <a class="nav-link active" href="kitchen_stations.php">
                            <i class="fas fa-utensils me-1"></i> Stations
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
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-utensils me-2"></i> Kitchen Stations</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStationModal">
                            <i class="fas fa-plus me-2"></i> Add New Station
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Stats Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Stations</h5>
                                        <h2 class="mb-0"><?php echo count($stations); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Active Stations</h5>
                                        <h2 class="mb-0">
                                            <?php 
                                            $active_count = array_reduce($stations, function($carry, $station) {
                                                return $carry + ($station['is_active'] ? 1 : 0);
                                            }, 0);
                                            echo $active_count;
                                            ?>
                                        </h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Assigned Products</h5>
                                        <h2 class="mb-0">
                                            <?php 
                                            $total_products = array_sum($products_by_station);
                                            echo $total_products;
                                            ?>
                                        </h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Inactive Stations</h5>
                                        <h2 class="mb-0">
                                            <?php echo count($stations) - $active_count; ?>
                                        </h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" id="searchStation" class="form-control" placeholder="Search stations...">
                            </div>
                            <div class="col-md-4">
                                <select id="filterStatus" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                                    <i class="fas fa-redo me-2"></i> Reset Filters
                                </button>
                            </div>
                        </div>
                        
                        <!-- Stations Table -->
                        <div class="table-responsive">
                            <table id="stationsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Color</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Assigned Products</th>
                                        <th>Display Order</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stations as $station): ?>
                                    <tr>
                                        <td>
                                            <div class="color-preview" style="background-color: <?php echo $station['color_code']; ?>"></div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($station['name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($station['description'] ?: 'No description'); ?>
                                        </td>
                                        <td>
                                            <?php $product_count = $products_by_station[$station['id']] ?? 0; ?>
                                            <span class="badge bg-<?php echo $product_count > 0 ? 'info' : 'secondary'; ?>">
                                                <?php echo $product_count; ?> products
                                            </span>
                                            <?php if ($product_count > 0): ?>
                                            <button class="btn btn-sm btn-outline-info ms-2" 
                                                    onclick="viewAssignedProducts(<?php echo $station['id']; ?>, '<?php echo htmlspecialchars(addslashes($station['name'])); ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $station['display_order']; ?>
                                        </td>
                                        <td>
                                            <?php if ($station['is_active']): ?>
                                            <span class="badge bg-success badge-status">Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger badge-status">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info btn-action" 
                                                    onclick="editStation(<?php echo $station['id']; ?>)"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning btn-action" 
                                                    onclick="toggleStationStatus(<?php echo $station['id']; ?>, <?php echo $station['is_active']; ?>, '<?php echo htmlspecialchars(addslashes($station['name'])); ?>')"
                                                    title="<?php echo $station['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $station['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-action" 
                                                    onclick="confirmDelete(<?php echo $station['id']; ?>, '<?php echo htmlspecialchars(addslashes($station['name'])); ?>', <?php echo $product_count; ?>)"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button class="btn btn-sm btn-success btn-action" 
                                                    onclick="assignProductsToStation(<?php echo $station['id']; ?>, '<?php echo htmlspecialchars(addslashes($station['name'])); ?>')"
                                                    title="Assign Products">
                                                <i class="fas fa-link"></i>
                                            </button>
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
    </div>
    
    <!-- Add Station Modal -->
    <div class="modal fade" id="addStationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Kitchen Station</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addStationForm" action="../api/kitchen_stations.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="stationName" class="form-label">Station Name *</label>
                            <input type="text" class="form-control" id="stationName" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="stationDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="stationDescription" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="stationColor" class="form-label">Color Code</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" id="stationColor" name="color_code" value="#3498db" title="Choose color">
                                        <input type="text" class="form-control" id="colorText" value="#3498db" maxlength="7">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="displayOrder" class="form-label">Display Order</label>
                                    <input type="number" class="form-control" id="displayOrder" name="display_order" value="0" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">Station is Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Station</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Station Modal -->
    <div class="modal fade" id="editStationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Kitchen Station</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editStationForm" action="../api/kitchen_stations.php" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="editStationId" name="id">
                    <div class="modal-body" id="editStationBody">
                        <!-- Content will be loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Station</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Products Modal -->
    <div class="modal fade" id="viewProductsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-boxes me-2"></i>
                        Products in Station: <span id="stationNameTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="productsList" class="row">
                        <!-- Products will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="deleteStationForm" method="POST">
                    <input type="hidden" id="deleteStationId" name="delete_id">
                    <div class="modal-body">
                        <p id="deleteMessage"></p>
                        <div id="productsWarning" class="alert alert-warning d-none">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            This station has <span id="productCount"></span> product(s) assigned. 
                            You cannot delete it until you reassign these products.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Station</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            const table = $('#stationsTable').DataTable({
                pageLength: 25,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search stations..."
                },
                columnDefs: [
                    { orderable: false, targets: [0, 6] }
                ]
            });
            
            // Search input
            $('#searchStation').on('keyup', function() {
                table.search(this.value).draw();
            });
            
            // Status filter
            $('#filterStatus').on('change', function() {
                table.column(5).search(this.value).draw();
            });
            
            // Color picker synchronization
            $('#stationColor').on('input', function() {
                $('#colorText').val(this.value);
            });
            
            $('#colorText').on('input', function() {
                const color = this.value;
                if (/^#[0-9A-F]{6}$/i.test(color)) {
                    $('#stationColor').val(color);
                }
            });
            
            // Handle add station form submission
            $('#addStationForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: '../api/kitchen_stations.php',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#addStationModal').modal('hide');
                            $('#addStationForm')[0].reset();
                            $('#stationColor').val('#3498db');
                            $('#colorText').val('#3498db');
                            
                            showAlert('success', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'An error occurred. Please try again.');
                    }
                });
            });
            
            // Handle edit station form submission
            $('#editStationForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: '../api/kitchen_stations.php',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#editStationModal').modal('hide');
                            showAlert('success', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'An error occurred. Please try again.');
                    }
                });
            });
        });
        
        function editStation(stationId) {
            $.ajax({
                url: '../api/kitchen_stations.php?action=get&id=' + stationId,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        const station = response.data;
                        
                        let formHTML = `
                            <div class="mb-3">
                                <label class="form-label">Station Name *</label>
                                <input type="text" class="form-control" name="name" value="${escapeHtml(station.name)}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2">${escapeHtml(station.description || '')}</textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Color Code</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" id="editStationColor" name="color_code" value="${station.color_code}" title="Choose color">
                                            <input type="text" class="form-control" id="editColorText" value="${station.color_code}" maxlength="7">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Display Order</label>
                                        <input type="number" class="form-control" name="display_order" value="${station.display_order}" min="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" ${station.is_active == 1 ? 'checked' : ''}>
                                <label class="form-check-label">Station is Active</label>
                            </div>
                        `;
                        
                        $('#editStationBody').html(formHTML);
                        $('#editStationId').val(stationId);
                        
                        // Color picker synchronization for edit form
                        $('#editStationColor').on('input', function() {
                            $('#editColorText').val(this.value);
                        });
                        
                        $('#editColorText').on('input', function() {
                            const color = this.value;
                            if (/^#[0-9A-F]{6}$/i.test(color)) {
                                $('#editStationColor').val(color);
                            }
                        });
                        
                        $('#editStationModal').modal('show');
                    } else {
                        showAlert('error', response.message);
                    }
                },
                error: function() {
                    showAlert('error', 'Failed to load station data');
                }
            });
        }
        
        function toggleStationStatus(stationId, currentStatus, stationName) {
            const newStatus = currentStatus ? 0 : 1;
            const action = newStatus ? 'activate' : 'deactivate';
            
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Station`,
                text: `Are you sure you want to ${action} "${stationName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: newStatus ? '#198754' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${action} it!`
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../api/kitchen_stations.php',
                        type: 'POST',
                        data: {
                            action: 'toggle_status',
                            id: stationId,
                            is_active: newStatus
                        },
                        success: function(response) {
                            if (response.success) {
                                showAlert('success', response.message);
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showAlert('error', response.message);
                            }
                        },
                        error: function() {
                            showAlert('error', 'Failed to update station status');
                        }
                    });
                }
            });
        }
        
        function confirmDelete(stationId, stationName, productCount) {
            $('#deleteStationId').val(stationId);
            
            if (productCount > 0) {
                $('#deleteMessage').html(`
                    You are trying to delete <strong>"${escapeHtml(stationName)}"</strong>.
                `);
                $('#productCount').text(productCount);
                $('#productsWarning').removeClass('d-none');
                $('#deleteConfirmModal .btn-danger').prop('disabled', true);
            } else {
                $('#deleteMessage').html(`
                    Are you sure you want to delete <strong>"${escapeHtml(stationName)}"</strong>?
                    This action cannot be undone.
                `);
                $('#productsWarning').addClass('d-none');
                $('#deleteConfirmModal .btn-danger').prop('disabled', false);
            }
            
            $('#deleteConfirmModal').modal('show');
        }
        
        function viewAssignedProducts(stationId, stationName) {
            $('#stationNameTitle').text(stationName);
            
            $.ajax({
                url: '../api/kitchen_stations.php?action=get_products&station_id=' + stationId,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        const products = response.products;
                        const container = $('#productsList');
                        
                        if (products.length > 0) {
                            let html = '';
                            products.forEach(product => {
                                html += `
                                    <div class="col-md-6 mb-3">
                                        <div class="card station-card" style="border-left-color: ${response.station_color}">
                                            <div class="card-body">
                                                <h6 class="card-title">${escapeHtml(product.name)}</h6>
                                                <p class="card-text text-muted mb-1">${escapeHtml(product.description || 'No description')}</p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-info">₱${parseFloat(product.price).toFixed(2)}</span>
                                                    <span class="badge bg-secondary">Stock: ${product.stock}</span>
                                                    <a href="products.php?edit=${product.id}" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            container.html(html);
                        } else {
                            container.html(`
                                <div class="col-12 text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No products assigned to this station</h5>
                                    <p class="text-muted">Assign products from the Products page</p>
                                </div>
                            `);
                        }
                        
                        $('#viewProductsModal').modal('show');
                    } else {
                        showAlert('error', response.message);
                    }
                },
                error: function() {
                    showAlert('error', 'Failed to load assigned products');
                }
            });
        }
        
        function resetFilters() {
            $('#searchStation').val('');
            $('#filterStatus').val('');
            $('#stationsTable').DataTable().search('').columns().search('').draw();
        }
        
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

        function assignProductsToStation(stationId, stationName) {
            // Store the station ID and name
            localStorage.setItem('preSelectStationId', stationId);
            localStorage.setItem('preSelectStationName', stationName);
            
            // Open assignments page
            window.open('station_assignments.php', '_blank');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>