<?php

require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_addon') {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? 0;
        $is_global = isset($_POST['is_global']) ? 1 : 0;
        $max_quantity = $_POST['max_quantity'] ?? 1;
        
        if (!empty($name)) {
            $stmt = $pdo->prepare("
                INSERT INTO addons (name, description, price, is_global, max_quantity)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $is_global, $max_quantity]);
            $addon_id = $pdo->lastInsertId();
            
            // Handle product associations
            if (!$is_global && isset($_POST['products']) && is_array($_POST['products'])) {
                foreach ($_POST['products'] as $product_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_addons (product_id, addon_id)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$product_id, $addon_id]);
                }
            }
        }
    } elseif ($action === 'update_addon') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $is_global = isset($_POST['is_global']) ? 1 : 0;
    $max_quantity = $_POST['max_quantity'] ?? 1;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    if ($id && !empty($name)) {
        $stmt = $pdo->prepare("
            UPDATE addons 
            SET name = ?, description = ?, price = ?, is_global = ?, 
                max_quantity = ?, is_available = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $price, $is_global, $max_quantity, $is_available, $id]);
        
        // Update product associations
        $pdo->prepare("DELETE FROM product_addons WHERE addon_id = ?")->execute([$id]);
        
        if (!$is_global && isset($_POST['products']) && is_array($_POST['products'])) {
            foreach ($_POST['products'] as $product_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO product_addons (product_id, addon_id)
                    VALUES (?, ?)
                ");
                // Change $addon_id to $id here
                $stmt->execute([$product_id, $id]);  // Changed from $addon_id to $id
            }
        }
    }
    } elseif ($action === 'delete_addon') {
        $id = $_POST['id'] ?? 0;
        
        if ($id) {
            // Delete associations first
            $pdo->prepare("DELETE FROM product_addons WHERE addon_id = ?")->execute([$id]);
            // Delete addon
            $pdo->prepare("DELETE FROM addons WHERE id = ?")->execute([$id]);
        }
    }
    
    header('Location: addons.php');
    exit();
}

// Get all addons
$stmt = $pdo->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM product_addons WHERE addon_id = a.id) as product_count
    FROM addons a 
    ORDER BY a.name
