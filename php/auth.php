<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

error_log("=== AUTH REQUEST RECEIVED ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET params: " . print_r($_GET, true));
error_log("POST data: " . print_r($_POST, true));

$action = $_POST['action'] ?? $_GET['action'] ?? '';
error_log("Action determined: " . $action);

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
    case 'save_game':
        handleSaveGame();
        break;
    case 'load_game':
        handleLoadGame();
        break;
    case 'list_saves':
        handleListSaves();
        break;
    case 'delete_save':
        handleDeleteSave();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function handleUpdateProgress() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? '';
    $coins = intval($_POST['coins'] ?? 0);
    $score = intval($_POST['score'] ?? 0);
    $currentStage = intval($_POST['current_stage'] ?? 0);
    $completedQuests = intval($_POST['completed_quests'] ?? 0);
    $mapChanges = intval($_POST['map_changes'] ?? 0);
    $coinCount = intval($_POST['coin_count'] ?? 0);
    $collectedItems = intval($_POST['collected_items'] ?? 0);
    $playtimeSeconds = intval($_POST['playtime_seconds'] ?? 0);
    
    $totalQuests = 38;
    $progressPercentage = min(floor(($completedQuests / $totalQuests) * 100), 100);
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    error_log("=== UPDATE PROGRESS REQUEST ===");
    error_log("User ID: $userId");
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
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
            updateGameProgressTable($userId, $coins, $score, $currentStage, $completedQuests, $progressPercentage);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Progress updated successfully',
                'progress_percentage' => $progressPercentage
            ]);
            
            error_log("SUCCESS: Progress updated for user $userId");
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update progress']);
        }
        
    } catch (PDOException $e) {
        error_log("Update progress error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateGameProgressTable($userId, $coins, $score, $currentStage, $completedQuests, $progressPercentage) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$userId]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($player) {
            $playerId = $player['id'];
            
            $stmt = $pdo->prepare("
                INSERT INTO game_progress (
                    player_id, score, coins, current_stage, progress_percentage, 
                    completed_quests, last_played, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    score = VALUES(score),
                    coins = VALUES(coins),
                    current_stage = VALUES(current_stage),
                    progress_percentage = VALUES(progress_percentage),
                    completed_quests = VALUES(completed_quests),
                    last_played = NOW(),
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $playerId, $score, $coins, $currentStage, 
                $progressPercentage, $completedQuests
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error updating game_progress table: " . $e->getMessage());
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
                'coins' => 0, 'score' => 0, 'current_stage' => 0, 'completed_quests' => 0,
                'map_changes' => 0, 'coin_count' => 0, 'collected_items' => 0, 'playtime_seconds' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Error getting user progress data: " . $e->getMessage());
        return [
            'coins' => 0, 'score' => 0, 'current_stage' => 0, 'completed_quests' => 0,
            'map_changes' => 0, 'coin_count' => 0, 'collected_items' => 0, 'playtime_seconds' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}

function updateUserProgressOnLogin($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE user_progress SET updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO user_progress (
                    user_id, coins, score, current_stage, completed_quests, 
                    map_changes, coin_count, collected_items, playtime_seconds,
                    created_at, updated_at
                ) VALUES (?, 0, 0, 1, 0, 0, 0, 0, 0, NOW(), NOW())
            ");
            $stmt->execute([$userId]);
        }
        
    } catch (PDOException $e) {
        error_log("Error updating user progress on login: " . $e->getMessage());
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
        
        if ($user['temp_password'] && $user['reset_expires'] && strtotime($user['reset_expires']) < time()) {
            echo json_encode(['success' => false, 'error' => 'Temporary password has expired. Please request a new one.']);
            return;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Incorrect password']);
            return;
        }
        
        $userId = $user['id'];
        
        updateUserProgressOnLogin($userId);
        $progressData = getUserProgressData($userId);
        
        $userData = [
            'id' => $userId,
            'id_number' => $user['id_number'], 
            'user_type' => $user['user_type'],
            'full_name' => $user['full_name'],
            'email' => $user['email'] ?? null, // ✅ ADDED
            'username' => $user['username'],
            'temp_password' => $user['temp_password'] ? true : false,
            'progress' => $progressData
        ];
        
        $redirectTo = 'Scene_GameMenu';
        
        if ($user['temp_password']) {
            $redirectTo = 'Scene_ChangePassword';
        }
        
        if ($user['user_type'] === 'student') {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
            $stmt->execute([$userId]);
            $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($studentData) {
                $userData = array_merge($userData, $studentData);
            }
        }
        
        if ($user['user_type'] === 'teacher') {
            $teacherCheck = $pdo->prepare("SELECT is_active FROM teachers WHERE user_id = ?");
            $teacherCheck->execute([$userId]);
            if ($teacherCheck->rowCount() > 0) {
                $teacherData = $teacherCheck->fetch(PDO::FETCH_ASSOC);
                if (!$teacherData['is_active']) {
                    throw new Exception('Your account is pending admin approval. Please wait for activation.');
                }
            }
        }
        
        $token = bin2hex(random_bytes(16));
        
        echo json_encode([
            'success' => true,
            'user' => $userData,
            'token' => $token,
            'redirect_to' => $redirectTo
        ]);
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Login exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleRegister() {
    global $pdo;
    
    $userType = $_POST['user_type'] ?? '';
    
    if ($userType === 'student') {
        registerStudent();
    } elseif ($userType === 'teacher') {
        registerTeacher();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid user type']);
    }
}

// ✅ UPDATED: Email-based password reset
function handleForgotPassword() {
    global $pdo;
    
    // ✅ CHANGED: Accept email instead of ID number
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Email address is required']);
        return;
    }
    
    // ✅ CHANGED: Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    try {
        // ✅ CHANGED: Search by email instead of ID number
        $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'No account found with this email address']);
            return;
        }
        
        // Generate temporary password
        $tempPassword = bin2hex(random_bytes(4));
        $tempPasswordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $resetExpires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        // Update user's password
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, temp_password = 1, reset_expires = ? WHERE id = ?");
        $stmt->execute([$tempPasswordHash, $resetExpires, $user['id']]);
        
        if ($stmt->rowCount() > 0) {
            // ✅ NEW: Send email with temporary password
            $emailSent = sendPasswordResetEmail($user, $tempPassword);
            
            if ($emailSent) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Password reset email sent successfully',
                    'email' => $email
                ]);
            } else {
                // Fallback: Still return success but log email failure
                error_log("Failed to send password reset email to: $email");
                echo json_encode([
                    'success' => true,
                    'message' => 'Password reset initiated',
                    'email' => $email,
                    'warning' => 'Email may not have been delivered'
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update password']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in handleForgotPassword: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    }
}

// ✅ NEW: Function to send password reset email
function sendPasswordResetEmail($user, $tempPassword) {
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

function registerStudent() {
    global $pdo;
    
    // ✅ UPDATED: Added email to required fields
    $requiredFields = ['id_number', 'full_name', 'email', 'username', 'password', 'section', 'year_level', 'rizal_professor'];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'error' => "All fields are required"]);
            return;
        }
    }
    
    if (!preg_match('/^\d{2}L-\d{4,5}$/', $_POST['id_number'])) {
        echo json_encode(['success' => false, 'error' => 'ID Number must be in XXL-XXXX format (e.g., 23L-4567)']);
        return;
    }
    
    // ✅ NEW: Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $_POST['username'])) {
        echo json_encode(['success' => false, 'error' => 'Username must be 3-20 characters and can only contain letters, numbers, and underscores']);
        return;
    }
    
    if (strlen($_POST['password']) < 6) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // ✅ UPDATED: Check email too
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id_number = ? OR email = ?");
        $stmt->execute([$_POST['username'], $_POST['id_number'], $_POST['email']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Username, ID number, or email already exists']);
            return;
        }
        
        // ✅ UPDATED: Insert with email
        $stmt = $pdo->prepare("
            INSERT INTO users (id_number, user_type, full_name, email, username, password_hash) 
            VALUES (?, 'student', ?, ?, ?, ?)
        ");
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $_POST['id_number'],
            $_POST['full_name'],
            $_POST['email'], // ✅ NEW
            $_POST['username'],
            $passwordHash
        ]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO students (user_id, section, year_level, rizal_professor)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $_POST['section'],
            $_POST['year_level'],
            $_POST['rizal_professor']
        ]);
        
        // ✅ NEW: Insert into players table with email
        $stmt = $pdo->prepare("
            INSERT INTO players (user_id, player_name, email, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$userId, $_POST['full_name'], $_POST['email']]);
        
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, coins, score, current_stage, completed_quests, 
                map_changes, coin_count, collected_items, playtime_seconds, created_at, updated_at)
            VALUES (?, 0, 0, 1, 0, 0, 0, 0, 0, NOW(), NOW())
        ");
        $stmt->execute([$userId]);
        
        // ✅ OPTIONAL: Send welcome email
        sendWelcomeEmail([
            'full_name' => $_POST['full_name'],
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'id_number' => $_POST['id_number']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'user_id' => $userId,
            'user' => [
                'id' => $userId,
                'user_type' => 'student',
                'id_number' => $_POST['id_number'],
                'full_name' => $_POST['full_name'],
                'email' => $_POST['email'], // ✅ NEW
                'username' => $_POST['username']
            ],
            'message' => 'Student registration successful'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Student registration error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

// ✅ NEW: Send welcome email
function sendWelcomeEmail($user) {
    $to = $user['email'];
    $subject = "Welcome to Filibustero Game!";
    
    $message = "
    <html>
    <head><title>Welcome to Filibustero!</title></head>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 5px;'>
            <h2 style='color: #4CAF50;'>Welcome, {$user['full_name']}!</h2>
            <p>Your account has been successfully created for Filibustero Game.</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 3px;'>
                <p style='margin: 5px 0;'><strong>Username:</strong> {$user['username']}</p>
                <p style='margin: 5px 0;'><strong>ID Number:</strong> {$user['id_number']}</p>
                <p style='margin: 5px 0;'><strong>Email:</strong> {$user['email']}</p>
            </div>
            
            <p>You can now login and start playing! Learn about Philippine history through an exciting game experience.</p>
            
            <p style='color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;'>
                This is an automated email. Please do not reply to this message.
            </p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Filibustero Game <noreply@filibustero-web.com>" . "\r\n";
    
    try {
        mail($to, $subject, $message, $headers);
    } catch (Exception $e) {
        error_log("Welcome email error: " . $e->getMessage());
    }
}

function registerTeacher() {
    global $pdo;
    
    $requiredFields = ['id_number', 'full_name', 'username', 'password', 'sections'];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'error' => "All fields are required"]);
            return;
        }
    }
    
    if (!preg_match('/^\d{2}L-\d{4,5}$/', $_POST['id_number'])) {
        echo json_encode(['success' => false, 'error' => 'ID Number must be in XXL-XXXX format (e.g., 23L-4567)']);
        return;
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $_POST['username'])) {
        echo json_encode(['success' => false, 'error' => 'Username must be 3-20 characters and can only contain letters, numbers, and underscores']);
        return;
    }
    
    if (strlen($_POST['password']) < 6) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        return;
    }
    
    $sections = is_array($_POST['sections']) ? $_POST['sections'] : explode(',', $_POST['sections']);
    if (empty($sections)) {
        echo json_encode(['success' => false, 'error' => 'At least one section must be selected']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id_number = ?");
        $stmt->execute([$_POST['username'], $_POST['id_number']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Username or ID number already exists']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO users (id_number, user_type, full_name, username, password_hash) 
            VALUES (?, 'teacher', ?, ?, ?)
        ");
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $_POST['id_number'],
            $_POST['full_name'],
            $_POST['username'],
            $passwordHash
        ]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO teachers (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("INSERT INTO teacher_sections (teacher_id, section) VALUES (?, ?)");
        foreach ($sections as $section) {
            $stmt->execute([$userId, trim($section)]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'user_id' => $userId,
            'sections' => $sections,
            'message' => 'Teacher registration successful'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Teacher registration error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
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
    
    if ($currentPassword === $newPassword) {
        echo json_encode(['success' => false, 'error' => 'New password must be different from current password']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        if ($user['temp_password'] && $user['reset_expires'] && strtotime($user['reset_expires']) < time()) {
            echo json_encode(['success' => false, 'error' => 'Temporary password has expired. Please request a new one.']);
            return;
        }
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
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
    global $pdo;
    
    error_log("handleEditAccount called");
    
    $input = file_get_contents('php://input');
    error_log("Raw input: " . $input);
    
    $data = json_decode($input, true);
    error_log("Decoded data: " . print_r($data, true));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        return;
    }
    
    $idNumber = $data['idNumber'] ?? '';
    $currentUsername = $data['currentUsername'] ?? '';
    $currentPassword = $data['temporaryPassword'] ?? '';
    $newUsername = $data['newUsername'] ?? '';
    $newPassword = $data['newPassword'] ?? '';
    $isTemporaryPasswordUpdate = $data['isTemporaryPasswordUpdate'] ?? false;
    
    if (empty($idNumber) || empty($currentUsername) || empty($currentPassword)) {
        echo json_encode(['success' => false, 'message' => 'ID Number, Current Username, and Current Password are required']);
        return;
    }
    
    if (!preg_match('/^\d{2}L-\d{4,5}$/', $idNumber)) {
        echo json_encode(['success' => false, 'message' => 'ID Number must be in XXL-XXXX format (e.g., 23L-4567)']);
        return;
    }
    
    if (!empty($newPassword) && strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, temp_password, reset_expires FROM users WHERE id_number = ?");
        $stmt->execute([$idNumber]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'No account found with this ID Number']);
            return;
        }
        
        if ($user['username'] !== $currentUsername) {
            echo json_encode(['success' => false, 'message' => 'Current username does not match records']);
            return;
        }
        
        if ($isTemporaryPasswordUpdate) {
            if (!$user['temp_password']) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No temporary password found for this account. Please use your regular password or request a new temporary password.'
                ]);
                return;
            }
            
            if ($user['reset_expires'] && strtotime($user['reset_expires']) < time()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Temporary password has expired. Please request a new temporary password through the forgot password option.'
                ]);
                return;
            }
            
            if (!password_verify($currentPassword, $user['password_hash'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'The current password does not match the temporary password that has been generated for your account!'
                ]);
                return;
            }
        } else {
            if (!password_verify($currentPassword, $user['password_hash'])) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                return;
            }
        }
        
        if (!empty($newUsername) && $newUsername !== $currentUsername) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$newUsername, $user['id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'New username already exists']);
                return;
            }
        }
        
        $updateFields = [];
        $updateParams = [];
        $changes = [];
        
        if (!empty($newUsername) && $newUsername !== $currentUsername) {
            $updateFields[] = 'username = ?';
            $updateParams[] = $newUsername;
            $changes[] = 'username';
        }
        
        if (!empty($newPassword)) {
            $updateFields[] = 'password_hash = ?';
            $updateFields[] = 'temp_password = 0';
            $updateFields[] = 'reset_expires = NULL';
            $updateParams[] = password_hash($newPassword, PASSWORD_DEFAULT);
            $changes[] = 'password';
        } else if ($isTemporaryPasswordUpdate && $user['temp_password']) {
            $updateFields[] = 'temp_password = 0';
            $updateFields[] = 'reset_expires = NULL';
            $changes[] = 'temporary password status cleared';
        }
        
        if (empty($updateFields)) {
            echo json_encode([
                'success' => true, 
                'message' => 'No changes were made (both fields were empty or same as current values)'
            ]);
            return;
        }
        
        $updateParams[] = $user['id'];
        $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute($updateParams);
        
        if ($result && $stmt->rowCount() > 0) {
            $message = 'Account updated successfully!';
            if (!empty($changes)) {
                $message .= ' Changed: ' . implode(', ', $changes);
            }
            
            if ($isTemporaryPasswordUpdate) {
                $message .= ' Your temporary password access has been updated.';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'updated_username' => $newUsername ?: $currentUsername,
                'temp_password_cleared' => $isTemporaryPasswordUpdate || !empty($newPassword)
            ]);
        } else if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'No changes were made (values were the same)',
                'updated_username' => $currentUsername
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update account']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in handleEditAccount: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
    }
}

