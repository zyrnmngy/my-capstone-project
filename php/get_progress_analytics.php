<?php
// =============================================
// get_progress_analytics.php
// =============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$password = 'Filibustero_capstone08';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    $action = $_POST['action'] ?? null;
    
    switch ($action) {
        case 'section_analytics':
            getSectionAnalytics($pdo, $_POST['section'] ?? '');
            break;
            
        case 'teacher_overview':
            getTeacherOverview($pdo, $_POST['teacher_id'] ?? 0);
            break;
            
        case 'student_quest_details':
            getStudentQuestDetails($pdo, $_POST['student_id'] ?? 0);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

function getSectionAnalytics($pdo, $section) {
    if (!$section) {
        echo json_encode(['success' => false, 'error' => 'Section is required']);
        return;
    }
    
    // Get section statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(u.id) as total_students,
            AVG(up.overall_progress) as avg_progress,
            MAX(up.overall_progress) as max_progress,
            MIN(up.overall_progress) as min_progress,
            AVG(up.current_stage) as avg_stage,
            MAX(up.current_stage) as max_stage,
            MIN(up.current_stage) as min_stage,
            SUM(up.playtime_hours) as total_playtime,
            AVG(up.playtime_hours) as avg_playtime,
            COUNT(CASE WHEN up.overall_progress >= 80 THEN 1 END) as high_performers,
            COUNT(CASE WHEN up.overall_progress < 50 THEN 1 END) as struggling_students
        FROM users u
        INNER JOIN students s ON u.id = s.user_id
        LEFT JOIN user_progress up ON u.id = up.user_id
        WHERE s.section = ? AND u.user_type = 'student'
    ");
    
    $stmt->execute([$section]);
    $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get stage distribution
    $stageStmt = $pdo->prepare("
        SELECT 
            up.current_stage,
            COUNT(*) as student_count
        FROM users u
        INNER JOIN students s ON u.id = s.user_id
        LEFT JOIN user_progress up ON u.id = up.user_id
        WHERE s.section = ? AND u.user_type = 'student'
        GROUP BY up.current_stage
        ORDER BY up.current_stage
    ");
    
    $stageStmt->execute([$section]);
    $stageDistribution = $stageStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics,
        'stage_distribution' => $stageDistribution
    ]);
}

function getTeacherOverview($pdo, $teacher_id) {
    if (!$teacher_id) {
        echo json_encode(['success' => false, 'error' => 'Teacher ID is required']);
        return;
    }
    
    // Get teacher's overall statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.user_id) as total_students,
            COUNT(DISTINCT ts.section) as total_sections,
            AVG(up.overall_progress) as avg_progress,
            SUM(up.playtime_hours) as total_playtime,
            COUNT(CASE WHEN up.overall_progress >= 80 THEN 1 END) as high_performers,
            COUNT(CASE WHEN up.overall_progress < 50 THEN 1 END) as struggling_students,
            COUNT(CASE WHEN up.last_save_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_this_week
        FROM teacher_sections ts
        INNER JOIN students s ON ts.section = s.section
        INNER JOIN users u ON s.user_id = u.id
        LEFT JOIN user_progress up ON u.id = up.user_id
        WHERE ts.teacher_id = ? AND u.user_type = 'student'
    ");
    
    $stmt->execute([$teacher_id]);
    $overview = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get section breakdown
    $sectionStmt = $pdo->prepare("
        SELECT 
            ts.section,
            COUNT(s.user_id) as student_count,
            AVG(up.overall_progress) as avg_progress,
            COUNT(CASE WHEN up.overall_progress >= 80 THEN 1 END) as high_performers
        FROM teacher_sections ts
        LEFT JOIN students s ON ts.section = s.section
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN user_progress up ON u.id = up.user_id
        WHERE ts.teacher_id = ?
        GROUP BY ts.section
        ORDER BY ts.section
    ");
    
    $sectionStmt->execute([$teacher_id]);
    $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'overview' => $overview,
        'sections' => $sections
    ]);
}

function getStudentQuestDetails($pdo, $student_id) {
    if (!$student_id) {
        echo json_encode(['success' => false, 'error' => 'Student ID is required']);
        return;
    }
    
    // Try to find player data by matching user info
    $userStmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
    $userStmt->execute([$student_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    // Find matching player record
    $playerStmt = $pdo->prepare("
        SELECT id as player_id FROM players 
        WHERE player_name = ? OR player_name LIKE ?
    ");
    $playerStmt->execute([$user['full_name'], '%' . $user['username'] . '%']);
    $player = $playerStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        echo json_encode([
            'success' => true,
            'quest_details' => [],
            'message' => 'No game progress found for this student'
        ]);
        return;
    }
    
    // Get detailed quest progress
    $questStmt = $pdo->prepare("
        SELECT 
            q.id as quest_id,
            q.quest_name,
            q.stage,
            q.quest_type,
            q.points_reward,
            q.coins_reward,
            pqp.is_completed,
            pqp.score_earned,
            pqp.coins_earned,
            pqp.attempts,
            pqp.completed_at,
            COUNT(qu.id) as question_count,
            COUNT(CASE WHEN pa.is_correct = 1 THEN 1 END) as correct_answers,
            COUNT(pa.id) as total_answers
        FROM quests q
        LEFT JOIN player_quest_progress pqp ON q.id = pqp.quest_id AND pqp.player_id = ?
        LEFT JOIN questions qu ON q.id = qu.quest_id
        LEFT JOIN player_answers pa ON qu.id = pa.question_id AND pa.player_id = ?
        WHERE q.is_active = 1
        GROUP BY q.id, pqp.id
        ORDER BY q.stage, q.quest_order
    ");
    
    $questStmt->execute([$player['player_id'], $player['player_id']]);
    $questDetails = $questStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'quest_details' => $questDetails,
        'player_id' => $player['player_id']
    ]);
}