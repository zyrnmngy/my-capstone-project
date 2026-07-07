<?php
// Prevent any output before JSON
ob_start();

require_once 'config.php';

// Clean any unwanted output
$unwanted_output = ob_get_clean();
if (!empty($unwanted_output)) {
    error_log("Unwanted output detected: " . $unwanted_output);
}

// Session is already started in config.php

setCORSHeaders();

try {
    $pdo = getDBConnection();
    
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
            handleForgotPassword($pdo);
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
            // Use your config.php jsonResponse format
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
            
            // Ensure user is a teacher
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
            
        case 'upload_profile_picture':
    if (!$token) {
        jsonResponse(false, null, 'Token required');
    }
    
    $verification = verifyToken($pdo, $token);
    if (!$verification['success']) {
        jsonResponse(false, null, $verification['error']);
    }
    
    $result = uploadProfilePicture($pdo, $verification['user']['id']);
    if ($result['success']) {
        jsonResponse(true, $result);
    } else {
        jsonResponse(false, null, $result['error']);
    }
    break;

    case 'get_profile_picture':
        if (!$token) {
            jsonResponse(false, null, 'Token required');
        }
        
        $verification = verifyToken($pdo, $token);
        if (!$verification['success']) {
            jsonResponse(false, null, $verification['error']);
        }
        
        $result = getProfilePicture($pdo, $verification['user']['id']);
        if ($result['success']) {
            // Return the image data
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        } else {
            jsonResponse(false, null, $result['error']);
        }
        break;

        default:
            jsonResponse(false, null, 'Invalid action', 400);
    }
    
} catch (Exception $e) {
    error_log('Auth error: ' . $e->getMessage());
    jsonResponse(false, null, 'System error occurred: ' . $e->getMessage(), 500);
}

function handleLogin($pdo) {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
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
            jsonResponse(false, null, 'Account not found', 401);
        }
        
        // Check if teacher account is active
        if ($user['actual_user_type'] === 'teacher' && $user['teacher_is_active'] == 0) {
            jsonResponse(false, null, 'Your teacher account is not yet activated. Please contact the administrator.', 403);
        }
        
        // ✅ FIXED: Verify password FIRST (works for both regular and temp passwords)
        if (!password_verify($password, $user['password_hash'])) {
            jsonResponse(false, null, 'Incorrect password', 401);
        }
        
        // ✅ FIXED: Check temp password expiration AFTER successful password verification
        if ($user['temp_password'] == 1) {
            if (!empty($user['reset_expires']) && strtotime($user['reset_expires']) < time()) {
                jsonResponse(false, null, 'Temporary password has expired. Please request a new one via Forgot Password.', 401);
            }
        }
        
        // ✅ FIXED: DO NOT clear temp_password flag here - only when they change password
        $tempPasswordActive = ($user['temp_password'] == 1);
        
        // Create session token
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
        
        // Try to save session to database
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
        
        // Build response
        $response = [
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'id_number' => $user['id_number'],
                'user_type' => $user['actual_user_type'],
                'email' => $user['email'] ?? null // ✅ Include email
            ],
            'token' => $sessionToken,
            'expires_at' => $expiresAt
        ];
        
        // ✅ FIXED: Add temp_password flag to response
        if ($tempPasswordActive) {
            $response['temp_password'] = true;
            $response['message'] = 'You are using a temporary password. Please change it in your dashboard settings.';
        }
        
        // Add teacher sections
        if ($user['actual_user_type'] === 'teacher') {
            $stmt = $pdo->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
            $stmt->execute([$user['id']]);
            $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $response['user']['sections'] = $sections;
        }
        
        // Add student info
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
        
        jsonResponse(true, $response);
        
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        jsonResponse(false, null, 'Login failed: ' . $e->getMessage(), 500);
    }
}

function handleRegister($pdo) {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $user_type = $_POST['user_type'] ?? 'student';
        
        // Validation
        if (empty($username) || empty($password) || empty($full_name) || empty($id_number)) {
            jsonResponse(false, null, 'All fields are required', 400);
        }
        
        if (strlen($password) < 6) {
            jsonResponse(false, null, 'Password must be at least 6 characters', 400);
        }
        
        // Check if username or id_number already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id_number = ?");
        $stmt->execute([$username, $id_number]);
        if ($stmt->fetch()) {
            jsonResponse(false, null, 'Username or ID number already exists', 409);
        }
        
        $pdo->beginTransaction();
        
        // Insert user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (user_type, id_number, full_name, username, password_hash) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_type, $id_number, $full_name, $username, $password_hash]);
        $user_id = $pdo->lastInsertId();
        
        // Create related records
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
            // Teachers start as inactive by default
            $stmt = $pdo->prepare("INSERT INTO teachers (user_id, is_active) VALUES (?, 0)");
            $stmt->execute([$user_id]);
            
            // Add teacher sections
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
        
        // Create user progress record
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
        logError('Registration error: ' . $e->getMessage());
        jsonResponse(false, null, 'Registration failed: ' . $e->getMessage(), 500);
    }
}

