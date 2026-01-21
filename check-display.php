<?php
// check-display.php
require_once 'includes/db.php';

echo "<h2>Display System Status Check</h2>";

// Check database connection
try {
    $pdo->query("SELECT 1");
    echo "✅ Database connection: OK<br>";
} catch (Exception $e) {
    echo "❌ Database connection: FAILED - " . $e->getMessage() . "<br>";
    exit;
}

// Check if tables exist
$tables = ['orders', 'order_display_status', 'public_display_settings'];
foreach ($tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "✅ Table '$table': EXISTS<br>";
    } catch (Exception $e) {
        echo "❌ Table '$table': MISSING<br>";
    }
}

// Check data
echo "<h3>Current Data:</h3>";
$orders = $pdo->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
$display = $pdo->query("SELECT COUNT(*) as count FROM order_display_status")->fetch()['count'];

echo "Total orders: $orders<br>";
echo "Display entries: $display<br>";

if ($display > 0) {
    echo "<p style='color: green;'>✅ Display has data to show!</p>";
    
    $sql = "SELECT * FROM order_display_status ORDER BY status";
    $results = $pdo->query($sql)->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Order #</th><th>Display Name</th><th>Status</th></tr>";
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>{$row['order_number']}</td>";
        echo "<td>{$row['display_name']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><a href='display/' target='_blank'>👉 View Display Now</a></p>";
} else {
    echo "<p style='color: orange;'>⚠️ No display entries found. Need test data.</p>";
    echo "<p><a href='test-clean.php'>👉 Generate Test Data</a></p>";
}
?>