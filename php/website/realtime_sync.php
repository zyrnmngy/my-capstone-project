<?php
require_once 'config.php';

setCORSHeaders();

try {
    $pdo = getDBConnection();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'update_progress':
            updatePlayerProgress($pdo);
            break;
        case 'save_quiz_attempt':
            saveQuizAttempt($pdo);
            break;
        case 'complete_quest':
            completeQuest($pdo);
            break;
        case 'update_coins':
            updateCoins($pdo);
            break;
        case 'update_score':
            updateScore($pdo);
            break;
        case 'create_player_record':
            createPlayerRecord($pdo);
            break;
        case 'get_player_data':
            getPlayerData($pdo);
            break;
        default:
            jsonResponse(false, null, 'Invalid action', 400);
    }
    
} catch (Exception $e) {
    logError('Sync error: ' . $e->getMessage());
    jsonResponse(false, null, 'System error occurred', 500);
}

function updatePlayerProgress($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        $stage = $_POST['stage'] ?? 1;
        $progress_percentage = $_POST['progress_percentage'] ?? 0;
        $coins = $_POST['coins'] ?? 0;
        $playtime_hours = $_POST['playtime_hours'] ?? 0;
        
        if (empty($user_id)) {
            jsonResponse(false, null, 'User ID is required', 400);
        }
        
        // Update user_progress table
        $stmt = $pdo->prepare("
            UPDATE user_progress 
            SET overall_progress = ?, 
                current_stage = ?, 
                coin_count = ?, 
                playtime_hours = ?,
                last_save_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$progress_percentage, $stage, $coins, $playtime_hours, $user_id]);
        
        // Also update game_progress if player record exists
        $stmt = $pdo->prepare("
            UPDATE game_progress gp
            JOIN players p ON gp.player_id = p.id 
            SET gp.current_stage = ?, 
                gp.progress_percentage = ?, 
                gp.coins = ?,
                gp.last_played = CURRENT_TIMESTAMP,
                gp.updated_at = CURRENT_TIMESTAMP
            WHERE p.user_id = ?
        ");
        $stmt->execute([$stage, $progress_percentage, $coins, $user_id]);
        
        // Log the progress save
        $stmt = $pdo->prepare("
            INSERT INTO progress_save_log (user_id, stage, progress_percentage, coins, playtime_hours)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $stage, $progress_percentage, $coins, $playtime_hours]);
        
        jsonResponse(true, [
            'message' => 'Progress updated successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        logError('Update progress error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to update progress', 500);
    }
}

function saveQuizAttempt($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        $question_id = $_POST['question_id'] ?? 0;
        $quest_id = $_POST['quest_id'] ?? 0;
        $selected_answer = $_POST['selected_answer'] ?? '';
        $is_correct = $_POST['is_correct'] ?? 0;
        $points_earned = $_POST['points_earned'] ?? 0;
        $time_spent = $_POST['time_spent'] ?? 0;
        
        if (empty($user_id) || empty($question_id) || empty($quest_id)) {
            jsonResponse(false, null, 'User ID, Question ID, and Quest ID are required', 400);
        }
        
        // Get current attempt number for this user and question
        $stmt = $pdo->prepare("
            SELECT MAX(attempt_number) as max_attempt 
            FROM quiz_attempts 
            WHERE user_id = ? AND question_id = ?
        ");
        $stmt->execute([$user_id, $question_id]);
        $result = $stmt->fetch();
        $attempt_number = ($result['max_attempt'] ?? 0) + 1;
        
        // Insert quiz attempt
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (
                user_id, question_id, quest_id, selected_answer, 
                is_correct, points_earned, time_spent, attempt_number, 
                attempted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $user_id, $question_id, $quest_id, $selected_answer,
            $is_correct, $points_earned, $time_spent, $attempt_number
        ]);
        
        // Update user's total score and coins if correct
        if ($is_correct) {
            $stmt = $pdo->prepare("
                UPDATE user_progress 
                SET total_score = total_score + ?, 
                    coin_count = coin_count + ?,
                    last_save_date = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");
            $stmt->execute([$points_earned, $points_earned, $user_id]);
        }
        
        // Update quest progress
        $stmt = $pdo->prepare("
            INSERT INTO user_quest_progress (user_id, quest_id, questions_answered, last_attempt_date)
            VALUES (?, ?, 1, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE 
            questions_answered = questions_answered + 1,
            last_attempt_date = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$user_id, $quest_id]);
        
        jsonResponse(true, [
            'attempt_number' => $attempt_number,
            'points_earned' => $points_earned,
            'message' => 'Quiz attempt saved successfully'
        ]);
        
    } catch (Exception $e) {
        logError('Save quiz attempt error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to save quiz attempt', 500);
    }
}