function handleForgotPassword($pdo) {
    try {
        // ✅ CHANGED: Accept email instead of ID number
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            jsonResponse(false, null, 'Email address is required', 400);
        }
        
        // ✅ NEW: Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, null, 'Invalid email format', 400);
        }
        
        error_log("🔍 Forgot password request for email: $email");
        
        // ✅ CHANGED: Search by email instead of ID number (TEACHERS ONLY)
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.full_name, u.email, u.user_type 
            FROM users u 
            WHERE u.email = ? AND u.user_type = 'teacher'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("❌ No teacher account found with email: $email");
            // Security: Don't reveal if email exists
            jsonResponse(true, [
                'message' => 'If a teacher account exists with this email, you will receive a password reset link shortly.'
            ]);
            return;
        }
        
        error_log("✅ User found: " . $user['username'] . " - " . $user['full_name']);
        
        // ✅ Generate temporary password (8 characters, same as game)
        $temp_password = bin2hex(random_bytes(4));
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
        $reset_expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
        
        // ✅ Update with temp password AND expiry time
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, temp_password = 1, reset_expires = ? 
            WHERE id = ?
        ");
        $stmt->execute([$password_hash, $reset_expires, $user['id']]);
        
        error_log("📝 Database updated with temp password for user ID: " . $user['id']);
        
        // ✅ Send email with temporary password
        $email_sent = sendTeacherPasswordResetEmail(
            $user['email'],
            $user['full_name'],
            $user['username'],
            $temp_password
        );
        
        if ($email_sent) {
            error_log("✅ Password reset email sent successfully to: " . $user['email']);
            jsonResponse(true, [
                'message' => 'Password reset email sent successfully. Please check your inbox.',
                'email' => $email
            ]);
        } else {
            error_log("❌ Failed to send password reset email to: " . $user['email']);
            jsonResponse(false, null, 'Failed to send email. Please try again later or contact support.', 500);
        }
        
    } catch (Exception $e) {
        logError('💥 Forgot password email error: ' . $e->getMessage());
        jsonResponse(false, null, 'An error occurred while processing your request. Please try again.', 500);
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
        
        // Get user
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, null, 'Account not found', 404);
        }
        
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            jsonResponse(false, null, 'Current password is incorrect', 401);
        }
        
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, temp_password = 0 
            WHERE id = ?
        ");
        $stmt->execute([$new_password_hash, $user['id']]);
        
        jsonResponse(true, ['message' => 'Password changed successfully']);
        
    } catch (Exception $e) {
        logError('Change password error: ' . $e->getMessage());
        jsonResponse(false, null, 'Failed to change password: ' . $e->getMessage(), 500);
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
        logError('Session verification error: ' . $e->getMessage());
        jsonResponse(false, null, 'Session verification failed: ' . $e->getMessage(), 500);
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
        
        // Get user data
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
        logError("Token verification error: " . $e->getMessage());
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
        logError("Get teacher sections error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to retrieve sections'
        ];
    }
}

