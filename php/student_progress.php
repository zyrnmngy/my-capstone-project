<?php
header('Content-Type: application/json');
require_once 'config.php';

$teacherId = $_POST['teacher_id'] ?? 0;

try {
    // Get sections handled by this teacher
    $stmt = $pdo->prepare("SELECT handled_sections FROM teachers WHERE user_id = ?");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        throw new Exception("Teacher not found");
    }
    
    // Get students in these sections
    $sections = explode(',', $teacher['handled_sections']);
    $placeholders = implode(',', array_fill(0, count($sections), '?'));
    
    $stmt = $pdo->prepare("
        SELECT u.id_number, u.full_name, s.section, 
               p.quiz1_score, p.quiz2_score
        FROM users u
        JOIN students s ON u.id = s.user_id
        LEFT JOIN student_progress p ON u.id = p.user_id
        WHERE s.section IN ($placeholders)
        ORDER BY s.section, u.full_name
    ");
    
    $stmt->execute($sections);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($students);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>