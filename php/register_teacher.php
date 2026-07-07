<?php
header('Content-Type: application/json');

// Database connection
$db = new mysqli('localhost', 'username', 'password', 'database_name');

if ($db->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$action = $_POST['action'] ?? '';
$userType = $_POST['user_type'] ?? '';

if ($action === 'register' && $userType === 'teacher') {
    // Validate and sanitize inputs
    $idNumber = $db->real_escape_string($_POST['id_number'] ?? '');
    $fullName = $db->real_escape_string($_POST['full_name'] ?? '');
    $username = $db->real_escape_string($_POST['username'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $sections = explode(',', $_POST['sections'] ?? '');
    
    // Basic validation
    if (empty($idNumber) || empty($fullName) || empty($username) || empty($password) || empty($sections)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }
    
    // Check if username exists
    $check = $db->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit;
    }
    
    // Insert teacher
    $db->query("INSERT INTO users (id_number, full_name, username, password, user_type) 
               VALUES ('$idNumber', '$fullName', '$username', '$password', 'teacher')");
    
    if ($db->affected_rows > 0) {
        $teacherId = $db->insert_id;
        
        // Insert sections
        foreach ($sections as $section) {
            $section = $db->real_escape_string($section);
            $db->query("INSERT INTO teacher_sections (teacher_id, section_name) 
                       VALUES ($teacherId, '$section')");
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Registration failed']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>