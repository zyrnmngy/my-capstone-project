<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$password = 'Filibustero_capstone08';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $input['user_id'] ?? null;
    $quest_id = $input['quest_id'] ?? null;
    $question_id = $input['question_id'] ?? null;
    $selected_answer = $input['selected_answer'] ?? null;
    $is_correct = $input['is_correct'] ?? false;
    $points_earned = $input['points_earned'] ?? 0;
    $time_spent = $input['time_spent'] ?? 0;
    
    if (!$user_id || !$quest_id || !$question_id) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Record the quiz attempt
        $sql = "INSERT INTO quiz_attempts 
                (user_id, question_id, quest_id, selected_answer, is_correct, points_earned, time_spent_seconds) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id, $question_id, $quest_id, $selected_answer, 
            $is_correct ? 1 : 0, $points_earned, $time_spent
        ]);
        
        // Update or create quiz session
        $session_sql = "INSERT INTO quiz_sessions (user_id, quest_id, total_questions, correct_answers, total_score)
                        VALUES (?, ?, 1, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        total_questions = total_questions + 1,
                        correct_answers = correct_answers + ?,
                        total_score = total_score + ?";
        
        $session_stmt = $pdo->prepare($session_sql);
        $session_stmt->execute([
            $user_id, $quest_id, $is_correct ? 1 : 0, $points_earned,
            $is_correct ? 1 : 0, $points_earned
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz answer recorded successfully'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
}
?>