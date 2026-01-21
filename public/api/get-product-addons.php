<?php
require_once '../../includes/db.php';

header('Content-Type: application/json');

$productId = $_GET['product_id'] ?? 0;

// Get product info
$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

// Get addons for this product - FIXED SQL
$sql = "
    SELECT a.* 
    FROM addons a
    JOIN product_addons pa ON a.id = pa.addon_id
    WHERE pa.product_id = ? AND a.is_available = 1
    
    UNION ALL
    
    SELECT a.* 
    FROM addons a
    WHERE a.is_global = 1 AND a.is_available = 1
    
    ORDER BY created_at
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$productId]);
$addons = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'product' => $product,
    'addons' => $addons
]);
?>