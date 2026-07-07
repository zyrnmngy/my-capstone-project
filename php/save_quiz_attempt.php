<?php
// File: save_quiz_attempt.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $host = 'localhost';
    $dbname = 'u769346877_filibustero_db';
    $username = 'u769346877_filibustero';
    $password = 'Filibustero_capstone08';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $userId = (int)$input['user_id'];
    $questionId = (int)$input['question_id'];
    $questId = (int)$input['quest_id'];
    $selectedAnswer = $input['selected_answer'];
    $isCorrect = (bool)$input['is_correct'];
    $pointsEarned = (int)($input['points_earned'] ?? ($isCorrect ? 5 : 0));
    $timeSpent = (int)($input['time_spent_seconds'] ?? 0);
    
    // Save quiz attempt
    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts 
        (user_id, question_id, quest_id, selected_answer, is_correct, points_earned, time_spent_seconds, answered_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $questionId,
        $questId,
        $selectedAnswer,
        $isCorrect,
        $pointsEarned,
        $timeSpent
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quiz attempt saved successfully',
        'attempt_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>