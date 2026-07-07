<?php
// get_user_stats.php - Get detailed statistics for a user
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
        
        // Get user progress with calculated stats
        $stmt = $pdo->prepare("
            SELECT 
                *,
                CASE 
                    WHEN overall_progress >= 100 THEN 'Completed'
                    WHEN overall_progress >= 75 THEN 'Near Completion'
                    WHEN overall_progress >= 50 THEN 'Halfway'
                    WHEN overall_progress >= 25 THEN 'Getting Started'
                    ELSE 'Just Started'
                END as progress_status,
                CASE 
                    WHEN playtime_hours >= 100 THEN 'Veteran'
                    WHEN playtime_hours >= 50 THEN 'Experienced'
                    WHEN playtime_hours >= 20 THEN 'Regular'
                    WHEN playtime_hours >= 5 THEN 'Newcomer'
                    ELSE 'Beginner'
                END as player_level,
                ROUND((overall_progress + story_progress + chapter_progress + quest_progress + item_progress + achievement_progress) / 6, 2) as average_progress
            FROM user_progress 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats) {
            // Calculate additional metrics
            $stats['completion_rate'] = round(($stats['completed_chapters'] + $stats['completed_quests']) / max(1, $stats['playtime_hours']) * 10, 2);
            $stats['efficiency_score'] = round($stats['overall_progress'] / max(1, $stats['playtime_hours']) * 10, 2);
            
            echo json_encode([
                'success' => true, 
                'stats' => $stats,
                'message' => 'User statistics retrieved successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'error' => 'No statistics found for this user'
            ]);
        }
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>