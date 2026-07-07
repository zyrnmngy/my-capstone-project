<?php
// game_progress_api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database connection (adjust these settings to match your database)
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$password = 'Filibustero_capstone08';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch($method) {
    case 'POST':
        if ($action === 'update_progress') {
            updateGameProgress($pdo);
        } elseif ($action === 'start_session') {
            startGameSession($pdo);
        } elseif ($action === 'end_session') {
            endGameSession($pdo);
        }
        break;
    case 'GET':
        if ($action === 'get_progress') {
            getGameProgress($pdo);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function updateGameProgress($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    $required_fields = ['player_id', 'coins', 'score', 'current_stage', 'completed_quests'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    try {
        // First, check if game_progress table exists, if not create it
        createGameProgressTable($pdo);
        
        // Calculate progress percentage
        $progress_percentage = min(floor(($input['completed_quests'] / 25) * 100), 100);
        
        // Prepare data for upsert
        $data = [
            'player_id' => $input['player_id'],
            'coins' => max(0, (int)$input['coins']),
            'score' => max(0, (int)$input['score']),
            'current_stage' => max(0, min(13, (int)$input['current_stage'])),
            'completed_quests' => max(0, min(25, (int)$input['completed_quests'])),
            'progress_percentage' => $progress_percentage,
            'correct_answers' => isset($input['correct_answers']) ? max(0, (int)$input['correct_answers']) : 0,
            'total_questions_answered' => isset($input['total_questions_answered']) ? max(0, (int)$input['total_questions_answered']) : 0,
            'play_time' => isset($input['play_time']) ? max(0, (int)$input['play_time']) : 0,
            'game_completed' => ($input['completed_quests'] >= 25) ? 1 : 0
        ];
        
        // Use the stored procedure if available, otherwise use direct SQL
        $stmt = $pdo->prepare("CALL UpdateProgress(?, ?, ?, ?, ?)");
        if ($stmt->execute([
            $data['player_id'],
            $data['coins'],
            $data['score'],
            $data['current_stage'],
            $data['completed_quests']
        ])) {
            // Update additional fields not covered by the stored procedure
            $updateStmt = $pdo->prepare("
                UPDATE game_progress 
                SET correct_answers = ?, 
                    total_questions_answered = ?, 
                    play_time = ?,
                    game_completed = ?,
                    last_played = CURRENT_TIMESTAMP
                WHERE player_id = ?
            ");
            
            $updateStmt->execute([
                $data['correct_answers'],
                $data['total_questions_answered'],
                $data['play_time'],
                $data['game_completed'],
                $data['player_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Progress updated successfully',
                'progress_percentage' => $progress_percentage
            ]);
        } else {
            throw new Exception('Failed to update progress');
        }
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to update progress',
            'message' => $e->getMessage()
        ]);
    }
}

function getGameProgress($pdo) {
    $player_id = $_GET['player_id'] ?? null;
    
    if (!$player_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing player_id parameter']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM game_progress 
            WHERE player_id = ? 
            ORDER BY last_updated DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$player_id]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            echo json_encode([
                'success' => true,
                'data' => $progress
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'data' => null,
                'message' => 'No progress found for this player'
            ]);
        }
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to retrieve progress',
            'message' => $e->getMessage()
        ]);
    }
}

function startGameSession($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['player_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing player_id']);
        return;
    }
    
    try {
        // Create game_sessions table if it doesn't exist
        createGameSessionsTable($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO game_sessions (player_id, start_time) 
            VALUES (?, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([$input['player_id']]);
        $session_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'session_id' => $session_id,
            'message' => 'Game session started'
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to start game session',
            'message' => $e->getMessage()
        ]);
    }
}

function endGameSession($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['session_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing session_id']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE game_sessions 
            SET end_time = CURRENT_TIMESTAMP,
                duration = TIMESTAMPDIFF(SECOND, start_time, CURRENT_TIMESTAMP)
            WHERE id = ?
        ");
        
        $stmt->execute([$input['session_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Game session ended'
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to end game session',
            'message' => $e->getMessage()
        ]);
    }
}

function createGameProgressTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `game_progress` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `player_id` int(11) NOT NULL,
        `coins` int(11) DEFAULT 0,
        `score` int(11) DEFAULT 0,
        `current_stage` int(11) DEFAULT 0,
        `completed_quests` int(11) DEFAULT 0,
        `progress_percentage` int(11) DEFAULT 0,
        `correct_answers` int(11) DEFAULT 0,
        `total_questions_answered` int(11) DEFAULT 0,
        `play_time` int(11) DEFAULT 0,
        `game_completed` tinyint(1) DEFAULT 0,
        `completion_time` datetime DEFAULT NULL,
        `last_played` timestamp NOT NULL DEFAULT current_timestamp(),
        `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `player_id` (`player_id`),
        KEY `idx_progress_percentage` (`progress_percentage`),
        KEY `idx_score` (`score`),
        CONSTRAINT `fk_game_progress_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
}

function createGameSessionsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `game_sessions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `player_id` int(11) NOT NULL,
        `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
        `end_time` timestamp NULL DEFAULT NULL,
        `duration` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_player_sessions` (`player_id`),
        KEY `idx_start_time` (`start_time`),
        CONSTRAINT `fk_sessions_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
}
?>