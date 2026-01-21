<?php
// display/api/get-next-order.php
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Get the next order number that will be assigned
$stmt = $pdo->query("
    SELECT MAX(CAST(SUBSTRING(order_number, -3) AS UNSIGNED)) as last_num 
    FROM orders 
    WHERE order_number LIKE 'ONL-%' 
       OR order_number LIKE 'WALK-%'
");
$result = $stmt->fetch();

$lastNum = $result['last_num'] ?? 0;
$nextNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
$nextNumber = "ONL-" . date('Y') . "-" . $nextNum;

echo json_encode([
    'success' => true,
    'nextNumber' => $nextNumber
]);
?>