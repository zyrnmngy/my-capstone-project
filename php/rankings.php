<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Start session for authentication
session_start();

// Debug logging
error_log("Rankings request received: " . print_r($_POST, true));

// Database configuration
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$password = 'Filibustero_capstone08';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test connection
    $test = $pdo->query("SELECT 1");
    if (!$test) {
        throw new Exception("Database test query failed");
    }
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed', 'details' => $e->getMessage()]);
    exit;
}

// Get the action from POST data
$action = $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? null;

error_log("Action: $action, User ID: $user_id");

if ($action !== 'get_rankings' || !$user_id) {
    error_log("Invalid request parameters");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters', 'received' => $_POST]);
    exit;
}

try {
    // Get current user information from the leaderboard view
    // Try both id_number (string format like 22L-2027) and numeric id
    $userQuery = "
        SELECT 
            id_number,
            player_name,
            section,
            year_level,
            rizal_professor,
            teacher_id,
            score,
            coins,
            progress_percentage
        FROM leaderboard 
        WHERE id_number = :user_id OR id_number = (
            SELECT id_number FROM users WHERE id = :user_id_numeric
        )
        LIMIT 1
    ";

    $userStmt = $pdo->prepare($userQuery);
    $userStmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
    $userStmt->bindParam(':user_id_numeric', $user_id, PDO::PARAM_INT);
    $userStmt->execute();
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Current user data: " . print_r($currentUser, true));
    
    if (!$currentUser) {
        error_log("User not found in leaderboard: $user_id");
        echo json_encode(['success' => false, 'error' => 'User not found in leaderboard']);
        exit;
    }
    
    $rankings = [
        'mySection' => [],
        'otherSections' => []
    ];
    
    // Get rankings for user's own section (My Section)
    $mySectionQuery = "
        SELECT 
            id_number,
            player_name,
            section,
            year_level,
            rizal_professor,
            teacher_id,
            score,
            coins,
            progress_percentage,
            current_stage,
            completed_quests,
            correct_answers,
            total_questions_answered,
            accuracy_percentage,
            game_completed,
            completion_time,
            last_played,
            (id_number = :current_user_id) as is_current_user
        FROM leaderboard 
        WHERE section = :section
        ORDER BY score DESC, progress_percentage DESC, completion_time ASC
        LIMIT 20
    ";
    
    $mySectionStmt = $pdo->prepare($mySectionQuery);
    $mySectionStmt->bindParam(':section', $currentUser['section'], PDO::PARAM_STR);
    $mySectionStmt->bindParam(':current_user_id', $user_id, PDO::PARAM_STR);
    $mySectionStmt->execute();
    $rankings['mySection'] = $mySectionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("My section results: " . count($rankings['mySection']));
    
    // Convert boolean values and format data for My Section
    foreach ($rankings['mySection'] as &$student) {
        $student['is_current_user'] = (bool)$student['is_current_user'];
        $student['score'] = (int)$student['score'];
        $student['coins'] = (int)$student['coins'];
        $student['current_stage'] = (int)$student['current_stage'];
        $student['progress_percentage'] = (float)$student['progress_percentage'];
        $student['completed_quests'] = (int)$student['completed_quests'];
        $student['correct_answers'] = (int)$student['correct_answers'];
        $student['total_questions_answered'] = (int)$student['total_questions_answered'];
        $student['accuracy_percentage'] = (float)$student['accuracy_percentage'];
        $student['game_completed'] = (bool)$student['game_completed'];
    }
    
    // Get rankings for other sections with same teacher_id (Other Sections)
    $otherSectionsQuery = "
        SELECT 
            id_number,
            player_name,
            section,
            year_level,
            rizal_professor,
            teacher_id,
            score,
            coins,
            progress_percentage,
            current_stage,
            completed_quests,
            correct_answers,
            total_questions_answered,
            accuracy_percentage,
            game_completed,
            completion_time,
            last_played,
            (id_number = :current_user_id) as is_current_user
        FROM leaderboard 
        WHERE teacher_id = :teacher_id AND section != :current_section
        ORDER BY score DESC, progress_percentage DESC, completion_time ASC
        LIMIT 20
    ";
    
    $otherSectionsStmt = $pdo->prepare($otherSectionsQuery);
    $otherSectionsStmt->bindParam(':teacher_id', $currentUser['teacher_id'], PDO::PARAM_INT);
    $otherSectionsStmt->bindParam(':current_section', $currentUser['section'], PDO::PARAM_STR);
    $otherSectionsStmt->bindParam(':current_user_id', $user_id, PDO::PARAM_STR);
    $otherSectionsStmt->execute();
    $rankings['otherSections'] = $otherSectionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Other sections results: " . count($rankings['otherSections']));
    
    // Convert boolean values and format data for Other Sections
    foreach ($rankings['otherSections'] as &$student) {
        $student['is_current_user'] = (bool)$student['is_current_user'];
        $student['score'] = (int)$student['score'];
        $student['coins'] = (int)$student['coins'];
        $student['current_stage'] = (int)$student['current_stage'];
        $student['progress_percentage'] = (float)$student['progress_percentage'];
        $student['completed_quests'] = (int)$student['completed_quests'];
        $student['correct_answers'] = (int)$student['correct_answers'];
        $student['total_questions_answered'] = (int)$student['total_questions_answered'];
        $student['accuracy_percentage'] = (float)$student['accuracy_percentage'];
        $student['game_completed'] = (bool)$student['game_completed'];
    }
    
    // Prepare response with current user info
    $response = [
        'success' => true,
        'rankings' => $rankings,
        'current_user' => [
            'id_number' => $currentUser['id_number'],
            'player_name' => $currentUser['player_name'],
            'section' => $currentUser['section'],
            'teacher_id' => $currentUser['teacher_id'],
            'score' => (int)$currentUser['score'],
            'coins' => (int)$currentUser['coins'],
            'progress_percentage' => (float)$currentUser['progress_percentage']
        ],
        'debug' => [
            'post_data' => $_POST,
            'user_found' => !!$currentUser
        ]
    ];
    
    error_log("Sending response with " . count($rankings['mySection']) . " mySection and " . 
              count($rankings['otherSections']) . " otherSections records");
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Database error in rankings.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database query failed',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in rankings.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
}
?>