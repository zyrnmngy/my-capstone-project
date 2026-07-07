<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output (they break JSON)
ini_set('log_errors', 1);     // Log errors to PHP error log instead

// Start output buffering to catch any unexpected output
ob_start();

try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    // Clean output buffer and send error
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Section number to name mapping function
function mapSectionNumbers($sectionNumbers) {
    $sectionMapping = [
        1 => "BTLED - ICT",
        2 => "BTLED - HE",
        3 => "BTLED - IA",
        4 => "BS INFOTECH",
        5 => "BSED - MATH",
        6 => "BSED - ENGLISH",
        7 => "BSED -SOCIAL STUDIES",
        8 => "BINDTECH - AT",
        9 => "BINDTECH - CT",
        10 => "BS - COMTECH",
        11 => "BINDTECH - MT",
        12 => "BS BIOLOGY"
    ];
    
    $sectionNames = [];
    foreach ($sectionNumbers as $num) {
        $num = (int)trim($num);
        if (isset($sectionMapping[$num])) {
            $sectionNames[] = $sectionMapping[$num];
        }
    }
    
    return $sectionNames;
}

// Input sanitization function
function sanitizeInput($input) {
    return trim(htmlspecialchars(strip_tags($input)));
}

// ID number validation function
function validateIdNumber($idNumber) {
    return preg_match('/^\d{2}L-\d{4,5}$/', $idNumber);
}

// ✅ NEW: Email validation function
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Required fields validation function
function validateRequiredFields($fields, $input) {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($input[$field])) {
            $errors[] = "Field '$field' is required";
        }
    }
    return $errors;
}

// Enable error logging for debugging
error_log("=== REGISTER REQUEST RECEIVED ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET params: " . print_r($_GET, true));
error_log("POST data: " . print_r($_POST, true));

// Handle both JSON and form data
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    if ($jsonInput) {
        $input = $jsonInput;
        error_log("JSON input received: " . print_r($input, true));
    }
} else {
    $input = $_POST;
    error_log("Form data received: " . print_r($input, true));
}

// Merge with GET parameters
$input = array_merge($_GET, $input);

// Determine action
$action = $input['action'] ?? '';
error_log("Action determined: " . $action);

