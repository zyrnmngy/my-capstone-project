<?php
// filibustero_api.php - Updated for proper user integration
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database connection - Update these for your localhost setup
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';  // Change this to your database name
$username = 'u769346877_filibustero';          // Your MySQL username
$password = 'Filibustero_capstone08';              // Your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Initialize database tables on first run
initializeDatabase($pdo);

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
        } elseif ($action === 'sync_user_progress') {
            syncUserProgress($pdo);
        }
        break;
    case 'GET':
        if ($action === 'get_progress') {
            getGameProgress($pdo);
        } elseif ($action === 'get_all_progress') {
            getAllProgress($pdo);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function initializeDatabase($pdo) {
    try {
        // Create game_progress table - Updated to use user_id instead of player_id
        $sql = "CREATE TABLE IF NOT EXISTS `game_progress` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `player_id` int(11) NOT NULL COMMENT 'Maps to users.id',
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
            CONSTRAINT `fk_game_progress_user` FOREIGN KEY (`player_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($sql);
        
        // Create game_sessions table with proper foreign key
        $sessionSql = "CREATE TABLE IF NOT EXISTS `game_sessions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `player_id` int(11) NOT NULL COMMENT 'Maps to users.id',
            `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
            `end_time` timestamp NULL DEFAULT NULL,
            `duration` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_player_sessions` (`player_id`),
            KEY `idx_start_time` (`start_time`),
            CONSTRAINT `fk_game_sessions_user` FOREIGN KEY (`player_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($sessionSql);
        
    } catch(Exception $e) {
        error_log("Database initialization error: " . $e->getMessage());
    }
}

function validateUserSession() {
    // Check if user is logged in via session or token
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please login first']);
        exit;
    }
    return $_SESSION['user_id'];
}

// Modify your update_progress function to use the authenticated user
function updateGameProgress($pdo) {
    // Validate user session first
    $user_id = validateUserSession();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    // Use the authenticated user ID, not the one from input (for security)
    $input['player_id'] = $user_id;
    
    // Validate that the player_id exists in the users table
    $user_id = (int)$input['player_id'];
    $checkUser = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ?");
    $checkUser->execute([$user_id]);
    $user = $checkUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(400);
        echo json_encode(['error' => "Invalid player_id: $user_id. User does not exist."]);
        return;
    }
    
    try {
        // Calculate progress percentage (25 total quests)
        $progress_percentage = min(floor(($input['completed_quests'] / 25) * 100), 100);
        
        // Prepare data
        $data = [
            'player_id' => $user_id,
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
        
        // Set completion time if game just completed
        $completion_time = null;
        if ($data['game_completed'] == 1) {
            // Check if it wasn't completed before
            $checkCompletion = $pdo->prepare("SELECT game_completed FROM game_progress WHERE player_id = ?");
            $checkCompletion->execute([$data['player_id']]);
            $wasCompleted = $checkCompletion->fetchColumn();
            
            if (!$wasCompleted) {
                $completion_time = date('Y-m-d H:i:s');
            }
        }
        
        // Upsert query
        $stmt = $pdo->prepare("
            INSERT INTO game_progress (
                player_id, coins, score, current_stage, completed_quests, progress_percentage,
                correct_answers, total_questions_answered, play_time, game_completed, completion_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                coins = VALUES(coins),
                score = VALUES(score),
                current_stage = VALUES(current_stage),
                completed_quests = VALUES(completed_quests),
                progress_percentage = VALUES(progress_percentage),
                correct_answers = VALUES(correct_answers),
                total_questions_answered = VALUES(total_questions_answered),
                play_time = VALUES(play_time),
                game_completed = VALUES(game_completed),
                completion_time = COALESCE(completion_time, VALUES(completion_time)),
                last_played = CURRENT_TIMESTAMP,
                last_updated = CURRENT_TIMESTAMP
        ");
        
        $result = $stmt->execute([
            $data['player_id'],
            $data['coins'],
            $data['score'],
            $data['current_stage'],
            $data['completed_quests'],
            $data['progress_percentage'],
            $data['correct_answers'],
            $data['total_questions_answered'],
            $data['play_time'],
            $data['game_completed'],
            $completion_time
        ]);
        
        if ($result) {
            // Get updated data
            $fetchStmt = $pdo->prepare("SELECT * FROM game_progress WHERE player_id = ?");
            $fetchStmt->execute([$data['player_id']]);
            $updatedData = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Progress updated successfully',
                'progress_percentage' => $progress_percentage,
                'player_name' => $user['full_name'],
                'data' => $updatedData,
                'completion_time' => $completion_time
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

function syncUserProgress($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id']);
        return;
    }
    
    try {
        $user_id = (int)$input['user_id'];
        
        // Check if user exists
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $checkUser->execute([$user_id]);
        if (!$checkUser->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user_id']);
            return;
        }
        
        // Prepare user progress data
        $data = [
            'user_id' => $user_id,
            'coin_count' => isset($input['coin_count']) ? max(0, (int)$input['coin_count']) : 0,
            'current_stage' => isset($input['current_stage']) ? max(1, (int)$input['current_stage']) : 1,
            'completed_quests' => isset($input['completed_quests']) ? max(0, (int)$input['completed_quests']) : 0,
            'overall_progress' => isset($input['overall_progress']) ? max(0, min(100, (float)$input['overall_progress'])) : 0,
            'quest_progress' => isset($input['quest_progress']) ? max(0, min(100, (float)$input['quest_progress'])) : 0
        ];
        
        // Update user_progress table
        $stmt = $pdo->prepare("
            UPDATE user_progress 
            SET coin_count = ?,
                current_stage = ?,
                completed_quests = ?,
                overall_progress = ?,
                quest_progress = ?,
                last_save_date = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        
        $result = $stmt->execute([
            $data['coin_count'],
            $data['current_stage'],
            $data['completed_quests'],
            $data['overall_progress'],
            $data['quest_progress'],
            $data['user_id']
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'User progress synced successfully'
            ]);
        } else {
            throw new Exception('Failed to sync user progress');
        }
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to sync user progress',
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
        // First check if user exists
        $checkUser = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ?");
        $checkUser->execute([$player_id]);
        $user = $checkUser->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid player_id. User does not exist.']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT gp.*, u.full_name as player_name 
            FROM game_progress gp
            JOIN users u ON gp.player_id = u.id
            WHERE gp.player_id = ? 
            ORDER BY gp.last_updated DESC 
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
                'message' => "No progress found for player: {$user['full_name']} (ID: $player_id)"
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

function getAllProgress($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT gp.*, u.full_name as player_name 
            FROM game_progress gp
            JOIN users u ON gp.player_id = u.id
            ORDER BY gp.last_updated DESC
        ");
        
        $stmt->execute();
        $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $progress
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to retrieve all progress',
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
        $player_id = (int)$input['player_id'];
        
        // Validate user exists
        $checkUser = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ?");
        $checkUser->execute([$player_id]);
        $user = $checkUser->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid player_id. User does not exist.']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO game_sessions (player_id, start_time) 
            VALUES (?, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([$player_id]);
        $session_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'session_id' => $session_id,
            'player_name' => $user['full_name'],
            'message' => "Game session started for {$user['full_name']}"
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


?>