function completeQuest($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        $quest_id = $_POST['quest_id'] ?? 0;
        $completion_time = $_POST['completion_time'] ?? 0;
        $final_score = $_POST['final_score'] ?? 0;
        $bonus_coins = $_POST['bonus_coins'] ?? 0;
        
        if (empty($user_id) || empty($quest_id)) {
            jsonResponse(false, null, 'User ID and Quest ID are required', 400);
        }
        
        // Check if quest already completed
        $stmt = $pdo->prepare("
            SELECT id FROM user_quest_progress 
            WHERE user_id = ? AND quest_id = ? AND is_completed = 1
        ");
        $stmt->execute([$user_id, $quest_id]);
        
        if ($stmt->fetch()) {
            jsonResponse(false, null, 'Quest already completed', 400);
        }
        
        // Mark quest as completed
        $stmt = $pdo->prepare("
            UPDATE user_quest_progress 
            SET is_completed = 1,
                completion_time_seconds = ?,
                final_score = ?,
                completed_at = CURRENT_TIMESTAMP
            WHERE user_id = ? AND quest_id = ?
        ");
        $stmt->execute([$completion_time, $final_score, $user_id, $quest_id]);
        
        // Award bonus coins and update progress
        $stmt = $pdo->prepare("
            UPDATE user_progress 
            SET coin_count = coin_count + ?,
                total_score = total_score + ?,
                quests_completed = quests_completed + 1,
                last_save_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$bonus_coins, $final_score, $user_id]);
        
        // Log quest completion
        $stmt = $pdo->prepare("
            INSERT INTO quest_completion_log (user_id, quest_id, completion_time, final_score, bonus_coins)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $quest_id, $completion_time, $final_score, $bonus_coins]);
        
        jsonResponse(true, [
            'bonus_coins' => $bonus_coins,
            'final_score' => $final_score,
            'message' => 'Quest completed successfully'
        ]);
        
    } catch (Exception $e) {
        logError('Complete quest error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to complete quest', 500);
    }
}

function updateCoins($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        $coins_earned = $_POST['coins_earned'] ?? 0;
        $reason = $_POST['reason'] ?? 'Game activity';
        
        if (empty($user_id)) {
            jsonResponse(false, null, 'User ID is required', 400);
        }
        
        // Update coin count
        $stmt = $pdo->prepare("
            UPDATE user_progress 
            SET coin_count = coin_count + ?,
                last_save_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$coins_earned, $user_id]);
        
        // Log coin transaction
        $stmt = $pdo->prepare("
            INSERT INTO coin_transactions (user_id, amount, transaction_type, reason, created_at)
            VALUES (?, ?, 'earned', ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$user_id, $coins_earned, $reason]);
        
        // Get updated coin count
        $stmt = $pdo->prepare("SELECT coin_count FROM user_progress WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        jsonResponse(true, [
            'coins_earned' => $coins_earned,
            'total_coins' => $result['coin_count'] ?? 0,
            'message' => 'Coins updated successfully'
        ]);
        
    } catch (Exception $e) {
        logError('Update coins error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to update coins', 500);
    }
}

