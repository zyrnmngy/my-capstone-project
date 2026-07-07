<?php
header('Content-Type: application/json');
require_once 'config.php'; // Include your database configuration

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to validate the ID format (XXL-XXXX)
function validateIdFormat($idNumber) {
    return preg_match('/^\d{2}L-\d{4,5}$/', $idNumber);
}

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(stripcslashes(trim($data)));
}

// Main response array
$response = [
    'success' => false,
    'message' => '',
    'errors' => []
];

try {
    // Get the raw POST data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate JSON data
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data received");
    }

    // Check required fields
    $requiredFields = ['idNumber', 'currentUsername', 'temporaryPassword'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $response['errors'][] = "The $field field is required";
        }
    }

    // Validate ID format if provided
    if (!empty($data['idNumber']) && !validateIdFormat($data['idNumber'])) {
        $response['errors'][] = "Invalid ID format. Please use XXL-XXXX format";
    }

    // If there are errors, return them
    if (!empty($response['errors'])) {
        $response['message'] = "Validation errors occurred";
        echo json_encode($response);
        exit;
    }

    // Sanitize inputs
    $idNumber = sanitizeInput($data['idNumber']);
    $currentUsername = sanitizeInput($data['currentUsername']);
    $temporaryPassword = sanitizeInput($data['temporaryPassword']);
    $newUsername = isset($data['newUsername']) ? sanitizeInput($data['newUsername']) : null;
    $newPassword = isset($data['newPassword']) ? sanitizeInput($data['newPassword']) : null;

    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Prepare SQL to find user by ID and temporary password
    $stmt = $conn->prepare("SELECT * FROM users WHERE id_number = ? AND username = ? AND temporary_password = ?");
    $stmt->bind_param("sss", $idNumber, $currentUsername, $temporaryPassword);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $response['message'] = "Invalid credentials. Please check your ID Number, current username, and temporary password.";
        echo json_encode($response);
        exit;
    }

    $user = $result->fetch_assoc();

    // Prepare update statement - FIXED LOGIC
    $updateFields = [];
    $params = [];
    $types = "";

    // Handle username update
    if (!empty($newUsername)) {
        $updateFields[] = "username = ?";
        $params[] = $newUsername;
        $types .= "s";
    }

    // Handle password update
    if (!empty($newPassword)) {
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateFields[] = "password = ?";
        $params[] = $hashedPassword;
        $types .= "s";
    }

    // Always clear temporary password after successful validation
    $updateFields[] = "temporary_password = NULL";

    // Only proceed if there are fields to update
    if (!empty($updateFields)) {
        $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $params[] = $user['id'];
        $types .= "i";

        $updateStmt = $conn->prepare($sql);
        
        if (!$updateStmt) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }
        
        // Dynamically bind parameters
        $updateStmt->bind_param($types, ...$params);
        
        if ($updateStmt->execute()) {
            if ($updateStmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = "Account updated successfully!";
                
                // If username was changed, return the new one
                if (!empty($newUsername)) {
                    $response['newUsername'] = $newUsername;
                }
            } else {
                $response['message'] = "No changes were made to your account.";
            }
        } else {
            throw new Exception("Failed to update account: " . $updateStmt->error);
        }
    } else {
        $response['message'] = "No changes were made to your account.";
    }

    // Close connections
    $stmt->close();
    if (isset($updateStmt)) $updateStmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Account Update Error: " . $e->getMessage());
}

echo json_encode($response);
?>