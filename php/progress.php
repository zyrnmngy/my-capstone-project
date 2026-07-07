<?php
require_once 'config.php';

$conn = getDBConnection();

// Handle progress tracking requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = isset($input['action']) ? sanitizeInput($input['action']) : '';
    
    if ($action === 'save_progress') {
        // Validate required fields
        $errors = validateRequiredFields(['user_id'], $input);
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
            exit;
        }
        
        $userId = (int)$input['user_id'];
        $progressData = [
            'overall_progress' => isset($input['overall_progress']) ? (float)$input['overall_progress'] : 0,
            'story_progress' => isset($input['story_progress']) ? (float)$input['story_progress'] : 0,
            'chapter_progress' => isset($input['chapter_progress']) ? (float)$input['chapter_progress'] : 0,
            'quest_progress' => isset($input['quest_progress']) ? (float)$input['quest_progress'] : 0,
            'item_progress' => isset($input['item_progress']) ? (float)$input['item_progress'] : 0,
            'achievement_progress' => isset($input['achievement_progress']) ? (float)$input['achievement_progress'] : 0,
            'playtime_hours' => isset($input['playtime_hours']) ? (int)$input['playtime_hours'] : 0,
            'current_stage' => isset($input['current_stage']) ? (int)$input['current_stage'] : 1,
            'coin_count' => isset($input['coin_count']) ? (int)$input['coin_count'] : 0,
            'completed_switches' => isset($input['completed_switches']) ? (int)$input['completed_switches'] : 0,
            'completed_chapters' => isset($input['completed_chapters']) ? (int)$input['completed_chapters'] : 0,
            'completed_quests' => isset($input['completed_quests']) ? (int)$input['completed_quests'] : 0,
            'collected_items' => isset($input['collected_items']) ? (int)$input['collected_items'] : 0,
            'unlocked_achievements' => isset($input['unlocked_achievements']) ? (int)$input['unlocked_achievements'] : 0,
            'environment_mode' => isset($input['environment_mode']) ? sanitizeInput($input['environment_mode']) : 'production',
            'last_save_date' => date('Y-m-d H:i:s')
        ];
        
        // Prepare update query
        $setParts = [];
        $params = [];
        $types = '';
        
        foreach ($progressData as $field => $value) {
            $setParts[] = "$field = ?";
            $params[] = $value;
            $types .= is_float($value) ? 'd' : (is_int($value) ? 'i' : 's');
        }
        
        $params[] = $userId;
        $types .= 'i';
        
        $query = "UPDATE user_progress SET " . implode(', ', $setParts) . " WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Progress saved successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save progress: ' . $conn->error]);
        }
        
    } elseif ($action === 'load_progress') {
        // Validate required fields
        $errors = validateRequiredFields(['user_id'], $input);
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
            exit;
        }
        
        $userId = (int)$input['user_id'];
        
        $stmt = $conn->prepare("SELECT * FROM user_progress WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'No progress data found']);
        } else {
            $progress = $result->fetch_assoc();
            echo json_encode(['success' => true, 'progress' => $progress]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
?>