<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['addon_id'])) {
    $addon_id = (int)$_GET['addon_id'];
    
    $stmt = $pdo->prepare("
        SELECT product_id 
        FROM product_addons 
        WHERE addon_id = ?
    ");
    $stmt->execute([$addon_id]);
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No addon ID provided'
    ]);
}