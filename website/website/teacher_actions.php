<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = 'localhost';
$dbname = 'filibustero_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_teacher_sections':
        getTeacherSections($pdo);
        break;
    case 'get_dashboard_stats':
        getDashboardStats($pdo);
        break;
    case 'get_student_progress':
        getStudentProgress($pdo);
        break;
    case 'get_section_leaderboard':
        getSectionLeaderboard($pdo);
        break;
    case 'get_quiz_attempts':
        getQuizAttempts($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function getTeacherSections($pdo) {
    try {
        $teacher_id = $_GET['teacher_id'] ?? '';
        
        if (empty($teacher_id)) {
            throw new Exception('Teacher ID is required');
        }
        
        $stmt = $pdo->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'sections' => $sections
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getDashboardStats($pdo) {
    try {
        $teacher_id = $_GET['teacher_id'] ?? '';
        
        if (empty($teacher_id)) {
            throw new Exception('Teacher ID is required');
        }
        
        // Get total students count for this teacher's sections
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT s.user_id) as total_students,
                   COALESCE(AVG(up.overall_progress), 0) as avg_progress
            FROM students s 
            LEFT JOIN user_progress up ON s.user_id = up.user_id
            WHERE s.section IN (
                SELECT section FROM teacher_sections WHERE teacher_id = ?
            )
        ");
        $stmt->execute([$teacher_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_students' => (int)$stats['total_students'],
                'avg_progress' => round($stats['avg_progress'], 1)
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getStudentProgress($pdo) {
    try {
        $teacher_id = $_GET['teacher_id'] ?? '';
        $section = $_GET['section'] ?? null;
        
        if (empty($teacher_id)) {
            throw new Exception('Teacher ID is required');
        }
        
        $sql = "
            SELECT u.id, u.full_name, s.section,
                   COALESCE(up.overall_progress, 0) as progress_percentage,
                   COALESCE(up.current_stage, 1) as current_stage,
                   COALESCE(up.coin_count, 0) as coins,
                   COALESCE(up.total_quiz_score, 0) as score,
                   COALESCE(gp.correct_answers, 0) as correct_answers,
                   COALESCE(gp.total_questions_answered, 0) as total_questions_answered,
                   gp.last_played
            FROM users u
            JOIN students s ON u.id = s.user_id
            LEFT JOIN user_progress up ON u.id = up.user_id
            LEFT JOIN players p ON u.id = p.user_id
            LEFT JOIN game_progress gp ON p.id = gp.player_id
            WHERE s.section IN (
                SELECT section FROM teacher_sections WHERE teacher_id = ?
            )";
        
        $params = [$teacher_id];
        
        if ($section) {
            $sql .= " AND s.section = ?";
            $params[] = $section;
        }
        
        $sql .= " ORDER BY u.full_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'students' => $students
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getSectionLeaderboard($pdo) {
    try {
        $teacher_id = $_GET['teacher_id'] ?? '';
        $section = $_GET['section'] ?? null;
        
        if (empty($teacher_id)) {
            throw new Exception('Teacher ID is required');
        }
        
        $sql = "
            SELECT p.player_name, s.section,
                   COALESCE(gp.score, 0) as score,
                   COALESCE(gp.coins, 0) as coins,
                   COALESCE(gp.progress_percentage, 0) as progress_percentage,
                   COALESCE(gp.current_stage, 1) as current_stage,
                   COALESCE(gp.correct_answers, 0) as correct_answers,
                   COALESCE(gp.total_questions_answered, 0) as total_questions_answered,
                   CASE 
                       WHEN gp.total_questions_answered > 0 
                       THEN ROUND((gp.correct_answers / gp.total_questions_answered) * 100, 2)
                       ELSE 0 
                   END as accuracy_percentage,
                   gp.completion_time,
                   gp.last_played
            FROM players p
            LEFT JOIN game_progress gp ON p.id = gp.player_id
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN students s ON u.id = s.user_id
            WHERE s.section IN (
                SELECT section FROM teacher_sections WHERE teacher_id = ?
            )";
        
        $params = [$teacher_id];
        
        if ($section) {
            $sql .= " AND s.section = ?";
            $params[] = $section;
        }
        
        $sql .= " ORDER BY gp.score DESC, gp.progress_percentage DESC, gp.completion_time ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'leaderboard' => $leaderboard
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getQuizAttempts($pdo) {
    try {
        $teacher_id = $_GET['teacher_id'] ?? '';
        $section = $_GET['section'] ?? null;
        
        if (empty($teacher_id)) {
            throw new Exception('Teacher ID is required');
        }
        
        $sql = "
            SELECT u.full_name as student_name, s.section,
                   q.quest_name,
                   COUNT(qa.id) as attempt_count,
                   SUM(qa.points_earned) as total_score,
                   (SELECT SUM(questions.points_value) 
                    FROM questions 
                    WHERE questions.quest_id = qa.quest_id) as max_possible_score,
                   MAX(qa.answered_at) as last_attempt
            FROM quiz_attempts qa
            JOIN users u ON qa.user_id = u.id
            JOIN students s ON u.id = s.user_id
            JOIN quests q ON qa.quest_id = q.id
            WHERE s.section IN (
                SELECT section FROM teacher_sections WHERE teacher_id = ?
            )";
        
        $params = [$teacher_id];
        
        if ($section) {
            $sql .= " AND s.section = ?";
            $params[] = $section;
        }
        
        $sql .= "
            GROUP BY qa.user_id, qa.quest_id, u.full_name, s.section, q.quest_name
            ORDER BY u.full_name, q.quest_order
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'attempts' => $attempts
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}