function updateScore($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        $points = $_POST['points'] ?? 0;
        $activity_type = $_POST['activity_type'] ?? 'gameplay';
        
        if (empty($user_id)) {
            jsonResponse(false, null, 'User ID is required', 400);
        }
        
        // Update total score
        $stmt = $pdo->prepare("
            UPDATE user_progress 
            SET total_score = total_score + ?,
                last_save_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([$points, $user_id]);
        
        // Log score update
        $stmt = $pdo->prepare("
            INSERT INTO score_log (user_id, points_earned, activity_type, earned_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$user_id, $points, $activity_type]);
        
        // Get updated total score
        $stmt = $pdo->prepare("SELECT total_score FROM user_progress WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        jsonResponse(true, [
            'points_earned' => $points,
            'total_score' => $result['total_score'] ?? 0,
            'message' => 'Score updated successfully'
        ]);
        
    } catch (Exception $e) {
        logError('Update score error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to update score', 500);
    }
}

function createPlayerRecord($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        $username = $_POST['username'] ?? '';
        $section_id = $_POST['section_id'] ?? 0;
        
        if (empty($user_id) || empty($username)) {
            jsonResponse(false, null, 'User ID and username are required', 400);
        }
        
        // Check if player record already exists
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->fetch()) {
            jsonResponse(false, null, 'Player record already exists', 400);
        }
        
        // Create player record
        $stmt = $pdo->prepare("
            INSERT INTO players (user_id, username, section_id, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$user_id, $username, $section_id]);
        $player_id = $pdo->lastInsertId();
        
        // Create initial user progress record
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (
                user_id, current_stage, overall_progress, coin_count, 
                total_score, playtime_hours, quests_completed, last_save_date
            ) VALUES (?, 1, 0, 0, 0, 0, 0, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE last_save_date = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$user_id]);
        
        // Create initial game progress record
        $stmt = $pdo->prepare("
            INSERT INTO game_progress (
                player_id, current_stage, progress_percentage, coins, 
                last_played, created_at, updated_at
            ) VALUES (?, 1, 0, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$player_id]);
        
        jsonResponse(true, [
            'player_id' => $player_id,
            'message' => 'Player record created successfully'
        ]);
        
    } catch (Exception $e) {
        logError('Create player error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to create player record', 500);
    }
}

function getPlayerData($pdo) {
    try {
        $user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? 0;
        
        if (empty($user_id)) {
            jsonResponse(false, null, 'User ID is required', 400);
        }
        
        // Get comprehensive player data
        $stmt = $pdo->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                u.email,
                p.id as player_id,
                p.section_id,
                s.section_name,
                s.grade_level,
                up.current_stage,
                up.overall_progress,
                up.coin_count,
                up.total_score,
                up.playtime_hours,
                up.quests_completed,
                up.last_save_date,
                gp.progress_percentage,
                gp.last_played,
                COUNT(DISTINCT qa.id) as total_attempts,
                COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.id END) as correct_attempts,
                COUNT(DISTINCT uqp.quest_id) as quests_attempted,
                COUNT(DISTINCT CASE WHEN uqp.is_completed = 1 THEN uqp.quest_id END) as quests_completed_count
            FROM users u
            LEFT JOIN players p ON u.id = p.user_id
            LEFT JOIN sections s ON p.section_id = s.id
            LEFT JOIN user_progress up ON u.id = up.user_id
            LEFT JOIN game_progress gp ON p.id = gp.player_id
            LEFT JOIN quiz_attempts qa ON u.id = qa.user_id
            LEFT JOIN user_quest_progress uqp ON u.id = uqp.user_id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$user_id]);
        $playerData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$playerData) {
            jsonResponse(false, null, 'Player not found', 404);
        }
        
        // Get recent quiz attempts
        $stmt = $pdo->prepare("
            SELECT 
                qa.question_id,
                qa.quest_id,
                qa.selected_answer,
                qa.is_correct,
                qa.points_earned,
                qa.time_spent,
                qa.attempt_number,
                qa.attempted_at,
                q.title as quest_title
            FROM quiz_attempts qa
            LEFT JOIN quests q ON qa.quest_id = q.id
            WHERE qa.user_id = ?
            ORDER BY qa.attempted_at DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
        $recentAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get quest progress details
        $stmt = $pdo->prepare("
            SELECT 
                uqp.*,
                q.title as quest_title,
                q.description as quest_description,
                q.total_questions
            FROM user_quest_progress uqp
            LEFT JOIN quests q ON uqp.quest_id = q.id
            WHERE uqp.user_id = ?
            ORDER BY uqp.last_attempt_date DESC
        ");
        $stmt->execute([$user_id]);
        $questProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate accuracy rate
        $accuracy_rate = $playerData['total_attempts'] > 0 
            ? round(($playerData['correct_attempts'] / $playerData['total_attempts']) * 100, 2)
            : 0;
        
        $responseData = [
            'player_info' => $playerData,
            'recent_attempts' => $recentAttempts,
            'quest_progress' => $questProgress,
            'statistics' => [
                'accuracy_rate' => $accuracy_rate,
                'average_playtime' => $playerData['playtime_hours'],
                'completion_rate' => $playerData['quests_attempted'] > 0 
                    ? round(($playerData['quests_completed_count'] / $playerData['quests_attempted']) * 100, 2)
                    : 0
            ]
        ];
        
        jsonResponse(true, $responseData);
        
    } catch (Exception $e) {
        logError('Get player data error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to retrieve player data', 500);
    }
}

