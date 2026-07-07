<?php
// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Set headers for actual requests
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);

// Database configuration
require_once 'config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Log the request
error_log("Save progress request: " . print_r($input, true));

// Validate required fields
$required_fields = ['player_id', 'coins', 'score', 'current_stage', 'completed_quests'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        echo json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]);
        exit;
    }
}

try {
    // Use PDO for better security and error handling
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Prepare data - use proper types
    $player_id = intval($input['player_id']);
    $coins = intval($input['coins']);
    $score = intval($input['score']);
    $current_stage = intval($input['current_stage']);
    $completed_quests = intval($input['completed_quests']);
    $total_quests = intval($input['total_quests'] ?? 25);
    $progress_percentage = floatval($input['progress_percentage'] ?? 0);
    $last_map_id = intval($input['last_map_id'] ?? 1);
    $play_time = intval($input['play_time'] ?? 0);
    $save_count = intval($input['save_count'] ?? 0);
    $correct_answers = intval($input['correct_answers'] ?? 0);
    $total_questions_answered = intval($input['total_questions_answered'] ?? 0);
    $game_completed = intval($input['game_completed'] ?? 0);
    $completion_time = isset($input['completion_time']) ? intval($input['completion_time']) : null;
    
    // Use INSERT ... ON DUPLICATE KEY UPDATE to prevent duplicates
    // This requires a UNIQUE constraint on player_id
    $sql = "INSERT INTO game_progress 
            (player_id, coins, score, current_stage, completed_quests, 
             total_quests, progress_percentage, last_map_id, play_time, 
             save_count, correct_answers, total_questions_answered, 
             game_completed, completion_time, last_played, created_at, last_updated) 
            VALUES 
            (:player_id, :coins, :score, :current_stage, :completed_quests,
             :total_quests, :progress_percentage, :last_map_id, :play_time,
             :save_count, :correct_answers, :total_questions_answered,
             :game_completed, :completion_time, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                coins = :coins,
                score = :score,
                current_stage = :current_stage,
                completed_quests = :completed_quests,
                total_quests = :total_quests,
                progress_percentage = :progress_percentage,
                last_map_id = :last_map_id,
                play_time = :play_time,
                save_count = :save_count,
                correct_answers = :correct_answers,
                total_questions_answered = :total_questions_answered,
                game_completed = :game_completed,
                completion_time = IF(:completion_time IS NOT NULL, :completion_time, completion_time),
                last_played = NOW(),
                last_updated = NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
    $stmt->bindParam(':coins', $coins, PDO::PARAM_INT);
    $stmt->bindParam(':score', $score, PDO::PARAM_INT);
    $stmt->bindParam(':current_stage', $current_stage, PDO::PARAM_INT);
    $stmt->bindParam(':completed_quests', $completed_quests, PDO::PARAM_INT);
    $stmt->bindParam(':total_quests', $total_quests, PDO::PARAM_INT);
    $stmt->bindParam(':progress_percentage', $progress_percentage, PDO::PARAM_STR);
    $stmt->bindParam(':last_map_id', $last_map_id, PDO::PARAM_INT);
    $stmt->bindParam(':play_time', $play_time, PDO::PARAM_INT);
    $stmt->bindParam(':save_count', $save_count, PDO::PARAM_INT);
    $stmt->bindParam(':correct_answers', $correct_answers, PDO::PARAM_INT);
    $stmt->bindParam(':total_questions_answered', $total_questions_answered, PDO::PARAM_INT);
    $stmt->bindParam(':game_completed', $game_completed, PDO::PARAM_INT);
    $stmt->bindParam(':completion_time', $completion_time, PDO::PARAM_INT);
    
    $stmt->execute();
    
    $action = $stmt->rowCount() > 0 ? ($pdo->lastInsertId() ? 'created' : 'updated') : 'no_change';
    
    // Log the save action (optional - only if progress_logs table exists)
    try {
        $log_sql = "INSERT INTO progress_logs 
                    (player_id, action, coins, score, current_stage, 
                     completed_quests, progress_percentage, timestamp) 
                    VALUES 
                    (:player_id, 'save', :coins, :score, :current_stage,
                     :completed_quests, :progress_percentage, NOW())";
        
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':coins', $coins, PDO::PARAM_INT);
        $log_stmt->bindParam(':score', $score, PDO::PARAM_INT);
        $log_stmt->bindParam(':current_stage', $current_stage, PDO::PARAM_INT);
        $log_stmt->bindParam(':completed_quests', $completed_quests, PDO::PARAM_INT);
        $log_stmt->bindParam(':progress_percentage', $progress_percentage, PDO::PARAM_STR);
        $log_stmt->execute();
    } catch (PDOException $e) {
        // Ignore logging errors
        error_log("Progress log failed: " . $e->getMessage());
    }
    
    error_log("Progress saved successfully for player_id: $player_id, action: $action");
    
    echo json_encode([
        'success' => true,
        'message' => "Progress $action successfully",
        'player_id' => $player_id,
        'action' => $action
    ]);
    
} catch (PDOException $e) {
    error_log("Save progress error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Save progress error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>