");
$addons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for associations
$stmt = $pdo->query("
    SELECT p.id, p.name, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_available = 1 
    ORDER BY c.name, p.name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Addons - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-plus-circle me-2"></i> Addon Management
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
                        <a class="nav-link active" href="addons.php">
                            <i class="fas fa-plus-circle me-1"></i> Addons
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
        <div class="row">
            <!-- Add Addon Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            Add New Addon
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_addon">
                            
                            <div class="mb-3">
                                <label for="addonName" class="form-label">Addon Name *</label>
                                <input type="text" class="form-control" id="addonName" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="addonDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="addonDescription" name="description" rows="2"></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="addonPrice" class="form-label">Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="addonPrice" name="price" step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="maxQuantity" class="form-label">Max Quantity</label>
                                    <input type="number" class="form-control" id="maxQuantity" name="max_quantity" value="1" min="1">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="isGlobal" name="is_global">
                                    <label class="form-check-label" for="isGlobal">
                                        Global Addon (Available for all products)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="productSelection">
                                <label class="form-label">Select Products</label>
                                <small class="text-muted d-block mb-2">Leave empty if global addon</small>
                                
                                <?php foreach ($products as $product): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="products[]" value="<?php echo $product['id']; ?>" id="product_<?php echo $product['id']; ?>">
                                    <label class="form-check-label" for="product_<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($product['category_name']); ?>)</small>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i> Save Addon
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Addons List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Addons List
                        </h5>
                        <span class="badge bg-primary">
                            <?php echo count($addons); ?> Addons
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Price</th>
                                        <th>Type</th>
                                        <th>Products</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($addons as $addon): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($addon['name']); ?></strong>
                                            <?php if (!$addon['is_available']): ?>
                                            <span class="badge bg-danger ms-1">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($addon['description'] ?: '-'); ?></td>
                                        <td>
                                            <strong>₱<?php echo number_format($addon['price'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($addon['is_global']): ?>
                                            <span class="badge bg-success">Global</span>
                                            <?php else: ?>
                                            <span class="badge bg-info">Product-specific</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($addon['is_global']): ?>
                                            <span class="text-muted">All products</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo $addon['product_count']; ?> products</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editAddonModal"
                                                    onclick="editAddon(<?php echo htmlspecialchars(json_encode($addon)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger"
                                                    onclick="deleteAddon(<?php echo $addon['id']; ?>, '<?php echo htmlspecialchars(addslashes($addon['name'])); ?>')">
                                                <i class="fas fa-trash"></i>
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
    
    <!-- Edit Addon Modal -->
    <div class="modal fade" id="editAddonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Addon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_addon">
                    <input type="hidden" name="id" id="editAddonId">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editAddonName" class="form-label">Addon Name *</label>
                                    <input type="text" class="form-control" id="editAddonName" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="editAddonDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="editAddonDescription" name="description" rows="2"></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="editAddonPrice" class="form-label">Price *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" class="form-control" id="editAddonPrice" name="price" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="editMaxQuantity" class="form-label">Max Quantity</label>
                                        <input type="number" class="form-control" id="editMaxQuantity" name="max_quantity" min="1">
                                    </div>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="editIsGlobal" name="is_global">
                                    <label class="form-check-label" for="editIsGlobal">
                                        Global Addon
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="editIsAvailable" name="is_available" checked>
                                    <label class="form-check-label" for="editIsAvailable">
                                        Addon Available
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="editProductSelection">
                            <label class="form-label">Select Products</label>
                            <small class="text-muted d-block mb-2">Leave empty if global addon</small>
                            
                            <div class="row">
                                <?php foreach ($products as $product): ?>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input product-check" type="checkbox" 
                                               name="products[]" value="<?php echo $product['id']; ?>" 
                                               id="edit_product_<?php echo $product['id']; ?>">
                                        <label class="form-check-label" for="edit_product_<?php echo $product['id']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($product['category_name']); ?>)</small>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Addon</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Toggle product selection based on global addon checkbox
        document.getElementById('isGlobal').addEventListener('change', function() {
            document.getElementById('productSelection').style.display = 
                this.checked ? 'none' : 'block';
        });
        
        document.getElementById('editIsGlobal').addEventListener('change', function() {
            document.getElementById('editProductSelection').style.display = 
                this.checked ? 'none' : 'block';
        });
        
        function editAddon(addon) {
    document.getElementById('editAddonId').value = addon.id;
    document.getElementById('editAddonName').value = addon.name;
    document.getElementById('editAddonDescription').value = addon.description || '';
    document.getElementById('editAddonPrice').value = addon.price;
    document.getElementById('editMaxQuantity').value = addon.max_quantity || 1;
    document.getElementById('editIsGlobal').checked = addon.is_global == 1;
    document.getElementById('editIsAvailable').checked = addon.is_available == 1;
    
    // Show/hide product selection
    document.getElementById('editProductSelection').style.display = 
        addon.is_global == 1 ? 'none' : 'block';
    
    // Uncheck all products first
    document.querySelectorAll('.product-check').forEach(cb => cb.checked = false);
    
    // Load product associations via AJAX
    loadAddonProducts(addon.id);
    
    const modalElement = document.getElementById('editAddonModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    modal.show();
}

function loadAddonProducts(addonId) {
    fetch(`../api/get-addon-products.php?addon_id=${addonId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.products) {
                // Check the checkboxes for products associated with this addon
                data.products.forEach(productId => {
                    const checkbox = document.querySelector(`#edit_product_${productId}`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
        })
        .catch(error => console.error('Error loading addon products:', error));
}
        
        function deleteAddon(id, name) {
            Swal.fire({
                title: 'Delete Addon?',
                text: `Are you sure you want to delete "${name}"?`,
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
                    actionInput.value = 'delete_addon';
                    form.appendChild(actionInput);
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = id;
                    form.appendChild(idInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        // Add this in your DOMContentLoaded or after your scripts
            document.addEventListener('DOMContentLoaded', function() {
                const editAddonModal = document.getElementById('editAddonModal');
                
                if (editAddonModal) {
                    editAddonModal.addEventListener('hidden.bs.modal', function () {
                        // Remove any leftover backdrops
                        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                        // Reset body styles
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    });
                }
            });
    </script>
</body>
</html>