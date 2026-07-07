<?php
// Database configuration
$host = 'localhost';
$dbname = 'u769346877_filibustero_db'; // Replace with your actual database name
$username = 'u769346877_filibustero'; // Replace with your database username
$password = 'Filibustero_capstone08'; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Admin credentials (since you don't have an admin table yet)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // Change this to a secure password

// Start session
session_start();
?>