function updateTeacherProfile($pdo, $data) {
    try {
        $user_id = $data['user_id'];
        
        // Get current user data
        $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, id_number FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }
        
        // Verify current password
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            return [
                'success' => false,
                'error' => 'Current password is incorrect'
            ];
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        $updates = [];
        $params = [];
        
        // Build update query for optional fields
        if (!empty($data['new_username']) && $data['new_username'] !== $user['username']) {
            // Check if new username already exists
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
            // Check if new ID number already exists
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
            $updates[] = "password_hash = ?, temp_password = 0";
            $params[] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }
        
        // Always update the required fields if they changed
        if ($data['full_name'] !== $user['full_name']) {
            $updates[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        
        if ($data['id_number'] !== $user['id_number']) {
            $updates[] = "id_number = ?";
            $params[] = $data['id_number'];
        }
        
        // Update user table if there are changes
        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $user['id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Handle section updates
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
        
        // Get updated user data
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
        logError("Update teacher profile error: " . $e->getMessage());
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

function uploadProfilePicture($pdo, $user_id) {
    try {
        if (!isset($_POST['image_data']) || empty($_POST['image_data'])) {
            return ['success' => false, 'error' => 'No image data provided'];
        }
        
        $image_data = $_POST['image_data'];
        
        // Validate base64 image
        if (strpos($image_data, 'data:image/') !== 0) {
            return ['success' => false, 'error' => 'Invalid image format'];
        }
        
        // Check if profile picture record exists
        $stmt = $pdo->prepare("SELECT id FROM user_profile_pictures WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE user_profile_pictures SET image_data = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$image_data, $user_id]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO user_profile_pictures (user_id, image_data, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $image_data]);
        }
        
        return ['success' => true, 'message' => 'Profile picture updated successfully'];
        
    } catch (Exception $e) {
        logError("Upload profile picture error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to upload profile picture'];
    }
}

function getProfilePicture($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT image_data FROM user_profile_pictures WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        if ($result && !empty($result['image_data'])) {
            return ['success' => true, 'image_data' => $result['image_data']];
        } else {
            return ['success' => false, 'error' => 'No profile picture found'];
        }
        
    } catch (Exception $e) {
        logError("Get profile picture error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to get profile picture'];
    }
}

function deleteProfilePicture($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM user_profile_pictures WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        return ['success' => true, 'message' => 'Profile picture deleted successfully'];
        
    } catch (Exception $e) {
        logError("Delete profile picture error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to delete profile picture'];
    }
}

function sendTeacherPasswordResetEmail($email, $full_name, $username, $tempPassword) {
    $to = $email;
    $subject = "Filibustero Teacher Portal - Password Reset";
    
    $message = "
    <html>
    <head>
        <title>Password Reset</title>
        <style>
            body { font-family: 'Inter', Arial, sans-serif; background-color: #faf8f5; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 30px auto; background-color: #fffcf7; border-radius: 16px; box-shadow: 0 10px 25px rgba(139, 107, 66, 0.08); overflow: hidden; border: 1px solid #e8dfd0; }
            .header { background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%); color: #fffcf7; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
            .header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.95; }
            .content { padding: 30px; color: #4a4235; line-height: 1.6; }
            .credentials-box { background: #faf6ef; border-left: 4px solid #d4a259; padding: 20px; margin: 20px 0; border-radius: 8px; }
            .credentials-box p { margin: 10px 0; }
            .password-display { font-size: 24px; color: #d4a259; font-weight: bold; letter-spacing: 3px; font-family: 'Courier New', monospace; background: #fffcf7; padding: 15px; border-radius: 5px; margin: 15px 0; text-align: center; border: 2px dashed #d4a259; }
            .warning-box { background: #fef9f0; border-left: 4px solid #b8863a; padding: 20px; margin: 20px 0; border-radius: 8px; color: #9c7a4d; }
            .warning-box h3 { margin-top: 0; color: #9c6d2e; font-size: 18px; }
            .warning-box ul { margin: 10px 0; padding-left: 20px; }
            .warning-box li { margin: 8px 0; }
            .steps { background: #f7f4ed; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .steps h3 { margin-top: 0; color: #3d3529; }
            .steps ol { margin: 10px 0; padding-left: 25px; }
            .steps li { margin: 10px 0; }
            .footer { background: #f5f1eb; padding: 20px; text-align: center; color: #7a6f5d; font-size: 12px; border-top: 1px solid #e8dfd0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏛️ Filibustero Teacher Portal</h1>
                <p>Password Reset Request</p>
            </div>
            
            <div class='content'>
                <p>Hello <strong>$full_name</strong>,</p>
                
                <p>We received a request to reset the password for your teacher account. A temporary password has been generated for you.</p>
                
                <div class='credentials-box'>
                    <p><strong>Username:</strong> <span style='color: #3d3529;'>$username</span></p>
                    <p><strong>Temporary Password:</strong></p>
                    <div class='password-display'>$tempPassword</div>
                </div>
                
                <div class='warning-box'>
                    <h3>⚠️ Important Security Notice:</h3>
                    <ul>
                        <li><strong>This temporary password expires in 1 hour</strong></li>
                        <li>Please log in immediately and change your password</li>
                        <li>Do not share this password with anyone</li>
                        <li>If you didn't request this reset, please contact support immediately</li>
                    </ul>
                </div>
                
                <div class='steps'>
                    <h3>How to Log In:</h3>
                    <ol>
                        <li>Go to the <strong>Filibustero Teacher Login</strong> page</li>
                        <li>Make sure <strong>Teacher</strong> tab is selected</li>
                        <li>Enter your username: <strong>$username</strong></li>
                        <li>Enter the temporary password shown above</li>
                        <li>Click '<strong>Login to Dashboard</strong>'</li>
                        <li><strong>Immediately change your password</strong> in the dashboard settings</li>
                    </ol>
                </div>
                
                <p style='margin-top: 30px;'>After logging in with the temporary password, you will be prompted to set a new secure password.</p>
                
                <p style='color: #7a6f5d; font-size: 14px; margin-top: 30px;'>If you did not request this password reset, please ignore this email or contact your administrator.</p>
            </div>
            
            <div class='footer'>
                <p><strong>This is an automated email from Filibustero Teacher Portal.</strong></p>
                <p>Please do not reply to this message. If you need assistance, please contact your system administrator.</p>
                <p style='margin-top: 15px;'>&copy; 2024 Filibustero. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Filibustero Teacher Portal <noreply@filibustero-teacher.com>" . "\r\n";
    $headers .= "Reply-To: support@filibustero-teacher.com" . "\r\n";
    
    try {
        $result = mail($to, $subject, $message, $headers);
        error_log("📧 Email send attempt to $to: " . ($result ? 'SUCCESS ✅' : 'FAILED ❌'));
        return $result;
    } catch (Exception $e) {
        error_log("💥 Email sending error: " . $e->getMessage());
        return false;
    }
}
?>