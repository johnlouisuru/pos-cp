<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? 0;

try {
    // Get global addons and product-specific addons
    $sql = "
        SELECT DISTINCT a.* 
        FROM addons a 
        LEFT JOIN product_addons pa ON a.id = pa.addon_id
        WHERE a.is_available = 1 
        AND (a.is_global = 1 OR pa.product_id = ?)
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