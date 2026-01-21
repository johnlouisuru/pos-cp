<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? null;
    $price = $data['price'] ?? 0;
    $is_global = $data['is_global'] ?? 0;
    $max_quantity = $data['max_quantity'] ?? 1;
    $is_available = $data['is_available'] ?? 1;
    
    if (empty($name) || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name and price are required']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO addons (name, description, price, is_global, max_quantity, is_available)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$name, $description, $price, $is_global, $max_quantity, $is_available]);
        
        $addon_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Addon created successfully',
            'addon_id' => $addon_id
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>