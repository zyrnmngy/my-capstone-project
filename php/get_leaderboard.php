<?php

//get_leaderboard.php
// Suppress any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Clean any output that might have been generated
ob_clean();

$response = ['success' => false, 'leaderboard' => [], 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $teacher_id = $_GET['teacher_id'] ?? '';
        $section = $_GET['section'] ?? '';
        
        if (empty($teacher_id)) {
            $response['error'] = 'Teacher ID is required';
            echo json_encode($response);
            exit();
        }
        
        // Modified query to join with teacher_sections to get students from sections assigned to this teacher
        $query = "
            SELECT 
                l.player_id,
                l.player_name as full_name,
                l.id_number,
                l.section,
                l.year_level,
                l.teacher_id,
                l.score,
                l.coins,
                l.current_stage,
                l.progress_percentage,
                l.completed_quests,
                l.correct_answers,
                l.total_questions_answered,
                l.accuracy_percentage,
                l.game_completed,
                l.completion_time,
                l.last_played
            FROM leaderboard l
            INNER JOIN teacher_sections ts ON l.section = ts.section
            WHERE ts.teacher_id = ?
        ";
        
        $params = [$teacher_id];
        
        // Add section filter if specified
        if (!empty($section)) {
            $query .= " AND l.section = ?";
            $params[] = $section;
        }
        
        // Order by score DESC, then by progress_percentage DESC, then by completion_time ASC
        $query .= " ORDER BY l.score DESC, l.progress_percentage DESC, l.completion_time ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert numeric strings to appropriate types for better JSON output
        foreach ($leaderboard as &$row) {
            $row['player_id'] = (int)$row['player_id'];
            // Remove this line if user_id doesn't exist in the leaderboard view
            // $row['user_id'] = (int)$row['user_id'];
            $row['teacher_id'] = (int)$row['teacher_id'];
            $row['score'] = (int)$row['score'];
            $row['coins'] = (int)$row['coins'];
            $row['current_stage'] = (int)$row['current_stage'];
            $row['progress_percentage'] = (float)$row['progress_percentage'];
            $row['completed_quests'] = (int)$row['completed_quests'];
            $row['correct_answers'] = (int)$row['correct_answers'];
            $row['total_questions_answered'] = (int)$row['total_questions_answered'];
            $row['accuracy_percentage'] = (float)$row['accuracy_percentage'];
            $row['game_completed'] = (bool)$row['game_completed'];
        }
        
        $response['success'] = true;
        $response['leaderboard'] = $leaderboard;
        
        error_log("Leaderboard query successful for teacher_id: $teacher_id" . 
                 ($section ? ", section: $section" : "") . 
                 ", found " . count($leaderboard) . " students");
        
    } else {
        $response['error'] = 'Invalid request method. GET required.';
    }
} catch (PDOException $e) {
    error_log("Database error in get_leaderboard.php: " . $e->getMessage());
    $response['error'] = 'Database error occurred';
} catch (Exception $e) {
    error_log("General error in get_leaderboard.php: " . $e->getMessage());
    $response['error'] = 'An error occurred';
}

echo json_encode($response);
?>