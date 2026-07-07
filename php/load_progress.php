<?php
// load_progress.php
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
        
        if (empty($user_id)) {
            echo json_encode(['success' => false, 'error' => 'User ID is required']);
            exit;
        }
        
        // Get user progress
        $stmt = $pdo->prepare("
            SELECT * FROM user_progress 
            WHERE user_id = ? 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            echo json_encode([
                'success' => true, 
                'progress' => $progress,
                'message' => 'Progress loaded successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'error' => 'No progress data found for this user'
            ]);
        }
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>