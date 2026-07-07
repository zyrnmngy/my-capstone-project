<?php
// Database configuration for Filibustero RPG Maker MZ System
// File: config/database.php

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'filibustero_game');
define('DB_USER', 'root'); // Change this to your MySQL username
define('DB_PASS', ''); // Change this to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// Session settings
define('SESSION_DURATION', 3600); // 1 hour in seconds
define('TEMP_PASSWORD_DURATION', 3600); // 1 hour for temporary passwords

// Security settings
define('PASSWORD_MIN_LENGTH', 6);
define('USERNAME_MIN_LENGTH', 3);

// Error reporting (set to false in production)
define('DEBUG_MODE', true);

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    private $pdo;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            if (DEBUG_MODE) {
                error_log("Database connection successful");
            }
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Database connection failed: " . $e->getMessage());
            }
            throw new Exception("Database connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Query error: " . $e->getMessage());
                error_log("SQL: " . $sql);
                error_log("Params: " . json_encode($params));
            }
            throw new Exception("Database query failed");
        }
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollback();
    }
}

// Utility functions
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

function generateTempPassword($length = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $temp_password = '';
    for ($i = 0; $i < $length; $i++) {
        $temp_password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $temp_password;
}

function validateIdNumber($id_number) {
    // Format: XXL-XXXX (e.g., 23L-4567)
    return preg_match('/^\d{2}[A-Z]-\d{4}$/', $id_number);
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function logDebug($message, $data = null) {
    if (DEBUG_MODE) {
        $log_message = "[" . date('Y-m-d H:i:s') . "] " . $message;
        if ($data !== null) {
            $log_message .= " | Data: " . json_encode($data);
        }
        error_log($log_message);
    }
}

// CORS headers for RPG Maker MZ
function setCorsHeaders() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Response helper functions
function sendJsonResponse($success, $data = null, $error = null) {
    setCorsHeaders();
    
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

function sendErrorResponse($error_message, $http_code = 400) {
    http_response_code($http_code);
    sendJsonResponse(false, null, $error_message);
}

function sendSuccessResponse($data = null) {
    sendJsonResponse(true, $data);
}
?>