// Additional functions for teacher dashboard functionality

function getStudentsBySection($pdo) {
    try {
        $section_id = $_GET['section_id'] ?? 0;
        $teacher_id = $_GET['teacher_id'] ?? 0;
        
        if (empty($section_id) || empty($teacher_id)) {
            jsonResponse(false, null, 'Section ID and Teacher ID are required', 400);
        }
        
        // Verify teacher has access to this section
        $stmt = $pdo->prepare("
            SELECT id FROM sections 
            WHERE id = ? AND teacher_id = ?
        ");
        $stmt->execute([$section_id, $teacher_id]);
        
        if (!$stmt->fetch()) {
            jsonResponse(false, null, 'Access denied to this section', 403);
        }
        
        // Get students with their progress
        $stmt = $pdo->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                u.email,
                u.full_name,
                up.current_stage,
                up.overall_progress,
                up.coin_count,
                up.total_score,
                up.playtime_hours,
                up.quests_completed,
                up.last_save_date,
                COUNT(DISTINCT qa.id) as total_quiz_attempts,
                COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.id END) as correct_attempts,
                CASE 
                    WHEN COUNT(DISTINCT qa.id) > 0 
                    THEN ROUND((COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.id END) / COUNT(DISTINCT qa.id)) * 100, 2)
                    ELSE 0 
                END as accuracy_rate
            FROM users u
            JOIN players p ON u.id = p.user_id
            LEFT JOIN user_progress up ON u.id = up.user_id
            LEFT JOIN quiz_attempts qa ON u.id = qa.user_id
            WHERE p.section_id = ?
            GROUP BY u.id
            ORDER BY up.total_score DESC, u.full_name
        ");
        $stmt->execute([$section_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, $students);
        
    } catch (Exception $e) {
        logError('Get students by section error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to retrieve students', 500);
    }
}

