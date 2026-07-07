<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u769346877_filibustero'); // Change to your database username
define('DB_PASS', 'Filibustero_capstone08'); // Change to your database password
define('DB_NAME', 'u769346877_filibustero_db');

// JWT Secret (for token generation)
define('JWT_SECRET', 'your_secret_key_here');

// Create database connection with error handling
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // Return JSON error for API consistency
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'error' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }

    // Set charset to handle special characters
    $conn->set_charset("utf8mb4");

    return $conn;
}

// Helper function to validate ID number format
function validateIdNumber($idNumber) {
    return preg_match('/^\d{2}L-\d{4,5}$/', $idNumber);
}

// Helper function to validate input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Helper function to validate required fields
function validateRequiredFields($fields, $data) {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "The field '$field' is required";
        }
    }
    return $errors;
}
?>