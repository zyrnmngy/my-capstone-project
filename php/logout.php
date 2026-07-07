<?php
require_once 'config.php';

$conn = getDBConnection();

// Handle logout request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : '';
    
    if ($action === 'logout') {
        $token = isset($_POST['token']) ? sanitizeInput($_POST['token']) : '';
        
        if (empty($token)) {
            echo json_encode(['success' => false, 'error' => 'Token is required']);
            exit;
        }
        
        // Delete session from database
        $stmt = $conn->prepare("DELETE FROM sessions WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
?>