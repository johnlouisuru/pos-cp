<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$category_id = $_GET['category_id'] ?? null;
$search = $_GET['search'] ?? '';

try {
    // In get-products.php, update the SQL query to include has_addons:
$sql = "SELECT p.*, c.name as category_name, k.name as station_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN kitchen_stations k ON p.station_id = k.id 
        WHERE p.is_available = 1";
    
    $params = [];
    
    if ($category_id) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
    }
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY p.display_order, p.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>