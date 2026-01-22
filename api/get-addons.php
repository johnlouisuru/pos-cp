<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? 0;

error_log("Fetching addons for product_id: $product_id");

try {
    // Debug: Log what we're querying
    error_log("Querying addons for product: $product_id");
    
    // Get global addons and product-specific addons
    $sql = "
        SELECT DISTINCT a.* 
        FROM addons a 
        LEFT JOIN product_addons pa ON a.id = pa.addon_id
        WHERE a.is_available = 1 
        AND (a.is_global = 1 OR pa.product_id = ?)
        ORDER BY a.name
    ";
    
    error_log("SQL: $sql");
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($addons) . " addons");
    
    // Debug output
    echo json_encode([
        'success' => true,
        'addons' => $addons,
        'debug' => [
            'product_id' => $product_id,
            'addon_count' => count($addons),
            'addons' => $addons
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => ['product_id' => $product_id]
    ]);
}
?>