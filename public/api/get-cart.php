<?php
// session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Calculate cart totals
$cartCount = 0;
$cartTotal = 0;

foreach ($_SESSION['cart'] as $productId => $item) {
    $cartCount += $item['quantity'];
    $cartTotal += ($item['price'] * $item['quantity']);
    
    // Add addons price
    if (!empty($item['addons'])) {
        foreach ($item['addons'] as $addon) {
            if (isset($addon['price'])) {
                $cartTotal += ($addon['price'] * $addon['quantity']);
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'cart' => $_SESSION['cart'],
    'cartCount' => $cartCount,
    'cartTotal' => $cartTotal
]);
?>