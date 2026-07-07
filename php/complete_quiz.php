// ============================================
// complete_quest.php - Quest completion handler
// ============================================
<?php
require_once 'config.php';
require_once 'cors.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) || !isset($data->quest_id)) {
    echo json_encode(array("success" => false, "message" => "User ID and Quest ID are required"));
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get player ID
    $player_query = "SELECT id FROM players WHERE user_id = :user_id";
    $player_stmt = $db->prepare($player_query);
    $player_stmt->bindParam(":user_id", $data->user_id);
    $player_stmt->execute();
    
    if ($player_stmt->rowCount() == 0) {
        echo json_encode(array("success" => false, "message" => "Player not found"));
        exit();
    }
    
    $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
    $player_id = $player['id'];
    
    // Insert or update quest progress
    $query = "INSERT INTO player_quest_progress 
              (player_id, quest_id, is_completed, score_earned, coins_earned, completed_at, attempts)
              VALUES 
              (:player_id, :quest_id, :is_completed, :score_earned, :coins_earned, NOW(), 1)
              ON DUPLICATE KEY UPDATE
              is_completed = :is_completed2,
              score_earned = score_earned + :score_earned2,
              coins_earned = coins_earned + :coins_earned2,
              attempts = attempts + 1,
              completed_at = IF(:is_completed3 = 1, NOW(), completed_at)";
    
    $stmt = $db->prepare($query);
    $is_completed = isset($data->is_completed) ? $data->is_completed : 1;
    $score_earned = isset($data->score_earned) ? $data->score_earned : 0;
    $coins_earned = isset($data->coins_earned) ? $data->coins_earned : 0;
    
    $stmt->bindParam(":player_id", $player_id);
    $stmt->bindParam(":quest_id", $data->quest_id);
    $stmt->bindParam(":is_completed", $is_completed);
    $stmt->bindParam(":score_earned", $score_earned);
    $stmt->bindParam(":coins_earned", $coins_earned);
    $stmt->bindParam(":is_completed2", $is_completed);
    $stmt->bindParam(":score_earned2", $score_earned);
    $stmt->bindParam(":coins_earned2", $coins_earned);
    $stmt->bindParam(":is_completed3", $is_completed);
    
    if ($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Quest progress updated"));
    } else {
        echo json_encode(array("success" => false, "message" => "Failed to update quest progress"));
    }
} catch(Exception $e) {
    echo json_encode(array("success" => false, "message" => $e->getMessage()));
}
?>