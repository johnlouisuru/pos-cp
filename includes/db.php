<?php
// includes/db.php
session_start();
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'posv1';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Timezone
date_default_timezone_set('Asia/Manila');
?>