function handleSaveGame() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? '';
    $saveSlot = $_POST['save_slot'] ?? 1;
    $saveData = $_POST['save_data'] ?? '';
    $saveInfo = $_POST['save_info'] ?? '';
    $saveTitle = $_POST['save_title'] ?? 'Save File ' . $saveSlot;
    $playtime = $_POST['playtime'] ?? 0;
    $timestamp = $_POST['timestamp'] ?? (time() * 1000);
    
    if (empty($userId) || empty($saveData)) {
        echo json_encode(['success' => false, 'error' => 'User ID and save data are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO game_saves (user_id, save_slot, save_data, save_info, save_title, playtime, timestamp, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                save_data = VALUES(save_data),
                save_info = VALUES(save_info),
                save_title = VALUES(save_title),
                playtime = VALUES(playtime),
                timestamp = VALUES(timestamp),
                updated_at = NOW()
        ");
        
        $result = $stmt->execute([$userId, $saveSlot, $saveData, $saveInfo, $saveTitle, $playtime, $timestamp]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Game saved successfully',
                'save_slot' => $saveSlot,
                'timestamp' => $timestamp
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save game']);
        }
        
    } catch (PDOException $e) {
        error_log("Save game error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleLoadGame() {
    global $pdo;
    
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
    $saveSlot = $_POST['save_slot'] ?? $_GET['save_slot'] ?? 1;
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT save_data, save_info, save_title, playtime, timestamp, updated_at 
            FROM game_saves 
            WHERE user_id = ? AND save_slot = ?
        ");
        $stmt->execute([$userId, $saveSlot]);
        $save = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($save) {
            echo json_encode([
                'success' => true,
                'save_data' => $save['save_data'],
                'save_info' => $save['save_info'],
                'save_title' => $save['save_title'],
                'playtime' => (int)$save['playtime'],
                'timestamp' => (int)$save['timestamp'],
                'updated_at' => $save['updated_at'],
                'server_timestamp' => time()
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No save found']);
        }
        
    } catch (PDOException $e) {
        error_log("Load game error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleListSaves() {
    global $pdo;
    
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT save_slot, save_info, save_title, playtime, timestamp, updated_at 
            FROM game_saves 
            WHERE user_id = ? 
            ORDER BY save_slot ASC
        ");
        $stmt->execute([$userId]);
        $saves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formattedSaves = array_map(function($save) {
            return [
                'save_slot' => (int)$save['save_slot'],
                'save_info' => $save['save_info'],
                'save_title' => $save['save_title'],
                'playtime' => (int)$save['playtime'],
                'timestamp' => (int)$save['timestamp'],
                'updated_at' => $save['updated_at']
            ];
        }, $saves);
        
        echo json_encode([
            'success' => true,
            'saves' => $formattedSaves,
            'count' => count($formattedSaves),
            'server_timestamp' => time()
        ]);
        
    } catch (PDOException $e) {
        error_log("List saves error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteSave() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? '';
    $saveSlot = $_POST['save_slot'] ?? '';
    
    if (empty($userId) || empty($saveSlot)) {
        echo json_encode(['success' => false, 'error' => 'User ID and save slot are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM game_saves WHERE user_id = ? AND save_slot = ?");
        $result = $stmt->execute([$userId, $saveSlot]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Save deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Save not found']);
        }
        
    } catch (PDOException $e) {
        error_log("Delete save error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>