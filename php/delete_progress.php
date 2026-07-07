<?php
// delete_progress.php - Delete user progress (optional utility)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$password = 'Filibustero_capstone08';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_id = $_POST['user_id'] ?? '';
        $confirm_delete = $_POST['confirm_delete'] ?? '';
        
        if (empty($user_id)) {
            echo json_encode(['success' => false, 'error' => 'User ID is required']);
            exit;
        }
        
        if ($confirm_delete !== 'YES_DELETE') {
            echo json_encode(['success' => false, 'error' => 'Delete confirmation required']);
            exit;
        }
        
        // Delete user progress
        $stmt = $pdo->prepare("DELETE FROM user_progress WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Progress deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'error' => 'No progress found for this user'
            ]);
        }
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>