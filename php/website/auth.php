<?php
// Prevent any output before JSON - MUST BE FIRST
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to output
ini_set('log_errors', 1); // Log errors instead

// Start output buffering BEFORE any other code
ob_start();

require_once 'config.php';

// PHPMailer for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only require PHPMailer if the file exists
$phpmailer_available = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $phpmailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');
}

// Clean any unwanted output
$unwanted_output = ob_get_clean();
if (!empty($unwanted_output)) {
    error_log("Unwanted output detected: " . $unwanted_output);
}

// Start clean output buffering for JSON
ob_start();

setCORSHeaders();

try {
    $pdo = getDBConnection();
    
    // Start session if needed (after headers are set)
    startSessionIfNeeded();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $token = $_POST['token'] ?? $_GET['token'] ?? '';
    
    error_log("Auth request - Action: $action");
    
    switch ($action) {
        case 'login':
            handleLogin($pdo);
            break;
            
        case 'register':
            handleRegister($pdo);
            break;
            
        case 'forgot_password':
            global $phpmailer_available;
            handleForgotPasswordEmail($pdo, $phpmailer_available);
            break;
                    
        case 'change_password':
            handleChangePassword($pdo);
            break;
            
        case 'verify_session':
            handleVerifySession($pdo);
            break;
            
        case 'get_teacher_sections':
            if (!$token) {
                jsonResponse(false, null, 'Token required');
            }
            
            $verification = verifyToken($pdo, $token);
            if (!$verification['success']) {
                jsonResponse(false, null, $verification['error']);
            }
            
            $result = getTeacherSections($pdo, $verification['user']['id']);
            if ($result['success']) {
                jsonResponse(true, $result);
            } else {
                jsonResponse(false, null, $result['error']);
            }
            break;

        case 'update_teacher_profile':
            if (!$token) {
                jsonResponse(false, null, 'Token required');
            }
            
            $verification = verifyToken($pdo, $token);
            if (!$verification['success']) {
                jsonResponse(false, null, $verification['error']);
            }
            
            if ($verification['user']['user_type'] !== 'teacher') {
                jsonResponse(false, null, 'Access denied');
            }
            
            $requiredFields = ['current_username', 'current_password', 'id_number', 'full_name'];
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                    jsonResponse(false, null, "Missing required field: $field");
                }
            }
            
            $updateData = [
                'user_id' => $verification['user']['id'],
                'current_username' => trim($_POST['current_username']),
                'current_password' => $_POST['current_password'],
                'id_number' => trim($_POST['id_number']),
                'full_name' => trim($_POST['full_name']),
                'new_username' => isset($_POST['new_username']) ? trim($_POST['new_username']) : '',
                'new_full_name' => isset($_POST['new_full_name']) ? trim($_POST['new_full_name']) : '',
                'new_id_number' => isset($_POST['new_id_number']) ? trim($_POST['new_id_number']) : '',
                'new_password' => isset($_POST['new_password']) ? $_POST['new_password'] : '',
                'sections_to_add' => isset($_POST['sections_to_add']) ? $_POST['sections_to_add'] : '',
                'sections_to_remove' => isset($_POST['sections_to_remove']) ? $_POST['sections_to_remove'] : ''
            ];
            
            $result = updateTeacherProfile($pdo, $updateData);
            if ($result['success']) {
                jsonResponse(true, $result);
            } else {
                jsonResponse(false, null, $result['error']);
            }
            break;

        case 'logout':
            if ($token) {
                invalidateSession($pdo, $token);
            }
            jsonResponse(true, ['message' => 'Logged out successfully']);
            break;

        default:
            jsonResponse(false, null, 'Invalid action', 400);
    }
    
} catch (Exception $e) {
    error_log('Auth error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    jsonResponse(false, null, 'System error occurred', 500);
}

function handleLogin($pdo) {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        error_log("Login attempt for username: $username");
        
        if (empty($username) || empty($password)) {
            jsonResponse(false, null, 'Username and password are required', 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   CASE 
                       WHEN t.user_id IS NOT NULL THEN 'teacher'
                       WHEN s.user_id IS NOT NULL THEN 'student' 
                       ELSE u.user_type 
                   END as actual_user_type,
                   t.is_active as teacher_is_active
            FROM users u 
            LEFT JOIN teachers t ON u.id = t.user_id 
            LEFT JOIN students s ON u.id = s.user_id 
            WHERE u.username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("User not found: $username");
            jsonResponse(false, null, 'Account not found', 401);
        }
        
        error_log("User found. Temp password flag: " . ($user['temp_password'] ?? '0'));
        error_log("Reset expires: " . ($user['reset_expires'] ?? 'NULL'));
        
        // Check if temporary password has expired
        if ($user['temp_password'] == 1 && $user['reset_expires']) {
            $expires_timestamp = strtotime($user['reset_expires']);
            $current_timestamp = time();
            
            error_log("Checking expiration - Expires: $expires_timestamp, Current: $current_timestamp");
            
            if ($expires_timestamp < $current_timestamp) {
                error_log("Temporary password expired for user: $username");
                jsonResponse(false, null, 'Temporary password has expired. Please request a new one.', 401);
            }
        }
        
        if ($user['actual_user_type'] === 'teacher' && $user['teacher_is_active'] == 0) {
            error_log("Teacher account not activated: $username");
            jsonResponse(false, null, 'Your teacher account is not yet activated. Please contact the administrator.', 403);
        }
        
        // Verify password
        $password_valid = password_verify($password, $user['password_hash']);
        error_log("Password verification result: " . ($password_valid ? 'SUCCESS' : 'FAILED'));
        
        if (!$password_valid) {
            error_log("Invalid password for user: $username");
            jsonResponse(false, null, 'Incorrect password', 401);
        }
        
        // Clear temporary password flag on successful login
        if ($user['temp_password'] == 1) {
            error_log("Clearing temp password flag for user: $username");
            $stmt = $pdo->prepare("UPDATE users SET temp_password = 0, reset_expires = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
        }
        
        // Generate session token
        $tokenData = [
            'user_id' => (int)$user['id'],
            'username' => $user['username'],
            'expires' => time() + (24 * 60 * 60)
        ];
        $sessionToken = base64_encode(json_encode($tokenData));
        $expiresAt = date('Y-m-d H:i:s', $tokenData['expires']);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['expires_at'] = $expiresAt;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                session_token = VALUES(session_token), 
                expires_at = VALUES(expires_at)
            ");
            $stmt->execute([$user['id'], $sessionToken, $expiresAt]);
        } catch (Exception $e) {
            error_log('Session table not available: ' . $e->getMessage());
        }
        
        $response = [
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'id_number' => $user['id_number'],
                'user_type' => $user['actual_user_type']
            ],
            'token' => $sessionToken,
            'expires_at' => $expiresAt
        ];
        
        // Note: Don't set temp_password flag in response after clearing it
        // It was already cleared above
        
        if ($user['actual_user_type'] === 'teacher') {
            $stmt = $pdo->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
            $stmt->execute([$user['id']]);
            $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $response['user']['sections'] = $sections;
        }
        
        if ($user['actual_user_type'] === 'student') {
            $stmt = $pdo->prepare("SELECT section, year_level, rizal_professor FROM students WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $studentInfo = $stmt->fetch();
            if ($studentInfo) {
                $response['user']['section'] = $studentInfo['section'];
                $response['user']['year_level'] = $studentInfo['year_level'];
                $response['user']['professor'] = $studentInfo['rizal_professor'];
            }
        }
        
        error_log("Login successful for user: $username");
        jsonResponse(true, $response);
        
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        jsonResponse(false, null, 'Login failed', 500);
    }
}

function handleRegister($pdo) {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $user_type = $_POST['user_type'] ?? 'student';
        
        if (empty($username) || empty($password) || empty($full_name) || empty($id_number)) {
            jsonResponse(false, null, 'All fields are required', 400);
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, null, 'Invalid email format', 400);
        }
        
        if (strlen($password) < 6) {
            jsonResponse(false, null, 'Password must be at least 6 characters', 400);
        }
        
        $checkQuery = "SELECT id FROM users WHERE username = ? OR id_number = ?";
        $checkParams = [$username, $id_number];
        
        if (!empty($email)) {
            $checkQuery .= " OR email = ?";
            $checkParams[] = $email;
        }
        
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute($checkParams);
        if ($stmt->fetch()) {
            jsonResponse(false, null, 'Username, ID number, or email already exists', 409);
        }
        
        $pdo->beginTransaction();
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (user_type, id_number, full_name, email, username, password_hash) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_type, $id_number, $full_name, $email, $username, $password_hash]);
        $user_id = $pdo->lastInsertId();
        
        if ($user_type === 'student') {
            $section = $_POST['section'] ?? 'BSED -SOCIAL STUDIES';
            $year_level = $_POST['year_level'] ?? '1';
            $professor = $_POST['professor'] ?? 'Prof. Charlene Etcubanas';
            
            $stmt = $pdo->prepare("
                INSERT INTO students (user_id, section, year_level, rizal_professor) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $section, $year_level, $professor]);
            
        } elseif ($user_type === 'teacher') {
            $stmt = $pdo->prepare("INSERT INTO teachers (user_id, is_active) VALUES (?, 0)");
            $stmt->execute([$user_id]);
            
            $sections = $_POST['sections'] ?? [];
            if (is_string($sections)) {
                $sections = explode(',', $sections);
            }
            if (empty($sections)) {
                $sections = ['BTLED - ICT', 'BTLED - HE', 'BTLED - IA'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO teacher_sections (teacher_id, section) VALUES (?, ?)");
            foreach ($sections as $section) {
                $section = trim($section);
                if (!empty($section)) {
                    $stmt->execute([$user_id, $section]);
                }
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO user_progress (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        $message = 'Registration successful';
        if ($user_type === 'teacher') {
            $message .= '. Your teacher account is pending activation by an administrator.';
        }
        
        jsonResponse(true, [
            'message' => $message,
            'user_id' => $user_id
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Registration error: ' . $e->getMessage());
        jsonResponse(false, null, 'Registration failed', 500);
    }
}

function handleForgotPasswordEmail($pdo, $phpmailer_available) {
    try {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        if (empty($email)) {
            jsonResponse(false, null, 'Email address is required', 400);
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, null, 'Invalid email address', 400);
            return;
        }
        
        error_log("🔍 Forgot password request for email: $email");
        
        $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("❌ No user found with email: $email");
            jsonResponse(true, [
                'message' => 'If an account exists with this email, you will receive a password reset link shortly.'
            ]);
            return;
        }
        
        error_log("✅ User found: " . $user['username'] . " - " . $user['full_name']);
        
        // Generate temporary password
        $temp_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        $reset_expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        error_log("Generated temp password (not logged in production): [REDACTED]");
        error_log("Reset expires at: $reset_expires");
        
        // Update database
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, temp_password = 1, reset_expires = ? 
            WHERE id = ?
        ");
        $result = $stmt->execute([$hashed_password, $reset_expires, $user['id']]);
        
        if (!$result) {
            error_log("❌ Failed to update database");
            jsonResponse(false, null, 'Failed to process password reset', 500);
            return;
        }
        
        error_log("📝 Database updated successfully");
        
        // Send email
        $email_sent = sendPasswordResetEmailSimple([
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'username' => $user['username']
        ], $temp_password);
        
        if ($email_sent) {
            error_log("✅ Password reset email sent successfully");
            jsonResponse(true, [
                'message' => 'Password reset email sent successfully. Please check your inbox.',
                'email' => $email
            ]);
        } else {
            error_log("❌ Failed to send email");
            jsonResponse(false, null, 'Failed to send email. Please try again later.', 500);
        }
        
    } catch (Exception $e) {
        error_log('💥 Forgot password error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        jsonResponse(false, null, 'An error occurred while processing your request', 500);
    }
}

function handleChangePassword($pdo) {
    try {
        $username = trim($_POST['username'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($username) || empty($current_password) || empty($new_password)) {
            jsonResponse(false, null, 'All fields are required', 400);
        }
        
        if (strlen($new_password) < 6) {
            jsonResponse(false, null, 'New password must be at least 6 characters', 400);
        }
        
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, null, 'Account not found', 404);
        }
        
        if (!password_verify($current_password, $user['password_hash'])) {
            jsonResponse(false, null, 'Current password is incorrect', 401);
        }
        
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, temp_password = 0, reset_expires = NULL, password_changed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_password_hash, $user['id']]);
        
        jsonResponse(true, ['message' => 'Password changed successfully']);
        
    } catch (Exception $e) {
        error_log('Change password error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to change password', 500);
    }
}

function handleVerifySession($pdo) {
    try {
        $token = $_POST['token'] ?? $_GET['token'] ?? '';
        
        if (empty($token)) {
            jsonResponse(false, null, 'Token is required', 400);
        }
        
        $verification = verifyToken($pdo, $token);
        if ($verification['success']) {
            jsonResponse(true, [
                'valid' => true,
                'user_id' => $verification['user']['id']
            ]);
        } else {
            jsonResponse(false, null, $verification['error'], 401);
        }
        
    } catch (Exception $e) {
        error_log('Session verification error: ' . $e->getMessage());
        jsonResponse(false, null, 'Session verification failed', 500);
    }
}

function verifyToken($pdo, $token) {
    try {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return ['success' => false, 'error' => 'Invalid token format'];
        }
        
        $tokenData = json_decode($decoded, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid token structure'];
        }
        
        if (!isset($tokenData['user_id'], $tokenData['expires'])) {
            return ['success' => false, 'error' => 'Invalid token data'];
        }
        
        if ($tokenData['expires'] < time()) {
            return ['success' => false, 'error' => 'Token expired'];
        }
        
        $stmt = $pdo->prepare("SELECT id, username, full_name, id_number, user_type FROM users WHERE id = ?");
        $stmt->execute([$tokenData['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        return [
            'success' => true,
            'user' => $user
        ];
        
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Token verification failed'];
    }
}

function getTeacherSections($pdo, $teacher_id) {
    try {
        $stmt = $pdo->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return [
            'success' => true,
            'sections' => $sections
        ];
    } catch (PDOException $e) {
        error_log("Get teacher sections error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to retrieve sections'
        ];
    }
}

function updateTeacherProfile($pdo, $data) {
    try {
        $user_id = $data['user_id'];
        
        $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, id_number FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }
        
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            return [
                'success' => false,
                'error' => 'Current password is incorrect'
            ];
        }
        
        $pdo->beginTransaction();
        
        $updates = [];
        $params = [];
        
        if (!empty($data['new_username']) && $data['new_username'] !== $user['username']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$data['new_username'], $user['id']]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'error' => 'Username already exists'
                ];
            }
            $updates[] = "username = ?";
            $params[] = $data['new_username'];
        }
        
        if (!empty($data['new_full_name']) && $data['new_full_name'] !== $user['full_name']) {
            $updates[] = "full_name = ?";
            $params[] = $data['new_full_name'];
        }
        
        if (!empty($data['new_id_number']) && $data['new_id_number'] !== $user['id_number']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $stmt->execute([$data['new_id_number'], $user['id']]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'error' => 'ID Number already exists'
                ];
            }
            $updates[] = "id_number = ?";
            $params[] = $data['new_id_number'];
        }
        
        if (!empty($data['new_password'])) {
            $updates[] = "password_hash = ?, temp_password = 0, reset_expires = NULL, password_changed_at = NOW()";
            $params[] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }
        
        if ($data['full_name'] !== $user['full_name']) {
            $updates[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        
        if ($data['id_number'] !== $user['id_number']) {
            $updates[] = "id_number = ?";
            $params[] = $data['id_number'];
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $user['id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        if (isset($data['sections_to_remove']) && !empty($data['sections_to_remove'])) {
            $sectionsToRemove = json_decode($data['sections_to_remove'], true);
            if ($sectionsToRemove && is_array($sectionsToRemove)) {
                $placeholders = str_repeat('?,', count($sectionsToRemove) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM teacher_sections WHERE teacher_id = ? AND section IN ($placeholders)");
                $stmt->execute(array_merge([$user['id']], $sectionsToRemove));
            }
        }
        
        if (isset($data['sections_to_add']) && !empty($data['sections_to_add'])) {
            $sectionsToAdd = json_decode($data['sections_to_add'], true);
            if ($sectionsToAdd && is_array($sectionsToAdd)) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO teacher_sections (teacher_id, section) VALUES (?, ?)");
                foreach ($sectionsToAdd as $section) {
                    $stmt->execute([$user['id'], $section]);
                }
            }
        }
        
        $stmt = $pdo->prepare("SELECT id, username, full_name, id_number FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $updatedUser = $stmt->fetch();
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Profile updated successfully',
            'updated_user' => [
                'id' => (int)$updatedUser['id'],
                'username' => $updatedUser['username'],
                'full_name' => $updatedUser['full_name'],
                'id_number' => $updatedUser['id_number'],
                'user_type' => 'teacher'
            ]
        ];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Update teacher profile error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Database error occurred'
        ];
    }
}

