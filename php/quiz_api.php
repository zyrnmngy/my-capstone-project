<?php
// =============================================
// QUIZ TRACKING API ENDPOINTS
// File: quiz_api.php
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$servername = "localhost";
$username = "u769346877_filibustero";
$password = "Filibustero_capstone08";
$dbname = "u769346877_filibustero_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'submit_quiz_answer':
        submitQuizAnswer($pdo);
        break;
    case 'start_quiz_session':
        startQuizSession($pdo);
        break;
    case 'end_quiz_session':
        endQuizSession($pdo);
        break;
    case 'get_student_quiz_summary':
        getStudentQuizSummary($pdo);
        break;
    case 'get_student_question_details':
        getStudentQuestionDetails($pdo);
        break;
    case 'get_section_quiz_overview':
        getSectionQuizOverview($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

// =============================================
// SUBMIT QUIZ ANSWER
// =============================================
function submitQuizAnswer($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? null;
        $question_id = $_POST['question_id'] ?? null;
        $quest_id = $_POST['quest_id'] ?? null;
        $selected_answer = $_POST['selected_answer'] ?? null;
        $time_spent = $_POST['time_spent'] ?? 0;
        
        if (!$user_id || !$question_id || !$quest_id) {
            throw new Exception('Missing required parameters');
        }
        
        // Get correct answer and points
        $stmt = $pdo->prepare("SELECT correct_answer, points_value FROM questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$question) {
            throw new Exception('Question not found');
        }
        
        $is_correct = ($selected_answer === $question['correct_answer']) ? 1 : 0;
        $points_earned = $is_correct ? $question['points_value'] : 0;
        
        // Get attempt number for this user/question combination
        $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as attempt_num FROM quiz_attempts WHERE user_id = ? AND question_id = ?");
        $stmt->execute([$user_id, $question_id]);
        $attempt_num = $stmt->fetchColumn();
        
        // Insert quiz attempt
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (user_id, question_id, quest_id, selected_answer, is_correct, points_earned, attempt_number, time_spent_seconds) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $question_id, $quest_id, $selected_answer, $is_correct, $points_earned, $attempt_num, $time_spent]);
        
        // Update user progress
        updateUserProgress($pdo, $user_id);
        
        echo json_encode([
            'success' => true,
            'is_correct' => $is_correct,
            'points_earned' => $points_earned,
            'attempt_number' => $attempt_num,
            'correct_answer' => $question['correct_answer']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// =============================================
// START QUIZ SESSION
// =============================================
function startQuizSession($pdo) {
    try {
        $user_id = $_POST['user_id'] ?? null;
        $quest_id = $_POST['quest_id'] ?? null;
        
        if (!$user_id || !$quest_id) {
            throw new Exception('Missing required parameters');
        }
        
        // Get total questions for this quest
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quest_id = ?");
        $stmt->execute([$quest_id]);
        $total_questions = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            INSERT INTO quiz_sessions (user_id, quest_id, total_questions) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $quest_id, $total_questions]);
        
        echo json_encode([
            'success' => true,
            'session_id' => $pdo->lastInsertId(),
            'total_questions' => $total_questions
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// =============================================
// END QUIZ SESSION
// =============================================
function endQuizSession($pdo) {
    try {
        $session_id = $_POST['session_id'] ?? null;
        $correct_answers = $_POST['correct_answers'] ?? 0;
        $total_score = $_POST['total_score'] ?? 0;
        $completion_time = $_POST['completion_time'] ?? 0;
        
        if (!$session_id) {
            throw new Exception('Missing session ID');
        }
        
        $stmt = $pdo->prepare("
            UPDATE quiz_sessions 
            SET session_end = NOW(), correct_answers = ?, total_score = ?, completion_time_seconds = ?, is_completed = 1 
            WHERE id = ?
        ");
        $stmt->execute([$correct_answers, $total_score, $completion_time, $session_id]);
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// =============================================
// GET STUDENT QUIZ SUMMARY
// =============================================
function getStudentQuizSummary($pdo) {
    try {
        $user_id = $_GET['user_id'] ?? null;
        
        if (!$user_id) {
            throw new Exception('Missing user ID');
        }
        
        // Get overall quiz statistics
        $stmt = $pdo->prepare("
            SELECT 
                u.full_name,
                s.section,
                COUNT(DISTINCT qa.question_id) as questions_attempted,
                COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.question_id END) as questions_correct,
                ROUND(COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.question_id END) * 100.0 / NULLIF(COUNT(DISTINCT qa.question_id), 0), 2) as accuracy_percentage,
                SUM(CASE WHEN qa.is_correct = 1 THEN qa.points_earned ELSE 0 END) as total_points,
                COUNT(qa.id) as total_attempts
            FROM users u
            LEFT JOIN students s ON u.id = s.user_id
            LEFT JOIN quiz_attempts qa ON u.id = qa.user_id
            WHERE u.id = ?
            GROUP BY u.id, u.full_name, s.section
        ");
        $stmt->execute([$user_id]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get quiz performance by quest
        $stmt = $pdo->prepare("
            SELECT 
                q.quest_name,
                q.stage,
                COUNT(qa.id) as attempts,
                COUNT(CASE WHEN qa.is_correct = 1 THEN 1 END) as correct,
                SUM(qa.points_earned) as points
            FROM quiz_attempts qa
            JOIN questions qs ON qa.question_id = qs.id
            JOIN quests q ON qs.quest_id = q.id
            WHERE qa.user_id = ?
            GROUP BY q.id, q.quest_name, q.stage
            ORDER BY q.stage, q.quest_order
        ");
        $stmt->execute([$user_id]);
        $quest_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'quest_performance' => $quest_performance
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// =============================================
// GET STUDENT QUESTION DETAILS
// =============================================
function getStudentQuestionDetails($pdo) {
    try {
        $user_id = $_GET['user_id'] ?? null;
        $question_id = $_GET['question_id'] ?? null;
        
        if (!$user_id) {
            throw new Exception('Missing user ID');
        }
        
        $sql = "
            SELECT 
                q.question_text,
                q.option_a,
                q.option_b,
                q.option_c,
                q.option_d,
                q.correct_answer,
                q.explanation,
                q.difficulty,
                q.points_value,
                qa.selected_answer,
                qa.is_correct,
                qa.points_earned,
                qa.attempt_number,
                qa.time_spent_seconds,
                qa.answered_at,
                qst.quest_name,
                qst.stage
            FROM quiz_attempts qa
            JOIN questions q ON qa.question_id = q.id
            JOIN quests qst ON q.quest_id = qst.id
            WHERE qa.user_id = ?
        ";
        
        $params = [$user_id];
        
        if ($question_id) {
            $sql .= " AND qa.question_id = ?";
            $params[] = $question_id;
        }
        
        $sql .= " ORDER BY qa.answered_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'attempts' => $attempts
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// =============================================
// GET SECTION QUIZ OVERVIEW
// =============================================
function getSectionQuizOverview($pdo) {
    try {
        $teacher_id = $_GET['teacher_id'] ?? null;
        $section = $_GET['section'] ?? null;
        
        if (!$teacher_id || !$section) {
            throw new Exception('Missing required parameters');
        }
        
        // Verify teacher has access to this section
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_sections WHERE teacher_id = ? AND section = ?");
        $stmt->execute([$teacher_id, $section]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Access denied to this section');
        }
        
        // Get student quiz performance for the section
        $stmt = $pdo->prepare("
            SELECT 
                u.id as user_id,
                u.full_name,
                COUNT(DISTINCT qa.question_id) as questions_attempted,
                COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.question_id END) as questions_correct,
                ROUND(COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.question_id END) * 100.0 / NULLIF(COUNT(DISTINCT qa.question_id), 0), 2) as accuracy_percentage,
                SUM(CASE WHEN qa.is_correct = 1 THEN qa.points_earned ELSE 0 END) as total_points,
                COUNT(qa.id) as total_attempts,
                MAX(qa.answered_at) as last_attempt
            FROM users u
            JOIN students s ON u.id = s.user_id
            LEFT JOIN quiz_attempts qa ON u.id = qa.user_id
            WHERE s.section = ? AND u.user_type = 'student'
            GROUP BY u.id, u.full_name
            ORDER BY accuracy_percentage DESC, total_points DESC
        ");
        $stmt->execute([$section]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'section' => $section,
            'students' => $students
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// =============================================
// UPDATE USER PROGRESS
// =============================================
function updateUserProgress($pdo, $user_id) {
    try {
        // Calculate quiz progress
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT qa.question_id) as attempted,
                COUNT(DISTINCT CASE WHEN qa.is_correct = 1 THEN qa.question_id END) as correct,
                SUM(qa.points_earned) as total_score
            FROM quiz_attempts qa
            WHERE qa.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get total questions available
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions");
        $stmt->execute();
        $total_questions = $stmt->fetchColumn();
        
        $quiz_progress = $total_questions > 0 ? ($stats['attempted'] / $total_questions) * 100 : 0;
        
        // Update user_progress table
        $stmt = $pdo->prepare("
            UPDATE user_progress 
            SET 
                quiz_progress = ?,
                total_quiz_score = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([min($quiz_progress, 100), $stats['total_score'] ?? 0, $user_id]);
        
    } catch (Exception $e) {
        error_log("Failed to update user progress: " . $e->getMessage());
    }
}

?>