<?php
// session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$productId = $data['product_id'] ?? 0;

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'No product selected']);
    exit;
}

if (isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
}

// Calculate cart totals
$cartCount = 0;
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
    $cartTotal += ($item['price'] * $item['quantity']);
}

echo json_encode([
    'success' => true,
    'cart' => $_SESSION['cart'],
    'cartCount' => $cartCount,
    'cartTotal' => $cartTotal
]);
?>