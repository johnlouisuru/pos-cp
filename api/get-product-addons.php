<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? 0;

try {
    // Get ONLY product-specific addons (not global)
    $sql = "
        SELECT a.* 
        FROM addons a 
        INNER JOIN product_addons pa ON a.id = pa.addon_id
        WHERE pa.product_id = ? AND a.is_available = 1 AND a.is_global = 0
        ORDER BY a.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'addons' => $addons
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>