function getStudentQuizAttempts($pdo) {
    try {
        $student_id = $_GET['student_id'] ?? 0;
        $teacher_id = $_GET['teacher_id'] ?? 0;
        
        if (empty($student_id) || empty($teacher_id)) {
            jsonResponse(false, null, 'Student ID and Teacher ID are required', 400);
        }
        
        // Verify teacher has access to this student
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM sections s
            JOIN players p ON s.id = p.section_id
            WHERE p.user_id = ? AND s.teacher_id = ?
        ");
        $stmt->execute([$student_id, $teacher_id]);
        
        if (!$stmt->fetch()) {
            jsonResponse(false, null, 'Access denied to this student', 403);
        }
        
        // Get quiz attempts grouped by quest
        $stmt = $pdo->prepare("
            SELECT 
                q.title as quiz_name,
                qa.quest_id,
                COUNT(qa.id) as total_attempts,
                COUNT(CASE WHEN qa.is_correct = 1 THEN 1 END) as correct_attempts,
                MAX(qa.attempted_at) as last_attempt,
                AVG(qa.time_spent) as avg_time_spent,
                SUM(qa.points_earned) as total_points
            FROM quiz_attempts qa
            JOIN quests q ON qa.quest_id = q.id
            WHERE qa.user_id = ?
            GROUP BY qa.quest_id
            ORDER BY qa.quest_id
        ");
        $stmt->execute([$student_id]);
        $quizSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get detailed attempts
        $stmt = $pdo->prepare("
            SELECT 
                qa.*,
                q.title as quiz_name,
                qq.question_text,
                qq.correct_answer
            FROM quiz_attempts qa
            JOIN quests q ON qa.quest_id = q.id
            JOIN quiz_questions qq ON qa.question_id = qq.id
            WHERE qa.user_id = ?
            ORDER BY qa.attempted_at DESC
        ");
        $stmt->execute([$student_id]);
        $detailedAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, [
            'quiz_summary' => $quizSummary,
            'detailed_attempts' => $detailedAttempts
        ]);
        
    } catch (Exception $e) {
        logError('Get student quiz attempts error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to retrieve quiz attempts', 500);
    }
}

function getSectionLeaderboard($pdo) {
    try {
        $section_id = $_GET['section_id'] ?? 0;
        $teacher_id = $_GET['teacher_id'] ?? 0;
        $limit = $_GET['limit'] ?? 50;
        
        if (empty($section_id) || empty($teacher_id)) {
            jsonResponse(false, null, 'Section ID and Teacher ID are required', 400);
        }
        
        // Verify teacher has access to this section
        $stmt = $pdo->prepare("
            SELECT id FROM sections 
            WHERE id = ? AND teacher_id = ?
        ");
        $stmt->execute([$section_id, $teacher_id]);
        
        if (!$stmt->fetch()) {
            jsonResponse(false, null, 'Access denied to this section', 403);
        }
        
        // Get leaderboard
        $stmt = $pdo->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                u.full_name,
                up.total_score,
                up.coin_count,
                up.current_stage,
                up.overall_progress,
                up.quests_completed,
                up.playtime_hours,
                RANK() OVER (ORDER BY up.total_score DESC) as rank_position
            FROM users u
            JOIN players p ON u.id = p.user_id
            JOIN user_progress up ON u.id = up.user_id
            WHERE p.section_id = ?
            ORDER BY up.total_score DESC, up.overall_progress DESC
            LIMIT ?
        ");
        $stmt->execute([$section_id, (int)$limit]);
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(true, $leaderboard);
        
    } catch (Exception $e) {
        logError('Get section leaderboard error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to retrieve leaderboard', 500);
    }
}

// Real-time sync helper function
function syncGameState($pdo, $user_id) {
    try {
        // This function can be called periodically to ensure data consistency
        $stmt = $pdo->prepare("
            SELECT 
                up.*,
                COUNT(DISTINCT qa.id) as quiz_attempts,
                COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.id END) as correct_answers,
                COUNT(DISTINCT uqp.quest_id) as active_quests
            FROM user_progress up
            LEFT JOIN quiz_attempts qa ON up.user_id = qa.user_id
            LEFT JOIN user_quest_progress uqp ON up.user_id = uqp.user_id AND uqp.is_completed = 0
            WHERE up.user_id = ?
            GROUP BY up.user_id
        ");
        $stmt->execute([$user_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        logError('Sync game state error: ' . $e->getMessage());
        return false;
    }
}

?>