<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero'; // Change this to your database username
$password = 'Filibustero_capstone08';     // Change this to your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Get the request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Get input data
$input = null;
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
}

// Get the action from URL or POST data
$action = isset($_GET['action']) ? $_GET['action'] : (isset($input['action']) ? $input['action'] : '');

switch ($action) {
    case 'login':
        handleLogin($pdo);
        break;
    case 'save_progress':
        handleSaveProgress($pdo);
        break;
    case 'load_progress':
        handleLoadProgress($pdo);
        break;
    case 'update_session':
        handleUpdateSession($pdo);
        break;
    case 'get_leaderboard':
        handleGetLeaderboard($pdo);
        break;
    case 'complete_quest':
        handleCompleteQuest($pdo);
        break;
    case 'answer_question':
        handleAnswerQuestion($pdo);
        break;
    case 'test':
        echo json_encode(['success' => true, 'message' => 'API is working', 'timestamp' => date('Y-m-d H:i:s')]);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
        break;
}

function handleLogin($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['password'])) {
        echo json_encode(['success' => false, 'error' => 'Username and password required']);
        return;
    }
    
    try {
        // FIXED: First try to find user by username only
        $stmt = $pdo->prepare("SELECT u.id, u.user_type, u.full_name, u.username, u.password_hash FROM users u WHERE u.username = ?");
        $stmt->execute([$input['username']]);
        $user_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_check) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        // FIXED: Verify password properly
        $password_valid = false;
        if (password_verify($input['password'], $user_check['password_hash'])) {
            $password_valid = true;
        } else if ($user_check['password_hash'] === $input['password']) {
            // Handle plain text passwords (not recommended, but for compatibility)
            $password_valid = true;
        } else if ($user_check['password_hash'] === md5($input['password'])) {
            // Handle MD5 hashed passwords
            $password_valid = true;
        }
        
        if (!$password_valid) {
            echo json_encode(['success' => false, 'error' => 'Invalid password']);
            return;
        }
        
        $user = [
            'id' => $user_check['id'],
            'user_type' => $user_check['user_type'],
            'full_name' => $user_check['full_name'],
            'username' => $user_check['username']
        ];
        
        // Get or create player profile
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$player) {
            // Create player profile
            $stmt = $pdo->prepare("INSERT INTO players (user_id, player_name, email) VALUES (?, ?, ?)");
            $email = $user['username'] . '@example.com'; // Default email
            $stmt->execute([$user['id'], $user['full_name'], $email]);
            $player_id = $pdo->lastInsertId();
            
            // Create initial game progress entry
            $stmt = $pdo->prepare("INSERT INTO game_progress (player_id, score, coins, current_stage, progress_percentage) VALUES (?, 0, 0, 0, 0)");
            $stmt->execute([$player_id]);
        } else {
            $player_id = $player['id'];
        }
        
        // FIXED: Ensure user_progress record exists
        $stmt = $pdo->prepare("SELECT id FROM user_progress WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $user_progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_progress) {
            // Create user_progress record with default values
            $stmt = $pdo->prepare("INSERT INTO user_progress (user_id, overall_progress, story_progress, current_stage, coin_count, completed_quests) VALUES (?, 0, 0, 0, 0, 0)");
            $stmt->execute([$user['id']]);
        }
        
        // Load current progress
        $progress = loadUserProgress($pdo, $user['id'], $player_id);
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'player_id' => $player_id,
            'progress' => $progress,
            'message' => 'Login successful - progress data loaded'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleSaveProgress($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get player ID
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$input['user_id']]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$player) {
            echo json_encode(['success' => false, 'error' => 'Player not found']);
            return;
        }
        
        $player_id = $player['id'];
        
        // Update user_progress using your existing column names
        $stmt = $pdo->prepare("
            INSERT INTO user_progress 
            (user_id, overall_progress, story_progress, chapter_progress, quest_progress, 
             item_progress, achievement_progress, current_stage, coin_count, completed_quests, 
             collected_items, unlocked_achievements, last_save_date, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                overall_progress = VALUES(overall_progress),
                story_progress = VALUES(story_progress),
                chapter_progress = VALUES(chapter_progress),
                quest_progress = VALUES(quest_progress),
                item_progress = VALUES(item_progress),
                achievement_progress = VALUES(achievement_progress),
                current_stage = VALUES(current_stage),
                coin_count = VALUES(coin_count),
                completed_quests = VALUES(completed_quests),
                collected_items = VALUES(collected_items),
                unlocked_achievements = VALUES(unlocked_achievements),
                last_save_date = NOW(),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $input['user_id'],
            $input['overall_progress'] ?? 0,
            $input['story_progress'] ?? 0,
            $input['chapter_progress'] ?? 0,
            $input['quest_progress'] ?? 0,
            $input['item_progress'] ?? 0,
            $input['achievement_progress'] ?? 0,
            $input['current_stage'] ?? 0,
            $input['coin_count'] ?? 0,
            $input['completed_quests'] ?? 0,
            $input['collected_items'] ?? 0,
            $input['unlocked_achievements'] ?? 0
        ]);
        
        // Update game_progress with INSERT ... ON DUPLICATE KEY UPDATE
        $stmt = $pdo->prepare("
            INSERT INTO game_progress 
            (player_id, score, coins, current_stage, progress_percentage, completed_quests, 
             correct_answers, total_questions_answered, last_played, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                coins = VALUES(coins),
                current_stage = VALUES(current_stage),
                progress_percentage = VALUES(progress_percentage),
                completed_quests = VALUES(completed_quests),
                correct_answers = VALUES(correct_answers),
                total_questions_answered = VALUES(total_questions_answered),
                last_played = NOW(),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $player_id,
            $input['score'] ?? 0,
            $input['coin_count'] ?? 0,
            $input['current_stage'] ?? 0,
            ($input['overall_progress'] ?? 0) * 100, // Convert to percentage
            $input['completed_quests'] ?? 0,
            $input['correct_answers'] ?? 0,
            $input['total_questions_answered'] ?? 0
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Progress saved successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to save progress: ' . $e->getMessage()]);
    }
}

