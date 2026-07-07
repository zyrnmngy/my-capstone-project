<?php
/**
 * Filibustero Game Progress Sync API
 * Dedicated file for handling game progress synchronization with database
 * Compatible with FilibusteroProgressBarDB.js plugin
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
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

// Log incoming request
error_log("=== PROGRESS SYNC REQUEST ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

// Clean any unexpected output
ob_clean();

// Get action from POST or GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Route to appropriate handler
switch ($action) {
    case 'sync_progress':
    case 'update_progress':
        handleUpdateProgress();
        break;
    case 'get_progress':
    case 'load_progress':
        handleGetProgress();
        break;
    case 'validate_user':
        handleValidateUser();
        break;
    case 'bulk_sync':
        handleBulkSync();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action. Supported: sync_progress, get_progress, validate_user, bulk_sync']);
        break;
}

/**
 * Update/Sync game progress to database
 */
function handleUpdateProgress() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    // Validate user exists
    if (!validateUserExists($userId)) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    // Extract progress data with defaults
    $progressData = [
        'coins' => intval($_POST['coins'] ?? 0),
        'score' => intval($_POST['score'] ?? 0),
        'current_stage' => intval($_POST['current_stage'] ?? 0),
        'completed_quests' => intval($_POST['completed_quests'] ?? 0),
        'map_changes' => intval($_POST['map_changes'] ?? 0),
        'coin_count' => intval($_POST['coin_count'] ?? 0),
        'collected_items' => intval($_POST['collected_items'] ?? 0),
        'playtime_seconds' => intval($_POST['playtime_seconds'] ?? 0)
    ];
    
    // Validate data ranges
    $progressData['current_stage'] = max(1, min(13, $progressData['current_stage']));
    $progressData['completed_quests'] = max(0, $progressData['completed_quests']);
    $progressData['coins'] = max(0, $progressData['coins']);
    $progressData['score'] = max(0, $progressData['score']);
    
    // Calculate progress percentage
    $totalQuests = 22; // Match your JavaScript plugin
    $progressPercentage = min(floor(($progressData['completed_quests'] / $totalQuests) * 100), 100);

    error_log("Calculating progress: {$progressData['completed_quests']} / $totalQuests = $progressPercentage%");
    
    // Log the sync attempt
    error_log("=== SYNCING PROGRESS FOR USER: $userId ===");
    error_log("Progress data: " . json_encode($progressData));
    error_log("Progress percentage: $progressPercentage%");
    
    try {
        // Update user_progress table
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
            $userId,
            $progressData['coins'],
            $progressData['score'],
            $progressData['current_stage'],
            $progressData['completed_quests'],
            $progressData['map_changes'],
            $progressData['coin_count'],
            $progressData['collected_items'],
            $progressData['playtime_seconds']
        ]);
        
        if ($result) {
            // Also update game_progress table if it exists
            updateGameProgressTable($userId, $progressData, $progressPercentage);
            
            // Update user's last activity
            updateUserActivity($userId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Progress synchronized successfully',
                'user_id' => $userId,
                'progress_percentage' => $progressPercentage,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            error_log("SUCCESS: Progress synced for user $userId");
        } else {
            throw new Exception('Failed to execute progress update query');
        }
        
    } catch (PDOException $e) {
        error_log("Database error in handleUpdateProgress: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database sync failed: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("General error in handleUpdateProgress: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Sync error: ' . $e->getMessage()]);
    }
}

/**
 * Get/Load progress data from database
 */
