<?php
// Database configuration for Filibustero project
// This file should be placed in your PHP directory (e.g., C:\xampp\htdocs\php\)

// Database settings
define('DB_HOST', 'localhost');
define('DB_USER', 'u769346877_filibustero'); // Change to your database username
define('DB_PASS', 'Filibustero_capstone08'); // Change to your database password
define('DB_NAME', 'u769346877_filibustero_db');
define('DB_CHARSET', 'utf8mb4');

// Create PDO connection function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        
        // Set PDO options
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

// CORS headers for API access
function setCORSHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// JSON response helper
function jsonResponse($success, $data = null, $error = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = ['success' => $success];
    
    if ($success && $data !== null) {
        if (is_array($data)) {
            $response = array_merge($response, $data);
        } else {
            $response['data'] = $data;
        }
    }
    
    if (!$success && $error !== null) {
        $response['error'] = $error;
    }
    
    echo json_encode($response);
    exit();
}

// Error logging
function logError($message, $context = []) {
    $logFile = __DIR__ . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logMessage = "[$timestamp] ERROR: $message $contextStr" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Debug logging (only in development)
function logDebug($message, $context = []) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $logFile = __DIR__ . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logMessage = "[$timestamp] DEBUG: $message $contextStr" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// Environment settings
define('DEBUG_MODE', true); // Set to false in production
define('API_VERSION', '1.0.0');

// Session settings for web dashboard
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
session_start();
?>