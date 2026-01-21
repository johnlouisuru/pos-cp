<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($categories);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>