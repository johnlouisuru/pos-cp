<?php

require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_category') {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $display_order = $_POST['display_order'] ?? 0;
        $icon_class = $_POST['icon_class'] ?? '';
        $color_code = $_POST['color_code'] ?? '#6c757d';
        
        if (!empty($name)) {
            $stmt = $pdo->prepare("
                INSERT INTO categories (name, description, display_order, icon_class, color_code)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $display_order, $icon_class, $color_code]);
        }
    } elseif ($action === 'update_category') {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $display_order = $_POST['display_order'] ?? 0;
        $icon_class = $_POST['icon_class'] ?? '';
        $color_code = $_POST['color_code'] ?? '#6c757d';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id && !empty($name)) {
            $stmt = $pdo->prepare("
                UPDATE categories 
                SET name = ?, description = ?, display_order = ?, 
                    icon_class = ?, color_code = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $display_order, $icon_class, $color_code, $is_active, $id]);
        }
    } elseif ($action === 'delete_category') {
        $id = $_POST['id'] ?? 0;
        
        if ($id) {
            // Check if category has products
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            $product_count = $stmt->fetchColumn();
            
            if ($product_count > 0) {
                // Instead of deleting, mark as inactive
                $pdo->prepare("UPDATE categories SET is_active = 0 WHERE id = ?")->execute([$id]);
            } else {
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            }
        }
    }
    
    header('Location: categories.php');
    exit();
}

// Get all categories
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c 
    ORDER BY display_order, name
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-card {
            border-radius: 10px;
            border: none;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin: 0 auto 15px;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #dee2e6;
            cursor: pointer;
        }
        
        .icon-preview {
            font-size: 24px;
            color: #6c757d;
        }
        
        .status-badge {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 20px;
        }
        .icon-preview {
    position: relative;
}
.copied-tooltip {
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-list me-2"></i> Category Management
            </a>
            <div class="collapse navbar-collapse">
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
                        <a class="nav-link active" href="categories.php">
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
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Add Category Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            Add New Category
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_category">
                            
                            <div class="mb-3">
                                <label for="categoryName" class="form-label">Category Name *</label>
                                <input type="text" class="form-control" id="categoryName" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="categoryDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="categoryDescription" name="description" rows="2"></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="displayOrder" class="form-label">Display Order</label>
                                    <input type="number" class="form-control" id="displayOrder" name="display_order" value="0" min="0">
                                </div>
                                <div class="col-md-6">
                                    <label for="colorCode" class="form-label">Color</label>
                                    <input type="color" class="form-control form-control-color" id="colorCode" name="color_code" value="#6c757d" title="Choose color">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="iconClass" class="form-label">Icon Class (FontAwesome)</label>
                                <input type="text" class="form-control" id="iconClass" name="icon_class" placeholder="fas fa-utensils">
                                <small class="text-muted">Leave empty for default icon</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i> Save Category
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Icon Reference -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-icons me-2"></i> Icon Reference (Click the icon to copy its name)</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-4 text-center">
                                    <div class="icon-preview" onclick="copyToClipboard('fas fa-hamburger', this)" style="cursor: pointer;">
                                        <i class="fas fa-hamburger"></i>
                                    </div>
                                    <small>fas fa-hamburger</small>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="icon-preview" onclick="copyToClipboard('fas fa-pizza-slice', this)" style="cursor: pointer;">
                                        <i class="fas fa-pizza-slice"></i>
                                    </div>
                                    <small>fas fa-pizza-slice</small>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="icon-preview" onclick="copyToClipboard('fas fa-wine-glass', this)" style="cursor: pointer;">
                                        <i class="fas fa-wine-glass"></i>
                                    </div>
                                    <small>fas fa-wine-glass</small>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="icon-preview" onclick="copyToClipboard('fas fa-utensils', this)" style="cursor: pointer;">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <small>fas fa-utensils</small>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="icon-preview" onclick="copyToClipboard('fas fa-ice-cream', this)" style="cursor: pointer;">
                                        <i class="fas fa-ice-cream"></i>
                                    </div>
                                    <small>fas fa-ice-cream</small>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="icon-preview" onclick="copyToClipboard('fas fa-coffee', this)" style="cursor: pointer;">
                                        <i class="fas fa-coffee"></i>
                                    </div>
                                    <small>fas fa-coffee</small>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
            
            <!-- Categories List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Categories List
                        </h5>
                        <span class="badge bg-primary">
                            <?php echo count($categories); ?> Categories
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($categories as $category): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card category-card">
                                    <div class="card-body text-center">
                                        <div class="category-icon" style="background: <?php echo $category['color_code']; ?>">
                                            <?php if ($category['icon_class']): ?>
                                            <i class="<?php echo htmlspecialchars($category['icon_class']); ?>"></i>
                                            <?php else: ?>
                                            <i class="fas fa-tag"></i>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                        
                                        <?php if ($category['description']): ?>
                                        <p class="card-text text-muted">
                                            <?php echo htmlspecialchars($category['description']); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-secondary">
                                                <?php echo $category['product_count']; ?> products
                                            </span>
                                            <span class="status-badge <?php echo $category['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">
                                                Order: <?php echo $category['display_order']; ?>
                                            </span>
                                            <div class="color-preview" 
                                                 style="background: <?php echo $category['color_code']; ?>"
                                                 title="Color: <?php echo $category['color_code']; ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="btn-group mt-3 w-100">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editCategoryModal"
                                                    onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
    </div>
    
    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" name="id" id="editCategoryId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editCategoryName" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="editCategoryName" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editCategoryDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editCategoryDescription" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editDisplayOrder" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="editDisplayOrder" name="display_order" min="0">
                            </div>
                            <div class="col-md-6">
                                <label for="editColorCode" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="editColorCode" name="color_code">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editIconClass" class="form-label">Icon Class</label>
                            <input type="text" class="form-control" id="editIconClass" name="icon_class">
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="editIsActive" name="is_active" checked>
                            <label class="form-check-label" for="editIsActive">Category Active</label>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function editCategory(category) {
            document.getElementById('editCategoryId').value = category.id;
            document.getElementById('editCategoryName').value = category.name;
            document.getElementById('editCategoryDescription').value = category.description || '';
            document.getElementById('editDisplayOrder').value = category.display_order;
            document.getElementById('editColorCode').value = category.color_code;
            document.getElementById('editIconClass').value = category.icon_class || '';
            document.getElementById('editIsActive').checked = category.is_active == 1;
            
            // Get or create modal instance (don't create new one each time)
            const modalElement = document.getElementById('editCategoryModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        }
        
        function deleteCategory(id, name) {
            Swal.fire({
                title: 'Delete Category?',
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
                    actionInput.value = 'delete_category';
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

        function copyToClipboard(text, element) {
    navigator.clipboard.writeText(text).then(function() {
        // Visual feedback
        const originalBg = element.style.backgroundColor;
        element.style.backgroundColor = '#d4edda';
        
        // Show "Copied!" tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'copied-tooltip';
        tooltip.textContent = 'Copied!';
        element.style.position = 'relative';
        element.appendChild(tooltip);
        
        setTimeout(function() {
            element.style.backgroundColor = originalBg;
            tooltip.remove();
        }, 1000);
    }).catch(function(err) {
        alert('Failed to copy to clipboard');
    });
}
    </script>
</body>
</html>