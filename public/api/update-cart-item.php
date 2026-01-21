<?php
// session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$productId = $data['product_id'] ?? 0;
$change = $data['change'] ?? 0;

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'No product selected']);
    exit;
}

if (!isset($_SESSION['cart'][$productId])) {
    echo json_encode(['success' => false, 'message' => 'Item not in cart']);
    exit;
}

// Update quantity
$newQuantity = $_SESSION['cart'][$productId]['quantity'] + $change;
$newQuantity = max(0, $newQuantity);

if ($newQuantity > 0) {
    $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
} else {
    // Remove if quantity is 0
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