function invalidateSession($pdo, $token) {
    return [
        'success' => true,
        'message' => 'Session invalidated'
    ];
}

function sendPasswordResetEmailSimple($user, $tempPassword) {
    $to = $user['email'];
    $subject = "Filibustero Game - Password Reset";
    
    $message = "
    <html>
    <head><title>Password Reset</title></head>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 5px;'>
            <h2 style='color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px;'>Password Reset Request</h2>
            <p>Hello <strong>{$user['full_name']}</strong>,</p>
            <p>You requested a password reset for your Filibustero Game account.</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #007bff; border-radius: 3px;'>
                <p style='margin: 0;'><strong>Username:</strong> {$user['username']}</p>
                <p style='margin: 10px 0 0 0;'><strong>Temporary Password:</strong> 
                    <span style='font-size: 20px; color: #007bff; font-weight: bold; letter-spacing: 2px;'>{$tempPassword}</span>
                </p>
            </div>
            
            <div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 3px;'>
                <h3 style='margin-top: 0; color: #856404;'>⚠️ Important Security Notice:</h3>
                <ul style='margin: 0; padding-left: 20px; color: #856404;'>
                    <li>This temporary password expires in <strong>1 hour</strong></li>
                    <li>Please login immediately and change your password</li>
                    <li>Do not share this password with anyone</li>
                    <li>If you didn't request this, please contact support immediately</li>
                </ul>
            </div>
            
            <p>After logging in with your temporary password, you will be required to set a new permanent password for security.</p>
            
            <div style='margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd;'>
                <p style='color: #666; font-size: 12px; margin: 0;'>
                    This is an automated email from Filibustero Game. Please do not reply to this message.<br>
                    If you need assistance, please contact your teacher or administrator.
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
        error_log("Email send attempt to $to: " . ($result ? 'SUCCESS' : 'FAILED'));
        return $result;
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}
?>