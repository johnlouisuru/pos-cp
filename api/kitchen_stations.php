<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getStation();
        break;
    case 'create':
        createStation();
        break;
    case 'update':
        updateStation();
        break;
    case 'toggle_status':
        toggleStatus();
        break;
    case 'get_products':
        getStationProducts();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getStation() {
    global $pdo;
    
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Station ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM kitchen_stations WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($station) {
        echo json_encode(['success' => true, 'data' => $station]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Station not found']);
    }
}

function createStation() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO kitchen_stations (name, description, color_code, display_order, is_active) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['name'],
            $_POST['description'] ?? null,
            $_POST['color_code'],
            $_POST['display_order'] ?? 0,
            isset($_POST['is_active']) ? 1 : 0
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Station created successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating station: ' . $e->getMessage()]);
    }
}

function updateStation() {
    global $pdo;
    
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Station ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE kitchen_stations 
            SET name = ?, description = ?, color_code = ?, display_order = ?, is_active = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['name'],
            $_POST['description'] ?? null,
            $_POST['color_code'],
            $_POST['display_order'] ?? 0,
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Station updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating station: ' . $e->getMessage()]);
    }
}

function toggleStatus() {
    global $pdo;
    
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Station ID required']);
        return;
    }
    
    $newStatus = $_POST['is_active'];
    
    try {
        $stmt = $pdo->prepare("UPDATE kitchen_stations SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $_POST['id']]);
        
        $action = $newStatus ? 'activated' : 'deactivated';
        echo json_encode(['success' => true, 'message' => "Station {$action} successfully"]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating station status']);
    }
}

function getStationProducts() {
    global $pdo;
    
    if (!isset($_GET['station_id'])) {
        echo json_encode(['success' => false, 'message' => 'Station ID required']);
        return;
    }
    
    // Get station color
    $stmt = $pdo->prepare("SELECT color_code FROM kitchen_stations WHERE id = ?");
    $stmt->execute([$_GET['station_id']]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get products
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.station_id = ? 
        ORDER BY p.name
    ");
    $stmt->execute([$_GET['station_id']]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'station_color' => $station['color_code']
    ]);
}