function handleLoadProgress($pdo) {
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        return;
    }
    
    try {
        // Get player ID
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$player) {
            echo json_encode(['success' => false, 'error' => 'Player not found']);
            return;
        }
        
        $progress = loadUserProgress($pdo, $user_id, $player['id']);
        
        echo json_encode([
            'success' => true, 
            'progress' => $progress,
            'loaded_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to load progress: ' . $e->getMessage()]);
    }
}

function handleUpdateSession($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        return;
    }
    
    try {
        // Get player ID
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$input['user_id']]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$player) {
            echo json_encode(['success' => false, 'error' => 'Player not found']);
            return;
        }
        
        // Update last played time
        $stmt = $pdo->prepare("UPDATE game_progress SET last_played = NOW() WHERE player_id = ?");
        $stmt->execute([$player['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Session updated']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to update session: ' . $e->getMessage()]);
    }
}

function handleGetLeaderboard($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM leaderboard ORDER BY score DESC, progress_percentage DESC LIMIT 10");
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'leaderboard' => $leaderboard]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get leaderboard: ' . $e->getMessage()]);
    }
}

function handleCompleteQuest($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id']) || !isset($input['quest_id'])) {
        echo json_encode(['success' => false, 'error' => 'User ID and Quest ID required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get player ID
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$input['user_id']]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$player) {
            echo json_encode(['success' => false, 'error' => 'Player not found']);
            return;
        }
        
        $player_id = $player['id'];
        
        // Get quest details
        $stmt = $pdo->prepare("SELECT * FROM quests WHERE id = ?");
        $stmt->execute([$input['quest_id']]);
        $quest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quest) {
            // If quest doesn't exist in database, use default values
            $quest = [
                'points_reward' => 5,
                'coins_reward' => 10
            ];
        }
        
        // Insert/update player quest progress
        $stmt = $pdo->prepare("
            INSERT INTO player_quest_progress (player_id, quest_id, is_completed, score_earned, coins_earned, completed_at) 
            VALUES (?, ?, 1, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
                is_completed = 1, 
                score_earned = VALUES(score_earned), 
                coins_earned = VALUES(coins_earned), 
                completed_at = NOW()
        ");
        $stmt->execute([
            $player_id,
            $input['quest_id'],
            $quest['points_reward'],
            $quest['coins_reward']
        ]);
        
        // Update game progress
        $stmt = $pdo->prepare("
            UPDATE game_progress SET 
                score = score + ?,
                coins = coins + ?,
                completed_quests = completed_quests + 1,
                updated_at = NOW()
            WHERE player_id = ?
        ");
        $stmt->execute([$quest['points_reward'], $quest['coins_reward'], $player_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Quest completed',
            'points_earned' => $quest['points_reward'],
            'coins_earned' => $quest['coins_reward']
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to complete quest: ' . $e->getMessage()]);
    }
}

function handleAnswerQuestion($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id']) || !isset($input['question_id']) || !isset($input['answer'])) {
        echo json_encode(['success' => false, 'error' => 'User ID, Question ID, and Answer required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get player ID
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$input['user_id']]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$player) {
            echo json_encode(['success' => false, 'error' => 'Player not found']);
            return;
        }
        
        $player_id = $player['id'];
        
        // Get question details
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
        $stmt->execute([$input['question_id']]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$question) {
            // If question doesn't exist, use defaults
            $question = [
                'correct_answer' => 'A',
                'points_value' => 5,
                'explanation' => 'No explanation available',
                'quest_id' => 1
            ];
        }
        
        $is_correct = ($input['answer'] === $question['correct_answer']);
        $points_earned = $is_correct ? $question['points_value'] : 0;
        
        // Record the answer
        $stmt = $pdo->prepare("
            INSERT INTO player_answers (player_id, question_id, selected_answer, is_correct, points_earned) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $player_id,
            $input['question_id'],
            $input['answer'],
            $is_correct ? 1 : 0,
            $points_earned
        ]);
        
        // Update game progress
        if ($is_correct) {
            $stmt = $pdo->prepare("
                UPDATE game_progress SET 
                    score = score + ?,
                    correct_answers = correct_answers + 1,
                    total_questions_answered = total_questions_answered + 1,
                    updated_at = NOW()
                WHERE player_id = ?
            ");
            $stmt->execute([$points_earned, $player_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE game_progress SET 
                    total_questions_answered = total_questions_answered + 1,
                    updated_at = NOW()
                WHERE player_id = ?
            ");
            $stmt->execute([$player_id]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'is_correct' => $is_correct,
            'correct_answer' => $question['correct_answer'],
            'explanation' => $question['explanation'],
            'points_earned' => $points_earned
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to record answer: ' . $e->getMessage()]);
    }
}

function loadUserProgress($pdo, $user_id, $player_id) {
    // Load user progress
    $stmt = $pdo->prepare("
        SELECT * FROM user_progress 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user_progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Load game progress
    $stmt = $pdo->prepare("
        SELECT * FROM game_progress 
        WHERE player_id = ?
    ");
    $stmt->execute([$player_id]);
    $game_progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Load completed quests
    $stmt = $pdo->prepare("
        SELECT quest_id FROM player_quest_progress 
        WHERE player_id = ? AND is_completed = 1
    ");
    $stmt->execute([$player_id]);
    $completed_quests = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return [
        'user_progress' => $user_progress,
        'game_progress' => $game_progress,
        'completed_quests' => $completed_quests,
        'loaded_timestamp' => date('Y-m-d H:i:s')
    ];
}
?>