function handleGetProgress() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    // Validate user exists
    if (!validateUserExists($userId)) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    error_log("=== LOADING PROGRESS FOR USER: $userId ===");
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                user_id, coins, score, current_stage, completed_quests, 
                map_changes, coin_count, collected_items, playtime_seconds,
                created_at, updated_at
            FROM user_progress 
            WHERE user_id = ?  -- CRITICAL: Filter by user ID
        ");
        $stmt->execute([$userId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            $progressData = [
                'user_id' => intval($progress['user_id']), // Include user ID in response
                'coins' => intval($progress['coins']),
                'score' => intval($progress['score']),
                'current_stage' => intval($progress['current_stage']),
                'completed_quests' => intval($progress['completed_quests']),
                'map_changes' => intval($progress['map_changes']),
                'coin_count' => intval($progress['coin_count']),
                'collected_items' => intval($progress['collected_items']),
                'playtime_seconds' => intval($progress['playtime_seconds']),
                'last_updated' => $progress['updated_at']
            ];
            
            error_log("SUCCESS: Progress loaded for user $userId");
            error_log("Loaded data: " . json_encode($progressData));
            
            echo json_encode([
                'success' => true,
                'data' => $progressData, // Changed from 'progress_data' to 'data'
                'user_id' => $userId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } else {
            // No progress found - return defaults
            echo json_encode([
                'success' => true,
                'data' => null, // Changed from 'progress_data' to 'data'
                'user_id' => $userId,
                'message' => 'No existing progress found',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            error_log("No progress found for user $userId");
        }
        
    } catch (PDOException $e) {
        error_log("Database error in handleGetProgress: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to load progress: ' . $e->getMessage()]);
    }
}

/**
 * Validate if user exists and is active
 */
function handleValidateUser() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, full_name, user_type FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'user_type' => $user['user_type']
                ],
                'message' => 'User validated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in handleValidateUser: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'User validation failed']);
    }
}

/**
 * Handle bulk progress sync (multiple data points at once)
 */
function handleBulkSync() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? '';
    $progressUpdates = $_POST['progress_updates'] ?? '';
    
    if (empty($userId) || empty($progressUpdates)) {
        echo json_encode(['success' => false, 'error' => 'User ID and progress updates are required']);
        return;
    }
    
    // Decode JSON progress updates
    $updates = json_decode($progressUpdates, true);
    if (!$updates || !is_array($updates)) {
        echo json_encode(['success' => false, 'error' => 'Invalid progress updates format']);
        return;
    }
    
    if (!validateUserExists($userId)) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    error_log("=== BULK SYNC FOR USER: $userId ===");
    error_log("Updates count: " . count($updates));
    
    try {
        $pdo->beginTransaction();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($updates as $update) {
            try {
                // Process each update individually
                $result = processSingleProgressUpdate($userId, $update);
                if ($result) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                error_log("Error in bulk update item: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bulk sync completed',
            'user_id' => $userId,
            'successful_updates' => $successCount,
            'failed_updates' => $errorCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Bulk sync error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Bulk sync failed: ' . $e->getMessage()]);
    }
}

/**
 * Helper Functions
 */

function validateUserExists($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error validating user: " . $e->getMessage());
        return false;
    }
}

function updateGameProgressTable($userId, $progressData) {
    global $pdo;
    
    try {
        // Check if there's a player record for this user
        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
        $stmt->execute([$userId]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($player) {
            $playerId = $player['id'];
            
            $stmt = $pdo->prepare("
            INSERT INTO game_progress (
                player_id, score, coins, current_stage,
                completed_quests, last_played, updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                coins = VALUES(coins),
                current_stage = VALUES(current_stage),
                progress_percentage = FLOOR((VALUES(completed_quests) / 22.0) * 100)
                completed_quests = VALUES(completed_quests),
                last_played = NOW(),
                updated_at = NOW()
        ");
            
            $stmt->execute([
                $playerId,
                $progressData['score'],
                $progressData['coins'],
                $progressData['current_stage'],
                $progressData['completed_quests']
            ]);
            
            error_log("Updated game_progress table for player ID: $playerId");
        } else {
            // If no player record, try direct user_id approach
            $stmt = $pdo->prepare("
                INSERT INTO game_progress (
                    user_id, score, coins, current_stage, progress_percentage,
                    completed_quests, last_played, updated_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    score = VALUES(score),
                    coins = VALUES(coins),
                    current_stage = VALUES(current_stage),
                    progress_percentage = FLOOR((VALUES(completed_quests) / 22.0) * 100)
                    completed_quests = VALUES(completed_quests),
                    last_played = NOW(),
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $userId,
                $progressData['score'],
                $progressData['coins'],
                $progressData['current_stage'],
                $progressData['completed_quests']
            ]);
            
            error_log("Updated game_progress table for user ID: $userId");
        }
        
    } catch (PDOException $e) {
        error_log("Error updating game_progress table: " . $e->getMessage());
        // Don't fail the main operation if this secondary update fails
    }
}

function updateUserActivity($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error updating user activity: " . $e->getMessage());
    }
}

function createDefaultProgress($userId) {
    global $pdo;
    
    $defaultData = [
        'coins' => 0,
        'score' => 0,
        'current_stage' => 0,
        'completed_quests' => 0,
        'map_changes' => 0,
        'coin_count' => 0,
        'collected_items' => 0,
        'playtime_seconds' => 0
    ];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (
                user_id, coins, score, current_stage, completed_quests,
                map_changes, coin_count, collected_items, playtime_seconds,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $userId,
            $defaultData['coins'],
            $defaultData['score'],
            $defaultData['current_stage'],
            $defaultData['completed_quests'],
            $defaultData['map_changes'],
            $defaultData['coin_count'],
            $defaultData['collected_items'],
            $defaultData['playtime_seconds']
        ]);
        
        error_log("Created default progress record for user: $userId");
        
    } catch (PDOException $e) {
        error_log("Error creating default progress: " . $e->getMessage());
    }
    
    return $defaultData;
}

function processSingleProgressUpdate($userId, $updateData) {
    global $pdo;
    
    // Validate and sanitize update data
    $data = [
        'coins' => max(0, intval($updateData['coins'] ?? 0)),
        'score' => max(0, intval($updateData['score'] ?? 0)),
        'current_stage' => max(0, min(38, intval($updateData['current_stage'] ?? 0))),
        'completed_quests' => max(0, intval($updateData['completed_quests'] ?? 0)),
        'map_changes' => max(0, intval($updateData['map_changes'] ?? 0)),
        'coin_count' => max(0, intval($updateData['coin_count'] ?? 0)),
        'collected_items' => max(0, intval($updateData['collected_items'] ?? 0)),
        'playtime_seconds' => max(0, intval($updateData['playtime_seconds'] ?? 0))
    ];
    
    $stmt = $pdo->prepare("
        UPDATE user_progress SET 
            coins = ?, score = ?, current_stage = ?, completed_quests = ?,
            map_changes = ?, coin_count = ?, collected_items = ?, playtime_seconds = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");
    
    return $stmt->execute([
        $data['coins'], $data['score'], $data['current_stage'], $data['completed_quests'],
        $data['map_changes'], $data['coin_count'], $data['collected_items'], 
        $data['playtime_seconds'], $userId
    ]);
}

// Utility function to get current server time for client sync
function getCurrentServerTime() {
    return [
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
}

// Add a simple health check endpoint
if ($_GET['action'] === 'health_check') {
    echo json_encode([
        'success' => true,
        'message' => 'Progress sync service is running',
        'server_time' => getCurrentServerTime(),
        'version' => '1.0.0'
    ]);
    exit();
}

?>