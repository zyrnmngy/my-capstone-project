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
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

$response = ['success' => false, 'students' => [], 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $section = $_POST['section'] ?? '';
        
        if (empty($section)) {
            $response['error'] = 'Section is required';
            echo json_encode($response);
            exit();
        }
        
        // Use leaderboard table as primary data source - aligned with get_leaderboard.php and get_student_details.php
        $stmt = $pdo->prepare("
            SELECT 
                player_id as user_id,
                player_name as full_name,
                id_number,
                section,
                year_level,
                teacher_id,
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
            WHERE section = ?
            ORDER BY full_name ASC
        ");
        
        $stmt->execute([$section]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert numeric strings to appropriate types for consistency with other endpoints
        foreach ($students as &$student) {
            $student['user_id'] = (int)$student['user_id'];
            $student['teacher_id'] = (int)$student['teacher_id'];
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
        
        $response['success'] = true;
        $response['students'] = $students;
        
        error_log("Students by section query successful for section: $section, found " . count($students) . " students");
        
    } else {
        $response['error'] = 'Invalid request method. POST required.';
    }
} catch (PDOException $e) {
    error_log("Database error in get_students_by_section.php: " . $e->getMessage());
    $response['error'] = 'Database error occurred';
} catch (Exception $e) {
    error_log("General error in get_students_by_section.php: " . $e->getMessage());
    $response['error'] = 'An error occurred';
}

echo json_encode($response);
?>