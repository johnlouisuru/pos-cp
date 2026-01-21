<?php
require_once '../config/database.php';

header('Content-Type: application/json');


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
        getProduct();
        break;
    case 'create':
        createProduct();
        break;
    case 'update':
        updateProduct();
        break;
    case 'delete':
        deleteProduct();
        break;
    case 'update_stock':
        updateStock();
        break;
    case 'update_addons_flag':
        updateAddonsFlag();
    break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getProduct() {
    global $pdo;
    
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    $id = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM products WHERE id = ?
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            echo json_encode(['success' => true, 'data' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function createProduct() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Collect form data
        $name = $_POST['name'] ?? '';
        $sku = $_POST['sku'] ?? null;
        $category_id = $_POST['category_id'] ?? null;
        $station_id = $_POST['station_id'] ?? null;
        $description = $_POST['description'] ?? null;
        $price = $_POST['price'] ?? 0;
        $cost = $_POST['cost'] ?? null;
        $stock = $_POST['stock'] ?? 0;
        $min_stock = $_POST['min_stock'] ?? 5;
        $calories = $_POST['calories'] ?? null;
        $preparation_time = $_POST['preparation_time'] ?? null;
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        $has_addons = isset($_POST['has_addons']) ? 1 : 0;
        $display_order = $_POST['display_order'] ?? 0;
        
        // Validate required fields
        if (empty($name) || empty($category_id) || empty($price)) {
            throw new Exception('Required fields are missing');
        }
        
        // Handle image upload
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;
            
            // Check file type
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($imageFileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $image_url = 'uploads/products/' . $fileName;
                }
            }
        }
        
        // Insert product
        $stmt = $pdo->prepare("
            INSERT INTO products (
                category_id, sku, name, description, price, cost, stock, min_stock,
                image_url, is_available, is_popular, has_addons, preparation_time,
                calories, display_order, station_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $category_id, $sku, $name, $description, $price, $cost, $stock, $min_stock,
            $image_url, $is_available, $is_popular, $has_addons, $preparation_time,
            $calories, $display_order, $station_id
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        // Log the stock addition if any
        if ($stock > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO inventory_logs (
                    product_id, change_type, quantity_change, new_stock_level,
                    notes, created_at
                ) VALUES (?, 'restock', ?, ?, 'Initial stock', NOW())
            ");
            $stmt->execute([$product_id, $stock, $stock]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product created successfully',
            'product_id' => $product_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateProduct() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $id = $_POST['id'] ?? 0;
        
        if (!$id) {
            throw new Exception('Product ID required');
        }
        
        // Get current product data
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        
        if (!$current) {
            throw new Exception('Product not found');
        }
        
        // Collect form data
        $name = $_POST['name'] ?? $current['name'];
        $sku = $_POST['sku'] ?? $current['sku'];
        $category_id = $_POST['category_id'] ?? $current['category_id'];
        $station_id = $_POST['station_id'] ?? $current['station_id'];
        $description = $_POST['description'] ?? $current['description'];
        $price = $_POST['price'] ?? $current['price'];
        $cost = $_POST['cost'] ?? $current['cost'];
        $stock = $_POST['stock'] ?? $current['stock'];
        $min_stock = $_POST['min_stock'] ?? $current['min_stock'];
        $calories = $_POST['calories'] ?? $current['calories'];
        $preparation_time = $_POST['preparation_time'] ?? $current['preparation_time'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        $has_addons = isset($_POST['has_addons']) ? 1 : 0;
        $display_order = $_POST['display_order'] ?? $current['display_order'];
        
        // Handle image upload
        $image_url = $current['image_url'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;
            
            // Check file type
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($imageFileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    // Delete old image if exists
                    if ($current['image_url'] && file_exists('../' . $current['image_url'])) {
                        unlink('../' . $current['image_url']);
                    }
                    $image_url = 'uploads/products/' . $fileName;
                }
            }
        }
        
        // Update product
        $stmt = $pdo->prepare("
            UPDATE products SET
                category_id = ?, sku = ?, name = ?, description = ?, price = ?, cost = ?,
                stock = ?, min_stock = ?, image_url = ?, is_available = ?, is_popular = ?,
                has_addons = ?, preparation_time = ?, calories = ?, display_order = ?,
                station_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $category_id, $sku, $name, $description, $price, $cost,
            $stock, $min_stock, $image_url, $is_available, $is_popular,
            $has_addons, $preparation_time, $calories, $display_order,
            $station_id, $id
        ]);
        
        // Log stock adjustment if changed
        if ($stock != $current['stock']) {
            $change = $stock - $current['stock'];
            $stmt = $pdo->prepare("
                INSERT INTO inventory_logs (
                    product_id, change_type, quantity_change, new_stock_level,
                    notes, created_at
                ) VALUES (?, 'adjustment', ?, ?, 'Manual adjustment', NOW())
            ");
            $stmt->execute([$id, $change, $stock]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteProduct() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    try {
        // Check if product has orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM order_items WHERE product_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['order_count'] > 0) {
            // Instead of deleting, mark as unavailable
            $pdo->prepare("UPDATE products SET is_available = 0 WHERE id = ?")->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Product has existing orders. Marked as unavailable instead of deleting.'
            ]);
        } else {
            // Get image path before deletion
            $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            // Delete product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            
            // Delete image file if exists
            if ($product['image_url'] && file_exists('../' . $product['image_url'])) {
                unlink('../' . $product['image_url']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateStock() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $product_id = $data['product_id'] ?? 0;
    $action = $data['action'] ?? '';
    $quantity = intval($data['quantity'] ?? 0);
    $notes = $data['notes'] ?? '';
    
    if (!$product_id || !$action || $quantity <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get current stock
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $current = $stmt->fetch();
        
        if (!$current) {
            throw new Exception('Product not found');
        }
        
        $current_stock = $current['stock'];
        $new_stock = $current_stock;
        
        // Determine quantity change based on action
        switch ($action) {
            case 'restock':
                $quantity_change = $quantity;
                $new_stock = $current_stock + $quantity;
                break;
            case 'adjustment':
                // Can be positive or negative adjustment
                $quantity_change = $quantity;
                $new_stock = $current_stock + $quantity;
                break;
            case 'waste':
                $quantity_change = -$quantity;
                $new_stock = $current_stock - $quantity;
                break;
            case 'set':
                $quantity_change = $quantity - $current_stock;
                $new_stock = $quantity;
                break;
            default:
                throw new Exception('Invalid action');
        }
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        // Log the stock change
        $stmt = $pdo->prepare("
            INSERT INTO inventory_logs (
                product_id, change_type, quantity_change, new_stock_level,
                notes, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $product_id,
            $action === 'set' ? 'adjustment' : $action,
            $quantity_change,
            $new_stock,
            $notes ?: 'Manual stock update'
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock updated successfully',
            'new_stock' => $new_stock
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateAddonsFlag() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? 0;
    $has_addons = $data['has_addons'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET has_addons = ? WHERE id = ?");
        $stmt->execute([$has_addons, $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Addons flag updated'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>