<?php
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

$response = ['success' => false, 'student' => [], 'attempts' => [], 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $user_id = $_GET['user_id'] ?? '';
        
        if (empty($user_id)) {
            $response['error'] = 'User ID is required';
            echo json_encode($response);
            exit();
        }
        
        // Get student basic info and progress from leaderboard table - aligned with get_leaderboard.php
        $stmt = $pdo->prepare("
            SELECT 
                player_id as user_id,
                player_name as full_name,
                id_number,
                section,
                year_level,
                teacher_id,
                rizal_professor,
                score,
                coins,
                current_stage,
                progress_percentage,
                completed_quests,
                correct_answers,
                total_questions_answered,
                accuracy_percentage,
                game_completed,
                completion_time,
                last_played
            FROM leaderboard
            WHERE player_id = ?
        ");
        
        $stmt->execute([$user_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Convert numeric strings to appropriate types for consistency with get_leaderboard.php
            $student['user_id'] = (int)$student['user_id'];
            $student['teacher_id'] = (int)$student['teacher_id'];
            $student['rizal_professor'] = (int)$student['rizal_professor'];
            $student['score'] = (int)$student['score'];
            $student['coins'] = (int)$student['coins'];
            $student['current_stage'] = (int)$student['current_stage'];
            $student['progress_percentage'] = (float)$student['progress_percentage'];
            $student['completed_quests'] = (int)$student['completed_quests'];
            $student['correct_answers'] = (int)$student['correct_answers'];
            $student['total_questions_answered'] = (int)$student['total_questions_answered'];
            $student['accuracy_percentage'] = (float)$student['accuracy_percentage'];
            $student['game_completed'] = (bool)$student['game_completed'];
            
            $response['student'] = $student;
            
            // Get quiz attempts - keeping the same detailed attempt information
            $attempt_stmt = $pdo->prepare("
                SELECT 
                    qa.answered_at,
                    COALESCE(q.quest_name, 'Unknown Quest') as quest_name,
                    qst.question_text,
                    qa.selected_answer,
                    qa.is_correct,
                    COALESCE(qa.points_earned, 0) as points_earned,
                    qa.time_spent_seconds
                FROM quiz_attempts qa
                LEFT JOIN questions qst ON qa.question_id = qst.id
                LEFT JOIN quests q ON qst.quest_id = q.id
                WHERE qa.user_id = ?
                ORDER BY qa.answered_at DESC
                LIMIT 50
            ");
            
            $attempt_stmt->execute([$user_id]);
            $attempts = $attempt_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert numeric values in attempts for consistency
            foreach ($attempts as &$attempt) {
                $attempt['is_correct'] = (bool)$attempt['is_correct'];
                $attempt['points_earned'] = (int)$attempt['points_earned'];
                $attempt['time_spent_seconds'] = (int)$attempt['time_spent_seconds'];
            }
            
            $response['attempts'] = $attempts;
            $response['success'] = true;
            
            error_log("Student details retrieved successfully for user_id: $user_id");
            
        } else {
            $response['error'] = 'Student not found';
            error_log("Student not found for user_id: $user_id");
        }
    } else {
        $response['error'] = 'Invalid request method. GET required.';
    }
} catch (PDOException $e) {
    error_log("Database error in get_student_details.php: " . $e->getMessage());
    $response['error'] = 'Database error occurred';
} catch (Exception $e) {
    error_log("General error in get_student_details.php: " . $e->getMessage());
    $response['error'] = 'An error occurred';
}

echo json_encode($response);
?>