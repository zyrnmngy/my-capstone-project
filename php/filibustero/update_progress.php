<?php
// update_progress.php - Localhost version
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Allow preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration for localhost
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$password = 'Filibustero_capstone08'; // Default XAMPP/WAMP password is empty

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get POST data
    $player_id = $_POST['player_id'] ?? null;
    $coins = $_POST['coins'] ?? 0;
    $score = $_POST['score'] ?? 0;
    $stage = $_POST['stage'] ?? 1;
    $quests = $_POST['quests'] ?? 0;
    $progress = $_POST['progress'] ?? 0;
    
    // Log received data for debugging
    error_log("Received data: player_id=$player_id, coins=$coins, score=$score, stage=$stage, quests=$quests, progress=$progress");
    
    if (!$player_id) {
        throw new Exception('Player ID is required');
    }
    
    // Calculate progress percentage (as a backup)
    $calculated_progress = min(floor(($quests / 25) * 100), 100);
    $final_progress = $progress > 0 ? $progress : $calculated_progress;
    
    // Insert or update progress
    $sql = "INSERT INTO game_progress 
            (player_id, coins, score, current_stage, completed_quests, progress_percentage, last_played)
            VALUES 
            (:player_id, :coins, :score, :stage, :quests, :progress, NOW())
            ON DUPLICATE KEY UPDATE
            coins = :coins_u,
            score = :score_u,
            current_stage = :stage_u,
            completed_quests = :quests_u,
            progress_percentage = :progress_u,
            last_played = NOW(),
            last_updated = NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
    $stmt->bindParam(':coins', $coins, PDO::PARAM_INT);
    $stmt->bindParam(':score', $score, PDO::PARAM_INT);
    $stmt->bindParam(':stage', $stage, PDO::PARAM_INT);
    $stmt->bindParam(':quests', $quests, PDO::PARAM_INT);
    $stmt->bindParam(':progress', $final_progress, PDO::PARAM_INT);
    $stmt->bindParam(':coins_u', $coins, PDO::PARAM_INT);
    $stmt->bindParam(':score_u', $score, PDO::PARAM_INT);
    $stmt->bindParam(':stage_u', $stage, PDO::PARAM_INT);
    $stmt->bindParam(':quests_u', $quests, PDO::PARAM_INT);
    $stmt->bindParam(':progress_u', $final_progress, PDO::PARAM_INT);
    
    $stmt->execute();
    
    // Log the update
    $log_sql = "INSERT INTO progress_logs 
                (player_id, action, coins, score, current_stage, completed_quests, progress_percentage)
                VALUES 
                (:player_id, 'update', :coins, :score, :stage, :quests, :progress)";
    
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
    $log_stmt->bindParam(':coins', $coins, PDO::PARAM_INT);
    $log_stmt->bindParam(':score', $score, PDO::PARAM_INT);
    $log_stmt->bindParam(':stage', $stage, PDO::PARAM_INT);
    $log_stmt->bindParam(':quests', $quests, PDO::PARAM_INT);
    $log_stmt->bindParam(':progress', $final_progress, PDO::PARAM_INT);
    $log_stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Progress updated successfully',
        'data' => [
            'player_id' => $player_id,
            'coins' => $coins,
            'score' => $score,
            'stage' => $stage,
            'quests' => $quests,
            'progress' => $final_progress
        ]
    ]);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log($error);
    echo json_encode(['success' => false, 'error' => $error]);
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    error_log($error);
    echo json_encode(['success' => false, 'error' => $error]);
}
?>