<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration - Update these to match your setup
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$password = 'Filibustero_capstone08';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    registerTeacher($pdo);
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Simple test endpoint
    if (isset($_GET['test'])) {
        echo json_encode(['success' => true, 'message' => 'PHP connection working!']);
        exit;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'get_sections') {
        $sections = getAvailableSections($pdo);
        echo json_encode(['success' => true, 'sections' => $sections]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

function registerTeacher($pdo) {
    // Log all received data for debugging
    error_log("Registration attempt - POST data: " . print_r($_POST, true));
    
    // Get form data
    $idNumber = trim($_POST['id_number'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $sections = $_POST['sections'] ?? [];
    
    // Log processed data
    error_log("Processed data - ID: $idNumber, Name: $fullName, Email: $email, Username: $username, Sections: " . print_r($sections, true));
    
    // Validate required fields
    if (empty($idNumber) || empty($fullName) || empty($email) || empty($username) || empty($password)) {
        error_log("Validation failed: Missing required fields");
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Validation failed: Invalid email format");
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    // Validate sections is an array and not empty
    if (!is_array($sections) || empty($sections)) {
        error_log("Validation failed: No sections selected");
        echo json_encode(['success' => false, 'error' => 'At least one section must be selected']);
        return;
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        error_log("Validation failed: Password too short");
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        return;
    }
    
    // Validate username format (alphanumeric and underscores only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        error_log("Validation failed: Invalid username format");
        echo json_encode(['success' => false, 'error' => 'Username can only contain letters, numbers, and underscores']);
        return;
    }
    
    // Check if username already exists
    if (usernameExists($pdo, $username)) {
        error_log("Validation failed: Username exists");
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        return;
    }
    
    // Check if email already exists
    if (emailExists($pdo, $email)) {
        error_log("Validation failed: Email exists");
        echo json_encode(['success' => false, 'error' => 'Email address already registered']);
        return;
    }
    
    // Check if ID number already exists
    if (idNumberExists($pdo, $idNumber)) {
        error_log("Validation failed: ID number exists");
        echo json_encode(['success' => false, 'error' => 'ID number already exists']);
        return;
    }
    
    // Validate sections against allowed sections
    $allowedSections = [
        'BTLED - ICT', 'BTLED - HE', 'BTLED - IA',
        'BS INFOTECH', 'BSED - MATH', 'BSED - ENGLISH',
        'BSED -SOCIAL STUDIES', 'BINDTECH - AT', 'BINDTECH - CT',
        'BS - COMTECH', 'BINDTECH - MT', 'BS BIOLOGY'
    ];
    
    foreach ($sections as $section) {
        if (!in_array($section, $allowedSections)) {
            error_log("Validation failed: Invalid section - $section");
            echo json_encode(['success' => false, 'error' => 'Invalid section selected: ' . htmlspecialchars($section)]);
            return;
        }
    }
    
    error_log("All validations passed, starting database transaction");
    
    try {
        $pdo->beginTransaction();
        error_log("Transaction started");
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        error_log("Password hashed");
        
        // Insert into users table with teacher type and email
        $sql = "INSERT INTO users (user_type, id_number, full_name, email, username, password_hash, temp_password, created_at, updated_at) 
                VALUES ('teacher', :id_number, :full_name, :email, :username, :password_hash, 0, NOW(), NOW())";
        
        error_log("Executing users insert query: $sql");
        error_log("With parameters: ID=$idNumber, Name=$fullName, Email=$email, Username=$username");
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':id_number' => $idNumber,
            ':full_name' => $fullName,
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => $passwordHash
        ]);
        
        if (!$result) {
            error_log("Users insert failed: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Failed to insert user");
        }
        
        $userId = $pdo->lastInsertId();
        error_log("User inserted successfully with ID: $userId");
        
        // Verify the user was actually inserted
        $verifyStmt = $pdo->prepare("SELECT id, username, email, user_type FROM users WHERE id = :id");
        $verifyStmt->execute([':id' => $userId]);
        $userRecord = $verifyStmt->fetch();
        error_log("User verification: " . print_r($userRecord, true));
        
        // Insert into teachers table with is_active = 0 (pending approval)
        $teacherSql = "INSERT INTO teachers (user_id, is_active) VALUES (:user_id, 0)";
        error_log("Executing teachers insert query: $teacherSql with user_id=$userId");
        
        $stmt = $pdo->prepare($teacherSql);
        $result = $stmt->execute([':user_id' => $userId]);
        
        if (!$result) {
            error_log("Teachers insert failed: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Failed to insert teacher record");
        }
        
        error_log("Teacher record inserted successfully");
        
        // Insert assigned sections into teacher_sections table
        $sectionSql = "INSERT INTO teacher_sections (teacher_id, section) VALUES (:teacher_id, :section)";
        $stmt = $pdo->prepare($sectionSql);
        
        foreach ($sections as $section) {
            error_log("Inserting section: $section for teacher_id: $userId");
            $result = $stmt->execute([
                ':teacher_id' => $userId,
                ':section' => $section
            ]);
            
            if (!$result) {
                error_log("Section insert failed: " . print_r($stmt->errorInfo(), true));
                throw new Exception("Failed to insert section: $section");
            }
        }
        
        error_log("All sections inserted successfully");
        
        // Create user_progress record for the teacher (optional but consistent with schema)
        try {
            $progressSql = "INSERT INTO user_progress (user_id, coins, score, playtime_hours, current_stage, coin_count, 
                                         completed_quests, map_changes, playtime_seconds, collected_items, created_at, updated_at) 
                            VALUES (:user_id, 0, 0, 0, 1, 0, 0, 0, 0, 0, NOW(), NOW())";
            
            $stmt = $pdo->prepare($progressSql);
            $stmt->execute([':user_id' => $userId]);
            error_log("User progress record created");
        } catch (PDOException $e) {
            // This might fail due to constraints, but it's not critical for teacher registration
            error_log("User progress creation warning: " . $e->getMessage());
        }
        
        $pdo->commit();
        error_log("Transaction committed successfully");
        
        // Final verification - check if user exists in database
        $finalCheck = $pdo->prepare("SELECT u.id, u.username, u.email, u.user_type, t.is_active FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.id = :id");
        $finalCheck->execute([':id' => $userId]);
        $finalResult = $finalCheck->fetch();
        error_log("Final verification: " . print_r($finalResult, true));
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registration successful! Your teacher account is pending admin approval. You will receive an email once approved.',
            'user_id' => $userId,
            'debug_info' => [
                'user_created' => true,
                'teacher_record_created' => true,
                'sections_count' => count($sections),
                'email_registered' => $email,
                'user_verification' => $finalResult
            ]
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("PDO Exception in registration: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Check if it's a duplicate entry error
        if ($e->getCode() == 23000) {
            if (strpos($e->getMessage(), 'username') !== false) {
                echo json_encode(['success' => false, 'error' => 'Username already exists']);
            } elseif (strpos($e->getMessage(), 'id_number') !== false) {
                echo json_encode(['success' => false, 'error' => 'ID number already exists']);
            } elseif (strpos($e->getMessage(), 'email') !== false) {
                echo json_encode(['success' => false, 'error' => 'Email address already registered']);
            } else {
                echo json_encode(['success' => false, 'error' => 'A record with this information already exists']);
            }
        } else {
            echo json_encode([
                'success' => false, 
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("General Exception in registration: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Registration error: ' . $e->getMessage()
        ]);
    }
}

function usernameExists($pdo, $username) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Username check error: " . $e->getMessage());
        return false;
    }
}

function emailExists($pdo, $email) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Email check error: " . $e->getMessage());
        return false;
    }
}

function idNumberExists($pdo, $idNumber) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = :id_number");
        $stmt->execute([':id_number' => $idNumber]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("ID number check error: " . $e->getMessage());
        return false;
    }
}

// Optional: Function to get available sections from database (if you want dynamic sections)
function getAvailableSections($pdo) {
    try {
        // This would require a sections table, or you can keep the hardcoded array
        $sections = [
            'BTLED - ICT', 'BTLED - HE', 'BTLED - IA',
            'BS INFOTECH', 'BSED - MATH', 'BSED - ENGLISH',
            'BSED -SOCIAL STUDIES', 'BINDTECH - AT', 'BINDTECH - CT',
            'BS - COMTECH', 'BINDTECH - MT', 'BS BIOLOGY'
        ];
        return $sections;
    } catch (PDOException $e) {
        error_log("Get sections error: " . $e->getMessage());
        return [];
    }
}
?>