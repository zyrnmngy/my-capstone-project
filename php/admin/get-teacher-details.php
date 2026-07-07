<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid teacher ID']);
    exit;
}

$teacher_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.id_number, u.user_type, u.created_at, t.is_active
        FROM users u 
        JOIN teachers t ON u.id = t.user_id 
        WHERE u.id = ? AND u.user_type = 'teacher'
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
    
    if ($teacher) {
        echo json_encode(['success' => true, 'teacher' => $teacher]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Teacher not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>