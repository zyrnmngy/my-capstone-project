<?php
// teacher_actions.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$password = 'Filibustero_capstone08';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_teacher_sections':
        getTeacherSections($pdo);
        break;
    case 'get_section_students':
        getSectionStudents($pdo);
        break;
    case 'get_student_details':
        getStudentDetails($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function getTeacherSections($pdo) {
    try {
        $teacher_id = $_POST['teacher_id'] ?? '';
        
        if (empty($teacher_id)) {
            echo json_encode(['success' => false, 'error' => 'Teacher ID is required']);
            return;
        }

        // Get sections assigned to this teacher with student counts
        $sql = "SELECT ts.section, COUNT(s.user_id) as student_count 
                FROM teacher_sections ts 
                LEFT JOIN students s ON s.section = ts.section 
                WHERE ts.teacher_id = ? 
                GROUP BY ts.section 
                ORDER BY ts.section";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$teacher_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'sections' => $sections]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getSectionStudents($pdo) {
    try {
        $teacher_id = $_POST['teacher_id'] ?? '';
        $section = $_POST['section'] ?? '';
        
        if (empty($teacher_id) || empty($section)) {
            echo json_encode(['success' => false, 'error' => 'Teacher ID and section are required']);
            return;
        }

        // Verify teacher has access to this section
        $check_sql = "SELECT 1 FROM teacher_sections WHERE teacher_id = ? AND section = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$teacher_id, $section]);
        
        if (!$check_stmt->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Access denied to this section']);
            return;
        }

        // Get students in the section with their progress data
        $sql = "SELECT 
                    u.id as user_id,
                    u.full_name,
                    s.section,
                    s.year_level,
                    s.rizal_professor,
                    COALESCE(up.overall_progress, 0) as overall_progress,
                    COALESCE(up.story_progress, 0) as story_progress,
                    COALESCE(up.chapter_progress, 0) as chapter_progress,
                    COALESCE(up.quest_progress, 0) as quest_progress,
                    COALESCE(up.current_stage, 1) as current_stage,
                    COALESCE(up.coin_count, 0) as coin_count,
                    COALESCE(up.completed_chapters, 0) as completed_chapters,
                    COALESCE(up.completed_quests, 0) as completed_quests,
                    COALESCE(up.collected_items, 0) as collected_items,
                    COALESCE(up.unlocked_achievements, 0) as unlocked_achievements,
                    COALESCE(up.playtime_hours, 0) as playtime_hours,
                    up.last_save_date,
                    up.created_at,
                    up.updated_at
                FROM users u
                JOIN students s ON u.id = s.user_id
                LEFT JOIN user_progress up ON u.id = up.user_id
                WHERE u.user_type = 'student' AND s.section = ?
                ORDER BY u.full_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$section]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'students' => $students]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getStudentDetails($pdo) {
    try {
        $teacher_id = $_POST['teacher_id'] ?? '';
        $student_id = $_POST['student_id'] ?? '';
        
        if (empty($teacher_id) || empty($student_id)) {
            echo json_encode(['success' => false, 'error' => 'Teacher ID and student ID are required']);
            return;
        }

        // Get student details with verification that teacher can access this student
        $sql = "SELECT 
                    u.id as user_id,
                    u.full_name,
                    s.section,
                    s.year_level,
                    s.rizal_professor,
                    up.*
                FROM users u
                JOIN students s ON u.id = s.user_id
                JOIN teacher_sections ts ON s.section = ts.section
                LEFT JOIN user_progress up ON u.id = up.user_id
                WHERE u.id = ? AND ts.teacher_id = ? AND u.user_type = 'student'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $teacher_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['success' => false, 'error' => 'Student not found or access denied']);
            return;
        }
        
        echo json_encode(['success' => true, 'student' => $student]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>