<?php
header('Content-Type: application/json');
require_once 'config.php'; // Changed from 'db_connect.php' to match register.php

$conn = getDBConnection(); // Using the same connection method as register.php

// Handle both POST and GET requests for testing
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = $_GET;
}

$action = $input['action'] ?? '';
$teacherId = $input['teacher_id'] ?? 0;

// If no action provided, show available actions for testing
if (empty($action)) {
    echo json_encode([
        'success' => false,
        'error' => 'No action provided',
        'available_actions' => ['get_student_progress', 'get_student_attempts', 'get_teacher_sections'],
        'usage' => 'Add ?action=get_teacher_sections&teacher_id=1 to test'
    ]);
    exit;
}

switch ($action) {
    case 'get_student_progress':
        getStudentProgress($teacherId, $conn);
        break;
    case 'get_student_attempts':
        getStudentAttempts($teacherId, $conn);
        break;
    case 'get_teacher_sections': // Added new action to debug sections
        getTeacherSections($teacherId, $conn);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

// New function to debug teacher sections
function getTeacherSections($teacherId, $conn) {
    try {
        $stmt = $conn->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
        $sections = [];
        
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row['section'];
        }
        
        echo json_encode([
            'success' => true, 
            'sections' => $sections,
            'teacher_id' => $teacherId
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getStudentProgress($teacherId, $conn) {
    try {
        // Get the sections this teacher handles
        $stmt = $conn->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row['section'];
        }
        
        if (empty($sections)) {
            echo json_encode([
                'success' => false, 
                'error' => 'No sections assigned',
                'debug_info' => ['teacher_id' => $teacherId]
            ]);
            return;
        }
        
        // Get students in these sections with their progress
        $placeholders = str_repeat('?,', count($sections) - 1) . '?';
        
        // Updated query to match your table structure
        $query = "
            SELECT 
                u.id, u.id_number, u.full_name, s.section, s.year_level,
                up.progress_percentage, up.last_save, up.current_scene
            FROM users u
            JOIN students s ON u.id = s.user_id
            LEFT JOIN user_progress up ON u.id = up.user_id
            WHERE s.section IN ($placeholders)
            ORDER BY s.section, u.full_name
        ";
        
        $stmt = $conn->prepare($query);
        
        // Bind parameters for sections
        $types = str_repeat('s', count($sections));
        $stmt->bind_param($types, ...$sections);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'students' => $students,
            'sections_found' => $sections
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getStudentAttempts($teacherId, $conn) {
    try {
        // Get the sections this teacher handles
        $stmt = $conn->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row['section'];
        }
        
        if (empty($sections)) {
            echo json_encode([
                'success' => false, 
                'error' => 'No sections assigned',
                'debug_info' => ['teacher_id' => $teacherId]
            ]);
            return;
        }
        
        // Get students in these sections with their quiz attempts
        $placeholders = str_repeat('?,', count($sections) - 1) . '?';
        
        // Updated query to match your table structure
        $query = "
            SELECT 
                u.id, u.id_number, u.full_name, s.section,
                up.quiz_attempts, up.quiz_scores
            FROM users u
            JOIN students s ON u.id = s.user_id
            LEFT JOIN user_progress up ON u.id = up.user_id
            WHERE s.section IN ($placeholders)
            ORDER BY s.section, u.full_name
        ";
        
        $stmt = $conn->prepare($query);
        
        // Bind parameters for sections
        $types = str_repeat('s', count($sections));
        $stmt->bind_param($types, ...$sections);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            // Decode quiz attempts JSON
            if ($row['quiz_attempts']) {
                $row['quiz_attempts'] = json_decode($row['quiz_attempts'], true);
            } else {
                $row['quiz_attempts'] = [];
            }
            
            if ($row['quiz_scores']) {
                $row['quiz_scores'] = json_decode($row['quiz_scores'], true);
            } else {
                $row['quiz_scores'] = [];
            }
            
            $students[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'students' => $students,
            'sections_found' => $sections
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

$conn->close();
?>