// Clean any unexpected output before processing
ob_clean();

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'forgot_password':
        handleForgotPassword();
        break;
    case 'change_password':
        handleChangePassword();
        break;
    case 'edit_account':
        handleEditAccount();
        break;
    case 'get_progress':
        handleGetProgress();
        break;
    case 'update_progress':
        handleUpdateProgress();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function handleRegister() {
    global $pdo, $input;
    
    error_log("=== REGISTER REQUEST ===");
    error_log("Input data: " . print_r($input, true));
    
    $userType = sanitizeInput($input['user_type'] ?? '');
    error_log("User type: " . $userType);
    
    // ✅ UPDATED: Added email to required fields
    $requiredFields = ['id_number', 'full_name', 'email', 'username', 'password'];
    $errors = validateRequiredFields($requiredFields, $input);
    
    if (!empty($errors)) {
        error_log("Validation errors: " . implode(', ', $errors));
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
        return;
    }
    
    $idNumber = sanitizeInput($input['id_number']);
    $fullName = sanitizeInput($input['full_name']);
    $email = sanitizeInput($input['email']); // ✅ NEW
    $username = sanitizeInput($input['username']);
    $password = sanitizeInput($input['password']);
    
    // Validate ID number format
    if (!validateIdNumber($idNumber)) {
        error_log("Invalid ID number format: " . $idNumber);
        echo json_encode(['success' => false, 'error' => 'Invalid ID Number format. Use XXL-XXXX format (e.g., 23L-4567)']);
        return;
    }
    
    // ✅ NEW: Validate email format
    if (!validateEmail($email)) {
        error_log("Invalid email format: " . $email);
        echo json_encode(['success' => false, 'error' => 'Invalid email format. Please enter a valid email address.']);
        return;
    }
    
    // Validate username length
    if (strlen($username) < 3) {
        error_log("Username too short: " . $username);
        echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters']);
        return;
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        error_log("Password too short");
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        return;
    }
    
    try {
        // ✅ UPDATED: Check if username, ID number, OR email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id_number = ? OR email = ?");
        $stmt->execute([$username, $idNumber, $email]);
        
        if ($stmt->fetch()) {
            error_log("Username, ID number, or email already exists: $username, $idNumber, $email");
            echo json_encode(['success' => false, 'error' => 'Username, ID Number, or Email already exists']);
            return;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        $pdo->beginTransaction();
        error_log("Started transaction");
        
        // ✅ UPDATED: Insert into users table WITH email
        $stmt = $pdo->prepare("INSERT INTO users (user_type, id_number, full_name, email, username, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$userType, $idNumber, $fullName, $email, $username, $passwordHash]);
        
        if (!$result) {
            throw new Exception("Failed to create user");
        }
        
        $userId = $pdo->lastInsertId();
        error_log("User created with ID: " . $userId);
        
        // Handle specific user types
        switch ($userType) {
            case 'student':
                registerStudentData($userId, $fullName, $email); // ✅ Pass email
                break;
                
            case 'teacher':
                registerTeacherData($userId);
                break;
                
            case 'admin':
                throw new Exception("Admin registration is not allowed through this form");
                break;
                
            default:
                throw new Exception("Invalid user type: " . $userType);
        }
        
        // Create initial progress record
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (
                user_id, coins, score, current_stage, completed_quests, 
                map_changes, coin_count, collected_items, playtime_seconds,
                created_at, updated_at
            ) VALUES (?, 0, 0, 1, 0, 0, 0, 0, 0, NOW(), NOW())
        ");
        $stmt->execute([$userId]);
        error_log("User progress record created");
        
        // ✅ NEW: Send welcome email
        sendWelcomeEmail([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'id_number' => $idNumber
        ]);
        
        // Commit transaction
        $pdo->commit();
        error_log("Transaction committed successfully");
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $userId,  // Keep for backward compatibility
            'user' => [           // Add user object to match login format
                'id' => $userId,
                'user_type' => $userType,
                'id_number' => $idNumber,
                'full_name' => $fullName,
                'email' => $email, // ✅ NEW
                'username' => $username
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Registration error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// ✅ UPDATED: Added email parameters
function registerStudentData($userId, $fullName, $email) {
    global $pdo, $input;
    
    $studentFields = ['section', 'year_level', 'rizal_professor'];
    $studentErrors = validateRequiredFields($studentFields, $input);
    
    if (!empty($studentErrors)) {
        throw new Exception("Student registration error: " . implode(', ', $studentErrors));
    }
    
    $section = sanitizeInput($input['section']);
    $yearLevel = sanitizeInput($input['year_level']);
    $rizalProfessor = sanitizeInput($input['rizal_professor']);
    
    error_log("Creating student record: Section=$section, Year=$yearLevel, Professor=$rizalProfessor");
    
    $stmt = $pdo->prepare("INSERT INTO students (user_id, section, year_level, rizal_professor) VALUES (?, ?, ?, ?)");
    $result = $stmt->execute([$userId, $section, $yearLevel, $rizalProfessor]);
    
    if (!$result) {
        throw new Exception("Failed to create student record");
    }
    
    error_log("Student record created successfully");
    
    // ✅ NEW: Insert into players table with email
    try {
        $stmt = $pdo->prepare("
            INSERT INTO players (user_id, player_name, email, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$userId, $fullName, $email]);
        error_log("Player record created with email: $email");
    } catch (PDOException $e) {
        error_log("Note: Players table might not exist or email column missing: " . $e->getMessage());
        // Don't fail registration if players table doesn't exist
    }
}

function registerTeacherData($userId) {
    global $pdo, $input;
    
    if (empty($input['sections'])) {
        throw new Exception("At least one section is required for teachers");
    }
    
    // Handle both array and comma-separated string
    $rawSections = is_array($input['sections']) ? $input['sections'] : explode(',', $input['sections']);
    $rawSections = array_map('trim', $rawSections);
    
    error_log("Raw sections received: " . print_r($rawSections, true));
    
    // Check if sections are numbers (from RPG Maker) or names
    $firstSection = reset($rawSections);
    if (is_numeric($firstSection)) {
        // Convert section numbers to section names
        $sections = mapSectionNumbers($rawSections);
        if (empty($sections)) {
            throw new Exception("Invalid section numbers provided");
        }
        error_log("Converted section numbers to names: " . print_r($sections, true));
    } else {
        // Already section names, just sanitize
        $sections = array_map('sanitizeInput', $rawSections);
        error_log("Using provided section names: " . print_r($sections, true));
    }
    
    // Create teacher record (if teachers table exists)
    try {
        $stmt = $pdo->prepare("INSERT INTO teachers (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        error_log("Teacher record created");
    } catch (PDOException $e) {
        error_log("Teachers table might not exist: " . $e->getMessage());
    }
    
    // Insert teacher sections
    $stmt = $pdo->prepare("INSERT INTO teacher_sections (teacher_id, section) VALUES (?, ?)");
    foreach ($sections as $section) {
        $stmt->execute([$userId, $section]);
        error_log("Added section '$section' for teacher ID $userId");
    }
    
    error_log("Teacher data created successfully with " . count($sections) . " sections");
}

// ✅ NEW: Send welcome email function
function sendWelcomeEmail($user) {
    $to = $user['email'];
    $subject = "Welcome to Filibustero Game!";
    
    $message = "
    <html>
    <head><title>Welcome to Filibustero!</title></head>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 5px;'>
            <h2 style='color: #4CAF50; border-bottom: 2px solid #4CAF50; padding-bottom: 10px;'>
                Welcome to Filibustero Game!
            </h2>
            <p>Hello <strong>{$user['full_name']}</strong>,</p>
            <p>Your account has been successfully created. Thank you for joining Filibustero Game!</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 3px;'>
                <h3 style='margin-top: 0; color: #333;'>Your Account Details:</h3>
                <p style='margin: 5px 0;'><strong>Username:</strong> {$user['username']}</p>
                <p style='margin: 5px 0;'><strong>ID Number:</strong> {$user['id_number']}</p>
                <p style='margin: 5px 0;'><strong>Email:</strong> {$user['email']}</p>
            </div>
            
            <div style='background: #e3f2fd; padding: 15px; margin: 20px 0; border-left: 4px solid #2196F3; border-radius: 3px;'>
                <h3 style='margin-top: 0; color: #1976D2;'>Getting Started:</h3>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li>You can now login and start playing!</li>
                    <li>Learn about Philippine history through gameplay</li>
                    <li>Complete quests and earn rewards</li>
                    <li>Track your progress and compete with classmates</li>
                </ul>
            </div>
            
            <p>If you have any questions or need assistance, please contact your teacher or administrator.</p>
            
            <div style='margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd;'>
                <p style='color: #666; font-size: 12px; margin: 0;'>
                    This is an automated email from Filibustero Game. Please do not reply to this message.<br>
                    Keep your login credentials secure and do not share them with anyone.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Filibustero Game <noreply@filibustero-web.com>" . "\r\n";
    $headers .= "Reply-To: support@filibustero-web.com" . "\r\n";
    
    try {
        $result = mail($to, $subject, $message, $headers);
        error_log("Welcome email send attempt to $to: " . ($result ? 'SUCCESS' : 'FAILED'));
        return $result;
    } catch (Exception $e) {
        error_log("Welcome email error: " . $e->getMessage());
        return false;
    }
}

function handleUpdateProgress() {
    global $pdo;
    
    // Get all possible progress fields from POST data
    $userId = $_POST['user_id'] ?? '';
    $coins = intval($_POST['coins'] ?? 0);
    $score = intval($_POST['score'] ?? 0);
    $currentStage = intval($_POST['current_stage'] ?? 1);
    $completedQuests = intval($_POST['completed_quests'] ?? 0);
    $mapChanges = intval($_POST['map_changes'] ?? 0);
    $coinCount = intval($_POST['coin_count'] ?? 0);
    $collectedItems = intval($_POST['collected_items'] ?? 0);
    $playtimeSeconds = intval($_POST['playtime_seconds'] ?? 0);
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    try {
        // First check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        // Update or insert progress data
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (
                user_id, coins, score, current_stage, completed_quests, 
                map_changes, coin_count, collected_items, playtime_seconds, 
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                coins = VALUES(coins),
                score = VALUES(score),
                current_stage = VALUES(current_stage),
                completed_quests = VALUES(completed_quests),
                map_changes = VALUES(map_changes),
                coin_count = VALUES(coin_count),
                collected_items = VALUES(collected_items),
                playtime_seconds = VALUES(playtime_seconds),
                updated_at = NOW()
        ");
        
        $result = $stmt->execute([
            $userId, $coins, $score, $currentStage, 
            $completedQuests, $mapChanges, $coinCount, 
            $collectedItems, $playtimeSeconds
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Progress updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update progress']);
        }
        
    } catch (PDOException $e) {
        error_log("Update progress error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleGetProgress() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_progress WHERE user_id = ?");
        $stmt->execute([$userId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            $progressData = [
                'coins' => intval($progress['coins']),
                'score' => intval($progress['score']),
                'current_stage' => intval($progress['current_stage']),
                'completed_quests' => intval($progress['completed_quests']),
                'map_changes' => intval($progress['map_changes']),
                'coin_count' => intval($progress['coin_count']),
                'collected_items' => intval($progress['collected_items']),
                'playtime_seconds' => intval($progress['playtime_seconds'])
            ];
            
            echo json_encode([
                'success' => true,
                'progress_data' => $progressData
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No progress data found']);
        }
        
    } catch (PDOException $e) {
        error_log("Get progress error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function getUserProgressData($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_progress WHERE user_id = ?");
        $stmt->execute([$userId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            return [
                'coins' => intval($progress['coins']),
                'score' => intval($progress['score']),
                'current_stage' => intval($progress['current_stage']),
                'completed_quests' => intval($progress['completed_quests']),
                'map_changes' => intval($progress['map_changes']),
                'coin_count' => intval($progress['coin_count']),
                'collected_items' => intval($progress['collected_items']),
                'playtime_seconds' => intval($progress['playtime_seconds']),
                'updated_at' => $progress['updated_at']
            ];
        } else {
            return [
                'coins' => 0, 'score' => 0, 'current_stage' => 1, 'completed_quests' => 0,
                'map_changes' => 0, 'coin_count' => 0, 'collected_items' => 0, 'playtime_seconds' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Error getting user progress data: " . $e->getMessage());
        return [
            'coins' => 0, 'score' => 0, 'current_stage' => 1, 'completed_quests' => 0,
            'map_changes' => 0, 'coin_count' => 0, 'collected_items' => 0, 'playtime_seconds' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}

function handleLogin() {
    global $pdo;
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Account not found']);
            return;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Incorrect password']);
            return;
        }
        
        $progressData = getUserProgressData($user['id']);
        
        // ✅ UPDATED: Added email to user data
        $userData = [
            'id' => $user['id'],
            'user_type' => $user['user_type'],
            'full_name' => $user['full_name'],
            'email' => $user['email'] ?? null, // ✅ NEW
            'username' => $user['username'],
            'progress' => $progressData
        ];
        
        if ($user['user_type'] === 'student') {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($studentData) {
                $userData = array_merge($userData, $studentData);
            }
        } elseif ($user['user_type'] === 'teacher') {
            $stmt = $pdo->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
            $stmt->execute([$user['id']]);
            $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $userData['sections'] = $sections;
        }
        
        echo json_encode([
            'success' => true,
            'user' => $userData,
            'token' => bin2hex(random_bytes(16)),
            'redirect_to' => $user['user_type'] === 'teacher' ? 'Scene_TeacherDashboard' : 'Scene_GameMenu'
        ]);
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleForgotPassword() {
    global $pdo;
    
    $idNumber = $_POST['id_number'] ?? '';
    
    if (empty($idNumber) || !validateIdNumber($idNumber)) {
        echo json_encode(['success' => false, 'error' => 'Valid ID Number is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id_number = ?");
        $stmt->execute([$idNumber]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'No account found with this ID Number']);
            return;
        }
        
        $tempPassword = bin2hex(random_bytes(4));
        $tempPasswordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $resetExpires = date('Y-m-d H:i:s', time() + 3600);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, temp_password = 1, reset_expires = ? WHERE id = ?");
        $stmt->execute([$tempPasswordHash, $resetExpires, $user['id']]);
        
        echo json_encode([
            'success' => true,
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'temp_password' => $tempPassword,
            'message' => 'Temporary password generated successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Forgot password error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    }
}

function handleChangePassword() {
    global $pdo;
    
    $username = $_POST['username'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            return;
        }
        
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, temp_password = 0, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$newPasswordHash, $user['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully',
            'redirect_to' => $user['user_type'] === 'teacher' ? 'Scene_TeacherDashboard' : 'Scene_GameMenu'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleEditAccount() {
    global $pdo, $input;
    
    if (empty($input['id_number']) || empty($input['current_username']) || empty($input['current_password'])) {
        echo json_encode(['success' => false, 'error' => 'All required fields must be filled']);
        return;
    }
    
    if (!validateIdNumber($input['id_number'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID Number format']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE id_number = ?");
        $stmt->execute([$input['id_number']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Account not found']);
            return;
        }
        
        if ($user['username'] !== $input['current_username'] || 
            !password_verify($input['current_password'], $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid current username or password']);
            return;
        }
        
        $updateFields = [];
        $updateParams = [];
        
        if (!empty($input['new_username'])) {
            $updateFields[] = 'username = ?';
            $updateParams[] = $input['new_username'];
        }
        
        if (!empty($input['new_password'])) {
            $updateFields[] = 'password_hash = ?';
            $updateParams[] = password_hash($input['new_password'], PASSWORD_DEFAULT);
        }
        
        if (empty($updateFields)) {
            echo json_encode(['success' => true, 'message' => 'No changes made']);
            return;
        }
        
        $updateParams[] = $user['id'];
        $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($updateParams);
        
        echo json_encode([
            'success' => true,
            'message' => 'Account updated successfully',
            'new_username' => $input['new_username'] ?? $user['username']
        ]);
        
    } catch (PDOException $e) {
        error_log("Edit account error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    }
}
?>