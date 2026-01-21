<?php

require_once '../config/database.php';

header('Content-Type: application/json');

// session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$product_id = $data['product_id'] ?? 0;
$addons = $data['addons'] ?? [];
$remove_only = $data['remove_only'] ?? 0;

// Add this at the beginning of your API file
error_log("API called: " . json_encode($data));
error_log("Product ID: " . $product_id);
error_log("Addons array: " . json_encode($addons));
error_log("Remove only: " . $remove_only);

if (!$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // If we're only removing one addon
    if ($remove_only) {
        $stmt = $pdo->prepare("
            DELETE pa FROM product_addons pa
            INNER JOIN addons a ON pa.addon_id = a.id
            WHERE pa.product_id = ? AND pa.addon_id = ? AND a.is_global = 0
        ");
        $stmt->execute([$product_id, $remove_only]);
        
        // Check if product still has any non-global addons
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as addon_count 
            FROM product_addons pa
            INNER JOIN addons a ON pa.addon_id = a.id
            WHERE pa.product_id = ? AND a.is_global = 0
        ");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch();
        
        // Update product's has_addons flag
        $has_addons = $result['addon_count'] > 0 ? 1 : 0;
        $pdo->prepare("UPDATE products SET has_addons = ? WHERE id = ?")
            ->execute([$has_addons, $product_id]);
    } else {
        // Original logic for batch update
        // Remove all existing product-specific addon associations for this product
        $stmt = $pdo->prepare("
            DELETE pa FROM product_addons pa
            INNER JOIN addons a ON pa.addon_id = a.id
            WHERE pa.product_id = ? AND a.is_global = 0
        ");
        $stmt->execute([$product_id]);
        
        // Add new associations
        if (!empty($addons)) {
            foreach ($addons as $addon_id) {
                // Check if addon is not global
                $stmt = $pdo->prepare("SELECT is_global FROM addons WHERE id = ?");
                $stmt->execute([$addon_id]);
                $addon = $stmt->fetch();
                
                if ($addon && !$addon['is_global']) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_addons (product_id, addon_id, display_order) 
                        VALUES (?, ?, 0)
                        ON DUPLICATE KEY UPDATE product_id = product_id
                    ");
                    $stmt->execute([$product_id, $addon_id]);
                }
            }
        }
        
        // Update product's has_addons flag
        $has_addons = !empty($addons) ? 1 : 0;
        $pdo->prepare("UPDATE products SET has_addons = ? WHERE id = ?")
            ->execute([$has_addons, $product_id]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Product addons updated successfully',
        'has_addons' => $has_addons ?? (!empty($addons) ? 1 : 0)
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>